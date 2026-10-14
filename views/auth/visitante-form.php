<?php $edita = !empty($usuario->id); ?>
<div class="page-header">
    <h2><?php echo $edita ? 'Editar visitante' : 'Nuevo visitante'; ?></h2>
    <a href="/usuarios" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <?php if (!empty($lleno)): ?>
        <p class="text-muted">Ya tienes el máximo de visitantes permitido. Elimina uno para poder crear otro.</p>
    <?php else: ?>
    <form method="POST" class="formulario">
        <div class="campo">
            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo s($usuario->nombre ?? ''); ?>" required>
        </div>
        <div class="campo">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo s($usuario->email ?? ''); ?>" required>
        </div>
        <div class="campo">
            <label>Rol</label>
            <input type="text" value="Visitante (solo lectura)" disabled>
        </div>
        <div class="campo campo-accesos">
            <label>Proyectos que puede ver</label>
            <small class="text-muted">Solo aparecen tus proyectos.</small>
            <?php
            $items = array_map(fn($p) => ['id' => (int)$p->id, 'titulo' => $p->nombre, 'detalle' => $p->codigo], $proyectos);
            $nombreCampo = 'proyectos';
            $marcados = $asignados ?? [];
            include __DIR__ . '/../templates/lista-accesos.php';
            ?>
        </div>
        <div class="campo">
            <label for="password"><?php echo $edita ? 'Nuevo password (dejar vacío para mantener)' : 'Password'; ?></label>
            <input type="password" id="password" name="password" <?php echo $edita ? '' : 'required'; ?> autocomplete="new-password">
        </div>
        <div class="campo">
            <label for="password2">Repetir password</label>
            <input type="password" id="password2" name="password2" <?php echo $edita ? '' : 'required'; ?> autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary"><?php echo $edita ? 'Guardar cambios' : 'Crear visitante'; ?></button>
    </form>
    <?php endif; ?>
</div>
