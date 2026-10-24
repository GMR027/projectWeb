<?php

namespace Classes;

use Model\Proyecto;

/**
 * Recursos y cuadrillas: personas o cuadrillas asignadas a actividades con un % de dedicación.
 * Carga semanal de un recurso = Σ (dedicación × días hábiles de la actividad dentro de la semana ÷ días hábiles de la semana).
 * Más de 100 % = sobrecarga. Los días hábiles salen del calendario del proyecto (jornada + festivos).
 */
class Recursos
{
    use AccesoDb;

    public const TIPOS = ['persona' => 'Persona', 'cuadrilla' => 'Cuadrilla'];
    public const MAX_SEMANAS = 26;

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query("SELECT 1 FROM recursos LIMIT 1");
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ---------- escritura ----------

    /** @return string|null mensaje de error */
    public static function guardar(int $pid, array $d): ?string
    {
        $nombre = mb_substr(trim((string)($d['nombre'] ?? '')), 0, 150);
        $personal = (int)($d['personal_id'] ?? 0);
        if ($personal) {
            $p = self::filas("SELECT nombre FROM personal WHERE id = $personal AND proyecto_id = $pid")[0] ?? null;
            if (!$p) $personal = 0;
            elseif ($nombre === '') $nombre = $p['nombre'];
        }
        if ($nombre === '') return 'El nombre es obligatorio';
        $tipo = isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'persona';
        $integrantes = $tipo === 'cuadrilla' ? max(1, min(500, (int)($d['integrantes'] ?? 1))) : 1;
        $horas = max(1, min(24, round((float)($d['horas_dia'] ?? 8), 1)));
        $id = (int)($d['id'] ?? 0);
        if ($id && !self::filas("SELECT id FROM recursos WHERE id = $id AND proyecto_id = $pid")) return 'El recurso no existe';
        $campos = "nombre = '" . self::esc($nombre) . "', tipo = '$tipo', personal_id = " . ($personal ?: 'NULL') . ", integrantes = $integrantes, horas_dia = $horas";
        if ($id) HistorialCambios::escribir('recursos', 'modificado', $id, fn() => self::db()->query("UPDATE recursos SET $campos WHERE id = $id"));
        else HistorialCambios::escribir('recursos', 'creado', 0, fn() => self::db()->query("INSERT INTO recursos SET proyecto_id = $pid, $campos"));
        return null;
    }

    public static function eliminar(int $pid, int $id): void
    {
        HistorialCambios::escribir('recursos', 'eliminado', $id,
            fn() => self::db()->query("DELETE FROM recursos WHERE id = $id AND proyecto_id = $pid"));
    }

    /** @return string|null mensaje de error */
    public static function asignar(int $pid, int $actividadId, int $recursoId, int $dedicacion): ?string
    {
        if (!self::filas("SELECT a.id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $actividadId AND p.proyecto_id = $pid")) return 'La actividad no existe';
        if (!self::filas("SELECT id FROM recursos WHERE id = $recursoId AND proyecto_id = $pid")) return 'El recurso no existe';
        $dedicacion = max(1, min(100, $dedicacion));
        self::db()->query("INSERT INTO actividad_recursos (actividad_id, recurso_id, dedicacion) VALUES ($actividadId, $recursoId, $dedicacion) ON DUPLICATE KEY UPDATE dedicacion = VALUES(dedicacion)");
        return null;
    }

    public static function quitar(int $pid, int $actividadId, int $recursoId): void
    {
        self::db()->query("DELETE ar FROM actividad_recursos ar JOIN recursos r ON r.id = ar.recurso_id WHERE ar.actividad_id = $actividadId AND ar.recurso_id = $recursoId AND r.proyecto_id = $pid");
    }

    // ---------- lectura ----------

    public static function recursos(int $pid): array
    {
        return self::filas("SELECT r.*, (SELECT COUNT(*) FROM actividad_recursos ar WHERE ar.recurso_id = r.id) asignaciones FROM recursos r WHERE r.proyecto_id = $pid ORDER BY r.tipo, r.nombre");
    }

    public static function personal(int $pid): array
    {
        return self::filas("SELECT id, nombre, departamento FROM personal WHERE proyecto_id = $pid ORDER BY nombre");
    }

    public static function asignaciones(int $pid): array
    {
        return self::filas("SELECT ar.actividad_id, ar.recurso_id, ar.dedicacion, a.codigo, a.nombre actividad, a.fecha_inicio, a.fecha_fin, a.progreso, a.hito,
                r.nombre recurso, r.tipo, p.codigo partida_codigo, p.nombre partida
            FROM actividad_recursos ar JOIN recursos r ON r.id = ar.recurso_id JOIN actividades a ON a.id = ar.actividad_id JOIN partidas p ON p.id = a.partida_id
            WHERE r.proyecto_id = $pid ORDER BY a.fecha_inicio, a.codigo, r.nombre");
    }

    /** Actividades del proyecto sin hitos, para el selector (formato de opciones_actividades) */
    public static function actividades(int $pid): array
    {
        $out = [];
        foreach (self::filas("SELECT a.id, a.codigo, a.nombre, a.partida_id, p.codigo partida_codigo, p.nombre partida, p.color_bg color FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE p.proyecto_id = $pid AND a.hito = 0 ORDER BY p.orden, p.codigo, a.orden, a.codigo") as $a) $out[(int)$a['id']] = $a;
        return $out;
    }

    /**
     * Carga de cada recurso por semana.
     * @return array{semanas: array[], recursos: array[], sobrecargas: array[]} recursos[i]['carga'][s] = ['pct','horas','capacidad','acts'=>[…]]
     */
    public static function carga(Proyecto $proyecto, int $desdeSemana = 0, int $nSemanas = 12): array
    {
        $pid = (int)$proyecto->id;
        $nSemanas = max(1, min(self::MAX_SEMANAS, $nSemanas));
        $cal = new Calendario($pid);
        $lunes = new \DateTime('monday this week');
        $lunes->modify(($desdeSemana >= 0 ? '+' : '') . ($desdeSemana * 7) . ' days');
        $semanas = [];
        for ($i = 0; $i < $nSemanas; $i++) {
            $ini = (clone $lunes)->modify('+' . ($i * 7) . ' days');
            $fin = (clone $ini)->modify('+6 days');
            $semanas[] = ['inicio' => $ini->format('Y-m-d'), 'fin' => $fin->format('Y-m-d'), 'numero' => (int)$ini->format('W'), 'habiles' => $cal->calcularDuracion($ini->format('Y-m-d'), $fin->format('Y-m-d'))];
        }
        $recursos = self::recursos($pid);
        $asig = [];
        foreach (self::asignaciones($pid) as $a) $asig[(int)$a['recurso_id']][] = $a;
        $sobrecargas = [];
        foreach ($recursos as &$r) {
            $r['carga'] = [];
            foreach ($semanas as $si => $s) {
                $pct = 0.0;
                $horas = 0.0;
                $acts = [];
                foreach ($asig[(int)$r['id']] ?? [] as $a) {
                    if ($a['fecha_inicio'] > $s['fin'] || $a['fecha_fin'] < $s['inicio'] || (int)$a['progreso'] >= 100) continue;
                    $d = $cal->calcularDuracion(max($a['fecha_inicio'], $s['inicio']), min($a['fecha_fin'], $s['fin']));
                    if ($d <= 0 || $s['habiles'] <= 0) continue;
                    $p = (int)$a['dedicacion'] * $d / $s['habiles'];
                    $pct += $p;
                    $horas += (int)$a['dedicacion'] / 100 * $d * (float)$r['horas_dia'] * (int)$r['integrantes'];
                    $acts[] = ['id' => (int)$a['actividad_id'], 'codigo' => $a['codigo'], 'nombre' => $a['actividad'], 'dedicacion' => (int)$a['dedicacion']];
                }
                $cap = $s['habiles'] * (float)$r['horas_dia'] * (int)$r['integrantes'];
                $r['carga'][$si] = ['pct' => round($pct, 0), 'horas' => round($horas, 1), 'capacidad' => round($cap, 1), 'acts' => $acts];
                if ($pct > 100.5) $sobrecargas[] = ['recurso_id' => (int)$r['id'], 'recurso' => $r['nombre'], 'semana' => $si, 'inicio' => $s['inicio'], 'pct' => round($pct, 0), 'acts' => $acts];
            }
        }
        return ['semanas' => $semanas, 'recursos' => $recursos, 'sobrecargas' => $sobrecargas];
    }

    /** Sobrecargas en las próximas semanas (para el semáforo de Salud): [recurso, semana, %] */
    public static function sobrecargasProximas(Proyecto $proyecto, int $semanas = 4): array
    {
        if (!self::filas("SELECT 1 FROM actividad_recursos LIMIT 1")) return [];
        return self::carga($proyecto, 0, $semanas)['sobrecargas'];
    }

    /** Nivel de una carga: 'ok' | 'alto' (≥ 85 %) | 'sobre' (> 100 %) | '' (sin carga) */
    public static function nivel(float $pct): string
    {
        return $pct > 100.5 ? 'sobre' : ($pct >= 85 ? 'alto' : ($pct > 0 ? 'ok' : ''));
    }
}
