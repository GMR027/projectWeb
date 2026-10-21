<?php

namespace Controllers;

use MVC\Router;
use Model\Archivo;
use Model\ArchivoCarpeta;

/** Visor de archivos: muestra en la misma ventana lo que hay en el Archivo del proyecto */
class VisorController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;

        $categorias = \Model\ArchivoCategoria::deProyecto($pid);
        $filtro = filter_var($_GET['categoria'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($filtro && !in_array($filtro, array_map(fn($c) => (int)$c->id, $categorias), true)) $filtro = null;

        $carpetas = ArchivoCarpeta::deProyecto($pid);
        $carpeta = null;   // null = todas, 0 = sin carpeta, >0 = esa carpeta
        if (($_GET['carpeta'] ?? '') === 'sin') $carpeta = 0;
        elseif ($c = filter_var($_GET['carpeta'] ?? null, FILTER_VALIDATE_INT)) {
            if (in_array($c, array_map(fn($x) => (int)$x->id, $carpetas), true)) $carpeta = $c;
        }

        $buscar = trim((string)($_GET['buscar'] ?? ''));
        $archivos = Archivo::deProyecto($pid, $filtro, $carpeta);
        if ($buscar !== '') {
            $b = mb_strtolower($buscar);
            $archivos = array_values(array_filter($archivos, fn($a) => str_contains(mb_strtolower($a->nombre), $b) || str_contains(mb_strtolower((string)$a->archivo_original), $b)));
        }

        $puede = puede_descargar();

        // Archivo a mostrar: el pedido por «archivo» o, si no, el primero que se pueda ver
        $ver = null;
        if ($idVer = filter_var($_GET['archivo'] ?? null, FILTER_VALIDATE_INT)) {
            $cand = Archivo::find($idVer);
            if ($cand && (int)$cand->proyecto_id === $pid) $ver = $cand;
        }
        if (!$ver) {
            foreach ($archivos as $a) {
                if ($puede && \Classes\VistaArchivo::tipo($a->extension())) { $ver = $a; break; }
            }
        }

        $router->render('visor/index', [
            'titulo' => 'Visor - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'categorias' => $categorias, 'filtro' => $filtro,
            'carpetas' => $carpetas, 'carpeta' => $carpeta,
            'sinCarpeta' => ArchivoCarpeta::sinCarpeta($pid),
            'archivos' => $archivos, 'totalArchivos' => count(Archivo::deProyecto($pid)),
            'ver' => $ver, 'puede' => $puede, 'buscar' => $buscar,
        ]);
    }
}
