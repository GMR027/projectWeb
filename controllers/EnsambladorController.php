<?php

namespace Controllers;

use Model\Proyecto;
use Classes\Ensamblador;

/** Ensamblador: API para la app de escritorio (solo admin). La IA se ejecuta en el equipo del admin; aquí se extrae, valida, aplica y revierte */
class EnsambladorController
{
    private static function sesion(Proyecto $p): int
    {
        $s = filter_var($_POST['sesion'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        if (!$s || !Ensamblador::sesionValida((int)$p->id, $s)) json_respuesta(['success' => false, 'error' => 'La sesión del Ensamblador no existe: sube un documento primero'], 404);
        return $s;
    }

    private static function modos(): array
    {
        $m = json_decode((string)($_POST['modos'] ?? '[]'), true);
        return is_array($m) ? array_values(array_filter($m, fn($x) => is_string($x) && isset(Ensamblador::MODOS[$x]))) : [];
    }

    public static function estado()
    {
        $p = proyecto_api();
        json_respuesta(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre, 'modos' => Ensamblador::MODOS,
            'submittals' => Ensamblador::submittals((int)$p->id), 'historial' => Ensamblador::historial((int)$p->id), 'formatos' => Ensamblador::EXTENSIONES]);
    }

    /** Recibe UN archivo (multipart: proyecto, sesion opcional, archivo) y lo agrega a la sesión */
    public static function subir()
    {
        $p = proyecto_api();
        $sesion = filter_var($_POST['sesion'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        if (!$sesion || !Ensamblador::sesionValida((int)$p->id, $sesion)) $sesion = Ensamblador::crearSesion((int)$p->id);
        $r = Ensamblador::agregarArchivo($p, $sesion, $_FILES['archivo'] ?? []);
        if (!$r['ok']) json_respuesta(['success' => false, 'error' => $r['error'], 'sesion' => $sesion], 422);
        json_respuesta(['success' => true, 'sesion' => $sesion, 'archivo' => $r['archivo'], 'csrf' => csrf_token()]);
    }

    public static function quitar()
    {
        $p = proyecto_api();
        $s = self::sesion($p);
        Ensamblador::quitarArchivo((int)$p->id, $s, (int)($_POST['archivo'] ?? 0));
        json_respuesta(['success' => true]);
    }

    /** Lotes de prompt para el modelo según lo que pidió el usuario (botones + texto libre) */
    public static function contexto()
    {
        $p = proyecto_api();
        $s = self::sesion($p);
        $r = Ensamblador::lotes($p, $s, trim(mb_substr((string)($_POST['instruccion'] ?? ''), 0, 1000)), self::modos(), (int)($_POST['submittal_id'] ?? 0));
        json_respuesta(['success' => true, 'csrf' => csrf_token()] + $r);
    }

    /** Respuestas de la IA ({modelo, tipo, instruccion, modos, submittal_id, respuestas}) → propuesta validada */
    public static function resultado()
    {
        $p = proyecto_api();
        $s = self::sesion($p);
        $c = json_decode((string)($_POST['datos'] ?? ''), true);
        if (!is_array($c) || !is_array($c['respuestas'] ?? null)) json_respuesta(['success' => false, 'error' => 'Datos no válidos'], 422);
        if (($c['tipo'] ?? '') === 'pregunta') {
            $t = is_array($c['respuestas'][0] ?? null) ? trim((string)($c['respuestas'][0]['respuesta'] ?? '')) : '';
            json_respuesta(['success' => true, 'respuesta' => $t !== '' ? mb_substr($t, 0, 4000) : 'No encontré eso en el documento.']);
        }
        $modos = array_values(array_filter((array)($c['modos'] ?? []), fn($x) => is_string($x) && isset(Ensamblador::MODOS[$x])));
        $r = Ensamblador::proponer($p, $c['respuestas'], $modos, (int)($c['submittal_id'] ?? 0), $s);
        if (!$r['items']) json_respuesta(['success' => true, 'propuesta' => null, 'notas' => $r['notas']]);
        $id = Ensamblador::guardarPropuesta((int)$p->id, $s, trim(mb_substr((string)($c['instruccion'] ?? ''), 0, 1000)), $modos, (string)($c['modelo'] ?? ''), $r['items']);
        json_respuesta(['success' => true, 'propuesta' => Ensamblador::propuesta((int)$p->id, $id), 'notas' => $r['notas']]);
    }

    public static function propuesta()
    {
        $p = proyecto_api();
        $x = Ensamblador::propuesta((int)$p->id, (int)($_GET['id'] ?? 0));
        if (!$x) json_respuesta(['success' => false, 'error' => 'Propuesta no encontrada'], 404);
        json_respuesta(['success' => true, 'propuesta' => $x]);
    }

    public static function aplicar()
    {
        $p = proyecto_api();
        $ids = json_decode((string)($_POST['items'] ?? '[]'), true);
        if (!is_array($ids) || !$ids) json_respuesta(['success' => false, 'error' => 'Elige al menos un cambio'], 422);
        try {
            json_respuesta(['success' => true] + Ensamblador::aplicar($p, (int)($_POST['id'] ?? 0), array_map('strval', $ids)));
        } catch (\Throwable $e) {
            json_respuesta(['success' => false, 'error' => $e->getMessage()], 409);
        }
    }

    public static function revertir()
    {
        $p = proyecto_api();
        try {
            json_respuesta(['success' => true] + Ensamblador::revertir($p, (int)($_POST['id'] ?? 0)));
        } catch (\Throwable $e) {
            json_respuesta(['success' => false, 'error' => $e->getMessage()], 409);
        }
    }

    public static function rechazar()
    {
        $p = proyecto_api();
        if (!Ensamblador::rechazar($p, (int)($_POST['id'] ?? 0))) json_respuesta(['success' => false, 'error' => 'La propuesta ya no está pendiente'], 409);
        json_respuesta(['success' => true]);
    }

    // ---------- Ordenar y renumerar por fechas (IA): la propuesta la calcula el servidor, la IA solo la explica ----------

    public static function ordenarPropuesta()
    {
        $p = proyecto_api();
        $prop = \Classes\Renumeracion::proponerPorFechas((int)$p->id);
        $u = \Classes\Renumeracion::ultimaDeshacible((int)$p->id);
        json_respuesta(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre, 'deshacer' => $u] + $prop);
    }

    public static function ordenarAplicar()
    {
        $p = proyecto_api();
        $sel = json_decode((string)($_POST['seleccion'] ?? '[]'), true);
        $r = is_array($sel) ? \Classes\Renumeracion::aplicarPorFechas((int)$p->id, (string)($_POST['huella'] ?? ''), $sel) : 'Selección no válida';
        if (is_string($r)) json_respuesta(['success' => false, 'error' => $r], 409);
        json_respuesta(['success' => true] + $r);
    }

    public static function ordenarRechazar()
    {
        $p = proyecto_api();
        \Classes\Auditoria::registrar((int)$p->id, 'ordenar', 'rechazado', 'ordenar_renumerar', 'Se descartó la propuesta de ordenar y renumerar por fechas (IA)', null, null, null, mb_substr((string)($_POST['modelo'] ?? ''), 0, 150), ['proyecto', (int)$p->id, 'Orden del programa']);
        json_respuesta(['success' => true]);
    }

    public static function ordenarDeshacer()
    {
        $p = proyecto_api();
        $e = \Classes\Renumeracion::deshacer((int)$p->id, (int)($_POST['id'] ?? 0));
        if ($e !== '') json_respuesta(['success' => false, 'error' => $e], 409);
        json_respuesta(['success' => true]);
    }
}
