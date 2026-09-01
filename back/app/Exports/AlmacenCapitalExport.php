<?php

namespace App\Exports;

use App\Exports\Almacenes\HojaCapital;
use App\Exports\Comun\ContextoReporte;
use App\Models\Almacen;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Capital del almacén antes y después de la revisión, a costo y a venta. */
class AlmacenCapitalExport implements WithMultipleSheets
{
    public function __construct(
        private readonly ContextoReporte $contexto,
        private readonly Almacen $almacen,
        private readonly Collection $detalles,
        private readonly array $resumen,
    ) {}

    public function sheets(): array
    {
        return [new HojaCapital($this->contexto, $this->almacen, $this->detalles, $this->resumen)];
    }
}
