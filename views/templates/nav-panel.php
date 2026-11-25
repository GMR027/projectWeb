<?php
/**
 * Panel «Todas las herramientas» (diálogo nativo): búsqueda, secciones, fijadas
 * y recientes. Se abre desde la barra de contexto, el riel, la barra inferior
 * móvil y el atajo Ctrl/⌘ + K.
 */
function navegacion_panel(array $grupos, ?string $claveActiva, $proyecto): void
{
    if (!$proyecto || !$grupos) return;
    $datos = [];
    foreach ($grupos as $nombre => $items) {
        foreach ($items as $item) {
            $label = t($item['label']);
            $corto = t($item['corto'] ?? $item['label']);
            $datos[] = [
                'key' => (string)($item['key'] ?? ''),
                'label' => $label,
                'corto' => $corto,
                'url' => (string)$item['url'],
                'icono' => (string)$item['icon'],
                'grupo' => t($nombre),
                'buscar' => $label . ' ' . $corto . ' ' . t($nombre),
            ];
        }
    }
    $claves = array_column($datos, 'key');
    $defecto = array_values(array_filter(['programa', 'reportes', 'costos', 'archivos'], fn($k) => in_array($k, $claves, true)));
    $defecto = array_slice($defecto, 0, 4);
    $idProyecto = (int)$proyecto->id;
    ?>
    <dialog class="nav-panel" id="nav-panel" aria-labelledby="nav-panel-titulo"
            data-activa="<?php echo s($claveActiva ?? ''); ?>"
            data-proyecto="<?php echo $idProyecto; ?>"
            data-fijadas-defecto="<?php echo s(implode(',', $defecto)); ?>">
        <header class="nav-panel-cab">
            <div>
                <span class="nav-panel-eyebrow"><i class="fa-solid fa-compass" aria-hidden="true"></i> <?php echo s(t('Navegación')); ?></span>
                <h2 id="nav-panel-titulo"><?php echo s(t('Todas las herramientas')); ?></h2>
                <p><?php echo s($proyecto->nombre); ?> · <span class="mono"><?php echo s($proyecto->codigo); ?></span></p>
            </div>
            <button type="button" class="nav-panel-x" data-nav-cerrar aria-label="<?php echo s(t('Cerrar')); ?>">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>
        <div class="nav-panel-busca">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="nav-panel-q" placeholder="<?php echo s(t('Filtrar herramientas…')); ?>"
                   aria-label="<?php echo s(t('Filtrar herramientas…')); ?>" autocomplete="off" autofocus>
            <kbd data-nav-kbd>⌘K</kbd>
        </div>
        <div class="nav-panel-chips" role="group" aria-label="<?php echo s(t('Secciones')); ?>">
            <button type="button" class="nav-panel-chip activo" data-nav-filtro="todas" aria-pressed="true"><?php echo s(t('Todas')); ?></button>
            <?php $i = 0; foreach ($grupos as $nombre => $items): ?>
                <button type="button" class="nav-panel-chip" data-nav-filtro="<?php echo $i; ?>" aria-pressed="false">
                    <?php echo s(t($nombre)); ?> <span><?php echo count($items); ?></span>
                </button>
            <?php $i++; endforeach; ?>
        </div>
        <div class="nav-panel-cuerpo">
            <div class="nav-panel-destacados" id="nav-panel-destacados" hidden></div>
            <div class="nav-panel-grupos" id="nav-panel-grupos">
                <?php $i = 0; foreach ($grupos as $nombre => $items): ?>
                    <section class="nav-panel-grupo" data-nav-grupo-panel="<?php echo $i; ?>">
                        <h4>
                            <i class="fa-solid <?php echo s(nav_icono_grupo($nombre)); ?>" aria-hidden="true"></i>
                            <?php echo s(t($nombre)); ?>
                            <span><?php echo count($items); ?></span>
                        </h4>
                        <div class="nav-panel-grid">
                            <?php foreach ($items as $item): $activa = ($item['key'] ?? '') === $claveActiva; ?>
                                <div class="nav-panel-item" data-buscar="<?php echo s(t($item['label']) . ' ' . t($item['corto'] ?? '') . ' ' . t($nombre)); ?>">
                                    <a href="<?php echo s($item['url']); ?>"
                                       class="nav-panel-enlace<?php echo $activa ? ' activa' : ''; ?>"
                                       aria-label="<?php echo s(t($item['label'])); ?>"
                                       <?php echo $activa ? 'aria-current="page"' : ''; ?><?php echo atributos_ayuda($item, true); ?>>
                                        <i class="fa-solid <?php echo s($item['icon']); ?>" aria-hidden="true"></i>
                                        <span class="nav-panel-nombre"><?php echo s(t($item['label'])); ?></span>
                                        <span class="nav-panel-nombre-corto"><?php echo s(t($item['corto'] ?? $item['label'])); ?></span>
                                    </a>
                                    <button type="button" class="nav-pin" data-nav-pin="<?php echo s($item['key'] ?? ''); ?>"
                                            aria-pressed="false" title="<?php echo s(t('Fijar esta herramienta')); ?>"
                                            aria-label="<?php echo s(t('Fijar esta herramienta')); ?>">
                                        <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php $i++; endforeach; ?>
            </div>
            <p class="nav-panel-vacio" id="nav-panel-vacio" hidden>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <?php echo s(t('No hay herramientas que coincidan.')); ?>
            </p>
        </div>
        <footer class="nav-panel-pie">
            <a href="/manual?proyecto=<?php echo $idProyecto; ?>"><i class="fa-solid fa-book-open" aria-hidden="true"></i> <?php echo s(t('Manual de usuario')); ?></a>
            <a href="/acciones?proyecto=<?php echo $idProyecto; ?>"><i class="fa-solid fa-bolt" aria-hidden="true"></i> <?php echo s(t('Acciones rápidas')); ?></a>
        </footer>
    </dialog>
    <script>window.PW_NAV = <?php echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <?php
}
