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

    /**
     * Fragmento del menú lateral con las acciones rápidas, para cargarlo aparte
     * (GET /api/acciones?proyecto=N). Evita ~20 consultas en cada página.
     */
    public static function fragmento()
    {
        redirigir_si_no_auth();
        $proyectoSidebar = \Model\Proyecto::find(filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT));
        if (!$proyectoSidebar || !puede_ver_proyecto($proyectoSidebar) || !herramienta_permitida('acciones')) {
            http_response_code(403);
            exit;
        }
        try {
            $accionesRapidas = AccionesRapidas::secciones((int)$proyectoSidebar->id, puede_gestionar_proyecto($proyectoSidebar));
        } catch (\Throwable $e) {
            $accionesRapidas = [];
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, max-age=30');
        include __DIR__ . '/../views/templates/sidebar-acciones.php';
        exit;
    }
}
