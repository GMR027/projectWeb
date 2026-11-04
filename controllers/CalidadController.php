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
            'puedeLiberar' => Calidad::disponible() && Calidad::puedeLiberar($proyecto),
            'detenidas' => $disponible ? Calidad::detenidas($pid) : [],
            'liberaciones' => $disponible ? Calidad::liberaciones($pid) : [],
            'ok' => texto_param($_GET['ok'] ?? ''),
            'error' => texto_param($_GET['error'] ?? ''),
        ]);
    }

    /** Libera el avance de una actividad detenida por calidad (solo dueño del proyecto o admin) */
    public static function liberar()
    {
        $p = proyecto_de_peticion(true);
        if (!Calidad::puedeLiberar($p)) {
            self::volver((int)$p->id, false, 'Solo el dueño del proyecto o el administrador pueden liberar un avance');
        }
        [$ok, $msg] = Calidad::liberar((int)$p->id, (int)($_POST['actividad_id'] ?? 0), (int)($_POST['progreso_autorizado'] ?? 100), texto_param($_POST['motivo'] ?? ''));
        self::volver((int)$p->id, $ok, $msg, '&detenidas=1');
    }

    public static function revocar()
    {
        $p = proyecto_de_peticion(true);
        if (!Calidad::puedeLiberar($p)) {
            self::volver((int)$p->id, false, 'Solo el dueño del proyecto o el administrador pueden revocar una liberación');
        }
        $ok = Calidad::revocarLiberacion((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Liberación revocada' : 'La liberación no existe', '&detenidas=1');
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
            texto_param($_POST['observacion'] ?? ''), texto_param($_POST['responsable'] ?? ''), !empty($_POST['resuelto']));
        $plan = (int)($_POST['plan_id'] ?? 0);
        self::volver((int)$p->id, $ok, $ok ? 'Punto actualizado' : 'No se pudo actualizar el punto', $plan ? '&ver=' . $plan : '');
    }

    public static function puntoAgregar()
    {
        $p = proyecto_de_peticion(true);
        $ok = Calidad::agregarPunto((int)$p->id, (int)($_POST['plan_id'] ?? 0), texto_param($_POST['descripcion'] ?? ''), texto_param($_POST['criterio'] ?? ''));
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
