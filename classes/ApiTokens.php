<?php

namespace Classes;

/**
 * Tokens de la API REST (Fase 5.1). El token completo («pw_» + 48 hex) se muestra una sola vez al crearlo; en la base solo vive su
 * SHA-256. Cada token es de un usuario y hereda lo que ese usuario puede ver o gestionar; además puede ser de solo lectura y limitarse
 * a un proyecto o a una fecha. Límite de 120 peticiones por minuto por token.
 */
class ApiTokens
{
    use AccesoDb;

    public const LIMITE_POR_MINUTO = 120;
    public const MAX_POR_USUARIO = 10;

    public static function disponible(): bool
    {
        return (bool)self::filas("SHOW TABLES LIKE 'api_tokens'");
    }

    public static function lista(int $uid): array
    {
        return self::filas("SELECT t.id, t.nombre, t.prefijo, t.escritura, t.proyecto_id, p.nombre AS proyecto, t.expira, t.revocado, t.usos, t.ultimo_uso, t.creado,
            (t.expira IS NOT NULL AND t.expira < CURDATE()) AS caducado FROM api_tokens t LEFT JOIN proyectos p ON p.id = t.proyecto_id WHERE t.usuario_id = $uid ORDER BY t.id DESC");
    }

    /** Crea un token. Devuelve [ok, token completo | mensaje] */
    public static function crear(int $uid, string $nombre, bool $escritura, int $proyectoId, int $dias): array
    {
        $nombre = mb_substr(trim(strip_tags($nombre)), 0, 100);
        if ($nombre === '') return [false, 'Ponle un nombre al token (para qué lo usas).'];
        if ((int)(self::filas("SELECT COUNT(*) n FROM api_tokens WHERE usuario_id = $uid AND revocado = 0")[0]['n'] ?? 0) >= self::MAX_POR_USUARIO) return [false, 'Máximo ' . self::MAX_POR_USUARIO . ' tokens activos. Revoca alguno.'];
        $token = 'pw_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $token);
        $prefijo = substr($token, 0, 10);
        $esc = $escritura ? 1 : 0;
        $pid = $proyectoId > 0 ? $proyectoId : null;
        $expira = $dias > 0 ? date('Y-m-d', strtotime("+$dias days")) : null;
        $st = self::db()->prepare('INSERT INTO api_tokens (usuario_id, nombre, token_hash, prefijo, escritura, proyecto_id, expira) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $st->bind_param('isssiis', $uid, $nombre, $hash, $prefijo, $esc, $pid, $expira);
        return $st->execute() ? [true, $token] : [false, 'No se pudo crear el token.'];
    }

    public static function revocar(int $uid, int $id): void
    {
        self::db()->query("UPDATE api_tokens SET revocado = 1 WHERE id = $id AND usuario_id = $uid");
    }

    /**
     * Comprueba un token. Devuelve ['token' => fila, 'usuario' => fila] o [null, 'motivo'] (códigos: sin_token, invalido, revocado, caducado, limite)
     * y cuenta el uso.
     */
    public static function verificar(string $token): array
    {
        if (!preg_match('/^pw_[0-9a-f]{48}$/', $token)) return [null, 'invalido'];
        $t = self::filas("SELECT * FROM api_tokens WHERE token_hash = '" . hash('sha256', $token) . "'")[0] ?? null;
        if (!$t) return [null, 'invalido'];
        if ((int)$t['revocado']) return [null, 'revocado'];
        if ($t['expira'] && $t['expira'] < date('Y-m-d')) return [null, 'caducado'];
        $u = self::filas('SELECT id, nombre, email, rol, confirmado FROM usuarios WHERE id = ' . (int)$t['usuario_id'])[0] ?? null;
        if (!$u) return [null, 'invalido'];
        $min = intdiv(time(), 60);
        $n = $t['ventana'] == $min ? (int)$t['ventana_n'] + 1 : 1;
        if ($n > self::LIMITE_POR_MINUTO) return [null, 'limite'];
        self::db()->query("UPDATE api_tokens SET usos = usos + 1, ultimo_uso = NOW(), ventana = $min, ventana_n = $n WHERE id = " . (int)$t['id']);
        return ['token' => $t, 'usuario' => $u];
    }
}
