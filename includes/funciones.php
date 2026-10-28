<?php
// includes/funciones.php

function s($html): string
{
    // Un arreglo manipulado en la URL (?error[]=…) no debe provocar un TypeError al escapar
    if (is_array($html) || is_object($html)) return '';
    return htmlspecialchars((string)($html ?? ''), ENT_QUOTES, 'UTF-8');
}

function pagina_actual($path): bool
{
    return str_contains($_SERVER['REQUEST_URI'] ?? '', $path);
}

/** ¿La petición llegó por HTTPS? (directo o detrás del Ingress) */
function es_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/**
 * Cabeceras de seguridad comunes a toda la aplicación. La CSP va en modo «Report-Only»: la plataforma usa
 * scripts y estilos en línea, así que primero se observan las violaciones en la consola antes de bloquear.
 */
function cabeceras_seguridad(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');          // no se puede incrustar en un sitio ajeno (clickjacking)
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(self), geolocation=(self)');
    // Todos los recursos (íconos, fuentes, PDF.js) se sirven desde el propio dominio: sin CDN externos.
    header("Content-Security-Policy-Report-Only: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "font-src 'self' data:; "
        . "img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; "
        . "frame-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'");
    if (es_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/**
 * Caducidad por inactividad de la web (la app de escritorio mantiene su sesión). Se llama al arrancar la
 * sesión; si pasaron más de 12 h sin actividad, se cierra antes de atender la petición.
 */
function sesion_por_inactividad(): void
{
    if (str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'ProjectWebDesktop')) return;
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['id'])) return;
    $limite = 12 * 3600;
    if (isset($_SESSION['_ultimo']) && (time() - (int)$_SESSION['_ultimo']) > $limite) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['_ultimo'] = time();
}

/**
 * Última red de seguridad: un error no controlado nunca debe dejar la página en blanco ni mostrar la
 * consulta SQL. Se registra con una referencia corta y se responde 500 (HTML o JSON según la petición).
 */
function manejar_error_no_controlado(\Throwable $e): void
{
    $ref = bin2hex(random_bytes(4));
    error_log("[error $ref] " . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (headers_sent()) {
        echo "\nError interno (ref. $ref)";
        return;
    }
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $json = str_starts_with($ruta, '/api/') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor', 'ref' => $ref], JSON_UNESCAPED_UNICODE);
        return;
    }
    header('Content-Type: text/html; charset=utf-8');
    $refE = htmlspecialchars($ref, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Error interno | ProjectWeb</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;color:#e2e8f0;font:16px/1.6 system-ui,sans-serif}'
        . '.c{max-width:44rem;padding:3rem;text-align:center}h1{font-size:2.2rem;margin:0 0 1rem}p{color:#94a3b8}code{background:#1e293b;padding:.2rem .5rem;border-radius:.4rem}'
        . 'a{display:inline-block;margin-top:1.6rem;padding:.8rem 1.6rem;border-radius:.8rem;background:#f97316;color:#fff;text-decoration:none;font-weight:600}</style></head><body><div class="c">'
        . '<h1>Algo salió mal</h1><p>El error quedó registrado. Vuelve a intentarlo; si sigue pasando, envía esta referencia al administrador.</p>'
        . '<p>Referencia: <code>' . $refE . '</code></p><a href="/dashboard">Volver al inicio</a></div></body></html>';
}

/** Usuario de solo lectura que se usa en el demo público (lo crea database/seed_demo.php) */
const DEMO_EMAIL = 'demo@projectweb.demo';

function es_demo(): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    return ($_SESSION['email'] ?? '') === DEMO_EMAIL;
}

/** Mensajes de contacto y soporte sin leer (bandeja del admin) */
function bandeja_sin_leer(): int
{
    return \Model\Contacto::total('leido = 0') + \Model\Soporte::total('leido = 0');
}

/** Respuestas del administrador que el cliente en sesión aún no ha visto (aviso en Soporte) */
function soporte_respuestas_nuevas(): int
{
    if (($_SESSION['rol'] ?? '') !== 'cliente' || empty($_SESSION['id'])) return 0;
    return \Model\SoporteRespuesta::nuevasDeUsuario((int)$_SESSION['id']);
}

function is_auth(): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['id']);
}

function is_admin(): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    return ($_SESSION['rol'] ?? '') === 'admin';
}

function is_cliente(): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    return in_array($_SESSION['rol'] ?? '', ['admin', 'cliente']);
}

function is_visitante(): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    return ($_SESSION['rol'] ?? 'visitante') === 'visitante';
}

/** Proyectos de los que el usuario es dueño */
function proyectos_creados_por(int $usuarioId): int
{
    return \Model\Proyecto::total('usuario_id = ' . $usuarioId);
}

/**
 * Cupo de proyectos de un cliente: ['usados' => n, 'limite' => n]. null si no aplica (admin: sin límite).
 */
function cupo_proyectos(): ?array
{
    if (!is_auth() || ($_SESSION['rol'] ?? '') !== 'cliente') return null;
    $u = \Model\Usuario::find((int)$_SESSION['id']);
    return ['usados' => proyectos_creados_por((int)$_SESSION['id']), 'limite' => (int)($u->limite_proyectos ?? 0)];
}

/** Admin: siempre. Cliente: mientras no alcance su límite de proyectos. Visitante: nunca. */
function puede_crear_proyecto(): bool
{
    if (is_admin()) return true;
    $cupo = cupo_proyectos();
    return $cupo !== null && $cupo['usados'] < $cupo['limite'];
}

function es_propietario($proyecto): bool
{
    return is_auth() && (int)$proyecto->usuario_id === (int)$_SESSION['id'];
}

/** Admin: todos. Cliente: los suyos. Visitante: ninguno de forma propia. */
function puede_gestionar_proyecto($proyecto): bool
{
    if (!$proyecto || !is_auth()) return false;
    if (is_admin()) return true;
    return ($_SESSION['rol'] ?? '') === 'cliente' && es_propietario($proyecto);
}

/** Admin: todos. Resto: propios o asignados por el admin. */
function puede_ver_proyecto($proyecto): bool
{
    if (!$proyecto || !is_auth()) return false;
    if (is_admin() || es_propietario($proyecto)) return true;
    return \Model\ProyectoAcceso::existe((int)$proyecto->id, (int)$_SESSION['id']);
}

function exigir_ver_proyecto($proyecto): void
{
    if (!puede_ver_proyecto($proyecto)) {
        header('Location: /proyectos');
        exit;
    }
}

function exigir_gestionar_proyecto($proyecto): void
{
    if (!puede_gestionar_proyecto($proyecto)) {
        header('Location: /proyectos');
        exit;
    }
}

/** Marcar revisiones (✅ ⚠ ❌ y «revisado»): admin y cliente con acceso al proyecto; el visitante solo las ve */
function puede_revisar($proyecto): bool
{
    return $proyecto && is_admin() && puede_ver_proyecto($proyecto);   // Revisiones: solo el administrador (ni clientes ni demo)
}

function puede_descargar(): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    return in_array($_SESSION['rol'] ?? '', ['admin', 'cliente']);
}

function usuario_actual(): ?array
{
    if (!is_auth()) return null;
    return [
        'id' => $_SESSION['id'],
        'nombre' => $_SESSION['nombre'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'rol' => $_SESSION['rol'] ?? 'visitante'
    ];
}

function redirigir_si_no_auth(): void
{
    if (!is_auth()) {
        header('Location: /login');
        exit;
    }
}

function redirigir_si_no_admin(): void
{
    redirigir_si_no_auth();
    if (!is_admin()) {
        header('Location: /dashboard');
        exit;
    }
}

function formatear_fecha($fecha, $formato = 'd/m/Y'): string
{
    if (!$fecha) return '';
    try {
        $dt = new DateTime($fecha);
        return $dt->format($formato);
    } catch (Exception $e) {
        return '';
    }
}

function numero_semana($fecha): int
{
    return (int) date('W', strtotime($fecha));
}

/**
 * Nombre de archivo de exportación: [prefijo]-[curva-]nombre[-CODIGO-EMPRESA][-sufijo].ext
 * Prefijo y sufijo los elige el usuario (ver nomenclatura_exportacion()); por defecto el prefijo es la fecha año-mes-día.
 * El prefijo del exportador (p. ej. el nombre de la curva) va antes del nombre que escribe el usuario.
 */
function generar_nombre_archivo(string $nombre_base, string $extension, ?\Model\Proyecto $proyecto = null, string $prefijo = ''): string
{
    $n = nomenclatura_exportacion();
    $partes = [];
    if ($t = nomenclatura_pieza($n['pre'])) $partes[] = $t;
    $medio = [$prefijo, $nombre_base];
    if ($n['cod']) { $medio[] = $proyecto->codigo ?? ''; $medio[] = $proyecto->empresa ?? ''; }
    foreach ($medio as $texto) {
        if (trim((string)$texto) !== '') $partes[] = slug_archivo($texto);
    }
    if ($t = nomenclatura_pieza($n['suf'])) $partes[] = $t;
    return implode('-', $partes) . ".{$extension}";
}

/** Tipos de prefijo/sufijo del nombre de exportación */
const NOMENCLATURA_TIPOS = ['ninguno', 'fecha_ymd', 'fecha_dmy', 'numerador', 'texto'];

/**
 * Nomenclatura vigente de la exportación (prefijo y sufijo + código/empresa). Sin configurar es la de siempre:
 * fecha año-mes-día al inicio y CÓDIGO-EMPRESA al final. Se fija con nomenclatura_desde_post().
 */
function nomenclatura_exportacion(?array $fijar = null): array
{
    static $actual = ['pre' => ['tipo' => 'fecha_ymd', 'valor' => ''], 'suf' => ['tipo' => 'ninguno', 'valor' => ''], 'cod' => true];
    if ($fijar !== null) $actual = $fijar;
    return $actual;
}

/** Lee del formulario (nom_pre_tipo, nom_pre_valor, nom_suf_tipo, nom_suf_valor, nom_cod) la nomenclatura elegida en el Centro de Exportación */
function nomenclatura_desde_post(array $p): void
{
    if (!isset($p['nom_pre_tipo']) && !isset($p['nom_suf_tipo'])) return;
    $pieza = function (string $k) use ($p) {
        $t = (string)($p["nom_{$k}_tipo"] ?? 'ninguno');
        return ['tipo' => in_array($t, NOMENCLATURA_TIPOS, true) ? $t : 'ninguno', 'valor' => mb_substr(trim((string)($p["nom_{$k}_valor"] ?? '')), 0, 60)];
    };
    nomenclatura_exportacion(['pre' => $pieza('pre'), 'suf' => $pieza('suf'), 'cod' => !empty($p['nom_cod'])]);
}

/** Texto de una pieza de la nomenclatura (vacío si no aplica) */
function nomenclatura_pieza(array $pz): string
{
    return match ($pz['tipo']) {
        'fecha_ymd' => date('Y-m-d'),
        'fecha_dmy' => date('d-m-Y'),
        'numerador' => str_pad((string)max(1, (int)$pz['valor']), 3, '0', STR_PAD_LEFT),
        'texto' => trim((string)$pz['valor']) !== '' ? slug_archivo($pz['valor']) : '',
        default => '',
    };
}


/**
 * Guarda una imagen subida (jpg/png/webp/gif) en public/build/img/{carpeta}.
 * Se vuelve a codificar con GD (quita EXIF, scripts incrustados y payloads), se limita el tamaño y se
 * decide el nombre y la extensión en el servidor. Devuelve el nombre o null si la imagen no es válida.
 */
function guardar_imagen_subida(string $campo, string $carpeta): ?string
{
    $f = $_FILES[$campo] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) {
        return null;
    }
    if ((int)($f['size'] ?? 0) > 10 * 1024 * 1024) return null;   // 10 MB
    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($f['tmp_name']);
    if (!isset($permitidos[$mime])) return null;
    $ext = $permitidos[$mime];
    try {
        $img = (new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver()))->read($f['tmp_name']);
        if ($img->width() > 2000 || $img->height() > 2000) $img->scaleDown(width: 2000, height: 2000);
        $destino = __DIR__ . '/../public/build/img/' . preg_replace('/[^a-z0-9_-]/', '', $carpeta);
        if (!is_dir($destino)) mkdir($destino, 0775, true);
        $nombre = bin2hex(random_bytes(16)) . '.' . $ext;
        $salida = in_array($ext, ['png', 'gif'], true) ? $img->toPng() : ($ext === 'webp' ? $img->toWebp(88) : $img->toJpeg(88));
        $salida->save($destino . '/' . $nombre);
        return is_file($destino . '/' . $nombre) ? $nombre : null;
    } catch (\Throwable $e) {
        error_log('Imagen subida: ' . $e->getMessage());
        return null;
    }
}

/** Texto seguro para nombres de archivo: sin acentos ni espacios ("Tubo Conduit 1/2" => "Tubo-Conduit-1-2") */
function slug_archivo(string $texto): string
{
    $texto = strtr($texto, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N']);
    $texto = preg_replace('/[^A-Za-z0-9]+/', '-', $texto);
    return trim($texto, '-') ?: 'sin-nombre';
}

/** Fecha compacta para móvil: 13/10/26 */
function fecha_movil($fecha): string
{
    $t = $fecha ? strtotime((string)$fecha) : false;
    return $t ? date('d/m/y', $t) : '—';
}

/** Icono Font Awesome según la extensión del archivo */
function archivo_icono(string $ext): string
{
    return match (true) {
        $ext === 'pdf' => 'fa-file-pdf',
        in_array($ext, ['doc', 'docx', 'odt', 'ott', 'rtf'], true) => 'fa-file-word',
        in_array($ext, ['xls', 'xlsx', 'ods', 'ots', 'csv'], true) => 'fa-file-excel',
        in_array($ext, ['ppt', 'pptx', 'odp', 'otp'], true) => 'fa-file-powerpoint',
        in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'svg', 'odg', 'otg'], true) => 'fa-file-image',
        in_array($ext, ['zip', 'rar', '7z'], true) => 'fa-file-zipper',
        $ext === 'sheetflow' => 'fa-table-cells',
        in_array($ext, ['xml', 'mpp', 'pod', 'kml'], true) => 'fa-file-code',
        in_array($ext, ['dwg', 'dxf', 'dwt', 'dws', 'dwf', 'dgn', 'rvt', 'rfa', 'rte', 'rft', 'ifc', 'ifczip', 'skp', 'nwd', 'nwc', '3dm', 'stp', 'step', 'obj'], true) => 'fa-drafting-compass',
        default => 'fa-file',
    };
}

/** Clase de color del icono según el tipo de archivo (PDF, documento, hoja de cálculo, imagen…) para distinguirlos de un vistazo */
function archivo_icono_clase(string $ext): string
{
    return match (true) {
        $ext === 'pdf' => 'ico-pdf',
        in_array($ext, ['doc', 'docx', 'odt', 'ott', 'rtf', 'txt'], true) => 'ico-doc',
        in_array($ext, ['xls', 'xlsx', 'ods', 'ots', 'csv'], true) => 'ico-hoja',
        in_array($ext, ['ppt', 'pptx', 'odp', 'otp'], true) => 'ico-pres',
        in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'svg', 'odg', 'otg'], true) => 'ico-img',
        in_array($ext, ['zip', 'rar', '7z'], true) => 'ico-zip',
        $ext === 'sheetflow' => 'ico-sheetflow',
        in_array($ext, ['xml', 'mpp', 'pod', 'kml'], true) => 'ico-codigo',
        in_array($ext, ['dwg', 'dxf', 'dwt', 'dws', 'dwf', 'dgn', 'rvt', 'rfa', 'rte', 'rft', 'ifc', 'ifczip', 'skp', 'nwd', 'nwc', '3dm', 'stp', 'step', 'obj'], true) => 'ico-cad',
        default => 'ico-otro',
    };
}

/** <i> del icono de un archivo por su extensión, con color propio del tipo */
function archivo_icono_html(string $ext, string $titulo = ''): string
{
    $ext = strtolower($ext);
    return '<i class="fa-solid ' . archivo_icono($ext) . ' ico-archivo ' . archivo_icono_clase($ext) . '"' . ($titulo !== '' ? ' title="' . htmlspecialchars($titulo, ENT_QUOTES) . '"' : '') . ' aria-hidden="true"></i>';
}

/** Proyecto indicado en la petición (campo "proyecto") validando permiso de ver o de gestionar */
function proyecto_de_peticion(bool $gestionar = false): \Model\Proyecto
{
    redirigir_si_no_auth();
    $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $proyecto = \Model\Proyecto::find(filter_var($fuente['proyecto'] ?? null, FILTER_VALIDATE_INT));
    if (!$proyecto) {
        header('Location: /proyectos');
        exit;
    }
    $gestionar ? exigir_gestionar_proyecto($proyecto) : exigir_ver_proyecto($proyecto);
    return $proyecto;
}

/** Entrega un archivo que ya está en memoria (cabeceras de descarga, o de vista en el navegador si $inline) y termina */
function enviar_descarga(string $datos, string $mime, string $nombre, bool $inline = false): void
{
    header_remove('Content-Type');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nombre . '"');
    header('Content-Length: ' . strlen($datos));
    echo $datos;
    exit;
}

/** Responde JSON y termina. Único punto de salida de las APIs (antes cada controlador tenía su copia) */
function json_respuesta(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/**
 * Proyecto de una petición de API (lee `proyecto` de POST o GET) o 403 en JSON.
 * $nivel: 'admin' (por defecto, herramientas de IA), 'gestionar' (admin o cliente dueño) o 'ver'.
 */
function proyecto_api(string $nivel = 'admin'): \Model\Proyecto
{
    $fuente = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $p = \Model\Proyecto::find(filter_var($fuente['proyecto'] ?? null, FILTER_VALIDATE_INT));
    $ok = $p && match ($nivel) {
        'gestionar' => puede_gestionar_proyecto($p),
        'ver' => puede_ver_proyecto($p),
        default => is_admin(),
    };
    if (!$ok) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
    return $p;
}

/** Fecha corta del MSR: "05-may-26" */
function fecha_corta_es($fecha): string
{
    if (!$fecha) return '';
    $m = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $t = strtotime($fecha);
    return date('d', $t) . '-' . $m[(int)date('n', $t)] . '-' . date('y', $t);
}

/** Fecha larga del encabezado del MSR: "martes, junio 09, 2026" */
function fecha_larga_es($fecha = null): string
{
    $t = $fecha ? strtotime($fecha) : time();
    $dias = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $dias[(int)date('N', $t)] . ', ' . $meses[(int)date('n', $t)] . ' ' . date('d, Y', $t);
}


/** ¿La petición viene de un modal (iframe)? Los formularios se muestran sin menú y, al guardar, avisan a la página padre */
function es_modal(): bool
{
    return ($_GET['modal'] ?? '') === '1';
}

/** Termina un guardado: en un modal avisa a la página que lo abrió; si no, redirige */
function terminar_guardado(string $destino, string $evento = 'actividad-guardada'): void
{
    if (es_modal()) {
        echo '<!DOCTYPE html><html><body><script>parent.postMessage({ tipo: ' . json_encode($evento) . ' }, location.origin);</script></body></html>';
        exit;
    }
    header('Location: ' . $destino);
    exit;
}


// ========== HERRAMIENTAS VISIBLES PARA EL VISITANTE ==========

/** Clave de la herramienta a la que pertenece una ruta (o null si la ruta no es una herramienta restringible) */
function herramienta_de_ruta(string $ruta): ?string
{
    $ruta = parse_url($ruta, PHP_URL_PATH) ?: '/';
    foreach (\Model\Usuario::HERRAMIENTAS as $clave => [, $prefijos]) {
        foreach ($prefijos as $p) {
            if ($ruta === $p || str_starts_with($ruta, $p . '/')) return $clave;
        }
    }
    return null;
}

/** Herramientas que el visitante en sesión puede usar (null = todas; siempre null para admin y cliente) */
function herramientas_visitante(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    if (!is_auth() || !is_visitante() || es_demo()) return $cache = null;
    $u = \Model\Usuario::find((int)$_SESSION['id']);
    return $cache = $u ? $u->herramientasPermitidas() : null;
}

/**
 * Rutas que no son una herramienta del proyecto y que un visitante siempre puede usar
 * (navegación general, soporte, enlaces públicos, media autenticada…).
 */
function ruta_general(string $ruta): bool
{
    $ruta = parse_url($ruta, PHP_URL_PATH) ?: '/';
    foreach (\Model\Usuario::RUTAS_GENERALES as $p) {
        if ($ruta === $p || str_starts_with($ruta, $p . '/')) return true;
    }
    return false;
}

/**
 * ¿El visitante puede usar esta herramienta? Con `$clave` se resuelve contra `Usuario::HERRAMIENTAS`.
 * Sin clave (ruta que no es una herramienta declarada) se aplica **denegar por defecto**: solo pasan
 * las rutas generales. Así una herramienta nueva no queda expuesta al visitante por olvido.
 */
function herramienta_permitida(?string $clave, ?string $ruta = null): bool
{
    $h = herramientas_visitante();
    if ($h === null) return true;   // admin, cliente o demo: sin restricción
    if ($clave !== null) return in_array($clave, $h, true);
    return ruta_general($ruta ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
}

/** Primera pantalla del proyecto a la que el usuario tiene acceso (el programa, salvo que se le haya ocultado) */
function url_inicio_proyecto(int $proyectoId): string
{
    $h = herramientas_visitante();
    $orden = ['programa', 'lookahead', 'vplan', 'lastplanner', 'reportes', 'bitacora', 'minutas', 'evidencias', 'archivos', 'curvas', 'msr', 'calendario', 'graficos'];
    foreach ($orden as $c) {
        if ($h === null || in_array($c, $h, true)) return \Model\Usuario::HERRAMIENTAS[$c][1][0] . '?proyecto=' . $proyectoId;
    }
    return '/proyectos';
}


// ========== PROTECCIÓN CSRF ==========
// Cada sesión tiene un código secreto. Se agrega solo a todos los formularios POST y a los fetch (ver csrf_inyectar) y el servidor
// rechaza cualquier POST que no lo traiga (public/index.php), así una página ajena no puede enviar formularios en nombre del usuario.

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

/** Comprueba el código de una petición que modifica datos; si falla, responde con el error y termina */
function csrf_verificar(): void
{
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) return;
    if (session_status() === PHP_SESSION_NONE) session_start();
    sesion_por_inactividad();   // una sesión vencida no puede seguir enviando formularios
    $enviado = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($enviado !== '' && hash_equals(csrf_token(), $enviado)) return;

    $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    // Una subida mayor que post_max_size llega vacía: no es un ataque, es un archivo demasiado grande
    $vacia = (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES);
    $msg = $vacia ? 'El archivo es demasiado grande para el servidor (revisa upload_max_filesize y post_max_size).' : 'La sesión caducó o la petición no es válida. Recarga la página e inténtalo de nuevo.';
    http_response_code(419);
    if (str_starts_with($ruta, '/api/') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    $volver = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '/';
    $host = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    if ($host && $host !== ($_SERVER['SERVER_NAME'] ?? '') && $host !== explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]) $volver = '/';
    header('Location: ' . $volver . '?error=' . urlencode($msg));
    exit;
}

/** Agrega el código a cada <form method="post"> (fuera de los <script>) y, en el <head>, el meta y la pieza que lo manda en fetch y en formularios creados por JavaScript */
function csrf_inyectar(string $html): string
{
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES);
    $partes = preg_split('#(<script\b.*?</script>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($partes as $i => $p) {
        if ($i % 2 === 0) {
            $partes[$i] = preg_replace_callback('#<form\b[^>]*\bmethod\s*=\s*["\']?post["\']?[^>]*>#i', fn($m) => $m[0] . '<input type="hidden" name="_csrf" value="' . $t . '">', $p);
        }
    }
    $html = implode('', $partes);
    $cabeza = '<meta name="csrf-token" content="' . $t . '"><script>(function(){var t=document.querySelector(\'meta[name=csrf-token]\').content,f=window.fetch;'
        . 'window.fetch=function(u,o){o=o||{};var m=String(o.method||(u&&u.method)||"GET").toUpperCase();if(m!=="GET"&&m!=="HEAD"){var url=typeof u==="string"?u:(u&&u.url)||"";if(!/^[a-z]+:/i.test(url)||url.indexOf(location.origin)===0){var h=new Headers(o.headers||(u&&u.headers)||{});h.set("X-CSRF-Token",t);o.headers=h;}}return f.call(this,u,o);};'
        . 'function poner(fm){if(!fm||String(fm.method).toLowerCase()!=="post"||fm.querySelector(\'input[name=_csrf]\'))return;var i=document.createElement("input");i.type="hidden";i.name="_csrf";i.value=t;fm.appendChild(i);}'
        . 'document.addEventListener("submit",function(e){poner(e.target)},true);var s=HTMLFormElement.prototype.submit;HTMLFormElement.prototype.submit=function(){poner(this);return s.call(this)};})();</script>';
    return preg_replace('#</head>#i', $cabeza . '</head>', $html, 1);
}

// ========== IP DEL CLIENTE ==========

/** ¿La IP está en alguno de los rangos (IP suelta o CIDR, separados por coma)? Sirve para IPv4 e IPv6 */
function ip_en_rangos(string $ip, string $rangos): bool
{
    $bin = @inet_pton($ip);
    if ($bin === false) return false;
    foreach (array_filter(array_map('trim', explode(',', $rangos))) as $r) {
        [$base, $bits] = array_pad(explode('/', $r, 2), 2, null);
        $b = @inet_pton($base);
        if ($b === false || strlen($b) !== strlen($bin)) continue;
        $bits = $bits === null ? strlen($b) * 8 : max(0, min(strlen($b) * 8, (int)$bits));
        $bytes = intdiv($bits, 8);
        if (substr($bin, 0, $bytes) !== substr($b, 0, $bytes)) continue;
        $resto = $bits % 8;
        if ($resto === 0 || (ord($bin[$bytes]) >> (8 - $resto)) === (ord($b[$bytes]) >> (8 - $resto))) return true;
    }
    return false;
}

/**
 * IP real del visitante. Detrás de un proxy (Ingress de Kubernetes) REMOTE_ADDR es la IP del proxy, así que solo si ésta
 * está en TRUSTED_PROXIES (.env, p. ej. "10.1.0.0/16") se lee X-Forwarded-For, de derecha a izquierda, saltando los proxies de confianza.
 * Sin TRUSTED_PROXIES se usa siempre REMOTE_ADDR (no se puede falsificar con cabeceras).
 */
function ip_cliente(): string
{
    $directa = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $confiables = trim((string)($_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?: ''));
    if ($confiables === '' || !ip_en_rangos($directa, $confiables)) return $directa;
    $lista = array_reverse(array_filter(array_map('trim', explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')))));
    foreach ($lista as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP) && !ip_en_rangos($ip, $confiables)) return $ip;
    }
    return $directa;
}

/**
 * Atributos de la burbuja de ayuda (src/js/ayuda.js) para un enlace de herramienta: título, qué hace y sección del manual.
 * Con $conTitulo la burbuja también muestra el nombre (útil en la barra de iconos, que no lo escribe).
 */
function atributos_ayuda(array $item, bool $conTitulo = false): string
{
    if (empty($item['ayuda'])) return $conTitulo ? ' title="' . s(t($item['label'])) . '"' : '';
    $proy = (int)($_GET['proyecto'] ?? $_POST['proyecto'] ?? 0);
    $manual = !empty($item['manual']) ? '/manual' . ($proy ? '?proyecto=' . $proy : '') . '#' . $item['manual'] : '';
    return ' data-ayuda="' . s(t($item['ayuda'])) . '"' . ($manual ? ' data-manual="' . s($manual) . '"' : '')
        . ($conTitulo ? ' data-ayuda-titulo="' . s(t($item['label'])) . '"' : '');
}

/**
 * Glosario de la plataforma (2026-10-25): el vocabulario interno se define UNA vez aquí y cualquier vista lo
 * reusa con `<?php echo glosario('lamina'); ?>` → burbuja de ayuda con título y botón a su sección del manual.
 * Así «lámina», «bloque», «cajetín», «estampa»… significan lo mismo en todas las herramientas.
 */
function glosario(string $termino): string
{
    static $G = null;
    $G ??= [
        'vplan' => ['vPlan', 'Grupo de láminas que se gestionan juntas (p. ej. una por área o disciplina): cada lámina lleva su plano, sus tablas y su programa.', 'vplan'],
        'lamina' => ['Lámina', 'Cada hoja del vPlan: un plano de fondo con bloques (tablas, títulos, estampas) colocados donde tú quieras.', 'vplan'],
        'bloque' => ['Bloque', 'Cada cosa que vive en una lámina: tabla del programa, título, logo, plano, estampa… Se mueve y redimensiona en «Acomodar bloques».', 'vplan'],
        'marcas' => ['Marcas', 'Lo que se dibuja sobre el plano (líneas, formas, textos, símbolos) con «Dibujar sobre el plano». Se guarda como imagen de la lámina.', 'vplan'],
        'partida' => ['Partida', 'Grupo de actividades del programa de obra (p. ej. «Cimentación»). Las tablas de tareas del vPlan salen de aquí.', 'programa'],
        'plano' => ['Plano', 'La imagen de fondo de la lámina: el dibujo técnico de la obra sobre el que se marcan cosas.', 'vplan'],
        'simbologia' => ['Simbología', 'La leyenda del dibujo (COMPLETE, IN PROGRESS, ON HOLD…). Se dibuja con el Editor y se elige por lámina.', 'simbolos'],
        'cajetin' => ['Cajetín', 'La tablita con los datos del dibujo (proyecto, título, fecha, revisión…) que se inserta con un clic y se llena sola.', 'simbolos'],
        'estampa' => ['Estampa inteligente', 'Bloque que se llena solo con datos del proyecto (fecha, avance, restricciones, actividades del día) y se actualiza en cada visita.', 'simbolos'],
        'recovery' => ['Recovery', 'Recuperación de una actividad: cuánto se recuperó cada día que se retrasó (las 5R del programa).', 'programa'],
        'tabla' => ['Tabla adicional', 'Tabla reutilizable del vPlan (punch list, restricciones, secundarias…) que se crea una vez y se inserta en cuantas láminas quieras.', 'vplan'],
        'sheetflow' => ['SheetFlow', 'Hojas de cálculo del proyecto: se crean aquí y se usan en vPlan, Editor y Redactor; las enlazadas se actualizan solas.', 'sheetflow'],
        'grafico' => ['Gráfico', 'Dibujo libre (croquis, diagrama, leyenda) hecho en el Editor; puede tener varias láminas, como una presentación.', 'editor'],
        'documento' => ['Documento', 'Documento de texto del proyecto (oficio, minuta, RFI…) con corrector, plantillas y exportación a Word, LibreOffice o PDF.', 'redactor'],
        'plantilla' => ['Plantilla', 'Punto de partida listo para editar: trae el formato armado y las marcas (proyecto, fecha, tu nombre) se llenan solas.', null],
        'departamento' => ['Departamento (carpeta)', 'Agrupa los documentos o las tablas de la lista; se renombra con el lápiz y cada uno se mueve con «Mover a…».', 'redactor'],
        'formula' => ['Fórmulas', 'Como en Excel: =A1*2, SUMA(A1:A5), SI(…). Las calcula el navegador y el resultado queda guardado con la tabla.', 'sheetflow'],
    ];
    $t = $G[strtolower(trim($termino))] ?? null;
    if (!$t) return '';
    $proy = (int)($_GET['proyecto'] ?? $_POST['proyecto'] ?? 0);
    $manual = $t[2] ? '/manual' . ($proy ? '?proyecto=' . $proy : '') . '#' . $t[2] : '';
    return ' data-ayuda="' . s(t($t[1])) . '" data-ayuda-titulo="' . s(t($t[0])) . '"' . ($manual ? ' data-manual="' . s($manual) . '"' : '');
}

/**
 * `<option>` de actividades agrupadas por partida; cada partida lleva su color (franja ● y fondo) para no confundirlas.
 * $actividades: salida de `Dependencias::actividades()`. $sel: id seleccionado. $largo: recorte del nombre.
 */
function opciones_actividades(array $actividades, $sel = null, int $largo = 60): string
{
    $grupos = [];
    foreach ($actividades as $id => $a) $grupos[$a['partida_id'] ?? 0][] = [(int)($a['id'] ?? $id), $a];
    $h = '';
    foreach ($grupos as $acts) {
        $p = $acts[0][1];
        $col = preg_match('/^#[0-9a-f]{6}$/i', (string)($p['color'] ?? '')) ? $p['color'] : '#64748b';
        $h .= '<optgroup label="● ' . s(trim(($p['partida_codigo'] ?? '') . ' ' . ($p['partida'] ?? ''))) . '" style="background:' . $col . ';color:#fff">';
        foreach ($acts as [$id, $a]) $h .= '<option value="' . $id . '"' . ((int)$sel === $id ? ' selected' : '') . ' style="background:' . $col . '33;color:inherit">● ' . s($a['codigo'] . ' ' . mb_substr($a['nombre'], 0, $largo)) . '</option>';
        $h .= '</optgroup>';
    }
    return $h;
}

/**
 * Estado vacío ilustrado (componente compartido `.estado-vacio` de componentes/_estados.scss): icono,
 * título, texto de una línea y hasta dos acciones. Sustituye al «Aún no hay…» en gris de las listas:
 * el usuario nuevo ve qué hacer, no solo que no hay nada.
 * $accion / $secundaria = ['texto', 'url', 'icono' (fa-solid opcional)]; $extra = HTML adicional dentro.
 */
function estado_vacio(string $icono, string $titulo, string $texto, ?array $accion = null, ?array $secundaria = null, string $extra = '', string $clase = ''): string
{
    $btn = function (array $a, string $clase): string {
        if (empty($a['url'])) return '';
        return '<a class="btn ' . $clase . '" href="' . s($a['url']) . '">' . (empty($a['icono']) ? '' : '<i class="fa-solid ' . s($a['icono']) . '"></i> ') . s($a['texto']) . '</a>';
    };
    $acciones = $btn($accion ?? [], 'btn-primary') . $btn($secundaria ?? [], 'btn-ghost');
    return '<div class="estado-vacio' . ($clase !== '' ? ' ' . s($clase) : '') . '"><i class="fa-solid ' . s($icono) . '"></i><b>' . s(t($titulo)) . '</b><p>' . s(t($texto)) . '</p>'
        . ($acciones ? '<div class="estado-vacio-acciones">' . $acciones . '</div>' : '')
        . $extra . '</div>';
}

/** Registros por página de las listas del proyecto (Ajustes del proyecto; 10 por defecto). Si falta la migración, 10. */
function registros_por_pagina($proyecto): int
{
    static $cache = [];
    $id = (int)(is_object($proyecto) ? $proyecto->id : $proyecto);
    if (!isset($cache[$id])) {
        $n = 10;
        try {
            $r = $GLOBALS['db']->query("SELECT registros_por_pagina FROM proyectos WHERE id = $id");
            if ($r && ($f = $r->fetch_row())) $n = (int)$f[0];
        } catch (\Throwable $e) {
        }
        $cache[$id] = max(1, min(500, $n ?: 10));
    }
    return $cache[$id];
}

/** Atributo que activa el paginador (src/js/paginador.js) en una tabla o lista: `<table class="tabla" <?php echo pag_attr($proyecto); ?>>` */
function pag_attr($proyecto): string
{
    return 'data-paginar="' . registros_por_pagina($proyecto) . '"';
}
