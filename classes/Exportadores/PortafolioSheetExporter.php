<?php

namespace Classes\Exportadores;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Tablero de portafolio en hoja de cálculo (.xlsx) para la reunión de dirección */
class PortafolioSheetExporter extends BaseExporter
{
    private array $filas = [];
    private array $resumen = [];
    private array $filtros = [];
    private string $formato = 'xlsx';

    public function setDatos(array $filas, array $resumen, array $filtros = []): self
    {
        $this->filas = $filas;
        $this->resumen = $resumen;
        $this->filtros = $filtros;
        return $this;
    }

    public function setFormato(string $formato): self
    {
        $this->formato = $formato === 'ods' ? 'ods' : 'xlsx';
        return $this;
    }

    protected function prefijoNombre(): string
    {
        return 'portafolio';
    }

    public function generar(string $nombreBase): string
    {
        if (!$this->filas) throw new \Exception('No hay obras que cumplan los filtros para exportar.');
        @ini_set('memory_limit', '512M');
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Portafolio');
        $r = $this->resumen;

        $hoja->mergeCells('A1:J1');
        $hoja->setCellValue('A1', 'Tablero de portafolio');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $hoja->mergeCells('A2:J2');
        $hoja->setCellValue('A2', (int)$r['total'] . ' obra(s) · Generado ' . date('d/m/Y H:i'));
        $hoja->getStyle('A2')->getFont()->setItalic(true);

        $kpis = [
            'Con alertas' => (int)$r['con_alertas'], 'PPC medio' => $r['ppc'] === null ? '—' : $r['ppc'] . ' %',
            'CPI medio' => $r['cpi'] ?? '—', 'SPI medio' => $r['spi'] ?? '—',
            'Avance medio' => $r['real'] === null ? '—' : $r['real'] . ' %',
        ];
        $c = 1;
        foreach ($kpis as $k => $v) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($c) . '4', $k);
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($c) . '5', $v);
            $c++;
        }

        $enc = ['Obra', 'Código', 'Cliente', 'Avance real %', 'Avance plan %', 'SPI', 'CPI', 'PPC %', 'Atrasadas', 'Punch', 'Salud', 'Alertas'];
        foreach ($enc as $i => $t) $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '7', $t);
        $hoja->getStyle('A7:L7')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A7:L7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F172A');
        $hoja->getStyle('A7:L7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $fila = 8;
        $rot = ['ok' => 'En orden', 'aviso' => 'Atención', 'critico' => 'Urgente'];
        foreach ($this->filas as $f) {
            $alertas = $f['alertas'] ? implode(' · ', array_map(fn($a) => $a['mensaje'], $f['alertas'])) : '';
            $vals = [$f['nombre'], $f['codigo'], $f['cliente'], $f['real'], $f['plan'], $f['spi'], $f['cpi'], $f['ppc'], $f['atrasadas'], $f['punch'], $rot[$f['salud']] ?? $f['salud'], $alertas];
            foreach ($vals as $i => $v) {
                $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $fila, $v === null ? '—' : $v);
            }
            $hoja->getStyle('A' . $fila . ':L' . $fila)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR);
            $fila++;
        }
        foreach (['A' => 34, 'B' => 12, 'C' => 24, 'D' => 13, 'E' => 13, 'F' => 8, 'G' => 8, 'H' => 8, 'I' => 10, 'J' => 8, 'K' => 11, 'L' => 60] as $col => $w) $hoja->getColumnDimension($col)->setWidth($w);
        $hoja->getStyle('D8:H' . max(8, $fila - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $hoja->freezePane('A8');

        $ext = $this->formato;
        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, $ext);
        IOFactory::createWriter($libro, $ext === 'ods' ? 'Ods' : 'Xlsx')->save($ruta);
        return $ruta;
    }
}
