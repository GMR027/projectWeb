<?php

namespace Classes;

/** Convierte la primera página de un PDF en PNG con las herramientas del servidor (pdftoppm o Ghostscript) */
class PdfRaster
{
    public static function esPdf(string $ruta): bool
    {
        $f = @fopen($ruta, 'rb');
        $cab = $f ? (string)fread($f, 1024) : '';
        if ($f) fclose($f);
        return str_contains($cab, '%PDF-');
    }

    public static function disponible(): bool
    {
        return ShellTools::disponible('pdftoppm') || ShellTools::disponible('gs');
    }

    /** Devuelve la ruta de un PNG temporal con la página 1 (lado mayor ≈ $lado px) o null si no se pudo. Quien llama borra el archivo. */
    public static function primeraPagina(string $pdf, int $lado = 4000): ?string
    {
        if (!self::esPdf($pdf)) return null;
        $base = tempnam(sys_get_temp_dir(), 'pdfr');
        @unlink($base);
        $out = $base . '.png';
        $ok = false;
        if (ShellTools::disponible('pdftoppm')) {
            $ok = ShellTools::correr(['pdftoppm', '-png', '-f', '1', '-l', '1', '-singlefile', '-scale-to', (string)$lado, $pdf, $base]);
        }
        if (!$ok && ShellTools::disponible('gs')) {
            $ok = ShellTools::correr(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-dFirstPage=1', '-dLastPage=1', '-sDEVICE=png16m', '-r150', '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4', '-sOutputFile=' . $out, $pdf]);
        }
        if ($ok && is_file($out) && filesize($out) > 0) return $out;
        @unlink($out);
        return null;
    }
}
