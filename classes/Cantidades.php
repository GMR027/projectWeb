<?php

namespace Classes;

/**
 * Cantidades de obra (catálogo de conceptos) por actividad: cantidad × precio unitario = importe.
 * Cuando todas las actividades con duración de una partida están valuadas, su presupuesto pasa a ser
 * la suma de los importes; si falta alguna, se conserva el presupuesto manual y se avisa.
 */
class Cantidades
{
    use AccesoDb;

    public static function deActividad(int $actividadId): ?array
    {
        return self::filas("SELECT * FROM actividad_cantidades WHERE actividad_id = $actividadId")[0] ?? null;
    }

    /** Importe de un concepto; null si falta cantidad o precio */
    public static function importe($cantidad, $precio): ?float
    {
        $c = self::numero($cantidad);
        $p = self::numero($precio);
        return ($c === null || $p === null) ? null : round($c * $p, 2);
    }

    /**
     * Crea, actualiza o borra el concepto de una actividad (una fila por actividad).
     * Devuelve [ok, mensaje].
     */
    public static function guardar(int $pid, int $actividadId, array $d): array
    {
        $act = self::filas("SELECT a.id, a.partida_id FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE a.id = $actividadId AND p.proyecto_id = $pid")[0] ?? null;
        if (!$act) return [false, 'La actividad no pertenece al proyecto'];

        $clave = trim((string)($d['clave'] ?? ''));
        $concepto = trim((string)($d['concepto'] ?? ''));
        $unidad = trim((string)($d['unidad'] ?? ''));
        $cantidad = self::numero($d['cantidad'] ?? null);
        $precio = self::numero($d['precio_unitario'] ?? null);
        if ($cantidad !== null && $cantidad < 0) return [false, 'La cantidad no puede ser negativa'];
        if ($precio !== null && $precio < 0) return [false, 'El precio unitario no puede ser negativo'];
        if ($concepto === '' && $clave === '' && $cantidad === null && $precio === null && $unidad === '') {
            self::db()->query("DELETE FROM actividad_cantidades WHERE actividad_id = $actividadId");
            self::recalcularPartida($pid, (int)$act['partida_id']);
            return [true, 'Concepto de obra quitado'];
        }
        if ($concepto === '') return [false, 'Escribe el concepto de obra'];

        $importe = ($cantidad !== null && $precio !== null) ? round($cantidad * $precio, 2) : null;
        $origen = mb_substr(trim((string)($d['origen'] ?? 'Programa')), 0, 255);
        $sql = "INSERT INTO actividad_cantidades (actividad_id, proyecto_id, clave, concepto, cantidad, unidad, precio_unitario, importe, origen)
            VALUES ($actividadId, $pid, '" . self::esc(mb_substr($clave, 0, 60)) . "', '" . self::esc(mb_substr($concepto, 0, 255)) . "',
                " . ($cantidad === null ? 'NULL' : $cantidad) . ", '" . self::esc(mb_substr($unidad, 0, 30)) . "',
                " . ($precio === null ? 'NULL' : $precio) . ", " . ($importe === null ? 'NULL' : $importe) . ", '" . self::esc($origen) . "')
            ON DUPLICATE KEY UPDATE clave = VALUES(clave), concepto = VALUES(concepto), cantidad = VALUES(cantidad),
                unidad = VALUES(unidad), precio_unitario = VALUES(precio_unitario), importe = VALUES(importe), origen = VALUES(origen)";
        if (!self::db()->query($sql)) return [false, 'No se pudo guardar el concepto de obra'];
        self::recalcularPartida($pid, (int)$act['partida_id']);
        return [true, 'Concepto de obra guardado'];
    }

    /**
     * Resumen de valuación por partida en dos consultas (para Costos y la ficha de la actividad).
     * @return array<int, array{total:int,valuadas:int,faltantes:int,suma:float,completo:bool}>
     */
    public static function resumenPartidas(int $pid, ?int $partidaId = null): array
    {
        $w = $partidaId ? ' AND pa.id = ' . (int)$partidaId : '';
        $filas = self::filas("SELECT pa.id partida_id, COUNT(a.id) total,
                SUM(CASE WHEN c.cantidad IS NOT NULL AND c.cantidad > 0 AND c.precio_unitario IS NOT NULL THEN 1 ELSE 0 END) valuadas,
                SUM(CASE WHEN c.cantidad IS NOT NULL AND c.cantidad > 0 AND c.precio_unitario IS NOT NULL THEN c.cantidad * c.precio_unitario ELSE 0 END) suma
            FROM partidas pa
            LEFT JOIN actividades a ON a.partida_id = pa.id AND a.hito = 0
            LEFT JOIN actividad_cantidades c ON c.actividad_id = a.id
            WHERE pa.proyecto_id = $pid$w
            GROUP BY pa.id");
        $out = [];
        foreach ($filas as $f) {
            $total = (int)$f['total'];
            $valuadas = (int)$f['valuadas'];
            $out[(int)$f['partida_id']] = [
                'total' => $total,
                'valuadas' => $valuadas,
                'faltantes' => max(0, $total - $valuadas),
                'suma' => round((float)$f['suma'], 2),
                'completo' => $total > 0 && $valuadas >= $total,
            ];
        }
        return $out;
    }

    /**
     * Sincroniza el presupuesto de una partida con sus cantidades.
     * Completa: presupuesto = suma de importes, origen «cantidades».
     * Incompleta: vuelve al presupuesto manual guardado, origen «manual».
     */
    public static function recalcularPartida(int $pid, int $partidaId): array
    {
        $r = self::resumenPartidas($pid, (int)$partidaId)[(int)$partidaId] ?? ['total' => 0, 'valuadas' => 0, 'faltantes' => 0, 'suma' => 0.0, 'completo' => false];
        if ($r['completo']) {
            $suma = round($r['suma'], 2);
            self::db()->query("INSERT INTO costos_partidas (partida_id, proyecto_id, presupuesto, presupuesto_manual, origen)
                VALUES (" . (int)$partidaId . ", $pid, $suma, $suma, 'cantidades')
                ON DUPLICATE KEY UPDATE presupuesto = $suma, origen = 'cantidades'");
        } else {
            self::db()->query("UPDATE costos_partidas SET presupuesto = COALESCE(presupuesto_manual, 0), origen = 'manual'
                WHERE partida_id = " . (int)$partidaId . " AND proyecto_id = $pid AND origen = 'cantidades'");
        }
        return $r;
    }

    /** Recalcula todas las partidas del proyecto (para datos que ya existían antes de la migración) */
    public static function recalcularProyecto(int $pid): int
    {
        $n = 0;
        foreach (array_keys(self::resumenPartidas($pid)) as $partidaId) {
            self::recalcularPartida($pid, (int)$partidaId);
            $n++;
        }
        return $n;
    }

    private static function numero($v): ?float
    {
        if ($v === null || $v === '') return null;
        $t = str_replace([',', '$', ' '], '', (string)$v);
        return is_numeric($t) ? (float)$t : null;
    }
}
