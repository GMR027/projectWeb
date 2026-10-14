<?php $esNueva = !$lamina->id; ?>
<div class="page-header">
    <h2><?php echo $esNueva ? 'Nueva lámina de vPlan' : 'Editar lámina'; ?></h2>
    <a href="/vplan?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/vplan/<?php echo $esNueva ? 'crear' : 'editar'; ?>" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if (!$esNueva): ?><input type="hidden" name="id" value="<?php echo s($lamina->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo">
                <label for="partida_id">Partida a mostrar *</label>
                <select id="partida_id" name="partida_id" required>
                    <option value="">— Selecciona —</option>
                    <?php foreach ($partidas as $p): ?><option value="<?php echo s($p->id); ?>" data-nombre="<?php echo s($p->nombre); ?>" <?php echo (int)$lamina->partida_id === (int)$p->id ? 'selected' : ''; ?>><?php echo s($p->codigo . ' · ' . $p->nombre); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="campo"><label for="titulo">Título de la lámina</label><input type="text" id="titulo" name="titulo" value="<?php echo s($lamina->titulo); ?>" placeholder="Por defecto, el nombre de la partida"></div>
            <div class="campo"><label for="supervisor">Supervisor</label><input type="text" id="supervisor" name="supervisor" value="<?php echo s($lamina->supervisor); ?>" placeholder="Ej: LUIS APARICIO / HÉCTOR MUÑIZ"></div>
            <div class="campo">
                <label for="plano">Imagen del plano (hasta Full HD o 4K)</label>
                <input type="file" id="plano" name="plano" accept="image/*">
                <?php if ($lamina->plano): ?>
                    <img class="vp-mini" src="/uploads/vplan/<?php echo s($lamina->plano); ?>" alt="" style="margin-top:.5rem">
                    <label class="text-sm"><input type="checkbox" name="quitar_plano" value="1"> Quitar plano (borra también las marcas dibujadas)</label>
                <?php endif; ?>
            </div>
            <div class="campo">
                <label for="simbologia">Imagen de simbología</label>
                <input type="file" id="simbologia" name="simbologia" accept="image/*">
                <?php if ($lamina->simbologia): ?>
                    <img class="vp-mini" src="/uploads/vplan/<?php echo s($lamina->simbologia); ?>" alt="" style="margin-top:.5rem">
                    <label class="text-sm"><input type="checkbox" name="quitar_simbologia" value="1"> Quitar simbología</label>
                <?php endif; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar lámina</button>
    </form>
</div>
<script>
(function () {
    const sel = document.getElementById('partida_id'), tit = document.getElementById('titulo');
    sel.addEventListener('change', () => { if (!tit.value.trim() && sel.selectedOptions[0]) tit.value = sel.selectedOptions[0].dataset.nombre || ''; });
})();
</script>
