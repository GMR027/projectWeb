<?php

namespace Classes;

use Classes\Herramientas\CausasRetraso;
use Classes\Herramientas\RiesgosPatron;
use Model\Proyecto;

/**
 * Análisis inteligente: panel principal del Centro de IA. Cada tema (herramienta) se calcula por separado con `seccion()` para poder
 * cargarlo o recargarlo por partes. Todo lo calcula el servidor (sin modelo): lo pendiente sale de `CentroIA::pendientes()` y los riesgos
 * por patrón y las causas de retraso de `preparar()` de sus herramientas. No cambia datos: aceptar/rechazar delega en el Centro de IA.
 */
class AnalisisInteligente
{
    /** clave => [título, icono, color, clave de uso en Revisiones] */
    public const TEMAS = [
        'inconsistencias' => ['Inconsistencias', 'fa-triangle-exclamation', '#ea580c', 'inconsistencias'],
        'materiales' => ['Análisis de materiales', 'fa-boxes-stacked', '#0284c7', 'materiales'],
        'programador' => ['Programador', 'fa-wand-magic-sparkles', '#7c3aed', 'programador'],
        'normativa' => ['Normativa', 'fa-scale-balanced', '#059669', 'normativa'],
        'riesgos' => ['Riesgos por patrón', 'fa-chart-line', '#dc2626', 'informe:riesgos_patron'],
        'causas' => ['Causas de retraso', 'fa-magnifying-glass-chart', '#d97706', 'informe:causas_retraso'],
    ];

    private const SEV = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
    private const CAUSAS = ['material' => 'Material', 'submittal' => 'Submittal / aprobación', 'restriccion' => 'Restricción', 'predecesora' => 'Predecesora sin terminar', 'clima' => 'Clima o paro técnico', 'bitacora' => 'Bitácora', 'sin_causa' => 'Sin causa registrada'];

    public static function catalogo(): array
    {
        $o = [];
        foreach (self::TEMAS as $k => [$titulo, $icono, $color]) $o[] = ['clave' => $k, 'titulo' => $titulo, 'icono' => $icono, 'color' => $color];
        return $o;
    }

    /** Una sección ya lista para pintar: total, segmentos del gráfico, elementos y estado de uso de la herramienta */
    public static function seccion(Proyecto $p, string $clave): ?array
    {
        if (!isset(self::TEMAS[$clave])) return null;
        $pid = (int)$p->id;
        [$titulo, $icono, $color, $claveUso] = self::TEMAS[$clave];
        $s = ['clave' => $clave, 'titulo' => $titulo, 'icono' => $icono, 'color' => $color, 'resumen' => '', 'segmentos' => [], 'items' => [], 'propuestas' => false, 'url' => '', 'total' => 0];
        match ($clave) {
            'riesgos' => self::riesgos($p, $s),
            'causas' => self::causas($p, $s),
            default => self::pendientes($p, $clave, $s),
        };
        $s['uso'] = ['estado' => 'sin_ejecutar', 'ejecutada' => null];
        foreach (Revisiones::herramientas($pid) as $h) {
            if ($h['clave'] === $claveUso) { $s['uso'] = ['estado' => $h['estado'], 'ejecutada' => $h['ejecutada']]; break; }
        }
        $s['generado'] = date('H:i:s');
        return $s;
    }

    private static function pendientes(Proyecto $p, string $clave, array &$s): void
    {
        $pid = (int)$p->id;
        $r = CentroIA::pendientes($p, ['herramienta' => $clave]);
        $porSev = array_fill_keys(array_keys(self::SEV), 0);
        foreach ($r['items'] as $x) {
            $porSev[$x['severidad']]++;
            $s['items'][] = ['id' => $x['id'], 'titulo' => $x['titulo'], 'texto' => $x['descripcion'], 'extra' => $x['solucion'], 'etiqueta' => self::SEV[$x['severidad']], 'sev' => $x['severidad'],
                'meta' => $x['categoria'] . ' · ' . ($x['origen'] === 'ia' ? 'detectada por la IA' : 'detectada por el sistema'), 'url' => $x['url'], 'resoluble' => $x['resoluble'], 'nota' => $x['accion'] === 'nota'];
        }
        foreach (self::SEV as $k => $n) if ($porSev[$k]) $s['segmentos'][] = ['etiqueta' => $n, 'valor' => $porSev[$k], 'clave' => $k];
        $s['total'] = count($r['items']);
        $s['propuestas'] = $clave !== 'programador';
        $s['url'] = ['inconsistencias' => '/inconsistencias', 'materiales' => '/analisis-materiales', 'programador' => '/programador', 'normativa' => '/normativa/vinculos'][$clave] . '?proyecto=' . $pid;
        $s['resumen'] = $s['total'] ? $s['total'] . ' propuesta(s) pendiente(s) de revisar. Nada se aplica sin que las aceptes.' : 'Sin propuestas pendientes.';
    }

    private static function riesgos(Proyecto $p, array &$s): void
    {
        $d = RiesgosPatron::preparar($p, [])['datos'];
        foreach ($d['submittals'] as $x) $s['items'][] = ['titulo' => 'Submittal ' . $x['n'], 'texto' => "{$x['depto']}: lleva {$x['trans']} d sin respuesta; lo usual {$x['p50']} d (P80 {$x['p80']} d, {$x['casos']} casos).", 'etiqueta' => ucfirst($x['nivel']), 'sev' => $x['nivel'], 'meta' => 'Submittal', 'tipos' => ['submittals']];
        foreach ($d['materiales'] as $x) $s['items'][] = ['titulo' => $x['n'], 'texto' => $x['txt'], 'etiqueta' => ucfirst($x['nivel']), 'sev' => $x['nivel'], 'meta' => 'Material · ' . $x['esp'], 'tipos' => ['materiales']];
        foreach ($d['partidas'] as $x) $s['items'][] = ['titulo' => $x['partida'], 'texto' => "Suele terminar {$x['atraso']} d tarde ({$x['casos']} casos); {$x['pendientes']} actividad(es) pendiente(s).", 'etiqueta' => 'Partida', 'sev' => 'media', 'meta' => 'Partida', 'tipos' => ['partidas']];
        foreach ([['Submittals', count($d['submittals'])], ['Materiales', count($d['materiales'])], ['Partidas', count($d['partidas'])]] as [$n, $v]) if ($v) $s['segmentos'][] = ['etiqueta' => $n, 'valor' => $v, 'clave' => mb_strtolower($n)];
        $s['total'] = count($s['items']);
        $alta = count(array_filter(array_merge($d['submittals'], $d['materiales']), fn($x) => $x['nivel'] === 'alta'));
        $m = $d['muestras'];
        $s['resumen'] = $s['total'] ? "$alta riesgo(s) alto(s) entre submittals y materiales. Calculado con {$m['submittals']} submittals respondidos, {$m['materiales']} materiales entregados y {$m['partidas']} actividades terminadas." : 'Sin riesgos por patrón con el historial disponible' . (($m['submittals'] + $m['materiales'] + $m['partidas']) < 9 ? ' (aún hay pocos datos históricos).' : '.');
        $s['url'] = '/informes-ia?proyecto=' . (int)$p->id;
    }

    private static function causas(Proyecto $p, array &$s): void
    {
        $d = CausasRetraso::preparar($p, ['dias' => 1])['datos'];
        foreach ($d['actividades'] as $x) {
            $s['items'][] = ['titulo' => $x['codigo'] . ' ' . $x['nombre'], 'texto' => implode(' · ', array_map(fn($c) => self::CAUSAS[$c[0]] . ': ' . $c[1], $x['causas'])), 'etiqueta' => $x['atraso'] . ' d', 'sev' => $x['atraso'] > 14 ? 'alta' : ($x['atraso'] > 5 ? 'media' : 'baja'),
                'meta' => 'Avance ' . $x['progreso'] . '%', 'tipos' => $x['tipos']];
        }
        foreach ($d['por_causa'] as $k => $v) if ($v) $s['segmentos'][] = ['etiqueta' => self::CAUSAS[$k], 'valor' => (int)$v, 'clave' => $k];
        $s['total'] = (int)$d['total'];
        $top = $d['por_causa'];
        arsort($top);
        $top = array_slice(array_filter($top), 0, 3, true);
        $s['resumen'] = $s['total'] ? $s['total'] . ' actividad(es) atrasada(s). Causas más frecuentes: ' . implode(', ', array_map(fn($k, $v) => mb_strtolower(self::CAUSAS[$k]) . " ($v)", array_keys($top), $top)) . '. Una actividad puede tener varias causas.' : 'No hay actividades atrasadas.';
        $s['url'] = '/informes-ia?proyecto=' . (int)$p->id;
    }
}
