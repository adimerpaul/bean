<?php

namespace App\Exports;

use App\Exports\Comun\ContextoReporte;
use App\Exports\Productos\HojaCompras;
use App\Exports\Productos\HojaInventario;
use App\Exports\Productos\HojaResumen;
use App\Exports\Productos\HojaVentas;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reporte de productos en cuatro hojas: resumen por categoría, inventario
 * valorizado, compras por producto y ventas por producto.
 */
class ProductosReporteExport implements WithMultipleSheets
{
    public function __construct(
        private readonly ContextoReporte $contexto,
        private readonly Collection $productos,
        private readonly Collection $compras,
        private readonly Collection $ventas,
    ) {}

    public function sheets(): array
    {
        return [
            new HojaResumen($this->contexto, $this->productos, $this->compras, $this->ventas),
            new HojaInventario($this->contexto, $this->productos),
            new HojaCompras($this->contexto, $this->compras, $this->productos),
            new HojaVentas($this->contexto, $this->ventas, $this->productos),
        ];
    }
}
