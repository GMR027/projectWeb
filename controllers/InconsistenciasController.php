<?php

namespace Controllers;

use Classes\Inconsistencias;

/** Inconsistencias de proyecto: la app de escritorio manda el análisis de la IA; aquí se guardan y el admin acepta o rechaza cada solución */
class InconsistenciasController extends HallazgosIAController
{
    protected const MODELO = Inconsistencias::class;
    protected const RUTA = 'inconsistencias';
    protected const SESION_MSG = 'inconsistencias_msg';
    protected const TITULO = 'Inconsistencias de proyecto';
    protected const TXT_SOLO_ADMIN = 'Solo el administrador puede ver las inconsistencias del proyecto';
    protected const TXT_PENDIENTES = 'inconsistencia(s)';

    protected static function datosVista(int $pid): array
    {
        return ['vigilancia' => Inconsistencias::estadoVigilancia($pid)];
    }

    /** Comprobación de vigilancia: la usa la app de escritorio cada cierto tiempo y el botón «Comprobar ahora» */
    public static function vigilancia()
    {
        $p = proyecto_api();
        json_respuesta(['success' => true] + Inconsistencias::vigilar($p));
    }

    public static function vigilar()
    {
        $p = proyecto_api();
        $r = Inconsistencias::vigilar($p);
        $_SESSION['inconsistencias_msg'] = ['ok' => true, 'texto' => $r['primera'] ? 'Primera comprobación guardada: desde ahora se avisará de lo que cambie.' : ($r['total_nuevos'] . ' hallazgo(s) nuevo(s) y ' . count($r['cambios']) . ' cambio(s) brusco(s) desde la comprobación anterior.')];
        header('Location: /inconsistencias?proyecto=' . (int)$p->id);
        exit;
    }
}
