<?php

namespace Classes\Exportadores;

use Model\Submittal;
use Classes\Pdf;

/** Paquete de submittal (datos + tabla de materiales) en PDF carta vertical */
class SubmittalPdfExporter extends BaseExporter
{
    private Submittal $submittal;

    public function setSubmittal(Submittal $s): self
    {
        $this->submittal = $s;
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $proyecto = $this->proyecto;
        $submittal = $this->submittal;
        $materiales = $submittal->materiales();
        $dueno = \Model\Usuario::find($proyecto->usuario_id);
        $img = fn(?string $a, string $c) => MsrPdfExporter::imagenBase64($a, $c);
        $logoE = $img($dueno && $dueno->logo ? $dueno->logo : $proyecto->logo, 'build/img/logos');
        $logoC = $img($proyecto->contratista_logo, 'build/img/logos');

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-submittal.php';
        $html = ob_get_clean();

        $pdf = Pdf::generar($html, 'letter', 'portrait', ['isHtml5ParserEnabled' => true]);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf);
        return $ruta;
    }
}
