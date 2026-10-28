<?php

namespace Controllers;

use MVC\Router;
use Model\Archivo;
use Classes\Contratos;

/** Contratos, fianzas, seguros y licencias: registro con vencimientos y aviso previo */
class ContratosController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Contratos::disponible();
        $filtro = in_array($_GET['situacion'] ?? '', ['vigente', 'por_vencer', 'vencido', 'cerrado'], true) ? $_GET['situacion'] : '';
        $editar = (int)($_GET['editar'] ?? 0);
        $router->render('contratos/index', [
            'titulo' => 'Contratos y fianzas - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'disponible' => $disponible,
            'filtro' => $filtro,
            'lista' => $disponible ? Contratos::lista($pid, $filtro) : [],
            'resumen' => $disponible ? Contratos::resumen($pid) : [],
            'editar' => $editar ? Contratos::obtener($pid, $editar) : null,
            'abrirForm' => isset($_GET['nuevo']) || $editar,
            'archivos' => $disponible ? Archivo::deProyecto($pid) : [],
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'ok' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['error'] ?? ''),
        ]);
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Contratos::guardar((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg);
    }

    public static function eliminar()
    {
        $p = proyecto_de_peticion(true);
        $ok = Contratos::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Registro eliminado' : 'No se pudo eliminar');
    }

    private static function volver(int $pid, bool $ok, string $msg): void
    {
        header('Location: /contratos?proyecto=' . $pid . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
