<?php

namespace Classes\Herramientas;

use Classes\Informes;
use Model\Bitacora;
use Model\Proyecto;

/** Dictado de bitácora: la transcripción de voz se limpia (puntuación, palabras mal reconocidas) sin agregar información y, al aprobarla, se crea la entrada */
class DictadoBitacora extends Base
{
    public static function id(): string { return 'dictado_bitacora'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Dictado de bitácora', 'grupo' => 'campo', 'tipo' => 'bitacora', 'aplicable' => true, 'voz' => true,
            'descripcion' => 'Dicta o pega una nota de obra. La IA corrige la puntuación y las palabras mal reconocidas sin agregar información, y al aprobarla se crea la entrada de bitácora.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [
                ['nombre' => 'texto', 'etiqueta' => 'Nota (dictada o escrita)', 'tipo' => 'texto', 'defecto' => '', 'max' => 4000, 'voz' => true],
                ['nombre' => 'departamento', 'etiqueta' => 'Departamento', 'tipo' => 'texto', 'defecto' => 'Obra', 'max' => 100],
                ['nombre' => 'importancia', 'etiqueta' => 'Importancia', 'tipo' => 'opcion', 'opciones' => Bitacora::IMPORTANCIAS, 'defecto' => 'media'],
                ['nombre' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => 'fecha', 'defecto' => date('Y-m-d')],
            ]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Bitácora ' . date('d/m/Y', strtotime($params['fecha'])) . ' · ' . self::corto($params['texto'], 50);
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $prompt = "Corriges la transcripción de voz de una nota de bitácora de obra. Corrige puntuación, mayúsculas y palabras mal reconocidas evidentes. NO agregues, quites ni cambies hechos, cifras, nombres ni códigos de actividad.\n\n"
            . "Nota original:\n\"\"\"\n" . $params['texto'] . "\n\"\"\"\n\nDevuelve SOLO un JSON: {\"texto\":\"la nota corregida\",\"importancia\":\"baja|media|alta|critica según la gravedad que se desprenda de la nota\"}";
        return ['datos' => ['original' => $params['texto']], 'lotes' => trim($params['texto']) === '' ? [] : [['titulo' => 'Limpiando la nota dictada', 'prompt' => $prompt]]];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $orig = trim((string)preg_replace('/[ \t]+/', ' ', $datos['original']));
        $ia = Informes::limpiarTexto(self::dato($respuestas, 'texto') ?? '', 4000);
        $texto = $ia !== '' ? $ia : $orig;
        $imp = self::dato($respuestas, 'importancia');
        $imp = isset(Bitacora::IMPORTANCIAS[$imp]) ? $imp : $params['importancia'];
        $secs = [Informes::kpis('Entrada de bitácora', [['Fecha', self::fmt($params['fecha'])], ['Departamento', $params['departamento']], ['Importancia', Bitacora::IMPORTANCIAS[$imp]]]), Informes::texto('Texto de la entrada' . ($ia !== '' ? ' (corregido con IA, revísalo)' : ''), $texto)];
        if ($ia !== '' && $ia !== $orig) $secs[] = Informes::texto('Texto original dictado', $orig);
        return ['secciones' => $secs, 'aplicar' => ['tipo' => 'bitacora', 'fecha' => $params['fecha'], 'departamento' => $params['departamento'], 'descripcion' => $texto, 'importancia' => $imp]];
    }
}
