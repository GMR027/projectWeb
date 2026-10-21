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

    public static function datos()
    {
        $p = proyecto_api('ver');
        json_respuesta(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function guardar()
    {
        $p = proyecto_api('gestionar');
        [$ok, $msg] = Dependencias::guardar((int)$p->id, (int)($_POST['pred'] ?? 0), (int)($_POST['suc'] ?? 0), (string)($_POST['tipo'] ?? 'FS'), (int)($_POST['retraso'] ?? 0));
        if (!$ok) json_respuesta(['success' => false, 'error' => $msg], 422);
        json_respuesta(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function eliminar()
    {
        $p = proyecto_api('gestionar');
        Dependencias::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        json_respuesta(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function fijar()
    {
        $p = proyecto_api('gestionar');
        if (!Dependencias::fijar((int)$p->id, (int)($_POST['actividad_id'] ?? 0), !empty($_POST['fija']))) json_respuesta(['success' => false, 'error' => 'Actividad no encontrada'], 404);
        json_respuesta(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function modo()
    {
        $p = proyecto_api('gestionar');
        if (!Dependencias::cambiarModo($p, (string)($_POST['modo'] ?? ''))) json_respuesta(['success' => false, 'error' => 'Modo no válido'], 422);
        json_respuesta(['success' => true, 'datos' => Dependencias::datos($p)]);
    }

    public static function previa()
    {
        $p = proyecto_api('gestionar');
        json_respuesta(['success' => true, 'cambios' => Dependencias::previa($p)]);
    }

    public static function aplicar()
    {
        $p = proyecto_api('gestionar');
        if ($p->modo_dependencias !== 'automatico') json_respuesta(['success' => false, 'error' => 'Solo se reprograma en modo automático'], 422);
        $n = Dependencias::aplicar($p);
        json_respuesta(['success' => true, 'movidas' => $n, 'datos' => Dependencias::datos($p)]);
    }

    public static function deshacer()
    {
        $p = proyecto_api('gestionar');
        $n = Dependencias::deshacer($p);
        json_respuesta(['success' => $n > 0, 'restauradas' => $n, 'error' => $n ? null : 'No hay nada que deshacer', 'datos' => Dependencias::datos($p)]);
    }
}
