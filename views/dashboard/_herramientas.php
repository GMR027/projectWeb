<?php
/** Lanzador de herramientas. Variables: $proyectoInicio, $proyectos, $gruposHerramientas, $herramientasTotal */
$iconosSeccion = [
    'Planeación' => 'fa-diagram-project',
    'Campo y ejecución' => 'fa-helmet-safety',
    'Materiales y compras' => 'fa-boxes-stacked',
    'Documentos y diseño' => 'fa-folder-open',
    'Inteligencia artificial' => 'fa-wand-magic-sparkles',
    'Administración' => 'fa-briefcase',
    'Proyecto' => 'fa-heart-pulse',
];
$nombres = array_keys($gruposHerramientas);
$activa = in_array('Planeación', $nombres, true) ? 'Planeación' : ($nombres[0] ?? '');
$indiceActivo = $activa !== '' ? array_search($activa, $nombres, true) : 0;
?>
<section class="card inicio-panel inicio-panel-herramientas">
    <header class="inicio-panel-cab">
        <div>
            <h3><i class="fa-solid fa-grip" aria-hidden="true"></i> <?php echo s(t('Herramientas')); ?></h3>
            <p class="text-muted"><?php echo $proyectoInicio ? s(t('Abre una herramienta de la obra elegida.')) : s(t('Crea una obra para abrir sus herramientas.')); ?></p>
        </div>
        <?php if ($herramientasTotal): ?><span class="inicio-cuenta"><?php echo (int)$herramientasTotal; ?></span><?php endif; ?>
    </header>

    <?php if (!$proyectoInicio): ?>
        <div class="inicio-panel-cuerpo">
            <div class="inicio-vacio-lista">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                <p><?php echo s(t('Aún no hay una obra a la que abrirle herramientas.')); ?></p>
                <?php if (puede_crear_proyecto()): ?><a href="/proyectos/crear" class="btn btn-sm btn-primary"><?php echo s(t('Crear proyecto')); ?></a><?php endif; ?>
            </div>
            <div class="inicio-atajos-mini">
                <a href="/plantillas-proyecto"><i class="fa-solid fa-copy" aria-hidden="true"></i> <?php echo s(t('Plantillas')); ?></a>
                <a href="/manual"><i class="fa-solid fa-book-open" aria-hidden="true"></i> <?php echo s(t('Manual')); ?></a>
                <a href="/novedades"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> <?php echo s(t('Novedades')); ?></a>
            </div>
        </div>
    <?php else: ?>
        <div class="inicio-panel-cuerpo inicio-herramientas-cuerpo">
            <?php if (count($proyectos) > 1): ?>
                <label class="inicio-select" for="inicio-proyecto">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <span class="sr-only"><?php echo s(t('Obra para las herramientas')); ?></span>
                    <select id="inicio-proyecto" data-inicio-proyecto>
                        <?php foreach ($proyectos as $p): ?>
                            <option value="<?php echo (int)$p->id; ?>"<?php echo (int)$p->id === (int)$proyectoInicio->id ? ' selected' : ''; ?>><?php echo s($p->nombre . ' · ' . $p->codigo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>

            <div class="inicio-secciones" role="group" aria-label="<?php echo s(t('Secciones de herramientas')); ?>">
                <?php $i = 0; foreach ($gruposHerramientas as $nombre => $items): ?>
                    <button type="button" class="inicio-seccion<?php echo $i === $indiceActivo ? ' activo' : ''; ?>"
                            data-inicio-seccion="<?php echo $i; ?>" aria-pressed="<?php echo $i === $indiceActivo ? 'true' : 'false'; ?>"
                            title="<?php echo s(t($nombre)); ?>">
                        <i class="fa-solid <?php echo s($iconosSeccion[$nombre] ?? 'fa-layer-group'); ?>" aria-hidden="true"></i>
                        <span><?php echo s(t($nombre)); ?></span>
                        <b><?php echo count($items); ?></b>
                    </button>
                <?php $i++; endforeach; ?>
            </div>

            <?php $i = 0; foreach ($gruposHerramientas as $nombre => $items): $mostrar = array_slice($items, 0, 6); ?>
                <div class="inicio-tools" data-inicio-grupo="<?php echo $i; ?>"<?php echo $i === $indiceActivo ? '' : ' hidden'; ?>>
                    <?php foreach ($mostrar as $item): ?>
                        <a class="inicio-tool" href="<?php echo s($item['url']); ?>" data-inicio-tool data-key="<?php echo s($item['key'] ?? ''); ?>" data-grupo="<?php echo $i; ?>"
                           title="<?php echo s(t($item['label'])); ?>">
                            <span class="inicio-tool-ico"><i class="fa-solid <?php echo s($item['icon']); ?>" aria-hidden="true"></i></span>
                            <span class="inicio-tool-txt">
                                <strong><?php echo s(t($item['corto'] ?? $item['label'])); ?></strong>
                                <small><?php echo s(t($item['label'])); ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($items) > count($mostrar)): ?>
                        <button type="button" class="inicio-tool inicio-tool-mas" data-inicio-herramientas data-grupo="<?php echo $i; ?>">
                            <span class="inicio-tool-ico"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></span>
                            <span class="inicio-tool-txt"><strong><?php echo count($items) - count($mostrar); ?> <?php echo s(t('más')); ?></strong><small><?php echo s(t('Ver todas')); ?></small></span>
                        </button>
                    <?php endif; ?>
                </div>
            <?php $i++; endforeach; ?>
        </div>
        <div class="inicio-panel-pie">
            <button type="button" class="btn btn-ghost btn-sm inicio-ver-todas" data-inicio-herramientas>
                <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i> <?php echo s(t('Ver todas las herramientas')); ?> <kbd class="inicio-kbd">⌘K</kbd>
            </button>
        </div>
    <?php endif; ?>
</section>
