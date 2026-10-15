<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-tags"></i> Especialidades</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · propias de este proyecto (no las ven otros proyectos ni usuarios)</p>
    </div>
    <a href="/materiales?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Materiales</a>
</div>
<div class="card">
    <form method="POST" action="/especialidades/guardar" class="la-controles" style="margin-bottom:1.5rem">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <input type="text" name="nombre" maxlength="100" placeholder="Nueva especialidad (ej. Estructura)" required>
        <button type="submit" class="btn btn-primary">+ Agregar</button>
    </form>
    <?php if (empty($especialidades)): ?>
        <p class="text-muted">Aún no hay especialidades. Agrega la primera; luego podrás elegirla en cada material.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th class="hide-m">Materiales</th><th class="hide-m">Submittals</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($especialidades as $e): ?>
                <tr>
                    <td>
                        <form method="POST" action="/especialidades/guardar" class="la-controles">
                            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                            <input type="hidden" name="id" value="<?php echo s($e->id); ?>">
                            <input type="text" name="nombre" maxlength="100" value="<?php echo s($e->nombre); ?>" required>
                            <button type="submit" class="btn btn-sm btn-ghost">Guardar</button>
                        </form>
                    </td>
                    <td class="mono hide-m"><?php echo (int)$e->n_materiales; ?></td>
                    <td class="mono hide-m"><?php echo (int)$e->n_submittals; ?></td>
                    <td class="acciones">
                        <form method="POST" action="/especialidades/eliminar" style="display:inline" data-confirm="¿Eliminar especialidad?|Solo se puede si ningún material ni submittal la usa.">
                            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                            <input type="hidden" name="id" value="<?php echo s($e->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
