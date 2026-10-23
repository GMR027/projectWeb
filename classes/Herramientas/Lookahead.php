<?php

namespace Classes\Herramientas;

use Classes\Dependencias;
use Classes\Informes;
use Model\Proyecto;

/**
 * Lookahead asistido (3 a 6 semanas): para cada actividad que arranca o corre en la ventana revisa si está lista (predecesoras terminadas, materiales
 * entregados, submittals aprobados, sin restricciones) y ordena qué liberar primero según urgencia y ruta crítica. La IA redacta las prioridades.
 */
class Lookahead extends Base
{
    public static function id(): string { return 'lookahead'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Lookahead asistido', 'grupo' => 'planeacion', 'tipo' => 'lookahead', 'aplicable' => false,
            'descripcion' => 'Revisa las próximas 3 a 6 semanas: qué actividades están listas, en riesgo o bloqueadas (predecesoras, materiales, submittals, restricciones) y qué liberar primero. La IA redacta las prioridades.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [['nombre' => 'semanas', 'etiqueta' => 'Semanas hacia adelante (3 a 6)', 'tipo' => 'numero', 'defecto' => 4, 'min' => 3, 'max' => 6]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Lookahead de ' . $params['semanas'] . ' semanas · ' . date('d/m/Y');
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $fin = date('Y-m-d', strtotime("+{$params['semanas']} weeks"));
        $dep = Dependencias::datos($p);
        $acts = Dependencias::actividades($pid);
        $crit = [];
        foreach ($dep['actividades'] as $x) if ($x['critica']) $crit[$x['id']] = true;
        $preds = [];
        foreach (Dependencias::relaciones($pid) as $r) $preds[$r['suc']][] = $r['pred'];
        $mats = [];
        foreach (self::filas("SELECT am.actividad_id, m.id, m.nombre, m.fecha_entrega_prog, m.fecha_entrega_real, a.sin_material,
                (SELECT s.estatus FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE sm.material_id = m.id ORDER BY (s.estatus IN ('aprobado','aprobado_an')) DESC, s.fecha DESC LIMIT 1) AS sub
            FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id JOIN actividades a ON a.id = am.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid") as $f) $mats[(int)$f['actividad_id']][] = $f;
        $restr = [];
        foreach (self::filas("SELECT r.actividad_id, t.nombre tipo, r.descripcion, r.id FROM restricciones r JOIN tipos_restriccion t ON t.id = r.tipo_id JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND r.estado = 'activa'") as $f) $restr[(int)$f['actividad_id']][] = $f;

        $filas = [];
        foreach ($acts as $id => $a) {
            if ((int)$a['progreso'] >= 100 || (int)$a['hito'] || $a['fecha_fin'] < $hoy || $a['fecha_inicio'] > $fin) continue;
            $dias = (int)floor((strtotime($a['fecha_inicio']) - strtotime($hoy)) / 86400);   // negativo = ya debía haber empezado
            $bloqueos = [];
            foreach ($preds[$id] ?? [] as $pid2) if (isset($acts[$pid2]) && (int)$acts[$pid2]['progreso'] < 100) $bloqueos[] = ['tipo' => 'predecesora', 'texto' => 'Predecesora ' . $acts[$pid2]['codigo'] . ' sin terminar (' . $acts[$pid2]['progreso'] . '%)'];
            foreach ($mats[$id] ?? [] as $m) {
                if ($m['fecha_entrega_real']) continue;
                if (!(int)$m['sin_material'] && (!$m['fecha_entrega_prog'] || $m['fecha_entrega_prog'] > $a['fecha_inicio'])) $bloqueos[] = ['tipo' => 'material', 'texto' => 'Material «' . self::corto($m['nombre'], 50) . '» ' . ($m['fecha_entrega_prog'] ? 'llega el ' . self::fmt($m['fecha_entrega_prog']) : 'sin fecha de entrega')];
                if ($m['sub'] !== null && !in_array($m['sub'], ['aprobado', 'aprobado_an'], true)) $bloqueos[] = ['tipo' => 'submittal', 'texto' => 'Submittal de «' . self::corto($m['nombre'], 40) . '» ' . ($m['sub'] === 'rechazado' ? 'rechazado' : 'en revisión')];
            }
            foreach ($restr[$id] ?? [] as $r) $bloqueos[] = ['tipo' => 'restriccion', 'texto' => 'Restricción (' . $r['tipo'] . '): ' . self::corto($r['descripcion'], 80), 'id' => (int)$r['id']];
            $estado = !$bloqueos ? 'lista' : ($dias <= 7 ? 'bloqueada' : 'en_riesgo');
            $semana = min((int)$params['semanas'], intdiv(max($dias, 0), 7) + 1);
            $puntaje = ($estado === 'lista' ? 0 : count($bloqueos) * 2 + (isset($crit[$id]) ? 5 : 0) + max(0, 14 - max($dias, 0)) / 2 + ($dias < 0 ? 4 : 0));
            $filas[] = ['id' => $id, 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'inicio' => $a['fecha_inicio'], 'dias' => $dias, 'semana' => $semana, 'estado' => $estado, 'critica' => isset($crit[$id]), 'bloqueos' => $bloqueos, 'puntaje' => $puntaje, 'progreso' => (int)$a['progreso']];
        }
        usort($filas, fn($x, $y) => [$x['inicio'], $x['codigo']] <=> [$y['inicio'], $y['codigo']]);
        $problemas = array_values(array_filter($filas, fn($x) => $x['estado'] !== 'lista'));
        usort($problemas, fn($x, $y) => $y['puntaje'] <=> $x['puntaje']);
        $datos = ['hoy' => $hoy, 'fin' => $fin, 'actividades' => $filas, 'prioridad' => array_slice($problemas, 0, 12)];

        $lin = array_map(fn($x) => $x['codigo'] . ' ' . self::corto($x['nombre'], 60) . ' (inicia ' . ($x['dias'] < 0 ? 'hace ' . abs($x['dias']) . ' d' : 'en ' . $x['dias'] . ' d') . ($x['critica'] ? ', RUTA CRÍTICA' : '') . '): ' . implode('; ', array_column($x['bloqueos'], 'texto')), array_slice($datos['prioridad'], 0, 10));
        $prompt = "Eres planificador de obra (metodología Last Planner). Con los datos del lookahead de {$params['semanas']} semanas redacta qué se debe liberar primero para que el trabajo pueda arrancar. Usa SOLO los datos; no inventes bloqueos ni fechas.\n\n"
            . "Proyecto: {$p->nombre} · Hoy: " . self::fmt($hoy) . " · Ventana hasta " . self::fmt($fin) . "\nActividades en la ventana: " . count($filas) . " · listas: " . count(array_filter($filas, fn($x) => $x['estado'] === 'lista')) . " · en riesgo: " . count(array_filter($filas, fn($x) => $x['estado'] === 'en_riesgo')) . " · bloqueadas: " . count(array_filter($filas, fn($x) => $x['estado'] === 'bloqueada'))
            . "\n\n## ACTIVIDADES CON BLOQUEOS (las más urgentes primero)\n" . ($lin ? implode("\n", $lin) : '(ninguna)')
            . "\n\nDevuelve SOLO un JSON: {\"resumen\":\"1 o 2 párrafos para la reunión de planeación\",\"prioridades\":[{\"accion\":\"qué hacer para liberar\",\"actividad\":\"código de la actividad\",\"motivo\":\"por qué es urgente\"}]}  (máx. 8 prioridades)";
        return ['datos' => $datos, 'lotes' => $filas ? [['titulo' => 'Redactando las prioridades del lookahead', 'prompt' => $prompt]] : []];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $a = $datos['actividades'];
        $cnt = fn(string $e) => count(array_filter($a, fn($x) => $x['estado'] === $e));
        $porSemana = [];
        foreach ($a as $x) { $porSemana[$x['semana']] ??= ['lista' => 0, 'en_riesgo' => 0, 'bloqueada' => 0]; $porSemana[$x['semana']][$x['estado']]++; }
        ksort($porSemana);
        $resumen = Informes::limpiarTexto(self::dato($respuestas, 'resumen') ?? '', 2500);
        $ia = $resumen !== '';
        if (!$ia) $resumen = 'En las próximas ' . $params['semanas'] . ' semanas hay ' . count($a) . ' actividad(es) por ejecutar: ' . $cnt('lista') . ' lista(s), ' . $cnt('en_riesgo') . ' en riesgo y ' . $cnt('bloqueada') . ' bloqueada(s). Atiende primero las que inician pronto o están en la ruta crítica (tabla «Qué liberar primero»).';
        $prios = [];
        foreach ((array)(self::dato($respuestas, 'prioridades') ?? []) as $x) if (is_array($x) && trim((string)($x['accion'] ?? '')) !== '') $prios[] = [Informes::limpiarTexto($x['actividad'] ?? '', 40), Informes::limpiarTexto($x['accion'], 220), Informes::limpiarTexto($x['motivo'] ?? '', 200)];
        $secs = [
            Informes::kpis('Ventana ' . self::fmt($datos['hoy']) . ' – ' . self::fmt($datos['fin']), [['Actividades', count($a)], ['Listas', $cnt('lista')], ['En riesgo', $cnt('en_riesgo'), 'bloqueos con tiempo para resolver'], ['Bloqueadas', $cnt('bloqueada'), 'inician en 7 días o ya debieron'], ['En ruta crítica', count(array_filter($a, fn($x) => $x['critica']))]]),
            Informes::texto('Resumen', $resumen),
        ];
        if ($prios) $secs[] = Informes::tabla('Prioridades sugeridas', ['Actividad', 'Acción', 'Motivo'], array_slice($prios, 0, 8));
        $secs[] = Informes::tabla('Por semana', ['Semana', 'Listas', 'En riesgo', 'Bloqueadas'], array_map(fn($k, $v) => ['Semana ' . $k, $v['lista'], $v['en_riesgo'], $v['bloqueada']], array_keys($porSemana), $porSemana));
        $secs[] = Informes::tabla('Qué liberar primero (calculado por el sistema)', ['Código', 'Actividad', 'Inicio', 'Estado', 'Ruta crítica', 'Bloqueos'], array_map(fn($x) => [$x['codigo'], self::corto($x['nombre'], 70), self::fmt($x['inicio']), ['bloqueada' => 'Bloqueada', 'en_riesgo' => 'En riesgo'][$x['estado']], $x['critica'] ? 'Sí' : '', implode(' · ', array_column($x['bloqueos'], 'texto'))], $datos['prioridad']));
        $secs[] = Informes::tabla('Todas las actividades de la ventana', ['Código', 'Actividad', 'Inicio', 'Semana', 'Estado'], array_map(fn($x) => [$x['codigo'], self::corto($x['nombre'], 80), self::fmt($x['inicio']), $x['semana'], ['lista' => 'Lista', 'en_riesgo' => 'En riesgo', 'bloqueada' => 'Bloqueada'][$x['estado']]], array_slice($a, 0, 150)));
        return ['secciones' => $secs];
    }
}
