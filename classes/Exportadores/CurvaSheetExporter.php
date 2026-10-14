<?php

namespace Classes\Exportadores;

use Model\Curva;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Curva (o gráfica maestra) en .xlsx / .ods: tabla de datos y gráfica como imagen */
class CurvaSheetExporter extends BaseExporter
{
    private ?Curva $curva = null;
    private string $formato = 'xlsx';

    public function setCurva(?Curva $c): self
    {
        $this->curva = $c;
        return $this;
    }

    public function setFormato(string $f): self
    {
        $this->formato = $f === 'ods' ? 'ods' : 'xlsx';
        return $this;
    }

    protected function prefijoNombre(): string
    {
        return $this->curva ? $this->curva->nombre : 'Maestra';
    }

    public function generar(string $nombreBase): string
    {
        $d = CurvaPdfExporter::armar($this->proyecto, $this->curva);
        $libro = new Spreadsheet();
        $h = $libro->getActiveSheet();
        $h->setTitle(mb_substr(preg_replace('/[\\\\\/?*:\[\]]/', ' ', $this->curva ? $this->curva->nombre : 'Gráfica maestra'), 0, 31));
        $h->setShowGridlines(false);
        $libro->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $h->setCellValueExplicit('B1', $d['titulo'], 's');
        $h->getStyle('B1')->getFont()->setBold(true)->setSize(12);
        foreach ($d['cols'] as $i => $c) {
            $h->setCellValueExplicit([3 + $i, 2], $c, 's');
        }
        $r = 2;
        foreach ($d['filas'] as $nombre => $vals) {
            $r++;
            $h->setCellValueExplicit("B$r", $nombre, 's');
            foreach ($vals as $i => $v) {
                if ($v !== null) $h->setCellValue([3 + $i, $r], $v);
            }
            $h->getStyle([3, $r, 2 + count($vals), $r])->getNumberFormat()->setFormatCode($d['decimales'] ? '#,##0.0' : '#,##0');
        }
        $ultCol = Coordinate::stringFromColumnIndex(2 + count($d['cols']));
        $h->getStyle("B2:$ultCol$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $h->getStyle("B3:B$r")->getFont()->setBold(true);
        $h->getStyle("C2:$ultCol$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $h->getColumnDimension('B')->setAutoSize(true);
        for ($c = 3; $c <= 2 + count($d['cols']); $c++) $h->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(11);

        // Gráfica como imagen debajo de la tabla
        $gd = imagecreatefromstring($d['png']);
        $dr = new MemoryDrawing();
        $dr->setImageResource($gd)->setRenderingFunction(MemoryDrawing::RENDERING_PNG)->setMimeType(MemoryDrawing::MIMETYPE_DEFAULT)
            ->setCoordinates('B' . ($r + 2))->setWidth(1000)->setHeight(530)->setWorksheet($h);

        $h->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LETTER)->setFitToWidth(1)->setFitToHeight(1);
        $h->getPageSetup()->setFitToPage(true);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, $this->formato);
        IOFactory::createWriter($libro, $this->formato === 'ods' ? 'Ods' : 'Xlsx')->save($ruta);
        return $ruta;
    }
}
