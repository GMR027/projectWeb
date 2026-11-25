<?php
/**
 * Riel lateral (modo panel): secciones del proyecto en 64 px y un panel que se
 * abre sobre el contenido con las herramientas de la sección elegida.
 * En móvil el riel se oculta y mandan la barra inferior y la hoja «Más».
 */
function navegacion_riel(array $grupos, ?string $claveActiva, int $grupoActivo, $proyecto): void
{
    if (!$proyecto || !$grupos) return;
    $nombres = array_keys($grupos);
    if (!isset($nombres[$grupoActivo])) $grupoActivo = 0;
    $idProyecto = (int)$proyecto->id;
    ?>
    <aside class="nav-riel" aria-label="<?php echo s(t('Secciones del proyecto')); ?>">
        <?php $i = 0; foreach ($grupos as $nombre => $items): ?>
            <button type="button" class="nav-riel-btn<?php echo $i === $grupoActivo ? ' activa' : ''; ?>"
                    data-riel-grupo="<?php echo $i; ?>"
                    data-riel-nombre="<?php echo s(t($nombre)); ?>"
                    data-riel-cuenta="<?php echo count($items); ?>"
                    aria-expanded="false" aria-controls="nav-riel-panel"
                    title="<?php echo s(t($nombre)); ?>">
                <i class="fa-solid <?php echo s(nav_icono_grupo($nombre)); ?>" aria-hidden="true"></i>
                <span class="sr-only"><?php echo s(t($nombre)); ?></span>
            </button>
        <?php $i++; endforeach; ?>
        <div class="nav-riel-sep" aria-hidden="true"></div>
        <button type="button" class="nav-riel-btn" data-nav-todas
                title="<?php echo s(t('Todas las herramientas')); ?>" aria-label="<?php echo s(t('Todas las herramientas')); ?>">
            <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
        </button>
        <div class="nav-riel-pie">
            <a href="/manual?proyecto=<?php echo $idProyecto; ?>" class="nav-riel-btn"
               title="<?php echo s(t('Manual de usuario')); ?>" aria-label="<?php echo s(t('Manual de usuario')); ?>">
                <i class="fa-solid fa-book-open" aria-hidden="true"></i>
            </a>
            <button type="button" class="nav-riel-btn" data-nav-abrir-menu
                    title="<?php echo s(t('Menú general')); ?>" aria-label="<?php echo s(t('Menú general')); ?>">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </aside>
    <div class="nav-riel-panel" id="nav-riel-panel" hidden aria-label="<?php echo s(t('Herramientas de la sección')); ?>">
        <header class="nav-riel-panel-cab">
            <div>
                <span class="nav-riel-panel-eyebrow"><?php echo s(t('Sección')); ?></span>
                <h2 id="nav-riel-panel-titulo"><?php echo s(t($nombres[$grupoActivo] ?? '')); ?></h2>
            </div>
            <button type="button" class="nav-riel-panel-x" data-riel-cerrar aria-label="<?php echo s(t('Cerrar')); ?>">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>
        <?php $i = 0; foreach ($grupos as $nombre => $items): ?>
            <div class="nav-riel-grupo" data-riel-panel="<?php echo $i; ?>"<?php echo $i === $grupoActivo ? '' : ' hidden'; ?>>
                <?php foreach ($items as $item): $activa = ($item['key'] ?? '') === $claveActiva; ?>
                    <a href="<?php echo s($item['url']); ?>"
                       class="nav-riel-item<?php echo $activa ? ' activa' : ''; ?>"
                       <?php echo $activa ? 'aria-current="page"' : ''; ?><?php echo atributos_ayuda($item, true); ?>>
                        <i class="fa-solid <?php echo s($item['icon']); ?>" aria-hidden="true"></i>
                        <span><?php echo s(t($item['label'])); ?></span>
                    </a>
                <?php endforeach; ?>
                <button type="button" class="nav-riel-todas" data-nav-todas data-nav-filtro-grupo="<?php echo $i; ?>">
                    <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
                    <?php echo s(t('Ver todas las herramientas')); ?>
                </button>
            </div>
        <?php $i++; endforeach; ?>
    </div>
    <?php
}
