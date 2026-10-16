<?php // Casillas de las herramientas que ve un visitante. Variables: $marcadas (claves marcadas o null = todas)
$todas = $marcadas === null;
?>
<div class="campo" id="campo-herramientas">
    <label>Herramientas que puede ver</label>
    <small class="text-muted">Marca solo lo que este visitante debe poder abrir; lo demás desaparece de su menú y no puede entrar por enlace. Con todas marcadas ve todo el proyecto.</small>
    <div class="herr-atajos"><button type="button" class="btn btn-sm btn-ghost" data-herr="todas">Todas</button> <button type="button" class="btn btn-sm btn-ghost" data-herr="ninguna">Ninguna</button></div>
    <div class="herr-grid">
        <?php foreach (\Model\Usuario::HERRAMIENTAS as $clave => [$etq]): ?>
            <label class="herr-item"><input type="checkbox" name="herr[]" value="<?php echo s($clave); ?>"<?php echo $todas || in_array($clave, $marcadas, true) ? ' checked' : ''; ?>> <?php echo s(t($etq)); ?></label>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function () {
    const caja = document.getElementById('campo-herramientas'); if (!caja) return;
    caja.querySelectorAll('[data-herr]').forEach(b => b.addEventListener('click', () => caja.querySelectorAll('input[type=checkbox]').forEach(c => { c.checked = b.dataset.herr === 'todas'; })));
})();
</script>
