<?php

namespace Controllers;

use Classes\Ahorro;
use Classes\LandingContenido;
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
        $router->render('landing/terminos', [
            'titulo' => 'Términos y condiciones',
            'landingLayout' => true,
            'actualizado' => '14 de octubre de 2026',
            'descripcion' => 'Términos y condiciones de uso de ProjectWeb, sistema de programas de obra.',
            'ogImage' => '/build/img/landing/hero.jpg',
        ]);
    }

    /** Servicios administrativos de seguimiento de obra (página pública) */
    public static function servicios(Router $router)
    {
        $router->render('landing/servicios', [
            'titulo' => 'Servicios de seguimiento de obra',
            'descripcion' => 'Servicios administrativos de seguimiento de obra: programa, materiales, reportes, cambios y documentación al día. La experiencia técnica de cada disciplina sigue siendo tuya.',
            'ogImage' => '/build/img/landing/campo.jpg',
            'landingLayout' => true,
            'demoDisponible' => (bool)self::proyectoDemo(),
            'whatsapp' => LandingContenido::whatsappFormateado(),
            'whatsappUrl' => LandingContenido::whatsappEnlace('Hola, quiero información sobre el servicio de seguimiento de obra de ProjectWeb.'),
            'precios' => [
                'piloto' => LandingContenido::precioServicio('piloto'),
                'diagnostico' => LandingContenido::precioServicio('diagnostico'),
                'quincenal' => LandingContenido::precioServicio('quincenal'),
                'semanal' => LandingContenido::precioServicio('semanal'),
                'oficina' => LandingContenido::precioServicio('oficina'),
            ],
        ]);
    }

    private static function render(Router $router): void
    {
        $router->render('landing/index', [
            'titulo' => 'Programas de obra bajo control',
            'descripcion' => 'ProjectWeb reúne programa, Gantt, lookahead, Last Planner, vPlan, materiales, reportes diarios, documentos y control de cambios en una sola plataforma. Menos programas, menos costo y una sola fuente de verdad.',
            'ogImage' => '/build/img/landing/hero.jpg',
            'landingLayout' => true,
            'demoDisponible' => (bool)self::proyectoDemo(),
            'contacto' => LandingContenido::contacto(),
            'whatsapp' => LandingContenido::whatsappFormateado(),
            'whatsappUrl' => LandingContenido::whatsappEnlace(),
            'frentes' => LandingContenido::frentes(),
            'totalHerramientas' => LandingContenido::total(),
            'ahorroEscenarios' => Ahorro::escenarios(),
            'ahorroRoles' => Ahorro::roles(),
            'ahorroReemplazos' => Ahorro::reemplazos(),
            'ahorroSupuestos' => Ahorro::supuestos(),
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
