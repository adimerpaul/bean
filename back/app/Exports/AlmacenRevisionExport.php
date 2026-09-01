<?php

namespace App\Exports;

use App\Exports\Almacenes\HojaAvance;
use App\Exports\Almacenes\HojaConteo;
use App\Exports\Almacenes\HojaPorUsuario;
use App\Exports\Comun\ContextoReporte;
use App\Models\Almacen;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Una revisión de almacén. Desde la pantalla de llenado se exporta el conteo;
 * desde la de avance, la comparación con el sistema y el aporte de cada persona.
 */
class AlmacenRevisionExport implements WithMultipleSheets
{
    public function __construct(
        private readonly ContextoReporte $contexto,
        private readonly Almacen $almacen,
        private readonly Collection $detalles,
        private readonly array $resumen,
        private readonly bool $conAvance = false,
    ) {}

    public function sheets(): array
    {
        return $this->conAvance
            ? [
                new HojaAvance($this->contexto, $this->almacen, $this->detalles, $this->resumen),
                new HojaPorUsuario($this->contexto, $this->almacen, $this->detalles),
            ]
            : [new HojaConteo($this->contexto, $this->almacen, $this->detalles)];
    }
}
