<?php

namespace Classes;

use Model\LineaBase;
use Model\Proyecto;

/** Líneas base del programa: foto inmutable (actividades, fechas, ruta crítica y fechas de materiales) y comparación contra el programa actual */
class LineasBase
{
    use AccesoDb;

    /** Guarda la foto del programa de hoy. Devuelve el id de la línea base */
    public static function capturar(Proyecto $p, string $nombre, string $notas = ''): int
    {
        $db = self::db();
        $pid = (int)$p->id;
        $esc = fn($v) => $db->real_escape_string((string)$v);
        $acts = Dependencias::actividades($pid);
        $criticas = [];
        foreach (Dependencias::datos($p)['actividades'] as $a) if ($a['critica']) $criticas[] = (int)$a['id'];
        $fin = $acts ? max(array_column($acts, 'fecha_fin')) : null;
        $db->begin_transaction();
        try {
            HistorialCambios::escribir('lineas_base', 'creado', 0, fn() => $db->query("INSERT INTO lineas_base (proyecto_id, nombre, notas, fin_programado, total_actividades, criticas_json, creado_por) VALUES ($pid, '{$esc(mb_substr($nombre, 0, 150))}', '{$esc(mb_substr($notas, 0, 500))}', "
                . ($fin ? "'$fin'" : 'NULL') . ', ' . count($acts) . ", '" . $esc(json_encode($criticas)) . "', '{$esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150))}')"));
            $id = (int)$db->insert_id;
            foreach ($acts as $a) {
                $db->query("INSERT INTO lineas_base_actividades (linea_id, actividad_id, codigo, nombre, partida, fecha_inicio, fecha_fin, duracion_dias, progreso, hito) VALUES ($id, " . (int)$a['id'] . ", '{$esc($a['codigo'])}', '{$esc($a['nombre'])}', '{$esc($a['partida_codigo'] . ' · ' . $a['partida'])}', '{$a['fecha_inicio']}', '{$a['fecha_fin']}', " . (int)$a['duracion_dias'] . ', ' . (int)$a['progreso'] . ', ' . (int)$a['hito'] . ')');
            }
            $db->query("INSERT INTO lineas_base_materiales (linea_id, material_id, nombre, cantidad, fecha_compra_prog, fecha_entrega_prog) SELECT $id, id, nombre, cantidad, fecha_compra_prog, fecha_entrega_prog FROM materiales WHERE proyecto_id = $pid");
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return $id;
    }

    public static function eliminar(int $pid, int $id): void
    {
        HistorialCambios::escribir('lineas_base', 'eliminado', $id,
            fn() => self::db()->query("DELETE FROM lineas_base WHERE id = $id AND proyecto_id = $pid"));
    }

    /** Línea base contra la que se compara por defecto: la más antigua (el programa inicial) */
    public static function inicial(int $pid): ?LineaBase
    {
        $l = LineaBase::deProyecto($pid);
        return $l ? end($l) : null;
    }

    /**
     * Compara una línea base con el programa de hoy.
     * Estados por actividad: igual | movida (cambió inicio, fin o duración) | nueva | eliminada.
     * Cada desviación se explica por una orden de cambio vigente, por un material en riesgo o queda «sin justificar».
     */
    public static function comparar(Proyecto $p, LineaBase $lb): array
    {
        $pid = (int)$p->id;
        $lid = (int)$lb->id;
        $cal = new RutaCritica(new Calendario($pid));
        $actuales = Dependencias::actividades($pid);
        $base = [];
        foreach (self::filas("SELECT * FROM lineas_base_actividades WHERE linea_id = $lid ORDER BY fecha_inicio, codigo") as $b) $base[(int)$b['actividad_id']] = $b;
        $oc = Cambios::justificantes($pid);
        $matRiesgo = CruceMateriales::actividadesConMaterialEnRiesgo(CruceMateriales::cruzar($p));
        $dep = Dependencias::datos($p);
        $criticasAhora = [];
        foreach ($dep['actividades'] as $a) if ($a['critica']) $criticasAhora[(int)$a['id']] = true;
        $criticasAntes = array_flip($lb->criticas());

        $filas = [];
        $causa = function (int $id) use ($oc, $matRiesgo): array {
            if (!empty($oc[$id])) return ['oc', implode(', ', array_column($oc[$id], 'orden'))];
            if (isset($matRiesgo[$id])) return ['material', 'Material en riesgo'];
            return ['sin_justificar', ''];
        };
        foreach ($actuales as $id => $a) {
            $b = $base[$id] ?? null;
            $fila = ['actividad_id' => $id, 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'partida' => $a['partida_codigo'] . ' · ' . $a['partida'], 'color' => $a['color'], 'progreso' => (int)$a['progreso'],
                'base_inicio' => $b['fecha_inicio'] ?? null, 'base_fin' => $b['fecha_fin'] ?? null, 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'd_inicio' => 0, 'd_fin' => 0, 'd_dur' => 0,
                'critica_antes' => isset($criticasAntes[$id]), 'critica_ahora' => isset($criticasAhora[$id]), 'causa' => null, 'detalle' => ''];
            if (!$b) {
                $fila['estado'] = 'nueva';
                [$fila['causa'], $fila['detalle']] = $causa($id);
            } else {
                $fila['d_inicio'] = $cal->difHab($b['fecha_inicio'], $a['fecha_inicio']);
                $fila['d_fin'] = $cal->difHab($b['fecha_fin'], $a['fecha_fin']);
                $fila['d_dur'] = (int)$a['duracion_dias'] - (int)$b['duracion_dias'];
                $fila['estado'] = $fila['d_inicio'] || $fila['d_fin'] || $fila['d_dur'] ? 'movida' : 'igual';
                if ($fila['estado'] === 'movida') [$fila['causa'], $fila['detalle']] = $causa($id);
            }
            $filas[] = $fila;
        }
        foreach ($base as $id => $b) {
            if (isset($actuales[$id])) continue;
            $filas[] = ['actividad_id' => $id, 'codigo' => $b['codigo'], 'nombre' => $b['nombre'], 'partida' => $b['partida'], 'color' => null, 'progreso' => (int)$b['progreso'], 'base_inicio' => $b['fecha_inicio'], 'base_fin' => $b['fecha_fin'],
                'inicio' => null, 'fin' => null, 'd_inicio' => 0, 'd_fin' => 0, 'd_dur' => 0, 'critica_antes' => isset($criticasAntes[$id]), 'critica_ahora' => false, 'estado' => 'eliminada', 'causa' => 'sin_justificar', 'detalle' => ''];
        }
        usort($filas, fn($x, $y) => strcmp($x['base_inicio'] ?? $x['inicio'] ?? '', $y['base_inicio'] ?? $y['inicio'] ?? ''));

        $finActual = $actuales ? max(array_column($actuales, 'fecha_fin')) : null;
        $r = ['igual' => 0, 'movida' => 0, 'nueva' => 0, 'eliminada' => 0, 'oc' => 0, 'material' => 0, 'sin_justificar' => 0];
        foreach ($filas as $f) {
            $r[$f['estado']]++;
            if ($f['causa']) $r[$f['causa']]++;
        }
        $entran = array_values(array_filter($filas, fn($f) => $f['critica_ahora'] && !$f['critica_antes'] && $f['estado'] !== 'eliminada'));
        $salen = array_values(array_filter($filas, fn($f) => $f['critica_antes'] && !$f['critica_ahora']));
        $resumen = $r + [
            'fin_base' => $lb->fin_programado, 'fin_actual' => $finActual, 'd_fin' => $lb->fin_programado && $finActual ? $cal->difHab($lb->fin_programado, $finActual) : 0,
            'criticas_entran' => $entran, 'criticas_salen' => $salen, 'hay_ruta_critica' => (bool)$criticasAhora || (bool)$criticasAntes,
        ];
        return ['linea' => $lb, 'filas' => $filas, 'resumen' => $resumen, 'contrato' => self::contrato($pid, $lb, $base, $actuales)];
    }

    /**
     * Fechas contractuales (las que dejó el Ensamblador) frente a la línea base y al programa de hoy.
     * Inicio y fin comparan el primer/último día; un hito se liga a la actividad de nombre parecido (≥ 0.65).
     * Los desfases son en días naturales: positivo = el programa queda DESPUÉS de la fecha del contrato.
     * @return array<int, array{tipo: string, nombre: string, fecha: string, penalizacion: string, base: ?string, actual: ?string, d_base: ?int, d_actual: ?int, actividad: ?string}>
     */
    public static function contrato(int $pid, LineaBase $lb, array $base, array $actuales): array
    {
        try {
            $fechas = self::filas("SELECT tipo, nombre, fecha, penalizacion FROM proyecto_fechas_contrato WHERE proyecto_id = $pid ORDER BY fecha ASC, id ASC");
        } catch (\Throwable $e) {   // migración pendiente
            return [];
        }
        $nombres = array_column($actuales, 'nombre', 'id');
        $dias = fn(?string $a, string $b) => $a ? (int)round((strtotime($a) - strtotime($b)) / 86400) : null;
        $out = [];
        foreach ($fechas as $f) {
            $b = $a = $act = null;
            if ($f['tipo'] === 'inicio') {
                $b = $base ? min(array_column($base, 'fecha_inicio')) : null;
                $a = $actuales ? min(array_column($actuales, 'fecha_inicio')) : null;
            } elseif ($f['tipo'] === 'fin') {
                $b = $lb->fin_programado ?: ($base ? max(array_column($base, 'fecha_fin')) : null);
                $a = $actuales ? max(array_column($actuales, 'fecha_fin')) : null;
            } else {
                [$id, $sim] = Ensamblador::mejor($f['nombre'], $nombres);
                if ($id && $sim >= 0.65) {
                    $act = $nombres[$id];
                    $a = $actuales[$id]['fecha_fin'];
                    $b = $base[$id]['fecha_fin'] ?? null;
                }
            }
            $out[] = ['tipo' => $f['tipo'], 'nombre' => $f['nombre'], 'fecha' => $f['fecha'], 'penalizacion' => $f['penalizacion'], 'base' => $b, 'actual' => $a,
                'd_base' => $dias($b, $f['fecha']), 'd_actual' => $dias($a, $f['fecha']), 'actividad' => $act];
        }
        return $out;
    }

    /** Materiales cuya fecha de entrega o compra cambió desde la línea base */
    public static function materialesMovidos(int $pid, int $lid): array
    {
        return self::filas("SELECT b.nombre, b.fecha_entrega_prog base_entrega, m.fecha_entrega_prog entrega, b.fecha_compra_prog base_compra, m.fecha_compra_prog compra, b.cantidad base_cantidad, m.cantidad
            FROM lineas_base_materiales b JOIN materiales m ON m.id = b.material_id
            WHERE b.linea_id = $lid AND m.proyecto_id = $pid AND (NOT (b.fecha_entrega_prog <=> m.fecha_entrega_prog) OR NOT (b.fecha_compra_prog <=> m.fecha_compra_prog) OR NOT (b.cantidad <=> m.cantidad)) ORDER BY m.nombre");
    }
}
