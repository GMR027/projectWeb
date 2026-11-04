<?php

namespace Controllers;

use MVC\Router;
use Classes\Almacen;

/** Almacén: existencias, órdenes de compra con materiales, recepciones, salidas y ajustes */
class AlmacenController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $vista = in_array($_GET['vista'] ?? '', ['existencias', 'ordenes', 'movimientos'], true) ? $_GET['vista'] : 'existencias';
        $disponible = Almacen::disponible();
        $ordenEditar = $disponible && !empty($_GET['orden']) ? Almacen::orden($pid, (int)$_GET['orden']) : null;
        $recibir = $disponible && !empty($_GET['recibir']) ? Almacen::orden($pid, (int)$_GET['recibir']) : null;
        $router->render('almacen/index', [
            'titulo' => 'Almacén - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'vista' => $vista,
            'disponible' => $disponible,
            'almacen' => $disponible ? Almacen::principal($pid) : [],
            'stock' => $disponible ? Almacen::stock($pid) : [],
            'resumen' => $disponible ? Almacen::resumen($pid) : [],
            'ordenes' => $disponible ? Almacen::ordenes($pid) : [],
            'ordenEditar' => $ordenEditar,
            'recibir' => $recibir,
            'recepciones' => $disponible ? Almacen::recepciones($pid) : [],
            'salidas' => $disponible ? Almacen::salidas($pid) : [],
            'movimientos' => $disponible ? Almacen::movimientos($pid) : [],
            'materiales' => $disponible ? Almacen::materiales($pid) : [],
            'actividades' => $disponible ? \Classes\Recursos::actividades($pid) : [],
            'partidas' => $proyecto->getPartidas(),
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'ok' => texto_param($_GET['ok'] ?? ''),
            'error' => texto_param($_GET['error'] ?? ''),
        ]);
    }

    public static function orden()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg, $id] = Almacen::guardarOrden((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, 'ordenes', $ok ? '&orden=' . (int)$id : '');
    }

    public static function recibir()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Almacen::recibir((int)$p->id, (int)($_POST['id'] ?? 0), $_POST);
        self::volver((int)$p->id, $ok, $msg, 'ordenes');
    }

    public static function salida()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Almacen::salida((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, 'existencias');
    }

    public static function ajuste()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Almacen::ajuste((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, 'existencias');
    }

    private static function volver(int $pid, bool $ok, string $msg, string $vista, string $extra = ''): void
    {
        header('Location: /almacen?proyecto=' . $pid . '&vista=' . $vista . $extra . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
