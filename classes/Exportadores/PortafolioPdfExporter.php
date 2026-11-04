<?php

namespace Classes\Exportadores;

use Classes\Pdf;

/** Tablero de portafolio en PDF horizontal: una fila por obra con sus indicadores y alertas */
class PortafolioPdfExporter extends BaseExporter
{
    private array $filas = [];
    private array $resumen = [];
    private array $filtros = [];

    public function setDatos(array $filas, array $resumen, array $filtros = []): self
    {
        $this->filas = $filas;
        $this->resumen = $resumen;
        $this->filtros = $filtros;
        return $this;
    }

    protected function prefijoNombre(): string
    {
        return 'portafolio';
    }

    public function generar(string $nombreBase): string
    {
        if (!$this->filas) throw new \Exception('No hay obras que cumplan los filtros para exportar.');
        $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $num = fn($v, $d = 2) => $v === null ? '—' : number_format((float)$v, $d);
        $pct = fn($v) => $v === null ? '—' : number_format((float)$v, 1) . ' %';
        $rot = ['ok' => 'En orden', 'aviso' => 'Atención', 'critico' => 'Urgente'];

        $filas = '';
        foreach ($this->filas as $f) {
            $alertas = $f['alertas'] ? implode(' · ', array_map(fn($a) => $a['mensaje'], $f['alertas'])) : '—';
            $filas .= '<tr>'
                . '<td><strong>' . $e($f['codigo']) . '</strong><br>' . $e($f['nombre']) . '<br><small>' . $e($f['cliente']) . '</small></td>'
                . '<td class="n">' . $pct($f['real']) . '</td><td class="n">' . $pct($f['plan']) . '</td>'
                . '<td class="n">' . $num($f['spi']) . '</td><td class="n">' . $num($f['cpi']) . '</td><td class="n">' . $pct($f['ppc']) . '</td>'
                . '<td class="n">' . (int)$f['atrasadas'] . '</td><td class="n">' . (int)$f['punch'] . '</td>'
                . '<td>' . $e($rot[$f['salud']] ?? $f['salud']) . '</td><td><small>' . $e($alertas) . '</small></td>'
                . '</tr>';
        }
        $r = $this->resumen;
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            @page { margin: 22px; }
            body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #1e293b; }
            h1 { font-size: 16px; margin: 0 0 4px; }
            .sub { color: #64748b; margin-bottom: 10px; }
            .kpis { margin-bottom: 10px; }
            .kpis span { display: inline-block; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px; margin-right: 6px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #0f172a; color: #fff; text-align: left; padding: 5px; font-size: 8px; }
            td { border-bottom: 1px solid #e2e8f0; padding: 5px; vertical-align: top; }
            .n { text-align: right; white-space: nowrap; }
            tr:nth-child(even) td { background: #f8fafc; }
        </style></head><body>';
        $html .= '<h1>Tablero de portafolio</h1><div class="sub">' . $e($this->proyecto->empresa ?: 'Programa de obra')
            . ' · ' . (int)$r['total'] . ' obra(s) · Generado ' . date('d/m/Y H:i') . '</div>';
        $html .= '<div class="kpis">'
            . '<span>Con alertas: <strong>' . (int)$r['con_alertas'] . '</strong></span>'
            . '<span>PPC medio: <strong>' . $pct($r['ppc']) . '</strong></span>'
            . '<span>CPI medio: <strong>' . $num($r['cpi']) . '</strong></span>'
            . '<span>SPI medio: <strong>' . $num($r['spi']) . '</strong></span>'
            . '<span>Avance medio: <strong>' . $pct($r['real']) . '</strong></span></div>';
        $html .= '<table><thead><tr><th>Obra</th><th>Avance real</th><th>Avance plan</th><th>SPI</th><th>CPI</th><th>PPC</th><th>Atrasadas</th><th>Punch</th><th>Salud</th><th>Alertas</th></tr></thead><tbody>'
            . $filas . '</tbody></table></body></html>';

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, Pdf::generar($html, 'letter', 'landscape'));
        return $ruta;
    }
}
