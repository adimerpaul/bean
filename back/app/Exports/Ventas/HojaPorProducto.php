<?php

namespace App\Exports\Ventas;

use App\Exports\Comun\ContextoReporte;
use Illuminate\Support\Collection;

/**
 * Qué se vendió y cuándo, acumulado por producto: cantidades, ganancia, primera
 * y última venta, y en columnas aparte lo que quedó anulado.
 */
class HojaPorProducto extends HojaDeVentas
{
    public function __construct(
        ContextoReporte $contexto,
        private readonly Collection $detalles,
        private readonly Collection $productos,
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Por producto';
    }

    protected function subtitulo(): string
    {
        return 'VENTAS POR PRODUCTO - '.ucfirst($this->contexto->periodo())
            .'     |     Ordenado por total vendido; lo anulado va en sus propias columnas';
    }

    protected function mensajeVacio(): string
    {
        return 'No se vendió ningún producto en los filtros seleccionados.';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => '#', 'ancho' => 5, 'formato' => 'entero'],
            ['titulo' => 'Código', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Producto', 'ancho' => 34, 'formato' => 'texto'],
            ['titulo' => 'Categoría', 'ancho' => 18, 'formato' => 'texto'],
            ['titulo' => 'Unidad', 'ancho' => 9, 'formato' => 'texto'],
            ['titulo' => 'Nº ventas', 'ancho' => 10, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Cantidad vendida', 'ancho' => 14, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Subtotal', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Descuento', 'ancho' => 12, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Total vendido', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Costo', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Ganancia', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => "Margen %\n(s/ venta)", 'ancho' => 11, 'formato' => 'porcentaje'],
            ['titulo' => "Precio promedio\nde venta", 'ancho' => 14, 'formato' => 'precio'],
            ['titulo' => '% del total', 'ancho' => 11, 'formato' => 'porcentaje'],
            ['titulo' => 'Primera venta', 'ancho' => 16, 'formato' => 'fechahora'],
            ['titulo' => 'Última venta', 'ancho' => 16, 'formato' => 'fechahora'],
            ['titulo' => 'Cant. anulada', 'ancho' => 12, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Total anulado', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => "Stock actual\n(hoy)", 'ancho' => 12, 'formato' => 'cantidad'],
        ];
    }

    protected function filas(): array
    {
        $grupos = $this->detalles->groupBy(fn ($d) => $d->producto_id ?? 'X'.$d->codigo);
        $totalGeneral = $this->detalles->filter(fn ($d) => $this->esCompletada($d))->sum(fn ($d) => (float) $d->total);

        return $grupos->map(function (Collection $lineas) use ($totalGeneral) {
            $validas = $lineas->filter(fn ($d) => $this->esCompletada($d));
            $anuladas = $lineas->reject(fn ($d) => $this->esCompletada($d));
            $primera = $lineas->first();
            $cantidad = round($validas->sum(fn ($d) => (float) $d->cantidad), 3);
            $total = round($validas->sum(fn ($d) => (float) $d->total), 2);
            $costo = round($validas->sum(fn ($d) => (float) $d->cantidad * (float) $d->precio_compra), 2);
            $fechas = $validas->pluck('fecha')->filter()->sort()->values();
            $producto = $primera->producto_id ? $this->productos->get($primera->producto_id) : null;

            return [
                $primera->codigo,
                $primera->nombre,
                $primera->categoria ?: ($producto?->categoriaRelacion?->nombre ?? '-'),
                $primera->unidad,
                $validas->pluck('venta_id')->unique()->count(),
                $cantidad,
                round($validas->sum(fn ($d) => (float) $d->subtotal), 2),
                round($validas->sum(fn ($d) => (float) $d->descuento), 2),
                $total,
                $costo,
                round($total - $costo, 2),
                $total > 0 ? round(($total - $costo) / $total * 100, 1) : null,
                $cantidad > 0 ? round($total / $cantidad, 4) : null,
                $totalGeneral > 0 ? round($total / $totalGeneral * 100, 1) : null,
                $this->fechaExcel($fechas->first()),
                $this->fechaExcel($fechas->last()),
                round($anuladas->sum(fn ($d) => (float) $d->cantidad), 3),
                round($anuladas->sum(fn ($d) => (float) $d->total), 2),
                $producto ? (float) $producto->stock_inicial : null,
            ];
        })
            ->sortByDesc(fn ($fila) => $fila[8])
            ->values()
            ->map(fn ($fila, $indice) => [$indice + 1, ...$fila])
            ->all();
    }
}
