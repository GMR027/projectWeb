<?php
$edita = !empty($usuario->id);
$tipo = $tipo ?? 'visitante';
$esColab = $tipo === 'colaborador';
?>
<div class="page-header">
    <h2><?php echo $edita ? 'Editar ' . ($esColab ? 'colaborador' : 'visitante') : ($esColab ? 'Nuevo colaborador' : 'Nuevo visitante'); ?></h2>
    <a href="/usuarios" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <?php if (!empty($lleno)): ?>
        <p class="text-muted">Ya tienes el máximo de <?php echo (int)($limite ?? 0); ?> <?php echo $esColab ? 'colaboradores' : 'visitantes'; ?> permitido. Elimina uno para poder crear otro.</p>
    <?php else: ?>
    <form method="POST" class="formulario">
        <div class="campo">
            <label for="tipo">Tipo de cuenta</label>
            <select id="tipo" name="tipo">
                <option value="visitante"<?php echo !$esColab ? ' selected' : ''; ?>>Visitante · solo lectura</option>
                <option value="colaborador"<?php echo $esColab ? ' selected' : ''; ?>>Colaborador · puede editar</option>
            </select>
            <small class="text-muted"><?php echo $esColab ? 'Colaboradores' : 'Visitantes'; ?>: <?php echo (int)($usados ?? 0); ?> de <?php echo (int)($limite ?? 0); ?> en este tipo. El administrador fija los topes de cada tipo.</small>
        </div>
        <div class="campo">
            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo s($usuario->nombre ?? ''); ?>" required>
        </div>
        <div class="campo">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo s($usuario->email ?? ''); ?>" required>
        </div>
        <div class="campo campo-accesos">
            <label>Proyectos a los que entra</label>
            <small class="text-muted">Solo aparecen tus proyectos.</small>
            <?php
            $items = array_map(fn($p) => ['id' => (int)$p->id, 'titulo' => $p->nombre, 'detalle' => $p->codigo], $proyectos);
            $nombreCampo = 'proyectos';
            $marcados = $asignados ?? [];
            include __DIR__ . '/../templates/lista-accesos.php';
            ?>
        </div>
        <?php $marcadas = $herramientasMarcadas ?? null; include __DIR__ . '/../templates/herramientas-visitante.php'; ?>
        <div class="campo">
            <label for="password"><?php echo $edita ? 'Nuevo password (dejar vacío para mantener)' : 'Password'; ?></label>
            <input type="password" id="password" name="password" <?php echo $edita ? '' : 'required'; ?> autocomplete="new-password">
        </div>
        <div class="campo">
            <label for="password2">Repetir password</label>
            <input type="password" id="password2" name="password2" <?php echo $edita ? '' : 'required'; ?> autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary"><?php echo $edita ? 'Guardar cambios' : ($esColab ? 'Crear colaborador' : 'Crear visitante'); ?></button>
    </form>
    <?php endif; ?>
</div>
