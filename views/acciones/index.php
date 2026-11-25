<?php $totalGeneral = array_sum(array_column($secciones, 'total')); $secActivas = count(array_filter($secciones, fn($s) => (int)$s['total'] > 0)); ?>
<div class="page-header">
    <div>
        <span class="adm-eyebrow">Administración de obra</span>
        <h2><i class="fa-solid fa-bolt"></i> Acciones rápidas</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo (int)$totalGeneral; ?> pendiente(s) en total</p>
        <div class="adm-meta">
            <span><i class="fa-solid fa-list-check"></i> <b><?php echo count($secciones); ?></b> frentes</span>
            <span><i class="fa-solid fa-circle-exclamation"></i> <b><?php echo $secActivas; ?></b> con pendientes</span>
        </div>
    </div>
</div>
<div class="cb-kpis">
    <div class="cb-kpi <?php echo $totalGeneral ? 'is-warning' : ''; ?>"><span class="adm-kpi-icono"><i class="fa-solid fa-bolt"></i></span><small>Pendientes totales</small><strong><?php echo (int)$totalGeneral; ?></strong><span>En todos los frentes de la obra</span></div>
    <div class="cb-kpi"><span class="adm-kpi-icono"><i class="fa-solid fa-list-check"></i></span><small>Frentes revisados</small><strong><?php echo count($secciones); ?></strong><span><?php echo $secActivas; ?> con algo por hacer</span></div>
    <div class="cb-kpi is-success"><span class="adm-kpi-icono"><i class="fa-solid fa-circle-check"></i></span><small>Sin pendientes</small><strong><?php echo count($secciones) - $secActivas; ?></strong><span>Frentes al día</span></div>
    <div class="cb-kpi is-info"><span class="adm-kpi-icono"><i class="fa-solid fa-arrows-rotate"></i></span><small>Actualización</small><strong><?php echo $gestiona ? 'Sí' : 'No'; ?></strong><span><?php echo $gestiona ? 'Puedes resolver desde aquí' : 'Solo lectura para tu acceso'; ?></span></div>
</div>
<div class="acc-pag-nav">
    <?php foreach ($secciones as $sec): ?>
        <a href="#acc-<?php echo s($sec['clave']); ?>" class="<?php echo $sec['total'] ? '' : 'vacia'; ?>"><?php echo s($sec['titulo']); ?><?php if ($sec['total']): ?> <b><?php echo (int)$sec['total']; ?></b><?php endif; ?></a>
    <?php endforeach; ?>
</div>
<?php foreach ($secciones as $sec): ?>
    <div class="card acc-pag-sec" id="acc-<?php echo s($sec['clave']); ?>">
        <h3><i class="fa-solid <?php echo s($sec['icono']); ?>"></i> <?php echo s($sec['titulo']); ?> <span class="text-muted">(<?php echo (int)$sec['total']; ?>)</span>
            <a href="<?php echo s($sec['ver']); ?>" class="btn btn-sm btn-ghost acc-ir">Ir al módulo</a></h3>
        <?php if (!$sec['items']): ?>
            <p class="text-muted">Nada pendiente.</p>
        <?php else: ?>
            <table class="tabla tabla-acciones">
                <thead><tr><?php if ($sec['clave'] === 'restr'): ?><th>WBS</th><?php endif; ?><th>Elemento</th><th>Detalle</th><?php if ($gestiona && in_array($sec['clave'], ['submittals', 'materiales'], true)): ?><th><?php echo $sec['clave'] === 'submittals' ? 'Estatus' : 'Compra y entrega'; ?></th><?php endif; ?><th><?php echo $gestiona && $sec['clave'] === 'restr' ? 'Acciones' : ''; ?></th></tr></thead>
                <tbody>
                <?php foreach ($sec['items'] as $it): ?>
                    <tr>
                        <?php if ($sec['clave'] === 'restr'): ?><td class="mono"><?php echo s($it['wbs'] ?? ''); ?></td><?php endif; ?>
                        <td><?php if ($it['chip']): ?><em class="acc-chip acc-<?php echo s(strtolower(str_replace('/', '', $it['chip']))); ?>"><?php echo s($it['chip']); ?></em> <?php endif; ?><?php echo s($it['titulo']); ?></td>
                        <td class="text-muted"><?php echo s($it['sub']); ?></td>
                        <?php if ($gestiona && $sec['clave'] === 'submittals'): ?>
                            <td>
                                <?php if (!empty($it['sid'])): ?>
                                <div class="toggle-estatus" role="group" aria-label="Estatus" data-url="/api/submittals/estatus" data-campo="estatus" data-id="<?php echo (int)$it['sid']; ?>">
                                    <?php foreach (\Model\Submittal::ESTATUS as $k => $etq): ?>
                                        <button type="button" class="te-op te-<?php echo $k; ?><?php echo $it['estatus'] === $k ? ' activo' : ''; ?>" data-valor="<?php echo $k; ?>"><?php echo s($etq); ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?><span class="text-muted text-sm">Sin submittal</span><?php endif; ?>
                            </td>
                        <?php elseif ($gestiona && $sec['clave'] === 'materiales'): ?>
                            <td>
                                <div class="toggle-estatus" role="group" aria-label="Compra" data-url="/api/materiales/estado" data-campo="compra" data-id="<?php echo (int)$it['mid']; ?>" data-prefijo="valor">
                                    <?php foreach (\Model\Material::COMPRA as $k => $etq): ?>
                                        <button type="button" class="te-op te-<?php echo $k; ?><?php echo $it['compra'] === $k ? ' activo' : ''; ?>" data-valor="<?php echo $k; ?>"><?php echo s($etq); ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-ghost btn-entregado<?php echo $it['entregado'] ? ' activo' : ''; ?>" data-id="<?php echo (int)$it['mid']; ?>"><i class="fa-solid fa-truck-ramp-box"></i> Entregado</button>
                            </td>
                        <?php endif; ?>
                        <td class="acciones">
                            <a href="<?php echo s($it['url']); ?>" class="btn btn-sm btn-ghost"><?php echo in_array($sec['clave'], ['restr', 'sinmat'], true) ? 'Editar actividad' : 'Abrir'; ?></a>
                            <?php if ($gestiona && $sec['clave'] === 'restr'): ?>
                                <form class="inline" method="POST" action="/programa/restricciones/resolver">
                                    <input type="hidden" name="id" value="<?php echo (int)$it['rid']; ?>">
                                    <input type="hidden" name="volver" value="/acciones">
                                    <button type="submit" class="btn btn-sm btn-primary">Resolver</button>
                                </form>
                                <a href="/programa/restricciones/editar?id=<?php echo (int)$it['rid']; ?>&volver=/acciones" class="btn btn-sm btn-secondary">Editar</a>
                                <form class="inline" method="POST" action="/programa/restricciones/eliminar" data-confirm="¿Eliminar restricción?|La restricción se quitará de la actividad. Esta acción no se puede deshacer.">
                                    <input type="hidden" name="id" value="<?php echo (int)$it['rid']; ?>">
                                    <input type="hidden" name="volver" value="/acciones">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Quitar" aria-label="Quitar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if ($gestiona): ?>
<script>
(() => {
    const proyecto = new URLSearchParams(location.search).get('proyecto') || '';
    const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams({ proyecto, ...datos }) }).then(r => r.json());
    document.addEventListener('click', async e => {
        const op = e.target.closest('.toggle-estatus .te-op');
        if (op && !op.classList.contains('activo')) {
            const g = op.closest('.toggle-estatus');
            const datos = g.dataset.campo === 'compra'
                ? { id: g.dataset.id, campo: 'compra', valor: op.dataset.valor }
                : { id: g.dataset.id, [g.dataset.campo]: op.dataset.valor };
            try {
                const d = await post(g.dataset.url, datos);
                if (d.success) g.querySelectorAll('.te-op').forEach(x => x.classList.toggle('activo', x === op));
            } catch (_) {}
            return;
        }
        const b = e.target.closest('.btn-entregado');
        if (!b) return;
        try {
            const d = await post('/api/materiales/estado', { id: b.dataset.id, campo: 'entregado', valor: b.classList.contains('activo') ? '0' : '1' });
            if (d.success) b.classList.toggle('activo', d.entregado);
        } catch (_) {}
    });
})();
</script>
<?php endif; ?>
