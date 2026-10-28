<?php

namespace Controllers;

use MVC\Router;
use Classes\Equipo;

/** Equipo y maquinaria: registro, horas por actividad, costo y servicios preventivos */
class EquipoController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Equipo::disponible();
        $editar = (int)($_GET['editar'] ?? 0);
        $equipo = $editar ? Equipo::obtener($pid, $editar) : null;
        $verHoras = (int)($_GET['horas'] ?? 0);
        $router->render('equipo/index', [
            'titulo' => 'Equipo y maquinaria - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'disponible' => $disponible,
            'lista' => $disponible ? Equipo::lista($pid) : [],
            'resumen' => $disponible ? Equipo::resumen($pid) : [],
            'servicios' => $disponible ? Equipo::serviciosProximos($pid) : [],
            'editar' => $equipo,
            'abrirForm' => isset($_GET['nuevo']) || $equipo,
            'verHoras' => $verHoras,
            'abrirHoras' => isset($_GET['registrar']),
            'horas' => $disponible ? Equipo::horas($pid, $verHoras) : [],
            'actividades' => $disponible ? \Classes\Recursos::actividades($pid) : [],
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'ok' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['error'] ?? ''),
        ]);
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Equipo::guardar((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg);
    }

    public static function horas()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Equipo::registrarHoras((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, $ok ? '&horas=' . (int)($_POST['equipo_id'] ?? 0) : '');
    }

    public static function eliminarHoras()
    {
        $p = proyecto_de_peticion(true);
        $ok = Equipo::eliminarHoras((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Registro eliminado' : 'No se pudo eliminar', '&horas=' . (int)($_POST['equipo_id'] ?? 0));
    }

    public static function eliminar()
    {
        $p = proyecto_de_peticion(true);
        $ok = Equipo::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Equipo eliminado' : 'No se pudo eliminar');
    }

    private static function volver(int $pid, bool $ok, string $msg, string $extra = ''): void
    {
        header('Location: /equipo?proyecto=' . $pid . $extra . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
