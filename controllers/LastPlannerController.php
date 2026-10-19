<?php

namespace Controllers;

use MVC\Router;
use Model\Partida;
use Classes\LastPlanner;

/** Last Planner: post-its automáticos por partida a partir del programa de obra */
class LastPlannerController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $partidas = $proyecto->getPartidas();
        $partidaId = filter_var($_GET['partida'] ?? null, FILTER_VALIDATE_INT);
        $partida = null;
        foreach ($partidas as $p) {
            if ((int)$p->id === (int)$partidaId) $partida = $p;
        }
        $todas = ($_GET['partida'] ?? '') === 'todas';
        $partida = $todas ? null : ($partida ?? ($partidas[0] ?? null));

        $tarjetas = $todas ? LastPlanner::tarjetasTodas($proyecto) : ($partida ? LastPlanner::tarjetas($proyecto, $partida, '1900-01-01', '2999-12-31') : []);
        $soloRestriccion = !empty($_GET['restriccion']);
        if ($soloRestriccion) {
            $tarjetas = array_values(array_filter($tarjetas, fn($t) => array_filter($t['items'], fn($i) => !empty($i['restriccion']))));
        }

        $router->render('lastplanner/index', [
            'titulo' => 'Last Planner - ' . $proyecto->nombre,
            'proyecto' => $proyecto, 'partidas' => $partidas, 'partida' => $partida,
            'todas' => $todas,
            'tarjetas' => $tarjetas, 'soloRestriccion' => $soloRestriccion,
        ]);
    }
}
