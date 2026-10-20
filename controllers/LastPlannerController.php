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

        $plantilla = \Model\Estampa::lastPlanner((int)$proyecto->id);
        $router->render('lastplanner/index', [
            'plantilla' => $plantilla, 'estampasLienzo' => array_values(array_filter(\Model\Estampa::deProyecto((int)$proyecto->id), fn($e) => ($e->config()['modo'] ?? '') === 'lienzo')),
            'titulo' => 'Last Planner - ' . $proyecto->nombre,
            'proyecto' => $proyecto, 'partidas' => $partidas, 'partida' => $partida,
            'todas' => $todas,
            'tarjetas' => $tarjetas, 'soloRestriccion' => $soloRestriccion,
        ]);
    }

    /** Elige la estampa que sirve de plantilla del post-it (0 = diseño clásico). La plantilla solo se modifica en el editor de estampas */
    public static function plantilla()
    {
        $proyecto = proyecto_de_peticion(true);
        $id = (int)($_POST['estampa'] ?? 0);
        $ok = \Model\Estampa::disponible() && ($id === 0 || (($e = \Model\Estampa::find($id)) && (int)$e->proyecto_id === (int)$proyecto->id)) && \Model\Estampa::usarEnLastPlanner((int)$proyecto->id, $id);
        header('Location: /lastplanner?proyecto=' . (int)$proyecto->id . ($ok ? '' : '&error=1'));
        exit;
    }
}
