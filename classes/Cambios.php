<?php

namespace Classes;

use Model\OrdenCambio;

/** Seguimiento de órdenes de cambio: resumen, retiros pendientes, qué actividades justifica cada orden y movimiento de un día */
class Cambios
{
    private static function filas(string $sql): array
    {
        try {
            $r = $GLOBALS['db']->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {   // migración pendiente
            return [];
        }
    }

    /** ¿Ya existen las tablas? (la migración puede estar pendiente) */
    public static function disponible(): bool
    {
        try {
            return (bool)$GLOBALS['db']->query('SELECT 1 FROM ordenes_cambio LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Indicadores de la pantalla principal */
    public static function resumen(int $pid): array
    {
        $porEstado = array_fill_keys(array_keys(OrdenCambio::ESTADOS), 0);
        foreach (self::filas("SELECT estado, COUNT(*) n FROM ordenes_cambio WHERE proyecto_id = $pid GROUP BY estado") as $f) $porEstado[$f['estado']] = (int)$f['n'];
        $aut = "'" . implode("','", OrdenCambio::AUTORIZADAS) . "'";
        $imp = self::filas("SELECT COALESCE(SUM(impacto_costo),0) costo, COALESCE(SUM(impacto_dias),0) dias FROM ordenes_cambio WHERE proyecto_id = $pid AND estado IN ($aut)")[0] ?? ['costo' => 0, 'dias' => 0];
        $pend = self::filas("SELECT COALESCE(SUM(impacto_costo),0) costo, COALESCE(SUM(impacto_dias),0) dias FROM ordenes_cambio WHERE proyecto_id = $pid AND estado = 'solicitada'")[0] ?? ['costo' => 0, 'dias' => 0];
        $retiros = self::retirosPendientes($pid);
        return ['por_estado' => $porEstado, 'total' => array_sum($porEstado), 'costo_autorizado' => (float)$imp['costo'], 'dias_autorizados' => (int)$imp['dias'],
            'costo_por_autorizar' => (float)$pend['costo'], 'dias_por_autorizar' => (int)$pend['dias'],
            'retiros_pendientes' => count($retiros), 'retiros_vencidos' => count(array_filter($retiros, fn($r) => $r['vencido']))];
    }

    /** Renglones temporales ya instalados que siguen sin retirarse (lo más viejo primero) */
    public static function retirosPendientes(int $pid): array
    {
        $hoy = date('Y-m-d');
        $out = [];
        foreach (self::filas("SELECT p.*, o.numero, o.titulo orden_titulo FROM cambio_partidas p JOIN ordenes_cambio o ON o.id = p.orden_id
                WHERE p.proyecto_id = $pid AND p.requiere_retiro = 1 AND p.estado = 'ejecutado' ORDER BY COALESCE(p.fecha_ejecucion, p.creado)") as $f) {
            $desde = $f['fecha_ejecucion'] ?: substr((string)$f['creado'], 0, 10);
            $f['dias_instalado'] = max(0, (int)floor((strtotime($hoy) - strtotime($desde)) / 86400));
            $f['vencido'] = $f['fecha_retiro_prevista'] ? $f['fecha_retiro_prevista'] < $hoy : false;
            $f['sin_programar'] = !$f['fecha_retiro_prevista'];
            $out[] = $f;
        }
        return $out;
    }

    /** [actividad_id => lista de ['orden' => número, 'estado' => estado]] de las órdenes vigentes (no rechazadas) que tocan cada actividad */
    public static function justificantes(int $pid, bool $soloAutorizadas = false): array
    {
        $est = $soloAutorizadas ? "'" . implode("','", OrdenCambio::AUTORIZADAS) . "'" : "'solicitada','aprobada','ejecutada','cerrada'";
        $out = [];
        $nuevo = function ($id, $f) use (&$out) {
            if (!$id) return;
            foreach ($out[(int)$id] ?? [] as $x) if ($x['orden'] === $f['numero']) return;
            $out[(int)$id][] = ['orden' => $f['numero'] !== '' ? $f['numero'] : '#' . $f['oid'], 'estado' => $f['estado']];
        };
        foreach (self::filas("SELECT id oid, numero, estado, actividad_id FROM ordenes_cambio WHERE proyecto_id = $pid AND estado IN ($est) AND actividad_id IS NOT NULL") as $f) $nuevo($f['actividad_id'], $f);
        foreach (self::filas("SELECT o.id oid, o.numero, o.estado, p.actividad_id FROM cambio_partidas p JOIN ordenes_cambio o ON o.id = p.orden_id
                WHERE p.proyecto_id = $pid AND o.estado IN ($est) AND p.estado <> 'cancelado' AND p.actividad_id IS NOT NULL") as $f) $nuevo($f['actividad_id'], $f);
        return $out;
    }

    /** Lo que se movió en un día: órdenes creadas/modificadas y renglones ejecutados, retirados o verificados */
    public static function delDia(int $pid, string $fecha): array
    {
        $f = $GLOBALS['db']->real_escape_string($fecha);
        $ordenes = self::filas("SELECT id, numero, titulo, estado, impacto_costo, impacto_dias, DATE(creado) = '$f' AS nueva FROM ordenes_cambio
            WHERE proyecto_id = $pid AND (DATE(creado) = '$f' OR DATE(actualizado) = '$f') ORDER BY id");
        $renglones = self::filas("SELECT p.id, p.tipo, p.descripcion, p.estado, p.ubicacion_original, p.ubicacion_nueva, o.numero FROM cambio_partidas p JOIN ordenes_cambio o ON o.id = p.orden_id
            WHERE p.proyecto_id = $pid AND p.estado <> 'pendiente' AND p.estado <> 'cancelado' AND (p.fecha_ejecucion = '$f' OR p.fecha_retiro_real = '$f' OR DATE(p.actualizado) = '$f') ORDER BY p.id");
        return ['ordenes' => $ordenes, 'renglones' => $renglones];
    }

    /** Material y plazos que piden las órdenes de cambio (renglones con material, no cancelados) */
    public static function materialesDeCambios(int $pid): array
    {
        return self::filas("SELECT p.id, p.descripcion, p.cantidad, p.unidad, p.fecha_prevista, p.estado, o.numero, o.estado orden_estado, m.nombre material, m.compra, m.fecha_entrega_prog, m.fecha_entrega_real,
                a.codigo act_codigo, a.fecha_inicio act_inicio
            FROM cambio_partidas p JOIN ordenes_cambio o ON o.id = p.orden_id JOIN materiales m ON m.id = p.material_id LEFT JOIN actividades a ON a.id = p.actividad_id
            WHERE p.proyecto_id = $pid AND p.estado <> 'cancelado' AND o.estado NOT IN ('rechazada','cerrada') ORDER BY o.id, p.id");
    }
}
