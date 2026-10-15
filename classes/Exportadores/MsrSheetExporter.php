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

        // ---- Barras de grupo y encabezados de columna ----
        $h->mergeCells('A13:I13');
        $texto('A13', 'STATUS DEL SUBMITTAL');
        $h->mergeCells('J13:Q13');
        $texto('J13', 'STATUS DE ENTREGA DEL MATERIAL O EQUIPO');
        self::relleno($h, 'A13:I13', self::NAVY);
        self::relleno($h, 'J13:Q13', self::AZUL);
        $h->getStyle('A13:Q13')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $h->getStyle('A13:Q13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $heads = [
            ['ITEM PROLOG', self::NAVY], ['Subcontractor submittal number', self::NAVY], ['Spec Number', self::NAVY], ['DESCRIPCIÓN', self::NAVY],
            ["FECHA REQUERIDA DE ENVIO DE SUBMITTAL (PROG)", self::NEGRO], ['No. SUBMITTAL ' . mb_strtoupper($p->contratista ?: 'CONTRATISTA'), self::GRIS],
            ['Real Submittal Date', self::GRIS], ['AnswerDate', self::GRIS], ['Status', self::GRIS],
            ['Delivery Time Schedule (Days)', self::AZUL], ['FECHA COMPRA DE MATERIAL (PROG)', self::AZUL], ['FECHA DE INSTALACION (PROG)', self::AZUL],
            ['FECHA DE COMPRA (REAL)', self::AZUL_OSC], ['FECHA DE ENTREGA (PROG)', self::AZUL_OSC], ['FECHA DE ENTREGA (REAL)', self::AZUL_OSC],
            ['DATOS DEL PROVEEDOR', self::AZUL], ['OBSERVACIONES', self::NAVY],
        ];
        foreach ($heads as $i => [$t, $color]) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $h->mergeCells("{$col}15:{$col}16");
            $texto("{$col}15", $t);
            self::relleno($h, "{$col}15:{$col}16", $color);
        }
        $est = $h->getStyle('A15:Q16');
        $est->getFont()->setBold(true)->setSize(7)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $est->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $est->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

        // ---- Renglones ----
        $r = 16;
        foreach ($d['secciones'] as $nombreSec => $lista) {
            if ($nombreSec !== '') {
                $r++;
                $h->getRowDimension($r)->setRowHeight(15);
                $texto("D$r", $nombreSec);
                self::relleno($h, "A$r:Q$r", self::SECCION);
                $h->getStyle("A$r:Q$r")->getFont()->setBold(true)->setSize(6)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
                $h->getStyle("D$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            foreach ($lista as $it) {
                $r++;
                $vals = [
                    2 => $it->sub_numero, 3 => $it->mat_referencia, 4 => $it->mat_nombre,
                    5 => self::fecha($it->fecha_envio_requerida), 6 => $it->no_submittal_contratista, 7 => self::fecha($it->fecha_envio_real),
                    8 => self::fecha($it->fecha_respuesta), 9 => $it->status, 10 => $it->diasEntrega(),
                    11 => self::fecha($it->fecha_compra_prog), 12 => self::fecha($it->fecha_instalacion_prog), 13 => self::fecha($it->fecha_compra_real),
                    14 => self::fecha($it->fecha_entrega_prog), 15 => self::fecha($it->fecha_entrega_real), 16 => $it->proveedor, 17 => $it->observaciones,
                ];
                foreach ($vals as $c => $v) {
                    if ($v === null || $v === '') continue;
                    in_array($c, [2, 3, 4, 6, 9, 16, 17], true) ? $h->setCellValueExplicit([$c, $r], (string)$v, 's') : $h->setCellValue([$c, $r], $v);
                }
                $h->getStyle("J$r")->getNumberFormat()->setFormatCode('0.00');
                foreach ([5, 7, 8, 11, 12, 13, 14, 15] as $c) {
                    $h->getStyle([$c, $r])->getNumberFormat()->setFormatCode('dd-mmm-yy');
                }
                self::relleno($h, "I$r", self::COLOR_STATUS[$it->status] ?? 'FFFFFF');
                if ($it->esCritico($d['umbral'])) self::relleno($h, "J$r", self::ROSA);
                $h->getStyle("P$r")->getFont()->getColor()->setRGB(self::AZUL_OSC);
                $h->getRowDimension($r)->setRowHeight(50);

                if ($ruta = \Classes\Imagenes::ruta($it->mat_imagen)) {
                    $dr = new Drawing();
                    $dr->setPath($ruta)->setCoordinates("A$r")->setResizeProportional(true)->setHeight(46)->setOffsetX(8)->setOffsetY(2)->setWorksheet($h);
                }
            }
        }
        if ($r > 16) {
            $h->getStyle("A17:Q$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);
            $h->getStyle("B17:Q$r")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            foreach (['B', 'C', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'] as $col) {
                $h->getStyle("{$col}17:$col$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $h->getStyle("I17:I$r")->getFont()->setBold(true);

            // Color automático de Status al editar el valor en la hoja (como en el archivo de referencia)
            $reglas = [];
            $reglas['A'] = self::COLOR_STATUS['A'];
            $reglas['AN'] = self::COLOR_STATUS['AN'];
            $reglas['P'] = self::COLOR_STATUS['P'];
            $reglas['RA'] = self::COLOR_STATUS['RA'];
            $condiciones = [];
            foreach ($reglas as $v => $color) {
                $c = new Conditional();
                $c->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition('"' . $v . '"');
                $c->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                $c->getStyle()->getFill()->getEndColor()->setRGB($color);
                $condiciones[] = $c;
            }
            $h->getStyle("I17:I$r")->setConditionalStyles($condiciones);
        }

        foreach (['A' => 12.6, 'B' => 15.75, 'C' => 11, 'D' => 48, 'E' => 16, 'F' => 14, 'G' => 13, 'H' => 13, 'I' => 14.75, 'J' => 14.4, 'K' => 14.4, 'L' => 12.75, 'M' => 13.9, 'N' => 12.75, 'O' => 15, 'P' => 19, 'Q' => 32.75] as $col => $w) {
            $h->getColumnDimension($col)->setWidth($w);
        }
        $h->freezePane('E17');
        $h->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $h->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(13, 16);
        $h->getPageSetup()->setFitToPage(true);
        $h->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.25)->setRight(0.25);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, $this->formato);
        IOFactory::createWriter($libro, $this->formato === 'ods' ? 'Ods' : 'Xlsx')->save($ruta);
        return $ruta;
    }
}
