<?php

namespace Classes;

/**
 * Webhooks (Fase 5.1): cuando algo cambia en un proyecto se avisa por HTTP a las URL que el proyecto tenga registradas.
 * - Cada aviso va firmado: cabecera X-ProjectWeb-Signature: sha256=<HMAC-SHA256 del cuerpo con el secreto del webhook>.
 * - Se envían al terminar la petición (la respuesta al usuario no espera) con tiempo máximo de 4 s, sin seguir redirecciones.
 * - Por seguridad (SSRF) solo se llama a direcciones públicas: la IP se resuelve y se fija en la conexión; las privadas, locales
 *   y de metadatos se rechazan, salvo que el servidor defina WEBHOOKS_PERMITIR_PRIVADOS=1 (pruebas en red interna).
 * - Diez fallos seguidos desactivan el webhook; cada intento queda en webhook_entregas (con su carga, para reintentar).
 */
class Webhooks
{
    use AccesoDb;

    public const EVENTOS = [
        'actividad.creada' => 'Se creó una actividad', 'actividad.actualizada' => 'Cambió una actividad (avance, fechas…)', 'actividad.eliminada' => 'Se eliminó una actividad',
        'restriccion.creada' => 'Nueva restricción', 'restriccion.actualizada' => 'Cambió una restricción (p. ej. se resolvió)',
        'bitacora.creada' => 'Nuevo registro en la bitácora', 'reporte.creado' => 'Nuevo reporte diario',
        'submittal.creado' => 'Nuevo paquete de submittals', 'submittal.actualizado' => 'Cambió un submittal (p. ej. su estatus)',
        'punch.creado' => 'Nuevo pendiente del punch list', 'punch.actualizado' => 'Cambió un pendiente', 'punch.estado' => 'Cambió el estado de un pendiente',
        'rfi.creado' => 'Nuevo RFI', 'rfi.actualizado' => 'Cambió un RFI', 'rfi.estado' => 'Cambió el estado de un RFI',
    ];
    /** Modelos que avisan solos desde ActiveRecord: tabla => [acción => evento] */
    public const EVENTO_DE = [
        'actividades' => ['creada' => 'actividad.creada', 'actualizada' => 'actividad.actualizada', 'eliminada' => 'actividad.eliminada'],
        'restricciones' => ['creada' => 'restriccion.creada', 'actualizada' => 'restriccion.actualizada'],
        'bitacora' => ['creada' => 'bitacora.creada'],
        'reportes_diarios' => ['creada' => 'reporte.creado'],
        'submittals' => ['creada' => 'submittal.creado', 'actualizada' => 'submittal.actualizado'],
    ];
    private const MAX_POR_PETICION = 50;
    private const FALLOS_PARA_APAGAR = 10;

    private static array $cola = [];
    private static bool $programado = false;

    // ---------- administración ----------

    public static function disponible(): bool
    {
        return (bool)self::filas("SHOW TABLES LIKE 'webhooks'");
    }

    public static function lista(int $pid): array
    {
        return self::filas("SELECT w.*, (SELECT COUNT(*) FROM webhook_entregas e WHERE e.webhook_id = w.id) AS entregas,
            (SELECT MAX(e.creado) FROM webhook_entregas e WHERE e.webhook_id = w.id) AS ultima FROM webhooks w WHERE w.proyecto_id = $pid ORDER BY w.id");
    }

    public static function obtener(int $pid, int $id): ?array
    {
        return self::filas("SELECT * FROM webhooks WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    public static function entregas(int $pid, int $id, int $limite = 15): array
    {
        if (!self::obtener($pid, $id)) return [];
        return self::filas("SELECT id, evento, codigo_http, ok, ms, error, creado FROM webhook_entregas WHERE webhook_id = $id ORDER BY id DESC LIMIT " . max(1, min(100, $limite)));
    }

    /** Eventos de un texto «a, b»: solo los conocidos; vacío o * = todos */
    public static function normalizarEventos($entrada): string
    {
        $l = is_array($entrada) ? $entrada : preg_split('/[\s,]+/', (string)$entrada, -1, PREG_SPLIT_NO_EMPTY);
        $l = array_values(array_unique(array_filter($l, fn($e) => isset(self::EVENTOS[$e]))));
        return $l ? implode(',', $l) : '*';
    }

    /** Crea un webhook. Devuelve [ok, mensaje | id] */
    public static function crear(int $pid, string $nombre, string $url, $eventos, string $usuario): array
    {
        $url = trim($url);
        if (($m = self::validarUrl($url)) !== null) return [false, $m];
        if (count(self::lista($pid)) >= 10) return [false, 'Máximo 10 webhooks por proyecto.'];
        $nombre = mb_substr(trim(strip_tags($nombre)), 0, 100);
        $secreto = bin2hex(random_bytes(20));
        $ev = self::normalizarEventos($eventos);
        $usuario = mb_substr($usuario, 0, 150);
        $st = self::db()->prepare('INSERT INTO webhooks (proyecto_id, nombre, url, secreto, eventos, creado_por) VALUES (?, ?, ?, ?, ?, ?)');
        $st->bind_param('isssss', $pid, $nombre, $url, $secreto, $ev, $usuario);
        return $st->execute() ? [true, (int)self::db()->insert_id] : [false, 'No se pudo guardar.'];
    }

    public static function cambiarActivo(int $pid, int $id, bool $activo): void
    {
        $a = $activo ? 1 : 0;
        self::db()->query("UPDATE webhooks SET activo = $a, fallos_seguidos = 0 WHERE id = $id AND proyecto_id = $pid");
    }

    public static function eliminar(int $pid, int $id): void
    {
        self::db()->query("DELETE FROM webhooks WHERE id = $id AND proyecto_id = $pid");
    }

    // ---------- seguridad de la URL ----------

    /** null si la URL es válida; si no, el motivo */
    public static function validarUrl(string $url): ?string
    {
        $p = parse_url($url);
        if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) return 'La URL debe empezar con http:// o https://';
        if (isset($p['user']) || isset($p['pass'])) return 'La URL no puede llevar usuario ni contraseña.';
        if (strlen($url) > 500) return 'La URL es demasiado larga.';
        if (self::resolver($p['host']) === null) return 'La dirección no se puede usar: debe ser pública (no local ni de red interna).';
        return null;
    }

    private static function esPublica(string $ip): bool
    {
        if (getenv('WEBHOOKS_PERMITIR_PRIVADOS') === '1') return true;
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** IP pública del host (o null si no resuelve o es privada) */
    private static function resolver(string $host): ?string
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) return self::esPublica($host) ? $host : null;
        $ips = array_column(@dns_get_record($host, DNS_A) ?: [], 'ip');
        if (!$ips) { $ip = gethostbyname($host); if ($ip !== $host) $ips = [$ip]; }
        if (!$ips) return null;
        foreach ($ips as $ip) if (!self::esPublica($ip)) return null;   // basta una privada para rechazar
        return $ips[0];
    }

    // ---------- emitir ----------

    private static function proyectosConWebhook(): array
    {
        static $c = null;
        return $c ??= array_flip(array_map('intval', array_column(self::filas('SELECT DISTINCT proyecto_id FROM webhooks WHERE activo = 1'), 'proyecto_id')));
    }

    /** Pone un evento en cola para los webhooks del proyecto que lo pidan; se envía al terminar la petición */
    public static function emitir(int $pid, string $evento, array $datos): void
    {
        if (!isset(self::proyectosConWebhook()[$pid]) || !isset(self::EVENTOS[$evento])) return;
        $nombre = self::filas("SELECT nombre FROM proyectos WHERE id = $pid")[0]['nombre'] ?? '';
        foreach (self::filas("SELECT * FROM webhooks WHERE proyecto_id = $pid AND activo = 1") as $w) {
            if ($w['eventos'] !== '*' && !in_array($evento, explode(',', $w['eventos']), true)) continue;
            if ((self::$cola[$w['id']]['n'] ?? 0) >= self::MAX_POR_PETICION) continue;
            $carga = json_encode(['id' => bin2hex(random_bytes(8)), 'evento' => $evento, 'fecha' => date('c'), 'proyecto' => ['id' => $pid, 'nombre' => $nombre], 'datos' => $datos], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            self::$cola[$w['id']]['w'] = $w;
            self::$cola[$w['id']]['n'] = (self::$cola[$w['id']]['n'] ?? 0) + 1;
            self::$cola[$w['id']]['envios'][] = [$evento, $carga];
        }
        if (self::$cola && !self::$programado) {
            self::$programado = true;
            register_shutdown_function([self::class, 'vaciar']);
        }
    }

    /** Envía lo que hay en cola (se llama al terminar la petición) */
    public static function vaciar(): void
    {
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        $cola = self::$cola;
        self::$cola = [];
        foreach ($cola as $g) foreach ($g['envios'] as [$evento, $carga]) {
            try { self::entregar($g['w'], $evento, $carga); } catch (\Throwable $e) { error_log('Webhook: ' . $e->getMessage()); }
        }
    }

    /** Un envío firmado. Registra el resultado y devuelve [ok, código, mensaje] */
    public static function entregar(array $w, string $evento, string $carga): array
    {
        $t0 = microtime(true);
        $p = parse_url($w['url']);
        $host = $p['host'] ?? '';
        $ip = self::resolver($host);
        $codigo = 0; $error = '';
        if ($ip === null) {
            $error = 'La dirección no es pública o no resuelve.';
        } else {
            $puerto = $p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80);
            $ch = curl_init($w['url']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $carga, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_MAXFILESIZE => 65536,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE => ["$host:$puerto:$ip"],   // se conecta a la IP ya comprobada (evita cambiar de DNS entre la revisión y el envío)
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json', 'User-Agent: ProjectWeb-Webhook/1', 'X-ProjectWeb-Event: ' . $evento,
                    'X-ProjectWeb-Delivery: ' . (json_decode($carga, true)['id'] ?? ''), 'X-ProjectWeb-Signature: sha256=' . hash_hmac('sha256', $carga, $w['secreto']),
                ],
            ]);
            curl_exec($ch);
            $codigo = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if (curl_errno($ch)) $error = mb_substr(curl_error($ch), 0, 250);
            curl_close($ch);
            if (!$error && ($codigo < 200 || $codigo >= 300)) $error = 'Respondió HTTP ' . $codigo;
        }
        $ok = $error === '' ? 1 : 0;
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $wid = (int)$w['id'];
        $st = self::db()->prepare('INSERT INTO webhook_entregas (webhook_id, evento, codigo_http, ok, ms, error, carga) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $st->bind_param('isiiiss', $wid, $evento, $codigo, $ok, $ms, $error, $carga);
        $st->execute();
        self::db()->query("DELETE FROM webhook_entregas WHERE webhook_id = $wid AND id < (SELECT m FROM (SELECT MAX(id) - 100 AS m FROM webhook_entregas WHERE webhook_id = $wid) x)");   // se conservan las últimas 100
        if ($ok) self::db()->query("UPDATE webhooks SET fallos_seguidos = 0 WHERE id = $wid");
        else self::db()->query("UPDATE webhooks SET fallos_seguidos = fallos_seguidos + 1, activo = IF(fallos_seguidos + 1 >= " . self::FALLOS_PARA_APAGAR . ", 0, activo) WHERE id = $wid");
        return [(bool)$ok, $codigo, $error];
    }

    /** Envía un evento de prueba (ping) y devuelve [ok, código, mensaje] */
    public static function probar(int $pid, int $id): array
    {
        $w = self::obtener($pid, $id);
        if (!$w) return [false, 0, 'El webhook no existe.'];
        $nombre = self::filas("SELECT nombre FROM proyectos WHERE id = $pid")[0]['nombre'] ?? '';
        $carga = json_encode(['id' => bin2hex(random_bytes(8)), 'evento' => 'ping', 'fecha' => date('c'), 'proyecto' => ['id' => $pid, 'nombre' => $nombre], 'datos' => ['mensaje' => 'Prueba de ProjectWeb']], JSON_UNESCAPED_UNICODE);
        return self::entregar($w, 'ping', $carga);
    }

    public static function reintentar(int $pid, int $entregaId): array
    {
        $e = self::filas("SELECT e.evento, e.carga, e.webhook_id FROM webhook_entregas e JOIN webhooks w ON w.id = e.webhook_id WHERE e.id = $entregaId AND w.proyecto_id = $pid")[0] ?? null;
        $w = $e ? self::obtener($pid, (int)$e['webhook_id']) : null;
        if (!$w || !$e['carga']) return [false, 0, 'La entrega no existe.'];
        return self::entregar($w, $e['evento'], $e['carga']);
    }

    // ---------- ganchos de los modelos ----------

    /** Proyecto al que pertenece un registro de una de las tablas que avisan */
    private static function proyectoDe(string $tabla, array $fila): int
    {
        switch ($tabla) {
            case 'actividades': return (int)(self::filas('SELECT proyecto_id FROM partidas WHERE id = ' . (int)($fila['partida_id'] ?? 0))[0]['proyecto_id'] ?? 0);
            case 'restricciones': return (int)(self::filas('SELECT p.proyecto_id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = ' . (int)($fila['actividad_id'] ?? 0))[0]['proyecto_id'] ?? 0);
            default: return (int)($fila['proyecto_id'] ?? 0);
        }
    }

    /** Lo llama ActiveRecord al crear, actualizar o eliminar en las tablas de EVENTO_DE */
    public static function desdeTabla(string $tabla, string $accion, int $id, array $fila): void
    {
        $evento = self::EVENTO_DE[$tabla][$accion] ?? null;
        if ($evento === null || !self::proyectosConWebhook()) return;
        $pid = self::proyectoDe($tabla, $fila);
        if ($pid) self::emitir($pid, $evento, ['id' => $id] + self::limpiar($fila));
    }

    /** Solo valores simples y cortos (sin binarios ni textos enormes) */
    private static function limpiar(array $fila): array
    {
        $o = [];
        foreach ($fila as $k => $v) {
            if (is_scalar($v) || $v === null) $o[$k] = is_string($v) ? mb_substr($v, 0, 500) : $v;
        }
        return $o;
    }
}
