<?php

namespace App\Exports\Productos;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaBase;
use Illuminate\Support\Collection;

class HojaCompras extends HojaBase
{
    /**
     * @param  Collection  $compras  Agregado por producto de compra_detalles.
     * @param  Collection  $productos  Productos del inventario indexados por id (categoría y stock actual).
     */
    public function __construct(
        ContextoReporte $contexto,
        private readonly Collection $compras,
        private readonly Collection $productos,
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Compras por producto';
    }

    protected function subtitulo(): string
    {
        return 'COMPRAS POR PRODUCTO - Periodo: '.ucfirst($this->contexto->periodo()).' (sólo compras completadas)';
    }

    protected function mensajeVacio(): string
    {
        return 'No hay compras registradas para los filtros seleccionados.';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => 'Código', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Producto', 'ancho' => 36, 'formato' => 'texto'],
            ['titulo' => 'Categoría', 'ancho' => 20, 'formato' => 'texto'],
            ['titulo' => 'Unidad', 'ancho' => 10, 'formato' => 'texto'],
            ['titulo' => 'Nº compras', 'ancho' => 11, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Cantidad comprada', 'ancho' => 14, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Costo total', 'ancho' => 16, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Costo promedio', 'ancho' => 14, 'formato' => 'precio'],
            ['titulo' => 'Última compra', 'ancho' => 14, 'formato' => 'fecha'],
            ['titulo' => 'Stock actual', 'ancho' => 12, 'formato' => 'cantidad'],
        ];
    }

    protected function filas(): array
    {
        return $this->compras->map(function ($fila) {
            $producto = $this->productos->get($fila->producto_id);
            $cantidad = (float) $fila->cantidad;
            $total = (float) $fila->total;

            return [
                $fila->codigo,
                $fila->nombre,
                $producto?->categoriaRelacion?->nombre ?? $producto?->categoria ?? '-',
                $fila->unidad,
                (int) $fila->documentos,
                $cantidad,
                $total,
                $cantidad > 0 ? round($total / $cantidad, 4) : null,
                $this->fechaExcel($fila->ultima),
                $producto ? (float) $producto->stock_inicial : null,
            ];
        })->all();
    }
}
