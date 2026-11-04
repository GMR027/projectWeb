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
        $materiales = array_map(fn($m) => ['id' => (int)$m->id, 'nombre' => $m->nombre], \Model\Material::deProyecto($pid));
        $acts = [];
        foreach (\Classes\Dependencias::actividades($pid) as $a) $acts[] = ['id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre']];
        return ['vigilancia' => Inconsistencias::estadoVigilancia($pid), 'materiales' => $materiales, 'actividadesProyecto' => $acts];
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

    /** Calcula el fin de una actividad con el calendario del proyecto (botón «Calcular fin por duración» del editor) */
    public static function calcularFin()
    {
        $p = proyecto_api();
        $pid = (int)$p->id;
        $aid = (int)($_POST['actividad_id'] ?? 0);
        $inicio = texto_param($_POST['inicio'] ?? '');
        $d = \DateTime::createFromFormat('Y-m-d', $inicio);
        $acts = \Classes\Dependencias::actividades($pid);
        if (!$d || $d->format('Y-m-d') !== $inicio || !isset($acts[$aid])) json_respuesta(['success' => false, 'error' => 'Actividad o fecha no válida'], 422);
        $dur = max(1, (int)$acts[$aid]['duracion_dias']);
        $fin = (new \Classes\Calendario($pid))->calcularFechaFin($inicio, $dur);
        json_respuesta(['success' => true, 'fin' => $fin, 'dur' => $dur]);
    }
}
