<?php

namespace Classes;

/**
 * Sesiones y dispositivos activos por usuario.
 *
 * Cada inicio de sesión web crea una fila con un token aleatorio; en la sesión PHP solo se guarda el token
 * y en la base su SHA-256. Así el usuario puede ver desde Ajustes dónde entró, cuántos dispositivos tiene
 * abiertos y cerrar los que ya no usa. La app Android usará el mismo registro con tipo «android».
 *
 * Si la tabla no existe (migración sin aplicar) todo sigue funcionando sin límite ni bloqueo.
 */
class Sesiones
{
    use AccesoDb;

    public const WEB = 'web';
    public const ANDROID = 'android';
    public const TTL_WEB = 604800;          // 7 días (la sesión real caduca antes por inactividad)
    public const TTL_ANDROID = 7776000;     // 90 días para el token de dispositivo
    public const TOCAR_CADA = 300;          // actualizar «último acceso» como máximo cada 5 min

    public static function disponible(): bool
    {
        static $hay = null;
        return $hay ??= (bool)self::filas("SHOW TABLES LIKE 'sesiones_usuario'");
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Token de la sesión web actual (null si esta petición no tiene) */
    public static function tokenActual(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $t = (string)($_SESSION['sesion_id'] ?? '');
        return $t !== '' ? $t : null;
    }

    /** Crea la fila de la sesión web actual y devuelve el token que debe guardarse en $_SESSION */
    public static function crearWeb(int $usuarioId): ?string
    {
        if ($usuarioId <= 0 || !self::disponible()) return null;
        // La app de escritorio conserva la sesión hasta que el usuario salga; la web caduca antes por inactividad.
        $ttl = str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'ProjectWebDesktop') ? 31536000 : self::TTL_WEB;
        return self::crear($usuarioId, self::WEB, bin2hex(random_bytes(32)), $ttl);
    }

    /** Crea una sesión de app (el token lo emite el futuro login de la app) */
    public static function crearAndroid(int $usuarioId, string $token, array $datos = []): ?string
    {
        if ($usuarioId <= 0 || $token === '' || !self::disponible()) return null;
        return self::crear($usuarioId, self::ANDROID, $token, self::TTL_ANDROID, $datos);
    }

    private static function crear(int $usuarioId, string $tipo, string $token, int $ttl, array $datos = []): ?string
    {
        $hash = self::hash($token);
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        [$disp, $nav, $so] = self::etiqueta($ua);
        $ip = self::esc(mb_substr(ip_cliente(), 0, 45));
        $agente = self::esc(mb_substr($ua, 0, 255));
        $dispositivo = self::esc(mb_substr((string)($datos['dispositivo'] ?? $disp), 0, 120));
        $navegador = self::esc(mb_substr((string)($datos['navegador'] ?? $nav), 0, 120));
        $sistema = self::esc(mb_substr((string)($datos['sistema'] ?? $so), 0, 80));
        $appVersion = isset($datos['app_version']) ? "'" . self::esc(mb_substr((string)$datos['app_version'], 0, 30)) . "'" : 'NULL';
        $push = isset($datos['push_token']) ? "'" . self::esc(mb_substr((string)$datos['push_token'], 0, 255)) . "'" : 'NULL';
        $tipo = $tipo === self::ANDROID ? self::ANDROID : self::WEB;
        // NOW() de MySQL: la BD y PHP pueden estar en zonas horarias distintas.
        $expira = 'DATE_ADD(NOW(), INTERVAL ' . (int)$ttl . ' SECOND)';
        try {
            self::db()->query("INSERT INTO sesiones_usuario
                (usuario_id, token_hash, tipo, dispositivo, navegador, sistema, ip, agente, app_version, push_token, expira)
                VALUES ($usuarioId, '$hash', '$tipo', '$dispositivo', '$navegador', '$sistema', '$ip', '$agente', $appVersion, $push, $expira)");
        } catch (\Throwable $e) {
            error_log('Sesiones: no se pudo crear la sesión: ' . $e->getMessage());
            return null;
        }
        return $token;
    }

    /** Crea la fila si la sesión web no la tiene (sesiones anteriores a la migración) */
    public static function asegurarWeb(): ?string
    {
        if (!self::disponible()) return null;
        $actual = self::tokenActual();
        if ($actual && self::filaPorToken($actual)) return $actual;
        $nuevo = self::crearWeb((int)($_SESSION['id'] ?? 0));
        if ($nuevo) $_SESSION['sesion_id'] = $nuevo;
        return $nuevo;
    }

    /**
     * Comprueba la sesión actual y actualiza su último acceso.
     * Devuelve false si fue revocada o caducó (el llamador debe cerrar la sesión PHP).
     */
    public static function tocar(?string $token = null): bool
    {
        if (!self::disponible()) return true;
        $token = $token ?: self::asegurarWeb();
        if (!$token) return true;   // sin fila (migración recién aplicada, error de escritura): no bloquear
        $fila = self::filaPorToken($token);
        if (!$fila) {
            // Una sesión PHP válida sin fila (p. ej. tabla recreada): se vuelve a registrar.
            if ($token === self::tokenActual() && (int)($_SESSION['id'] ?? 0) > 0) {
                self::crearWeb((int)$_SESSION['id']);
            }
            return true;
        }
        if ((int)$fila['revocada'] === 1) return false;
        if (!empty($fila['expira']) && strtotime((string)$fila['expira']) <= time()) {
            self::db()->query("UPDATE sesiones_usuario SET revocada = 1, revocada_en = NOW() WHERE id = " . (int)$fila['id']);
            return false;
        }
        $ultimo = !empty($_SESSION['sesion_tocado']) ? (int)$_SESSION['sesion_tocado'] : 0;
        if (time() - $ultimo >= self::TOCAR_CADA) {
            self::db()->query("UPDATE sesiones_usuario SET ultimo_acceso = NOW() WHERE id = " . (int)$fila['id']);
            $_SESSION['sesion_tocado'] = time();
            if (random_int(1, 100) === 1) self::limpiar();
        }
        return true;
    }

    private static function filaPorToken(string $token): ?array
    {
        $hash = self::hash($token);
        return self::filas("SELECT * FROM sesiones_usuario WHERE token_hash = '$hash' LIMIT 1")[0] ?? null;
    }

    /**
     * Valida el token de una app Android y devuelve la sesión + el usuario.
     * El token completo nunca se guarda; se compara su SHA-256.
     * @return array{sesion:array,usuario:array}|null
     */
    public static function verificarToken(string $token): ?array
    {
        if ($token === '' || !self::disponible()) return null;
        $hash = self::hash($token);
        $f = self::filas("SELECT s.id AS sesion_id, s.tipo, s.dispositivo, s.navegador, s.sistema, s.ip,
                s.creado AS sesion_creado, s.ultimo_acceso, s.expira, s.revocada, s.app_version, s.push_token,
                u.id, u.nombre, u.email, u.rol, u.nivel_acceso, u.organizacion_id, u.rol_org, u.confirmado,
                u.debe_cambiar_password, u.herramientas, u.herramientas_edicion, u.puede_crear_proyectos
            FROM sesiones_usuario s JOIN usuarios u ON u.id = s.usuario_id
            WHERE s.token_hash = '$hash' AND s.tipo = 'android' AND s.revocada = 0
              AND (s.expira IS NULL OR s.expira > NOW()) AND u.confirmado = 1
            LIMIT 1")[0] ?? null;
        if (!$f) return null;
        self::db()->query("UPDATE sesiones_usuario SET ultimo_acceso = NOW() WHERE id = " . (int)$f['sesion_id']);
        $usuario = [];
        foreach (['id','nombre','email','rol','nivel_acceso','organizacion_id','rol_org','confirmado','debe_cambiar_password','herramientas','herramientas_edicion','puede_crear_proyectos'] as $k) $usuario[$k] = $f[$k] ?? null;
        $sesion = $f; unset($sesion['id'], $sesion['nombre'], $sesion['email'], $sesion['rol'], $sesion['nivel_acceso'], $sesion['organizacion_id'], $sesion['rol_org'], $sesion['confirmado'], $sesion['debe_cambiar_password'], $sesion['herramientas'], $sesion['herramientas_edicion'], $sesion['puede_crear_proyectos']);
        return ['sesion' => $sesion, 'usuario' => $usuario];
    }

    /** Cierra una sesión de app por su token (logout del dispositivo) */
    public static function cerrarToken(string $token): bool
    {
        if ($token === '' || !self::disponible()) return false;
        $hash = self::hash($token);
        self::db()->query("UPDATE sesiones_usuario SET revocada = 1, revocada_en = NOW(), revocada_por = usuario_id
            WHERE token_hash = '$hash' AND revocada = 0");
        return self::db()->affected_rows > 0;
    }

    private static function fila(int $id): ?array
    {
        return self::filas("SELECT * FROM sesiones_usuario WHERE id = " . (int)$id . " LIMIT 1")[0] ?? null;
    }

    /** Sesiones activas de un usuario. La actual se marca con `actual`. */
    public static function listar(int $usuarioId, ?string $tokenActual = null): array
    {
        if ($usuarioId <= 0 || !self::disponible()) return [];
        $hash = $tokenActual ? "'" . self::hash($tokenActual) . "'" : "''";
        $filas = self::filas("SELECT * FROM sesiones_usuario
            WHERE usuario_id = $usuarioId AND revocada = 0 AND (expira IS NULL OR expira > NOW())
            ORDER BY ultimo_acceso DESC, id DESC");
        return array_map(fn($f) => self::preparar($f, $hash), $filas);
    }

    /** Todas las sesiones activas, para el administrador (Sistema → Sesiones) */
    public static function listarTodas(array $filtros = [], int $limite = 300): array
    {
        if (!self::disponible()) return [];
        $where = ["s.revocada = 0", "(s.expira IS NULL OR s.expira > NOW())"];
        $q = trim((string)($filtros['q'] ?? ''));
        if ($q !== '') {
            $e = self::esc($q);
            $where[] = "(u.nombre LIKE '%$e%' OR u.email LIKE '%$e%' OR s.ip LIKE '%$e%' OR s.dispositivo LIKE '%$e%')";
        }
        if (!empty($filtros['usuario_id'])) $where[] = 's.usuario_id = ' . (int)$filtros['usuario_id'];
        if (($filtros['tipo'] ?? '') === self::ANDROID) $where[] = "s.tipo = 'android'";
        elseif (($filtros['tipo'] ?? '') === self::WEB) $where[] = "s.tipo = 'web'";
        $limite = max(1, min(1000, $limite));
        $filas = self::filas("SELECT s.*, u.nombre, u.email, u.rol
            FROM sesiones_usuario s JOIN usuarios u ON u.id = s.usuario_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.ultimo_acceso DESC, s.id DESC LIMIT $limite");
        return array_map(fn($f) => self::preparar($f) + ['nombre' => $f['nombre'] ?? '', 'email' => $f['email'] ?? '', 'rol' => $f['rol'] ?? ''], $filas);
    }

    public static function contar(int $usuarioId): int
    {
        if ($usuarioId <= 0 || !self::disponible()) return 0;
        $r = self::filas("SELECT COUNT(*) n FROM sesiones_usuario WHERE usuario_id = $usuarioId AND revocada = 0 AND (expira IS NULL OR expira > NOW())")[0] ?? [];
        return (int)($r['n'] ?? 0);
    }

    /** Total de sesiones activas de toda la plataforma (panel del administrador) */
    public static function contarTodas(): int
    {
        if (!self::disponible()) return 0;
        $r = self::filas("SELECT COUNT(*) n FROM sesiones_usuario WHERE revocada = 0 AND (expira IS NULL OR expira > NOW())")[0] ?? [];
        return (int)($r['n'] ?? 0);
    }

    private static function preparar(array $f, string $hashActual = "''"): array
    {
        $f['actual'] = ($f['token_hash'] ?? '') !== '' && hash_equals((string)$f['token_hash'], trim($hashActual, "'"));
        $f['tipo_texto'] = ($f['tipo'] ?? '') === self::ANDROID ? 'App Android' : 'Navegador web';
        $f['icono'] = ($f['tipo'] ?? '') === self::ANDROID ? 'fa-mobile-screen' : 'fa-desktop';
        if (trim((string)($f['dispositivo'] ?? '')) === '') $f['dispositivo'] = 'Dispositivo desconocido';
        if (trim((string)($f['ip'] ?? '')) === '') $f['ip'] = 'IP no disponible';
        return $f;
    }

    /** Cierra una sesión propia */
    public static function cerrar(int $usuarioId, int $id): bool
    {
        if ($usuarioId <= 0 || $id <= 0 || !self::disponible()) return false;
        self::db()->query("UPDATE sesiones_usuario SET revocada = 1, revocada_en = NOW(), revocada_por = $usuarioId
            WHERE id = " . (int)$id . " AND usuario_id = $usuarioId AND revocada = 0");
        return self::db()->affected_rows > 0;
    }

    /** Cierra todas las sesiones propias menos la actual */
    public static function cerrarOtras(int $usuarioId, ?string $tokenActual = null): int
    {
        if ($usuarioId <= 0 || !self::disponible()) return 0;
        $hash = $tokenActual ? "'" . self::hash($tokenActual) . "'" : "''";
        self::db()->query("UPDATE sesiones_usuario SET revocada = 1, revocada_en = NOW(), revocada_por = $usuarioId
            WHERE usuario_id = $usuarioId AND revocada = 0 AND token_hash <> $hash");
        return max(0, (int)self::db()->affected_rows);
    }

    /** Cierra una sesión desde el panel del administrador */
    public static function cerrarAdmin(int $id, int $adminId): bool
    {
        if ($id <= 0 || $adminId <= 0 || !self::disponible()) return false;
        self::db()->query("UPDATE sesiones_usuario SET revocada = 1, revocada_en = NOW(), revocada_por = $adminId
            WHERE id = " . (int)$id . " AND revocada = 0");
        return self::db()->affected_rows > 0;
    }

    /** Cierra la sesión web actual (logout) */
    public static function revocarActual(): void
    {
        $token = self::tokenActual();
        if (!$token || !self::disponible()) return;
        $hash = self::hash($token);
        self::db()->query("UPDATE sesiones_usuario SET revocada = 1, revocada_en = NOW(), revocada_por = " . (int)($_SESSION['id'] ?? 0) . "
            WHERE token_hash = '$hash' AND revocada = 0");
    }

    /** Cierra todas las sesiones de un usuario (p. ej. al cambiar la contraseña), salvo la actual */
    public static function revocarTodas(int $usuarioId, ?string $excepto = null): int
    {
        return self::cerrarOtras($usuarioId, $excepto);
    }

    /** Limpia filas viejas; se llama de vez en cuando, no en cada petición */
    public static function limpiar(): void
    {
        if (!self::disponible()) return;
        try {
            self::db()->query("DELETE FROM sesiones_usuario
                WHERE (revocada = 1 AND revocada_en IS NOT NULL AND revocada_en < NOW() - INTERVAL 90 DAY)
                   OR (expira IS NOT NULL AND expira < NOW() - INTERVAL 90 DAY)");
        } catch (\Throwable $e) {
        }
    }

    /**
     * Etiqueta legible del navegador y sistema a partir del User-Agent.
     * @return array{0:string,1:string,2:string} [dispositivo, navegador, sistema]
     */
    public static function etiqueta(string $ua): array
    {
        $u = strtolower($ua);
        if ($u === '') return ['Dispositivo desconocido', 'Desconocido', 'Desconocido'];
        if (str_contains($u, 'projectwebandroid')) return ['App Project Web en Android', 'App Project Web', 'Android'];
        $so = 'Desconocido';
        if (str_contains($u, 'android')) $so = 'Android';
        elseif (str_contains($u, 'iphone') || str_contains($u, 'ipad') || str_contains($u, 'ipod')) $so = 'iOS';
        elseif (str_contains($u, 'windows')) $so = 'Windows';
        elseif (str_contains($u, 'cros')) $so = 'ChromeOS';
        elseif (str_contains($u, 'mac os') || str_contains($u, 'macintosh')) $so = 'macOS';
        elseif (str_contains($u, 'linux')) $so = 'Linux';

        $nav = 'Navegador';
        if (str_contains($u, 'edg/') || str_contains($u, 'edgios/')) $nav = 'Edge';
        elseif (str_contains($u, 'opr/') || str_contains($u, 'opera')) $nav = 'Opera';
        elseif (str_contains($u, 'samsungbrowser')) $nav = 'Samsung Internet';
        elseif (str_contains($u, 'firefox') || str_contains($u, 'fxios')) $nav = 'Firefox';
        elseif (str_contains($u, 'chrome') || str_contains($u, 'crios')) $nav = 'Chrome';
        elseif (str_contains($u, 'safari')) $nav = 'Safari';
        elseif (str_contains($u, 'msie') || str_contains($u, 'trident')) $nav = 'Internet Explorer';

        $disp = ($nav === 'Navegador' && $so === 'Desconocido') ? 'Dispositivo desconocido' : "$nav en $so";
        return [$disp, $nav, $so];
    }
}
