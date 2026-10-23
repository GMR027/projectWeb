<?php

namespace Classes\Herramientas;

use Classes\CurvaS;
use Classes\Dependencias;
use Classes\Informes;
use Classes\Programador;
use Model\Proyecto;

/** Resumen ejecutivo semanal para el cliente: avance contra programa, logros, atrasos, próximas semanas, riesgos y compras. Se exporta a PDF */
class ResumenSemanal extends Base
{
    public static function id(): string { return 'resumen_semanal'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Resumen ejecutivo semanal', 'grupo' => 'campo', 'tipo' => 'resumen_semanal', 'aplicable' => false,
            'descripcion' => 'Arma el resumen de la semana con la Curva S, las actividades terminadas e iniciadas, los atrasos, lo que viene, los obstáculos y los submittals y materiales pendientes. La IA redacta el resumen ejecutivo; el informe se exporta a PDF.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [['nombre' => 'fecha', 'etiqueta' => 'Cualquier día de la semana', 'tipo' => 'fecha', 'defecto' => date('Y-m-d')]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        $l = self::lunes($params['fecha']);
        return 'Resumen semanal ' . date('d/m', strtotime($l)) . ' al ' . date('d/m/Y', strtotime($l . ' +6 days'));
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $lun = self::lunes($params['fecha']);
        $dom = date('Y-m-d', strtotime($lun . ' +6 days'));
        $hoy = min($dom, date('Y-m-d'));
        $acts = Dependencias::actividades($pid);
        $curva = CurvaS::calcular($p);
        $per = null;
        foreach ($curva['periodos'] ?? [] as $x) if ($x['inicio'] <= $dom) $per = $x;
        $terminadas = $iniciadas = [];
        foreach (self::filas("SELECT h.actividad_id, MIN(h.fecha) primera, MAX(CASE WHEN h.progreso >= 100 THEN h.fecha END) fin FROM actividad_avance h JOIN actividades a ON a.id = h.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid GROUP BY h.actividad_id") as $x) {
            $a = $acts[(int)$x['actividad_id']] ?? null;
            if (!$a) continue;
            if ($x['fin'] && $x['fin'] >= $lun && $x['fin'] <= $dom) $terminadas[] = $a;
            elseif ($x['primera'] >= $lun && $x['primera'] <= $dom) $iniciadas[] = $a;
        }
        $atrasadas = array_values(array_filter($acts, fn($a) => !(int)$a['hito'] && (int)$a['progreso'] < 100 && $a['fecha_fin'] < $hoy));
        usort($atrasadas, fn($x, $y) => strcmp($x['fecha_fin'], $y['fecha_fin']));
        $d14 = date('Y-m-d', strtotime($dom . ' +14 days'));
        $proximas = array_values(array_filter($acts, fn($a) => (int)$a['progreso'] < 100 && $a['fecha_inicio'] > $dom && $a['fecha_inicio'] <= $d14));
        usort($proximas, fn($x, $y) => strcmp($x['fecha_inicio'], $y['fecha_inicio']));
        $hitos = array_values(array_filter($acts, fn($a) => (int)$a['hito'] && $a['fecha_inicio'] > $dom && $a['fecha_inicio'] <= date('Y-m-d', strtotime($dom . ' +30 days'))));
        $obst = array_slice(Programador::contexto($p, $hoy)['obstaculos'], 0, 8);
        $submittals = self::filas("SELECT COALESCE(NULLIF(numero_submittal,''), CONCAT('#', id)) n, fecha, DATEDIFF('$hoy', fecha) dias FROM submittals WHERE proyecto_id = $pid AND estatus = 'enviado' ORDER BY fecha LIMIT 8");
        $entregas = self::filas("SELECT nombre, fecha_entrega_prog FROM materiales WHERE proyecto_id = $pid AND fecha_entrega_real IS NULL AND fecha_entrega_prog BETWEEN '$hoy' AND '$d14' ORDER BY fecha_entrega_prog LIMIT 10");
        $restr = (int)(self::filas("SELECT COUNT(*) n FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND r.estado = 'activa'")[0]['n'] ?? 0);
        $dep = Dependencias::datos($p);
        $datos = ['lunes' => $lun, 'domingo' => $dom, 'periodo' => $per, 'resumen_curva' => $curva['resumen'] ?? [], 'terminadas' => array_map(fn($a) => $a['codigo'] . ' ' . $a['nombre'], $terminadas), 'iniciadas' => array_map(fn($a) => $a['codigo'] . ' ' . $a['nombre'], $iniciadas),
            'atrasadas' => array_map(fn($a) => ['c' => $a['codigo'], 'n' => $a['nombre'], 'fin' => $a['fecha_fin'], 'p' => (int)$a['progreso']], $atrasadas), 'proximas' => array_map(fn($a) => ['c' => $a['codigo'], 'n' => $a['nombre'], 'ini' => $a['fecha_inicio']], $proximas),
            'hitos' => array_map(fn($a) => ['n' => $a['nombre'], 'f' => $a['fecha_inicio']], $hitos), 'obstaculos' => $obst, 'submittals' => $submittals, 'entregas' => $entregas, 'restricciones' => $restr, 'criticas' => $dep['resumen']['criticas'] ?? 0,
            'total' => count($acts), 'completadas' => count(array_filter($acts, fn($a) => (int)$a['progreso'] >= 100))];
        $r = $datos['resumen_curva'];
        $prompt = "Eres el gerente de proyecto de una obra y escribes el resumen ejecutivo semanal para el cliente. Redacta en español, tono profesional y claro, SOLO con los datos de abajo; no inventes cifras ni causas.\n\n"
            . "Proyecto: {$p->nombre} · Semana del " . self::fmt($lun) . " al " . self::fmt($dom) . "\n"
            . "Avance programado: " . ($per ? $per['plan'] . '%' : 's/d') . " · Avance real: " . ($per && $per['real'] !== null ? $per['real'] . '%' : 's/d') . (isset($r['spi']) && $r['spi'] !== null ? " · SPI {$r['spi']}" : '') . "\n"
            . "Actividades terminadas esta semana: " . count($terminadas) . " · iniciadas: " . count($iniciadas) . " · atrasadas: " . count($atrasadas) . " · en ruta crítica: {$datos['criticas']}\n"
            . "\n## TERMINADAS\n" . ($terminadas ? implode("\n", array_map(fn($t) => self::corto($t, 90), array_slice($datos['terminadas'], 0, 8))) : '(ninguna)')
            . "\n\n## MÁS ATRASADAS\n" . ($atrasadas ? implode("\n", array_map(fn($a) => "{$a['c']} {$a['n']} (debió terminar " . self::fmt($a['fin']) . ", avance {$a['p']}%)", array_map(fn($x) => ['c' => $x['codigo'], 'n' => self::corto($x['nombre'], 70), 'fin' => $x['fecha_fin'], 'p' => (int)$x['progreso']], array_slice($atrasadas, 0, 6)))) : '(ninguna)')
            . "\n\n## OBSTÁCULOS PRINCIPALES\n" . ($obst ? implode("\n", array_map(fn($o) => "[{$o['severidad']}] " . self::corto($o['descripcion'], 140), array_slice($obst, 0, 6))) : '(ninguno)')
            . "\n\n## PRÓXIMOS 14 DÍAS\n" . ($proximas ? implode("\n", array_map(fn($a) => self::fmt($a['fecha_inicio']) . ' ' . self::corto($a['nombre'], 70), array_slice($proximas, 0, 6))) : '(sin inicios)')
            . "\n\n## PENDIENTES DE COMPRAS\nSubmittals en revisión: " . count($submittals) . " · entregas de materiales en 14 días: " . count($entregas) . " · restricciones activas: $restr"
            . "\n\nDevuelve SOLO un JSON: {\"resumen\":\"2 a 3 párrafos de resumen ejecutivo\",\"recomendaciones\":[\"3 a 5 acciones concretas para la dirección\"]}";
        return ['datos' => $datos, 'lotes' => [['titulo' => 'Redactando el resumen ejecutivo', 'prompt' => $prompt]]];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $per = $datos['periodo'];
        $r = $datos['resumen_curva'];
        $resumen = Informes::limpiarTexto(self::dato($respuestas, 'resumen') ?? '', 3500);
        $ia = $resumen !== '';
        if (!$ia) {
            $desv = $per && $per['real'] !== null ? round($per['real'] - $per['plan'], 1) : null;
            $resumen = 'Del ' . self::fmt($datos['lunes']) . ' al ' . self::fmt($datos['domingo']) . ($per ? ": el avance programado es {$per['plan']}%" . ($per['real'] !== null ? " y el real {$per['real']}%" . ($desv !== null ? ' (' . ($desv >= 0 ? '+' : '') . "$desv puntos)" : '') : '') . '.' : '.')
                . "\n\nSe terminaron " . count($datos['terminadas']) . ' actividad(es) y se iniciaron ' . count($datos['iniciadas']) . '. Hay ' . count($datos['atrasadas']) . ' actividad(es) atrasadas, ' . $datos['criticas'] . ' en ruta crítica y ' . $datos['restricciones'] . ' restricción(es) activa(s).';
        }
        $reco = self::textos(self::dato($respuestas, 'recomendaciones'), 6, 260);
        $secs = [
            Informes::kpis('Indicadores', [['Avance programado', $per ? $per['plan'] . '%' : 's/d'], ['Avance real', $per && $per['real'] !== null ? $per['real'] . '%' : 's/d'], ['SPI', isset($r['spi']) && $r['spi'] !== null ? $r['spi'] : 's/d', 'real ÷ programado'],
                ['Terminadas', count($datos['terminadas']), 'esta semana'], ['Iniciadas', count($datos['iniciadas']), 'esta semana'], ['Atrasadas', count($datos['atrasadas'])], ['Ruta crítica', $datos['criticas'], 'actividades'], ['Completadas', $datos['completadas'] . ' / ' . $datos['total']]]),
            Informes::texto('Resumen ejecutivo', $resumen),
        ];
        if ($reco) $secs[] = Informes::lista('Recomendaciones', $reco);
        $secs[] = Informes::lista('Terminadas esta semana', $datos['terminadas']);
        $secs[] = Informes::lista('Iniciadas esta semana', $datos['iniciadas']);
        $secs[] = Informes::tabla('Actividades atrasadas (las 15 más antiguas)', ['Código', 'Actividad', 'Debió terminar', 'Avance'], array_map(fn($a) => [$a['c'], self::corto($a['n'], 90), self::fmt($a['fin']), $a['p'] . '%'], array_slice($datos['atrasadas'], 0, 15)));
        $secs[] = Informes::tabla('Próximos 14 días', ['Código', 'Actividad', 'Inicio'], array_map(fn($a) => [$a['c'], self::corto($a['n'], 90), self::fmt($a['ini'])], array_slice($datos['proximas'], 0, 15)));
        if ($datos['hitos']) $secs[] = Informes::tabla('Hitos próximos (30 días)', ['Hito', 'Fecha'], array_map(fn($h) => [self::corto($h['n'], 90), self::fmt($h['f'])], $datos['hitos']));
        $secs[] = Informes::lista('Obstáculos principales', array_map(fn($o) => '[' . $o['severidad'] . '] ' . self::corto($o['descripcion'], 180), $datos['obstaculos']));
        $secs[] = Informes::tabla('Submittals en revisión', ['Submittal', 'Enviado', 'Días sin respuesta'], array_map(fn($s) => [$s['n'], self::fmt($s['fecha']), $s['dias']], $datos['submittals']));
        $secs[] = Informes::tabla('Entregas de materiales (14 días)', ['Material', 'Entrega programada'], array_map(fn($e) => [self::corto($e['nombre'], 90), self::fmt($e['fecha_entrega_prog'])], $datos['entregas']));
        return ['secciones' => $secs];
    }
}
