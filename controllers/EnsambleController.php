<?php

namespace Controllers;

use Classes\EnsamblePdf;
use Model\ArchivoCarpeta;
use Model\Ensamble;
use MVC\Router;

/** Ensamblador de documentos: une documentos del Redactor y PDF del Archivo en un PDF no editable que se mantiene al día */
class EnsambleController
{
    private static function deProyecto($proyecto, $id): ?Ensamble
    {
        $e = Ensamble::find(filter_var($id, FILTER_VALIDATE_INT));
        return $e && (int)$e->proyecto_id === (int)$proyecto->id ? $e : null;
    }

    private static function volver($proyecto, string $extra = ''): void
    {
        header('Location: /ensamblador-documentos?proyecto=' . $proyecto->id . $extra);
        exit;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('ensamblador-documentos/index', [
            'titulo' => 'Ensamblador de documentos - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'ensambles' => array_map(fn($e) => ['e' => $e, 'fuentes' => EnsamblePdf::resumen($e)], Ensamble::deProyecto((int)$proyecto->id)),
            'puedeEditar' => puede_gestionar_proyecto($proyecto), 'puedeDescargar' => puede_descargar(),
            'carpetas' => ArchivoCarpeta::deProyecto((int)$proyecto->id),
        ]);
    }

    public static function formulario(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $e = !empty($_REQUEST['id']) ? self::deProyecto($proyecto, $_REQUEST['id']) : new Ensamble();
        if (!$e) self::volver($proyecto);
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $e->proyecto_id = $proyecto->id;
            $e->nombre = mb_substr(trim((string)($_POST['nombre'] ?? '')), 0, 150);
            $e->fuentes = json_encode((new Ensamble(['fuentes' => (string)($_POST['fuentes'] ?? '[]')]))->listaFuentes());
            if (!$e->id) $e->creado_por = $_SESSION['id'] ?? null;
            $alertas = $e->validar();
            // solo se aceptan fuentes que existen en este proyecto
            $validas = array_flip(array_map(fn($f) => $f['t'] . $f['id'], array_merge(...array_values(EnsamblePdf::disponibles((int)$proyecto->id)))));
            $limpias = array_values(array_filter($e->listaFuentes(), fn($f) => isset($validas[$f['t'] . $f['id']])));
            if (count($limpias) !== count($e->listaFuentes())) $alertas['error'][] = 'Alguno de los documentos elegidos ya no está disponible';
            if (!$alertas) {
                $e->fuentes = json_encode($limpias);
                $r = $e->guardar();
                $id = $e->id ?: ($r['id'] ?? 0);
                $e = Ensamble::find($id);
                // si ya vive en Archivo, la copia se actualiza con la nueva receta
                $msg = $e->archivo_id ? EnsamblePdf::guardarEnArchivo($e) : null;
                self::volver($proyecto, $msg ? '&error=' . urlencode($msg) : '&ok=1');
            }
        }
        $router->render('ensamblador-documentos/formulario', [
            'titulo' => ($e->id ? 'Editar ensamble' : 'Nuevo ensamble') . ' - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'ensamble' => $e, 'alertas' => $alertas,
            'disponibles' => EnsamblePdf::disponibles((int)$proyecto->id), 'seleccion' => EnsamblePdf::resumen($e),
        ]);
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($e = self::deProyecto($proyecto, $_POST['id'] ?? null)) $e->eliminar();   // la copia en Archivo (si hay) queda como PDF fijo
        self::volver($proyecto);
    }

    /** Guarda el PDF en Archivo, ligado al ensamble: se vuelve a generar solo cuando cambia una fuente */
    public static function guardarEnArchivo()
    {
        $proyecto = proyecto_de_peticion(true);
        $e = self::deProyecto($proyecto, $_POST['id'] ?? null);
        if (!$e) self::volver($proyecto);
        $car = filter_var($_POST['carpeta_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($car) {
            $k = ArchivoCarpeta::find($car);
            if (!$k || (int)$k->proyecto_id !== (int)$proyecto->id) $car = null;
        }
        $msg = EnsamblePdf::guardarEnArchivo($e, $car);
        self::volver($proyecto, $msg ? '&error=' . urlencode($msg) : '&guardado=1');
    }

    /** PDF generado ahora mismo con las fuentes actuales (inline=1 lo abre en el navegador) */
    public static function descargar()
    {
        $proyecto = proyecto_de_peticion();
        $e = self::deProyecto($proyecto, $_GET['id'] ?? null);
        if (!$e || !puede_descargar()) self::volver($proyecto, '&error=' . urlencode('No se puede descargar el documento'));
        try {
            [$tmp, ] = EnsamblePdf::generar($e);
        } catch (\Throwable $x) {
            error_log('Ensamblador de documentos: ' . $x->getMessage());
            self::volver($proyecto, '&error=' . urlencode('No se pudo generar el PDF'));
        }
        $nombre = EnsamblePdf::nombreArchivo($e);
        header_remove('Content-Type');
        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . (!empty($_GET['inline']) ? 'inline' : 'attachment') . '; filename="' . $nombre . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}
