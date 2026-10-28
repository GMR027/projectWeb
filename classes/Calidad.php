<?php

namespace Classes;

/**
 * Calidad e inspecciones (ITP): plan de puntos de inspección por actividad o por proyecto.
 * Cada punto se marca cumple / no cumple / no aplica; las no conformidades abiertas se ven aparte.
 */
class Calidad
{
    use AccesoDb;

    public const TIPOS = ['itp' => 'ITP (plan de inspección)', 'inspeccion' => 'Inspección', 'prueba' => 'Prueba de laboratorio'];
    public const ESTADOS = ['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'conforme' => 'Conforme', 'no_conforme' => 'No conforme', 'cerrado' => 'Cerrado'];
    public const RESULTADOS = ['pendiente' => 'Pendiente', 'cumple' => 'Cumple', 'no_cumple' => 'No cumple', 'na' => 'No aplica'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM calidad_planes LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Planes con su actividad y el conteo de puntos */
    public static function planes(int $pid, string $estado = ''): array
    {
        $w = isset(self::ESTADOS[$estado]) ? " AND c.estado = '$estado'" : '';
        return self::filas("SELECT c.*, a.codigo act_codigo, a.nombre act_nombre,
                (SELECT COUNT(*) FROM calidad_puntos p WHERE p.plan_id = c.id) puntos,
                (SELECT COUNT(*) FROM calidad_puntos p WHERE p.plan_id = c.id AND p.resultado = 'cumple') cumplen,
                (SELECT COUNT(*) FROM calidad_puntos p WHERE p.plan_id = c.id AND p.resultado = 'no_cumple' AND p.resuelto = 0) nc
            FROM calidad_planes c LEFT JOIN actividades a ON a.id = c.actividad_id
            WHERE c.proyecto_id = $pid$w
            ORDER BY FIELD(c.estado, 'no_conforme', 'pendiente', 'en_proceso', 'conforme', 'cerrado'), c.fecha IS NULL, c.fecha, c.id DESC");
    }

    public static function plan(int $pid, int $id): ?array
    {
        return self::filas("SELECT c.*, a.codigo act_codigo, a.nombre act_nombre FROM calidad_planes c
            LEFT JOIN actividades a ON a.id = c.actividad_id WHERE c.id = $id AND c.proyecto_id = $pid")[0] ?? null;
    }

    public static function puntos(int $planId): array
    {
        return self::filas("SELECT * FROM calidad_puntos WHERE plan_id = $planId ORDER BY orden, id");
    }

    /** Crea o actualiza un plan. En uno nuevo, `puntos` es una lista separada por líneas. */
    public static function guardarPlan(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $titulo = trim((string)($d['titulo'] ?? ''));
        if ($titulo === '') return [false, 'El título es obligatorio', 0];
        $actividad = (int)($d['actividad_id'] ?? 0);
        if ($actividad) {
            $ok = self::filas("SELECT 1 FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $actividad AND p.proyecto_id = $pid");
            if (!$ok) $actividad = 0;
        }
        $tipo = isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'itp';
        $estado = isset(self::ESTADOS[$d['estado'] ?? '']) ? $d['estado'] : 'pendiente';
        $fecha = self::fecha($d['fecha'] ?? '');
        $campos = "actividad_id = " . ($actividad ?: 'NULL') . ", codigo = '" . self::esc(mb_substr(trim((string)($d['codigo'] ?? '')), 0, 40)) . "',
            titulo = '" . self::esc(mb_substr($titulo, 0, 200)) . "', tipo = '$tipo', norma = '" . self::esc(mb_substr(trim((string)($d['norma'] ?? '')), 0, 200)) . "',
            responsable = '" . self::esc(mb_substr(trim((string)($d['responsable'] ?? '')), 0, 150)) . "',
            fecha = " . ($fecha ? "'$fecha'" : 'NULL') . ", estado = '$estado', notas = '" . self::esc(mb_substr(trim((string)($d['notas'] ?? '')), 0, 5000)) . "'";

        if ($id) {
            if (!self::plan($pid, $id)) return [false, 'El plan no existe', 0];
            self::db()->query("UPDATE calidad_planes SET $campos WHERE id = $id AND proyecto_id = $pid");
        } else {
            self::db()->query("INSERT INTO calidad_planes SET proyecto_id = $pid, $campos");
            $id = (int)self::db()->insert_id;
            $lineas = preg_split('/\R/', (string)($d['puntos'] ?? '')) ?: [];
            $orden = 0;
            foreach ($lineas as $linea) {
                $linea = trim($linea);
                if ($linea === '') continue;
                [$desc, $criterio] = array_pad(explode('|', $linea, 2), 2, '');
                self::db()->query("INSERT INTO calidad_puntos SET plan_id = $id, orden = " . (++$orden)
                    . ", descripcion = '" . self::esc(mb_substr(trim($desc), 0, 500)) . "', criterio = '" . self::esc(mb_substr(trim($criterio), 0, 300)) . "'");
            }
        }
        self::recalcular($id);
        return [true, 'Plan guardado', $id];
    }

    /** Agrega un punto a un plan existente */
    public static function agregarPunto(int $pid, int $planId, string $descripcion, string $criterio = ''): bool
    {
        $plan = self::plan($pid, $planId);
        $descripcion = trim($descripcion);
        if (!$plan || $descripcion === '') return false;
        $orden = (int)(self::filas("SELECT COALESCE(MAX(orden), 0) n FROM calidad_puntos WHERE plan_id = $planId")[0]['n'] ?? 0) + 1;
        self::db()->query("INSERT INTO calidad_puntos SET plan_id = $planId, orden = $orden,
            descripcion = '" . self::esc(mb_substr($descripcion, 0, 500)) . "',
            criterio = '" . self::esc(mb_substr(trim($criterio), 0, 300)) . "'");
        self::recalcular($planId);
        return true;
    }

    /** Marca un punto (y recalcula el estado del plan) */
    public static function marcarPunto(int $pid, int $puntoId, string $resultado, string $observacion, string $responsable = '', bool $resuelto = false): bool
    {
        if (!isset(self::RESULTADOS[$resultado])) return false;
        $p = self::filas("SELECT p.*, c.id plan, c.proyecto_id FROM calidad_puntos p JOIN calidad_planes c ON c.id = p.plan_id
            WHERE p.id = $puntoId AND c.proyecto_id = $pid")[0] ?? null;
        if (!$p) return false;
        $fecha = $resultado === 'pendiente' ? 'NULL' : "'" . date('Y-m-d') . "'";
        self::db()->query("UPDATE calidad_puntos SET resultado = '$resultado', fecha = $fecha,
            observacion = '" . self::esc(mb_substr(trim($observacion), 0, 1000)) . "',
            responsable = '" . self::esc(mb_substr(trim($responsable), 0, 150)) . "',
            resuelto = " . ($resuelto ? 1 : 0) . " WHERE id = $puntoId");
        self::recalcular((int)$p['plan']);
        return true;
    }

    /** El estado del plan sale de sus puntos: una no conformidad abierta manda */
    private static function recalcular(int $planId): void
    {
        $p = self::filas("SELECT resultado, resuelto FROM calidad_puntos WHERE plan_id = $planId");
        if (!$p) return;
        $nc = 0; $ok = 0; $hechos = 0;
        foreach ($p as $x) {
            if ($x['resultado'] === 'no_cumple') {
                // Una no conformidad resuelta ya no bloquea el plan: cuenta como punto atendido
                if ((int)$x['resuelto']) $ok++; else $nc++;
            } elseif ($x['resultado'] === 'cumple' || $x['resultado'] === 'na') {
                $ok++;
            }
            if ($x['resultado'] !== 'pendiente') $hechos++;
        }
        $estado = $nc ? 'no_conforme' : ($hechos === 0 ? 'pendiente' : ($ok === count($p) ? 'conforme' : 'en_proceso'));
        self::db()->query("UPDATE calidad_planes SET estado = '$estado' WHERE id = $planId AND estado <> 'cerrado'");
    }

    /** Puntos con no conformidad abierta (para la lista y el resumen) */
    public static function noConformidades(int $pid): array
    {
        return self::filas("SELECT p.*, c.titulo plan_titulo, c.codigo plan_codigo, a.codigo act_codigo, a.nombre act_nombre
            FROM calidad_puntos p JOIN calidad_planes c ON c.id = p.plan_id LEFT JOIN actividades a ON a.id = c.actividad_id
            WHERE c.proyecto_id = $pid AND p.resultado = 'no_cumple' AND p.resuelto = 0
            ORDER BY p.fecha, p.id");
    }

    public static function resumen(int $pid): array
    {
        $c = self::filas("SELECT
            COUNT(*) total,
            SUM(estado = 'pendiente') pendiente, SUM(estado = 'en_proceso') en_proceso,
            SUM(estado = 'conforme') conforme, SUM(estado = 'no_conforme') no_conforme,
            SUM(estado = 'cerrado') cerrado
            FROM calidad_planes WHERE proyecto_id = $pid")[0] ?? [];
        $p = self::filas("SELECT
            COUNT(*) total,
            SUM(p.resultado = 'pendiente') pendiente, SUM(p.resultado = 'cumple') cumple,
            SUM(p.resultado = 'no_cumple' AND p.resuelto = 0) nc
            FROM calidad_puntos p JOIN calidad_planes c ON c.id = p.plan_id WHERE c.proyecto_id = $pid")[0] ?? [];
        return [
            'planes' => (int)($c['total'] ?? 0), 'pendiente' => (int)($c['pendiente'] ?? 0), 'en_proceso' => (int)($c['en_proceso'] ?? 0),
            'conforme' => (int)($c['conforme'] ?? 0), 'no_conforme' => (int)($c['no_conforme'] ?? 0), 'cerrado' => (int)($c['cerrado'] ?? 0),
            'puntos' => (int)($p['total'] ?? 0), 'puntos_pendientes' => (int)($p['pendiente'] ?? 0), 'puntos_cumplen' => (int)($p['cumple'] ?? 0),
            'nc' => (int)($p['nc'] ?? 0),
        ];
    }

    public static function eliminar(int $pid, int $id): bool
    {
        if (!self::plan($pid, $id)) return false;
        self::db()->query("DELETE FROM calidad_planes WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    private static function fecha($v): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', (string)$v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }
}
