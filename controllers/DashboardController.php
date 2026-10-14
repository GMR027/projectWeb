<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Partida;
use Model\Actividad;
use Model\Restriccion;

class DashboardController
{
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $usuario = usuario_actual();

        // Admin ve todos; cliente sus proyectos + asignados; visitante solo los asignados
        $proyectos = Proyecto::visiblesPara($usuario);

        // Estadísticas rápidas
        $totalProyectos = count($proyectos);

        $router->render('dashboard/index', [
            'titulo' => 'Inicio',
            'proyectos' => $proyectos,
            'usuario' => $usuario,
            'totalProyectos' => $totalProyectos
        ]);
    }
}
