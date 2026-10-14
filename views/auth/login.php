<div class="auth-card">
    <div class="auth-header">
        <div class="logo-grande"><i class="fa-solid fa-helmet-safety"></i></div>
        <h1>ProjectWeb</h1>
        <p class="auth-subtitle">Sistema de Programas de Obra</p>
    </div>

    <form method="POST" action="/login" class="formulario">
        <div class="campo">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo s($usuario->email ?? ''); ?>" required>
        </div>
        <div class="campo">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
    </form>
    <p class="text-muted text-sm" style="text-align:center; margin-top:1.6rem">
        ¿Olvidaste tu contraseña? <a href="/#contacto">Escríbenos desde Contacto</a> para restablecerla.<br>
        Al acceder aceptas los <a href="/terminos" target="_blank" rel="noopener">términos y condiciones de uso</a>.
    </p>
</div>
