<?php

namespace Controllers;

use MVC\Router;
use Classes\Costos;

/** Costos y presupuesto: presupuesto por partida, costo real, estimaciones y valor ganado (ver Classes\Costos). Solo quien gestiona el proyecto */
class CostosController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $pestana = in_array($_GET['pestana'] ?? '', ['resumen', 'real', 'estimaciones', 'cambios'], true) ? $_GET['pestana'] : 'resumen';
        $disponible = Costos::disponible();
        $resumen = $disponible ? Costos::resumen($proyecto) : null;
        $movimientos = $disponible && in_array($pestana, ['real', 'estimaciones'], true) ? Costos::movimientos($pid, $pestana === 'real' ? 'real' : 'estimacion') : [];
        $editar = null;
        foreach ($movimientos as $m) if ((int)$m['id'] === (int)($_GET['editar'] ?? 0)) $editar = $m;
        $router->render('costos/index', [
            'titulo' => 'Costos - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'pestana' => $pestana, 'disponible' => $disponible,
            'resumen' => $resumen,
            'curva' => $disponible && $pestana === 'resumen' ? Costos::curva($proyecto, $resumen['total']['bac']) : [],
            'movimientos' => $movimientos,
            'cambios' => $disponible && $pestana === 'cambios' ? Costos::cambiosAutorizados($pid) : [],
            'ordenes' => $disponible ? \Model\OrdenCambio::deProyecto($pid) : [],
            'editar' => $editar, 'error' => (string)($_GET['error'] ?? ''), 'ok' => (string)($_GET['ok'] ?? ''),
        ]);
    }

    private static function volver(int $pid, string $pestana, string $extra = ''): void
    {
        header('Location: /costos?proyecto=' . $pid . '&pestana=' . $pestana . $extra);
        exit;
    }

    public static function presupuesto()
    {
        $proyecto = proyecto_de_peticion(true);
        foreach ((array)($_POST['presupuesto'] ?? []) as $partidaId => $monto) {
            $monto = str_replace([',', '$', ' '], '', (string)$monto);
            if ($monto === '' || !is_numeric($monto)) $monto = '0';
            Costos::guardarPresupuesto((int)$proyecto->id, (int)$partidaId, (float)$monto);
        }
        self::volver((int)$proyecto->id, 'resumen', '&ok=presupuesto');
    }

    public static function movimiento()
    {
        $proyecto = proyecto_de_peticion(true);
        $tipo = ($_POST['tipo'] ?? '') === 'estimacion' ? 'estimacion' : 'real';
        $d = $_POST;
        $d['tipo'] = $tipo;
        $d['monto'] = str_replace([',', '$', ' '], '', (string)($d['monto'] ?? ''));
        $error = Costos::guardarMovimiento((int)$proyecto->id, $d, (string)($_SESSION['nombre'] ?? ''));
        self::volver((int)$proyecto->id, $tipo === 'real' ? 'real' : 'estimaciones', $error ? '&error=' . urlencode($error) : '&ok=1');
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        Costos::eliminarMovimiento((int)$proyecto->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$proyecto->id, ($_POST['tipo'] ?? '') === 'estimacion' ? 'estimaciones' : 'real', '&ok=1');
    }
}
