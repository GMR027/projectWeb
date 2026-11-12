<?php

namespace Classes\Exportadores;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/** Catálogo de conceptos / presupuesto en hoja de cálculo (.xlsx / .ods), agrupado por partida */
class PresupuestoSheetExporter extends BaseExporter
{
    private array $datos = [];
    private string $formato = 'xlsx';

    public function setDatos(array $datos): self
    {
        $this->datos = $datos;
        return $this;
    }

    public function setFormato(string $formato): self
    {
        $this->formato = $formato === 'ods' ? 'ods' : 'xlsx';
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $d = $this->datos;
        $t = $d['totales'];
        $libro = new Spreadsheet();
        $libro->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
        $h = $libro->getActiveSheet();
        $h->setTitle('Presupuesto');
        $h->setShowGridlines(false);
        $texto = fn(string $celda, string $v) => $h->setCellValueExplicit($celda, $v, DataType::TYPE_STRING);
        $money = '"$"#,##0.00';

        // Cabecera
        $h->mergeCells('A1:H1');
        $texto('A1', 'Presupuesto de obra · Catálogo de conceptos');
        $h->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('0F172A');
        $h->getRowDimension(1)->setRowHeight(24);
        $h->mergeCells('A2:H2');
        $proy = trim($this->proyecto->nombre . ($this->proyecto->codigo ? ' · ' . $this->proyecto->codigo : '') . ($this->proyecto->ubicacion ? ' · ' . $this->proyecto->ubicacion : ''));
        $texto('A2', $proy);
        $h->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('334155');
        $h->mergeCells('A3:H3');
        $texto('A3', 'Generado el ' . date('d/m/Y H:i') . ' · ' . count($d['grupos']) . ' partida(s) · ' . ($d['sinPartida'] ? 'incluye conceptos generales' : 'sin conceptos generales'));
        $h->getStyle('A3')->getFont()->setSize(8)->getColor()->setRGB('64748B');

        $cab = ['Clave', 'Concepto', 'Unidad', 'Cantidad', 'P. unitario', 'Importe', 'Programado', 'Pendiente'];
        foreach ($cab as $i => $txt) $texto(Coordinate::stringFromColumnIndex($i + 1) . '5', $txt);
        $h->getStyle('A5:H5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('17365D');
        $h->getStyle('A5:H5')->getFont()->setBold(true)->setSize(8)->getColor()->setRGB('FFFFFF');
        $h->getStyle('A5:H5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $h->getStyle('A5:H5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $h->getRowDimension(5)->setRowHeight(26);

        $r = 6;
        $grupos = $d['grupos'];
        if ($d['sinPartida']) $grupos[] = $d['sinPartida'];
        foreach ($grupos as $g) {
            $h->mergeCells("A$r:H$r");
            $texto("A$r", $g['codigo'] . ' · ' . $g['nombre'] . '  —  subtotal $' . number_format((float)$g['subtotal'], 2));
            $h->getStyle("A$r:H$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
            $h->getStyle("A$r")->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('0F172A');
            $h->getStyle("A$r:H$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $r++;
            foreach ($g['conceptos'] as $c) {
                $texto("A$r", (string)($c['clave'] ?: '—'));
                $texto("B$r", (string)$c['descripcion']);
                $texto("C$r", (string)($c['unidad'] ?: '—'));
                $h->setCellValue("D$r", (float)$c['cantidad']);
                $h->setCellValue("E$r", (float)$c['precio_unitario']);
                $h->setCellValue("F$r", (float)$c['importe']);
                $h->setCellValue("G$r", (float)$c['importe_programado']);
                $h->setCellValue("H$r", (float)$c['pendiente']);
                foreach (['D' => '#,##0.000', 'E' => $money, 'F' => $money, 'G' => $money, 'H' => $money] as $col => $fmt) {
                    $h->getStyle($col . $r)->getNumberFormat()->setFormatCode($fmt);
                    $h->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $h->getStyle("A$r:H$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $h->getStyle("A$r")->getFont()->setName('Courier New')->setSize(8);
                $h->getStyle("B$r")->getFont()->setSize(8);
                $r++;
            }
            // Totales de la partida
            $h->mergeCells("A$r:F$r");
            $texto("A$r", 'Total ' . $g['codigo'] . ' · ' . $g['nombre']);
            $h->setCellValue("G$r", (float)$g['programado']);
            $h->setCellValue("H$r", (float)$g['pendiente']);
            $h->getStyle("A$r:H$r")->getFont()->setBold(true)->setSize(8);
            $h->getStyle("A$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $h->getStyle("G$r:H$r")->getNumberFormat()->setFormatCode($money);
            $h->getStyle("A$r:H$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $r++;
            $r++;   // separación
        }

        // Resumen económico
        $filas = [
            ['Subtotal del catálogo', $t['directo']],
            ['Partidas con presupuesto manual (sin conceptos)', $t['manual'] ?? 0],
            ['Ajustes manuales por partida', $t['ajustes']],
            ['Órdenes de cambio autorizadas', $t['cambios']],
            ['Indirectos', $t['indirectos']],
            ['Financiamiento', $t['financiamiento']],
            ['Utilidad', $t['utilidad']],
            ['IVA', $t['iva']],
        ];
        foreach ($filas as [$etq, $monto]) {
            $h->mergeCells("A$r:F$r");
            $texto("A$r", $etq);
            $h->setCellValue("G$r", (float)$monto);
            $h->getStyle("G$r")->getNumberFormat()->setFormatCode($money);
            $h->getStyle("A$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $h->getStyle("A$r:G$r")->getFont()->setSize(8);
            $r++;
        }
        $h->mergeCells("A$r:F$r");
        $texto("A$r", 'TOTAL' . (!$t['iva_en_bac'] ? ' (sin IVA en el presupuesto vigente)' : ''));
        $h->setCellValue("G$r", (float)$t['total']);
        $h->getStyle("A$r:G$r")->getFont()->setBold(true)->setSize(10);
        $h->getStyle("A$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $h->getStyle("G$r")->getNumberFormat()->setFormatCode($money);
        $h->getStyle("A$r:G$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $h->getStyle("A$r:G$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (['A' => 15, 'B' => 62, 'C' => 9, 'D' => 12, 'E' => 14, 'F' => 16, 'G' => 16, 'H' => 15] as $col => $ancho) $h->getColumnDimension($col)->setWidth($ancho);
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
