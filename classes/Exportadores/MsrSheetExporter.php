<?php

namespace Classes\Exportadores;

use Controllers\MsrController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** MSR en hoja de cálculo (.xlsx / .ods), A4 horizontal, replicando el formato de referencia */
class MsrSheetExporter extends BaseExporter
{
    private const NAVY = '17365D', NEGRO = '0C0C0C', GRIS = '7F7F7F', AZUL = '0070C0', AZUL_OSC = '1F497D', SECCION = '3F3F3F';
    private const COLOR_STATUS = ['A' => '92D050', 'AN' => 'C5E384', 'P' => 'FFEB9C', 'RA' => 'F79646'];
    private const ROSA = 'FF99FF';

    private string $formato = 'xlsx';

    private ?\Model\Msr $msr = null;

    public function setMsr(?\Model\Msr $msr): self
    {
        $this->msr = $msr;
        return $this;
    }

    public function setFormato(string $f): self
    {
        $this->formato = $f === 'ods' ? 'ods' : 'xlsx';
        return $this;
    }

    private static function fecha(?string $f): ?float
    {
        return $f ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime($f)) : null;
    }

    private static function relleno($h, string $rango, string $rgb): void
    {
        $h->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    private static function logo($h, ?string $archivo, string $celda, int $alto, int $offX = 4, int $offY = 3): void
    {
        $ruta = $archivo ? __DIR__ . '/../../public/build/img/logos/' . basename($archivo) : null;
        if (!$ruta || !is_file($ruta)) return;
        if (strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'webp' && function_exists('imagecreatefromwebp')) {
            $tmp = tempnam(sys_get_temp_dir(), 'logo') . '.png';
            imagepng(imagecreatefromwebp($ruta), $tmp);
            $ruta = $tmp;
        }
        $dr = new Drawing();
        $dr->setPath($ruta)->setCoordinates($celda)->setResizeProportional(true)->setHeight($alto)->setOffsetX($offX)->setOffsetY($offY)->setWorksheet($h);
    }

    public function generar(string $nombreBase): string
    {
        $d = MsrController::datos($this->proyecto, $this->msr);
        $p = $this->proyecto;
        $libro = new Spreadsheet();
        $libro->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $h = $libro->getActiveSheet();
        $h->setTitle('MSR');
        $h->setShowGridlines(false);
        $ult = 'Q';
        $texto = fn(string $celda, string $v) => $h->setCellValueExplicit($celda, $v, 's');

        // ---- Encabezado (filas 1-12) ----
        foreach ([1 => 14, 2 => 14, 3 => 14, 4 => 14, 5 => 14, 6 => 14, 7 => 8, 8 => 13, 9 => 13, 10 => 13, 11 => 13, 12 => 8, 13 => 18, 14 => 3.75, 15 => 22.5, 16 => 28.5] as $r => $alto) {
            $h->getRowDimension($r)->setRowHeight($alto);
        }
        // Logo del contratista a la izquierda; título al centro; logo de la empresa en caja a la derecha
        self::logo($h, $p->contratista_logo, 'A1', 60);
        $h->mergeCells('D2:H4');
        $texto('D2', "MSR -MATERIAL STATUS REPORT\nREPORTE DEL STATUS DE LOS MATERIALES");
        $h->getStyle('D2')->getFont()->setBold(true)->setSize(11);
        $h->getStyle('D2')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $h->mergeCells('I1:L6');
        $h->getStyle('I1:L6')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        self::logo($h, $d['logoEmpresa'], 'J1', 66, 20, 6);

        // Datos del proyecto (derecha)
        $h->mergeCells('O1:Q1');
        $texto('O1', 'FECHA: ' . fecha_larga_es());
        $h->getStyle('O1')->getFont()->setBold(true)->setSize(8);
        $h->getStyle('O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $filas = [2 => ['CLIENTE:', $p->contratista], 3 => ['PROYECTO:', $p->nombre], 4 => ['JOB No.', $p->numero_trabajo], 5 => ['LOCALIZACIÓN:', $p->ubicacion]];
        foreach ($filas as $r => [$k, $v]) {
            $texto("O$r", $k);
            $h->mergeCells("P$r:Q$r");
            $texto("P$r", (string)$v);
            $h->getStyle("O$r")->getFont()->setSize(8);
            $h->getStyle("P$r")->getFont()->setSize(8)->setBold(true);
        }
        $h->getStyle('O1:Q5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Leyenda de status
        $texto('I8', 'STATUS SUBMITTAL');
        $h->getStyle('I8')->getFont()->setBold(true)->setSize(9);
        $ley = [9 => ['A', 'Aprobado', 'A'], 10 => ['AN', 'Aprobado con anotaciones', 'AN'], 11 => ['P', 'En revisión', 'P'], 12 => ['RA', 'Resubmitar para aprobación', 'RA']];
        foreach ($ley as $r => [$k, $t, $st]) {
            $texto("I$r", $k);
            $h->mergeCells("J$r:L$r");
            $texto("J$r", $t);
            self::relleno($h, "I$r", self::COLOR_STATUS[$st]);
            $h->getStyle("I$r")->getFont()->setBold(true)->setSize(9);
            $h->getStyle("I$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $h->getStyle("J$r")->getFont()->setSize(9);
        }
        $h->getStyle('I9:L11')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $h->mergeCells('O8:Q8');
        $texto('O8', 'Identificación tiempo de entrega');
        $h->getStyle('O8')->getFont()->setBold(true)->setSize(9);
        $h->mergeCells('P9:Q9');
        $texto('P9', 'Tiempo de entrega de Material Crítico');
        $h->getStyle('P9')->getFont()->setSize(9);
        self::relleno($h, 'O9', self::ROSA);
        $h->getStyle('O8:Q9')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // ---- Barra de grupos y encabezados: las columnas, nombres, colores y orden que eligió el usuario ----
        $cols = $d['columnas'];
        $n = max(1, count($cols));
        $L = fn(int $i) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $ultC = $L($n);
        $rgb = fn(string $hex) => strtoupper(ltrim($hex, '#'));
        $tinta = function (string $hex) {
            $x = ltrim($hex, '#');
            return (0.299 * hexdec(substr($x, 0, 2)) + 0.587 * hexdec(substr($x, 2, 2)) + 0.114 * hexdec(substr($x, 4, 2))) / 255 > 0.62 ? 'FF111111' : 'FFFFFFFF';
        };
        $blanco = new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF');
        $pos = 1;
        foreach ($d['grupos'] as $g) {
            $ini = $L($pos); $fin = $L($pos + $g['span'] - 1);
            if ($g['span'] > 1) $h->mergeCells("{$ini}13:{$fin}13");
            if ($g['n'] !== '') {
                $texto("{$ini}13", $g['n']);
                self::relleno($h, "{$ini}13:{$fin}13", $rgb($g['c']));
                $h->getStyle("{$ini}13")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($tinta($g['c'])));
            }
            $h->getStyle("{$ini}13:{$fin}13")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $pos += $g['span'];
        }
        foreach ($cols as $i => $col) {
            $letra = $L($i + 1);
            $h->mergeCells("{$letra}15:{$letra}16");
            $texto("{$letra}15", $col['n']);
            self::relleno($h, "{$letra}15:{$letra}16", $rgb($col['c']));
            $h->getStyle("{$letra}15")->getFont()->setBold(true)->setSize(7)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($tinta($col['c'])));
        }
        $est = $h->getStyle("A15:{$ultC}16");
        $est->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $est->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

        // ---- Renglones ----
        $idx = [];
        foreach ($cols as $i => $col) $idx[$col['k']] = $i + 1;
        $r = 16;
        foreach ($d['secciones'] as $nombreSec => $lista) {
            if ($nombreSec !== '') {
                $r++;
                $h->getRowDimension($r)->setRowHeight(15);
                $cs = $d['coloresSec'][$nombreSec] ?? '#3F3F3F';
                $texto("A$r", $nombreSec);
                $h->mergeCells("A$r:{$ultC}$r");
                self::relleno($h, "A$r:{$ultC}$r", $rgb($cs));
                $h->getStyle("A$r")->getFont()->setBold(true)->setSize(6)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($tinta($cs)));
                $h->getStyle("A$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            foreach ($lista as $it) {
                $r++;
                foreach ($cols as $i => $col) {
                    $k = $col['k']; $c = $i + 1; $letra = $L($c);
                    if ($k === 'imagen') continue;
                    if ($k === 'dias') {
                        if (($v = $it->diasEntrega()) !== null) $h->setCellValue([$c, $r], $v);
                        $h->getStyle([$c, $r])->getNumberFormat()->setFormatCode('0.00');
                        if ($it->esCritico($d['umbral'])) self::relleno($h, "$letra$r", self::ROSA);
                    } elseif (str_starts_with($k, 'fecha')) {
                        if (($v = self::fecha($it->$k)) !== null) $h->setCellValue([$c, $r], $v);
                        $h->getStyle([$c, $r])->getNumberFormat()->setFormatCode('dd-mmm-yy');
                    } else {
                        $v = match (true) {
                            $k === 'sub_numero' => $it->sub_numero, $k === 'referencia' => $it->mat_referencia, $k === 'descripcion' => $it->mat_nombre,
                            str_starts_with($k, 'txt_') => $it->extra($k), default => (string)$it->$k,
                        };
                        if ($v !== null && $v !== '') $h->setCellValueExplicit([$c, $r], (string)$v, 's');
                        if ($k === 'status') self::relleno($h, "$letra$r", self::COLOR_STATUS[$it->status] ?? 'FFFFFF');
                        if ($k === 'proveedor') $h->getStyle("$letra$r")->getFont()->getColor()->setRGB(self::AZUL_OSC);
                    }
                }
                $h->getRowDimension($r)->setRowHeight(50);
                if (isset($idx['imagen']) && ($ruta = \Classes\Imagenes::ruta($it->mat_imagen))) {
                    $dr = new Drawing();
                    $dr->setPath($ruta)->setCoordinates($L($idx['imagen']) . $r)->setResizeProportional(true)->setHeight(46)->setOffsetX(8)->setOffsetY(2)->setWorksheet($h);
                }
            }
        }
        if ($r > 16) {
            $h->getStyle("A17:{$ultC}$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);
            $h->getStyle("A17:{$ultC}$r")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            foreach ($cols as $i => $col) {
                $k = $col['k'];
                if (!in_array($k, ['descripcion', 'observaciones', 'proveedor'], true) && !str_starts_with($k, 'txt_')) {
                    $h->getStyle($L($i + 1) . "17:" . $L($i + 1) . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
            if (isset($idx['status'])) {
                $ls = $L($idx['status']);
                $h->getStyle("{$ls}17:{$ls}$r")->getFont()->setBold(true);
                // Color automático de Status al editar el valor en la hoja
                $condiciones = [];
                foreach (self::COLOR_STATUS as $v => $color) {
                    $cnd = new Conditional();
                    $cnd->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition('"' . $v . '"');
                    $cnd->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                    $cnd->getStyle()->getFill()->getEndColor()->setRGB($color);
                    $condiciones[] = $cnd;
                }
                $h->getStyle("{$ls}17:{$ls}$r")->setConditionalStyles($condiciones);
            }
        }

        $anchos = ['imagen' => 12.6, 'sub_numero' => 15.75, 'referencia' => 11, 'descripcion' => 48, 'proveedor' => 19, 'observaciones' => 32.75];
        foreach ($cols as $i => $col) {
            $h->getColumnDimension($L($i + 1))->setWidth($anchos[$col['k']] ?? (str_starts_with($col['k'], 'txt_') ? 24 : 14.4));
        }
        $h->freezePane('A17');
        $h->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $h->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(13, 16);
        $h->getPageSetup()->setFitToPage(true);
        $h->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.25)->setRight(0.25);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, $this->formato);
        IOFactory::createWriter($libro, $this->formato === 'ods' ? 'Ods' : 'Xlsx')->save($ruta);
        return $ruta;
    }
}
