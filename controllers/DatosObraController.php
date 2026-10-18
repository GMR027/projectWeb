<?php

namespace Controllers;

use MVC\Router;
use Classes\DatosObra;

/** Datos del contrato y de la obra que dejó el Ensamblador, en solo lectura, con las alertas de fechas contractuales */
class DatosObraController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $router->render('datos-obra/index', [
            'titulo' => 'Datos de obra - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'alertas' => DatosObra::alertas($pid), 'fechas' => DatosObra::fechasContrato($pid),
            'responsables' => DatosObra::responsables($pid), 'cantidades' => DatosObra::cantidades($pid),
            'contactos' => puede_gestionar_proyecto($proyecto) ? DatosObra::contactos($pid) : null,
        ]);
    }
}
