<?php

namespace Classes\Herramientas;

use Classes\Calendario;
use Classes\Clima;
use Classes\Dependencias;
use Classes\Informes;
use Classes\Inconsistencias;
use Model\Proyecto;

/**
 * Clima y calendario inteligente: con el pronóstico del lugar del proyecto señala los días de lluvia o viento que afectan actividades de exterior, registra las anomalías
 * recientes (días perdidos probables) y propone marcar como no hábiles los días de lluvia fuerte. Las propuestas se aceptan o rechazan en Análisis inteligente.
 */
class ClimaCalendario extends Base
{
    public static function id(): string { return 'clima'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Clima y calendario', 'grupo' => 'planeacion', 'tipo' => 'clima', 'aplicable' => false,
            'descripcion' => 'Consulta el pronóstico del lugar del proyecto (Open-Meteo; solo se envía el nombre del lugar o las coordenadas), marca los días de lluvia o viento que frenan el trabajo de exterior, registra las anomalías recientes y propone días no hábiles. Nada cambia hasta que aceptes cada propuesta.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [['nombre' => 'lugar', 'etiqueta' => 'Coordenadas «lat,lon» (opcional; si no, se usa la ubicación del proyecto)', 'tipo' => 'texto', 'defecto' => '', 'max' => 40],
                ['nombre' => 'dias', 'etiqueta' => 'Días de pronóstico (7 a 16)', 'tipo' => 'numero', 'defecto' => 14, 'min' => 7, 'max' => 16],
                ['nombre' => 'proponer', 'etiqueta' => '¿Proponer días no hábiles por lluvia fuerte?', 'tipo' => 'opcion', 'opciones' => ['si' => 'Sí, como propuestas por aceptar', 'no' => 'No, solo informar'], 'defecto' => 'si']]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Clima y calendario · ' . date('d/m/Y');
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $fin = date('Y-m-d', strtotime('+' . ((int)$params['dias'] - 1) . ' days'));
        $act = Clima::actualizar($p, $params['lugar']);
        $cal = new Calendario($pid);
        $acts = Dependencias::actividades($pid);
        $ext = array_filter($acts, fn($a) => (int)$a['progreso'] < 100 && !(int)$a['hito'] && Clima::esExterior($a['nombre']));
        $enDia = fn(string $d) => array_values(array_filter($ext, fn($a) => $a['fecha_inicio'] <= $d && $a['fecha_fin'] >= $d));
        $nohab = array_column(self::filas("SELECT fecha FROM calendario_festivos WHERE proyecto_id = $pid"), 'fecha');
        $prono = [];
        $propuestas = [];
        foreach (Clima::dias($pid, $hoy, $fin) as $d) {
            if ($d['tipo'] !== 'pronostico') continue;
            $c = Clima::condicion($d);
            $afect = $c && $cal->esHabil($d['fecha']) ? array_map(fn($a) => $a['codigo'] . ' ' . self::corto($a['nombre'], 45), array_slice($enDia($d['fecha']), 0, 6)) : [];
            $prono[] = ['fecha' => $d['fecha'], 'precip' => (float)$d['precip_mm'], 'prob' => $d['prob'], 'viento' => (float)$d['viento_kmh'], 'tmax' => $d['tmax'], 'tmin' => $d['tmin'], 'cond' => $c, 'habil' => $cal->esHabil($d['fecha']), 'afectadas' => $afect, 'n_afect' => count($c ? $enDia($d['fecha']) : [])];
            if ($c && Clima::esFuerte($d) && $cal->esHabil($d['fecha']) && !in_array($d['fecha'], $nohab, true)) $propuestas[] = $d;
        }
        $anom = [];
        foreach (Clima::dias($pid, date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('-1 day'))) as $d) {
            if ($d['tipo'] !== 'real' || !($c = Clima::condicion($d)) || !$cal->esHabil($d['fecha'])) continue;
            $a = $enDia($d['fecha']);
            $anom[] = ['fecha' => $d['fecha'], 'precip' => (float)$d['precip_mm'], 'viento' => (float)$d['viento_kmh'], 'cond' => $c, 'n_afect' => count($a), 'registrado' => in_array($d['fecha'], $nohab, true)];
        }
        $datos = ['actualizacion' => $act, 'pronostico' => $prono, 'propuestas' => $propuestas, 'anomalias' => $anom, 'exteriores' => count($ext), 'perdidos' => count(array_filter($anom, fn($x) => $x['n_afect'] > 0 && !$x['registrado']))];
        $malos = array_filter($prono, fn($x) => $x['cond']);
        $prompt = "Eres planificador de obra. Con el pronóstico del lugar del proyecto, explica qué días son problemáticos para los trabajos de exterior y cómo reorganizar el trabajo. Usa SOLO los datos; no inventes pronósticos.\n\nProyecto: {$p->nombre} · Lugar: " . ($act['lugar'] ?: 'sin ubicar') . " · Actividades de exterior pendientes: " . count($ext)
            . "\n\n## DÍAS ADVERSOS PRONOSTICADOS\n" . ($malos ? implode("\n", array_map(fn($x) => self::fmt($x['fecha']) . ": {$x['cond']} (lluvia {$x['precip']} mm" . ($x['prob'] !== null ? ", prob. {$x['prob']} %" : '') . ", viento {$x['viento']} km/h); actividades de exterior en curso: {$x['n_afect']}" . ($x['afectadas'] ? ' (' . implode('; ', array_slice($x['afectadas'], 0, 3)) . ')' : ''), array_slice($malos, 0, 10))) : '(ninguno)')
            . "\n\n## ANOMALÍAS DE LOS ÚLTIMOS 60 DÍAS\nDías laborables con lluvia o viento fuertes: " . count($anom) . " · con actividades de exterior en curso y sin marcar como no hábiles: {$datos['perdidos']}"
            . "\n\nDevuelve SOLO un JSON: {\"resumen\":\"1 o 2 párrafos\",\"recomendaciones\":[\"3 a 5 acciones para reorganizar el trabajo\"]}";
        return ['datos' => $datos, 'lotes' => $prono ? [['titulo' => 'Interpretando el pronóstico', 'prompt' => $prompt]] : []];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $pid = (int)$p->id;
        $act = $datos['actualizacion'];
        $pr = $datos['pronostico'];
        $malos = array_values(array_filter($pr, fn($x) => $x['cond']));
        $creadas = 0;
        if ($params['proponer'] === 'si' && $datos['propuestas']) {
            $hall = [];
            foreach ($datos['propuestas'] as $d) {
                $motivo = ((float)$d['precip_mm'] >= Clima::LLUVIA_FUERTE_MM ? 'Lluvia fuerte prevista (' . $d['precip_mm'] . ' mm)' : 'Viento fuerte previsto (' . $d['viento_kmh'] . ' km/h)');
                $hall[] = ['huella' => sha1("clima:$pid:{$d['fecha']}"), 'categoria' => 'clima', 'severidad' => 'media', 'origen' => 'sistema', 'titulo' => 'Día no hábil probable: ' . self::fmt($d['fecha']) . ' (' . Clima::condicion($d) . ')',
                    'descripcion' => "$motivo. El día es laborable en el calendario del proyecto y hay " . count(array_filter($pr, fn($x) => $x['fecha'] === $d['fecha'] && $x['n_afect'] > 0)) . ' grupo(s) de actividades de exterior en curso.', 'actividades' => [], 'solucion' => ['texto' => 'Marcar el ' . self::fmt($d['fecha']) . ' como día no hábil por clima (las fechas de las actividades se recalculan con el calendario).', 'accion' => 'agregar_dia_no_habil', 'params' => ['fecha' => $d['fecha'], 'motivo' => $motivo]]];
            }
            $r = Inconsistencias::guardar($p, $hall, '', ['del_sistema' => count($hall), 'de_la_ia' => 0]);
            if ($r['nuevos']) { Inconsistencias::marcarCorrida($pid, $r['corrida'], true, 'Clima ' . date('d/m H:i')); $creadas = $r['nuevos']; }
        }
        $resumen = Informes::limpiarTexto(self::dato($respuestas, 'resumen') ?? '', 2500);
        $ia = $resumen !== '';
        if (!$ia) $resumen = !$pr ? ($act['mensaje'] ?? 'Sin datos de clima.') : (count($malos) ? 'En los próximos ' . count($pr) . ' días el pronóstico marca ' . count($malos) . ' día(s) con lluvia o viento que frenan el trabajo de exterior. ' . ($datos['exteriores'] ? 'Hay ' . $datos['exteriores'] . ' actividad(es) de exterior pendientes.' : '') : 'No se esperan días de lluvia o viento fuertes en los próximos ' . count($pr) . ' días.');
        $reco = self::textos(self::dato($respuestas, 'recomendaciones'), 6, 240);
        $secs = [
            Informes::kpis('Clima de ' . ($act['lugar'] ?: 'el proyecto'), [['Días adversos pronosticados', count($malos), 'de ' . count($pr)], ['Actividades de exterior pendientes', $datos['exteriores']], ['Días no hábiles propuestos', $creadas, 'por aceptar en Análisis inteligente'], ['Días perdidos probables (60 d)', $datos['perdidos'], 'lluvia o viento con trabajo de exterior']]),
            Informes::texto('Resumen' . ($ia ? ' (redactado con IA, revísalo)' : ' (automático)'), $resumen . ($act['ok'] ? '' : "\n\n" . $act['mensaje'])),
        ];
        if ($reco) $secs[] = Informes::lista('Recomendaciones', $reco);
        if ($creadas) $secs[] = Informes::texto('Qué sigue', "Se crearon $creadas propuesta(s) de día no hábil. Nada cambió todavía: acéptalas o recházalas en Análisis inteligente; al aceptar, el calendario del proyecto las considera.");
        $secs[] = Informes::tabla('Pronóstico', ['Fecha', 'Lluvia (mm)', 'Prob.', 'Viento (km/h)', 'Máx / mín (°C)', 'Condición', 'Actividades de exterior en curso'], array_map(fn($x) => [self::fmt($x['fecha']) . (!$x['habil'] ? ' (no hábil)' : ''), $x['precip'], $x['prob'] !== null ? $x['prob'] . ' %' : '—', $x['viento'], ($x['tmax'] ?? '—') . ' / ' . ($x['tmin'] ?? '—'), $x['cond'] ?: '—', $x['n_afect'] ?: '—'], $pr));
        $secs[] = Informes::tabla('Anomalías de los últimos 60 días (días laborables con lluvia o viento fuertes)', ['Fecha', 'Condición', 'Lluvia (mm)', 'Viento (km/h)', 'Actividades de exterior en curso', 'Ya marcado no hábil'], array_map(fn($x) => [self::fmt($x['fecha']), $x['cond'], $x['precip'], $x['viento'], $x['n_afect'] ?: '—', $x['registrado'] ? 'Sí' : 'No'], $datos['anomalias']));
        $secs[] = Informes::texto('Criterios', 'Se considera adverso un día con lluvia ≥ ' . Clima::LLUVIA_MM . ' mm (o probabilidad ≥ 80 % con ≥ 5 mm) o viento ≥ ' . Clima::VIENTO_KMH . ' km/h. Se propone día no hábil con lluvia ≥ ' . Clima::LLUVIA_FUERTE_MM . ' mm o viento ≥ ' . Clima::VIENTO_FUERTE_KMH . ' km/h. Las actividades de exterior se reconocen por palabras de su nombre (azotea, fachada, colado, excavación, estacionamiento…). Fuente: Open-Meteo.');
        return ['secciones' => $secs];
    }
}
