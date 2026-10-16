<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-pen-ruler"></i> <?php echo s(t('Editor')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · gráficos y láminas libres con las herramientas del vPlan</p>
    </div>
    <div class="la-controles">
        <a href="/graficos/categorias?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-tags"></i> Categorías</a>
        <?php if ($puedeEditar): ?><a href="/graficos/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> <?php echo s(t('Nuevo gráfico')); ?></a><?php endif; ?>
    </div>
</div>

<div class="card">
    <?php if (!$graficos): ?>
        <p class="text-muted"><?php echo s(t('Aún no hay gráficos.')); ?> <?php echo $puedeEditar ? 'Crea el primero con «Nuevo gráfico».' : ''; ?></p>
    <?php else: ?>
        <div class="tabla-wrapper">
        <table class="tabla">
            <thead><tr><th><?php echo s(t('Fecha')); ?></th><th><?php echo s(t('Nombre')); ?></th><th class="hide-m"><?php echo s(t('Categoría')); ?></th><th class="hide-m"><?php echo s(t('Tamaño')); ?></th><th><?php echo s(t('Acciones')); ?></th></tr></thead>
            <tbody>
            <?php foreach ($graficos as $g): $q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$g->id; ?>
                <tr>
                    <td class="mono"><?php echo s(formatear_fecha($g->creado, 'd/m/Y H:i')); ?></td>
                    <td>
                        <div class="gr-nombre">
                            <?php if ($g->portada): ?><img class="gr-mini" src="/uploads/vplan/<?php echo s($g->portada); ?>" alt="" loading="lazy"><?php else: ?><span class="gr-mini gr-mini-vacio"><i class="fa-regular fa-image"></i></span><?php endif; ?>
                            <div><strong><?php echo s($g->nombre); ?></strong><?php if ((int)$g->num_laminas > 1): ?><br><small class="text-muted"><?php echo (int)$g->num_laminas; ?> láminas</small><?php endif; ?></div>
                        </div>
                    </td>
                    <td class="hide-m"><?php echo $g->categoria_nombre ? s($g->categoria_nombre) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="mono hide-m"><?php echo (int)$g->ancho; ?> × <?php echo (int)$g->alto; ?> px</td>
                    <td class="acciones">
                        <a href="/graficos/ver?<?php echo $q; ?>" class="btn btn-sm btn-ghost"><i class="fa-regular fa-eye"></i> <?php echo s(t('Ver')); ?></a>
                        <?php if ($puedeEditar): ?><a href="/graficos/abrir?<?php echo $q; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen"></i> <?php echo s(t('Editar')); ?></a><?php endif; ?>
                        <?php if ($puedeDescargar && $g->portada): ?>
                            <details class="gr-descargar">
                                <summary class="btn btn-sm btn-ghost"><i class="fa-solid fa-download"></i> <?php echo s(t('Descargar')); ?></summary>
                                <div class="gr-descargar-menu">
                                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=png"><i class="fa-regular fa-image"></i> <?php echo (int)$g->num_laminas > 1 ? 'PNG (ZIP con todas las láminas)' : 'PNG'; ?></a>
                                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>
                                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=odp"><i class="fa-regular fa-file-powerpoint"></i> Presentación .odp</a>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if ($puedeEditar): ?>
                            <form method="POST" action="/graficos/duplicar" style="display:inline">
                                <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>">
                                <button type="submit" class="btn btn-sm btn-ghost"><i class="fa-regular fa-copy"></i> <?php echo s(t('Duplicar')); ?></button>
                            </form>
                            <form method="POST" action="/graficos/eliminar" style="display:inline" data-confirm="¿Eliminar «<?php echo s($g->nombre); ?>»?|Se borrarán también sus imágenes. Esta acción no se puede deshacer.|Sí, eliminar">
                                <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> <?php echo s(t('Eliminar')); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
