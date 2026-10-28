<?php

namespace Classes;

use Model\Proyecto;

/**
 * Entrega las imágenes de los proyectos que viven en `public/uploads` (evidencias, reportes, punch,
 * materiales, personal, planos del vPlan, símbolos, firmas y fotos de soporte).
 *
 * Antes se servían como archivos estáticos: cualquiera con la URL las veía. Ahora `/uploads/…` se reescribe
 * a `index.php` (Apache y servidor de desarrollo) y solo se entrega el archivo si la persona puede ver el
 * proyecto —o si trae un token válido del enlace público del cliente—.
 *
 * No se mueven los archivos de sitio: Dompdf los lee del disco con `chroot` en `public/` y los respaldos
 * siguen funcionando igual.
 */
class Media
{
    use AccesoDb;

    /** MIME con el que se entrega cada extensión (solo imágenes) */
    private const MIMES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
    ];

    /** Atiende la petición de `/uploads/...` y termina */
    public static function atender(): never
    {
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (!preg_match('#^/uploads/([a-z_]{2,20})/(?:min/)?([A-Za-z0-9][A-Za-z0-9._-]{0,120})$#', $ruta, $m)) {
            self::noEncontrado();
        }
        $carpeta = $m[1];
        $archivo = basename($m[2]);
        // Solo nombres generados por el servidor: 32 hex + extensión de imagen.
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|gif|webp|svg)$/', $archivo, $ext)) {
            self::noEncontrado();
        }
        $disco = __DIR__ . '/../public/uploads/' . $carpeta . '/' . (str_contains($ruta, '/min/') ? 'min/' : '') . $archivo;
        $real = realpath($disco);
        $raiz = realpath(__DIR__ . '/../public/uploads');
        if (!$real || !$raiz || !str_starts_with($real, $raiz . DIRECTORY_SEPARATOR) || !is_file($real)) {
            self::noEncontrado();
        }

        // 0) Si el visitante tiene ocultas las herramientas donde se usa esa imagen, tampoco la ve.
        $herramientas = [
            'evidencias' => ['evidencias'], 'reportes' => ['reportes', 'evidencias'], 'punch' => ['punch', 'vplan'],
            'materiales' => ['materiales', 'submittals', 'msr'], 'personal' => ['minutas', 'reportes', 'recursos', 'redactor'],
            'vplan' => ['vplan', 'graficos', 'submittals', 'view360', 'redactor'], 'simbolos' => ['graficos', 'vplan'],
            'firmas' => ['redactor'], 'soporte' => [],
        ][$carpeta] ?? [];
        if ($herramientas && !array_filter($herramientas, fn($k) => herramienta_permitida($k))) self::prohibido();

        // 1) Recurso de un proyecto: se permite a quien puede verlo.
        $esMiniatura = str_contains($ruta, '/min/');
        $pid = self::proyectoDe($carpeta, $archivo, $esMiniatura);
        if ($pid !== null) {
            if (!self::puedeVerProyecto($pid)) self::prohibido();
            self::entregar($real, $ext[1]);
        }

        // 2) Firmas manuscritas y fotos de soporte: son de una persona, no de un proyecto.
        if ($carpeta === 'firmas' && self::duenoDe('firmas', $archivo)) self::entregar($real, $ext[1]);
        if ($carpeta === 'soporte' && self::duenoDe('soporte', $archivo)) self::entregar($real, $ext[1]);

        self::noEncontrado();
    }

    /** Proyecto dueño del archivo, o null si ninguna tabla lo referencia */
    private static function proyectoDe(string $carpeta, string $archivo, bool $esMiniatura = false): ?int
    {
        $a = self::esc($archivo);
        if ($esMiniatura && $carpeta === 'punch') {
            // min/<hash>.jpg es la miniatura de <hash>.png o <hash>.jpg
            $base = self::esc(pathinfo($archivo, PATHINFO_FILENAME));
            $sql = "SELECT proyecto_id FROM punch_items WHERE foto_antes LIKE '$base.%' OR foto_despues LIKE '$base.%' LIMIT 1";
            $id = (int)(self::filas($sql)[0]['proyecto_id'] ?? 0);
            return $id ?: null;
        }
        $sql = match ($carpeta) {
            'evidencias' => "SELECT proyecto_id FROM evidencias WHERE foto = '$a' LIMIT 1",
            'materiales' => "SELECT proyecto_id FROM materiales WHERE imagen = '$a' LIMIT 1",
            'personal' => "SELECT proyecto_id FROM personal WHERE imagen = '$a' LIMIT 1",
            'simbolos' => "SELECT proyecto_id FROM simbolos WHERE archivo = '$a' LIMIT 1",
            'reportes' => "SELECT proyecto_id FROM reportes_diarios WHERE fotos_json LIKE '%\"$a\"%' LIMIT 1",
            'punch' => "SELECT proyecto_id FROM punch_items WHERE foto_antes = '$a' OR foto_despues = '$a' LIMIT 1",
            'vplan' => "SELECT proyecto_id FROM vplan_laminas WHERE plano = '$a' OR simbologia = '$a' OR marcas_png = '$a' OR marcas_json LIKE '%$a%'"
                . " UNION SELECT g.proyecto_id FROM editor_laminas l JOIN editor_graficos g ON g.id = l.grafico_id WHERE l.marcas_png = '$a' OR l.marcas_json LIKE '%$a%'"
                . " UNION SELECT proyecto_id FROM editor_graficos WHERE fondo_imagen = '$a' LIMIT 1",
            default => '',
        };
        if ($sql === '') return null;
        $id = (int)(self::filas($sql)[0]['proyecto_id'] ?? 0);
        return $id ?: null;
    }

    /** ¿La petición puede ver ese proyecto? (sesión con acceso, o token del enlace público del cliente) */
    private static function puedeVerProyecto(int $pid): bool
    {
        $token = (string)($_GET['t'] ?? '');
        if ($token !== '' && Enlaces::proyectoDeToken($token, $pid)) return true;
        if (!is_auth()) return false;
        $p = Proyecto::find($pid);
        return $p && puede_ver_proyecto($p);
    }

    /** Firmas y fotos de soporte: solo su dueño o el administrador */
    private static function duenoDe(string $carpeta, string $archivo): bool
    {
        if (!is_auth()) return false;
        if (is_admin()) return true;
        $a = self::esc($archivo);
        $id = (int)($_SESSION['id'] ?? 0);
        $fila = $carpeta === 'firmas'
            ? self::filas("SELECT usuario_id FROM redactor_firmas WHERE archivo = '$a' LIMIT 1")[0] ?? []
            : self::filas("SELECT s.usuario_id FROM soporte_respuesta_fotos f JOIN soporte_respuestas r ON r.id = f.respuesta_id JOIN soportes s ON s.id = r.soporte_id WHERE f.archivo = '$a' LIMIT 1")[0] ?? [];
        return (int)($fila['usuario_id'] ?? 0) === $id;
    }

    private static function entregar(string $real, string $ext): never
    {
        header('Content-Type: ' . (self::MIMES[$ext] ?? 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");   // un SVG no ejecuta scripts aunque se abra solo
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . filesize($real));
        readfile($real);
        exit;
    }

    private static function noEncontrado(): never
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Archivo no encontrado');
    }

    private static function prohibido(): never
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('No autorizado');
    }
}
