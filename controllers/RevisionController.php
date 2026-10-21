<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Revisiones;

/** Revisiones por proyecto: pantalla de entrada (qué está revisado y qué no), marcado ✅ ⚠ ❌ y estado de las herramientas de IA. Solo administrador (ni cliente ni demo) */
class RevisionController
{
    public static function index(Router $router)
    {
        if (!is_admin()) { http_response_code(403); exit('Solo el administrador puede ver las revisiones'); }
        $proyecto = proyecto_de_peticion();
        $pid = (int)$proyecto->id;
        $pestana = in_array($_GET['pestana'] ?? '', ['resumen', 'materiales', 'actividades', 'herramientas', 'historial'], true) ? $_GET['pestana'] : 'resumen';
        $filtro = in_array($_GET['filtro'] ?? '', ['correcto', 'revisar', 'no_cumple', 'sin_evaluar', 'sin_revisar', 'cambio'], true) ? $_GET['filtro'] : '';
        $materiales = Revisiones::elementos($pid, 'material');
        $actividades = Revisiones::elementos($pid, 'actividad');
        $herramientas = Revisiones::herramientas($pid);
        $router->render('revisiones/index', [
            'titulo' => 'Revisiones - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'pestana' => $pestana, 'filtro' => $filtro,
            'materiales' => $materiales, 'actividades' => $actividades, 'herramientas' => $herramientas,
            'cMateriales' => Revisiones::conteo($materiales), 'cActividades' => Revisiones::conteo($actividades), 'cHerramientas' => Revisiones::conteoHerramientas($herramientas),
            'historial' => $pestana === 'historial' ? Revisiones::historial($pid, ['tipo' => $_GET['tipo'] ?? '', 'ref' => $_GET['ref'] ?? ''], 200) : [],
            'puede' => puede_revisar($proyecto),
        ]);
    }

    public static function marcar()
    {
        $p = proyecto_api();
        if (!puede_revisar($p)) json_respuesta(['success' => false, 'error' => 'No tienes permiso para marcar revisiones'], 403);
        $revisado = isset($_POST['revisado']) && $_POST['revisado'] !== '' ? $_POST['revisado'] === '1' : null;
        $veredicto = isset($_POST['veredicto']) && $_POST['veredicto'] !== '' ? (string)$_POST['veredicto'] : null;
        $nota = isset($_POST['nota']) ? (string)$_POST['nota'] : null;
        $e = Revisiones::marcar((int)$p->id, (string)($_POST['tipo'] ?? ''), (string)($_POST['ref'] ?? ''), $veredicto, $revisado, $nota, usuario_actual());
        if (!$e) json_respuesta(['success' => false, 'error' => 'No se pudo guardar (elemento no encontrado)'], 422);
        json_respuesta(['success' => true, 'estado' => $e, 'resumen' => Revisiones::resumen((int)$p->id)]);
    }

    /** Resumen y estado de las herramientas de IA (la app de escritorio lo pinta junto a cada herramienta) */
    public static function resumen()
    {
        $p = proyecto_api();
        $r = Revisiones::resumen((int)$p->id);
        $r['lista_herramientas'] = Revisiones::herramientas((int)$p->id);
        json_respuesta(['success' => true] + $r);
    }
}
