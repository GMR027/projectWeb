<?php

namespace Classes;

/**
 * Puente de sesión app → web.
 *
 * La app Android abre las herramientas grandes en el navegador del teléfono. Para no pedir la
 * contraseña otra vez, pide un código de un solo uso (120 s) y abre `/acceso-app?c=…`, que inicia
 * la sesión web del mismo usuario y redirige a la ruta pedida. El código se guarda solo como
 * SHA-256 (`app_enlaces`) y se marca usado en la misma consulta que lo valida.
 */
class EnlacesApp
{
    use AccesoDb;

    public const TTL = 120;   // segundos

    public static function disponible(): bool
    {
        static $hay = null;
        if ($hay !== null) return $hay;
        try {
            $hay = (bool)self::db()->query("SELECT 1 FROM app_enlaces LIMIT 1");
        } catch (\Throwable $e) {
            $hay = false;
        }
        return $hay;
    }

    /** Crea el código (texto plano) para ese usuario y ruta. Null si la tabla no está migrada. */
    public static function crear(int $usuarioId, string $ruta): ?string
    {
        if ($usuarioId <= 0 || !self::disponible()) return null;
        $codigo = bin2hex(random_bytes(24));
        $hash = hash('sha256', $codigo);
        $ruta = self::rutaSegura($ruta);
        // NOW() de MySQL, no la hora de PHP: la BD puede estar en otra zona y el código caduca en 120 s.
        $expira = 'DATE_ADD(NOW(), INTERVAL ' . (int)self::TTL . ' SECOND)';
        try {
            // Limpieza barata: lo usado o caducado de hace más de un día ya no sirve.
            self::db()->query("DELETE FROM app_enlaces WHERE expira < NOW() - INTERVAL 1 DAY OR (usado = 1 AND usado_en < NOW() - INTERVAL 1 DAY)");
            self::db()->query("INSERT INTO app_enlaces (usuario_id, codigo_hash, ruta, expira)
                VALUES ($usuarioId, '$hash', '" . self::esc($ruta) . "', $expira)");
        } catch (\Throwable $e) {
            error_log('EnlacesApp: no se pudo crear el acceso: ' . $e->getMessage());
            return null;
        }
        return $codigo;
    }

    /** Canjea el código una sola vez. Devuelve la fila (usuario_id, ruta) o null. */
    public static function canjear(string $codigo): ?array
    {
        if (!preg_match('/^[0-9a-f]{48}$/', $codigo) || !self::disponible()) return null;
        $hash = hash('sha256', $codigo);
        try {
            self::db()->query("UPDATE app_enlaces SET usado = 1, usado_en = NOW()
                WHERE codigo_hash = '$hash' AND usado = 0 AND expira > NOW()");
            if ((int)self::db()->affected_rows !== 1) return null;
        } catch (\Throwable $e) {
            return null;
        }
        return self::filas("SELECT * FROM app_enlaces WHERE codigo_hash = '$hash' LIMIT 1")[0] ?? null;
    }

    public static function url(string $codigo): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $base . '/acceso-app?c=' . urlencode($codigo);
    }

    /** Solo rutas internas normales: nada de //host, esquemas, saltos de línea ni /logout. */
    public static function rutaSegura(string $ruta): string
    {
        $ruta = trim($ruta);
        if ($ruta === '' || $ruta[0] !== '/' || str_starts_with($ruta, '//')) return '/dashboard';
        if (strlen($ruta) > 255) return '/dashboard';
        if (preg_match('/[\x00-\x20\\\\]/', $ruta)) return '/dashboard';
        if (!preg_match('#^/[A-Za-z0-9._~!$&\'()*+,;=:@/?%\-\[\]]*$#', $ruta)) return '/dashboard';
        if (str_starts_with($ruta, '/logout') || str_starts_with($ruta, '/acceso-app')) return '/dashboard';
        return $ruta;
    }
}
