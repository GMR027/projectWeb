<?php

namespace Classes;

use Model\Proyecto;

/**
 * Flujo de caja semanal: cobros de estimaciones, pagos de compromisos, costo real
 * y gastos recurrentes. Proyecta 12 semanas y calcula el costo final con lo gastado,
 * lo comprometido y el trabajo restante. No concilia banco: es una proyección.
 */
class FlujoCaja
{
    use AccesoDb;

    public const FRECUENCIAS = ['semanal' => 'Semanal', 'quincenal' => 'Quincenal', 'mensual' => 'Mensual'];
    public const TIPOS = ['ingreso' => 'Ingreso', 'egreso' => 'Egreso'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM flujo_conceptos LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Proyección por semana. @return array{semanas:array,eventos:array,totales:array}
     */
    public static function proyectar(Proyecto $p, string $desde, int $semanas = 12): array
    {
        $pid = (int)$p->id;
        $semanas = max(1, min(52, $semanas));
        $d = new \DateTime($desde ?: 'today');
        $d->modify('monday this week');
        $inicio = $d->format('Y-m-d');
        $fin = (clone $d)->modify('+' . ($semanas * 7 - 1) . ' days')->format('Y-m-d');
        $semanasArr = [];
        for ($i = 0; $i < $semanas; $i++) {
            $ini = (clone $d)->modify('+' . ($i * 7) . ' days');
            $f = (clone $ini)->modify('+6 days');
            $semanasArr[] = ['inicio' => $ini->format('Y-m-d'), 'fin' => $f->format('Y-m-d'), 'numero' => (int)$ini->format('W'),
                'ingresos' => 0.0, 'egresos' => 0.0, 'neto' => 0.0, 'acumulado' => 0.0];
        }
        $eventos = [];
        $agregar = function (string $fecha, string $tipo, string $concepto, float $monto, string $origen) use (&$eventos, &$semanasArr, $inicio, $fin) {
            if ($fecha < $inicio || $fecha > $fin) return;
            $idx = null;
            foreach ($semanasArr as $i => $s) if ($fecha >= $s['inicio'] && $fecha <= $s['fin']) { $idx = $i; break; }
            if ($idx === null) return;
            $eventos[] = ['fecha' => $fecha, 'semana' => $idx, 'tipo' => $tipo, 'concepto' => $concepto, 'monto' => round($monto, 2), 'origen' => $origen];
            if ($tipo === 'ingreso') $semanasArr[$idx]['ingresos'] += $monto; else $semanasArr[$idx]['egresos'] += $monto;
        };

        // Cobros de estimaciones al cliente: real, estimada o fecha + días de cobro
        $dias = (int)(self::filas("SELECT dias_cobro FROM costos_contrato WHERE proyecto_id = $pid")[0]['dias_cobro'] ?? 30);
        foreach (self::filas("SELECT numero, fecha, neto, estado, fecha_cobro_estimada, fecha_cobro_real FROM estimaciones
            WHERE proyecto_id = $pid AND estado IN ('aprobada','pagada') ORDER BY fecha") as $e) {
            $fecha = $e['fecha_cobro_real'] ?: ($e['estado'] === 'pagada' ? $e['fecha']
                : ($e['fecha_cobro_estimada'] ?: date('Y-m-d', strtotime($e['fecha'] . ' +' . $dias . ' days'))));
            $agregar($fecha, 'ingreso', 'Estimación ' . $e['numero'], (float)$e['neto'], 'estimacion');
        }

        // Pagos pendientes de compromisos (saldo no facturado)
        foreach (self::filas("SELECT c.numero, c.proveedor, c.fecha, c.entrega, c.fecha_pago_programada, c.monto,
                COALESCE((SELECT SUM(monto) FROM compromiso_aplicaciones a WHERE a.compromiso_id = c.id), 0) aplicado
            FROM compromisos c WHERE c.proyecto_id = $pid AND c.estado IN ('vigente','parcial') ORDER BY c.fecha") as $c) {
            $saldo = round(max(0, (float)$c['monto'] - (float)$c['aplicado']), 2);
            if ($saldo <= 0) continue;
            $fecha = $c['fecha_pago_programada'] ?: ($c['entrega'] ?: $c['fecha']);
            $agregar($fecha, 'egreso', 'Compromiso ' . ($c['numero'] ?: $c['proveedor']), $saldo, 'compromiso');
        }

        // Costo real ya registrado (incluye facturas aplicadas a compromisos)
        foreach (self::filas("SELECT fecha, concepto, monto FROM costos_movimientos
            WHERE proyecto_id = $pid AND tipo = 'real' ORDER BY fecha") as $m) {
            $agregar($m['fecha'], 'egreso', $m['concepto'], (float)$m['monto'], 'real');
        }

        // Gastos e ingresos recurrentes
        foreach (self::conceptos($pid) as $c) {
            foreach (self::ocurrencias($c, $inicio, $fin) as $fecha) {
                $agregar($fecha, $c['tipo'], $c['nombre'], (float)$c['monto'], 'recurrente');
            }
        }

        // Ordenar por fecha y calcular acumulado
        usort($eventos, fn($a, $b) => [$a['fecha'], $a['tipo']] <=> [$b['fecha'], $b['tipo']]);
        $acu = 0.0;
        foreach ($semanasArr as &$s) {
            $s['ingresos'] = round($s['ingresos'], 2);
            $s['egresos'] = round($s['egresos'], 2);
            $s['neto'] = round($s['ingresos'] - $s['egresos'], 2);
            $acu += $s['neto'];
            $s['acumulado'] = round($acu, 2);
        }
        return ['semanas' => $semanasArr, 'eventos' => $eventos,
            'totales' => ['ingresos' => round(array_sum(array_column($semanasArr, 'ingresos')), 2),
                'egresos' => round(array_sum(array_column($semanasArr, 'egresos')), 2),
                'neto' => round($acu, 2), 'minimo' => round(min(array_merge([0], array_column($semanasArr, 'acumulado'))), 2)]];
    }

    /** Costo final: real + comprometido + trabajo restante no cubierto por compromisos */
    public static function costoFinal(Proyecto $p): array
    {
        $r = Costos::resumen($p)['total'];
        $bac = (float)$r['bac']; $ev = (float)$r['ev']; $ac = (float)$r['ac']; $comp = (float)$r['comprometido'];
        $restante = max(0, $bac - $ev - $comp);
        $final = round($ac + $comp + $restante, 2);
        return ['ac' => $ac, 'comprometido' => $comp, 'restante' => round($restante, 2), 'final' => $final,
            'bac' => $bac, 'variacion' => round($final - $bac, 2), 'eac' => $r['eac'] ?? null];
    }

    public static function conceptos(int $pid): array
    {
        return self::filas("SELECT * FROM flujo_conceptos WHERE proyecto_id = $pid AND activo = 1 ORDER BY tipo, nombre");
    }

    public static function guardarConcepto(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $nombre = mb_substr(trim((string)($d['nombre'] ?? '')), 0, 200);
        if ($nombre === '') return [false, 'Escribe el nombre del concepto'];
        $tipo = isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'egreso';
        $frecuencia = isset(self::FRECUENCIAS[$d['frecuencia'] ?? '']) ? $d['frecuencia'] : 'mensual';
        $monto = self::numero($d['monto'] ?? null);
        if ($monto === null || $monto <= 0) return [false, 'El monto debe ser mayor que cero'];
        $inicio = self::fecha($d['fecha_inicio'] ?? '') ?: date('Y-m-d');
        $fin = self::fecha($d['fecha_fin'] ?? '');
        $v = fn($x) => "'" . self::esc($x) . "'";
        if ($id) {
            if (!self::filas("SELECT id FROM flujo_conceptos WHERE id = $id AND proyecto_id = $pid")) return [false, 'El concepto no existe'];
            self::db()->query("UPDATE flujo_conceptos SET nombre = {$v($nombre)}, tipo = '$tipo', monto = " . round($monto, 2) . ",
                frecuencia = '$frecuencia', fecha_inicio = '$inicio', fecha_fin = " . ($fin ? "'$fin'" : 'NULL') . ", notas = {$v(mb_substr((string)($d['notas'] ?? ''), 0, 500))}
                WHERE id = $id AND proyecto_id = $pid");
        } else {
            self::db()->query("INSERT INTO flujo_conceptos (proyecto_id, nombre, tipo, monto, frecuencia, fecha_inicio, fecha_fin, notas)
                VALUES ($pid, {$v($nombre)}, '$tipo', " . round($monto, 2) . ", '$frecuencia', '$inicio', " . ($fin ? "'$fin'" : 'NULL') . ", {$v(mb_substr((string)($d['notas'] ?? ''), 0, 500))})");
            $id = (int)self::db()->insert_id;
        }
        return [true, 'Concepto guardado'];
    }

    public static function eliminarConcepto(int $pid, int $id): bool
    {
        self::db()->query("DELETE FROM flujo_conceptos WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    /** Fechas de ocurrencia de un concepto recurrente dentro de la ventana */
    private static function ocurrencias(array $c, string $desde, string $hasta): array
    {
        $f = new \DateTime($c['fecha_inicio']);
        $fin = $c['fecha_fin'] ? new \DateTime($c['fecha_fin']) : null;
        $out = [];
        $paso = $c['frecuencia'] === 'semanal' ? '+7 days' : ($c['frecuencia'] === 'quincenal' ? '+14 days' : '+1 month');
        $guard = 0;
        while ($f->format('Y-m-d') <= $hasta && $guard++ < 500) {
            $d = $f->format('Y-m-d');
            if ($d >= $desde && (!$fin || $f <= $fin)) $out[] = $d;
            if ($fin && $f > $fin) break;
            $f->modify($paso);
        }
        return $out;
    }

    private static function fecha($v): ?string
    {
        $v = trim((string)$v);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return null;
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $v : null;
    }

    private static function numero($v): ?float
    {
        if ($v === null || $v === '') return null;
        $t = str_replace([',', '$', ' '], '', (string)$v);
        return is_numeric($t) ? (float)$t : null;
    }
}
