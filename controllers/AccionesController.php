<?php

namespace Controllers;

use MVC\Router;
use Classes\AccionesRapidas;

/** Página con todas las acciones rápidas del proyecto (se abre en pestaña aparte desde el sidebar) */
class AccionesController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('acciones/index', [
            'titulo' => 'Acciones rápidas - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'gestiona' => $gestiona = puede_gestionar_proyecto($proyecto),
            'secciones' => AccionesRapidas::secciones((int)$proyecto->id, $gestiona, null),
        ]);
    }
}
