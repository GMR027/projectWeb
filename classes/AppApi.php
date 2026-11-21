<?php

namespace Classes;

use Model\Actividad;
use Model\Archivo;
use Model\Bitacora;
use Model\Evidencia;
use Model\Organizacion;
use Model\Partida;
use Model\Personal;
use Model\Proyecto;
use Model\ReporteDiario;
use Model\ReporteMaterial;
use Model\ReporteTiempo;
use Model\Restriccion;
use Model\Usuario;

/**
 * API de la app Android (Fase 1).
 *
 * Autenticación por token de dispositivo (`pwa_…`): se guarda solo su SHA-256 en
 * `sesiones_usuario`. La API se atiende antes de Router/CSRF, igual que `/api/v1`.
 * El contexto de permisos es el mismo de la web: se monta una sesión PHP efímera,
 * sin cookie, para que `puede_ver_proyecto()` y compañía funcionen.
 */
class AppApi
{
    use AccesoDb;

    public const VERSION = '1';

    /** @var array{sesion:array,usuario:array}|null */
    private static ?array $auth = null;

    public static function esDeLaApi(): bool
    {
        $r = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return $r === '/api/app' || str_starts_with($r, '/api/app/');
    }

    public static function despachar(): never
    {
        $ruta = substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', 8); // quita «/api/app»
        $ruta = '/' . trim($ruta, '/');
        if ($ruta === '/') $ruta = '/';
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // La app es un WebView con origen propio (tauri://localhost) y usa Bearer, no cookies.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-App-Token');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        if ($metodo === 'OPTIONS') { http_response_code(204); header('Allow: GET, POST, OPTIONS'); exit; }

        $rutas = [
            ['POST', '/login', 'login'],
            ['GET', '/me', 'me'],
            ['POST', '/logout', 'logout'],
            ['GET', '/bootstrap', 'bootstrap'],
            ['GET', '/sync', 'syncGet'],
            ['POST', '/sync', 'syncPost'],
            ['GET', '/sesiones', 'sesiones'],
            ['POST', '/sesiones/cerrar', 'cerrarSesion'],
            ['GET', '/archivo', 'archivoGet'],
            ['POST', '/enlace', 'enlace'],
            ['GET', '/chat', 'chatGet'],
            ['POST', '/chat/nueva', 'chatNueva'],
            ['POST', '/chat/mensaje', 'chatMensaje'],
            ['GET', '/chat/borradores', 'chatBorradores'],
            ['POST', '/chat/borrador', 'chatBorrador'],
            ['POST', '/chat/borrador/aplicar', 'chatAplicar'],
            ['POST', '/chat/borrador/rechazar', 'chatRechazar'],
            ['GET', '/modelos', 'modelos'],
        ];
        $fn = null; $permitidos = [];
        foreach ($rutas as [$m, $r, $f]) {
            if ($r === $ruta) {
                $permitidos[] = $m;
                if ($m === $metodo) $fn = $f;
            }
        }
        if (!$fn) {
            if ($permitidos) { header('Allow: ' . implode(', ', $permitidos)); self::error(405, 'metodo_no_permitido', 'Método no permitido.'); }
            self::error(404, 'no_encontrado', 'La ruta no existe.');
        }

        if ($fn !== 'login') {
            self::$auth = self::autenticar();
            self::abrirSesion(self::$auth['usuario']);
        }

        self::{$fn}();
    }

    // ---------- entrada / salida ----------

    private static function responder(array $datos, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private static function error(int $codigo, string $clave, string $mensaje): never
    {
        if ($codigo === 401) header('WWW-Authenticate: Bearer realm="ProjectWebApp"');
        self::responder(['success' => false, 'codigo' => $clave, 'error' => $mensaje], $codigo);
    }

    private static function cabecera(string $nombre): string
    {
        $k = 'HTTP_' . strtoupper(str_replace('-', '_', $nombre));
        return (string)($_SERVER[$k] ?? $_SERVER['REDIRECT_' . $k] ?? '');
    }

    private static function token(): string
    {
        $auth = self::cabecera('Authorization');
        if (preg_match('/^Bearer\s+(pwa_[A-Za-z0-9._-]+)$/i', $auth, $m)) return $m[1];
        $cabecera = self::cabecera('X-App-Token');
        if ($cabecera !== '') return $cabecera;
        // Solo para abrir un archivo desde el visor del sistema (no se puede enviar Bearer en una URL).
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (str_ends_with($ruta, '/api/app/archivo') && isset($_GET['token']) && preg_match('/^pwa_[A-Za-z0-9._-]+$/', (string)$_GET['token'])) {
            return (string)$_GET['token'];
        }
        return '';
    }

    private static function cuerpo(): array
    {
        $crudo = (string)file_get_contents('php://input');
        if ($crudo !== '' && str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'json')) {
            $j = json_decode($crudo, true);
            if (!is_array($j)) self::error(400, 'json_invalido', 'El cuerpo no es un JSON válido.');
            return $j;
        }
        return $_POST;
    }

    private static function texto($v, int $max = 0): string
    {
        $s = is_scalar($v) ? trim((string)$v) : '';
        return $max > 0 ? mb_substr($s, 0, $max) : $s;
    }

    // ---------- autenticación ----------

    /** @return array{sesion:array,usuario:array} */
    private static function autenticar(): array
    {
        $token = self::token();
        if ($token === '') self::error(401, 'sin_token', 'Falta el token de la app.');
        $r = Sesiones::verificarToken($token);
        if (!$r) self::error(401, 'token_invalido', 'El token no es válido, caducó o la sesión se cerró.');
        if ((int)($r['usuario']['debe_cambiar_password'] ?? 0) === 1) {
            self::error(403, 'password_inicial', 'Debes cambiar la contraseña inicial desde la web antes de usar la app.');
        }
        return $r;
    }

    /** Sesión PHP de usar y tirar para reutilizar los permisos de la web */
    private static function abrirSesion(array $u): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_abort();
        ini_set('session.use_cookies', '0');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cache_limiter', '');
        session_id('app' . bin2hex(random_bytes(12)));
        session_start();
        $_SESSION = [
            'id' => (int)$u['id'],
            'nombre' => (string)$u['nombre'],
            'email' => (string)$u['email'],
            'rol' => (string)$u['rol'],
            'nivel_acceso' => (string)($u['nivel_acceso'] ?? 'ver'),
            'organizacion_id' => $u['organizacion_id'] ?? null,
            'rol_org' => (string)($u['rol_org'] ?? 'visitante'),
        ];
        register_shutdown_function(fn() => @session_destroy());
    }

    private static function usuario(): array
    {
        return self::$auth['usuario'] ?? [];
    }

    private static function sesion(): array
    {
        return self::$auth['sesion'] ?? [];
    }

    private static function usuarioPublico(array $u): array
    {
        return [
            'id' => (int)($u['id'] ?? 0),
            'nombre' => (string)($u['nombre'] ?? ''),
            'email' => (string)($u['email'] ?? ''),
            'rol' => (string)($u['rol'] ?? ''),
            'nivel_acceso' => (string)($u['nivel_acceso'] ?? 'ver'),
            'organizacion_id' => isset($u['organizacion_id']) ? (int)$u['organizacion_id'] : null,
            'rol_org' => (string)($u['rol_org'] ?? 'visitante'),
            'puede_crear_proyectos' => (int)($u['puede_crear_proyectos'] ?? 0) === 1,
        ];
    }

    // ---------- endpoints ----------

    private static function login(): never
    {
        $b = self::cuerpo();
        $email = strtolower(self::texto($b['email'] ?? '', 100));
        $password = is_string($b['password'] ?? null) ? $b['password'] : '';
        if ($email === '' || $password === '') self::error(422, 'faltan_datos', 'Escribe tu correo y contraseña.');

        $ip = ip_cliente();
        if (random_int(1, 100) === 1) LoginLimite::limpiar();
        $espera = LoginLimite::espera($ip, $email);
        if ($espera > 0) {
            Accesos::registrar(null, $email, 'bloqueado');
            self::error(429, 'demasiados_intentos', 'Demasiados intentos. Espera ' . (int)ceil($espera / 60) . ' minuto(s).');
        }

        $u = Usuario::where('email', $email);
        if (!$u || !(int)$u->confirmado || !$u->comprobarPassword($password)) {
            LoginLimite::fallo($ip, $email);
            Accesos::registrar($u ? (int)$u->id : null, $email, 'fallo');
            self::error(401, 'credenciales', 'El correo o la contraseña no son correctos.');
        }
        if ((int)($u->debe_cambiar_password ?? 0) === 1) {
            self::error(403, 'password_inicial', 'Esta cuenta tiene una contraseña inicial. Cámbiala desde la web antes de entrar a la app.');
        }

        LoginLimite::exito($ip, $email);
        if (password_needs_rehash((string)$u->password, PASSWORD_BCRYPT)) {
            $u->password = $password;
            $u->hashPassword();
            $u->guardar();
        }
        Usuario::marcarUltimoAcceso((int)$u->id);
        Accesos::registrar((int)$u->id, $email, 'ok');

        $token = 'pwa_' . bin2hex(random_bytes(32));
        $datos = is_array($b['dispositivo'] ?? null) ? $b['dispositivo'] : [];
        $guardado = Sesiones::crearAndroid((int)$u->id, $token, [
            'dispositivo' => self::texto($datos['nombre'] ?? $datos['modelo'] ?? '', 120),
            'sistema' => self::texto($datos['sistema'] ?? '', 80),
            'app_version' => self::texto($datos['app_version'] ?? '', 30),
            'push_token' => self::texto($datos['push_token'] ?? '', 255),
        ]);
        if ($guardado === null) {
            self::error(503, 'sesiones_no_disponible', 'La app no está habilitada en este servidor (falta aplicar database/migracion_sesiones_dispositivos.sql).');
        }

        $usuario = self::usuarioArrayDe($u);
        $proyectos = self::proyectosResumen($usuario);
        $org = Organizacion::deUsuario((int)$u->id);
        self::responder([
            'success' => true,
            'token' => $token,
            'token_tipo' => 'Bearer',
            'expira' => date('c', time() + Sesiones::TTL_ANDROID),
            'usuario' => self::usuarioPublico($usuario),
            'organizacion' => $org ? ['id' => (int)$org->id, 'nombre' => $org->nombre, 'plan' => $org->plan_id, 'estado' => $org->estado_plan] : null,
            'proyectos' => $proyectos,
            'servidor' => self::servidor(),
        ]);
    }

    private static function usuarioArrayDe(Usuario $u): array
    {
        return [
            'id' => (int)$u->id,
            'nombre' => (string)$u->nombre,
            'email' => (string)$u->email,
            'rol' => (string)$u->rol,
            'nivel_acceso' => (string)($u->nivel_acceso ?? 'ver'),
            'organizacion_id' => $u->organizacion_id !== null ? (int)$u->organizacion_id : null,
            'rol_org' => (string)($u->rol_org ?? 'visitante'),
            'confirmado' => (int)($u->confirmado ?? 0),
            'debe_cambiar_password' => (int)($u->debe_cambiar_password ?? 0),
            'herramientas' => $u->herramientas ?? null,
            'herramientas_edicion' => $u->herramientas_edicion ?? null,
            'puede_crear_proyectos' => (int)($u->puede_crear_proyectos ?? 0),
        ];
    }

    private static function me(): never
    {
        $u = self::usuario();
        $org = Organizacion::deUsuario((int)$u['id']);
        self::responder([
            'success' => true,
            'usuario' => self::usuarioPublico($u),
            'organizacion' => $org ? ['id' => (int)$org->id, 'nombre' => $org->nombre, 'plan' => $org->plan_id, 'estado' => $org->estado_plan] : null,
            'sesion' => [
                'id' => (int)(self::sesion()['sesion_id'] ?? 0),
                'tipo' => 'android',
                'dispositivo' => (string)(self::sesion()['dispositivo'] ?? ''),
                'ultimo_acceso' => (string)(self::sesion()['ultimo_acceso'] ?? ''),
                'expira' => (string)(self::sesion()['expira'] ?? ''),
            ],
        ]);
    }

    private static function logout(): never
    {
        Sesiones::cerrarToken(self::token());
        self::responder(['success' => true, 'mensaje' => 'Sesión cerrada.']);
    }

    private static function bootstrap(): never
    {
        $u = self::usuario();
        self::responder([
            'success' => true,
            'usuario' => self::usuarioPublico($u),
            'proyectos' => self::proyectosResumen($u),
            'sesiones_abiertas' => Sesiones::contar((int)$u['id']),
            'servidor' => self::servidor(),
            'modelos' => self::catalogoModelos(),
            'asistente' => ['version' => 1, 'historial_web' => false, 'borradores' => false],
        ]);
    }

    private static function syncGet(): never
    {
        $u = self::usuario();
        $pid = (int)($_GET['proyecto'] ?? 0);
        $proyectos = $pid > 0 ? [Proyecto::find($pid)] : Proyecto::visiblesPara($u);
        $datos = [];
        foreach ($proyectos as $p) {
            if (!$p || !puede_ver_proyecto($p)) continue;
            $datos[] = self::snapshot($p);
        }
        self::responder([
            'success' => true,
            'generado' => date('c'),
            'proyectos' => $datos,
            'totales' => [
                'proyectos' => count($datos),
                'actividades' => array_sum(array_map(fn($x) => count($x['actividades']), $datos)),
            ],
        ]);
    }

    private static function syncPost(): never
    {
        $b = self::cuerpo();
        $ops = $b['operaciones'] ?? [];
        if (!is_array($ops) || !$ops) self::error(422, 'sin_operaciones', 'No se recibieron operaciones.');
        $ops = array_slice($ops, 0, 100);
        $resultados = [];
        foreach ($ops as $op) {
            if (!is_array($op)) continue;
            $resultados[] = self::aplicarOperacion($op);
        }
        self::responder(['success' => true, 'resultados' => $resultados]);
    }

    private static function sesiones(): never
    {
        $u = self::usuario();
        $token = self::token();
        $lista = Sesiones::listar((int)$u['id'], $token);
        self::responder(['success' => true, 'sesiones' => $lista, 'total' => count($lista)]);
    }

    private static function cerrarSesion(): never
    {
        $b = self::cuerpo();
        $id = (int)($b['id'] ?? 0);
        if ($id <= 0) self::error(422, 'id_invalido', 'Falta la sesión que quieres cerrar.');
        $ok = Sesiones::cerrar((int)self::usuario()['id'], $id);
        self::responder(['success' => $ok, 'mensaje' => $ok ? 'Sesión cerrada.' : 'No se pudo cerrar la sesión.']);
    }


    /** Puente de sesión: URL de un solo uso para abrir una herramienta en el navegador ya autenticado. */
    private static function enlace(): never
    {
        $u = self::usuario();
        $b = self::cuerpo();
        $ruta = EnlacesApp::rutaSegura((string)($b['ruta'] ?? '/dashboard'));
        $codigo = EnlacesApp::crear((int)$u['id'], $ruta);
        if (!$codigo) self::error(503, 'sin_puente', 'El servidor no tiene habilitado el acceso directo. Actualiza la plataforma.');
        self::responder([
            'success' => true,
            'url' => EnlacesApp::url($codigo),
            'expira' => EnlacesApp::TTL,
        ]);
    }

    private static function archivoGet(): never
    {
        $id = (int)($_GET['id'] ?? 0);
        $a = $id > 0 ? Archivo::find($id) : null;
        $p = $a ? Proyecto::find((int)$a->proyecto_id) : null;
        if (!$a || !$p || !puede_ver_proyecto($p)) self::error(404, 'no_encontrado', 'El archivo no existe o no tienes acceso.');
        $ruta = $a->ruta();
        if (!$ruta) self::error(404, 'sin_archivo', 'El archivo ya no está en el servidor.');
        $nombre = trim((string)$a->archivo_original) ?: (string)$a->nombre;
        $nombre = basename($nombre);
        if ($nombre === '') $nombre = 'archivo';
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $inline = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'txt', 'csv'], true);
        header('Content-Type: ' . ($inline ? ($ext === 'pdf' ? 'application/pdf' : (in_array($ext, ['png','jpg','jpeg','gif','webp','svg'], true) ? 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext) : 'text/plain; charset=utf-8')) : 'application/octet-stream'));
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $nombre) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($ruta);
        exit;
    }

    private static function chatGet(): never
    {
        if (!Asistente::disponible()) self::error(503, 'asistente_no_disponible', 'El historial del asistente aún no está habilitado.');
        $pid = (int)($_GET['proyecto'] ?? 0);
        $p = $pid > 0 ? Proyecto::find($pid) : null;
        if (!$p || !puede_ver_proyecto($p)) self::error(404, 'no_encontrado', 'El proyecto no existe o no tienes acceso.');
        $uid = (int)self::usuario()['id'];
        $conv = Asistente::ultimaConversacion($uid, $pid);
        $msgs = $conv ? Asistente::mensajes($conv) : [];
        self::responder(['success' => true, 'conversacion_id' => $conv, 'mensajes' => $msgs, 'borradores' => Asistente::borradores($uid, $pid)]);
    }

    private static function chatNueva(): never
    {
        if (!Asistente::disponible()) self::error(503, 'asistente_no_disponible', 'El historial del asistente aún no está habilitado.');
        $b = self::cuerpo();
        $pid = (int)($b['proyecto'] ?? 0);
        $p = $pid > 0 ? Proyecto::find($pid) : null;
        if (!$p || !puede_ver_proyecto($p)) self::error(404, 'no_encontrado', 'El proyecto no existe o no tienes acceso.');
        $uid = (int)self::usuario()['id'];
        $conv = Asistente::crearConversacion($uid, $pid);
        if (!$conv) self::error(500, 'guardado', 'No se pudo crear la conversación.');
        self::responder(['success' => true, 'conversacion_id' => $conv]);
    }

    private static function chatMensaje(): never
    {
        if (!Asistente::disponible()) self::error(503, 'asistente_no_disponible', 'El historial del asistente aún no está habilitado.');
        $b = self::cuerpo();
        $pid = (int)($b['proyecto'] ?? 0);
        $p = $pid > 0 ? Proyecto::find($pid) : null;
        if (!$p || !puede_ver_proyecto($p)) self::error(404, 'no_encontrado', 'El proyecto no existe o no tienes acceso.');
        $uid = (int)self::usuario()['id'];
        $conv = (int)($b['conversacion_id'] ?? 0);
        if ($conv <= 0 || !Asistente::conversacion($conv, $uid, false)) $conv = Asistente::ultimaConversacion($uid, $pid);
        $rol = self::texto($b['rol'] ?? 'user', 20);
        $contenido = self::texto($b['contenido'] ?? '', 20000);
        if ($contenido === '') self::error(422, 'mensaje_vacio', 'El mensaje está vacío.');
        $datos = is_array($b['datos'] ?? null) ? $b['datos'] : null;
        $modelo = self::texto($b['modelo'] ?? '', 150);
        $id = Asistente::agregarMensaje($conv, $rol, $contenido, $datos, $modelo, self::texto($b['uuid'] ?? '', 64) ?: null);
        if (!$id) self::error(500, 'guardado', 'No se pudo guardar el mensaje.');
        self::responder(['success' => true, 'id' => $id, 'conversacion_id' => $conv]);
    }

    private static function chatBorradores(): never
    {
        if (!Asistente::disponible()) self::responder(['success' => true, 'borradores' => []]);
        $pid = (int)($_GET['proyecto'] ?? 0);
        if ($pid > 0) {
            $p = Proyecto::find($pid);
            if (!$p || !puede_ver_proyecto($p)) self::error(404, 'no_encontrado', 'El proyecto no existe o no tienes acceso.');
        }
        self::responder(['success' => true, 'borradores' => Asistente::borradores((int)self::usuario()['id'], $pid)]);
    }

    private static function chatBorrador(): never
    {
        if (!Asistente::disponible()) self::error(503, 'asistente_no_disponible', 'Los borradores aún no están habilitados.');
        $b = self::cuerpo();
        $pid = (int)($b['proyecto'] ?? 0);
        $p = $pid > 0 ? Proyecto::find($pid) : null;
        if (!$p || !puede_ver_proyecto($p)) self::error(404, 'no_encontrado', 'El proyecto no existe o no tienes acceso.');
        if (!puede_gestionar_proyecto($p)) self::error(403, 'solo_lectura', 'No puedes crear borradores en esta obra.');
        $tipo = self::texto($b['tipo'] ?? '', 20);
        if (!in_array($tipo, Asistente::TIPOS, true)) self::error(422, 'tipo_invalido', 'Tipo de borrador no válido.');
        $datos = is_array($b['datos'] ?? null) ? $b['datos'] : [];
        $conv = (int)($b['conversacion_id'] ?? 0) ?: 0;
        if ($conv <= 0 || !Asistente::conversacion($conv, (int)self::usuario()['id'], false)) $conv = (int)Asistente::ultimaConversacion((int)self::usuario()['id'], $pid);
        $id = Asistente::crearBorrador((int)self::usuario()['id'], $pid, $tipo, $datos, $conv ?: null, self::texto($b['uuid'] ?? '', 64) ?: null);
        if (!$id) self::error(500, 'guardado', 'No se pudo guardar el borrador.');
        self::responder(['success' => true, 'id' => $id]);
    }

    private static function chatAplicar(): never
    {
        $b = self::cuerpo();
        $id = (int)($b['id'] ?? 0);
        $r = Asistente::aplicarBorrador($id, (int)self::usuario()['id'], false);
        self::responder(['success' => (bool)$r['ok'], 'mensaje' => $r['mensaje'], 'entidad_id' => (int)$r['entidad_id']], $r['ok'] ? 200 : 422);
    }

    private static function chatRechazar(): never
    {
        $b = self::cuerpo();
        $id = (int)($b['id'] ?? 0);
        $ok = Asistente::rechazarBorrador($id, (int)self::usuario()['id'], false);
        self::responder(['success' => $ok, 'mensaje' => $ok ? 'Borrador rechazado.' : 'No se pudo rechazar el borrador.'], $ok ? 200 : 422);
    }

    private static function modelos(): never
    {
        self::responder(['success' => true, 'modelos' => self::catalogoModelos()]);
    }

    // ---------- datos ----------

    private static function servidor(): array
    {
        $apk = ApkAndroid::ultima();
        return [
            'api' => self::VERSION,
            'app_min' => '0.1.0',
            'apk' => $apk ? [
                'version' => $apk['version'],
                'versionCode' => $apk['version_code'],
                'size' => $apk['tam'],
                'sha256' => $apk['sha'],
                'fecha' => date('c', $apk['fecha']),
                'notas' => $apk['notas'],
                'minSdk' => $apk['min_sdk'],
                'url' => '/api/android/descargar',
            ] : null,
        ];
    }

    private static function catalogoModelos(): array
    {
        // El catálogo real (archivo GGUF + SHA-256) se publica en la Fase 1 completa.
        return [];
    }

    private static function proyectosResumen(array $u): array
    {
        $out = [];
        foreach (Proyecto::visiblesPara($u) as $p) {
            $out[] = [
                'id' => (int)$p->id,
                'codigo' => (string)$p->codigo,
                'nombre' => (string)$p->nombre,
                'fecha_inicio' => (string)($p->fecha_inicio ?? ''),
                'fecha_fin' => (string)($p->fecha_fin ?? ''),
                'progreso' => round((float)$p->getProgreso(), 1),
                'archivado' => (int)($p->archivado ?? 0),
                'organizacion_id' => isset($p->organizacion_id) ? (int)$p->organizacion_id : null,
            ];
        }
        return $out;
    }

    private static function snapshot(Proyecto $p): array
    {
        $pid = (int)$p->id;
        $actividades = [];
        $restricciones = Restriccion::activasPorProyecto($pid);
        $restPorActividad = [];
        foreach ($restricciones as $r) $restPorActividad[(int)$r->actividad_id][] = $r;
        foreach ($p->getPartidasConActividades() as $bloque) {
            $pa = $bloque['partida'];
            foreach ($bloque['actividades'] as $a) {
                $actividades[] = [
                    'id' => (int)$a->id,
                    'partida_id' => (int)$a->partida_id,
                    'partida_codigo' => (string)($pa->codigo ?? ''),
                    'partida_nombre' => (string)($pa->nombre ?? ''),
                    'codigo' => (string)$a->codigo,
                    'nombre' => (string)$a->nombre,
                    'fecha_inicio' => (string)$a->fecha_inicio,
                    'fecha_fin' => (string)$a->fecha_fin,
                    'duracion_dias' => (int)($a->duracion_dias ?? 0),
                    'progreso' => (int)($a->progreso ?? 0),
                    'status' => (string)($a->status ?? ''),
                    'prioridad' => (string)($a->prioridad ?? 'media'),
                    'responsable' => (string)($a->responsable ?? ''),
                    'hito' => (int)($a->hito ?? 0),
                    'restriccion_tipo' => (string)($a->restriccion_tipo ?? 'ninguna'),
                    'restriccion_fecha' => $a->restriccion_fecha ?? null,
                    'icono' => $a->icono ?? null,
                    'restricciones_activas' => array_map(fn($r) => [
                        'id' => (int)$r->id, 'descripcion' => (string)($r->descripcion ?? ''), 'fecha' => $r->fecha ?? null,
                    ], $restPorActividad[(int)$a->id] ?? []),
                ];
            }
        }

        $punch = Punch::listar($pid);
        $rfis = Rfi::disponible() ? Rfi::lista($pid) : [];
        $equipo = Equipo::disponible() ? Equipo::lista($pid) : [];
        $servicios = Equipo::disponible() ? Equipo::serviciosProximos($pid, 30) : [];
        $contratos = Contratos::disponible() ? Contratos::lista($pid) : [];
        $bitacora = array_slice(Bitacora::deProyecto($pid), 0, 100);

        $materiales = self::filas("SELECT m.id, m.nombre, m.marca, m.modelo, m.cantidad, m.unidad, m.compra,
                m.fecha_entrega_prog, m.fecha_entrega_real,
                v.estado AS verificacion_estado, v.resumen AS verificacion_resumen, v.verificado, v.huella
            FROM materiales m LEFT JOIN material_verificaciones v ON v.material_id = m.id
            WHERE m.proyecto_id = $pid ORDER BY m.nombre LIMIT 1000");
        $aplicaciones = self::filas("SELECT a.entidad_id, a.referencia, a.requisito, a.nota, a.origen, a.estado,
                d.titulo documento, d.codigo, f.clausula, f.pagina
            FROM normativa_aplicaciones a
            LEFT JOIN normativa_documentos d ON d.id = a.documento_id
            LEFT JOIN normativa_fragmentos f ON f.id = a.fragmento_id
            WHERE a.proyecto_id = $pid AND a.entidad_tipo = 'material' AND a.estado = 'aceptada'
            ORDER BY a.id LIMIT 1500");
        $normPorMaterial = [];
        foreach ($aplicaciones as $ap) $normPorMaterial[(int)$ap['entidad_id']][] = $ap;
        foreach ($materiales as &$m) $m['normativa'] = $normPorMaterial[(int)$m['id']] ?? [];
        unset($m);

        $incidentes = Seguridad::incidentes($pid, 100);
        $atsPendientes = Seguridad::atsPendientes($pid);
        $segResumen = Seguridad::resumen($pid);
        $riesgos = self::filas("SELECT id, titulo, categoria, probabilidad, impacto, responsable, estado, fecha_compromiso
            FROM riesgos WHERE proyecto_id = $pid AND estado IN ('identificado','en_tratamiento') ORDER BY fecha_compromiso IS NULL, fecha_compromiso LIMIT 200");

        $recordatorios = self::recordatorios($p, $actividades, $punch, $rfis, $equipo, $contratos);

        return [
            'proyecto' => [
                'id' => $pid,
                'codigo' => (string)$p->codigo,
                'nombre' => (string)$p->nombre,
                'fecha_inicio' => (string)($p->fecha_inicio ?? ''),
                'fecha_fin' => (string)($p->fecha_fin ?? ''),
                'progreso' => round((float)$p->getProgreso(), 1),
                'archivado' => (int)($p->archivado ?? 0),
            ],
            'permisos' => [
                'editar' => puede_gestionar_proyecto($p),
                'borrar' => puede_eliminar_proyecto($p),
            ],
            'actividades' => $actividades,
            'punch' => $punch,
            'rfis' => $rfis,
            'materiales' => $materiales,
            'equipo' => $equipo,
            'archivos' => array_map(fn($a) => [
                'id' => (int)$a->id,
                'nombre' => (string)$a->nombre,
                'fecha' => (string)$a->fecha,
                'revision' => (string)($a->numero_revision ?? ''),
                'categoria' => (string)($a->categoria_nombre ?? ''),
                'tamano' => (int)$a->tamano,
                'extension' => strtolower(pathinfo((string)$a->archivo_original, PATHINFO_EXTENSION)),
            ], array_slice(Archivo::deProyecto($pid), 0, 300)),
            'parte' => [
                'personal' => array_map(fn($x) => [
                    'id' => (int)$x->id,
                    'nombre' => (string)$x->nombre,
                    'departamento' => (string)($x->departamento ?? ''),
                    'tarifa_hora' => (float)($x->tarifa_hora ?? 0),
                ], Personal::deProyecto($pid)),
                'turnos' => ReporteDiario::TURNOS,
                'causas_tiempo' => ReporteTiempo::CAUSAS,
                'tipos_material' => ReporteMaterial::TIPOS,
            ],
            'servicios' => $servicios,
            'contratos' => $contratos,
            'calendario' => [
                'jornada' => (function () use ($pid) {
                    $j = [];
                    foreach (self::filas("SELECT dia_semana, factor FROM calendario_jornada WHERE proyecto_id = $pid") as $f) $j[(int)$f['dia_semana']] = (float)$f['factor'];
                    return $j ?: [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 0.5, 7 => 0];
                })(),
                'festivos' => array_column(self::filas("SELECT fecha FROM calendario_festivos WHERE proyecto_id = $pid"), 'fecha'),
            ],
            'partes' => array_map(fn($r) => [
                'id' => (int)$r['id'],
                'fecha' => (string)$r['fecha'],
                'turno' => (string)$r['turno'],
                'area' => (string)($r['area'] ?? ''),
                'supervisor' => (string)($r['supervisor'] ?? ''),
                'estado' => (string)$r['estado'],
                'comentarios' => (string)($r['comentarios'] ?? ''),
                'personal' => (int)$r['personal'],
                'equipo' => (int)$r['equipo'],
                'materiales' => (int)$r['materiales'],
                'tiempos' => round((float)$r['tiempos'], 2),
                'horas_personal' => round((float)$r['horas_personal'], 2),
                'horas_equipo' => round((float)$r['horas_equipo'], 2),
            ], ReporteDiario::recientes($pid, 12)),
            'bitacora' => $bitacora,
            'seguridad' => [
                'resumen' => $segResumen,
                'incidentes' => $incidentes,
                'ats_pendientes' => $atsPendientes,
            ],
            'riesgos' => $riesgos,
            'recordatorios' => $recordatorios,
            'salud' => Salud::semaforo($p),
            'generado' => date('c'),
        ];
    }

    private static function recordatorios(Proyecto $p, array $actividades, array $punch, array $rfis, array $equipo, array $contratos): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $out = [];
        foreach ($punch as $x) {
            if (empty($x['fecha_limite']) || !in_array($x['estado'], ['abierto', 'en_proceso'], true)) continue;
            $out[] = [
                'id' => 'punch-' . (int)$x['id'], 'tipo' => 'punch', 'entidad_id' => (int)$x['id'],
                'titulo' => 'Punch: ' . (string)$x['titulo'], 'fecha' => (string)$x['fecha_limite'],
                'detalle' => trim((string)($x['ubicacion'] ?? '') . ' ' . (string)($x['responsable'] ?? '')),
                'nivel' => ($x['fecha_limite'] < $hoy ? 'critico' : (in_array($x['severidad'], ['alta', 'critica'], true) ? 'aviso' : 'ok')),
            ];
        }
        foreach ($rfis as $x) {
            if (empty($x['fecha_limite']) || $x['estado'] !== 'abierto') continue;
            $out[] = [
                'id' => 'rfi-' . (int)$x['id'], 'tipo' => 'rfi', 'entidad_id' => (int)$x['id'],
                'titulo' => 'RFI ' . (string)$x['numero'] . ': ' . (string)$x['asunto'], 'fecha' => (string)$x['fecha_limite'],
                'detalle' => (string)($x['responsable'] ?? ''), 'nivel' => ($x['fecha_limite'] < $hoy ? 'critico' : 'aviso'),
            ];
        }
        foreach ($equipo as $x) {
            if (empty($x['proximo_servicio'])) continue;
            $out[] = [
                'id' => 'equipo-' . (int)$x['id'], 'tipo' => 'equipo', 'entidad_id' => (int)$x['id'],
                'titulo' => 'Mantenimiento: ' . (string)$x['nombre'], 'fecha' => (string)$x['proximo_servicio'],
                'detalle' => trim((string)($x['marca'] ?? '') . ' ' . (string)($x['modelo'] ?? '') . ' · ' . (string)($x['estado'] ?? '')),
                'nivel' => ((string)$x['proximo_servicio'] <= $hoy ? 'critico' : 'aviso'),
            ];
        }
        foreach ($contratos as $x) {
            if (empty($x['vencimiento']) || ($x['estado'] ?? '') === 'cerrado') continue;
            $out[] = [
                'id' => 'contrato-' . (int)$x['id'], 'tipo' => 'contrato', 'entidad_id' => (int)$x['id'],
                'titulo' => 'Vence ' . (string)($x['tipo'] ?? 'contrato') . ': ' . (string)($x['numero'] ?? ''), 'fecha' => (string)$x['vencimiento'],
                'detalle' => (string)($x['contraparte'] ?? ''), 'nivel' => ((string)$x['vencimiento'] <= $hoy ? 'critico' : 'aviso'),
            ];
        }
        foreach ($actividades as $a) {
            if (!empty($a['hito']) || (int)$a['progreso'] >= 100 || empty($a['fecha_fin'])) continue;
            if ((string)$a['fecha_fin'] > date('Y-m-d', strtotime('+30 days'))) continue;
            $out[] = [
                'id' => 'actividad-' . (int)$a['id'], 'tipo' => 'actividad', 'entidad_id' => (int)$a['id'],
                'titulo' => 'Actividad: ' . (string)$a['nombre'], 'fecha' => (string)$a['fecha_fin'],
                'detalle' => trim(implode(' · ', array_filter([(string)$a['codigo'], (string)($a['responsable'] ?? '')]))),
                'nivel' => ((string)$a['fecha_fin'] < $hoy ? 'critico' : 'aviso'),
            ];
        }
        foreach (self::filas("SELECT id, nombre, fecha, tipo FROM proyecto_fechas_contrato WHERE proyecto_id = $pid") as $x) {
            $out[] = [
                'id' => 'hito-' . (int)$x['id'], 'tipo' => 'hito', 'entidad_id' => (int)$x['id'],
                'titulo' => (string)$x['nombre'], 'fecha' => (string)$x['fecha'], 'detalle' => (string)$x['tipo'],
                'nivel' => ((string)$x['fecha'] < $hoy ? 'critico' : 'aviso'),
            ];
        }
        foreach (self::filas("SELECT id, titulo, fecha_compromiso FROM riesgos WHERE proyecto_id = $pid AND estado IN ('identificado','en_tratamiento') AND fecha_compromiso IS NOT NULL") as $x) {
            $out[] = [
                'id' => 'riesgo-' . (int)$x['id'], 'tipo' => 'riesgo', 'entidad_id' => (int)$x['id'],
                'titulo' => 'Riesgo: ' . (string)$x['titulo'], 'fecha' => (string)$x['fecha_compromiso'], 'detalle' => '',
                'nivel' => ((string)$x['fecha_compromiso'] < $hoy ? 'critico' : 'aviso'),
            ];
        }
        usort($out, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
        return array_slice($out, 0, 200);
    }

    // ---------- operaciones offline (Fase 1) ----------

    private static function aplicarOperacion(array $op): array
    {
        $tipo = self::texto($op['tipo'] ?? '', 40);
        $uuid = self::texto($op['uuid'] ?? '', 64);
        $pid = (int)($op['proyecto'] ?? 0);
        if ($tipo === '' || $pid <= 0) return ['ok' => false, 'codigo' => 'operacion_invalida', 'mensaje' => 'Falta el tipo o el proyecto.'];
        if ($uuid !== '' && !preg_match('/^[A-Za-z0-9._-]{8,64}$/', $uuid)) return ['ok' => false, 'codigo' => 'uuid_invalido', 'mensaje' => 'El identificador de la operación no es válido.'];

        if ($uuid !== '' && self::operacionPrevia($uuid)) return self::operacionPrevia($uuid);

        $p = Proyecto::find($pid);
        if (!$p || !puede_ver_proyecto($p)) return ['ok' => false, 'codigo' => 'no_encontrado', 'mensaje' => 'El proyecto no existe o no tienes acceso.'];
        if ($tipo === 'chat_mensaje') {
            $uid = (int)self::usuario()['id'];
            $conv = (int)($op['conversacion_id'] ?? 0);
            if ($conv <= 0 || !Asistente::conversacion($conv, $uid, false)) $conv = Asistente::ultimaConversacion($uid, $pid);
            $id = Asistente::agregarMensaje($conv, self::texto($op['rol'] ?? 'user', 20), self::texto($op['contenido'] ?? '', 20000), is_array($op['datos'] ?? null) ? $op['datos'] : null, self::texto($op['modelo'] ?? '', 150), $uuid ?: null);
            $res = $id ? ['ok' => true, 'datos' => ['id' => $id, 'conversacion_id' => $conv]] : ['ok' => false, 'codigo' => 'guardado', 'mensaje' => 'No se pudo guardar el mensaje.'];
            $res['uuid'] = $uuid;
            if ($uuid !== '' && !empty($res['ok'])) self::guardarOperacion($uuid, $uid, $pid, $tipo, $res);
            return $res;
        }
        if (!puede_gestionar_proyecto($p)) return ['ok' => false, 'codigo' => 'solo_lectura', 'mensaje' => 'No puedes modificar este proyecto.'];

        $res = match ($tipo) {
            'actividad_progreso' => self::opActividad($p, $op),
            'bitacora_crear' => self::opBitacora($p, $op),
            'punch_crear' => self::opPunch($p, $op),
            'punch_estado' => self::opPunchEstado($p, $op),
            'rfi_crear' => self::opRfi($p, $op),
            'rfi_estado' => self::opRfiEstado($p, $op),
            'parte_diario' => self::opParteDiario($p, $op),
            'parte_cerrar' => self::opParteCerrar($p, $op),
            'evidencia_crear' => self::opEvidencia($p, $op),
            default => ['ok' => false, 'codigo' => 'tipo_desconocido', 'mensaje' => 'Tipo de operación no soportado: ' . $tipo],
        };
        $res['uuid'] = $uuid;
        if ($uuid !== '' && !empty($res['ok'])) self::guardarOperacion($uuid, (int)self::usuario()['id'], $pid, $tipo, $res);
        return $res;
    }

    private static function opParteDiario(Proyecto $p, array $op): array
    {
        $parte = $op['parte'] ?? null;
        if (!is_array($parte)) return ['ok' => false, 'codigo' => 'datos', 'mensaje' => 'Falta el parte diario.'];
        $fotos = is_array($parte['fotos_base64'] ?? null) ? array_slice($parte['fotos_base64'], 0, 5) : [];
        unset($parte['fotos_base64']);
        if (empty($parte['offline_uuid'])) $parte['offline_uuid'] = (string)($op['uuid'] ?? '');
        $r = ParteDiario::guardar($p, $parte, []);
        if (empty($r['ok'])) {
            $errores = array_values(array_filter((array)($r['errores'] ?? [])));
            return ['ok' => false, 'codigo' => 'parte', 'mensaje' => $errores ? implode(' ', $errores) : 'No se pudo guardar el parte diario.', 'datos' => ['errores' => $errores]];
        }
        $id = (int)($r['id'] ?? 0);
        $fotosGuardadas = $id > 0 && $fotos ? ParteDiario::adjuntarFotosBase64($p, $id, $fotos) : 0;
        return ['ok' => true, 'datos' => ['id' => $id, 'avisos' => $r['avisos'] ?? [], 'fotos' => $fotosGuardadas]];
    }

    private static function opEvidencia(Proyecto $p, array $op): array
    {
        $fecha = trim((string)($op['fecha'] ?? ''));
        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) $fecha = date('Y-m-d');
        $actividadId = (int)($op['actividad_id'] ?? 0);
        $actividad = Actividad::find($actividadId);
        $partida = $actividad ? Partida::find((int)$actividad->partida_id) : null;
        if (!$actividad || !$partida || (int)$partida->proyecto_id !== (int)$p->id) {
            return ['ok' => false, 'codigo' => 'datos', 'mensaje' => 'Elige una actividad válida para la evidencia.'];
        }
        $fotos = is_array($op['fotos_base64'] ?? null) ? array_slice($op['fotos_base64'], 0, 5) : [];
        if (!$fotos) return ['ok' => false, 'codigo' => 'datos', 'mensaje' => 'Adjunta al menos una foto.'];
        $descripcion = mb_substr(trim((string)($op['descripcion'] ?? '')), 0, 300);
        $departamento = mb_substr(trim((string)($op['departamento'] ?? '')), 0, 100);
        $categoria = mb_substr(trim((string)($op['categoria'] ?? '')), 0, 100);
        $uid = (int)self::usuario()['id'];
        $guardadas = 0;
        foreach ($fotos as $data) {
            if (!is_string($data) || !preg_match('#^data:image/(?:jpeg|jpg|png|webp);base64,(.+)$#s', $data, $m)) continue;
            $bin = base64_decode($m[1], true);
            if (!$bin || strlen($bin) > 15 * 1024 * 1024) continue;
            if (!Almacenamiento::cabe((int)$p->id, strlen($bin))) break;
            $tmp = tempnam(sys_get_temp_dir(), 'ev');
            if (!$tmp || file_put_contents($tmp, $bin) === false) { @unlink((string)$tmp); continue; }
            $nombre = Imagenes::guardarEn(Evidencia::CARPETA, $tmp, 1920, 85);
            @unlink($tmp);
            if (!$nombre) continue;
            (new Evidencia([
                'proyecto_id' => (int)$p->id,
                'actividad_id' => (int)$actividad->id,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'departamento' => $departamento,
                'categoria' => $categoria,
                'foto' => $nombre,
                'usuario_id' => $uid,
            ]))->guardar();
            $guardadas++;
        }
        if (!$guardadas) return ['ok' => false, 'codigo' => 'evidencia', 'mensaje' => 'No se pudo guardar ninguna foto.'];
        return ['ok' => true, 'datos' => ['fotos' => $guardadas, 'actividad_id' => (int)$actividad->id, 'fecha' => $fecha]];
    }

    private static function opParteCerrar(Proyecto $p, array $op): array
    {
        $uuid = (string)($op['offline_uuid'] ?? $op['uuid'] ?? '');
        $r = ReporteDiario::porOfflineUuid((int)$p->id, $uuid);
        if (!$r) return ['ok' => false, 'codigo' => 'no_encontrado', 'mensaje' => 'El parte todavía no se ha guardado.'];
        [$ok, $msg] = ParteDiario::cerrar($p, (int)$r->id);
        return ['ok' => $ok, 'codigo' => $ok ? '' : 'cerrar', 'mensaje' => $msg, 'datos' => ['id' => (int)$r->id]];
    }

    private static function opActividad(Proyecto $p, array $op): array
    {
        $id = (int)($op['id'] ?? 0);
        $progreso = max(0, min(100, (int)($op['progreso'] ?? -1)));
        if ($id <= 0 || $progreso < 0) return ['ok' => false, 'codigo' => 'datos', 'mensaje' => 'Falta la actividad o el avance.'];
        $a = Actividad::find($id);
        if (!$a) return ['ok' => false, 'codigo' => 'no_encontrado', 'mensaje' => 'La actividad no existe.'];
        $partida = Partida::find((int)$a->partida_id);
        if (!$partida || (int)$partida->proyecto_id !== (int)$p->id) return ['ok' => false, 'codigo' => 'no_encontrado', 'mensaje' => 'La actividad no pertenece al proyecto.'];
        $anterior = (int)$a->progreso;
        $conflicto = isset($op['base_progreso']) && (int)$op['base_progreso'] !== $anterior;
        $a->progreso = $progreso;
        $a->sincronizarStatus();
        $r = $a->guardar();
        $ok = is_array($r) ? !empty($r['resultado']) : (bool)$r;
        if (!$ok) return ['ok' => false, 'codigo' => 'guardado', 'mensaje' => 'No se pudo guardar el avance. Revisa restricciones o la compuerta de calidad.'];
        $partida->recalcularFechas();
        return ['ok' => true, 'datos' => ['id' => $id, 'progreso' => (int)$a->progreso, 'status' => $a->status, 'anterior' => $anterior, 'conflicto' => $conflicto]];
    }

    private static function opBitacora(Proyecto $p, array $op): array
    {
        $b = new Bitacora([
            'proyecto_id' => (int)$p->id,
            'fecha' => self::texto($op['fecha'] ?? date('Y-m-d'), 10),
            'departamento' => self::texto($op['departamento'] ?? '', 100),
            'descripcion' => self::texto($op['descripcion'] ?? '', 5000),
            'importancia' => self::texto($op['importancia'] ?? 'media', 10),
        ]);
        $alertas = $b->validar();
        if ($alertas) return ['ok' => false, 'codigo' => 'datos', 'mensaje' => implode(' ', array_merge(...array_values($alertas)))];
        $r = $b->guardar();
        $ok = is_array($r) ? !empty($r['resultado']) : (bool)$r;
        if (!$ok) return ['ok' => false, 'codigo' => 'guardado', 'mensaje' => 'No se pudo guardar la bitácora.'];
        return ['ok' => true, 'datos' => ['id' => (int)(is_array($r) ? ($r['id'] ?? $b->id) : $b->id)]];
    }

    private static function opPunch(Proyecto $p, array $op): array
    {
        [$ok, $msg, $id] = Punch::guardar((int)$p->id, [
            'titulo' => self::texto($op['titulo'] ?? '', 200),
            'descripcion' => self::texto($op['descripcion'] ?? '', 3000),
            'ubicacion' => self::texto($op['ubicacion'] ?? '', 200),
            'categoria' => self::texto($op['categoria'] ?? '', 60),
            'severidad' => self::texto($op['severidad'] ?? 'media', 20),
            'responsable' => self::texto($op['responsable'] ?? '', 150),
            'contratista' => self::texto($op['contratista'] ?? '', 150),
            'fecha_emision' => self::texto($op['fecha_emision'] ?? '', 10),
            'fecha_limite' => self::texto($op['fecha_limite'] ?? '', 10),
            'actividad_id' => (int)($op['actividad_id'] ?? 0),
        ], null, 'manual');
        return $ok ? ['ok' => true, 'datos' => ['id' => $id, 'mensaje' => $msg]] : ['ok' => false, 'codigo' => 'datos', 'mensaje' => $msg];
    }

    private static function opPunchEstado(Proyecto $p, array $op): array
    {
        $id = (int)($op['id'] ?? 0);
        if ($id <= 0) return ['ok' => false, 'codigo' => 'datos', 'mensaje' => 'Falta el pendiente.'];
        [$ok, $msg] = Punch::estado($p, $id, self::texto($op['estado'] ?? '', 20));
        return $ok ? ['ok' => true, 'datos' => ['id' => $id, 'estado' => self::texto($op['estado'] ?? '', 20)]] : ['ok' => false, 'codigo' => 'datos', 'mensaje' => $msg];
    }

    private static function opRfi(Proyecto $p, array $op): array
    {
        $error = Rfi::guardar((int)$p->id, [
            'asunto' => self::texto($op['asunto'] ?? '', 200),
            'pregunta' => self::texto($op['pregunta'] ?? '', 5000),
            'responsable' => self::texto($op['responsable'] ?? '', 150),
            'prioridad' => self::texto($op['prioridad'] ?? 'media', 20),
            'fecha' => self::texto($op['fecha'] ?? '', 10),
            'fecha_limite' => self::texto($op['fecha_limite'] ?? '', 10),
            'actividad_id' => (int)($op['actividad_id'] ?? 0),
        ], (string)(self::usuario()['nombre'] ?? 'App'));
        return $error === null ? ['ok' => true, 'datos' => ['id' => (int)Rfi::$ultimoId]] : ['ok' => false, 'codigo' => 'datos', 'mensaje' => $error];
    }

    private static function opRfiEstado(Proyecto $p, array $op): array
    {
        $id = (int)($op['id'] ?? 0);
        if ($id <= 0) return ['ok' => false, 'codigo' => 'datos', 'mensaje' => 'Falta el RFI.'];
        $estado = self::texto($op['estado'] ?? '', 20);
        $ok = Rfi::cambiarEstado((int)$p->id, $id, $estado);
        return $ok ? ['ok' => true, 'datos' => ['id' => $id, 'estado' => $estado]] : ['ok' => false, 'codigo' => 'datos', 'mensaje' => 'No se pudo cambiar el estado del RFI.'];
    }

    private static function operacionPrevia(string $uuid): ?array
    {
        if (!self::opsDisponible()) return null;
        $e = self::esc($uuid);
        $r = self::filas("SELECT resultado_json FROM app_operaciones WHERE uuid = '$e' LIMIT 1")[0] ?? null;
        if (!$r) return null;
        $j = json_decode((string)$r['resultado_json'], true);
        return is_array($j) ? $j : null;
    }

    private static function guardarOperacion(string $uuid, int $uid, int $pid, string $tipo, array $res): void
    {
        if (!self::opsDisponible()) return;
        $e = self::esc($uuid); $t = self::esc($tipo);
        $json = self::esc(json_encode($res, JSON_UNESCAPED_UNICODE) ?: '{}');
        try {
            self::db()->query("INSERT IGNORE INTO app_operaciones (uuid, usuario_id, proyecto_id, tipo, resultado_json)
                VALUES ('$e', $uid, $pid, '$t', '$json')");
        } catch (\Throwable $x) {
            error_log('AppApi: no se pudo guardar la operación: ' . $x->getMessage());
        }
    }

    private static function opsDisponible(): bool
    {
        static $hay = null;
        return $hay ??= (bool)self::filas("SHOW TABLES LIKE 'app_operaciones'");
    }
}
