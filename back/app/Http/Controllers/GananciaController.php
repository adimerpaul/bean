<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Control de ganancias sobre los snapshots de venta_detalles: la ganancia de cada línea es
 * (precio_venta - precio_compra) × cantidad - descuento prorrateado, igual que en el panel de inicio.
 * Sólo cuentan ventas COMPLETADAS y sin borrar.
 */
class GananciaController extends Controller
{
    private const GAIN = '((d.precio_venta - COALESCE(d.precio_compra, 0)) * d.cantidad) - d.descuento';

    private const COST = 'COALESCE(d.precio_compra, 0) * d.cantidad';

    /** Opciones de los filtros de la pantalla. */
    public function catalogos(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ganancias');

        return response()->json([
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre', 'color']),
            'productos' => Producto::orderBy('nombre')->get(['id', 'codigo', 'nombre', 'categoria_id', 'unidad']),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Resumen del rango: indicadores (con comparación contra el periodo anterior del mismo largo),
     * serie temporal de ganancia e ingresos, ranking por producto y por categoría.
     * Filtros: ?desde&hasta (por defecto el mes en curso), categorias[], productos[], user_id.
     */
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ganancias');
        [$from, $to] = $this->range($request, now()->startOfMonth());
        $granularity = $this->granularity($from, $to);

        $lines = fn ($a = null, $b = null) => $this->lines($request, $a ?? $from, $b ?? $to);
        $totals = $this->totals($lines());

        // Periodo anterior del mismo largo, para la variación de los indicadores.
        $seconds = $from->diffInSeconds($to) + 1;
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subSeconds($seconds - 1);
        $previous = $this->totals($lines($prevFrom, $prevTo));

        $products = $lines()
            ->selectRaw('d.producto_id, MAX(d.codigo) as codigo, MAX(d.nombre) as nombre, MAX(d.unidad) as unidad, MAX(d.foto) as foto,
                MAX(d.categoria) as categoria, SUM(d.cantidad) as cantidad, SUM(d.total) as ventas, SUM('.self::COST.') as costo,
                SUM(d.descuento) as descuento, SUM('.self::GAIN.') as ganancia, COUNT(DISTINCT d.venta_id) as num_ventas')
            ->groupBy('d.producto_id')->orderByDesc('ganancia')->get()
            ->map(fn ($row) => $this->withMargin($row));

        $category = "COALESCE(NULLIF(d.categoria, ''), 'SIN CATEGORÍA')";
        $categories = $lines()
            ->selectRaw("$category as nombre, SUM(d.cantidad) as cantidad, SUM(d.total) as ventas, SUM(".self::COST.') as costo,
                SUM('.self::GAIN.') as ganancia, COUNT(DISTINCT d.producto_id) as productos')
            ->groupBy(DB::raw($category))->orderByDesc('ganancia')->get()
            ->map(fn ($row) => $this->withMargin($row));

        return response()->json([
            'periodo' => ['desde' => $from->toDateTimeString(), 'hasta' => $to->toDateTimeString(), 'granularidad' => $granularity,
                'anterior_desde' => $prevFrom->toDateTimeString(), 'anterior_hasta' => $prevTo->toDateTimeString()],
            'indicadores' => $totals,
            'anterior' => $previous,
            'serie' => $this->series($lines(), $from, $to, $granularity),
            'productos' => $products,
            'categorias' => $categories,
        ]);
    }

    /**
     * Líneas vendidas detrás de una cifra: de un producto (?producto_id) o de un tramo del gráfico
     * (?desde&hasta con hora). Respeta los mismos filtros que el resumen y pagina las líneas.
     */
    public function ventas(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ganancias');
        [$from, $to] = $this->range($request, now()->startOfMonth());
        $granularity = $this->granularity($from, $to);
        $lines = function () use ($request, $from, $to) {
            $query = $this->lines($request, $from, $to);
            if ($productId = $request->integer('producto_id')) {
                $query->where('d.producto_id', $productId);
            }

            return $query;
        };

        $perPage = min(max((int) $request->input('per_page', 50), 1), 500);
        $rows = $lines()
            ->select('d.id', 'd.venta_id', 'v.numero', 'v.fecha', 'v.usuario_nombre', 'v.caja', 'v.tipo_pago',
                'd.producto_id', 'd.codigo', 'd.nombre', 'd.unidad', 'd.cantidad', 'd.precio_compra', 'd.precio_venta',
                'd.subtotal', 'd.descuento', 'd.total')
            ->selectRaw(self::GAIN.' as ganancia')
            ->orderByDesc('v.fecha')->orderByDesc('d.id')
            ->paginate($perPage);

        return response()->json([
            'resumen' => $this->totals($lines()),
            'serie' => $this->series($lines(), $from, $to, $granularity),
            'lineas' => $rows,
        ]);
    }

    /** Líneas de venta completadas del rango con los filtros de la pantalla. */
    private function lines(Request $request, Carbon $from, Carbon $to)
    {
        $query = DB::table('venta_detalles as d')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->where('v.estado', 'COMPLETADA')
            ->whereNull('v.deleted_at')->whereNull('d.deleted_at')
            ->whereBetween('v.fecha', [$from, $to]);

        // La categoría se toma del producto actual: es la que el usuario ve en el filtro.
        $categories = array_filter(array_map('intval', (array) $request->input('categorias', [])));
        if ($categories) {
            $query->whereIn('d.producto_id', DB::table('productos')->whereIn('categoria_id', $categories)->select('id'));
        }
        $products = array_filter(array_map('intval', (array) $request->input('productos', [])));
        if ($products) {
            $query->whereIn('d.producto_id', $products);
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('v.user_id', $userId);
        }

        return $query;
    }

    private function totals($query): array
    {
        $row = $query->selectRaw('COALESCE(SUM(d.total), 0) as ventas, COALESCE(SUM('.self::COST.'), 0) as costo,
            COALESCE(SUM(d.descuento), 0) as descuento, COALESCE(SUM('.self::GAIN.'), 0) as ganancia,
            COALESCE(SUM(d.cantidad), 0) as unidades, COUNT(DISTINCT d.venta_id) as cantidad_ventas,
            COUNT(DISTINCT d.producto_id) as productos')->first();

        $sales = (float) $row->ventas;
        $gain = (float) $row->ganancia;

        return [
            'ventas' => round($sales, 2), 'costo' => round((float) $row->costo, 2), 'descuento' => round((float) $row->descuento, 2),
            'ganancia' => round($gain, 2), 'margen' => $sales > 0 ? round($gain / $sales * 100, 2) : 0,
            'unidades' => round((float) $row->unidades, 3), 'cantidad_ventas' => (int) $row->cantidad_ventas,
            'productos' => (int) $row->productos,
            'ganancia_por_venta' => $row->cantidad_ventas ? round($gain / $row->cantidad_ventas, 2) : 0,
        ];
    }

    private function withMargin(object $row): object
    {
        foreach (['cantidad', 'ventas', 'costo', 'ganancia', 'descuento'] as $field) {
            if (isset($row->$field)) {
                $row->$field = round((float) $row->$field, $field === 'cantidad' ? 3 : 2);
            }
        }
        $row->margen = $row->ventas > 0 ? round($row->ganancia / $row->ventas * 100, 2) : 0;

        return $row;
    }

    /**
     * Rango pedido. Una fecha sola (YYYY-MM-DD) se extiende al día completo; con hora se usa tal cual,
     * que es como llega un tramo del gráfico. Faltantes: desde el inicio por defecto hasta hoy.
     */
    private function range(Request $request, Carbon $defaultFrom): array
    {
        $parse = function ($value, bool $end) {
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }
            try {
                $date = Carbon::parse($value);
            } catch (\Exception) {
                return null;
            }

            return strlen($value) <= 10 ? ($end ? $date->endOfDay() : $date->startOfDay()) : $date;
        };

        $from = $parse($request->query('desde'), false) ?? $defaultFrom->copy()->startOfDay();
        $to = $parse($request->query('hasta'), true) ?? now()->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function granularity(Carbon $from, Carbon $to): string
    {
        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        return $days <= 2 ? 'hora' : ($days <= 92 ? 'dia' : 'mes');
    }

    /** Serie con todos los tramos del rango (también los vacíos) y el intervalo exacto de cada uno. */
    private function series($query, Carbon $from, Carbon $to, string $granularity): array
    {
        $format = ['hora' => '%Y-%m-%d %H', 'mes' => '%Y-%m'][$granularity] ?? '%Y-%m-%d';
        $bucket = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('$format', v.fecha)"
            : "DATE_FORMAT(v.fecha, '$format')";

        $rows = $query->selectRaw("$bucket as periodo, SUM(d.total) as ventas, SUM(".self::COST.') as costo, SUM('.self::GAIN.') as ganancia, COUNT(DISTINCT d.venta_id) as cantidad')
            ->groupBy(DB::raw($bucket))->get()->keyBy('periodo');

        $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $serie = [];
        $cursor = $granularity === 'hora' ? $from->copy()->startOfHour() : ($granularity === 'mes' ? $from->copy()->startOfMonth() : $from->copy()->startOfDay());
        while ($cursor <= $to) {
            if ($granularity === 'hora') {
                [$key, $label, $end] = [$cursor->format('Y-m-d H'), $cursor->format('d/m H').':00', $cursor->copy()->endOfHour()];
            } elseif ($granularity === 'mes') {
                [$key, $label, $end] = [$cursor->format('Y-m'), $months[$cursor->month - 1].' '.$cursor->format('y'), $cursor->copy()->endOfMonth()];
            } else {
                [$key, $label, $end] = [$cursor->format('Y-m-d'), $cursor->format('d/m'), $cursor->copy()->endOfDay()];
            }
            $row = $rows[$key] ?? null;
            $serie[] = [
                'label' => $label,
                'desde' => $cursor->max($from)->toDateTimeString(), 'hasta' => $end->copy()->min($to)->toDateTimeString(),
                'ventas' => round((float) ($row->ventas ?? 0), 2), 'costo' => round((float) ($row->costo ?? 0), 2),
                'ganancia' => round((float) ($row->ganancia ?? 0), 2), 'cantidad' => (int) ($row->cantidad ?? 0),
            ];
            $cursor = $end->copy()->addSecond();
        }

        return $serie;
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
