<?php

namespace Classes;

use Model\Actividad;
use Model\Partida;
use Model\Proyecto;

/**
 * Catálogo de conceptos del presupuesto.
 *
 * - `conceptos_obra`: clave, descripción, unidad, cantidad contractual y precio unitario.
 *   Un concepto pertenece a una partida (o a ninguna: conceptos generales).
 * - `actividad_conceptos`: cuánto de cada concepto ejecuta cada actividad del programa.
 *
 * El presupuesto de la partida sale del catálogo (cantidad × precio) y NO depende de que
 * el programa esté completo; lo que falta por repartir se muestra como «pendiente».
 */
class Conceptos
{
    use AccesoDb;

    private static ?array $esquema = null;

    /**
     * Estado real del esquema que necesita el catálogo: las 3 tablas, la columna
     * `costos_partidas.ajuste` y el enum `origen` con 'conceptos'. Si algo falta,
     * el catálogo se considera NO disponible y las pantallas muestran el aviso
     * en vez de fallar al escribir.
     */
    public static function estadoEsquema(): array
    {
        if (self::$esquema !== null) return self::$esquema;
        $faltan = [];
        $tablas = [];
        foreach (['conceptos_obra', 'actividad_conceptos', 'proyecto_cargos'] as $t) {
            $ok = false;
            try {
                $r = self::db()->query("SHOW TABLES LIKE '" . self::esc($t) . "'");
                $ok = (bool)($r && $r->num_rows);
            } catch (\Throwable $e) {
            }
            $tablas[$t] = $ok;
            if (!$ok) $faltan[] = 'tabla ' . $t;
        }
        $ajuste = false;
        $origen = '';
        try {
            $r = self::db()->query("SHOW COLUMNS FROM costos_partidas LIKE 'ajuste'");
            $ajuste = (bool)($r && $r->num_rows);
        } catch (\Throwable $e) {
        }
        try {
            $r = self::db()->query("SHOW COLUMNS FROM costos_partidas LIKE 'origen'");
            if ($r && ($f = $r->fetch_assoc())) $origen = (string)$f['Type'];
        } catch (\Throwable $e) {
        }
        $enum = str_contains($origen, "'conceptos'");
        if (!$ajuste) $faltan[] = 'columna costos_partidas.ajuste';
        if (!$enum) $faltan[] = "el enum costos_partidas.origen con 'conceptos'";
        return self::$esquema = ['ok' => !$faltan, 'faltan' => $faltan, 'tablas' => $tablas, 'ajuste' => $ajuste, 'enum' => $enum];
    }

    public static function disponible(): bool
    {
        return self::estadoEsquema()['ok'];
    }

    /** Ejecuta una consulta sin lanzar: el catálogo nunca debe tumbar el guardado de una actividad */
    private static function ejecutar(string $sql): bool
    {
        try {
            return (bool)self::db()->query($sql);
        } catch (\Throwable $e) {
            error_log('Conceptos: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Repara el esquema del catálogo (CLI): crea las tablas y agrega las columnas
     * que falten. Es idempotente; no toca datos existentes.
     * @return array{0:bool,1:array<int,string>,2:array}
     */
    public static function repararEsquema(): array
    {
        $mensajes = [];
        $e = self::estadoEsquema();

        foreach (self::ddlTablas() as $tabla => $sql) {
            if (!empty($e['tablas'][$tabla])) continue;
            if (self::ejecutar($sql)) $mensajes[] = 'Creada la tabla ' . $tabla;
            else $mensajes[] = 'No se pudo crear la tabla ' . $tabla;
        }
        if (!$e['ajuste']) {
            if (self::ejecutar("ALTER TABLE costos_partidas ADD COLUMN ajuste DECIMAL(16,2) NOT NULL DEFAULT 0")) {
                $mensajes[] = 'Agregada la columna costos_partidas.ajuste';
            } else {
                $mensajes[] = 'No se pudo agregar costos_partidas.ajuste';
            }
        }
        if (!$e['enum']) {
            if (self::ejecutar("ALTER TABLE costos_partidas MODIFY COLUMN origen ENUM('manual','cantidades','conceptos') NOT NULL DEFAULT 'manual'")) {
                $mensajes[] = 'El enum costos_partidas.origen ya acepta «conceptos»';
            } else {
                $mensajes[] = 'No se pudo actualizar el enum costos_partidas.origen';
            }
        }
        // Columnas imprescindibles si una versión anterior dejó la tabla a medias
        foreach (self::columnasCriticas() as $tabla => $cols) {
            if (empty($e['tablas'][$tabla])) continue;
            foreach ($cols as $col => $ddl) {
                $existe = false;
                try {
                    $r = self::db()->query("SHOW COLUMNS FROM `$tabla` LIKE '" . self::esc($col) . "'");
                    $existe = (bool)($r && $r->num_rows);
                } catch (\Throwable $ex) {
                }
                if (!$existe) {
                    if (self::ejecutar("ALTER TABLE `$tabla` ADD COLUMN $ddl")) $mensajes[] = 'Agregada ' . $tabla . '.' . $col;
                    else $mensajes[] = 'No se pudo agregar ' . $tabla . '.' . $col;
                }
            }
        }
        // Filas huérfanas: si una tabla se creó a mano sin llaves, pueden existir y
        // impedir que se agreguen las foráneas. Se limpian antes.
        if (!empty($e['tablas']['actividad_conceptos'])) {
            self::ejecutar("DELETE ac FROM actividad_conceptos ac LEFT JOIN actividades a ON a.id = ac.actividad_id WHERE a.id IS NULL");
            self::ejecutar("DELETE ac FROM actividad_conceptos ac LEFT JOIN conceptos_obra co ON co.id = ac.concepto_id WHERE co.id IS NULL");
        }
        if (!empty($e['tablas']['conceptos_obra'])) {
            self::ejecutar("UPDATE conceptos_obra co LEFT JOIN partidas p ON p.id = co.partida_id SET co.partida_id = NULL WHERE co.partida_id IS NOT NULL AND p.id IS NULL");
            self::ejecutar("DELETE co FROM conceptos_obra co LEFT JOIN proyectos p ON p.id = co.proyecto_id WHERE p.id IS NULL");
        }
        if (!empty($e['tablas']['proyecto_cargos'])) {
            self::ejecutar("DELETE pc FROM proyecto_cargos pc LEFT JOIN proyectos p ON p.id = pc.proyecto_id WHERE p.id IS NULL");
        }
        // Llaves e índices: sin ellos, borrar una actividad dejaría vínculos huérfanos
        foreach (self::relaciones() as $rel) {
            $existe = false;
            try {
                $r = self::db()->query($rel['check']);
                $existe = (bool)($r && (int)($r->fetch_row()[0] ?? 0) > 0);
            } catch (\Throwable $ex) {
            }
            if (!$existe) {
                if (self::ejecutar($rel['sql'])) $mensajes[] = $rel['msg'];
                else $mensajes[] = 'No se pudo agregar: ' . $rel['msg'];
            }
        }
        self::$esquema = null;
        $final = self::estadoEsquema();
        return [$final['ok'], $mensajes, $final];
    }

    /** @return array<string,string> */
    private static function ddlTablas(): array
    {
        return [
            'conceptos_obra' => "CREATE TABLE IF NOT EXISTS conceptos_obra (
                id INT AUTO_INCREMENT PRIMARY KEY, proyecto_id INT NOT NULL, partida_id INT NULL,
                clave VARCHAR(60) NULL, descripcion VARCHAR(255) NOT NULL, unidad VARCHAR(30) NOT NULL DEFAULT '',
                cantidad DECIMAL(14,3) NOT NULL DEFAULT 0, precio_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,
                origen VARCHAR(255) NOT NULL DEFAULT 'manual', notas VARCHAR(500) NOT NULL DEFAULT '',
                activo TINYINT(1) NOT NULL DEFAULT 1, orden INT NOT NULL DEFAULT 0,
                creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP, actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_co_clave (proyecto_id, clave), KEY idx_co_partida (proyecto_id, partida_id, orden),
                FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
                FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE SET NULL) ENGINE=InnoDB",
            'actividad_conceptos' => "CREATE TABLE IF NOT EXISTS actividad_conceptos (
                actividad_id INT NOT NULL, concepto_id INT NOT NULL, cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
                motivo_excedido VARCHAR(500) NOT NULL DEFAULT '', creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (actividad_id, concepto_id), KEY idx_ac_concepto (concepto_id),
                FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
                FOREIGN KEY (concepto_id) REFERENCES conceptos_obra(id) ON DELETE CASCADE) ENGINE=InnoDB",
            'proyecto_cargos' => "CREATE TABLE IF NOT EXISTS proyecto_cargos (
                proyecto_id INT NOT NULL PRIMARY KEY, indirectos DECIMAL(6,3) NOT NULL DEFAULT 0,
                financiamiento DECIMAL(6,3) NOT NULL DEFAULT 0, utilidad DECIMAL(6,3) NOT NULL DEFAULT 0,
                iva DECIMAL(6,3) NOT NULL DEFAULT 16, iva_en_bac TINYINT(1) NOT NULL DEFAULT 0,
                actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE) ENGINE=InnoDB",
        ];
    }

    /** @return array<string,array<string,string>> */
    private static function columnasCriticas(): array
    {
        return [
            'conceptos_obra' => [
                'clave' => 'clave VARCHAR(60) NULL',
                'descripcion' => "descripcion VARCHAR(255) NOT NULL DEFAULT ''",
                'unidad' => "unidad VARCHAR(30) NOT NULL DEFAULT ''",
                'cantidad' => 'cantidad DECIMAL(14,3) NOT NULL DEFAULT 0',
                'precio_unitario' => 'precio_unitario DECIMAL(14,2) NOT NULL DEFAULT 0',
            ],
            'actividad_conceptos' => [
                'cantidad' => 'cantidad DECIMAL(14,3) NOT NULL DEFAULT 0',
                'motivo_excedido' => "motivo_excedido VARCHAR(500) NOT NULL DEFAULT ''",
            ],
            'proyecto_cargos' => [
                'indirectos' => 'indirectos DECIMAL(6,3) NOT NULL DEFAULT 0',
                'financiamiento' => 'financiamiento DECIMAL(6,3) NOT NULL DEFAULT 0',
                'utilidad' => 'utilidad DECIMAL(6,3) NOT NULL DEFAULT 0',
                'iva' => 'iva DECIMAL(6,3) NOT NULL DEFAULT 16',
                'iva_en_bac' => 'iva_en_bac TINYINT(1) NOT NULL DEFAULT 0',
            ],
        ];
    }

    /**
     * Llaves primarias, índices y foráneas que deben existir. Si la tabla se creó a mano
     * sin ellas, borrar una actividad dejaría filas huérfanas en actividad_conceptos.
     * @return array<int,array{check:string,sql:string,msg:string}>
     */
    private static function relaciones(): array
    {
        $idx = fn(string $t, string $n) => "SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = '$t' AND index_name = '$n'";
        $fk = fn(string $t, string $c, string $ref) => "SELECT COUNT(*) FROM information_schema.key_column_usage
            WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = '$c' AND referenced_table_name = '$ref'";
        return [
            ['check' => $idx('conceptos_obra', 'PRIMARY'), 'sql' => 'ALTER TABLE conceptos_obra ADD PRIMARY KEY (id)', 'msg' => 'Agregada la llave primaria de conceptos_obra'],
            ['check' => $idx('conceptos_obra', 'uq_co_clave'), 'sql' => 'ALTER TABLE conceptos_obra ADD UNIQUE KEY uq_co_clave (proyecto_id, clave)', 'msg' => 'Agregada la clave única de conceptos_obra'],
            ['check' => $idx('conceptos_obra', 'idx_co_partida'), 'sql' => 'ALTER TABLE conceptos_obra ADD KEY idx_co_partida (proyecto_id, partida_id, orden)', 'msg' => 'Agregado el índice de partida del catálogo'],
            ['check' => $fk('conceptos_obra', 'proyecto_id', 'proyectos'), 'sql' => 'ALTER TABLE conceptos_obra ADD CONSTRAINT fk_co_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE', 'msg' => 'Agregada la relación conceptos_obra → proyectos'],
            ['check' => $fk('conceptos_obra', 'partida_id', 'partidas'), 'sql' => 'ALTER TABLE conceptos_obra ADD CONSTRAINT fk_co_partida FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE SET NULL', 'msg' => 'Agregada la relación conceptos_obra → partidas'],
            ['check' => $idx('actividad_conceptos', 'PRIMARY'), 'sql' => 'ALTER TABLE actividad_conceptos ADD PRIMARY KEY (actividad_id, concepto_id)', 'msg' => 'Agregada la llave primaria de actividad_conceptos'],
            ['check' => $idx('actividad_conceptos', 'idx_ac_concepto'), 'sql' => 'ALTER TABLE actividad_conceptos ADD KEY idx_ac_concepto (concepto_id)', 'msg' => 'Agregado el índice de concepto en actividad_conceptos'],
            ['check' => $fk('actividad_conceptos', 'actividad_id', 'actividades'), 'sql' => 'ALTER TABLE actividad_conceptos ADD CONSTRAINT fk_ac_actividad FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE', 'msg' => 'Agregada la relación actividad_conceptos → actividades'],
            ['check' => $fk('actividad_conceptos', 'concepto_id', 'conceptos_obra'), 'sql' => 'ALTER TABLE actividad_conceptos ADD CONSTRAINT fk_ac_concepto FOREIGN KEY (concepto_id) REFERENCES conceptos_obra(id) ON DELETE CASCADE', 'msg' => 'Agregada la relación actividad_conceptos → conceptos_obra'],
            ['check' => $idx('proyecto_cargos', 'PRIMARY'), 'sql' => 'ALTER TABLE proyecto_cargos ADD PRIMARY KEY (proyecto_id)', 'msg' => 'Agregada la llave primaria de proyecto_cargos'],
            ['check' => $fk('proyecto_cargos', 'proyecto_id', 'proyectos'), 'sql' => 'ALTER TABLE proyecto_cargos ADD CONSTRAINT fk_pc_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE', 'msg' => 'Agregada la relación proyecto_cargos → proyectos'],
        ];
    }

    /** Importe de un concepto (cantidad × precio unitario) */
    public static function importe($cantidad, $precio): float
    {
        return round(max(0, (float)$cantidad) * max(0, (float)$precio), 2);
    }

    // ---------- lectura ----------

    /** Conceptos del proyecto con lo programado y lo pendiente ya calculado */
    public static function deProyecto(int $pid, ?int $partidaId = null, string $busca = ''): array
    {
        if (!self::disponible()) return [];
        $w = $partidaId !== null ? ' AND co.partida_id = ' . (int)$partidaId : '';
        $b = trim($busca);
        if ($b !== '') {
            $t = "'%" . self::esc($b) . "%'";
            $w .= " AND (co.clave LIKE $t OR co.descripcion LIKE $t)";
        }
        $filas = self::filas("SELECT co.*, pa.codigo partida_codigo, pa.nombre partida, pa.color_bg color,
                COALESCE((SELECT SUM(ac.cantidad) FROM actividad_conceptos ac WHERE ac.concepto_id = co.id), 0) programado_qty,
                (SELECT COUNT(*) FROM actividad_conceptos ac WHERE ac.concepto_id = co.id) actividades,
                (SELECT COUNT(*) FROM actividad_conceptos ac WHERE ac.concepto_id = co.id AND ac.motivo_excedido <> '') excedidos
            FROM conceptos_obra co
            LEFT JOIN partidas pa ON pa.id = co.partida_id
            WHERE co.proyecto_id = $pid AND co.activo = 1$w
            ORDER BY (co.partida_id IS NULL), pa.orden, pa.codigo, co.orden, co.clave, co.descripcion");
        foreach ($filas as &$f) $f = self::completar($f);
        return $filas;
    }

    /** Conceptos que se pueden ligar a una actividad: los de su partida y los generales (sin partida) */
    public static function asignables(int $pid, int $partidaId): array
    {
        if (!self::disponible()) return [];
        $filas = self::filas("SELECT co.*, pa.codigo partida_codigo, pa.nombre partida,
                COALESCE((SELECT SUM(ac.cantidad) FROM actividad_conceptos ac WHERE ac.concepto_id = co.id), 0) programado_qty,
                (SELECT COUNT(*) FROM actividad_conceptos ac WHERE ac.concepto_id = co.id) actividades,
                0 excedidos
            FROM conceptos_obra co LEFT JOIN partidas pa ON pa.id = co.partida_id
            WHERE co.proyecto_id = $pid AND co.activo = 1 AND (co.partida_id = " . (int)$partidaId . " OR co.partida_id IS NULL)
            ORDER BY (co.partida_id IS NULL), co.clave, co.descripcion");
        foreach ($filas as &$f) $f = self::completar($f);
        return $filas;
    }

    public static function obtener(int $pid, int $id): ?array
    {
        $f = self::filas("SELECT co.*, pa.codigo partida_codigo, pa.nombre partida,
                COALESCE((SELECT SUM(ac.cantidad) FROM actividad_conceptos ac WHERE ac.concepto_id = co.id), 0) programado_qty,
                (SELECT COUNT(*) FROM actividad_conceptos ac WHERE ac.concepto_id = co.id) actividades,
                (SELECT COUNT(*) FROM actividad_conceptos ac WHERE ac.concepto_id = co.id AND ac.motivo_excedido <> '') excedidos
            FROM conceptos_obra co
            LEFT JOIN partidas pa ON pa.id = co.partida_id WHERE co.id = $id AND co.proyecto_id = $pid")[0] ?? null;
        return $f ? self::completar($f) : null;
    }

    /** Conceptos validados para una actividad (conserva el orden del catálogo) */
    public static function asignacionesDeActividad(int $actividadId): array
    {
        if (!self::disponible()) return [];
        $filas = self::filas("SELECT ac.concepto_id, ac.cantidad, ac.motivo_excedido,
                co.clave, co.descripcion, co.unidad, co.precio_unitario, co.cantidad cantidad_concepto, co.partida_id,
                (SELECT COALESCE(SUM(x.cantidad), 0) FROM actividad_conceptos x
                    WHERE x.concepto_id = co.id AND x.actividad_id <> ac.actividad_id) otras
            FROM actividad_conceptos ac JOIN conceptos_obra co ON co.id = ac.concepto_id
            WHERE ac.actividad_id = $actividadId
            ORDER BY co.orden, co.clave, co.descripcion");
        foreach ($filas as &$f) {
            $f['cantidad'] = (float)$f['cantidad'];
            $f['cantidad_concepto'] = (float)$f['cantidad_concepto'];
            $f['otras'] = (float)$f['otras'];
            $f['precio_unitario'] = (float)$f['precio_unitario'];
            $f['importe'] = self::importe($f['cantidad'], $f['precio_unitario']);
            $f['pct'] = $f['cantidad_concepto'] > 0 ? round($f['cantidad'] / $f['cantidad_concepto'] * 100, 1) : 0.0;
            $f['excedido'] = $f['cantidad_concepto'] > 0 && $f['otras'] + $f['cantidad'] > $f['cantidad_concepto'] + 0.0005;
        }
        return $filas;
    }

    /** Importe que aporta cada actividad (para la Curva S y el valor ganado) */
    public static function importeActividades(int $pid): array
    {
        if (!self::disponible()) return [];
        $out = [];
        foreach (self::filas("SELECT ac.actividad_id, SUM(ac.cantidad * co.precio_unitario) importe
            FROM actividad_conceptos ac JOIN conceptos_obra co ON co.id = ac.concepto_id AND co.activo = 1
            JOIN actividades a ON a.id = ac.actividad_id JOIN partidas pa ON pa.id = a.partida_id
            WHERE pa.proyecto_id = $pid GROUP BY ac.actividad_id") as $f) {
            $out[(int)$f['actividad_id']] = round((float)$f['importe'], 2);
        }
        return $out;
    }

    /**
     * Resumen por partida: conceptos, suma del catálogo, importe programado y pendiente.
     * @return array{partidas: array<int,array>, sinPartida: array}
     */
    public static function resumenPartidas(int $pid): array
    {
        $vacio = ['conceptos' => 0, 'cantidad' => 0.0, 'suma' => 0.0, 'programado_qty' => 0.0, 'programado' => 0.0, 'pendiente' => 0.0, 'actividades' => 0];
        if (!self::disponible()) return ['partidas' => [], 'sinPartida' => $vacio];
        $cat = [];
        foreach (self::filas("SELECT partida_id, COUNT(*) conceptos, COALESCE(SUM(cantidad), 0) cantidad,
                COALESCE(SUM(cantidad * precio_unitario), 0) suma
            FROM conceptos_obra WHERE proyecto_id = $pid AND activo = 1 GROUP BY partida_id") as $f) {
            $cat[(int)$f['partida_id']] = ['conceptos' => (int)$f['conceptos'], 'cantidad' => (float)$f['cantidad'], 'suma' => (float)$f['suma']];
        }
        $prog = [];
        foreach (self::filas("SELECT co.partida_id, COALESCE(SUM(ac.cantidad), 0) qty,
                COALESCE(SUM(ac.cantidad * co.precio_unitario), 0) importe,
                COUNT(DISTINCT ac.actividad_id) actividades
            FROM actividad_conceptos ac JOIN conceptos_obra co ON co.id = ac.concepto_id
            WHERE co.proyecto_id = $pid AND co.activo = 1 GROUP BY co.partida_id") as $f) {
            $prog[(int)$f['partida_id']] = ['qty' => (float)$f['qty'], 'importe' => (float)$f['importe'], 'actividades' => (int)$f['actividades']];
        }
        $out = ['partidas' => [], 'sinPartida' => $vacio];
        foreach (array_unique(array_merge(array_keys($cat), array_keys($prog))) as $key) {
            $key = (int)$key;
            $c = $cat[$key] ?? ['conceptos' => 0, 'cantidad' => 0.0, 'suma' => 0.0];
            $p = $prog[$key] ?? ['qty' => 0.0, 'importe' => 0.0, 'actividades' => 0];
            $fila = [
                'conceptos' => (int)$c['conceptos'], 'cantidad' => round((float)$c['cantidad'], 3),
                'suma' => round((float)$c['suma'], 2),
                'programado_qty' => round((float)$p['qty'], 3),
                'programado' => round((float)$p['importe'], 2),
                'pendiente' => round(max(0, (float)$c['suma'] - (float)$p['importe']), 2),
                'actividades' => (int)$p['actividades'],
            ];
            if ($key > 0) $out['partidas'][$key] = $fila;
            else $out['sinPartida'] = $fila;
        }
        return $out;
    }

    public static function resumenPartida(int $pid, int $partidaId): array
    {
        $r = self::resumenPartidas($pid);
        return $r['partidas'][$partidaId]
            ?? ['conceptos' => 0, 'cantidad' => 0.0, 'suma' => 0.0, 'programado_qty' => 0.0, 'programado' => 0.0, 'pendiente' => 0.0, 'actividades' => 0];
    }

    // ---------- escritura ----------

    /** @return array{0:bool,1:string,2:int} */
    public static function guardar(int $pid, array $d): array
    {
        if (!self::disponible()) return [false, 'Falta aplicar database/migracion_catalogo_conceptos.sql', 0];
        $id = (int)($d['id'] ?? 0);
        $descripcion = trim((string)($d['descripcion'] ?? $d['concepto'] ?? ''));
        if ($descripcion === '') return [false, 'Escribe la descripción del concepto', 0];
        $clave = trim((string)($d['clave'] ?? ''));
        $clave = $clave === '' ? null : mb_substr($clave, 0, 60);
        $unidad = mb_substr(trim((string)($d['unidad'] ?? '')), 0, 30);
        $cantidad = self::numero($d['cantidad'] ?? 0);
        $precio = self::numero($d['precio_unitario'] ?? 0);
        if ($cantidad === null || $cantidad < 0) return [false, 'La cantidad debe ser un número mayor o igual a cero', 0];
        if ($precio === null || $precio < 0) return [false, 'El precio unitario debe ser un número mayor o igual a cero', 0];
        $partidaId = (int)($d['partida_id'] ?? 0);
        if ($partidaId && !self::filas("SELECT id FROM partidas WHERE id = $partidaId AND proyecto_id = $pid")) {
            return [false, 'La partida no pertenece al proyecto', 0];
        }
        if ($clave !== null) {
            $t = "'" . self::esc($clave) . "'";
            if (self::filas("SELECT id FROM conceptos_obra WHERE proyecto_id = $pid AND clave = $t AND id <> $id")) {
                return [false, 'La clave «' . $clave . '» ya existe en este proyecto', 0];
            }
        }
        $antes = $id ? self::filaCruda($pid, $id) : null;
        if ($id && !$antes) return [false, 'El concepto no existe', 0];
        // Cambiar de partida un concepto ya repartido dejaría el presupuesto de una partida
        // sin sus actividades (o al revés): se pide desligarlo primero.
        if ($antes && (int)($antes['partida_id'] ?? 0) !== $partidaId) {
            $n = (int)(self::filas("SELECT COUNT(*) n FROM actividad_conceptos WHERE concepto_id = $id")[0]['n'] ?? 0);
            if ($n > 0) {
                return [false, 'El concepto tiene ' . $n . ' actividad(es) ligada(s); quítalas antes de cambiarlo de partida', 0];
            }
        }
        $origen = mb_substr(trim((string)($d['origen'] ?? ($antes['origen'] ?? 'manual'))), 0, 255) ?: 'manual';
        $notas = mb_substr(trim((string)($d['notas'] ?? '')), 0, 500);
        $activo = array_key_exists('activo', $d) ? (!empty($d['activo']) ? 1 : 0) : 1;
        $orden = (int)($d['orden'] ?? 0);
        $v = fn($x) => "'" . self::esc($x) . "'";
        $campos = "partida_id = " . ($partidaId ?: 'NULL') . ", clave = " . ($clave === null ? 'NULL' : $v($clave))
            . ", descripcion = {$v(mb_substr($descripcion, 0, 255))}, unidad = {$v($unidad)}, cantidad = $cantidad,
            precio_unitario = $precio, origen = {$v($origen)}, notas = {$v($notas)}, activo = $activo, orden = $orden";
        if ($id) {
            $r = HistorialCambios::escribir('conceptos_obra', 'modificado', $id,
                fn() => self::ejecutar("UPDATE conceptos_obra SET $campos WHERE id = $id AND proyecto_id = $pid"));
            if (!$r) return [false, 'No se pudo guardar el concepto', 0];
        } else {
            $nuevoId = 0;
            $r = HistorialCambios::escribir('conceptos_obra', 'creado', 0, function () use ($pid, $campos, &$nuevoId) {
                $res = self::ejecutar("INSERT INTO conceptos_obra SET proyecto_id = $pid, $campos");
                $nuevoId = (int)self::db()->insert_id;   // antes de que el historial haga sus consultas
                return $res;
            });
            if (!$r || !$nuevoId) return [false, 'No se pudo guardar el concepto', 0];
            $id = $nuevoId;
        }
        if ($antes && (int)$antes['partida_id'] > 0 && (int)$antes['partida_id'] !== $partidaId) {
            self::recalcularPartida($pid, (int)$antes['partida_id']);
        }
        if ($partidaId) self::recalcularPartida($pid, $partidaId);
        return [true, 'Concepto guardado', $id];
    }

    /** @return array{0:bool,1:string} */
    public static function eliminar(int $pid, int $id): array
    {
        $c = self::obtener($pid, $id);
        if (!$c) return [false, 'El concepto no existe'];
        $n = (int)(self::filas("SELECT COUNT(*) n FROM actividad_conceptos WHERE concepto_id = $id")[0]['n'] ?? 0);
        if ($n > 0) return [false, 'El concepto tiene ' . $n . ' actividad(es) ligada(s). Quítalas primero desde la actividad.'];
        HistorialCambios::escribir('conceptos_obra', 'eliminado', $id,
            fn() => self::ejecutar("DELETE FROM conceptos_obra WHERE id = $id AND proyecto_id = $pid"));
        if ((int)$c['partida_id'] > 0) self::recalcularPartida($pid, (int)$c['partida_id']);
        return [true, 'Concepto eliminado'];
    }

    /** Liga una cantidad de un concepto a una actividad (si excede el catálogo, pide motivo) */
    public static function asignar(int $pid, int $actividadId, int $conceptoId, float $cantidad, string $motivo = ''): array
    {
        if (!self::disponible()) return [false, 'Falta aplicar database/migracion_catalogo_conceptos.sql'];
        $act = self::filas("SELECT a.id, a.partida_id FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE a.id = $actividadId AND p.proyecto_id = $pid")[0] ?? null;
        if (!$act) return [false, 'La actividad no pertenece al proyecto'];
        $c = self::obtener($pid, $conceptoId);
        if (!$c) return [false, 'El concepto no pertenece al proyecto'];
        if (empty($c['activo'])) return [false, 'El concepto está inactivo'];
        if ($c['partida_id'] !== null && (int)$c['partida_id'] !== (int)$act['partida_id']) {
            return [false, 'El concepto es de otra partida; solo se pueden usar conceptos de la misma partida o generales'];
        }
        if ($cantidad < 0) return [false, 'La cantidad no puede ser negativa'];
        $cantidad = round($cantidad, 3);
        if ($cantidad <= 0.0005) return self::desasignar($pid, $actividadId, $conceptoId);
        $otras = (float)(self::filas("SELECT COALESCE(SUM(cantidad), 0) n FROM actividad_conceptos
            WHERE concepto_id = $conceptoId AND actividad_id <> $actividadId")[0]['n'] ?? 0);
        $excede = (float)$c['cantidad'] > 0 && $otras + $cantidad > (float)$c['cantidad'] + 0.0005;
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo));
        if ($excede && mb_strlen($motivo) < 10) {
            return [false, 'Excede la cantidad del catálogo (' . self::cantidad($c['cantidad'], $c['unidad']) . '). '
                . 'Lo programado sería ' . self::cantidad($otras + $cantidad, $c['unidad']) . '. Escribe el motivo (mínimo 10 caracteres) para autorizarlo.'];
        }
        $m = $excede ? "'" . self::esc(mb_substr($motivo, 0, 500)) . "'" : "''";
        if (!self::ejecutar("REPLACE INTO actividad_conceptos (actividad_id, concepto_id, cantidad, motivo_excedido)
            VALUES ($actividadId, $conceptoId, $cantidad, $m)")) {
            return [false, 'No se pudo guardar la cantidad en la actividad'];
        }
        self::recalcularPartida($pid, (int)$act['partida_id']);
        return [true, $excede ? 'Cantidad guardada con motivo de excedido' : 'Cantidad ligada a la actividad'];
    }

    /** @return array{0:bool,1:string} */
    public static function desasignar(int $pid, int $actividadId, int $conceptoId): array
    {
        if (!self::disponible()) return [false, 'Falta aplicar la migración del catálogo de conceptos'];
        $act = self::filas("SELECT a.partida_id FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE a.id = $actividadId AND p.proyecto_id = $pid")[0] ?? null;
        if (!$act) return [false, 'La actividad no pertenece al proyecto'];
        if (!self::ejecutar("DELETE FROM actividad_conceptos WHERE actividad_id = $actividadId AND concepto_id = $conceptoId")) {
            return [false, 'No se pudo quitar el concepto de la actividad'];
        }
        self::recalcularPartida($pid, (int)$act['partida_id']);
        return [true, 'Concepto quitado de la actividad'];
    }

    /** Sincroniza costos_partidas con el catálogo (presupuesto = suma + ajuste) */
    public static function recalcularPartida(int $pid, int $partidaId): void
    {
        if (!self::disponible() || $partidaId <= 0) return;
        try {
            $r = self::resumenPartida($pid, $partidaId);
            $guardada = self::filas("SELECT ajuste, presupuesto_manual FROM costos_partidas WHERE partida_id = $partidaId AND proyecto_id = $pid")[0] ?? null;
            $ajuste = (float)($guardada['ajuste'] ?? 0);
            if ((int)$r['conceptos'] > 0) {
                $total = round((float)$r['suma'] + $ajuste, 2);
                self::ejecutar("INSERT INTO costos_partidas (partida_id, proyecto_id, presupuesto, presupuesto_manual, ajuste, origen)
                    VALUES ($partidaId, $pid, $total, NULL, $ajuste, 'conceptos')
                    ON DUPLICATE KEY UPDATE presupuesto = VALUES(presupuesto), ajuste = VALUES(ajuste), origen = 'conceptos'");
            } else {
                self::ejecutar("UPDATE costos_partidas SET presupuesto = COALESCE(presupuesto_manual, 0), origen = 'manual'
                    WHERE partida_id = $partidaId AND proyecto_id = $pid");
            }
        } catch (\Throwable $e) {
            // El catálogo no debe impedir guardar una actividad: se registra y se sigue
            error_log('Conceptos::recalcularPartida: ' . $e->getMessage());
        }
    }

    public static function recalcularProyecto(int $pid): int
    {
        $n = 0;
        foreach (self::filas("SELECT id FROM partidas WHERE proyecto_id = $pid") as $p) {
            self::recalcularPartida($pid, (int)$p['id']);
            $n++;
        }
        return $n;
    }

    // ---------- reparto y generación de actividades ----------

    /** Reparte lo pendiente del concepto entre las actividades de su partida, por duración */
    public static function repartir(int $pid, int $conceptoId): array
    {
        $c = self::obtener($pid, $conceptoId);
        if (!$c) return [false, 'El concepto no existe'];
        if ($c['partida_id'] === null) return [false, 'Un concepto sin partida no se puede repartir; asígnalo primero a una partida'];
        $pendiente = max(0, (float)$c['cantidad'] - (float)$c['programado_qty']);
        if ($pendiente <= 0.0005) return [false, 'El concepto ya está repartido por completo'];
        $acts = self::filas("SELECT id, COALESCE(duracion_dias, 0) d FROM actividades
            WHERE partida_id = " . (int)$c['partida_id'] . " AND hito = 0 AND duracion_dias > 0 ORDER BY orden, codigo");
        if (!$acts) return [false, 'La partida no tiene actividades con duración para repartir'];
        $total = array_sum(array_map(fn($a) => (float)$a['d'], $acts));
        if ($total <= 0) return [false, 'Las actividades no tienen duración'];
        $restante = $pendiente;
        $n = 0;
        $ultimo = count($acts) - 1;
        foreach ($acts as $i => $a) {
            $parte = $i === $ultimo ? $restante : round($pendiente * (float)$a['d'] / $total, 3);
            if ($parte <= 0) continue;
            $actual = (float)(self::filas("SELECT cantidad FROM actividad_conceptos WHERE actividad_id = " . (int)$a['id'] . " AND concepto_id = $conceptoId")[0]['cantidad'] ?? 0);
            [$ok] = self::asignar($pid, (int)$a['id'], $conceptoId, round($actual + $parte, 3), 'Reparto automático del catálogo');
            if ($ok) { $n++; $restante = round($restante - $parte, 3); }
        }
        return $n > 0 ? [true, 'Se repartió entre ' . $n . ' actividad(es)'] : [false, 'No se pudo repartir el concepto'];
    }

    /** Crea la actividad de la partida del concepto y le liga lo que falta por programar */
    public static function crearActividad(int $pid, int $conceptoId): array
    {
        $c = self::obtener($pid, $conceptoId);
        if (!$c) return [false, 'El concepto no existe', 0];
        if ($c['partida_id'] === null) return [false, 'Asigna primero el concepto a una partida', 0];
        $partida = Partida::find((int)$c['partida_id']);
        $proyecto = Proyecto::find($pid);
        if (!$partida || !$proyecto) return [false, 'La partida ya no existe', 0];

        $prefijo = trim((string)$partida->codigo);
        $max = 0;
        $usados = [];
        foreach (self::filas("SELECT codigo FROM actividades WHERE partida_id = " . (int)$partida->id) as $a) {
            $cod = trim((string)$a['codigo']);
            $usados[mb_strtolower($cod)] = true;
            if ($prefijo !== '' && preg_match('/^' . preg_quote($prefijo, '/') . '\.(\d+)$/', $cod, $m)) $max = max($max, (int)$m[1]);
        }
        $n = $max + 1;
        while (isset($usados[mb_strtolower($prefijo . '.' . $n)])) $n++;
        $codigo = $prefijo . '.' . $n;

        $cal = new Calendario($pid);
        $inicio = $partida->fecha_inicio ?: ($proyecto->fecha_inicio ?: date('Y-m-d'));
        $d = new \DateTime($inicio);
        $guardia = 0;
        while (!$cal->esHabil($d->format('Y-m-d')) && $guardia++ < 400) $d->modify('+1 day');
        $inicio = $d->format('Y-m-d');
        $orden = (int)(self::filas("SELECT COALESCE(MAX(orden), 0) n FROM actividades WHERE partida_id = " . (int)$partida->id)[0]['n'] ?? 0) + 1;

        $act = new Actividad([
            'partida_id' => (int)$partida->id, 'codigo' => $codigo,
            'nombre' => mb_substr((string)$c['descripcion'], 0, 255), 'descripcion' => '',
            'fecha_inicio' => $inicio, 'duracion_dias' => 1, 'fecha_fin' => $cal->calcularFechaFin($inicio, 1),
            'progreso' => 0, 'prioridad' => 'media', 'hito' => 0, 'orden' => $orden,
        ]);
        try {
            $res = $act->guardar();
        } catch (\Throwable $e) {
            error_log('Conceptos::crearActividad: ' . $e->getMessage());
            return [false, 'No se pudo crear la actividad: ' . $e->getMessage(), 0];
        }
        if (!is_array($res) || empty($res['resultado'])) return [false, 'No se pudo crear la actividad', 0];
        $aid = (int)$res['id'];
        $pendiente = max(0, (float)$c['cantidad'] - (float)$c['programado_qty']);
        self::asignar($pid, $aid, $conceptoId, $pendiente > 0 ? $pendiente : (float)$c['cantidad'], 'Actividad creada desde el catálogo');
        try { $partida->recalcularFechas(); } catch (\Throwable $e) { error_log('Conceptos::crearActividad (fechas): ' . $e->getMessage()); }
        return [true, 'Actividad ' . $codigo . ' creada', $aid];
    }

    // ---------- integración con el Ensamblador ----------

    /**
     * Deja en el catálogo el concepto que describe una actividad (importaciones y Ensamblador):
     * reutiliza el concepto con la misma clave (o descripción + unidad en la partida) y liga la cantidad.
     * @return array{concepto_id:int, previa:?array, creado:bool}
     */
    public static function fijarDesdeTexto(int $pid, int $actividadId, array $d): array
    {
        $act = self::filas("SELECT a.id, a.partida_id FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE a.id = $actividadId AND p.proyecto_id = $pid")[0] ?? null;
        if (!$act) throw new \RuntimeException('La actividad no pertenece al proyecto');
        $clave = trim((string)($d['clave'] ?? ''));
        $descripcion = trim((string)($d['descripcion'] ?? $d['concepto'] ?? ''));
        if ($descripcion === '') throw new \RuntimeException('El concepto necesita descripción');
        $unidad = mb_substr(trim((string)($d['unidad'] ?? '')), 0, 30);
        $cantidad = max(0, (float)($d['cantidad'] ?? 0));
        $precio = isset($d['precio_unitario']) && is_numeric($d['precio_unitario']) ? (float)$d['precio_unitario'] : null;
        if ($precio === null && !empty($d['importe']) && $cantidad > 0) $precio = round((float)$d['importe'] / $cantidad, 2);

        $tClave = "'" . self::esc(mb_substr($clave, 0, 60)) . "'";
        $encontrado = null;
        if ($clave !== '') {
            $encontrado = self::filas("SELECT * FROM conceptos_obra WHERE proyecto_id = $pid AND clave = $tClave")[0] ?? null;
        } else {
            $encontrado = self::filas("SELECT * FROM conceptos_obra WHERE proyecto_id = $pid
                AND partida_id = " . (int)$act['partida_id'] . " AND LOWER(descripcion) = '" . self::esc(mb_strtolower(mb_substr($descripcion, 0, 255))) . "'
                AND unidad = '" . self::esc($unidad) . "'")[0] ?? null;
        }
        $creado = false;
        if (!$encontrado) {
            [$ok, $msg, $id] = self::guardar($pid, [
                'partida_id' => (int)$act['partida_id'], 'clave' => $clave, 'descripcion' => $descripcion,
                'unidad' => $unidad, 'cantidad' => $cantidad,
                'precio_unitario' => $precio ?? 0, 'origen' => (string)($d['origen'] ?? 'Ensamblador'),
            ]);
            if (!$ok) throw new \RuntimeException($msg);
            $encontrado = self::filaCruda($pid, $id);
            $creado = true;
        }
        $cid = (int)$encontrado['id'];
        if ($creado && $cantidad > 0) {
            // La cantidad contractual arranca en lo asignado para no marcar un pendiente falso
            self::ejecutar("UPDATE conceptos_obra SET cantidad = $cantidad WHERE id = $cid AND proyecto_id = $pid");
        } elseif ($precio !== null && (float)$encontrado['precio_unitario'] <= 0) {
            self::ejecutar("UPDATE conceptos_obra SET precio_unitario = $precio WHERE id = $cid AND proyecto_id = $pid");
        }
        $previa = self::filas("SELECT cantidad FROM actividad_conceptos WHERE actividad_id = $actividadId AND concepto_id = $cid")[0] ?? null;
        self::asignar($pid, $actividadId, $cid, $cantidad, 'Importado del Ensamblador');
        return ['concepto_id' => $cid, 'previa' => $previa ? ['cantidad' => (float)$previa['cantidad']] : null, 'creado' => $creado];
    }

    public static function restaurarAsignacion(int $pid, int $actividadId, int $conceptoId, ?array $previa): void
    {
        if ($previa !== null && isset($previa['cantidad'])) {
            self::asignar($pid, $actividadId, $conceptoId, (float)$previa['cantidad'], 'Restaurado desde el Ensamblador');
        } else {
            self::desasignar($pid, $actividadId, $conceptoId);
        }
    }

    // ---------- presupuesto para pantallas y exportaciones ----------

    /**
     * Catálogo agrupado por partida con totales y cargos, listo para la vista y los exportadores.
     * @return array{grupos: array, sinPartida: ?array, totales: array, cargos: array}
     */
    public static function presupuesto(int $pid): array
    {
        $conceptos = self::deProyecto($pid);
        $partidas = [];
        foreach (self::filas("SELECT id, codigo, nombre, color_bg FROM partidas WHERE proyecto_id = $pid ORDER BY orden, codigo") as $p) {
            $partidas[(int)$p['id']] = ['id' => (int)$p['id'], 'codigo' => $p['codigo'], 'nombre' => $p['nombre'], 'color' => $p['color_bg'], 'conceptos' => [], 'subtotal' => 0.0, 'programado' => 0.0, 'pendiente' => 0.0];
        }
        $sin = ['id' => 0, 'codigo' => '—', 'nombre' => 'Conceptos generales (sin partida)', 'color' => null, 'conceptos' => [], 'subtotal' => 0.0, 'programado' => 0.0, 'pendiente' => 0.0, 'ajuste' => 0.0, 'total' => 0.0];
        foreach ($conceptos as $c) {
            $g = (int)($c['partida_id'] ?? 0);
            if ($g > 0 && isset($partidas[$g])) $destino = &$partidas[$g];
            else $destino = &$sin;
            $destino['conceptos'][] = $c;
            $destino['subtotal'] = round($destino['subtotal'] + (float)$c['importe'], 2);
            $destino['programado'] = round($destino['programado'] + (float)$c['importe_programado'], 2);
            $destino['pendiente'] = round($destino['pendiente'] + (float)$c['pendiente'], 2);
            unset($destino);
        }
        $grupos = array_values(array_filter($partidas, fn($g) => $g['conceptos'] !== []));
        $sin = $sin['conceptos'] ? $sin : null;
        $pres = array_column(self::filas("SELECT partida_id, ajuste, presupuesto_manual, origen FROM costos_partidas WHERE proyecto_id = $pid"), null, 'partida_id');

        $ajustes = 0.0;
        foreach ($grupos as &$g) {
            $g['ajuste'] = round((float)($pres[$g['id']]['ajuste'] ?? 0), 2);
            $g['total'] = round($g['subtotal'] + $g['ajuste'], 2);
            $ajustes += $g['ajuste'];
        }
        unset($g);

        // Partidas sin conceptos con presupuesto manual: se muestran para que el documento cuadre con Costos
        $manualTotal = 0.0;
        foreach ($partidas as $id => $g) {
            if ($g['conceptos']) continue;
            $manual = round((float)($pres[$id]['presupuesto_manual'] ?? $pres[$id]['presupuesto'] ?? 0), 2);
            if ($manual <= 0) continue;
            $g['conceptos'][] = [
                'clave' => '', 'descripcion' => 'Presupuesto manual (partida sin conceptos)', 'unidad' => '',
                'cantidad' => 1.0, 'precio_unitario' => $manual, 'importe' => $manual,
                'programado_qty' => 0.0, 'importe_programado' => 0.0, 'pendiente_qty' => 0.0, 'pendiente' => 0.0,
                'pct' => 0.0, 'excedido' => false, 'actividades' => 0,
            ];
            $g['subtotal'] = $manual;
            $g['programado'] = 0.0;
            $g['pendiente'] = 0.0;
            $g['ajuste'] = 0.0;
            $g['total'] = $manual;
            $g['manual'] = true;
            $grupos[] = $g;
            $manualTotal += $manual;
        }

        $cambios = 0.0;
        foreach (Costos::cambiosAutorizados($pid) as $oc) $cambios += (float)$oc['impacto_costo'];
        $directo = round(array_sum(array_column($grupos, 'subtotal')) + ($sin['subtotal'] ?? 0) - $manualTotal, 2);
        $programado = round(array_sum(array_column($grupos, 'programado')) + ($sin['programado'] ?? 0), 2);
        $pendiente = round(array_sum(array_column($grupos, 'pendiente')) + ($sin['pendiente'] ?? 0), 2);
        $cargosCfg = CargosProyecto::deProyecto($pid);
        $subtotal = round($directo + $manualTotal + $ajustes + $cambios, 2);
        $desglose = CargosProyecto::desglose($subtotal, $cargosCfg);
        return [
            'grupos' => $grupos, 'sinPartida' => $sin,
            'totales' => [
                'directo' => $directo, 'manual' => round($manualTotal, 2), 'ajustes' => round($ajustes, 2), 'cambios' => round($cambios, 2),
                'subtotal' => $subtotal, 'programado' => $programado, 'pendiente' => $pendiente,
                'indirectos' => $desglose['indirectos'], 'financiamiento' => $desglose['financiamiento'],
                'utilidad' => $desglose['utilidad'], 'iva' => $desglose['iva'], 'total' => $desglose['total'],
                'factor' => $desglose['factor'], 'iva_en_bac' => (int)$cargosCfg['iva_en_bac'],
            ],
            'cargos' => $cargosCfg,
        ];
    }

    // ---------- utilidades ----------

    /** Formatea una cantidad con su unidad (3 decimales, sin ceros de sobra) */
    public static function cantidad($n, string $unidad = ''): string
    {
        $t = rtrim(rtrim(number_format((float)$n, 3, '.', ','), '0'), '.');
        return ($t === '' ? '0' : $t) . ($unidad !== '' ? ' ' . $unidad : '');
    }

    private static function completar(array $f): array
    {
        $cantidad = (float)$f['cantidad'];
        $precio = (float)$f['precio_unitario'];
        $progQty = (float)($f['programado_qty'] ?? 0);
        $f['cantidad'] = $cantidad;
        $f['precio_unitario'] = $precio;
        $f['programado_qty'] = round($progQty, 3);
        $f['importe'] = self::importe($cantidad, $precio);
        $f['importe_programado'] = self::importe($progQty, $precio);
        $f['pendiente_qty'] = round(max(0, $cantidad - $progQty), 3);
        $f['pendiente'] = self::importe($f['pendiente_qty'], $precio);
        $f['pct'] = $cantidad > 0 ? round(min(100, $progQty / $cantidad * 100), 1) : 0.0;
        $f['excedido'] = $cantidad > 0 && $progQty > $cantidad + 0.0005;
        $f['actividades'] = (int)($f['actividades'] ?? 0);
        return $f;
    }

    private static function filaCruda(int $pid, int $id): ?array
    {
        return self::filas("SELECT * FROM conceptos_obra WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    private static function numero($v): ?float
    {
        if ($v === null || $v === '') return 0.0;
        $t = str_replace([',', '$', ' '], '', (string)$v);
        return is_numeric($t) ? (float)$t : null;
    }
}
