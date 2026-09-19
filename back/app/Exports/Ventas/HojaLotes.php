<?php

namespace App\Exports\Ventas;

use App\Exports\Comun\ContextoReporte;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Qué lote salió en cada venta y con qué vencimiento: cruza venta_detalle_lotes
 * con lotes para responder cuándo vencía lo que se vendió y con cuántos días de
 * margen salió. Las ventas anuladas devolvieron el lote, por eso no suman.
 */
class HojaLotes extends HojaDeVentas
{
    private const COLUMNA_ESTADO = 13;

    public function __construct(
        ContextoReporte $contexto,
        private readonly Collection $lotes,
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Lotes y vencimientos';
    }

    protected function subtitulo(): string
    {
        $vencidos = $this->lotes->filter(fn ($l) => $l->estado === 'COMPLETADA' && $this->margen($l) !== null && $this->margen($l) < 0)->count();

        return 'LOTES VENDIDOS Y SU VENCIMIENTO - '.ucfirst($this->contexto->periodo())
            .'     |     Ordenado por fecha de vencimiento'
            .($vencidos ? '     |     ¡Atención! '.$vencidos.' líneas salieron con el lote ya vencido' : '');
    }

    protected function mensajeVacio(): string
    {
        return 'Ninguna venta del periodo consumió lotes con vencimiento registrado.';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => '#', 'ancho' => 5, 'formato' => 'entero'],
            ['titulo' => 'Nº venta', 'ancho' => 13, 'formato' => 'texto'],
            ['titulo' => 'Fecha de venta', 'ancho' => 17, 'formato' => 'fechahora'],
            ['titulo' => 'Usuario', 'ancho' => 22, 'formato' => 'texto'],
            ['titulo' => 'Código', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Producto', 'ancho' => 34, 'formato' => 'texto'],
            ['titulo' => 'Unidad', 'ancho' => 9, 'formato' => 'texto'],
            ['titulo' => 'Lote', 'ancho' => 16, 'formato' => 'texto'],
            ['titulo' => 'Vence el', 'ancho' => 13, 'formato' => 'fecha'],
            ['titulo' => "Días de margen\nal vender", 'ancho' => 13, 'formato' => 'entero'],
            ['titulo' => 'Situación del lote', 'ancho' => 18, 'formato' => 'texto'],
            ['titulo' => 'Cantidad vendida', 'ancho' => 14, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Costo', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Estado de la venta', 'ancho' => 16, 'formato' => 'texto'],
        ];
    }

    protected function filas(): array
    {
        return $this->lotes
            // Sin vencimiento al final: lo urgente es lo que caduca antes.
            ->sortBy(fn ($fila) => [$fila->fecha_vencimiento ?? '9999-12-31', (string) $fila->fecha])
            ->values()
            ->map(function ($fila, $indice) {
                $margen = $this->margen($fila);

                return [
                    $indice + 1,
                    $fila->numero,
                    $this->fechaExcel($fila->fecha),
                    $fila->usuario_nombre,
                    $fila->codigo,
                    $fila->nombre,
                    $fila->unidad,
                    $fila->lote ?: '-',
                    $this->fechaExcel($fila->fecha_vencimiento),
                    $margen,
                    $this->situacion($margen),
                    (float) $fila->cantidad,
                    round((float) $fila->cantidad * (float) $fila->precio_compra, 2),
                    $fila->estado,
                ];
            })
            ->all();
    }

    protected function filaTotales(array $columnas, array $filas): array
    {
        return $this->totalesDeCompletadas($columnas, $filas, self::COLUMNA_ESTADO);
    }

    /** Días entre la venta y el vencimiento del lote; negativo si ya estaba vencido. */
    private function margen($fila): ?int
    {
        return $fila->fecha_vencimiento
            ? (int) Carbon::parse($fila->fecha)->startOfDay()->diffInDays(Carbon::parse($fila->fecha_vencimiento)->startOfDay(), false)
            : null;
    }

    private function situacion(?int $margen): string
    {
        return match (true) {
            $margen === null => 'SIN VENCIMIENTO',
            $margen < 0 => 'VENCIDO AL VENDER',
            $margen <= 7 => 'POR VENCER (7 DÍAS)',
            $margen <= 30 => 'POR VENCER (30 DÍAS)',
            default => 'VIGENTE',
        };
    }
}
