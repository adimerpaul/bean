<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductosExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Collection $productos) {}

    public function collection(): Collection
    {
        return $this->productos->map(fn ($producto) => [
            $producto->codigo,
            $producto->codigo_barras,
            $producto->nombre,
            $producto->categoriaRelacion?->nombre ?? $producto->categoria,
            $producto->unidad,
            $producto->precio_compra,
            $producto->precio_venta,
            $producto->stock_inicial,
        ]);
    }

    public function headings(): array
    {
        return ['Código', 'Código de barras', 'Producto', 'Categoría', 'Unidad', 'Precio compra', 'Precio venta', 'Stock'];
    }
}
