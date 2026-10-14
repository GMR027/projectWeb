<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-cubes"></i> Catálogo de materiales</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($materiales); ?> material(es)</p>
    </div>
    <div class="la-controles">
        <form method="GET" class="buscador-inline">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
            <input type="search" name="q" value="<?php echo s($q); ?>" placeholder="Buscar material, referencia…">
        </form>
        <a href="/materiales/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">+ Nuevo material</a>
    </div>
</div>

<div class="card">
    <?php if (empty($materiales)): ?>
        <p class="text-muted">Aún no hay materiales<?php echo $q ? ' que coincidan con la búsqueda' : ''; ?>. Agrega uno o impórtalos desde el MSR.</p>
    <?php else: ?>
        <table class="tabla">
            <thead>
                <tr><th>Imagen</th><th class="hide-m">Referencia</th><th>Material</th><th class="hide-m">Especialidad</th><th class="hide-m">Web</th><th class="hide-m">Ficha</th><th class="hide-m">Certificado calidad</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($materiales as $m): ?>
                <tr>
                    <td><?php if ($m->imagen): ?><img class="mat-thumb" src="/uploads/materiales/<?php echo s($m->imagen); ?>" alt=""><?php else: ?><span class="mat-thumb mat-thumb-vacio"><i class="fa-regular fa-image"></i></span><?php endif; ?></td>
                    <td class="mono hide-m"><?php echo s($m->referencia); ?></td>
                    <td><?php echo s($m->nombre); ?></td>
                    <td class="hide-m"><?php echo s($m->especialidad); ?></td>
                    <td class="hide-m"><?php if ($m->link_web): ?><a href="<?php echo s($m->link_web); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo s($m->link_web); ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?></td>
                    <td class="hide-m"><?php if ($m->ficha_tecnica): ?><a href="/materiales/ficha?id=<?php echo s($m->id); ?>" target="_blank" title="<?php echo s($m->ficha_tecnica); ?>"><i class="fa-solid fa-file-pdf"></i></a><?php endif; ?></td>
                    <td class="hide-m"><?php echo s($m->certificado_calidad); ?></td>
                    <td class="acciones">
                        <a href="/materiales/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($m->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/materiales/eliminar" style="display:inline" data-confirm="¿Eliminar material?|Se quitará también de los submittals y del MSR.">
                            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                            <input type="hidden" name="id" value="<?php echo s($m->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
