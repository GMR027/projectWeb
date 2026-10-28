<?php

namespace Controllers;

use MVC\Router;
use Model\Archivo;
use Classes\Cierre;
use Classes\Pdf;

/** Cierre de obra: lista de entrega, avance y acta de entrega-recepción en PDF */
class CierreController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Cierre::disponible();
        $filtro = isset(Cierre::CATEGORIAS[$_GET['categoria'] ?? '']) ? $_GET['categoria'] : '';
        $editar = (int)($_GET['editar'] ?? 0);
        $router->render('cierre/index', [
            'titulo' => 'Cierre de obra - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'disponible' => $disponible,
            'config' => $disponible ? Cierre::config($pid) : [],
            'items' => $disponible ? Cierre::items($pid, $filtro) : [],
            'resumen' => $disponible ? Cierre::resumen($pid) : [],
            'filtro' => $filtro,
            'editar' => $editar ? Cierre::item($pid, $editar) : null,
            'abrirForm' => isset($_GET['nuevo']) || $editar,
            'archivos' => $disponible ? Archivo::deProyecto($pid) : [],
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'ok' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['error'] ?? ''),
        ]);
    }

    public static function config()
    {
        $p = proyecto_de_peticion(true);
        Cierre::guardarConfig((int)$p->id, $_POST);
        self::volver((int)$p->id, true, 'Datos del cierre guardados');
    }

    public static function guardarItem()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Cierre::guardarItem((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg);
    }

    public static function eliminarItem()
    {
        $p = proyecto_de_peticion(true);
        $ok = Cierre::eliminarItem((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Punto eliminado' : 'No se pudo eliminar');
    }

    public static function sugeridos()
    {
        $p = proyecto_de_peticion(true);
        $n = Cierre::crearSugeridos((int)$p->id);
        self::volver((int)$p->id, true, $n ? "$n punto(s) de la lista estándar agregados" : 'El proyecto ya tiene puntos de cierre');
    }

    /** Acta de entrega-recepción en PDF */
    public static function pdf()
    {
        $p = proyecto_de_peticion();
        if (!puede_descargar() && !es_demo()) { http_response_code(403); exit('No autorizado'); }
        $pid = (int)$p->id;
        $config = Cierre::config($pid);
        $items = Cierre::items($pid);
        $resumen = Cierre::resumen($pid);
        $proyecto = $p;   // la vista del acta usa $proyecto, como el resto de exportadores
        ob_start();
        include __DIR__ . '/../views/cierre/pdf.php';
        $html = ob_get_clean();
        $pdf = Pdf::generar($html, 'letter', 'portrait');
        $nombre = 'Acta-entrega-' . slug_archivo($p->nombre) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private static function volver(int $pid, bool $ok, string $msg): void
    {
        header('Location: /cierre?proyecto=' . $pid . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
