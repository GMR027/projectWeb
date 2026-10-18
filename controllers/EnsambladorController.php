<?php

namespace Controllers;

use Model\Proyecto;
use Classes\Ensamblador;

/** Ensamblador: API para la app de escritorio (solo admin). La IA se ejecuta en el equipo del admin; aquí se extrae, valida, aplica y revierte */
class EnsambladorController
{
    private static function json(array $d, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private static function proyectoApi(): Proyecto
    {
        $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $p = is_admin() ? Proyecto::find(filter_var($fuente['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$p) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        return $p;
    }

    private static function sesion(Proyecto $p): int
    {
        $s = filter_var($_POST['sesion'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        if (!$s || !Ensamblador::sesionValida((int)$p->id, $s)) self::json(['success' => false, 'error' => 'La sesión del Ensamblador no existe: sube un documento primero'], 404);
        return $s;
    }

    private static function modos(): array
    {
        $m = json_decode((string)($_POST['modos'] ?? '[]'), true);
        return is_array($m) ? array_values(array_filter($m, fn($x) => is_string($x) && isset(Ensamblador::MODOS[$x]))) : [];
    }

    public static function estado()
    {
        $p = self::proyectoApi();
        self::json(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre, 'modos' => Ensamblador::MODOS,
            'submittals' => Ensamblador::submittals((int)$p->id), 'historial' => Ensamblador::historial((int)$p->id), 'formatos' => Ensamblador::EXTENSIONES]);
    }

    /** Recibe UN archivo (multipart: proyecto, sesion opcional, archivo) y lo agrega a la sesión */
    public static function subir()
    {
        $p = self::proyectoApi();
        $sesion = filter_var($_POST['sesion'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        if (!$sesion || !Ensamblador::sesionValida((int)$p->id, $sesion)) $sesion = Ensamblador::crearSesion((int)$p->id);
        $r = Ensamblador::agregarArchivo($p, $sesion, $_FILES['archivo'] ?? []);
        if (!$r['ok']) self::json(['success' => false, 'error' => $r['error'], 'sesion' => $sesion], 422);
        self::json(['success' => true, 'sesion' => $sesion, 'archivo' => $r['archivo'], 'csrf' => csrf_token()]);
    }

    public static function quitar()
    {
        $p = self::proyectoApi();
        $s = self::sesion($p);
        Ensamblador::quitarArchivo((int)$p->id, $s, (int)($_POST['archivo'] ?? 0));
        self::json(['success' => true]);
    }

    /** Lotes de prompt para el modelo según lo que pidió el usuario (botones + texto libre) */
    public static function contexto()
    {
        $p = self::proyectoApi();
        $s = self::sesion($p);
        $r = Ensamblador::lotes($p, $s, trim(mb_substr((string)($_POST['instruccion'] ?? ''), 0, 1000)), self::modos(), (int)($_POST['submittal_id'] ?? 0));
        self::json(['success' => true, 'csrf' => csrf_token()] + $r);
    }

    /** Respuestas de la IA ({modelo, tipo, instruccion, modos, submittal_id, respuestas}) → propuesta validada */
    public static function resultado()
    {
        $p = self::proyectoApi();
        $s = self::sesion($p);
        $c = json_decode((string)($_POST['datos'] ?? ''), true);
        if (!is_array($c) || !is_array($c['respuestas'] ?? null)) self::json(['success' => false, 'error' => 'Datos no válidos'], 422);
        if (($c['tipo'] ?? '') === 'pregunta') {
            $t = is_array($c['respuestas'][0] ?? null) ? trim((string)($c['respuestas'][0]['respuesta'] ?? '')) : '';
            self::json(['success' => true, 'respuesta' => $t !== '' ? mb_substr($t, 0, 4000) : 'No encontré eso en el documento.']);
        }
        $modos = array_values(array_filter((array)($c['modos'] ?? []), fn($x) => is_string($x) && isset(Ensamblador::MODOS[$x])));
        $r = Ensamblador::proponer($p, $c['respuestas'], $modos, (int)($c['submittal_id'] ?? 0), $s);
        if (!$r['items']) self::json(['success' => true, 'propuesta' => null, 'notas' => $r['notas']]);
        $id = Ensamblador::guardarPropuesta((int)$p->id, $s, trim(mb_substr((string)($c['instruccion'] ?? ''), 0, 1000)), $modos, (string)($c['modelo'] ?? ''), $r['items']);
        self::json(['success' => true, 'propuesta' => Ensamblador::propuesta((int)$p->id, $id), 'notas' => $r['notas']]);
    }

    public static function propuesta()
    {
        $p = self::proyectoApi();
        $x = Ensamblador::propuesta((int)$p->id, (int)($_GET['id'] ?? 0));
        if (!$x) self::json(['success' => false, 'error' => 'Propuesta no encontrada'], 404);
        self::json(['success' => true, 'propuesta' => $x]);
    }

    public static function aplicar()
    {
        $p = self::proyectoApi();
        $ids = json_decode((string)($_POST['items'] ?? '[]'), true);
        if (!is_array($ids) || !$ids) self::json(['success' => false, 'error' => 'Elige al menos un cambio'], 422);
        try {
            self::json(['success' => true] + Ensamblador::aplicar($p, (int)($_POST['id'] ?? 0), array_map('strval', $ids)));
        } catch (\Throwable $e) {
            self::json(['success' => false, 'error' => $e->getMessage()], 409);
        }
    }

    public static function revertir()
    {
        $p = self::proyectoApi();
        try {
            self::json(['success' => true] + Ensamblador::revertir($p, (int)($_POST['id'] ?? 0)));
        } catch (\Throwable $e) {
            self::json(['success' => false, 'error' => $e->getMessage()], 409);
        }
    }

    public static function rechazar()
    {
        $p = self::proyectoApi();
        if (!Ensamblador::rechazar($p, (int)($_POST['id'] ?? 0))) self::json(['success' => false, 'error' => 'La propuesta ya no está pendiente'], 409);
        self::json(['success' => true]);
    }

    // ---------- Ordenar y renumerar por fechas (IA): la propuesta la calcula el servidor, la IA solo la explica ----------

    public static function ordenarPropuesta()
    {
        $p = self::proyectoApi();
        $prop = \Classes\Renumeracion::proponerPorFechas((int)$p->id);
        $u = \Classes\Renumeracion::ultimaDeshacible((int)$p->id);
        self::json(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre, 'deshacer' => $u] + $prop);
    }

    public static function ordenarAplicar()
    {
        $p = self::proyectoApi();
        $sel = json_decode((string)($_POST['seleccion'] ?? '[]'), true);
        $r = is_array($sel) ? \Classes\Renumeracion::aplicarPorFechas((int)$p->id, (string)($_POST['huella'] ?? ''), $sel) : 'Selección no válida';
        if (is_string($r)) self::json(['success' => false, 'error' => $r], 409);
        self::json(['success' => true] + $r);
    }

    public static function ordenarRechazar()
    {
        $p = self::proyectoApi();
        \Classes\Auditoria::registrar((int)$p->id, 'ordenar', 'rechazado', 'ordenar_renumerar', 'Se descartó la propuesta de ordenar y renumerar por fechas (IA)', null, null, null, mb_substr((string)($_POST['modelo'] ?? ''), 0, 150), ['proyecto', (int)$p->id, 'Orden del programa']);
        self::json(['success' => true]);
    }

    public static function ordenarDeshacer()
    {
        $p = self::proyectoApi();
        $e = \Classes\Renumeracion::deshacer((int)$p->id, (int)($_POST['id'] ?? 0));
        if ($e !== '') self::json(['success' => false, 'error' => $e], 409);
        self::json(['success' => true]);
    }
}
