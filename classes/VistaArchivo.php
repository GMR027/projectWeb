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
        return ShellTools::disponible('soffice');
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
        $pdf = ShellTools::libreOffice($ruta, $ext, 'pdf');
        if (!$pdf) return null;
        $ok = @copy($pdf, $cache);
        @unlink($pdf);
        return $ok ? $cache : null;
    }
}
