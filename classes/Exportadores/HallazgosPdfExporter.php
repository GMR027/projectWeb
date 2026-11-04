<?php

namespace Classes\Exportadores;

use Classes\Pdf;

/**
 * Reporte de hallazgos de la IA agrupados por problema (Inconsistencias / Análisis de materiales).
 * Lo alimenta HallazgosIAController::exportar() con la misma lista y filtros de la pantalla.
 */
class HallazgosPdfExporter extends BaseExporter
{
    private array $datos = [];

    public function setDatos(array $datos): self
    {
        $this->datos = $datos;
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        if (empty($this->datos['lista'])) throw new \Exception('No hay hallazgos que cumplan los filtros para exportar.');
        $proyecto = $this->proyecto;
        $datos = $this->datos;
        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-hallazgos.php';
        $html = ob_get_clean();
        $pdf = Pdf::generar($html, 'a4', 'landscape', ['isHtml5ParserEnabled' => true, 'marca' => $this->proyecto]);
        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf);
        return $ruta;
    }
}
