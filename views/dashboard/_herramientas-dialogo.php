<?php
/** Ventana «Todas las herramientas» del inicio. Variables: $proyectos, $proyectoInicio, $gruposHerramientas, $herramientasTotal */
$iconosSeccion = [
    'Planeación' => 'fa-diagram-project',
    'Campo y ejecución' => 'fa-helmet-safety',
    'Materiales y compras' => 'fa-boxes-stacked',
    'Documentos y diseño' => 'fa-folder-open',
    'Inteligencia artificial' => 'fa-wand-magic-sparkles',
    'Administración' => 'fa-briefcase',
    'Proyecto' => 'fa-heart-pulse',
];
?>
<dialog class="inicio-dialogo inicio-dialogo-herramientas" id="inicio-herramientas-dialogo" aria-labelledby="inicio-herr-titulo">
    <div class="ventana-cab">
        <span class="ventana-icono"><i class="fa-solid fa-compass" aria-hidden="true"></i></span>
        <div class="ventana-cab-txt">
            <span class="ventana-eyebrow"><?php echo s(t('Navegación')); ?></span>
            <h3 id="inicio-herr-titulo"><?php echo s(t('Todas las herramientas')); ?></h3>
            <p data-inicio-proyecto-nombre><?php echo $proyectoInicio ? s($proyectoInicio->nombre . ' · ' . $proyectoInicio->codigo) : s(t('Sin obra seleccionada')); ?></p>
        </div>
        <button type="button" class="ventana-x" data-inicio-cerrar aria-label="<?php echo s(t('Cerrar')); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>

    <?php if (!$proyectoInicio): ?>
        <div class="ventana-cuerpo">
            <div class="inicio-vacio-lista">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                <p><?php echo s(t('Crea o abre una obra para ver sus herramientas.')); ?></p>
                <?php if (puede_crear_proyecto()): ?><a href="/proyectos/crear" class="btn btn-primary"><?php echo s(t('Crear proyecto')); ?></a><?php endif; ?>
            </div>
            <div class="inicio-atajos-mini">
                <a href="/proyectos"><i class="fa-solid fa-building" aria-hidden="true"></i> <?php echo s(t('Todos los proyectos')); ?></a>
                <a href="/plantillas-proyecto"><i class="fa-solid fa-copy" aria-hidden="true"></i> <?php echo s(t('Plantillas')); ?></a>
                <a href="/manual"><i class="fa-solid fa-book-open" aria-hidden="true"></i> <?php echo s(t('Manual')); ?></a>
                <a href="/novedades"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> <?php echo s(t('Novedades')); ?></a>
            </div>
        </div>
    <?php else: ?>
        <div class="inicio-dialogo-cab">
            <div class="inicio-dialogo-busca">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="inicio-herr-q" data-inicio-busca placeholder="<?php echo s(t('Filtrar herramientas…')); ?>" aria-label="<?php echo s(t('Filtrar herramientas…')); ?>" autocomplete="off">
                <kbd class="inicio-kbd">⌘K</kbd>
            </div>
            <?php if (count($proyectos) > 1): ?>
                <label class="inicio-select inicio-select--compacto">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <span class="sr-only"><?php echo s(t('Obra para las herramientas')); ?></span>
                    <select data-inicio-proyecto>
                        <?php foreach ($proyectos as $p): ?>
                            <option value="<?php echo (int)$p->id; ?>"<?php echo (int)$p->id === (int)$proyectoInicio->id ? ' selected' : ''; ?>><?php echo s($p->nombre . ' · ' . $p->codigo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
        <div class="inicio-dialogo-chips" role="group" aria-label="<?php echo s(t('Secciones')); ?>">
            <button type="button" class="inicio-seccion activo" data-inicio-dialogo-seccion="todas" aria-pressed="true"><?php echo s(t('Todas')); ?> <b><?php echo (int)$herramientasTotal; ?></b></button>
            <?php $i = 0; foreach ($gruposHerramientas as $nombre => $items): ?>
                <button type="button" class="inicio-seccion" data-inicio-dialogo-seccion="<?php echo $i; ?>" aria-pressed="false">
                    <i class="fa-solid <?php echo s($iconosSeccion[$nombre] ?? 'fa-layer-group'); ?>" aria-hidden="true"></i>
                    <?php echo s(t($nombre)); ?> <b><?php echo count($items); ?></b>
                </button>
            <?php $i++; endforeach; ?>
        </div>
        <div class="ventana-cuerpo inicio-dialogo-cuerpo">
            <div class="inicio-dialogo-fijadas" id="inicio-fijadas" hidden></div>
            <div class="inicio-dialogo-recientes" id="inicio-recientes" hidden></div>
            <div id="inicio-dialogo-grupos">
                <?php $i = 0; foreach ($gruposHerramientas as $nombre => $items): ?>
                    <section class="inicio-dialogo-grupo" data-inicio-dialogo-grupo="<?php echo $i; ?>">
                        <h4><i class="fa-solid <?php echo s($iconosSeccion[$nombre] ?? 'fa-layer-group'); ?>" aria-hidden="true"></i> <?php echo s(t($nombre)); ?> <span><?php echo count($items); ?></span></h4>
                        <div class="inicio-dialogo-grid">
                            <?php foreach ($items as $item): ?>
                                <a class="inicio-dlg-item" href="<?php echo s($item['url']); ?>" data-inicio-tool data-key="<?php echo s($item['key'] ?? ''); ?>" data-grupo="<?php echo $i; ?>"
                                   data-buscar="<?php echo s(t($item['label']) . ' ' . t($item['corto'] ?? '') . ' ' . t($nombre)); ?>">
                                    <span class="inicio-dlg-ico"><i class="fa-solid <?php echo s($item['icon']); ?>" aria-hidden="true"></i></span>
                                    <span class="inicio-dlg-txt"><strong><?php echo s(t($item['label'])); ?></strong><small><?php echo s(t($item['corto'] ?? $item['label'])); ?></small></span>
                                    <i class="fa-solid fa-arrow-right inicio-dlg-flecha" aria-hidden="true"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php $i++; endforeach; ?>
            </div>
            <p class="inicio-dialogo-vacio" id="inicio-herr-vacio" hidden><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> <?php echo s(t('No hay herramientas que coincidan.')); ?></p>
        </div>
        <div class="ventana-pie">
            <a href="/manual?proyecto=<?php echo (int)$proyectoInicio->id; ?>" class="btn btn-sm btn-ghost" data-inicio-proyecto-enlace><i class="fa-solid fa-book-open" aria-hidden="true"></i> <?php echo s(t('Manual')); ?></a>
            <a href="/acciones?proyecto=<?php echo (int)$proyectoInicio->id; ?>" class="btn btn-sm btn-ghost" data-inicio-proyecto-enlace><i class="fa-solid fa-bolt" aria-hidden="true"></i> <?php echo s(t('Acciones rápidas')); ?></a>
            <span class="inicio-pie-espacio"></span>
            <span class="text-muted text-sm"><i class="fa-solid fa-keyboard" aria-hidden="true"></i> <?php echo s(t('Ctrl/⌘ K para abrir')); ?></span>
        </div>
    <?php endif; ?>
</dialog>
