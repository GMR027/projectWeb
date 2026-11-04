<?php

namespace Controllers;

use MVC\Router;
use Classes\Costos;
use Classes\Cantidades;
use Classes\Compromisos;
use Classes\Estimaciones;
use Classes\FlujoCaja;
use Model\Archivo;

/** Costos y presupuesto: presupuesto por partida, costo real, estimaciones y valor ganado (ver Classes\Costos). Solo quien gestiona el proyecto */
class CostosController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $pestana = in_array($_GET['pestana'] ?? '', ['resumen', 'real', 'estimaciones', 'compromisos', 'flujo', 'cambios'], true) ? $_GET['pestana'] : 'resumen';
        $disponible = Costos::disponible();
        $resumen = $disponible ? Costos::resumen($proyecto) : null;
        $movimientos = $disponible && in_array($pestana, ['real', 'estimaciones'], true) ? Costos::movimientos($pid, $pestana === 'real' ? 'real' : 'estimacion') : [];
        $editar = null;
        foreach ($movimientos as $m) if ((int)$m['id'] === (int)($_GET['editar'] ?? 0)) $editar = $m;
        $compromisos = $disponible && $pestana === 'compromisos' ? Compromisos::lista($pid) : [];
        $compromisoEditar = $disponible && $pestana === 'compromisos' && !empty($_GET['editar_compromiso']) ? Compromisos::compromiso($pid, (int)$_GET['editar_compromiso']) : null;
        $aplicar = $disponible && $pestana === 'compromisos' && !empty($_GET['aplicar']) ? Compromisos::compromiso($pid, (int)$_GET['aplicar']) : null;
        $estimaciones = $disponible && $pestana === 'estimaciones' && Estimaciones::disponible() ? Estimaciones::lista($pid) : [];
        $estimacionEditar = $disponible && $pestana === 'estimaciones' && !empty($_GET['editar_estimacion']) ? Estimaciones::estimacion($pid, (int)$_GET['editar_estimacion']) : null;
        $contrato = $disponible && $pestana === 'estimaciones' && Estimaciones::disponible() ? Estimaciones::contrato($pid) : [];
        $resumenContrato = $disponible && $pestana === 'estimaciones' && Estimaciones::disponible() ? Estimaciones::resumenContrato($pid) : [];
        $desdeFlujo = texto_param($_GET['desde'] ?? '') ?: date('Y-m-d');
        $semanasFlujo = max(1, min(52, (int)($_GET['semanas'] ?? 12)));
        $flujo = $disponible && $pestana === 'flujo' && FlujoCaja::disponible() ? FlujoCaja::proyectar($proyecto, $desdeFlujo, $semanasFlujo) : [];
        $costoFinal = $disponible && $pestana === 'flujo' ? FlujoCaja::costoFinal($proyecto) : [];
        $conceptosFlujo = $disponible && $pestana === 'flujo' && FlujoCaja::disponible() ? FlujoCaja::conceptos($pid) : [];
        $router->render('costos/index', [
            'titulo' => 'Costos - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'pestana' => $pestana, 'disponible' => $disponible,
            'resumen' => $resumen,
            'curva' => $disponible && $pestana === 'resumen' ? Costos::curva($proyecto, $resumen['total']['bac']) : [],
            'movimientos' => $movimientos,
            'cambios' => $disponible && $pestana === 'cambios' ? Costos::cambiosAutorizados($pid) : [],
            'ordenes' => $disponible ? \Model\OrdenCambio::deProyecto($pid) : [],
            'editar' => $editar, 'error' => texto_param($_GET['error'] ?? ''), 'ok' => texto_param($_GET['ok'] ?? ''),
            'compromisos' => $compromisos, 'compromisoEditar' => $compromisoEditar, 'aplicar' => $aplicar,
            'archivos' => $disponible && $pestana === 'compromisos' ? Archivo::deProyecto($pid) : [],
            'actividades' => $disponible && $pestana === 'compromisos' ? \Classes\Recursos::actividades($pid) : [],
            'estimaciones' => $estimaciones, 'estimacionEditar' => $estimacionEditar, 'contrato' => $contrato,
            'resumenContrato' => $resumenContrato,
            'siguienteNumero' => $disponible && $pestana === 'estimaciones' && Estimaciones::disponible() ? Estimaciones::siguienteNumero($pid) : '',
            'flujo' => $flujo, 'costoFinal' => $costoFinal, 'conceptosFlujo' => $conceptosFlujo,
            'desdeFlujo' => $desdeFlujo, 'semanasFlujo' => $semanasFlujo,
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

    public static function conceptoFlujo()
    {
        $proyecto = proyecto_de_peticion(true);
        [$ok, $msg] = FlujoCaja::guardarConcepto((int)$proyecto->id, $_POST);
        self::volver((int)$proyecto->id, 'flujo', ($ok ? '&ok=' : '&error=') . urlencode($msg));
    }

    public static function eliminarConceptoFlujo()
    {
        $proyecto = proyecto_de_peticion(true);
        FlujoCaja::eliminarConcepto((int)$proyecto->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$proyecto->id, 'flujo', '&ok=' . urlencode('Concepto eliminado'));
    }

    public static function compromiso()
    {
        $proyecto = proyecto_de_peticion(true);
        [$ok, $msg] = Compromisos::guardar((int)$proyecto->id, $_POST);
        self::volver((int)$proyecto->id, 'compromisos', ($ok ? '&ok=' : '&error=') . urlencode($msg));
    }

    public static function aplicarFactura()
    {
        $proyecto = proyecto_de_peticion(true);
        [$ok, $msg] = Compromisos::aplicar((int)$proyecto->id, (int)($_POST['id'] ?? 0), $_POST);
        self::volver((int)$proyecto->id, 'compromisos', ($ok ? '&ok=' : '&error=') . urlencode($msg));
    }

    public static function cerrarCompromiso()
    {
        $proyecto = proyecto_de_peticion(true);
        [$ok, $msg] = Compromisos::cerrar((int)$proyecto->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$proyecto->id, 'compromisos', ($ok ? '&ok=' : '&error=') . urlencode($msg));
    }

    public static function eliminarCompromiso()
    {
        $proyecto = proyecto_de_peticion(true);
        $ok = Compromisos::eliminar((int)$proyecto->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$proyecto->id, 'compromisos', $ok ? '&ok=' . urlencode('Compromiso eliminado') : '&error=' . urlencode('Solo se pueden eliminar compromisos sin facturas aplicadas'));
    }

    /** Recalcula el presupuesto de todas las partidas desde sus cantidades (para datos anteriores a la migración) */
    public static function recalcular()
    {
        $proyecto = proyecto_de_peticion(true);
        $n = Cantidades::recalcularProyecto((int)$proyecto->id);
        self::volver((int)$proyecto->id, 'resumen', '&ok=' . urlencode("Presupuestos recalculados en $n partida(s)"));
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
