<?php

namespace Classes\Exportadores;

use Controllers\MinutaController;
use Classes\Pdf;
use Model\Minuta;

/** Minuta en PDF A4 vertical (los compromisos solo se imprimen si existen) */
class MinutaPdfExporter extends BaseExporter
{
    private Minuta $minuta;

    public function setMinuta(Minuta $m): self
    {
        $this->minuta = $m;
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $dueno = \Model\Usuario::find($this->proyecto->usuario_id);
        $datos = MinutaController::detalle($this->minuta, (int)$this->proyecto->id);
        $datos['img'] = fn(?string $archivo, string $carpeta) => MsrPdfExporter::imagenBase64($archivo, $carpeta);
        $datos['logoEmpresa'] = $dueno && $dueno->logo ? $dueno->logo : $this->proyecto->logo;
        $datos['proyecto'] = $this->proyecto;
        $datos['minuta'] = $this->minuta;
        extract($datos);

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-minuta.php';
        $html = ob_get_clean();

        $pdf = Pdf::generar($html, 'a4', 'portrait', ['isHtml5ParserEnabled' => true]);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf);
        return $ruta;
    }
}
