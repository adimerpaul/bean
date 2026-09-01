<?php

namespace App\Exports\Almacenes;

use App\Models\AlmacenDetalle;

/** Los lotes contados de una línea, en una sola celda: "L-12: 4 (vence 12/09/2026)". */
class ResumenLotes
{
    public static function texto(AlmacenDetalle $detalle): string
    {
        $lotes = $detalle->conteos ?? collect();
        if ($lotes->isEmpty()) {
            return '—';
        }

        return $lotes->map(function ($lote) use ($detalle) {
            $cantidad = number_format((float) $lote->cantidad, $detalle->unidad === 'KG' ? 3 : 0, '.', '');
            $vence = $lote->fecha_vencimiento ? ' (vence '.$lote->fecha_vencimiento->format('d/m/Y').')' : '';

            return ($lote->lote ?: 'sin lote').': '.$cantidad.$vence;
        })->implode(' · ');
    }
}
