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
    /** Etiqueta legible de cada grupo de problema (regla del sistema o «categoria:accion» de la IA) */
    public const GRUPOS = [];
    protected const TXT_ESTE = 'El hallazgo';
    protected const TXT_RECHAZADO = 'Rechazado';
    protected const TXT_CAMBIO = '';

    /** Días que una propuesta que mueve el programa sigue siendo aplicable */
    public const VIGENCIA_DIAS = 14;
    /** Acciones que NUNCA se aplican por lote: mueven fechas, avance, duración o relaciones */
    public const ACCIONES_RIESGO = ['ajustar_fechas', 'ajustar_progreso', 'ajustar_duracion', 'agregar_dependencia', 'quitar_dependencia', 'recalcular_partida', 'sincronizar_instalacion', 'sincronizar_msr_material'];
    /** Motivos de rechazo: sirven para medir la precisión de cada regla y del modelo */
    public const MOTIVOS = [
        'no_aplica' => 'No aplica al proyecto',
        'dato_correcto' => 'El dato actual es correcto',
        'ya_resuelto' => 'Ya estaba resuelto',
        'riesgo' => 'Aplicarlo tendría un riesgo',
        'sin_evidencia' => 'No hay evidencia suficiente',
        'otro' => 'Otro motivo',
    ];

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
        if (!empty($f['id'])) $w[] = 'id = ' . (int)$f['id'];
        if (!empty($f['corrida'])) $w[] = 'corrida_id = ' . (int)$f['corrida'];
        if (in_array($f['estado'] ?? '', ['pendiente', 'aceptada', 'rechazada', 'obsoleta'], true)) $w[] = "estado = '{$f['estado']}'";
        if (isset(static::SEVERIDADES[$f['severidad'] ?? ''])) $w[] = "severidad = '{$f['severidad']}'";
        if (isset(static::CATEGORIAS[$f['categoria'] ?? ''])) $w[] = "categoria = '{$f['categoria']}'";
        if (in_array($f['origen'] ?? '', ['sistema', 'ia'], true)) $w[] = "origen = '{$f['origen']}'";
        if (!empty($f['grupo'])) $w[] = "grupo = '" . self::db()->real_escape_string((string)$f['grupo']) . "'";
        // Accionable = propone un cambio de datos; observación = solo informa (nota)
        if (($f['tipo'] ?? '') === 'accionable') $w[] = "JSON_UNQUOTE(JSON_EXTRACT(solucion_json, '$.accion')) <> 'nota'";
        if (($f['tipo'] ?? '') === 'observacion') $w[] = "JSON_UNQUOTE(JSON_EXTRACT(solucion_json, '$.accion')) = 'nota'";
        $filas = self::filas('SELECT * FROM ' . static::TABLA_HALLAZGOS . ' WHERE ' . implode(' AND ', $w) . " ORDER BY FIELD(estado,'pendiente','obsoleta','aceptada','rechazada'), FIELD(severidad,'critica','alta','media','baja'), " . static::ORDEN_EXTRA . 'id');
        return static::enriquecer($pid, $filas);
    }

    /** Un hallazgo enriquecido para el editor en línea (se pide al abrir el aviso, no se manda en la lista) */
    public static function paraEditor(int $pid, int $id): ?array
    {
        return self::listar($pid, ['id' => $id])[0] ?? null;
    }

    /**
     * Agrupa el listado por PROBLEMA (`grupo`): todas las actividades con la misma regla juntas.
     * Devuelve [{grupo, etiqueta, categoria, severidad, total, pendientes, items}] con los grupos más
     * numerosos y severos primero.
     */
    public static function agrupar(array $lista): array
    {
        $g = [];
        foreach ($lista as $x) {
            $clave = (string)($x['grupo'] ?? '') !== '' ? (string)$x['grupo'] : (string)$x['categoria'];
            if (!isset($g[$clave])) {
                $g[$clave] = ['grupo' => $clave, 'etiqueta' => static::GRUPOS[$clave] ?? (static::CATEGORIAS[$x['categoria']] ?? $x['categoria']),
                    'categoria' => $x['categoria'], 'severidad' => $x['severidad'], 'total' => 0, 'pendientes' => 0, 'accionables' => 0, 'observaciones' => 0, 'riesgo' => 0, 'items' => []];
            }
            $g[$clave]['total']++;
            if ($x['estado'] === 'pendiente') {
                $g[$clave]['pendientes']++;
                $accion = (string)($x['solucion']['accion'] ?? 'nota');
                if ($accion === 'nota') $g[$clave]['observaciones']++;
                else $g[$clave]['accionables']++;
                if (!self::accionSegura($accion)) $g[$clave]['riesgo']++;
            }
            if ((static::SEVERIDADES[$x['severidad']] ?? 0) > (static::SEVERIDADES[$g[$clave]['severidad']] ?? 0)) $g[$clave]['severidad'] = $x['severidad'];
            $g[$clave]['items'][] = $x;
        }
        $out = array_values($g);
        usort($out, fn($a, $b) => [(static::SEVERIDADES[$b['severidad']] ?? 0), $b['total']] <=> [(static::SEVERIDADES[$a['severidad']] ?? 0), $a['total']]);
        return $out;
    }

    public static function conteos(int $pid, int $corrida = 0): array
    {
        $c = ['pendiente' => 0, 'aceptada' => 0, 'rechazada' => 0, 'obsoleta' => 0, 'criticas' => 0, 'accionables' => 0, 'observaciones' => 0, 'vencidas' => 0];
        $q = 'SELECT estado, severidad, JSON_UNQUOTE(JSON_EXTRACT(solucion_json, \'$.accion\')) accion,
                     (vigente_hasta IS NOT NULL AND vigente_hasta < NOW()) vencido, COUNT(*) n
              FROM ' . static::TABLA_HALLAZGOS . " WHERE proyecto_id = $pid" . ($corrida ? " AND corrida_id = $corrida" : '') . ' GROUP BY estado, severidad, accion, vencido';
        foreach (self::filas($q) as $f) {
            $c[$f['estado']] += (int)$f['n'];
            if ($f['estado'] !== 'pendiente') continue;
            if (in_array($f['severidad'], ['alta', 'critica'], true)) $c['criticas'] += (int)$f['n'];
            if ($f['accion'] === 'nota') $c['observaciones'] += (int)$f['n']; else $c['accionables'] += (int)$f['n'];
            if ((int)$f['vencido']) $c['vencidas'] += (int)$f['n'];
        }
        return $c;
    }

    /** ¿Se puede aplicar por lote sin simulación de impacto? */
    public static function accionSegura(string $accion): bool
    {
        return !in_array($accion, self::ACCIONES_RIESGO, true);
    }

    /** Vigencia de una propuesta: las que mueven el programa caducan; las observaciones no */
    protected static function vigenciaPara(string $accion): ?string
    {
        if ($accion === 'nota') return null;
        return date('Y-m-d H:i:s', strtotime('+' . self::VIGENCIA_DIAS . ' days'));
    }

    /**
     * Inserta el hallazgo o ACTUALIZA el pendiente con la misma huella (evita duplicados entre corridas).
     * $entidades son las columnas JSON propias de la herramienta (actividades_json, materiales_json…).
     * @return string nuevo | actualizado
     */
    protected static function upsertHallazgo(int $pid, int $corrida, array $h, string $modelo, array $entidades): string
    {
        $db = self::db();
        $esc = fn($v) => $db->real_escape_string((string)$v);
        $huella = (string)$h['huella'];
        $prev = self::filas('SELECT id FROM ' . static::TABLA_HALLAZGOS . " WHERE proyecto_id = $pid AND huella = '" . $esc($huella) . "' AND estado = 'pendiente' ORDER BY id DESC LIMIT 1")[0] ?? null;
        $accion = (string)($h['solucion']['accion'] ?? 'nota');
        $campos = [
            'corrida_id' => (int)$corrida,
            'grupo' => (string)($h['grupo'] ?? $h['categoria']),
            'categoria' => (string)$h['categoria'],
            'severidad' => (string)$h['severidad'],
            'origen' => (string)$h['origen'],
            'titulo' => (string)$h['titulo'],
            'descripcion' => (string)$h['descripcion'],
            'solucion_json' => json_encode($h['solucion'], JSON_UNESCAPED_UNICODE),
            'modelo' => $h['origen'] === 'ia' ? $modelo : '',
            'evidencia_json' => !empty($h['evidencia']) ? json_encode($h['evidencia'], JSON_UNESCAPED_UNICODE) : null,
            'vigente_hasta' => self::vigenciaPara($accion),
        ];
        foreach ($entidades as $k => $v) $campos[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
        $partes = [];
        foreach ($campos as $k => $v) $partes[] = $k . ' = ' . ($v === null ? 'NULL' : ($k === 'corrida_id' ? (int)$v : "'" . $esc($v) . "'"));
        if ($prev) {
            self::db()->query('UPDATE ' . static::TABLA_HALLAZGOS . ' SET ' . implode(', ', $partes) . ' WHERE id = ' . (int)$prev['id'] . " AND proyecto_id = $pid");
            // Los pendientes repetidos de corridas viejas dejan de contar (no se borran: quedan como obsoletos).
            self::db()->query('UPDATE ' . static::TABLA_HALLAZGOS . " SET estado = 'obsoleta' WHERE proyecto_id = $pid AND huella = '" . $esc($huella) . "' AND estado = 'pendiente' AND id <> " . (int)$prev['id']);
            return 'actualizado';
        }
        $cols = array_keys($campos);
        $vals = array_map(fn($k) => $campos[$k] === null ? 'NULL' : ($k === 'corrida_id' ? (int)$campos[$k] : "'" . $esc($campos[$k]) . "'"), $cols);
        array_unshift($cols, 'proyecto_id', 'huella');
        array_unshift($vals, (string)$pid, "'" . $esc($huella) . "'");
        self::db()->query('INSERT INTO ' . static::TABLA_HALLAZGOS . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')');
        return 'nuevo';
    }

    /**
     * Salud de la revisión: acción, precisión, antigüedad, duplicados y las reglas que más ruido generan.
     * Todo se calcula con los datos guardados; no llama a la IA.
     */
    public static function salud(int $pid, int $corrida = 0): array
    {
        $filas = self::filas('SELECT estado, severidad, origen, grupo, modelo, motivo_rechazo, vigente_hasta, detectado,
                JSON_UNQUOTE(JSON_EXTRACT(solucion_json, \'$.accion\')) accion
            FROM ' . static::TABLA_HALLAZGOS . " WHERE proyecto_id = $pid" . ($corrida ? " AND corrida_id = $corrida" : ''));
        $m = ['total' => 0, 'pendiente' => 0, 'aceptada' => 0, 'rechazada' => 0, 'obsoleta' => 0, 'accionables' => 0, 'observaciones' => 0,
            'vencidas' => 0, 'criticas' => 0, 'precision' => null, 'antiguedad' => 0, 'porGrupo' => [], 'porOrigen' => [], 'porModelo' => [], 'motivos' => []];
        $hoy = time();
        $base = [];
        foreach ($filas as $f) {
            $m['total']++;
            $m[$f['estado']] = ($m[$f['estado']] ?? 0) + 1;
            $accionable = $f['accion'] !== 'nota';
            if ($f['estado'] === 'pendiente') {
                if ($accionable) $m['accionables']++; else $m['observaciones']++;
                if ($f['vigente_hasta'] && strtotime($f['vigente_hasta']) < $hoy) $m['vencidas']++;
                if (in_array($f['severidad'] ?? '', ['alta', 'critica'], true)) $m['criticas']++;
                if ($f['detectado']) $m['antiguedad'] = max($m['antiguedad'], (int)floor(($hoy - strtotime($f['detectado'])) / 86400));
            }
            if (!$accionable) continue;
            $base[] = $f;
        }
        $cerrados = $m['aceptada'] + $m['rechazada'];
        $m['precision'] = $cerrados > 0 ? round(100 * $m['aceptada'] / $cerrados, 1) : null;
        foreach ($base as $f) {
            $g = (string)($f['grupo'] ?: 'otro');
            if (!isset($m['porGrupo'][$g])) $m['porGrupo'][$g] = ['grupo' => $g, 'etiqueta' => static::GRUPOS[$g] ?? $g, 'total' => 0, 'pendiente' => 0, 'aceptada' => 0, 'rechazada' => 0, 'precision' => null];
            $m['porGrupo'][$g]['total']++;
            $m['porGrupo'][$g][$f['estado']] = ($m['porGrupo'][$g][$f['estado']] ?? 0) + 1;
            $o = $f['origen'] ?: 'sistema';
            if (!isset($m['porOrigen'][$o])) $m['porOrigen'][$o] = ['origen' => $o, 'total' => 0, 'aceptada' => 0, 'rechazada' => 0, 'precision' => null];
            $m['porOrigen'][$o]['total']++;
            if ($f['estado'] === 'aceptada' || $f['estado'] === 'rechazada') $m['porOrigen'][$o][$f['estado']]++;
            if ($f['modelo'] !== '') {
                if (!isset($m['porModelo'][$f['modelo']])) $m['porModelo'][$f['modelo']] = ['modelo' => $f['modelo'], 'total' => 0, 'aceptada' => 0, 'rechazada' => 0];
                $m['porModelo'][$f['modelo']]['total']++;
                if ($f['estado'] === 'aceptada' || $f['estado'] === 'rechazada') $m['porModelo'][$f['modelo']][$f['estado']]++;
            }
        }
        foreach ($m['porGrupo'] as &$g) {
            $c = $g['aceptada'] + $g['rechazada'];
            $g['precision'] = $c > 0 ? round(100 * $g['aceptada'] / $c, 1) : null;
        }
        unset($g);
        foreach ($m['porOrigen'] as &$o) {
            $c = $o['aceptada'] + $o['rechazada'];
            $o['precision'] = $c > 0 ? round(100 * $o['aceptada'] / $c, 1) : null;
        }
        unset($o);
        uasort($m['porGrupo'], fn($a, $b) => [$b['pendiente'], $b['total']] <=> [$a['pendiente'], $a['total']]);
        foreach (self::filas('SELECT motivo_rechazo, COUNT(*) n FROM ' . static::TABLA_HALLAZGOS . " WHERE proyecto_id = $pid" . ($corrida ? " AND corrida_id = $corrida" : '') . " AND estado = 'rechazada' AND motivo_rechazo <> '' GROUP BY motivo_rechazo ORDER BY n DESC") as $f) {
            $m['motivos'][$f['motivo_rechazo']] = (int)$f['n'];
        }
        $m['duplicados'] = (int)(self::filas('SELECT COUNT(*) - COUNT(DISTINCT huella) n FROM ' . static::TABLA_HALLAZGOS . " WHERE proyecto_id = $pid" . ($corrida ? " AND corrida_id = $corrida" : '') . " AND estado = 'pendiente'")[0]['n'] ?? 0);
        return $m;
    }

    // ---------- aceptar / rechazar / reabrir ----------

    private static function obtener(int $pid, int $id): ?array
    {
        return self::filas('SELECT * FROM ' . static::TABLA_HALLAZGOS . " WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    private static function cerrar(int $pid, int $id, string $estado, ?array $antes, string $motivo = ''): void
    {
        $db = self::db();
        $a = $antes === null ? 'NULL' : "'" . $db->real_escape_string(json_encode($antes, JSON_UNESCAPED_UNICODE)) . "'";
        $quien = $db->real_escape_string(usuario_actual()['nombre'] ?? '');
        $m = $db->real_escape_string(mb_substr($motivo, 0, 60));
        $db->query('UPDATE ' . static::TABLA_HALLAZGOS . " SET estado = '$estado', motivo_rechazo = '$m', antes_json = $a, resuelto_por = '$quien', resuelto_en = NOW() WHERE id = $id AND proyecto_id = $pid");
    }

    /** Rechaza con un motivo obligatorio: sin motivo no se puede mejorar la precisión de las reglas */
    public static function rechazar(int $pid, int $id, string $motivo = 'no_aplica', string $nota = ''): array
    {
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] !== 'pendiente') return [false, static::TXT_ESTE . ' ya no está pendiente'];
        if (!isset(self::MOTIVOS[$motivo])) $motivo = 'otro';
        self::cerrar($pid, $id, 'rechazada', null, $motivo);
        $sol = json_decode($h['solucion_json'], true) ?: [];
        $detalle = 'Rechazado (' . self::MOTIVOS[$motivo] . '): ' . $h['titulo'] . (trim($nota) !== '' ? ' · ' . mb_substr(trim($nota), 0, 300) : '');
        Auditoria::registrar($pid, static::HERRAMIENTA, 'rechazado', (string)($sol['accion'] ?? 'nota'), $detalle, null,
            ['motivo' => $motivo, 'nota' => mb_substr(trim($nota), 0, 500)] + (array)($sol['params'] ?? []), $id);
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
            // Lo que deshace la IA ya queda en Auditoria: aquí no debe nacer un registro manual aparte
            [$ok, $msg] = \Classes\HistorialCambios::silenciar(fn() => static::restaurarAccion($p, json_decode($h['antes_json'], true) ?: []));
            if (!$ok) return [false, $msg];
        }
        $sol = json_decode($h['solucion_json'], true) ?: [];
        $vig = self::vigenciaPara((string)($sol['accion'] ?? 'nota'));
        self::db()->query('UPDATE ' . static::TABLA_HALLAZGOS . " SET estado = 'pendiente', motivo_rechazo = '', vigente_hasta = " . ($vig ? "'$vig'" : 'NULL') . ", antes_json = NULL, resuelto_por = '', resuelto_en = NULL WHERE id = $id AND proyecto_id = $pid");
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
        // Una propuesta que mueve el programa caduca: si el análisis es viejo, se vuelve a revisar.
        // Las fechas relativas («desde hoy») se recalculan al aplicar y por eso no caducan.
        $relativa = ($params['modo'] ?? '') === 'desde_hoy';
        if (!$relativa && !empty($h['vigente_hasta']) && strtotime((string)$h['vigente_hasta']) < time() && !self::accionSegura($accion)) {
            return [false, 'Este hallazgo venció el ' . date('d/m/Y', strtotime((string)$h['vigente_hasta'])) . '. Vuelve a analizar o corrígelo a mano desde «Corregir aquí».'];
        }
        if (!empty($s['esperado']) && static::esperadoDe($pid, $accion, $params) !== $s['esperado']) return [false, static::TXT_CAMBIO];
        $db = self::db();
        $db->begin_transaction();
        try {
            // Los datos que mueve la IA se anotan en Auditoria; el historial manual no debe duplicarlos
            [$ok, $msg, $antes] = \Classes\HistorialCambios::silenciar(fn() => static::ejecutarAccion($p, $accion, $params));
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

    /**
     * Corrección a mano desde el editor en línea: el admin elige la acción y escribe los valores.
     * Se valida igual que una propuesta (paramsValidos), se guarda la solución corregida en el hallazgo
     * y se aplica con el mismo cierre y reversión que «Aceptar». Aquí NO se exige `esperado`: la persona
     * está viendo los valores actuales y decidiendo, no aplicando una propuesta vieja de la IA.
     */
    public static function corregir(Proyecto $p, int $id, string $accion, array $params): array
    {
        $pid = (int)$p->id;
        $h = self::obtener($pid, $id);
        if (!$h || $h['estado'] !== 'pendiente') return [false, static::TXT_ESTE . ' ya no está pendiente'];
        if (!isset(static::ACCIONES[$accion])) return [false, 'Acción no permitida'];
        $limpios = static::paramsValidos($pid, $accion, $params);
        if ($limpios === null) return [false, 'Los valores de la corrección no son válidos o el dato ya no pertenece al proyecto. Revisa el formulario.'];
        $s = json_decode((string)$h['solucion_json'], true) ?: [];
        $s['accion'] = $accion;
        $s['params'] = $limpios;
        $s['texto'] = trim((string)($s['texto'] ?? '')) !== '' ? (string)$s['texto'] : 'Corrección manual desde el aviso.';
        $db = self::db();
        $db->begin_transaction();
        try {
            [$ok, $msg, $antes] = \Classes\HistorialCambios::silenciar(fn() => static::ejecutarAccion($p, $accion, $limpios));
            if (!$ok) { $db->rollback(); return [false, $msg]; }
            // La solución corregida queda con el estado que dejó: si se reabre, se puede volver a aplicar sin falsos «cambió»
            $s['esperado'] = static::esperadoDe($pid, $accion, $limpios);
            $db->query('UPDATE ' . static::TABLA_HALLAZGOS . " SET solucion_json = '" . $db->real_escape_string(json_encode($s, JSON_UNESCAPED_UNICODE)) . "' WHERE id = $id AND proyecto_id = $pid");
            self::cerrar($pid, $id, 'aceptada', $antes);
            Auditoria::registrar($pid, static::HERRAMIENTA, 'corregido', $accion, 'Corregido a mano: ' . $msg, $antes, $limpios, $id, $h['origen'] === 'ia' ? (string)$h['modelo'] : '');
            $db->query('UPDATE ' . static::TABLA_CORRIDAS . ' SET guardada = 1, nombre = IF(nombre = \'\', CONCAT(\'Análisis \', DATE_FORMAT(creado, \'%d/%m/%Y\')), nombre) WHERE id = ' . (int)$h['corrida_id']);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, 'No se pudo aplicar: ' . $e->getMessage()];
        }
        return [true, $msg];
    }

    /**
     * «Corregir todas» de un grupo: aplica la solución de cada hallazgo pendiente, una por una y en
     * transacciones independientes (un fallo no detiene al resto).
     * @return array{ok: int, fallos: array<int,string>}
     */
    public static function aplicarLote(Proyecto $p, array $ids, bool $permitirRiesgo = false): array
    {
        $ok = 0;
        $fallos = [];
        $omitidos = [];
        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
            $h = self::obtener((int)$p->id, $id);
            if (!$h) { $fallos[$id] = 'El hallazgo no existe'; continue; }
            $sol = json_decode((string)$h['solucion_json'], true) ?: [];
            $accion = (string)($sol['accion'] ?? 'nota');
            // Fechas, avance, duración y relaciones NUNCA se aplican en lote: exigen revisar el impacto.
            if (!$permitirRiesgo && !self::accionSegura($accion)) { $omitidos[$id] = 'Requiere revisión individual (mueve el programa)'; continue; }
            [$bien, $msg] = self::aceptar($p, $id);
            if ($bien) $ok++; else $fallos[$id] = $msg;
        }
        return ['ok' => $ok, 'fallos' => $fallos, 'omitidos' => $omitidos];
    }
}
