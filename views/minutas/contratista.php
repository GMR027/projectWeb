<?php $puede = puede_gestionar_proyecto($proyecto); $id = s($proyecto->id); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-helmet-safety"></i> Personal del contratista</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($lista); ?> persona(s)</p>
    </div>
    <div class="la-controles">
        <a href="/minutas?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Minutas</a>
        <?php if ($puede): ?><a href="/minutas/contratista/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary">+ Nuevo</a><?php endif; ?>
    </div>
</div>
<div class="card">
    <?php if (empty($lista)): ?>
        <p class="text-muted">Aún no hay personal del contratista.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th class="hide-m">Puesto</th><th class="hide-m">Empresa</th><?php if ($puede): ?><th>Acciones</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($lista as $p): ?>
                <tr>
                    <td><?php echo s($p->nombre); ?></td><td class="hide-m"><?php echo s($p->puesto) ?: '—'; ?></td><td class="hide-m"><?php echo s($p->empresa) ?: '—'; ?></td>
                    <?php if ($puede): ?>
                    <td class="acciones">
                        <a href="/minutas/contratista/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($p->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/minutas/contratista/eliminar" style="display:inline" data-confirm="¿Eliminar persona?|Se quitará del catálogo y de las minutas donde aparece.|Eliminar">
                            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                            <input type="hidden" name="id" value="<?php echo s($p->id); ?>">
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
