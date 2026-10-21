<?php
/**
 * Qué hace cada herramienta (texto de la burbuja de ayuda) y sección del manual donde se explica: ruta => [descripción, id de sección].
 * Las burbujas las dibuja src/js/ayuda.js a partir de los atributos data-ayuda y data-manual.
 */
function ayudas_herramientas(): array
{
    return [
        '/programa' => ['Tabla WBS con partidas y actividades, y su Gantt por semanas. Aquí se captura el avance y se calculan las fechas con el calendario del proyecto.', 'programa'],
        '/planificador' => ['Acomoda las actividades en el tiempo y simula escenarios antes de cambiar el programa.', 'planificador'],
        '/dependencias' => ['Relaciones entre actividades (FS, SS, FF, SF) con holguras y ruta crítica.', 'dependencias'],
        '/lookahead' => ['Vista de las próximas semanas para liberar restricciones y comprometer el trabajo. Incluye el Planificador en su segunda pestaña.', 'lookahead'],
        '/vplan' => ['Láminas libres de planeación visual: bloques, tablas, notas y programa en una hoja lista para imprimir.', 'vplan'],
        '/lastplanner' => ['Planeación semanal con post-its por actividad y medición de cumplimiento (PPC).', 'lastplanner'],
        '/calendario' => ['Calendario del proyecto: actividades por día, jornada semanal y festivos.', 'calendario'],
        '/reportes' => ['Reporte diario de obra: personal, equipo, clima, avances e incidencias.', 'reportes'],
        '/bitacora' => ['Registro cronológico de eventos, acuerdos y compromisos de la obra.', 'bitacora'],
        '/minutas' => ['Minutas de reunión con asistentes, acuerdos y responsables.', 'minutas'],
        '/evidencias' => ['Fotos de avance ligadas a cada actividad.', 'evidencias'],
        '/curvas' => ['Curvas de productividad: rendimiento real contra lo planeado por actividad.', 'curvas'],
        '/seguridad' => ['Permisos de trabajo, pláticas e inspecciones de seguridad.', 'seguridad'],
        '/punch' => ['Lista de pendientes y detalles por corregir antes de entregar la obra. Se puede insertar como tabla en vPlan, Redactor y Editor, eligiendo qué ver.', 'punch'],
        '/mapa-avance' => ['Plano o gráfico coloreado según el avance de cada zona.', 'tipo-obra'],
        '/visor' => ['Mira los archivos del Archivo del proyecto (PDF, imágenes, hojas de cálculo…) sin salir de esta ventana.', 'visor'],
        '/msr' => ['Reporte de materiales (MSR): pedidos, fechas de requerimiento y estado de entrega.', 'msr'],
        '/cambios' => ['Órdenes de cambio: instalar, retirar o reubicar, con su efecto en el programa.', 'cambios'],
        '/lineas-base' => ['Fotos del programa para comparar lo planeado contra lo real.', 'lineas-base'],
        '/submittals' => ['Paquetes de submittals con sus materiales para aprobación.', 'submittals'],
        '/materiales' => ['Catálogo de materiales del proyecto con referencia, especialidad y cantidades.', 'materiales'],
        '/plantillas' => ['Plantillas de partidas y actividades según el tipo de obra.', 'tipo-obra'],
        '/archivos' => ['Archivero del proyecto: planos, documentos y revisiones, con revisión antivirus. La pestaña «Visor» muestra los archivos sin salir de la ventana.', 'archivos'],
        '/datos-obra' => ['Fechas contractuales, responsables, cantidades y contactos de la obra.', 'datos-obra'],
        '/graficos' => ['Editor de gráficos y planos: dibuja, anota y enlaza zonas. La biblioteca de símbolos, estampas y cajetines está en su pestaña «Símbolos».', 'editor'],
        '/redactor' => ['Procesador de texto del proyecto: documentos con tablas e imágenes, exportables a DOCX, ODT y PDF.', 'redactor'],
        '/sheetflow' => ['Tablas con fórmulas; importa y exporta XLSX, CSV y PDF.', 'sheetflow'],
        '/simbolos' => ['Biblioteca de símbolos para usar en el Editor y el vPlan.', 'simbolos'],
        '/view360' => ['Gráficos interactivos con zonas que abren vistas 360 de la obra.', 'view360'],
        '/comparador' => ['Compara dos imágenes o PDF con deslizador, fundido o diferencias.', 'comparador'],
        '/ensamblador-documentos' => ['Une en un solo PDF documentos del Redactor y PDF del Archivo; el PDF se actualiza solo cuando cambian las fuentes.', 'ensamblador-documentos'],
        '/traductor' => ['Traduce español ↔ inglés un texto o un documento completo (PDF, Word, Excel, PowerPoint…).', 'traductor'],
        '/normativa' => ['Normas y especificaciones en PDF, ligadas a materiales y actividades.', 'normativa'],
        '/programador' => ['La IA propone partidas y actividades a partir de lo que describes; tú decides qué aceptar.', 'programador-ia'],
        '/inconsistencias' => ['Revisión de datos con IA: busca lo que no cuadra en el programa y en los materiales (pestañas Inconsistencias y Análisis de materiales) y propone correcciones.', 'inconsistencias'],
        '/analisis-materiales' => ['Analiza los materiales del proyecto con IA y sugiere ajustes.', 'analisis-materiales'],
        '/propuestas-ia' => ['Centro de IA: propuestas pendientes, informes, voz, chat y herramientas inteligentes.', 'centro-ia'],
        '/revisiones' => ['Estado de revisión (✅ ⚠ ❌) por herramienta del proyecto.', 'revisiones'],
        '/directorio' => ['Un solo lugar para el personal, los departamentos, las categorías y las especialidades; las demás herramientas los ofrecen en sus listas.', 'directorio'],
        '/especialidades' => ['Etiquetas de especialidad (eléctrica, plomería…) para clasificar materiales.', 'materiales'],
        '/informes-ia' => ['Informes generados por la IA, listos para revisar y aplicar.', 'centro-ia'],
        '/grabaciones' => ['Grabaciones de reuniones y sus transcripciones.', 'centro-ia'],
        '/historial-cambios' => ['Historial de lo que la IA cambió y opción de deshacerlo.', 'centro-ia'],
        '/biblioteca-ia' => ['Biblioteca de consultas y resultados guardados de la IA.', 'centro-ia'],
        '/acciones' => ['Pendientes del proyecto reunidos en un solo lugar.', 'acciones'],
        '/ajustes' => ['Jornada semanal, festivos y configuración del proyecto.', 'ajustes'],
        '/exportar' => ['Exporta el programa a PDF, XML (MS Project) y otros formatos.', 'exportar'],
        '/proyectos' => ['Lista de los proyectos a los que tienes acceso.', 'inicio'],
    ];
}

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
        ['Planeación', '/dependencias', 'fa-diagram-project', 'Dependencias de obra'],
        ['Planeación', '/lookahead', 'fa-binoculars', 'Lookahead', true, ['/planificador']],
        ['Planeación', '/vplan', 'fa-sheet-plastic', 'vPlan'],
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
        ['Documentos y diseño', '/archivos', 'fa-folder-open', 'Archivo', true, ['/visor']],
        ['Documentos y diseño', '/datos-obra', 'fa-file-contract', 'Datos de obra'],
        ['Documentos y diseño', '/graficos', 'fa-pen-ruler', 'Editor', true, ['/simbolos']],
        ['Documentos y diseño', '/redactor', 'fa-file-pen', 'Redactor'],
        ['Documentos y diseño', '/sheetflow', 'fa-table-cells', 'SheetFlow'],
        ['Documentos y diseño', '/view360', 'fa-street-view', 'View360'],
        ['Documentos y diseño', '/ensamblador-documentos', 'fa-object-group', 'Unir documentos (PDF)'],
        ['Documentos y diseño', '/comparador', 'fa-code-compare', 'Comparador de documentos'],
        ['Documentos y diseño', '/traductor', 'fa-language', 'Traductor'],
        ['Documentos y diseño', '/normativa', 'fa-scale-balanced', 'Normativa y especificaciones'],
        ['Inteligencia artificial', '/programador', 'fa-wand-magic-sparkles', 'Programador de actividades', $admin],
        ['Inteligencia artificial', '/inconsistencias', 'fa-magnifying-glass-chart', 'Revisión de datos', $admin, ['/analisis-materiales']],
        ['Inteligencia artificial', '/propuestas-ia', 'fa-robot', 'Centro de IA', $admin, ['/informes-ia', '/grabaciones', '/historial-cambios', '/biblioteca-ia']],
        ['Inteligencia artificial', '/revisiones', 'fa-clipboard-check', 'Revisiones', $admin],
        ['Administración', '/directorio', 'fa-address-book', 'Directorio', $gestiona && !is_visitante(), ['/especialidades']],
        ['Administración', '/acciones', 'fa-bolt', 'Acciones rápidas', $admin],
        ['Proyecto', '/ajustes', 'fa-gear', 'Ajustes de proyecto', !is_visitante()],
        ['Proyecto', '/exportar', 'fa-file-export', 'Exportar', !is_visitante()],
    ];
    $ayudas = ayudas_herramientas();
    static $nombreLookahead = [];   // nombre propio del Lookahead por proyecto (Ajustes del Lookahead)
    $nombreLookahead[$id] ??= \Model\LookaheadConfig::deProyecto($id)->nombre;
    $grupos = [];
    foreach ($defs as $d) {
        [$cat, $ruta, $icono, $etiqueta] = $d;
        if ($ruta === '/lookahead' && $nombreLookahead[$id] !== '') $etiqueta = $nombreLookahead[$id];
        $visible = $d[4] ?? true;
        $tambien = $d[5] ?? [];
        // Una entrada que agrupa herramientas hermanas se ve si el visitante puede usar cualquiera; lleva a la primera que tenga permitida
        $permitidas = array_values(array_filter(array_merge([$ruta], $tambien), fn($r) => herramienta_permitida(herramienta_de_ruta($r))));
        if ($visible === false || !$permitidas) continue;
        $destino = $permitidas[0];
        $grupos[$cat][] = ['url' => "$destino?proyecto=$id", 'icon' => $icono, 'label' => $etiqueta, 'match' => $ruta, 'key' => ltrim($ruta, '/'), 'tambien' => $tambien, 'ayuda' => $ayudas[$ruta][0] ?? '', 'manual' => $ayudas[$ruta][1] ?? ''];
    }
    return $grupos;
}

/**
 * Pestañas que agrupan herramientas hermanas (comparten una sola entrada del menú). Se dibuja arriba de la página.
 * $grupo: lookahead | archivo | revision | editor. $activa: ruta de la pestaña actual. Solo muestra las que el usuario puede usar.
 */
function pestanas_herramienta(string $grupo, string $activa, $proyecto): void
{
    $grupos = [
        'lookahead' => ['fa-binoculars', 'Lookahead y planificador', [['/lookahead', 'fa-binoculars', 'Lookahead'], ['/planificador', 'fa-diagram-next', 'Planificador']]],
        'archivo' => ['fa-folder-open', 'Archivo', [['/archivos', 'fa-folder-open', 'Archivo'], ['/visor', 'fa-eye', 'Visor']]],
        'revision' => ['fa-magnifying-glass-chart', 'Revisión de datos (IA)', [['/inconsistencias', 'fa-magnifying-glass-chart', 'Inconsistencias'], ['/analisis-materiales', 'fa-boxes-stacked', 'Análisis de materiales']]],
        'editor' => ['fa-pen-ruler', 'Editor', [['/graficos', 'fa-pen-ruler', 'Gráficos'], ['/simbolos', 'fa-shapes', 'Símbolos, estampas y cajetines']]],
    ];
    if (!isset($grupos[$grupo]) || !$proyecto) return;
    [$icono, $titulo, $tabs] = $grupos[$grupo];
    $tabs = array_values(array_filter($tabs, fn($t) => herramienta_permitida(herramienta_de_ruta($t[0]))));
    if (count($tabs) < 2) return;
    echo '<nav class="centro-ia-tabs no-print" aria-label="' . s($titulo) . '"><span class="centro-ia-tabs__titulo"><i class="fa-solid ' . $icono . '"></i> ' . s(t($titulo)) . '</span>';
    foreach ($tabs as [$ruta, $ic, $nombre]) {
        $activaEsta = $ruta === $activa;
        echo '<a href="' . s($ruta . '?proyecto=' . (int)$proyecto->id) . '" class="centro-ia-tabs__tab' . ($activaEsta ? ' activa' : '') . '"' . ($activaEsta ? ' aria-current="page"' : '') . '><i class="fa-solid ' . $ic . '"></i> ' . s(t($nombre)) . '</a>';
    }
    echo '</nav>';
}
