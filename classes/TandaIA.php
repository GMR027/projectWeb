<?php

namespace Classes;

use Model\Proyecto;

/**
 * Revertir por tanda: deshace lo que la IA aplicó desde cierta hora, apoyándose en la auditoría (`auditoria_cambios`).
 * Cada cambio se revierte con el mismo método que usa su herramienta por separado (reabrir un hallazgo, deshacer una partida…),
 * del más reciente al más antiguo, y deja su propio evento en la auditoría. Lo que ya no se puede deshacer se informa, no se fuerza.
 */
class TandaIA
{
    use AccesoDb;

    public const MAX = 300;
    private const TABLAS = ['inconsistencias' => 'inconsistencias', 'materiales' => 'analisis_materiales_hallazgos'];

    /** Fecha y hora válidas (Y-m-d H:i:s) o null. Acepta también «Y-m-d\TH:i» de los campos datetime-local */
    public static function fecha(string $v): ?string
    {
        $v = str_replace('T', ' ', trim($v));
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $f) {
            $d = \DateTime::createFromFormat('!' . $f, $v);
            if ($d && $d->format($f) === $v) return $d->format('Y-m-d H:i:s');
        }
        return null;
    }

    /**
     * Cambios aplicados desde $desde que todavía se pueden revertir, del más reciente al más antiguo.
     * @return list<array{id: int, herramienta: string, accion: string, resumen: string, entidad: string, creado: string, usuario: string, modelo: string}>
     */
    public static function candidatos(int $pid, string $desde): array
    {
        $out = [];
        $sql = "SELECT * FROM auditoria_cambios WHERE proyecto_id = $pid AND evento = 'aplicado' AND creado >= '" . self::esc($desde) . "' AND herramienta IN ('inconsistencias','materiales','normativa','programador') ORDER BY id DESC LIMIT " . self::MAX;
        foreach (self::filas($sql) as $a) {
            if (!self::revertible($pid, $a)) continue;
            $out[] = ['id' => (int)$a['id'], 'herramienta' => $a['herramienta'], 'accion' => $a['accion'], 'resumen' => $a['resumen'], 'entidad' => trim($a['entidad_tipo'] . ' ' . $a['entidad_nombre']), 'creado' => $a['creado'], 'usuario' => $a['usuario'], 'modelo' => $a['modelo']];
        }
        return $out;
    }

    /** ¿Sigue vigente el cambio (no se revirtió ya) y hay con qué deshacerlo? */
    private static function revertible(int $pid, array $a): bool
    {
        $ref = (int)$a['referencia_id'];
        if (isset(self::TABLAS[$a['herramienta']])) {
            $h = self::filas('SELECT estado, antes_json FROM ' . self::TABLAS[$a['herramienta']] . " WHERE id = $ref AND proyecto_id = $pid")[0] ?? null;
            return $h && $h['estado'] === 'aceptada' && $h['antes_json'] !== null;
        }
        if ($a['herramienta'] === 'normativa') {
            return $a['accion'] === 'vincular_normativa' && $ref > 0 && self::filas("SELECT 1 FROM normativa_aplicaciones WHERE id = $ref AND proyecto_id = $pid AND estado = 'aceptada' AND origen <> 'manual'") !== [];
        }
        if ($a['herramienta'] === 'programador') {
            if ($a['accion'] !== 'aceptar_partida' || !$a['entidad_id']) return false;
            $c = self::filas("SELECT aceptadas_json FROM programador_propuestas WHERE id = $ref AND proyecto_id = $pid")[0]['aceptadas_json'] ?? null;
            $m = $c ? json_decode($c, true) : [];
            return is_array($m) && isset($m[(int)$a['entidad_id']]);
        }
        return false;
    }

    private static function revertir(Proyecto $p, array $a): array
    {
        $pid = (int)$p->id;
        $ref = (int)$a['referencia_id'];
        switch ($a['herramienta']) {
            case 'inconsistencias': return Inconsistencias::reabrir($p, $ref);
            case 'materiales': return AnalisisMateriales::reabrir($p, $ref);
            case 'programador': return HistorialCambios::silenciar(fn() => ProgramadorAcciones::deshacerPartida($p, $ref, (int)$a['entidad_id']));
            case 'normativa':
                self::db()->query("UPDATE normativa_aplicaciones SET estado = 'propuesta', resuelto_por = '' WHERE id = $ref AND proyecto_id = $pid");
                Auditoria::registrar($pid, 'normativa', 'reabierto', 'vincular_normativa', 'Vínculo de normativa devuelto a propuesta (revertir por tanda): ' . $a['entidad_nombre'], null, null, $ref, '', [$a['entidad_tipo'], $a['entidad_id'] === null ? null : (int)$a['entidad_id'], $a['entidad_nombre']]);
                return [true, 'Vínculo devuelto a propuesta'];
        }
        return [false, 'No se puede revertir'];
    }

    /**
     * Revierte los cambios elegidos (todos los candidatos si $ids es null).
     * @return array{revertidos: int, fallos: list<array{resumen: string, motivo: string}>}
     */
    public static function aplicar(Proyecto $p, string $desde, ?array $ids = null): array
    {
        $pid = (int)$p->id;
        $quiero = $ids === null ? null : array_flip(array_map('intval', $ids));
        $res = ['revertidos' => 0, 'fallos' => []];
        $filas = self::filas("SELECT * FROM auditoria_cambios WHERE proyecto_id = $pid AND evento = 'aplicado' AND creado >= '" . self::esc($desde) . "' AND herramienta IN ('inconsistencias','materiales','normativa','programador') ORDER BY id DESC LIMIT " . self::MAX);
        foreach ($filas as $a) {
            if ($quiero !== null && !isset($quiero[(int)$a['id']])) continue;
            if (!self::revertible($pid, $a)) continue;
            [$ok, $msg] = self::revertir($p, $a);
            if ($ok) $res['revertidos']++;
            else $res['fallos'][] = ['resumen' => $a['resumen'], 'motivo' => $msg];
        }
        return $res;
    }
}
