<?php

namespace Controllers;

use Classes\AnalisisMateriales;

/** Análisis de materiales: la app de escritorio manda el análisis de la IA; aquí se guardan y el admin acepta o rechaza cada solución */
class AnalisisMaterialesController extends HallazgosIAController
{
    protected const MODELO = AnalisisMateriales::class;
    protected const RUTA = 'analisis-materiales';
    protected const SESION_MSG = 'analisis_materiales_msg';
    protected const TITULO = 'Análisis de materiales';
    protected const TXT_SOLO_ADMIN = 'Solo el administrador puede usar el análisis de materiales';
    protected const TXT_PENDIENTES = 'hallazgo(s)';

    protected static function datosVista(int $pid): array
    {
        $acts = [];
        foreach (\Classes\Dependencias::actividades($pid) as $a) $acts[] = ['id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre']];
        $subs = array_map(fn($s) => ['id' => (int)$s->id, 'numero' => $s->numero_submittal ?: ('#' . $s->id)], \Model\Submittal::deProyecto($pid));
        $frags = 0;
        try { $r = $GLOBALS['db']->query("SELECT COUNT(*) AS n FROM normativa_fragmentos WHERE proyecto_id = $pid"); $frags = $r ? (int)$r->fetch_row()[0] : 0; } catch (\Throwable $e) {}
        return ['actividadesProyecto' => $acts, 'submittalsProyecto' => $subs,
            'inteligentes' => \Classes\MaterialesInteligentes::activo($pid), 'normativaFrags' => $frags];
    }

    /** Activa o desactiva Materiales inteligentes desde la web (antes solo se podía desde el escritorio) */
    public static function inteligentes()
    {
        $p = proyecto_api();
        \Classes\MaterialesInteligentes::fijarActivo((int)$p->id, !empty($_POST['activar']));
        $_SESSION['analisis_materiales_msg'] = ['ok' => true, 'texto' => !empty($_POST['activar'])
            ? 'Materiales inteligentes activado: la app de escritorio verificará cada material contra la normativa indexada.'
            : 'Materiales inteligentes desactivado.'];
        header('Location: /analisis-materiales?proyecto=' . (int)$p->id);
        exit;
    }
}
