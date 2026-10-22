<?php

namespace Controllers;

use MVC\Router;
use Classes\Enlaces;

/** Enlaces de solo lectura (cliente temporal y suscripción de calendario .ics): quien gestiona el proyecto los crea y revoca */
class CompartirController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $disponible = Enlaces::disponible();
        $router->render('compartir/index', [
            'titulo' => 'Compartir - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'disponible' => $disponible,
            'clientes' => $disponible ? Enlaces::lista($pid, 'cliente') : [], 'calendarios' => $disponible ? Enlaces::lista($pid, 'ics') : [],
            'error' => (string)($_GET['error'] ?? ''), 'ok' => (string)($_GET['ok'] ?? ''),
        ]);
    }

    public static function crear()
    {
        $p = proyecto_de_peticion(true);
        $tipo = ($_POST['tipo'] ?? '') === 'ics' ? 'ics' : 'cliente';
        $error = Enlaces::crear((int)$p->id, $tipo, (string)($_POST['nombre'] ?? ''), (array)($_POST['secciones'] ?? []), (int)($_POST['dias'] ?? 0), (string)($_SESSION['nombre'] ?? ''));
        header('Location: /compartir?proyecto=' . $p->id . ($error ? '&error=' . urlencode($error) : '&ok=1'));
        exit;
    }

    public static function revocar()
    {
        $p = proyecto_de_peticion(true);
        Enlaces::revocar((int)$p->id, (int)($_POST['id'] ?? 0));
        header('Location: /compartir?proyecto=' . $p->id . '&ok=1');
        exit;
    }
}
