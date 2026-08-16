<?php

namespace App\Http\Controllers;

use App\Exports\Productos\ContextoReporte;
use App\Exports\ProductosReporteExport;
use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Productos');
        $query = $this->filteredSortedQuery($request);

        $perPage = (int) $request->input('per_page', 20);

        return response()->json($query->paginate($perPage === 0 ? 500 : min(max($perPage, 1), 500)));
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeAction($request, 'Ver Productos');

        $productos = $this->filteredSortedQuery($request)->get()->keyBy('id');
        [$desde, $hasta] = $this->rangoFechas($request);
        // Con filtros de catálogo activos, los movimientos se limitan a esos productos.
        $ids = ($request->filled('q') || $request->integer('categoria_id')) ? $productos->keys()->all() : null;

        $export = new ProductosReporteExport(
            $this->contextoReporte($request, $desde, $hasta),
            $productos,
            $this->comprasPorProducto($desde, $hasta, $ids),
            $this->ventasPorProducto($desde, $hasta, $ids),
        );

        return Excel::download($export, 'reporte_productos_'.now()->format('Ymd_His').'.xlsx');
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function rangoFechas(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        return [
            $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : null,
            $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : null,
        ];
    }

    private function contextoReporte(Request $request, ?Carbon $desde, ?Carbon $hasta): ContextoReporte
    {
        $empresa = Configuracion::first();
        $filtros = [];
        if ($busqueda = trim((string) $request->input('q'))) {
            $filtros['Búsqueda'] = $busqueda;
        }
        if ($categoriaId = $request->integer('categoria_id')) {
            $filtros['Categoría'] = Categoria::find($categoriaId)?->nombre ?? 'ID '.$categoriaId;
        }

        return new ContextoReporte(
            empresa: $empresa?->nombre_empresa ?: 'Bean',
            nit: $empresa?->nit,
            usuario: $request->user()?->name ?? $request->user()?->username ?? '-',
            generado: now(),
            desde: $desde,
            hasta: $hasta,
            filtros: $filtros,
        );
    }

    private function comprasPorProducto(?Carbon $desde, ?Carbon $hasta, ?array $ids)
    {
        return DB::table('compra_detalles as d')
            ->join('compras as c', 'c.id', '=', 'd.compra_id')
            ->whereNull('c.deleted_at')
            ->where('c.estado', 'COMPLETADA')
            ->when($desde, fn ($q) => $q->where('c.fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('c.fecha', '<=', $hasta))
            ->when($ids !== null, fn ($q) => $q->whereIn('d.producto_id', $ids))
            ->groupBy('d.producto_id', 'd.codigo', 'd.nombre', 'd.unidad')
            ->selectRaw('d.producto_id, d.codigo, d.nombre, d.unidad,
                COUNT(DISTINCT d.compra_id) as documentos,
                SUM(d.cantidad) as cantidad,
                SUM(d.total) as total,
                MAX(c.fecha) as ultima')
            ->orderByRaw('SUM(d.total) desc')
            ->get();
    }

    private function ventasPorProducto(?Carbon $desde, ?Carbon $hasta, ?array $ids)
    {
        return DB::table('venta_detalles as d')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->whereNull('v.deleted_at')
            ->whereNull('d.deleted_at')
            ->where('v.estado', 'COMPLETADA')
            ->when($desde, fn ($q) => $q->where('v.fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('v.fecha', '<=', $hasta))
            ->when($ids !== null, fn ($q) => $q->whereIn('d.producto_id', $ids))
            ->groupBy('d.producto_id', 'd.codigo', 'd.nombre', 'd.unidad', 'd.categoria')
            ->selectRaw('d.producto_id, d.codigo, d.nombre, d.unidad, d.categoria,
                COUNT(DISTINCT d.venta_id) as documentos,
                SUM(d.cantidad) as cantidad,
                SUM(d.subtotal) as subtotal,
                SUM(d.descuento) as descuento,
                SUM(d.total) as total,
                SUM(d.cantidad * d.precio_compra) as costo,
                MAX(v.fecha) as ultima')
            ->orderByRaw('SUM(d.total) desc')
            ->get();
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeAction($request, 'Ver Productos');
        $productos = $this->filteredSortedQuery($request)->get();

        return Pdf::loadView('productos.reporte', compact('productos'))->setPaper('letter', 'landscape')
            ->download('productos_'.now()->format('Ymd_His').'.pdf');
    }

    private function filteredSortedQuery(Request $request)
    {
        $query = Producto::with('categoriaRelacion:id,nombre,color');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('codigo', 'like', "%{$search}%")
                ->orWhere('nombre', 'like', "%{$search}%")
                ->orWhere('codigo_barras', 'like', "%{$search}%")
                ->orWhere('categoria', 'like', "%{$search}%"));
        }
        if ($categoriaId = $request->integer('categoria_id')) {
            $query->where('categoria_id', $categoriaId);
        }

        [$column, $direction] = match ($request->input('orden')) {
            'nombre_desc' => ['nombre', 'desc'],
            'stock_desc' => ['stock_inicial', 'desc'],
            'stock_asc' => ['stock_inicial', 'asc'],
            'precio_venta_desc' => ['precio_venta', 'desc'],
            'precio_venta_asc' => ['precio_venta', 'asc'],
            'precio_compra_desc' => ['precio_compra', 'desc'],
            'precio_compra_asc' => ['precio_compra', 'asc'],
            'categoria_asc' => ['categoria', 'asc'],
            default => ['nombre', 'asc'],
        };

        return $query->orderBy($column, $direction)->orderBy('nombre');
    }

    public function catalogos(Request $request)
    {
        $this->authorizeAction($request, 'Ver Productos');

        return response()->json([
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre', 'color']),
            'unidades' => Producto::whereNotNull('unidad')->distinct()->orderBy('unidad')->pluck('unidad'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Productos');

        return response()->json(Producto::create($this->validatedData($request)), 201);
    }

    public function storeCategoria(Request $request)
    {
        $this->authorizeAction($request, 'Crear Productos');
        $data = $request->validate(['nombre' => ['required', 'string', 'max:100'], 'color' => ['nullable', 'string', 'max:30']]);
        $data['nombre'] = mb_strtoupper(trim($data['nombre']));

        return response()->json(Categoria::create($data), 201);
    }

    public function updateCategoria(Request $request, Categoria $categoria)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $data = $request->validate(['nombre' => ['required', 'string', 'max:100'], 'color' => ['nullable', 'string', 'max:30']]);
        $data['nombre'] = mb_strtoupper(trim($data['nombre']));
        $categoria->update($data);
        Producto::where('categoria_id', $categoria->id)->update(['categoria' => $categoria->nombre]);

        return response()->json($categoria->fresh());
    }

    public function destroyCategoria(Request $request, Categoria $categoria)
    {
        $this->authorizeAction($request, 'Eliminar Productos');
        abort_if($categoria->productos()->exists(), 422, 'No se puede eliminar una categoría que tiene productos');
        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada']);
    }

    public function updateBarcode(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $data = $request->validate([
            'codigo_barras' => ['nullable', 'string', 'max:100', Rule::unique('productos')->whereNull('deleted_at')->ignore($producto)],
        ]);
        $producto->update($data);

        return response()->json($producto->fresh());
    }

    public function update(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $producto->update($this->validatedData($request, $producto));

        return response()->json($producto->fresh());
    }

    public function destroy(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Eliminar Productos');
        $this->deletePhoto($producto);
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    private function validatedData(Request $request, ?Producto $producto = null): array
    {
        $puedeEditarStock = (bool) $request->user()?->hasPermissionTo('Editar Stock Inicial');
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('productos')->whereNull('deleted_at')->ignore($producto)],
            'codigo_barras' => ['nullable', 'string', 'max:100', Rule::unique('productos')->whereNull('deleted_at')->ignore($producto)],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'unidad' => ['required', 'string', 'max:20'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock_inicial' => [$puedeEditarStock ? 'required' : 'nullable', 'numeric', 'min:0', 'decimal:0,3'],
        ]);
        foreach (['codigo', 'nombre', 'categoria', 'unidad'] as $field) {
            $data[$field] = isset($data[$field]) && $data[$field] !== null
                ? mb_strtoupper(trim($data[$field])) : null;
        }
        if (! empty($data['categoria_id'])) {
            $data['categoria'] = Categoria::find($data['categoria_id'])?->nombre;
        }
        if (! $puedeEditarStock) {
            // Sin el permiso el stock queda intacto (o en cero al crear); sólo se mueve por compras y ventas.
            if ($producto) {
                unset($data['stock_inicial']);
            } else {
                $data['stock_inicial'] = 0;
            }
        }

        return $data;
    }

    public function uploadPhoto(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $request->validate(['foto' => ['required', 'image', 'max:8192']]);

        $file = $request->file('foto');

        return $this->savePhoto($producto, file_get_contents($file->getPathname()));
    }

    public function uploadPhotoFromUrl(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $data = $request->validate(['url' => ['required', 'url:http,https', 'max:2048']]);
        $host = parse_url($data['url'], PHP_URL_HOST);
        abort_unless($host && $this->isPublicHost($host), 422, 'La dirección de imagen no está permitida');

        $response = Http::timeout(12)->connectTimeout(5)
            ->withHeaders(['User-Agent' => 'BeanVentas/1.0'])
            ->get($data['url']);
        abort_unless($response->successful(), 422, 'No se pudo descargar la imagen');
        abort_unless(str_starts_with(strtolower($response->header('Content-Type', '')), 'image/'), 422, 'La dirección no corresponde a una imagen');
        abort_if(strlen($response->body()) > 8 * 1024 * 1024, 422, 'La imagen supera el máximo de 8 MB');

        return $this->savePhoto($producto, $response->body());
    }

    private function savePhoto(Producto $producto, string $contents)
    {
        $this->deletePhoto($producto);
        $image = imagecreatefromstring($contents);
        abort_unless($image, 422, 'No se pudo procesar la fotografía');

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 700 / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $output = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($output, 255, 255, 255);
        imagefill($output, 0, 0, $white);
        imagecopyresampled($output, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $directory = public_path('images/productos');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = "producto_{$producto->id}_".time().'.webp';
        imagewebp($output, "{$directory}/{$filename}", 85);
        imagedestroy($image);
        imagedestroy($output);

        $producto->update(['foto' => "productos/{$filename}"]);

        return response()->json($producto->fresh());
    }

    private function isPublicHost(string $host): bool
    {
        $addresses = gethostbynamel($host) ?: [];
        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    private function deletePhoto(Producto $producto): void
    {
        if ($producto->foto) {
            $path = public_path('images/'.$producto->foto);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
