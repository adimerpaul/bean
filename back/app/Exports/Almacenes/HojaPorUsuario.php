<?php

namespace App\Exports\Almacenes;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaBase;
use App\Models\Almacen;
use Illuminate\Support\Collection;

/** Cuánto contó cada persona en la revisión y cuántas diferencias encontró. */
class HojaPorUsuario extends HojaBase
{
    public function __construct(
        ContextoReporte $contexto,
        private readonly Almacen $almacen,
        private readonly Collection $detalles,
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Por usuario';
    }

    protected function subtitulo(): string
    {
        return 'QUIÉN CONTÓ EN LA REVISIÓN '.$this->almacen->numero;
    }

    protected function mensajeVacio(): string
    {
        return 'Todavía nadie contó productos en esta revisión.';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => '#', 'ancho' => 5, 'formato' => 'entero'],
            ['titulo' => 'Usuario', 'ancho' => 26, 'formato' => 'texto'],
            ['titulo' => 'Productos contados', 'ancho' => 17, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Cuadran', 'ancho' => 11, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Sobrantes', 'ancho' => 11, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Faltantes', 'ancho' => 11, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Valor diferencia', 'ancho' => 16, 'formato' => 'moneda', 'total' => true],
        ];
    }

    protected function filas(): array
    {
        $diferencia = fn ($detalle) => (float) $detalle->diferencia_actual;

        return $this->detalles->groupBy(fn ($detalle) => $detalle->usuario_nombre ?: '—')
            ->map(fn (Collection $grupo, $usuario) => [
                $usuario,
                $grupo->count(),
                $grupo->filter(fn ($d) => abs($diferencia($d)) < 0.0005)->count(),
                $grupo->filter(fn ($d) => $diferencia($d) > 0.0005)->count(),
                $grupo->filter(fn ($d) => $diferencia($d) < -0.0005)->count(),
                round($grupo->sum(fn ($d) => $diferencia($d) * (float) $d->precio_compra), 2),
            ])
            ->sortByDesc(fn ($fila) => $fila[1])
            ->values()
            ->map(fn ($fila, $indice) => [$indice + 1, ...$fila])
            ->all();
    }
}
