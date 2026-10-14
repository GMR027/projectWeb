<?php
// Helper para generar breadcrumbs de navegación del proyecto
function breadcrumb_proyecto($proyecto, $pagina_actual = '') {
    if (!$proyecto) return;
    $items = [
        ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Proyectos', 'key' => 'proyectos'],
        ['url' => '/programa?proyecto=' . $proyecto->id, 'icon' => 'fa-list-check', 'label' => 'Programa', 'key' => 'programa'],
        ['url' => '/lookahead?proyecto=' . $proyecto->id, 'icon' => 'fa-binoculars', 'label' => 'Lookahead 4S', 'key' => 'lookahead'],
        ['url' => '/vplan?proyecto=' . $proyecto->id, 'icon' => 'fa-map-location-dot', 'label' => 'vPlan', 'key' => 'vplan'],
        ['url' => '/lastplanner?proyecto=' . $proyecto->id, 'icon' => 'fa-note-sticky', 'label' => 'Last Planner', 'key' => 'lastplanner'],
        ['url' => '/reportes?proyecto=' . $proyecto->id, 'icon' => 'fa-clipboard-list', 'label' => 'Reporte diario', 'key' => 'reportes'],
        ['url' => '/bitacora?proyecto=' . $proyecto->id, 'icon' => 'fa-book', 'label' => 'Bitácora', 'key' => 'bitacora'],
        ['url' => '/minutas?proyecto=' . $proyecto->id, 'icon' => 'fa-handshake', 'label' => 'Minutas', 'key' => 'minutas'],
        ['url' => '/archivos?proyecto=' . $proyecto->id, 'icon' => 'fa-folder-open', 'label' => 'Archivo', 'key' => 'archivos'],
        ['url' => '/curvas?proyecto=' . $proyecto->id, 'icon' => 'fa-chart-line', 'label' => 'Curvas', 'key' => 'curvas'],
        ['url' => '/msr?proyecto=' . $proyecto->id, 'icon' => 'fa-truck-ramp-box', 'label' => 'MSR', 'key' => 'msr'],
    ];
    if (puede_gestionar_proyecto($proyecto)) {
        $items[] = ['url' => '/submittals?proyecto=' . $proyecto->id, 'icon' => 'fa-boxes-packing', 'label' => 'Submittals', 'key' => 'submittals'];
        $items[] = ['url' => '/materiales?proyecto=' . $proyecto->id, 'icon' => 'fa-cubes', 'label' => 'Materiales', 'key' => 'materiales'];
    }
    $items[] = ['url' => '/calendario?proyecto=' . $proyecto->id, 'icon' => 'fa-calendar-days', 'label' => 'Calendario', 'key' => 'calendario'];
    if (!is_visitante()) {
        $items[] = ['url' => '/ajustes?proyecto=' . $proyecto->id, 'icon' => 'fa-gear', 'label' => 'Ajustes', 'key' => 'ajustes'];
    }
    if (puede_descargar()) {
        $items[] = ['url' => '/exportar?proyecto=' . $proyecto->id, 'icon' => 'fa-file-export', 'label' => 'Exportar', 'key' => 'exportar'];
    }
    ?>
    <nav class="breadcrumb-nav">
        <div class="breadcrumb-container">
            <?php foreach ($items as $item): ?>
                <a href="<?php echo s($item['url']); ?>"
                   class="breadcrumb-item <?php echo $pagina_actual === $item['key'] ? 'active' : ''; ?>">
                    <i class="fa-solid <?php echo s($item['icon']); ?>"></i>
                    <span><?php echo s($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php
}
