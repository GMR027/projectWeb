<?php

namespace Classes\Exportadores;

use Classes\Pdf;

/** Catálogo de conceptos / presupuesto en PDF carta vertical, con marca del cliente y totales */
class PresupuestoPdfExporter extends BaseExporter
{
    private array $datos = [];
    private ?string $fecha = null;

    public function setDatos(array $datos): self
    {
        $this->datos = $datos;
        return $this;
    }

    /** Fecha de emisión del presupuesto */
    public function setFecha(string $fecha): self
    {
        $this->fecha = $fecha;
        return $this;
    }

    public function generar(string $nombreBase): string
    {
        $proyecto = $this->proyecto;
        $datos = $this->datos;
        $fechaDocumento = $this->fecha ?: date('Y-m-d');
        $dueno = \Model\Usuario::find($proyecto->usuario_id);
        $img = fn(?string $a, string $c) => MsrPdfExporter::imagenBase64($a, $c);
        $logoE = $img($dueno && $dueno->logo ? $dueno->logo : $proyecto->logo, 'build/img/logos');
        $logoC = $img($proyecto->contratista_logo, 'build/img/logos');

        ob_start();
        include __DIR__ . '/../../views/exportar/pdf-presupuesto.php';
        $html = ob_get_clean();

        $pdf = Pdf::generar($html, 'letter', 'portrait', ['isHtml5ParserEnabled' => true, 'marca' => $proyecto]);

        $ruta = $this->carpetaSalida . '/' . $this->nombreFinal($nombreBase, 'pdf');
        file_put_contents($ruta, $pdf);
        return $ruta;
    }
}
