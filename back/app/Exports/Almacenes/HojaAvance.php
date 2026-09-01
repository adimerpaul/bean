<?php

namespace App\Exports\Almacenes;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaBase;
use App\Models\Almacen;
use Illuminate\Support\Collection;

/** Comparación línea por línea entre el stock del sistema y lo contado. */
class HojaAvance extends HojaBase
{
    public function __construct(
        ContextoReporte $contexto,
        private readonly Almacen $almacen,
        private readonly Collection $detalles,
        private readonly array $resumen,
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Avance';
    }

    protected function subtitulo(): string
    {
        $aplicado = $this->almacen->estado === 'APLICADO'
            ? 'Aplicado por '.($this->almacen->aplicado_por_nombre ?: '—').' el '.$this->almacen->fecha_aplicado?->format('d/m/Y H:i')
            : 'Pendiente de aplicar';

        return 'AVANCE DE LA REVISIÓN '.$this->almacen->numero
            .'     |     '.$this->resumen['revisados'].' de '.$this->resumen['total_productos'].' productos revisados'
            .'     |     '.$this->resumen['con_diferencia'].' con diferencia'
            .'     |     '.$aplicado;
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
            ['titulo' => 'Sistema', 'ancho' => 12, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Contado', 'ancho' => 12, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Diferencia', 'ancho' => 12, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Resultado', 'ancho' => 13, 'formato' => 'texto'],
            ['titulo' => 'Precio compra', 'ancho' => 14, 'formato' => 'precio'],
            ['titulo' => 'Valor diferencia', 'ancho' => 16, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Stock resultante', 'ancho' => 15, 'formato' => 'cantidad'],
            ['titulo' => 'Lotes contados', 'ancho' => 42, 'formato' => 'texto'],
        ];
    }

    protected function filas(): array
    {
        return $this->detalles->values()->map(function ($detalle, $indice) {
            $diferencia = (float) $detalle->diferencia_actual;

            return [
                $indice + 1,
                $detalle->codigo,
                $detalle->nombre,
                $detalle->unidad,
                $detalle->usuario_nombre ?: '—',
                (float) $detalle->stock_actual,
                (float) $detalle->cantidad,
                $diferencia,
                match (true) {
                    abs($diferencia) < 0.0005 => 'CUADRA',
                    $diferencia > 0 => 'SOBRANTE',
                    default => 'FALTANTE',
                },
                (float) $detalle->precio_compra,
                round($diferencia * (float) $detalle->precio_compra, 2),
                $detalle->stock_nuevo !== null ? (float) $detalle->stock_nuevo : (float) $detalle->cantidad,
                ResumenLotes::texto($detalle),
            ];
        })->all();
    }
}
