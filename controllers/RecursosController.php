<?php

namespace Controllers;

use MVC\Router;
use Classes\Recursos;

/** Recursos y cuadrillas: carga por semana con alerta de sobrecarga (ver Classes\Recursos) */
class RecursosController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Recursos::disponible();
        $desde = max(-26, min(104, (int)($_GET['desde'] ?? 0)));
        $semanas = max(4, min(Recursos::MAX_SEMANAS, (int)($_GET['semanas'] ?? 12)));
        $pestana = in_array($_GET['pestana'] ?? '', ['carga', 'recursos', 'asignaciones'], true) ? $_GET['pestana'] : 'carga';
        $gestiona = puede_gestionar_proyecto($proyecto);
        $router->render('recursos/index', [
            'titulo' => 'Recursos y cuadrillas - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'disponible' => $disponible, 'pestana' => $pestana,
            'desde' => $desde, 'nSemanas' => $semanas, 'puedeEditar' => $gestiona,
            'carga' => $disponible ? Recursos::carga($proyecto, $desde, $semanas) : null,
            'asignaciones' => $disponible && $pestana === 'asignaciones' ? Recursos::asignaciones($pid) : [],
            'actividades' => $disponible && $gestiona && $pestana === 'asignaciones' ? Recursos::actividades($pid) : [],
            'personal' => $disponible && $gestiona && $pestana === 'recursos' ? Recursos::personal($pid) : [],
            'editar' => null, 'error' => (string)($_GET['error'] ?? ''), 'ok' => (string)($_GET['ok'] ?? ''),
        ]);
    }

    private static function volver(int $pid, string $pestana, ?string $error = null): void
    {
        header('Location: /recursos?proyecto=' . $pid . '&pestana=' . $pestana . ($error ? '&error=' . urlencode($error) : '&ok=1'));
        exit;
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        self::volver((int)$p->id, 'recursos', Recursos::guardar((int)$p->id, $_POST));
    }

    public static function eliminar()
    {
        $p = proyecto_de_peticion(true);
        Recursos::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, 'recursos');
    }

    public static function asignar()
    {
        $p = proyecto_de_peticion(true);
        self::volver((int)$p->id, 'asignaciones', Recursos::asignar((int)$p->id, (int)($_POST['actividad_id'] ?? 0), (int)($_POST['recurso_id'] ?? 0), (int)($_POST['dedicacion'] ?? 100)));
    }

    public static function quitar()
    {
        $p = proyecto_de_peticion(true);
        Recursos::quitar((int)$p->id, (int)($_POST['actividad_id'] ?? 0), (int)($_POST['recurso_id'] ?? 0));
        self::volver((int)$p->id, 'asignaciones');
    }
}
