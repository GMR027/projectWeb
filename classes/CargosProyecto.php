<?php

namespace Classes;

/**
 * Cargos que se aplican al subtotal directo del catálogo de conceptos:
 * indirectos, financiamiento y utilidad (porcentajes) e IVA.
 * El IVA se muestra siempre en el PDF y las estimaciones, pero por defecto NO entra
 * al presupuesto vigente (el costo real se captura sin IVA y el CPI quedaría distorsionado).
 */
class CargosProyecto
{
    use AccesoDb;

    public const CAMPOS = ['indirectos', 'financiamiento', 'utilidad', 'iva'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query("SELECT 1 FROM proyecto_cargos LIMIT 1");
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Cargos del proyecto; si la migración no está aplicada o no hay fila, todo queda en cero salvo el IVA. */
    public static function deProyecto(int $pid): array
    {
        $base = ['indirectos' => 0.0, 'financiamiento' => 0.0, 'utilidad' => 0.0, 'iva' => 16.0, 'iva_en_bac' => 0];
        if (!self::disponible()) return $base;
        $f = self::filas("SELECT indirectos, financiamiento, utilidad, iva, iva_en_bac FROM proyecto_cargos WHERE proyecto_id = $pid")[0] ?? null;
        if (!$f) return $base;
        foreach (self::CAMPOS as $c) $base[$c] = (float)$f[$c];
        $base['iva_en_bac'] = (int)$f['iva_en_bac'];
        return $base;
    }

    /** @return array{0:bool,1:string} */
    public static function guardar(int $pid, array $d): array
    {
        if (!self::disponible()) return [false, 'Falta aplicar database/migracion_catalogo_conceptos.sql'];
        $v = [];
        foreach (self::CAMPOS as $c) {
            $n = self::numero($d[$c] ?? 0);
            if ($n === null || $n < 0 || $n > 100) return [false, '«' . ucfirst($c) . '» debe ser un porcentaje entre 0 y 100'];
            $v[$c] = round($n, 3);
        }
        $ivaBac = !empty($d['iva_en_bac']) ? 1 : 0;
        $antes = self::filas("SELECT * FROM proyecto_cargos WHERE proyecto_id = $pid")[0] ?? null;
        $sql = "INSERT INTO proyecto_cargos (proyecto_id, indirectos, financiamiento, utilidad, iva, iva_en_bac)
            VALUES ($pid, {$v['indirectos']}, {$v['financiamiento']}, {$v['utilidad']}, {$v['iva']}, $ivaBac)
            ON DUPLICATE KEY UPDATE indirectos = VALUES(indirectos), financiamiento = VALUES(financiamiento),
                utilidad = VALUES(utilidad), iva = VALUES(iva), iva_en_bac = VALUES(iva_en_bac)";
        try {
            if (!self::db()->query($sql)) return [false, 'No se pudieron guardar los cargos del proyecto'];
        } catch (\Throwable $e) {
            error_log('CargosProyecto::guardar: ' . $e->getMessage());
            return [false, 'No se pudieron guardar los cargos del proyecto'];
        }
        $despues = self::filas("SELECT * FROM proyecto_cargos WHERE proyecto_id = $pid")[0] ?? null;
        HistorialCambios::desdeImagen('proyecto_cargos', $antes ? 'modificado' : 'creado', $antes, $despues);
        return [true, 'Cargos del proyecto guardados'];
    }

    /** Factor que multiplica el subtotal directo (1 = sin cargos). */
    public static function factor(array $c): float
    {
        $pct = (float)($c['indirectos'] ?? 0) + (float)($c['financiamiento'] ?? 0) + (float)($c['utilidad'] ?? 0);
        if (!empty($c['iva_en_bac'])) $pct += (float)($c['iva'] ?? 0);
        return round(1 + $pct / 100, 6);
    }

    /**
     * Desglose en dinero de los cargos sobre un subtotal (sin incluir el subtotal).
     * @return array{subtotal:float,indirectos:float,financiamiento:float,utilidad:float,iva:float,total:float,factor:float}
     */
    public static function desglose(float $subtotal, array $c): array
    {
        $subtotal = round($subtotal, 2);
        $ind = round($subtotal * (float)($c['indirectos'] ?? 0) / 100, 2);
        $fin = round($subtotal * (float)($c['financiamiento'] ?? 0) / 100, 2);
        $uti = round($subtotal * (float)($c['utilidad'] ?? 0) / 100, 2);
        $iva = round($subtotal * (float)($c['iva'] ?? 0) / 100, 2);
        $total = round(($subtotal + $ind + $fin + $uti + (!empty($c['iva_en_bac']) ? $iva : 0)), 2);
        return ['subtotal' => $subtotal, 'indirectos' => $ind, 'financiamiento' => $fin, 'utilidad' => $uti,
            'iva' => $iva, 'total' => $total, 'factor' => self::factor($c)];
    }

    private static function numero($v): ?float
    {
        if ($v === null || $v === '') return 0.0;
        $t = str_replace([',', '%', '$', ' '], '', (string)$v);
        return is_numeric($t) ? (float)$t : null;
    }
}
