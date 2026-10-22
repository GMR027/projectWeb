<?php

namespace Classes;

use Model\Proyecto;

/**
 * Costos y presupuesto del proyecto.
 *  - Presupuesto por partida (`costos_partidas`), ajustado con las órdenes de cambio autorizadas (impacto_costo).
 *  - Movimientos (`costos_movimientos`): costo real (gastos) y estimaciones (lo que se cobra al cliente).
 *  - Valor ganado: PV = presupuesto × avance programado, EV = presupuesto × avance real (ambos pesados por la duración de las
 *    actividades de la partida, igual que la Curva S), AC = costo real. CPI = EV/AC, SPI = EV/PV.
 *  - La curva de costos por semana sale de la Curva S (`CurvaS::calcular`): PV = plan % × presupuesto total, EV = real % × presupuesto.
 */
class Costos
{
    use AccesoDb;

    public const TIPOS = ['real' => 'Costo real', 'estimacion' => 'Estimación'];
    public const ESTADOS_ESTIMACION = ['borrador' => 'Borrador', 'aprobada' => 'Aprobada', 'pagada' => 'Pagada'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query("SELECT 1 FROM costos_partidas LIMIT 1");
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ---------- escritura ----------

    public static function guardarPresupuesto(int $pid, int $partidaId, float $monto): bool
    {
        if (!self::filas("SELECT id FROM partidas WHERE id = $partidaId AND proyecto_id = $pid")) return false;
        $monto = max(0, round($monto, 2));
        self::db()->query("INSERT INTO costos_partidas (partida_id, proyecto_id, presupuesto) VALUES ($partidaId, $pid, $monto) ON DUPLICATE KEY UPDATE presupuesto = VALUES(presupuesto)");
        return true;
    }

    /** @return string|null mensaje de error */
    public static function guardarMovimiento(int $pid, array $d, string $usuario): ?string
    {
        $tipo = isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'real';
        $fecha = \DateTime::createFromFormat('Y-m-d', (string)($d['fecha'] ?? ''));
        if (!$fecha || $fecha->format('Y-m-d') !== $d['fecha']) return 'La fecha no es válida';
        $monto = is_numeric($d['monto'] ?? null) ? round((float)$d['monto'], 2) : null;
        if ($monto === null || $monto == 0.0) return 'El monto es obligatorio';
        $concepto = mb_substr(trim((string)($d['concepto'] ?? '')), 0, 255);
        if ($concepto === '') return 'El concepto es obligatorio';
        $partida = (int)($d['partida_id'] ?? 0);
        if ($partida && !self::filas("SELECT id FROM partidas WHERE id = $partida AND proyecto_id = $pid")) $partida = 0;
        $oc = (int)($d['orden_cambio_id'] ?? 0);
        if ($oc && !self::filas("SELECT id FROM ordenes_cambio WHERE id = $oc AND proyecto_id = $pid")) $oc = 0;
        $estado = isset(self::ESTADOS_ESTIMACION[$d['estado'] ?? '']) ? $d['estado'] : 'aprobada';
        $id = (int)($d['id'] ?? 0);
        if ($id && !self::filas("SELECT id FROM costos_movimientos WHERE id = $id AND proyecto_id = $pid")) return 'El movimiento no existe';
        $v = fn($x) => "'" . self::esc($x) . "'";
        $campos = "partida_id = " . ($partida ?: 'NULL') . ", tipo = {$v($tipo)}, fecha = {$v($d['fecha'])}, concepto = {$v($concepto)}, referencia = {$v(mb_substr(trim((string)($d['referencia'] ?? '')), 0, 100))}, monto = $monto, estado = {$v($estado)}, orden_cambio_id = " . ($oc ?: 'NULL');
        if ($id) self::db()->query("UPDATE costos_movimientos SET $campos WHERE id = $id");
        else self::db()->query("INSERT INTO costos_movimientos SET proyecto_id = $pid, usuario = {$v(mb_substr($usuario, 0, 150))}, $campos");
        return null;
    }

    public static function eliminarMovimiento(int $pid, int $id): void
    {
        self::db()->query("DELETE FROM costos_movimientos WHERE id = $id AND proyecto_id = $pid");
    }

    // ---------- lectura ----------

    public static function movimientos(int $pid, string $tipo = ''): array
    {
        $w = isset(self::TIPOS[$tipo]) ? " AND m.tipo = '$tipo'" : '';
        return self::filas("SELECT m.*, p.codigo partida_codigo, p.nombre partida, oc.numero oc_numero FROM costos_movimientos m
            LEFT JOIN partidas p ON p.id = m.partida_id LEFT JOIN ordenes_cambio oc ON oc.id = m.orden_cambio_id
            WHERE m.proyecto_id = $pid$w ORDER BY m.fecha DESC, m.id DESC");
    }

    /** Importe por orden de cambio autorizada (solo las que tienen costo) */
    public static function cambiosAutorizados(int $pid): array
    {
        $est = "'" . implode("','", \Model\OrdenCambio::AUTORIZADAS) . "'";
        return self::filas("SELECT oc.id, oc.numero, oc.titulo, oc.impacto_costo, oc.estado, p.id partida_id FROM ordenes_cambio oc
            LEFT JOIN actividades a ON a.id = oc.actividad_id LEFT JOIN partidas p ON p.id = a.partida_id
            WHERE oc.proyecto_id = $pid AND oc.estado IN ($est) AND oc.impacto_costo <> 0 ORDER BY oc.fecha, oc.id");
    }

    /** Avance programado y real (0-1) de cada partida a la fecha, pesado por la duración de sus actividades */
    private static function avancePartidas(Proyecto $proyecto, string $fecha): array
    {
        $cal = new Calendario((int)$proyecto->id);
        $out = [];
        $filas = self::filas("SELECT a.id, a.partida_id, a.fecha_inicio, a.fecha_fin, a.duracion_dias, a.hito, a.progreso FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = " . (int)$proyecto->id);
        foreach ($filas as $a) {
            $peso = (int)$a['hito'] ? 0 : max(0, (int)$a['duracion_dias']);
            $pid = (int)$a['partida_id'];
            $o = &$out[$pid];
            $o ??= ['peso' => 0.0, 'plan' => 0.0, 'real' => 0.0];
            if ($peso <= 0) { unset($o); continue; }
            if ($fecha >= $a['fecha_fin']) $f = 1.0;
            elseif ($fecha < $a['fecha_inicio']) $f = 0.0;
            else {
                $tot = $cal->calcularDuracion($a['fecha_inicio'], $a['fecha_fin']);
                $f = $tot > 0 ? min(1.0, $cal->calcularDuracion($a['fecha_inicio'], $fecha) / $tot) : 0.0;
            }
            $o['peso'] += $peso;
            $o['plan'] += $peso * $f;
            $o['real'] += $peso * min(100, (int)$a['progreso']) / 100;
            unset($o);
        }
        foreach ($out as &$o) {
            $o['plan'] = $o['peso'] > 0 ? $o['plan'] / $o['peso'] : 0.0;
            $o['real'] = $o['peso'] > 0 ? $o['real'] / $o['peso'] : 0.0;
        }
        return $out;
    }

    /**
     * Resumen completo: por partida (presupuesto, cambios, PV, EV, AC, estimado) y totales con índices.
     * @return array{partidas: array[], total: array, sinPartida: array}
     */
    public static function resumen(Proyecto $proyecto, ?string $fecha = null): array
    {
        $pid = (int)$proyecto->id;
        $fecha ??= date('Y-m-d');
        $av = self::avancePartidas($proyecto, $fecha);
        $pres = array_column(self::filas("SELECT partida_id, presupuesto FROM costos_partidas WHERE proyecto_id = $pid"), 'presupuesto', 'partida_id');
        $cambios = [];
        $cambiosGenerales = 0.0;
        foreach (self::cambiosAutorizados($pid) as $c) {
            if ($c['partida_id']) $cambios[(int)$c['partida_id']] = ($cambios[(int)$c['partida_id']] ?? 0) + (float)$c['impacto_costo'];
            else $cambiosGenerales += (float)$c['impacto_costo'];
        }
        $ac = $es = [];
        $sinReal = $sinEst = 0.0;
        foreach (self::filas("SELECT partida_id, tipo, estado, SUM(monto) m FROM costos_movimientos WHERE proyecto_id = $pid GROUP BY partida_id, tipo, estado") as $m) {
            $p = (int)$m['partida_id'];
            if ($m['tipo'] === 'real') { $p ? $ac[$p] = ($ac[$p] ?? 0) + (float)$m['m'] : $sinReal += (float)$m['m']; }
            elseif ($m['estado'] !== 'borrador') { $p ? $es[$p] = ($es[$p] ?? 0) + (float)$m['m'] : $sinEst += (float)$m['m']; }
        }
        $partidas = [];
        $t = ['presupuesto' => 0.0, 'cambios' => 0.0, 'bac' => 0.0, 'pv' => 0.0, 'ev' => 0.0, 'ac' => 0.0, 'estimado' => 0.0];
        foreach (self::filas("SELECT id, codigo, nombre, color_bg FROM partidas WHERE proyecto_id = $pid ORDER BY orden, codigo") as $p) {
            $id = (int)$p['id'];
            $presupuesto = (float)($pres[$id] ?? 0);
            $camb = (float)($cambios[$id] ?? 0);
            $bac = $presupuesto + $camb;
            $a = $av[$id] ?? ['plan' => 0.0, 'real' => 0.0];
            $f = ['id' => $id, 'codigo' => $p['codigo'], 'nombre' => $p['nombre'], 'color' => $p['color_bg'], 'presupuesto' => $presupuesto, 'cambios' => $camb, 'bac' => $bac,
                'plan_pct' => round($a['plan'] * 100, 1), 'real_pct' => round($a['real'] * 100, 1),
                'pv' => round($bac * $a['plan'], 2), 'ev' => round($bac * $a['real'], 2), 'ac' => round((float)($ac[$id] ?? 0), 2), 'estimado' => round((float)($es[$id] ?? 0), 2)];
            $partidas[] = $f;
            foreach (['presupuesto', 'cambios', 'bac', 'pv', 'ev', 'ac', 'estimado'] as $k) $t[$k] += $f[$k];
        }
        // Lo que no se asignó a una partida cuenta en los totales: costo real, estimaciones y cambios sin partida
        $t['cambios'] += $cambiosGenerales;
        $t['bac'] += $cambiosGenerales;
        $t['ac'] += $sinReal;
        $t['estimado'] += $sinEst;
        $t = array_map(fn($x) => round($x, 2), $t);
        $t += self::indices($t['pv'], $t['ev'], $t['ac'], $t['bac']);
        $t['por_cobrar'] = round($t['ev'] - $t['estimado'], 2);
        foreach ($partidas as &$f) $f += self::indices($f['pv'], $f['ev'], $f['ac'], $f['bac']);
        return ['partidas' => $partidas, 'total' => $t, 'sinPartida' => ['cambios' => round($cambiosGenerales, 2), 'real' => round($sinReal, 2), 'estimado' => round($sinEst, 2)]];
    }

    /** CPI, SPI, variaciones y proyecciones al final (EAC = BAC / CPI) */
    public static function indices(float $pv, float $ev, float $ac, float $bac): array
    {
        $cpi = $ac > 0 ? round($ev / $ac, 2) : null;
        $spi = $pv > 0 ? round($ev / $pv, 2) : null;
        $eac = $cpi ? round($bac / $cpi, 2) : null;
        return ['cpi' => $cpi, 'spi' => $spi, 'cv' => round($ev - $ac, 2), 'sv' => round($ev - $pv, 2), 'eac' => $eac, 'vac' => $eac !== null ? round($bac - $eac, 2) : null];
    }

    /**
     * Curva de costos por semana, a partir de la Curva S: PV y EV acumulados en dinero y AC/estimado acumulados de los movimientos.
     * @return array[] ['inicio','fin','pv','ev'(null en el futuro),'ac','estimado']
     */
    public static function curva(Proyecto $proyecto, ?float $bac = null): array
    {
        $pid = (int)$proyecto->id;
        $bac ??= self::resumen($proyecto)['total']['bac'];
        $cs = CurvaS::calcular($proyecto);
        if (!$cs['hayDatos'] || $bac <= 0) return [];
        $mov = self::filas("SELECT tipo, estado, fecha, monto FROM costos_movimientos WHERE proyecto_id = $pid ORDER BY fecha");
        $hoy = date('Y-m-d');
        $out = [];
        foreach ($cs['periodos'] as $p) {
            $ac = $es = 0.0;
            foreach ($mov as $m) {
                if ($m['fecha'] > $p['fin']) break;
                if ($m['tipo'] === 'real') $ac += (float)$m['monto'];
                elseif ($m['estado'] !== 'borrador') $es += (float)$m['monto'];
            }
            $pasada = $p['inicio'] <= $hoy;
            $out[] = ['inicio' => $p['inicio'], 'fin' => $p['fin'], 'pv' => round($bac * $p['plan'] / 100, 2),
                'ev' => $p['real'] !== null ? round($bac * $p['real'] / 100, 2) : null,
                'ac' => $pasada ? round($ac, 2) : null, 'estimado' => $pasada ? round($es, 2) : null];
        }
        return $out;
    }

    /** Semáforo del costo para Salud: CPI < 0.9 crítico, < 1 aviso */
    public static function nivel(?float $cpi): string
    {
        return $cpi === null ? 'ok' : ($cpi < 0.9 ? 'critico' : ($cpi < 1 ? 'aviso' : 'ok'));
    }

    public static function moneda(float $x): string
    {
        return ($x < 0 ? '-' : '') . '$' . number_format(abs($x), 2);
    }
}
