<?php

namespace Controllers;

use MVC\Router;
use Model\Archivo;

/** Comparador de documentos: empareja dos imágenes o PDF (del proyecto o externos) y los compara con un deslizador */
class ComparadorController
{
    /** Tipos que se pueden comparar: extensión => tipo MIME (el SVG queda fuera a propósito) */
    private const TIPOS = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $archivos = [];
        if (puede_descargar()) {   // leer un archivo del proyecto equivale a descargarlo
            foreach (Archivo::deProyecto((int)$proyecto->id) as $a) {
                if (isset(self::TIPOS[$a->extension()]) && $a->ruta()) $archivos[] = $a;
            }
        }
        $router->render('comparador/index', ['titulo' => 'Comparador de documentos - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'archivos' => $archivos]);
    }

    /** Entrega un archivo del proyecto (pdf o imagen) para que el navegador lo dibuje */
    public static function archivo()
    {
        redirigir_si_no_auth();
        $a = Archivo::find(filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT));
        $proyecto = $a ? \Model\Proyecto::find($a->proyecto_id) : null;
        if (!$proyecto) { http_response_code(404); exit; }
        exigir_ver_proyecto($proyecto);
        if (!puede_descargar()) { http_response_code(403); exit('No autorizado'); }
        $ext = $a->extension();
        $ruta = $a->ruta();
        if (!isset(self::TIPOS[$ext]) || !$ruta) { http_response_code(404); exit('Archivo no disponible'); }
        header('Content-Type: ' . self::TIPOS[$ext]);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
}
