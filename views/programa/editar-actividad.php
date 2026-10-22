<?php if (!es_modal()): ?>
<div class="page-header">
    <h2>Editar Actividad — <?php echo s($proyecto->nombre); ?></h2>
    <div>
        <?php if (is_admin()): ?><a href="/historial-cambios?proyecto=<?php echo s($proyecto->id); ?>&entidad_tipo=actividad&entidad_id=<?php echo s($actividad->id); ?>" target="_top" class="btn btn-ghost" title="Cambios que las herramientas de IA hicieron a esta actividad"><i class="fa-solid fa-clock-rotate-left"></i> Historial</a><?php endif; ?>
        <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($duplicado)): ?>
<div class="modal-overlay abierto" id="modal-codigo-duplicado" data-tono="aviso" aria-hidden="false">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="mcd-titulo">
        <h4 id="mcd-titulo">No se puede renumerar</h4>
        <p>El código <strong class="mono"><?php echo s($duplicado['codigo']); ?></strong> ya existe en este programa, así que no se puede asignar a esta actividad.</p>
        <p>¿Quieres usar el siguiente número disponible de su partida, <strong class="mono"><?php echo s($duplicado['propuesto']); ?></strong>?</p>
        <div class="mf-botones">
            <button type="button" class="btn btn-ghost" id="mcd-cancelar">Cancelar</button>
            <button type="button" class="btn btn-primary" id="mcd-aceptar" data-codigo="<?php echo s($duplicado['propuesto']); ?>">Usar <?php echo s($duplicado['propuesto']); ?></button>
        </div>
    </div>
</div>
<script>
(() => {
    const modal = document.getElementById('modal-codigo-duplicado');
    const campo = document.getElementById('codigo');
    const cerrar = () => { modal.classList.remove('abierto'); modal.setAttribute('aria-hidden', 'true'); campo.focus(); campo.select(); };
    document.getElementById('mcd-cancelar').addEventListener('click', cerrar);
    modal.addEventListener('click', e => { if (e.target === modal) cerrar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrar(); });
    document.getElementById('mcd-aceptar').addEventListener('click', e => {
        campo.value = e.currentTarget.dataset.codigo;
        campo.form.requestSubmit();
    });
})();
</script>
<?php endif; ?>

<div class="card">
    <form method="POST" enctype="multipart/form-data" class="formulario">
        <div class="form-grid">
            <div class="campo">
                <label for="partida_id">Partida *</label>
                <select id="partida_id" name="partida_id" required>
                    <?php foreach ($partidas as $p): ?>
                        <option value="<?php echo s($p->id); ?>" <?php echo $actividad->partida_id == $p->id ? 'selected' : ''; ?>>
                            <?php echo s($p->codigo . ' — ' . $p->nombre); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="codigo">Código *</label>
                <input type="text" id="codigo" name="codigo" value="<?php echo s($actividad->codigo); ?>" required>
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
            </div>
                        <div class="campo campo-full">
                <label class="campo-check"><input type="checkbox" id="hito" name="hito" value="1" <?php echo (int)$actividad->hito === 1 ? 'checked' : ''; ?>> Es un hito (duración 0: empieza y termina el mismo día)</label>
            </div>
<div class="campo">
                <label for="duracion_dias">Duración (días hábiles) *</label>
                <input type="number" id="duracion_dias" name="duracion_dias" value="<?php echo s($actividad->duracion_dias); ?>" min="1" required data-min="1">
                <small class="text-muted">Fecha fin actual: <strong><?php echo formatear_fecha($actividad->fecha_fin); ?></strong> (se recalculará al guardar)</small>
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
                <?php if (!empty($actividad->icono)): ?>
                    <span class="prg-icono-vista"><img src="<?php echo s($actividad->urlIcono()); ?>" alt="Icono actual"></span>
                    <label class="campo-check"><input type="checkbox" name="quitar_icono" value="1"> Quitar el icono actual</label>
                <?php endif; ?>
                <input type="file" id="icono" name="icono" accept="image/png,image/jpeg,image/webp,image/gif">
                <small class="text-muted">Opcional: imagen pequeña circular sobre la barra en la Línea de tiempo. Para cambiarla, elige otra imagen.</small>
            </div>
            <?php include __DIR__ . '/_materiales-actividad.php'; ?>
            <?php $tipoEntidad = 'actividad'; $entidadId = $actividad->id; include __DIR__ . '/../normativa/_seccion.php'; ?>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
<script>
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

    <hr style="margin: 2rem 0; border-color: rgba(255,255,255,0.1);">

    <?php if (es_modal()): ?>
        <p class="text-muted text-sm">Las restricciones se gestionan en la página completa de la actividad.</p>
        <a href="/programa/actividades/editar?id=<?php echo s($actividad->id); ?>" target="_top" class="btn btn-ghost btn-sm"><i class="fa-solid fa-up-right-from-square"></i> Abrir actividad completa (restricciones)</a>
</div>
<?php return; endif; ?>

    <h4>Restricciones de esta Actividad</h4>
    <?php $restricciones = $actividad->getRestricciones(); ?>
    <?php if (empty($restricciones)): ?>
        <p class="text-muted">No hay restricciones registradas.</p>
    <?php else: ?>
        <table class="tabla">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($restricciones as $r): ?>
                    <tr>
                        <td><span class="badge" style="background: <?php echo s($r->getTipo()->color); ?>22; color: <?php echo s($r->getTipo()->color); ?>"><?php echo s($r->getTipo()->nombre); ?></span></td>
                        <td class="mono"><?php echo s($r->codigo); ?></td>
                        <td><?php echo s($r->descripcion); ?></td>
                        <td class="mono"><?php echo formatear_fecha($r->fecha); ?></td>
                        <td><span class="badge-status status-<?php echo s($r->estado); ?>"><?php echo s(ucfirst($r->estado)); ?></span></td>
                        <td class="acciones">
                            <?php if ($r->estado === 'activa'): ?>
                                <form method="POST" action="/programa/restricciones/resolver" style="display:inline">
                                    <input type="hidden" name="id" value="<?php echo s($r->id); ?>">
                                    <button type="submit" class="btn btn-xs btn-primary">Resolver</button>
                                </form>
                            <?php endif; ?>
                            <a href="/programa/restricciones/editar?id=<?php echo s($r->id); ?>" class="btn btn-xs btn-secondary">Editar</a>
                            <form method="POST" action="/programa/restricciones/eliminar" style="display:inline" data-confirm="¿Eliminar restricción?|La restricción se quitará de la actividad. Esta acción no se puede deshacer.">
                                <input type="hidden" name="id" value="<?php echo s($r->id); ?>">
                                <button type="submit" class="btn btn-xs btn-danger">✕</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <a href="/programa/restricciones/crear?actividad=<?php echo s($actividad->id); ?>" class="btn btn-secondary" style="margin-top: 1rem;">+ Agregar Restricción</a>
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
