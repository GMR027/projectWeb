<?php $puede = puede_gestionar_proyecto($proyecto); $id = s($proyecto->id); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-sitemap"></i> Departamentos de la bitácora</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($lista); ?> departamento(s)</p>
    </div>
    <div class="la-controles">
        <a href="/bitacora?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Bitácora</a>
        <?php if ($puede): ?><a href="/bitacora/departamentos/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary">+ Nuevo departamento</a><?php endif; ?>
    </div>
</div>
<div class="card">
    <?php if (empty($lista)): ?>
        <p class="text-muted">Aún no hay departamentos.<?php echo $puede ? ' Crea el primero con «Nuevo departamento».' : ''; ?></p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Departamento</th><th class="hide-m">Registros</th><?php if ($puede): ?><th>Acciones</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($lista as $f): $d = $f['depto']; ?>
                <tr>
                    <td><?php echo s($d->nombre); ?></td>
                    <td class="mono hide-m"><?php echo (int)$f['uso']; ?></td>
                    <?php if ($puede): ?>
                    <td class="acciones">
                        <a href="/bitacora/departamentos/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($d->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/bitacora/departamentos/eliminar" style="display:inline" data-confirm="¿Eliminar departamento?|Se quitará «<?php echo s($d->nombre); ?>» del catálogo. Si algún registro de la bitácora lo usa, no se podrá eliminar.|Eliminar">
                            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                            <input type="hidden" name="id" value="<?php echo s($d->id); ?>">
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
