<?php

namespace Classes;

/**
 * Registro de riesgos de la obra: lista manual por proyecto.
 * La severidad es probabilidad (1-3) × impacto (1-3) y no se guarda: se calcula al consultar.
 * Un riesgo puede ligarse a una actividad y convertirse en restricción cuando se materializa.
 */
class Riesgos
{
    use AccesoDb;

    public const PROBABILIDADES = [1 => 'Baja', 2 => 'Media', 3 => 'Alta'];
    public const IMPACTOS = [1 => 'Bajo', 2 => 'Medio', 3 => 'Alto'];
    public const CATEGORIAS = [
        'tecnico' => 'Técnico',
        'clima' => 'Clima',
        'suministro' => 'Suministro',
        'seguridad' => 'Seguridad',
        'financiero' => 'Financiero',
        'permisos' => 'Permisos y trámites',
        'mano_obra' => 'Mano de obra',
        'calidad' => 'Calidad',
        'otro' => 'Otro',
    ];
    public const ESTRATEGIAS = ['mitigar' => 'Mitigar', 'transferir' => 'Transferir', 'aceptar' => 'Aceptar', 'evitar' => 'Evitar'];
    public const ESTADOS = [
        'identificado' => 'Identificado',
        'en_tratamiento' => 'En tratamiento',
        'materializado' => 'Materializado',
        'cerrado' => 'Cerrado',
    ];
    public const NIVELES = ['verde' => 'Bajo', 'amarillo' => 'Medio', 'rojo' => 'Alto'];

    /** Categoría del riesgo → nombre del tipo de restricción que se crea al materializarse */
    private const TIPO_RESTRICCION = [
        'tecnico' => 'Ingeniería', 'clima' => 'Clima', 'suministro' => 'Material', 'seguridad' => 'Otro',
        'financiero' => 'Contrato', 'permisos' => 'Acceso', 'mano_obra' => 'Mano de Obra', 'calidad' => 'Ingeniería', 'otro' => 'Otro',
    ];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM riesgos LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ---------- cálculo ----------

    public static function severidad(int $probabilidad, int $impacto): int
    {
        return max(1, min(3, $probabilidad)) * max(1, min(3, $impacto));
    }

    /** verde (1-3) · amarillo (4-6) · rojo (7-9) */
    public static function nivel(int $severidad): string
    {
        return $severidad >= 7 ? 'rojo' : ($severidad >= 4 ? 'amarillo' : 'verde');
    }

    /** ¿Sigue abierto? (ni materializado ni cerrado) */
    public static function abierto(array $r): bool
    {
        return in_array($r['estado'] ?? '', ['identificado', 'en_tratamiento'], true);
    }

    // ---------- consulta ----------

    /**
     * Riesgos del proyecto con su severidad calculada. Filtros: sev (verde|amarillo|rojo), estado, cat, q.
     * Los abiertos van primero, luego por severidad y fecha compromiso.
     */
    public static function lista(int $pid, array $f = []): array
    {
        $w = ["r.proyecto_id = $pid"];
        $sev = (string)($f['sev'] ?? '');
        if (isset(self::NIVELES[$sev])) {
            $w[] = ['verde' => '(probabilidad * impacto) BETWEEN 1 AND 3', 'amarillo' => '(probabilidad * impacto) BETWEEN 4 AND 6', 'rojo' => '(probabilidad * impacto) BETWEEN 7 AND 9'][$sev];
        }
        if (isset(self::ESTADOS[$f['estado'] ?? ''])) $w[] = "estado = '" . self::esc($f['estado']) . "'";
        if (isset(self::CATEGORIAS[$f['cat'] ?? ''])) $w[] = "categoria = '" . self::esc($f['cat']) . "'";
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $t = "'%" . self::esc($q) . "%'";
            $w[] = "(titulo LIKE $t OR descripcion LIKE $t OR responsable LIKE $t OR plan_accion LIKE $t)";
        }
        $filas = self::filas("SELECT r.*, a.codigo act_codigo, a.nombre act_nombre, pa.codigo partida_codigo
            FROM riesgos r
            LEFT JOIN actividades a ON a.id = r.actividad_id
            LEFT JOIN partidas pa ON pa.id = a.partida_id
            WHERE " . implode(' AND ', $w) . "
            ORDER BY (r.estado IN ('materializado','cerrado')) ASC, (r.probabilidad * r.impacto) DESC,
                     r.fecha_compromiso IS NULL, r.fecha_compromiso ASC, r.id DESC");
        foreach ($filas as &$r) {
            $r['severidad'] = self::severidad((int)$r['probabilidad'], (int)$r['impacto']);
            $r['nivel'] = self::nivel($r['severidad']);
        }
        return $filas;
    }

    public static function riesgo(int $pid, int $id): ?array
    {
        $r = self::filas("SELECT r.*, a.codigo act_codigo, a.nombre act_nombre FROM riesgos r
            LEFT JOIN actividades a ON a.id = r.actividad_id WHERE r.id = $id AND r.proyecto_id = $pid")[0] ?? null;
        if ($r) {
            $r['severidad'] = self::severidad((int)$r['probabilidad'], (int)$r['impacto']);
            $r['nivel'] = self::nivel($r['severidad']);
        }
        return $r;
    }

    /**
     * Totales para el tablero y el semáforo de salud.
     * @return array{total:int,abiertos:int,rojos:int,amarillos:int,verdes:int,vencidos:int,semana:int,materializados:int,cerrados:int}
     */
    public static function resumen(int $pid): array
    {
        $out = ['total' => 0, 'abiertos' => 0, 'rojos' => 0, 'amarillos' => 0, 'verdes' => 0, 'vencidos' => 0, 'semana' => 0, 'materializados' => 0, 'cerrados' => 0, 'tratamiento' => 0];
        $hoy = date('Y-m-d');
        $en7 = date('Y-m-d', strtotime('+7 days'));
        foreach (self::filas("SELECT probabilidad, impacto, estado, fecha_compromiso FROM riesgos WHERE proyecto_id = $pid") as $r) {
            $out['total']++;
            $sev = self::severidad((int)$r['probabilidad'], (int)$r['impacto']);
            if ($r['estado'] === 'materializado') $out['materializados']++;
            if ($r['estado'] === 'cerrado') { $out['cerrados']++; continue; }
            if (!self::abierto($r)) continue;
            $out['abiertos']++;
            if ($r['estado'] === 'en_tratamiento') $out['tratamiento']++;
            $out[$sev >= 7 ? 'rojos' : ($sev >= 4 ? 'amarillos' : 'verdes')]++;
            $fecha = (string)($r['fecha_compromiso'] ?? '');
            if ($fecha !== '' && $fecha < $hoy) $out['vencidos']++;
            if ($sev >= 7 && $fecha !== '' && $fecha <= $en7) $out['semana']++;
        }
        return $out;
    }

    /**
     * Conteo de riesgos abiertos por casilla de la matriz 3×3: [probabilidad][impacto] => cantidad.
     * Las filas van de probabilidad alta a baja y las columnas de impacto bajo a alto.
     */
    public static function matriz(int $pid): array
    {
        $m = [];
        for ($p = 1; $p <= 3; $p++) for ($i = 1; $i <= 3; $i++) $m[$p][$i] = 0;
        foreach (self::filas("SELECT probabilidad, impacto FROM riesgos WHERE proyecto_id = $pid AND estado IN ('identificado','en_tratamiento')") as $r) {
            $p = max(1, min(3, (int)$r['probabilidad']));
            $i = max(1, min(3, (int)$r['impacto']));
            $m[$p][$i]++;
        }
        return $m;
    }

    /** Riesgos rojos abiertos, para el semáforo, el programa y el resumen matutino */
    public static function rojosAbiertos(int $pid, ?int $actividadId = null): array
    {
        $w = $actividadId ? " AND actividad_id = " . (int)$actividadId : '';
        $filas = self::filas("SELECT * FROM riesgos WHERE proyecto_id = $pid AND estado IN ('identificado','en_tratamiento')$w
            AND (probabilidad * impacto) >= 7 ORDER BY fecha_compromiso IS NULL, fecha_compromiso, id DESC");
        foreach ($filas as &$r) {
            $r['severidad'] = self::severidad((int)$r['probabilidad'], (int)$r['impacto']);
            $r['nivel'] = self::nivel($r['severidad']);
        }
        return $filas;
    }

    // ---------- escritura ----------

    /** Crea o actualiza un riesgo. Devuelve [ok, mensaje, id]. */
    public static function guardar(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $titulo = trim((string)($d['titulo'] ?? ''));
        if ($titulo === '') return [false, 'Escribe qué riesgo es', 0];
        $prob = (int)($d['probabilidad'] ?? 2);
        $imp = (int)($d['impacto'] ?? 2);
        if (!isset(self::PROBABILIDADES[$prob]) || !isset(self::IMPACTOS[$imp])) return [false, 'Elige probabilidad e impacto', 0];
        $categoria = is_string($d['categoria'] ?? null) && isset(self::CATEGORIAS[$d['categoria']]) ? $d['categoria'] : 'tecnico';
        $estrategia = is_string($d['estrategia'] ?? null) && isset(self::ESTRATEGIAS[$d['estrategia']]) ? $d['estrategia'] : 'mitigar';
        $estado = is_string($d['estado'] ?? null) && isset(self::ESTADOS[$d['estado']]) ? $d['estado'] : 'identificado';
        $actividad = (int)($d['actividad_id'] ?? 0);
        if ($actividad && !self::filas("SELECT 1 FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $actividad AND p.proyecto_id = $pid")) {
            $actividad = 0;
        }
        $fecha = self::fecha($d['fecha_compromiso'] ?? '');
        $responsable = mb_substr(trim((string)($d['responsable'] ?? '')), 0, 150);
        if ($responsable === '') return [false, 'Indica quién responde del riesgo', 0];
        $armar = fn() => "actividad_id = " . ($actividad ?: 'NULL') . ", titulo = '" . self::esc(mb_substr($titulo, 0, 250)) . "',
            descripcion = '" . self::esc(mb_substr(trim((string)($d['descripcion'] ?? '')), 0, 5000)) . "',
            categoria = '$categoria', probabilidad = $prob, impacto = $imp,
            responsable = '" . self::esc($responsable) . "', estrategia = '$estrategia',
            plan_accion = '" . self::esc(mb_substr(trim((string)($d['plan_accion'] ?? '')), 0, 5000)) . "',
            fecha_compromiso = " . ($fecha ? "'$fecha'" : 'NULL') . ", estado = '$estado'";

        if ($id) {
            if (!self::riesgo($pid, $id)) return [false, 'El riesgo no pertenece al proyecto', 0];
            self::db()->query("UPDATE riesgos SET " . $armar() . " WHERE id = $id AND proyecto_id = $pid");
            $evento = 'actualizar_riesgo';
        } else {
            $usuario = self::esc(mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150));
            self::db()->query("INSERT INTO riesgos SET proyecto_id = $pid, " . $armar() . ", creado_por = '$usuario'");
            $id = (int)self::db()->insert_id;
            $evento = 'crear_riesgo';
        }
        self::auditar($pid, $evento, 'editar', ($evento === 'crear_riesgo' ? 'Creó' : 'Actualizó') . ' el riesgo «' . mb_substr($titulo, 0, 160) . '» (' . self::severidad($prob, $imp) . '/9)', $id);
        return [true, 'Riesgo guardado', $id];
    }

    public static function eliminar(int $pid, int $id): bool
    {
        $r = self::riesgo($pid, $id);
        if (!$r) return false;
        self::db()->query("DELETE FROM riesgos WHERE id = $id AND proyecto_id = $pid");
        self::auditar($pid, 'eliminar_riesgo', 'eliminar', 'Eliminó el riesgo «' . mb_substr((string)$r['titulo'], 0, 160) . '»', $id);
        return true;
    }

    /**
     * Convierte un riesgo materializado en restricción de su actividad (sin capturar dos veces).
     * Solo funciona si el riesgo tiene una actividad ligada.
     */
    public static function materializar(int $pid, int $id): array
    {
        $r = self::riesgo($pid, $id);
        if (!$r) return [false, 'El riesgo no existe'];
        if (!empty($r['restriccion_id'])) return [false, 'Ese riesgo ya se convirtió en restricción'];
        $aid = (int)($r['actividad_id'] ?? 0);
        if (!$aid) return [false, 'Liga el riesgo a una actividad para convertirlo en restricción'];

        $nombre = self::TIPO_RESTRICCION[$r['categoria']] ?? 'Otro';
        $tipo = self::filas("SELECT id FROM tipos_restriccion WHERE nombre = '" . self::esc($nombre) . "'")[0]
            ?? self::filas("SELECT id FROM tipos_restriccion ORDER BY id LIMIT 1")[0] ?? null;
        if (!$tipo) return [false, 'No hay tipos de restricción configurados'];
        $tipoId = (int)$tipo['id'];
        $desc = trim((string)$r['titulo'] . (trim((string)$r['plan_accion']) !== '' ? ' · ' . $r['plan_accion'] : ''));
        $fecha = date('Y-m-d');
        self::db()->query("INSERT INTO restricciones (actividad_id, tipo_id, codigo, descripcion, fecha, estado)
            VALUES ($aid, $tipoId, '" . self::esc('R-' . (int)$r['id']) . "', '" . self::esc(mb_substr($desc, 0, 5000)) . "', '$fecha', 'activa')");
        $rid = (int)self::db()->insert_id;
        self::db()->query("UPDATE riesgos SET estado = 'materializado', restriccion_id = $rid WHERE id = $id AND proyecto_id = $pid");
        self::auditar($pid, 'materializar_riesgo', 'editar', 'Materializó el riesgo «' . mb_substr((string)$r['titulo'], 0, 160) . '» como restricción de la actividad ' . $aid, $id);
        return [true, 'Riesgo materializado y convertido en restricción'];
    }

    /** Riesgo rojo abierto ligado a cada actividad, en el formato que usa Salud para la etiqueta «En riesgo» */
    public static function riesgosDeActividades(int $pid): array
    {
        $out = [];
        foreach (self::rojosAbiertos($pid) as $r) {
            $aid = (int)($r['actividad_id'] ?? 0);
            if (!$aid) continue;
            $out[$aid][] = [
                'tipo' => 'registro',
                'texto' => 'Riesgo alto: ' . self::corto((string)$r['titulo'], 120),
                'url' => "/riesgos?proyecto=$pid&ver=" . (int)$r['id'],
            ];
        }
        return $out;
    }

    private static function fecha($v): ?string
    {
        $v = trim((string)$v);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return null;
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $v : null;
    }

    private static function auditar(int $pid, string $evento, string $accion, string $resumen, int $id): void
    {
        try {
            \Classes\Auditoria::registrar($pid, 'riesgos', $evento, $accion, $resumen, null, null, $id, 'Riesgo');
        } catch (\Throwable $e) {
        }
    }
}
