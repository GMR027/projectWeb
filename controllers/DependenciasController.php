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

    /** Proyecto de la petición; $gestionar exige permiso de edición */
    private static function proyectoApi(bool $gestionar): Proyecto
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $p = is_auth() ? Proyecto::find(filter_var($fuente['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        $ok = $p && ($gestionar ? puede_gestionar_proyecto($p) : puede_ver_proyecto($p));
        if (!$ok) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        return $p;
    }

    public static function datos()
    {
        $p = self::proyectoApi(false);
        json_respuesta(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function guardar()
    {
        $p = self::proyectoApi(true);
        [$ok, $msg] = Dependencias::guardar((int)$p->id, (int)($_POST['pred'] ?? 0), (int)($_POST['suc'] ?? 0), (string)($_POST['tipo'] ?? 'FS'), (int)($_POST['retraso'] ?? 0));
        if (!$ok) json_respuesta(['success' => false, 'error' => $msg], 422);
        json_respuesta(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function eliminar()
    {
        $p = self::proyectoApi(true);
        Dependencias::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        json_respuesta(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function fijar()
    {
        $p = self::proyectoApi(true);
        if (!Dependencias::fijar((int)$p->id, (int)($_POST['actividad_id'] ?? 0), !empty($_POST['fija']))) json_respuesta(['success' => false, 'error' => 'Actividad no encontrada'], 404);
        json_respuesta(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function modo()
    {
        $p = self::proyectoApi(true);
        if (!Dependencias::cambiarModo($p, (string)($_POST['modo'] ?? ''))) json_respuesta(['success' => false, 'error' => 'Modo no válido'], 422);
        json_respuesta(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function previa()
    {
        $p = self::proyectoApi(true);
        json_respuesta(['success' => true, 'cambios' => Dependencias::previa($p)]);
    }

    public static function aplicar()
    {
        $p = self::proyectoApi(true);
        if ($p->modo_dependencias !== 'automatico') json_respuesta(['success' => false, 'error' => 'Solo se reprograma en modo automático'], 422);
        $n = Dependencias::aplicar($p);
        json_respuesta(['success' => true, 'movidas' => $n, 'datos' => Dependencias::datos($p)]);
    }

    public static function deshacer()
    {
        $p = self::proyectoApi(true);
        $n = Dependencias::deshacer($p);
        json_respuesta(['success' => $n > 0, 'restauradas' => $n, 'error' => $n ? null : 'No hay nada que deshacer', 'datos' => Dependencias::datos($p)]);
    }
}
