<?php

namespace Classes;

use Model\Proyecto;

/**
 * Estimaciones completas: subtotal por partida + IVA − retención − amortización del anticipo ± ajuste.
 * Al aprobarse descuentan el saldo del contrato, acumulan el fondo retenido y crean su movimiento en Costos.
 */
class Estimaciones
{
    use AccesoDb;

    public const ESTADOS = ['borrador' => 'Borrador', 'aprobada' => 'Aprobada', 'pagada' => 'Pagada'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM estimaciones LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Datos del contrato; si no hay fila devuelve valores por defecto razonables */
    public static function contrato(int $pid): array
    {
        $c = self::filas("SELECT * FROM costos_contrato WHERE proyecto_id = $pid")[0] ?? [];
        return [
            'monto_contrato' => (float)($c['monto_contrato'] ?? 0),
            'anticipo_monto' => (float)($c['anticipo_monto'] ?? 0),
            'retencion_pct' => (float)($c['retencion_pct'] ?? 5),
            'iva_pct' => (float)($c['iva_pct'] ?? 16),
            'notas' => (string)($c['notas'] ?? ''),
        ];
    }

    public static function guardarContrato(int $pid, array $d): array
    {
        $monto = self::numero($d['monto_contrato'] ?? null) ?? 0;
        $anticipo = self::numero($d['anticipo_monto'] ?? null) ?? 0;
        $ret = self::numero($d['retencion_pct'] ?? null) ?? 5;
        $iva = self::numero($d['iva_pct'] ?? null) ?? 16;
        if ($monto < 0 || $anticipo < 0) return [false, 'Los montos no pueden ser negativos'];
        if ($anticipo > $monto + 0.005) return [false, 'El anticipo no puede ser mayor que el monto del contrato'];
        if ($ret < 0 || $ret > 100 || $iva < 0 || $iva > 100) return [false, 'Los porcentajes deben estar entre 0 y 100'];
        $notas = mb_substr(trim((string)($d['notas'] ?? '')), 0, 5000);
        $v = fn($x) => "'" . self::esc($x) . "'";
        self::db()->query("INSERT INTO costos_contrato (proyecto_id, monto_contrato, anticipo_monto, retencion_pct, iva_pct, notas)
            VALUES ($pid, " . round($monto, 2) . ", " . round($anticipo, 2) . ", " . round($ret, 2) . ", " . round($iva, 2) . ", {$v($notas)})
            ON DUPLICATE KEY UPDATE monto_contrato = VALUES(monto_contrato), anticipo_monto = VALUES(anticipo_monto),
                retencion_pct = VALUES(retencion_pct), iva_pct = VALUES(iva_pct), notas = VALUES(notas)");
        self::auditar($pid, 'guardar_contrato', 'editar', 'Actualizó los datos del contrato: ' . Costos::moneda($monto) . ', anticipo ' . Costos::moneda($anticipo), 0);
        return [true, 'Datos del contrato guardados'];
    }

    public static function lista(int $pid): array
    {
        return self::filas("SELECT e.*, m.estado movimiento_estado,
                (SELECT COUNT(*) FROM estimacion_partidas ep WHERE ep.estimacion_id = e.id) partidas
            FROM estimaciones e LEFT JOIN costos_movimientos m ON m.id = e.movimiento_id
            WHERE e.proyecto_id = $pid ORDER BY e.fecha DESC, e.id DESC");
    }

    public static function estimacion(int $pid, int $id): ?array
    {
        $e = self::filas("SELECT * FROM estimaciones WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
        if (!$e) return null;
        $e['partidas'] = self::detalle($e);
        return $e;
    }

    /** Detalle por partida con el acumulado anterior (solo estimaciones aprobadas o pagadas anteriores) */
    private static function detalle(array $e): array
    {
        $id = (int)$e['id'];
        $pid = (int)$e['proyecto_id'];
        $filas = self::filas("SELECT ep.*, pa.codigo partida_codigo, pa.nombre partida
            FROM estimacion_partidas ep JOIN partidas pa ON pa.id = ep.partida_id
            WHERE ep.estimacion_id = $id ORDER BY pa.orden, pa.codigo");
        foreach ($filas as &$f) {
            $partidaId = (int)$f['partida_id'];
            $f['anterior'] = round((float)(self::filas("SELECT COALESCE(SUM(ep.importe), 0) n
                FROM estimacion_partidas ep JOIN estimaciones e ON e.id = ep.estimacion_id
                WHERE e.proyecto_id = $pid AND e.id < $id AND e.estado IN ('aprobada','pagada') AND ep.partida_id = $partidaId")[0]['n'] ?? 0), 2);
            $f['acumulado'] = round($f['anterior'] + (float)$f['importe'], 2);
        }
        return $filas;
    }

    public static function siguienteNumero(int $pid): string
    {
        $n = (int)(self::filas("SELECT COUNT(*) n FROM estimaciones WHERE proyecto_id = $pid")[0]['n'] ?? 0) + 1;
        return 'EST-' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Crea o actualiza una estimación con su detalle y sincroniza el movimiento de Costos.
     * Devuelve [ok, mensaje, id].
     */
    public static function guardar(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $numero = mb_substr(trim((string)($d['numero'] ?? '')), 0, 40);
        if ($numero === '') return [false, 'Escribe el número de la estimación', 0];
        $fecha = self::fecha($d['fecha'] ?? '') ?: date('Y-m-d');
        $desde = self::fecha($d['periodo_desde'] ?? '');
        $hasta = self::fecha($d['periodo_hasta'] ?? '');
        $estado = is_string($d['estado'] ?? null) && isset(self::ESTADOS[$d['estado']]) ? $d['estado'] : 'borrador';
        $ivaPct = max(0, min(100, self::numero($d['iva_pct'] ?? null) ?? 16));
        $retPct = max(0, min(100, self::numero($d['retencion_pct'] ?? null) ?? 5));
        $antPct = max(0, min(100, self::numero($d['anticipo_pct'] ?? null) ?? 0));
        $ajuste = self::numero($d['ajuste'] ?? null) ?? 0;
        $notas = mb_substr(trim((string)($d['notas'] ?? '')), 0, 5000);

        $lineas = [];
        $subtotal = 0.0;
        foreach ((array)($d['partidas'] ?? []) as $partidaId => $importe) {
            $partidaId = (int)$partidaId;
            $imp = self::numero($importe);
            if ($imp === null || $imp <= 0) continue;
            if (!self::filas("SELECT id FROM partidas WHERE id = $partidaId AND proyecto_id = $pid")) continue;
            $lineas[$partidaId] = round($imp, 2);
            $subtotal += $imp;
        }
        $subtotal = round($subtotal, 2);
        if ($subtotal <= 0) return [false, 'Captura al menos un importe por partida', $id];
        $base = round($subtotal + $ajuste, 2);
        if ($base < 0) return [false, 'El ajuste no puede dejar la estimación en negativo', $id];

        $contrato = self::contrato($pid);
        if ($estado !== 'borrador' && $contrato['monto_contrato'] <= 0) return [false, 'Configura primero el monto del contrato', $id];
        $actual = $id ? self::estimacion($pid, $id) : null;
        if ($id && !$actual) return [false, 'La estimación no pertenece al proyecto', 0];
        $dup = self::filas("SELECT id FROM estimaciones WHERE proyecto_id = $pid AND numero = '" . self::esc($numero) . "' AND id <> $id")[0] ?? null;
        if ($dup) return [false, 'Ya existe una estimación con ese número', $id];

        $calc = self::calcular($subtotal, $ajuste, $ivaPct, $retPct, $antPct);
        // No amortizar más anticipo del que queda pendiente (sumando lo que esta estimación ya tenía)
        $rc = self::resumenContrato($pid);
        $maxAmort = max(0, (float)$rc['anticipo_pendiente'] + (float)($actual['amortizacion'] ?? 0));
        if ($calc['amortizacion'] > $maxAmort) {
            $calc['amortizacion'] = round($maxAmort, 2);
            $calc['neto'] = round($calc['total_bruto'] - $calc['retencion'] - $calc['amortizacion'], 2);
        }
        $iva = $calc['iva']; $retencion = $calc['retencion']; $amortizacion = $calc['amortizacion'];
        $bruto = $calc['total_bruto']; $neto = $calc['neto'];
        $usuario = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
        $v = fn($x) => "'" . self::esc($x) . "'";
        $campos = "numero = {$v($numero)}, fecha = '$fecha', periodo_desde = " . ($desde ? "'$desde'" : 'NULL') . ", periodo_hasta = " . ($hasta ? "'$hasta'" : 'NULL') . ",
            subtotal = $subtotal, iva_pct = " . round($ivaPct, 2) . ", iva = $iva, retencion_pct = " . round($retPct, 2) . ", retencion = $retencion,
            anticipo_pct = " . round($antPct, 2) . ", amortizacion = $amortizacion, ajuste = " . round($ajuste, 2) . ",
            total_bruto = $bruto, neto = $neto, estado = '$estado', notas = {$v($notas)}";

        $db = self::db();
        $nuevo = !$id;
        $db->begin_transaction();
        try {
            if ($id) {
                $db->query("UPDATE estimaciones SET $campos WHERE id = $id AND proyecto_id = $pid");
            } else {
                $db->query("INSERT INTO estimaciones SET proyecto_id = $pid, usuario = {$v($usuario)}, $campos");
                $id = (int)$db->insert_id;
            }
            $db->query("DELETE FROM estimacion_partidas WHERE estimacion_id = $id");
            foreach ($lineas as $partidaId => $imp) {
                $db->query("INSERT INTO estimacion_partidas (estimacion_id, partida_id, importe) VALUES ($id, $partidaId, $imp)");
            }

            // Movimiento de Costos (lo que se presenta al cliente, con IVA)
            $movId = (int)($actual['movimiento_id'] ?? 0);
            $concepto = 'Estimación ' . $numero;
            if ($movId) {
                $db->query("UPDATE costos_movimientos SET fecha = '$fecha', concepto = {$v($concepto)}, referencia = {$v($numero)},
                    monto = $bruto, estado = '$estado', partida_id = NULL WHERE id = $movId AND proyecto_id = $pid");
            } else {
                $db->query("INSERT INTO costos_movimientos (proyecto_id, partida_id, tipo, fecha, concepto, referencia, monto, estado, usuario)
                    VALUES ($pid, NULL, 'estimacion', '$fecha', {$v($concepto)}, {$v($numero)}, $bruto, '$estado', {$v($usuario)})");
                $movId = (int)$db->insert_id;
            }
            $db->query("UPDATE estimaciones SET movimiento_id = $movId WHERE id = $id");
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, 'No se pudo guardar la estimación', $id];
        }
        self::auditar($pid, $nuevo ? 'crear_estimacion' : 'actualizar_estimacion', 'editar', ($nuevo ? 'Creó' : 'Actualizó') . ' la estimación ' . $numero . ' por ' . Costos::moneda($neto) . ' netos', $id);
        return [true, 'Estimación guardada', $id];
    }

    public static function eliminar(int $pid, int $id): array
    {
        $e = self::estimacion($pid, $id);
        if (!$e) return [false, 'La estimación no existe'];
        if ($e['estado'] === 'pagada') return [false, 'No se puede eliminar una estimación pagada'];
        $db = self::db();
        if (!empty($e['movimiento_id'])) $db->query("DELETE FROM costos_movimientos WHERE id = " . (int)$e['movimiento_id'] . " AND proyecto_id = $pid");
        $db->query("DELETE FROM estimaciones WHERE id = $id AND proyecto_id = $pid");
        self::auditar($pid, 'eliminar_estimacion', 'eliminar', 'Eliminó la estimación ' . $e['numero'], $id);
        return [true, 'Estimación eliminada'];
    }

    /**
     * Resumen del contrato: ejecutado, saldo, fondo retenido y anticipo pendiente.
     * @return array{monto_contrato:float,cambios:float,contrato_vigente:float,ejecutado:float,saldo:float,retenido:float,amortizado:float,anticipo_monto:float,anticipo_pendiente:float,bruto:float,neto:float,retencion_pct:float,iva_pct:float}
     */
    public static function resumenContrato(int $pid): array
    {
        $c = self::contrato($pid);
        $sum = self::filas("SELECT COALESCE(SUM(subtotal + ajuste), 0) ejecutado, COALESCE(SUM(retencion), 0) retenido,
                COALESCE(SUM(amortizacion), 0) amortizado, COALESCE(SUM(total_bruto), 0) bruto, COALESCE(SUM(neto), 0) neto
            FROM estimaciones WHERE proyecto_id = $pid AND estado IN ('aprobada','pagada')")[0]
            ?? ['ejecutado' => 0, 'retenido' => 0, 'amortizado' => 0, 'bruto' => 0, 'neto' => 0];
        $cambios = 0.0;
        foreach (Costos::cambiosAutorizados($pid) as $oc) $cambios += (float)$oc['impacto_costo'];
        $contratoVigente = round($c['monto_contrato'] + $cambios, 2);
        return [
            'monto_contrato' => $c['monto_contrato'], 'cambios' => round($cambios, 2), 'contrato_vigente' => $contratoVigente,
            'ejecutado' => round((float)$sum['ejecutado'], 2),
            'saldo' => round($contratoVigente - (float)$sum['ejecutado'], 2),
            'retenido' => round((float)$sum['retenido'], 2), 'amortizado' => round((float)$sum['amortizado'], 2),
            'anticipo_monto' => $c['anticipo_monto'], 'anticipo_pendiente' => round(max(0, $c['anticipo_monto'] - (float)$sum['amortizado']), 2),
            'bruto' => round((float)$sum['bruto'], 2), 'neto' => round((float)$sum['neto'], 2),
            'retencion_pct' => $c['retencion_pct'], 'iva_pct' => $c['iva_pct'],
        ];
    }

    /**
     * Cálculo de una estimación: base = subtotal + ajuste; IVA y retención sobre la base;
     * amortización del anticipo sobre la base; neto = bruto − retención − amortización.
     * @return array{base:float,iva:float,retencion:float,amortizacion:float,total_bruto:float,neto:float}
     */
    public static function calcular(float $subtotal, float $ajuste, float $ivaPct, float $retPct, float $antPct): array
    {
        $base = round($subtotal + $ajuste, 2);
        $iva = round($base * $ivaPct / 100, 2);
        $retencion = round($base * $retPct / 100, 2);
        $amortizacion = round($base * $antPct / 100, 2);
        $bruto = round($base + $iva, 2);
        return ['base' => $base, 'iva' => $iva, 'retencion' => $retencion, 'amortizacion' => $amortizacion,
            'total_bruto' => $bruto, 'neto' => round($bruto - $retencion - $amortizacion, 2)];
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

    private static function auditar(int $pid, string $evento, string $accion, string $resumen, int $id): void
    {
        try {
            \Classes\Auditoria::registrar($pid, 'costos', $evento, $accion, $resumen, null, null, $id, 'Estimación');
        } catch (\Throwable $e) {
        }
    }
}
