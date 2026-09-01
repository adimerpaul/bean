<?php

namespace App\Exports\Comun;

use Carbon\CarbonInterface;

/**
 * Datos de cabecera compartidos por todas las hojas del reporte:
 * empresa, usuario que exporta, momento de generación y filtros aplicados.
 */
class ContextoReporte
{
    public function __construct(
        public readonly string $empresa,
        public readonly ?string $nit,
        public readonly string $usuario,
        public readonly CarbonInterface $generado,
        public readonly ?CarbonInterface $desde,
        public readonly ?CarbonInterface $hasta,
        public readonly array $filtros = [],
    ) {}

    public function generadoPor(): string
    {
        $partes = ['Generado: '.$this->generado->format('d/m/Y H:i'), 'Usuario: '.$this->usuario];
        if ($this->nit) {
            $partes[] = 'NIT: '.$this->nit;
        }

        return implode('     |     ', $partes);
    }

    public function periodo(): string
    {
        return match (true) {
            $this->desde && $this->hasta => 'del '.$this->desde->format('d/m/Y').' al '.$this->hasta->format('d/m/Y'),
            (bool) $this->desde => 'desde el '.$this->desde->format('d/m/Y'),
            (bool) $this->hasta => 'hasta el '.$this->hasta->format('d/m/Y'),
            default => 'todo el histórico',
        };
    }

    public function filtrosTexto(): string
    {
        $partes = ['Periodo: '.$this->periodo()];
        foreach ($this->filtros as $etiqueta => $valor) {
            $partes[] = $etiqueta.': '.$valor;
        }

        return 'Filtros aplicados     |     '.implode('     |     ', $partes);
    }
}
