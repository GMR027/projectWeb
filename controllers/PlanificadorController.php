<?php

namespace Controllers;

use MVC\Router;
use Classes\Planificador;

/** Planificador: qué actividades se pueden ejecutar según los materiales y restricciones */
class PlanificadorController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $semanas = in_array((int)($_GET['semanas'] ?? 4), [0, 2, 4, 8, 12], true) ? (int)($_GET['semanas'] ?? 4) : 4;
        $partidaId = filter_var($_GET['partida'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $solo = !empty($_GET['solo']);
        $router->render('planificador/index', [
            'titulo' => 'Planificador - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'datos' => Planificador::datos((int)$proyecto->id, $partidaId, $semanas, $solo),
            'partidas' => $proyecto->getPartidas(), 'semanas' => $semanas, 'partidaId' => $partidaId, 'solo' => $solo,
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
        ]);
    }
}
