<?php

namespace App\Exports\Almacenes;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaBase;
use App\Models\Almacen;
use Illuminate\Support\Collection;

/** Lo que se contó en una revisión, tal como se ve en la pantalla de llenado. */
class HojaConteo extends HojaBase
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
        return 'Conteo';
    }

    protected function subtitulo(): string
    {
        return 'REVISIÓN DE ALMACÉN '.$this->almacen->numero
            .'     |     '.($this->almacen->estado === 'BORRADOR' ? 'EN REVISIÓN' : $this->almacen->estado)
            .'     |     '.($this->almacen->descripcion ?: 'Conteo del stock físico de la tienda');
    }

    protected function mensajeVacio(): string
    {
        return 'Todavía no se contó ningún producto en esta revisión.';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => '#', 'ancho' => 5, 'formato' => 'entero'],
            ['titulo' => 'Código', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Producto', 'ancho' => 38, 'formato' => 'texto'],
            ['titulo' => 'Unidad', 'ancho' => 9, 'formato' => 'texto'],
            ['titulo' => 'Contó', 'ancho' => 20, 'formato' => 'texto'],
            ['titulo' => 'Stock sistema', 'ancho' => 13, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Contado', 'ancho' => 12, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Diferencia', 'ancho' => 12, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Lotes contados', 'ancho' => 42, 'formato' => 'texto'],
            ['titulo' => 'Lote', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Vencimiento', 'ancho' => 13, 'formato' => 'fecha'],
            ['titulo' => 'Precio compra', 'ancho' => 14, 'formato' => 'precio'],
            ['titulo' => 'Valor a costo', 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Observación', 'ancho' => 30, 'formato' => 'texto'],
        ];
    }

    protected function filas(): array
    {
        return $this->detalles->values()->map(fn ($detalle, $indice) => [
            $indice + 1,
            $detalle->codigo,
            $detalle->nombre,
            $detalle->unidad,
            $detalle->usuario_nombre ?: '—',
            (float) $detalle->stock_actual,
            (float) $detalle->cantidad,
            (float) $detalle->diferencia_actual,
            ResumenLotes::texto($detalle),
            $detalle->lote ?: '—',
            $this->fechaExcel($detalle->fecha_vencimiento?->toDateString()),
            (float) $detalle->precio_compra,
            round((float) $detalle->cantidad * (float) $detalle->precio_compra, 2),
            $detalle->observacion ?: '—',
        ])->all();
    }
}
