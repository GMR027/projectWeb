<?php $esNuevo = !$submittal->id; ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo paquete de submittal' : 'Editar submittal ' . s($submittal->numero_submittal); ?></h2>
    <a href="/submittals?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/submittals/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($submittal->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($submittal->fecha); ?>" required></div>
            <div class="campo"><label for="msr_id">MSR</label>
                <select id="msr_id" name="msr_id">
                    <option value="">— Sin MSR —</option>
                    <?php foreach ($msrs as $m): ?>
                        <option value="<?php echo s($m->id); ?>"<?php echo (int)$submittal->msr_id === (int)$m->id ? ' selected' : ''; ?>><?php echo s($m->nombre); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo"><label for="numero_submittal">Número de submittal *</label><input type="text" id="numero_submittal" name="numero_submittal" value="<?php echo s($submittal->numero_submittal); ?>" required></div>
            <div class="campo"><label for="numero_trabajo">Número de trabajo</label><input type="text" id="numero_trabajo" name="numero_trabajo" value="<?php echo s($submittal->numero_trabajo); ?>"></div>
            <div class="campo"><label>Proyecto</label><input type="text" value="<?php echo s($proyecto->nombre); ?>" disabled></div>
            <div class="campo"><label for="departamento">Departamento</label><input type="text" id="departamento" name="departamento" value="<?php echo s($submittal->departamento); ?>" list="lista-deptos" maxlength="100"><datalist id="lista-deptos"><?php foreach (\Classes\Catalogo::departamentos((int)$proyecto->id) as $d): ?><option value="<?php echo s($d); ?>"><?php endforeach; ?></datalist></div>
            <div class="campo"><label for="para">Para</label><input type="text" id="para" name="para" value="<?php echo s($submittal->para); ?>"></div>
            <div class="campo"><label for="especialidad">Especialidad</label><input type="text" id="especialidad" name="especialidad" value="<?php echo s($submittal->especialidad); ?>" list="lista-esp"><datalist id="lista-esp"><?php foreach (\Model\Especialidad::nombres((int)$proyecto->id) as $e): ?><option value="<?php echo s($e); ?>"><?php endforeach; ?></datalist></div>
            <div class="campo campo-full">
                <label>Materiales del paquete</label>
                <small class="text-muted">Cada material marcado genera su renglón en el MSR. <a href="/materiales/crear?proyecto=<?php echo s($proyecto->id); ?>">Agregar material al catálogo</a></small>
                <?php
                $noAprobados = is_admin() ? ($noAprobados ?? []) : [];
                $items = array_map(fn($m) => ['id' => (int)$m->id, 'titulo' => $m->nombre . (isset($noAprobados[(int)$m->id]) ? ' ⚠ NO APROBADO por normativa: ' . $noAprobados[(int)$m->id]['resumen'] : ''), 'detalle' => trim($m->especialidad . ' ' . $m->referencia)], $materiales);
                $nombreCampo = 'materiales';
                $marcados = $marcados ?? [];
                include __DIR__ . '/../templates/lista-accesos.php';
                ?>
                <?php if ($noAprobados): ?>
                    <div class="norma-aviso" role="alert">
                        <strong><i class="fa-solid fa-triangle-exclamation"></i> Hay materiales no aprobados por las normativas y especificaciones</strong> (marcados con ⚠ en la lista).
                        <label class="campo-check"><input type="checkbox" name="confirmar_no_aprobados" value="1"<?php echo !empty($pedirConfirmacion) ? ' checked' : ''; ?>> Entiendo el riesgo: enviar el paquete aunque incluya materiales no aprobados</label>
                    </div>
                <?php endif; ?>
            </div>
            <div class="campo campo-full">
                <label for="laminas">Láminas del Editor <span class="text-muted">(opcional)</span></label>
                <small class="text-muted">Láminas creadas en <a href="/graficos?proyecto=<?php echo s($proyecto->id); ?>">/graficos</a>. Se exportan en el PDF debajo de la lista de materiales. Usa Ctrl/Cmd + clic para elegir varias.</small>
                <?php if (!$laminasDisponibles): ?>
                    <p class="text-muted">No hay láminas en los gráficos de este proyecto. <a href="/graficos/crear?proyecto=<?php echo s($proyecto->id); ?>">Crear un gráfico</a>.</p>
                <?php else: ?>
                    <?php $laminasMarcadas = array_map('intval', $laminasMarcadas ?? []); ?>
                    <?php if ($categoriasLamina): ?>
                        <div class="campo" style="max-width:36rem">
                            <label for="lamina-categoria">Filtrar por categoría</label>
                            <select id="lamina-categoria">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categoriasLamina as $c): ?>
                                    <option value="<?php echo s($c->id); ?>"><?php echo s($c->nombre); ?></option>
                                <?php endforeach; ?>
                                <option value="0">Sin categoría</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    <select id="laminas" name="laminas[]" multiple size="10" style="width:100%;max-width:70rem">
                        <?php foreach ($laminasDisponibles as $l): ?>
                            <option value="<?php echo s($l->id); ?>" data-categoria="<?php echo s($l->categoria_id ?: '0'); ?>"<?php echo in_array((int)$l->id, $laminasMarcadas, true) ? ' selected' : ''; ?>>
                                <?php echo s($l->grafico_nombre . ' · ' . $l->titulo((int)$l->orden)); ?><?php echo $l->categoria_nombre ? ' · ' . s($l->categoria_nombre) : ''; ?><?php echo !$l->marcas_png ? ' (sin imagen guardada)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="laminas-resumen" class="text-muted"></small>
                <?php endif; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar paquete</button>
    </form>
</div>
<?php if (!empty($laminasDisponibles)): ?>
<script>
(function () {
    const sel = document.getElementById('laminas');
    if (!sel) return;
    const filtro = document.getElementById('lamina-categoria');
    const resumen = document.getElementById('laminas-resumen');
    const opciones = Array.from(sel.options).map(o => ({ value: o.value, texto: o.textContent.replace(/\s+/g, ' ').trim(), categoria: o.dataset.categoria || '0' }));
    let seleccion = new Set(Array.from(sel.selectedOptions).map(o => o.value));

    const pintar = () => {
        const cat = filtro ? filtro.value : '';
        sel.innerHTML = '';
        opciones.forEach(d => {
            // Las ya elegidas siguen visibles aunque el filtro sea de otra categoría
            if (cat && d.categoria !== cat && !seleccion.has(d.value)) return;
            const o = document.createElement('option');
            o.value = d.value;
            o.textContent = d.texto;
            o.dataset.categoria = d.categoria;
            o.selected = seleccion.has(d.value);
            sel.appendChild(o);
        });
        actualizarResumen();
    };
    const actualizarResumen = () => {
        const n = sel.selectedOptions.length;
        if (resumen) resumen.textContent = n ? n + ' lámina(s) seleccionada(s)' : 'Ninguna lámina seleccionada';
    };
    sel.addEventListener('change', () => {
        seleccion = new Set(Array.from(sel.selectedOptions).map(o => o.value));
        actualizarResumen();
    });
    if (filtro) filtro.addEventListener('change', pintar);
    actualizarResumen();
})();
</script>
<?php endif; ?>
