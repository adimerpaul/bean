<?php

namespace App\Exports\Ventas;

use App\Exports\Comun\ContextoReporte;
use Illuminate\Support\Collection;

/** Cuándo se vendió, día por día: sirve para comparar jornadas y ver el ritmo del negocio. */
class HojaPorDia extends HojaDeVentas
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
        return 'Por día';
    }

    protected function subtitulo(): string
    {
        return 'VENTAS POR DÍA - '.ucfirst($this->contexto->periodo())
            .'     |     Sólo los días con movimiento';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => 'Fecha', 'ancho' => 13, 'formato' => 'fecha'],
            ['titulo' => 'Día', 'ancho' => 12, 'formato' => 'texto'],
            ['titulo' => 'Nº ventas', 'ancho' => 10, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Productos', 'ancho' => 11, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Subtotal', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Descuento', 'ancho' => 12, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Total cobrado', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Efectivo', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'QR', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Costo', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Ganancia', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Ticket promedio', 'ancho' => 14, 'formato' => 'moneda'],
            ['titulo' => '% del total', 'ancho' => 11, 'formato' => 'porcentaje'],
            ['titulo' => 'Primera venta', 'ancho' => 11, 'formato' => 'texto'],
            ['titulo' => 'Última venta', 'ancho' => 11, 'formato' => 'texto'],
            ['titulo' => 'Ventas anuladas', 'ancho' => 12, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Total anulado', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
        ];
    }

    protected function filas(): array
    {
        $totalGeneral = $this->ventas->filter(fn ($v) => $this->esCompletada($v))->sum(fn ($v) => (float) $v->total);

        return $this->ventas->groupBy(fn ($venta) => $venta->fecha->format('Y-m-d'))
            ->map(function (Collection $grupo, string $dia) use ($totalGeneral) {
                $validas = $grupo->filter(fn ($v) => $this->esCompletada($v));
                $anuladas = $grupo->reject(fn ($v) => $this->esCompletada($v));
                $total = round($validas->sum(fn ($v) => (float) $v->total), 2);
                $costo = round($validas->sum(fn ($v) => $this->porVenta[$v->id]['costo'] ?? 0), 2);
                $horas = $validas->pluck('fecha')->filter()->sort()->values();

                return [
                    $this->fechaExcel($dia),
                    $this->diaSemana($dia),
                    $validas->count(),
                    round($validas->sum(fn ($v) => $this->porVenta[$v->id]['unidades'] ?? 0), 3),
                    round($validas->sum(fn ($v) => (float) $v->subtotal), 2),
                    round($validas->sum(fn ($v) => (float) $v->descuento), 2),
                    $total,
                    round($validas->sum(fn ($v) => (float) $v->monto_efectivo), 2),
                    round($validas->sum(fn ($v) => (float) $v->monto_qr), 2),
                    $costo,
                    round($total - $costo, 2),
                    $validas->count() ? round($total / $validas->count(), 2) : null,
                    $totalGeneral > 0 ? round($total / $totalGeneral * 100, 1) : null,
                    $horas->isNotEmpty() ? $this->hora($horas->first()) : null,
                    $horas->isNotEmpty() ? $this->hora($horas->last()) : null,
                    $anuladas->count(),
                    round($anuladas->sum(fn ($v) => (float) $v->total), 2),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }
}
