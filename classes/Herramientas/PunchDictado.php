<?php

namespace Classes\Herramientas;

use Classes\Informes;
use Classes\Punch;
use Model\Proyecto;

/** Punch list por dictado: lo dictado en un recorrido se estructura en pendientes (título, ubicación, categoría, severidad); al aprobar el informe se crean en el punch list */
class PunchDictado extends Base
{
    public static function id(): string { return 'punch_dictado'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Punch list por dictado', 'grupo' => 'seguridad', 'tipo' => 'punch', 'aplicable' => true, 'voz' => true,
            'descripcion' => 'Dicta lo que ves en un recorrido de calidad (“cámara 3 del nivel 2 desenfocada; falta etiqueta en el gabinete IDF…”). La IA lo separa en pendientes con ubicación, categoría y severidad; al aprobar el informe se crean en el punch list. Sin IA se separa por frases.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [['nombre' => 'texto', 'etiqueta' => 'Lo dictado en el recorrido', 'tipo' => 'texto', 'defecto' => '', 'max' => 6000, 'voz' => true],
                ['nombre' => 'ubicacion', 'etiqueta' => 'Ubicación general (si no la dices en cada pendiente)', 'tipo' => 'texto', 'defecto' => '', 'max' => 200], ['nombre' => 'contratista', 'etiqueta' => 'Contratista responsable (opcional)', 'tipo' => 'texto', 'defecto' => '', 'max' => 150]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Recorrido de calidad · ' . date('d/m/Y H:i');
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $cats = implode(', ', array_keys(array_merge(...array_values(array_map(fn($pl) => array_flip(array_column($pl['items'], 1)), Punch::PLANTILLAS)))));
        $prompt = "Estructuras los pendientes de calidad (punch list) que un supervisor dictó durante un recorrido de obra. Separa cada problema distinto en un pendiente. No inventes problemas ni ubicaciones que no se dijeron. La transcripción puede tener errores de reconocimiento de voz.\n\n"
            . "Ubicación general: " . ($params['ubicacion'] ?: '(no indicada)') . "\nCategorías sugeridas: $cats\n\n## LO DICTADO\n\"\"\"\n{$params['texto']}\n\"\"\"\n\n"
            . "Devuelve SOLO un JSON: {\"pendientes\":[{\"titulo\":\"qué está mal, breve\",\"descripcion\":\"detalle si lo hay\",\"ubicacion\":\"\",\"categoria\":\"\",\"severidad\":\"baja|media|alta|critica\",\"responsable\":\"\"}]}";
        return ['datos' => ['texto' => $params['texto']], 'lotes' => trim($params['texto']) === '' ? [] : [['titulo' => 'Separando los pendientes dictados', 'prompt' => $prompt]]];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $items = [];
        foreach ((array)(self::dato($respuestas, 'pendientes') ?? []) as $x) {
            if (!is_array($x)) continue;
            $t = Informes::limpiarTexto($x['titulo'] ?? '', 200);
            if ($t === '') continue;
            $items[] = ['titulo' => $t, 'descripcion' => Informes::limpiarTexto($x['descripcion'] ?? '', 800), 'ubicacion' => Informes::limpiarTexto($x['ubicacion'] ?? '', 200) ?: $params['ubicacion'], 'categoria' => Informes::limpiarTexto($x['categoria'] ?? '', 60),
                'severidad' => isset(Punch::SEVERIDADES[$x['severidad'] ?? '']) ? $x['severidad'] : 'media', 'responsable' => Informes::limpiarTexto($x['responsable'] ?? '', 150), 'contratista' => $params['contratista']];
        }
        if (!$items) foreach (array_slice(array_filter(array_map('trim', preg_split('/[.;\n]+/u', $datos['texto']))), 0, 15) as $frase) if (mb_strlen($frase) >= 8) $items[] = ['titulo' => mb_substr($frase, 0, 200), 'descripcion' => '', 'ubicacion' => $params['ubicacion'], 'categoria' => '', 'severidad' => 'media', 'responsable' => '', 'contratista' => $params['contratista']];
        $items = array_slice($items, 0, 40);
        return ['secciones' => [
            Informes::kpis('Recorrido', [['Pendientes identificados', count($items)]]),
            Informes::texto('Antes de aprobar', $items ? 'Revisa la lista: al pulsar «Crear los pendientes» se agregan al punch list con estado «abierto». Ajusta la severidad y la ubicación después en el punch list si hace falta.' : 'No se identificó ningún pendiente en el texto.'),
            Informes::tabla('Pendientes', ['Pendiente', 'Ubicación', 'Categoría', 'Severidad', 'Responsable'], array_map(fn($x) => [$x['titulo'], $x['ubicacion'] ?: '—', $x['categoria'] ?: '—', Punch::SEVERIDADES[$x['severidad']], $x['responsable'] ?: ($x['contratista'] ?: '—')], $items)),
            Informes::texto('Texto dictado', $datos['texto']),
        ], 'aplicar' => ['tipo' => 'punch', 'items' => $items]];
    }
}
