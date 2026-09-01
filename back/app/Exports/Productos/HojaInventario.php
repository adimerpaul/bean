<?php

namespace App\Exports\Productos;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Comun\HojaBase;
use Illuminate\Support\Collection;

class HojaInventario extends HojaBase
{
    public function __construct(ContextoReporte $contexto, private readonly Collection $productos)
    {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Inventario';
    }

    protected function subtitulo(): string
    {
        return 'INVENTARIO DE PRODUCTOS - Existencias al '.$this->contexto->generado->format('d/m/Y H:i');
    }

    protected function mensajeVacio(): string
    {
        return 'No hay productos para los filtros seleccionados.';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => '#', 'ancho' => 5, 'formato' => 'entero'],
            ['titulo' => 'Código', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Cód. barras', 'ancho' => 16, 'formato' => 'texto'],
            ['titulo' => 'Producto', 'ancho' => 38, 'formato' => 'texto'],
            ['titulo' => 'Categoría', 'ancho' => 20, 'formato' => 'texto'],
            ['titulo' => 'Unidad', 'ancho' => 10, 'formato' => 'texto'],
            ['titulo' => 'Stock', 'ancho' => 12, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Precio compra', 'ancho' => 14, 'formato' => 'moneda'],
            ['titulo' => 'Precio venta', 'ancho' => 14, 'formato' => 'moneda'],
            ['titulo' => 'Valor a costo', 'ancho' => 16, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Valor a venta', 'ancho' => 16, 'formato' => 'moneda', 'total' => true],
            ['titulo' => "Margen %\n(s/ venta)", 'ancho' => 11, 'formato' => 'porcentaje'],
            ['titulo' => 'Estado', 'ancho' => 14, 'formato' => 'texto'],
        ];
    }

    protected function filas(): array
    {
        return $this->productos->values()->map(function ($producto, $indice) {
            $stock = (float) $producto->stock_inicial;
            $compra = (float) $producto->precio_compra;
            $venta = (float) $producto->precio_venta;

            return [
                $indice + 1,
                $producto->codigo,
                $producto->codigo_barras,
                $producto->nombre,
                $producto->categoriaRelacion?->nombre ?? $producto->categoria,
                $producto->unidad,
                $stock,
                $compra,
                $venta,
                round($stock * $compra, 2),
                round($stock * $venta, 2),
                $venta > 0 ? round(($venta - $compra) / $venta * 100, 1) : null,
                match (true) {
                    $stock <= 0 => 'SIN STOCK',
                    $stock <= 5 => 'STOCK BAJO',
                    default => 'DISPONIBLE',
                },
            ];
        })->all();
    }
}
