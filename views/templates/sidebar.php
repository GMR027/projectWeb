<?php
/**
 * Menú lateral general (drawer): Inicio, Proyectos, Portafolio, Usuarios, Bandeja,
 * Sistema, recientes y acciones rápidas. Las herramientas del proyecto viven en
 * la barra compacta / el riel y en el panel «Todas las herramientas».
 */
require_once __DIR__ . '/navegacion-global.php';
$rol = $_SESSION['rol'] ?? 'visitante';
$proyectoCtx = $proyectoCtx ?? filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
$proyectoSidebar = $proyectoSidebar ?? null;
if (!$proyectoSidebar && $proyectoCtx) {
    $pSidebar = \Model\Proyecto::find($proyectoCtx);
    if ($pSidebar && puede_ver_proyecto($pSidebar)) $proyectoSidebar = $pSidebar;
}
$menu = $menuGlobal ?? menu_global_nav();
$cargarAcciones = !empty($proyectoSidebar) && herramienta_permitida('acciones');
$etiquetasRol = ['admin' => 'Administrador', 'cliente' => 'Cliente', 'visitante' => 'Visitante'];
?>
<aside class="sidebar" id="sidebar" aria-label="<?php echo s(t('Menú general')); ?>">
    <div class="sidebar-header">
        <a href="/dashboard" class="logo">
            <i class="fa-solid fa-helmet-safety logo-icon"></i>
            <span class="logo-text">ProjectWeb</span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menu as $item): ?>
            <?php
            $activo = pagina_actual($item['match']);
            // La ficha de un proyecto (Salud del proyecto) no es el listado de proyectos.
            if ($item['match'] === '/proyectos' && pagina_actual('/proyectos/ver')) $activo = false;
            ?>
            <a href="<?php echo s($item['url']); ?>" class="nav-link <?php echo $activo ? 'active' : ''; ?>"<?php echo $activo ? ' aria-current="page"' : ''; ?><?php echo atributos_ayuda($item); ?>>
                <i class="fa-solid <?php echo s($item['icon']); ?> nav-icon"></i>
                <span><?php echo s(t($item['label'])); ?></span>
                <?php if (!empty($item['badge'])): ?><b class="nav-badge"><?php echo (int)$item['badge']; ?></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <div id="nav-recientes" class="nav-recientes" data-titulo="<?php echo s(t('Recientes')); ?>"></div>
        <?php if ($cargarAcciones): ?>
            <div class="acc-lazy" id="acc-rapidas" data-url="/api/acciones?proyecto=<?php echo (int)$proyectoSidebar->id; ?>" data-cargada="0"></div>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="/manual<?php echo $proyectoCtx ? '?proyecto=' . (int)$proyectoCtx : ''; ?>" class="nav-link manual-link <?php echo (pagina_actual('/manual') && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/manual/curso')) ? 'active' : ''; ?>">
            <i class="fa-solid fa-book-open nav-icon"></i>
            <span><?php echo s(t('Manual de usuario')); ?></span>
        </a>
        <?php if (is_admin()): ?>
        <a href="/manual/curso" class="nav-link manual-link <?php echo (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/manual/curso') ? 'active' : ''; ?>">
            <i class="fa-solid fa-graduation-cap nav-icon"></i>
            <span><?php echo s(t('Curso del administrador')); ?></span>
        </a>
        <?php endif; ?>
        <div class="version-tag"><?php echo s($etiquetasRol[$rol] ?? 'Visitante'); ?> · v1.0.0</div>
    </div>
</aside>
