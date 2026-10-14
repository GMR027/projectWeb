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
    return htmlspecialchars($html ?? '', ENT_QUOTES, 'UTF-8');
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
 * Nombre de archivo de exportación: año-mes-día-[prefijo-]nombre-CODIGO-EMPRESA.ext
 * El prefijo (p. ej. el nombre de la curva) va antes del nombre que escribe el usuario.
 */
function generar_nombre_archivo(string $nombre_base, string $extension, ?\Model\Proyecto $proyecto = null, string $prefijo = ''): string
{
    $partes = [date('Y-m-d')];
    foreach ([$prefijo, $nombre_base, $proyecto->codigo ?? '', $proyecto->empresa ?? ''] as $texto) {
        if (trim($texto) !== '') $partes[] = slug_archivo($texto);
    }
    return implode('-', $partes) . ".{$extension}";
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
        in_array($ext, ['xls', 'xlsx', 'xlsm', 'ods', 'ots', 'csv'], true) => 'fa-file-excel',
        in_array($ext, ['ppt', 'pptx', 'odp', 'otp'], true) => 'fa-file-powerpoint',
        in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'svg', 'odg', 'otg'], true) => 'fa-file-image',
        in_array($ext, ['zip', 'rar', '7z'], true) => 'fa-file-zipper',
        in_array($ext, ['xml', 'dwg', 'dxf', 'mpp', 'pod'], true) => 'fa-file-code',
        default => 'fa-file',
    };
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
