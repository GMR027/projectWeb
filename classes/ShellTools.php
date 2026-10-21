<?php

namespace Classes;

/** Programas externos (poppler, LibreOffice, Ghostscript…): comprobar que existen y correrlos con tope de tiempo y de salida */
class ShellTools
{
    public static function disponible(string $bin): bool
    {
        static $cache = [];
        if (isset($cache[$bin])) return $cache[$bin];
        foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $d) {
            if ($d !== '' && is_executable($d . '/' . $bin)) return $cache[$bin] = true;
        }
        return $cache[$bin] = false;
    }

    /**
     * Corre $cmd (arreglo, sin shell) y devuelve ['salida', 'error', 'codigo', 'agotado'] o null si no se pudo iniciar.
     * `agotado` = se pasó del tiempo o del tamaño y se mató el proceso. $env = variables extra (p. ej. HOME).
     */
    public static function ejecutar(array $cmd, int $segundos = 60, int $maxBytes = 8_000_000, ?array $env = null): ?array
    {
        $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($proc)) return null;
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $salida = $error = '';
        $fin = time() + $segundos;
        $agotado = false;
        while (true) {
            $salida .= (string)stream_get_contents($pipes[1]);
            $error .= (string)stream_get_contents($pipes[2]);
            if (!proc_get_status($proc)['running']) break;
            if (time() > $fin || strlen($salida) > $maxBytes) { $agotado = true; proc_terminate($proc, 9); break; }
            usleep(40000);
        }
        $salida .= (string)stream_get_contents($pipes[1]);
        $error .= (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $codigo = proc_close($proc);
        return ['salida' => $salida, 'error' => $error, 'codigo' => $codigo, 'agotado' => $agotado];
    }

    /** true si el comando terminó con código 0 */
    public static function correr(array $cmd, int $segundos = 60): bool
    {
        $r = self::ejecutar($cmd, $segundos, 1_000_000);
        return $r !== null && !$r['agotado'] && $r['codigo'] === 0;
    }

    /** Une PDF en orden con pdfunite o, si no está, Ghostscript */
    public static function unirPdf(array $rutas, string $salida): bool
    {
        if (count($rutas) === 1) return copy($rutas[0], $salida);
        $cmds = [];
        if (self::disponible('pdfunite')) $cmds[] = array_merge(['pdfunite'], $rutas, [$salida]);
        if (self::disponible('gs')) $cmds[] = array_merge(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite', '-sOutputFile=' . $salida], $rutas);
        foreach ($cmds as $cmd) {
            if (self::correr($cmd, 180) && is_file($salida) && filesize($salida) > 0) return true;
        }
        return false;
    }

    /**
     * Convierte con LibreOffice (doc, xls, ppt, odt… → pdf, docx, xlsx…). Devuelve la ruta de un archivo temporal nuevo
     * (quien llama lo borra) o null si no hay LibreOffice o falló. LibreOffice decide el filtro por la extensión, por eso se copia con $extOrigen.
     */
    public static function libreOffice(string $ruta, string $extOrigen, string $formato = 'pdf', int $segundos = 120): ?string
    {
        if (!self::disponible('soffice')) return null;
        $dir = sys_get_temp_dir() . '/lo_' . bin2hex(random_bytes(6));
        if (!@mkdir($dir, 0700)) return null;
        $resultado = null;
        try {
            $ext = preg_replace('/[^a-z0-9]/', '', strtolower($extOrigen)) ?: 'bin';
            $entrada = "$dir/origen.$ext";
            if (!@copy($ruta, $entrada)) return null;
            self::ejecutar(['soffice', "-env:UserInstallation=file://$dir/perfil", '--headless', '--convert-to', $formato, '--outdir', $dir, $entrada], $segundos, 1_000_000, ['HOME' => $dir] + getenv());
            if (is_file("$dir/origen.$formato")) {
                $copia = tempnam(sys_get_temp_dir(), 'lo');
                if (@copy("$dir/origen.$formato", $copia)) $resultado = $copia; else @unlink($copia);
            }
        } finally {
            self::borrarDir($dir);
        }
        return $resultado;
    }

    public static function borrarDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /** Texto de un PDF con pdftotext; null si no se pudo correr. $layout conserva las columnas */
    public static function pdfATexto(string $ruta, bool $layout = true, int $segundos = 60, int $maxBytes = 8_000_000): ?array
    {
        return self::ejecutar(array_merge(['pdftotext'], $layout ? ['-layout'] : [], ['-enc', 'UTF-8', $ruta, '-']), $segundos, $maxBytes);
    }
}
