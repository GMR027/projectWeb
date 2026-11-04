<?php

namespace Controllers;

use MVC\Router;
use Classes\Riesgos;
use Classes\Pdf;

/** Registro de riesgos de la obra: lista, alta/edición, conversión a restricción y matriz en PDF */
class RiesgoController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Riesgos::disponible();
        $sev = texto_param($_GET['sev'] ?? '');
        $estado = texto_param($_GET['estado'] ?? '');
        $cat = texto_param($_GET['cat'] ?? '');
        $filtros = [
            'sev' => isset(Riesgos::NIVELES[$sev]) ? $sev : '',
            'estado' => isset(Riesgos::ESTADOS[$estado]) ? $estado : '',
            'cat' => isset(Riesgos::CATEGORIAS[$cat]) ? $cat : '',
            'q' => texto_param($_GET['q'] ?? ''),
        ];
        $ver = (int)($_GET['ver'] ?? 0);
        $editar = (int)($_GET['editar'] ?? 0);
        $riesgo = $ver ? Riesgos::riesgo($pid, $ver) : null;
        $editarRiesgo = $editar ? Riesgos::riesgo($pid, $editar) : null;
        $router->render('riesgos/index', [
            'titulo' => 'Riesgos - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'disponible' => $disponible,
            'filtros' => $filtros,
            'lista' => $disponible ? Riesgos::lista($pid, $filtros) : [],
            'resumen' => $disponible ? Riesgos::resumen($pid) : [],
            'matriz' => $disponible ? Riesgos::matriz($pid) : [],
            'riesgo' => $riesgo,
            'editar' => $editarRiesgo,
            'abrirForm' => isset($_GET['nuevo']) || $editarRiesgo,
            'actividades' => $disponible ? \Classes\Recursos::actividades($pid) : [],
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'puedeEliminar' => puede_eliminar_proyecto($proyecto),
            'ok' => texto_param($_GET['ok'] ?? ''),
            'error' => texto_param($_GET['error'] ?? ''),
        ]);
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg, $id] = Riesgos::guardar((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, $id ? '&ver=' . $id : '');
    }

    public static function eliminar()
    {
        $p = proyecto_de_peticion(true);
        if (!puede_eliminar_proyecto($p)) self::volver((int)$p->id, false, 'Solo el dueño del proyecto o el administrador pueden eliminar un riesgo');
        $ok = Riesgos::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $ok ? 'Riesgo eliminado' : 'El riesgo no existe');
    }

    public static function materializar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Riesgos::materializar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $msg);
    }

    /** Lista y matriz de riesgos en PDF, para la junta semanal */
    public static function pdf()
    {
        $p = proyecto_de_peticion();
        if (!puede_descargar() && !es_demo()) { http_response_code(403); exit('No autorizado'); }
        $pid = (int)$p->id;
        $proyecto = $p;   // la vista usa $proyecto, como el resto de exportadores
        $lista = Riesgos::lista($pid);
        $resumen = Riesgos::resumen($pid);
        $matriz = Riesgos::matriz($pid);
        ob_start();
        include __DIR__ . '/../views/riesgos/pdf.php';
        $html = ob_get_clean();
        $pdf = Pdf::generar($html, 'letter', 'landscape', ['marca' => $p]);
        enviar_descarga($pdf, 'application/pdf', 'Riesgos-' . slug_archivo($p->nombre) . '-' . date('Y-m-d') . '.pdf');
    }

    private static function volver(int $pid, bool $ok, string $msg, string $extra = ''): void
    {
        header('Location: /riesgos?proyecto=' . $pid . $extra . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
