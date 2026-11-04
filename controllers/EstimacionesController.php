<?php

namespace Controllers;

use MVC\Router;
use Classes\Estimaciones;
use Classes\Pdf;

/** Estimaciones completas: contrato, detalle por partida, deducciones y PDF */
class EstimacionesController
{
    public static function contrato()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Estimaciones::guardarContrato((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg);
    }

    public static function guardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Estimaciones::guardar((int)$p->id, $_POST);
        self::volver((int)$p->id, $ok, $msg);
    }

    public static function eliminar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Estimaciones::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        self::volver((int)$p->id, $ok, $msg);
    }

    public static function pdf()
    {
        $p = proyecto_de_peticion();
        if (!puede_descargar() && !es_demo()) { http_response_code(403); exit('No autorizado'); }
        $pid = (int)$p->id;
        $estimacion = Estimaciones::estimacion($pid, (int)($_GET['id'] ?? 0));
        if (!$estimacion) { http_response_code(404); exit('La estimación no existe'); }
        $proyecto = $p;
        $contrato = Estimaciones::contrato($pid);
        $resumen = Estimaciones::resumenContrato($pid);
        ob_start();
        include __DIR__ . '/../views/estimaciones/pdf.php';
        $html = ob_get_clean();
        $pdf = Pdf::generar($html, 'letter', 'portrait', ['marca' => $p]);
        enviar_descarga($pdf, 'application/pdf', 'Estimacion-' . slug_archivo((string)$estimacion['numero']) . '-' . date('Y-m-d') . '.pdf');
    }

    private static function volver(int $pid, bool $ok, string $msg): void
    {
        header('Location: /costos?proyecto=' . $pid . '&pestana=estimaciones&' . ($ok ? 'ok=' : 'error=') . urlencode($msg));
        exit;
    }
}
