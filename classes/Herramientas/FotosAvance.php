<?php

namespace Classes\Herramientas;

use Classes\Dependencias;
use Classes\Informes;
use Classes\Inconsistencias;
use Classes\MapaAvance;
use Model\Proyecto;

/**
 * Avance por fotos: un modelo CON VISIÓN mira la foto de evidencia más reciente de cada actividad y la clasifica en rojo (no avanzado), amarillo (a medias) o verde (terminado),
 * con un porcentaje aproximado y su nivel de confianza. Si la diferencia con el programa es clara se propone el cambio de avance; nunca se aplica solo.
 */
class FotosAvance extends Base
{
    public static function id(): string { return 'fotos_avance'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Avance por fotos (visión)', 'grupo' => 'campo', 'tipo' => 'fotos_avance', 'aplicable' => false,
            'descripcion' => 'Un modelo con visión mira la foto de evidencia más reciente de cada actividad y la clasifica en rojo (no avanzado), amarillo (a medias) o verde (terminado), con un porcentaje aproximado. Si difiere claramente del programa se propone el cambio de avance. Requiere una conexión con visión (por ejemplo llava o qwen-vl en Ollama, o una API con imágenes); la estimación puede equivocarse, por eso siempre la confirmas tú.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'medio', 'vision' => true],
            'parametros' => [['nombre' => 'dias', 'etiqueta' => 'Fotos de los últimos (días)', 'tipo' => 'numero', 'defecto' => 30, 'min' => 1, 'max' => 365], ['nombre' => 'maximo', 'etiqueta' => 'Máximo de actividades por corrida', 'tipo' => 'numero', 'defecto' => 6, 'min' => 1, 'max' => 20]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Avance por fotos · ' . date('d/m/Y H:i');
    }

    /** Última foto de evidencia por actividad pendiente de la ventana */
    private static function fotos(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $desde = date('Y-m-d', strtotime('-' . (int)$params['dias'] . ' days'));
        $acts = Dependencias::actividades($pid);
        $out = [];
        foreach (self::filas("SELECT e.id, e.actividad_id, e.fecha, e.descripcion FROM evidencias e WHERE e.proyecto_id = $pid AND e.actividad_id IS NOT NULL AND e.fecha >= '$desde' AND e.foto <> '' ORDER BY e.fecha DESC, e.id DESC") as $f) {
            $a = $acts[(int)$f['actividad_id']] ?? null;
            if (!$a || (int)$a['hito'] || isset($out[(int)$f['actividad_id']])) continue;
            $out[(int)$f['actividad_id']] = ['evidencia' => (int)$f['id'], 'fecha' => $f['fecha'], 'nota' => $f['descripcion'], 'act' => $a];
            if (count($out) >= (int)$params['maximo']) break;
        }
        return $out;
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $fotos = self::fotos($p, $params);
        $lotes = [];
        foreach ($fotos as $aid => $f) {
            $a = $f['act'];
            $lotes[] = ['titulo' => 'Mirando la foto de ' . $a['codigo'] . ' ' . self::corto($a['nombre'], 45), 'clave' => 'A' . $aid, 'imagenes' => [['url' => "/api/ia/evidencia?proyecto=$pid&id={$f['evidencia']}"]], 'prompt' =>
                "Eres supervisor de obra y estimas el avance de un trabajo a partir de UNA foto. Actividad: «{$a['codigo']} {$a['nombre']}». Avance registrado en el programa: {$a['progreso']} %. Foto tomada el " . self::fmt($f['fecha']) . ($f['nota'] !== '' ? '. Nota de la foto: «' . self::corto($f['nota'], 150) . '»' : '') . ".\n\n"
                . "Clasifica lo que se VE de esa actividad: \"rojo\" = no se ve avance o apenas iniciado; \"amarillo\" = a medias; \"verde\" = terminado; \"incierto\" = la foto no permite juzgar esa actividad (otra zona, borrosa, sin el trabajo a la vista). Sé conservador: si dudas, usa \"incierto\" o confianza \"baja\". No inventes lo que no se ve.\n\n"
                . "Devuelve SOLO un JSON: {\"estado\":\"rojo|amarillo|verde|incierto\",\"porcentaje\":0-100 o null,\"confianza\":\"baja|media|alta\",\"descripcion\":\"qué se ve, en una frase\"}"];
        }
        return ['datos' => ['fotos' => $fotos], 'lotes' => $lotes];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $pid = (int)$p->id;
        $aj = MapaAvance::ajustes($pid);
        $filas = [];
        $hall = [];
        $por = [];
        foreach ($respuestas as $r) if (isset($r['_clave'])) $por[(string)$r['_clave']] = $r;
        foreach ($datos['fotos'] as $aid => $f) {
            $a = $f['act'];
            $r = $por['A' . $aid] ?? null;
            if (!$r) { $filas[] = [$a['codigo'] . ' ' . self::corto($a['nombre'], 50), self::fmt($f['fecha']), '—', '—', '—', 'Sin respuesta del modelo (no hay conexión con visión o falló)']; continue; }
            $estado = in_array($r['estado'] ?? '', ['rojo', 'amarillo', 'verde', 'incierto'], true) ? $r['estado'] : 'incierto';
            $conf = in_array($r['confianza'] ?? '', ['baja', 'media', 'alta'], true) ? $r['confianza'] : 'baja';
            $desc = Informes::limpiarTexto($r['descripcion'] ?? '', 240);
            $pctIA = isset($r['porcentaje']) && is_numeric($r['porcentaje']) && $r['porcentaje'] >= 0 && $r['porcentaje'] <= 100 ? (int)round($r['porcentaje']) : null;
            $pct = $estado === 'incierto' ? null : ($pctIA ?? $aj[$estado]);
            $actual = (int)$a['progreso'];
            $filas[] = [$a['codigo'] . ' ' . self::corto($a['nombre'], 50), self::fmt($f['fecha']), ucfirst($estado), $pct !== null ? $pct . ' %' : '—', $conf, $desc ?: '—'];
            if ($pct !== null && $conf !== 'baja' && abs($pct - $actual) >= 10) {
                $hall[] = ['huella' => sha1("fotos:$pid:$aid:{$f['evidencia']}:$pct"), 'categoria' => 'avance_plano', 'severidad' => 'media', 'origen' => 'ia', 'titulo' => "La foto del " . self::fmt($f['fecha']) . " indica ~{$pct}% en {$a['codigo']} (registrado {$actual}%)",
                    'descripcion' => "Clasificación $estado (confianza $conf). " . ($desc ?: 'Sin descripción.'), 'actividades' => [$aid],
                    'solucion' => ['texto' => "Poner el avance de «{$a['codigo']}» en $pct% (estimación visual: confírmala con la foto antes de aceptar).", 'accion' => 'ajustar_progreso', 'params' => ['actividad_id' => $aid, 'progreso' => $pct]]];
            }
        }
        $creadas = 0;
        if ($hall) {
            $r = Inconsistencias::guardar($p, $hall, '', ['del_sistema' => 0, 'de_la_ia' => count($hall)]);
            if ($r['nuevos']) { Inconsistencias::marcarCorrida($pid, $r['corrida'], true, 'Avance por fotos ' . date('d/m H:i')); $creadas = $r['nuevos']; }
        }
        return ['secciones' => [
            Informes::kpis('Resultado', [['Fotos analizadas', count($datos['fotos'])], ['Con respuesta del modelo', count($por)], ['Cambios de avance propuestos', $creadas]]),
            Informes::texto('Importante', ($datos['fotos'] ? 'La clasificación la hace un modelo de visión y puede equivocarse (ángulo, luz, zona distinta). Solo se proponen cambios cuando la confianza es media o alta y la diferencia con el programa es de al menos 10 puntos; revisa la foto antes de aceptar en Análisis inteligente.' : 'No hay fotos de evidencia recientes con actividad asignada. Súbelas en Evidencias de cada actividad.') . ($creadas ? " Se crearon $creadas propuesta(s)." : '')),
            Informes::tabla('Fotos analizadas', ['Actividad', 'Foto del', 'Estado', 'Avance estimado', 'Confianza', 'Lo que se ve'], $filas),
        ]];
    }
}
