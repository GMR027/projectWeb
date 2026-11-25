<?php
/**
 * Navegación inferior móvil: Inicio · Programa · Reporte diario · Más.
 * «Más» abre el panel de herramientas (o el menú general si no hay proyecto).
 */
function navegacion_inferior($proyecto): void
{
    $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
    $rol = $_SESSION['rol'] ?? 'visitante';
    $activo = static function (string $m) use ($ruta): bool {
        $m = rtrim($m, '/');
        return $ruta === $m || str_starts_with($ruta, $m . '/');
    };

    $items = [
        ['url' => '/dashboard', 'icon' => 'fa-house', 'label' => 'Inicio', 'match' => '/dashboard'],
    ];
    if ($proyecto) {
        if (herramienta_permitida('programa', '/programa')) {
            $items[] = ['url' => '/programa?proyecto=' . (int)$proyecto->id, 'icon' => 'fa-chart-gantt', 'label' => 'Programa', 'match' => '/programa'];
        }
        if (herramienta_permitida('reportes', '/reportes')) {
            $items[] = ['url' => '/reportes?proyecto=' . (int)$proyecto->id, 'icon' => 'fa-clipboard-list', 'label' => 'Reporte', 'match' => '/reportes'];
        }
    } else {
        $items[] = ['url' => '/proyectos', 'icon' => 'fa-building', 'label' => 'Proyectos', 'match' => '/proyectos'];
        if (in_array($rol, ['admin', 'cliente'], true)) {
            $items[] = ['url' => '/portafolio', 'icon' => 'fa-table-columns', 'label' => 'Portafolio', 'match' => '/portafolio'];
        }
    }
    ?>
    <nav class="nav-inferior" aria-label="<?php echo s(t('Navegación principal')); ?>">
        <?php foreach ($items as $item): $es = $activo($item['match']); ?>
            <a href="<?php echo s($item['url']); ?>" class="nav-inf-item<?php echo $es ? ' activo' : ''; ?>"<?php echo $es ? ' aria-current="page"' : ''; ?>>
                <i class="fa-solid <?php echo s($item['icon']); ?>" aria-hidden="true"></i>
                <span><?php echo s(t($item['label'])); ?></span>
            </a>
        <?php endforeach; ?>
        <button type="button" class="nav-inf-item" data-nav-mas="<?php echo $proyecto ? '1' : '0'; ?>"
                <?php echo $proyecto ? 'data-nav-todas' : 'data-nav-abrir-menu'; ?>
                aria-label="<?php echo s(t('Más herramientas')); ?>">
            <i class="fa-solid fa-grip" aria-hidden="true"></i>
            <span><?php echo s(t('Más')); ?></span>
        </button>
    </nav>
    <?php
}
