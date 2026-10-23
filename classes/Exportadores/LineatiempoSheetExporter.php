<?php

namespace Classes\Exportadores;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Línea de tiempo activa en hoja de cálculo (.ods), con los filtros del usuario, tabloide horizontal */
class LineatiempoSheetExporter extends BaseExporter
{
    private array $lt = [];
    private array $filtros = [];
    private array $partidasSel = [];
    private string $formato = 'ods';

    public function setDatos(array $lt, array $filtros, array $partidasSel = []): self
    {
        $this->lt = $lt;
        $this->filtros = $filtros;
        $this->partidasSel = $partidasSel;
        return $this;
    }

    public function setFormato(string $formato): self
    {
        $this->formato = $formato === 'ods' ? 'ods' : 'xlsx';
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $lt = $this->lt;
        if (empty($lt['actividades'])) throw new \Exception('No hay actividades que cumplan los filtros para exportar.');
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $idxDe = fn($f) => (int)round((strtotime($f) - strtotime($lt['inicio'])) / 86400);
        $estados = ['no_iniciada' => ['No iniciada', 'ef4444'], 'en_proceso' => ['En proceso', 'd97706'], 'completada' => ['Completada', '22c55e']];
        $MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $DOW = ['', 'L', 'M', 'M', 'J', 'V', 'S', 'D'];
        $L = fn(int $c) => Coordinate::stringFromColumnIndex($c);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Línea de tiempo');

        // Columnas: A = código, B = actividad, C = estado, D = %, E = inicio, F = fin, G = duración, H = restricción; de I en adelante, un día por columna
        $col0 = 9;
        $nDias = count($lt['dias']);
        $ultima = $col0 + $nDias - 1;
        $relleno = fn(string $rango, string $color) => $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        $borde = fn(string $rango) => $hoja->getStyle($rango)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // ---- Encabezado (proyecto, filtros, generado) ----
        $hoja->mergeCells('A1:' . $L($ultima) . '1');
        $hoja->setCellValue('A1', $this->proyecto->nombre . ' — Línea de tiempo activa');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->mergeCells('A2:' . $L($ultima) . '2');
        $hoja->setCellValue('A2', trim($this->proyecto->codigo . ' · ' . $this->proyecto->ubicacion, ' ·')
            . ' · Del ' . date('d/m/Y', strtotime($lt['inicio'])) . ' al ' . date('d/m/Y', strtotime($lt['fin']))
            . ' · Generado ' . date('d/m/Y H:i'));
        $hoja->mergeCells('A3:' . $L($ultima) . '3');
        $hoja->setCellValue('A3', 'Filtros: ' . LineatiempoPdfExporter::textoFiltros($this->filtros, $this->partidasSel)
            . ' · ' . (int)$lt['total'] . ' actividades' . ($lt['conRestriccion'] ? ' (' . (int)$lt['conRestriccion'] . ' con restricción)' : ''));

        // ---- Eje: banda de mes (fila 4) + día con su letra (fila 5) ----
        $meses = [];
        foreach ($lt['dias'] as $i => $d) {
            $u = $meses ? array_key_last($meses) : null;
            if ($u !== null && $meses[$u]['mes'] === $d['mes'] && $meses[$u]['anio'] === $d['anio']) $meses[$u]['i1'] = $i;
            else $meses[] = ['i0' => $i, 'i1' => $i, 'mes' => $d['mes'], 'anio' => $d['anio']];
        }
        foreach ($meses as $m) {
            $ini = $col0 + $m['i0'];
            $fin = $col0 + $m['i1'];
            $hoja->mergeCells($L($ini) . '4:' . $L($fin) . '4');
            $hoja->setCellValue($L($ini) . '4', $MESES[$m['mes']] . ' ' . $m['anio']);
        }
        foreach ($lt['dias'] as $i => $d) {
            $c = $L($col0 + $i);
            $hoja->setCellValueExplicit($c . '5', $d['nro'], DataType::TYPE_NUMERIC);
            $hoja->setCellValue($c . '6', $DOW[(int)$d['dow']]);
        }
        // Estilo del eje (sin combinar filas: cada día mantiene su celda)
        foreach (['A4:' . $L($ultima) . '4', 'A5:' . $L($ultima) . '5', 'A6:' . $L($ultima) . '6'] as $r) {
            $relleno($r, '212942');
            $hoja->getStyle($r)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $hoja->getStyle($r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }
        // Días no hábiles sombreados y «hoy» marcado en el eje (filas de día, no la banda de meses)
        foreach ($lt['dias'] as $i => $d) {
            if (!$d['habil']) $relleno($L($col0 + $i) . '5:' . $L($col0 + $i) . '6', 'BFBFBF');
            if ($d['fecha'] === $lt['hoy']) $relleno($L($col0 + $i) . '5:' . $L($col0 + $i) . '6', 'FF5A1F');
        }

        // ---- Filas: grupo de partida + actividades con su barra ----
        $r = 7;
        $cabeceras = ['Código', 'Actividad', 'Estado', '%', 'Inicio', 'Fin', 'Duración (días)', 'Restricción'];
        foreach ($cabeceras as $k => $texto) {
            $c = $L($k + 1);
            $hoja->mergeCells($c . '7:' . $c . '8');
            $hoja->setCellValue($c . '7', $texto);
        }
        $hoja->mergeCells($L($col0) . '7:' . $L($ultima) . '8');
        $hoja->setCellValue($L($col0) . '7', 'Diagrama (una columna por día)');
        foreach (['A7:' . $L($col0 - 1) . '8', $L($col0) . '7:' . $L($ultima) . '8'] as $rango) {
            $relleno($rango, '0B132B');
            $hoja->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $hoja->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        }
        $r = 9;

        foreach ($lt['partidas'] as $p) {
            $acts = array_values(array_filter($lt['actividades'], fn($a) => (int)$a['partida_id'] === (int)$p['id']));
            if (!$acts) continue;
            $color = ltrim($p['color'] ?: '#3b82f6', '#');

            $hoja->mergeCells('A' . $r . ':' . $L($ultima) . $r);
            $hoja->setCellValue('A' . $r, '◆ ' . $p['codigo'] . ' · ' . $p['nombre'] . ' (' . (int)$p['total'] . ' actividades'
                . ($p['restricciones'] ? ', ' . (int)$p['restricciones'] . ' con restricción' : '') . ')');
            $relleno('A' . $r . ':' . $L($ultima) . $r, 'CFCFCF');
            $hoja->getStyle('A' . $r)->getFont()->setBold(true);
            $r++;

            foreach ($acts as $a) {
                $hoja->setCellValueExplicit('A' . $r, (string)$a['codigo'], DataType::TYPE_STRING);
                $hoja->setCellValue('B' . $r, $a['nombre']);
                [$estTxt, $estColor] = $estados[$a['status']] ?? $estados['no_iniciada'];
                $hoja->setCellValue('C' . $r, $estTxt);
                $hoja->getStyle('C' . $r)->getFont()->getColor()->setRGB($estColor);
                $hoja->setCellValueExplicit('D' . $r, (int)$a['progreso'] / 100, DataType::TYPE_NUMERIC);
                $hoja->getStyle('D' . $r)->getNumberFormat()->setFormatCode('0%');
                $hoja->setCellValueExplicit('E' . $r, $a['fecha_inicio'], DataType::TYPE_STRING);
                $hoja->setCellValueExplicit('F' . $r, $a['fecha_fin'], DataType::TYPE_STRING);
                $hoja->setCellValueExplicit('G' . $r, $a['hito'] ? 'Hito' : (int)$a['duracion_dias'], $a['hito'] ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
                if ($a['restriccion']) {
                    $hoja->setCellValue('H' . $r, '⚠ ' . implode(' · ', array_map('strval', $a['restriccion']['texto'] ?? [])));
                    $hoja->getStyle('H' . $r)->getFont()->getColor()->setRGB('dc2626');
                }

                [$i0, $i1] = [max(0, $idxDe($a['fecha_inicio'])), min($nDias - 1, $idxDe($a['fecha_fin']))];
                if ($i1 < $i0) $i1 = $i0;
                $cIni = $col0 + $i0;
                $cFin = $col0 + $i1;
                if ($a['hito']) {
                    $hoja->setCellValue($L($cIni) . $r, '◆');
                    $hoja->getStyle($L($cIni) . $r)->getFont()->setBold(true)->getColor()->setRGB($color);
                } else {
                    // Barra partida en dos tramos: avance (color aclarado) y pendiente (color de la partida)
                    $diasBarra = $i1 - $i0 + 1;
                    $diasFill = (int)round($diasBarra * min(100, (int)$a['progreso']) / 100);
                    if ($diasFill > 0) $relleno($L($cIni) . $r . ':' . $L($cIni + $diasFill - 1) . $r, ltrim(LineatiempoPdfExporter::claro($color), '#'));
                    if ($diasFill < $diasBarra) $relleno($L($cIni + $diasFill) . $r . ':' . $L($cFin) . $r, $color);
                }
                $r++;
            }
        }
        $finTabla = $r - 1;

        // ---- Formato general ----
        // Bordes solo en las columnas de datos: aplicarlos a cientos de columnas de días
        // (proyectos de años) haría el archivo muy lento de generar sin aportar visualmente.
        $borde('A7:' . $L($col0 - 1) . $finTabla);
        $hoja->getStyle('A9:' . $L($col0 - 1) . $finTabla)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getStyle('B9:B' . $finTabla)->getAlignment()->setWrapText(true);
        $hoja->getStyle('D9:D' . $finTabla)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('A9:' . $L($col0 - 1) . $finTabla)->getFont()->setSize(9);
        $hoja->getColumnDimension('A')->setWidth(8);
        $hoja->getColumnDimension('B')->setWidth(48);
        $hoja->getColumnDimension('C')->setWidth(11);
        $hoja->getColumnDimension('D')->setWidth(6);
        $hoja->getColumnDimension('E')->setWidth(10);
        $hoja->getColumnDimension('F')->setWidth(10);
        $hoja->getColumnDimension('G')->setWidth(8);
        $hoja->getColumnDimension('H')->setWidth(30);
        for ($c = $col0; $c <= $ultima; $c++) $hoja->getColumnDimension($L($c))->setWidth(3.4);
        $hoja->getRowDimension(1)->setRowHeight(20);
        $hoja->freezePane($L($col0) . '9');

        // ---- Impresión: tabloide horizontal, 1 página de ancho ----
        $ps = $hoja->getPageSetup();
        $ps->setPaperSize(PageSetup::PAPERSIZE_TABLOID)->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $ps->setFitToWidth(1)->setFitToHeight(0);
        $hoja->getSheetView()->setZoomScale(85);
        $ps->setRowsToRepeatAtTopByStartAndEnd(4, 8);
        $ps->setPrintArea('A1:' . $L($ultima) . $finTabla);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, $this->formato);
        IOFactory::createWriter($libro, $this->formato === 'ods' ? 'Ods' : 'Xlsx')->save($ruta);
        return $ruta;
    }
}
