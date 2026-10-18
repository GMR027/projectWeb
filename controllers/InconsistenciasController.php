<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Inconsistencias;

/** Inconsistencias de proyecto: la app de escritorio manda el análisis de la IA; aquí se guardan y el admin acepta o rechaza cada solución */
class InconsistenciasController
{
    private static function json(array $d, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Solo el admin (es quien usa las herramientas de IA) */
    private static function proyectoApi(): Proyecto
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $p = is_admin() ? Proyecto::find(filter_var($fuente['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$p) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        return $p;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        if (!is_admin()) { http_response_code(403); exit('Solo el administrador puede ver las inconsistencias del proyecto'); }
        $pid = (int)$proyecto->id;
        $corridas = Inconsistencias::corridas($pid);
        $pedida = filter_var($_GET['corrida'] ?? null, FILTER_VALIDATE_INT);
        $corrida = null;
        foreach ($corridas as $c) if ((int)$c['id'] === $pedida || ($corrida === null && !$pedida)) { $corrida = $c; if ((int)$c['id'] === $pedida) break; }
        $filtros = ['estado' => $_GET['estado'] ?? 'pendiente', 'severidad' => $_GET['severidad'] ?? '', 'categoria' => $_GET['categoria'] ?? '', 'origen' => $_GET['origen'] ?? '', 'corrida' => $corrida ? (int)$corrida['id'] : 0];
        $router->render('inconsistencias/index', [
            'titulo' => 'Inconsistencias de proyecto - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filtros' => $filtros, 'corridas' => $corridas, 'corrida' => $corrida,
            'lista' => $corrida ? Inconsistencias::listar($pid, $filtros) : [], 'lotes' => $corrida ? Inconsistencias::lotesDeCorrida($pid, (int)$corrida['id']) : [], 'conteos' => Inconsistencias::conteos($pid, $corrida ? (int)$corrida['id'] : 0),
            'mensaje' => $_SESSION['inconsistencias_msg'] ?? null, 'vigilancia' => Inconsistencias::estadoVigilancia($pid),
        ]);
        unset($_SESSION['inconsistencias_msg']);
    }

    /** Lotes de prompt para el modelo (la app de escritorio los envía y devuelve las respuestas a /resultado) */
    public static function contexto()
    {
        $p = self::proyectoApi();
        $sistema = Inconsistencias::detectarSistema($p);
        self::json(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre, 'del_sistema' => count($sistema), 'lotes' => Inconsistencias::lotes($p, $sistema)]);
    }

    /** Recibe las respuestas de la IA ({modelo, respuestas:[...]}), las suma a lo que detecta el sistema y las guarda */
    public static function resultado()
    {
        $p = self::proyectoApi();
        $cuerpo = json_decode((string)($_POST['datos'] ?? ''), true);
        if (!is_array($cuerpo) || !is_array($cuerpo['respuestas'] ?? null)) self::json(['success' => false, 'error' => 'Datos no válidos'], 422);
        $sistema = Inconsistencias::detectarSistema($p);
        $ia = Inconsistencias::normalizarIA($p, $cuerpo['respuestas']);
        $r = Inconsistencias::guardar($p, array_merge($sistema, $ia), (string)($cuerpo['modelo'] ?? ''), ['del_sistema' => count($sistema), 'de_la_ia' => count($ia)]);
        $lotes = is_array($cuerpo['lotes'] ?? null) ? Inconsistencias::guardarLotes($p, $r['corrida'], $cuerpo['lotes']) : 0;
        self::json(['success' => true, 'del_sistema' => count($sistema), 'de_la_ia' => count($ia), 'lotes' => $lotes] + $r);
    }

    /** Comprobación de vigilancia: la usa la app de escritorio cada cierto tiempo y el botón «Comprobar ahora» */
    public static function vigilancia()
    {
        $p = self::proyectoApi();
        self::json(['success' => true] + Inconsistencias::vigilar($p));
    }

    public static function vigilar()
    {
        $p = self::proyectoApi();
        $r = Inconsistencias::vigilar($p);
        $_SESSION['inconsistencias_msg'] = ['ok' => true, 'texto' => $r['primera'] ? 'Primera comprobación guardada: desde ahora se avisará de lo que cambie.' : ($r['total_nuevos'] . ' hallazgo(s) nuevo(s) y ' . count($r['cambios']) . ' cambio(s) brusco(s) desde la comprobación anterior.')];
        header('Location: /inconsistencias?proyecto=' . (int)$p->id);
        exit;
    }

    /** Revisión rápida solo con las reglas del sistema (sin IA) */
    public static function revisar()
    {
        $p = self::proyectoApi();
        $r = Inconsistencias::guardar($p, Inconsistencias::detectarSistema($p));
        $_SESSION['inconsistencias_msg'] = ['ok' => true, 'texto' => $r['nuevos'] . ' inconsistencia(s) pendiente(s) con las reglas del sistema' . ($r['ya_resueltos'] ? " ({$r['ya_resueltos']} ya resueltas o rechazadas no se repiten)" : '') . '. Para la revisión con IA usa la app de escritorio.'];
        header('Location: /inconsistencias?proyecto=' . (int)$p->id . '&corrida=' . $r['corrida']);
        exit;
    }

    /** Guarda con nombre el análisis (queda siempre disponible para resolverlo después) o lo quita de guardados */
    public static function guardarAnalisis()
    {
        $p = self::proyectoApi();
        $id = (int)($_POST['corrida'] ?? 0);
        Inconsistencias::marcarCorrida((int)$p->id, $id, empty($_POST['quitar']), (string)($_POST['nombre'] ?? ''));
        $_SESSION['inconsistencias_msg'] = ['ok' => true, 'texto' => empty($_POST['quitar']) ? 'Análisis guardado: podrás consultarlo y resolver sus inconsistencias cuando quieras.' : 'El análisis ya no está guardado (se conservan los 5 más recientes).'];
        header('Location: /inconsistencias?proyecto=' . (int)$p->id . '&corrida=' . $id);
        exit;
    }

    public static function eliminarAnalisis()
    {
        $p = self::proyectoApi();
        Inconsistencias::eliminarCorrida((int)$p->id, (int)($_POST['corrida'] ?? 0));
        $_SESSION['inconsistencias_msg'] = ['ok' => true, 'texto' => 'Análisis eliminado. Los cambios que ya hubieras aplicado al proyecto se conservan.'];
        header('Location: /inconsistencias?proyecto=' . (int)$p->id);
        exit;
    }

    public static function aceptar()
    {
        $p = self::proyectoApi();
        [$ok, $msg] = Inconsistencias::aceptar($p, (int)($_POST['id'] ?? 0));
        self::json(['success' => $ok, 'mensaje' => $msg, 'conteos' => Inconsistencias::conteos((int)$p->id, (int)($_POST['corrida'] ?? 0))], $ok ? 200 : 422);
    }

    public static function rechazar()
    {
        $p = self::proyectoApi();
        [$ok, $msg] = Inconsistencias::rechazar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::json(['success' => $ok, 'mensaje' => $msg, 'conteos' => Inconsistencias::conteos((int)$p->id, (int)($_POST['corrida'] ?? 0))], $ok ? 200 : 422);
    }

    public static function reabrir()
    {
        $p = self::proyectoApi();
        [$ok, $msg] = Inconsistencias::reabrir($p, (int)($_POST['id'] ?? 0));
        self::json(['success' => $ok, 'mensaje' => $msg, 'conteos' => Inconsistencias::conteos((int)$p->id, (int)($_POST['corrida'] ?? 0))], $ok ? 200 : 422);
    }
}
