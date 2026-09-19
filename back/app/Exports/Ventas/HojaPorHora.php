<?php

namespace App\Exports\Ventas;

use App\Exports\Comun\ContextoReporte;
use Illuminate\Support\Collection;

/**
 * A qué hora se vende: las franjas del día con lo acumulado del periodo,
 * para decidir turnos y reposición.
 */
class HojaPorHora extends HojaDeVentas
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
        return 'Por hora';
    }

    protected function subtitulo(): string
    {
        return 'VENTAS POR FRANJA HORARIA - '.ucfirst($this->contexto->periodo())
            .'     |     Acumulado de todos los días del periodo';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => 'Franja', 'ancho' => 16, 'formato' => 'texto'],
            ['titulo' => 'Nº ventas', 'ancho' => 10, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Productos', 'ancho' => 11, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Total cobrado', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Efectivo', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'QR', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Descuento', 'ancho' => 12, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Ganancia', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Ticket promedio', 'ancho' => 14, 'formato' => 'moneda'],
            ['titulo' => '% del total', 'ancho' => 11, 'formato' => 'porcentaje'],
            ['titulo' => 'Ventas anuladas', 'ancho' => 12, 'formato' => 'entero', 'total' => true],
        ];
    }

    protected function filas(): array
    {
        $totalGeneral = $this->ventas->filter(fn ($v) => $this->esCompletada($v))->sum(fn ($v) => (float) $v->total);
        $porHora = $this->ventas->groupBy(fn ($venta) => (int) $venta->fecha->format('G'));

        // Sólo las franjas con movimiento: un día entero de ceros no aporta nada.
        return collect(range(0, 23))
            ->filter(fn (int $hora) => $porHora->has($hora))
            ->map(function (int $hora) use ($porHora, $totalGeneral) {
                $grupo = $porHora->get($hora);
                $validas = $grupo->filter(fn ($v) => $this->esCompletada($v));
                $total = round($validas->sum(fn ($v) => (float) $v->total), 2);
                $costo = round($validas->sum(fn ($v) => $this->porVenta[$v->id]['costo'] ?? 0), 2);
                $etiqueta = str_pad((string) $hora, 2, '0', STR_PAD_LEFT);

                return [
                    $etiqueta.':00 - '.$etiqueta.':59',
                    $validas->count(),
                    round($validas->sum(fn ($v) => $this->porVenta[$v->id]['unidades'] ?? 0), 3),
                    $total,
                    round($validas->sum(fn ($v) => (float) $v->monto_efectivo), 2),
                    round($validas->sum(fn ($v) => (float) $v->monto_qr), 2),
                    round($validas->sum(fn ($v) => (float) $v->descuento), 2),
                    round($total - $costo, 2),
                    $validas->count() ? round($total / $validas->count(), 2) : null,
                    $totalGeneral > 0 ? round($total / $totalGeneral * 100, 1) : null,
                    $grupo->count() - $validas->count(),
                ];
            })
            ->values()
            ->all();
    }
}
