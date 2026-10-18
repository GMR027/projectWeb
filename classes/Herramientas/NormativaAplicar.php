<?php

namespace Classes\Herramientas;

use Classes\Informes;
use Classes\Normativa;
use Model\Proyecto;

/**
 * Normativa y especificaciones con IA: para cada material o actividad sin vínculos el sistema busca las cláusulas candidatas (sección CSI y palabras,
 * en español o inglés) y la IA decide cuáles aplican de verdad, resumiendo el requisito en español. Todo queda como propuesta que el usuario acepta o rechaza.
 */
class NormativaAplicar extends Base
{
    private const POR_LOTE = 1;   // un elemento por consulta: los modelos pequeños escriben JSON válido cuando la tarea y el formato son simples

    public static function id(): string { return 'normativa_aplicar'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Aplicar normativa y especificaciones', 'grupo' => 'documentos', 'tipo' => 'normativa', 'aplicable' => false,
            'descripcion' => 'Lee las normas y especificaciones cargadas (PDF en español o inglés) y propone en qué materiales y actividades aplica cada cláusula, con el requisito resumido en español y la cita original. Tú aceptas o rechazas cada vínculo. Sin IA se proponen solo los de sección CSI exacta y mucha coincidencia de palabras.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'medio'],
            'parametros' => [['nombre' => 'ambito', 'etiqueta' => 'Qué revisar', 'tipo' => 'opcion', 'opciones' => ['materiales' => 'Materiales', 'actividades' => 'Actividades', 'ambos' => 'Materiales y actividades'], 'defecto' => 'materiales'],
                ['nombre' => 'maximo', 'etiqueta' => 'Máximo de elementos por corrida', 'tipo' => 'numero', 'defecto' => 16, 'min' => 1, 'max' => 40]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Normativa aplicable · ' . date('d/m/Y H:i');
    }

    /** Elementos sin vínculos que tienen candidatos: [clave => [tipo, id, nombre, cand[]]] */
    private static function elementos(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $max = (int)$params['maximo'];
        $ids = [];
        if (in_array($params['ambito'], ['materiales', 'ambos'], true)) foreach (self::filas("SELECT id FROM materiales WHERE proyecto_id = $pid AND id NOT IN (SELECT entidad_id FROM normativa_aplicaciones WHERE entidad_tipo = 'material' AND proyecto_id = $pid) ORDER BY id LIMIT 300") as $f) $ids[] = ['material', (int)$f['id']];
        if (in_array($params['ambito'], ['actividades', 'ambos'], true)) foreach (self::filas("SELECT a.id FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND a.hito = 0 AND a.id NOT IN (SELECT entidad_id FROM normativa_aplicaciones WHERE entidad_tipo = 'actividad' AND proyecto_id = $pid) ORDER BY a.id LIMIT 300") as $f) $ids[] = ['actividad', (int)$f['id']];
        $out = [];
        foreach ($ids as [$tipo, $id]) {
            $c = Normativa::candidatos($pid, $tipo, $id, 4);
            if (!$c) continue;
            $out[($tipo === 'material' ? 'M' : 'A') . $id] = ['tipo' => $tipo, 'id' => $id, 'nombre' => Normativa::entidad($pid, $tipo, $id)['nombre'], 'cand' => $c];
            if (count($out) >= $max) break;
        }
        return $out;
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $el = self::elementos($p, $params);
        $lotes = [];
        $n = 0;
        foreach ($el as $k => $e) {
            $n++;
            $cands = implode("\n", array_map(fn($c) => "- CLÁUSULA {$c['id']} [{$c['doc']} · " . ($c['clausula'] !== '' ? $c['clausula'] . ' · ' : '') . "pág. {$c['pagina']} · " . strtoupper($c['idioma']) . "] " . self::corto(trim($c['titulo'] . '. ' . $c['texto']), 420), $e['cand']));
            $lotes[] = ['titulo' => "Revisando normativa: elemento $n de " . count($el) . ' (' . self::corto($e['nombre'], 40) . ')', 'clave' => $k, 'prompt' =>
                "Eres especialista en normativa y especificaciones de construcción. Decide cuáles de estas CLÁUSULAS le aplican DE VERDAD al elemento (que exijan algo sobre ese material o trabajo, no solo que compartan palabras). Las cláusulas pueden estar en inglés o en español; resume SIEMPRE el requisito en español, con base solo en el texto de la cláusula; no inventes requisitos.\n\n"
                . "ELEMENTO (" . ($e['tipo'] === 'material' ? 'material' : 'actividad') . "): " . self::corto($e['nombre'], 140) . "\n\nCLÁUSULAS CANDIDATAS:\n$cands\n\n"
                . "Responde SOLO con un objeto JSON, sin texto antes ni después ni bloques de código, con esta forma exacta:\n{\"aplicaciones\":[{\"clausula\":123,\"aplica\":true,\"requisito\":\"qué exige, en español, máx. 200 caracteres\"},{\"clausula\":456,\"aplica\":false,\"requisito\":\"\"}]}\nIncluye una entrada por cada cláusula candidata."];
        }
        return ['datos' => ['elementos' => $el], 'lotes' => $lotes];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $pid = (int)$p->id;
        $el = $datos['elementos'];
        $ia = $reglas = 0;
        $filas = [];
        $decididos = [];
        foreach ($respuestas as $r) {
            foreach ((array)($r['aplicaciones'] ?? []) as $a) {
                if (!is_array($a)) continue;
                $k = (string)($r['_clave'] ?? ($a['elemento'] ?? ''));
                $cid = (int)($a['clausula'] ?? 0);
                if (!isset($el[$k]) || !in_array($cid, array_map(fn($c) => (int)$c['id'], $el[$k]['cand']), true)) continue;   // solo lo que el sistema ofreció
                $decididos[$k] = true;
                if (empty($a['aplica']) || $a['aplica'] === 'false') continue;
                $req = Informes::limpiarTexto($a['requisito'] ?? '', 300);
                if (Normativa::proponerIA($pid, $el[$k]['tipo'], $el[$k]['id'], $cid, $req, '')) {
                    $ia++;
                    $c = array_values(array_filter($el[$k]['cand'], fn($c) => (int)$c['id'] === $cid))[0];
                    $filas[] = [self::corto($el[$k]['nombre'], 60), $c['doc'] . ' · ' . ($c['clausula'] ?: 'pág. ' . $c['pagina']), 'IA', self::corto($req, 140)];
                }
            }
        }
        // Lo que la IA no resolvió (sin IA o lote fallido): reglas del sistema (CSI exacta o mucha coincidencia de palabras)
        foreach ($el as $k => $e) {
            if (isset($decididos[$k])) continue;
            foreach ($e['cand'] as $c) {
                if ($c['origen'] === 'palabras' && $c['puntaje'] < 60) continue;
                if (Normativa::proponerRegla($pid, $e['tipo'], $e['id'], $c)) {
                    $reglas++;
                    $filas[] = [self::corto($e['nombre'], 60), $c['doc'] . ' · ' . ($c['clausula'] ?: 'pág. ' . $c['pagina']), $c['origen'] === 'csi' ? 'Sección CSI' : 'Palabras', ''];
                }
            }
        }
        return ['secciones' => [
            Informes::kpis('Resultado', [['Elementos revisados', count($el)], ['Vínculos propuestos por IA', $ia], ['Propuestos por reglas', $reglas], ['Pendientes de decidir', Normativa::conteoPendientes($pid)]]),
            Informes::texto('Qué sigue', 'Nada se aplica todavía: acepta o rechaza cada vínculo en Normativa y especificaciones → Vínculos (o en Análisis inteligente). Los aceptados aparecen en el formulario de cada material y actividad.' . (!$el ? ' No hay elementos con cláusulas candidatas: carga documentos en Normativa y especificaciones o revisa que los materiales tengan referencia o nombre descriptivo.' : '')),
            Informes::tabla('Vínculos propuestos en esta corrida', ['Elemento', 'Cláusula', 'Origen', 'Requisito (resumen)'], array_slice($filas, 0, 60)),
        ]];
    }
}
