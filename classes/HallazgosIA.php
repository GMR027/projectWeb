<?php

namespace Classes;

use Model\Proyecto;

/**
 * Base de las herramientas que guardan hallazgos con una solución propuesta (Inconsistencias, Análisis de materiales):
 * corridas (análisis guardables), listado, conteos y el flujo aceptar → aplicar / rechazar / reabrir → revertir, siempre con Auditoria.
 * Cada clase hija pone sus tablas y textos en constantes y lo que es suyo en los hooks: detectar, guardar, lotes e IA.
 */
abstract class HallazgosIA
{
    use AccesoDb;

    // Las hijas las sobrescriben (static:: en cada uso)
    protected const TABLA_CORRIDAS = '';
    protected const TABLA_HALLAZGOS = '';
    protected const TABLA_LOTES = '';
    /** Clave en Auditoria y en Revisiones */
    protected const HERRAMIENTA = '';
    /** Qué cubre cada lote del registro (columna `{x}_json` y clave del arreglo): actividades | materiales */
    protected const ENTIDAD_LOTES = '';
    /** Orden extra del listado antes del id (p. ej. 'categoria, ') */
    protected const ORDEN_EXTRA = '';
    protected const TXT_ESTE = 'El hallazgo';
    protected const TXT_RECHAZADO = 'Rechazado';
    protected const TXT_CAMBIO = '';

    // ---------- hooks de la hija ----------

    /** Estado de los datos que la solución va a tocar al detectar (null si no aplica) */
    abstract protected static function esperadoDe(int $pid, string $accion, array $params): ?array;
    /** Parámetros ya validados contra la base, o null si la solución dejó de ser aplicable */
    abstract protected static function paramsValidos(int $pid, string $accion, array $p): ?array;
    /** @return array{0: bool, 1: string, 2: ?array} resultado, mensaje y valores previos (null si no cambia datos) */
    abstract protected static function ejecutarAccion(Proyecto $p, string $accion, array $x): array;
    /** @return array{0: bool, 1: string} */
    abstract protected static function restaurarAccion(Proyecto $p, array $antes): array;
    /** Agrega a cada fila del listado sus entidades ya resueltas (nombres) y la solución decodificada */
    abstract protected static function enriquecer(int $pid, array $filas): array;

    // ---------- corridas ----------

    public static function corridas(int $pid): array
    {
        return self::filas("SELECT c.id, c.nombre, c.guardada, c.modelo, c.del_sistema, c.de_la_ia, c.creado,
                   SUM(i.estado = 'pendiente') AS pendientes, SUM(i.estado = 'aceptada') AS aceptadas, SUM(i.estado = 'rechazada') AS rechazadas, COUNT(i.id) AS total
            FROM " . static::TABLA_CORRIDAS . ' c LEFT JOIN ' . static::TABLA_HALLAZGOS . " i ON i.corrida_id = c.id WHERE c.proyecto_id = $pid GROUP BY c.id ORDER BY c.id DESC");
    }

    /** Guarda con nombre un análisis (queda siempre disponible) o lo quita de guardados */
    public static function marcarCorrida(int $pid, int $id, bool $guardar, string $nombre = ''): void
    {
        $db = self::db();
        $n = $db->real_escape_string(self::corto($nombre, 150));
        $db->query('UPDATE ' . static::TABLA_CORRIDAS . ' SET guardada = ' . ($guardar ? 1 : 0) . ", nombre = '" . ($guardar ? $n : '') . "' WHERE id = $id AND proyecto_id = $pid");
    }

    public static function eliminarCorrida(int $pid, int $id): void
    {
        self::db()->query('DELETE FROM ' . static::TABLA_CORRIDAS . " WHERE id = $id AND proyecto_id = $pid");
    }

    /** Registro por lote de un análisis (sin la respuesta cruda) */
    public static function lotesDeCorrida(int $pid, int $corrida): array
    {
        $col = static::ENTIDAD_LOTES;
        $out = [];
        foreach (self::filas("SELECT id, orden, titulo, estado, error, {$col}_json, hallazgos_json FROM " . static::TABLA_LOTES . " WHERE proyecto_id = $pid AND corrida_id = $corrida ORDER BY orden") as $f) {
            $f[$col] = json_decode((string)$f["{$col}_json"], true) ?: [];
            $f['hallazgos'] = json_decode((string)$f['hallazgos_json'], true) ?: [];
            unset($f["{$col}_json"], $f['hallazgos_json']);
            $out[] = $f;
        }
        return $out;
    }

    // ---------- listado ----------

    public static function listar(int $pid, array $f = []): array
    {
        $w = ["proyecto_id = $pid"];
        if (!empty($f['corrida'])) $w[] = 'corrida_id = ' . (int)$f['corrida'];
        if (in_array($f['estado'] ?? '', ['pendiente', 'aceptada', 'rechazada'], true)) $w[] = "estado = '{$f['estado']}'";
        if (isset(static::SEVERIDADES[$f['severidad'] ?? ''])) $w[] = "severidad = '{$f['severidad']}'";
        if (isset(static::CATEGORIAS[$f['categoria'] ?? ''])) $w[] = "categoria = '{$f['categoria']}'";
        if (in_array($f['origen'] ?? '', ['sistema', 'ia'], true)) $w[] = "origen = '{$f['origen']}'";
        $filas = self::filas('SELECT * FROM ' . static::TABLA_HALLAZGOS . ' WHERE ' . implode(' AND ', $w) . " ORDER BY FIELD(estado,'pendiente','aceptada','rechazada'), FIELD(severidad,'critica','alta','media','baja'), " . static::ORDEN_EXTRA . 'id');
        return static::enriquecer($pid, $filas);
    }

    public static function conteos(int $pid, int $corrida = 0): array
    {
        $c = ['pendiente' => 0, 'aceptada' => 0, 'rechazada' => 0, 'criticas' => 0];
        foreach (self::filas('SELECT estado, severidad, COUNT(*) n FROM ' . static::TABLA_HALLAZGOS . " WHERE proyecto_id = $pid" . ($corrida ? " AND corrida_id = $corrida" : '') . ' GROUP BY estado, severidad') as $f) {
            $c[$f['estado']] += (int)$f['n'];
            if ($f['estado'] === 'pendiente' && in_array($f['severidad'], ['alta', 'critica'], true)) $c['criticas'] += (int)$f['n'];
        }
        return $c;
    }

    // ---------- aceptar / rechazar / reabrir ----------

    private static function obtener(int $pid, int $id): ?array
    {
        return self::filas('SELECT * FROM ' . static::TABLA_HALLAZGOS . " WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    private static function cerrar(int $pid, int $id, string $estado, ?array $antes): void
    {
        $db = self::db();
        $a = $antes === null ? 'NULL' : "'" . $db->real_escape_string(json_encode($antes, JSON_UNESCAPED_UNICODE)) . "'";
        $quien = $db->real_escape_string(usuario_actual()['nombre'] ?? '');
        $db->query('UPDATE ' . static::TABLA_HALLAZGOS . " SET estado = '$estado', antes_json = $a, resuelto_por = '$quien', resuelto_en = NOW() WHERE id = $id AND proyecto_id = $pid");
    }

    public static function rechazar(int $pid, int $id): array
    {
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] !== 'pendiente') return [false, static::TXT_ESTE . ' ya no está pendiente'];
        self::cerrar($pid, $id, 'rechazada', null);
        $sol = json_decode($h['solucion_json'], true) ?: [];
        Auditoria::registrar($pid, static::HERRAMIENTA, 'rechazado', (string)($sol['accion'] ?? 'nota'), 'Rechazado: ' . $h['titulo'], null, (array)($sol['params'] ?? []), $id);
        return [true, static::TXT_RECHAZADO . ': no se volverá a mostrar'];
    }

    /** Vuelve a dejar pendiente algo rechazado, o revierte lo aplicado si la acción lo permite */
    public static function reabrir(Proyecto $p, int $id): array
    {
        $pid = (int)$p->id;
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] === 'pendiente') return [false, 'No hay nada que reabrir'];
        $revirtio = $h['estado'] === 'aceptada' && $h['antes_json'] !== null;
        if ($revirtio) {
            [$ok, $msg] = static::restaurarAccion($p, json_decode($h['antes_json'], true) ?: []);
            if (!$ok) return [false, $msg];
        }
        self::db()->query('UPDATE ' . static::TABLA_HALLAZGOS . " SET estado = 'pendiente', antes_json = NULL, resuelto_por = '', resuelto_en = NULL WHERE id = $id AND proyecto_id = $pid");
        $sol = json_decode($h['solucion_json'], true) ?: [];
        Auditoria::registrar($pid, static::HERRAMIENTA, $revirtio ? 'revertido' : 'reabierto', (string)($sol['accion'] ?? 'nota'), ($revirtio ? 'Cambio revertido: ' : 'Vuelve a pendiente: ') . $h['titulo'], $revirtio ? json_decode($h['antes_json'], true) : null, null, $id, '', Auditoria::entidad($pid, (string)($sol['accion'] ?? 'nota'), (array)($sol['params'] ?? [])));
        return [true, $revirtio ? 'Cambio revertido; vuelve a estar pendiente' : 'Vuelve a estar pendiente'];
    }

    public static function aceptar(Proyecto $p, int $id): array
    {
        $pid = (int)$p->id;
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] !== 'pendiente') return [false, static::TXT_ESTE . ' ya no está pendiente'];
        $s = json_decode($h['solucion_json'], true) ?: [];
        $accion = $s['accion'] ?? 'nota';
        $params = static::paramsValidos($pid, $accion, (array)($s['params'] ?? []));
        if ($params === null || !isset(static::ACCIONES[$accion])) return [false, 'La solución ya no es aplicable (los datos cambiaron). Rechaza o resuelve a mano.'];
        if (!empty($s['esperado']) && static::esperadoDe($pid, $accion, $params) !== $s['esperado']) return [false, static::TXT_CAMBIO];
        $db = self::db();
        $db->begin_transaction();
        try {
            [$ok, $msg, $antes] = static::ejecutarAccion($p, $accion, $params);
            if (!$ok) { $db->rollback(); return [false, $msg]; }
            self::cerrar($pid, $id, 'aceptada', $antes);
            Auditoria::registrar($pid, static::HERRAMIENTA, 'aplicado', $accion, $msg, $antes, $params, $id, $h['origen'] === 'ia' ? (string)$h['modelo'] : '');
            // un análisis con cambios aplicados no debe rotar
            $db->query('UPDATE ' . static::TABLA_CORRIDAS . ' SET guardada = 1, nombre = IF(nombre = \'\', CONCAT(\'Análisis \', DATE_FORMAT(creado, \'%d/%m/%Y\')), nombre) WHERE id = ' . (int)$h['corrida_id']);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, 'No se pudo aplicar: ' . $e->getMessage()];
        }
        return [true, $msg];
    }
}
