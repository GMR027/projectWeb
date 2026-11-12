<?php

namespace Classes;

/**
 * Fachada de compatibilidad del «concepto de obra» de una actividad.
 *
 * Desde el catálogo de conceptos la fuente de verdad es `Classes\Conceptos`
 * (`conceptos_obra` + `actividad_conceptos`). Esta fachada conserva la API que usan
 * el programa, el Ensamblador y los datos de obra, para que el cambio no obligue
 * a reescribir todos los llamadores de una vez.
 */
class Cantidades
{
    use AccesoDb;

    /** Importe de un concepto; null si falta cantidad o precio */
    public static function importe($cantidad, $precio): ?float
    {
        if ($cantidad === null || $cantidad === '' || $precio === null || $precio === '') return null;
        return Conceptos::importe($cantidad, $precio);
    }

    /** Primer concepto ligado a la actividad, con la forma antigua (clave, concepto, cantidad…) */
    public static function deActividad(int $actividadId): ?array
    {
        $a = Conceptos::asignacionesDeActividad($actividadId)[0] ?? null;
        if (!$a) return null;
        return [
            'actividad_id' => $actividadId,
            'clave' => (string)$a['clave'],
            'concepto' => (string)$a['descripcion'],
            'cantidad' => (float)$a['cantidad'],
            'unidad' => (string)$a['unidad'],
            'precio_unitario' => (float)$a['precio_unitario'],
            'importe' => (float)$a['importe'],
            'origen' => '',
        ];
    }

    /**
     * Guarda el concepto de una actividad desde el formulario antiguo.
     * Se mantiene para enlaces y herramientas viejas; la pantalla nueva usa el catálogo.
     * @return array{0:bool,1:string}
     */
    public static function guardar(int $pid, int $actividadId, array $d): array
    {
        $clave = trim((string)($d['clave'] ?? ''));
        $concepto = trim((string)($d['concepto'] ?? ''));
        $unidad = trim((string)($d['unidad'] ?? ''));
        $cantidad = self::numero($d['cantidad'] ?? null);
        $precio = self::numero($d['precio_unitario'] ?? null);
        if ($cantidad !== null && $cantidad < 0) return [false, 'La cantidad no puede ser negativa'];
        if ($precio !== null && $precio < 0) return [false, 'El precio unitario no puede ser negativo'];
        if ($concepto === '' && $clave === '' && $cantidad === null && $precio === null && $unidad === '') {
            foreach (Conceptos::asignacionesDeActividad($actividadId) as $a) {
                Conceptos::desasignar($pid, $actividadId, (int)$a['concepto_id']);
            }
            return [true, 'Concepto de obra quitado'];
        }
        if ($concepto === '') return [false, 'Escribe el concepto de obra'];
        // El formulario antiguo manejaba un solo concepto por actividad: se reemplazan los vínculos
        foreach (Conceptos::asignacionesDeActividad($actividadId) as $a) {
            Conceptos::desasignar($pid, $actividadId, (int)$a['concepto_id']);
        }
        try {
            $r = Conceptos::fijarDesdeTexto($pid, $actividadId, [
                'clave' => $clave, 'descripcion' => $concepto, 'unidad' => $unidad,
                'cantidad' => $cantidad ?? 0, 'precio_unitario' => $precio, 'origen' => (string)($d['origen'] ?? 'Programa'),
            ]);
            return [true, 'Concepto de obra guardado'];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    /**
     * Resumen por partida manteniendo la forma que ya consumían Costos y la ficha de la actividad:
     * total de actividades con duración, cuántas tienen al menos un concepto y la suma del catálogo.
     * @return array<int, array{total:int,valuadas:int,faltantes:int,suma:float,completo:bool}>
     */
    public static function resumenPartidas(int $pid, ?int $partidaId = null): array
    {
        $w = $partidaId ? ' AND pa.id = ' . (int)$partidaId : '';
        $filas = self::filas("SELECT pa.id partida_id, COUNT(a.id) total,
                SUM(CASE WHEN EXISTS (SELECT 1 FROM actividad_conceptos ac WHERE ac.actividad_id = a.id) THEN 1 ELSE 0 END) valuadas
            FROM partidas pa
            LEFT JOIN actividades a ON a.partida_id = pa.id AND a.hito = 0
            WHERE pa.proyecto_id = $pid$w
            GROUP BY pa.id");
        $cat = Conceptos::resumenPartidas($pid);
        $out = [];
        foreach ($filas as $f) {
            $id = (int)$f['partida_id'];
            $total = (int)$f['total'];
            $valuadas = (int)$f['valuadas'];
            $out[$id] = [
                'total' => $total,
                'valuadas' => $valuadas,
                'faltantes' => max(0, $total - $valuadas),
                'suma' => (float)($cat['partidas'][$id]['suma'] ?? 0),
                'completo' => $total > 0 && $valuadas >= $total,
            ];
        }
        return $out;
    }

    /** Sincroniza el presupuesto de una partida con el catálogo */
    public static function recalcularPartida(int $pid, int $partidaId): array
    {
        Conceptos::recalcularPartida($pid, (int)$partidaId);
        $out = self::resumenPartidas($pid, (int)$partidaId)[(int)$partidaId]
            ?? ['total' => 0, 'valuadas' => 0, 'faltantes' => 0, 'suma' => 0.0, 'completo' => false];
        return $out;
    }

    public static function recalcularProyecto(int $pid): int
    {
        return Conceptos::recalcularProyecto($pid);
    }

    private static function numero($v): ?float
    {
        if ($v === null || $v === '') return null;
        $t = str_replace([',', '$', ' '], '', (string)$v);
        return is_numeric($t) ? (float)$t : null;
    }
}
