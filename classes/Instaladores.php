<?php

namespace Classes;

/** Instaladores de DictaFlow (Linux) guardados en el volumen del clúster; solo los gestiona y descarga el admin */
class Instaladores
{
    /** Terminaciones permitidas (nada ejecutable por el servidor web) */
    private const EXT = ['.deb', '.rpm', '.pacman', '.appimage', '.tar.gz', '.sha256sums.txt', 'latest-linux.yml'];

    public static function dir(): string
    {
        $d = dirname(__DIR__) . '/storage/instaladores';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    /** Nombre seguro o null: solo letras, números, punto, guion y guion bajo, y una terminación permitida */
    public static function nombreValido(string $n): ?string
    {
        $n = basename(trim($n));
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $n)) return null;
        $min = strtolower($n);
        foreach (self::EXT as $e) {
            if (str_ends_with($min, $e) || (in_array($e, ['.sha256sums.txt', 'latest-linux.yml'], true) && $min === ltrim($e, '.'))) return $n;
        }
        return null;
    }

    public static function ruta(string $nombre): ?string
    {
        $n = self::nombreValido($nombre);
        $r = $n ? self::dir() . '/' . $n : null;
        return $r && is_file($r) ? $r : null;
    }

    /** @return array<int,array{nombre:string,tam:int,fecha:int,sha:string}> */
    public static function listar(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*') ?: [] as $f) {
            $n = basename($f);
            if (!is_file($f) || !self::nombreValido($n)) continue;
            $out[] = ['nombre' => $n, 'tam' => filesize($f), 'fecha' => filemtime($f), 'sha' => self::sha($f)];
        }
        usort($out, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
        return $out;
    }

    /** Versión publicada: la lee de latest-linux.yml (lo genera electron-builder) */
    public static function version(): string
    {
        $r = self::ruta('latest-linux.yml');
        if ($r && preg_match('/^version:\s*[\'"]?([0-9][0-9A-Za-z.+-]{0,30})/m', (string)file_get_contents($r), $m)) return $m[1];
        return '';
    }

    private static function hashToken(): string { return self::dir() . '/.token_hash'; }

    /** Crea un token de dispositivo para la app de escritorio (solo se guarda su hash; reemplaza al anterior) */
    public static function generarToken(): string
    {
        $t = bin2hex(random_bytes(24));
        file_put_contents(self::hashToken(), hash('sha256', $t));
        return $t;
    }

    public static function tokenValido(string $t): bool
    {
        $f = self::hashToken();
        return $t !== '' && is_file($f) && hash_equals(trim((string)file_get_contents($f)), hash('sha256', $t));
    }

    public static function hayToken(): bool { return is_file(self::hashToken()); }

    /** SHA-256 con caché junto al archivo (se recalcula si el archivo es más nuevo) */
    public static function sha(string $f): string
    {
        $c = $f . '.sha256';
        if (is_file($c) && filemtime($c) >= filemtime($f)) return trim((string)file_get_contents($c));
        $h = (string)hash_file('sha256', $f);
        @file_put_contents($c, $h);
        return $h;
    }

    /**
     * Guarda (o reemplaza) un instalador subido. $destino fuerza el nombre al reemplazar.
     * @return array{nombre:string,reemplazo:bool,cambio:bool}
     */
    public static function guardar(array $archivo, ?string $destino = null): array
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            throw new \RuntimeException(($archivo['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($archivo['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE
                ? 'El archivo supera el límite de subida del servidor.' : 'No se recibió el archivo.');
        }
        $nombre = self::nombreValido($destino ?: (string)$archivo['name']);
        if (!$nombre) throw new \RuntimeException('Nombre o tipo no permitido. Usa .deb, .rpm, .pacman, .AppImage, .tar.gz o SHA256SUMS.txt o latest-linux.yml (sin espacios).');
        $final = self::dir() . '/' . $nombre;
        $reemplazo = is_file($final);
        $antes = $reemplazo ? self::sha($final) : '';
        $nuevo = (string)hash_file('sha256', $archivo['tmp_name']);
        $tmp = $final . '.subiendo';
        if (!move_uploaded_file($archivo['tmp_name'], $tmp)) throw new \RuntimeException('No se pudo guardar el archivo (¿permisos del volumen?).');
        chmod($tmp, 0664);
        rename($tmp, $final);   // atómico: nadie descarga un archivo a medias
        @file_put_contents($final . '.sha256', $nuevo);
        return ['nombre' => $nombre, 'reemplazo' => $reemplazo, 'cambio' => $antes !== $nuevo];
    }

    public static function eliminar(string $nombre): void
    {
        if ($r = self::ruta($nombre)) { @unlink($r); @unlink($r . '.sha256'); }
    }

    public static function entregar(string $nombre): bool
    {
        $r = self::ruta($nombre);
        if (!$r) return false;
        @set_time_limit(0);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($r) . '"');
        header('Content-Length: ' . filesize($r));
        header('X-Content-Type-Options: nosniff');
        readfile($r);
        return true;
    }
}
