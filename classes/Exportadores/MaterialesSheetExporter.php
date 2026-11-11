<?php

namespace Classes\Exportadores;

use Classes\Imagenes;
use Classes\MaterialesCatalogo;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/** Catálogo de materiales en hoja de cálculo (.ods / .xlsx), agrupado por especialidad y con imagen */
class MaterialesSheetExporter extends BaseExporter
{
    private array $materiales = [];
    private array $filtros = [];
    private string $formato = 'ods';

    public function setMateriales(array $materiales): self
    {
        $this->materiales = $materiales;
        return $this;
    }

    public function setFiltros(array $filtros): self
    {
        $this->filtros = $filtros;
        return $this;
    }

    public function setFormato(string $formato): self
    {
        $this->formato = $formato === 'xlsx' ? 'xlsx' : 'ods';
        return $this;
    }

    private static function fecha(?string $f): ?float
    {
        return $f ? ExcelDate::PHPToExcel(new \DateTime($f)) : null;
    }

    public function generar(string $nombreBase): string
    {
        $libro = new Spreadsheet();
        $libro->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
        $h = $libro->getActiveSheet();
        $h->setTitle('Materiales');
        $h->setShowGridlines(false);
        $texto = fn(string $celda, string $v) => $h->setCellValueExplicit($celda, $v, DataType::TYPE_STRING);
        $L = fn(int $i) => Coordinate::stringFromColumnIndex($i);

        // Cabecera
        $h->mergeCells('A1:H1');
        $texto('A1', 'Catálogo de materiales');
        $h->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('0F172A');
        $h->getRowDimension(1)->setRowHeight(24);
        $h->mergeCells('A2:H2');
        $texto('A2', trim($this->proyecto->nombre . ($this->proyecto->codigo ? ' · ' . $this->proyecto->codigo : '') . ($this->proyecto->ubicacion ? ' · ' . $this->proyecto->ubicacion : '')));
        $h->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('334155');
        $h->mergeCells('A3:H3');
        $texto('A3', count($this->materiales) . ' material(es) · ' . MaterialesCatalogo::textoFiltros($this->filtros) . ' · Generado el ' . date('d/m/Y H:i'));
        $h->getStyle('A3')->getFont()->setSize(8)->getColor()->setRGB('64748B');

        // Encabezados
        $cab = ['Imagen', 'Referencia', 'Material', 'Especialidad', 'Status de compra', 'Fecha de compra programada', 'Fecha de entrega programada', 'Fecha de entrega real'];
        foreach ($cab as $i => $t) $texto($L($i + 1) . '5', $t);
        $rangoCab = 'A5:H5';
        $h->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('17365D');
        $h->getStyle($rangoCab)->getFont()->setBold(true)->setSize(8)->getColor()->setRGB('FFFFFF');
        $h->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $h->getStyle($rangoCab)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $h->getRowDimension(5)->setRowHeight(30);

        $r = 6;
        foreach (MaterialesCatalogo::secciones($this->materiales) as $especialidad => $lista) {
            $h->mergeCells("A$r:H$r");
            $texto("A$r", $especialidad . ' · ' . count($lista) . ' material(es)');
            $h->getStyle("A$r:H$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
            $h->getStyle("A$r")->getFont()->setBold(true)->setSize(8)->getColor()->setRGB('0F172A');
            $h->getStyle("A$r:H$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $h->getRowDimension($r)->setRowHeight(18);
            $r++;
            foreach ($lista as $m) {
                $texto("B$r", (string)($m->referencia ?: '—'));
                $texto("C$r", (string)$m->nombre);
                $texto("D$r", (string)($m->especialidad ?: 'Sin especialidad'));
                $texto("E$r", MaterialesCatalogo::compra($m->compra));
                foreach (['fecha_compra_prog' => 'F', 'fecha_entrega_prog' => 'G', 'fecha_entrega_real' => 'H'] as $campo => $col) {
                    $f = self::fecha($m->$campo ?? null);
                    if ($f !== null) {
                        $h->setCellValue($col . $r, $f);
                        $h->getStyle($col . $r)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    }
                }
                $h->getStyle("A$r:H$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $h->getStyle("A$r:H$r")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $h->getStyle("B$r")->getFont()->setName('Courier New')->setSize(8)->getColor()->setRGB('334155');
                $h->getStyle("C$r")->getFont()->setBold(true)->setSize(8);
                $h->getStyle("E$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                foreach (['F', 'G', 'H'] as $col) $h->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $h->getRowDimension($r)->setRowHeight(34);
                if (($ruta = Imagenes::ruta($m->imagen))) {
                    $dr = new Drawing();
                    $dr->setPath($ruta)->setCoordinates('A' . $r)->setResizeProportional(true)->setHeight(30)->setOffsetX(8)->setOffsetY(2)->setWorksheet($h);
                }
                $r++;
            }
        }

        foreach (['A' => 15, 'B' => 17, 'C' => 52, 'D' => 22, 'E' => 16, 'F' => 18, 'G' => 18, 'H' => 18] as $col => $ancho) $h->getColumnDimension($col)->setWidth($ancho);
        $h->freezePane('A6');
        if ($r > 6) $h->setAutoFilter('A5:H' . ($r - 1));
        $h->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0)->setFitToPage(true);
        $h->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5, 5);
        $h->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.25)->setRight(0.25);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, $this->formato);
        IOFactory::createWriter($libro, $this->formato === 'xlsx' ? 'Xlsx' : 'Ods')->save($ruta);
        return $ruta;
    }
}
