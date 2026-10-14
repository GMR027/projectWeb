<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Usuario;

class LandingController
{
    /** Visitantes sin sesión: página de presentación. Con sesión: el inicio de siempre. */
    public static function index(Router $router)
    {
        if (is_auth()) {
            DashboardController::index($router);
            return;
        }
        self::render($router);
    }

    /** Página de presentación también para quien ya tiene sesión (útil para el admin) */
    public static function inicio(Router $router)
    {
        self::render($router);
    }

    /** Términos y condiciones de uso (página pública) */
    public static function terminos(Router $router)
    {
        $router->render('landing/terminos', ['titulo' => 'Términos y condiciones', 'landingLayout' => true, 'actualizado' => '14 de octubre de 2026']);
    }

    private static function render(Router $router): void
    {
        $router->render('landing/index', [
            'titulo' => 'Programas de obra bajo control',
            'landingLayout' => true,
            'demoDisponible' => (bool)self::proyectoDemo(),
            'contacto' => trim($_ENV['CONTACT_EMAIL'] ?? ''),
        ]);
    }

    private static function proyectoDemo(): ?Proyecto
    {
        return Proyecto::where('codigo', 'DEMO') ?: null;
    }

    /** Entra como el usuario demo (rol visitante: solo puede ver) y abre el proyecto de ejemplo */
    public static function demo()
    {
        if (is_auth() && !es_demo()) {
            header('Location: /dashboard');
            exit;
        }
        $proyecto = self::proyectoDemo();
        $usuario = Usuario::where('email', DEMO_EMAIL);
        if (!$proyecto || !$usuario || $usuario->rol !== 'visitante') {
            header('Location: /');
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['id'] = $usuario->id;
        $_SESSION['nombre'] = $usuario->nombre;
        $_SESSION['email'] = $usuario->email;
        $_SESSION['rol'] = $usuario->rol;
        header('Location: /programa?proyecto=' . $proyecto->id);
        exit;
    }
}
