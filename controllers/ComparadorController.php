<?php

namespace Controllers;

use MVC\Router;
use Model\Archivo;
use Model\ComparadorGuardado;
use Classes\Comparador;

/** Comparador de documentos: empareja dos imágenes o PDF (del proyecto o externos), los compara con un deslizador y guarda anotaciones */
class ComparadorController
{
    /** Tipos que se pueden comparar: extensión => tipo MIME (el SVG queda fuera a propósito) */
    private const TIPOS = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $fuentes = [];
        if (puede_descargar() || es_demo()) {   // leer un archivo del proyecto equivale a descargarlo (el demo puede: sus planos son de ejemplo)
            $fuentes = Comparador::fuentes($pid);
        }
        $guardados = puede_descargar() ? Comparador::listar($pid) : [];
        $abrir = null;
        $gid = (int)($_GET['guardado'] ?? 0);
        if ($gid && puede_descargar() && ($g = Comparador::obtener($pid, $gid))) {
            $abrir = $g->paraJs();
            $abrir['autor'] = (string)$g->creado_por;
            $abrir['puede_editar'] = Comparador::puedeEditar(['usuario_id' => $g->usuario_id]);
            $abrir['publico_url'] = $g->token ? Comparador::urlPublica((string)$g->token) : '';
        }
        $router->render('comparador/index', [
            'titulo' => 'Comparador de documentos - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'fuentes' => $fuentes, 'guardados' => $guardados, 'abrir' => $abrir,
            'puedeGuardar' => puede_descargar() && !is_visitante(),
            'historial' => $gid && puede_descargar() ? (int)($_GET['historial'] ?? 0) : 0,
            'aviso' => !empty($_GET['borrado']) ? 'Comparación eliminada.' : (!empty($_GET['duplicado']) ? 'Comparación duplicada.' : ''),
        ]);
    }

    /** Página pública de solo lectura de una comparación compartida: /comparador/publico?t=… */
    public static function publico(Router $router)
    {
        $g = Comparador::publicoPorToken(is_string($_GET['t'] ?? null) ? $_GET['t'] : '');
        if (!$g) {
            http_response_code(404);
            header('Cache-Control: no-store');
            $router->render('comparador/publico-no-disponible', ['titulo' => 'Enlace no disponible', 'publicoLayout' => true]);
            return;
        }
        $proyecto = \Model\Proyecto::find((int)$g->proyecto_id);
        if (!$proyecto) {
            http_response_code(404);
            $router->render('comparador/publico-no-disponible', ['titulo' => 'Enlace no disponible', 'publicoLayout' => true]);
            return;
        }
        $abrir = $g->paraJs();
        $abrir['autor'] = (string)$g->creado_por;
        $abrir['puede_editar'] = false;
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
        $router->render('comparador/index', [
            'titulo' => 'Comparación · ' . $g->nombre, 'proyecto' => $proyecto,
            'fuentes' => [], 'guardados' => [], 'abrir' => $abrir,
            'puedeGuardar' => false, 'historial' => 0, 'aviso' => '',
            'publicoLayout' => true, 'publico' => true, 'token' => (string)$g->token,
        ]);
    }

    /** Documento de una comparación compartida (sin sesión, solo con el token) */
    public static function publicoFuente()
    {
        $g = Comparador::publicoPorToken(is_string($_GET['t'] ?? null) ? $_GET['t'] : '');
        if (!$g) { http_response_code(404); exit('Enlace no disponible'); }
        $f = Comparador::fuentePublica($g, (string)($_GET['slot'] ?? ''));
        if (!$f) { http_response_code(404); exit('Documento no disponible'); }
        if (isset($f['bytes'])) self::entregarDatos($f['bytes'], $f['mime'], $f['nombre'] . '.' . $f['ext']);
        else self::entregar($f['ruta'], $f['mime']);
    }

    /** Crea (o devuelve) el enlace de solo lectura de una comparación */
    public static function compartir()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $g = Comparador::obtener((int)$p->id, (int)($_POST['id'] ?? 0));
        if (!$g) json_respuesta(['success' => false, 'error' => 'La comparación ya no existe.'], 404);
        if (!Comparador::puedeEditar(['usuario_id' => $g->usuario_id])) json_respuesta(['success' => false, 'error' => 'Solo quien la creó o el administrador pueden compartirla.'], 403);
        if (!empty($_POST['revocar'])) {
            Comparador::revocarToken((int)$p->id, (int)$g->id);
            json_respuesta(['success' => true, 'url' => '', 'revocado' => true]);
        }
        $t = Comparador::token((int)$p->id, (int)$g->id);
        if (!$t) json_respuesta(['success' => false, 'error' => 'No se pudo crear el enlace.'], 422);
        json_respuesta(['success' => true, 'url' => Comparador::urlPublica($t), 'token' => $t]);
    }

    /** Envía el enlace de la comparación a los destinos de Telegram del proyecto */
    public static function telegram()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $g = Comparador::obtener((int)$p->id, (int)($_POST['id'] ?? 0));
        if (!$g) json_respuesta(['success' => false, 'error' => 'La comparación ya no existe.'], 404);
        if (!\Classes\Telegram::disponible()) json_respuesta(['success' => false, 'error' => 'Telegram no está configurado.'], 422);
        $destinos = array_values(array_filter(\Classes\Telegram::destinos((int)$p->id), fn($d) => (int)($d['activo'] ?? 1) === 1));
        if (!$destinos) json_respuesta(['success' => false, 'error' => 'El proyecto no tiene destinos de Telegram.'], 422);
        $t = Comparador::token((int)$p->id, (int)$g->id);
        $url = $t ? Comparador::urlPublica($t) : '';
        $texto = mb_substr(trim((string)($_POST['mensaje'] ?? '')), 0, 600);
        $html = '<b>Comparación de documentos</b>' . "\n" . \Classes\Telegram::h($g->nombre) . "\n" . \Classes\Telegram::h((string)$p->nombre)
            . ($texto !== '' ? "\n\n" . \Classes\Telegram::h($texto) : '')
            . ($url !== '' ? "\n\n" . '<a href="' . \Classes\Telegram::h($url) . '">Abrir la comparación</a>' : '');
        $ok = 0;
        foreach ($destinos as $d) {
            [$bien] = \Classes\Telegram::enviar((int)$d['chat_id'], $html);
            if ($bien) $ok++;
        }
        $pdf = (string)($_POST['pdf'] ?? '');
        if ($pdf !== '' && $ok && preg_match('#^data:application/pdf;base64,#', $pdf)) {
            $bin = base64_decode(substr($pdf, strpos($pdf, ',') + 1), true);
            if ($bin !== false && strlen($bin) <= 14 * 1024 * 1024) {
                $tmp = tempnam(sys_get_temp_dir(), 'cmp');
                file_put_contents($tmp, $bin);
                foreach ($destinos as $d) \Classes\Telegram::enviarArchivo((int)$d['chat_id'], $tmp, 'Comparacion.pdf', $g->nombre);
                @unlink($tmp);
            }
        }
        if (!$ok) json_respuesta(['success' => false, 'error' => 'Telegram no aceptó el mensaje (revisa el token del bot).'], 422);
        json_respuesta(['success' => true, 'mensaje' => 'Enviado a ' . $ok . ' destino(s) de Telegram.']);
    }

    /** Historial de versiones de una comparación */
    public static function versiones()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $g = Comparador::obtener((int)$p->id, (int)($_GET['id'] ?? 0));
        if (!$g) json_respuesta(['success' => false, 'error' => 'La comparación ya no existe.'], 404);
        json_respuesta(['success' => true, 'versiones' => Comparador::versiones((int)$p->id, (int)$g->id)]);
    }

    /** Restaura una versión anterior y devuelve sus datos para pintarlos en el navegador */
    public static function restaurar()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $g = Comparador::obtener((int)$p->id, (int)($_POST['id'] ?? 0));
        if (!$g) json_respuesta(['success' => false, 'error' => 'La comparación ya no existe.'], 404);
        if (!Comparador::puedeEditar(['usuario_id' => $g->usuario_id])) json_respuesta(['success' => false, 'error' => 'Solo quien la creó o el administrador pueden restaurarla.'], 403);
        $datos = Comparador::restaurarVersion((int)$p->id, (int)$g->id, (int)($_POST['version'] ?? 0));
        if (!$datos) json_respuesta(['success' => false, 'error' => 'La versión ya no existe.'], 404);
        json_respuesta(['success' => true, 'datos' => $datos, 'mensaje' => 'Versión restaurada.']);
    }

    /** Entrega un documento del proyecto como fuente de comparación: /comparador/fuente?proyecto=…&tipo=vplan&id=12 */
    public static function fuente()
    {
        $proyecto = proyecto_de_peticion();
        if (!puede_descargar() && !es_demo()) { http_response_code(403); exit('No autorizado'); }
        $tipo = (string)($_GET['tipo'] ?? '');
        if (!in_array($tipo, Comparador::FUENTES_PROYECTO, true)) { http_response_code(404); exit('Fuente no disponible'); }
        $f = Comparador::fuenteDeProyecto((int)$proyecto->id, $tipo, (int)($_GET['id'] ?? 0));
        if (!$f) { http_response_code(404); exit('Documento no disponible'); }
        if (isset($f['bytes'])) self::entregarDatos($f['bytes'], $f['mime'], $f['nombre'] . '.' . $f['ext']);
        else self::entregar($f['ruta'], $f['mime']);
    }

    /** Entrega un archivo del proyecto (pdf o imagen) para que el navegador lo dibuje */
    public static function archivo()
    {
        redirigir_si_no_auth();
        $a = Archivo::find(filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT));
        $proyecto = $a ? \Model\Proyecto::find($a->proyecto_id) : null;
        if (!$proyecto) { http_response_code(404); exit; }
        exigir_ver_proyecto($proyecto);
        if (!puede_descargar() && !es_demo()) { http_response_code(403); exit('No autorizado'); }
        $ext = $a->extension();
        $ruta = $a->ruta();
        if (!isset(self::TIPOS[$ext]) || !$ruta) { http_response_code(404); exit('Archivo no disponible'); }
        self::entregar($ruta, self::TIPOS[$ext]);
    }

    /** Copia de un documento externo de una comparación guardada */
    public static function archivoGuardado()
    {
        $proyecto = proyecto_de_peticion();
        if (!puede_descargar()) { http_response_code(403); exit('No autorizado'); }
        $g = Comparador::obtener((int)$proyecto->id, (int)($_GET['id'] ?? 0));
        $archivo = $g ? Comparador::archivoSubido($g, (string)($_GET['slot'] ?? '')) : null;
        if (!$archivo) { http_response_code(404); exit('Archivo no disponible'); }
        self::entregar($archivo['ruta'], $archivo['mime']);
    }

    /** Miniatura de una comparación guardada */
    public static function miniatura()
    {
        $proyecto = proyecto_de_peticion();
        if (!puede_descargar()) { http_response_code(403); exit('No autorizado'); }
        $g = Comparador::obtener((int)$proyecto->id, (int)($_GET['id'] ?? 0));
        $ruta = $g ? Comparador::rutaMiniatura($g) : null;
        if (!$ruta) { http_response_code(404); exit; }
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    public static function guardar()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $g = Comparador::obtener((int)$p->id, $id);
            if (!$g) json_respuesta(['success' => false, 'error' => 'La comparación ya no existe.'], 404);
            if (!Comparador::puedeEditar(['usuario_id' => $g->usuario_id])) json_respuesta(['success' => false, 'error' => 'Solo quien la creó o el administrador pueden modificarla.'], 403);
        }
        [$ok, $msg, $nid] = Comparador::guardar($p, $_POST, $_FILES);
        if (!$ok) json_respuesta(['success' => false, 'error' => $msg], 422);
        $g = Comparador::obtener((int)$p->id, $nid);
        json_respuesta(['success' => true, 'mensaje' => $msg, 'id' => $nid, 'datos' => $g ? $g->paraJs() : null, 'tarjeta' => $g ? $g->tarjeta() : null]);
    }

    public static function eliminar()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $id = (int)($_POST['id'] ?? 0);
        $g = Comparador::obtener((int)$p->id, $id);
        if (!$g) json_respuesta(['success' => false, 'error' => 'La comparación ya no existe.'], 404);
        if (!Comparador::puedeEditar(['usuario_id' => $g->usuario_id])) json_respuesta(['success' => false, 'error' => 'Solo quien la creó o el administrador pueden eliminarla.'], 403);
        Comparador::eliminar((int)$p->id, $id);
        if (!empty($_POST['volver'])) { header('Location: /comparador?proyecto=' . (int)$p->id . '&borrado=1'); exit; }
        json_respuesta(['success' => true, 'mensaje' => 'Comparación eliminada.']);
    }

    /** Duplica una comparación (copia también los documentos externos) */
    public static function duplicar()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $g = Comparador::obtener((int)$p->id, (int)($_POST['id'] ?? 0));
        if (!$g) json_respuesta(['success' => false, 'error' => 'La comparación ya no existe.'], 404);
        $id = Comparador::duplicar($p, $g);
        if (!$id) json_respuesta(['success' => false, 'error' => 'No se pudo duplicar la comparación.'], 422);
        if (!empty($_POST['volver'])) { header('Location: /comparador?proyecto=' . (int)$p->id . '&duplicado=1'); exit; }
        json_respuesta(['success' => true, 'id' => $id, 'mensaje' => 'Comparación duplicada.']);
    }

    private static function entregar(string $ruta, string $mime): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($ruta) . '"');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    /** Entrega un documento generado en memoria (PDF del Redactor) */
    private static function entregarDatos(string $datos, string $mime, string $nombre): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . rawurlencode($nombre) . '"');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        header('Content-Length: ' . strlen($datos));
        echo $datos;
        exit;
    }
}
