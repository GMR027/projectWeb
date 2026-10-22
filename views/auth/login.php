<div class="auth-card">
    <div class="auth-header">
        <div class="logo-grande"><i class="fa-solid fa-helmet-safety"></i></div>
        <h1><?php echo s(t('ProjectWeb')); ?></h1>
        <p class="auth-subtitle"><?php echo s(t('Sistema de Programas de Obra')); ?></p>
    </div>

    <form method="POST" action="/login" class="formulario">
        <div class="campo">
            <label for="email"><?php echo s(t('Email')); ?></label>
            <input type="email" id="email" name="email" value="<?php echo s($usuario->email ?? ''); ?>" required>
        </div>
        <div class="campo">
            <label for="password"><?php echo s(t('Password')); ?></label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?php echo s(t('Iniciar Sesión')); ?></button>
    </form>
    <p class="text-muted text-sm" style="text-align:center; margin-top:1.6rem">
        <?php echo s(t('¿Olvidaste tu contraseña?')); ?> <a href="/#contacto"><?php echo s(t('Escríbenos desde Contacto')); ?></a> <?php echo s(t('para restablecerla.')); ?><br>
        <?php echo s(t('Al acceder aceptas los')); ?> <a href="/terminos" target="_blank" rel="noopener"><?php echo s(t('términos y condiciones de uso')); ?></a>.
    </p>
</div>
