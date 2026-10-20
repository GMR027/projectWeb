<?php

namespace Classes;

/** Qué archivos del Archivo se pueden ver en el navegador: PDF, imágenes y texto tal cual; ofimática convertida a PDF con LibreOffice */
class VistaArchivo
{
    private const IMAGENES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp'];   // el SVG queda fuera a propósito
    private const TEXTO = ['txt', 'csv'];
    private const OFIMATICA = ['doc', 'docx', 'rtf', 'odt', 'ott', 'xls', 'xlsx', 'ods', 'ots', 'ppt', 'pptx', 'odp', 'otp'];

    /** 'directo', 'texto', 'convertir' o null si no se puede ver */
    public static function tipo(string $ext): ?string
    {
        $ext = strtolower($ext);
        if ($ext === 'pdf' || isset(self::IMAGENES[$ext])) return 'directo';
        if (in_array($ext, self::TEXTO, true)) return 'texto';
        if (in_array($ext, self::OFIMATICA, true) && self::hayLibreOffice()) return 'convertir';
        return null;
    }

    public static function hayLibreOffice(): bool
    {
        static $r = null;
        if ($r !== null) return $r;
        foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $d) if ($d !== '' && is_executable($d . '/soffice')) return $r = true;
        return $r = false;
    }

    public static function mime(string $ext): string
    {
        $ext = strtolower($ext);
        return $ext === 'pdf' ? 'application/pdf' : (self::IMAGENES[$ext] ?? 'text/plain; charset=utf-8');
    }

    /** PDF de un documento de ofimática (se guarda en caché según su contenido); null si no se pudo convertir */
    public static function pdfDe(string $ruta, string $ext): ?string
    {
        $cache = sys_get_temp_dir() . '/vista_' . sha1($ruta . filesize($ruta) . filemtime($ruta)) . '.pdf';
        if (is_file($cache)) return $cache;
        @set_time_limit(150);
        $dir = sys_get_temp_dir() . '/vista_' . bin2hex(random_bytes(6));
        if (!@mkdir($dir, 0700)) return null;
        $entrada = "$dir/origen." . preg_replace('/[^a-z0-9]/', '', strtolower($ext));   // LibreOffice elige el filtro por la extensión
        copy($ruta, $entrada);
        $p = @proc_open(['soffice', "-env:UserInstallation=file://$dir/perfil", '--headless', '--convert-to', 'pdf', '--outdir', $dir, $entrada], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($p)) {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $limite = time() + 120;
            while (proc_get_status($p)['running'] && time() < $limite) { stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); usleep(100000); }
            if (proc_get_status($p)['running']) proc_terminate($p, 9);
            proc_close($p);
        }
        $ok = is_file("$dir/origen.pdf") && @copy("$dir/origen.pdf", $cache);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
        return $ok ? $cache : null;
    }
}
