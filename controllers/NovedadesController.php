<?php

namespace Controllers;

use MVC\Router;
use Classes\Novedades;

/**
 * Novedades de la plataforma.
 *
 * - GET  /novedades          página completa (todos los roles) y marca lo leído
 * - POST /novedades/vistas   marca como vistas; responde JSON si es fetch, o
 *                            vuelve a `volver` (formulario de Ajustes)
 */
class NovedadesController
{
    /** Página con el historial completo de novedades */
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $rol = $_SESSION['rol'] ?? 'visitante';
        $usuarioId = (int)($_SESSION['id'] ?? 0);

        $lista = Novedades::para($rol, $usuarioId);
        $sinVer = Novedades::sinVer($lista);
        if ($sinVer > 0) {   // abrir la página es leerlas (las marcas «Nueva» se conservan en esta visita)
            Novedades::marcarVistas($usuarioId);
        }

        $router->render('novedades/index', [
            'titulo' => 'Novedades de la plataforma',
            'novedades' => $lista,
            'sinVer' => $sinVer,
        ]);
    }

    /** Marca las novedades como vistas (campana de la barra superior y botón de Ajustes) */
    public static function vistas()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /novedades');
            exit;
        }
        Novedades::marcarVistas((int)($_SESSION['id'] ?? 0));

        $json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        if ($json) {
            json_respuesta(['success' => true]);
        }

        // Formulario normal: volver a la página de la que vino (solo rutas internas)
        $volver = (string)($_POST['volver'] ?? '/novedades');
        if (!str_starts_with($volver, '/') || str_starts_with($volver, '//')) $volver = '/novedades';
        header('Location: ' . $volver);
        exit;
    }
}
