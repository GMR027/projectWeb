<?php
// includes/funciones.php

function debuguear($variable): void
{
    echo "<pre style='background:#111;color:#0f0;padding:1rem;font-size:12px;'>";
    var_dump($variable);
    echo "</pre>";
    exit;
}

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

function puede_editar(): bool
{
    return is_admin();
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
 * Devuelve el nombre del archivo o null si no hay archivo válido.
 */
function guardar_imagen_subida(string $campo, string $carpeta): ?string
{
    if (empty($_FILES[$campo]['tmp_name']) || !is_uploaded_file($_FILES[$campo]['tmp_name'])) {
        return null;
    }
    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($_FILES[$campo]['tmp_name']);
    if (!isset($permitidos[$mime])) {
        return null;
    }
    $destino = __DIR__ . '/../public/build/img/' . $carpeta;
    if (!is_dir($destino)) mkdir($destino, 0775, true);
    $nombre = md5(uniqid('', true)) . '.' . $permitidos[$mime];
    return move_uploaded_file($_FILES[$campo]['tmp_name'], $destino . '/' . $nombre) ? $nombre : null;
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

function herramienta_permitida(?string $clave): bool
{
    if ($clave === null) return true;
    $h = herramientas_visitante();
    return $h === null || in_array($clave, $h, true);
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
