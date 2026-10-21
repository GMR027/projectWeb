<?php

namespace Classes;

use Model\Proyecto;

/**
 * Normativa y especificaciones: PDFs en español o inglés que se leen con pdftotext, se parten por cláusulas (con página, idioma y sección CSI)
 * y se vinculan a materiales y actividades. La búsqueda cruza idiomas con un glosario técnico; si la referencia del material es una sección CSI
 * (27 15 00) el cruce es directo y sin IA. Los vínculos nacen como «propuesta» y el usuario los acepta o rechaza; también se pueden capturar a mano.
 */
class Normativa
{
    public const MAX_PDF = 60 * 1024 * 1024;
    public const MAX_FRAGMENTOS = 4000;
    private const FRAGMENTO_MAX = 1800;
    public const TIPOS = ['norma' => 'Norma', 'especificacion' => 'Especificación', 'otro' => 'Otro'];
    public const IDIOMAS = ['es' => 'Español', 'en' => 'Inglés', 'mixto' => 'Mixto'];

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    private static function filas(string $sql): array
    {
        try {
            $r = self::db()->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function esc($v): string
    {
        return self::db()->real_escape_string((string)$v);
    }

    private static function corto(?string $t, int $n): string
    {
        $t = trim((string)preg_replace('/\s+/u', ' ', (string)$t));
        return mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
    }

    // ---------- documentos ----------

    public static function carpeta(int $pid): string
    {
        return dirname(__DIR__) . "/storage/normativa/$pid";
    }

    public static function bytes(int $pid): int
    {
        return (int)(self::filas("SELECT COALESCE(SUM(tamano), 0) n FROM normativa_documentos WHERE proyecto_id = $pid")[0]['n'] ?? 0);
    }

    public static function listar(int $pid): array
    {
        return self::filas("SELECT * FROM normativa_documentos WHERE proyecto_id = $pid ORDER BY id DESC");
    }

    public static function obtener(int $pid, int $id): ?array
    {
        return self::filas("SELECT * FROM normativa_documentos WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    public static function ruta(array $d): string
    {
        return self::carpeta((int)$d['proyecto_id']) . '/' . basename($d['archivo']);
    }

    public const EXT_WORD = ['docx', 'doc'];

    /** Extensión permitida según el contenido real (no el nombre): pdf, docx o doc; '' si no es ninguna */
    public static function formato(string $tmp, string $nombre): string
    {
        $mime = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if ($mime === 'application/pdf') return 'pdf';
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if ($ext === 'docx' || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $zip = new \ZipArchive();
            $ok = $zip->open($tmp) === true && $zip->locateName('word/document.xml') !== false;
            if ($zip->status === \ZipArchive::ER_OK) $zip->close();
            return $ok ? 'docx' : '';
        }
        if ($ext === 'doc' && in_array($mime, ['application/msword', 'application/x-ole-storage', 'application/CDFV2'], true)) return 'doc';
        return '';
    }

    /** Texto por páginas de cualquier formato admitido. @return array{0: ?array, 1: string} */
    public static function extraer(string $ruta): array
    {
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        if ($ext === 'docx') return self::extraerDocx($ruta);
        if ($ext === 'doc') {
            $pdf = self::convertirAPdf($ruta);
            if ($pdf === null) return [null, 'Los .doc antiguos necesitan LibreOffice en el servidor; guarda el archivo como .docx o PDF.'];
            $r = self::extraerPdf($pdf);
            @unlink($pdf);
            return $r;
        }
        return self::extraerPdf($ruta);
    }

    /** Convierte con LibreOffice (si existe) a un PDF temporal; null si no se pudo */
    private static function convertirAPdf(string $ruta): ?string
    {
        return ShellTools::libreOffice($ruta, pathinfo($ruta, PATHINFO_EXTENSION), 'pdf');
    }

    /** Texto de un .docx leyendo word/document.xml (sin LibreOffice). Las páginas salen de los saltos de página; si no hay, se agrupan por tamaño */
    private static function extraerDocx(string $ruta): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) return [null, 'No se pudo abrir el documento de Word.'];
        $xml = (string)$zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === '' || strlen($xml) > 80 * 1024 * 1024) return [null, 'El documento de Word está vacío o es demasiado grande.'];
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) return [null, 'El documento de Word está dañado.'];
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $paginas = [''];
        $explicito = false;
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', $ns);
        foreach ($dom->getElementsByTagNameNS($ns, 'p') as $p) {
            $linea = '';
            foreach ($xp->query('.//w:t | .//w:tab | .//w:br | .//w:lastRenderedPageBreak', $p) as $n) {
                if ($n->localName === 't') $linea .= $n->textContent;
                elseif ($n->localName === 'tab') $linea .= ' ';
                elseif ($n->localName === 'lastRenderedPageBreak' || ($n->localName === 'br' && $n->getAttributeNS($ns, 'type') === 'page')) {
                    $paginas[count($paginas) - 1] .= $linea . "\n"; $linea = ''; $paginas[] = ''; $explicito = true;
                } else $linea .= ' ';
            }
            $paginas[count($paginas) - 1] .= $linea . "\n";
        }
        $paginas = array_values(array_filter($paginas, fn($t) => trim($t) !== ''));
        if (!$paginas) return [null, 'El documento de Word no tiene texto.'];
        if (!$explicito && count($paginas) === 1) {   // sin saltos de página: se agrupan párrafos hasta ~3500 caracteres
            $grupos = [];
            $cur = '';
            foreach (explode("\n", $paginas[0]) as $l) {
                $cur .= $l . "\n";
                if (mb_strlen($cur) >= 3500) { $grupos[] = $cur; $cur = ''; }
            }
            if (trim($cur) !== '') $grupos[] = $cur;
            $paginas = $grupos;
        }
        return [$paginas, ''];
    }

    /** Texto de un PDF por páginas (pdftotext), con tope de tiempo y de tamaño. @return array{0: ?array, 1: string} */
    private static function extraerPdf(string $ruta): array
    {
        $r = ShellTools::pdfATexto($ruta, true, 180, 40 * 1024 * 1024);
        if ($r === null) return [null, 'No se pudo ejecutar pdftotext (instala poppler-utils).'];
        $out = $r['salida'];
        $err = $r['error'];
        if (trim($out) === '') return [null, trim($err) !== '' ? mb_substr(trim($err), 0, 200) : 'El PDF no tiene texto.'];
        $pag = explode("\f", $out);
        if (end($pag) === '' || trim((string)end($pag)) === '') array_pop($pag);
        return [$pag, ''];
    }

    /** Parte el texto por páginas en fragmentos con cláusula, título, página y sección CSI */
    public static function partir(array $paginas): array
    {
        $out = [];
        $cur = null;
        $csi = null;
        $abrir = function (int $pag, string $clausula, string $titulo) use (&$cur, &$csi) {
            $cur = ['pagina' => $pag, 'clausula' => mb_substr($clausula, 0, 60), 'titulo' => mb_substr($titulo, 0, 250), 'csi' => $csi, 'lineas' => []];
        };
        $cerrar = function () use (&$cur, &$out) {
            if (!$cur) return;
            $texto = trim((string)preg_replace('/\s+/u', ' ', implode(' ', $cur['lineas'])));
            if (mb_strlen($texto) >= 40 || ($texto !== '' && $cur['titulo'] !== '')) $out[] = ['pagina' => $cur['pagina'], 'clausula' => $cur['clausula'], 'titulo' => $cur['titulo'], 'texto' => mb_substr($texto, 0, 2500), 'csi' => $cur['csi']];
            $cur = null;
        };
        foreach ($paginas as $i => $texto) {
            $pag = $i + 1;
            foreach (preg_split("/\r?\n/", $texto) as $linea) {
                $l = trim($linea);
                if ($l === '') { if ($cur && mb_strlen(implode(' ', $cur['lineas'])) > self::FRAGMENTO_MAX) { $c = $cur; $cerrar(); $abrir($pag, $c['clausula'] . ' (cont.)', $c['titulo']); } continue; }
                if (preg_match('/^(?:SECTION|SECCI[ÓO]N)\s+(\d{2})\s?(\d{2})\s?(\d{2})\b[\s\-–:.]*(.*)$/iu', $l, $m)) {
                    $cerrar(); $csi = $m[1] . $m[2] . $m[3]; $abrir($pag, "Sección {$m[1]} {$m[2]} {$m[3]}", $m[4] ?: ''); continue;
                }
                if (preg_match('/^(?:PART|PARTE)\s+(\d)\s*[\-–:.]?\s*(.*)$/iu', $l, $m) && mb_strlen($l) < 70) { $cerrar(); $abrir($pag, 'Parte ' . $m[1], $m[2]); continue; }
                if (preg_match('/^(?:ART[ÍI]CULO|ARTICLE|CAP[ÍI]TULO|CHAPTER)\s+([0-9IVXLC]+(?:\.\d+)*)\b[\s\-–:.]*(.*)$/iu', $l, $m)) { $cerrar(); $abrir($pag, preg_replace('/\s+/u', ' ', mb_substr($l, 0, 30)), $m[2]); continue; }
                if (preg_match('/^(\d{1,2}(?:\.\d{1,2}){0,4})\.?\s+(\p{Lu}[^\n]{3,110})$/u', $l, $m) && !preg_match('/[.;,]$/', $l) && mb_strlen($l) < 120) { $cerrar(); $abrir($pag, $m[1], $m[2]); continue; }
                if (!$cur) $abrir($pag, '', '');
                $cur['lineas'][] = $l;
            }
        }
        $cerrar();
        return array_slice($out, 0, self::MAX_FRAGMENTOS);
    }

    /** Código normativo reconocible en un texto (NOM-001-SEDE, NFPA 70, ANSI/TIA-568, ISO 9001, sección CSI…); '' si no hay */
    public static function detectarCodigo(string $texto): string
    {
        $t = preg_replace('/[ \t]+/u', ' ', $texto);
        $patrones = [
            '/\b(NOM-\d{3}(?:-[A-Z0-9]+){1,4})\b/u', '/\b(NMX-[A-Z]-[\dA-Z-]+)\b/u', '/\b(ANSI\/[A-Z]+[ -]?\d+(?:[.-][\dA-Z]+)*)\b/u',
            '/\b((?:NFPA|ISO|IEC|IEEE|ASTM|UL|TIA|EIA|BICSI|NEC) ?-?\d+(?:[.-][\dA-Z]+)*)\b/u',
        ];
        if (preg_match('/\b(?:SECTION|SECCI[ÓO]N)\s+(\d{2})\s?(\d{2})\s?(\d{2})\b/iu', $t, $m)) return "CSI {$m[1]} {$m[2]} {$m[3]}";
        foreach ($patrones as $re) if (preg_match($re, $t, $m)) return mb_substr(trim($m[1]), 0, 80);
        return '';
    }

    /** Título probable: lo que sigue a «SECCIÓN 27 15 00 –», o la primera línea con sentido de la primera página */
    public static function detectarTitulo(string $primeraPagina): string
    {
        $lineas = array_values(array_filter(array_map('trim', preg_split('/\R/u', $primeraPagina)), fn($l) => $l !== ''));
        foreach ($lineas as $i => $l) {
            if ($i > 12) break;
            if (preg_match('/^(?:SECTION|SECCI[ÓO]N)\s+\d{2}\s?\d{2}\s?\d{2}\b[\s\-–:.]*(.*)$/iu', $l, $m)) {
                $t = trim($m[1]) !== '' ? trim($m[1]) : ($lineas[$i + 1] ?? '');
                if (mb_strlen($t) >= 4) return self::corto(self::capitalizar($t), 200);
            }
        }
        foreach (array_slice($lineas, 0, 8) as $l) {
            if (mb_strlen($l) < 6 || mb_strlen($l) > 150 || preg_match('/^[\d\W]+$/u', $l) || preg_match('/^(p[áa]g(ina)?|page|\d+ de \d+)/iu', $l)) continue;
            return self::corto(self::capitalizar($l), 200);
        }
        return '';
    }

    private static function capitalizar(string $t): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $t));
        return $t === mb_strtoupper($t) && mb_strlen($t) > 3 ? mb_convert_case(mb_strtolower($t), MB_CASE_TITLE) : $t;
    }

    /** Tipo probable según el código: norma (NOM, ISO, NFPA…) o especificación (sección CSI y lo demás) */
    public static function detectarTipo(string $codigo, string $titulo = ''): string
    {
        if (preg_match('/^(NOM|NMX|ANSI|NFPA|ISO|IEC|IEEE|ASTM|UL|TIA|EIA|BICSI|NEC)\b/iu', $codigo)) return 'norma';
        if (preg_match('/\b(norma|standard|reglamento|code)\b/iu', $titulo)) return 'norma';
        return 'especificacion';
    }

    /**
     * Sube un archivo (PDF o Word). Título y código vacíos se detectan del contenido; tipo 'auto' se deduce del código.
     * @return array{0: bool, 1: string, 2: int} ok, mensaje e id del documento
     */
    public static function subir(Proyecto $p, array $archivo, string $titulo, string $tipo, string $codigo): array
    {
        $pid = (int)$p->id;
        $tmp = (string)($archivo['tmp_name'] ?? '');
        $nombreOrig = (string)($archivo['name'] ?? 'documento');
        if ($tmp === '' || !is_uploaded_file($tmp) && !is_file($tmp)) return [false, 'No llegó el archivo.', 0];
        $ext = self::formato($tmp, $nombreOrig);
        if ($ext === '') return [false, 'Solo se admiten PDF y Word (.docx, .doc).', 0];
        $tam = (int)filesize($tmp);
        if ($tam < 1 || $tam > self::MAX_PDF) return [false, 'El archivo está vacío o supera ' . Almacenamiento::formato(self::MAX_PDF) . '.', 0];
        if (!Almacenamiento::cabe($pid, $tam)) return [false, Almacenamiento::mensajeLleno($pid), 0];
        $dir = self::carpeta($pid);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) return [false, 'No se pudo crear la carpeta de normativa.', 0];
        $nombre = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!(is_uploaded_file($tmp) ? move_uploaded_file($tmp, "$dir/$nombre") : copy($tmp, "$dir/$nombre"))) return [false, 'No se pudo guardar el archivo.', 0];
        $titulo = trim($titulo);
        $codigo = trim($codigo);
        $porNombre = pathinfo($nombreOrig, PATHINFO_FILENAME) ?: 'Documento';
        $db = self::db();
        $usuario = self::esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150));
        $db->query("INSERT INTO normativa_documentos (proyecto_id, titulo, tipo, codigo, archivo, tamano, creado_por) VALUES ($pid, '" . self::esc(mb_substr($titulo !== '' ? $titulo : $porNombre, 0, 200)) . "', '" . (isset(self::TIPOS[$tipo]) ? $tipo : 'especificacion') . "', '" . self::esc(mb_substr($codigo, 0, 80)) . "', '$nombre', $tam, '$usuario')");
        $id = (int)$db->insert_id;
        $paginas = null;
        [$ok, $msg] = self::indexar($id, $paginas);
        // lo que el usuario dejó en automático se completa con el contenido (o con el nombre del archivo)
        $primera = $paginas ? implode("\n", array_slice($paginas, 0, 2)) : '';
        $cambios = [];
        if ($titulo === '') $cambios[] = "titulo = '" . self::esc(mb_substr(self::detectarTitulo($primera) ?: $porNombre, 0, 200)) . "'";
        if ($codigo === '') $codigo = self::detectarCodigo($primera) ?: self::detectarCodigo($porNombre);
        if ($codigo !== '') $cambios[] = "codigo = '" . self::esc(mb_substr($codigo, 0, 80)) . "'";
        if (!isset(self::TIPOS[$tipo])) $cambios[] = "tipo = '" . self::detectarTipo($codigo, $titulo) . "'";
        if ($cambios) $db->query('UPDATE normativa_documentos SET ' . implode(', ', $cambios) . " WHERE id = $id");
        return [true, $msg, $id];
    }

    /**
     * Sube varios archivos de un mismo formulario (campo múltiple). Los datos por archivo van en arreglos paralelos y vacío = automático.
     * @return array{0: int, 1: int, 2: string[]} subidos, con problemas y mensajes
     */
    public static function subirLote(Proyecto $p, array $files, array $titulos, array $codigos, array $tipos): array
    {
        $n = is_array($files['name'] ?? null) ? count($files['name']) : 0;
        $subidos = $fallos = 0;
        $mensajes = [];
        for ($i = 0; $i < min($n, 40); $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $nombre = (string)$files['name'][$i];
            if ($files['error'][$i] !== UPLOAD_ERR_OK) { $fallos++; $mensajes[] = "$nombre: no se pudo recibir el archivo."; continue; }
            $archivo = ['name' => $nombre, 'tmp_name' => $files['tmp_name'][$i]];
            [$ok, $msg] = self::subir($p, $archivo, (string)($titulos[$i] ?? ''), (string)($tipos[$i] ?? 'auto'), (string)($codigos[$i] ?? ''));
            if ($ok) $subidos++; else $fallos++;
            if (!$ok || stripos($msg, 'indexado') === false) $mensajes[] = "$nombre: $msg";
        }
        return [$subidos, $fallos, $mensajes];
    }

    /** (Re)indexa un documento: extrae el texto, lo parte y lo guarda. @return array{0: bool, 1: string} */
    public static function indexar(int $docId, ?array &$paginasLeidas = null): array
    {
        $db = self::db();
        $d = self::filas("SELECT * FROM normativa_documentos WHERE id = $docId")[0] ?? null;
        if (!$d) return [false, 'Documento no encontrado'];
        $db->query("DELETE FROM normativa_fragmentos WHERE documento_id = $docId");
        [$paginas, $error] = self::extraer(self::ruta($d));
        $paginasLeidas = $paginas;
        if (!$paginas) {
            $db->query("UPDATE normativa_documentos SET estado = 'error', nota = '" . self::esc(mb_substr($error, 0, 290)) . "', paginas = 0, fragmentos = 0 WHERE id = $docId");
            return [false, 'Se guardó el archivo pero no se pudo leer: ' . $error];
        }
        $chars = array_sum(array_map('mb_strlen', $paginas));
        if ($chars / max(count($paginas), 1) < 80) {
            $db->query("UPDATE normativa_documentos SET estado = 'sin_texto', paginas = " . count($paginas) . ", fragmentos = 0, nota = 'El PDF parece escaneado (sin texto seleccionable). Este equipo no tiene OCR instalado; sube una versión con texto.' WHERE id = $docId");
            return [false, 'Se guardó el archivo, pero parece escaneado (sin texto): no se puede leer sin OCR. Sube una versión con texto seleccionable.'];
        }
        $frag = self::partir($paginas);
        $es = $en = 0;
        $pid = (int)$d['proyecto_id'];
        foreach ($frag as $i => $f) {
            $frag[$i]['idioma'] = Glosario::idioma($f['titulo'] . ' ' . $f['texto']);
            if ($frag[$i]['idioma'] === 'es') $es++; elseif ($frag[$i]['idioma'] === 'en') $en++;
        }
        $mayoria = $en > $es ? 'en' : 'es';   // los fragmentos demasiado cortos para decidir toman el idioma dominante del documento
        foreach ($frag as $f) {
            $db->query("INSERT INTO normativa_fragmentos (documento_id, proyecto_id, pagina, clausula, titulo, texto, idioma, csi) VALUES ($docId, $pid, " . (int)$f['pagina'] . ", '" . self::esc($f['clausula']) . "', '" . self::esc($f['titulo']) . "', '" . self::esc($f['texto']) . "', '" . ($f['idioma'] ?? $mayoria) . "', " . ($f['csi'] ? "'" . self::esc($f['csi']) . "'" : 'NULL') . ')');
        }
        $tot = max(1, $es + $en);
        $idDoc = $es / $tot >= 0.85 ? 'es' : ($en / $tot >= 0.85 ? 'en' : 'mixto');
        $db->query("UPDATE normativa_documentos SET estado = 'indexado', idioma = '$idDoc', paginas = " . count($paginas) . ', fragmentos = ' . count($frag) . ", nota = '' WHERE id = $docId");
        return [true, 'Documento indexado: ' . count($paginas) . ' páginas, ' . count($frag) . ' cláusulas (' . self::IDIOMAS[$idDoc] . ').'];
    }

    public static function eliminar(int $pid, int $id): void
    {
        $d = self::obtener($pid, $id);
        if (!$d) return;
        @unlink(self::ruta($d));
        self::db()->query("DELETE FROM normativa_documentos WHERE id = $id AND proyecto_id = $pid");
    }

    public static function fragmentos(int $docId, string $q = '', int $limite = 200): array
    {
        $w = "documento_id = $docId" . ($q !== '' ? " AND (texto LIKE '%" . self::esc(str_replace(['%', '_'], ['\\%', '\\_'], $q)) . "%' OR titulo LIKE '%" . self::esc(str_replace(['%', '_'], ['\\%', '\\_'], $q)) . "%')" : '');
        return self::filas("SELECT id, pagina, clausula, titulo, idioma, csi, LEFT(texto, 600) texto FROM normativa_fragmentos WHERE $w ORDER BY id LIMIT " . max(1, min(500, $limite)));
    }

    // ---------- búsqueda ----------

    /** Fragmentos relevantes para unas palabras (en cualquier idioma), con puntaje 0–100 */
    public static function buscar(int $pid, array $palabras, int $limite = 5, array $excluir = []): array
    {
        $base = array_values(array_filter($palabras, fn($w) => strlen($w) >= 3));
        if (count($base) < 1) return [];
        $exp = Glosario::expandir($base);
        $q = self::esc(implode(' ', array_slice($exp, 0, 30)));
        $filas = self::filas("SELECT f.id, f.documento_id, d.titulo doc, d.codigo, f.pagina, f.clausula, f.titulo, f.texto, f.idioma, f.csi, MATCH(f.titulo, f.texto) AGAINST ('$q' IN NATURAL LANGUAGE MODE) rel
            FROM normativa_fragmentos f JOIN normativa_documentos d ON d.id = f.documento_id WHERE f.proyecto_id = $pid AND MATCH(f.titulo, f.texto) AGAINST ('$q' IN NATURAL LANGUAGE MODE) ORDER BY rel DESC LIMIT 40");
        $directas = array_map(fn($w) => Glosario::variantes($w), $base);
        $out = [];
        foreach ($filas as $f) {
            if (in_array((int)$f['id'], $excluir, true)) continue;
            $tokens = array_flip(Glosario::palabras($f['titulo'] . ' ' . $f['texto']));
            $tk = $tokens + array_flip(array_map(fn($t) => Glosario::variantes($t)[1] ?? $t, array_keys($tokens)));
            $dir = 0;
            foreach ($directas as $vs) foreach ($vs as $v) if (isset($tk[$v])) { $dir++; break; }
            $otras = array_diff($exp, $base);
            $ex = 0;
            foreach ($otras as $o) if (isset($tk[$o])) $ex++;
            $hits = $dir + min($ex, 3) * 0.5;
            if ($dir + $ex < 2) continue;
            $f['puntaje'] = round(min(100, $hits / max(count($base), 2) * 100), 1);
            $f['origen'] = 'palabras';
            unset($f['rel']);
            $out[] = $f;
        }
        usort($out, fn($a, $b) => $b['puntaje'] <=> $a['puntaje']);
        return array_slice($out, 0, $limite);
    }

    /** Datos de la entidad para buscar: texto, referencia (CSI) y nombre a mostrar */
    public static function entidad(int $pid, string $tipo, int $id): ?array
    {
        if ($tipo === 'material') {
            $m = self::filas("SELECT nombre, nombre_base, especialidad, referencia FROM materiales WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
            return $m ? ['nombre' => $m['nombre'], 'texto' => ($m['nombre_base'] ?: $m['nombre']) . ' ' . $m['especialidad'], 'csi' => preg_replace('/\D/', '', (string)$m['referencia'])] : null;
        }
        $a = self::filas("SELECT a.codigo, a.nombre, a.descripcion FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $id AND pa.proyecto_id = $pid")[0] ?? null;
        if (!$a) return null;
        $csi = array_column(self::filas("SELECT m.referencia FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id WHERE am.actividad_id = $id"), 'referencia');
        return ['nombre' => $a['codigo'] . ' ' . $a['nombre'], 'texto' => $a['nombre'] . ' ' . $a['descripcion'], 'csi' => $csi ? preg_replace('/\D/', '', (string)$csi[0]) : ''];
    }

    /** Fragmentos candidatos para una entidad: primero los de su sección CSI, luego por palabras. Excluye los ya vinculados (en cualquier estado) */
    public static function candidatos(int $pid, string $tipo, int $id, int $limite = 4): array
    {
        $e = self::entidad($pid, $tipo, $id);
        if (!$e) return [];
        $ya = array_map('intval', array_column(self::filas("SELECT fragmento_id FROM normativa_aplicaciones WHERE proyecto_id = $pid AND entidad_tipo = '$tipo' AND entidad_id = $id AND fragmento_id IS NOT NULL"), 'fragmento_id'));
        $out = [];
        if (strlen($e['csi']) === 6) {
            $pal = Glosario::expandir(Glosario::palabras($e['texto']));
            $sec = self::filas("SELECT f.id, f.documento_id, d.titulo doc, d.codigo, f.pagina, f.clausula, f.titulo, f.texto, f.idioma, f.csi FROM normativa_fragmentos f JOIN normativa_documentos d ON d.id = f.documento_id WHERE f.proyecto_id = $pid AND f.csi = '{$e['csi']}' ORDER BY f.id LIMIT 80");
            foreach ($sec as $i => $f) {
                $tk = array_flip(Glosario::palabras($f['titulo'] . ' ' . $f['texto']));
                $sec[$i]['_p'] = (str_starts_with($f['clausula'], 'Sección') ? 1000 : 0) + count(array_filter($pal, fn($w) => isset($tk[$w]) || isset($tk[Glosario::variantes($w)[1] ?? $w])));
            }
            usort($sec, fn($x, $y) => $y['_p'] <=> $x['_p'] ?: $x['id'] <=> $y['id']);
            foreach (array_slice($sec, 0, $limite) as $f) {
                if (in_array((int)$f['id'], $ya, true)) continue;
                unset($f['_p']);
                $out[(int)$f['id']] = $f + ['puntaje' => 100, 'origen' => 'csi'];
            }
        }
        foreach (self::buscar($pid, Glosario::palabras($e['texto']), $limite, $ya) as $f) if (!isset($out[(int)$f['id']])) $out[(int)$f['id']] = $f;
        return array_slice(array_values($out), 0, $limite);
    }

    // ---------- vínculos ----------

    private static function crearVinculo(int $pid, string $tipo, int $id, ?int $frag, ?int $doc, string $ref, ?string $req, string $nota, string $origen, string $estado, float $puntaje, string $modelo = ''): int
    {
        $db = self::db();
        $db->query("INSERT IGNORE INTO normativa_aplicaciones (proyecto_id, documento_id, fragmento_id, entidad_tipo, entidad_id, referencia, requisito, nota, origen, estado, puntaje, modelo) VALUES ($pid, " . ($doc ?: 'NULL') . ', ' . ($frag ?: 'NULL') . ", '$tipo', $id, '" . self::esc(mb_substr($ref, 0, 200)) . "', " . ($req === null || $req === '' ? 'NULL' : "'" . self::esc(mb_substr($req, 0, 1500)) . "'") . ", '" . self::esc(mb_substr($nota, 0, 500)) . "', '$origen', '$estado', $puntaje, '" . self::esc(mb_substr($modelo, 0, 150)) . "')");
        return (int)$db->affected_rows > 0 ? (int)$db->insert_id : 0;
    }

    /** Propone vínculos sin IA (sección CSI exacta y palabras con buen puntaje) para entidades sin ninguno. @return array{entidades: int, propuestas: int} */
    public static function sugerirSinIA(Proyecto $p, string $ambito = 'ambos', int $max = 40): array
    {
        $pid = (int)$p->id;
        $ent = [];
        if (in_array($ambito, ['materiales', 'ambos'], true)) foreach (self::filas("SELECT id FROM materiales WHERE proyecto_id = $pid AND id NOT IN (SELECT entidad_id FROM normativa_aplicaciones WHERE entidad_tipo = 'material' AND proyecto_id = $pid) ORDER BY id LIMIT $max") as $f) $ent[] = ['material', (int)$f['id']];
        if (in_array($ambito, ['actividades', 'ambos'], true)) foreach (self::filas("SELECT a.id FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND a.hito = 0 AND a.id NOT IN (SELECT entidad_id FROM normativa_aplicaciones WHERE entidad_tipo = 'actividad' AND proyecto_id = $pid) ORDER BY a.id LIMIT $max") as $f) $ent[] = ['actividad', (int)$f['id']];
        $n = 0;
        foreach (array_slice($ent, 0, $max) as [$tipo, $id]) {
            foreach (self::candidatos($pid, $tipo, $id, 3) as $c) {
                if ($c['origen'] === 'palabras' && $c['puntaje'] < 60) continue;
                if (self::crearVinculo($pid, $tipo, $id, (int)$c['id'], (int)$c['documento_id'], '', null, '', $c['origen'], 'propuesta', (float)$c['puntaje'])) $n++;
            }
        }
        return ['entidades' => count($ent), 'propuestas' => $n];
    }

    /** Vínculo propuesto por las reglas del sistema a partir de un candidato de `candidatos()` */
    public static function proponerRegla(int $pid, string $tipo, int $id, array $c): bool
    {
        return (bool)self::crearVinculo($pid, $tipo, $id, (int)$c['id'], (int)$c['documento_id'], '', null, '', $c['origen'], 'propuesta', (float)$c['puntaje']);
    }

    /** Vínculo propuesto por la IA (validado por quien llama) */
    public static function proponerIA(int $pid, string $tipo, int $id, int $frag, string $requisito, string $modelo): bool
    {
        $f = self::filas("SELECT documento_id FROM normativa_fragmentos WHERE id = $frag AND proyecto_id = $pid")[0] ?? null;
        return $f ? (bool)self::crearVinculo($pid, $tipo, $id, $frag, (int)$f['documento_id'], '', $requisito, '', 'ia', 'propuesta', 100, $modelo) : false;
    }

    public static function agregarManual(int $pid, string $tipo, int $id, ?int $doc, string $ref, string $nota): bool
    {
        if (!self::entidad($pid, $tipo, $id) || (trim($ref) === '' && !$doc)) return false;
        if ($doc && !self::obtener($pid, $doc)) $doc = null;
        $db = self::db();
        $db->query("INSERT INTO normativa_aplicaciones (proyecto_id, documento_id, entidad_tipo, entidad_id, referencia, nota, origen, estado, resuelto_por) VALUES ($pid, " . ($doc ?: 'NULL') . ", '$tipo', $id, '" . self::esc(mb_substr($ref, 0, 200)) . "', '" . self::esc(mb_substr($nota, 0, 500)) . "', 'manual', 'aceptada', '" . self::esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150)) . "')");
        return true;
    }

    /** Vínculos manuales que vienen en un formulario de creación o edición (normativa_doc[], normativa_ref[], normativa_nota[]) */
    public static function guardarManuales(string $tipo, int $id, int $pid, array $post): int
    {
        $n = 0;
        $refs = (array)($post['normativa_ref'] ?? []);
        foreach ($refs as $i => $ref) {
            if (self::agregarManual($pid, $tipo, $id, ((int)(($post['normativa_doc'] ?? [])[$i] ?? 0)) ?: null, (string)$ref, (string)(($post['normativa_nota'] ?? [])[$i] ?? ''))) $n++;
        }
        return $n;
    }

    public static function resolver(Proyecto $p, int $id, string $estado): array
    {
        $pid = (int)$p->id;
        $a = self::filas("SELECT * FROM normativa_aplicaciones WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
        if (!$a || $a['estado'] !== 'propuesta') return [false, 'El vínculo ya no está pendiente'];
        if (!in_array($estado, ['aceptada', 'rechazada'], true)) return [false, 'Estado no válido'];
        self::db()->query("UPDATE normativa_aplicaciones SET estado = '$estado', resuelto_por = '" . self::esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150)) . "' WHERE id = $id");
        $e = self::entidad($pid, $a['entidad_tipo'], (int)$a['entidad_id']);
        Auditoria::registrar($pid, 'normativa', $estado === 'aceptada' ? 'aplicado' : 'rechazado', 'vincular_normativa', ($estado === 'aceptada' ? 'Normativa vinculada a ' : 'Vínculo de normativa rechazado para ') . ($e['nombre'] ?? $a['entidad_tipo']), null, ['aplicacion_id' => $id, 'fragmento_id' => $a['fragmento_id']], $id, (string)$a['modelo'], [$a['entidad_tipo'], (int)$a['entidad_id'], $e['nombre'] ?? '']);
        return [true, $estado === 'aceptada' ? 'Normativa vinculada' : 'Rechazado: no se volverá a proponer'];
    }

    public static function quitar(Proyecto $p, int $id): bool
    {
        $pid = (int)$p->id;
        $a = self::filas("SELECT * FROM normativa_aplicaciones WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
        if (!$a) return false;
        if ($a['origen'] === 'manual' || $a['estado'] === 'aceptada') {
            $e = self::entidad($pid, $a['entidad_tipo'], (int)$a['entidad_id']);
            Auditoria::registrar($pid, 'normativa', 'revertido', 'vincular_normativa', 'Normativa desvinculada de ' . ($e['nombre'] ?? $a['entidad_tipo']), ['referencia' => $a['referencia'], 'fragmento_id' => $a['fragmento_id']], null, $id, '', [$a['entidad_tipo'], (int)$a['entidad_id'], $e['nombre'] ?? '']);
        }
        self::db()->query("DELETE FROM normativa_aplicaciones WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    /** Vínculos de una entidad (para su formulario) con la cita del fragmento */
    public static function deEntidad(int $pid, string $tipo, int $id): array
    {
        return self::filas("SELECT a.*, d.titulo doc, d.codigo, f.pagina, f.clausula, f.titulo ftitulo, LEFT(f.texto, 500) ftexto, f.idioma FROM normativa_aplicaciones a LEFT JOIN normativa_documentos d ON d.id = a.documento_id LEFT JOIN normativa_fragmentos f ON f.id = a.fragmento_id
            WHERE a.proyecto_id = $pid AND a.entidad_tipo = '$tipo' AND a.entidad_id = $id AND a.estado <> 'rechazada' ORDER BY FIELD(a.estado, 'aceptada', 'propuesta'), a.id");
    }

    public static function aplicaciones(int $pid, array $f = []): array
    {
        $w = ["a.proyecto_id = $pid"];
        if (in_array($f['estado'] ?? 'propuesta', ['propuesta', 'aceptada', 'rechazada'], true)) $w[] = "a.estado = '" . ($f['estado'] ?? 'propuesta') . "'";
        if (in_array($f['entidad_tipo'] ?? '', ['actividad', 'material'], true)) $w[] = "a.entidad_tipo = '{$f['entidad_tipo']}'";
        $filas = self::filas("SELECT a.*, d.titulo doc, d.codigo, f.pagina, f.clausula, f.titulo ftitulo, LEFT(f.texto, 700) ftexto, f.idioma FROM normativa_aplicaciones a LEFT JOIN normativa_documentos d ON d.id = a.documento_id LEFT JOIN normativa_fragmentos f ON f.id = a.fragmento_id WHERE " . implode(' AND ', $w) . ' ORDER BY a.puntaje DESC, a.id DESC LIMIT 300');
        foreach ($filas as &$x) $x['entidad'] = self::entidad($pid, $x['entidad_tipo'], (int)$x['entidad_id'])['nombre'] ?? '(eliminada)';
        return $filas;
    }

    public static function conteoPendientes(int $pid): int
    {
        return (int)(self::filas("SELECT COUNT(*) n FROM normativa_aplicaciones WHERE proyecto_id = $pid AND estado = 'propuesta'")[0]['n'] ?? 0);
    }
}
