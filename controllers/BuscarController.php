<?php

namespace Controllers;

use MVC\Router;
use Classes\Busqueda;

/** Buscador global del proyecto (una caja en la barra superior) */
class BuscarController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $q = trim((string)($_GET['q'] ?? ''));
        $router->render('buscar/index', [
            'titulo' => 'Buscar - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'q' => $q,
            'grupos' => Busqueda::buscar($proyecto, $q),
            'valida' => (bool)Busqueda::palabras($q),
        ]);
    }
}
