<?php

namespace Controllers;

use MVC\Router;
use Classes\Rfi;
use Classes\Revisiones;

/** RFI: registro con responsable, fecha límite y la actividad que bloquea (ver Classes\Rfi) */
class RfiController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Rfi::disponible();
        $filtro = $_GET['estado'] ?? '';
        if (!isset(Rfi::ESTADOS[$filtro]) && $filtro !== 'vencidos') $filtro = '';
        $gestiona = puede_gestionar_proyecto($proyecto);
        $lista = $disponible ? Rfi::lista($pid, $filtro === 'vencidos' ? '' : $filtro, $filtro === 'vencidos') : [];
        $editar = null;
        $ver = (int)($_GET['editar'] ?? 0);
        if ($ver) foreach ($lista ?: Rfi::lista($pid) as $r) if ((int)$r['id'] === $ver) $editar = $r;
        $revs = $disponible ? Revisiones::mapa($pid, 'rfi') : [];
        $router->render('rfi/index', [
            'titulo' => 'RFI - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'disponible' => $disponible, 'filtro' => $filtro,
            'lista' => $lista, 'resumen' => $disponible ? Rfi::resumen($pid) : [], 'puedeEditar' => $gestiona, 'editar' => $editar,
            'actividades' => $disponible && $gestiona ? \Classes\Recursos::actividades($pid) : [],
            'revs' => $revs, 'puedeRev' => puede_revisar($proyecto), 'abrirForm' => isset($_GET['nuevo']) || $editar,
            'numero' => $disponible ? Rfi::siguienteNumero($pid) : '', 'error' => (string)($_GET['error'] ?? ''), 'ok' => (string)($_GET['ok'] ?? ''),
        ]);
    }

    private static function volver(int $pid, ?string $error = null, string $extra = ''): void
    {
        header('Location: /rfi?proyecto=' . $pid . ($error ? '&error=' . urlencode($error) : '&ok=1') . $extra);
        exit;
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        $error = Rfi::guardar((int)$p->id, $_POST, (string)($_SESSION['nombre'] ?? ''));
        self::volver((int)$p->id, $error, $error && !empty($_POST['id']) ? '&editar=' . (int)$_POST['id'] : ($error ? '&nuevo=1' : ''));
    }

    public static function estado()
    {
        $p = proyecto_de_peticion(true);
        Rfi::cambiarEstado((int)$p->id, (int)($_POST['id'] ?? 0), (string)($_POST['estado'] ?? ''));
        self::volver((int)$p->id);
    }

    public static function eliminar()
    {
        $p = proyecto_de_peticion(true);
        Rfi::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id);
    }
}
