<?php
/**
 * Barra de contexto: sección + herramientas de esa sección.
 *
 * Modo compacto (predeterminado): dos filas finas (56 px de barra de aplicación
 * + 40 px de esta barra). En las páginas sin proyecto se muestra la navegación global.
 * El riel lateral (modo panel) usa las mismas secciones desde nav-riel.php.
 */
require_once __DIR__ . '/herramientas-proyecto.php';

/**
 * Herramienta activa según la ruta actual.
 * Devuelve [clave, largo_de_la_ruta, índice_de_grupo, item]. Si no hay, [null, -1, 0, null].
 */
function nav_activa(array $grupos): array
{
    $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
    $mejor = [null, -1, 0, null];
    $i = 0;
    foreach ($grupos as $items) {
        foreach ($items as $item) {
            $rutas = array_merge([(string)($item['match'] ?? '')], $item['tambien'] ?? []);
            foreach ($rutas as $m) {
                $m = rtrim((string)$m, '/');
                if ($m === '') continue;
                if (($ruta === $m || str_starts_with($ruta, $m . '/')) && strlen($m) > $mejor[1]) {
                    $mejor = [$item['key'] ?? '', strlen($m), $i, $item];
                }
            }
        }
        $i++;
    }
    return $mejor;
}

/** Ícono de cada sección (claves de herramientas_proyecto()). */
function nav_icono_grupo(string $grupo): string
{
    return [
        'Planeación' => 'fa-diagram-project',
        'Campo y ejecución' => 'fa-helmet-safety',
        'Materiales y compras' => 'fa-boxes-stacked',
        'Documentos y diseño' => 'fa-folder-open',
        'Inteligencia artificial' => 'fa-wand-magic-sparkles',
        'Administración' => 'fa-briefcase',
        'Proyecto' => 'fa-heart-pulse',
    ][$grupo] ?? 'fa-layer-group';
}

/** Herramienta activa de la navegación global. Devuelve [clave, largo, item]. */
function nav_global_activa(array $menu): array
{
    $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
    $mejor = [null, -1, null];
    foreach ($menu as $item) {
        $m = rtrim((string)($item['match'] ?? ''), '/');
        if ($m === '') continue;
        if (($ruta === $m || str_starts_with($ruta, $m . '/')) && strlen($m) > $mejor[1]) {
            $mejor = [$item['key'] ?? '', strlen($m), $item];
        }
    }
    if ($mejor[0] === null && $ruta === '/') $mejor = ['g:dashboard', 0, null];
    return $mejor;
}

/**
 * Barra de contexto de un proyecto: selector de sección + dock de herramientas.
 * $claveActiva y $grupoActivo vienen de views/layout.php (nav_activa() una sola vez).
 */
function navegacion_proyecto($proyecto, array $grupos, ?string $claveActiva, int $grupoActivo): void
{
    if (!$proyecto || !$grupos) return;
    $nombres = array_keys($grupos);
    if (!isset($nombres[$grupoActivo])) $grupoActivo = 0;
    $nombreActivo = $nombres[$grupoActivo] ?? '';
    $nActivas = count($grupos[$nombreActivo] ?? []);
    ?>
    <nav class="nav-contexto" aria-label="<?php echo s(t('Herramientas del proyecto')); ?>">
        <div class="nav-contexto-barra">
            <div class="nav-seccion-wrap">
                <button type="button" class="nav-seccion-btn" data-nav-seccion aria-expanded="false" aria-controls="nav-secciones">
                    <i class="fa-solid <?php echo s(nav_icono_grupo($nombreActivo)); ?>" aria-hidden="true"></i>
                    <span data-nav-seccion-txt><?php echo s(t($nombreActivo)); ?></span>
                    <span class="nav-cuenta" data-nav-seccion-cuenta><?php echo $nActivas; ?></span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="nav-secciones" id="nav-secciones" hidden>
                    <?php $i = 0; foreach ($grupos as $nombre => $items): ?>
                        <button type="button" class="nav-seccion-op" data-nav-grupo="<?php echo $i; ?>"
                                data-nav-icono="<?php echo s(nav_icono_grupo($nombre)); ?>"
                                data-nav-nombre="<?php echo s(t($nombre)); ?>"
                                data-nav-cuenta="<?php echo count($items); ?>"
                                aria-pressed="<?php echo $i === $grupoActivo ? 'true' : 'false'; ?>">
                            <i class="fa-solid <?php echo s(nav_icono_grupo($nombre)); ?>" aria-hidden="true"></i>
                            <span><?php echo s(t($nombre)); ?></span>
                            <span class="nav-seccion-cuenta"><?php echo count($items); ?></span>
                        </button>
                    <?php $i++; endforeach; ?>
                </div>
            </div>
            <span class="nav-sep" aria-hidden="true"></span>
            <div class="nav-dock" data-nav-dock>
                <?php $i = 0; foreach ($grupos as $nombre => $items): ?>
                    <div class="nav-dock-grupo" data-nav-panel="<?php echo $i; ?>"<?php echo $i === $grupoActivo ? '' : ' hidden'; ?>>
                        <?php foreach ($items as $item): $activa = ($item['key'] ?? '') === $claveActiva; ?>
                            <a href="<?php echo s($item['url']); ?>"
                               class="nav-herramienta<?php echo $activa ? ' activa' : ''; ?>"
                               data-nav-clave="<?php echo s($item['key'] ?? ''); ?>"
                               data-nav-grupo="<?php echo $i; ?>"
                               title="<?php echo s(t($item['label'])); ?>"
                               aria-label="<?php echo s(t($item['label'])); ?>"<?php echo $activa ? ' aria-current="page"' : ''; ?><?php echo atributos_ayuda($item, true); ?>>
                                <i class="fa-solid <?php echo s($item['icon']); ?>" aria-hidden="true"></i>
                                <span class="nav-herramienta-nombre"><?php echo s(t($item['corto'] ?? $item['label'])); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php $i++; endforeach; ?>
            </div>
            <button type="button" class="nav-contexto-btn nav-mas" data-nav-todas data-nav-filtro-grupo="<?php echo $grupoActivo; ?>"
                    title="<?php echo s(t('Más herramientas de esta sección')); ?>" aria-label="<?php echo s(t('Más herramientas de esta sección')); ?>">
                <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
            </button>
            <button type="button" class="nav-contexto-btn" data-nav-todas
                    title="<?php echo s(t('Todas las herramientas')); ?>" aria-label="<?php echo s(t('Todas las herramientas')); ?>">
                <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
            </button>
            <button type="button" class="nav-contexto-btn nav-fijar" data-nav-fijar<?php echo $claveActiva ? '' : ' disabled'; ?>
                    title="<?php echo s(t('Fijar esta herramienta')); ?>" aria-label="<?php echo s(t('Fijar esta herramienta')); ?>" aria-pressed="false">
                <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
            </button>
        </div>
    </nav>
    <?php
}

/** Barra de contexto de las páginas globales (Inicio, Proyectos, Portafolio…). */
function navegacion_global(array $menu): void
{
    if (!$menu) return;
    [$claveActiva] = nav_global_activa($menu);
    ?>
    <nav class="nav-contexto nav-contexto--global" aria-label="<?php echo s(t('Navegación')); ?>">
        <div class="nav-contexto-barra">
            <div class="nav-dock">
                <?php foreach ($menu as $item): $activa = ($item['key'] ?? '') === $claveActiva; ?>
                    <a href="<?php echo s($item['url']); ?>"
                       class="nav-global-item<?php echo $activa ? ' activa' : ''; ?>"
                       <?php echo $activa ? 'aria-current="page"' : ''; ?><?php echo atributos_ayuda($item, true); ?>>
                        <i class="fa-solid <?php echo s($item['icon']); ?>" aria-hidden="true"></i>
                        <span><?php echo s(t($item['corto'] ?? $item['label'])); ?></span>
                        <?php if (!empty($item['badge'])): ?><b class="nav-badge"><?php echo (int)$item['badge']; ?></b><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
    <?php
}
