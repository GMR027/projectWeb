<?php

namespace Classes\Herramientas;

use Classes\Dependencias;
use Classes\Informes;
use Model\Proyecto;

/**
 * Detector de riesgos por patrón: aprende de TU historial cuánto tardan los submittals (por departamento), los materiales (por especialidad) y las partidas en
 * terminar contra lo programado, y avisa dónde lo pendiente va camino de repetir ese patrón. Es estadística (mediana y percentil 80), no IA: la IA solo la redacta.
 * Entre proyectos solo se usan cifras agregadas, nunca nombres.
 */
class RiesgosPatron extends Base
{
    private const MIN = 3;   // casos mínimos para confiar en un grupo; con menos se usa el general

    public static function id(): string { return 'riesgos_patron'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Riesgos por patrón', 'grupo' => 'planeacion', 'tipo' => 'riesgos_patron', 'aplicable' => false,
            'descripcion' => 'Compara lo pendiente con lo que ha tardado antes en tu propio historial (submittals por departamento, materiales por especialidad, partidas contra su programa) y señala dónde es probable un retraso. Estadística propia; la IA solo redacta el resumen.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'], 'parametros' => []];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Riesgos por patrón · ' . date('d/m/Y');
    }

    private static function pct(array $v, float $q): float
    {
        sort($v);
        $i = ($q / 100) * (count($v) - 1);
        $lo = (int)floor($i);
        $hi = (int)ceil($i);
        return round($v[$lo] + ($v[$hi] - $v[$lo]) * ($i - $lo), 1);
    }

    /** Mediana y P80 de un grupo si tiene casos suficientes; si no, null */
    private static function est(array $v): ?array
    {
        return count($v) >= self::MIN ? ['n' => count($v), 'p50' => self::pct($v, 50), 'p80' => self::pct($v, 80)] : null;
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $acts = Dependencias::actividades($pid);
        // --- submittals
        $porDepto = [];
        $todos = [];
        foreach (self::filas("SELECT departamento, DATEDIFF(fecha_respuesta, fecha) d FROM submittals WHERE fecha_respuesta IS NOT NULL AND fecha IS NOT NULL AND estatus <> 'enviado' AND DATEDIFF(fecha_respuesta, fecha) BETWEEN 0 AND 400") as $f) { $porDepto[trim((string)$f['departamento'])][] = (int)$f['d']; $todos[] = (int)$f['d']; }
        $estGen = self::est($todos);
        $inicioPorSub = [];
        foreach (self::filas("SELECT sm.submittal_id, MIN(a.fecha_inicio) ini FROM submittal_materiales sm JOIN actividad_materiales am ON am.material_id = sm.material_id JOIN actividades a ON a.id = am.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid GROUP BY sm.submittal_id") as $f) $inicioPorSub[(int)$f['submittal_id']] = $f['ini'];
        $riesgoSub = [];
        foreach (self::filas("SELECT id, COALESCE(NULLIF(numero_submittal,''), CONCAT('#', id)) n, departamento, fecha, DATEDIFF('$hoy', fecha) trans FROM submittals WHERE proyecto_id = $pid AND estatus = 'enviado' AND fecha IS NOT NULL") as $f) {
            $est = self::est($porDepto[trim((string)$f['departamento'])] ?? []) ?: $estGen;
            if (!$est) continue;
            $ini = $inicioPorSub[(int)$f['id']] ?? null;
            $margen = $ini ? (int)floor((strtotime($ini) - strtotime($hoy)) / 86400) : null;
            $trans = (int)$f['trans'];
            $nivel = $trans > $est['p80'] ? 'alta' : ($trans > $est['p50'] ? 'media' : 'baja');
            if ($margen !== null && $margen < max(0, $est['p80'] - $trans)) $nivel = $nivel === 'baja' ? 'media' : 'alta';
            if ($nivel === 'baja') continue;
            $riesgoSub[] = ['n' => $f['n'], 'depto' => $f['departamento'] ?: '(sin departamento)', 'trans' => $trans, 'p50' => $est['p50'], 'p80' => $est['p80'], 'casos' => $est['n'], 'margen' => $margen, 'nivel' => $nivel];
        }
        // --- materiales (compra real → entrega real)
        $porEsp = [];
        $todosM = [];
        foreach (self::filas("SELECT especialidad, DATEDIFF(fecha_entrega_real, fecha_compra_real) d FROM materiales WHERE fecha_entrega_real IS NOT NULL AND fecha_compra_real IS NOT NULL AND DATEDIFF(fecha_entrega_real, fecha_compra_real) BETWEEN 0 AND 365") as $f) { $porEsp[trim((string)$f['especialidad'])][] = (int)$f['d']; $todosM[] = (int)$f['d']; }
        $estGenM = self::est($todosM);
        $riesgoMat = [];
        foreach (self::filas("SELECT m.id, m.nombre, m.especialidad, m.compra, m.fecha_compra_real, (SELECT MIN(a.fecha_inicio) FROM actividad_materiales am JOIN actividades a ON a.id = am.actividad_id WHERE am.material_id = m.id) ini FROM materiales m WHERE m.proyecto_id = $pid AND m.fecha_entrega_real IS NULL") as $m) {
            $est = self::est($porEsp[trim((string)$m['especialidad'])] ?? []) ?: $estGenM;
            if (!$est || !$m['ini']) continue;
            $margen = (int)floor((strtotime($m['ini']) - strtotime($hoy)) / 86400);
            if ($m['compra'] === 'comprado' && $m['fecha_compra_real']) {
                $trans = (int)floor((strtotime($hoy) - strtotime($m['fecha_compra_real'])) / 86400);
                $llega = date('Y-m-d', strtotime($m['fecha_compra_real'] . " +{$est['p80']} days"));
                $nivel = $trans > $est['p80'] ? 'alta' : ($llega > $m['ini'] ? 'media' : 'baja');
                $txt = "Comprado hace $trans d; lo usual es {$est['p50']} d (80 % de los casos ≤ {$est['p80']} d)";
            } else {
                $falta = $est['p80'];
                $nivel = $margen < $est['p50'] ? 'alta' : ($margen < $falta ? 'media' : 'baja');
                $txt = "Sin comprar; tarda ~{$est['p50']} d (80 % ≤ {$est['p80']} d) y la actividad inicia en $margen d";
            }
            if ($nivel === 'baja') continue;
            $riesgoMat[] = ['n' => $m['nombre'], 'esp' => $m['especialidad'] ?: '(sin especialidad)', 'txt' => $txt, 'ini' => $m['ini'], 'margen' => $margen, 'casos' => $est['n'], 'nivel' => $nivel];
        }
        // --- partidas: atraso histórico al terminar
        $term = [];
        foreach (self::filas("SELECT a.id, a.partida_id, a.fecha_fin, MIN(h.fecha) real_fin FROM actividades a JOIN partidas pa ON pa.id = a.partida_id JOIN actividad_avance h ON h.actividad_id = a.id AND h.progreso >= 100 WHERE pa.proyecto_id = $pid AND a.hito = 0 GROUP BY a.id") as $f) $term[(int)$f['partida_id']][] = (int)floor((strtotime($f['real_fin']) - strtotime($f['fecha_fin'])) / 86400);
        $todasD = array_merge(...array_values($term ?: [[]]));
        $riesgoPart = [];
        $porPartida = [];
        foreach ($acts as $a) if (!(int)$a['hito'] && (int)$a['progreso'] < 100) $porPartida[(int)$a['partida_id']][] = $a;
        foreach ($porPartida as $pidp => $lista) {
            $d = $term[$pidp] ?? [];
            $prom = count($d) >= self::MIN ? array_sum($d) / count($d) : (count($todasD) >= self::MIN ? array_sum($todasD) / count($todasD) : null);
            if ($prom === null || $prom < 2) continue;
            $a0 = $lista[0];
            $riesgoPart[] = ['partida' => $a0['partida_codigo'] . ' ' . self::corto($a0['partida'], 50), 'atraso' => round($prom, 1), 'casos' => count($d) >= self::MIN ? count($d) : count($todasD), 'propio' => count($d) >= self::MIN, 'pendientes' => count($lista), 'fin' => max(array_column($lista, 'fecha_fin'))];
        }
        usort($riesgoPart, fn($x, $y) => $y['atraso'] <=> $x['atraso']);
        $ord = ['alta' => 0, 'media' => 1];
        usort($riesgoSub, fn($x, $y) => [$ord[$x['nivel']], $y['trans']] <=> [$ord[$y['nivel']], $x['trans']]);
        usort($riesgoMat, fn($x, $y) => [$ord[$x['nivel']], $x['margen']] <=> [$ord[$y['nivel']], $y['margen']]);
        $datos = ['submittals' => $riesgoSub, 'materiales' => $riesgoMat, 'partidas' => $riesgoPart, 'muestras' => ['submittals' => count($todos), 'materiales' => count($todosM), 'partidas' => count($todasD)]];

        $lin = array_merge(array_map(fn($x) => "[{$x['nivel']}] Submittal {$x['n']} ({$x['depto']}): lleva {$x['trans']} d sin respuesta; lo usual {$x['p50']} d (P80 {$x['p80']} d)", array_slice($riesgoSub, 0, 6)),
            array_map(fn($x) => "[{$x['nivel']}] Material " . self::corto($x['n'], 45) . ": {$x['txt']}", array_slice($riesgoMat, 0, 6)), array_map(fn($x) => "[partida] {$x['partida']}: suele terminar {$x['atraso']} d tarde ({$x['casos']} casos)", array_slice($riesgoPart, 0, 4)));
        $prompt = "Eres analista de riesgos de proyectos de construcción. Con estos riesgos calculados por patrón histórico redacta un resumen para la dirección: dónde está el peligro y qué hacer primero. Usa SOLO los datos; no inventes cifras.\n\nProyecto: {$p->nombre} · Hoy: " . self::fmt($hoy)
            . "\nBase estadística: {$datos['muestras']['submittals']} submittals respondidos, {$datos['muestras']['materiales']} materiales entregados, {$datos['muestras']['partidas']} actividades terminadas con fecha real.\n\n## RIESGOS\n" . ($lin ? implode("\n", $lin) : '(ninguno)')
            . "\n\nDevuelve SOLO un JSON: {\"resumen\":\"1 o 2 párrafos\",\"acciones\":[\"3 a 5 acciones concretas, de la más urgente a la menos\"]}";
        return ['datos' => $datos, 'lotes' => $lin ? [['titulo' => 'Redactando el análisis de riesgos', 'prompt' => $prompt]] : []];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $s = $datos['submittals']; $m = $datos['materiales']; $pa = $datos['partidas']; $mu = $datos['muestras'];
        $alta = count(array_filter(array_merge($s, $m), fn($x) => $x['nivel'] === 'alta'));
        $resumen = Informes::limpiarTexto(self::dato($respuestas, 'resumen') ?? '', 2500);
        $ia = $resumen !== '';
        if (!$ia) $resumen = ($s || $m || $pa) ? "Según el historial, hay $alta riesgo(s) alto(s) entre submittals y materiales, " . count($s) . ' submittal(s) y ' . count($m) . ' material(es) con riesgo en total, y ' . count($pa) . ' partida(s) que suelen terminar tarde.' : 'Con el historial disponible no se encontraron riesgos por patrón' . (($mu['submittals'] + $mu['materiales'] + $mu['partidas']) < 9 ? ' (hay pocos datos históricos: el análisis mejora conforme se acumulan submittals respondidos, entregas reales y avances registrados)' : '') . '.';
        $acc = self::textos(self::dato($respuestas, 'acciones'), 6, 240);
        $nv = ['alta' => 'Alta', 'media' => 'Media'];
        $secs = [
            Informes::kpis('Riesgos por patrón', [['Riesgo alto', $alta], ['Submittals en riesgo', count($s)], ['Materiales en riesgo', count($m)], ['Partidas que suelen atrasarse', count($pa)]]),
            Informes::texto('Resumen' . ($ia ? ' (redactado con IA, revísalo)' : ' (automático)'), $resumen),
        ];
        if ($acc) $secs[] = Informes::lista('Acciones sugeridas', $acc);
        $secs[] = Informes::tabla('Submittals pendientes en riesgo', ['Nivel', 'Submittal', 'Departamento', 'Días sin respuesta', 'Lo usual (mediana / P80)', 'Casos', 'Días para que inicie la actividad'], array_map(fn($x) => [$nv[$x['nivel']], $x['n'], $x['depto'], $x['trans'], $x['p50'] . ' / ' . $x['p80'] . ' d', $x['casos'], $x['margen'] ?? '—'], $s));
        $secs[] = Informes::tabla('Materiales en riesgo', ['Nivel', 'Material', 'Especialidad', 'Situación', 'Casos'], array_map(fn($x) => [$nv[$x['nivel']], self::corto($x['n'], 70), $x['esp'], $x['txt'], $x['casos']], array_slice($m, 0, 40)));
        $secs[] = Informes::tabla('Partidas que suelen terminar tarde', ['Partida', 'Atraso medio (días)', 'Base', 'Actividades pendientes', 'Fin programado'], array_map(fn($x) => [$x['partida'], $x['atraso'], ($x['propio'] ? 'esta partida' : 'todo el proyecto') . ' · ' . $x['casos'] . ' casos', $x['pendientes'], self::fmt($x['fin'])], $pa));
        $secs[] = Informes::texto('Cómo se calcula', "Se compara lo pendiente contra el tiempo que tardaron casos anteriores: submittals respondidos ({$mu['submittals']} casos, por departamento), materiales comprados y entregados ({$mu['materiales']} casos, por especialidad) y actividades terminadas contra su fecha programada ({$mu['partidas']} casos, por partida). Un grupo necesita al menos 3 casos; con menos se usa el promedio general. «Alta» = ya pasó el 80 % de los casos o no alcanzará a llegar a tiempo; «Media» = pasó la mediana. Entre proyectos solo se usan cifras agregadas.");
        return ['secciones' => $secs];
    }
}
