<?php

namespace Classes\Herramientas;

use Classes\Glosario;
use Classes\Informes;
use Classes\Rfi;
use Model\Proyecto;

/**
 * Contradicciones entre documentos (Fase 5.4): busca datos que se dicen distinto en dos fuentes del proyecto —por ejemplo el plano pide tubería de
 * 4 pulgadas y la especificación de 6, o el catálogo de materiales trae un calibre distinto al de la norma—. El servidor hace la búsqueda sin IA:
 * parte los documentos de Normativa (especificaciones, normas y planos subidos como PDF) en oraciones, extrae las magnitudes (número + unidad, con
 * conversión pulgadas↔mm, etc.), empareja oraciones de documentos DISTINTOS que hablan de lo mismo (palabras técnicas en común, también entre español
 * e inglés con el glosario) y marca como candidatas las que dan una cifra distinta en la misma magnitud. La IA solo juzga cada candidata (¿de verdad se
 * contradicen o hablan de cosas distintas?) y explica. Al aprobar el informe, cada contradicción confirmada puede volverse un RFI.
 */
class Contradicciones extends Base
{
    private const MAX_CANDIDATAS = 20;
    private const POR_LOTE = 5;
    private const MAX_ORACIONES = 2500;
    private const COMUNES = ['para', 'sera', 'seran', 'debe', 'deben', 'segun', 'como', 'este', 'esta', 'entre', 'sobre', 'cada', 'todo', 'todos', 'minimo', 'maximo', 'the', 'shall', 'will', 'must', 'with', 'from', 'that', 'this', 'each', 'all', 'minimum', 'maximum', 'per', 'and', 'for', 'not', 'are', 'any', 'una', 'los', 'las', 'del', 'por', 'con', 'que', 'ser', 'tipo', 'type', 'mas', 'menos', 'incluye', 'including'];
    /** unidad (como aparece) => [dimensión, factor a la unidad base] */
    private const UNIDADES = [
        'mm' => ['long', 1], 'milímetros' => ['long', 1], 'milímetro' => ['long', 1], 'milimetros' => ['long', 1], 'milimetro' => ['long', 1], 'cm' => ['long', 10], 'centímetros' => ['long', 10], 'centímetro' => ['long', 10], 'centimetros' => ['long', 10], 'centimetro' => ['long', 10], 'm' => ['long', 1000], 'metro' => ['long', 1000], 'metros' => ['long', 1000], 'pulgada' => ['long', 25.4], 'pulgadas' => ['long', 25.4], 'pulg' => ['long', 25.4], 'inch' => ['long', 25.4], 'inches' => ['long', 25.4], '"' => ['long', 25.4], '″' => ['long', 25.4], 'ft' => ['long', 304.8], 'pies' => ['long', 304.8],
        'kg' => ['masa', 1], 'lb' => ['masa', 0.4536], 'libras' => ['masa', 0.4536],
        'v' => ['volt', 1], 'volt' => ['volt', 1], 'volts' => ['volt', 1], 'vca' => ['volt', 1], 'vcd' => ['volt', 1],
        'a' => ['amp', 1], 'amp' => ['amp', 1], 'amperes' => ['amp', 1], 'amperios' => ['amp', 1],
        'w' => ['watt', 1], 'watts' => ['watt', 1], 'kw' => ['watt', 1000],
        'awg' => ['awg', 1], 'psi' => ['presion', 1], 'kpa' => ['presion', 0.145038], 'mpa' => ['presion', 145.038],
        'mp' => ['mp', 1], 'megapixel' => ['mp', 1], 'megapixeles' => ['mp', 1], 'hz' => ['hz', 1], 'db' => ['db', 1], 'lux' => ['lux', 1], '%' => ['pct', 1],
    ];
    private const NOMBRE_DIM = ['long' => 'longitud', 'masa' => 'peso', 'volt' => 'voltaje', 'amp' => 'corriente', 'watt' => 'potencia', 'awg' => 'calibre AWG', 'presion' => 'presión', 'mp' => 'megapíxeles', 'hz' => 'frecuencia', 'db' => 'decibeles', 'lux' => 'luminosidad', 'pct' => 'porcentaje'];

    public static function id(): string { return 'contradicciones'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Contradicciones entre documentos', 'grupo' => 'documentos', 'tipo' => 'contradicciones', 'aplicable' => true,
            'descripcion' => 'Busca datos que dos documentos del proyecto dicen distinto (p. ej. el plano pide tubería de 4 pulgadas y la especificación de 6; el catálogo de materiales trae otro calibre que la norma). El sistema encuentra las candidatas sin IA; la IA decide si de verdad se contradicen. Las confirmadas pueden volverse RFI. Usa los documentos de Normativa y especificaciones (PDF o Word, planos incluidos) y el catálogo de materiales.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'medio'],
            'parametros' => [
                ['nombre' => 'ambito', 'etiqueta' => 'Qué comparar', 'tipo' => 'opcion', 'defecto' => 'ambos', 'opciones' => ['ambos' => 'Documentos entre sí y contra materiales', 'documentos' => 'Solo documentos entre sí', 'materiales' => 'Solo materiales contra documentos']],
                ['nombre' => 'comunes', 'etiqueta' => 'Palabras técnicas en común mínimas (más = menos candidatas, más precisas)', 'tipo' => 'numero', 'defecto' => 3, 'min' => 2, 'max' => 8],
            ]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Contradicciones entre documentos · ' . date('d/m/Y');
    }

    // ---------- extracción de magnitudes ----------

    /** Magnitudes de un texto: [['dim','valor' (en la unidad base),'texto' (como se escribió)], …] */
    public static function magnitudes(string $t): array
    {
        $out = [];
        $re = '/(?<![\w.,])(\d{1,6}(?:[.,]\d{1,3})?)\s?(mm|cm|m|mil[ií]metros?|cent[ií]metros?|metros?|pulgadas?|pulg|inch(?:es)?|"|″|ft|pies|kg|lb|libras|volts?|vca|vcd|V|amp(?:eres|erios)?|A|kW|W|watts|AWG|awg|psi|kPa|MPa|megap[ií]xeles|MP|Hz|dB|lux|%)(?![\p{L}\d²³])/u';
        if (!preg_match_all($re, $t, $m, PREG_SET_ORDER)) return [];
        foreach ($m as $x) {
            $u = $x[2];
            // símbolos de una letra: solo en su forma exacta (evita «12 a 15», «5 m» sí, «3 V» sí, «10 w» no)
            $k = mb_strtolower($u);
            if (in_array($u, ['a', 'v', 'w'], true)) continue;   // en minúscula «a» es preposición y «w»/«v» casi siempre una letra suelta
            if (!isset(self::UNIDADES[$k])) continue;
            [$dim, $f] = self::UNIDADES[$k];
            $v = (float)str_replace(',', '.', $x[1]);
            $out[] = ['dim' => $dim, 'valor' => round($v * $f, 3), 'texto' => trim($x[1] . ' ' . $u)];
        }
        return $out;
    }

    /** Palabras técnicas de una oración, llevadas a una forma común entre español e inglés */
    public static function claves(string $t): array
    {
        $out = [];
        foreach (Glosario::palabras($t) as $w) {
            if (strlen($w) < 4 || ctype_digit($w) || in_array($w, self::COMUNES, true)) continue;
            $out[Glosario::canonica($w)] = true;
        }
        return array_keys($out);
    }

    /** ¿Dos listas de magnitudes dicen cosas distintas en la misma dimensión? Devuelve [dim, a.texto, b.texto] o null */
    public static function difieren(array $a, array $b): ?array
    {
        foreach ($a as $x) {
            $mismos = array_filter($b, fn($y) => $y['dim'] === $x['dim']);
            if (!$mismos) continue;
            foreach ($mismos as $y) if (abs($x['valor'] - $y['valor']) <= max(0.01, abs($x['valor']) * 0.01)) continue 2;   // alguna coincide: no hay conflicto en esta dimensión
            $y = reset($mismos);
            return [$x['dim'], $x['texto'], $y['texto']];
        }
        return null;
    }

    private static function oraciones(string $texto): array
    {
        $out = [];
        foreach (preg_split('/(?<=[.;:])\s+|\R+/u', $texto) as $o) {
            $o = trim(preg_replace('/\s+/u', ' ', $o));
            if (mb_strlen($o) >= 25 && mb_strlen($o) <= 600) $out[] = $o;
        }
        return $out;
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $ambito = (string)$params['ambito'];
        $minimo = (int)$params['comunes'];
        // fuentes: [origen, etiqueta, ref, texto]
        $src = []; $docs = [];
        {
            foreach (self::filas("SELECT f.id, f.documento_id, f.pagina, f.clausula, f.texto, d.titulo doc, d.codigo FROM normativa_fragmentos f JOIN normativa_documentos d ON d.id = f.documento_id WHERE f.proyecto_id = $pid ORDER BY f.documento_id, f.id LIMIT 4000") as $f) {
                $docs[(int)$f['documento_id']] = true;
                foreach (self::oraciones($f['texto']) as $o) {
                    $mag = self::magnitudes($o);
                    if (!$mag) continue;
                    $src[] = ['tipo' => 'doc', 'grupo' => 'd' . $f['documento_id'], 'fuente' => trim(($f['codigo'] ? $f['codigo'] . ' · ' : '') . $f['doc']) . ', pág. ' . (int)$f['pagina'] . ($f['clausula'] ? ', cláusula ' . $f['clausula'] : ''), 'texto' => $o, 'mag' => $mag, 'claves' => self::claves($o)];
                    if (count($src) >= self::MAX_ORACIONES) break 2;
                }
            }
        }
        $nMat = 0;
        if ($ambito !== 'documentos') {
            foreach (self::filas("SELECT id, nombre, marca, modelo, valor_extra FROM materiales WHERE proyecto_id = $pid LIMIT 1500") as $m) {
                $t = trim(implode(' ', array_filter([$m['nombre'], $m['marca'], $m['modelo'], $m['valor_extra']])));
                $mag = self::magnitudes($t);
                if (!$mag) continue;
                $nMat++;
                $src[] = ['tipo' => 'mat', 'grupo' => 'm' . $m['id'], 'fuente' => 'Catálogo de materiales: ' . self::corto($m['nombre'], 60), 'texto' => $t, 'mag' => $mag, 'claves' => self::claves($t)];
            }
        }
        // Índice invertido por palabra técnica: cada oración solo se compara con las que comparten palabras
        $post = [];
        foreach ($src as $i => $s) foreach ($s['claves'] as $k) $post[$k][] = $i;
        $cands = []; $vistos = [];
        foreach ($src as $i => $s) {
            $cuenta = [];
            foreach ($s['claves'] as $k) {
                if (count($post[$k]) > 150) continue;   // palabra demasiado común: no distingue
                foreach ($post[$k] as $j) if ($j > $i) $cuenta[$j] = ($cuenta[$j] ?? 0) + 1;
            }
            foreach ($cuenta as $j => $n) {
                if ($n < $minimo) continue;
                $t = $src[$j];
                if ($t['grupo'] === $s['grupo']) continue;                                   // mismo documento o mismo material
                if ($ambito === 'documentos' && ($s['tipo'] === 'mat' || $t['tipo'] === 'mat')) continue;
                if ($ambito === 'materiales' && $s['tipo'] === $t['tipo']) continue;
                if ($s['tipo'] === 'mat' && $t['tipo'] === 'mat') continue;
                $d = self::difieren($s['mag'], $t['mag']);
                if (!$d) continue;
                $huella = md5($s['texto'] . '|' . $t['texto']);
                if (isset($vistos[$huella])) continue;
                $vistos[$huella] = true;
                $cands[] = ['comunes' => $n, 'dim' => $d[0], 'a' => ['fuente' => $s['fuente'], 'texto' => $s['texto'], 'dato' => $d[1]], 'b' => ['fuente' => $t['fuente'], 'texto' => $t['texto'], 'dato' => $d[2]]];
            }
        }
        usort($cands, fn($x, $y) => $y['comunes'] <=> $x['comunes']);
        $cands = array_slice($cands, 0, self::MAX_CANDIDATAS);
        foreach ($cands as $i => &$c) $c['n'] = $i + 1;
        unset($c);

        $lotes = [];
        foreach (array_chunk($cands, self::POR_LOTE) as $grupo) {
            $txt = implode("\n\n", array_map(fn($c) => "### Caso {$c['n']} (tema: " . self::NOMBRE_DIM[$c['dim']] . ")\nFuente A — {$c['a']['fuente']}:\n«" . self::corto($c['a']['texto'], 420) . "»\nFuente B — {$c['b']['fuente']}:\n«" . self::corto($c['b']['texto'], 420) . '»', $grupo));
            $lotes[] = ['titulo' => 'Juzgando los casos ' . $grupo[0]['n'] . ' a ' . end($grupo)['n'], 'prompt' => "Eres revisor técnico de proyectos de construcción. Para cada caso compara la Fuente A con la Fuente B y decide si SE CONTRADICEN: hablan del mismo elemento y piden o dan un valor distinto de la misma magnitud. Si hablan de cosas distintas (otro elemento, otro tramo, otro material) NO es contradicción. Usa SOLO el texto dado; no supongas.\n\n$txt\n\nDevuelve SOLO un JSON: {\"resultados\":[{\"n\":1,\"contradice\":true,\"severidad\":\"baja|media|alta\",\"explicacion\":\"una frase que diga qué valor da cada fuente\"}]}"];
        }
        return ['datos' => ['candidatas' => $cands, 'documentos' => count($docs), 'oraciones' => count($src), 'materiales' => $nMat, 'ambito' => $ambito], 'lotes' => $lotes];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $juicio = [];
        foreach ($respuestas as $r) foreach ((array)($r['resultados'] ?? []) as $x) if (is_array($x) && isset($x['n'])) $juicio[(int)$x['n']] = $x;
        $ia = (bool)$juicio;
        $conf = []; $otras = [];
        foreach ($datos['candidatas'] as $c) {
            $j = $juicio[$c['n']] ?? null;
            $es = $j !== null && filter_var($j['contradice'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $sev = in_array($j['severidad'] ?? '', ['baja', 'media', 'alta'], true) ? $j['severidad'] : 'media';
            $c['expl'] = Informes::limpiarTexto($j['explicacion'] ?? '', 400);
            $c['sev'] = $sev;
            if ($es) $conf[] = $c; else $otras[] = $c;
        }
        $nombre = fn($c) => $c['a']['dato'] . ' vs ' . $c['b']['dato'];
        $kpis = [['Documentos revisados', $datos['documentos']], ['Oraciones con magnitudes', $datos['oraciones']], ['Casos encontrados', count($datos['candidatas'])]];
        if ($ia) $kpis[] = ['Contradicciones confirmadas', count($conf)];
        $secs = [Informes::kpis('Resultado', $kpis)];
        if (!$datos['candidatas']) $secs[] = Informes::texto('Sin hallazgos', 'No se encontraron datos que dos documentos digan distinto con los criterios elegidos. ' . ($datos['documentos'] ? '' : 'Sube las especificaciones, normas o planos (PDF o Word) en «Normativa y especificaciones» para poder compararlos.'));
        $sevEtq = ['alta' => '🔴 Alta', 'media' => '🟡 Media', 'baja' => '🟢 Baja'];
        if ($conf) $secs[] = Informes::tabla('Contradicciones confirmadas', ['Tema', 'Fuente A', 'Fuente B', 'Dice A / Dice B', 'Severidad', 'Explicación'],
            array_map(fn($c) => [self::NOMBRE_DIM[$c['dim']], $c['a']['fuente'], $c['b']['fuente'], $nombre($c), $sevEtq[$c['sev']], $c['expl'] ?: '—'], $conf));
        if ($otras) $secs[] = Informes::tabla($ia ? 'Casos descartados (hablan de cosas distintas)' : 'Posibles contradicciones (revísalas)', ['Tema', 'Fuente A', 'Fuente B', 'Dice A / Dice B', 'Texto A', 'Texto B'],
            array_map(fn($c) => [self::NOMBRE_DIM[$c['dim']], $c['a']['fuente'], $c['b']['fuente'], $nombre($c), self::corto($c['a']['texto'], 160), self::corto($c['b']['texto'], 160)], $otras));
        $secs[] = Informes::texto('Cómo funciona', 'El sistema compara oraciones de documentos distintos que hablan de lo mismo (palabras técnicas en común, también entre español e inglés) y que dan una cifra distinta en la misma magnitud (longitud, voltaje, calibre, etc., con conversión de unidades). Eso solo detecta candidatas: verifica siempre contra el documento original antes de actuar. Solo se comparan las oraciones que traen número con unidad.');
        $rfis = array_map(fn($c) => [
            'asunto' => self::corto('Contradicción de ' . self::NOMBRE_DIM[$c['dim']] . ': ' . $nombre($c), 100),
            'pregunta' => "Se detectó una contradicción entre dos documentos del proyecto.\n\nFuente A — {$c['a']['fuente']}: «" . self::corto($c['a']['texto'], 300) . "»\nFuente B — {$c['b']['fuente']}: «" . self::corto($c['b']['texto'], 300) . "»\n" . ($c['expl'] ? "\n{$c['expl']}\n" : '') . "\nSe solicita confirmar cuál prevalece para ejecutar conforme a lo correcto.",
            'prioridad' => $c['sev'] === 'alta' ? 'alta' : 'media', 'actividad_id' => 0, 'responsable' => '',
        ], $conf);
        return ['secciones' => $secs, 'aplicar' => ['tipo' => 'contradicciones', 'rfis' => $rfis]];
    }
}
