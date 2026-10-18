<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Programador;
use Classes\ProgramadorAcciones;

/** Programador de actividades (IA): la app de escritorio manda el análisis del modelo y aquí se calcula y se muestra el programa propuesto */
class ProgramadorController
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
        if (!is_admin()) { http_response_code(403); exit('Solo el administrador puede usar el Programador de actividades'); }
        $lista = Programador::lista((int)$proyecto->id);
        $id = filter_var($_GET['propuesta'] ?? ($lista[0]['id'] ?? null), FILTER_VALIDATE_INT);
        $propuesta = $id ? Programador::obtener((int)$proyecto->id, $id) : null;
        $otraId = filter_var($_GET['comparar'] ?? null, FILTER_VALIDATE_INT);
        $otra = $propuesta && $otraId && $otraId !== $id ? Programador::obtener((int)$proyecto->id, $otraId) : null;
        $router->render('programador/index', [
            'titulo' => 'Programador de actividades - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'lista' => $lista, 'propuesta' => $propuesta,
            'impacto' => $propuesta ? ProgramadorAcciones::impacto($proyecto, $propuesta) : [],
            'curva' => $propuesta ? ProgramadorAcciones::curva($proyecto, $propuesta['filas']) : [],
            'seguimiento' => $propuesta ? ProgramadorAcciones::seguimiento((int)$propuesta['id']) : [],
            'otra' => $otra, 'comparacion' => $otra ? ProgramadorAcciones::comparar($propuesta, $otra) : null,
            'mensaje' => $_SESSION['programador_msg'] ?? null,
        ]);
        unset($_SESSION['programador_msg']);
    }

    /** Proyectos entre los que elegir en la app de escritorio (no depende de qué página esté abierta) */
    public static function proyectos()
    {
        if (!is_admin()) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        self::json(['success' => true, 'proyectos' => array_map(fn($p) => ['id' => (int)$p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre], Proyecto::all('nombre ASC'))]);
    }

    /** Lotes de prompt para el modelo (la app de escritorio los envía y devuelve las respuestas a /propuesta) */
    public static function contexto()
    {
        $p = self::proyectoApi();
        $ctx = Programador::contexto($p);
        $lotes = Programador::lotes($ctx);
        self::json(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $ctx['proyecto']['nombre'], 'actividades' => count($ctx['actividades']), 'analizables' => count(Programador::analizables($ctx)), 'lotes' => $lotes]);
    }

    /** Fase de integración: con lo que respondió cada paquete, devuelve el prompt que los conecta (o null si no hace falta) */
    public static function integracion()
    {
        $p = self::proyectoApi();
        $cuerpo = json_decode((string)($_POST['datos'] ?? ''), true);
        if (!is_array($cuerpo) || !is_array($cuerpo['respuestas'] ?? null)) self::json(['success' => false, 'error' => 'Datos no válidos'], 422);
        self::json(['success' => true, 'lote' => Programador::integracion(Programador::contexto($p), $cuerpo['respuestas'])]);
    }

    /** Fase de revisión: con lo obtenido de los paquetes, devuelve el prompt para que la IA detecte qué se puede mejorar (o null) */
    public static function revision()
    {
        $p = self::proyectoApi();
        $cuerpo = json_decode((string)($_POST['datos'] ?? ''), true);
        if (!is_array($cuerpo) || !is_array($cuerpo['respuestas'] ?? null)) self::json(['success' => false, 'error' => 'Datos no válidos'], 422);
        self::json(['success' => true, 'lote' => Programador::revision($p, $cuerpo['respuestas'])]);
    }

    /** Recibe las respuestas de la IA (JSON: {modelo, respuestas:[...]}) y guarda la propuesta */
    public static function propuesta()
    {
        $p = self::proyectoApi();
        $cuerpo = json_decode((string)($_POST['datos'] ?? ''), true);
        if (!is_array($cuerpo) || !is_array($cuerpo['respuestas'] ?? null)) self::json(['success' => false, 'error' => 'Datos no válidos'], 422);
        $calculo = Programador::construir($p, $cuerpo['respuestas']);
        $id = Programador::guardar($p, $calculo, (string)($cuerpo['modelo'] ?? ''));
        self::json(['success' => true, 'id' => $id, 'resumen' => array_diff_key($calculo['resumen'], ['obstaculos' => 1, 'mejoras' => 1]) + ['n_mejoras' => count($calculo['resumen']['mejoras'])]]);
    }

    /** Guarda la propuesta con un nombre (queda siempre disponible en el Programa) o la quita de guardadas */
    public static function guardar()
    {
        $p = self::proyectoApi();
        $id = (int)($_POST['id'] ?? 0);
        if (!Programador::obtener((int)$p->id, $id)) self::json(['success' => false, 'error' => 'Propuesta no encontrada'], 404);
        Programador::marcar((int)$p->id, $id, empty($_POST['quitar']), (string)($_POST['nombre'] ?? ''));
        header('Location: /programador?proyecto=' . (int)$p->id . '&propuesta=' . $id);
        exit;
    }

    /** Acepta una partida de la propuesta: sus fechas, duraciones y predecesoras pasan al programa de obra */
    public static function aceptar()
    {
        $p = self::proyectoApi();
        $id = (int)($_POST['id'] ?? 0);
        [$ok, $msg] = ProgramadorAcciones::aceptarPartida($p, $id, (int)($_POST['partida'] ?? 0));
        $_SESSION['programador_msg'] = ['ok' => $ok, 'texto' => $msg];
        header('Location: /programador?proyecto=' . (int)$p->id . '&propuesta=' . $id);
        exit;
    }

    public static function deshacer()
    {
        $p = self::proyectoApi();
        $id = (int)($_POST['id'] ?? 0);
        [$ok, $msg] = ProgramadorAcciones::deshacerPartida($p, $id, (int)($_POST['partida'] ?? 0));
        $_SESSION['programador_msg'] = ['ok' => $ok, 'texto' => $msg];
        header('Location: /programador?proyecto=' . (int)$p->id . '&propuesta=' . $id);
        exit;
    }

    /** Estado y responsable de un obstáculo o mejora (fetch desde la vista) */
    public static function seguimiento()
    {
        $p = self::proyectoApi();
        $ok = ProgramadorAcciones::marcarSeguimiento((int)$p->id, (int)($_POST['id'] ?? 0), (string)($_POST['clave'] ?? ''), (string)($_POST['estado'] ?? ''), (string)($_POST['responsable'] ?? ''));
        self::json(['success' => $ok], $ok ? 200 : 422);
    }

    public static function exportar()
    {
        $p = self::proyectoApi();
        $prop = Programador::obtener((int)$p->id, (int)($_GET['propuesta'] ?? 0));
        if (!$prop) { http_response_code(404); exit('Propuesta no encontrada'); }
        $base = date('Y_m_d') . '_' . slug_archivo(usuario_actual()['nombre'] ?? 'programador');
        if (($_GET['formato'] ?? '') === 'pdf') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $base . '.pdf"');
            echo ProgramadorAcciones::pdf($p, $prop);
        } else {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $base . '.xlsx"');
            echo ProgramadorAcciones::xlsx($p, $prop);
        }
        exit;
    }

    public static function eliminar()
    {
        $p = self::proyectoApi();
        Programador::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        header('Location: /programador?proyecto=' . (int)$p->id);
        exit;
    }
}
