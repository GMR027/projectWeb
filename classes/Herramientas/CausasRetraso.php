<?php

namespace Classes\Herramientas;

use Classes\Dependencias;
use Classes\Informes;
use Classes\Voz;
use Model\Proyecto;

/**
 * Análisis de causas de retraso: para cada actividad atrasada cruza materiales (llegaron después del inicio), submittals (aprobados tarde o sin aprobar),
 * restricciones, predecesoras sin terminar, días no hábiles por clima/paro y menciones en bitácora, y las agrupa por causa. La IA resume las causas raíz.
 */
class CausasRetraso extends Base
{
    public const CAUSAS = ['material' => 'Material', 'submittal' => 'Submittal / aprobación', 'restriccion' => 'Restricción', 'predecesora' => 'Predecesora sin terminar', 'clima' => 'Clima o paro técnico', 'bitacora' => 'Mencionada en la bitácora', 'sin_causa' => 'Sin causa registrada'];

    public static function id(): string { return 'causas_retraso'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Causas de retraso', 'grupo' => 'planeacion', 'tipo' => 'causas_retraso', 'aplicable' => false,
            'descripcion' => 'Explica por qué se atrasaron las actividades: cruza materiales, submittals, restricciones, predecesoras, clima y bitácora, y agrupa las causas. Sirve de sustento para reclamaciones y para actuar sobre la causa raíz. La IA resume las causas principales.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [['nombre' => 'dias', 'etiqueta' => 'Atraso mínimo en días', 'tipo' => 'numero', 'defecto' => 1, 'min' => 0, 'max' => 365]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Causas de retraso · ' . date('d/m/Y');
    }

    /**
     * Causa de cada actividad atrasada y total por causa. Determinista, reutilizable desde otros informes:
     * no arma prompts ni pide IA, solo cruza datos.
     * @return array{actividades: array[], por_causa: array<int, int>, total: int}
     */
    public static function analizar(Proyecto $p, int $diasMin): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $acts = Dependencias::actividades($pid);
        $preds = [];
        foreach (Dependencias::relaciones($pid) as $r) $preds[$r['suc']][] = $r['pred'];
        $mats = [];
        foreach (self::filas("SELECT am.actividad_id, m.nombre, m.fecha_entrega_prog, m.fecha_entrega_real, a.sin_material,
                (SELECT s.estatus FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE sm.material_id = m.id ORDER BY (s.estatus IN ('aprobado','aprobado_an')) DESC, s.fecha DESC LIMIT 1) sub,
                (SELECT s.fecha_respuesta FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE sm.material_id = m.id AND s.estatus IN ('aprobado','aprobado_an') ORDER BY s.fecha_respuesta LIMIT 1) resp
            FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id JOIN actividades a ON a.id = am.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid") as $f) $mats[(int)$f['actividad_id']][] = $f;
        $restr = [];
        foreach (self::filas("SELECT r.actividad_id, t.nombre tipo, r.descripcion, r.estado FROM restricciones r JOIN tipos_restriccion t ON t.id = r.tipo_id JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND r.estado <> 'cancelada'") as $f) $restr[(int)$f['actividad_id']][] = $f;
        $dnh = self::filas("SELECT fecha, motivo, tipo FROM calendario_festivos WHERE proyecto_id = $pid AND tipo IN ('clima', 'paro_tecnico')");
        $bit = self::filas("SELECT fecha, departamento, descripcion FROM bitacora WHERE proyecto_id = $pid AND fecha <= '$hoy'");

        $filas = [];
        $porCausa = array_fill_keys(array_keys(self::CAUSAS), 0);
        foreach ($acts as $id => $a) {
            if ((int)$a['hito'] || (int)$a['progreso'] >= 100) continue;
            $atraso = (int)floor((strtotime($hoy) - strtotime($a['fecha_fin'])) / 86400);
            if ($atraso < max(1, $diasMin)) continue;
            $c = [];
            foreach ($mats[$id] ?? [] as $m) {
                if ((int)$m['sin_material']) continue;
                $ent = $m['fecha_entrega_real'] ?: $m['fecha_entrega_prog'];
                if (!$m['fecha_entrega_real'] && (!$m['fecha_entrega_prog'] || $m['fecha_entrega_prog'] > $a['fecha_inicio'])) $c[] = ['material', '«' . self::corto($m['nombre'], 40) . '» ' . ($m['fecha_entrega_prog'] ? 'llegará el ' . self::fmt($m['fecha_entrega_prog']) : 'sin fecha de entrega')];
                elseif ($m['fecha_entrega_real'] && $m['fecha_entrega_real'] > $a['fecha_inicio']) $c[] = ['material', '«' . self::corto($m['nombre'], 40) . '» se entregó el ' . self::fmt($m['fecha_entrega_real']) . ', después del inicio programado'];
                if ($m['sub'] !== null && !in_array($m['sub'], ['aprobado', 'aprobado_an'], true)) $c[] = ['submittal', 'Submittal de «' . self::corto($m['nombre'], 40) . '» ' . ($m['sub'] === 'rechazado' ? 'rechazado' : 'sin aprobar')];
                elseif ($m['resp'] && $m['resp'] > $a['fecha_inicio']) $c[] = ['submittal', 'Submittal de «' . self::corto($m['nombre'], 40) . '» aprobado el ' . self::fmt($m['resp']) . ', después del inicio programado'];
            }
            foreach ($restr[$id] ?? [] as $r) $c[] = ['restriccion', $r['tipo'] . ($r['estado'] === 'activa' ? ' (activa)' : ' (resuelta)') . ': ' . self::corto($r['descripcion'], 70)];
            foreach ($preds[$id] ?? [] as $pr) if (isset($acts[$pr]) && (int)$acts[$pr]['progreso'] < 100 && $acts[$pr]['fecha_fin'] <= $hoy) $c[] = ['predecesora', 'Predecesora ' . $acts[$pr]['codigo'] . ' sin terminar (' . $acts[$pr]['progreso'] . '%)'];
            $dias = array_filter($dnh, fn($d) => $d['fecha'] >= $a['fecha_inicio'] && $d['fecha'] <= min($hoy, $a['fecha_fin']));
            if ($dias) $c[] = ['clima', count($dias) . ' día(s) no hábil(es) por ' . implode('/', array_unique(array_map(fn($d) => $d['tipo'] === 'clima' ? 'clima' : 'paro técnico', $dias)))];
            $tk = Voz::palabras($a['nombre']);
            foreach ($bit as $b) {
                if ($b['fecha'] < $a['fecha_inicio']) continue;
                $txt = Voz::normalizar($b['descripcion']);
                if (str_contains($txt, Voz::normalizar($a['codigo'])) || count(array_intersect($tk, Voz::palabras($b['descripcion']))) >= 3) { $c[] = ['bitacora', self::fmt($b['fecha']) . ': ' . self::corto($b['descripcion'], 90)]; break; }
            }
            if (!$c) $c[] = ['sin_causa', (int)$a['progreso'] === 0 ? 'No inició y no hay causa registrada' : 'Avanza más lento de lo programado y no hay causa registrada'];
            $tipos = array_unique(array_column($c, 0));
            foreach ($tipos as $t) $porCausa[$t]++;
            $filas[] = ['codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'atraso' => $atraso, 'progreso' => (int)$a['progreso'], 'causas' => $c, 'tipos' => array_values($tipos)];
        }
        usort($filas, fn($x, $y) => $y['atraso'] <=> $x['atraso']);
        return ['actividades' => $filas, 'por_causa' => $porCausa, 'total' => count($filas)];
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $hoy = date('Y-m-d');
        $datos = self::analizar($p, (int)$params['dias']);
        $filas = $datos['actividades'];
        $porCausa = $datos['por_causa'];

        $orden = $porCausa; arsort($orden);
        $lin = array_map(fn($x) => $x['codigo'] . ' ' . self::corto($x['nombre'], 55) . " (atraso {$x['atraso']} d, {$x['progreso']}%): " . implode('; ', array_slice(array_map(fn($c) => self::CAUSAS[$c[0]] . ' – ' . $c[1], $x['causas']), 0, 3)), array_slice($filas, 0, 14));
        $prompt = "Eres analista de proyectos de construcción y explicas por qué se atrasó la obra para la dirección. Usa SOLO los datos; no inventes causas. Distingue causas internas (programación, compras) de externas (clima, cliente) cuando se pueda.\n\n"
            . "Proyecto: {$p->nombre} · Hoy: " . self::fmt($hoy) . " · Actividades atrasadas: " . count($filas) . "\nActividades afectadas por causa: " . implode(', ', array_map(fn($k, $v) => self::CAUSAS[$k] . " $v", array_keys($orden), $orden))
            . "\n\n## LAS MÁS ATRASADAS\n" . ($lin ? implode("\n", $lin) : '(ninguna)')
            . "\n\nDevuelve SOLO un JSON: {\"resumen\":\"1 o 2 párrafos con las causas raíz principales\",\"acciones\":[\"3 a 5 acciones para evitar que se repita\"]}";
        return ['datos' => $datos, 'lotes' => $filas ? [['titulo' => 'Analizando las causas de retraso', 'prompt' => $prompt]] : []];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $por = $datos['por_causa'];
        arsort($por);
        $resumen = Informes::limpiarTexto(self::dato($respuestas, 'resumen') ?? '', 2500);
        $ia = $resumen !== '';
        $top = array_slice(array_filter($por), 0, 3, true);
        if (!$ia) $resumen = $datos['total'] ? 'Hay ' . $datos['total'] . ' actividad(es) atrasada(s). Las causas que más aparecen son: ' . implode(', ', array_map(fn($k, $v) => mb_strtolower(self::CAUSAS[$k]) . " ($v)", array_keys($top), $top)) . '. Una actividad puede tener más de una causa.' : 'No hay actividades atrasadas con los criterios elegidos.';
        $acc = self::textos(self::dato($respuestas, 'acciones'), 6, 240);
        $secs = [
            Informes::kpis('Atrasos', [['Actividades atrasadas', $datos['total']], ...array_map(fn($k, $v) => [self::CAUSAS[$k], $v], array_keys(array_filter($por)), array_filter($por))]),
            Informes::texto('Causas principales', $resumen),
        ];
        if ($acc) $secs[] = Informes::lista('Acciones sugeridas', $acc);
        $secs[] = Informes::tabla('Actividades atrasadas y sus causas (las 40 con más atraso)', ['Código', 'Actividad', 'Atraso (días)', 'Avance', 'Causas encontradas'], array_map(fn($x) => [$x['codigo'], self::corto($x['nombre'], 70), $x['atraso'], $x['progreso'] . '%', implode(' · ', array_map(fn($c) => self::CAUSAS[$c[0]] . ': ' . $c[1], $x['causas']))], array_slice($datos['actividades'], 0, 40)));
        return ['secciones' => $secs];
    }
}
