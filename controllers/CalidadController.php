<?php

namespace Controllers;

use MVC\Router;
use Classes\Calidad;

/** Calidad e inspecciones (ITP): planes de puntos de inspección, resultados y no conformidades */
class CalidadController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Calidad::disponible();
        $filtro = isset(Calidad::ESTADOS[$_GET['estado'] ?? '']) ? $_GET['estado'] : '';
        $lista = $disponible ? Calidad::planes($pid, $filtro) : [];
        $ver = (int)($_GET['ver'] ?? 0);
        $plan = $ver ? Calidad::plan($pid, $ver) : null;
        $editar = (int)($_GET['editar'] ?? 0);
        $editarPlan = $editar ? Calidad::plan($pid, $editar) : null;
        $router->render('calidad/index', [
            'titulo' => 'Calidad e inspecciones - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'disponible' => $disponible,
            'filtro' => $filtro,
            'lista' => $lista,
            'resumen' => $disponible ? Calidad::resumen($pid) : [],
            'noConformidades' => $disponible ? Calidad::noConformidades($pid) : [],
            'plan' => $plan,
            'puntos' => $plan ? Calidad::puntos((int)$plan['id']) : [],
            'editarPlan' => $editarPlan,
            'abrirForm' => isset($_GET['nuevo']) || $editarPlan,
            'actividades' => Calidad::disponible() ? \Classes\Recursos::actividades($pid) : [],
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'ok' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['error'] ?? ''),
        ]);
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg, $id] = Calidad::guardarPlan((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, $ok ? '&ver=' . $id : ((int)($_POST['id'] ?? 0) ? '&editar=' . (int)$_POST['id'] : '&nuevo=1'));
    }

    public static function punto()
    {
        $p = proyecto_de_peticion(true);
        $ok = Calidad::marcarPunto((int)$p->id, (int)($_POST['punto_id'] ?? 0), (string)($_POST['resultado'] ?? 'pendiente'),
            (string)($_POST['observacion'] ?? ''), (string)($_POST['responsable'] ?? ''), !empty($_POST['resuelto']));
        $plan = (int)($_POST['plan_id'] ?? 0);
        self::volver((int)$p->id, $ok, $ok ? 'Punto actualizado' : 'No se pudo actualizar el punto', $plan ? '&ver=' . $plan : '');
    }

    public static function puntoAgregar()
    {
        $p = proyecto_de_peticion(true);
        $ok = Calidad::agregarPunto((int)$p->id, (int)($_POST['plan_id'] ?? 0), (string)($_POST['descripcion'] ?? ''), (string)($_POST['criterio'] ?? ''));
        $plan = (int)($_POST['plan_id'] ?? 0);
        self::volver((int)$p->id, $ok, $ok ? 'Punto agregado' : 'Escribe la descripción del punto', $plan ? '&ver=' . $plan : '');
    }

    public static function eliminar()
    {
        $p = proyecto_de_peticion(true);
        $ok = Calidad::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Plan eliminado' : 'No se pudo eliminar');
    }

    private static function volver(int $pid, bool $ok, string $msg, string $extra = ''): void
    {
        header('Location: /calidad?proyecto=' . $pid . ($extra ?: '') . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
