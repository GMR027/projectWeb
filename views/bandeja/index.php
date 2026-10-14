<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-inbox"></i> Bandeja de entrada</h2>
        <p class="text-muted">Mensajes de contacto de la página principal y de soporte de tus clientes · <strong><?php echo (int)$sinLeer; ?></strong> sin leer</p>
    </div>
</div>

<div class="msg-tabs">
    <a href="/bandeja" class="<?php echo $filtro === 'todos' ? 'activo' : ''; ?>">Todos</a>
    <a href="/bandeja?tipo=contacto" class="<?php echo $filtro === 'contacto' ? 'activo' : ''; ?>"><i class="fa-solid fa-envelope"></i> Contacto</a>
    <a href="/bandeja?tipo=soporte" class="<?php echo $filtro === 'soporte' ? 'activo' : ''; ?>"><i class="fa-solid fa-headset"></i> Soporte</a>
</div>

<div class="card">
    <table class="tabla">
        <thead><tr><th>Tipo</th><th>De</th><th class="hide-m">Asunto</th><th class="hide-m">Fecha</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): $url = '/bandeja/ver?tipo=' . $it['tipo'] . '&id=' . (int)$it['id']; ?>
            <tr class="msg-fila <?php echo $it['leido'] ? '' : 'sin-leer'; ?>" onclick="location.href='<?php echo s($url); ?>'">
                <td><span class="badge <?php echo $it['tipo'] === 'contacto' ? 'badge-admin' : 'badge-cliente'; ?>"><?php echo $it['tipo'] === 'contacto' ? 'Contacto' : 'Soporte'; ?></span></td>
                <td><?php echo s($it['de']); ?></td>
                <td class="hide-m"><?php echo s($it['asunto']); ?></td>
                <td class="mono hide-m"><?php echo s(substr($it['fecha'], 0, 16)); ?></td>
                <td class="acciones" onclick="event.stopPropagation()">
                    <a href="<?php echo s($url); ?>" class="btn btn-sm btn-ghost">Abrir</a>
                    <form method="POST" action="/bandeja/accion" style="display:inline" data-confirm="¿Eliminar mensaje?|Esta acción no se puede deshacer.">
                        <input type="hidden" name="tipo" value="<?php echo s($it['tipo']); ?>"><input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                        <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="volver" value="<?php echo s($filtro); ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="5" class="text-muted">No hay mensajes todavía.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
