<?php

namespace App\Exports\Almacenes;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaBase;
use App\Models\Almacen;
use Illuminate\Support\Collection;

/**
 * Capital del almacén: cuánto vale la mercadería hoy y cuánto valdrá después de
 * aplicar la revisión, a precio de compra y a precio de venta. Los productos que
 * nadie contó entran en una sola línea al final para que el total sea el de toda
 * la tienda, no sólo el de lo revisado.
 */
class HojaCapital extends HojaBase
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
        return 'Capital';
    }

    protected function subtitulo(): string
    {
        return 'CAPITAL EN ALMACÉN - REVISIÓN '.$this->almacen->numero
            .'     |     Antes: Bs '.number_format($this->resumen['costo_antes'], 2)
            .' (venta Bs '.number_format($this->resumen['venta_antes'], 2).')'
            .'     |     Después: Bs '.number_format($this->resumen['costo_despues'], 2)
            .' (venta Bs '.number_format($this->resumen['venta_despues'], 2).')';
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
            ['titulo' => 'Producto', 'ancho' => 36, 'formato' => 'texto'],
            ['titulo' => 'Unidad', 'ancho' => 9, 'formato' => 'texto'],
            ['titulo' => 'Precio compra', 'ancho' => 13, 'formato' => 'precio'],
            ['titulo' => 'Precio venta', 'ancho' => 13, 'formato' => 'precio'],
            ['titulo' => "Stock actual\n(sistema)", 'ancho' => 12, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => "Capital actual\na costo", 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => "Capital actual\na venta", 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => "Stock después\nde la revisión", 'ancho' => 13, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => "Capital después\na costo", 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => "Capital después\na venta", 'ancho' => 15, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Diferencia', 'ancho' => 11, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => "Diferencia\na costo", 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => "Diferencia\na venta", 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Resultado', 'ancho' => 12, 'formato' => 'texto'],
        ];
    }

    protected function filas(): array
    {
        $filas = $this->detalles->values()->map(function ($detalle, $indice) {
            $compra = (float) $detalle->precio_compra;
            $venta = (float) ($detalle->producto?->precio_venta ?? 0);
            $antes = (float) $detalle->stock_actual;
            $despues = (float) $detalle->cantidad;
            $diferencia = (float) $detalle->diferencia_actual;

            return [
                $indice + 1,
                $detalle->codigo,
                $detalle->nombre,
                $detalle->unidad,
                $compra,
                $venta,
                $antes,
                round($antes * $compra, 2),
                round($antes * $venta, 2),
                $despues,
                round($despues * $compra, 2),
                round($despues * $venta, 2),
                $diferencia,
                round($diferencia * $compra, 2),
                round($diferencia * $venta, 2),
                match (true) {
                    abs($diferencia) < 0.0005 => 'CUADRA',
                    $diferencia > 0 => 'SOBRANTE',
                    default => 'FALTANTE',
                },
            ];
        })->all();

        $resto = $this->resumen['no_revisados'];
        if ($resto['productos'] > 0) {
            // Lo que nadie contó no cambia, pero suma al capital de la tienda.
            $filas[] = [
                count($filas) + 1,
                '—',
                'PRODUCTOS NO REVISADOS ('.$resto['productos'].')',
                '—', 0, 0,
                $resto['cantidad'], $resto['costo'], $resto['venta'],
                $resto['cantidad'], $resto['costo'], $resto['venta'],
                0, 0, 0, 'SIN CONTAR',
            ];
        }

        return $filas;
    }
}
