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
    $menu[] = ['url' => '/usuarios', 'icon' => 'fa-user-group', 'label' => 'Mis Visitantes', 'match' => '/usuarios'];
    $menu[] = ['url' => '/soporte', 'icon' => 'fa-headset', 'label' => 'Soporte', 'match' => '/soporte', 'badge' => soporte_respuestas_nuevas()];
} else {
    $menu[] = ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Proyectos Asignados', 'match' => '/proyectos'];
}

// Accesos del proyecto en curso (solo si el usuario puede verlo): mismas categorías que la barra de iconos
require_once __DIR__ . '/herramientas-proyecto.php';
$gruposProyecto = [];
if ($proyectoCtx) {
    $proyectoSidebar = \Model\Proyecto::find($proyectoCtx);
    if ($proyectoSidebar && puede_ver_proyecto($proyectoSidebar)) $gruposProyecto = herramientas_proyecto($proyectoSidebar);
}
$menuProyecto = array_merge(...array_values($gruposProyecto ?: [[]]));
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
        <?php $ayudasMenu = ayudas_herramientas(); ?>
        <?php foreach ($menu as $item): ?>
            <?php if (($ayudasMenu[$item['match']] ?? null) && empty($item['ayuda'])) $item += ['ayuda' => $ayudasMenu[$item['match']][0], 'manual' => $ayudasMenu[$item['match']][1]]; ?>
            <a href="<?php echo s($item['url']); ?>" class="nav-link <?php echo $_SERVER['REQUEST_URI'] !== '/' && (pagina_actual($item['match']) || array_filter($item['tambien'] ?? [], 'pagina_actual')) && !($item['match'] === '/proyectos' && pagina_actual('/proyectos/crear')) ? 'active' : ''; ?>"<?php echo atributos_ayuda($item); ?>>
                <i class="fa-solid <?php echo s($item['icon']); ?> nav-icon"></i>
                <span><?php echo s(t($item['label'])); ?></span>
                <?php if (!empty($item['badge'])): ?><b class="nav-badge"><?php echo (int)$item['badge']; ?></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if ($menuProyecto || ($proyectoSidebar ?? null)): ?>
            <?php foreach ($gruposProyecto as $nombreGrupo => $itemsGrupo): ?>
                <div class="nav-section"><?php echo s(t($nombreGrupo)); ?></div>
                <?php foreach ($itemsGrupo as $item): ?>
                    <a href="<?php echo s($item['url']); ?>" class="nav-link <?php echo pagina_actual($item['match']) || array_filter($item['tambien'], 'pagina_actual') ? 'active' : ''; ?>"<?php echo atributos_ayuda($item); ?>>
                        <i class="fa-solid <?php echo s($item['icon']); ?> nav-icon"></i>
                        <span><?php echo s(t($item['label'])); ?></span>
                    </a>
                <?php endforeach; ?>
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
