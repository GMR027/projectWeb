<?php

namespace Classes\Herramientas;

use Classes\Calendario;
use Classes\Dependencias;
use Classes\Informes;
use Classes\Simulacion;
use Classes\Voz;
use Model\Proyecto;

/**
 * Simulador de escenarios: «¿qué pasa si esta actividad / este material / todo lo pendiente se atrasa N días?». Calcula el fin del programa con el calendario y las
 * relaciones, y con una simulación estadística (1000 corridas con duraciones inciertas) da la probabilidad de terminar a tiempo. Todo lo calcula el sistema; la IA redacta.
 */
class Simulador extends Base
{
    private const ITER = 1000;
    private const U = ['baja' => 0.10, 'media' => 0.20, 'alta' => 0.35];

    public static function id(): string { return 'simulador'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Simulador de escenarios', 'grupo' => 'planeacion', 'tipo' => 'simulador', 'aplicable' => false,
            'descripcion' => 'Prueba «qué pasa si»: retrasa una actividad, la entrega de un material o todo lo pendiente y mira cómo cambia la fecha de fin y la probabilidad de terminar a tiempo (simulación con duraciones inciertas). No modifica el programa.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [
                ['nombre' => 'tipo', 'etiqueta' => 'Escenario', 'tipo' => 'opcion', 'opciones' => ['base' => 'Sin cambios (solo la incertidumbre de las duraciones)', 'actividad' => 'Se retrasa una actividad', 'material' => 'Se retrasa la entrega de un material', 'todo' => 'Se retrasa todo lo no iniciado'], 'defecto' => 'actividad'],
                ['nombre' => 'objetivo', 'etiqueta' => 'Código de la actividad o parte del nombre del material', 'tipo' => 'texto', 'defecto' => '', 'max' => 100],
                ['nombre' => 'dias', 'etiqueta' => 'Días de retraso', 'tipo' => 'numero', 'defecto' => 14, 'min' => 0, 'max' => 180],
                ['nombre' => 'incertidumbre', 'etiqueta' => 'Incertidumbre de las duraciones', 'tipo' => 'opcion', 'opciones' => ['baja' => 'Baja (−10 % / +20 %)', 'media' => 'Media (−20 % / +40 %)', 'alta' => 'Alta (−35 % / +70 %)'], 'defecto' => 'media'],
            ]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        $t = ['base' => 'Sin cambios', 'actividad' => 'Retraso de actividad ' . $params['objetivo'], 'material' => 'Retraso de material «' . self::corto($params['objetivo'], 30) . '»', 'todo' => 'Retraso general'][$params['tipo']];
        return 'Simulación: ' . $t . ($params['tipo'] !== 'base' ? ' (+' . $params['dias'] . ' d)' : '') . ' · ' . date('d/m/Y');
    }

    private static function motor(Proyecto $p, array $acts, array $rels): Simulacion
    {
        $lista = array_map(fn($a) => ['id' => (int)$a['id'], 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'dur' => (int)$a['duracion_dias'], 'hito' => (int)$a['hito'], 'fija' => (int)$a['fecha_fija'], 'progreso' => (int)$a['progreso']], array_values($acts));
        $sim = new Simulacion(new Calendario((int)$p->id), date('Y-m-d', strtotime(min(array_merge([date('Y-m-d')], array_column($lista, 'inicio'))) . ' -30 days')), 2200);
        $sim->cargar($lista, $rels);
        return $sim;
    }

    private static function q(array $fines, float $q): int
    {
        return $fines[(int)floor($q * (count($fines) - 1))];
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $acts = Dependencias::actividades($pid);
        $rels = Dependencias::relaciones($pid);
        if (!$acts) return ['datos' => ['vacio' => true], 'lotes' => []];
        $u = self::U[$params['incertidumbre']] ?? 0.2;
        $base = self::motor($p, $acts, $rels);
        $esc = self::motor($p, $acts, $rels);
        $nota = '';
        $afectadas = 0;
        if ($params['tipo'] === 'actividad') {
            [$id, $motivo] = Voz::resolverActividad($acts, $params['objetivo']);
            if (!$id) $nota = 'No se encontró la actividad «' . $params['objetivo'] . '» (' . $motivo . '): el escenario quedó igual al caso base.';
            elseif ($esc->retrasarInicio($id, (int)$params['dias'])) { $afectadas = 1; $nota = 'Se retrasa ' . (int)$params['dias'] . ' días hábiles el inicio de ' . $acts[$id]['codigo'] . ' ' . self::corto($acts[$id]['nombre'], 60) . '.'; }
            else $nota = 'La actividad ' . $acts[$id]['codigo'] . ' ya terminó o tiene fecha fija: no se puede retrasar.';
        } elseif ($params['tipo'] === 'material') {
            $tk = Voz::palabras($params['objetivo']);
            $hoy = date('Y-m-d');
            $mats = array_filter(self::filas("SELECT m.id, m.nombre, m.fecha_entrega_prog FROM materiales m WHERE m.proyecto_id = $pid AND m.fecha_entrega_real IS NULL"), fn($m) => $tk && count(array_intersect($tk, Voz::palabras($m['nombre']))) >= min(2, count($tk)));
            foreach (array_slice($mats, 0, 8) as $m) {
                $nueva = date('Y-m-d', strtotime(max($m['fecha_entrega_prog'] ?: $hoy, $hoy) . ' +' . (int)$params['dias'] . ' days'));
                foreach (self::filas('SELECT actividad_id FROM actividad_materiales WHERE material_id = ' . (int)$m['id']) as $f) if ($esc->noAntesDe((int)$f['actividad_id'], $nueva)) $afectadas++;
            }
            $nota = $mats ? 'Se retrasa ' . (int)$params['dias'] . ' días la entrega de ' . count($mats) . ' material(es) que coinciden con «' . $params['objetivo'] . '»; ' . $afectadas . ' actividad(es) no pueden empezar antes de que lleguen.' : 'No se encontraron materiales pendientes de entrega que coincidan con «' . $params['objetivo'] . '»: el escenario quedó igual al caso base.';
        } elseif ($params['tipo'] === 'todo') {
            foreach ($acts as $id => $a) if ($esc->retrasarInicio($id, (int)$params['dias'])) $afectadas++;
            $nota = 'Se retrasa ' . (int)$params['dias'] . ' días hábiles el inicio de las ' . $afectadas . ' actividades que no han terminado ni tienen fecha fija.';
        } else $nota = 'Caso base: solo se aplica la incertidumbre de las duraciones.';

        $rb = $base->correr();
        $re = $esc->correr();
        $mb = $base->montecarlo(self::ITER, $u);
        $me = $esc->montecarlo(self::ITER, $u);
        $meta = $p->fecha_fin ?: $base->fecha($rb['fin']);
        $im = $base->idx($meta);
        $prob = fn(array $f) => round(count(array_filter($f, fn($x) => $x <= $im)) / count($f) * 100, 1);
        $pct = fn(Simulacion $s, array $f) => array_combine(['p10', 'p50', 'p80', 'p90'], array_map(fn($q) => $s->fecha(self::q($f, $q)), [.1, .5, .8, .9]));
        $porAct = [];
        foreach ($acts as $id => $a) {
            $i = $base->indiceDe($id);
            if ($re['ef'][$i] > $rb['ef'][$i]) $porAct[] = ['codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'base' => $base->fecha($rb['ef'][$i]), 'esc' => $esc->fecha($re['ef'][$i]), 'dias' => $base->dif($rb['ef'][$i], $re['ef'][$i])];
        }
        usort($porAct, fn($x, $y) => $y['dias'] <=> $x['dias']);
        arsort($me['criticidad']);
        $crit = [];
        foreach (array_slice($me['criticidad'], 0, 8, true) as $i => $c) { $a = $acts[$base->actividad($i)['id']]; $crit[] = ['codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'pct' => round($c / self::ITER * 100)]; }
        $datos = ['nota' => $nota, 'meta' => $meta, 'fin_base' => $base->fecha($rb['fin']), 'fin_esc' => $esc->fecha($re['fin']), 'dif' => $base->dif($rb['fin'], $re['fin']), 'afectadas' => $afectadas,
            'mc_base' => $pct($base, $mb['fines']) + ['prob' => $prob($mb['fines'])], 'mc_esc' => $pct($esc, $me['fines']) + ['prob' => $prob($me['fines'])], 'mas_afectadas' => array_slice($porAct, 0, 15), 'total_afectadas' => count($porAct), 'criticas' => $crit, 'u' => $u];

        $prompt = "Eres analista de planeación de proyectos de construcción. Explica el resultado de esta simulación a la dirección: qué cambia, qué tan probable es cumplir la fecha y qué hacer. Usa SOLO los datos; no inventes cifras.\n\nProyecto: {$p->nombre} · Fecha meta (fin del proyecto): " . self::fmt($meta)
            . "\nEscenario: $nota\nFin esperado sin cambios: " . self::fmt($datos['fin_base']) . " · con el escenario: " . self::fmt($datos['fin_esc']) . " (" . ($datos['dif'] >= 0 ? '+' : '') . $datos['dif'] . " días hábiles)"
            . "\nProbabilidad de terminar en la meta: sin cambios {$datos['mc_base']['prob']} % · con el escenario {$datos['mc_esc']['prob']} %\nFecha de fin con 50 % / 80 % de confianza (escenario): " . self::fmt($datos['mc_esc']['p50']) . " / " . self::fmt($datos['mc_esc']['p80'])
            . "\nActividades que se mueven: {$datos['total_afectadas']}\nActividades que más veces definen la fecha final: " . implode(', ', array_map(fn($c) => "{$c['codigo']} ({$c['pct']} %)", array_slice($crit, 0, 5)))
            . "\n\nDevuelve SOLO un JSON: {\"resumen\":\"1 o 2 párrafos\",\"recomendaciones\":[\"3 a 5 acciones para proteger la fecha\"]}";
        return ['datos' => $datos, 'lotes' => [['titulo' => 'Redactando la interpretación de la simulación', 'prompt' => $prompt]]];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        if (!empty($datos['vacio'])) return ['secciones' => [Informes::texto('Sin actividades', 'El proyecto no tiene actividades que simular.')]];
        $b = $datos['mc_base']; $e = $datos['mc_esc'];
        $resumen = Informes::limpiarTexto(self::dato($respuestas, 'resumen') ?? '', 2500);
        $ia = $resumen !== '';
        if (!$ia) $resumen = $datos['nota'] . "\n\nEl fin esperado pasa de " . self::fmt($datos['fin_base']) . ' a ' . self::fmt($datos['fin_esc']) . ' (' . ($datos['dif'] >= 0 ? '+' : '') . $datos['dif'] . ' días hábiles). La probabilidad de terminar el ' . self::fmt($datos['meta']) . " es de {$b['prob']} % sin cambios y {$e['prob']} % con el escenario.";
        $reco = self::textos(self::dato($respuestas, 'recomendaciones'), 6, 240);
        $secs = [
            Informes::kpis('Resultado', [['Fin esperado (sin cambios)', self::fmt($datos['fin_base'])], ['Fin esperado (escenario)', self::fmt($datos['fin_esc']), ($datos['dif'] >= 0 ? '+' : '') . $datos['dif'] . ' días hábiles'], ['Probabilidad de cumplir ' . self::fmt($datos['meta']), $e['prob'] . ' %', 'sin cambios: ' . $b['prob'] . ' %'], ['Actividades que se mueven', $datos['total_afectadas']]]),
            Informes::texto('Interpretación' . ($ia ? ' (redactada con IA, revísala)' : ' (automática)'), $resumen),
        ];
        if ($reco) $secs[] = Informes::lista('Recomendaciones', $reco);
        $secs[] = Informes::tabla('Fecha de fin según la confianza deseada', ['Confianza', 'Sin cambios', 'Escenario'], [['50 % (lo más probable)', self::fmt($b['p50']), self::fmt($e['p50'])], ['80 %', self::fmt($b['p80']), self::fmt($e['p80'])], ['90 %', self::fmt($b['p90']), self::fmt($e['p90'])], ['Probabilidad de terminar el ' . self::fmt($datos['meta']), $b['prob'] . ' %', $e['prob'] . ' %']]);
        $secs[] = Informes::tabla('Actividades más afectadas por el escenario', ['Código', 'Actividad', 'Fin sin cambios', 'Fin con escenario', 'Días hábiles de atraso'], array_map(fn($x) => [$x['codigo'], self::corto($x['nombre'], 70), self::fmt($x['base']), self::fmt($x['esc']), '+' . $x['dias']], $datos['mas_afectadas']));
        $secs[] = Informes::tabla('Actividades que más veces definen la fecha de fin (en las simulaciones)', ['Código', 'Actividad', '% de las simulaciones'], array_map(fn($c) => [$c['codigo'], self::corto($c['nombre'], 80), $c['pct'] . ' %'], $datos['criticas']));
        $secs[] = Informes::texto('Supuestos', 'Se simulan 1000 corridas variando la duración restante de cada actividad no terminada (distribución triangular: de −' . round($datos['u'] * 100) . ' % a +' . round($datos['u'] * 200) . ' %, porque el trabajo se atrasa más de lo que se adelanta). Lo que debía haber empezado y no ha empezado se reprograma desde hoy. Se respetan la jornada, los festivos y las relaciones entre actividades. No se modifica el programa: es un ejercicio de análisis.');
        return ['secciones' => $secs];
    }
}
