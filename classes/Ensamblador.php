<?php

namespace Classes;

use Model\Actividad;
use Model\Archivo;
use Model\Material;
use Model\Partida;
use Model\Proyecto;
use Model\Submittal;

/**
 * Ensamblador (app de escritorio, solo admin): la IA lee PDF, PowerPoint, Word, OpenDocument o texto y propone
 * partidas nuevas, cambios a partidas que coinciden por nombre, materiales y paquetes de submittal.
 * El servidor extrae el texto, arma los lotes, valida lo que contesta la IA y aplica/revierte; la IA nunca escribe datos.
 */
class Ensamblador
{
    use AccesoDb;

    public const EXTENSIONES = ['pdf', 'docx', 'doc', 'pptx', 'odt', 'txt', 'md'];
    public const MAX_BYTES = 50 * 1024 * 1024;
    public const MODOS = [
        'partidas_nuevas' => 'Agregar partidas nuevas',
        'partidas_existentes' => 'Modificar partidas existentes (si coincide el nombre)',
        'materiales' => 'Agregar materiales',
        'submittal_nuevo' => 'Crear un submittal nuevo',
        'submittal_existente' => 'Agregar a un submittal existente',
        'dependencias' => 'Dependencias entre actividades («después de…», «previo a…»)',
        'contrato' => 'Contrato: fechas contractuales, festivos y restricciones',
        'responsables' => 'Contactos y responsables (directorios y minutas)',
        'cantidades' => 'Cantidades de obra (catálogo de conceptos)',
        'minuta' => 'Compromisos de una minuta (a la bitácora)',
        'normativa' => 'Guardar el documento en Normativa',
    ];
    private const LOTE_CARACTERES = 6500;
    private const MAX_LOTES = 40;
    private const MAX_CARACTERES = 3000000;
    private const PALABRAS_VACIAS = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'e', 'en', 'para', 'con', 'a', 'al', 'un', 'una', 'tipo', 'por'];

    private static function filas(string $sql): array
    {
        $r = self::db()->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }

    // ------------------------------------------------------------------ nombres

    /** Minúsculas, sin acentos ni signos ni palabras vacías: para comparar nombres */
    public static function norm(string $s): string
    {
        $s = strtr(mb_strtolower($s), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        $p = array_filter(explode(' ', trim($s)), fn($w) => $w !== '' && !in_array($w, self::PALABRAS_VACIAS, true));
        return implode(' ', $p);
    }

    /** 0..1: 1 = mismo nombre normalizado */
    public static function similitud(string $a, string $b): float
    {
        $na = self::norm($a);
        $nb = self::norm($b);
        if ($na === '' || $nb === '') return 0.0;
        if ($na === $nb) return 1.0;
        $ta = array_unique(explode(' ', $na));
        $tb = array_unique(explode(' ', $nb));
        $jac = count(array_intersect($ta, $tb)) / max(1, count(array_unique(array_merge($ta, $tb))));
        similar_text(mb_substr($na, 0, 200), mb_substr($nb, 0, 200), $pct);
        $contiene = (mb_strlen($na) >= 6 && mb_strlen($nb) >= 6 && (str_contains($na, $nb) || str_contains($nb, $na))) ? 0.88 : 0.0;
        return max($jac, $pct / 100, $contiene);
    }

    /** Mejor candidato [id, similitud] entre [id => nombre] */
    public static function mejor(string $nombre, array $candidatos): array
    {
        $id = null;
        $max = 0.0;
        foreach ($candidatos as $cid => $cn) {
            $s = self::similitud($nombre, (string)$cn);
            if ($s > $max) { $max = $s; $id = $cid; }
        }
        return [$id, $max];
    }

    // ------------------------------------------------------------------ extracción de texto

    /** Texto por páginas (diapositivas, secciones…). @return array{0: ?array, 1: string} páginas o null + aviso */
    public static function extraer(string $ruta, string $ext): array
    {
        return match ($ext) {
            'pdf', 'docx', 'doc' => Normativa::extraer($ruta),
            'pptx' => self::extraerPptx($ruta),
            'odt' => self::extraerOdt($ruta),
            default => self::extraerTexto($ruta),
        };
    }

    private static function agrupar(string $texto): array
    {
        $grupos = [];
        $cur = '';
        foreach (explode("\n", $texto) as $l) {
            $cur .= $l . "\n";
            if (mb_strlen($cur) >= 3500) { $grupos[] = $cur; $cur = ''; }
        }
        if (trim($cur) !== '') $grupos[] = $cur;
        return $grupos;
    }

    private static function extraerTexto(string $ruta): array
    {
        $t = (string)@file_get_contents($ruta, false, null, 0, self::MAX_BYTES);
        if (!mb_check_encoding($t, 'UTF-8')) $t = mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
        $t = preg_replace('/^\xEF\xBB\xBF/', '', $t);
        $p = self::agrupar(str_replace("\r", '', $t));
        return $p ? [$p, ''] : [null, 'El archivo de texto está vacío.'];
    }

    private static function dom(string $xml): ?\DOMDocument
    {
        $d = new \DOMDocument();
        return ($xml !== '' && @$d->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) ? $d : null;
    }

    private static function extraerPptx(string $ruta): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) return [null, 'No se pudo abrir la presentación.'];
        $diapos = $notas = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = (string)$zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $n, $m)) $diapos[(int)$m[1]] = $n;
            elseif (preg_match('#^ppt/notesSlides/notesSlide(\d+)\.xml$#', $n, $m)) $notas[(int)$m[1]] = $n;
        }
        ksort($diapos);
        $ns = 'http://schemas.openxmlformats.org/drawingml/2006/main';
        $texto = function (string $nombre) use ($zip, $ns): string {
            $xml = (string)$zip->getFromName($nombre);
            if (strlen($xml) > 40 * 1024 * 1024 || !($d = self::dom($xml))) return '';
            $out = '';
            foreach ($d->getElementsByTagNameNS($ns, 'p') as $p) {
                $l = '';
                foreach ($p->getElementsByTagNameNS($ns, 't') as $t) $l .= $t->textContent;
                if (trim($l) !== '') $out .= trim($l) . "\n";
            }
            return $out;
        };
        $pag = [];
        foreach ($diapos as $num => $nombre) {
            $t = $texto($nombre);
            if (isset($notas[$num]) && ($nt = trim($texto($notas[$num]))) !== '') $t .= "Notas del orador: $nt\n";
            if (trim($t) !== '') $pag[] = "Diapositiva $num\n$t";
        }
        $zip->close();
        return $pag ? [$pag, ''] : [null, 'La presentación no tiene texto (¿solo imágenes?).'];
    }

    private static function extraerOdt(string $ruta): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) return [null, 'No se pudo abrir el documento.'];
        $xml = (string)$zip->getFromName('content.xml');
        $zip->close();
        if (strlen($xml) > 80 * 1024 * 1024 || !($d = self::dom($xml))) return [null, 'El documento está vacío o dañado.'];
        $ns = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
        $out = '';
        $xp = new \DOMXPath($d);
        $xp->registerNamespace('text', $ns);
        foreach ($xp->query('//text:h | //text:p') as $n) $out .= trim($n->textContent) . "\n";
        $p = self::agrupar($out);
        return $p ? [$p, ''] : [null, 'El documento no tiene texto.'];
    }

    // ------------------------------------------------------------------ sesiones y archivos

    public static function crearSesion(int $pid): int
    {
        $u = self::esc(usuario_actual()['nombre'] ?? '');
        self::db()->query("INSERT INTO ensamblador_sesiones (proyecto_id, usuario) VALUES ($pid, '$u')");
        return (int)self::db()->insert_id;
    }

    public static function sesionValida(int $pid, int $sesion): bool
    {
        return (bool)self::filas("SELECT id FROM ensamblador_sesiones WHERE id = $sesion AND proyecto_id = $pid");
    }

    /**
     * Guarda un archivo subido: revisión de seguridad, copia en el Archivero y texto extraído.
     * @return array{ok: bool, error?: string, archivo?: array}
     */
    public static function agregarArchivo(Proyecto $p, int $sesion, array $f): array
    {
        $pid = (int)$p->id;
        if (empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) return ['ok' => false, 'error' => 'No llegó el archivo (¿supera el límite del servidor?)'];
        $nombre = mb_substr(basename((string)$f['name']), 0, 255);
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES, true)) return ['ok' => false, 'error' => "Formato no admitido (.$ext). Usa PDF, PowerPoint (.pptx), Word, OpenDocument (.odt) o texto."];
        if ($f['size'] > self::MAX_BYTES) return ['ok' => false, 'error' => 'El archivo supera los 50 MB'];
        if ($motivos = EscanerArchivos::revisar($f['tmp_name'], $nombre)) return ['ok' => false, 'error' => implode(' ', $motivos)];
        $hash = hash_file('sha256', $f['tmp_name']);
        $previo = self::filas("SELECT * FROM ensamblador_archivos WHERE proyecto_id = $pid AND hash = '" . self::esc($hash) . "' ORDER BY id DESC LIMIT 1")[0] ?? null;
        $duplicado = $previo !== null;
        if ($previo && (int)$previo['sesion_id'] === $sesion) {   // el mismo archivo ya está en esta conversación
            return ['ok' => true, 'archivo' => ['id' => (int)$previo['id'], 'nombre' => $nombre, 'paginas' => (int)$previo['paginas'], 'caracteres' => (int)$previo['caracteres'], 'aviso' => $previo['aviso'], 'duplicado' => true, 'repetido' => true, 'archivo_id' => $previo['archivo_id'], 'sin_texto' => trim((string)$previo['texto']) === '']];
        }

        $archivoId = $previo['archivo_id'] ?? null;
        $texto = $previo['texto'] ?? null;
        $aviso = $previo['aviso'] ?? '';
        $paginas = (int)($previo['paginas'] ?? 0);
        if (!$duplicado) {
            if (!Almacenamiento::cabe($pid, (int)$f['size'])) return ['ok' => false, 'error' => Almacenamiento::mensajeLleno($pid)];
            $dir = Archivo::carpeta($pid);
            $guardado = Archivo::guardarArchivoSubido($pid, $f['tmp_name'], $ext);
            if (!$guardado) return ['ok' => false, 'error' => 'No se pudo guardar el archivo en el servidor'];
            // Copia en el Archivero (solo los formatos que acepta esa sección; el resto queda solo como texto)
            if (in_array($ext, Archivo::EXTENSIONES, true)) {
                $a = new Archivo(['proyecto_id' => $pid, 'nombre' => mb_substr(pathinfo($nombre, PATHINFO_FILENAME), 0, 200), 'archivo_original' => $nombre, 'archivo_guardado' => $guardado, 'tamano' => (int)$f['size']]);
                $r = $a->guardar();
                $archivoId = is_array($r) ? (int)$r['id'] : null;
            }
            [$pags, $aviso] = self::extraer("$dir/$guardado", $ext);
            $texto = $pags ? implode("\f", $pags) : '';
            if ($texto !== '' && mb_strlen($texto) > self::MAX_CARACTERES) { $texto = mb_substr($texto, 0, self::MAX_CARACTERES); $aviso = 'Documento muy largo: se leyeron los primeros ' . number_format(self::MAX_CARACTERES, 0, '.', ',') . ' caracteres.'; }
            $paginas = $pags ? count($pags) : 0;
            if (!$pags && $ext === 'pdf') $aviso = trim(($aviso ? $aviso . ' ' : '') . 'Si es un PDF escaneado, necesita OCR: este equipo no lo tiene.');
        }
        $sql = sprintf(
            "INSERT INTO ensamblador_archivos (sesion_id, proyecto_id, nombre, hash, tamano, paginas, caracteres, texto, aviso, archivo_id) VALUES (%d, %d, '%s', '%s', %d, %d, %d, '%s', '%s', %s)",
            $sesion, $pid, self::esc($nombre), self::esc($hash), (int)$f['size'], $paginas, mb_strlen((string)$texto), self::esc($texto), self::esc(mb_substr($aviso, 0, 255)), $archivoId ? (int)$archivoId : 'NULL'
        );
        self::db()->query($sql);
        $id = (int)self::db()->insert_id;
        return ['ok' => true, 'archivo' => ['id' => $id, 'nombre' => $nombre, 'paginas' => $paginas, 'caracteres' => mb_strlen((string)$texto), 'aviso' => $aviso, 'duplicado' => $duplicado, 'archivo_id' => $archivoId, 'sin_texto' => trim((string)$texto) === '']];
    }

    public static function archivos(int $pid, int $sesion): array
    {
        return self::filas("SELECT id, nombre, paginas, caracteres, aviso, archivo_id, texto FROM ensamblador_archivos WHERE proyecto_id = $pid AND sesion_id = $sesion ORDER BY id ASC");
    }

    public static function quitarArchivo(int $pid, int $sesion, int $id): void
    {
        self::db()->query("DELETE FROM ensamblador_archivos WHERE id = $id AND proyecto_id = $pid AND sesion_id = $sesion");
    }

    // ------------------------------------------------------------------ contexto del proyecto

    public static function submittals(int $pid): array
    {
        return array_map(fn($s) => ['id' => (int)$s['id'], 'numero' => $s['numero_submittal'], 'especialidad' => $s['especialidad'], 'estatus' => $s['estatus']],
            self::filas("SELECT id, numero_submittal, especialidad, estatus FROM submittals WHERE proyecto_id = $pid ORDER BY numero_submittal ASC, id ASC"));
    }

    private static function partidasProyecto(int $pid): array
    {
        return self::filas("SELECT id, codigo, nombre, descripcion FROM partidas WHERE proyecto_id = $pid ORDER BY orden ASC, id ASC");
    }

    private static function actividadesProyecto(int $pid): array
    {
        return self::filas("SELECT a.id, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid ORDER BY pa.orden ASC, pa.id ASC, a.orden ASC, a.id ASC");
    }

    private static function materialesProyecto(int $pid): array
    {
        return self::filas("SELECT id, nombre, nombre_base, marca, modelo, valor_extra, referencia, cantidad, unidad, especialidad FROM materiales WHERE proyecto_id = $pid ORDER BY nombre ASC");
    }

    // ------------------------------------------------------------------ lotes para la IA

    /** Páginas [archivo, n, texto] de la sesión */
    private static function paginas(int $pid, int $sesion): array
    {
        $out = [];
        foreach (self::archivos($pid, $sesion) as $a) {
            foreach (explode("\f", (string)$a['texto']) as $i => $t) if (trim($t) !== '') $out[] = [$a['nombre'], $i + 1, trim($t)];
        }
        return $out;
    }

    /** Parte las páginas en trozos de ~6500 caracteres, con su procedencia. @return array [{titulo, texto}] */
    private static function trozos(array $paginas): array
    {
        $trozos = [];
        $cur = null;
        $cierra = function () use (&$trozos, &$cur) {
            if ($cur && trim($cur['texto']) !== '') { $trozos[] = ['titulo' => $cur['archivo'] . ' · págs. ' . $cur['desde'] . ($cur['hasta'] > $cur['desde'] ? '–' . $cur['hasta'] : ''), 'texto' => $cur['texto']]; }
            $cur = null;
        };
        foreach ($paginas as [$arch, $n, $t]) {
            foreach (mb_str_split($t, self::LOTE_CARACTERES) as $parte) {
                if ($cur && ($cur['archivo'] !== $arch || mb_strlen($cur['texto']) + mb_strlen($parte) > self::LOTE_CARACTERES)) $cierra();
                if (!$cur) $cur = ['archivo' => $arch, 'desde' => $n, 'hasta' => $n, 'texto' => ''];
                $cur['hasta'] = $n;
                $cur['texto'] .= "[pág. $n]\n$parte\n\n";
            }
        }
        $cierra();
        return $trozos;
    }

    private static function contextoProyecto(Proyecto $p, bool $actividades = false, bool $personal = false): string
    {
        $pid = (int)$p->id;
        $l = ['PROYECTO: ' . $p->nombre];
        $l[] = 'PARTIDAS EXISTENTES (id | código | nombre):';
        foreach (array_slice(self::partidasProyecto($pid), 0, 150) as $x) $l[] = "{$x['id']} | {$x['codigo']} | " . mb_substr($x['nombre'], 0, 90);
        $l[] = 'MATERIALES EXISTENTES (id | nombre):';
        foreach (array_slice(self::materialesProyecto($pid), 0, 300) as $x) $l[] = "{$x['id']} | " . mb_substr($x['nombre'], 0, 90);
        if ($personal) {
            $l[] = 'PERSONAL EXISTENTE (id | nombre | departamento):';
            foreach (array_slice(self::filas("SELECT id, nombre, departamento FROM personal WHERE proyecto_id = $pid ORDER BY nombre"), 0, 200) as $x) $l[] = "{$x['id']} | {$x['nombre']} | {$x['departamento']}";
        }
        if ($actividades) {
            $l[] = 'ACTIVIDADES EXISTENTES (id | código | nombre):';
            foreach (array_slice(self::actividadesProyecto($pid), 0, 400) as $x) $l[] = "{$x['id']} | {$x['codigo']} | " . mb_substr($x['nombre'], 0, 90);
        }
        $l[] = 'SUBMITTALS EXISTENTES (id | número | especialidad):';
        foreach (array_slice(self::submittals($pid), 0, 100) as $x) $l[] = "{$x['id']} | {$x['numero']} | {$x['especialidad']}";
        return implode("\n", $l);
    }

    /** ¿El mensaje libre es una pregunta sobre el documento y no una orden? */
    public static function esPregunta(string $texto, array $modos): bool
    {
        if ($modos) return false;
        $t = trim(mb_strtolower($texto));
        return $t !== '' && (str_contains($t, '?') || str_contains($t, '¿') || (bool)preg_match('/^(qu[eé]|cu[aá]l|cu[aá]nt|c[oó]mo|d[oó]nde|resume|resumen|dime|explica|hay )/u', $t));
    }

    /** @return array{tipo: string, lotes: array, aviso?: string, mensaje?: string} */
    public static function lotes(Proyecto $p, int $sesion, string $instruccion, array $modos, int $submittalId): array
    {
        $pid = (int)$p->id;
        $paginas = self::paginas($pid, $sesion);
        if (!$paginas) return ['tipo' => 'ninguno', 'lotes' => [], 'mensaje' => 'Sube primero un documento con texto legible.'];
        $trozos = self::trozos($paginas);
        $aviso = '';
        if (count($trozos) > self::MAX_LOTES) { $trozos = array_slice($trozos, 0, self::MAX_LOTES); $aviso = 'El documento es muy largo: se analizan solo los primeros ' . self::MAX_LOTES . ' fragmentos.'; }

        if (self::esPregunta($instruccion, $modos)) {
            $claves = array_filter(explode(' ', self::norm($instruccion)), fn($w) => mb_strlen($w) > 2);
            $puntos = [];
            foreach ($trozos as $i => $t) { $n = self::norm($t['texto']); $puntos[$i] = array_sum(array_map(fn($k) => substr_count($n, $k), $claves)); }
            arsort($puntos);
            $elegidos = array_slice(array_keys($puntos), 0, 3);
            sort($elegidos);
            $doc = implode("\n\n---\n\n", array_map(fn($i) => '(' . $trozos[$i]['titulo'] . ")\n" . $trozos[$i]['texto'], $elegidos));
            $prompt = "Eres un asistente de obra civil. Responde en español, breve y solo con lo que dicen los fragmentos del documento; si no está, dilo.\n\nPREGUNTA: " . $instruccion . "\n\nFRAGMENTOS DEL DOCUMENTO:\n" . $doc . "\n\nResponde SOLO con este JSON: {\"respuesta\": \"...\"}";
            return ['tipo' => 'pregunta', 'lotes' => [['titulo' => 'Respondiendo sobre el documento', 'prompt' => $prompt]], 'aviso' => $aviso];
        }

        if ($modos === ['normativa']) return ['tipo' => 'local', 'lotes' => [], 'aviso' => $aviso];   // no necesita IA: solo se guarda el archivo
        $todos = !$modos;
        $quiere = fn(string $m) => $m !== 'normativa' && ($todos || in_array($m, $modos, true));
        $pide = [];
        if ($quiere('partidas_nuevas') || $quiere('partidas_existentes')) $pide[] = '"partidas"';
        if ($quiere('materiales') || $quiere('submittal_nuevo') || $quiere('submittal_existente')) $pide[] = '"materiales"';
        if ($quiere('submittal_nuevo') || $quiere('submittal_existente')) $pide[] = '"submittal"';
        if ($quiere('dependencias')) $pide[] = '"dependencias"';
        if ($quiere('contrato')) array_push($pide, '"fechas_contrato"', '"festivos"', '"restricciones"');
        if ($quiere('responsables')) array_push($pide, '"contactos"', '"responsables"');
        if ($quiere('cantidades')) $pide[] = '"cantidades"';
        if ($quiere('minuta')) $pide[] = '"compromisos"';
        $queUsuario = [];
        foreach (self::MODOS as $k => $etq) if (in_array($k, $modos, true)) $queUsuario[] = $etq . ($k === 'submittal_existente' && $submittalId ? " (submittal id $submittalId)" : '');
        $ctx = self::contextoProyecto($p, $quiere('dependencias') || $quiere('contrato') || $quiere('cantidades') || $quiere('responsables'), $quiere('responsables') || $quiere('minuta'));
        $formato = <<<'TXT'
Responde SOLO con un JSON con esta forma (omite las claves que no apliquen; usa null si el documento no lo dice):
{
 "partidas": [{"nombre": "", "descripcion": "", "coincide_con_id": null,
               "actividades": [{"nombre": "", "duracion_dias": null, "descripcion": ""}]}],
 "materiales": [{"nombre_base": "", "marca": "", "modelo": "", "valor_extra": "", "cantidad": null, "unidad": "", "especialidad": "", "referencia": "", "coincide_con_id": null}],
 "submittal": {"numero": "", "especialidad": "", "materiales": ["nombre del material"]},
 "dependencias": [{"predecesora": "", "predecesora_id": null, "sucesora": "", "sucesora_id": null, "tipo": "FS", "retraso_dias": 0, "cita": ""}],
 "fechas_contrato": [{"tipo": "inicio|fin|hito", "nombre": "", "fecha": "AAAA-MM-DD", "penalizacion": "", "cita": ""}],
 "festivos": [{"fecha": "AAAA-MM-DD", "motivo": "", "cita": ""}],
 "restricciones": [{"descripcion": "", "tipo": "Contrato", "fecha": null, "actividad": "", "actividad_id": null, "cita": ""}],
 "contactos": [{"nombre": "", "cargo": "", "empresa": "", "departamento": "", "telefono": "", "correo": ""}],
 "responsables": [{"persona": "", "actividad": "", "actividad_id": null, "cita": ""}],
 "cantidades": [{"clave": "", "concepto": "", "cantidad": null, "unidad": "", "importe": null, "actividad": "", "actividad_id": null, "cita": ""}],
 "compromisos": [{"tema": "", "responsable": "", "departamento": "", "fecha_reunion": null, "fecha_limite": null, "importancia": "media", "cita": ""}],
 "notas": ["advertencias o dudas sobre el documento"]
}
Reglas:
- Usa SOLO lo que dice el fragmento; no inventes cantidades, marcas, modelos, duraciones ni nombres.
- "coincide_con_id": el id de la partida/material EXISTENTE (de las listas) que es claramente la misma cosa que el documento menciona; si no estás seguro, null.
- Un material es un artículo comprable o instalable (equipo, cable, cámara, tubería…). "nombre_base" es el nombre genérico sin marca ni modelo; "marca" y "modelo" van aparte; "valor_extra" para capacidad, calibre, longitud, color, etc.
- "cantidad" es un número; "unidad" una abreviatura (pza, m, m2, kg, lote…).
- Una partida es un capítulo/concepto de trabajo; sus actividades son los pasos concretos.
- Las claves "partidas"/"materiales"/"submittal"/"dependencias"/"fechas_contrato"/"festivos"/"restricciones" deben ir solo si el usuario las pidió (igual "contactos"/"responsables"/"cantidades"/"compromisos").
- "dependencias": "después de X" / "una vez terminado X" = X es predecesora de la actividad que habla (tipo FS); "previo a Y" = la actividad que habla es predecesora de Y; "al mismo tiempo que / junto con" = SS. Usa el id de ACTIVIDADES EXISTENTES; si no hay una clara, deja el id en null y escribe el nombre. "retraso_dias" negativo = adelanto.
- "fechas_contrato": inicio, término y hitos con fecha; "penalizacion" solo si el texto la menciona. Fechas siempre AAAA-MM-DD; si falta el año o es ambiguo, no la incluyas y dilo en "notas".
- "festivos": solo días que el documento declara inhábiles o no laborables, uno por fecha.
- "contactos": personas con nombre propio de directorios, minutas o listas de asistentes; teléfono y correo solo si aparecen escritos. No inventes cargos.
- "responsables": quién ejecuta o responde por una actividad existente ("a cargo de", "responsable:"). "persona" es el nombre tal como aparece.
- "cantidades": renglones del catálogo de conceptos (clave, descripción, cantidad, unidad, importe si lo trae). Liga cada uno a la actividad existente que lo ejecuta; si no hay una clara, deja actividad_id en null. No calcules importes.
- "compromisos": acuerdos de una minuta con responsable y/o fecha. "importancia": baja, media, alta o critica. Fechas AAAA-MM-DD.
- "restricciones": permisos, entregas o autorizaciones que depende de un tercero (cliente, autoridad) y que condicionan una actividad. "tipo" es uno de: Clima, Contrato, Ingeniería, RFI, Submittal, Material, Equipo, Mano de Obra, Acceso, Otro. "cita" es la frase corta del documento de donde sale.
TXT;
        $lotes = [];
        foreach ($trozos as $i => $t) {
            $prompt = "Eres un asistente de planeación de obra civil que convierte documentos en datos del programa de obra.\n\n"
                . 'LO QUE PIDE EL USUARIO: ' . ($queUsuario ? implode('; ', $queUsuario) : 'decide tú qué información útil trae el documento (partidas, materiales, submittal)') . "\n"
                . ($instruccion !== '' ? 'INSTRUCCIÓN ADICIONAL DEL USUARIO: ' . $instruccion . "\n" : '')
                . 'Claves a devolver: ' . implode(', ', $pide ?: ['"notas"']) . "\n\n"
                . "DATOS ACTUALES DEL PROYECTO:\n$ctx\n\n"
                . 'FRAGMENTO ' . ($i + 1) . ' de ' . count($trozos) . ' (' . $t['titulo'] . "):\n" . $t['texto'] . "\n\n" . $formato;
            $lotes[] = ['titulo' => 'Fragmento ' . ($i + 1) . ' de ' . count($trozos) . ' · ' . $t['titulo'], 'prompt' => $prompt];
        }
        return ['tipo' => 'extraccion', 'lotes' => $lotes, 'aviso' => $aviso];
    }

    // ------------------------------------------------------------------ de la respuesta de la IA a la propuesta

    private static function texto($v, int $max = 255): string
    {
        return is_scalar($v) ? trim(mb_substr(preg_replace('/\s+/u', ' ', (string)$v), 0, $max)) : '';
    }

    private static function numero($v): ?float
    {
        if (is_string($v)) $v = str_replace([',', ' '], ['', ''], $v);
        return is_numeric($v) && $v >= 0 && $v < 1e9 ? round((float)$v, 2) : null;
    }

    /**
     * Valida y junta las respuestas de todos los lotes, compara con lo que ya existe y devuelve los ítems de la propuesta.
     * @param array $respuestas JSON de cada lote
     */
    public static function proponer(Proyecto $p, array $respuestas, array $modos, int $submittalId, int $sesion = 0): array
    {
        $pid = (int)$p->id;
        $todos = !$modos;
        $quiere = fn(string $m) => $todos || in_array($m, $modos, true);
        $partidas = self::partidasProyecto($pid);
        $nombresPartida = array_column($partidas, 'nombre', 'id');
        $porId = array_column($partidas, null, 'id');
        $materiales = self::materialesProyecto($pid);
        $nombresMat = array_column($materiales, 'nombre', 'id');
        $matPorId = array_column($materiales, null, 'id');

        // 1) juntar lo de todos los lotes
        $pa = [];   // norm nombre → partida
        $ma = [];   // norm nombre completo → material
        $sub = ['numero' => '', 'especialidad' => '', 'materiales' => []];
        $notas = [];
        $de = $fc = $fe = $re = [];   // dependencias, fechas de contrato, festivos, restricciones (crudos)
        $op = ['contactos' => [], 'responsables' => [], 'cantidades' => [], 'compromisos' => []];
        foreach ($respuestas as $r) {
            if (!is_array($r)) continue;
            foreach ((array)($r['dependencias'] ?? []) as $x) if (is_array($x)) $de[] = $x;
            foreach ((array)($r['fechas_contrato'] ?? []) as $x) if (is_array($x)) $fc[] = $x;
            foreach ((array)($r['festivos'] ?? []) as $x) if (is_array($x)) $fe[] = $x;
            foreach ((array)($r['restricciones'] ?? []) as $x) if (is_array($x)) $re[] = $x;
            foreach ($op as $k => $_) foreach ((array)($r[$k] ?? []) as $x) if (is_array($x)) $op[$k][] = $x;
            foreach ((array)($r['partidas'] ?? []) as $x) {
                if (!is_array($x) || ($n = self::texto($x['nombre'] ?? '', 200)) === '') continue;
                $k = self::norm($n);
                $pa[$k] ??= ['nombre' => $n, 'descripcion' => '', 'coincide' => null, 'actividades' => []];
                if ($pa[$k]['descripcion'] === '') $pa[$k]['descripcion'] = self::texto($x['descripcion'] ?? '', 1000);
                $cid = (int)($x['coincide_con_id'] ?? 0);
                if ($cid && isset($porId[$cid])) $pa[$k]['coincide'] = $cid;
                foreach ((array)($x['actividades'] ?? []) as $a) {
                    if (!is_array($a) || ($an = self::texto($a['nombre'] ?? '', 200)) === '') continue;
                    $d = self::numero($a['duracion_dias'] ?? null);
                    $pa[$k]['actividades'][self::norm($an)] ??= ['nombre' => $an, 'duracion_dias' => $d !== null ? max(1, min(365, (int)round($d))) : null, 'descripcion' => self::texto($a['descripcion'] ?? '', 1000)];
                }
            }
            foreach ((array)($r['materiales'] ?? []) as $x) {
                if (!is_array($x) || ($b = self::texto($x['nombre_base'] ?? ($x['nombre'] ?? ''), 200)) === '') continue;
                $m = ['nombre_base' => $b, 'marca' => self::texto($x['marca'] ?? '', 100), 'modelo' => self::texto($x['modelo'] ?? '', 100), 'valor_extra' => self::texto($x['valor_extra'] ?? '', 150),
                    'cantidad' => self::numero($x['cantidad'] ?? null), 'unidad' => self::texto($x['unidad'] ?? '', 30), 'especialidad' => self::texto($x['especialidad'] ?? '', 100), 'referencia' => self::texto($x['referencia'] ?? '', 100),
                    'coincide' => isset($matPorId[(int)($x['coincide_con_id'] ?? 0)]) ? (int)$x['coincide_con_id'] : null];
                $k = self::norm(Material::componerNombre($m['nombre_base'], $m['marca'], $m['modelo'], $m['valor_extra']));
                if (!isset($ma[$k])) $ma[$k] = $m;
                else foreach (['cantidad', 'unidad', 'especialidad', 'referencia', 'coincide'] as $c) if (($ma[$k][$c] ?? null) === null || $ma[$k][$c] === '') $ma[$k][$c] = $m[$c];
            }
            if (is_array($r['submittal'] ?? null)) {
                $s = $r['submittal'];
                if ($sub['numero'] === '') $sub['numero'] = self::texto($s['numero'] ?? '', 100);
                if ($sub['especialidad'] === '') $sub['especialidad'] = self::texto($s['especialidad'] ?? '', 100);
                foreach ((array)($s['materiales'] ?? []) as $n) if (($n = self::texto($n, 255)) !== '') $sub['materiales'][self::norm($n)] = $n;
            }
            foreach ((array)($r['notas'] ?? []) as $n) if (($n = self::texto($n, 300)) !== '') $notas[] = $n;
        }

        $items = [];
        $nuevoId = function (string $pref) use (&$items) { return $pref . (count($items) + 1); };

        // 2) partidas: coincidencia por nombre con las existentes
        foreach ($pa as $x) {
            [$cid, $sim] = self::mejor($x['nombre'], $nombresPartida);
            if ($x['coincide'] && self::similitud($x['nombre'], $nombresPartida[$x['coincide']]) >= 0.5) { $cid = $x['coincide']; $sim = max($sim, self::similitud($x['nombre'], $nombresPartida[$cid])); }
            $actividades = array_values($x['actividades']);
            if ($cid && $sim >= 0.65) {
                if (!$quiere('partidas_existentes')) {
                    if ($quiere('partidas_nuevas')) {   // ya existe y no se pidió modificar: se avisa y queda sin marcar
                        $items[] = ['id' => $nuevoId('p'), 'tipo' => 'partida_nueva', 'titulo' => $x['nombre'], 'confianza' => 'dudosa', 'marcado' => false,
                            'detalle' => 'Ya existe «' . $porId[$cid]['codigo'] . ' ' . $porId[$cid]['nombre'] . '» (' . round($sim * 100) . '% de parecido); no la agregues salvo que sea otra.',
                            'datos' => ['nombre' => $x['nombre'], 'descripcion' => $x['descripcion'], 'actividades' => $actividades], 'estado' => 'pendiente'];
                    }
                    continue;
                }
                $existentes = array_column(self::filas('SELECT id, nombre, descripcion FROM actividades WHERE partida_id = ' . (int)$cid), null, 'id');
                $nuevas = $dup = [];
                $completar = [];
                foreach ($actividades as $a) {
                    [$aid, $as] = self::mejor($a['nombre'], array_column($existentes, 'nombre', 'id'));
                    if ($aid && $as >= 0.8) {
                        if (trim((string)$existentes[$aid]['descripcion']) === '' && $a['descripcion'] !== '') $completar[] = ['id' => (int)$aid, 'nombre' => $existentes[$aid]['nombre'], 'descripcion' => $a['descripcion']];
                        else $dup[] = $a['nombre'];
                    } else $nuevas[] = $a;
                }
                $desc = ($x['descripcion'] !== '' && trim((string)$porId[$cid]['descripcion']) === '') ? $x['descripcion'] : null;
                if ($desc === null && !$nuevas && !$completar) continue;   // nada que cambiar
                $detalle = [];
                if ($desc !== null) $detalle[] = 'completa la descripción';
                if ($nuevas) $detalle[] = count($nuevas) . ' actividad(es) nueva(s)';
                if ($completar) $detalle[] = 'completa la descripción de ' . count($completar) . ' actividad(es)';
                if ($dup) $detalle[] = count($dup) . ' ya existían';
                $items[] = ['id' => $nuevoId('pa'), 'tipo' => 'partida_actualizar', 'titulo' => $porId[$cid]['codigo'] . ' ' . $porId[$cid]['nombre'], 'confianza' => $sim >= 0.9 ? 'alta' : 'dudosa', 'marcado' => $sim >= 0.9,
                    'detalle' => ucfirst(implode(' · ', $detalle)) . ($sim < 1 ? ' · el documento la llama «' . $x['nombre'] . '» (' . round($sim * 100) . '%)' : ''),
                    'datos' => ['partida_id' => (int)$cid, 'descripcion' => $desc, 'actividades_nuevas' => $nuevas, 'actividades_completar' => $completar], 'estado' => 'pendiente'];
            } elseif ($quiere('partidas_nuevas')) {
                $sinDur = count(array_filter($actividades, fn($a) => $a['duracion_dias'] === null));
                $items[] = ['id' => $nuevoId('p'), 'tipo' => 'partida_nueva', 'titulo' => $x['nombre'], 'confianza' => 'alta', 'marcado' => true,
                    'detalle' => count($actividades) . ' actividad(es)' . ($sinDur ? " · $sinDur sin duración en el documento (se pondrá 1 día)" : '') . ' · las fechas se calculan a continuación del programa actual',
                    'datos' => ['nombre' => $x['nombre'], 'descripcion' => $x['descripcion'], 'actividades' => $actividades], 'estado' => 'pendiente'];
            }
        }

        // 3) materiales: nuevos, o datos que le faltan a uno existente
        $vistos = [];
        foreach ($ma as $m) {
            $completo = Material::componerNombre($m['nombre_base'], $m['marca'], $m['modelo'], $m['valor_extra']);
            [$cid, $sim] = self::mejor($completo, $nombresMat);
            if ($m['coincide'] && self::similitud($completo, $nombresMat[$m['coincide']]) >= 0.5) { $cid = $m['coincide']; $sim = max($sim, self::similitud($completo, $nombresMat[$cid])); }
            $vistos[self::norm($completo)] = $cid && $sim >= 0.85 ? (int)$cid : null;
            if (!$quiere('materiales')) continue;
            if ($cid && $sim >= 0.85) {
                $e = $matPorId[$cid];
                $cambios = [];
                foreach (['marca', 'modelo', 'valor_extra', 'unidad', 'especialidad', 'referencia'] as $c) if ($m[$c] !== '' && trim((string)$e[$c]) === '') $cambios[$c] = $m[$c];
                if ($m['cantidad'] !== null && ($e['cantidad'] === null || (float)$e['cantidad'] == 0.0)) $cambios['cantidad'] = $m['cantidad'];
                if (!$cambios) continue;   // ya está completo
                $items[] = ['id' => $nuevoId('ma'), 'tipo' => 'material_actualizar', 'titulo' => $e['nombre'], 'confianza' => $sim >= 0.9 ? 'alta' : 'dudosa', 'marcado' => $sim >= 0.9,
                    'detalle' => 'Completa: ' . implode(', ', array_map(fn($k, $v) => "$k = $v", array_keys($cambios), $cambios)) . ($sim < 1 ? ' · coincide al ' . round($sim * 100) . '%' : ''),
                    'datos' => ['material_id' => (int)$cid, 'cambios' => $cambios], 'estado' => 'pendiente'];
            } else {
                $datos = array_diff_key($m, ['coincide' => 1]);
                $faltan = [];
                if ($m['cantidad'] === null) $faltan[] = 'cantidad';
                if ($m['unidad'] === '') $faltan[] = 'unidad';
                $items[] = ['id' => $nuevoId('m'), 'tipo' => 'material_nuevo', 'titulo' => $completo, 'confianza' => 'alta', 'marcado' => true,
                    'detalle' => ($m['cantidad'] !== null ? $m['cantidad'] . ' ' . $m['unidad'] : 'sin cantidad') . ($m['especialidad'] !== '' ? ' · ' . $m['especialidad'] : '') . ($faltan ? ' · falta: ' . implode(', ', $faltan) : ''),
                    'datos' => $datos, 'estado' => 'pendiente'];
            }
        }

        // 4) submittal
        $subs = array_column(self::submittals($pid), null, 'id');
        $quiereSub = $quiere('submittal_nuevo') || $quiere('submittal_existente');
        if ($quiereSub && ($sub['materiales'] || $sub['numero'] !== '')) {
            $existente = $quiere('submittal_existente') && $submittalId && isset($subs[$submittalId]);
            if (!$existente && !$quiere('submittal_nuevo')) {
                $notas[] = 'Pediste agregar a un submittal existente pero no elegiste cuál: elige uno en la lista y vuelve a enviar.';
            } else {
                $lista = array_values($sub['materiales']);
                if (!$lista) $lista = array_map(fn($m) => Material::componerNombre($m['nombre_base'], $m['marca'], $m['modelo'], $m['valor_extra']), array_values($ma));   // sin lista propia: los materiales del documento
                $nuevos = 0;
                foreach ($lista as $n) { [$cid, $s] = self::mejor($n, $nombresMat); if (!$cid || $s < 0.85) $nuevos++; }
                $items[] = ['id' => $nuevoId('s'), 'tipo' => 'submittal', 'titulo' => $existente ? 'Agregar al submittal ' . $subs[$submittalId]['numero'] : 'Submittal nuevo' . ($sub['numero'] !== '' ? ' ' . $sub['numero'] : ''),
                    'confianza' => 'alta', 'marcado' => true,
                    'detalle' => count($lista) . ' material(es)' . ($nuevos ? " · $nuevos no existen todavía y se crearán (sin cantidad)" : ''),
                    'datos' => ['destino' => $existente ? 'existente' : 'nuevo', 'submittal_id' => $existente ? $submittalId : null, 'numero' => $sub['numero'], 'especialidad' => $sub['especialidad'], 'materiales' => $lista], 'estado' => 'pendiente'];
            }
        }
        self::proponerContrato($p, $de, $fc, $fe, $re, $quiere, $items, $notas);
        self::proponerOperativo($p, $op, $sesion, $modos, $todos, $items, $notas);
        return ['items' => $items, 'notas' => array_values(array_unique($notas))];
    }

    /** Fecha Y-m-d válida y razonable (2000–2100) o null */
    private static function fecha($v): ?string
    {
        $v = self::texto($v, 20);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || $m[1] < 2000 || $m[1] > 2100) return null;
        return $v;
    }

    private static function fechaLarga(string $f): string
    {
        return date('d-m-Y', strtotime($f));
    }

    /** Actividad por id de la IA (si es del proyecto) o por parecido de nombre ≥ 0.65. @return array{0: ?int, 1: float} */
    private static function resolverActividad($id, string $nombre, array $porId, array $nombres): array
    {
        $id = (int)$id;
        if ($id && isset($porId[$id])) return [$id, $nombre === '' ? 1.0 : max(0.7, self::similitud($nombre, $nombres[$id]))];
        if ($nombre === '') return [null, 0.0];
        [$cid, $sim] = self::mejor($nombre, $nombres);
        return $cid && $sim >= 0.65 ? [(int)$cid, $sim] : [null, $sim];
    }

    /** Teléfono con solo caracteres de teléfono, o '' */
    private static function telefono($v): string
    {
        $t = self::texto($v, 40);
        return preg_match('/^[0-9+()\-. ext#]{5,40}$/i', $t) ? $t : '';
    }

    private static function correo($v): string
    {
        $t = strtolower(self::texto($v, 150));
        return filter_var($t, FILTER_VALIDATE_EMAIL) ? $t : '';
    }

    /** Contactos, responsables, cantidades de obra, compromisos de minuta y documentos para Normativa → ítems */
    private static function proponerOperativo(Proyecto $p, array $op, int $sesion, array $modos, bool $todos, array &$items, array &$notas): void
    {
        $pid = (int)$p->id;
        $quiere = fn(string $m) => $m !== 'normativa' && ($todos || in_array($m, $modos, true));
        $nuevoId = fn(string $pref) => $pref . (count($items) + 1);
        $acts = array_column(self::actividadesProyecto($pid), null, 'id');
        $nombresAct = array_column($acts, 'nombre', 'id');
        $et = fn(int $id) => $acts[$id]['codigo'] . ' ' . $acts[$id]['nombre'];

        // ---- contactos (y responsables, que pueden nombrar a alguien que aún no está en el directorio)
        if ($quiere('responsables')) {
            $personal = self::filas("SELECT p.id, p.nombre, p.departamento, COALESCE(c.cargo,'') cargo, COALESCE(c.empresa,'') empresa, COALESCE(c.telefono,'') telefono, COALESCE(c.correo,'') correo FROM personal p LEFT JOIN personal_contacto c ON c.personal_id = p.id WHERE p.proyecto_id = $pid");
            $nomPers = array_column($personal, 'nombre', 'id');
            $porId = array_column($personal, null, 'id');
            $vistos = [];
            $contactos = $op['contactos'];
            foreach ($op['responsables'] as $x) if (($n = self::texto($x['persona'] ?? '', 150)) !== '') $contactos[] = ['nombre' => $n];   // alguien nombrado solo como responsable
            foreach ($contactos as $x) {
                $nombre = self::texto($x['nombre'] ?? '', 150);
                if (mb_strlen($nombre) < 3 || isset($vistos[self::norm($nombre)])) {
                    if (isset($vistos[self::norm($nombre)])) {   // la misma persona en otra línea: se completan los datos que falten
                        $k = $vistos[self::norm($nombre)];
                        foreach (['cargo' => 100, 'empresa' => 150, 'departamento' => 100] as $c => $m) if ($items[$k]['datos'][$c] === '') $items[$k]['datos'][$c] = self::texto($x[$c] ?? '', $m);
                        if ($items[$k]['datos']['telefono'] === '') $items[$k]['datos']['telefono'] = self::telefono($x['telefono'] ?? '');
                        if ($items[$k]['datos']['correo'] === '') $items[$k]['datos']['correo'] = self::correo($x['correo'] ?? '');
                    }
                    continue;
                }
                [$pidx, $sim] = self::mejor($nombre, $nomPers);
                $d = ['nombre' => $nombre, 'cargo' => self::texto($x['cargo'] ?? '', 100), 'empresa' => self::texto($x['empresa'] ?? '', 150), 'departamento' => self::texto($x['departamento'] ?? '', 100),
                    'telefono' => self::telefono($x['telefono'] ?? ''), 'correo' => self::correo($x['correo'] ?? ''), 'personal_id' => null];
                if ($pidx && $sim >= 0.9) { $d['personal_id'] = (int)$pidx; }
                $vistos[self::norm($nombre)] = count($items);
                $items[] = ['id' => $nuevoId('t'), 'tipo' => 'contacto', 'titulo' => $nombre, 'confianza' => 'alta', 'marcado' => true, 'detalle' => '', 'datos' => $d, 'estado' => 'pendiente'];
            }
            // el detalle y lo que ya está completo se calculan al final, con los datos ya juntos
            foreach ($items as $k => $it) {
                if ($it['tipo'] !== 'contacto' || $it['detalle'] !== '') continue;
                $d = $it['datos'];
                if ($d['personal_id']) {
                    $e = $porId[$d['personal_id']];
                    $cambios = [];
                    foreach (['cargo', 'empresa', 'telefono', 'correo'] as $c) if ($d[$c] !== '' && trim((string)$e[$c]) === '') $cambios[$c] = $d[$c];
                    if ($d['departamento'] !== '' && trim((string)$e['departamento']) === '') $cambios['departamento'] = $d['departamento'];
                    if (!$cambios) { unset($items[$k]); continue; }   // ya está completo
                    $items[$k]['datos']['cambios'] = $cambios;
                    $items[$k]['titulo'] = $e['nombre'];
                    $items[$k]['detalle'] = 'Ya está en el personal; completa: ' . implode(', ', array_map(fn($c, $v) => "$c = $v", array_keys($cambios), $cambios));
                } else {
                    $datos = array_filter([$d['departamento'], $d['cargo'], $d['empresa'], $d['telefono'], $d['correo']]);
                    $items[$k]['detalle'] = 'Persona nueva en el personal del proyecto' . ($datos ? ' · ' . implode(' · ', $datos) : ' · solo el nombre');
                }
            }
            $items = array_values($items);

            // responsables de actividad
            $vistas = [];
            foreach ($op['responsables'] as $x) {
                $persona = self::texto($x['persona'] ?? '', 150);
                [$aid, $sim] = self::resolverActividad($x['actividad_id'] ?? 0, self::texto($x['actividad'] ?? '', 200), $acts, $nombresAct);
                if ($persona === '' || !$aid) { if ($persona !== '') $notas[] = 'Responsable sin actividad en el programa: «' . $persona . '» (' . self::texto($x['actividad'] ?? '?', 80) . ').'; continue; }
                if (isset($vistas[$aid])) continue;
                $vistas[$aid] = true;
                $previo = self::filas("SELECT p.nombre FROM actividad_responsables r JOIN personal p ON p.id = r.personal_id WHERE r.actividad_id = $aid")[0]['nombre'] ?? null;
                if ($previo !== null && self::similitud($previo, $persona) >= 0.9) continue;
                $conf = $sim >= 0.9 ? 'alta' : 'dudosa';
                $items[] = ['id' => $nuevoId('rs'), 'tipo' => 'responsable', 'titulo' => $persona . ' → ' . $et($aid), 'confianza' => $conf, 'marcado' => $conf === 'alta',
                    'detalle' => 'Responsable de la actividad' . ($previo ? " · reemplaza a $previo" : '') . (($c = self::texto($x['cita'] ?? '', 300)) !== '' ? ' · «' . $c . '»' : ''),
                    'datos' => ['actividad_id' => $aid, 'persona' => $persona, 'previo' => $previo], 'estado' => 'pendiente'];
            }
        }

        // ---- cantidades de obra
        if ($quiere('cantidades')) {
            $vistas = [];
            foreach ($op['cantidades'] as $x) {
                $concepto = self::texto($x['concepto'] ?? '', 255);
                $cant = self::numero($x['cantidad'] ?? null);
                [$aid, $sim] = self::resolverActividad($x['actividad_id'] ?? 0, self::texto($x['actividad'] ?? '', 200), $acts, $nombresAct);
                if ($aid === null && $concepto !== '') [$aid, $sim] = self::resolverActividad(0, $concepto, $acts, $nombresAct);
                if ($concepto === '' && ($x['clave'] ?? '') === '') continue;
                if ($cant === null) { $notas[] = 'Concepto sin cantidad numérica: «' . mb_substr($concepto, 0, 80) . '».'; continue; }
                if (!$aid) { $notas[] = 'Concepto sin actividad a la que ligarlo: «' . mb_substr($concepto, 0, 80) . '» (' . $cant . ' ' . self::texto($x['unidad'] ?? '', 20) . ').'; continue; }
                if (isset($vistas[$aid])) { $notas[] = 'Dos conceptos para «' . $acts[$aid]['nombre'] . '»: solo se propone el primero.'; continue; }
                $vistas[$aid] = true;
                $unidad = self::texto($x['unidad'] ?? '', 30);
                $importe = self::numero($x['importe'] ?? null);
                $previa = self::filas("SELECT clave, concepto, cantidad, unidad, importe FROM actividad_cantidades WHERE actividad_id = $aid")[0] ?? null;
                if ($previa && (float)$previa['cantidad'] === (float)$cant && $previa['unidad'] === $unidad && ($importe === null || (float)$previa['importe'] === (float)$importe)) continue;
                $conf = $sim >= 0.9 ? 'alta' : 'dudosa';
                $items[] = ['id' => $nuevoId('q'), 'tipo' => 'cantidad_obra', 'titulo' => trim(self::texto($x['clave'] ?? '', 60) . ' ' . $concepto) . ' → ' . $et($aid), 'confianza' => $conf, 'marcado' => $conf === 'alta',
                    'detalle' => $cant . ' ' . $unidad . ($importe !== null ? ' · importe ' . number_format($importe, 2) : '') . ($previa ? ' · reemplaza ' . (float)$previa['cantidad'] . ' ' . $previa['unidad'] : '') . (($c = self::texto($x['cita'] ?? '', 300)) !== '' ? ' · «' . $c . '»' : ''),
                    'datos' => ['actividad_id' => $aid, 'clave' => self::texto($x['clave'] ?? '', 60), 'concepto' => $concepto, 'cantidad' => $cant, 'unidad' => $unidad, 'importe' => $importe, 'origen' => self::texto($x['cita'] ?? '', 255)], 'estado' => 'pendiente'];
            }
        }

        // ---- compromisos de minuta → bitácora
        if ($quiere('minuta')) {
            $existentes = self::filas("SELECT descripcion FROM bitacora WHERE proyecto_id = $pid ORDER BY id DESC LIMIT 300");
            foreach ($op['compromisos'] as $x) {
                $tema = self::texto($x['tema'] ?? '', 500);
                if ($tema === '') continue;
                $resp = self::texto($x['responsable'] ?? '', 150);
                $limite = self::fecha($x['fecha_limite'] ?? '');
                $fecha = self::fecha($x['fecha_reunion'] ?? '') ?: date('Y-m-d');
                $desc = 'Compromiso de minuta: ' . $tema . ($resp !== '' ? '. Responsable: ' . $resp : '') . ($limite ? '. Fecha límite: ' . self::fechaLarga($limite) : '') . '.';
                foreach ($existentes as $e) if (self::similitud($desc, (string)$e['descripcion']) >= 0.85) continue 2;
                $imp = in_array($x['importancia'] ?? '', ['baja', 'media', 'alta', 'critica'], true) ? $x['importancia'] : 'media';
                $items[] = ['id' => $nuevoId('cm'), 'tipo' => 'compromiso', 'titulo' => mb_substr($tema, 0, 110), 'confianza' => 'alta', 'marcado' => true,
                    'detalle' => 'Entrada de bitácora del ' . self::fechaLarga($fecha) . ' · importancia ' . $imp . ($resp !== '' ? " · $resp" : '') . ($limite ? ' · límite ' . self::fechaLarga($limite) : ''),
                    'datos' => ['fecha' => $fecha, 'departamento' => self::texto($x['departamento'] ?? '', 100), 'descripcion' => $desc, 'importancia' => $imp], 'estado' => 'pendiente'];
            }
        }

        // ---- documentos de la sesión → Normativa (solo PDF y Word que quedaron en el Archivero)
        if (in_array('normativa', $modos, true) && $sesion) {
            $docs = self::filas("SELECT titulo, codigo FROM normativa_documentos WHERE proyecto_id = $pid");
            foreach (self::filas("SELECT ea.id, ea.nombre, ea.archivo_id, ea.paginas FROM ensamblador_archivos ea WHERE ea.sesion_id = $sesion AND ea.proyecto_id = $pid AND ea.archivo_id IS NOT NULL ORDER BY ea.id") as $f) {
                $ext = strtolower(pathinfo($f['nombre'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf', 'docx', 'doc'], true)) { $notas[] = 'Normativa solo admite PDF y Word: «' . $f['nombre'] . '» no se guarda.'; continue; }
                $base = pathinfo($f['nombre'], PATHINFO_FILENAME);
                $dup = null;
                foreach ($docs as $d) if (self::similitud($base, $d['titulo']) >= 0.95) $dup = $d['titulo'];
                $items[] = ['id' => $nuevoId('n'), 'tipo' => 'normativa_documento', 'titulo' => $f['nombre'], 'confianza' => $dup ? 'dudosa' : 'alta', 'marcado' => !$dup,
                    'detalle' => 'Se indexa en Normativa (título, código y tipo se detectan del contenido) y se proponen vínculos a actividades y materiales para que los aceptes allí' . ($dup ? ' · ⚠ ya hay un documento «' . $dup . '»' : ''),
                    'datos' => ['archivo_id' => (int)$f['archivo_id'], 'nombre' => $f['nombre']], 'estado' => 'pendiente'];
            }
        }
    }

    /** Dependencias, fechas contractuales, festivos y restricciones → ítems de la propuesta */
    private static function proponerContrato(Proyecto $p, array $de, array $fc, array $fe, array $re, callable $quiere, array &$items, array &$notas): void
    {
        $pid = (int)$p->id;
        $acts = array_column(self::actividadesProyecto($pid), null, 'id');
        $nombres = array_column($acts, 'nombre', 'id');
        $et = fn(int $id) => $acts[$id]['codigo'] . ' ' . $acts[$id]['nombre'];
        $nuevoId = fn(string $pref) => $pref . (count($items) + 1);

        if ($quiere('dependencias')) {
            $rels = Dependencias::relaciones($pid);
            $ya = [];
            foreach ($rels as $r) $ya[$r['pred'] . '>' . $r['suc']] = $r;
            $vistas = [];
            foreach ($de as $x) {
                $pn = self::texto($x['predecesora'] ?? '', 200);
                $sn = self::texto($x['sucesora'] ?? '', 200);
                [$pa, $ps] = self::resolverActividad($x['predecesora_id'] ?? 0, $pn, $acts, $nombres);
                [$su, $ss] = self::resolverActividad($x['sucesora_id'] ?? 0, $sn, $acts, $nombres);
                if (!$pa || !$su) { $notas[] = 'Dependencia sin resolver (no se encontró la actividad en el programa): «' . ($pn ?: '?') . '» → «' . ($sn ?: '?') . '».'; continue; }
                if ($pa === $su) continue;
                $tipo = strtoupper(self::texto($x['tipo'] ?? 'FS', 2));
                if (!isset(Dependencias::TIPOS[$tipo])) $tipo = 'FS';
                $retraso = max(-365, min(365, (int)round((float)($x['retraso_dias'] ?? 0))));
                $k = "$pa>$su";
                if (isset($vistas[$k])) continue;
                $vistas[$k] = true;
                $previa = $ya[$k] ?? null;
                if ($previa && $previa['tipo'] === $tipo && $previa['retraso'] === $retraso) continue;   // ya existe igual
                $cita = self::texto($x['cita'] ?? '', 300);
                $conf = min($ps, $ss) >= 0.9 ? 'alta' : 'dudosa';
                $items[] = ['id' => $nuevoId('d'), 'tipo' => 'dependencia', 'titulo' => $et($pa) . ' → ' . $et($su), 'confianza' => $conf, 'marcado' => $conf === 'alta',
                    'detalle' => $tipo . ' (' . Dependencias::TIPOS[$tipo] . ')' . ($retraso ? ($retraso > 0 ? " · retraso $retraso d" : ' · adelanto ' . abs($retraso) . ' d') : '') . ($previa ? ' · reemplaza ' . $previa['tipo'] . ($previa['retraso'] ? " {$previa['retraso']} d" : '') : '') . ($cita !== '' ? ' · «' . $cita . '»' : ''),
                    'datos' => ['pred' => $pa, 'suc' => $su, 'tipo' => $tipo, 'retraso' => $retraso, 'cita' => $cita, 'previa' => $previa ? ['tipo' => $previa['tipo'], 'retraso' => $previa['retraso']] : null], 'estado' => 'pendiente'];
            }
        }

        if (!$quiere('contrato')) return;

        // festivos / días no hábiles
        $existentes = array_column(self::filas("SELECT fecha FROM calendario_festivos WHERE proyecto_id = $pid"), 'fecha');
        $vistos = [];
        foreach ($fe as $x) {
            $f = self::fecha($x['fecha'] ?? '');
            if (!$f) { $notas[] = 'Festivo con fecha no válida o ambigua: «' . self::texto($x['fecha'] ?? '', 40) . '».'; continue; }
            if (isset($vistos[$f])) continue;
            $vistos[$f] = true;
            if (in_array($f, $existentes, true)) continue;
            $motivo = self::texto($x['motivo'] ?? '', 200) ?: 'Día no hábil (contrato)';
            $items[] = ['id' => $nuevoId('f'), 'tipo' => 'festivo', 'titulo' => self::fechaLarga($f) . ' · ' . $motivo, 'confianza' => 'alta', 'marcado' => true,
                'detalle' => 'Día no hábil: las actividades que lo crucen se recalculan al reprogramar' . (($c = self::texto($x['cita'] ?? '', 300)) !== '' ? ' · «' . $c . '»' : ''),
                'datos' => ['fecha' => $f, 'motivo' => $motivo], 'estado' => 'pendiente'];
        }

        // fechas contractuales, comparadas con el programa
        $rango = self::filas('SELECT MIN(a.fecha_inicio) ini, MAX(a.fecha_fin) fin FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = ' . $pid)[0] ?? [];
        $previas = self::filas("SELECT tipo, nombre, fecha FROM proyecto_fechas_contrato WHERE proyecto_id = $pid");
        $vistos = [];
        foreach ($fc as $x) {
            $f = self::fecha($x['fecha'] ?? '');
            $tipo = in_array($x['tipo'] ?? '', ['inicio', 'fin', 'hito'], true) ? $x['tipo'] : 'hito';
            $nombre = self::texto($x['nombre'] ?? '', 200) ?: ['inicio' => 'Inicio contractual', 'fin' => 'Término contractual', 'hito' => 'Hito contractual'][$tipo];
            if (!$f) { $notas[] = 'Fecha contractual sin fecha válida: «' . $nombre . '».'; continue; }
            $k = $tipo . $f . self::norm($nombre);
            if (isset($vistos[$k])) continue;
            $vistos[$k] = true;
            foreach ($previas as $v) if ($v['tipo'] === $tipo && $v['fecha'] === $f && self::norm($v['nombre']) === self::norm($nombre)) continue 2;
            $cmp = '';
            $prog = $tipo === 'inicio' ? ($rango['ini'] ?? null) : ($tipo === 'fin' ? ($rango['fin'] ?? null) : null);
            if ($tipo === 'hito') {
                [$aid] = self::resolverActividad(0, $nombre, $acts, $nombres);
                if ($aid) { $prog = $acts[$aid]['fecha_fin']; $cmp = ' (actividad «' . $acts[$aid]['nombre'] . '»)'; }
            }
            if ($prog) {
                $dias = (int)round((strtotime($prog) - strtotime($f)) / 86400);
                $cmp = ' · el programa dice ' . self::fechaLarga($prog) . $cmp . ($dias === 0 ? ': coincide' : ($dias > 0 ? ": $dias día(s) DESPUÉS del contrato" : ': ' . abs($dias) . ' día(s) antes del contrato'));
            }
            $pen = self::texto($x['penalizacion'] ?? '', 200);
            $items[] = ['id' => $nuevoId('c'), 'tipo' => 'fecha_contrato', 'titulo' => $nombre . ' · ' . self::fechaLarga($f), 'confianza' => 'alta', 'marcado' => true,
                'detalle' => ucfirst($tipo) . ($pen !== '' ? ' · penalización: ' . $pen : '') . $cmp . (($c = self::texto($x['cita'] ?? '', 300)) !== '' ? ' · «' . $c . '»' : ''),
                'datos' => ['tipo' => $tipo, 'nombre' => $nombre, 'fecha' => $f, 'penalizacion' => $pen, 'origen' => self::texto($x['cita'] ?? '', 255)], 'estado' => 'pendiente'];
        }

        // restricciones (cada una cuelga de una actividad)
        $tipos = array_column(self::filas('SELECT id, nombre FROM tipos_restriccion'), 'nombre', 'id');
        $activas = self::filas('SELECT r.actividad_id, r.descripcion FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = ' . $pid . " AND r.estado = 'activa'");
        foreach ($re as $x) {
            $desc = self::texto($x['descripcion'] ?? '', 1000);
            if ($desc === '') continue;
            [$aid, $sim] = self::resolverActividad($x['actividad_id'] ?? 0, self::texto($x['actividad'] ?? '', 200), $acts, $nombres);
            if (!$aid) { $notas[] = 'Restricción sin actividad a la que ligarla (ligar a mano en la actividad): «' . mb_substr($desc, 0, 120) . '».'; continue; }
            foreach ($activas as $v) if ((int)$v['actividad_id'] === $aid && self::similitud($desc, (string)$v['descripcion']) >= 0.8) continue 2;
            $tn = self::texto($x['tipo'] ?? '', 50);
            [$tid, $ts] = self::mejor($tn, $tipos);
            if (!$tid || $ts < 0.8) $tid = array_search('Contrato', $tipos, true) ?: array_search('Otro', $tipos, true);
            if (!$tid) { $notas[] = 'No hay tipos de restricción configurados: «' . mb_substr($desc, 0, 80) . '» no se propone.'; continue; }
            $f = self::fecha($x['fecha'] ?? '');
            $conf = $sim >= 0.9 ? 'alta' : 'dudosa';
            $items[] = ['id' => $nuevoId('r'), 'tipo' => 'restriccion', 'titulo' => mb_substr($desc, 0, 110), 'confianza' => $conf, 'marcado' => $conf === 'alta',
                'detalle' => 'Restricción ' . ($tipos[$tid] ?? '') . ' en «' . $et($aid) . '»' . ($f ? ' · fecha límite ' . self::fechaLarga($f) : '') . (($c = self::texto($x['cita'] ?? '', 300)) !== '' ? ' · «' . $c . '»' : ''),
                'datos' => ['actividad_id' => $aid, 'tipo_id' => (int)$tid, 'descripcion' => $desc, 'fecha' => $f], 'estado' => 'pendiente'];
        }
    }

    // ------------------------------------------------------------------ propuestas guardadas

    public static function guardarPropuesta(int $pid, int $sesion, string $instruccion, array $modos, string $modelo, array $items): int
    {
        self::db()->query(sprintf("INSERT INTO ensamblador_propuestas (sesion_id, proyecto_id, instruccion, modos, modelo, items_json) VALUES (%d, %d, '%s', '%s', '%s', '%s')",
            $sesion, $pid, self::esc(mb_substr($instruccion, 0, 1000)), self::esc(implode(',', $modos)), self::esc(mb_substr($modelo, 0, 150)), self::esc(json_encode($items, JSON_UNESCAPED_UNICODE))));
        return (int)self::db()->insert_id;
    }

    public static function propuesta(int $pid, int $id): ?array
    {
        $f = self::filas("SELECT * FROM ensamblador_propuestas WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
        if (!$f) return null;
        $f['items'] = json_decode((string)$f['items_json'], true) ?: [];
        unset($f['items_json']);
        return $f;
    }

    private static function guardarItems(int $id, array $items, ?string $estado = null): void
    {
        self::db()->query('UPDATE ensamblador_propuestas SET items_json = \'' . self::esc(json_encode($items, JSON_UNESCAPED_UNICODE)) . '\'' . ($estado ? ", estado = '" . self::esc($estado) . "'" : '') . " WHERE id = $id");
    }

    public static function historial(int $pid, int $limite = 15): array
    {
        $out = [];
        foreach (self::filas("SELECT id, instruccion, modos, estado, creado, items_json FROM ensamblador_propuestas WHERE proyecto_id = $pid ORDER BY id DESC LIMIT " . (int)$limite) as $f) {
            $it = json_decode((string)$f['items_json'], true) ?: [];
            $out[] = ['id' => (int)$f['id'], 'instruccion' => $f['instruccion'], 'estado' => $f['estado'], 'creado' => $f['creado'], 'items' => count($it), 'aplicados' => count(array_filter($it, fn($i) => ($i['estado'] ?? '') === 'aplicado'))];
        }
        return $out;
    }

    public static function rechazar(Proyecto $p, int $id): bool
    {
        $pr = self::propuesta((int)$p->id, $id);
        if (!$pr || $pr['estado'] !== 'pendiente') return false;
        self::db()->query("UPDATE ensamblador_propuestas SET estado = 'rechazada' WHERE id = $id");
        Auditoria::registrar((int)$p->id, 'ensamblador', 'rechazado', 'ens_propuesta', 'Propuesta del Ensamblador rechazada (' . count($pr['items']) . ' cambios)', null, null, $id, $pr['modelo'], ['proyecto', (int)$p->id, 'Ensamblador']);
        return true;
    }

    // ------------------------------------------------------------------ aplicar

    private static function proximaFecha(Proyecto $p, Calendario $cal): string
    {
        $r = self::filas('SELECT MAX(a.fecha_fin) f FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = ' . (int)$p->id)[0]['f'] ?? null;
        $base = $r ? date('Y-m-d', strtotime($r . ' +1 day')) : ($p->fecha_inicio ?: date('Y-m-d'));
        return $base;
    }

    /** Crea las actividades [..] a continuación de $desde; devuelve los ids */
    private static function crearActividades(Partida $pa, array $acts, string $desde, Calendario $cal, string $prefijo, int $siguiente, int $orden): array
    {
        $ids = [];
        foreach ($acts as $a) {
            $dur = (int)($a['duracion_dias'] ?? 0) ?: 1;
            $fin = $cal->calcularFechaFin($desde, $dur);
            $act = new Actividad(['partida_id' => $pa->id, 'codigo' => $prefijo . $siguiente, 'nombre' => $a['nombre'], 'descripcion' => $a['descripcion'] ?? '', 'fecha_inicio' => $desde, 'duracion_dias' => $dur, 'fecha_fin' => $fin, 'progreso' => 0, 'prioridad' => 'media', 'orden' => $orden]);
            $act->sincronizarStatus();
            $r = $act->guardar();
            if (!is_array($r) || empty($r['id'])) throw new \RuntimeException('No se pudo crear la actividad «' . $a['nombre'] . '»');
            $ids[] = (int)$r['id'];
            $desde = date('Y-m-d', strtotime($fin . ' +1 day'));
            $siguiente++;
            $orden++;
        }
        return $ids;
    }

    /** Aplica un ítem y devuelve lo necesario para revertirlo. Lanza si falla */
    private static function aplicarItem(Proyecto $p, array $it, array &$materialesCreados): array
    {
        $pid = (int)$p->id;
        $d = $it['datos'];
        $cal = new Calendario($pid);
        switch ($it['tipo']) {
            case 'partida_nueva': {
                $codigo = Renumeracion::siguienteCodigoPartida($pid);
                $orden = (int)(self::filas("SELECT COALESCE(MAX(orden),0) m FROM partidas WHERE proyecto_id = $pid")[0]['m'] ?? 0) + 1;
                $pa = new Partida(['proyecto_id' => $pid, 'codigo' => $codigo, 'nombre' => $d['nombre'], 'descripcion' => $d['descripcion'] ?? '', 'orden' => $orden]);
                $res = $pa->guardar();
                if (!is_array($res) || empty($res['id'])) throw new \RuntimeException('No se pudo crear la partida');
                $pa->id = (int)$res['id'];
                $ids = self::crearActividades($pa, $d['actividades'] ?? [], self::proximaFecha($p, $cal), $cal, preg_replace('/\.0$/', '', $codigo) . '.', 1, 1);
                $pa->recalcularFechas();
                return ['partida_id' => (int)$pa->id, 'actividades' => $ids, 'codigo' => $codigo];
            }
            case 'partida_actualizar': {
                $pa = Partida::find((int)$d['partida_id']);
                if (!$pa || (int)$pa->proyecto_id !== $pid) throw new \RuntimeException('La partida ya no existe');
                $antes = ['partida_id' => (int)$pa->id, 'descripcion_antes' => null, 'actividades' => [], 'completadas' => []];
                if (!empty($d['descripcion'])) {
                    $antes['descripcion_antes'] = (string)$pa->descripcion;
                    $antes['descripcion_despues'] = (string)$d['descripcion'];
                    $pa->descripcion = $d['descripcion'];
                    $pa->guardar();
                }
                foreach ($d['actividades_completar'] ?? [] as $c) {
                    $a = Actividad::find((int)$c['id']);
                    if (!$a || (int)$a->partida_id !== (int)$pa->id || trim((string)$a->descripcion) !== '') continue;
                    $a->descripcion = $c['descripcion'];
                    $a->guardar();
                    $antes['completadas'][] = ['id' => (int)$a->id, 'descripcion' => $c['descripcion']];
                }
                if (!empty($d['actividades_nuevas'])) {
                    $fila = self::filas('SELECT codigo, orden, fecha_fin FROM actividades WHERE partida_id = ' . (int)$pa->id);
                    $max = 0;
                    $ordMax = 0;
                    $fin = null;
                    foreach ($fila as $f) { if (preg_match('/(\d+)$/', (string)$f['codigo'], $m)) $max = max($max, (int)$m[1]); $ordMax = max($ordMax, (int)$f['orden']); $fin = max($fin ?? '', $f['fecha_fin']); }
                    $desde = $fin ? date('Y-m-d', strtotime($fin . ' +1 day')) : self::proximaFecha($p, $cal);
                    $prefijo = preg_match('/^(\d+)/', (string)$pa->codigo, $m) ? $m[1] . '.' : $pa->codigo . '.';
                    $antes['actividades'] = self::crearActividades($pa, $d['actividades_nuevas'], $desde, $cal, $prefijo, $max + 1, $ordMax + 1);
                    $pa->recalcularFechas();
                }
                return $antes;
            }
            case 'material_nuevo': {
                $m = new Material($d + ['proyecto_id' => $pid]);
                $m->proyecto_id = $pid;
                $m->nombre_base = $d['nombre_base'];
                $m->especialidad = $d['especialidad'] !== '' ? \Model\Especialidad::resolver($pid, $d['especialidad']) : '';
                $m->armarNombre();
                $res = $m->guardar();
                if (!is_array($res) || empty($res['id'])) throw new \RuntimeException('No se pudo crear el material');
                $materialesCreados[self::norm($m->nombre)] = (int)$res['id'];
                return ['material_id' => (int)$res['id'], 'nombre' => $m->nombre];
            }
            case 'material_actualizar': {
                $m = Material::find((int)$d['material_id']);
                if (!$m || (int)$m->proyecto_id !== $pid) throw new \RuntimeException('El material ya no existe');
                $antes = ['material_id' => (int)$m->id, 'nombre_antes' => $m->nombre, 'campos' => []];
                foreach ($d['cambios'] as $c => $v) {
                    if (!in_array($c, ['marca', 'modelo', 'valor_extra', 'unidad', 'especialidad', 'referencia', 'cantidad'], true)) continue;
                    if ($c === 'cantidad' ? ($m->cantidad !== null && (float)$m->cantidad != 0.0) : trim((string)$m->$c) !== '') continue;   // alguien lo llenó mientras tanto
                    $antes['campos'][$c] = ['antes' => $m->$c, 'despues' => $v];
                    $m->$c = $c === 'especialidad' ? \Model\Especialidad::resolver($pid, (string)$v) : $v;
                }
                if (!$antes['campos']) throw new \RuntimeException('El material ya tenía esos datos');
                $m->armarNombre();
                $m->guardar();
                $antes['nombre_despues'] = $m->nombre;
                return $antes;
            }
            case 'contacto': {
                $creado = false;
                $perId = (int)($d['personal_id'] ?? 0);
                if ($perId) {
                    $per = \Model\Personal::find($perId);
                    if (!$per || (int)$per->proyecto_id !== $pid) throw new \RuntimeException('La persona ya no está en el personal');
                } else {
                    [$cid, $s] = self::mejor($d['nombre'], array_column(self::filas("SELECT id, nombre FROM personal WHERE proyecto_id = $pid"), 'nombre', 'id'));
                    if ($cid && $s >= 0.9) { $perId = (int)$cid; $per = \Model\Personal::find($perId); }   // otro ítem de esta tanda ya la creó
                    else {
                        $per = new \Model\Personal(['proyecto_id' => $pid, 'nombre' => $d['nombre'], 'departamento' => $d['departamento']]);
                        $res = $per->guardar();
                        if (!is_array($res) || empty($res['id'])) throw new \RuntimeException('No se pudo agregar a la persona');
                        $perId = (int)$res['id'];
                        $creado = true;
                    }
                }
                $previa = self::filas("SELECT cargo, empresa, telefono, correo FROM personal_contacto WHERE personal_id = $perId")[0] ?? null;
                $nuevo = ($previa ?? ['cargo' => '', 'empresa' => '', 'telefono' => '', 'correo' => '']);
                foreach (['cargo', 'empresa', 'telefono', 'correo'] as $c) if (trim((string)$nuevo[$c]) === '' && ($d[$c] ?? '') !== '') $nuevo[$c] = $d[$c];
                self::db()->query(sprintf("REPLACE INTO personal_contacto (personal_id, cargo, empresa, telefono, correo) VALUES (%d, '%s', '%s', '%s', '%s')", $perId, self::esc($nuevo['cargo']), self::esc($nuevo['empresa']), self::esc($nuevo['telefono']), self::esc($nuevo['correo'])));
                $deptoAntes = null;
                if (!$creado && trim((string)$per->departamento) === '' && ($d['departamento'] ?? '') !== '') { $deptoAntes = (string)$per->departamento; $per->departamento = $d['departamento']; $per->guardar(); }
                return ['personal_id' => $perId, 'creado' => $creado, 'previa' => $previa, 'departamento_antes' => $deptoAntes, 'departamento_despues' => $deptoAntes !== null ? $d['departamento'] : null];
            }
            case 'responsable': {
                $act = Actividad::find((int)$d['actividad_id']);
                if (!$act) throw new \RuntimeException('La actividad ya no existe');
                [$cid, $s] = self::mejor($d['persona'], array_column(self::filas("SELECT id, nombre FROM personal WHERE proyecto_id = $pid"), 'nombre', 'id'));
                if (!$cid || $s < 0.85) throw new \RuntimeException('«' . $d['persona'] . '» no está en el personal: aplica también su contacto');
                $previo = self::filas('SELECT personal_id FROM actividad_responsables WHERE actividad_id = ' . (int)$act->id)[0]['personal_id'] ?? null;
                self::db()->query('REPLACE INTO actividad_responsables (actividad_id, personal_id) VALUES (' . (int)$act->id . ', ' . (int)$cid . ')');
                return ['actividad_id' => (int)$act->id, 'personal_id' => (int)$cid, 'previo' => $previo !== null ? (int)$previo : null];
            }
            case 'cantidad_obra': {
                $act = Actividad::find((int)$d['actividad_id']);
                if (!$act) throw new \RuntimeException('La actividad ya no existe');
                $previa = self::filas('SELECT clave, concepto, cantidad, unidad, importe, origen FROM actividad_cantidades WHERE actividad_id = ' . (int)$act->id)[0] ?? null;
                self::db()->query(sprintf("REPLACE INTO actividad_cantidades (actividad_id, proyecto_id, clave, concepto, cantidad, unidad, importe, origen) VALUES (%d, %d, '%s', '%s', %s, '%s', %s, '%s')",
                    (int)$act->id, $pid, self::esc($d['clave']), self::esc($d['concepto']), (float)$d['cantidad'], self::esc($d['unidad']), $d['importe'] === null ? 'NULL' : (float)$d['importe'], self::esc($d['origen'])));
                return ['actividad_id' => (int)$act->id, 'previa' => $previa];
            }
            case 'compromiso': {
                $b = new \Model\Bitacora(['proyecto_id' => $pid, 'fecha' => $d['fecha'], 'departamento' => $d['departamento'], 'descripcion' => $d['descripcion'], 'importancia' => $d['importancia']]);
                if ($errores = $b->validar()) throw new \RuntimeException(implode(' ', array_merge(...array_values($errores))));
                $res = $b->guardar();
                if (!is_array($res) || empty($res['id'])) throw new \RuntimeException('No se pudo crear la entrada de bitácora');
                return ['bitacora_id' => (int)$res['id']];
            }
            case 'normativa_documento': {
                $a = Archivo::find((int)$d['archivo_id']);
                if (!$a || (int)$a->proyecto_id !== $pid) throw new \RuntimeException('El archivo ya no está en el Archivero');
                $ruta = Archivo::carpeta($pid) . '/' . $a->archivo_guardado;
                if (!is_file($ruta)) throw new \RuntimeException('No se encontró el archivo guardado');
                [$ok, $msg, $docId] = Normativa::subir($p, ['name' => $d['nombre'], 'tmp_name' => $ruta], '', 'auto', '');
                if (!$docId) throw new \RuntimeException($msg);
                $sug = ['entidades' => 0, 'propuestas' => 0];
                if ($ok) { try { $sug = Normativa::sugerirSinIA($p); } catch (\Throwable $e) {} }
                return ['documento_id' => $docId, 'mensaje' => $msg, 'propuestas' => $sug['propuestas']];
            }
            case 'dependencia': {
                $previa = null;
                foreach (Dependencias::relaciones($pid) as $r) if ($r['pred'] === (int)$d['pred'] && $r['suc'] === (int)$d['suc']) $previa = $r;
                [$ok, $msg] = Dependencias::guardar($pid, (int)$d['pred'], (int)$d['suc'], $d['tipo'], (int)$d['retraso']);
                if (!$ok) throw new \RuntimeException($msg);
                $nueva = null;
                foreach (Dependencias::relaciones($pid) as $r) if ($r['pred'] === (int)$d['pred'] && $r['suc'] === (int)$d['suc']) $nueva = $r;
                return ['dependencia_id' => $nueva['id'] ?? null, 'previa' => $previa ? ['tipo' => $previa['tipo'], 'retraso' => $previa['retraso']] : null, 'pred' => (int)$d['pred'], 'suc' => (int)$d['suc']];
            }
            case 'festivo': {
                if (self::filas("SELECT id FROM calendario_festivos WHERE proyecto_id = $pid AND fecha = '" . self::esc($d['fecha']) . "'")) throw new \RuntimeException('Esa fecha ya está en el calendario');
                $f = new \Model\CalendarioFestivo(['proyecto_id' => $pid, 'fecha' => $d['fecha'], 'motivo' => $d['motivo'], 'tipo' => 'otro']);
                $res = $f->guardar();
                if (!is_array($res) || empty($res['id'])) throw new \RuntimeException('No se pudo agregar el día no hábil');
                return ['festivo_id' => (int)$res['id']];
            }
            case 'fecha_contrato': {
                self::db()->query(sprintf("INSERT INTO proyecto_fechas_contrato (proyecto_id, tipo, nombre, fecha, penalizacion, origen) VALUES (%d, '%s', '%s', '%s', '%s', '%s')",
                    $pid, self::esc($d['tipo']), self::esc($d['nombre']), self::esc($d['fecha']), self::esc($d['penalizacion']), self::esc($d['origen'])));
                if (!self::db()->insert_id) throw new \RuntimeException('No se pudo guardar la fecha contractual (¿aplicaste la migración?)');
                return ['fecha_id' => (int)self::db()->insert_id];
            }
            case 'restriccion': {
                $act = Actividad::find((int)$d['actividad_id']);
                if (!$act) throw new \RuntimeException('La actividad ya no existe');
                $r = new \Model\Restriccion(['actividad_id' => (int)$d['actividad_id'], 'tipo_id' => (int)$d['tipo_id'], 'descripcion' => $d['descripcion'], 'fecha' => $d['fecha'] ?: null, 'estado' => 'activa']);
                $res = $r->guardar();
                if (!is_array($res) || empty($res['id'])) throw new \RuntimeException('No se pudo crear la restricción');
                return ['restriccion_id' => (int)$res['id']];
            }
            case 'submittal': {
                // materiales del paquete: existentes (por nombre), recién creados en esta tanda o nuevos sin cantidad
                $todos = array_column(self::materialesProyecto($pid), 'nombre', 'id');
                $ids = [];
                $creados = [];
                foreach ($d['materiales'] as $n) {
                    $k = self::norm($n);
                    if (isset($materialesCreados[$k])) { $ids[] = $materialesCreados[$k]; continue; }
                    [$cid, $s] = self::mejor($n, $todos);
                    if ($cid && $s >= 0.85) { $ids[] = (int)$cid; continue; }
                    $m = new Material(['proyecto_id' => $pid, 'nombre_base' => $n, 'nombre' => $n]);
                    $m->proyecto_id = $pid;
                    $m->nombre_base = $n;
                    $m->armarNombre();
                    $res = $m->guardar();
                    if (!is_array($res) || empty($res['id'])) throw new \RuntimeException('No se pudo crear el material «' . $n . '»');
                    $materialesCreados[$k] = $creados[] = $ids[] = (int)$res['id'];
                    $todos[(int)$res['id']] = $m->nombre;
                }
                if ($d['destino'] === 'existente') {
                    $s = Submittal::find((int)$d['submittal_id']);
                    if (!$s || (int)$s->proyecto_id !== $pid) throw new \RuntimeException('El submittal ya no existe');
                    $previos = $s->idsMateriales();
                    $s->sincronizarMateriales(array_merge($previos, $ids));
                    return ['destino' => 'existente', 'submittal_id' => (int)$s->id, 'previos' => $previos, 'materiales_creados' => $creados];
                }
                $numero = $d['numero'] !== '' ? $d['numero'] : 'ENS-' . date('Ymd-His');
                $s = new Submittal(['proyecto_id' => $pid, 'numero_submittal' => $numero, 'numero_trabajo' => (string)$p->numero_trabajo, 'especialidad' => $d['especialidad'] !== '' ? \Model\Especialidad::resolver($pid, $d['especialidad']) : '']);
                $s->proyecto_id = $pid;
                $res = $s->guardar();
                if (!is_array($res) || empty($res['id'])) throw new \RuntimeException('No se pudo crear el submittal');
                $s->id = (int)$res['id'];
                $s->sincronizarMateriales($ids);
                return ['destino' => 'nuevo', 'submittal_id' => (int)$s->id, 'numero' => $numero, 'materiales_creados' => $creados];
            }
        }
        throw new \RuntimeException('Tipo de cambio desconocido');
    }

    /** Entidad para la auditoría */
    private static function entidadDe(array $it, array $antes): array
    {
        return match ($it['tipo']) {
            'partida_nueva', 'partida_actualizar' => ['partida', $antes['partida_id'] ?? null, $it['titulo']],
            'material_nuevo', 'material_actualizar' => ['material', $antes['material_id'] ?? null, $it['titulo']],
            'dependencia' => ['dependencia', $antes['dependencia_id'] ?? null, $it['titulo']],
            'festivo' => ['festivo', $antes['festivo_id'] ?? null, $it['titulo']],
            'fecha_contrato' => ['fecha_contrato', $antes['fecha_id'] ?? null, $it['titulo']],
            'normativa_documento' => ['normativa', $antes['documento_id'] ?? null, $it['titulo']],
            'contacto' => ['personal', $antes['personal_id'] ?? null, $it['titulo']],
            'responsable' => ['actividad', $antes['actividad_id'] ?? null, $it['titulo']],
            'cantidad_obra' => ['actividad', $antes['actividad_id'] ?? null, $it['titulo']],
            'compromiso' => ['bitacora', $antes['bitacora_id'] ?? null, $it['titulo']],
            'restriccion' => ['restriccion', $antes['restriccion_id'] ?? null, $it['titulo']],
            default => ['submittal', $antes['submittal_id'] ?? null, $it['titulo']],
        };
    }

    /**
     * Aplica los ítems elegidos (por id) en orden: materiales antes que submittals. Cada uno es independiente.
     * @return array{resultados: array, propuesta: array}
     */
    public static function aplicar(Proyecto $p, int $id, array $elegidos): array
    {
        $pid = (int)$p->id;
        $pr = self::propuesta($pid, $id);
        if (!$pr || !in_array($pr['estado'], ['pendiente', 'aplicada'], true)) throw new \RuntimeException('La propuesta ya no se puede aplicar');
        $items = $pr['items'];
        $orden = ['partida_nueva' => 1, 'partida_actualizar' => 2, 'material_nuevo' => 3, 'material_actualizar' => 4, 'submittal' => 5, 'festivo' => 6, 'dependencia' => 7, 'restriccion' => 8, 'fecha_contrato' => 9, 'normativa_documento' => 10, 'contacto' => 11, 'responsable' => 12, 'cantidad_obra' => 13, 'compromiso' => 14];
        $indices = array_keys($items);
        usort($indices, fn($a, $b) => ($orden[$items[$a]['tipo']] ?? 9) <=> ($orden[$items[$b]['tipo']] ?? 9));
        $resultados = [];
        $creados = [];
        foreach ($indices as $i) {
            $it = $items[$i];
            if (!in_array($it['id'], $elegidos, true) || ($it['estado'] ?? '') === 'aplicado') continue;
            self::db()->begin_transaction();
            try {
                $antes = self::aplicarItem($p, $it, $creados);
                self::db()->commit();
                $items[$i]['estado'] = 'aplicado';
                $items[$i]['antes'] = $antes;
                $items[$i]['error'] = null;
                $resultados[] = ['id' => $it['id'], 'ok' => true, 'titulo' => $it['titulo']];
                Auditoria::registrar($pid, 'ensamblador', 'aplicado', 'ens_' . $it['tipo'], $it['titulo'] . ' — ' . ($it['detalle'] ?? ''), null, ['antes' => $antes], $id, $pr['modelo'], self::entidadDe($it, $antes));
            } catch (\Throwable $e) {
                self::db()->rollback();
                $items[$i]['estado'] = 'error';
                $items[$i]['error'] = $e->getMessage();
                $resultados[] = ['id' => $it['id'], 'ok' => false, 'titulo' => $it['titulo'], 'error' => $e->getMessage()];
            }
        }
        $hay = (bool)array_filter($items, fn($x) => ($x['estado'] ?? '') === 'aplicado');
        self::guardarItems($id, $items, $hay ? 'aplicada' : null);
        if ($hay) Revisiones::registrarEjecucion($pid, 'ensamblador');
        return ['resultados' => $resultados, 'propuesta' => self::propuesta($pid, $id)];
    }

    // ------------------------------------------------------------------ revertir

    private static function revertirItem(Proyecto $p, array $it): string
    {
        $pid = (int)$p->id;
        $a = $it['antes'] ?? [];
        $db = self::db();
        switch ($it['tipo']) {
            case 'partida_nueva': {
                $lista = array_filter(array_map('intval', $a['actividades'] ?? []));
                if ($lista) $db->query('DELETE FROM actividades WHERE id IN (' . implode(',', $lista) . ')');
                $resto = (int)(self::filas('SELECT COUNT(*) n FROM actividades WHERE partida_id = ' . (int)$a['partida_id'])[0]['n'] ?? 0);
                if ($resto === 0) { $db->query('DELETE FROM partidas WHERE id = ' . (int)$a['partida_id'] . " AND proyecto_id = $pid"); return 'Partida eliminada'; }
                return "La partida se conservó: tiene $resto actividad(es) agregada(s) después";
            }
            case 'partida_actualizar': {
                $lista = array_filter(array_map('intval', $a['actividades'] ?? []));
                if ($lista) $db->query('DELETE FROM actividades WHERE id IN (' . implode(',', $lista) . ')');
                foreach ($a['completadas'] ?? [] as $c) $db->query('UPDATE actividades SET descripcion = \'\' WHERE id = ' . (int)$c['id'] . " AND descripcion = '" . self::esc($c['descripcion']) . "'");
                if ($a['descripcion_antes'] !== null) $db->query('UPDATE partidas SET descripcion = \'' . self::esc($a['descripcion_antes']) . '\' WHERE id = ' . (int)$a['partida_id'] . " AND descripcion = '" . self::esc($a['descripcion_despues'] ?? '') . "'");
                if ($pa = Partida::find((int)$a['partida_id'])) $pa->recalcularFechas();
                return 'Cambios de la partida deshechos';
            }
            case 'material_nuevo': {
                $mid = (int)$a['material_id'];
                $usos = (int)(self::filas("SELECT (SELECT COUNT(*) FROM submittal_materiales WHERE material_id = $mid) + (SELECT COUNT(*) FROM actividad_materiales WHERE material_id = $mid) n")[0]['n'] ?? 0);
                if ($usos > 0) return 'El material se conservó: ya se usa en submittals o actividades';
                $db->query("DELETE FROM materiales WHERE id = $mid AND proyecto_id = $pid");
                return 'Material eliminado';
            }
            case 'material_actualizar': {
                $m = Material::find((int)$a['material_id']);
                if (!$m) return 'El material ya no existe';
                $n = 0;
                foreach ($a['campos'] as $c => $v) if (is_numeric($m->$c) && is_numeric($v['despues']) ? (float)$m->$c === (float)$v['despues'] : (string)$m->$c === (string)$v['despues']) { $m->$c = $v['antes']; $n++; }
                if ($n) { $m->armarNombre(); $m->guardar(); }
                return $n ? 'Datos del material restaurados' : 'El material cambió después: no se tocó';
            }
            case 'contacto': {
                $perId = (int)$a['personal_id'];
                if (!empty($a['creado'])) {
                    $usos = (int)(self::filas("SELECT COUNT(*) n FROM actividad_responsables WHERE personal_id = $perId")[0]['n'] ?? 0);
                    if ($usos > 0) return 'La persona se conservó: ya es responsable de actividades';
                    $db->query("DELETE FROM personal WHERE id = $perId AND proyecto_id = $pid");
                    return 'Persona eliminada del personal';
                }
                if (!empty($a['previa'])) $db->query(sprintf("REPLACE INTO personal_contacto (personal_id, cargo, empresa, telefono, correo) VALUES (%d, '%s', '%s', '%s', '%s')", $perId, self::esc($a['previa']['cargo']), self::esc($a['previa']['empresa']), self::esc($a['previa']['telefono']), self::esc($a['previa']['correo'])));
                else $db->query("DELETE FROM personal_contacto WHERE personal_id = $perId");
                if (($a['departamento_antes'] ?? null) !== null) $db->query("UPDATE personal SET departamento = '" . self::esc($a['departamento_antes']) . "' WHERE id = $perId AND proyecto_id = $pid AND departamento = '" . self::esc($a['departamento_despues'] ?? '') . "'");
                return 'Datos de contacto restaurados';
            }
            case 'responsable': {
                $aid = (int)$a['actividad_id'];
                if ($a['previo'] !== null) $db->query("REPLACE INTO actividad_responsables (actividad_id, personal_id) VALUES ($aid, " . (int)$a['previo'] . ')');
                else $db->query("DELETE FROM actividad_responsables WHERE actividad_id = $aid AND personal_id = " . (int)$a['personal_id']);
                return 'Responsable restaurado';
            }
            case 'cantidad_obra': {
                $aid = (int)$a['actividad_id'];
                if (!empty($a['previa'])) {
                    $v = $a['previa'];
                    $db->query(sprintf("REPLACE INTO actividad_cantidades (actividad_id, proyecto_id, clave, concepto, cantidad, unidad, importe, origen) VALUES (%d, %d, '%s', '%s', %s, '%s', %s, '%s')", $aid, $pid, self::esc($v['clave']), self::esc($v['concepto']), $v['cantidad'] === null ? 'NULL' : (float)$v['cantidad'], self::esc($v['unidad']), $v['importe'] === null ? 'NULL' : (float)$v['importe'], self::esc($v['origen'])));
                    return 'Cantidad anterior restaurada';
                }
                $db->query("DELETE FROM actividad_cantidades WHERE actividad_id = $aid");
                return 'Cantidad de obra eliminada';
            }
            case 'compromiso':
                $db->query('DELETE FROM bitacora WHERE id = ' . (int)$a['bitacora_id'] . " AND proyecto_id = $pid");
                return 'Entrada de bitácora eliminada';
            case 'normativa_documento':
                Normativa::eliminar($pid, (int)$a['documento_id']);
                return 'Documento quitado de Normativa (con sus vínculos propuestos)';
            case 'dependencia': {
                $did = (int)($a['dependencia_id'] ?? 0);
                if (!empty($a['previa'])) {
                    $db->query("UPDATE actividad_dependencias SET tipo = '" . self::esc($a['previa']['tipo']) . "', retraso = " . (int)$a['previa']['retraso'] . " WHERE id = $did AND proyecto_id = $pid");
                    return 'Relación restaurada a como estaba';
                }
                Dependencias::eliminar($pid, $did);
                return 'Dependencia eliminada';
            }
            case 'festivo':
                $db->query('DELETE FROM calendario_festivos WHERE id = ' . (int)$a['festivo_id'] . " AND proyecto_id = $pid");
                return 'Día no hábil quitado';
            case 'fecha_contrato':
                $db->query('DELETE FROM proyecto_fechas_contrato WHERE id = ' . (int)$a['fecha_id'] . " AND proyecto_id = $pid");
                return 'Fecha contractual eliminada';
            case 'restriccion': {
                $rid = (int)$a['restriccion_id'];
                $db->query("DELETE FROM restricciones WHERE id = $rid AND estado = 'activa'");
                return 'Restricción eliminada';
            }
            case 'submittal': {
                $sid = (int)$a['submittal_id'];
                $s = Submittal::find($sid);
                if ($s) {
                    if ($a['destino'] === 'nuevo') $s->eliminar();
                    else $s->sincronizarMateriales($a['previos'] ?? []);
                }
                foreach ($a['materiales_creados'] ?? [] as $mid) {
                    $mid = (int)$mid;
                    $usos = (int)(self::filas("SELECT (SELECT COUNT(*) FROM submittal_materiales WHERE material_id = $mid) + (SELECT COUNT(*) FROM actividad_materiales WHERE material_id = $mid) n")[0]['n'] ?? 0);
                    if ($usos === 0) $db->query("DELETE FROM materiales WHERE id = $mid AND proyecto_id = $pid");
                }
                return $a['destino'] === 'nuevo' ? 'Submittal eliminado' : 'Materiales quitados del submittal';
            }
        }
        return '';
    }

    /** Deshace todo lo aplicado de la propuesta, en orden inverso */
    public static function revertir(Proyecto $p, int $id): array
    {
        $pid = (int)$p->id;
        $pr = self::propuesta($pid, $id);
        if (!$pr || $pr['estado'] !== 'aplicada') throw new \RuntimeException('Esta propuesta no tiene cambios aplicados');
        $items = $pr['items'];
        $res = [];
        foreach (array_reverse(array_keys($items)) as $i) {
            $it = $items[$i];
            if (($it['estado'] ?? '') !== 'aplicado') continue;
            self::db()->begin_transaction();
            try {
                $msg = self::revertirItem($p, $it);
                self::db()->commit();
                $items[$i]['estado'] = 'revertido';
                $res[] = ['id' => $it['id'], 'titulo' => $it['titulo'], 'mensaje' => $msg];
                Auditoria::registrar($pid, 'ensamblador', 'revertido', 'ens_' . $it['tipo'], $it['titulo'] . ' — ' . $msg, null, null, $id, $pr['modelo'], self::entidadDe($it, $it['antes'] ?? []));
            } catch (\Throwable $e) {
                self::db()->rollback();
                $res[] = ['id' => $it['id'], 'titulo' => $it['titulo'], 'mensaje' => 'No se pudo deshacer: ' . $e->getMessage()];
            }
        }
        $quedan = (bool)array_filter($items, fn($x) => ($x['estado'] ?? '') === 'aplicado');
        self::guardarItems($id, $items, $quedan ? 'aplicada' : 'revertida');
        return ['resultados' => $res, 'propuesta' => self::propuesta($pid, $id)];
    }
}
