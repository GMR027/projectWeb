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
        return self::existe('pdftoppm') || self::existe('gs');
    }

    private static function existe(string $bin): bool
    {
        foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $d) if ($d !== '' && is_executable($d . '/' . $bin)) return true;
        return false;
    }

    private static function correr(array $cmd): bool
    {
        $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($p)) return false;
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        return proc_close($p) === 0;
    }

    /** Devuelve la ruta de un PNG temporal con la página 1 (lado mayor ≈ $lado px) o null si no se pudo. Quien llama borra el archivo. */
    public static function primeraPagina(string $pdf, int $lado = 4000): ?string
    {
        if (!self::esPdf($pdf)) return null;
        $base = tempnam(sys_get_temp_dir(), 'pdfr');
        @unlink($base);
        $out = $base . '.png';
        $ok = false;
        if (self::existe('pdftoppm')) {
            $ok = self::correr(['pdftoppm', '-png', '-f', '1', '-l', '1', '-singlefile', '-scale-to', (string)$lado, $pdf, $base]);
        }
        if (!$ok && self::existe('gs')) {
            $ok = self::correr(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-dFirstPage=1', '-dLastPage=1', '-sDEVICE=png16m', '-r150', '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4', '-sOutputFile=' . $out, $pdf]);
        }
        if ($ok && is_file($out) && filesize($out) > 0) return $out;
        @unlink($out);
        return null;
    }
}
