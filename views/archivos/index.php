<?php $puede = puede_gestionar_proyecto($proyecto); $desc = puede_descargar(); $id = s($proyecto->id); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-folder-open"></i> Archivo</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($archivos); ?> archivo(s)</p>
    </div>
    <div class="la-controles">
        <form method="GET" class="buscador-inline">
            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
            <select name="categoria" onchange="this.form.submit()" aria-label="Filtrar por categoría">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $c): ?><option value="<?php echo s($c->id); ?>" <?php echo (int)$filtro === (int)$c->id ? 'selected' : ''; ?>><?php echo s($c->nombre); ?></option><?php endforeach; ?>
            </select>
        </form>
        <a href="/archivos/categorias?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-tags"></i> Categorías</a>
        <?php if ($puede): ?><a href="/archivos/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary">+ Subir archivo</a><?php endif; ?>
    </div>
</div>
<div class="card">
    <?php if (empty($archivos)): ?>
        <p class="text-muted">No hay archivos<?php echo $filtro ? ' en esta categoría' : ''; ?>.<?php echo $puede ? ' Sube el primero con «Subir archivo».' : ''; ?></p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th class="hide-m">Fecha</th><th class="hide-m">Categoría</th><th>Nombre del archivo</th><th class="hide-m">Rev.</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($archivos as $a): ?>
                <tr>
                    <td class="mono hide-m" style="white-space:nowrap"><?php echo s(fecha_corta_es($a->fecha)); ?></td>
                    <td class="hide-m"><?php echo s($a->categoria_nombre ?: '—'); ?></td>
                    <td>
                        <i class="fa-solid <?php echo s(archivo_icono($a->extension())); ?>"></i> <?php echo s($a->nombre); ?>
                        <div class="text-sm text-muted mono"><?php echo s($a->archivo_original); ?> · <?php echo s($a->tamanoLegible()); ?></div>
                        <div class="solo-movil text-sm text-muted"><?php echo s(fecha_corta_es($a->fecha)); ?><?php echo $a->categoria_nombre ? ' · ' . s($a->categoria_nombre) : ''; ?><?php echo $a->numero_revision ? ' · Rev. ' . s($a->numero_revision) : ''; ?></div>
                    </td>
                    <td class="mono hide-m"><?php echo s($a->numero_revision ?: '—'); ?></td>
                    <td class="acciones">
                        <?php if ($desc): ?><a href="/archivos/descargar?proyecto=<?php echo $id; ?>&id=<?php echo s($a->id); ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-download"></i> Descargar</a><?php endif; ?>
                        <?php if ($puede): ?>
                            <a href="/archivos/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($a->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                            <form method="POST" action="/archivos/eliminar" style="display:inline" data-confirm="¿Eliminar archivo?|Se borrará «<?php echo s($a->nombre); ?>» del servidor. Esta acción no se puede deshacer.|Eliminar">
                                <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                                <input type="hidden" name="id" value="<?php echo s($a->id); ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
