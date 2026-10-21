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
        '/lookahead' => ['Vista de las próximas semanas para liberar restricciones y comprometer el trabajo.', 'lookahead'],
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
        '/archivos' => ['Archivero del proyecto: planos, documentos y revisiones, con revisión antivirus.', 'archivos'],
        '/datos-obra' => ['Fechas contractuales, responsables, cantidades y contactos de la obra.', 'datos-obra'],
        '/graficos' => ['Editor de gráficos y planos: dibuja, anota y enlaza zonas.', 'editor'],
        '/redactor' => ['Procesador de texto del proyecto: documentos con tablas e imágenes, exportables a DOCX, ODT y PDF.', 'redactor'],
        '/sheetflow' => ['Tablas con fórmulas; importa y exporta XLSX, CSV y PDF.', 'sheetflow'],
        '/simbolos' => ['Biblioteca de símbolos para usar en el Editor y el vPlan.', 'simbolos'],
        '/view360' => ['Gráficos interactivos con zonas que abren vistas 360 de la obra.', 'view360'],
        '/comparador' => ['Compara dos imágenes o PDF con deslizador, fundido o diferencias.', 'comparador'],
        '/traductor' => ['Traduce español ↔ inglés un texto o un documento completo (PDF, Word, Excel, PowerPoint…).', 'traductor'],
        '/normativa' => ['Normas y especificaciones en PDF, ligadas a materiales y actividades.', 'normativa'],
        '/programador' => ['La IA propone partidas y actividades a partir de lo que describes; tú decides qué aceptar.', 'programador-ia'],
        '/inconsistencias' => ['Revisa el proyecto en busca de datos que no cuadran y propone correcciones.', 'inconsistencias'],
        '/analisis-materiales' => ['Analiza los materiales del proyecto con IA y sugiere ajustes.', 'analisis-materiales'],
        '/propuestas-ia' => ['Centro de IA: propuestas pendientes, informes, voz, chat y herramientas inteligentes.', 'centro-ia'],
        '/revisiones' => ['Estado de revisión (✅ ⚠ ❌) por herramienta del proyecto.', 'revisiones'],
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
        ['Planeación', '/planificador', 'fa-diagram-next', 'Planificador'],
        ['Planeación', '/dependencias', 'fa-diagram-project', 'Dependencias de obra'],
        ['Planeación', '/lookahead', 'fa-binoculars', 'Lookahead'],
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
        ['Campo', '/visor', 'fa-eye', 'Visor'],
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
        ['Documentos y diseño', '/ensamblador-documentos', 'fa-object-group', 'Ensamblador de documentos'],
        ['Documentos y diseño', '/comparador', 'fa-code-compare', 'Comparador de documentos'],
        ['Documentos y diseño', '/traductor', 'fa-language', 'Traductor'],
        ['Documentos y diseño', '/normativa', 'fa-scale-balanced', 'Normativa y especificaciones'],
        ['Inteligencia artificial', '/programador', 'fa-wand-magic-sparkles', 'Programador de actividades', $admin],
        ['Inteligencia artificial', '/inconsistencias', 'fa-magnifying-glass-chart', 'Inconsistencias de proyecto', $admin],
        ['Inteligencia artificial', '/analisis-materiales', 'fa-boxes-stacked', 'Análisis de materiales', $admin],
        ['Inteligencia artificial', '/propuestas-ia', 'fa-robot', 'Centro de IA', $admin, ['/informes-ia', '/grabaciones', '/historial-cambios', '/biblioteca-ia']],
        ['Inteligencia artificial', '/revisiones', 'fa-clipboard-check', 'Revisiones', $admin],
        ['Administración', '/especialidades', 'fa-tags', 'Especialidades', $admin],
        ['Administración', '/informes-ia', 'fa-file-lines', 'Informes de IA', $admin],
        ['Administración', '/grabaciones', 'fa-microphone', 'Grabaciones de reuniones', $admin],
        ['Administración', '/historial-cambios', 'fa-clock-rotate-left', 'Historial de cambios de IA', $admin],
        ['Administración', '/biblioteca-ia', 'fa-book-bookmark', 'Biblioteca de IA', $admin],
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
        if ($visible === false || !herramienta_permitida(herramienta_de_ruta($ruta))) continue;
        $grupos[$cat][] = ['url' => "$ruta?proyecto=$id", 'icon' => $icono, 'label' => $etiqueta, 'match' => $ruta, 'key' => ltrim($ruta, '/'), 'tambien' => $tambien, 'ayuda' => $ayudas[$ruta][0] ?? '', 'manual' => $ayudas[$ruta][1] ?? ''];
    }
    return $grupos;
}
