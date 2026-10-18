<?php

namespace Classes;

/**
 * Revisión de seguridad de los archivos subidos a la sección Archivo, ANTES de guardarlos:
 * nombre (dobles extensiones), contenido real contra la extensión (firma), ejecutables disfrazados,
 * macros, zips peligrosos (bombas, rutas «../», ejecutables dentro) y antivirus ClamAV (clamd) si está configurado.
 * Variables: CLAMAV_HOST (+ CLAMAV_PORT, 3310 por defecto) o CLAMAV_SOCKET; CLAMAV_REQUIRED=1 rechaza la subida si el antivirus no responde.
 */
class EscanerArchivos
{
    /** Extensiones que nunca deben aparecer en el nombre (ni como doble extensión ni dentro de un zip) */
    private const PELIGROSAS = ['exe', 'dll', 'com', 'scr', 'bat', 'cmd', 'msi', 'msp', 'pif', 'cpl', 'lnk', 'hta', 'vbs', 'vbe', 'wsf', 'wsh',
        'ps1', 'psm1', 'sh', 'bash', 'jar', 'js', 'jse', 'mjs', 'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'inc',
        'asp', 'aspx', 'jsp', 'jspx', 'cgi', 'pl', 'py', 'rb', 'html', 'htm', 'xhtml', 'shtml', 'htaccess', 'reg', 'apk', 'app', 'deb', 'rpm', 'elf', 'so', 'sys', 'vbaproject'];

    private const OLE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    private const MAX_ENTRADAS_ZIP = 5000;
    private const MAX_DESCOMPRIMIDO = 4 * 1024 * 1024 * 1024;   // 4 GB
    private const MAX_RATIO = 250;                                // descomprimido / comprimido por entrada

    /** @return string[] motivos de rechazo (vacío = archivo limpio) */
    public static function revisar(string $ruta, string $nombreOriginal): array
    {
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if ($e = self::revisarNombre($nombreOriginal)) return [$e];

        $cabeza = (string)@file_get_contents($ruta, false, null, 0, 4096);
        if ($cabeza === '' && (int)@filesize($ruta) === 0) return ['El archivo está vacío'];
        if ($e = self::revisarFirma($ruta, $ext, $cabeza)) return [$e];

        if (in_array($ext, ['zip', 'ifczip', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'odg', 'otg', 'ott', 'ots', 'otp', 'odf'], true)) {
            if ($e = self::revisarZip($ruta, $ext === 'zip' || $ext === 'ifczip')) return [$e];
        }
        if (in_array($ext, ['doc', 'xls', 'ppt'], true) && self::contieneMacrosOle($ruta)) {
            return ['El documento contiene macros (VBA); guárdalo sin macros'];
        }
        return self::antivirus($ruta);
    }

    private static function revisarNombre(string $nombre): ?string
    {
        if (str_contains($nombre, "\0")) return 'Nombre de archivo no válido';
        $partes = explode('.', strtolower(basename(str_replace('\\', '/', $nombre))));
        array_shift($partes);   // lo anterior al primer punto es el nombre
        foreach ($partes as $p) {
            if (in_array(trim($p), self::PELIGROSAS, true)) return 'Nombre de archivo sospechoso (.' . $p . ' dentro del nombre)';
        }
        return null;
    }

    /** El contenido debe corresponder a la extensión y no ser un ejecutable/script */
    private static function revisarFirma(string $ruta, string $ext, string $cabeza): ?string
    {
        $inicio = ltrim($cabeza, "\xEF\xBB\xBF \t\r\n");
        if (str_starts_with($cabeza, 'MZ') || str_starts_with($cabeza, "\x7FELF") || str_starts_with($cabeza, '#!')
            || in_array(substr($cabeza, 0, 4), ["\xCA\xFE\xBA\xBE", "\xFE\xED\xFA\xCE", "\xFE\xED\xFA\xCF", "\xCF\xFA\xED\xFE", "\xCE\xFA\xED\xFE"], true)
            || stripos($cabeza, '<?php') !== false) {
            return 'El archivo es un ejecutable o script, no un documento';
        }

        $ok = match (true) {
            $ext === 'pdf' => str_contains(substr($cabeza, 0, 1024), '%PDF-'),
            in_array($ext, ['doc', 'xls', 'ppt', 'mpp'], true) => str_starts_with($cabeza, self::OLE),
            in_array($ext, ['zip', 'ifczip', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'odg', 'otg', 'ott', 'ots', 'otp', 'odf'], true) => str_starts_with($cabeza, "PK\x03\x04") || str_starts_with($cabeza, "PK\x05\x06"),
            $ext === 'rar' => str_starts_with($cabeza, 'Rar!'),
            $ext === '7z' => str_starts_with($cabeza, "7z\xBC\xAF\x27\x1C"),
            $ext === 'dwg' => (bool)preg_match('/^AC\d/', $cabeza),
            $ext === 'ifc' => str_contains(substr($cabeza, 0, 512), 'ISO-10303-21'),
            $ext === 'rtf' => str_starts_with($inicio, '{\\rtf'),
            $ext === 'skp' => str_starts_with($cabeza, "\xFF\xFE\xFF\x0E"),
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'], true) => @getimagesize($ruta) !== false,
            $ext === 'svg' => self::svgSeguro($ruta),
            in_array($ext, ['txt', 'csv', 'xml', 'pod', 'dxf', 'kml', 'obj', 'stp', 'step'], true) => ($ext === 'dxf' && str_starts_with($cabeza, 'AutoCAD Binary DXF')) || !str_contains($cabeza, "\0"),
            default => true,   // Revit (rvt, rfa…), dgn, dwt, dws, dwf, nwd, 3dm… formatos binarios propietarios: solo controles generales + antivirus
        };
        return $ok ? null : 'El contenido no corresponde a un archivo .' . $ext . ' (extensión falsa o archivo dañado)';
    }

    private static function svgSeguro(string $ruta): bool
    {
        $t = (string)@file_get_contents($ruta, false, null, 0, 5 * 1024 * 1024);
        return stripos($t, '<svg') !== false
            && !preg_match('/<\s*(script|foreignObject|iframe|embed|object)\b|\son[a-z]+\s*=|javascript:|<!ENTITY/i', $t);
    }

    private static function revisarZip(string $ruta, bool $esContenedorLibre): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($ruta, \ZipArchive::RDONLY) !== true) return 'El archivo comprimido está dañado o no se puede leer';
        try {
            if ($zip->numFiles > self::MAX_ENTRADAS_ZIP) return 'El archivo comprimido tiene demasiados elementos';
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $s = $zip->statIndex($i);
                if ($s === false) return 'El archivo comprimido está dañado';
                $n = str_replace('\\', '/', (string)$s['name']);
                if (str_contains($n, "\0") || str_starts_with($n, '/') || preg_match('#(^|/)\.\.(/|$)#', $n)) return 'El comprimido contiene rutas no válidas';
                if (!empty($s['encryption_method'])) return 'El comprimido está cifrado y no se puede revisar en busca de virus';
                $total += (int)$s['size'];
                if ($total > self::MAX_DESCOMPRIMIDO) return 'El comprimido ocupa demasiado al descomprimirse';
                if ($s['comp_size'] > 0 && $s['size'] / $s['comp_size'] > self::MAX_RATIO && $s['size'] > 10 * 1024 * 1024) return 'El comprimido parece una bomba de compresión';
                $base = strtolower(basename($n));
                if (str_contains($base, 'vbaproject') || preg_match('#(^|/)(Basic|Scripts)/#', $n)) return 'El documento contiene macros; guárdalo sin macros';
                if ($esContenedorLibre && substr($n, -1) !== '/') {
                    $partes = explode('.', $base);
                    array_shift($partes);
                    foreach ($partes as $p) if (in_array($p, self::PELIGROSAS, true)) return 'El comprimido contiene un archivo no permitido (' . basename($n) . ')';
                }
            }
        } finally {
            $zip->close();
        }
        return null;
    }

    /** Busca el proyecto VBA (UTF-16) en documentos OLE de Office antiguos */
    private static function contieneMacrosOle(string $ruta): bool
    {
        $aguja = "V\0B\0A\0_\0P\0R\0O\0J\0E\0C\0T\0";
        $h = @fopen($ruta, 'rb');
        if (!$h) return false;
        $cola = '';
        while (!feof($h)) {
            $bloque = fread($h, 1 << 20);
            if ($bloque === false) break;
            if (str_contains($cola . $bloque, $aguja)) { fclose($h); return true; }
            $cola = substr($bloque, -strlen($aguja));
        }
        fclose($h);
        return false;
    }

    // ============================ ClamAV ============================

    private static function conf(string $k): string
    {
        return trim((string)($_ENV[$k] ?? getenv($k) ?: ''));
    }

    public static function antivirusConfigurado(): bool
    {
        return self::conf('CLAMAV_HOST') !== '' || self::conf('CLAMAV_SOCKET') !== '';
    }

    /** @return string[] */
    private static function antivirus(string $ruta): array
    {
        $requerido = in_array(strtolower(self::conf('CLAMAV_REQUIRED')), ['1', 'true', 'si', 'sí'], true);
        if (!self::antivirusConfigurado()) {
            return $requerido ? ['El antivirus no está configurado en el servidor; no se aceptan archivos por ahora'] : [];
        }
        $destino = self::conf('CLAMAV_SOCKET') !== '' ? 'unix://' . self::conf('CLAMAV_SOCKET')
            : 'tcp://' . self::conf('CLAMAV_HOST') . ':' . (self::conf('CLAMAV_PORT') ?: '3310');
        $errno = 0; $errstr = '';
        $s = @stream_socket_client($destino, $errno, $errstr, 5);
        $falla = $requerido ? ['No se pudo verificar el archivo con el antivirus; inténtalo de nuevo en unos minutos'] : [];
        if (!$s) { error_log("EscanerArchivos: clamd inaccesible ($destino): $errstr"); return $falla; }

        stream_set_timeout($s, 120);
        $h = @fopen($ruta, 'rb');
        if (!$h) { fclose($s); return $falla; }
        fwrite($s, "zINSTREAM\0");
        while (!feof($h)) {
            $bloque = fread($h, 1 << 16);
            if ($bloque === false || $bloque === '') break;
            if (@fwrite($s, pack('N', strlen($bloque)) . $bloque) === false) break;
        }
        fclose($h);
        @fwrite($s, pack('N', 0));
        $resp = trim((string)stream_get_contents($s));
        fclose($s);

        if (preg_match('/^stream: (.+) FOUND$/', $resp, $m)) {
            error_log("EscanerArchivos: malware detectado ({$m[1]})");
            return ['El antivirus detectó una amenaza en el archivo (' . $m[1] . '); no se guardó'];
        }
        if (str_ends_with($resp, 'OK')) return [];
        error_log("EscanerArchivos: respuesta inesperada de clamd: $resp");
        return $falla;
    }
}
