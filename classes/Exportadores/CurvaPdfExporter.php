<?php

namespace Classes\Exportadores;

use Controllers\CurvaController;
use Model\Curva;
use Classes\CurvaProductividad;
use Classes\Pdf;

/** Curva de productividad (o gráfica maestra) en PDF carta horizontal */
class CurvaPdfExporter extends BaseExporter
{
    private ?Curva $curva = null;

    /** null = gráfica maestra */
    public function setCurva(?Curva $c): self
    {
        $this->curva = $c;
        return $this;
    }

    /** Estructura común para PDF y hoja: título, columnas, filas de tabla y PNG de la gráfica */
    public static function armar(\Model\Proyecto $proyecto, ?Curva $curva): array
    {
        if ($curva) {
            $s = CurvaProductividad::series($curva);
            $u = $curva->unidad;
            return [
                'titulo' => mb_strtoupper($curva->nombre),
                'cols' => array_map(fn($f) => date('n/j/y', strtotime($f)), $s['fechas']),
                'filas' => [
                    "Planned $u" => $s['planeado'], "Actual $u" => $s['actual'],
                    "Planned Cumulative $u" => $s['planeado_acum'], "Actual Cumulative $u" => $s['actual_acum'],
                ],
                'png' => GraficaGd::curva($s, $curva->nombre, $u),
                'decimales' => 0,
            ];
        }
        $m = CurvaController::datosMaestra($proyecto);
        $filas = [];
        foreach ($m['series'] as $s) {
            $filas[$s['nombre'] . ' · plan %'] = $s['planeado'];
            $filas[$s['nombre'] . ' · real %'] = $s['real'];
        }
        return [
            'titulo' => 'GRÁFICA MAESTRA · ' . mb_strtoupper($proyecto->nombre),
            'cols' => array_map(fn($f) => date('d/m/y', strtotime($f)), $m['semanas']),
            'filas' => $filas,
            'png' => GraficaGd::maestra($m, 'GRÁFICA MAESTRA'),
            'decimales' => 1,
        ];
    }

    protected function prefijoNombre(): string
    {
        return $this->curva ? $this->curva->nombre : 'Maestra';
    }

    public function generar(string $nombreBase): string
    {
        $d = self::armar($this->proyecto, $this->curva);
        $proyecto = $this->proyecto;
        $png = 'data:image/png;base64,' . base64_encode($d['png']);
        $esMaestra = $this->curva === null;

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-curva.php';
        $html = ob_get_clean();

        $pdf = Pdf::generar($html, 'letter', 'landscape', ['isHtml5ParserEnabled' => true, 'marca' => $proyecto]);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf);
        return $ruta;
    }
}
