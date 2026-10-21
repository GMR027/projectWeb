<?php

namespace Classes;

use Model\Proyecto;

/**
 * Curva S del programa: avance acumulado programado contra real, por semana.
 * - Peso de cada actividad = su duración en días (los hitos pesan 0).
 * - Programado: cada actividad reparte su peso de forma lineal entre sus días hábiles (calendario del proyecto).
 * - Real: % de avance de cada actividad en cada fecha, tomado del historial que se anota al cambiar el avance (actividad_avance).
 *   Una semana capturada a mano (curva_s_manual) sustituye al cálculo.
 */
class CurvaS
{
    use AccesoDb;

    public const MAX_SEMANAS = 150;

    /** Actividades del proyecto con su peso */
    private static function actividades(int $proyectoId): array
    {
        $r = self::db()->query("SELECT a.id, a.fecha_inicio, a.fecha_fin, a.duracion_dias, a.hito FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $proyectoId");
        $out = [];
        while ($f = $r->fetch_assoc()) {
            $f['peso'] = (int)$f['hito'] ? 0 : max(0, (int)$f['duracion_dias']);
            $out[(int)$f['id']] = $f;
        }
        // Con el catálogo de conceptos completo (importe en todas las actividades con duración) el avance se pesa por importe
        try {
            $imp = [];
            $q = self::db()->query("SELECT actividad_id, importe FROM actividad_cantidades WHERE proyecto_id = $proyectoId AND importe > 0");
            while ($q && ($g = $q->fetch_assoc())) $imp[(int)$g['actividad_id']] = (float)$g['importe'];
            $pesadas = array_filter($out, fn($a) => $a['peso'] > 0);
            if ($pesadas && !array_diff_key($pesadas, $imp)) foreach ($out as $id => $a) $out[$id]['peso'] = $a['peso'] > 0 ? $imp[$id] : 0;
        } catch (\Throwable $e) {   // migración pendiente
        }
        return $out;
    }

    /** [actividad_id => [[fecha, progreso], ...] ordenado por fecha] */
    private static function historial(int $proyectoId): array
    {
        $r = self::db()->query("SELECT h.actividad_id, h.fecha, h.progreso FROM actividad_avance h JOIN actividades a ON a.id = h.actividad_id JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $proyectoId ORDER BY h.fecha");
        $out = [];
        while ($f = $r->fetch_assoc()) $out[(int)$f['actividad_id']][] = [$f['fecha'], (int)$f['progreso']];
        return $out;
    }

    public static function manual(int $proyectoId): array
    {
        $out = [];
        try {
            $r = self::db()->query("SELECT fecha, avance FROM curva_s_manual WHERE proyecto_id = $proyectoId");
            while ($f = $r->fetch_assoc()) $out[$f['fecha']] = (float)$f['avance'];
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public static function guardarManual(int $proyectoId, array $valores): void
    {
        $db = self::db();
        $db->query("DELETE FROM curva_s_manual WHERE proyecto_id = $proyectoId");
        foreach ($valores as $fecha => $v) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha) || $v === '' || !is_numeric($v)) continue;
            $v = max(0, min(100, round((float)$v, 2)));
            $db->query("INSERT INTO curva_s_manual (proyecto_id, fecha, avance) VALUES ($proyectoId, '" . $db->real_escape_string($fecha) . "', $v)");
        }
    }

    /** Fracción (0-1) de la actividad hecha según el programa a la fecha $d */
    private static function fraccionPlan(array $a, string $d, Calendario $cal, array &$cache): float
    {
        if ($d < $a['fecha_inicio']) return 0.0;
        if ($d >= $a['fecha_fin']) return 1.0;
        $id = $a['id'];
        $total = $cache[$id] ??= $cal->calcularDuracion($a['fecha_inicio'], $a['fecha_fin']);
        if ($total <= 0) return 0.0;
        return min(1.0, $cal->calcularDuracion($a['fecha_inicio'], $d) / $total);
    }

    private static function avanceA(array $h, string $d): int
    {
        $v = 0;
        foreach ($h as [$f, $p]) {
            if ($f > $d) break;
            $v = $p;
        }
        return $v;
    }

    /** @return array{periodos: array, resumen: array, hayDatos: bool} */
    public static function calcular(Proyecto $proyecto, ?array $alterno = null): array
    {
        $pid = (int)$proyecto->id;
        $acts = self::actividades($pid);
        // $alterno [id => [inicio, fin]]: otro programa (p. ej. una propuesta del Programador) calculado en las mismas semanas bajo la clave 'alt'
        $alt = null;
        if ($alterno) {
            $alt = $acts;
            foreach ($alterno as $id => [$i, $f]) if (isset($alt[$id])) { $alt[$id]['fecha_inicio'] = $i; $alt[$id]['fecha_fin'] = $f; }
        }
        $total = array_sum(array_column($acts, 'peso'));
        if (!$acts || $total <= 0) return ['periodos' => [], 'resumen' => [], 'hayDatos' => false];

        $inicio = min(array_merge([$proyecto->fecha_inicio], array_column($acts, 'fecha_inicio'), $alt ? array_column($alt, 'fecha_inicio') : []));
        $fin = max(array_merge([$proyecto->fecha_fin], array_column($acts, 'fecha_fin'), $alt ? array_column($alt, 'fecha_fin') : []));
        $cal = new Calendario($pid);
        $hist = self::historial($pid);
        $manual = self::manual($pid);
        $hoy = date('Y-m-d');
        $cache = [];
        $cacheAlt = [];
        $periodos = [];
        $f = new \DateTime($inicio);
        for ($i = 0; $i < self::MAX_SEMANAS && $f->format('Y-m-d') <= $fin; $i++) {
            $ini = $f->format('Y-m-d');
            $finSem = (clone $f)->modify('+6 days')->format('Y-m-d');
            $plan = 0.0;
            foreach ($acts as $a) {
                if ($a['peso'] > 0) $plan += $a['peso'] * self::fraccionPlan($a, $finSem, $cal, $cache);
            }
            $planAlt = null;
            if ($alt) {
                $planAlt = 0.0;
                foreach ($alt as $a) {
                    if ($a['peso'] > 0) $planAlt += $a['peso'] * self::fraccionPlan($a, $finSem, $cal, $cacheAlt);
                }
            }
            $real = null;
            $esManual = false;
            if (isset($manual[$ini])) {
                $real = $manual[$ini];
                $esManual = true;
            } elseif ($ini <= $hoy) {
                $corte = min($finSem, $hoy);
                $suma = 0.0;
                foreach ($acts as $id => $a) {
                    if ($a['peso'] > 0) $suma += $a['peso'] * self::avanceA($hist[$id] ?? [], $corte) / 100;
                }
                $real = round($suma / $total * 100, 2);
            }
            $periodos[] = ['inicio' => $ini, 'fin' => $finSem, 'plan' => round($plan / $total * 100, 2), 'real' => $real, 'manual' => $esManual] + ($alt ? ['alt' => round($planAlt / $total * 100, 2)] : []);
            $f->modify('+7 days');
        }

        // Resumen a la semana en curso (o la última con dato real)
        $actual = null;
        foreach ($periodos as $p) {
            if ($p['inicio'] <= $hoy && $p['real'] !== null) $actual = $p;
        }
        $resumen = [];
        if ($actual) {
            $resumen = ['plan' => $actual['plan'], 'real' => $actual['real'], 'desvio' => round($actual['real'] - $actual['plan'], 2),
                'spi' => $actual['plan'] > 0 ? round($actual['real'] / $actual['plan'], 2) : null, 'semana' => $actual['inicio']];
        }
        return ['periodos' => $periodos, 'resumen' => $resumen, 'hayDatos' => true];
    }

    /** Primera fecha con historial anotado (para avisar desde cuándo hay datos reales automáticos) */
    public static function primerHistorial(int $proyectoId): ?string
    {
        $r = self::db()->query("SELECT MIN(h.fecha) FROM actividad_avance h JOIN actividades a ON a.id = h.actividad_id JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $proyectoId");
        $f = $r ? $r->fetch_row() : null;
        return $f && $f[0] ? $f[0] : null;
    }
}
