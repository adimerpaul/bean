<?php

namespace App\Exports\Almacenes;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaBase;
use Illuminate\Support\Collection;

/** Listado de revisiones de almacén con los mismos filtros de la pantalla. */
class HojaRevisiones extends HojaBase
{
    public function __construct(ContextoReporte $contexto, private readonly Collection $almacenes)
    {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Revisiones';
    }

    protected function subtitulo(): string
    {
        return 'REVISIONES DE ALMACÉN - Movimientos: '.ucfirst($this->contexto->periodo());
    }

    protected function mensajeVacio(): string
    {
        return 'No hay revisiones para los filtros seleccionados.';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => '#', 'ancho' => 5, 'formato' => 'entero'],
            ['titulo' => 'Número', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Fecha', 'ancho' => 17, 'formato' => 'fechahora'],
            ['titulo' => 'Descripción', 'ancho' => 34, 'formato' => 'texto'],
            ['titulo' => 'Creó', 'ancho' => 20, 'formato' => 'texto'],
            ['titulo' => 'Productos', 'ancho' => 11, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Cantidad contada', 'ancho' => 15, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Valor a costo', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Estado', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Aplicado por', 'ancho' => 20, 'formato' => 'texto'],
            ['titulo' => 'Fecha aplicado', 'ancho' => 17, 'formato' => 'fechahora'],
            ['titulo' => 'Observación', 'ancho' => 34, 'formato' => 'texto'],
        ];
    }

    protected function filas(): array
    {
        return $this->almacenes->values()->map(fn ($almacen, $indice) => [
            $indice + 1,
            $almacen->numero,
            $this->fechaExcel($almacen->fecha?->toDateTimeString()),
            $almacen->descripcion ?: '—',
            $almacen->usuario_nombre,
            (int) ($almacen->detalles_count ?? 0),
            (float) $almacen->total_cantidad,
            (float) $almacen->total_costo,
            $almacen->estado === 'BORRADOR' ? 'EN REVISIÓN' : $almacen->estado,
            $almacen->aplicado_por_nombre ?: '—',
            $this->fechaExcel($almacen->fecha_aplicado?->toDateTimeString()),
            $almacen->observacion ?: '—',
        ])->all();
    }
}
