<?php

namespace Controllers;

use MVC\Router;

/** Manual de usuario: una página con todas las herramientas explicadas, filtrada según el rol y las herramientas permitidas */
class ManualController
{
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $rol = $_SESSION['rol'] ?? 'visitante';
        $secciones = array_values(array_filter(
            require __DIR__ . '/../views/manual/_secciones.php',
            fn($s) => in_array($rol, $s['roles'], true) && herramienta_permitida($s['herr'] ?? null)
        ));
        $router->render('manual/index', ['titulo' => 'Manual de usuario', 'secciones' => $secciones, 'rol' => $rol]);
    }
}
