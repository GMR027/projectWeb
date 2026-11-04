<?php

namespace Controllers;

use MVC\Router;
use Model\Archivo;
use Classes\Subcontratos;
use Classes\Pdf;

/** Subcontratos: alcance, monto, documentos obligatorios, estimaciones y compuerta de pago */
class SubcontratoController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Subcontratos::disponible();
        $ver = $disponible && !empty($_GET['ver']) ? Subcontratos::subcontrato($pid, (int)$_GET['ver']) : null;
        $editar = $disponible && !empty($_GET['editar']) ? Subcontratos::subcontrato($pid, (int)$_GET['editar']) : null;
        $router->render('subcontratos/index', [
            'titulo' => 'Subcontratos - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'disponible' => $disponible,
            'lista' => $disponible ? Subcontratos::lista($pid) : [],
            'resumen' => $disponible ? Subcontratos::resumen($pid) : [],
            'subcontrato' => $ver,
            'editar' => $editar,
            'abrirForm' => isset($_GET['nuevo']) || $editar,
            'siguienteCodigo' => $disponible ? Subcontratos::siguienteCodigo($pid) : '',
            'archivos' => $disponible ? Archivo::deProyecto($pid) : [],
            'partidas' => $proyecto->getPartidas(),
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'ok' => texto_param($_GET['ok'] ?? ''),
            'error' => texto_param($_GET['error'] ?? ''),
        ]);
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg, $id] = Subcontratos::guardar((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, $id ? '&ver=' . $id : '');
    }

    public static function documento()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Subcontratos::guardarDocumento((int)$p->id, (int)($_POST['id'] ?? 0), $_POST);
        self::volver((int)$p->id, $ok, $msg, '&ver=' . (int)($_POST['id'] ?? 0));
    }

    public static function estimacion()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Subcontratos::guardarEstimacion((int)$p->id, (int)($_POST['subcontrato_id'] ?? 0), $_POST);
        self::volver((int)$p->id, $ok, $msg, '&ver=' . (int)($_POST['subcontrato_id'] ?? 0));
    }

    public static function pdf()
    {
        $p = proyecto_de_peticion();
        if (!puede_descargar() && !es_demo()) { http_response_code(403); exit('No autorizado'); }
        $pid = (int)$p->id;
        $s = Subcontratos::subcontrato($pid, (int)($_GET['id'] ?? 0));
        if (!$s) { http_response_code(404); exit('El subcontrato no existe'); }
        $proyecto = $p;
        ob_start();
        include __DIR__ . '/../views/subcontratos/pdf.php';
        $html = ob_get_clean();
        $pdf = Pdf::generar($html, 'letter', 'portrait', ['marca' => $p]);
        enviar_descarga($pdf, 'application/pdf', 'Subcontrato-' . slug_archivo((string)($s['codigo'] ?: $s['id'])) . '-' . date('Y-m-d') . '.pdf');
    }

    private static function volver(int $pid, bool $ok, string $msg, string $extra = ''): void
    {
        header('Location: /subcontratos?proyecto=' . $pid . $extra . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
