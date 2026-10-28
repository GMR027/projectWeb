<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Enlaces;
use Classes\CalendarioIcs;
use Classes\CurvaS;

/** Páginas públicas de solo lectura (sin sesión): resumen para el cliente y calendario .ics. El acceso es el token del enlace */
class EnlaceClienteController
{
    private static function sinCache(): void
    {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
    }

    private static function noDisponible(Router $router)
    {
        self::sinCache();
        http_response_code(404);
        $router->render('cliente/no-disponible', ['titulo' => 'Enlace no disponible', 'publicoLayout' => true]);
    }

    public static function ver(Router $router)
    {
        $e = Enlaces::resolver((string)($_GET['t'] ?? ''), 'cliente');
        $proyecto = $e ? Proyecto::find((int)$e['proyecto_id']) : null;
        if (!$e || !$proyecto) return self::noDisponible($router);
        self::sinCache();
        $pid = (int)$proyecto->id;
        $secciones = array_filter(explode(',', (string)$e['secciones']));
        $curva = in_array('avance', $secciones, true) ? CurvaS::calcular($proyecto) : null;
        // Las fotos viven detrás de /uploads (con permiso): el enlace público las autoriza con su token
        $fotos = in_array('fotos', $secciones, true) ? Enlaces::fotos($pid) : [];
        foreach ($fotos as &$foto) {
            $foto['url'] .= (str_contains($foto['url'], '?') ? '&' : '?') . 't=' . urlencode((string)$e['token']);
        }
        unset($foto);
        $router->render('cliente/ver', [
            'titulo' => $proyecto->nombre, 'publicoLayout' => true, 'proyecto' => $proyecto, 'enlace' => $e, 'secciones' => $secciones,
            'avance' => in_array('avance', $secciones, true) ? Enlaces::avancePartidas($pid) : null,
            'curva' => $curva, 'proximas' => in_array('avance', $secciones, true) ? Enlaces::proximas($pid) : null,
            'fotos' => $fotos,
            'reportes' => in_array('reportes', $secciones, true) ? Enlaces::reportes($pid) : [],
        ]);
    }

    public static function ics(Router $router)
    {
        $e = Enlaces::resolver((string)($_GET['t'] ?? ''), 'ics');
        $proyecto = $e ? Proyecto::find((int)$e['proyecto_id']) : null;
        if (!$e || !$proyecto) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Enlace no disponible';
            return;
        }
        self::sinCache();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="' . slug_archivo($proyecto->nombre) . '.ics"');
        echo CalendarioIcs::generar($proyecto);
    }
}
