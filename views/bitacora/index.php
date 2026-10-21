<?php $puede = puede_gestionar_proyecto($proyecto); $id = s($proyecto->id); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-book"></i> Bitácora de obra</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($registros); ?> registro(s)</p>
    </div>
    <div class="la-controles">
        <form method="GET" class="buscador-inline" style="display:flex;gap:.8rem">
            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
            <select name="departamento" onchange="this.form.submit()" aria-label="Filtrar por departamento">
                <option value="">Todos los departamentos</option>
                <?php foreach ($deptos as $d): ?><option value="<?php echo s($d); ?>" <?php echo $filtroDepto === $d ? 'selected' : ''; ?>><?php echo s($d); ?></option><?php endforeach; ?>
                <option value="__sin" <?php echo $filtroDepto === '__sin' ? 'selected' : ''; ?>>Sin departamento</option>
            </select>
            <select name="importancia" onchange="this.form.submit()" aria-label="Filtrar por importancia">
                <option value="">Toda importancia</option>
                <?php foreach (\Model\Bitacora::IMPORTANCIAS as $k => $v): ?><option value="<?php echo s($k); ?>" <?php echo $filtroImp === $k ? 'selected' : ''; ?>><?php echo s($v); ?></option><?php endforeach; ?>
            </select>
            <?php if ($filtroDepto !== '' || $filtroImp !== ''): ?><a href="/bitacora?proyecto=<?php echo $id; ?>" class="btn btn-ghost">Limpiar</a><?php endif; ?>
        </form>
        <a href="/bitacora/departamentos?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-sitemap"></i> Departamentos</a>
        <?php if ($puede): ?><a href="/bitacora/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary">+ Nuevo registro</a><?php endif; ?>
    </div>
</div>
<div class="card">
    <?php if (empty($registros)): ?>
        <p class="text-muted"><?php echo ($filtroDepto !== '' || $filtroImp !== '') ? 'Ningún registro coincide con el filtro.' : 'Aún no hay registros en la bitácora.'; ?></p>
    <?php else: ?>
        <table class="tabla" <?php echo pag_attr($proyecto); ?>>
            <thead><tr><th class="hide-m">Fecha</th><th class="hide-m">Departamento</th><th>Descripción</th><th class="hide-m">Importancia</th><?php if ($puede): ?><th>Acciones</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($registros as $b): ?>
                <tr>
                    <td class="mono hide-m" style="white-space:nowrap"><?php echo s(fecha_corta_es($b->fecha)); ?></td>
                    <td class="hide-m"><?php echo s($b->departamento ?: '—'); ?></td>
                    <td style="white-space:pre-wrap"><div class="solo-movil text-sm text-muted" style="white-space:normal"><span class="badge bit-<?php echo s($b->importancia); ?>"><?php echo s(\Model\Bitacora::IMPORTANCIAS[$b->importancia] ?? $b->importancia); ?></span> <?php echo s(fecha_corta_es($b->fecha)); ?><?php echo $b->departamento ? ' · ' . s($b->departamento) : ''; ?></div><?php echo s($b->descripcion); ?></td>
                    <td class="hide-m"><span class="badge bit-<?php echo s($b->importancia); ?>"><?php echo s(\Model\Bitacora::IMPORTANCIAS[$b->importancia] ?? $b->importancia); ?></span></td>
                    <?php if ($puede): ?>
                    <td class="acciones">
                        <a href="/bitacora/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($b->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/bitacora/eliminar" style="display:inline" data-confirm="¿Eliminar registro?|Se borrará este registro de la bitácora. Esta acción no se puede deshacer.|Eliminar">
                            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                            <input type="hidden" name="id" value="<?php echo s($b->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
