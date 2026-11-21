<?php

namespace Classes;

/**
 * APK de la app Android publicado desde la web.
 *
 * Los archivos viven fuera del docroot (storage/android) y se descargan solo con sesión iniciada.
 * El nombre debe ser projectweb-android-<versión>.apk para poder comparar versiones; el SHA-256,
 * tamaño y notas se guardan en un JSON al lado para no recalcularlos en cada visita.
 */
class ApkAndroid
{
    use AccesoDb;

    private const MAX_BYTES = 500 * 1024 * 1024;   // 500 MB: un APK normal ronda 30–80 MB
    private const MIN_SDK = 26;                    // Android 8.0

    public static function dir(): string
    {
        $d = dirname(__DIR__) . '/storage/android';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    public static function nombreValido(string $n): ?string
    {
        $n = basename(trim($n));
        return preg_match('/^projectweb-android-\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?\.apk$/i', $n) ? $n : null;
    }

    public static function versionDeNombre(string $n): string
    {
        return preg_match('/^projectweb-android-(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)\.apk$/i', basename($n), $m) ? $m[1] : '';
    }

    public static function versionCode(string $version): int
    {
        if (!preg_match('/(\d+)\.(\d+)\.(\d+)/', $version, $m)) return 0;
        // Mismo esquema que Tauri/Android: 1.2.3 → 1002003 (major*1,000,000 + minor*1,000 + patch).
        return (int)$m[1] * 1000000 + (int)$m[2] * 1000 + (int)$m[3];
    }

    public static function ruta(string $nombre): ?string
    {
        $n = self::nombreValido($nombre);
        $r = $n ? self::dir() . '/' . $n : null;
        return $r && is_file($r) ? $r : null;
    }

    /** @return array<int,array{archivo:string,version:string,version_code:int,tam:int,fecha:int,sha:string,notas:string,min_sdk:int}> */
    public static function listar(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.apk') ?: [] as $f) {
            $n = basename($f);
            if (!self::nombreValido($n)) continue;
            $meta = self::leerMeta($f);
            $out[] = [
                'archivo' => $n,
                'version' => (string)($meta['versionName'] ?? self::versionDeNombre($n)),
                'version_code' => (int)($meta['versionCode'] ?? self::versionCode(self::versionDeNombre($n))),
                'tam' => (int)filesize($f),
                'fecha' => (int)filemtime($f),
                'sha' => (string)($meta['sha256'] ?? self::sha($f)),
                'notas' => (string)($meta['notas'] ?? ''),
                'min_sdk' => (int)($meta['minSdk'] ?? self::MIN_SDK),
            ];
        }
        usort($out, fn($a, $b) => ($b['version_code'] <=> $a['version_code']) ?: ($b['fecha'] <=> $a['fecha']));
        return $out;
    }

    public static function ultima(): ?array
    {
        return self::listar()[0] ?? null;
    }

    public static function version(): string
    {
        return self::ultima()['version'] ?? '';
    }

    /**
     * Guarda o reemplaza un APK subido.
     * @return array{archivo:string,reemplazo:bool,cambio:bool,version:string}
     */
    public static function guardar(array $archivo, ?string $destino = null, string $notas = ''): array
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            throw new \RuntimeException(($archivo['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($archivo['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE
                ? 'El APK supera el límite de subida del servidor.' : 'No se recibió el APK.');
        }
        if ((int)($archivo['size'] ?? 0) > self::MAX_BYTES) throw new \RuntimeException('El APK supera el tamaño máximo permitido (500 MB).');
        $nombre = self::nombreValido($destino ?: (string)$archivo['name']);
        if (!$nombre) {
            throw new \RuntimeException('El nombre debe ser projectweb-android-<versión>.apk, por ejemplo projectweb-android-0.1.0.apk.');
        }
        if ($motivos = EscanerArchivos::revisar($archivo['tmp_name'], $nombre, true, ['apk'])) {
            throw new \RuntimeException(implode(' ', $motivos));
        }
        $final = self::dir() . '/' . $nombre;
        $reemplazo = is_file($final);
        $antes = $reemplazo ? self::sha($final) : '';
        $nuevo = (string)hash_file('sha256', $archivo['tmp_name']);
        $tmp = $final . '.subiendo';
        if (!move_uploaded_file($archivo['tmp_name'], $tmp)) throw new \RuntimeException('No se pudo guardar el APK (¿permisos del volumen?).');
        @chmod($tmp, 0664);
        if (!rename($tmp, $final)) { @unlink($tmp); throw new \RuntimeException('No se pudo publicar el APK.'); }
        $version = self::versionDeNombre($nombre);
        self::guardarMeta($final, [
            'versionName' => $version,
            'versionCode' => self::versionCode($version),
            'size' => (int)filesize($final),
            'sha256' => $nuevo,
            'minSdk' => self::MIN_SDK,
            'notas' => mb_substr(trim($notas), 0, 1000),
            'fecha' => date('c'),
            'archivo' => $nombre,
        ]);
        return ['archivo' => $nombre, 'reemplazo' => $reemplazo, 'cambio' => $antes !== $nuevo, 'version' => $version];
    }

    public static function eliminar(string $nombre): void
    {
        if ($r = self::ruta($nombre)) {
            @unlink($r);
            @unlink($r . '.json');
        }
    }

    public static function entregar(string $nombre): bool
    {
        $r = self::ruta($nombre);
        if (!$r) return false;
        @set_time_limit(0);
        while (ob_get_level()) ob_end_clean();
        $size = (int)filesize($r);
        $start = 0;
        $end = $size - 1;
        $parcial = false;
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', trim((string)$_SERVER['HTTP_RANGE']), $m)) {
            if ($m[1] !== '') $start = max(0, (int)$m[1]);
            if ($m[2] !== '') $end = min($size - 1, (int)$m[2]);
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                return true;
            }
            $parcial = true;
        }
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . basename($r) . '"');
        header('Accept-Ranges: bytes');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        if ($parcial) {
            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        }
        header('Content-Length: ' . ($end - $start + 1));
        $fp = fopen($r, 'rb');
        if (!$fp) return false;
        fseek($fp, $start);
        $resto = $end - $start + 1;
        while ($resto > 0 && !feof($fp)) {
            $bloque = fread($fp, min(1 << 16, $resto));
            if ($bloque === false || $bloque === '') break;
            echo $bloque;
            $resto -= strlen($bloque);
        }
        fclose($fp);
        return true;
    }

    private static function metaRuta(string $archivo): string
    {
        return $archivo . '.json';
    }

    /** @return array<string,mixed> */
    public static function leerMeta(string $archivo): array
    {
        $r = self::metaRuta($archivo);
        if (!is_file($r)) return [];
        try {
            $j = json_decode((string)file_get_contents($r), true);
            return is_array($j) ? $j : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function guardarMeta(string $archivo, array $meta): void
    {
        @file_put_contents(self::metaRuta($archivo), json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /** SHA-256 del APK; se guarda en el JSON para no recalcularlo en cada listado */
    public static function sha(string $archivo): string
    {
        $meta = self::leerMeta($archivo);
        if (!empty($meta['sha256']) && (int)($meta['size'] ?? 0) === (int)@filesize($archivo)) return (string)$meta['sha256'];
        $sha = (string)hash_file('sha256', $archivo);
        if (is_array($meta) && $meta !== []) {
            $meta['sha256'] = $sha;
            $meta['size'] = (int)@filesize($archivo);
            self::guardarMeta($archivo, $meta);
        }
        return $sha;
    }
}
