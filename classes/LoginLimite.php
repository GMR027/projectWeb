<?php

namespace Classes;

/**
 * Límite de intentos de acceso (fuerza bruta): 5 fallos por IP y correo en 15 minutos
 * bloquean ese par durante 15 minutos. Es una protección adicional al hash de contraseña;
 * si la migración todavía no se aplicó, el login sigue funcionando sin límite.
 */
class LoginLimite
{
    use AccesoDb;

    public const MAX_INTENTOS = 5;
    public const VENTANA = 900;   // segundos en los que se cuentan los fallos (15 min)
    public const BLOQUEO = 900;   // segundos de bloqueo (15 min)

    public static function disponible(): bool
    {
        return (bool)self::filas("SHOW TABLES LIKE 'login_intentos'");
    }

    /** Segundos que faltan para poder volver a intentarlo (0 = puede intentarlo) */
    public static function espera(string $ip, string $email): int
    {
        if (!self::disponible()) return 0;
        [$ip, $email] = self::claves($ip, $email);
        $fila = self::filas("SELECT bloqueado_hasta FROM login_intentos WHERE ip = '$ip' AND email = '$email' LIMIT 1")[0] ?? null;
        return max(0, (int)($fila['bloqueado_hasta'] ?? 0) - time());
    }

    /** Cuenta un fallo y bloquea al llegar al máximo. Nunca interrumpe el login si la tabla no existe. */
    public static function fallo(string $ip, string $email): void
    {
        if (!self::disponible()) return;
        [$ip, $email] = self::claves($ip, $email);
        $ahora = time();
        $desde = $ahora - self::VENTANA;
        $max = self::MAX_INTENTOS;
        $bloqueo = self::BLOQUEO;
        try {
            self::db()->query(
                "INSERT INTO login_intentos (ip, email, intentos, ultimo, bloqueado_hasta)
                 VALUES ('$ip', '$email', 1, $ahora, 0)
                 ON DUPLICATE KEY UPDATE
                    intentos = IF(ultimo < $desde, 1, intentos + 1),
                    bloqueado_hasta = IF(intentos >= $max, $ahora + $bloqueo, 0),
                    ultimo = $ahora"
            );
        } catch (\Throwable $e) {
            error_log('LoginLimite: ' . $e->getMessage());
        }
    }

    /** Acceso correcto: se limpia el contador de ese par IP/correo */
    public static function exito(string $ip, string $email): void
    {
        if (!self::disponible()) return;
        [$ip, $email] = self::claves($ip, $email);
        try {
            self::db()->query("DELETE FROM login_intentos WHERE ip = '$ip' AND email = '$email'");
        } catch (\Throwable $e) {
            error_log('LoginLimite: ' . $e->getMessage());
        }
    }

    /** Borra registros viejos (se llama de vez en cuando desde el propio login) */
    public static function limpiar(): void
    {
        if (!self::disponible()) return;
        try {
            $limite = time() - 86400;
            self::db()->query("DELETE FROM login_intentos WHERE ultimo < $limite");
        } catch (\Throwable $e) {
            error_log('LoginLimite: ' . $e->getMessage());
        }
    }

    /** @return array{0:string,1:string} valores escapados y normalizados para la clave única */
    private static function claves(string $ip, string $email): array
    {
        $email = mb_substr(mb_strtolower(trim($email), 'UTF-8'), 0, 100);
        $ip = mb_substr(trim($ip), 0, 45);
        return [self::esc($ip), self::esc($email)];
    }
}
