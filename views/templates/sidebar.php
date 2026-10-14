<?php
$rol = $_SESSION['rol'] ?? 'visitante';
$proyectoCtx = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);

// Menú principal según el rol
$menu = [
    ['url' => '/dashboard', 'icon' => 'fa-house', 'label' => 'Inicio', 'match' => '/dashboard'],
];
if ($rol === 'admin') {
    $menu[] = ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Portafolio de Proyectos', 'match' => '/proyectos'];
    $menu[] = ['url' => '/usuarios', 'icon' => 'fa-users-gear', 'label' => 'Usuarios y Accesos', 'match' => '/usuarios'];
    $menu[] = ['url' => '/bandeja', 'icon' => 'fa-inbox', 'label' => 'Bandeja de entrada', 'match' => '/bandeja', 'badge' => bandeja_sin_leer()];
} elseif ($rol === 'cliente') {
    $menu[] = ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Mis Proyectos', 'match' => '/proyectos'];
    if (puede_crear_proyecto()) $menu[] = ['url' => '/proyectos/crear', 'icon' => 'fa-plus', 'label' => 'Nuevo Proyecto', 'match' => '/proyectos/crear'];
    $menu[] = ['url' => '/usuarios', 'icon' => 'fa-user-group', 'label' => 'Mis Visitantes', 'match' => '/usuarios'];
    $menu[] = ['url' => '/soporte', 'icon' => 'fa-headset', 'label' => 'Soporte', 'match' => '/soporte'];
} else {
    $menu[] = ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Proyectos Asignados', 'match' => '/proyectos'];
}

// Accesos del proyecto en curso (solo si el usuario puede verlo)
$menuProyecto = [];
if ($proyectoCtx) {
    $proyectoSidebar = \Model\Proyecto::find($proyectoCtx);
    if ($proyectoSidebar && puede_ver_proyecto($proyectoSidebar)) {
        $menuProyecto[] = ['url' => "/programa?proyecto=$proyectoCtx", 'icon' => 'fa-list-check', 'label' => 'Programa', 'match' => '/programa'];
        $menuProyecto[] = ['url' => "/lookahead?proyecto=$proyectoCtx", 'icon' => 'fa-binoculars', 'label' => 'Lookahead 4S', 'match' => '/lookahead'];
        $menuProyecto[] = ['url' => "/vplan?proyecto=$proyectoCtx", 'icon' => 'fa-map-location-dot', 'label' => 'vPlan', 'match' => '/vplan'];
        $menuProyecto[] = ['url' => "/lastplanner?proyecto=$proyectoCtx", 'icon' => 'fa-note-sticky', 'label' => 'Last Planner', 'match' => '/lastplanner'];
        $menuProyecto[] = ['url' => "/reportes?proyecto=$proyectoCtx", 'icon' => 'fa-clipboard-list', 'label' => 'Reporte diario', 'match' => '/reportes'];
        $menuProyecto[] = ['url' => "/bitacora?proyecto=$proyectoCtx", 'icon' => 'fa-book', 'label' => 'Bitácora de obra', 'match' => '/bitacora'];
        $menuProyecto[] = ['url' => "/minutas?proyecto=$proyectoCtx", 'icon' => 'fa-handshake', 'label' => 'Minutas', 'match' => '/minutas'];
        $menuProyecto[] = ['url' => "/archivos?proyecto=$proyectoCtx", 'icon' => 'fa-folder-open', 'label' => 'Archivo', 'match' => '/archivos'];
        $menuProyecto[] = ['url' => "/curvas?proyecto=$proyectoCtx", 'icon' => 'fa-chart-line', 'label' => 'Curvas de productividad', 'match' => '/curvas'];
        $menuProyecto[] = ['url' => "/msr?proyecto=$proyectoCtx", 'icon' => 'fa-truck-ramp-box', 'label' => 'MSR · Materiales', 'match' => '/msr'];
        if (puede_gestionar_proyecto($proyectoSidebar)) {
            $menuProyecto[] = ['url' => "/submittals?proyecto=$proyectoCtx", 'icon' => 'fa-boxes-packing', 'label' => 'Submittals', 'match' => '/submittals'];
            $menuProyecto[] = ['url' => "/materiales?proyecto=$proyectoCtx", 'icon' => 'fa-cubes', 'label' => 'Catálogo de materiales', 'match' => '/materiales'];
        }
        $menuProyecto[] = ['url' => "/calendario?proyecto=$proyectoCtx", 'icon' => 'fa-calendar-days', 'label' => 'Calendario', 'match' => '/calendario'];
        if ($rol !== 'visitante') {
            $menuProyecto[] = ['url' => "/ajustes?proyecto=$proyectoCtx", 'icon' => 'fa-gear', 'label' => 'Ajustes', 'match' => '/ajustes'];
            $menuProyecto[] = ['url' => "/exportar?proyecto=$proyectoCtx", 'icon' => 'fa-file-export', 'label' => 'Exportar', 'match' => '/exportar'];
        }
    }
}
$etiquetasRol = ['admin' => 'Administrador', 'cliente' => 'Cliente', 'visitante' => 'Visitante'];
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="/dashboard" class="logo">
            <i class="fa-solid fa-helmet-safety logo-icon"></i>
            <span class="logo-text">ProjectWeb</span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menu as $item): ?>
            <a href="<?php echo s($item['url']); ?>" class="nav-link <?php echo $_SERVER['REQUEST_URI'] !== '/' && pagina_actual($item['match']) && !($item['match'] === '/proyectos' && pagina_actual('/proyectos/crear')) ? 'active' : ''; ?>">
                <i class="fa-solid <?php echo s($item['icon']); ?> nav-icon"></i>
                <span><?php echo s($item['label']); ?></span>
                <?php if (!empty($item['badge'])): ?><b class="nav-badge"><?php echo (int)$item['badge']; ?></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if ($menuProyecto): ?>
            <div class="nav-section">Proyecto actual</div>
            <?php foreach ($menuProyecto as $item): ?>
                <a href="<?php echo s($item['url']); ?>" class="nav-link <?php echo pagina_actual($item['match']) ? 'active' : ''; ?>">
                    <i class="fa-solid <?php echo s($item['icon']); ?> nav-icon"></i>
                    <span><?php echo s($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="version-tag"><?php echo s($etiquetasRol[$rol] ?? 'Visitante'); ?> · v1.0.0</div>
    </div>
</aside>
