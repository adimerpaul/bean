<?php

namespace App\Exports\Ventas;

use App\Exports\Comun\ContextoReporte;
use Illuminate\Support\Collection;

/**
 * Una fila por producto vendido en cada documento: es la hoja para armar tablas
 * dinámicas. Los precios son el snapshot guardado en venta_detalles.
 */
class HojaDetalle extends HojaDeVentas
{
    private const COLUMNA_ESTADO = 20;

    public function __construct(
        ContextoReporte $contexto,
        private readonly Collection $detalles,
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Detalle';
    }

    protected function subtitulo(): string
    {
        return 'DETALLE DE PRODUCTOS VENDIDOS - '.ucfirst($this->contexto->periodo())
            .'     |     Una fila por producto y documento; los totales no suman lo anulado';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => '#', 'ancho' => 5, 'formato' => 'entero'],
            ['titulo' => 'Nº venta', 'ancho' => 13, 'formato' => 'texto'],
            ['titulo' => 'Fecha y hora', 'ancho' => 17, 'formato' => 'fechahora'],
            ['titulo' => 'Día', 'ancho' => 11, 'formato' => 'texto'],
            ['titulo' => 'Hora', 'ancho' => 8, 'formato' => 'texto'],
            ['titulo' => 'Usuario', 'ancho' => 22, 'formato' => 'texto'],
            ['titulo' => 'Caja', 'ancho' => 9, 'formato' => 'texto'],
            ['titulo' => 'Código', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Producto', 'ancho' => 34, 'formato' => 'texto'],
            ['titulo' => 'Categoría', 'ancho' => 18, 'formato' => 'texto'],
            ['titulo' => 'Unidad', 'ancho' => 9, 'formato' => 'texto'],
            ['titulo' => 'Cantidad', 'ancho' => 11, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Precio venta', 'ancho' => 13, 'formato' => 'precio'],
            ['titulo' => 'Precio compra', 'ancho' => 13, 'formato' => 'precio'],
            ['titulo' => 'Subtotal', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Descuento', 'ancho' => 12, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Total', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Costo', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Ganancia', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Forma de pago', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Estado', 'ancho' => 13, 'formato' => 'texto'],
        ];
    }

    protected function filas(): array
    {
        return $this->detalles->values()->map(function ($detalle, $indice) {
            $costo = round((float) $detalle->cantidad * (float) $detalle->precio_compra, 2);

            return [
                $indice + 1,
                $detalle->numero,
                $this->fechaExcel($detalle->fecha),
                $this->diaSemana($detalle->fecha),
                $this->hora($detalle->fecha),
                $detalle->usuario_nombre,
                'CAJA '.($detalle->caja ?? 1),
                $detalle->codigo,
                $detalle->nombre,
                $detalle->categoria ?: '-',
                $detalle->unidad,
                (float) $detalle->cantidad,
                (float) $detalle->precio_venta,
                (float) $detalle->precio_compra,
                (float) $detalle->subtotal,
                (float) $detalle->descuento,
                (float) $detalle->total,
                $costo,
                round((float) $detalle->total - $costo, 2),
                $detalle->tipo_pago,
                $detalle->estado,
            ];
        })->all();
    }

    protected function filaTotales(array $columnas, array $filas): array
    {
        return $this->totalesDeCompletadas($columnas, $filas, self::COLUMNA_ESTADO);
    }

    protected function mensajeVacio(): string
    {
        return 'No se vendió ningún producto en los filtros seleccionados.';
    }
}
