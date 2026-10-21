<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-cubes"></i> Catálogo de materiales</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($materiales); ?> material(es)</p>
    </div>
    <div class="la-controles">
        <form method="GET" class="buscador-inline">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
            <input type="search" name="q" value="<?php echo s($q); ?>" placeholder="Buscar material, referencia…" data-filtra=".main-content table.tabla" autocomplete="off">
            <select name="especialidad" class="btn btn-ghost btn-sm" aria-label="Filtrar por especialidad" onchange="this.form.submit()">
                <option value="">Todas las especialidades</option>
                <option value="__sin"<?php echo $fEsp === '__sin' ? ' selected' : ''; ?>>Sin especialidad</option>
                <?php foreach ($especialidades as $e): ?><option value="<?php echo s($e); ?>"<?php echo $fEsp === $e ? ' selected' : ''; ?>><?php echo s($e); ?></option><?php endforeach; ?>
            </select>
            <select name="entrega" class="btn btn-ghost btn-sm" aria-label="Filtrar por estatus de entrega" onchange="this.form.submit()">
                <option value="">Toda entrega</option>
                <option value="pendiente"<?php echo $fEntrega === 'pendiente' ? ' selected' : ''; ?>>Entrega pendiente</option>
                <option value="entregado"<?php echo $fEntrega === 'entregado' ? ' selected' : ''; ?>>Entregado</option>
            </select>
            <select name="compra" class="btn btn-ghost btn-sm" aria-label="Filtrar por compra" onchange="this.form.submit()">
                <option value="">Toda compra</option>
                <?php foreach (\Model\Material::COMPRA as $k => $etq): ?><option value="<?php echo $k; ?>"<?php echo $fCompra === $k ? ' selected' : ''; ?>><?php echo $etq; ?></option><?php endforeach; ?>
            </select>
            <?php if ($q !== '' || $fEsp !== '' || $fEntrega !== '' || $fCompra !== ''): ?><a href="/materiales?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost btn-sm">Limpiar</a><?php endif; ?>
        </form>
        <a href="/especialidades?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-tags"></i> Especialidades</a>
        <a href="/materiales/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">+ Nuevo material</a>
    </div>
</div>

<?php
$verif = $verificaciones ?? [];
$malos = array_filter($materiales, fn($m) => ($v = $verif[(int)$m->id] ?? null) && $v['estado'] === 'no_aprobado' && \Classes\MaterialesInteligentes::vigente($v, $m));
?>
<?php if ($malos): ?>
    <div class="norma-aviso" role="alert">
        <strong><i class="fa-solid fa-triangle-exclamation"></i> <?php echo count($malos); ?> material(es) no cumplen las normativas y especificaciones del proyecto</strong>
        <ul><?php foreach ($malos as $m): ?><li><?php echo s($m->nombre); ?> — <?php echo s($verif[(int)$m->id]['resumen']); ?></li><?php endforeach; ?></ul>
        <small class="text-muted">No los incluyas en un submittal hasta corregirlos o confirmar con quien lleva la normativa.</small>
    </div>
<?php endif; ?>
<?php if ($inteligentes ?? false): ?><p class="text-muted text-sm"><i class="fa-solid fa-wand-magic-sparkles"></i> Materiales inteligentes activo: cada material nuevo o modificado se verifica contra las normativas y especificaciones del proyecto desde la app de escritorio.</p><?php endif; ?>
<div class="card">
    <?php if (empty($materiales)): ?>
        <p class="text-muted">Aún no hay materiales<?php echo ($q !== '' || $fEsp !== '' || $fEntrega !== '' || $fCompra !== '') ? ' que coincidan con los filtros' : ''; ?>. Agrega uno o impórtalos desde el MSR.</p>
    <?php else: ?>
        <table class="tabla" <?php echo pag_attr($proyecto); ?> data-proyecto="<?php echo s($proyecto->id); ?>">
            <thead>
                <tr><?php if (is_admin()): ?><th>Revisión</th><?php endif; ?><th class="hide-m">Imagen</th><th class="hide-m">Referencia</th><th class="col-mat">Material</th><th class="hide-m">Especialidad</th><?php if ($inteligentes || $verif): ?><th>Normativa</th><?php endif; ?><th>Compra</th><th>Entrega</th><th class="hide-m">Web</th><th class="hide-m">Ficha</th><th class="hide-m">Certificado calidad</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            <?php $revs = $revisiones ?? []; $puedeRev = puede_revisar($proyecto); foreach ($materiales as $m): ?>
                <tr>
                    <?php if (is_admin()): ?><td><?php echo \Classes\Revisiones::chip('material', (string)$m->id, \Classes\Revisiones::estado($revs[(string)$m->id] ?? null, \Classes\Revisiones::huellaMaterial((array)$m)), $puedeRev, $m->nombre); ?></td><?php endif; ?>
                    <td class="hide-m"><?php if ($m->imagen): ?><img class="mat-thumb" src="/uploads/materiales/<?php echo s($m->imagen); ?>" alt=""><?php else: ?><span class="mat-thumb mat-thumb-vacio"><i class="fa-regular fa-image"></i></span><?php endif; ?></td>
                    <td class="mono hide-m"><?php echo s($m->referencia); ?></td>
                    <td class="col-mat"><?php echo s($m->nombre); ?></td>
                    <td class="hide-m"><?php echo s($m->especialidad); ?></td>
                    <?php if ($inteligentes || $verif): ?><td><?php $v = $verif[(int)$m->id] ?? null; echo \Classes\MaterialesInteligentes::badge($v, \Classes\MaterialesInteligentes::vigente($v, $m), $inteligentes); ?></td><?php endif; ?>
                    <td>
                        <div class="toggle-estatus" role="group" aria-label="Compra" data-id="<?php echo s($m->id); ?>">
                            <?php foreach (\Model\Material::COMPRA as $k => $etq): ?>
                                <button type="button" class="te-op te-<?php echo $k; ?><?php echo $m->compra === $k ? ' activo' : ''; ?>" data-campo="compra" data-valor="<?php echo $k; ?>"><?php echo $etq; ?></button>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-ghost btn-entregado<?php echo $m->entregado() ? ' activo' : ''; ?>" data-id="<?php echo s($m->id); ?>" title="<?php echo $m->entregado() ? 'Entregado el ' . s(formatear_fecha($m->fecha_entrega_real)) . ' · clic para desmarcar' : 'Clic para marcar como entregado (hoy)'; ?>">
                            <i class="fa-solid fa-truck-ramp-box"></i> Entregado<?php echo $m->entregado() ? ' · ' . s(formatear_fecha($m->fecha_entrega_real)) : ''; ?>
                        </button>
                    </td>
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
<script>
(() => {
    const proyecto = new URLSearchParams(location.search).get('proyecto') || '';
    const post = datos => fetch('/api/materiales/estado', { method: 'POST', body: new URLSearchParams({ proyecto, ...datos }) }).then(r => r.json());
    document.addEventListener('click', async e => {
        const op = e.target.closest('.toggle-estatus .te-op');
        if (op && !op.classList.contains('activo')) {
            const g = op.closest('.toggle-estatus');
            try {
                const d = await post({ id: g.dataset.id, campo: 'compra', valor: op.dataset.valor });
                if (d.success) g.querySelectorAll('.te-op').forEach(x => x.classList.toggle('activo', x === op));
            } catch (_) {}
            return;
        }
        const b = e.target.closest('.btn-entregado');
        if (!b) return;
        try {
            const d = await post({ id: b.dataset.id, campo: 'entregado', valor: b.classList.contains('activo') ? '0' : '1' });
            if (!d.success) return;
            b.classList.toggle('activo', d.entregado);
            const f = d.fecha_entrega_real ? d.fecha_entrega_real.split('-').reverse().join('/') : '';
            b.innerHTML = '<i class="fa-solid fa-truck-ramp-box"></i> Entregado' + (d.entregado ? ' · ' + f : '');
        } catch (_) {}
    });
})();
</script>
