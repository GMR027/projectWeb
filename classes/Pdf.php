<?php

namespace Classes;

use Dompdf\Dompdf;
use Dompdf\Options;

/** Único lugar donde se arma Dompdf (antes cada exportador repetía Options + papel + render) */
class Pdf
{
    /**
     * Dompdf listo para renderizar (con el HTML ya cargado y el papel puesto).
     * $papel: nombre ('letter', 'tabloid', 'a4') o [0, 0, ancho_pt, alto_pt]. $opciones: se suman a la fuente por defecto ('marca' => Proyecto aplica la marca del cliente).
     */
    public static function preparar(string $html, string|array $papel = 'letter', string $orientacion = 'portrait', array $opciones = []): Dompdf
    {
        if (isset($opciones['marca'])) {   // 'marca' => Proyecto: banda y colores del cliente (Classes\Marca)
            if ($opciones['marca'] instanceof \Model\Proyecto) $html = Marca::aplicar($html, $opciones['marca']);
            unset($opciones['marca']);
        }
        $o = new Options();
        foreach (['defaultFont' => 'DejaVu Sans'] + $opciones as $clave => $valor) $o->set($clave, $valor);
        $dompdf = new Dompdf($o);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($papel, $orientacion);
        return $dompdf;
    }

    /** PDF como bytes */
    public static function generar(string $html, string|array $papel = 'letter', string $orientacion = 'portrait', array $opciones = []): string
    {
        $dompdf = self::preparar($html, $papel, $orientacion, $opciones);
        $dompdf->render();
        return $dompdf->output();
    }

    /** PDF guardado en $ruta; devuelve la ruta */
    public static function guardar(string $ruta, string $html, string|array $papel = 'letter', string $orientacion = 'portrait', array $opciones = []): string
    {
        file_put_contents($ruta, self::generar($html, $papel, $orientacion, $opciones));
        return $ruta;
    }
}
