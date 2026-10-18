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
    $menu[] = ['url' => '/sistema', 'icon' => 'fa-gear', 'label' => 'Ajustes', 'match' => '/sistema'];
} elseif ($rol === 'cliente') {
    $menu[] = ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Mis Proyectos', 'match' => '/proyectos'];
    if (puede_crear_proyecto()) $menu[] = ['url' => '/proyectos/crear', 'icon' => 'fa-plus', 'label' => 'Nuevo Proyecto', 'match' => '/proyectos/crear'];
    $menu[] = ['url' => '/usuarios', 'icon' => 'fa-user-group', 'label' => 'Mis Visitantes', 'match' => '/usuarios'];
    $menu[] = ['url' => '/soporte', 'icon' => 'fa-headset', 'label' => 'Soporte', 'match' => '/soporte', 'badge' => soporte_respuestas_nuevas()];
} else {
    $menu[] = ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Proyectos Asignados', 'match' => '/proyectos'];
}

// Accesos del proyecto en curso (solo si el usuario puede verlo)
$menuProyecto = [];
if ($proyectoCtx) {
    $proyectoSidebar = \Model\Proyecto::find($proyectoCtx);
    if ($proyectoSidebar && puede_ver_proyecto($proyectoSidebar)) {
        $menuProyecto[] = ['url' => "/programa?proyecto=$proyectoCtx", 'icon' => 'fa-list-check', 'label' => 'Programa', 'match' => '/programa'];
        $menuProyecto[] = ['url' => "/dependencias?proyecto=$proyectoCtx", 'icon' => 'fa-diagram-project', 'label' => 'Dependencias de obra', 'match' => '/dependencias'];
        $menuProyecto[] = ['url' => "/lookahead?proyecto=$proyectoCtx", 'icon' => 'fa-binoculars', 'label' => 'Lookahead 4S', 'match' => '/lookahead'];
        $menuProyecto[] = ['url' => "/vplan?proyecto=$proyectoCtx", 'icon' => 'fa-map-location-dot', 'label' => 'vPlan', 'match' => '/vplan'];
        $menuProyecto[] = ['url' => "/lastplanner?proyecto=$proyectoCtx", 'icon' => 'fa-note-sticky', 'label' => 'Last Planner', 'match' => '/lastplanner'];
        $menuProyecto[] = ['url' => "/reportes?proyecto=$proyectoCtx", 'icon' => 'fa-clipboard-list', 'label' => 'Reporte diario', 'match' => '/reportes'];
        $menuProyecto[] = ['url' => "/bitacora?proyecto=$proyectoCtx", 'icon' => 'fa-book', 'label' => 'Bitácora de obra', 'match' => '/bitacora'];
        $menuProyecto[] = ['url' => "/minutas?proyecto=$proyectoCtx", 'icon' => 'fa-handshake', 'label' => 'Minutas', 'match' => '/minutas'];
        $menuProyecto[] = ['url' => "/evidencias?proyecto=$proyectoCtx", 'icon' => 'fa-camera-retro', 'label' => 'Evidencias', 'match' => '/evidencias'];
        $menuProyecto[] = ['url' => "/archivos?proyecto=$proyectoCtx", 'icon' => 'fa-folder-open', 'label' => 'Archivo', 'match' => '/archivos'];
        $menuProyecto[] = ['url' => "/curvas?proyecto=$proyectoCtx", 'icon' => 'fa-chart-line', 'label' => 'Curvas de productividad', 'match' => '/curvas'];
        $menuProyecto[] = ['url' => "/msr?proyecto=$proyectoCtx", 'icon' => 'fa-truck-ramp-box', 'label' => 'MSR · Materiales', 'match' => '/msr'];
        $menuProyecto[] = ['url' => "/cambios?proyecto=$proyectoCtx", 'icon' => 'fa-file-circle-exclamation', 'label' => 'Órdenes de cambio', 'match' => '/cambios'];
        $menuProyecto[] = ['url' => "/lineas-base?proyecto=$proyectoCtx", 'icon' => 'fa-timeline', 'label' => 'Líneas base', 'match' => '/lineas-base'];
        if (puede_gestionar_proyecto($proyectoSidebar)) {
            $menuProyecto[] = ['url' => "/submittals?proyecto=$proyectoCtx", 'icon' => 'fa-boxes-packing', 'label' => 'Submittals', 'match' => '/submittals'];
            $menuProyecto[] = ['url' => "/materiales?proyecto=$proyectoCtx", 'icon' => 'fa-cubes', 'label' => 'Catálogo de materiales', 'match' => '/materiales'];
        }
        $menuProyecto[] = ['url' => "/plantillas?proyecto=$proyectoCtx", 'icon' => 'fa-layer-group', 'label' => 'Tipo de obra', 'match' => '/plantillas'];
        $menuProyecto[] = ['url' => "/seguridad?proyecto=$proyectoCtx", 'icon' => 'fa-helmet-safety', 'label' => 'Seguridad y permisos', 'match' => '/seguridad'];
        $menuProyecto[] = ['url' => "/punch?proyecto=$proyectoCtx", 'icon' => 'fa-clipboard-check', 'label' => 'Punch list', 'match' => '/punch'];
        $menuProyecto[] = ['url' => "/mapa-avance?proyecto=$proyectoCtx", 'icon' => 'fa-map-location-dot', 'label' => 'Mapa de avance', 'match' => '/mapa-avance'];
        $menuProyecto[] = ['url' => "/revisiones?proyecto=$proyectoCtx", 'icon' => 'fa-clipboard-check', 'label' => 'Revisiones', 'match' => '/revisiones'];
        $menuProyecto[] = ['url' => "/normativa?proyecto=$proyectoCtx", 'icon' => 'fa-scale-balanced', 'label' => 'Normativa y especificaciones', 'match' => '/normativa'];
        $menuProyecto[] = ['url' => "/calendario?proyecto=$proyectoCtx", 'icon' => 'fa-calendar-days', 'label' => 'Calendario', 'match' => '/calendario'];
        $menuProyecto[] = ['url' => "/graficos?proyecto=$proyectoCtx", 'icon' => 'fa-pen-ruler', 'label' => 'Editor', 'match' => '/graficos'];
        $menuProyecto[] = ['url' => "/redactor?proyecto=$proyectoCtx", 'icon' => 'fa-file-pen', 'label' => 'Redactor', 'match' => '/redactor'];
        $menuProyecto[] = ['url' => "/simbolos?proyecto=$proyectoCtx", 'icon' => 'fa-shapes', 'label' => 'Símbolos', 'match' => '/simbolos'];
        $menuProyecto[] = ['url' => "/view360?proyecto=$proyectoCtx", 'icon' => 'fa-street-view', 'label' => 'View360', 'match' => '/view360'];
        if (is_admin()) $menuProyecto[] = ['url' => "/programador?proyecto=$proyectoCtx", 'icon' => 'fa-wand-magic-sparkles', 'label' => 'Programador de actividades', 'match' => '/programador'];
        if (is_admin()) $menuProyecto[] = ['url' => "/inconsistencias?proyecto=$proyectoCtx", 'icon' => 'fa-magnifying-glass-chart', 'label' => 'Inconsistencias de proyecto', 'match' => '/inconsistencias'];
        if (is_admin()) $menuProyecto[] = ['url' => "/analisis-materiales?proyecto=$proyectoCtx", 'icon' => 'fa-boxes-stacked', 'label' => 'Análisis de materiales', 'match' => '/analisis-materiales'];
        if (is_admin()) $menuProyecto[] = ['url' => "/propuestas-ia?proyecto=$proyectoCtx", 'icon' => 'fa-robot', 'label' => 'Centro de IA', 'match' => '/propuestas-ia', 'tambien' => ['/informes-ia', '/grabaciones', '/historial-cambios', '/biblioteca-ia']];
        $menuProyecto[] = ['url' => "/comparador?proyecto=$proyectoCtx", 'icon' => 'fa-code-compare', 'label' => 'Comparador de documentos', 'match' => '/comparador'];
        if ($rol !== 'visitante') {
            $menuProyecto[] = ['url' => "/ajustes?proyecto=$proyectoCtx", 'icon' => 'fa-gear', 'label' => 'Ajustes', 'match' => '/ajustes'];
            $menuProyecto[] = ['url' => "/exportar?proyecto=$proyectoCtx", 'icon' => 'fa-file-export', 'label' => 'Exportar', 'match' => '/exportar'];
        }
    }
}
// Los visitantes solo ven las herramientas que se les permitieron
$menuProyecto = array_values(array_filter($menuProyecto, fn($i) => herramienta_permitida(herramienta_de_ruta($i['match']))));
// Acciones rápidas del proyecto en curso
$accionesRapidas = [];
if (!empty($proyectoSidebar) && $menuProyecto && herramienta_permitida('acciones')) {
    try {
        $accionesRapidas = \Classes\AccionesRapidas::secciones((int)$proyectoSidebar->id, puede_gestionar_proyecto($proyectoSidebar));
    } catch (\Throwable $e) {
        $accionesRapidas = [];
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
            <a href="<?php echo s($item['url']); ?>" class="nav-link <?php echo $_SERVER['REQUEST_URI'] !== '/' && (pagina_actual($item['match']) || array_filter($item['tambien'] ?? [], 'pagina_actual')) && !($item['match'] === '/proyectos' && pagina_actual('/proyectos/crear')) ? 'active' : ''; ?>">
                <i class="fa-solid <?php echo s($item['icon']); ?> nav-icon"></i>
                <span><?php echo s(t($item['label'])); ?></span>
                <?php if (!empty($item['badge'])): ?><b class="nav-badge"><?php echo (int)$item['badge']; ?></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if ($menuProyecto || ($proyectoSidebar ?? null)): ?>
            <?php if (herramienta_permitida('planificador')): ?>
            <a href="/planificador?proyecto=<?php echo (int)$proyectoCtx; ?>" class="nav-link <?php echo pagina_actual('/planificador') ? 'active' : ''; ?>">
                <i class="fa-solid fa-diagram-next nav-icon"></i>
                <span><?php echo s(t('Planificador')); ?></span>
            </a>
            <?php endif; ?>
            <div class="nav-section"><?php echo s(t('Proyecto actual')); ?></div>
            <?php foreach ($menuProyecto as $item): ?>
                <a href="<?php echo s($item['url']); ?>" class="nav-link <?php echo pagina_actual($item['match']) ? 'active' : ''; ?>">
                    <i class="fa-solid <?php echo s($item['icon']); ?> nav-icon"></i>
                    <span><?php echo s(t($item['label'])); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($accionesRapidas): ?>
            <?php $totalAcciones = array_sum(array_column($accionesRapidas, 'total')); ?>
            <div class="nav-section"><?php echo s(t('Acciones rápidas')); ?></div>
            <?php if ($totalAcciones > 5): ?>
                <a href="/acciones?proyecto=<?php echo (int)$proyectoSidebar->id; ?>" target="_blank" rel="noopener" class="nav-link acc-todas">
                    <i class="fa-solid fa-up-right-from-square nav-icon"></i>
                    <span><?php echo s(t('Ver todas en otra pestaña')); ?></span>
                    <b class="nav-badge"><?php echo (int)$totalAcciones; ?></b>
                </a>
            <?php endif; ?>
            <?php foreach ($accionesRapidas as $sec): ?>
                <details class="acc-sec"<?php echo $sec['abierta'] && $sec['total'] ? ' open' : ''; ?>>
                    <summary class="<?php echo $sec['total'] ? '' : 'vacia'; ?>">
                        <i class="fa-solid <?php echo s($sec['icono']); ?> nav-icon"></i>
                        <span><?php echo s($sec['titulo']); ?></span>
                        <?php if ($sec['total']): ?><b class="nav-badge"><?php echo (int)$sec['total']; ?></b><?php endif; ?>
                    </summary>
                    <?php if (!$sec['items']): ?>
                        <p class="acc-vacio"><?php echo s(t('Nada pendiente')); ?></p>
                    <?php else: ?>
                        <ul class="acc-lista">
                            <?php foreach ($sec['items'] as $it): ?>
                                <li><a href="<?php echo s($it['url']); ?>">
                                    <span class="acc-titulo"><?php echo !empty($it['wbs']) ? '<span class="mono">' . s($it['wbs']) . '</span> · ' : ''; echo s($it['titulo']); ?></span>
                                    <span class="acc-sub"><?php if ($it['chip']): ?><em class="acc-chip acc-<?php echo s(strtolower(str_replace('/', '', $it['chip']))); ?>"><?php echo s($it['chip']); ?></em> <?php endif; ?><?php echo s($it['sub']); ?></span>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($sec['total'] > count($sec['items'])): ?>
                            <a class="acc-mas" href="/acciones?proyecto=<?php echo (int)$proyectoSidebar->id; ?>#acc-<?php echo s($sec['clave']); ?>" target="_blank" rel="noopener">Ver todas (<?php echo (int)$sec['total']; ?>) →</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="/manual<?php echo $proyectoCtx ? '?proyecto=' . (int)$proyectoCtx : ''; ?>" class="nav-link manual-link <?php echo pagina_actual('/manual') ? 'active' : ''; ?>">
            <i class="fa-solid fa-book-open nav-icon"></i>
            <span><?php echo s(t('Manual de usuario')); ?></span>
        </a>
        <div class="version-tag"><?php echo s($etiquetasRol[$rol] ?? 'Visitante'); ?> · v1.0.0</div>
    </div>
</aside>
