<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\InicioUsuario;
use Classes\Novedades;

class DashboardController
{
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $usuario = usuario_actual();

        // Admin ve todos; cliente sus proyectos + asignados; visitante solo los asignados
        $proyectos = Proyecto::visiblesPara($usuario);

        // Inicio personalizado por usuario (Fase 4.5): bloques activos y en su orden
        $inicio = InicioUsuario::de((int)$usuario['id']);
        $activos = array_column(array_filter($inicio['bloques'], fn($b) => $b['on']), 'k');

        $router->render('dashboard/index', [
            'titulo' => 'Inicio',
            'proyectos' => $proyectos,
            'usuario' => $usuario,
            'totalProyectos' => count($proyectos),
            'inicio' => $inicio,
            'activos' => $activos,
            'datos' => InicioUsuario::datos($proyectos, $activos),
            'novedades' => in_array('novedades', $activos, true) ? array_slice(Novedades::para($usuario['rol'], (int)$usuario['id']), 0, 4) : [],
            'personalizando' => isset($_GET['personalizar']),
        ]);
    }

    public static function guardarInicio()
    {
        redirigir_si_no_auth();
        $bloques = [];
        foreach ((array)($_POST['bloques'] ?? []) as $b) if (is_array($b) && !empty($b['k'])) $bloques[] = ['k' => (string)$b['k'], 'on' => !empty($b['on'])];
        if (isset($_POST['restablecer'])) InicioUsuario::restablecer((int)$_SESSION['id']);
        else InicioUsuario::guardar((int)$_SESSION['id'], $bloques, (int)($_POST['proyectos_n'] ?? 6));
        header('Location: /dashboard');
        exit;
    }
}
