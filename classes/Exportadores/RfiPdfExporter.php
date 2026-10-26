<?php

namespace Classes\Exportadores;

use Classes\Pdf;

/** RFI (datos y respuesta) en PDF carta vertical, con las láminas del Editor que tenga adjuntas */
class RfiPdfExporter extends BaseExporter
{
    private array $rfi = [];
    private array $laminas = [];

    public function setRfi(array $rfi, array $laminas = []): self
    {
        $this->rfi = $rfi;
        $this->laminas = $laminas;
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $proyecto = $this->proyecto;
        $rfi = $this->rfi;
        $laminas = $this->laminas;
        $dueno = \Model\Usuario::find($proyecto->usuario_id);
        $img = fn(?string $a, string $c) => MsrPdfExporter::imagenBase64($a, $c);
        $logoE = $img($dueno && $dueno->logo ? $dueno->logo : $proyecto->logo, 'build/img/logos');
        $logoC = $img($proyecto->contratista_logo, 'build/img/logos');

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-rfi.php';
        $html = ob_get_clean();

        $pdf = Pdf::generar($html, 'letter', 'portrait', ['isHtml5ParserEnabled' => true, 'marca' => $proyecto]);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf);
        return $ruta;
    }
}
