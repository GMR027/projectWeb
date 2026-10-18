<?php
// Helper para generar breadcrumbs de navegación del proyecto
function breadcrumb_proyecto($proyecto, $pagina_actual = '') {
    if (!$proyecto) return;
    $items = [
        ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Proyectos', 'key' => 'proyectos'],
        ['url' => '/programa?proyecto=' . $proyecto->id, 'icon' => 'fa-list-check', 'label' => 'Programa', 'key' => 'programa'],
        ['url' => '/dependencias?proyecto=' . $proyecto->id, 'icon' => 'fa-diagram-project', 'label' => 'Dependencias', 'key' => 'dependencias'],
        ['url' => '/lookahead?proyecto=' . $proyecto->id, 'icon' => 'fa-binoculars', 'label' => 'Lookahead 4S', 'key' => 'lookahead'],
        ['url' => '/vplan?proyecto=' . $proyecto->id, 'icon' => 'fa-map-location-dot', 'label' => 'vPlan', 'key' => 'vplan'],
        ['url' => '/lastplanner?proyecto=' . $proyecto->id, 'icon' => 'fa-note-sticky', 'label' => 'Last Planner', 'key' => 'lastplanner'],
        ['url' => '/reportes?proyecto=' . $proyecto->id, 'icon' => 'fa-clipboard-list', 'label' => 'Reporte diario', 'key' => 'reportes'],
        ['url' => '/bitacora?proyecto=' . $proyecto->id, 'icon' => 'fa-book', 'label' => 'Bitácora', 'key' => 'bitacora'],
        ['url' => '/minutas?proyecto=' . $proyecto->id, 'icon' => 'fa-handshake', 'label' => 'Minutas', 'key' => 'minutas'],
        ['url' => '/archivos?proyecto=' . $proyecto->id, 'icon' => 'fa-folder-open', 'label' => 'Archivo', 'key' => 'archivos'],
        ['url' => '/curvas?proyecto=' . $proyecto->id, 'icon' => 'fa-chart-line', 'label' => 'Curvas', 'key' => 'curvas'],
        ['url' => '/msr?proyecto=' . $proyecto->id, 'icon' => 'fa-truck-ramp-box', 'label' => 'MSR', 'key' => 'msr'],
        ['url' => '/cambios?proyecto=' . $proyecto->id, 'icon' => 'fa-file-circle-exclamation', 'label' => 'Cambios', 'key' => 'cambios'],
    ];
    if (puede_gestionar_proyecto($proyecto)) {
        $items[] = ['url' => '/submittals?proyecto=' . $proyecto->id, 'icon' => 'fa-boxes-packing', 'label' => 'Submittals', 'key' => 'submittals'];
        $items[] = ['url' => '/materiales?proyecto=' . $proyecto->id, 'icon' => 'fa-cubes', 'label' => 'Materiales', 'key' => 'materiales'];
    }
    $items[] = ['url' => '/calendario?proyecto=' . $proyecto->id, 'icon' => 'fa-calendar-days', 'label' => 'Calendario', 'key' => 'calendario'];
    $items[] = ['url' => '/evidencias?proyecto=' . $proyecto->id, 'icon' => 'fa-camera-retro', 'label' => 'Evidencias', 'key' => 'evidencias'];
    $items[] = ['url' => '/graficos?proyecto=' . $proyecto->id, 'icon' => 'fa-pen-ruler', 'label' => 'Editor', 'key' => 'editor'];
    $items[] = ['url' => '/redactor?proyecto=' . $proyecto->id, 'icon' => 'fa-file-pen', 'label' => 'Redactor', 'key' => 'redactor'];
    $items[] = ['url' => '/simbolos?proyecto=' . $proyecto->id, 'icon' => 'fa-shapes', 'label' => 'Símbolos', 'key' => 'simbolos'];
    $items[] = ['url' => '/view360?proyecto=' . $proyecto->id, 'icon' => 'fa-street-view', 'label' => 'View360', 'key' => 'view360'];
    $items[] = ['url' => '/comparador?proyecto=' . $proyecto->id, 'icon' => 'fa-code-compare', 'label' => 'Comparador', 'key' => 'comparador'];
    if (!is_visitante()) {
        $items[] = ['url' => '/ajustes?proyecto=' . $proyecto->id, 'icon' => 'fa-gear', 'label' => 'Ajustes', 'key' => 'ajustes'];
    }
    if (puede_descargar()) {
        $items[] = ['url' => '/exportar?proyecto=' . $proyecto->id, 'icon' => 'fa-file-export', 'label' => 'Exportar', 'key' => 'exportar'];
    }
    $items = array_values(array_filter($items, fn($i) => herramienta_permitida(herramienta_de_ruta($i['url']))));
    ?>
    <nav class="breadcrumb-nav">
        <div class="breadcrumb-container">
            <?php foreach ($items as $item): ?>
                <a href="<?php echo s($item['url']); ?>"
                   class="breadcrumb-item <?php echo $pagina_actual === $item['key'] ? 'active' : ''; ?>">
                    <i class="fa-solid <?php echo s($item['icon']); ?>"></i>
                    <span><?php echo s(t($item['label'])); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php
}
