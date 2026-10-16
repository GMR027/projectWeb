<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Dependencias;

/** Dependencias de obra: pantalla interactiva y API JSON (relaciones, modo, reprogramación) */
class DependenciasController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('dependencias/index', [
            'titulo' => 'Dependencias de obra - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'datos' => Dependencias::datos($proyecto),
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    private static function json(array $d, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Proyecto de la petición; $gestionar exige permiso de edición */
    private static function proyectoApi(bool $gestionar): Proyecto
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $p = is_auth() ? Proyecto::find(filter_var($fuente['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        $ok = $p && ($gestionar ? puede_gestionar_proyecto($p) : puede_ver_proyecto($p));
        if (!$ok) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        return $p;
    }

    public static function datos()
    {
        $p = self::proyectoApi(false);
        self::json(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function guardar()
    {
        $p = self::proyectoApi(true);
        [$ok, $msg] = Dependencias::guardar((int)$p->id, (int)($_POST['pred'] ?? 0), (int)($_POST['suc'] ?? 0), (string)($_POST['tipo'] ?? 'FS'), (int)($_POST['retraso'] ?? 0));
        if (!$ok) self::json(['success' => false, 'error' => $msg], 422);
        self::json(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function eliminar()
    {
        $p = self::proyectoApi(true);
        Dependencias::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::json(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function fijar()
    {
        $p = self::proyectoApi(true);
        if (!Dependencias::fijar((int)$p->id, (int)($_POST['actividad_id'] ?? 0), !empty($_POST['fija']))) self::json(['success' => false, 'error' => 'Actividad no encontrada'], 404);
        self::json(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function modo()
    {
        $p = self::proyectoApi(true);
        if (!Dependencias::cambiarModo($p, (string)($_POST['modo'] ?? ''))) self::json(['success' => false, 'error' => 'Modo no válido'], 422);
        self::json(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function previa()
    {
        $p = self::proyectoApi(true);
        self::json(['success' => true, 'cambios' => Dependencias::previa($p)]);
    }

    public static function aplicar()
    {
        $p = self::proyectoApi(true);
        if ($p->modo_dependencias !== 'automatico') self::json(['success' => false, 'error' => 'Solo se reprograma en modo automático'], 422);
        $n = Dependencias::aplicar($p);
        self::json(['success' => true, 'movidas' => $n, 'datos' => Dependencias::datos($p)]);
    }

    public static function deshacer()
    {
        $p = self::proyectoApi(true);
        $n = Dependencias::deshacer($p);
        self::json(['success' => $n > 0, 'restauradas' => $n, 'error' => $n ? null : 'No hay nada que deshacer', 'datos' => Dependencias::datos($p)]);
    }
}
