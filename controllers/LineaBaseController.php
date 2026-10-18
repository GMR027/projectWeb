<?php

namespace Controllers;

use MVC\Router;
use Classes\LineasBase;
use Model\LineaBase;

/** Líneas base del programa: guardar la foto de hoy y compararla con el programa actual */
class LineaBaseController
{
    private static function lineaDe($proyecto): ?LineaBase
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $l = LineaBase::find(filter_var($fuente['id'] ?? null, FILTER_VALIDATE_INT));
        return $l && (int)$l->proyecto_id === (int)$proyecto->id ? $l : null;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('lineas-base/index', [
            'titulo' => 'Líneas base - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'lineas' => LineaBase::deProyecto((int)$proyecto->id),
            'puede' => puede_gestionar_proyecto($proyecto), 'mensaje' => $_SESSION['lineas_msg'] ?? null,
        ]);
        unset($_SESSION['lineas_msg']);
    }

    public static function capturar()
    {
        $proyecto = proyecto_de_peticion(true);
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') $nombre = (LineaBase::deProyecto((int)$proyecto->id) ? 'Línea base ' : 'Programa inicial ') . date('d/m/Y');
        $id = LineasBase::capturar($proyecto, $nombre, trim($_POST['notas'] ?? ''));
        $_SESSION['lineas_msg'] = ['ok' => true, 'texto' => 'Línea base guardada: ' . $nombre];
        header('Location: /lineas-base?proyecto=' . $proyecto->id . '&nueva=' . $id);
        exit;
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($l = self::lineaDe($proyecto)) LineasBase::eliminar((int)$proyecto->id, (int)$l->id);
        header('Location: /lineas-base?proyecto=' . $proyecto->id);
        exit;
    }

    public static function comparar(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $linea = self::lineaDe($proyecto);
        if (!$linea) {
            header('Location: /lineas-base?proyecto=' . $proyecto->id);
            exit;
        }
        $r = LineasBase::comparar($proyecto, $linea);
        $router->render('lineas-base/comparar', [
            'titulo' => 'Comparar con ' . $linea->nombre . ' - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'linea' => $linea, 'cmp' => $r,
            'materiales' => LineasBase::materialesMovidos((int)$proyecto->id, (int)$linea->id), 'soloCambios' => ($_GET['todas'] ?? '') !== '1',
        ]);
    }
}
