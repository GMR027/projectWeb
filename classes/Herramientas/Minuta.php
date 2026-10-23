<?php

namespace Classes\Herramientas;

use Classes\Grabaciones;
use Classes\Informes;
use Model\Bitacora;
use Model\Proyecto;

/** Asistente de minutas: de la transcripción de una reunión saca resumen, temas, compromisos con responsable y notas; al aprobarla se crea la minuta */
class Minuta extends Base
{
    private const TROZO = 6500;

    public static function id(): string { return 'minuta'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Asistente de minutas', 'grupo' => 'documentos', 'tipo' => 'minuta', 'aplicable' => true, 'voz' => true, 'grabacion' => true,
            'descripcion' => 'Graba la reunión (se guarda en el proyecto) o pega la transcripción. La IA extrae resumen, temas, acuerdos con responsables y notas; al aprobar se crea la minuta. Las reuniones largas se procesan por partes.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'medio'],
            'parametros' => [
                ['nombre' => 'nombre', 'etiqueta' => 'Nombre de la minuta', 'tipo' => 'texto', 'defecto' => 'Reunión de coordinación', 'max' => 200],
                ['nombre' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => 'fecha', 'defecto' => date('Y-m-d')],
                ['nombre' => 'importancia', 'etiqueta' => 'Importancia', 'tipo' => 'opcion', 'opciones' => Bitacora::IMPORTANCIAS, 'defecto' => 'media'],
                ['nombre' => 'transcripcion', 'etiqueta' => 'Transcripción de la reunión', 'tipo' => 'texto', 'defecto' => '', 'max' => 120000, 'voz' => true],
                ['nombre' => 'grabacion', 'etiqueta' => 'Grabación guardada', 'tipo' => 'numero', 'defecto' => 0, 'min' => 0, 'max' => 2000000000, 'oculto' => true],
            ]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Minuta · ' . self::corto($params['nombre'], 80) . ' · ' . date('d/m/Y', strtotime($params['fecha']));
    }

    /** Parte la transcripción en trozos de tamaño razonable cortando en saltos de línea o puntos */
    public static function trozos(string $t): array
    {
        $t = trim($t);
        $out = [];
        while (mb_strlen($t) > self::TROZO) {
            $corte = mb_strrpos(mb_substr($t, 0, self::TROZO), "\n") ?: mb_strrpos(mb_substr($t, 0, self::TROZO), '. ') ?: self::TROZO;
            $out[] = trim(mb_substr($t, 0, $corte + 1));
            $t = trim(mb_substr($t, $corte + 1));
        }
        if ($t !== '') $out[] = $t;
        return $out;
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $trozos = self::trozos($params['transcripcion']);
        $n = count($trozos);
        $lotes = [];
        foreach ($trozos as $i => $t) {
            $lotes[] = ['titulo' => $n > 1 ? 'Analizando la parte ' . ($i + 1) . ' de ' . $n : 'Analizando la reunión', 'prompt' =>
                "Eres el secretario técnico de una reunión de obra y redactas la minuta. Usa SOLO lo que aparece en la transcripción" . ($n > 1 ? " (es la parte " . ($i + 1) . " de $n)" : '') . "; no inventes acuerdos, responsables ni fechas. Si no se dijo un responsable, déjalo vacío. La transcripción puede tener errores de reconocimiento de voz.\n\n"
                . "Proyecto: {$p->nombre} · Reunión: {$params['nombre']}\n\n## TRANSCRIPCIÓN\n\"\"\"\n$t\n\"\"\"\n\n"
                . "Devuelve SOLO un JSON: {\"resumen\":\"1 párrafo\",\"temas\":[{\"tema\":\"asunto tratado\",\"responsable\":\"\",\"departamento\":\"\"}],\"compromisos\":[{\"tema\":\"qué se acordó hacer\",\"responsable\":\"quién\",\"departamento\":\"\",\"fecha\":\"YYYY-MM-DD o vacío\"}],\"notas\":[\"datos relevantes sueltos\"]}"];
        }
        return ['datos' => ['transcripcion' => $params['transcripcion'], 'trozos' => $n], 'lotes' => $lotes];
    }

    private static function items($v, bool $conFecha): array
    {
        $out = [];
        foreach ((array)$v as $x) {
            if (!is_array($x)) continue;
            $tema = Informes::limpiarTexto($x['tema'] ?? '', 300);
            if ($tema === '') continue;
            $f = (string)($x['fecha'] ?? '');
            $f = $conFecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : '';
            $it = ['tema' => $tema . ($f ? ' (fecha: ' . date('d/m/Y', strtotime($f)) . ')' : ''), 'responsable' => Informes::limpiarTexto($x['responsable'] ?? '', 100), 'departamento' => Informes::limpiarTexto($x['departamento'] ?? '', 100)];
            $out[mb_strtolower($it['tema'])] = $it;   // deduplica lo repetido entre partes
        }
        return array_values($out);
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $temas = $compromisos = $notas = $resumenes = [];
        foreach ($respuestas as $r) {
            foreach (self::items($r['temas'] ?? [], false) as $t) $temas[mb_strtolower($t['tema'])] = $t;
            foreach (self::items($r['compromisos'] ?? [], true) as $t) $compromisos[mb_strtolower($t['tema'])] = $t;
            foreach (self::textos($r['notas'] ?? [], 20) as $n) $notas[mb_strtolower($n)] = $n;
            $res = Informes::limpiarTexto($r['resumen'] ?? '', 1500);
            if ($res !== '') $resumenes[] = $res;
        }
        $temas = array_values($temas);
        $compromisos = array_values($compromisos);
        $fila = fn($x) => [self::corto($x['tema'], 200), $x['responsable'] ?: '—', $x['departamento'] ?: '—'];
        $secs = [Informes::kpis('Minuta', [['Fecha', self::fmt($params['fecha'])], ['Temas', count($temas)], ['Compromisos', count($compromisos)], ['Duración de la transcripción', mb_strlen($datos['transcripcion']) . ' caracteres', $datos['trozos'] > 1 ? $datos['trozos'] . ' partes' : '']])];
        if ($resumenes) $secs[] = Informes::texto('Resumen', implode("\n\n", $resumenes));
        $secs[] = Informes::tabla('Temas tratados', ['Tema', 'Responsable', 'Departamento'], array_map($fila, $temas));
        $secs[] = Informes::tabla('Compromisos', ['Compromiso', 'Responsable', 'Departamento'], array_map($fila, $compromisos));
        if ($notas) $secs[] = Informes::lista('Notas', array_values($notas));
        if ((int)$params['grabacion'] > 0 && ($g = Grabaciones::obtener((int)$p->id, (int)$params['grabacion']))) {
            $secs[] = Informes::texto('Grabación guardada', 'Audio de ' . Grabaciones::duracion((int)$g['duracion_seg']) . ' guardado en el proyecto (Grabaciones de reuniones, /grabaciones?proyecto=' . (int)$p->id . ').');
        }
        $secs[] = Informes::texto('Transcripción completa', $datos['transcripcion'] !== '' ? $datos['transcripcion'] : '(vacía)');
        return ['secciones' => $secs, 'aplicar' => ['tipo' => 'minuta', 'fecha' => $params['fecha'], 'nombre' => $params['nombre'], 'importancia' => $params['importancia'], 'temas' => $temas, 'compromisos' => $compromisos]];
    }
}
