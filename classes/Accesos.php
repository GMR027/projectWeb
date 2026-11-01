<?php

namespace Classes;

/**
 * Registro de accesos (auditoría mínima de sesión): quién entró, desde qué IP y con qué resultado.
 * Se guarda en `accesos` (database/migracion_accesos.sql). Si la tabla no existe, no hace nada.
 */
class Accesos
{
    use AccesoDb;

    public const MAX_FILAS = 5000;   // se poda solo para no crecer sin fin

    public static function disponible(): bool
    {
        static $hay = null;
        return $hay ??= (bool)self::filas("SHOW TABLES LIKE 'accesos'");
    }

    /** Registra un intento de acceso. $resultado: ok | fallo | bloqueado | demo */
    public static function registrar(?int $usuarioId, string $email, string $resultado): void
    {
        if (!self::disponible()) return;
        try {
            $ip = self::esc(mb_substr(ip_cliente(), 0, 45));
            $email = self::esc(mb_substr(mb_strtolower(trim($email)), 0, 100));
            $ua = self::esc(mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255));
            $res = self::esc(preg_replace('/[^a-z]/', '', $resultado) ?: 'ok');
            $uid = $usuarioId !== null ? (int)$usuarioId : 'NULL';
            self::db()->query("INSERT INTO accesos (usuario_id, email, ip, agente, resultado) VALUES ($uid, '$email', '$ip', '$ua', '$res')");
            if (random_int(1, 200) === 1) self::podar();
        } catch (\Throwable $e) {
            error_log('Accesos: ' . $e->getMessage());
        }
    }

    /** Últimos accesos con el nombre del usuario (si sigue existiendo) */
    public static function recientes(int $limite = 50): array
    {
        if (!self::disponible()) return [];
        $limite = max(1, min(500, $limite));
        return self::filas("SELECT a.*, u.nombre, u.rol FROM accesos a LEFT JOIN usuarios u ON u.id = a.usuario_id ORDER BY a.id DESC LIMIT $limite");
    }

    /** Resumen para el panel: totales de las últimas 24 h */
    public static function resumen(): array
    {
        if (!self::disponible()) return ['ok' => 0, 'fallo' => 0, 'ips' => 0];
        $r = self::filas("SELECT SUM(resultado = 'ok') ok, SUM(resultado = 'fallo') fallo, COUNT(DISTINCT ip) ips FROM accesos WHERE creado >= NOW() - INTERVAL 1 DAY")[0] ?? [];
        return ['ok' => (int)($r['ok'] ?? 0), 'fallo' => (int)($r['fallo'] ?? 0), 'ips' => (int)($r['ips'] ?? 0)];
    }

    private static function podar(): void
    {
        try {
            self::db()->query('DELETE FROM accesos WHERE id <= (SELECT id FROM (SELECT id FROM accesos ORDER BY id DESC LIMIT 1 OFFSET ' . self::MAX_FILAS . ') t)');
        } catch (\Throwable $e) {
        }
    }
}
