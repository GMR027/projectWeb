<?php
// Barra superior de la landing y de Servicios. $base = '' en la landing (anclas locales) o '/' en las demás páginas.
$base = $base ?? '';
$activa = $activa ?? '';
?>
<header class="lp-nav" id="top">
    <div class="lp-wrap lp-nav-in">
        <a href="/" class="lp-logo"><span class="lp-logo-i"><i class="fa-solid fa-helmet-safety"></i></span> ProjectWeb</a>
        <input type="checkbox" id="lp-menu" class="lp-menu-chk" aria-label="Menú">
        <label for="lp-menu" class="lp-burger" aria-hidden="true"><i class="fa-solid fa-bars"></i></label>
        <nav class="lp-links">
            <a href="<?php echo $base; ?>#funciones">Funciones</a>
            <a href="<?php echo $base; ?>#novedades">Novedades</a>
            <a href="/servicios"<?php echo $activa === 'servicios' ? ' class="on" aria-current="page"' : ''; ?>>Servicios</a>
            <a href="<?php echo $base; ?>#planes">Planes</a>
            <a href="<?php echo $base; ?>#contacto">Contacto</a>
            <?php if (!empty($demoDisponible)): ?><a href="/demo" class="lp-btn lp-btn-ghost lp-btn-sm"><i class="fa-solid fa-play"></i> Demo</a><?php endif; ?>
            <a href="/login" class="lp-btn lp-btn-primary lp-btn-sm">Iniciar sesión</a>
        </nav>
    </div>
</header>
