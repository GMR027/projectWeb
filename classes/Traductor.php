<?php

namespace Classes;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Traductor español ↔ inglés de textos y documentos (PDF, Word, Writer, Excel, Calc, PowerPoint, Impress, TXT, CSV).
 * El motor es cualquier servidor compatible con LibreTranslate (POST /translate); se configura con TRADUCTOR_URL
 * (y TRADUCTOR_API_KEY si lo pide). Los documentos no salen del servidor propio si el motor es propio.
 * Se traduce por párrafo: el formato del párrafo se conserva, el de cada fragmento interno (negritas a media frase) se pierde.
 */
class Traductor
{
    public const IDIOMAS = ['es' => 'Español', 'en' => 'Inglés'];
    public const MAX_TEXTO = 20000;
    public const MAX_ARCHIVO = 20 * 1024 * 1024;
    public const MAX_CARACTERES_DOC = 600000;
    public const EXTENSIONES = ['pdf', 'docx', 'doc', 'odt', 'rtf', 'xlsx', 'xls', 'ods', 'csv', 'pptx', 'ppt', 'odp', 'txt', 'md'];
    /** Formatos viejos que se convierten con LibreOffice antes de traducir: extensión => formato de salida */
    private const CONVERTIR = ['doc' => 'docx', 'rtf' => 'docx', 'xls' => 'xlsx', 'ppt' => 'pptx'];
    /** Motor local: cada llamada carga el modelo (~2 s), así que se mandan lotes grandes. HTTP: lotes chicos */
    private const LOTE_LOCAL = [400, 60000];
    private const LOTE_HTTP = [40, 4000];

    private static int $traducidos = 0;
    /** @var array<string,string> */
    private static array $cache = [];

    private static function conf(string $k): string
    {
        return trim((string)($_ENV[$k] ?? getenv($k) ?: ''));
    }

    /** Ruta del Python con ctranslate2 y sentencepiece (TRADUCTOR_PYTHON, o el entorno virtual de storage/traductor) */
    private static function python(): string
    {
        if (($p = self::conf('TRADUCTOR_PYTHON')) !== '') return $p;
        $venv = self::dirModelos() . '/venv/bin/python';
        return is_file($venv) ? $venv : '';
    }

    private static function dirModelos(): string
    {
        return rtrim(self::conf('TRADUCTOR_DIR') ?: __DIR__ . '/../storage/traductor', '/');
    }

    /** ¿Hay un motor local instalado con los dos sentidos (es→en y en→es)? */
    public static function localDisponible(): bool
    {
        if (self::python() === '') return false;
        foreach (['es_en', 'en_es'] as $d) {
            if (!is_file(self::dirModelos() . "/$d/model/model.bin")) return false;
        }
        return true;
    }

    /** Hay motor: el local (por defecto) o un servidor LibreTranslate si se define TRADUCTOR_URL */
    public static function configurado(): bool
    {
        return self::conf('TRADUCTOR_URL') !== '' || self::localDisponible();
    }

    public static function idiomaValido(string $c): bool
    {
        return isset(self::IDIOMAS[$c]);
    }

    /** Traduce una lista de textos conservando el orden. @param string[] $textos @return string[] */
    public static function traducir(array $textos, string $de, string $a): array
    {
        if (!self::configurado()) throw new \RuntimeException('El traductor no está disponible en este servidor.');
        if (!self::idiomaValido($de) || !self::idiomaValido($a) || $de === $a) throw new \RuntimeException('Idiomas no válidos.');
        $salida = [];
        $pendientes = [];   // índice => texto único que falta traducir
        foreach ($textos as $i => $t) {
            $t = (string)$t;
            if (!preg_match('/\p{L}/u', $t)) { $salida[$i] = $t; continue; }   // números, signos, vacío: se quedan igual
            $k = "$de>$a:" . $t;
            if (isset(self::$cache[$k])) { $salida[$i] = self::$cache[$k]; continue; }
            $pendientes[$i] = $t;
        }
        $unicos = array_values(array_unique($pendientes));
        [$maxItems, $maxChars] = self::conf('TRADUCTOR_URL') !== '' ? self::LOTE_HTTP : self::LOTE_LOCAL;
        $lote = [];
        $largo = 0;
        $enviar = function () use (&$lote, &$largo, $de, $a) {
            if (!$lote) return;
            $res = self::llamar($lote, $de, $a);
            foreach ($lote as $j => $orig) self::$cache["$de>$a:" . $orig] = $res[$j] ?? $orig;
            $lote = [];
            $largo = 0;
        };
        foreach ($unicos as $t) {
            self::$traducidos += mb_strlen($t);
            if (self::$traducidos > self::MAX_CARACTERES_DOC) throw new \RuntimeException('El documento es demasiado largo para traducirlo de una vez.');
            foreach (self::partir($t) as $trozo) {
                if ($lote && (count($lote) >= $maxItems || $largo + strlen($trozo) > $maxChars)) $enviar();
                $lote[] = $trozo;
                $largo += strlen($trozo);
            }
        }
        $enviar();
        foreach ($pendientes as $i => $t) {
            $salida[$i] = implode(' ', array_map(fn($p) => self::$cache["$de>$a:" . $p] ?? $p, self::partir($t)));
        }
        ksort($salida);
        return array_values($salida);
    }

    public static function traducirTexto(string $texto, string $de, string $a): string
    {
        // Se traduce por líneas para conservar saltos y listas
        $lineas = preg_split('/\R/u', $texto);
        return implode("\n", self::traducir($lineas, $de, $a));
    }

    /** Un texto largo se parte en frases (≤ 1500 caracteres por trozo) */
    private static function partir(string $t): array
    {
        if (mb_strlen($t) <= 1500) return [$t];
        $partes = preg_split('/(?<=[.!?;:])\s+/u', $t) ?: [$t];
        $trozos = [];
        $actual = '';
        foreach ($partes as $p) {
            while (mb_strlen($p) > 1500) { $trozos[] = mb_substr($p, 0, 1500); $p = mb_substr($p, 1500); }
            if ($actual !== '' && mb_strlen($actual) + mb_strlen($p) > 1500) { $trozos[] = $actual; $actual = ''; }
            $actual .= ($actual === '' ? '' : ' ') . $p;
        }
        if ($actual !== '') $trozos[] = $actual;
        return $trozos;
    }

    /** @param string[] $lote @return string[] */
    private static function llamar(array $lote, string $de, string $a): array
    {
        return self::conf('TRADUCTOR_URL') !== '' ? self::llamarHttp($lote, $de, $a) : self::llamarLocal($lote, $de, $a);
    }

    /** Motor local: bin/traductor.py (CTranslate2 + modelos Opus-MT en storage/traductor) */
    private static function llamarLocal(array $lote, string $de, string $a): array
    {
        $proc = @proc_open([self::python(), __DIR__ . '/../bin/traductor.py'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['TRADUCTOR_DIR' => self::dirModelos(), 'PATH' => '/usr/bin:/bin', 'OMP_NUM_THREADS' => '4']);
        if (!is_resource($proc)) throw new \RuntimeException('No se pudo iniciar el motor de traducción.');
        fwrite($pipes[0], json_encode(['de' => $de, 'a' => $a, 'q' => array_values($lote)], JSON_UNESCAPED_UNICODE));
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = $err = '';
        $limite = time() + 900;
        while (true) {
            $out .= (string)stream_get_contents($pipes[1]);
            $err .= (string)stream_get_contents($pipes[2]);
            if (!proc_get_status($proc)['running']) break;
            if (time() > $limite) { proc_terminate($proc, 9); break; }
            usleep(30000);
        }
        $out .= (string)stream_get_contents($pipes[1]);
        $err .= (string)stream_get_contents($pipes[2]);
        proc_close($proc);
        $d = json_decode($out, true);
        if (!is_array($d['t'] ?? null) || count($d['t']) !== count($lote)) {
            error_log('Traductor local: ' . substr($err, -500));
            throw new \RuntimeException('El motor de traducción local falló.');
        }
        return array_map('strval', $d['t']);
    }

    /** @param string[] $lote @return string[] */
    private static function llamarHttp(array $lote, string $de, string $a): array
    {
        $cuerpo = ['q' => array_values($lote), 'source' => $de, 'target' => $a, 'format' => 'text'];
        if (($k = self::conf('TRADUCTOR_API_KEY')) !== '') $cuerpo['api_key'] = $k;
        $ch = curl_init(rtrim(self::conf('TRADUCTOR_URL'), '/') . '/translate');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
        ]);
        $r = curl_exec($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($r === false) {
            error_log('Traductor: ' . $err);
            throw new \RuntimeException('No se pudo conectar con el motor de traducción.');
        }
        $d = json_decode((string)$r, true);
        if ($codigo !== 200 || !is_array($d)) {
            error_log("Traductor HTTP $codigo: " . substr((string)$r, 0, 300));
            throw new \RuntimeException($codigo === 429 ? 'El motor de traducción está saturado; intenta en un momento.' : 'El motor de traducción devolvió un error.');
        }
        $t = $d['translatedText'] ?? null;
        if (!is_array($t)) $t = [$t];
        if (count($t) !== count($lote)) throw new \RuntimeException('Respuesta inesperada del motor de traducción.');
        return array_map('strval', $t);
    }

    // ───────────────────────── Documentos ─────────────────────────

    /**
     * Traduce un archivo y devuelve la ruta del resultado (en el directorio temporal; quien llama lo borra).
     * @return array{0: string, 1: string, 2: string} [ruta, nombre de descarga, tipo MIME]
     */
    public static function documento(string $ruta, string $nombreOriginal, string $de, string $a): array
    {
        self::$traducidos = 0;
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES, true)) throw new \RuntimeException('Formato no compatible. Usa PDF, Word, Writer, Excel, Calc, PowerPoint, Impress, CSV o TXT.');
        $base = preg_replace('/[^\p{L}\p{N}._ -]+/u', '', pathinfo($nombreOriginal, PATHINFO_FILENAME)) ?: 'documento';
        $sufijo = strtoupper($a);
        $temporales = [];
        try {
            if (isset(self::CONVERTIR[$ext])) {
                $ruta = self::convertir($ruta, $ext, self::CONVERTIR[$ext], $temporales);
                $ext = self::CONVERTIR[$ext];
            }
            $salida = tempnam(sys_get_temp_dir(), 'trad');
            $temporales[] = $salida;
            switch ($ext) {
                case 'txt': case 'md':
                    file_put_contents($salida, self::traducirTexto(self::utf8((string)file_get_contents($ruta)), $de, $a));
                    break;
                case 'pdf':
                    self::pdf($ruta, $salida, $de, $a);
                    break;
                case 'xlsx': case 'ods': case 'csv':
                    self::hoja($ruta, $salida, $ext, $de, $a);
                    break;
                default:
                    self::zipXml($ruta, $salida, $ext, $de, $a);
            }
            $mimes = [
                'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8', 'md' => 'text/plain; charset=utf-8', 'csv' => 'text/csv; charset=utf-8',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'odt' => 'application/vnd.oasis.opendocument.text',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'odp' => 'application/vnd.oasis.opendocument.presentation',
            ];
            $final = tempnam(sys_get_temp_dir(), 'trad');
            rename($salida, $final);
            $temporales = array_diff($temporales, [$salida]);
            return [$final, "{$base}_$sufijo.$ext", $mimes[$ext] ?? 'application/octet-stream'];
        } finally {
            foreach ($temporales as $t) @unlink($t);
        }
    }

    private static function utf8(string $s): string
    {
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
        return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }

    /** Convierte un formato viejo (doc, xls, ppt, rtf) con LibreOffice. Los temporales se anotan para borrarlos */
    private static function convertir(string $ruta, string $extOrigen, string $formato, array &$temporales): string
    {
        $dir = sys_get_temp_dir() . '/trad_' . bin2hex(random_bytes(6));
        mkdir($dir, 0700);
        $entrada = "$dir/origen.$extOrigen";   // LibreOffice decide el filtro por la extensión
        copy($ruta, $entrada);
        $perfil = "$dir/perfil";
        $cmd = ['soffice', "-env:UserInstallation=file://$perfil", '--headless', '--convert-to', $formato, '--outdir', $dir, $entrada];
        $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) { self::borrarDir($dir); throw new \RuntimeException('Este formato necesita LibreOffice en el servidor; guárdalo como DOCX, XLSX o PPTX.'); }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $limite = time() + 120;
        while (proc_get_status($proc)['running'] && time() < $limite) { stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); usleep(100000); }
        if (proc_get_status($proc)['running']) proc_terminate($proc, 9);
        proc_close($proc);
        $res = "$dir/origen.$formato";
        if (!is_file($res)) { self::borrarDir($dir); throw new \RuntimeException('No se pudo convertir el documento; guárdalo como DOCX, XLSX o PPTX.'); }
        $copia = tempnam(sys_get_temp_dir(), 'trad');
        copy($res, $copia);
        self::borrarDir($dir);
        $temporales[] = $copia;
        return $copia;
    }

    private static function borrarDir(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /** PDF → PDF nuevo solo con el texto traducido (el diseño original no se conserva) */
    private static function pdf(string $ruta, string $salida, string $de, string $a): void
    {
        $proc = @proc_open(['pdftotext', '-enc', 'UTF-8', $ruta, '-'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) throw new \RuntimeException('El servidor no puede leer PDF (falta poppler-utils).');
        stream_set_blocking($pipes[1], false);
        $texto = '';
        $limite = time() + 120;
        while (proc_get_status($proc)['running'] && time() < $limite && strlen($texto) < 30 * 1024 * 1024) { $texto .= (string)stream_get_contents($pipes[1]); usleep(40000); }
        $texto .= (string)stream_get_contents($pipes[1]);
        if (proc_get_status($proc)['running']) proc_terminate($proc, 9);
        proc_close($proc);
        if (!preg_match('/\p{L}{3}/u', $texto)) throw new \RuntimeException('El PDF no tiene texto seleccionable (parece escaneado); no se puede traducir sin OCR.');

        $paginas = array_values(array_filter(explode("\f", $texto), fn($p) => trim($p) !== ''));
        $parrafos = [];   // [página, texto]
        foreach ($paginas as $n => $pag) {
            foreach (preg_split('/\n\s*\n/u', $pag) as $bloque) {
                $b = trim(preg_replace('/\s*\n\s*/u', ' ', $bloque));
                if ($b !== '') $parrafos[] = [$n, $b];
            }
        }
        $trad = self::traducir(array_column($parrafos, 1), $de, $a);
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:10.5pt;line-height:1.4}p{margin:0 0 7pt}.pag{page-break-before:always;color:#888;font-size:8pt;margin-bottom:10pt}</style></head><body>';
        $actual = -1;
        foreach ($parrafos as $i => [$n]) {
            if ($n !== $actual) {
                $html .= '<div class="pag"' . ($actual === -1 ? ' style="page-break-before:avoid"' : '') . '>' . ($n + 1) . '</div>';
                $actual = $n;
            }
            $html .= '<p>' . htmlspecialchars($trad[$i], ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $opts = new \Dompdf\Options();
        $opts->set('isRemoteEnabled', false);
        $opts->set('isPhpEnabled', false);
        $dompdf = new \Dompdf\Dompdf($opts);
        $dompdf->setPaper('letter');
        $dompdf->loadHtml($html . '</body></html>');
        $dompdf->render();
        file_put_contents($salida, $dompdf->output());
    }

    /** XLSX / ODS / CSV: traduce las celdas de texto (no fórmulas, números ni fechas) */
    private static function hoja(string $ruta, string $salida, string $ext, string $de, string $a): void
    {
        $lector = IOFactory::createReaderForFile($ruta);
        $libro = $lector->load($ruta);
        $celdas = [];
        $textos = [];
        foreach ($libro->getAllSheets() as $h) {
            foreach ($h->getCellCollection()->getCoordinates() as $coord) {
                $c = $h->getCell($coord);
                if ($c->isFormula() || !in_array($c->getDataType(), [DataType::TYPE_STRING, DataType::TYPE_STRING2, DataType::TYPE_INLINE], true)) continue;
                $v = (string)$c->getValue();
                if ($v === '') continue;
                $celdas[] = [$h, $coord];
                $textos[] = $v;
            }
        }
        foreach (self::traducir($textos, $de, $a) as $i => $t) $celdas[$i][0]->setCellValueExplicit($celdas[$i][1], $t, DataType::TYPE_STRING);
        $tipo = ['xlsx' => 'Xlsx', 'ods' => 'Ods', 'csv' => 'Csv'][$ext];
        IOFactory::createWriter($libro, $tipo)->save($salida);
        $libro->disconnectWorksheets();
    }

    /** DOCX / PPTX / ODT / ODP: reescribe el texto de cada párrafo dentro del XML del zip */
    private static function zipXml(string $ruta, string $salida, string $ext, string $de, string $a): void
    {
        copy($ruta, $salida);
        $zip = new \ZipArchive();
        if ($zip->open($salida) !== true) throw new \RuntimeException('El archivo está dañado o no es un ' . strtoupper($ext) . ' válido.');
        // [expresión sobre el nombre de la parte, elemento de texto, elemento de párrafo]
        $reglas = [
            'docx' => ['#^word/(document|header\d*|footer\d*|footnotes|endnotes|comments)\.xml$#', 'w:t', 'w:p'],
            'pptx' => ['#^ppt/(slides/slide\d+|notesSlides/notesSlide\d+)\.xml$#', 'a:t', 'a:p'],
            'odt' => ['#^(content|styles)\.xml$#', null, 'text:p|text:h'],
            'odp' => ['#^(content|styles)\.xml$#', null, 'text:p|text:h'],
        ];
        [$patron, $hoja, $parrafo] = $reglas[$ext];
        $partes = [];   // nombre => [DOMDocument, [párrafo => [nodos de texto]]]
        $textos = [];
        $destinos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = (string)$zip->getNameIndex($i);
            if (!preg_match($patron, $nombre)) continue;
            $xml = (string)$zip->getFromIndex($i);
            $dom = new \DOMDocument();
            if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) continue;
            $xp = new \DOMXPath($dom);
            if (!$hoja) {   // ODF: <text:s/> es un espacio; se vuelve texto para que no se pierda al unir los fragmentos
                foreach (iterator_to_array($xp->query('//text:s')) as $sp) {
                    $sp->parentNode->replaceChild($dom->createTextNode(str_repeat(' ', max(1, (int)$sp->getAttribute('text:c')))), $sp);
                }
            }
            $grupos = new \SplObjectStorage();
            $nodos = $hoja ? $xp->query("//$hoja/text()") : $xp->query('//text()');
            foreach ($nodos as $n) {
                $p = $n->parentNode;
                $esParrafo = fn($e) => in_array($e->nodeName, explode('|', $parrafo), true);
                while ($p && !$esParrafo($p)) $p = $p->parentNode;
                if (!$p) continue;
                $grupos[$p] = ($grupos->contains($p) ? $grupos[$p] : []);
                $l = $grupos[$p];
                $l[] = $n;
                $grupos[$p] = $l;
            }
            foreach ($grupos as $p) {
                $nl = $grupos[$p];
                $texto = implode('', array_map(fn($n) => $n->nodeValue, $nl));
                $textos[] = $texto;
                $destinos[] = $nl;
            }
            $partes[$nombre] = $dom;
        }
        if (!$textos) { $zip->close(); throw new \RuntimeException('No se encontró texto para traducir en el documento.'); }
        foreach (self::traducir($textos, $de, $a) as $i => $t) {
            $nl = $destinos[$i];
            if ($t === $textos[$i]) continue;
            $nl[0]->nodeValue = $t;
            if ($hoja === 'w:t' && $nl[0]->parentNode instanceof \DOMElement) $nl[0]->parentNode->setAttribute('xml:space', 'preserve');
            for ($j = 1; $j < count($nl); $j++) $nl[$j]->nodeValue = '';
        }
        foreach ($partes as $nombre => $dom) $zip->addFromString($nombre, $dom->saveXML());
        $zip->close();
    }
}
