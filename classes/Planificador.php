<?php

namespace Classes;

/**
 * Semáforo de las actividades según sus materiales y sus restricciones activas.
 * Un material cuenta como disponible en cuanto está COMPRADO (la entrega puede tardar, pero ya se puede planificar).
 *  - lista:      todos sus materiales comprados y sin restricciones activas (o sin materiales vinculados)
 *  - bloqueada:  falta comprar algún material o tiene una restricción activa
 */
class Planificador
{
    /** @return array{listas:array,bloqueadas:array,inversa:array,sinMateriales:int,total:int} */
    public static function datos(int $pid, ?int $partidaId, int $semanas, bool $soloVinculadas): array
    {
        global $db;
        $q = function (string $sql) use ($db): array {
            $r = $db->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        };
        $f = fn(?string $d) => $d ? formatear_fecha($d, 'd/m') : null;

        $acts = $q("SELECT a.id, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin, a.progreso, a.hito, a.sin_material, a.prioridad,
                           pa.id AS partida_id, pa.codigo AS pcod, pa.nombre AS pnom
                    FROM actividades a JOIN partidas pa ON pa.id = a.partida_id
                    WHERE pa.proyecto_id = $pid AND a.status <> 'completada'
                    ORDER BY a.fecha_inicio ASC, a.id ASC");
        $mats = $restr = [];
        if ($acts) {
            $ids = implode(',', array_column($acts, 'id'));
            foreach ($q("SELECT am.actividad_id, m.cantidad, m.unidad, m.id AS mid, m.nombre, m.compra, m.fecha_compra_prog, m.fecha_entrega_prog, m.fecha_entrega_real
                         FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id WHERE am.actividad_id IN ($ids) ORDER BY m.nombre") as $r) {
                $mats[$r['actividad_id']][] = $r;
            }
            foreach ($q("SELECT r.actividad_id, r.descripcion, r.fecha, t.nombre AS tipo FROM restricciones r JOIN tipos_restriccion t ON t.id = r.tipo_id
                         WHERE r.estado = 'activa' AND r.actividad_id IN ($ids)") as $r) {
                $restr[$r['actividad_id']][] = $r;
            }
        }

        $hoy = date('Y-m-d');
        $lunes = date('Y-m-d', strtotime('monday this week'));
        $limite = $semanas > 0 ? date('Y-m-d', strtotime("$lunes +" . ($semanas * 7 - 1) . ' days')) : null;
        $estado = [];     // id => 'lista'|'pronto'|'bloqueada'
        $tarjetas = [];
        $sinMat = 0;
        foreach ($acts as $a) {
            $id = (int)$a['id'];
            $motivos = [];
            $libera = [];        // fechas de las que depende
            $sinFecha = false;
            $nivel = 0;           // 0 lista, 1 pronto, 2 bloqueada
            $vinculada = !empty($mats[$id]);
            if (!$vinculada && !(int)$a['sin_material']) $sinMat++;
            foreach ($mats[$id] ?? [] as $m) {
                $cant = $m['cantidad'] !== null ? ' (' . rtrim(rtrim($m['cantidad'], '0'), '.') . ($m['unidad'] ? ' ' . $m['unidad'] : '') . ')' : '';
                if ($m['fecha_entrega_real']) continue;
                if ($m['compra'] === 'comprado') {
                    // Comprado: ya se puede planificar; la entrega pendiente solo se informa
                    $motivos[] = ['t' => 'Comprado, por entregar: ' . $m['nombre'] . $cant . ($m['fecha_entrega_prog'] ? ' · entrega prog. ' . $f($m['fecha_entrega_prog']) : ''), 'n' => 0];
                } else {
                    $nivel = 2;
                    $motivos[] = ['t' => 'Falta comprar: ' . $m['nombre'] . $cant . ($m['fecha_compra_prog'] ? ' · compra prog. ' . $f($m['fecha_compra_prog']) : ''), 'n' => 2];
                    $m['fecha_compra_prog'] ? $libera[] = $m['fecha_compra_prog'] : $sinFecha = true;
                }
            }
            foreach ($restr[$id] ?? [] as $r) {
                $nivel = 2;
                $motivos[] = ['t' => 'Restricción: ' . $r['tipo'] . ($r['descripcion'] ? ' — ' . mb_strimwidth(trim($r['descripcion']), 0, 60, '…') : ''), 'n' => 2];
                $r['fecha'] ? $libera[] = $r['fecha'] : $sinFecha = true;
            }
            $clave = $nivel >= 2 ? 'bloqueada' : 'lista';
            $estado[$id] = $clave;

            // Ventana y filtros solo afectan lo que se muestra
            if ($partidaId && (int)$a['partida_id'] !== $partidaId) continue;
            if ($limite && ($a['fecha_inicio'] > $limite || $a['fecha_fin'] < $lunes)) continue;
            if ($soloVinculadas && !$vinculada) continue;
            $tarjetas[$clave][] = $a + [
                'vinculada' => $vinculada, 'motivos' => $motivos,
                'libera' => $libera ? $f(max($libera)) : null, 'sin_fecha' => $sinFecha, 'nivel' => $nivel,
                'vencida' => $a['fecha_inicio'] < $hoy,
            ];
        }

        // Vista inversa: materiales ya comprados (o entregados) y las actividades pendientes que los usan
        $inversa = [];
        foreach ($q("SELECT m.id AS mid, m.nombre, m.imagen, m.fecha_entrega_real, m.fecha_entrega_prog, m.compra, a.id AS aid, a.codigo, a.nombre AS anombre, a.fecha_inicio, pa.id AS partida_id
                     FROM materiales m JOIN actividad_materiales am ON am.material_id = m.id JOIN actividades a ON a.id = am.actividad_id
                     JOIN partidas pa ON pa.id = a.partida_id
                     WHERE m.proyecto_id = $pid AND (m.compra = 'comprado' OR m.fecha_entrega_real IS NOT NULL) AND a.status <> 'completada' ORDER BY m.nombre, a.fecha_inicio") as $r) {
            if ($partidaId && (int)$r['partida_id'] !== $partidaId) continue;
            $inversa[$r['mid']]['nombre'] = $r['nombre'];
            $inversa[$r['mid']]['imagen'] = $r['imagen'];
            $inversa[$r['mid']]['entrega'] = $r['fecha_entrega_real'] ? 'Entregado el ' . $f($r['fecha_entrega_real'])
                : 'Comprado · ' . ($r['fecha_entrega_prog'] ? 'entrega prog. ' . $f($r['fecha_entrega_prog']) : 'sin fecha de entrega');
            $inversa[$r['mid']]['actividades'][] = [
                'id' => (int)$r['aid'], 'codigo' => $r['codigo'], 'nombre' => $r['anombre'], 'inicio' => $f($r['fecha_inicio']),
                'estado' => $estado[(int)$r['aid']] ?? 'lista', 'futura' => $r['fecha_inicio'] > $hoy,
            ];
        }

        return [
            'listas' => $tarjetas['lista'] ?? [], 'bloqueadas' => $tarjetas['bloqueada'] ?? [],
            'inversa' => array_values($inversa), 'sinMateriales' => $sinMat, 'total' => count($acts),
        ];
    }
}
