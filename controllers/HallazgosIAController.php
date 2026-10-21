<?php

namespace Controllers;

use MVC\Router;

/**
 * Base de los controladores de las herramientas con hallazgos de IA (Inconsistencias y Análisis de materiales): la app de escritorio manda
 * el análisis; aquí se guardan y el admin acepta, rechaza o reabre cada solución. Las hijas ponen su modelo, vista y textos en constantes.
 */
abstract class HallazgosIAController
{
    /** Clase del modelo (extiende Classes\HallazgosIA) */
    protected const MODELO = '';
    /** Ruta y carpeta de la vista (p. ej. 'inconsistencias') y clave del mensaje en sesión */
    protected const RUTA = '';
    protected const SESION_MSG = '';
    protected const TITULO = '';
    protected const TXT_SOLO_ADMIN = '';
    /** «inconsistencia(s)» / «hallazgo(s)» en el aviso de la revisión rápida */
    protected const TXT_PENDIENTES = '';

    /** Datos extra para la vista (p. ej. el estado de la vigilancia) */
    protected static function datosVista(int $pid): array
    {
        return [];
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        if (!is_admin()) { http_response_code(403); exit(static::TXT_SOLO_ADMIN); }
        $m = static::MODELO;
        $pid = (int)$proyecto->id;
        $corridas = $m::corridas($pid);
        $pedida = filter_var($_GET['corrida'] ?? null, FILTER_VALIDATE_INT);
        $corrida = null;
        foreach ($corridas as $c) if ((int)$c['id'] === $pedida || ($corrida === null && !$pedida)) { $corrida = $c; if ((int)$c['id'] === $pedida) break; }
        $filtros = ['estado' => $_GET['estado'] ?? 'pendiente', 'severidad' => $_GET['severidad'] ?? '', 'categoria' => $_GET['categoria'] ?? '', 'origen' => $_GET['origen'] ?? '', 'corrida' => $corrida ? (int)$corrida['id'] : 0];
        $router->render(static::RUTA . '/index', [
            'titulo' => static::TITULO . ' - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filtros' => $filtros, 'corridas' => $corridas, 'corrida' => $corrida,
            'lista' => $corrida ? $m::listar($pid, $filtros) : [], 'lotes' => $corrida ? $m::lotesDeCorrida($pid, (int)$corrida['id']) : [], 'conteos' => $m::conteos($pid, $corrida ? (int)$corrida['id'] : 0),
            'mensaje' => $_SESSION[static::SESION_MSG] ?? null,
        ] + static::datosVista($pid));
        unset($_SESSION[static::SESION_MSG]);
    }

    /** Lotes de prompt para el modelo (la app de escritorio los envía y devuelve las respuestas a /resultado) */
    public static function contexto()
    {
        $p = proyecto_api();
        $m = static::MODELO;
        $sistema = $m::detectarSistema($p);
        json_respuesta(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre, 'del_sistema' => count($sistema), 'lotes' => $m::lotes($p, $sistema)]);
    }

    /** Recibe las respuestas de la IA ({modelo, respuestas:[...]}), las suma a lo que detecta el sistema y las guarda */
    public static function resultado()
    {
        $p = proyecto_api();
        $m = static::MODELO;
        $cuerpo = json_decode((string)($_POST['datos'] ?? ''), true);
        if (!is_array($cuerpo) || !is_array($cuerpo['respuestas'] ?? null)) json_respuesta(['success' => false, 'error' => 'Datos no válidos'], 422);
        $sistema = $m::detectarSistema($p);
        $ia = $m::normalizarIA($p, $cuerpo['respuestas']);
        $r = $m::guardar($p, array_merge($sistema, $ia), (string)($cuerpo['modelo'] ?? ''), ['del_sistema' => count($sistema), 'de_la_ia' => count($ia)]);
        $lotes = is_array($cuerpo['lotes'] ?? null) ? $m::guardarLotes($p, $r['corrida'], $cuerpo['lotes']) : 0;
        json_respuesta(['success' => true, 'del_sistema' => count($sistema), 'de_la_ia' => count($ia), 'lotes' => $lotes] + $r);
    }

    /** Revisión rápida solo con las reglas del sistema (sin IA) */
    public static function revisar()
    {
        $p = proyecto_api();
        $m = static::MODELO;
        $r = $m::guardar($p, $m::detectarSistema($p));
        $_SESSION[static::SESION_MSG] = ['ok' => true, 'texto' => $r['nuevos'] . ' ' . static::TXT_PENDIENTES . ' pendiente(s) con las reglas del sistema' . ($r['ya_resueltos'] ? " ({$r['ya_resueltos']} ya resueltas o rechazadas no se repiten)" : '') . '. Para la revisión con IA usa la app de escritorio.'];
        header('Location: /' . static::RUTA . '?proyecto=' . (int)$p->id . '&corrida=' . $r['corrida']);
        exit;
    }

    /** Guarda con nombre el análisis (queda siempre disponible para resolverlo después) o lo quita de guardados */
    public static function guardarAnalisis()
    {
        $p = proyecto_api();
        $m = static::MODELO;
        $id = (int)($_POST['corrida'] ?? 0);
        $m::marcarCorrida((int)$p->id, $id, empty($_POST['quitar']), (string)($_POST['nombre'] ?? ''));
        $_SESSION[static::SESION_MSG] = ['ok' => true, 'texto' => empty($_POST['quitar']) ? 'Análisis guardado: podrás consultarlo y resolver sus inconsistencias cuando quieras.' : 'El análisis ya no está guardado (se conservan los 5 más recientes).'];
        header('Location: /' . static::RUTA . '?proyecto=' . (int)$p->id . '&corrida=' . $id);
        exit;
    }

    public static function eliminarAnalisis()
    {
        $p = proyecto_api();
        $m = static::MODELO;
        $m::eliminarCorrida((int)$p->id, (int)($_POST['corrida'] ?? 0));
        $_SESSION[static::SESION_MSG] = ['ok' => true, 'texto' => 'Análisis eliminado. Los cambios que ya hubieras aplicado al proyecto se conservan.'];
        header('Location: /' . static::RUTA . '?proyecto=' . (int)$p->id);
        exit;
    }

    public static function aceptar()
    {
        $p = proyecto_api();
        $m = static::MODELO;
        [$ok, $msg] = $m::aceptar($p, (int)($_POST['id'] ?? 0));
        json_respuesta(['success' => $ok, 'mensaje' => $msg, 'conteos' => $m::conteos((int)$p->id, (int)($_POST['corrida'] ?? 0))], $ok ? 200 : 422);
    }

    public static function rechazar()
    {
        $p = proyecto_api();
        $m = static::MODELO;
        [$ok, $msg] = $m::rechazar((int)$p->id, (int)($_POST['id'] ?? 0));
        json_respuesta(['success' => $ok, 'mensaje' => $msg, 'conteos' => $m::conteos((int)$p->id, (int)($_POST['corrida'] ?? 0))], $ok ? 200 : 422);
    }

    public static function reabrir()
    {
        $p = proyecto_api();
        $m = static::MODELO;
        [$ok, $msg] = $m::reabrir($p, (int)($_POST['id'] ?? 0));
        json_respuesta(['success' => $ok, 'mensaje' => $msg, 'conteos' => $m::conteos((int)$p->id, (int)($_POST['corrida'] ?? 0))], $ok ? 200 : 422);
    }
}
