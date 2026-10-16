<?php // Variables: $proyecto, $graficos, $puedeEditar ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-street-view"></i> View360</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($graficos); ?> gráfico(s) interactivo(s)</p>
    </div>
    <?php if ($puedeEditar): ?>
        <a href="/view360/crear?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Crear gráfico interactivo</a>
    <?php endif; ?>
</div>
<div class="card">
    <?php if (empty($graficos)): ?>
        <p class="text-muted">Aún no hay gráficos interactivos.<?php echo $puedeEditar ? ' Crea el primero con «Crear gráfico interactivo».' : ''; ?></p>
    <?php else: ?>
        <div class="tabla-wrapper">
        <table class="tabla">
            <thead><tr><th>Fecha</th><th>Nombre del gráfico</th><th>Versión</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($graficos as $g): ?>
                <tr>
                    <td class="mono"><?php echo s(formatear_fecha($g->fecha)); ?></td>
                    <td><?php echo s($g->nombre); ?></td>
                    <td class="mono"><?php echo $g->version !== '' ? s($g->version) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="acciones">
                        <?php if ($g->grafico_id): ?><a href="/view360/ver?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$g->id; ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i> Ver</a><?php endif; ?>
                        <?php if ($puedeEditar): ?>
                        <?php if ($g->grafico_id): ?><a href="/graficos/abrir?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$g->grafico_id; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen-ruler"></i> Editor</a><?php endif; ?>
                        <a href="/view360/editar?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$g->id; ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/view360/eliminar" style="display:inline" data-confirm="¿Eliminar gráfico?|Se eliminará «<?php echo s($g->nombre); ?>».">
                            <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$g->id; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">✕</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
