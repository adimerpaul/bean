<?php

namespace App\Exports\Ventas;

use App\Exports\Comun\ContextoReporte;
use Illuminate\Support\Collection;

/** Quién hizo las ventas: cuánto cobró cada persona, en qué cajas y qué anuló. */
class HojaPorUsuario extends HojaDeVentas
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
        return 'Por usuario';
    }

    protected function subtitulo(): string
    {
        return 'QUIÉN VENDIÓ - '.ucfirst($this->contexto->periodo())
            .'     |     '.$this->ventas->pluck('usuario_nombre')->unique()->count().' usuarios con movimiento';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => '#', 'ancho' => 5, 'formato' => 'entero'],
            ['titulo' => 'Usuario', 'ancho' => 26, 'formato' => 'texto'],
            ['titulo' => 'Cajas usadas', 'ancho' => 16, 'formato' => 'texto'],
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
            ['titulo' => 'Ventas anuladas', 'ancho' => 12, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Total anulado', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Primera venta', 'ancho' => 16, 'formato' => 'fechahora'],
            ['titulo' => 'Última venta', 'ancho' => 16, 'formato' => 'fechahora'],
        ];
    }

    protected function filas(): array
    {
        $totalGeneral = $this->ventas->filter(fn ($v) => $this->esCompletada($v))->sum(fn ($v) => (float) $v->total);

        return $this->ventas->groupBy(fn ($venta) => $venta->usuario_nombre ?: '—')
            ->map(function (Collection $grupo, string $usuario) use ($totalGeneral) {
                $validas = $grupo->filter(fn ($v) => $this->esCompletada($v));
                $anuladas = $grupo->reject(fn ($v) => $this->esCompletada($v));
                $total = round($validas->sum(fn ($v) => (float) $v->total), 2);
                $costo = round($validas->sum(fn ($v) => $this->porVenta[$v->id]['costo'] ?? 0), 2);
                $fechas = $validas->pluck('fecha')->filter()->sort()->values();

                return [
                    $usuario,
                    $grupo->pluck('caja')->map(fn ($caja) => (int) ($caja ?? 1))->unique()->sort()
                        ->map(fn ($caja) => 'CAJA '.$caja)->implode(', '),
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
                    $anuladas->count(),
                    round($anuladas->sum(fn ($v) => (float) $v->total), 2),
                    $this->fechaExcel($fechas->first()?->toDateTimeString()),
                    $this->fechaExcel($fechas->last()?->toDateTimeString()),
                ];
            })
            ->sortByDesc(fn ($fila) => $fila[6])
            ->values()
            ->map(fn ($fila, $indice) => [$indice + 1, ...$fila])
            ->all();
    }
}
