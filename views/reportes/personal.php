<?php $puede = puede_gestionar_proyecto($proyecto); $id = s($proyecto->id); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-users"></i> Personal</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($personal); ?> persona(s)</p>
    </div>
    <div class="la-controles">
        <a href="/reportes?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Reportes</a>
        <?php if ($puede): ?><a href="/reportes/personal/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary">+ Nuevo</a><?php endif; ?>
    </div>
</div>
<div class="card">
    <?php if (empty($personal)): ?>
        <p class="text-muted">Aún no hay personal registrado.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th class="hide-m">Foto</th><th>Nombre</th><th class="hide-m">Departamento</th><?php if ($puede): ?><th>Acciones</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($personal as $p): ?>
                <tr>
                    <td class="hide-m"><?php if ($p->imagen): ?><img class="rd-avatar" src="/uploads/personal/<?php echo s($p->imagen); ?>" alt=""><?php else: ?><span class="rd-avatar rd-avatar-vacio"><i class="fa-solid fa-user"></i></span><?php endif; ?></td>
                    <td><?php echo s($p->nombre); ?></td>
                    <td class="hide-m"><?php echo s($p->departamento); ?></td>
                    <?php if ($puede): ?>
                    <td class="acciones">
                        <a href="/reportes/personal/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($p->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/reportes/personal/eliminar" style="display:inline" data-confirm="¿Eliminar persona?|Se quitará del catálogo y de los reportes donde aparece.|Eliminar">
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
