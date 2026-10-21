<?php // Aviso que solo se ve en celular. Variables opcionales: $avisoTexto, $avisoLectura (true = el contenido sigue visible pero sin edición)
$avisoTexto = $avisoTexto ?? 'Esta herramienta solo se puede editar en una tablet o en un equipo de escritorio.';
$avisoLectura = $avisoLectura ?? false;
?>
<div class="aviso-escritorio no-print" role="note">
    <i class="fa-solid fa-tablet-screen-button" aria-hidden="true"></i>
    <p><strong><?php echo s(t('Edición no disponible en celular')); ?></strong><br><?php echo s(t($avisoTexto)); ?><?php echo $avisoLectura ? ' ' . s(t('Aquí puedes consultarla.')) : ''; ?></p>
</div>
<?php if ($avisoLectura): ?>
<script>
(() => {   // En celular el contenido queda solo de lectura
    if (!window.matchMedia('(max-width: 64rem)').matches) return;
    document.body.classList.add('lectura-movil');
    const bloquear = () => document.querySelectorAll('[contenteditable="true"]').forEach(e => e.setAttribute('contenteditable', 'false'));
    window.addEventListener('load', () => { bloquear(); setTimeout(bloquear, 800); });
})();
</script>
<?php endif; ?>
