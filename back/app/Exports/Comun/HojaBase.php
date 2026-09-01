<?php

namespace App\Exports\Comun;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Hoja con el formato común del reporte: banda de título (empresa, subtítulo,
 * fecha/usuario, filtros), tabla con autofiltro, panel congelado, formatos
 * numéricos por columna y fila de totales.
 */
abstract class HojaBase implements FromArray, WithEvents, WithTitle
{
    protected const NEGRO = '171717';

    protected const NARANJA = 'F57C00';

    protected const GRIS = '6B7280';

    protected const LINEA = 'E5E7EB';

    protected const CEBRA = 'FAFAFA';

    protected const RESALTADO = 'FFF3E0';

    /** Fila donde se dibuja el encabezado de la tabla. */
    protected const FILA_CABECERA = 6;

    private const FORMATOS = [
        'entero' => '#,##0',
        'cantidad' => '#,##0.000',
        'moneda' => '"Bs "#,##0.00;[Red]-"Bs "#,##0.00',
        'precio' => '"Bs "#,##0.0000',
        'porcentaje' => '0.0"%";[Red]-0.0"%"',
        'fecha' => 'dd/mm/yyyy',
        'fechahora' => 'dd/mm/yyyy hh:mm',
    ];

    private ?array $filasCache = null;

    private ?array $columnasCache = null;

    public function __construct(protected readonly ContextoReporte $contexto) {}

    /** Las filas y columnas se calculan una sola vez: se usan al escribir y al dar formato. */
    protected function filasCalculadas(): array
    {
        return $this->filasCache ??= array_values($this->filas());
    }

    protected function columnasCalculadas(): array
    {
        return $this->columnasCache ??= $this->columnas();
    }

    abstract public function title(): string;

    abstract protected function subtitulo(): string;

    /** @return array<int, array{titulo: string, ancho: int, formato?: string, total?: bool}> */
    abstract protected function columnas(): array;

    abstract protected function filas(): array;

    protected function mensajeVacio(): string
    {
        return 'No hay movimientos para los filtros seleccionados.';
    }

    /** Excel ordena y filtra por fecha sólo si la celda guarda el número de serie, no el texto. */
    protected function fechaExcel(?string $fecha): ?float
    {
        return $fecha ? ExcelDate::PHPToExcel(Carbon::parse($fecha)) : null;
    }

    public function array(): array
    {
        $columnas = $this->columnasCalculadas();
        $filas = $this->filasCalculadas();

        $cabecera = [
            [$this->contexto->empresa],
            [$this->subtitulo()],
            [$this->contexto->generadoPor()],
            [$this->contexto->filtrosTexto()],
            [null], // Fila separadora: un array vacío lo descarta el writer y descuadraría la tabla.
            array_column($columnas, 'titulo'),
        ];

        if ($filas === []) {
            return [...$cabecera, [$this->mensajeVacio()]];
        }

        return [...$cabecera, ...$filas, $this->filaTotales($columnas, $filas)];
    }

    protected function filaTotales(array $columnas, array $filas): array
    {
        $fila = [];
        foreach ($columnas as $indice => $columna) {
            if ($indice === 0) {
                $fila[] = 'TOTALES';

                continue;
            }
            $fila[] = ($columna['total'] ?? false) ? array_sum(array_column($filas, $indice)) : null;
        }

        return $fila;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $columnas = $this->columnasCalculadas();
            $totalFilas = count($this->filasCalculadas());
            $hoja = $event->sheet->getDelegate();
            $ultimaColumna = Coordinate::stringFromColumnIndex(count($columnas));

            $this->dibujarTitulo($hoja, $columnas, $ultimaColumna);
            $this->dibujarCabecera($hoja, $columnas, $ultimaColumna);

            if ($totalFilas > 0) {
                $this->dibujarDatos($hoja, $columnas, $ultimaColumna, $totalFilas);
            } else {
                $hoja->getStyle('A'.(self::FILA_CABECERA + 1))->getFont()->setItalic(true)->getColor()->setRGB(self::GRIS);
            }

            $this->configurarImpresion($hoja, $ultimaColumna);
        }];
    }

    private function dibujarTitulo($hoja, array $columnas, string $ultimaColumna): void
    {
        foreach (range(1, 4) as $fila) {
            $hoja->mergeCells("A{$fila}:{$ultimaColumna}{$fila}");
            $hoja->getStyle("A{$fila}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::NEGRO);
        $hoja->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::NARANJA);
        $hoja->getStyle('A3:A4')->getFont()->setSize(9)->getColor()->setRGB(self::GRIS);
        $hoja->getStyle('A4')->getFont()->setItalic(true);

        $hoja->getRowDimension(1)->setRowHeight(24);
        $hoja->getRowDimension(2)->setRowHeight(18);
        $hoja->getRowDimension(5)->setRowHeight(6);

        // Franja naranja bajo la banda de título.
        $hoja->getStyle("A4:{$ultimaColumna}4")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::NARANJA);

        foreach ($columnas as $indice => $columna) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice + 1))->setWidth($columna['ancho'] ?? 16);
        }
    }

    private function dibujarCabecera($hoja, array $columnas, string $ultimaColumna): void
    {
        $fila = self::FILA_CABECERA;
        $hoja->getStyle("A{$fila}:{$ultimaColumna}{$fila}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NEGRO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::NEGRO]]],
        ]);
        $hoja->getRowDimension($fila)->setRowHeight(28);
        $hoja->freezePane('A'.($fila + 1));
        $hoja->getTabColor()->setRGB(self::NARANJA);
    }

    private function dibujarDatos($hoja, array $columnas, string $ultimaColumna, int $totalFilas): void
    {
        $primera = self::FILA_CABECERA + 1;
        $ultima = self::FILA_CABECERA + $totalFilas;
        $totales = $ultima + 1;

        $hoja->getStyle("A{$primera}:{$ultimaColumna}{$totales}")->applyFromArray([
            'font' => ['size' => 10],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::LINEA]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        for ($fila = $primera + 1; $fila <= $ultima; $fila += 2) {
            $hoja->getStyle("A{$fila}:{$ultimaColumna}{$fila}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::CEBRA);
        }

        foreach ($columnas as $indice => $columna) {
            $letra = Coordinate::stringFromColumnIndex($indice + 1);
            $formato = $columna['formato'] ?? 'texto';
            $estilo = $hoja->getStyle("{$letra}{$primera}:{$letra}{$totales}");

            if (isset(self::FORMATOS[$formato])) {
                $estilo->getNumberFormat()->setFormatCode(self::FORMATOS[$formato]);
            }
            $estilo->getAlignment()->setHorizontal(match ($formato) {
                'texto' => Alignment::HORIZONTAL_LEFT,
                'fecha', 'fechahora' => Alignment::HORIZONTAL_CENTER,
                default => Alignment::HORIZONTAL_RIGHT,
            });
        }

        $hoja->getStyle("A{$totales}:{$ultimaColumna}{$totales}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::RESALTADO]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::NARANJA]]],
        ]);
        $hoja->getRowDimension($totales)->setRowHeight(20);

        $hoja->setAutoFilter('A'.self::FILA_CABECERA.":{$ultimaColumna}{$ultima}");
    }

    private function configurarImpresion($hoja, string $ultimaColumna): void
    {
        $configuracion = $hoja->getPageSetup();
        $configuracion->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $configuracion->setPrintArea("A1:{$ultimaColumna}".$hoja->getHighestRow());
        $hoja->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, self::FILA_CABECERA);
        $hoja->getHeaderFooter()->setOddFooter('&L'.$this->contexto->empresa.' - '.$this->title().'&RPágina &P de &N');
        $hoja->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
    }
}
