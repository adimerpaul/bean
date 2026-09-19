<?php

namespace App\Exports\Ventas;

use App\Exports\Comun\ContextoReporte;
use Illuminate\Support\Collection;

/**
 * Consolidado por caja y forma de pago. Lo anulado va en columnas propias, así
 * que la fila de totales es el ingreso real del periodo.
 */
class HojaResumen extends HojaDeVentas
{
    public function __construct(
        ContextoReporte $contexto,
        private readonly Collection $ventas,
        private readonly Collection $porVenta,
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Resumen';
    }

    protected function subtitulo(): string
    {
        $completadas = $this->ventas->filter(fn ($v) => $this->esCompletada($v));
        $anuladas = $this->ventas->count() - $completadas->count();

        return 'RESUMEN DE VENTAS - '.ucfirst($this->contexto->periodo())
            .'     |     '.$completadas->count().' completadas por Bs '.number_format($completadas->sum(fn ($v) => (float) $v->total), 2)
            .'     |     '.$anuladas.' anuladas';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => 'Caja', 'ancho' => 10, 'formato' => 'texto'],
            ['titulo' => 'Forma de pago', 'ancho' => 15, 'formato' => 'texto'],
            ['titulo' => 'Nº ventas', 'ancho' => 10, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Productos', 'ancho' => 11, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Subtotal', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Descuento', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Total cobrado', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Efectivo', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'QR', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Costo', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Ganancia', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => "Margen %\n(s/ venta)", 'ancho' => 11, 'formato' => 'porcentaje'],
            ['titulo' => 'Ticket promedio', 'ancho' => 14, 'formato' => 'moneda'],
            ['titulo' => 'Ventas anuladas', 'ancho' => 12, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Total anulado', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
        ];
    }

    protected function filas(): array
    {
        return $this->ventas
            ->groupBy(fn ($venta) => ('CAJA '.($venta->caja ?? 1)).'|'.$venta->tipo_pago)
            ->map(function (Collection $grupo, string $clave) {
                [$caja, $pago] = explode('|', $clave);
                $validas = $grupo->filter(fn ($v) => $this->esCompletada($v));
                $anuladas = $grupo->reject(fn ($v) => $this->esCompletada($v));
                $total = round($validas->sum(fn ($v) => (float) $v->total), 2);
                $costo = round($validas->sum(fn ($v) => $this->porVenta[$v->id]['costo'] ?? 0), 2);

                return [
                    $caja,
                    $pago,
                    $validas->count(),
                    round($validas->sum(fn ($v) => $this->porVenta[$v->id]['unidades'] ?? 0), 3),
                    round($validas->sum(fn ($v) => (float) $v->subtotal), 2),
                    round($validas->sum(fn ($v) => (float) $v->descuento), 2),
                    $total,
                    round($validas->sum(fn ($v) => (float) $v->monto_efectivo), 2),
                    round($validas->sum(fn ($v) => (float) $v->monto_qr), 2),
                    $costo,
                    round($total - $costo, 2),
                    $total > 0 ? round(($total - $costo) / $total * 100, 1) : null,
                    $validas->count() ? round($total / $validas->count(), 2) : null,
                    $anuladas->count(),
                    round($anuladas->sum(fn ($v) => (float) $v->total), 2),
                ];
            })
            ->sortBy([[0, 'asc'], [1, 'asc']])
            ->values()
            ->all();
    }
}
