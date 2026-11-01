<?php

namespace Classes;

/**
 * Límite de intentos de acceso (fuerza bruta). Se cuentan tres cosas a la vez:
 *
 *  - el par IP + correo (5 fallos / 15 min): frena el ataque dirigido a una cuenta;
 *  - el correo solo (10 fallos / 15 min): frena el ataque desde muchas IP contra la misma cuenta;
 *  - la IP sola (30 fallos / 15 min): frena el «password spraying» (muchas cuentas desde un equipo).
 *
 * Los contadores globales se guardan en la misma tabla con el comodín `*` (correo `*` = solo IP,
 * IP `*` = solo correo), así no hace falta una tabla nueva. Si la migración todavía no se aplicó,
 * el login sigue funcionando sin límite.
 */
class LoginLimite
{
    use AccesoDb;

    public const MAX_INTENTOS = 5;     // por par IP + correo
    public const MAX_POR_CORREO = 10;  // por correo, desde cualquier IP
    public const MAX_POR_IP = 30;      // por IP, contra cualquier correo
    public const VENTANA = 900;        // segundos en los que se cuentan los fallos (15 min)
    public const BLOQUEO = 900;        // segundos de bloqueo (15 min)

    public static function disponible(): bool
    {
        return (bool)self::filas("SHOW TABLES LIKE 'login_intentos'");
    }

    /** Segundos que faltan para poder volver a intentarlo (0 = puede intentarlo) */
    public static function espera(string $ip, string $email): int
    {
        if (!self::disponible()) return 0;
        [$ip, $email] = self::claves($ip, $email);
        $espera = 0;
        foreach (self::combinaciones($ip, $email) as [$i, $e]) {
            $fila = self::filas("SELECT bloqueado_hasta FROM login_intentos WHERE ip = '$i' AND email = '$e' LIMIT 1")[0] ?? null;
            $espera = max($espera, (int)($fila['bloqueado_hasta'] ?? 0) - time());
        }
        return max(0, $espera);
    }

    /** Cuenta un fallo en los tres contadores y bloquea los que lleguen a su máximo. Nunca interrumpe el login. */
    public static function fallo(string $ip, string $email): void
    {
        if (!self::disponible()) return;
        [$ip, $email] = self::claves($ip, $email);
        self::contar($ip, $email, self::MAX_INTENTOS);
        self::contar('*', $email, self::MAX_POR_CORREO);
        self::contar($ip, '*', self::MAX_POR_IP);
    }

    /** Acceso correcto: se limpia el contador del par y el del correo (el de la IP se conserva para no regalar intentos). */
    public static function exito(string $ip, string $email): void
    {
        if (!self::disponible()) return;
        [$ip, $email] = self::claves($ip, $email);
        try {
            self::db()->query("DELETE FROM login_intentos WHERE (ip = '$ip' AND email = '$email') OR (ip = '*' AND email = '$email')");
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

    /** Suma un fallo a un contador concreto y lo bloquea al llegar a $max */
    private static function contar(string $ip, string $email, int $max): void
    {
        $ahora = time();
        $desde = $ahora - self::VENTANA;
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

    /** Las tres filas que cuentan el intento: par, correo e IP */
    private static function combinaciones(string $ip, string $email): array
    {
        return [[$ip, $email], ['*', $email], [$ip, '*']];
    }

    /** @return array{0:string,1:string} valores escapados y normalizados para la clave única */
    private static function claves(string $ip, string $email): array
    {
        $email = mb_substr(mb_strtolower(trim($email), 'UTF-8'), 0, 100);
        $ip = mb_substr(trim($ip), 0, 45);
        return [self::esc($ip), self::esc($email)];
    }
}
