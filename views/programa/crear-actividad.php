<?php if (!es_modal()): ?>
<div class="page-header">
    <h2>Nueva Actividad — <?php echo s($proyecto->nombre); ?></h2>
    <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<?php endif; ?>

<div class="card">
    <?php if (empty($partidas)): ?>
        <div class="alerta alerta-error">Primero debes crear al menos una partida.</div>
        <a href="/programa/partidas/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary" target="_top">Crear Partida</a>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" class="formulario" id="form-actividad">
            <div class="form-grid">
                <div class="campo">
                    <label for="partida_id">Partida *</label>
                    <select id="partida_id" name="partida_id" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($partidas as $p): ?>
                            <option value="<?php echo s($p->id); ?>" <?php echo ($partidaIdSeleccionada == $p->id || ($actividad->partida_id ?? null) == $p->id) ? 'selected' : ''; ?>>
                                <?php echo s($p->codigo . ' — ' . $p->nombre); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="codigo">Código *</label>
                    <input type="text" id="codigo" name="codigo" value="<?php echo s($actividad->codigo); ?>" placeholder="ACT-01.1" required>
                </div>
                <div class="campo campo-full">
                    <label for="nombre">Nombre de la Actividad *</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo s($actividad->nombre); ?>" required>
                </div>
                <div class="campo campo-full">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="3"><?php echo s($actividad->descripcion); ?></textarea>
                </div>
                <div class="campo">
                    <label for="fecha_inicio">Fecha de Inicio *</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo s($actividad->fecha_inicio); ?>" required>
                    <small class="text-muted">Sugerida: día hábil siguiente a la última actividad. Puedes cambiarla.</small>
                </div>
                                <div class="campo campo-full">
                    <label class="campo-check"><input type="checkbox" id="hito" name="hito" value="1" <?php echo (int)$actividad->hito === 1 ? 'checked' : ''; ?>> Es un hito (duración 0: empieza y termina el mismo día)</label>
                </div>
<div class="campo">
                    <label for="duracion_dias">Duración (días hábiles) *</label>
                    <input type="number" id="duracion_dias" name="duracion_dias" value="<?php echo s($actividad->duracion_dias); ?>" min="1" required data-min="1">
                    <small class="text-muted">La fecha de fin se calculará automáticamente con el calendario del proyecto.</small>
                </div>
                <div class="campo">
                    <label for="progreso">Progreso (%) *</label>
                    <input type="number" id="progreso" name="progreso" value="<?php echo s($actividad->progreso); ?>" min="0" max="100" required>
                </div>
                <div class="campo">
                    <label for="prioridad">Prioridad</label>
                    <select id="prioridad" name="prioridad">
                        <option value="baja" <?php echo $actividad->prioridad === 'baja' ? 'selected' : ''; ?>>Baja</option>
                        <option value="media" <?php echo $actividad->prioridad === 'media' ? 'selected' : ''; ?>>Media</option>
                        <option value="alta" <?php echo $actividad->prioridad === 'alta' ? 'selected' : ''; ?>>Alta</option>
                        <option value="critica" <?php echo $actividad->prioridad === 'critica' ? 'selected' : ''; ?>>Crítica</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="orden">Orden</label>
                    <input type="number" id="orden" name="orden" value="<?php echo s($actividad->orden); ?>" min="0">
                </div>
                <div class="campo">
                    <label for="icono">Icono de la actividad</label>
                    <input type="file" id="icono" name="icono" accept="image/png,image/jpeg,image/webp,image/gif">
                    <small class="text-muted">Opcional: imagen pequeña que se muestra en forma circular sobre la barra de la actividad en la Línea de tiempo activa (y en el programa si activas la columna «Icono»).</small>
                </div>
                <?php include __DIR__ . '/_materiales-actividad.php'; ?>
            <?php $tipoEntidad = 'actividad'; $entidadId = null; include __DIR__ . '/../normativa/_seccion.php'; ?>
            </div>
            <button type="submit" class="btn btn-primary">Crear Actividad</button>
        </form>
<script>
(function () {
    // Al cambiar de partida se sugiere su fecha, salvo que el usuario ya haya tocado la fecha
    const sugeridas = <?php echo json_encode($sugeridas ?? [], JSON_FORCE_OBJECT); ?>;
    const sel = document.getElementById('partida_id'), fi = document.getElementById('fecha_inicio');
    let tocada = false;
    fi?.addEventListener('input', () => { tocada = true; });
    sel?.addEventListener('change', () => { if (!tocada && sugeridas[sel.value]) fi.value = sugeridas[sel.value]; });
})();
(function () {
    const h = document.getElementById('hito'), d = document.getElementById('duracion_dias');
    if (!h || !d) return;
    const alternar = () => {
        d.disabled = h.checked;
        d.required = !h.checked;
        if (h.checked) d.value = 0; else if (+d.value < 1) d.value = 1;
    };
    h.addEventListener('change', alternar); alternar();
})();
</script>
    <?php endif; ?>
</div>

<?php if (puede_gestionar_proyecto($proyecto)): ?>
<script>
    (function () {
        var nombre = document.getElementById('nombre'), dur = document.getElementById('duracion_dias'); if (!nombre || !dur) return;
        var aviso = document.createElement('small'); aviso.className = 'text-muted'; aviso.id = 'dur-sugerida'; aviso.style.display = 'block'; dur.parentNode.appendChild(aviso);
        var t = null, excluir = <?php echo (int)($actividad->id ?? 0); ?>;
        function consulta() {
            if (nombre.value.trim().length < 6) { aviso.textContent = ''; return; }
            fetch('/api/duracion-sugerida?proyecto=<?php echo (int)$proyecto->id; ?>&excluir=' + excluir + '&nombre=' + encodeURIComponent(nombre.value), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
                var s = j.sugerencia; aviso.replaceChildren();
                if (!s) { aviso.textContent = ''; return; }
                aviso.append('Actividades parecidas duran entre ' + s.min + ' y ' + s.max + ' d (mediana ' + s.p50 + ' d, ' + s.n + ' comparables). ');
                var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-xs btn-ghost'; b.textContent = 'Usar ' + s.p50 + ' d'; b.addEventListener('click', function () { dur.value = s.p50; dur.dispatchEvent(new Event('input', { bubbles: true })); dur.dispatchEvent(new Event('change', { bubbles: true })); }); aviso.append(b);
                if (s.ejemplos.length) aviso.append(' Ej.: ' + s.ejemplos.join('; '));
            }).catch(function () {});
        }
        nombre.addEventListener('input', function () { clearTimeout(t); t = setTimeout(consulta, 600); }); consulta();
    })();
</script>
<?php endif; ?>
