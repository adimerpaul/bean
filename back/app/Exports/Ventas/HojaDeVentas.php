<?php

namespace App\Exports\Ventas;

use App\Exports\Comun\HojaBase;
use Carbon\Carbon;

/** Utilidades comunes a las hojas del reporte de ventas. */
abstract class HojaDeVentas extends HojaBase
{
    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    protected function mensajeVacio(): string
    {
        return 'No hay ventas registradas para los filtros seleccionados.';
    }

    protected function diaSemana($fecha): string
    {
        return self::DIAS[Carbon::parse($fecha)->dayOfWeek];
    }

    protected function hora($fecha): string
    {
        return Carbon::parse($fecha)->format('H:i');
    }

    /** Ganancia de una línea: lo cobrado menos el costo del snapshot. */
    protected function ganancia($detalle): float
    {
        return round((float) $detalle->total - (float) $detalle->cantidad * (float) $detalle->precio_compra, 2);
    }

    protected function esCompletada($venta): bool
    {
        return ($venta->estado ?? null) === 'COMPLETADA';
    }

    /**
     * Fila de totales que ignora las filas anuladas: en las hojas que listan
     * documentos o líneas, sumar lo anulado inflaría el ingreso del periodo.
     */
    protected function totalesDeCompletadas(array $columnas, array $filas, int $columnaEstado): array
    {
        $validas = array_values(array_filter($filas, fn ($fila) => $fila[$columnaEstado] === 'COMPLETADA'));
        $totales = parent::filaTotales($columnas, $validas);
        $totales[0] = 'TOTALES (sólo completadas)';

        return $totales;
    }
}
