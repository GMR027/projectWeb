<?php /** Cambio de contraseña. Variables: $obligatorio (contraseña inicial o temporal). */ ?>
<div class="auth-card">
    <div class="auth-header">
        <div class="logo-grande"><i class="fa-solid fa-key"></i></div>
        <h1><?php echo s(t('Cambiar contraseña')); ?></h1>
        <p class="auth-subtitle">
            <?php echo $obligatorio
                ? s(t('Tu cuenta tiene una contraseña inicial. Elige una propia para continuar.'))
                : s(t('Elige una contraseña nueva para tu cuenta.')); ?>
        </p>
    </div>

    <?php if ($obligatorio): ?>
        <div class="alerta alerta-aviso" role="status">
            <i class="fa-solid fa-shield-halved"></i>
            <?php echo s(t('Por seguridad, no puedes usar la plataforma hasta cambiar la contraseña con la que te dieron acceso.')); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['pw_error'])): ?>
        <div class="alerta alerta-error" role="alert"><?php echo s($_GET['pw_error']); ?></div>
    <?php endif; ?>

    <form method="POST" action="/cuenta/password" class="formulario">
        <div class="campo">
            <label for="password_actual"><?php echo s(t('Contraseña actual')); ?></label>
            <input type="password" id="password_actual" name="password_actual" autocomplete="current-password" required autofocus>
        </div>
        <div class="campo">
            <label for="password_nueva"><?php echo s(t('Nueva contraseña')); ?></label>
            <input type="password" id="password_nueva" name="password_nueva" minlength="10" autocomplete="new-password" required>
            <small class="text-muted"><?php echo s(t('Mínimo 10 caracteres; no uses una contraseña común.')); ?></small>
        </div>
        <div class="campo">
            <label for="password_nueva2"><?php echo s(t('Repetir nueva contraseña')); ?></label>
            <input type="password" id="password_nueva2" name="password_nueva2" minlength="10" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-floppy-disk"></i> <?php echo s(t('Guardar contraseña')); ?></button>
        <?php if (!$obligatorio): ?>
            <p class="text-center mt-1"><a href="/dashboard"><?php echo s(t('Volver al inicio')); ?></a></p>
        <?php endif; ?>
    </form>
</div>
<script>
// Mostrar/ocultar contraseña (mismo patrón que el resto de formularios del sistema)
document.querySelectorAll('input[type=password]').forEach(function (inp) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'btn btn-sm btn-ghost'; b.textContent = 'Ver';
    b.setAttribute('aria-label', 'Mostrar u ocultar la contraseña');
    b.addEventListener('click', function () { inp.type = inp.type === 'password' ? 'text' : 'password'; b.textContent = inp.type === 'password' ? 'Ver' : 'Ocultar'; });
    inp.insertAdjacentElement('afterend', b);
});
</script>
