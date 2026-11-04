<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\LineaBalance;

/** Línea de balance (takt): frentes, oficios, conflictos y generador de actividades */
class BalanceController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $router->render('balance/index', [
            'titulo' => 'Línea de balance - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'disponible' => LineaBalance::disponible(),
            'frentes' => LineaBalance::disponible() ? LineaBalance::frentes($pid) : [],
            'tipos' => LineaBalance::disponible() ? LineaBalance::tipos($pid) : [],
            'datos' => LineaBalance::disponible() ? LineaBalance::datos($proyecto) : [],
            'partidas' => $proyecto->getPartidas(),
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'ok' => texto_param($_GET['ok'] ?? ''),
            'error' => texto_param($_GET['error'] ?? ''),
        ]);
    }

    public static function tipo()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = LineaBalance::guardarTipo((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg);
    }

    public static function tipoEditar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = LineaBalance::editarTipo((int)$p->id, (int)($_POST['id'] ?? 0), $_POST);
        self::volver((int)$p->id, $ok, $msg);
    }

    public static function tipoEliminar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = LineaBalance::eliminarTipo((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $msg);
    }

    public static function frente()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = LineaBalance::guardarFrente((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg);
    }

    public static function frenteEliminar()
    {
        $p = proyecto_de_peticion(true);
        $ok = LineaBalance::eliminarFrente((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Frente eliminado' : 'El frente tiene actividades: quítalas o reasígnalas primero');
    }

    public static function generar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = LineaBalance::generar($p, $_POST);
        self::volver((int)$p->id, $ok, $msg);
    }

    private static function volver(int $pid, bool $ok, string $msg): void
    {
        header('Location: /balance?proyecto=' . $pid . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
