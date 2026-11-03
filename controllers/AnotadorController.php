<?php

namespace Controllers;

use MVC\Router;
use Model\AnotadorDocumento;
use Model\Proyecto;
use Classes\Anotador;
use Classes\RedactorFirmas;
use Classes\Estampas;
use Classes\Pdf;
use Classes\PdfRaster;
use Model\Estampa;

/** Anotador: anota planos y documentos PDF, mide áreas y volúmenes, inserta imágenes, estampas y firmas */
class AnotadorController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        if (random_int(1, 100) === 1) Anotador::limpiarHuerfanos($pid);
        $fuentes = [];
        if (puede_descargar() || es_demo()) {
            $fuentes = Anotador::fuentes($pid);
        }
        $documentos = puede_descargar() ? Anotador::listar($pid) : [];
        $abrir = null;
        $did = (int)($_GET['id'] ?? 0);
        if ($did && puede_descargar() && ($d = Anotador::obtener($pid, $did))) {
            $abrir = $d->paraJs();
        }
        $router->render('anotador/index', [
            'titulo' => 'Anotador - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'fuentes' => $fuentes, 'documentos' => $documentos, 'abrir' => $abrir,
            'puedeGuardar' => puede_gestionar_proyecto($proyecto),
            'aviso' => !empty($_GET['borrado']) ? 'Documento eliminado.' : (!empty($_GET['duplicado']) ? 'Documento duplicado.' : ''),
            'historial' => $did && puede_descargar() ? (int)($_GET['historial'] ?? 0) : 0,
        ]);
    }

    /** Documento base: una copia guardada o una fuente del proyecto */
    public static function fuente()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar() || !herramienta_permitida('anotador')) { http_response_code(403); exit('No autorizado'); }
        $pid = (int)$p->id;
        $f = null;
        $docId = (int)($_GET['doc'] ?? 0);
        if ($docId) {
            $d = Anotador::obtener($pid, $docId);
            $f = $d ? Anotador::fuenteDocumento($d) : null;
        } else {
            $tipo = texto_param($_GET['tipo'] ?? '');
            $id = (int)($_GET['id'] ?? 0);
            if (in_array($tipo, Anotador::FUENTES_PROYECTO, true) && $id > 0) $f = Anotador::fuenteDeProyecto($pid, $tipo, $id);
        }
        if (!$f) { http_response_code(404); exit('Documento no disponible'); }
        if (isset($f['bytes'])) self::entregarDatos($f['bytes'], $f['mime'], $f['nombre'] . '.' . $f['ext']);
        else self::entregar($f['ruta'], $f['mime'], $f['nombre'] . '.' . $f['ext']);
    }

    /** Miniatura de un documento guardado (nunca estática) */
    public static function miniatura()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar() || !herramienta_permitida('anotador')) { http_response_code(403); exit('No autorizado'); }
        $d = Anotador::obtener((int)$p->id, (int)($_GET['id'] ?? 0));
        if (!$d || !$d->miniatura) { http_response_code(404); exit; }
        $ruta = Anotador::MINIATURAS . '/' . basename((string)$d->miniatura);
        if (!is_file($ruta)) { http_response_code(404); exit; }
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=300');
        readfile($ruta);
        exit;
    }

    public static function guardar()
    {
        $p = proyecto_api('ver');
        if (!puede_gestionar_proyecto($p)) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $r = Anotador::guardar($p, $_POST, $_FILES);
        if (!$r['ok']) json_respuesta(['success' => false, 'error' => $r['error']], 422);
        json_respuesta(['success' => true, 'id' => $r['id']]);
    }

    public static function eliminar()
    {
        $p = proyecto_api('ver');
        if (!puede_gestionar_proyecto($p)) { header('Location: /anotador?proyecto=' . (int)$p->id . '&error=permiso'); exit; }
        $d = Anotador::obtener((int)$p->id, (int)($_POST['id'] ?? 0));
        if ($d && !Anotador::puedeEditar(['usuario_id' => $d->usuario_id, 'finalizado' => false])) {
            header('Location: /anotador?proyecto=' . (int)$p->id . '&error=permiso');
            exit;
        }
        if ($d) Anotador::eliminar((int)$p->id, (int)$d->id);
        header('Location: /anotador?proyecto=' . (int)$p->id . '&borrado=1');
        exit;
    }

    public static function duplicar()
    {
        $p = proyecto_api('ver');
        if (!puede_gestionar_proyecto($p)) { header('Location: /anotador?proyecto=' . (int)$p->id . '&error=permiso'); exit; }
        $d = Anotador::obtener((int)$p->id, (int)($_POST['id'] ?? 0));
        if ($d) Anotador::duplicar($p, $d);
        header('Location: /anotador?proyecto=' . (int)$p->id . '&duplicado=1');
        exit;
    }

    public static function versiones()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        json_respuesta(['success' => true, 'versiones' => Anotador::versiones((int)$p->id, (int)($_GET['id'] ?? 0))]);
    }

    public static function restaurar()
    {
        $p = proyecto_api('ver');
        if (!puede_gestionar_proyecto($p)) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        exigir_eliminar_contenido($p);   // restaurar una versión pisa la actual: solo dueño o admin
        $r = Anotador::restaurarVersion((int)$p->id, (int)($_POST['id'] ?? 0), (int)($_POST['version'] ?? 0));
        if (!$r) json_respuesta(['success' => false, 'error' => 'No se pudo restaurar la versión.'], 422);
        json_respuesta(['success' => true]);
    }

    /** Imagen insertable: archivo o PNG en data URI; devuelve la copia del proyecto */
    public static function imagen()
    {
        $p = proyecto_api('ver');
        if (!puede_gestionar_proyecto($p)) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $r = Anotador::guardarImagen($p, $_FILES, $_POST);
        if (!$r) json_respuesta(['success' => false, 'error' => 'Imagen no válida o espacio insuficiente.'], 422);
        json_respuesta(['success' => true] + $r);
    }

    /** Imagen del Anotador (incluye las que aún no están referenciadas por un documento guardado) */
    public static function imagenVer()
    {
        $p = proyecto_de_peticion();
        if (!puede_descargar() || !herramienta_permitida('anotador')) { http_response_code(403); exit('No autorizado'); }
        $n = basename(texto_param($_GET['archivo'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}\.(?:png|jpg|jpeg|webp|gif)$/i', $n)) { http_response_code(404); exit; }
        $ruta = Anotador::CARPETA . '/' . $n;
        if (!is_file($ruta)) { http_response_code(404); exit; }
        $mime = mime_content_type($ruta) ?: 'image/png';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=300');
        readfile($ruta);
        exit;
    }

    /** Firmas personales del usuario (biblioteca del Redactor) */
    public static function firmas()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $firmas = array_map(function ($f) use ($p) {
            $f['src'] = '/anotador/firma-ver?proyecto=' . (int)$p->id . '&id=' . (int)$f['id'];
            return $f;
        }, RedactorFirmas::deUsuario((int)($_SESSION['id'] ?? 0)));
        json_respuesta(['success' => true, 'firmas' => $firmas]);
    }

    /** Entrega una firma del usuario aunque el módulo Redactor esté oculto */
    public static function firmaVer()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar() || !herramienta_permitida('anotador')) { http_response_code(403); exit('No autorizado'); }
        $n = RedactorFirmas::archivoDe((int)($_SESSION['id'] ?? 0), (int)($_GET['id'] ?? 0));
        $ruta = RedactorFirmas::CARPETA . '/' . (string)$n;
        if (!$n || !is_file($ruta)) { http_response_code(404); exit; }
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=600');
        readfile($ruta);
        exit;
    }

    /** Guarda una firma dibujada: copia visible en el proyecto + biblioteca personal */
    public static function firma()
    {
        $p = proyecto_api('ver');
        if (!puede_gestionar_proyecto($p)) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $r = Anotador::guardarFirma($p, texto_param($_POST['nombre'] ?? ''), texto_param($_POST['png'] ?? ''), (int)($_SESSION['id'] ?? 0));
        if (is_string($r)) json_respuesta(['success' => false, 'error' => $r], 422);
        if (!empty($r['biblioteca']['id'])) $r['biblioteca']['src'] = '/anotador/firma-ver?proyecto=' . (int)$p->id . '&id=' . (int)$r['biblioteca']['id'];
        json_respuesta(['success' => true] + $r);
    }

    /** Estampas inteligentes del proyecto para insertar en el plano */
    public static function estampas()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar() || !herramienta_permitida('anotador')) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $out = [];
        try {
            foreach (Estampa::deProyecto((int)$p->id) as $e) {
                $c = $e->config();
                $dim = Estampas::dimensiones($c);
                $out[] = ['id' => (int)$e->id, 'nombre' => (string)$e->nombre, 'w' => round($dim[0]), 'h' => round($dim[1])];
            }
        } catch (\Throwable $ex) {
        }
        json_respuesta(['success' => true, 'estampas' => $out]);
    }

    /** Convierte una estampa inteligente en PNG para colocarla sobre el plano (con caché por día) */
    public static function estampa()
    {
        $p = proyecto_api('ver');
        if (!puede_descargar() || !herramienta_permitida('anotador')) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $e = Estampa::find((int)($_GET['id'] ?? 0));
        if (!$e || (int)$e->proyecto_id !== (int)$p->id) json_respuesta(['success' => false, 'error' => 'La estampa no existe en este proyecto.'], 404);
        if (!PdfRaster::disponible()) json_respuesta(['success' => false, 'error' => 'El servidor no puede convertir la estampa a imagen (falta pdftoppm o Ghostscript).'], 422);
        $cfg = $e->config();
        $dim = Estampas::dimensiones($cfg);
        $W = max(24, min(2400, $dim[0]));
        $H = max(24, min(2400, $dim[1]));
        $clave = substr(hash('sha256', (int)$e->id . '|' . $W . 'x' . $H . '|' . date('Y-m-d') . '|' . json_encode($cfg)), 0, 32) . '.png';
        $ruta = Anotador::CARPETA . '/' . $clave;
        if (!is_file($ruta)) {
            try {
                $html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:0}html,body{margin:0;padding:0;width:100%;height:100%;}</style></head><body>'
                    . Estampas::renderGuardada($e, $p, null, ['corte' => date('Y-m-d')], 'pt', 1.0) . '</body></html>';
                $pdf = Pdf::generar($html, [0, 0, $W, $H], 'portrait', ['isRemoteEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
                $tmp = tempnam(sys_get_temp_dir(), 'ante');
                file_put_contents($tmp, $pdf);
                $png = PdfRaster::primeraPagina($tmp, 1600);
                @unlink($tmp);
                if (!$png) throw new \RuntimeException('No se pudo rasterizar la estampa.');
                if (!is_dir(Anotador::CARPETA)) @mkdir(Anotador::CARPETA, 0775, true);
                if (!@copy($png, $ruta)) throw new \RuntimeException('No se pudo guardar la estampa.');
                @unlink($png);
            } catch (\Throwable $ex) {
                error_log('Anotador · estampa: ' . $ex->getMessage());
                json_respuesta(['success' => false, 'error' => 'No se pudo preparar la estampa: ' . $ex->getMessage()], 422);
            }
        }
        $info = @getimagesize($ruta);
        header('Content-Type: image/png');
        header('Cache-Control: private, max-age=600');
        readfile($ruta);
        exit;
    }

    /** Finaliza la firma: bloquea el documento y registra quién firmó */
    public static function firmar()
    {
        $p = proyecto_api('ver');
        if (!puede_gestionar_proyecto($p)) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        $d = Anotador::obtener((int)$p->id, (int)($_POST['id'] ?? 0));
        if (!$d) json_respuesta(['success' => false, 'error' => 'El documento no existe.'], 404);
        if (!Anotador::puedeEditar(['usuario_id' => $d->usuario_id, 'finalizado' => false])) json_respuesta(['success' => false, 'error' => 'Solo quien lo creó o el administrador pueden firmarlo.'], 403);
        $r = Anotador::finalizar($p, $d, texto_param($_POST['cargo'] ?? ''));
        if (!$r['ok']) json_respuesta(['success' => false, 'error' => $r['error']], 422);
        json_respuesta(['success' => true, 'hash' => $r['hash'], 'firmado' => $r['firmado'], 'fecha' => $r['fecha']]);
    }

    public static function reabrir()
    {
        $p = proyecto_api('ver');
        if (!is_admin()) json_respuesta(['success' => false, 'error' => 'Solo el administrador puede reabrir un documento firmado.'], 403);
        $d = Anotador::obtener((int)$p->id, (int)($_POST['id'] ?? 0));
        if (!$d || !Anotador::reabrir($p, $d)) json_respuesta(['success' => false, 'error' => 'No se pudo reabrir.'], 422);
        json_respuesta(['success' => true]);
    }

    // ---------- entrega de archivos ----------
    private static function entregar(string $ruta, string $mime, string $nombre): never
    {
        if (!is_file($ruta)) { http_response_code(404); exit; }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: inline; filename="' . rawurlencode($nombre) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=60');
        readfile($ruta);
        exit;
    }

    private static function entregarDatos(string $datos, string $mime, string $nombre): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($datos));
        header('Content-Disposition: inline; filename="' . rawurlencode($nombre) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=60');
        echo $datos;
        exit;
    }
}
