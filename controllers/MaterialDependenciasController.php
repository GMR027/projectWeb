<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\DependenciasMateriales;

/** Dependencias de materiales: pantalla, relaciones, reprogramación y sugerencias (API JSON) */
class MaterialDependenciasController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('materiales/dependencias', [
            'titulo' => 'Dependencias de materiales - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'datos' => DependenciasMateriales::datos($proyecto),
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }

    public static function datos()
    {
        $p = proyecto_api('ver');
        json_respuesta(['success' => true, 'datos' => DependenciasMateriales::datos($p)]);
    }

    public static function guardar()
    {
        $p = proyecto_api('gestionar');
        [$ok, $msg] = DependenciasMateriales::guardar(
            (int)$p->id,
            (int)($_POST['pred'] ?? 0),
            (int)($_POST['suc'] ?? 0),
            (string)($_POST['requisito'] ?? 'entrega'),
            (int)($_POST['retraso'] ?? 0),
            (string)($_POST['nota'] ?? '')
        );
        if (!$ok) json_respuesta(['success' => false, 'error' => $msg], 422);
        json_respuesta(['success' => true, 'datos' => DependenciasMateriales::datos($p)]);
    }

    /** Guarda varios prerrequisitos para un mismo material en una sola petición */
    public static function guardarVarios()
    {
        $p = proyecto_api('gestionar');
        $suc = (int)($_POST['suc'] ?? 0);
        $items = json_decode((string)($_POST['items'] ?? '[]'), true);
        if (!is_array($items) || !$items) json_respuesta(['success' => false, 'error' => 'No hay materiales seleccionados'], 422);
        $errores = [];
        $n = 0;
        foreach ($items as $x) {
            [$ok, $msg] = DependenciasMateriales::guardar(
                (int)$p->id, (int)($x['pred'] ?? 0), $suc,
                (string)($x['requisito'] ?? 'entrega'), (int)($x['retraso'] ?? 0), (string)($x['nota'] ?? '')
            );
            if ($ok) $n++; else $errores[] = $msg;
        }
        json_respuesta(['success' => true, 'guardadas' => $n, 'errores' => array_values(array_unique($errores)), 'datos' => DependenciasMateriales::datos($p)]);
    }

    public static function eliminar()
    {
        $p = proyecto_api('gestionar');
        DependenciasMateriales::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        json_respuesta(['success' => true, 'datos' => DependenciasMateriales::datos($p)]);
    }

    public static function modo()
    {
        $p = proyecto_api('gestionar');
        if (!DependenciasMateriales::cambiarModo($p, (string)($_POST['modo'] ?? ''))) {
            json_respuesta(['success' => false, 'error' => 'Modo no válido'], 422);
        }
        json_respuesta(['success' => true, 'datos' => DependenciasMateriales::datos($p)]);
    }

    public static function previa()
    {
        $p = proyecto_api('gestionar');
        json_respuesta(['success' => true] + DependenciasMateriales::previa($p));
    }

    public static function aplicar()
    {
        $p = proyecto_api('gestionar');
        if (($p->modo_dependencias_materiales ?? 'apagado') !== 'automatico') {
            json_respuesta(['success' => false, 'error' => 'Solo se reprograma en modo automático'], 422);
        }
        $n = DependenciasMateriales::aplicar($p);
        json_respuesta(['success' => true, 'movidas' => $n, 'datos' => DependenciasMateriales::datos($p)]);
    }

    public static function deshacer()
    {
        $p = proyecto_api('gestionar');
        $n = DependenciasMateriales::deshacer($p);
        json_respuesta(['success' => $n > 0, 'restauradas' => $n, 'error' => $n ? null : 'No hay nada que deshacer', 'datos' => DependenciasMateriales::datos($p)]);
    }

    public static function sugerencias()
    {
        $p = proyecto_api('ver');
        json_respuesta(['success' => true, 'sugerencias' => DependenciasMateriales::sugerencias($p)]);
    }

    public static function aplicarSugerencias()
    {
        $p = proyecto_api('gestionar');
        $rel = json_decode((string)($_POST['relaciones'] ?? '[]'), true);
        $act = json_decode((string)($_POST['actividades'] ?? '[]'), true);
        if (!is_array($rel) || !is_array($act)) json_respuesta(['success' => false, 'error' => 'Datos no válidos'], 422);
        $r = DependenciasMateriales::aplicarSugerencias($p, $rel, $act);
        json_respuesta(['success' => true] + $r + ['datos' => DependenciasMateriales::datos($p)]);
    }
}
