<?php

namespace Classes\Exportadores;

use Controllers\MinutaController;
use Dompdf\Dompdf;
use Dompdf\Options;
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

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $dompdf->output());
        return $ruta;
    }
}
