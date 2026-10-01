<?php

namespace App\Http\Controllers;

use App\Exports\Comun\ContextoReporte;
use App\Exports\VentasReporteExport;
use App\Models\Configuracion;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $query = $this->filteredQuery($request)
            ->withCount('detalles')
            // Resumen corto de productos para la columna del listado: sólo lo que se pinta.
            ->with(['detalles' => fn ($q) => $q->select('id', 'venta_id', 'nombre', 'cantidad', 'unidad')])
            ->latest('fecha');

        $perPage = (int) $request->input('per_page', 50);

        return response()->json($query->paginate($perPage === 0 ? 500 : min(max($perPage, 1), 500)));
    }

    public function summary(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $query = $this->filteredQuery($request)->where('estado', 'COMPLETADA');

        return response()->json([
            'efectivo' => (clone $query)->sum('monto_efectivo'),
            'qr' => (clone $query)->sum('monto_qr'),
            'total' => (clone $query)->sum('total'),
            'descuento' => (clone $query)->sum('descuento'),
            'cantidad' => (clone $query)->count(),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Panel de inicio. El rango se elige con ?periodo=hoy|ayer|semana|mes|anio|rango (por defecto la semana)
     * y todos los indicadores, no sólo la serie, se calculan dentro de ese rango.
     * Con periodo=rango manda el usuario las fechas: ?desde=YYYY-MM-DD&hasta=YYYY-MM-DD.
     */
    public function dashboard(Request $request)
    {
        $this->authorizeAction($request, 'Ver Estadísticas');
        [$period, $from, $to, $granularity] = $this->dashboardRange((string) $request->query('periodo', 'semana'), $request);

        $sales = fn () => Venta::where('estado', 'COMPLETADA')->whereBetween('fecha', [$from, $to]);
        $details = fn () => DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'COMPLETADA')
            ->whereBetween('ventas.fecha', [$from, $to])
            ->whereNull('ventas.deleted_at')->whereNull('venta_detalles.deleted_at');

        $total = (float) $sales()->sum('total');
        $count = (int) $sales()->count();
        $items = (float) $details()->sum('venta_detalles.cantidad');
        $profit = (float) $details()
            ->selectRaw('COALESCE(SUM(((venta_detalles.precio_venta - venta_detalles.precio_compra) * venta_detalles.cantidad) - venta_detalles.descuento), 0) AS total')
            ->value('total');

        $bucket = $this->periodBucket($granularity);
        $rows = $sales()->selectRaw("$bucket as periodo, SUM(total) as total, COUNT(*) as cantidad")
            ->groupBy('periodo')->get()->keyBy('periodo');
        $serie = $this->dashboardSeries($from, $to, $granularity, $rows);

        $byUser = $sales()->selectRaw('usuario_nombre as nombre, SUM(total) as total')
            ->groupBy('usuario_nombre')->orderByDesc('total')->limit(8)->get();
        $payments = $sales()->selectRaw('tipo_pago as nombre, SUM(total) as total')
            ->groupBy('tipo_pago')->get();
        // Ganancia por fila del detalle: lo mismo que el indicador, pero agrupado.
        $gain = 'COALESCE(SUM(((venta_detalles.precio_venta - venta_detalles.precio_compra) * venta_detalles.cantidad) - venta_detalles.descuento), 0) as ganancia';
        $topProducts = $details()
            ->selectRaw("venta_detalles.producto_id, venta_detalles.nombre, venta_detalles.foto, SUM(venta_detalles.cantidad) as cantidad, SUM(venta_detalles.total) as total, $gain")
            ->groupBy('venta_detalles.producto_id', 'venta_detalles.nombre', 'venta_detalles.foto')
            ->orderByDesc('cantidad')->limit(8)->get();
        // Mismo ranking agrupado por categoría del snapshot; las ventas sin categoría se juntan en una sola línea.
        $category = "COALESCE(NULLIF(venta_detalles.categoria, ''), 'SIN CATEGORÍA')";
        $topCategories = $details()
            ->selectRaw("$category as nombre, SUM(venta_detalles.cantidad) as cantidad, SUM(venta_detalles.total) as total, $gain")
            ->groupBy(DB::raw($category))
            ->orderByDesc('cantidad')->limit(8)->get();

        return response()->json([
            'periodo' => ['clave' => $period, 'titulo' => $this->periodTitle($period), 'desde' => $from->toDateTimeString(), 'hasta' => $to->toDateTimeString(), 'granularidad' => $granularity],
            'indicadores' => ['ventas' => $total, 'ganancia' => $profit, 'productos' => $items, 'cantidad_ventas' => $count, 'ticket_promedio' => $count ? $total / $count : 0],
            'diario' => $serie, 'usuarios' => $byUser, 'pagos' => $payments,
            'productos_top' => $topProducts, 'categorias_top' => $topCategories,
        ]);
    }

    /** Rango del panel: [clave, desde, hasta, granularidad de la serie]. */
    private function dashboardRange(string $period, ?Request $request = null): array
    {
        if ($period === 'rango') {
            return $this->customRange($request);
        }

        return match ($period) {
            'hoy' => ['hoy', now()->startOfDay(), now()->endOfDay(), 'hora'],
            'ayer' => ['ayer', now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), 'hora'],
            'mes' => ['mes', now()->subDays(29)->startOfDay(), now()->endOfDay(), 'dia'],
            'anio' => ['anio', now()->subMonths(11)->startOfMonth(), now()->endOfDay(), 'mes'],
            default => ['semana', now()->subDays(6)->startOfDay(), now()->endOfDay(), 'dia'],
        };
    }

    /**
     * Rango elegido a mano. Fechas inválidas o faltantes caen en hoy, se ordenan si vienen
     * al revés y la granularidad de la serie se decide por el largo del rango.
     */
    private function customRange(?Request $request): array
    {
        $parse = function ($value) {
            try {
                return $value ? Carbon::parse((string) $value) : null;
            } catch (\Exception) {
                return null;
            }
        };

        $from = $parse($request?->query('desde')) ?? now();
        $to = $parse($request?->query('hasta')) ?? $from->copy();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $days = $from->diffInDays($to) + 1;
        $granularity = $days <= 2 ? 'hora' : ($days <= 92 ? 'dia' : 'mes');

        return ['rango', $from, $to, $granularity];
    }

    private function periodTitle(string $period): string
    {
        return ['hoy' => 'Hoy', 'ayer' => 'Ayer', 'mes' => 'Últimos 30 días', 'anio' => 'Últimos 12 meses', 'rango' => 'Rango elegido'][$period] ?? 'Últimos 7 días';
    }

    /** Agrupación por hora/día/mes; MySQL y el SQLite de los tests usan los mismos tokens de formato. */
    private function periodBucket(string $granularity): string
    {
        $format = ['hora' => '%Y-%m-%d %H', 'mes' => '%Y-%m'][$granularity] ?? '%Y-%m-%d';

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('$format', fecha)"
            : "DATE_FORMAT(fecha, '$format')";
    }

    /** Rellena los huecos del rango para que la serie no salte periodos sin ventas. */
    private function dashboardSeries($from, $to, string $granularity, $rows)
    {
        $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $serie = [];
        $cursor = $from->copy();
        while ($cursor <= $to) {
            if ($granularity === 'hora') {
                $key = $cursor->format('Y-m-d H');
                $label = $cursor->format('H').':00';
                $cursor->addHour();
            } elseif ($granularity === 'mes') {
                $key = $cursor->format('Y-m');
                $label = $months[$cursor->month - 1];
                $cursor->addMonth();
            } else {
                $key = $cursor->format('Y-m-d');
                $label = $cursor->format('d/m');
                $cursor->addDay();
            }
            $row = $rows[$key] ?? null;
            $serie[] = ['label' => $label, 'total' => (float) ($row->total ?? 0), 'cantidad' => (int) ($row->cantidad ?? 0)];
        }

        return $serie;
    }

    /**
     * Reporte de ventas en varias hojas con los mismos filtros de la pantalla.
     * Incluye completadas y anuladas: cada hoja separa lo anulado en columnas
     * propias para que los totales sigan siendo el ingreso real del periodo.
     */
    public function exportExcel(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');

        $ventas = $this->filteredQuery($request)->orderBy('fecha')->get();
        $detalles = $this->detallesDelFiltro($request);
        $productos = Producto::with('categoriaRelacion:id,nombre')
            ->whereIn('id', $detalles->pluck('producto_id')->filter()->unique())
            ->get()->keyBy('id');

        $export = new VentasReporteExport(
            $this->contextoReporte($request),
            $ventas,
            $detalles,
            $this->lotesDelFiltro($request),
            $productos,
        );

        return Excel::download($export, 'reporte_ventas_'.now()->format('Ymd_His').'.xlsx');
    }

    /** Líneas de venta del filtro, con los datos de cabecera que necesitan las hojas. */
    private function detallesDelFiltro(Request $request)
    {
        return DB::table('venta_detalles as d')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->whereNull('d.deleted_at')
            ->whereIn('d.venta_id', $this->filteredQuery($request)->select('id'))
            ->orderBy('v.fecha')->orderBy('d.id')
            ->select('d.id', 'd.venta_id', 'd.producto_id', 'd.codigo', 'd.nombre', 'd.categoria', 'd.unidad',
                'd.precio_compra', 'd.precio_venta', 'd.cantidad', 'd.subtotal', 'd.descuento', 'd.total',
                'v.numero', 'v.fecha', 'v.estado', 'v.usuario_nombre', 'v.caja', 'v.tipo_pago')
            ->get();
    }

    /** Lotes consumidos por esas ventas: de ahí sale qué vencimiento tenía lo vendido. */
    private function lotesDelFiltro(Request $request)
    {
        return DB::table('venta_detalle_lotes as vdl')
            ->join('venta_detalles as d', 'd.id', '=', 'vdl.venta_detalle_id')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->join('lotes as l', 'l.id', '=', 'vdl.lote_id')
            ->whereNull('d.deleted_at')
            ->whereIn('d.venta_id', $this->filteredQuery($request)->select('id'))
            ->orderBy('v.fecha')
            ->select('v.numero', 'v.fecha', 'v.estado', 'v.usuario_nombre', 'd.codigo', 'd.nombre', 'd.unidad',
                'd.precio_compra', 'l.lote', 'l.fecha_vencimiento', 'vdl.cantidad')
            ->get();
    }

    /** Cabecera del reporte: empresa, quién exporta, periodo y filtros de la pantalla. */
    private function contextoReporte(Request $request): ContextoReporte
    {
        $empresa = Configuracion::first();
        $filtros = [];
        if ($busqueda = trim((string) $request->input('q'))) {
            $filtros['Búsqueda'] = $busqueda;
        }
        if ($userId = $request->integer('user_id')) {
            $filtros['Usuario'] = User::find($userId)?->name ?? 'ID '.$userId;
        }
        if ($caja = $request->integer('caja')) {
            $filtros['Caja'] = 'CAJA '.$caja;
        }
        $desde = $request->input('hora_desde');
        $hasta = $request->input('hora_hasta');
        if ($desde || $hasta) {
            $filtros['Horario'] = ($desde ?: '00:00').' a '.($hasta ?: '23:59');
        }

        return new ContextoReporte(
            empresa: $empresa?->nombre_empresa ?: 'Bean',
            nit: $empresa?->nit,
            usuario: $request->user()?->name ?? $request->user()?->username ?? '-',
            generado: now(),
            desde: $request->date('desde')?->startOfDay(),
            hasta: $request->date('hasta')?->endOfDay(),
            filtros: $filtros,
        );
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $ventas = $this->filteredQuery($request)->latest('fecha')->get();
        $valid = $ventas->where('estado', 'COMPLETADA');
        $resumen = ['efectivo' => $valid->sum('monto_efectivo'), 'qr' => $valid->sum('monto_qr'), 'total' => $valid->sum('total')];

        return Pdf::loadView('ventas.reporte', compact('ventas', 'resumen'))->setPaper('letter', 'landscape')
            ->download('ventas_'.now()->format('Ymd_His').'.pdf');
    }

    public function show(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, 'Ver Ventas');

        return response()->json($venta->load('detalles'));
    }

    /**
     * Antes de exportar las ventas offline se pregunta cuáles ya existen: así se
     * vinculan (en vez de registrarse otra vez) las que llegaron al servidor aunque
     * la respuesta se haya perdido.
     */
    public function verificarOffline(Request $request)
    {
        $this->authorizeAction($request, ['Crear Ventas', 'Crear Ventas Offline']);
        $data = $request->validate([
            'uuids' => ['required', 'array', 'min:1', 'max:200'],
            'uuids.*' => ['required', 'uuid'],
        ]);

        $ventas = Venta::whereIn('uuid', $data['uuids'])
            ->get(['id', 'uuid', 'numero', 'fecha', 'total', 'estado']);

        return response()->json(['registradas' => $ventas->keyBy('uuid')]);
    }

    public function store(Request $request)
    {
        // Con uuid es el envío de una venta hecha sin conexión: basta el permiso offline.
        $this->authorizeAction($request, $request->filled('uuid') ? ['Crear Ventas', 'Crear Ventas Offline'] : 'Crear Ventas');
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'fecha_offline' => ['nullable', 'date'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'caja' => ['nullable', 'integer', 'between:1,5'],
            'tipo_pago' => ['required', 'in:EFECTIVO,QR,COMBINADO'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'detalles.*.precio_venta' => ['required', 'numeric', 'min:0'],
        ]);

        // Reenvío de una venta offline que ya llegó: se devuelve la registrada, no se duplica.
        if ($registrada = $this->ventaOfflineRegistrada($data['uuid'] ?? null)) {
            return response()->json($registrada->load('detalles')->toArray() + ['duplicada' => true]);
        }

        try {
            $venta = $this->registrarVenta($request, $data);
        } catch (UniqueConstraintViolationException $e) {
            // Dos envíos simultáneos del mismo uuid: gana el primero.
            $registrada = $this->ventaOfflineRegistrada($data['uuid'] ?? null);
            if (! $registrada) {
                throw $e;
            }

            return response()->json($registrada->load('detalles')->toArray() + ['duplicada' => true]);
        }

        return response()->json($venta->load('detalles'), 201);
    }

    private function ventaOfflineRegistrada(?string $uuid): ?Venta
    {
        return $uuid ? Venta::where('uuid', $uuid)->first() : null;
    }

    /** Hora del cobro según el equipo que guardó la venta offline. */
    private function fechaOffline(?string $fecha): ?Carbon
    {
        return $fecha ? Carbon::parse($fecha)->setTimezone(config('app.timezone')) : null;
    }

    /**
     * La venta se fecha con la hora del cobro salvo que el reloj del equipo esté
     * adelantado o atrasado más de 30 días: ahí se usa la hora de llegada.
     */
    private function fechaVenta(?Carbon $offline): Carbon
    {
        return ! $offline || $offline->isFuture() || $offline->lt(now()->subDays(30)) ? now() : $offline;
    }

    private function registrarVenta(Request $request, array $data): Venta
    {
        $puedeCambiarPrecio = (bool) $request->user()->hasPermissionTo('Modificar Precio en Venta');

        return DB::transaction(function () use ($request, $data, $puedeCambiarPrecio) {
            $items = [];
            $subtotal = 0;
            $requestedByProduct = [];
            foreach ($data['detalles'] as $detail) {
                $product = Producto::lockForUpdate()->findOrFail($detail['producto_id']);
                $quantity = round((float) $detail['cantidad'], 3);
                $requestedByProduct[$product->id] = round(($requestedByProduct[$product->id] ?? 0) + $quantity, 3);
                abort_if((float) $product->stock_inicial + 0.0001 < $requestedByProduct[$product->id], 422, "Stock insuficiente para {$product->nombre}");
                $salePrice = round((float) $detail['precio_venta'], 4);
                // Sin el permiso el precio siempre es el del catálogo; el frontend lo bloquea, esto cubre el envío directo a la API.
                abort_if(! $puedeCambiarPrecio && abs($salePrice - (float) $product->precio_venta) > 0.0001, 403,
                    "No tiene permiso para modificar el precio de venta de {$product->nombre}");
                $lineSubtotal = round($salePrice * $quantity, 2);
                $subtotal += $lineSubtotal;
                $items[] = [$product, $quantity, $salePrice, $lineSubtotal];
            }

            $discount = round((float) ($data['descuento'] ?? 0), 2);
            abort_if($discount > $subtotal, 422, 'El descuento no puede superar el subtotal');
            $total = round($subtotal - $discount, 2);
            $cash = $data['tipo_pago'] === 'EFECTIVO' ? $total : round((float) ($data['monto_efectivo'] ?? 0), 2);
            $qr = $data['tipo_pago'] === 'QR' ? $total : round((float) ($data['monto_qr'] ?? 0), 2);
            abort_if(abs(($cash + $qr) - $total) > 0.009, 422, 'Los montos de efectivo y QR deben sumar el total de la venta');

            $offlineDate = $this->fechaOffline($data['fecha_offline'] ?? null);
            $sale = Venta::create([
                'uuid' => $data['uuid'] ?? null,
                'user_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name,
                'caja' => (int) ($data['caja'] ?? 1),
                'subtotal' => $subtotal,
                'descuento' => $discount,
                'total' => $total,
                'tipo_pago' => $data['tipo_pago'],
                'monto_efectivo' => $cash,
                'monto_qr' => $qr,
                'estado' => 'COMPLETADA',
                'observacion' => $data['observacion'] ?? null,
                'fecha' => $this->fechaVenta($offlineDate),
                'fecha_offline' => $offlineDate,
            ]);
            $sale->update(['numero' => 'V-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT)]);

            $allocated = 0;
            foreach ($items as $index => [$product, $quantity, $salePrice, $lineSubtotal]) {
                $lineDiscount = $index === array_key_last($items)
                    ? $discount - $allocated
                    : round($discount * ($lineSubtotal / $subtotal), 2);
                $allocated += $lineDiscount;
                $saleDetail = $sale->detalles()->create([
                    'producto_id' => $product->id,
                    'codigo' => $product->codigo,
                    'codigo_barras' => $product->codigo_barras,
                    'nombre' => $product->nombre,
                    'categoria' => $product->categoria,
                    'unidad' => $product->unidad,
                    'foto' => $product->foto,
                    'precio_compra' => $product->precio_compra,
                    'precio_venta' => $salePrice,
                    'cantidad' => $quantity,
                    'subtotal' => $lineSubtotal,
                    'descuento' => $lineDiscount,
                    'total' => $lineSubtotal - $lineDiscount,
                ]);
                $product->decrement('stock_inicial', $quantity);
                $remaining = $quantity;
                $lots = Lote::where('producto_id', $product->id)
                    ->where('cantidad_disponible', '>', 0)
                    ->orderByRaw('fecha_vencimiento IS NULL')
                    ->orderBy('fecha_vencimiento')->orderBy('id')->lockForUpdate()->get();
                foreach ($lots as $lot) {
                    if ($remaining <= 0.0001) {
                        break;
                    }
                    $taken = min($remaining, (float) $lot->cantidad_disponible);
                    $lot->decrement('cantidad_disponible', $taken);
                    DB::table('venta_detalle_lotes')->insert([
                        'venta_detalle_id' => $saleDetail->id, 'lote_id' => $lot->id,
                        'cantidad' => $taken, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $remaining = round($remaining - $taken, 3);
                }
            }

            return $sale;
        });
    }

    private function filteredQuery(Request $request)
    {
        $query = Venta::query();
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$search}%")
                ->orWhere('usuario_nombre', 'like', "%{$search}%")
                ->orWhere('estado', 'like', "%{$search}%"));
        }
        if ($from = $request->date('desde')) {
            $query->whereDate('fecha', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('fecha', '<=', $to);
        }
        $timeFrom = trim((string) $request->input('hora_desde'));
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $timeFrom)) {
            $query->whereTime('fecha', '>=', $timeFrom.':00');
        }
        $timeTo = trim((string) $request->input('hora_hasta'));
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $timeTo)) {
            $query->whereTime('fecha', '<=', $timeTo.':59');
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($caja = $request->integer('caja')) {
            $query->where('caja', $caja);
        }

        return $query;
    }

    public function cancel(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, 'Anular Ventas');
        abort_if($venta->estado === 'ANULADA', 422, 'La venta ya está anulada');

        DB::transaction(function () use ($venta) {
            foreach ($venta->detalles as $detail) {
                Producto::whereKey($detail->producto_id)->increment('stock_inicial', $detail->cantidad);
                $allocations = DB::table('venta_detalle_lotes')->where('venta_detalle_id', $detail->id)->get();
                foreach ($allocations as $allocation) {
                    Lote::whereKey($allocation->lote_id)->increment('cantidad_disponible', $allocation->cantidad);
                }
            }
            $venta->update(['estado' => 'ANULADA']);
        });

        return response()->json($venta->fresh());
    }

    /** Con varios permisos basta tener cualquiera de ellos. */
    private function authorizeAction(Request $request, string|array $permission): void
    {
        abort_unless($request->user()?->hasAnyPermission((array) $permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
