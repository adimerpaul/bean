<?php

namespace App\Exports\Productos;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaBase;
use Illuminate\Support\Collection;

/**
 * Consolidado por categoría: existencias valorizadas + movimiento de compras y
 * ventas del periodo. La fila de totales funciona como resumen general.
 */
class HojaResumen extends HojaBase
{
    public function __construct(
        ContextoReporte $contexto,
        private readonly Collection $productos,
        private readonly Collection $compras,
        private readonly Collection $ventas,
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Resumen';
    }

    protected function subtitulo(): string
    {
        return 'RESUMEN POR CATEGORÍA - Inventario al '.$this->contexto->generado->format('d/m/Y H:i')
            .'     |     Movimientos: '.ucfirst($this->contexto->periodo());
    }

    protected function mensajeVacio(): string
    {
        return 'No hay información para los filtros seleccionados.';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => 'Categoría', 'ancho' => 26, 'formato' => 'texto'],
            ['titulo' => 'Productos', 'ancho' => 11, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Stock', 'ancho' => 12, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Valor a costo', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Valor a venta', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Cant. comprada', 'ancho' => 14, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Costo compras', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Cant. vendida', 'ancho' => 14, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Total ventas', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Ganancia', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => '% de ventas', 'ancho' => 12, 'formato' => 'porcentaje'],
        ];
    }

    protected function filas(): array
    {
        $acumulado = [];
        $sumar = function (string $categoria, array $valores) use (&$acumulado) {
            $categoria = $categoria !== '' ? $categoria : 'SIN CATEGORÍA';
            $acumulado[$categoria] ??= array_fill_keys(
                ['productos', 'stock', 'costo', 'venta', 'cantidad_compra', 'total_compra', 'cantidad_venta', 'total_venta', 'ganancia'], 0
            );
            foreach ($valores as $clave => $valor) {
                $acumulado[$categoria][$clave] += $valor;
            }
        };

        foreach ($this->productos as $producto) {
            $stock = (float) $producto->stock_inicial;
            $sumar((string) ($producto->categoriaRelacion?->nombre ?? $producto->categoria), [
                'productos' => 1,
                'stock' => $stock,
                'costo' => $stock * (float) $producto->precio_compra,
                'venta' => $stock * (float) $producto->precio_venta,
            ]);
        }

        foreach ($this->compras as $fila) {
            $producto = $this->productos->get($fila->producto_id);
            $sumar((string) ($producto?->categoriaRelacion?->nombre ?? $producto?->categoria), [
                'cantidad_compra' => (float) $fila->cantidad,
                'total_compra' => (float) $fila->total,
            ]);
        }

        foreach ($this->ventas as $fila) {
            $producto = $this->productos->get($fila->producto_id);
            $sumar((string) ($fila->categoria ?: ($producto?->categoriaRelacion?->nombre ?? $producto?->categoria)), [
                'cantidad_venta' => (float) $fila->cantidad,
                'total_venta' => (float) $fila->total,
                'ganancia' => (float) $fila->total - (float) $fila->costo,
            ]);
        }

        $totalVentas = array_sum(array_column($acumulado, 'total_venta'));

        $filas = collect($acumulado)->map(fn ($datos, $categoria) => [
            $categoria,
            $datos['productos'],
            round($datos['stock'], 3),
            round($datos['costo'], 2),
            round($datos['venta'], 2),
            round($datos['cantidad_compra'], 3),
            round($datos['total_compra'], 2),
            round($datos['cantidad_venta'], 3),
            round($datos['total_venta'], 2),
            round($datos['ganancia'], 2),
            $totalVentas > 0 ? round($datos['total_venta'] / $totalVentas * 100, 1) : null,
        ]);

        return $filas->sortByDesc(fn ($fila) => $fila[8])->values()->all();
    }
}
