<?php

namespace Classes\Exportadores;

use Controllers\LookaheadController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** 4WLA en hoja de cálculo editable (.xlsx / .ods), tabloide horizontal */
class LookaheadSheetExporter extends BaseExporter
{
    private \DateTime $lunes;
    private string $formato = 'xlsx';

    private const COLOR_X = ['completada' => '16A34A', 'en_proceso' => 'B7791F', 'no_iniciada' => '555555', 'restriccion' => 'DC2626'];

    public function setFechaReferencia(string $fechaYmd): self
    {
        $this->lunes = LookaheadController::lunesDe($fechaYmd);
        return $this;
    }

    public function setFormato(string $formato): self
    {
        $this->formato = $formato === 'ods' ? 'ods' : 'xlsx';
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $p = $this->proyecto;
        $lunes = $this->lunes ?? LookaheadController::lunesDe('');
        $d = LookaheadController::datos($p, $lunes);
        $semanas = $d['semanas'];
        $tipos = $d['tipos'];

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('4WLA');

        // Mapa de columnas: A=ID, B=Actividad, semana actual, %Comp, PTA, 4 semanas, limitaciones
        $col = 3;
        $colDia = [];   // fecha => índice de columna
        $colSemIni = [];
        foreach ($semanas as $i => $sem) {
            $colSemIni[$i] = $col;
            foreach ($sem['dias'] as $dia) {
                $colDia[$dia['fecha']] = $col++;
            }
            if ($i === 0) {
                $colComp = $col++;
                $colPta = $col++;
            }
        }
        $colTipoIni = $col;
        $ultima = $colTipoIni + count($tipos) - 1;
        $L = fn(int $c) => Coordinate::stringFromColumnIndex($c);

        $azulOscuro = '0A2A6B';
        $azul = '0B6FC0';
        $gris = 'D9D9D9';
        $relleno = fn(string $rango, string $color) => $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);

        // ---- Encabezado ----
        $hoja->mergeCells('A1:B5');
        $hoja->setCellValue('A1', $p->nombre . "\nInicio: " . date('d/m/Y', strtotime($p->fecha_inicio))
            . '   Fin: ' . date('d/m/Y', strtotime($p->fecha_fin))
            . "\nGenerado: " . date('d/m/Y H:i'));
        $hoja->getStyle('A1')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_BOTTOM);
        // Logos en la parte superior del bloque: empresa a la izquierda y contratista a su derecha
        foreach ([[$p->logoEmpresa(), 'A1', 4], [$p->contratista_logo, 'B1', 260]] as [$logo, $celda, $offX]) {
            $ruta = $logo ? __DIR__ . '/../../public/build/img/logos/' . basename($logo) : null;
            if ($ruta && is_file($ruta)) {
                if (strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'webp' && function_exists('imagecreatefromwebp')) {
                    $tmp = tempnam(sys_get_temp_dir(), 'logo') . '.png';
                    imagepng(imagecreatefromwebp($ruta), $tmp);
                    $ruta = $tmp;
                }
                $dr = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                $dr->setPath($ruta)->setCoordinates($celda)->setHeight(48)->setOffsetX($offX)->setOffsetY(4)->setWorksheet($hoja);
            }
        }
        $hoja->getStyle('A1')->getFont()->setBold(true);
        $relleno('A1:B5', $gris);

        $hoja->mergeCells($L(3) . '1:' . $L($colTipoIni - 1) . '1');
        $hoja->setCellValue($L(3) . '1', '4 Weeks Look Ahead · ' . $p->nombre);
        $hoja->mergeCells($L($colTipoIni) . '1:' . $L($ultima) . '1');
        $hoja->setCellValue($L($colTipoIni) . '1', 'Limitaciones');
        $relleno('C1:' . $L($ultima) . '1', $azulOscuro);
        $hoja->getStyle('C1:' . $L($ultima) . '1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('C1:' . $L($ultima) . '1')->getAlignment()->setHorizontal('center')->setVertical('center');

        foreach ($semanas as $i => $sem) {
            $ini = $colSemIni[$i];
            $fin = $ini + 6;
            $hoja->mergeCells($L($ini) . '2:' . $L($fin) . '2');
            $hoja->setCellValue($L($ini) . '2', $sem['etiqueta'] . ' · S' . $sem['iso']);
            $relleno($L($ini) . '2:' . $L($fin) . '2', $i === 0 ? '0A8FB0' : $azul);
            $hoja->mergeCells($L($ini) . '3:' . $L($fin) . '3');
            $hoja->setCellValue($L($ini) . '3', $sem['mes']);
            foreach ($sem['dias'] as $k => $dia) {
                $c = $L($ini + $k);
                $hoja->setCellValue($c . '4', $dia['numero']);
                $hoja->setCellValue($c . '5', $dia['letra']);
                if (!$dia['habil']) $relleno($c . '4:' . $c . '5', 'BFBFBF');
                else $relleno($c . '4:' . $c . '5', $gris);
            }
        }
        $hoja->getStyle('C2:' . $L($colPta) . '2')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('C2:' . $L($ultima) . '5')->getAlignment()->setHorizontal('center')->setVertical('center');

        foreach ([[$colComp, '% Comp'], [$colPta, 'PTA']] as [$c, $txt]) {
            $hoja->mergeCells($L($c) . '2:' . $L($c) . '5');
            $hoja->setCellValue($L($c) . '2', $txt);
            $relleno($L($c) . '2:' . $L($c) . '5', $gris);
            $hoja->getStyle($L($c) . '2')->getFont()->setBold(true)->getColor()->setRGB('000000');
        }
        foreach ($tipos as $k => $t) {
            $c = $L($colTipoIni + $k);
            $hoja->mergeCells($c . '2:' . $c . '5');
            $hoja->setCellValue($c . '2', $t->nombre);
            $hoja->getStyle($c . '2')->getAlignment()->setTextRotation(90);
            $relleno($c . '2:' . $c . '5', $azul);
            $hoja->getStyle($c . '2')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        }
        $hoja->setCellValue('A5', ''); // ID/Actividad ya cubiertos por la celda combinada

        // ---- Filas ----
        $r = 6;
        foreach ($d['filas'] as $bloque) {
            $partida = $bloque['partida'];
            $hoja->setCellValueExplicit('A' . $r, (string)$partida->codigo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $hoja->setCellValue('B' . $r, mb_strtoupper($partida->nombre));
            $relleno('A' . $r . ':' . $L($ultima) . $r, 'CFCFCF');
            $hoja->getStyle('A' . $r . ':B' . $r)->getFont()->setBold(true);
            $r++;
            foreach ($bloque['actividades'] as $f) {
                $act = $f['actividad'];
                $hoja->setCellValueExplicit('A' . $r, (string)$act->codigo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $hoja->setCellValue('B' . $r, $act->nombre);
                if ($f['atrasada']) $hoja->getStyle('B' . $r)->getFont()->getColor()->setRGB('DC2626');

                $colorX = self::COLOR_X[$f['con_restriccion'] ? 'restriccion' : $act->status] ?? '555555';
                foreach ($semanas as $sem) {
                    foreach ($sem['dias'] as $dia) {
                        $c = $L($colDia[$dia['fecha']]) . $r;
                        if (!$dia['habil']) $relleno($c, 'D9D9D9');
                        if ($f['celdas'][$dia['fecha']] ?? false) {
                            $hoja->setCellValue($c, 'X');
                            $hoja->getStyle($c)->getFont()->setBold(true)->getColor()->setRGB($colorX);
                        }
                    }
                }
                $prog = (int)$act->progreso;
                $cc = $L($colComp) . $r;
                $hoja->setCellValue($cc, $prog / 100);
                $hoja->getStyle($cc)->getNumberFormat()->setFormatCode('0%');
                if ($prog >= 100) $relleno($cc, '22C55E');
                elseif ($prog > 0) $relleno($cc, 'FACC15');
                $cp = $L($colPta) . $r;
                $hoja->setCellValue($cp, $f['pta_ok'] ? 'OK' : '!');
                $hoja->getStyle($cp)->getFont()->setBold(true)->getColor()->setRGB($f['pta_ok'] ? '16A34A' : 'DC2626');

                foreach ($tipos as $k => $t) {
                    if (isset($f['tipos'][$t->id])) {
                        $c = $L($colTipoIni + $k) . $r;
                        $hoja->setCellValue($c, '●');
                        $hoja->getStyle($c)->getFont()->getColor()->setRGB(ltrim($t->color, '#'));
                    }
                }
                $r++;
            }
        }
        $fin = $r - 1;

        // ---- Formato general ----
        $hoja->getStyle('A1:' . $L($ultima) . $fin)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $hoja->getStyle('C6:' . $L($ultima) . $fin)->getAlignment()->setHorizontal('center');
        $hoja->getStyle('A6:A' . $fin)->getAlignment()->setHorizontal('center')->setVertical('center');
        $hoja->getStyle('B6:B' . $fin)->getAlignment()->setWrapText(true)->setVertical('center');
        $hoja->getStyle('A1:' . $L($ultima) . $fin)->getFont()->setSize(9);
        $hoja->getColumnDimension('A')->setWidth(8);
        $hoja->getColumnDimension('B')->setWidth(62);
        for ($c = 3; $c <= $ultima; $c++) $hoja->getColumnDimension($L($c))->setWidth(3.6);
        $hoja->getColumnDimension($L($colComp))->setWidth(7);
        $hoja->getColumnDimension($L($colPta))->setWidth(5);
        $hoja->getRowDimension(1)->setRowHeight(22);
        // 4 filas de ~19pt = ~76pt de alto para los rótulos verticales de limitaciones
        for ($i = 2; $i <= 5; $i++) $hoja->getRowDimension($i)->setRowHeight(19);
        $hoja->freezePane('C6');

        // ---- Impresión: tabloide horizontal, 1 página de ancho ----
        $ps = $hoja->getPageSetup();
        $ps->setPaperSize(PageSetup::PAPERSIZE_TABLOID)->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $ps->setFitToWidth(1)->setFitToHeight(0);
        $hoja->getSheetView()->setZoomScale(90);
        $ps->setRowsToRepeatAtTopByStartAndEnd(1, 5);
        $ps->setPrintArea('A1:' . $L($ultima) . $fin);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, $this->formato);
        IOFactory::createWriter($libro, $this->formato === 'ods' ? 'Ods' : 'Xlsx')->save($ruta);
        return $ruta;
    }
}
