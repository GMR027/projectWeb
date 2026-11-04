<?php

namespace Controllers;

use MVC\Router;
use Model\Archivo;
use Classes\Transmittals;
use Classes\Pdf;

/** Envíos de documentos con acuse de recibo (transmittals) */
class TransmittalController
{
    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $disponible = Transmittals::disponible();
        $ver = $disponible && !empty($_GET['ver']) ? Transmittals::transmittal($pid, (int)$_GET['ver']) : null;
        $editar = $disponible && !empty($_GET['editar']) ? Transmittals::transmittal($pid, (int)$_GET['editar']) : null;
        $router->render('transmittals/index', [
            'titulo' => 'Envíos - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'disponible' => $disponible,
            'lista' => $disponible ? Transmittals::lista($pid) : [],
            'resumen' => $disponible ? Transmittals::resumen($pid) : [],
            'transmittal' => $ver,
            'editar' => $editar,
            'abrirForm' => isset($_GET['nuevo']) || $editar,
            'siguienteNumero' => $disponible ? Transmittals::siguienteNumero($pid) : '',
            'archivos' => $disponible ? Archivo::deProyecto($pid) : [],
            'puedeEditar' => puede_gestionar_proyecto($proyecto),
            'ok' => texto_param($_GET['ok'] ?? ''),
            'error' => texto_param($_GET['error'] ?? ''),
        ]);
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg, $id] = Transmittals::guardar((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg, $id ? '&ver=' . $id : '');
    }

    public static function pdf()
    {
        $p = proyecto_de_peticion();
        if (!puede_descargar() && !es_demo()) { http_response_code(403); exit('No autorizado'); }
        $pid = (int)$p->id;
        $t = Transmittals::transmittal($pid, (int)($_GET['id'] ?? 0));
        if (!$t) { http_response_code(404); exit('El envío no existe'); }
        $proyecto = $p;
        ob_start();
        include __DIR__ . '/../views/transmittals/pdf.php';
        $html = ob_get_clean();
        $pdf = Pdf::generar($html, 'letter', 'portrait', ['marca' => $p]);
        enviar_descarga($pdf, 'application/pdf', 'Envio-' . slug_archivo((string)$t['numero']) . '-' . date('Y-m-d') . '.pdf');
    }

    /** Acuse público por token (sin sesión) */
    public static function acusePublico(Router $router)
    {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
        $token = texto_param($_POST['t'] ?? $_GET['t'] ?? '');
        $ok = ''; $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            [$okb, $msg] = Transmittals::acuse($token, $_POST);
            if ($okb) $ok = $msg; else $error = $msg;
        }
        $r = Transmittals::porToken($token);
        if (!$r) {
            http_response_code(404);
            $router->render('transmittals/acuse', ['titulo' => 'Acuse no disponible', 'publicoLayout' => true, 'error' => 'El enlace de acuse no es válido o ya venció.', 'r' => null, 'token' => $token]);
            return;
        }
        $router->render('transmittals/acuse', ['titulo' => 'Acuse de recibo · ' . $r['numero'], 'publicoLayout' => true,
            'r' => $r, 'token' => $token, 'ok' => $ok, 'error' => $error]);
    }

    private static function volver(int $pid, bool $ok, string $msg, string $extra = ''): void
    {
        header('Location: /transmittals?proyecto=' . $pid . $extra . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
