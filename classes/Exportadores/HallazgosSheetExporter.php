<?php

namespace Classes\Exportadores;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reporte de hallazgos agrupados por problema en hoja de cálculo (.ods): resumen por problema y detalle.
 * Lo alimenta HallazgosIAController::exportar().
 */
class HallazgosSheetExporter extends BaseExporter
{
    private array $datos = [];

    public function setDatos(array $datos): self
    {
        $this->datos = $datos;
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $d = $this->datos;
        if (empty($d['lista'])) throw new \Exception('No hay hallazgos que cumplan los filtros para exportar.');
        @ini_set('memory_limit', '512M');
        $sev = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
        $est = ['pendiente' => 'Pendiente', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada'];
        $esMat = $d['herramienta'] === 'analisis-materiales';
        $entidades = fn($x) => $esMat ? implode(', ', array_map(fn($m) => $m['nombre'], $x['materiales'] ?? [])) : implode(', ', array_map(fn($a) => trim($a['codigo'] . ' ' . $a['nombre']), $x['actividades'] ?? []));
        $L = fn(int $c) => Coordinate::stringFromColumnIndex($c);

        $libro = new Spreadsheet();
        // ---- Resumen ----
        $h = $libro->getActiveSheet();
        $h->setTitle('Resumen por problema');
        $h->mergeCells('A1:G1');
        $h->setCellValueExplicit('A1', $d['titulo'] . ' — ' . $this->proyecto->nombre, DataType::TYPE_STRING);
        $h->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $h->mergeCells('A2:G2');
        $h->setCellValueExplicit('A2', 'Generado ' . $d['generado'] . ($d['corrida'] ? ' · ' . ($d['corrida']['nombre'] ?: 'Análisis') . ' · ' . date('d/m/Y H:i', strtotime($d['corrida']['creado'])) : ''), DataType::TYPE_STRING);
        $h->mergeCells('A3:G3');
        $h->setCellValueExplicit('A3', count($d['lista']) . ' hallazgo(s) · ' . count($d['grupos']) . ' problema(s) distinto(s)', DataType::TYPE_STRING);

        $cab = ['Problema', 'Categoría', 'Severidad', 'Total', 'Pendientes', 'Aceptados', 'Rechazados'];
        foreach ($cab as $i => $t) $h->setCellValueExplicit([$i + 1, 5], $t, DataType::TYPE_STRING);
        $h->getStyle('A5:G5')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $h->getStyle('A5:G5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
        $r = 5;
        foreach ($d['grupos'] as $g) {
            $r++;
            $h->setCellValueExplicit([1, $r], $g['etiqueta'], DataType::TYPE_STRING);
            $h->setCellValueExplicit([2, $r], $d['categorias'][$g['categoria']] ?? $g['categoria'], DataType::TYPE_STRING);
            $h->setCellValueExplicit([3, $r], $sev[$g['severidad']] ?? $g['severidad'], DataType::TYPE_STRING);
            $h->setCellValue([4, $r], (int)$g['total']);
            $h->setCellValue([5, $r], (int)$g['pendientes']);
            $h->setCellValue([6, $r], count(array_filter($g['items'], fn($x) => $x['estado'] === 'aceptada')));
            $h->setCellValue([7, $r], count(array_filter($g['items'], fn($x) => $x['estado'] === 'rechazada')));
        }
        $h->getStyle("A5:G$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $h->getStyle("A5:G$r")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach (range(1, 7) as $c) $h->getColumnDimension($L($c))->setWidth([46, 24, 12, 10, 12, 12, 12][$c - 1]);
        $h->getStyle("A5:G$r")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        // ---- Detalle ----
        $h2 = $libro->createSheet();
        $h2->setTitle('Hallazgos');
        $cab2 = [$esMat ? 'Material(es)' : 'Actividad(es)', 'Problema', 'Categoría', 'Severidad', 'Origen', 'Título', 'Descripción', 'Solución propuesta', 'Acción', 'Estado', 'Resuelto por', 'Fecha'];
        foreach ($cab2 as $i => $t) $h2->setCellValueExplicit([$i + 1, 1], $t, DataType::TYPE_STRING);
        $h2->getStyle('A1:' . $L(count($cab2)) . '1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $h2->getStyle('A1:' . $L(count($cab2)) . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
        $fila = 1;
        foreach ($d['grupos'] as $g) foreach ($g['items'] as $x) {
            $fila++;
            $vals = [$entidades($x), $g['etiqueta'], $d['categorias'][$x['categoria']] ?? $x['categoria'], $sev[$x['severidad']] ?? $x['severidad'],
                $x['origen'] === 'ia' ? 'IA' : 'Sistema', $x['titulo'], $x['descripcion'], $x['solucion']['texto'] ?? '', $x['solucion']['accion'] ?? 'nota',
                $est[$x['estado']] ?? $x['estado'], $x['resuelto_por'] ?: '', $x['resuelto_en'] ? date('d/m/Y H:i', strtotime($x['resuelto_en'])) : ''];
            foreach ($vals as $i => $v) $h2->setCellValueExplicit([$i + 1, $fila], (string)$v, DataType::TYPE_STRING);
        }
        $h2->getStyle('A1:' . $L(count($cab2)) . $fila)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $h2->getStyle('A1:' . $L(count($cab2)) . $fila)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach ([42, 26, 16, 10, 9, 38, 55, 50, 16, 11, 18, 16] as $i => $w) $h2->getColumnDimension($L($i + 1))->setWidth($w);
        $h2->freezePane('A2');
        $h2->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        $h2->getPageSetup()->setFitToPage(true);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'ods');
        IOFactory::createWriter($libro, 'Ods')->save($ruta);
        return $ruta;
    }
}
