<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\CalendarioFestivo;
use Classes\Calendario;

class CalendarioController
{
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $proyectoId = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }

        exigir_ver_proyecto($proyecto);
        $festivos = $proyecto->getFestivos();
        $mesActual = $_GET['mes'] ?? date('Y-m');
        $calendario = new Calendario($proyectoId);
        $actividades = self::obtenerActividadesDelMes($proyecto, $mesActual);

        $router->render('calendario/index', [
            'titulo' => 'Calendario - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'festivos' => $festivos,
            'mesActual' => $mesActual,
            'actividades' => $actividades,
            'calendario' => $calendario
        ]);
    }

    private static function obtenerActividadesDelMes(Proyecto $proyecto, string $mesYmd): array
    {
        $inicioMes = new \DateTime($mesYmd . '-01');
        $finMes = clone $inicioMes;
        $finMes->modify('last day of this month');

        $resultado = [];
        foreach ($proyecto->getPartidas() as $partida) {
            foreach ($partida->getActividades() as $act) {
                $inicioAct = new \DateTime($act->fecha_inicio);
                $finAct = new \DateTime($act->fecha_fin);

                if ($inicioAct <= $finMes && $finAct >= $inicioMes) {
                    $resultado[] = [
                        'actividad' => $act,
                        'partida' => $partida,
                        'dias_en_mes' => self::calcularDiasEnMes($act, $inicioMes, $finMes)
                    ];
                }
            }
        }
        return $resultado;
    }

    private static function calcularDiasEnMes($actividad, \DateTime $inicioMes, \DateTime $finMes): array
    {
        $dias = [];
        $inicio = max(new \DateTime($actividad->fecha_inicio), $inicioMes);
        $fin = min(new \DateTime($actividad->fecha_fin), $finMes);

        $curr = clone $inicio;
        while ($curr <= $fin) {
            $dias[] = $curr->format('j');
            $curr->modify('+1 day');
        }
        return $dias;
    }
}
