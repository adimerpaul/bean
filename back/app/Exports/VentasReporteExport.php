<?php

namespace App\Exports;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Ventas\HojaDetalle;
use App\Exports\Ventas\HojaLotes;
use App\Exports\Ventas\HojaPorDia;
use App\Exports\Ventas\HojaPorHora;
use App\Exports\Ventas\HojaPorProducto;
use App\Exports\Ventas\HojaPorUsuario;
use App\Exports\Ventas\HojaResumen;
use App\Exports\Ventas\HojaVentas;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reporte de ventas del periodo filtrado, en ocho hojas: resumen por caja y
 * forma de pago, documentos, detalle línea por línea, agregados por producto,
 * por usuario (quién vendió), por día y por hora (cuándo se vendió) y los lotes
 * consumidos con su fecha de vencimiento.
 *
 * Todas las hojas reciben las mismas ventas —completadas y anuladas— y separan
 * lo anulado en columnas propias para que los totales sigan siendo el ingreso real.
 */
class VentasReporteExport implements WithMultipleSheets
{
    /** @var Collection<int, array> Agregados por venta: ítems, unidades, costo. */
    private readonly Collection $porVenta;

    /**
     * @param  Collection  $ventas  Ventas del filtro (modelos Venta), ordenadas por fecha.
     * @param  Collection  $detalles  venta_detalles del filtro con los datos de su venta.
     * @param  Collection  $lotes  Asignaciones de venta_detalle_lotes con lote y vencimiento.
     * @param  Collection  $productos  Productos involucrados, indexados por id (stock actual).
     */
    public function __construct(
        private readonly ContextoReporte $contexto,
        private readonly Collection $ventas,
        private readonly Collection $detalles,
        private readonly Collection $lotes,
        private readonly Collection $productos,
    ) {
        $this->porVenta = $detalles->groupBy('venta_id')->map(fn (Collection $lineas) => [
            'items' => $lineas->count(),
            'unidades' => round($lineas->sum(fn ($d) => (float) $d->cantidad), 3),
            'costo' => round($lineas->sum(fn ($d) => (float) $d->cantidad * (float) $d->precio_compra), 2),
        ]);
    }

    public function sheets(): array
    {
        return [
            new HojaResumen($this->contexto, $this->ventas, $this->porVenta),
            new HojaVentas($this->contexto, $this->ventas, $this->porVenta),
            new HojaDetalle($this->contexto, $this->detalles),
            new HojaPorProducto($this->contexto, $this->detalles, $this->productos),
            new HojaPorUsuario($this->contexto, $this->ventas, $this->porVenta),
            new HojaPorDia($this->contexto, $this->ventas, $this->porVenta),
            new HojaPorHora($this->contexto, $this->ventas, $this->porVenta),
            new HojaLotes($this->contexto, $this->lotes),
        ];
    }
}
