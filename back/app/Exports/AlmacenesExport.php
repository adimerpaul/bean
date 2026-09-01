<?php

namespace App\Exports;

use App\Exports\Almacenes\HojaRevisiones;
use App\Exports\Comun\ContextoReporte;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Listado de revisiones de almacén (pantalla /almacenes). */
class AlmacenesExport implements WithMultipleSheets
{
    public function __construct(
        private readonly ContextoReporte $contexto,
        private readonly Collection $almacenes,
    ) {}

    public function sheets(): array
    {
        return [new HojaRevisiones($this->contexto, $this->almacenes)];
    }
}
