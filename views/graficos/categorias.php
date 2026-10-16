<?php $puede = puede_gestionar_proyecto($proyecto); $id = s($proyecto->id); // Variables: $proyecto, $lista [[cat, uso]] ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-tags"></i> Categorías de gráficos</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($lista); ?> categoría(s)</p>
    </div>
    <div class="la-controles">
        <a href="/graficos?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Editor</a>
        <?php if ($puede): ?><a href="/graficos/categorias/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary">+ Nueva categoría</a><?php endif; ?>
    </div>
</div>
<div class="card">
    <?php if (empty($lista)): ?>
        <p class="text-muted">Aún no hay categorías.<?php echo $puede ? ' Crea la primera con «Nueva categoría».' : ''; ?></p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Categoría</th><th class="hide-m">Gráficos</th><?php if ($puede): ?><th>Acciones</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($lista as $f): $c = $f['cat']; ?>
                <tr>
                    <td><?php echo s($c->nombre); ?></td>
                    <td class="mono hide-m"><?php echo (int)$f['uso']; ?></td>
                    <?php if ($puede): ?>
                    <td class="acciones">
                        <a href="/graficos/categorias/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($c->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/graficos/categorias/eliminar" style="display:inline" data-confirm="¿Eliminar categoría?|Se quitará «<?php echo s($c->nombre); ?>». Si algún gráfico la usa, no se podrá eliminar.|Eliminar">
                            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                            <input type="hidden" name="id" value="<?php echo s($c->id); ?>">
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
