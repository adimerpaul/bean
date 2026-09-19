<?php

namespace App\Exports\Ventas;

use App\Exports\Comun\ContextoReporte;
use Illuminate\Support\Collection;

/** Un documento por fila: vendidas y anuladas, con quién la hizo y qué dejó de ganancia. */
class HojaVentas extends HojaDeVentas
{
    private const COLUMNA_ESTADO = 17;

    public function __construct(
        ContextoReporte $contexto,
        private readonly Collection $ventas,
        private readonly Collection $porVenta,
    ) {
        parent::__construct($contexto);
    }

    public function title(): string
    {
        return 'Ventas';
    }

    protected function subtitulo(): string
    {
        return 'DOCUMENTOS DE VENTA - '.ucfirst($this->contexto->periodo())
            .'     |     Filtra por la columna Estado para ver sólo COMPLETADA o ANULADA; los totales no suman lo anulado';
    }

    protected function columnas(): array
    {
        return [
            ['titulo' => '#', 'ancho' => 5, 'formato' => 'entero'],
            ['titulo' => 'Nº venta', 'ancho' => 13, 'formato' => 'texto'],
            ['titulo' => 'Fecha y hora', 'ancho' => 17, 'formato' => 'fechahora'],
            ['titulo' => 'Día', 'ancho' => 11, 'formato' => 'texto'],
            ['titulo' => 'Hora', 'ancho' => 8, 'formato' => 'texto'],
            ['titulo' => 'Usuario', 'ancho' => 22, 'formato' => 'texto'],
            ['titulo' => 'Caja', 'ancho' => 9, 'formato' => 'texto'],
            ['titulo' => 'Forma de pago', 'ancho' => 14, 'formato' => 'texto'],
            ['titulo' => 'Productos', 'ancho' => 10, 'formato' => 'entero', 'total' => true],
            ['titulo' => 'Unidades', 'ancho' => 11, 'formato' => 'cantidad', 'total' => true],
            ['titulo' => 'Subtotal', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Descuento', 'ancho' => 12, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Total', 'ancho' => 14, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Efectivo', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'QR', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Costo', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Ganancia', 'ancho' => 13, 'formato' => 'moneda', 'total' => true],
            ['titulo' => 'Estado', 'ancho' => 13, 'formato' => 'texto'],
            ['titulo' => 'Observación', 'ancho' => 30, 'formato' => 'texto'],
        ];
    }

    protected function filas(): array
    {
        return $this->ventas->values()->map(function ($venta, $indice) {
            $total = (float) $venta->total;
            $costo = (float) ($this->porVenta[$venta->id]['costo'] ?? 0);

            return [
                $indice + 1,
                $venta->numero,
                $this->fechaExcel($venta->fecha?->toDateTimeString()),
                $this->diaSemana($venta->fecha),
                $this->hora($venta->fecha),
                $venta->usuario_nombre,
                'CAJA '.($venta->caja ?? 1),
                $venta->tipo_pago,
                (int) ($this->porVenta[$venta->id]['items'] ?? 0),
                (float) ($this->porVenta[$venta->id]['unidades'] ?? 0),
                (float) $venta->subtotal,
                (float) $venta->descuento,
                $total,
                (float) $venta->monto_efectivo,
                (float) $venta->monto_qr,
                $costo,
                round($total - $costo, 2),
                $venta->estado,
                $venta->observacion,
            ];
        })->all();
    }

    protected function filaTotales(array $columnas, array $filas): array
    {
        return $this->totalesDeCompletadas($columnas, $filas, self::COLUMNA_ESTADO);
    }
}
