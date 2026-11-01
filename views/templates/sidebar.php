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
    $menu[] = ['url' => '/sistema', 'icon' => 'fa-gear', 'label' => 'Ajustes de app', 'match' => '/sistema'];
} elseif ($rol === 'cliente') {
    $menu[] = ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Mis Proyectos', 'match' => '/proyectos'];
    if (puede_crear_proyecto()) $menu[] = ['url' => '/proyectos/crear', 'icon' => 'fa-plus', 'label' => 'Nuevo Proyecto', 'match' => '/proyectos/crear'];
    $menu[] = ['url' => '/usuarios', 'icon' => 'fa-user-group', 'label' => 'Mi equipo', 'match' => '/usuarios'];
    $menu[] = ['url' => '/soporte', 'icon' => 'fa-headset', 'label' => 'Soporte', 'match' => '/soporte', 'badge' => soporte_respuestas_nuevas()];
} else {
    $menu[] = ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Proyectos Asignados', 'match' => '/proyectos'];
}

// Accesos del proyecto en curso (solo si el usuario puede verlo): mismas categorías que la barra de iconos
require_once __DIR__ . '/herramientas-proyecto.php';
$gruposProyecto = [];
if ($proyectoCtx) {
    // Si la vista ya cargó el proyecto, se reutiliza (antes se consultaba dos veces en la misma página)
    $proyectoSidebar = (isset($proyecto) && $proyecto instanceof \Model\Proyecto && (int)$proyecto->id === (int)$proyectoCtx)
        ? $proyecto
        : \Model\Proyecto::find($proyectoCtx);
    if ($proyectoSidebar && puede_ver_proyecto($proyectoSidebar)) $gruposProyecto = herramientas_proyecto($proyectoSidebar);
}
$menuProyecto = array_merge(...array_values($gruposProyecto ?: [[]]));
// Acciones rápidas: se cargan aparte (/api/acciones) la primera vez que se abre el menú.
// Antes se calculaban en cada página (~20 consultas) aunque el panel estuviera cerrado.
$cargarAcciones = !empty($proyectoSidebar) && $menuProyecto && herramienta_permitida('acciones');
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
        <?php $ayudasMenu = ayudas_herramientas(); ?>
        <?php foreach ($menu as $item): ?>
            <?php if (($ayudasMenu[$item['match']] ?? null) && empty($item['ayuda'])) $item += ['ayuda' => $ayudasMenu[$item['match']][0], 'manual' => $ayudasMenu[$item['match']][1]]; ?>
            <?php $activo = $_SERVER['REQUEST_URI'] !== '/' && (pagina_actual($item['match']) || array_filter($item['tambien'] ?? [], 'pagina_actual')) && !($item['match'] === '/proyectos' && pagina_actual('/proyectos/crear')); ?>
            <a href="<?php echo s($item['url']); ?>" class="nav-link <?php echo $activo ? 'active' : ''; ?>"<?php echo $activo ? ' aria-current="page"' : ''; ?><?php echo atributos_ayuda($item); ?>>
                <i class="fa-solid <?php echo s($item['icon']); ?> nav-icon"></i>
                <span><?php echo s(t($item['label'])); ?></span>
                <?php if (!empty($item['badge'])): ?><b class="nav-badge"><?php echo (int)$item['badge']; ?></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <div id="nav-recientes" class="nav-recientes" data-titulo="<?php echo s(t('Recientes')); ?>"></div>
        <?php if ($menuProyecto || ($proyectoSidebar ?? null)): ?>
            <?php foreach ($gruposProyecto as $nombreGrupo => $itemsGrupo): ?>
                <div class="nav-section"><?php echo s(t($nombreGrupo)); ?></div>
                <?php foreach ($itemsGrupo as $item): ?>
                    <?php $activo = pagina_actual($item['match']) || array_filter($item['tambien'], 'pagina_actual'); ?>
                    <a href="<?php echo s($item['url']); ?>" class="nav-link <?php echo $activo ? 'active' : ''; ?>"<?php echo $activo ? ' aria-current="page"' : ''; ?><?php echo atributos_ayuda($item); ?>>
                        <i class="fa-solid <?php echo s($item['icon']); ?> nav-icon"></i>
                        <span><?php echo s(t($item['label'])); ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($cargarAcciones): ?>
            <div class="acc-lazy" id="acc-rapidas" data-url="/api/acciones?proyecto=<?php echo (int)$proyectoSidebar->id; ?>" data-cargada="0"></div>
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
