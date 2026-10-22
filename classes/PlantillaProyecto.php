<?php

namespace Classes;

use Model\Partida;
use Model\Proyecto;

/**
 * Plantillas de proyecto completas (Fase 4.3): se guarda la ESTRUCTURA de un proyecto terminado y con ella se arranca uno nuevo.
 * Entra: programa (partidas, actividades, dependencias, con las fechas como días desde el inicio), jornada, diseños y campos
 * personalizados, categorías y catálogos, materiales (sin compras ni archivos) y la configuración del proyecto.
 * No entra nada que sea historia de la obra: avance, reportes, bitácora, fotos, costos, RFI, restricciones ni archivos.
 * Se guarda en plantillas_proyecto.datos (JSON); los ids se reasignan al aplicarla.
 */
class PlantillaProyecto
{
    use AccesoDb;

    public const VERSION = 1;
    /** Columnas del proyecto que viajan en la plantilla (nombre, código, fechas y logos son de cada obra) */
    private const CAMPOS_PROYECTO = ['descripcion', 'color', 'modo_dependencias', 'registros_por_pagina', 'tema_modo', 'tema_paleta', 'msr_umbral_critico',
        'programa_nombre', 'programa_columnas_json', 'programa_indicador_json', 'marca_activa', 'marca_nombre', 'marca_color', 'marca_color2'];
    private const ACTIVIDAD = ['codigo', 'nombre', 'descripcion', 'duracion_dias', 'prioridad', 'hito', 'sin_material', 'orden', 'fecha_fija', 'responsable', 'libre_texto', 'libre_numero'];
    private const FECHAS_ACTIVIDAD = ['fecha_inicio', 'fecha_fin', 'rec_inicio', 'rec_fin', 'libre_fecha'];
    /** tabla con proyecto_id → columnas que se copian tal cual */
    private const SIMPLES = [
        'calendario_jornada' => ['dia_semana', 'factor'],
        'bitacora_departamentos' => ['nombre'],
        'archivo_categorias' => ['nombre'],
        'especialidades' => ['nombre'],
        'catalogo_categorias' => ['ambito', 'nombre'],
        'permisos_tipos' => ['nombre', 'palabras', 'medidas_json'],
    ];
    private const MATERIAL = ['referencia', 'nombre_base', 'marca', 'modelo', 'valor_extra', 'nombre', 'especialidad', 'link_web', 'cantidad', 'unidad'];

    // ---------- permisos y lista ----------

    /** Plantillas que el usuario puede usar: las suyas y las compartidas (el admin ve todas) */
    public static function visibles(array $usuario): array
    {
        $uid = (int)($usuario['id'] ?? 0);
        $where = ($usuario['rol'] ?? '') === 'admin' ? '1' : "(p.usuario_id = $uid OR p.compartida = 1)";
        return self::filas("SELECT p.id, p.nombre, p.descripcion, p.compartida, p.origen, p.resumen_json, p.creado, p.usuario_id, u.nombre AS autor
            FROM plantillas_proyecto p LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE $where ORDER BY p.creado DESC");
    }

    public static function obtener(int $id, array $usuario): ?array
    {
        foreach (self::visibles($usuario) as $p) if ((int)$p['id'] === $id) return $p;
        return null;
    }

    public static function disponible(): bool
    {
        return (bool)self::filas("SHOW TABLES LIKE 'plantillas_proyecto'");
    }

    public static function eliminar(int $id, array $usuario): bool
    {
        $p = self::obtener($id, $usuario);
        if (!$p || (($usuario['rol'] ?? '') !== 'admin' && (int)$p['usuario_id'] !== (int)$usuario['id'])) return false;
        return (bool)self::db()->query("DELETE FROM plantillas_proyecto WHERE id = $id");
    }

    // ---------- guardar un proyecto como plantilla ----------

    private static function dias(?string $fecha, string $base): ?int
    {
        if (!$fecha || $fecha === '0000-00-00') return null;
        return (int)round((strtotime($fecha) - strtotime($base)) / 86400);
    }

    private static function columnasDe(string $tabla): array
    {
        static $c = [];
        return $c[$tabla] ??= array_column(self::filas("SHOW COLUMNS FROM `$tabla`"), 'Field');
    }

    private static function pick(array $fila, array $cols): array
    {
        $o = [];
        foreach ($cols as $c) if (array_key_exists($c, $fila)) $o[$c] = $fila[$c];
        return $o;
    }

    /** Foto de la estructura del proyecto */
    public static function capturar(Proyecto $p): array
    {
        $pid = (int)$p->id;
        $base = (string)$p->fecha_inicio;
        $d = ['version' => self::VERSION, 'duracion_dias' => max(0, (int)self::dias((string)$p->fecha_fin, $base)), 'proyecto' => [], 'partidas' => [], 'actividades' => [], 'dependencias' => [],
            'simples' => [], 'campos' => [], 'disenos' => [], 'materiales' => [], 'actividad_materiales' => [], 'estampas' => [], 'lookahead' => null, 'tipo_obra' => null];

        $fila = self::filas("SELECT * FROM proyectos WHERE id = $pid")[0] ?? [];
        $d['proyecto'] = self::pick($fila, self::CAMPOS_PROYECTO);

        foreach (self::filas("SELECT * FROM partidas WHERE proyecto_id = $pid ORDER BY orden, id") as $r) {
            $d['partidas'][] = ['ref' => (int)$r['id']] + self::pick($r, ['codigo', 'nombre', 'descripcion', 'color_bg', 'orden']);
        }
        foreach (self::filas("SELECT a.* FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid ORDER BY p.orden, a.orden, a.id") as $r) {
            $a = ['ref' => (int)$r['id'], 'partida' => (int)$r['partida_id']] + self::pick($r, self::ACTIVIDAD);
            foreach (self::FECHAS_ACTIVIDAD as $f) $a[$f] = self::dias($r[$f] ?? null, $base);
            $d['actividades'][] = $a;
        }
        foreach (self::filas("SELECT * FROM actividad_dependencias WHERE proyecto_id = $pid") as $r) {
            $d['dependencias'][] = ['pred' => (int)$r['predecesora_id'], 'suc' => (int)$r['sucesora_id'], 'tipo' => $r['tipo'], 'retraso' => (int)$r['retraso']];
        }
        foreach (self::SIMPLES as $t => $cols) {
            $d['simples'][$t] = array_map(fn($r) => self::pick($r, $cols), self::filas("SELECT * FROM `$t` WHERE proyecto_id = $pid ORDER BY id"));
        }
        foreach (self::filas("SELECT * FROM campos_personalizados WHERE proyecto_id = $pid ORDER BY orden, id") as $r) {
            $d['campos'][] = ['ref' => (int)$r['id']] + self::pick($r, ['entidad', 'nombre', 'tipo', 'opciones', 'orden']);
        }
        foreach (self::filas("SELECT herramienta, columnas_json FROM disenos_lista WHERE proyecto_id = $pid") as $r) $d['disenos'][] = $r;
        foreach (self::filas("SELECT * FROM materiales WHERE proyecto_id = $pid ORDER BY id") as $r) {
            $d['materiales'][] = ['ref' => (int)$r['id']] + self::pick($r, self::MATERIAL);
        }
        foreach (self::filas("SELECT am.actividad_id, am.material_id FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id WHERE m.proyecto_id = $pid") as $r) {
            $d['actividad_materiales'][] = ['a' => (int)$r['actividad_id'], 'm' => (int)$r['material_id']];
        }
        foreach (self::filas("SELECT id, nombre, config_json FROM estampas WHERE proyecto_id = $pid") as $r) {
            $d['estampas'][] = ['ref' => (int)$r['id'], 'nombre' => $r['nombre'], 'config_json' => $r['config_json']];
        }
        $la = self::filas("SELECT * FROM lookahead_config WHERE proyecto_id = $pid")[0] ?? null;
        if ($la) $d['lookahead'] = self::pick($la, ['nombre', 'pta_nombre', 'semanas', 'titulo', 'encabezado_id']);
        $d['tipo_obra'] = self::filas("SELECT plantilla FROM proyecto_plantilla WHERE proyecto_id = $pid")[0]['plantilla'] ?? null;
        return $d;
    }

    /** Guarda el proyecto como plantilla. Devuelve [ok, id | mensaje] */
    public static function guardar(Proyecto $p, array $usuario, string $nombre, string $descripcion, bool $compartida): array
    {
        $nombre = mb_substr(trim(strip_tags($nombre)), 0, 150);
        if ($nombre === '') return [false, 'Ponle un nombre a la plantilla.'];
        $d = self::capturar($p);
        if (!$d['partidas']) return [false, 'El proyecto no tiene partidas: no hay estructura que guardar.'];
        $resumen = [
            'partidas' => count($d['partidas']), 'actividades' => count($d['actividades']), 'dependencias' => count($d['dependencias']),
            'materiales' => count($d['materiales']), 'campos' => count($d['campos']), 'dias' => $d['duracion_dias'],
        ];
        $json = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $resJson = json_encode($resumen);
        $desc = mb_substr(trim(strip_tags($descripcion)), 0, 500);
        $origen = mb_substr((string)$p->nombre, 0, 200);
        $comp = $compartida && ($usuario['rol'] ?? '') === 'admin' ? 1 : 0;
        $uid = (int)$usuario['id'];
        $st = self::db()->prepare('INSERT INTO plantillas_proyecto (usuario_id, nombre, descripcion, compartida, origen, resumen_json, datos) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $st->bind_param('ississs', $uid, $nombre, $desc, $comp, $origen, $resJson, $json);
        return $st->execute() ? [true, (int)self::db()->insert_id] : [false, 'No se pudo guardar la plantilla.'];
    }

    // ---------- aplicar ----------

    private static function insertar(string $tabla, array $fila): int
    {
        $cols = array_values(array_intersect(array_keys($fila), self::columnasDe($tabla)));
        $vals = array_map(fn($c) => $fila[$c], $cols);
        $st = self::db()->prepare("INSERT INTO `$tabla` (`" . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
        $st->bind_param(str_repeat('s', count($cols)), ...$vals);
        $st->execute();
        return (int)self::db()->insert_id;
    }

    private static function fecha(string $base, ?int $dias): ?string
    {
        return $dias === null ? null : date('Y-m-d', strtotime("$base " . ($dias >= 0 ? '+' : '') . "$dias days"));
    }

    /** El proyecto está vacío (sin partidas): solo así se le puede aplicar una plantilla */
    public static function estaVacio(int $pid): bool
    {
        return !self::filas("SELECT 1 FROM partidas WHERE proyecto_id = $pid LIMIT 1");
    }

    /**
     * Rellena un proyecto vacío con la estructura de la plantilla; las fechas se cuentan desde el inicio del proyecto.
     * Devuelve [ok, mensaje]. Todo o nada.
     */
    public static function aplicar(int $plantillaId, Proyecto $p, array $usuario): array
    {
        $pl = self::obtener($plantillaId, $usuario);
        if (!$pl) return [false, 'La plantilla no existe.'];
        $pid = (int)$p->id;
        if (!self::estaVacio($pid)) return [false, 'El proyecto ya tiene partidas: la plantilla solo se aplica a un proyecto vacío.'];
        $d = json_decode((string)(self::filas("SELECT datos FROM plantillas_proyecto WHERE id = $plantillaId")[0]['datos'] ?? ''), true);
        if (!is_array($d) || ($d['version'] ?? 0) > self::VERSION) return [false, 'La plantilla está dañada o es de una versión más nueva.'];

        $base = (string)$p->fecha_inicio;
        $db = self::db();
        $db->begin_transaction();
        try {
            // configuración del proyecto
            $cols = array_intersect(array_keys($d['proyecto'] ?? []), self::columnasDe('proyectos'));
            // campos personalizados primero: sus ids nuevos se necesitan para los diseños del programa
            $mapCampos = [];
            foreach ($d['campos'] ?? [] as $c) {
                $mapCampos[(int)$c['ref']] = self::insertar('campos_personalizados', ['proyecto_id' => $pid] + array_diff_key($c, ['ref' => 1]));
            }
            $remapCp = function (?string $json) use ($mapCampos): ?string {
                if ($json === null || $json === '') return $json;
                return preg_replace_callback('/"cp_(\d+)"/', fn($m) => isset($mapCampos[(int)$m[1]]) ? '"cp_' . $mapCampos[(int)$m[1]] . '"' : '"cp_0"', $json);
            };
            if ($cols) {
                $sets = []; $vals = [];
                foreach ($cols as $c) {
                    $sets[] = "`$c` = ?";
                    $v = $d['proyecto'][$c];
                    $vals[] = $c === 'programa_columnas_json' ? $remapCp($v) : $v;
                }
                $st = $db->prepare('UPDATE proyectos SET ' . implode(', ', $sets) . ' WHERE id = ?');
                $vals[] = $pid;
                $st->bind_param(str_repeat('s', count($cols)) . 'i', ...$vals);
                $st->execute();
            }
            foreach ($d['disenos'] ?? [] as $x) {
                self::insertar('disenos_lista', ['proyecto_id' => $pid, 'herramienta' => $x['herramienta'], 'columnas_json' => $remapCp($x['columnas_json'])]);
            }

            // jornada y catálogos
            if (!empty($d['simples']['calendario_jornada'])) $db->query("DELETE FROM calendario_jornada WHERE proyecto_id = $pid");
            foreach (self::SIMPLES as $t => $_) foreach ($d['simples'][$t] ?? [] as $r) self::insertar($t, ['proyecto_id' => $pid] + $r);

            // estampas (el encabezado del Lookahead apunta a una)
            $mapEstampas = [];
            foreach ($d['estampas'] ?? [] as $e) $mapEstampas[(int)$e['ref']] = self::insertar('estampas', ['proyecto_id' => $pid, 'nombre' => $e['nombre'], 'config_json' => $e['config_json']]);
            if (!empty($d['lookahead'])) {
                $la = $d['lookahead'];
                $la['encabezado_id'] = $mapEstampas[(int)($la['encabezado_id'] ?? 0)] ?? null;
                self::insertar('lookahead_config', ['proyecto_id' => $pid] + $la);
            }
            if (!empty($d['tipo_obra'])) Plantillas::elegir($pid, (string)$d['tipo_obra'], 'manual', 0, 'Plantilla de proyecto «' . $pl['nombre'] . '»');

            // programa
            $calendario = new Calendario($pid);
            $mapPartidas = [];
            foreach ($d['partidas'] ?? [] as $r) $mapPartidas[(int)$r['ref']] = self::insertar('partidas', ['proyecto_id' => $pid] + array_diff_key($r, ['ref' => 1]));
            $mapActs = [];
            foreach ($d['actividades'] ?? [] as $a) {
                if (!isset($mapPartidas[(int)$a['partida']])) continue;
                $fila = array_diff_key($a, ['ref' => 1, 'partida' => 1]);
                foreach (self::FECHAS_ACTIVIDAD as $f) $fila[$f] = self::fecha($base, $a[$f] ?? null);
                if ($fila['fecha_inicio']) {   // el fin se recalcula con la jornada y los festivos del proyecto nuevo
                    $fila['fecha_fin'] = !empty($a['hito']) ? $fila['fecha_inicio'] : $calendario->calcularFechaFin($fila['fecha_inicio'], (int)$a['duracion_dias']);
                }
                $fila += ['partida_id' => $mapPartidas[(int)$a['partida']], 'progreso' => 0, 'status' => 'no_iniciada', 'pta' => 0];
                $mapActs[(int)$a['ref']] = self::insertar('actividades', $fila);
            }
            foreach ($d['dependencias'] ?? [] as $x) {
                if (!isset($mapActs[(int)$x['pred']], $mapActs[(int)$x['suc']])) continue;
                self::insertar('actividad_dependencias', ['proyecto_id' => $pid, 'predecesora_id' => $mapActs[(int)$x['pred']], 'sucesora_id' => $mapActs[(int)$x['suc']], 'tipo' => $x['tipo'], 'retraso' => $x['retraso']]);
            }

            // materiales (sin compras, fechas ni archivos)
            $mapMat = [];
            foreach ($d['materiales'] ?? [] as $m) $mapMat[(int)$m['ref']] = self::insertar('materiales', ['proyecto_id' => $pid, 'compra' => 'pendiente'] + array_diff_key($m, ['ref' => 1]));
            foreach ($d['actividad_materiales'] ?? [] as $x) {
                if (isset($mapActs[(int)$x['a']], $mapMat[(int)$x['m']])) self::insertar('actividad_materiales', ['actividad_id' => $mapActs[(int)$x['a']], 'material_id' => $mapMat[(int)$x['m']]]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            error_log('Plantilla de proyecto: ' . $e->getMessage());
            return [false, 'No se pudo aplicar la plantilla: ' . $e->getMessage()];
        }

        foreach ($mapPartidas as $nuevo) {   // fechas de cada partida según sus actividades
            if ($pa = Partida::find($nuevo)) $pa->recalcularFechas();
        }
        $nuevo = Proyecto::find($pid);
        if ($nuevo && ($nuevo->modo_dependencias ?? 'apagado') === 'automatico' && $mapActs) {
            try { Dependencias::propagar($nuevo); } catch (\Throwable $e) { error_log('Plantilla: ' . $e->getMessage()); }
        }
        return [true, count($mapActs) . ' actividades en ' . count($mapPartidas) . ' partidas.'];
    }
}
