<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\PlantillaProyecto;

/** Fase 4.3: guardar un proyecto terminado como plantilla y arrancar proyectos nuevos desde ella */
class PlantillasProyectoController
{
    private static function volver(?Proyecto $p, bool $ok, string $texto): never
    {
        $_SESSION['pp_msg'] = ['ok' => $ok, 'texto' => $texto];
        header('Location: /plantillas-proyecto' . ($p ? '?proyecto=' . (int)$p->id : ''));
        exit;
    }

    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        if (is_visitante()) { header('Location: /proyectos'); exit; }
        $proyecto = null;
        if (isset($_GET['proyecto'])) $proyecto = proyecto_de_peticion(true);
        $usuario = usuario_actual();
        $router->render('plantillas-proyecto/index', [
            'titulo' => 'Plantillas de proyecto', 'proyecto' => $proyecto, 'disponible' => PlantillaProyecto::disponible(),
            'plantillas' => PlantillaProyecto::disponible() ? PlantillaProyecto::visibles($usuario) : [], 'usuario' => $usuario,
            'vacio' => $proyecto ? PlantillaProyecto::estaVacio((int)$proyecto->id) : false,
            'mensaje' => $_SESSION['pp_msg'] ?? null,
        ]);
        unset($_SESSION['pp_msg']);
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $r] = PlantillaProyecto::guardar($p, usuario_actual(), (string)($_POST['nombre'] ?? ''), (string)($_POST['descripcion'] ?? ''), !empty($_POST['compartida']));
        self::volver($p, $ok, $ok ? 'Plantilla guardada. Ya puedes elegirla al crear un proyecto nuevo.' : (string)$r);
    }

    public static function aplicar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = PlantillaProyecto::aplicar((int)($_POST['id'] ?? 0), $p, usuario_actual());
        if ($ok) { header('Location: ' . url_inicio_proyecto((int)$p->id)); exit; }
        self::volver($p, false, $msg);
    }

    public static function eliminar()
    {
        redirigir_si_no_auth();
        $ok = PlantillaProyecto::eliminar((int)($_POST['id'] ?? 0), usuario_actual());
        $p = isset($_POST['proyecto']) ? Proyecto::find((int)$_POST['proyecto']) : null;
        self::volver($p && puede_gestionar_proyecto($p) ? $p : null, $ok, $ok ? 'Plantilla eliminada.' : 'No se pudo eliminar la plantilla.');
    }
}
