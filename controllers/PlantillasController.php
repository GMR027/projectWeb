<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Plantillas;

/** Plantillas por tipo de obra: elegir el tipo del proyecto y, si está vacío, sembrar su base de partidas y actividades */
class PlantillasController
{
    private static function admin(): Proyecto
    {
        $p = Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT));
        if (!$p || !is_auth() || !puede_gestionar_proyecto($p)) { http_response_code(403); exit('No autorizado'); }
        return $p;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $router->render('plantillas/index', ['titulo' => 'Tipo de obra - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'actual' => Plantillas::actual($pid), 'catalogo' => Plantillas::CATALOGO, 'historial' => Plantillas::historial($pid), 'identificacion' => isset($_GET['identificar']) ? Plantillas::identificar($proyecto) : null, 'puede' => puede_gestionar_proyecto($proyecto),
            'partidas' => (int)($GLOBALS['db']->query("SELECT COUNT(*) FROM partidas WHERE proyecto_id = $pid")->fetch_row()[0] ?? 0), 'mensaje' => $_SESSION['plantillas_msg'] ?? null]);
        unset($_SESSION['plantillas_msg']);
    }

    public static function elegir()
    {
        $p = self::admin();
        $ident = ($_POST['origen'] ?? '') === 'identificado';
        $ok = Plantillas::elegir((int)$p->id, (string)($_POST['plantilla'] ?? ''), $ident ? 'identificado' : 'manual', (int)($_POST['confianza'] ?? 0), $ident ? mb_substr((string)($_POST['indicios'] ?? ''), 0, 500) : '');
        $_SESSION['plantillas_msg'] = ['ok' => $ok, 'texto' => $ok ? 'Tipo de obra guardado. Las herramientas de IA y las reglas de secuencia ya lo usan.' : 'Tipo de obra no válido.'];
        header('Location: /plantillas?proyecto=' . (int)$p->id);
        exit;
    }

    public static function aplicar()
    {
        $p = self::admin();
        [$ok, $msg] = Plantillas::aplicarBase($p);
        $_SESSION['plantillas_msg'] = ['ok' => $ok, 'texto' => $msg];
        header('Location: /plantillas?proyecto=' . (int)$p->id);
        exit;
    }
}
