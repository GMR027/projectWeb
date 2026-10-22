<?php

namespace Controllers;

use MVC\Router;
use Classes\Marca;

/** Fase 4.4: marca del cliente (logo y colores) para los PDF del proyecto */
class MarcaController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $router->render('marca/index', [
            'titulo' => 'Marca del cliente - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'marca' => Marca::configuracion($proyecto),
            'ok' => isset($_GET['ok']), 'error' => (string)($_GET['error'] ?? ''),
        ]);
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        $logo = null;   // null = dejar el que hay
        if (!empty($_POST['quitar_logo'])) $logo = '';
        if ($nuevo = guardar_imagen_subida('marca_logo', 'logos')) $logo = $nuevo;
        elseif (!empty($_FILES['marca_logo']['name']) && ($_FILES['marca_logo']['error'] ?? 0) === UPLOAD_ERR_OK) {
            header('Location: /marca?proyecto=' . $p->id . '&error=' . urlencode('El logo debe ser una imagen JPG, PNG, WEBP o GIF.'));
            exit;
        }
        $ok = Marca::guardar((int)$p->id, !empty($_POST['activa']), (string)($_POST['nombre'] ?? ''), (string)($_POST['color'] ?? ''), (string)($_POST['color2'] ?? ''), $logo);
        header('Location: /marca?proyecto=' . $p->id . ($ok ? '&ok=1' : '&error=' . urlencode('No se pudo guardar (¿falta aplicar la migración de personalización?)')));
        exit;
    }
}
