<?php use Classes\CurvaProductividad as CP; $esNueva = !$curva->id; ?>
<div class="page-header">
    <h2><?php echo $esNueva ? 'Nueva curva de productividad' : 'Editar curva'; ?></h2>
    <a href="/curvas?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/curvas/<?php echo $esNueva ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if (!$esNueva): ?><input type="hidden" name="id" value="<?php echo s($curva->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="nombre">Nombre de la curva *</label><input type="text" id="nombre" name="nombre" value="<?php echo s($curva->nombre); ?>" required placeholder="Ej: Instalación de tubería"></div>
            <div class="campo">
                <label for="material_id">Material a medir</label>
                <select id="material_id" name="material_id">
                    <option value="">— Sin material —</option>
                    <?php foreach ($materiales as $m): ?>
                        <option value="<?php echo s($m->id); ?>" <?php echo (int)$curva->material_id === (int)$m->id ? 'selected' : ''; ?>><?php echo s($m->nombre . ($m->especialidad ? ' · ' . $m->especialidad : '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo"><label for="cantidad">Cantidad *</label><input type="number" id="cantidad" name="cantidad" min="1" step="1" value="<?php echo s($curva->cantidad ?: ''); ?>" required></div>
            <div class="campo"><label for="unidad">Unidad</label><input type="text" id="unidad" name="unidad" value="<?php echo s($curva->unidad); ?>" maxlength="20" placeholder="m, pza, kg…"></div>
            <div class="campo"><label for="fecha_inicio">Fecha inicio *</label><input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo s($curva->fecha_inicio); ?>" required></div>
            <div class="campo"><label for="fecha_fin">Fecha fin *</label><input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo s($curva->fecha_fin); ?>" required></div>
            <div class="campo">
                <label for="tipo_curva">Tipo de curva</label>
                <select id="tipo_curva" name="tipo_curva">
                    <?php foreach (CP::TIPOS as $k => $t): ?><option value="<?php echo $k; ?>" <?php echo $curva->tipo_curva === $k ? 'selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="text-muted text-sm">Por defecto se usan las fechas del programa de obra (<?php echo formatear_fecha($proyecto->fecha_inicio); ?> → <?php echo formatear_fecha($proyecto->fecha_fin); ?>). Las semanas se recalculan solas al cambiar las fechas; el planeado siempre suma la cantidad total.</p>
        <p class="text-muted text-sm" id="semanas-info"></p>
        <button type="submit" class="btn btn-primary">Guardar curva</button>
    </form>
</div>
<script>
(function () {
    const i = document.getElementById('fecha_inicio'), f = document.getElementById('fecha_fin'), t = document.getElementById('semanas-info');
    const calc = () => {
        if (!i.value || !f.value) return t.textContent = '';
        const d = Math.round((new Date(f.value) - new Date(i.value)) / 864e5) + 1;
        t.textContent = d < 1 ? 'La fecha fin es anterior al inicio.' : 'Semanas de cálculo: ' + Math.max(1, Math.min(90, Math.ceil(d / 7)));
    };
    i.addEventListener('change', calc); f.addEventListener('change', calc); calc();
})();
</script>
