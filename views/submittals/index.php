<?php $puede = puede_gestionar_proyecto($proyecto); $pid = (int)$proyecto->id; $cols = $diseno['cols']; $nCols = count($cols); $grupoActual = null; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-boxes-packing"></i> <?php echo s(t('Submittals')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($paquetes); ?> paquete(s)</p>
    </div>
    <a href="/submittals/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary"><?php echo s(t('+ Nuevo paquete')); ?></a>
</div>
<?php $herramienta = 'submittals'; $puedeDisenar = $puede; include __DIR__ . '/../listas/_barra.php'; ?>
<div class="card">
    <?php if (empty($paquetes)): ?>
        <p class="text-muted"><?php echo $cp['filtros'] ? 'Ningún paquete coincide con el filtro.' : 'Aún no hay paquetes. Crea uno y elige los materiales del catálogo.'; ?></p>
    <?php else: ?>
        <div class="tabla-wrapper"><table class="tabla tabla-submittals" <?php echo $cp['agrupar'] ? '' : pag_attr($proyecto); ?>>
            <thead><tr>
                <?php foreach ($cols as $c): ?><th class="<?php echo $c['movil'] ? '' : 'hide-m'; ?>"><?php echo s($c['n']); ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($paquetes as $p): ?>
                <?php if ($cp['agrupar'] && $grupoActual !== $cp['grupo'][(int)$p->id]): $grupoActual = $cp['grupo'][(int)$p->id]; ?>
                    <tr class="cp-grupo"><td colspan="<?php echo $nCols; ?>"><?php echo s($grupoActual); ?></td></tr>
                <?php endif; ?>
                <tr>
                    <?php foreach ($cols as $c): $hm = $c['movil'] ? '' : 'hide-m'; switch ($c['k']): case 'numero': ?>
                    <td class="mono"><a href="/submittals/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($p->id); ?>"><?php echo s($p->numero_submittal); ?></a></td>
                    <?php break; case 'msr': ?>
                    <td class="hide-m"><?php echo $p->msr_id ? s($msrNombres[$p->msr_id] ?? '') : '<span class="text-muted">—</span>'; ?></td>
                    <?php break; case 'fecha': ?>
                    <td class="mono hide-m"><?php echo formatear_fecha($p->fecha); ?></td>
                    <?php break; case 'especialidad': ?>
                    <td class="hide-m"><?php echo s($p->especialidad); ?></td>
                    <?php break; case 'departamento': ?>
                    <td class="hide-m"><?php echo s($p->departamento); ?></td>
                    <?php break; case 'para': ?>
                    <td class="hide-m"><?php echo s($p->para); ?></td>
                    <?php break; case 'materiales': ?>
                    <td class="mono hide-m"><?php echo $conteos[(int)$p->id] ?? 0; ?></td>
                    <?php break; case 'estatus': ?>
                    <td>
                        <div class="toggle-estatus" role="group" aria-label="Estatus" data-url="/api/submittals/estatus" data-campo="estatus" data-id="<?php echo s($p->id); ?>" data-actual="<?php echo s($p->estatus); ?>">
                            <?php foreach (\Model\Submittal::ESTATUS as $k => $etq): ?>
                                <button type="button" class="te-op te-<?php echo $k; ?><?php echo $p->estatus === $k ? ' activo' : ''; ?>" data-valor="<?php echo $k; ?>"><?php echo $etq; ?></button>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <?php break; case 'acciones': ?>
                    <td class="acciones hide-m">
                        <a href="/submittals/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($p->id); ?>" class="btn btn-sm btn-ghost"><?php echo s(t('Ver')); ?></a>
                        <a href="/submittals/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($p->id); ?>" class="btn btn-sm btn-ghost"><?php echo s(t('Editar')); ?></a>
                        <form method="POST" action="/submittals/eliminar" style="display:inline" data-confirm="¿Eliminar paquete?|Se quitarán también sus renglones del MSR.">
                            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                            <input type="hidden" name="id" value="<?php echo s($p->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">✕</button>
                        </form>
                    </td>
                    <?php break; default: if ($c['campo']) echo \Classes\CamposPersonalizados::celda($c['campo'], (int)$p->id, $cp['valores'][(int)$p->id][$c['campo']['id']] ?? '', $puede); endswitch; endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<script>
document.querySelectorAll('.toggle-estatus').forEach(g => g.addEventListener('click', async e => {
    const b = e.target.closest('.te-op');
    if (!b || b.classList.contains('activo')) return;
    try {
        const r = await fetch(g.dataset.url, { method: 'POST', body: new URLSearchParams({ proyecto: new URLSearchParams(location.search).get('proyecto') || '', id: g.dataset.id, [g.dataset.campo]: b.dataset.valor }) });
        const d = await r.json();
        if (!d.success) return;
        g.querySelectorAll('.te-op').forEach(x => x.classList.toggle('activo', x === b));
    } catch (_) {}
}));
</script>
