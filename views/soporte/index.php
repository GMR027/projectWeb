<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-headset"></i> Soporte</h2>
        <p class="text-muted">Escribe tu duda o lo que necesitas; el administrador recibirá tu mensaje en su bandeja de entrada.</p>
    </div>
    <a href="/dashboard" class="btn btn-ghost">← Inicio</a>
</div>

<?php if (isset($_GET['enviado'])): ?><div class="alerta alerta-exito">Mensaje enviado. Te responderemos lo antes posible.</div><?php endif; ?>
<?php if (isset($_GET['editado'])): ?><div class="alerta alerta-exito">Mensaje actualizado.</div><?php endif; ?>

<div class="card" style="margin-bottom:2rem">
    <h3 class="card-titulo">Nuevo mensaje</h3>
    <form method="POST" action="/soporte/crear" class="formulario">
        <div class="campo">
            <label>Nombre de usuario</label>
            <input type="text" value="<?php echo s($_SESSION['nombre'] ?? ''); ?>" disabled>
        </div>
        <div class="campo">
            <label for="tema">Tema</label>
            <input type="text" id="tema" name="tema" maxlength="200" value="<?php echo s($soporte->tema); ?>" placeholder="Ej. No puedo exportar mi programa" required>
        </div>
        <div class="campo">
            <label for="informacion">Información</label>
            <textarea id="informacion" name="informacion" rows="6" maxlength="5000" placeholder="Cuéntanos tu duda o el soporte que necesitas" required><?php echo s($soporte->informacion); ?></textarea>
        </div>
        <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Enviar</button>
    </form>
</div>

<div class="card">
    <h3 class="card-titulo">Mis mensajes</h3>
    <table class="tabla">
        <thead><tr><th>Tema</th><th class="hide-m">Información</th><th class="hide-m">Fecha</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($mensajes as $m): ?>
            <tr>
                <td><?php echo s($m->tema); ?></td>
                <td class="hide-m text-muted"><?php echo s(mb_strimwidth($m->informacion, 0, 90, '…')); ?></td>
                <td class="mono hide-m"><?php echo s(substr($m->creado, 0, 16)); ?></td>
                <td><span class="badge <?php echo $m->leido ? 'badge-cliente' : 'badge-visitante'; ?>"><?php echo $m->leido ? 'Visto' : 'Enviado'; ?></span></td>
                <td class="acciones">
                    <a href="/soporte/editar?id=<?php echo s($m->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                    <form method="POST" action="/soporte/eliminar" style="display:inline" data-confirm="¿Eliminar mensaje?|Se quitará también de la bandeja del administrador.">
                        <input type="hidden" name="id" value="<?php echo s($m->id); ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$mensajes): ?><tr><td colspan="5" class="text-muted">Aún no has enviado mensajes.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
