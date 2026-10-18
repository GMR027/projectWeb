<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Dependencias;
use Classes\MapaAvance;

/** Mapa de avance: el color de las marcas de las láminas (Editor/vPlan) se traduce a avance por actividad. Ver: acceso al proyecto; calcular/configurar: quien gestiona el proyecto */
class MapaAvanceController
{
    private static function gestion(): Proyecto
    {
        $p = Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT));
        if (!$p || !is_auth() || !puede_gestionar_proyecto($p)) { http_response_code(403); exit('No autorizado'); }
        return $p;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $calc = MapaAvance::calcular($proyecto);
        $acts = Dependencias::actividades($pid);
        $hist = [];
        foreach (array_slice(array_keys($calc['actividades']), 0, 60) as $a) $hist[$a] = MapaAvance::historial($pid, (int)$a, 8);
        $router->render('mapa-avance/index', ['titulo' => 'Mapa de avance - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'calc' => $calc, 'actividades' => $acts, 'historial' => $hist, 'mapeos' => MapaAvance::mapeos($pid),
            'puede' => puede_gestionar_proyecto($proyecto), 'mensaje' => $_SESSION['mapa_msg'] ?? null]);
        unset($_SESSION['mapa_msg']);
    }

    public static function ajustes()
    {
        $p = self::gestion();
        $ok = MapaAvance::guardarAjustes((int)$p->id, (int)($_POST['rojo'] ?? 0), (int)($_POST['amarillo'] ?? 50), (int)($_POST['verde'] ?? 100));
        $_SESSION['mapa_msg'] = ['ok' => $ok, 'texto' => $ok ? 'Porcentajes guardados.' : 'Los porcentajes deben ir de menor a mayor: rojo ≤ amarillo ≤ verde.'];
        header('Location: /mapa-avance?proyecto=' . (int)$p->id);
        exit;
    }

    public static function mapear()
    {
        $p = self::gestion();
        $ok = MapaAvance::mapear((int)$p->id, (int)($_POST['lamina'] ?? 0), (string)($_POST['capa'] ?? ''), (int)($_POST['actividad'] ?? 0));
        $_SESSION['mapa_msg'] = ['ok' => $ok, 'texto' => $ok ? 'Asignación guardada.' : 'No se pudo guardar la asignación.'];
        header('Location: /mapa-avance?proyecto=' . (int)$p->id);
        exit;
    }

    public static function calcular()
    {
        $p = self::gestion();
        $r = MapaAvance::proponer($p, (float)($_POST['umbral'] ?? 5));
        $_SESSION['mapa_msg'] = ['ok' => true, 'texto' => $r['propuestas'] ? "Se guardó la foto de hoy y se crearon {$r['propuestas']} propuesta(s) de avance. Nada cambió todavía: acéptalas o recházalas en el Análisis inteligente." : 'Se guardó la foto de hoy. El avance de los planos coincide con el del programa (no hay propuestas).'];
        header('Location: ' . ($r['corrida'] ? '/inconsistencias?proyecto=' . (int)$p->id . '&corrida=' . $r['corrida'] : '/mapa-avance?proyecto=' . (int)$p->id));
        exit;
    }
}
