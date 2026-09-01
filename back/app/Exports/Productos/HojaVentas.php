<?php

namespace App\Exports\Productos;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaBase;
use Illuminate\Support\Collection;

class HojaVentas extends HojaBase
{
    /**
     * @param  Collection  $ventas  Agregado por producto de venta_detalles.
     * @param  Collection  $productos  Productos del inventario indexados por id (categoría y stock actual).
     */
    public function __construct(
        ContextoReporte $contexto,
        private readonly Collection $ventas,
        private readonly Collection $productos,
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Ventas por producto';
    }

    protected function subtitulo(): string
    {
        return 'VENTAS POR PRODUCTO - Periodo: '.ucfirst($this->contexto->periodo()).' (sólo ventas completadas)';
    }

    protected function mensajeVacio(): string
    {
        return 'No hay ventas registradas para los filtros seleccionados.';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => 'Código', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Producto', 'ancho' => 36, 'formato' => 'texto'],
            ['titulo' => 'Categoría', 'ancho' => 20, 'formato' => 'texto'],
            ['titulo' => 'Unidad', 'ancho' => 10, 'formato' => 'texto'],
            ['titulo' => 'Nº ventas', 'ancho' => 10, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Cantidad vendida', 'ancho' => 14, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Subtotal', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Descuento', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Total vendido', 'ancho' => 16, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Costo', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Ganancia', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => "Margen %\n(s/ venta)", 'ancho' => 11, 'formato' => 'porcentaje'],
            ['titulo' => 'Última venta', 'ancho' => 14, 'formato' => 'fecha'],
            ['titulo' => 'Stock actual', 'ancho' => 12, 'formato' => 'cantidad'],
        ];
    }

    protected function filas(): array
    {
        return $this->ventas->map(function ($fila) {
            $producto = $this->productos->get($fila->producto_id);
            $total = (float) $fila->total;
            $costo = (float) $fila->costo;
            $ganancia = round($total - $costo, 2);

            return [
                $fila->codigo,
                $fila->nombre,
                $fila->categoria ?: ($producto?->categoriaRelacion?->nombre ?? $producto?->categoria ?? '-'),
                $fila->unidad,
                (int) $fila->documentos,
                (float) $fila->cantidad,
                (float) $fila->subtotal,
                (float) $fila->descuento,
                $total,
                round($costo, 2),
                $ganancia,
                $total > 0 ? round($ganancia / $total * 100, 1) : null,
                $this->fechaExcel($fila->ultima),
                $producto ? (float) $producto->stock_inicial : null,
            ];
        })->all();
    }
}
