<?php

namespace Classes;

use Model\Proyecto;
use Model\ProyectoAcceso;

/**
 * Bot de Telegram de la plataforma (un solo bot para todos; el token lo pone el admin desde Integraciones y se guarda
 * CIFRADO en la tabla `telegram_config` —AES-256-GCM con la clave de TELEGRAM_KEY o storage/secretos—, y el del entorno
 * TELEGRAM_BOT_TOKEN queda solo como respaldo. En pantalla nunca se vuelve a mostrar completo).
 * - Vinculación: cada persona pulsa «Conectar mi Telegram» en Integraciones y se abre el bot con un código de un solo uso (15 min).
 *   Un grupo se vincula igual (enlace «startgroup») y queda como destino de avisos de UN proyecto.
 * - Avisos: Webhooks::emitir() también llama a aviso(); se envían al terminar la petición a los destinos del proyecto que pidieron ese evento.
 * - Entrada: Telegram llama a /api/telegram/webhook (cabecera con secreto derivado del token) o, sin dirección pública, bin/telegram.php --sondeo.
 *   La conversación (avance, fotos, notas) está en TelegramBot.
 * - Permisos: iguales a los de la web (admin: todo; cliente: sus proyectos; visitante: nunca entra al bot).
 */
class Telegram
{
    use AccesoDb;

    public const EVENTOS = [
        'rfi.creado' => 'Nuevo RFI', 'rfi.estado' => 'Cambia el estado de un RFI',
        'punch.creado' => 'Nuevo pendiente del punch list', 'punch.estado' => 'Cambia el estado de un pendiente',
        'restriccion.creada' => 'Nueva restricción en una actividad', 'submittal.actualizado' => 'Cambia un submittal',
        'bitacora.creada' => 'Registro de la bitácora con importancia alta o crítica', 'clima.alerta' => 'Mañana habrá lluvia o viento fuerte',
    ];
    public const INICIALES = ['rfi.creado', 'punch.creado', 'restriccion.creada', 'clima.alerta'];
    private const MAX_FALLOS = 5;
    private const MAX_COLA = 30;
    public const VIGENCIA_CODIGO = 900;

    /** Para pruebas: función (método, parámetros) → arreglo de respuesta; reemplaza las llamadas HTTP a Telegram */
    public static $http = null;

    private static array $cola = [];
    private static bool $programado = false;
    /** telegram_config ya leída y descifrada: null = sin leer, [] = sin fila (se cachea para no repetir la consulta) */
    private static ?array $cfg = null;
    private static ?bool $cfgTabla = null;
    private static ?string $clave = null;

    // ---------- configuración ----------

    public static function env(string $clave): string
    {
        $v = $_ENV[$clave] ?? $_SERVER[$clave] ?? getenv($clave);
        return is_string($v) ? trim($v) : '';
    }

    public static function token(): string
    {
        $t = self::cfgFila()['token'] ?? '';
        return $t !== '' ? $t : self::env('TELEGRAM_BOT_TOKEN');
    }

    public static function configurado(): bool
    {
        return self::token() !== '';
    }

    /** Tabla de configuración creada (migración telegram_config) */
    public static function disponibleConfig(): bool
    {
        if (self::$cfgTabla === null) self::$cfgTabla = isset($GLOBALS['db']) && (bool)self::filas("SHOW TABLES LIKE 'telegram_config'");
        return self::$cfgTabla;
    }

    // ---------- token en la base (cifrado) ----------

    /** Últimos4 caracteres del token, para mostrarlo en pantalla sin exponerlo */
    public static function mascara(string $token): string
    {
        return $token !== '' ? '•••• ' . mb_substr($token, -4) : '';
    }

    /**
     * Clave de32 bytes para cifrar el token: `TELEGRAM_KEY` (hex de64, base64 o cualquier texto) o en su
     * defecto `storage/secretos/telegram.key`, que se crea sola la primera vez con permisos0600 (fuera de
     * public y del repositorio). Vacío si no se puede crear: en ese caso no se guarda nada en claro.
     */
    public static function clave(): string
    {
        if (self::$clave !== null) return self::$clave;
        $k = trim(self::env('TELEGRAM_KEY'));
        if ($k !== '') return self::$clave = preg_match('/^[0-9a-f]{64}$/i', $k) ? strtolower($k) : hash('sha256', $k);
        $dir = __DIR__ . '/../storage/secretos';
        $ruta = $dir . '/telegram.key';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        if (is_file($ruta)) {
            $v = trim((string)@file_get_contents($ruta));
            if (preg_match('/^[0-9a-f]{64}$/i', $v)) return self::$clave = strtolower($v);
        }
        $n = bin2hex(random_bytes(32));
        if (@file_put_contents($ruta, $n, LOCK_EX) === false) return self::$clave = '';
        @chmod($ruta, 0600);
        return self::$clave = $n;
    }

    /** AES-256-GCM. Vacío si no hay clave: así nunca se guarda el token en claro por accidente */
    public static function cifrar(string $texto): string
    {
        $k = self::clave();
        if ($k === '' || !function_exists('openssl_encrypt')) return '';
        $iv = random_bytes(12);
        $tag = '';
        $c = openssl_encrypt($texto, 'aes-256-gcm', hex2bin($k), OPENSSL_RAW_DATA, $iv, $tag);
        return $c === false ? '' : $iv . $tag . $c;
    }

    /** Vacío si la clave cambió o el dato está dañado (no lanza error) */
    public static function descifrar(string $blob): string
    {
        $k = self::clave();
        if ($k === '' || strlen($blob) < 29 || !function_exists('openssl_decrypt')) return '';
        $d = openssl_decrypt(substr($blob, 28), 'aes-256-gcm', hex2bin($k), OPENSSL_RAW_DATA, substr($blob, 0, 12), substr($blob, 12, 16));
        return $d === false ? '' : $d;
    }

    /** Fila de telegram_config ya leída y descifrada (se cachea; [] si no hay) */
    private static function cfgFila(): array
    {
        if (self::$cfg !== null) return self::$cfg;
        if (!self::disponibleConfig()) return self::$cfg = [];
        $f = self::filas('SELECT token_cifrado, bot_username, actualizado, actualizado_por FROM telegram_config WHERE id = 1')[0] ?? [];
        if (!$f) return self::$cfg = [];
        $f['token'] = self::descifrar((string)($f['token_cifrado'] ?? ''));
        unset($f['token_cifrado']);
        return self::$cfg = $f;
    }

    /**
     * Qué se sabe del token para la pantalla: de dónde sale (base o entorno), la máscara, el @usuario y
     * cuándo/quién lo guardó. `ilegible` = hay fila pero ya no se puede descifrar (clave perdida o cambiada).
     */
    public static function configGuardada(): array
    {
        $f = self::cfgFila();
        $deBD = (string)($f['token'] ?? '');
        $deEnv = self::env('TELEGRAM_BOT_TOKEN');
        $efectivo = $deBD !== '' ? $deBD : $deEnv;
        return ['guardado' => $deBD !== '', 'ilegible' => ($f !== [] && $deBD === ''),
            'origen' => $deBD !== '' ? 'base' : ($deEnv !== '' ? 'entorno' : ''), 'mascara' => self::mascara($efectivo),
            'username' => (string)($f['bot_username'] ?? ''), 'actualizado' => (string)($f['actualizado'] ?? ''),
            'actualizado_por' => (string)($f['actualizado_por'] ?? '')];
    }

    /**
     * Guarda el token cifrado en la base (solo admin, desde Integraciones). Antes de guardarlo lo comprueba
     * contra Telegram con getMe, así un token mal pegado no deja al bot «configurado» a medias.
     * @return array{0: bool, 1: string} [ok, mensaje]
     */
    public static function guardarToken(string $token, string $quien): array
    {
        $token = trim($token);
        if (!self::disponibleConfig()) return [false, 'Falta aplicar la migración database/migracion_telegram_config.sql.'];
        if (!preg_match('/^\d{5,}:[A-Za-z0-9_-]{20,}$/', $token)) return [false, 'Ese token no tiene el formato que da @BotFather (números, dos puntos y el resto).'];
        $cif = self::cifrar($token);
        if ($cif === '') return [false, 'No se pudo cifrar el token: faltan permisos de escritura en storage/secretos o la extensión openssl.'];
        $r = self::consultar($token, 'getMe', [], 6);
        if (empty($r['ok'])) return [false, 'Telegram no reconoce ese token: ' . (string)($r['description'] ?? 'revisa que sea el que dio @BotFather') . '.'];
        $bot = mb_substr((string)($r['result']['username'] ?? ''), 0, 100);
        $db = self::db();
        $sql = "INSERT INTO telegram_config (id, token_cifrado, bot_username, actualizado, actualizado_por) VALUES (1, '"
            . $db->real_escape_string($cif) . "', '" . $db->real_escape_string($bot) . "', NOW(), '" . $db->real_escape_string(mb_substr(trim($quien), 0, 150)) . "')
            ON DUPLICATE KEY UPDATE token_cifrado = VALUES(token_cifrado), bot_username = VALUES(bot_username), actualizado = NOW(), actualizado_por = VALUES(actualizado_por)";
        if (!$db->query($sql)) return [false, 'No se pudo guardar: ' . $db->error];
        self::$cfg = null;
        @unlink(__DIR__ . '/../storage/telegram_bot.json');   // el caché de getMe va firmado con sha1(token)
        return [true, 'Token guardado: el bot es @' . $bot . '.'];
    }

    /** Borra el token de la base (vuelve al del entorno, si lo hay) */
    public static function borrarToken(): bool
    {
        if (!self::disponibleConfig()) return false;
        $ok = (bool)self::db()->query('DELETE FROM telegram_config WHERE id = 1');
        if ($ok) { self::$cfg = null; @unlink(__DIR__ . '/../storage/telegram_bot.json'); }
        return $ok;
    }

    public static function disponible(): bool
    {
        return (bool)self::filas("SHOW TABLES LIKE 'telegram_vinculos'");
    }

    /** Secreto que Telegram devuelve en cada llamada al webhook; se deriva del token, no hay que configurar nada más */
    public static function secretoWebhook(): string
    {
        return substr(hash_hmac('sha256', 'projectweb-telegram-webhook', self::token()), 0, 48);
    }

    public static function h($t): string
    {
        return htmlspecialchars((string)$t, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ---------- API de Telegram ----------

    public static function api(string $metodo, array $params = [], int $espera = 10): ?array
    {
        if (self::$http) return (self::$http)($metodo, $params);
        if (!self::configurado()) return null;
        return self::consultar(self::token(), $metodo, $params, $espera);
    }

    /** Llamada con un token explícito (para validar uno recién pegado antes de guardarlo) */
    public static function consultar(string $token, string $metodo, array $params = [], int $espera = 10): ?array
    {
        if (self::$http) return (self::$http)($metodo, $params);
        if ($token === '') return null;
        $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $metodo);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $espera, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $r = curl_exec($ch);
        curl_close($ch);
        $j = $r === false ? null : json_decode((string)$r, true);
        return is_array($j) ? $j : null;
    }

    /** Datos del bot (getMe), guardados en storage/ para no preguntar en cada pantalla */
    public static function bot(): ?array
    {
        $h = sha1(self::token());
        $arch = __DIR__ . '/../storage/telegram_bot.json';
        $c = is_file($arch) ? json_decode((string)@file_get_contents($arch), true) : null;
        if (is_array($c) && ($c['h'] ?? '') === $h && !self::$http) return $c;
        $r = self::api('getMe', [], 6);
        if (empty($r['ok'])) return null;
        $c = ['h' => $h, 'username' => (string)($r['result']['username'] ?? ''), 'nombre' => (string)($r['result']['first_name'] ?? '')];
        if (!self::$http) @file_put_contents($arch, json_encode($c));
        return $c;
    }

    public static function usuarioBot(): string
    {
        $u = ltrim(self::env('TELEGRAM_BOT_USERNAME'), '@');
        if ($u === '') $u = ltrim((string)self::configGuardada()['username'], '@');
        return $u !== '' ? $u : (string)(self::bot()['username'] ?? '');
    }

    /** Envía un mensaje HTML. @return array{0: bool, 1: int} [ok, código de error de Telegram] */
    public static function enviar(int $chat, string $html, ?array $botones = null): array
    {
        $p = ['chat_id' => $chat, 'text' => mb_substr($html, 0, 4000), 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        if ($botones) $p['reply_markup'] = ['inline_keyboard' => $botones];
        $r = self::api('sendMessage', $p);
        if (!empty($r['ok'])) return [true, 0];
        if (($r['error_code'] ?? 0) == 400 && stripos((string)($r['description'] ?? ''), 'parse entities') !== false) {   // HTML mal formado: se manda como texto
            unset($p['parse_mode']);
            $p['text'] = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $r = self::api('sendMessage', $p);
            if (!empty($r['ok'])) return [true, 0];
        }
        return [false, (int)($r['error_code'] ?? 0)];
    }

    /** Descarga un archivo enviado al bot (hasta $max bytes). Devuelve la ruta de un archivo temporal o null */
    public static function descargar(string $fileId, int $max = 15 * 1024 * 1024): ?string
    {
        $r = self::api('getFile', ['file_id' => $fileId]);
        $ruta = $r['result']['file_path'] ?? null;
        if (empty($r['ok']) || !is_string($ruta) || ($r['result']['file_size'] ?? 0) > $max) return null;
        $tmp = tempnam(sys_get_temp_dir(), 'tg');
        if (self::$http) {
            $b = (self::$http)('__descargar', ['ruta' => $ruta]);
            if (!isset($b['bytes'])) { @unlink($tmp); return null; }
            file_put_contents($tmp, $b['bytes']);
            return $tmp;
        }
        $f = fopen($tmp, 'wb');
        $ch = curl_init('https://api.telegram.org/file/bot' . self::token() . '/' . $ruta);
        curl_setopt_array($ch, [CURLOPT_FILE => $f, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_MAXFILESIZE => $max]);
        $ok = curl_exec($ch) !== false && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
        curl_close($ch);
        fclose($f);
        if (!$ok || filesize($tmp) === 0) { @unlink($tmp); return null; }
        return $tmp;
    }

    // ---------- permisos (los mismos de la web, sin sesión) ----------

    public static function usuarioPorId(int $id): ?array
    {
        return self::filas("SELECT id, nombre, email, rol FROM usuarios WHERE id = $id")[0] ?? null;
    }

    public static function puedeVer(array $u, Proyecto $p): bool
    {
        if ($u['rol'] === 'visitante') return false;
        return $u['rol'] === 'admin' || (int)$p->usuario_id === (int)$u['id'] || ProyectoAcceso::existe((int)$p->id, (int)$u['id']);
    }

    public static function puedeGestionar(array $u, Proyecto $p): bool
    {
        return $u['rol'] === 'admin' || ($u['rol'] === 'cliente' && (int)$p->usuario_id === (int)$u['id']);
    }

    /** Proyectos del usuario que puede ver (o solo gestionar) */
    public static function proyectosDe(array $u, bool $soloGestionables = false): array
    {
        if ($u['rol'] === 'visitante') return [];
        $l = array_values(array_filter(Proyecto::visiblesPara($u), fn($p) => self::puedeVer($u, $p)));
        return $soloGestionables ? array_values(array_filter($l, fn($p) => self::puedeGestionar($u, $p))) : $l;
    }

    // ---------- vinculación ----------

    public static function crearCodigo(int $uid, string $tipo, ?int $pid = null): string
    {
        $db = self::db();
        $db->query('DELETE FROM telegram_codigos WHERE expira < NOW()');
        $db->query("DELETE FROM telegram_codigos WHERE usuario_id = $uid AND tipo = '" . ($tipo === 'grupo' ? 'grupo' : 'usuario') . "'");
        $al = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $c = '';
        for ($i = 0; $i < 10; $i++) $c .= $al[random_int(0, strlen($al) - 1)];
        $t = $tipo === 'grupo' ? 'grupo' : 'usuario';
        $p = $pid ? (int)$pid : 'NULL';
        $db->query("INSERT INTO telegram_codigos (codigo, usuario_id, tipo, proyecto_id, expira) VALUES ('$c', $uid, '$t', $p, DATE_ADD(NOW(), INTERVAL " . self::VIGENCIA_CODIGO . ' SECOND))');
        return $c;
    }

    public static function enlace(string $codigo, string $tipo): string
    {
        return 'https://t.me/' . self::usuarioBot() . ($tipo === 'grupo' ? '?startgroup=' : '?start=') . $codigo;
    }

    /** Código vigente (sin gastarlo) */
    public static function codigo(string $codigo): ?array
    {
        return self::filas("SELECT * FROM telegram_codigos WHERE codigo = '" . self::esc($codigo) . "' AND expira >= NOW()")[0] ?? null;
    }

    public static function gastarCodigo(string $codigo): void
    {
        self::db()->query("DELETE FROM telegram_codigos WHERE codigo = '" . self::esc($codigo) . "'");
    }

    public static function vinculoDeUsuario(int $uid): ?array
    {
        return self::filas("SELECT * FROM telegram_vinculos WHERE usuario_id = $uid")[0] ?? null;
    }

    public static function vinculoDeChat(int $chat): ?array
    {
        return self::filas("SELECT * FROM telegram_vinculos WHERE chat_id = $chat")[0] ?? null;
    }

    public static function vincular(int $uid, int $chat, string $nombreTg): void
    {
        $db = self::db();
        $db->query("DELETE FROM telegram_vinculos WHERE usuario_id = $uid OR chat_id = $chat");
        $st = $db->prepare('INSERT INTO telegram_vinculos (usuario_id, chat_id, nombre_tg) VALUES (?, ?, ?)');
        $n = mb_substr($nombreTg, 0, 150);
        $st->bind_param('iis', $uid, $chat, $n);
        $st->execute();
        $db->query("UPDATE telegram_destinos SET chat_id = $chat, activo = 1, fallos_seguidos = 0 WHERE usuario_id = $uid");   // sus avisos siguen en el chat nuevo
    }

    public static function desvincular(int $uid): void
    {
        self::db()->query("DELETE FROM telegram_destinos WHERE usuario_id = $uid");
        self::db()->query("DELETE FROM telegram_vinculos WHERE usuario_id = $uid");
    }

    public static function proyectoActivo(int $chat, ?int $pid): void
    {
        self::db()->query("UPDATE telegram_vinculos SET proyecto_id = " . ($pid ? (int)$pid : 'NULL') . " WHERE chat_id = $chat");
    }

    // ---------- destinos de avisos ----------

    public static function destinos(int $pid): array
    {
        return self::filas("SELECT d.*, u.nombre AS usuario FROM telegram_destinos d LEFT JOIN usuarios u ON u.id = d.usuario_id WHERE d.proyecto_id = $pid ORDER BY d.usuario_id IS NULL DESC, d.id");
    }

    public static function destino(int $pid, int $id): ?array
    {
        return self::filas("SELECT * FROM telegram_destinos WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    public static function agregarDestino(int $pid, int $chat, string $titulo, ?int $uid): void
    {
        $ev = self::esc(implode(',', self::INICIALES));
        $t = self::esc(mb_substr($titulo, 0, 150));
        $u = $uid ? (int)$uid : 'NULL';
        self::db()->query("INSERT INTO telegram_destinos (proyecto_id, chat_id, titulo, usuario_id, eventos) VALUES ($pid, $chat, '$t', $u, '$ev')
            ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), activo = 1, fallos_seguidos = 0");
        self::$destinosCache = null;
    }

    /** Chat privado de la persona como destino de avisos del proyecto. @return array{0: bool, 1: string} */
    public static function activarMiChat(int $pid, array $u, bool $activo): array
    {
        $v = self::vinculoDeUsuario((int)$u['id']);
        if (!$v) return [false, 'Primero conecta tu Telegram.'];
        if (!$activo) {
            self::db()->query("DELETE FROM telegram_destinos WHERE proyecto_id = $pid AND usuario_id = " . (int)$u['id']);
            self::$destinosCache = null;
            return [true, 'Ya no recibirás avisos de este proyecto en tu chat.'];
        }
        self::agregarDestino($pid, (int)$v['chat_id'], 'Mi chat (' . $u['nombre'] . ')', (int)$u['id']);
        return [true, 'Listo: los avisos de este proyecto llegarán a tu chat de Telegram.'];
    }

    public static function quitarDestino(int $pid, int $id): void
    {
        self::db()->query("DELETE FROM telegram_destinos WHERE id = $id AND proyecto_id = $pid");
        self::$destinosCache = null;
    }

    public static function pausarDestino(int $pid, int $id, bool $activo): void
    {
        self::db()->query('UPDATE telegram_destinos SET activo = ' . ($activo ? 1 : 0) . ", fallos_seguidos = 0 WHERE id = $id AND proyecto_id = $pid");
        self::$destinosCache = null;
    }

    public static function guardarEventos(int $pid, int $id, $eventos): void
    {
        $l = array_values(array_filter(is_array($eventos) ? $eventos : [], fn($e) => is_string($e) && isset(self::EVENTOS[$e])));
        self::db()->query("UPDATE telegram_destinos SET eventos = '" . self::esc(implode(',', $l)) . "' WHERE id = $id AND proyecto_id = $pid");
    }

    /** Envía un mensaje de prueba al destino. @return array{0: bool, 1: string} */
    public static function probar(int $pid, int $id): array
    {
        $d = self::destino($pid, $id);
        if (!$d) return [false, 'El destino no existe.'];
        $nombre = self::filas("SELECT nombre FROM proyectos WHERE id = $pid")[0]['nombre'] ?? '';
        [$ok, $err] = self::enviar((int)$d['chat_id'], "✅ <b>Prueba de avisos</b>\nProyecto: " . self::h($nombre) . "\nSi ves esto, los avisos llegarán aquí.");
        self::resultado($id, $ok);
        return [$ok, $ok ? 'Mensaje de prueba enviado.' : 'Telegram no entregó el mensaje' . ($err === 403 ? ' (el bot fue bloqueado o expulsado del chat)' : ($err ? " (error $err)" : '')) . '.'];
    }

    private static ?array $destinosCache = null;

    /** Proyectos con al menos un destino activo (una consulta por petición) */
    public static function proyectosConDestino(): array
    {
        if (self::$destinosCache === null) {
            self::$destinosCache = array_flip(array_map('intval', array_column(self::filas('SELECT DISTINCT proyecto_id FROM telegram_destinos WHERE activo = 1'), 'proyecto_id')));
        }
        return self::$destinosCache;
    }

    private static function resultado(int $id, bool $ok): void
    {
        if ($ok) self::db()->query("UPDATE telegram_destinos SET fallos_seguidos = 0 WHERE id = $id");
        else self::db()->query('UPDATE telegram_destinos SET fallos_seguidos = fallos_seguidos + 1, activo = IF(fallos_seguidos + 1 >= ' . self::MAX_FALLOS . ", 0, activo) WHERE id = $id");
    }

    // ---------- avisos ----------

    /** Pone un evento en cola para los destinos del proyecto; se envía al terminar la petición */
    public static function aviso(int $pid, string $evento, array $datos): void
    {
        if (!isset(self::EVENTOS[$evento]) || !self::configurado() || !isset(self::proyectosConDestino()[$pid]) || count(self::$cola) >= self::MAX_COLA) return;
        self::$cola[] = [$pid, $evento, $datos];
        if (!self::$programado) {
            self::$programado = true;
            register_shutdown_function([self::class, 'vaciar']);
        }
    }

    public static function vaciar(): void
    {
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        $cola = self::$cola;
        self::$cola = [];
        foreach ($cola as [$pid, $evento, $datos]) {
            try { self::entregarAviso($pid, $evento, $datos); } catch (\Throwable $e) { error_log('Telegram: ' . $e->getMessage()); }
        }
    }

    /** @return int a cuántos destinos se envió */
    public static function entregarAviso(int $pid, string $evento, array $datos): int
    {
        $texto = self::mensajeEvento($pid, $evento, $datos);
        if ($texto === null) return 0;
        $nombre = self::filas("SELECT nombre FROM proyectos WHERE id = $pid")[0]['nombre'] ?? '';
        $n = 0;
        foreach (self::filas("SELECT * FROM telegram_destinos WHERE proyecto_id = $pid AND activo = 1") as $d) {
            if (!in_array($evento, explode(',', $d['eventos']), true)) continue;
            if ($d['usuario_id'] && !self::usuarioVe((int)$d['usuario_id'], $pid)) continue;   // perdió el acceso al proyecto
            [$ok] = self::enviar((int)$d['chat_id'], '<b>' . self::h($nombre) . "</b>\n" . $texto);
            self::resultado((int)$d['id'], $ok);
            if ($ok) $n++;
        }
        return $n;
    }

    private static function usuarioVe(int $uid, int $pid): bool
    {
        $u = self::usuarioPorId($uid);
        $p = Proyecto::find($pid);
        return $u && $p && self::puedeVer($u, $p);
    }

    private const PRIO = ['baja' => '🟢', 'media' => '🟡', 'alta' => '🟠', 'critica' => '🔴'];

    /** Texto del aviso, o null si ese evento no se avisa (p. ej. bitácora de importancia baja) */
    public static function mensajeEvento(int $pid, string $evento, array $d): ?string
    {
        $h = fn($t) => self::h($t);
        $f = fn($fecha) => $fecha ? date('d/m/Y', strtotime((string)$fecha)) : '';
        switch ($evento) {
            case 'rfi.creado':
                return '❓ <b>Nuevo RFI ' . $h($d['numero'] ?? '') . '</b>' . "\n" . $h($d['asunto'] ?? '') . "\n" . (self::PRIO[$d['prioridad'] ?? 'media'] ?? '') . ' Prioridad ' . $h($d['prioridad'] ?? 'media')
                    . (!empty($d['fecha_limite']) ? ' · responder antes del ' . $f($d['fecha_limite']) : '');
            case 'rfi.estado':
                $r = self::filas('SELECT numero, asunto FROM rfis WHERE id = ' . (int)($d['id'] ?? 0) . " AND proyecto_id = $pid")[0] ?? null;
                return '❓ <b>RFI ' . $h($r['numero'] ?? '') . '</b> ahora está «' . $h($d['estado'] ?? '') . '»' . ($r ? "\n" . $h($r['asunto']) : '');
            case 'punch.creado':
                return '🔧 <b>Nuevo pendiente</b> ' . (self::PRIO[$d['severidad'] ?? 'media'] ?? '') . ' ' . $h($d['severidad'] ?? '') . "\n" . $h($d['titulo'] ?? '')
                    . (!empty($d['ubicacion']) ? "\n📍 " . $h($d['ubicacion']) : '') . (!empty($d['responsable']) ? "\n👷 " . $h($d['responsable']) : '');
            case 'punch.estado':
                return '🔧 Pendiente «' . $h($d['titulo'] ?? '') . '»: ' . $h($d['antes'] ?? '') . ' → <b>' . $h($d['estado'] ?? '') . '</b>';
            case 'restriccion.creada':
                $a = self::filas('SELECT codigo, nombre FROM actividades WHERE id = ' . (int)($d['actividad_id'] ?? 0))[0] ?? null;
                return '🚧 <b>Nueva restricción</b>' . ($a ? ' en ' . $h($a['codigo'] . ' ' . $a['nombre']) : '') . "\n" . $h(self::corto($d['descripcion'] ?? '', 300));
            case 'submittal.actualizado':
                return '📄 Submittal <b>' . $h($d['numero_submittal'] ?? '') . '</b>: ' . $h(str_replace('_', ' ', $d['estatus'] ?? ''));
            case 'bitacora.creada':
                if (!in_array($d['importancia'] ?? '', ['alta', 'critica'], true)) return null;
                return '📒 <b>Bitácora</b> ' . (self::PRIO[$d['importancia']] ?? '') . ' ' . $h($d['importancia']) . "\n" . $h(self::corto($d['descripcion'] ?? '', 500));
            case 'clima.alerta':
                return (string)($d['texto'] ?? '') ?: null;
        }
        return null;
    }

    // ---------- entrada (webhook y sondeo) ----------

    public static function esWebhook(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') === '/api/telegram/webhook';
    }

    /** Atiende la llamada de Telegram y termina. Siempre 200 si el secreto es correcto (si no, Telegram reintentaría sin parar) */
    public static function atender(): never
    {
        header('Content-Type: application/json');
        $secreto = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        if (!self::configurado() || !self::disponible() || !hash_equals(self::secretoWebhook(), $secreto)) {
            http_response_code(403);
            echo '{"ok":false}';
            exit;
        }
        $upd = json_decode((string)file_get_contents('php://input'), true);
        try {
            if (is_array($upd)) TelegramBot::procesar($upd);
        } catch (\Throwable $e) {
            error_log('Telegram bot: ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine());
        }
        echo '{"ok":true}';
        exit;
    }

    /** Registra en Telegram la dirección pública del webhook. @return array{0: bool, 1: string} */
    public static function registrarWebhook(string $base): array
    {
        if (!self::configurado()) return [false, 'Falta TELEGRAM_BOT_TOKEN en el servidor.'];
        if (!str_starts_with($base, 'https://')) return [false, 'Telegram solo acepta direcciones https:// públicas. En tu equipo local usa «php bin/telegram.php --sondeo».'];
        $r = self::api('setWebhook', ['url' => rtrim($base, '/') . '/api/telegram/webhook', 'secret_token' => self::secretoWebhook(), 'allowed_updates' => ['message', 'callback_query', 'my_chat_member'], 'max_connections' => 10]);
        return !empty($r['ok']) ? [true, 'Webhook registrado: el bot ya recibe mensajes.'] : [false, 'Telegram respondió: ' . ($r['description'] ?? 'sin respuesta')];
    }

    public static function estadoWebhook(): ?array
    {
        $r = self::api('getWebhookInfo', [], 6);
        return !empty($r['ok']) ? $r['result'] : null;
    }
}
