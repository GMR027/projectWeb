<?php
/**
 * Herramientas del proyecto agrupadas por categoría: fuente única del menú lateral y de la barra de iconos.
 * Devuelve [categoría => [ ['url','icon','label','match','key', 'tambien'?], ... ]] ya filtrado por rol y permisos.
 */
function herramientas_proyecto($proyecto): array
{
    if (!$proyecto) return [];
    $id = (int)$proyecto->id;
    $gestiona = puede_gestionar_proyecto($proyecto);
    $admin = is_admin();
    // [categoría, ruta, icono, etiqueta, visible, extras]
    $defs = [
        ['Planeación', '/programa', 'fa-list-check', 'Programa'],
        ['Planeación', '/planificador', 'fa-diagram-next', 'Planificador'],
        ['Planeación', '/dependencias', 'fa-diagram-project', 'Dependencias de obra'],
        ['Planeación', '/lookahead', 'fa-binoculars', 'Lookahead 4S'],
        ['Planeación', '/vplan', 'fa-map-location-dot', 'vPlan'],
        ['Planeación', '/lastplanner', 'fa-note-sticky', 'Last Planner'],
        ['Planeación', '/calendario', 'fa-calendar-days', 'Calendario'],
        ['Campo', '/reportes', 'fa-clipboard-list', 'Reporte diario'],
        ['Campo', '/bitacora', 'fa-book', 'Bitácora de obra'],
        ['Campo', '/minutas', 'fa-handshake', 'Minutas'],
        ['Campo', '/evidencias', 'fa-camera-retro', 'Evidencias'],
        ['Campo', '/curvas', 'fa-chart-line', 'Curvas de productividad'],
        ['Campo', '/seguridad', 'fa-helmet-safety', 'Seguridad y permisos'],
        ['Campo', '/punch', 'fa-clipboard-check', 'Punch list'],
        ['Campo', '/mapa-avance', 'fa-map-location-dot', 'Mapa de avance'],
        ['Materiales y cambios', '/msr', 'fa-truck-ramp-box', 'MSR · Materiales'],
        ['Materiales y cambios', '/cambios', 'fa-file-circle-exclamation', 'Órdenes de cambio'],
        ['Materiales y cambios', '/lineas-base', 'fa-timeline', 'Líneas base'],
        ['Materiales y cambios', '/submittals', 'fa-boxes-packing', 'Submittals', $gestiona],
        ['Materiales y cambios', '/materiales', 'fa-cubes', 'Catálogo de materiales', $gestiona],
        ['Materiales y cambios', '/plantillas', 'fa-layer-group', 'Tipo de obra'],
        ['Documentos y diseño', '/archivos', 'fa-folder-open', 'Archivo'],
        ['Documentos y diseño', '/datos-obra', 'fa-file-contract', 'Datos de obra'],
        ['Documentos y diseño', '/graficos', 'fa-pen-ruler', 'Editor'],
        ['Documentos y diseño', '/redactor', 'fa-file-pen', 'Redactor'],
        ['Documentos y diseño', '/sheetflow', 'fa-table-cells', 'SheetFlow'],
        ['Documentos y diseño', '/simbolos', 'fa-shapes', 'Símbolos'],
        ['Documentos y diseño', '/view360', 'fa-street-view', 'View360'],
        ['Documentos y diseño', '/comparador', 'fa-code-compare', 'Comparador de documentos'],
        ['Documentos y diseño', '/normativa', 'fa-scale-balanced', 'Normativa y especificaciones'],
        ['Inteligencia artificial', '/programador', 'fa-wand-magic-sparkles', 'Programador de actividades', $admin],
        ['Inteligencia artificial', '/inconsistencias', 'fa-magnifying-glass-chart', 'Inconsistencias de proyecto', $admin],
        ['Inteligencia artificial', '/analisis-materiales', 'fa-boxes-stacked', 'Análisis de materiales', $admin],
        ['Inteligencia artificial', '/propuestas-ia', 'fa-robot', 'Centro de IA', $admin, ['/informes-ia', '/grabaciones', '/historial-cambios', '/biblioteca-ia']],
        ['Inteligencia artificial', '/revisiones', 'fa-clipboard-check', 'Revisiones'],
        ['Administración', '/especialidades', 'fa-tags', 'Especialidades', $admin],
        ['Administración', '/informes-ia', 'fa-file-lines', 'Informes de IA', $admin],
        ['Administración', '/grabaciones', 'fa-microphone', 'Grabaciones de reuniones', $admin],
        ['Administración', '/historial-cambios', 'fa-clock-rotate-left', 'Historial de cambios de IA', $admin],
        ['Administración', '/biblioteca-ia', 'fa-book-bookmark', 'Biblioteca de IA', $admin],
        ['Administración', '/acciones', 'fa-bolt', 'Acciones rápidas', $admin],
        ['Proyecto', '/ajustes', 'fa-gear', 'Ajustes de proyecto', !is_visitante()],
        ['Proyecto', '/exportar', 'fa-file-export', 'Exportar', !is_visitante()],
    ];
    $grupos = [];
    foreach ($defs as $d) {
        [$cat, $ruta, $icono, $etiqueta] = $d;
        $visible = $d[4] ?? true;
        $tambien = $d[5] ?? [];
        if ($visible === false || !herramienta_permitida(herramienta_de_ruta($ruta))) continue;
        $grupos[$cat][] = ['url' => "$ruta?proyecto=$id", 'icon' => $icono, 'label' => $etiqueta, 'match' => $ruta, 'key' => ltrim($ruta, '/'), 'tambien' => $tambien];
    }
    return $grupos;
}
