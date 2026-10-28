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
                <?php $tieneSubactividades = !empty($subactividades ?? []); ?>
                <input type="number" id="progreso" name="progreso" value="<?php echo s($actividad->progreso); ?>" min="0" max="100" required
                       <?php echo $tieneSubactividades ? 'readonly' : ''; ?>>
                <small class="text-muted">
                    <?php if ($tieneSubactividades): ?>
                        Se calcula como promedio de <?php echo count($subactividades); ?> subactividad<?php echo count($subactividades) === 1 ? '' : 'es'; ?> (se gestionan al final de la actividad).
                    <?php else: ?>
                        Sin subactividades, este es el avance de la actividad.
                    <?php endif; ?>
                </small>
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
        <p class="text-muted text-sm">Las restricciones y subactividades se gestionan en la página completa de la actividad.</p>
        <a href="/programa/actividades/editar?id=<?php echo s($actividad->id); ?>" target="_top" class="btn btn-ghost btn-sm"><i class="fa-solid fa-up-right-from-square"></i> Abrir actividad completa (restricciones y subactividades)</a>
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

    <hr style="margin: 2rem 0; border-color: rgba(255,255,255,0.1);">

    <?php
    $subEstado = $subEstado ?? '';
    $subError = $subError ?? '';
    $totalSub = count($subactividades ?? []);
    $promedioSub = $totalSub ? \Model\Subactividad::promedio(array_map(fn($x) => (int)$x->status, $subactividades)) : 0;
    $hojaSub = $hojaSubactividades ?? null;
    $hojaVersion = $hojaSub ? (string)$hojaSub->actualizado : '';
    ?>
    <h4 id="subactividades">Subactividades</h4>
    <p class="text-muted text-sm">
        Divide la actividad en partes con su propio avance. Si hay subactividades, el <strong>Progreso (%)</strong> de la actividad es su promedio y no se puede escribir a mano.
        Las subactividades no aparecen en el programa, dependencias, línea de tiempo ni Lookahead.
    </p>
    <?php if ($subEstado === 'creada'): ?><div class="alerta alerta-exito">Subactividad agregada.</div><?php endif; ?>
    <?php if ($subEstado === 'editada'): ?><div class="alerta alerta-exito">Subactividades guardadas y avance de la actividad recalculado.</div><?php endif; ?>
    <?php if ($subEstado === 'eliminada'): ?><div class="alerta alerta-exito">Subactividad eliminada.</div><?php endif; ?>
    <?php if ($subEstado === 'desvinculada'): ?><div class="alerta alerta-exito">Tabla desvinculada. Se conserva como una tabla normal de SheetFlow.</div><?php endif; ?>
    <?php if ($subError !== ''): ?><div class="alerta alerta-error"><?php echo s($subError); ?></div><?php endif; ?>

    <div class="sub-sheetflow" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;padding:.9rem 1rem;margin:1rem 0;border:1px solid rgba(255,255,255,.08);border-radius:.6rem;background:rgba(255,255,255,.02);">
        <?php if ($hojaSub): ?>
            <span class="badge" style="background:rgba(0,180,216,.12);color:var(--secondary);border-color:rgba(0,180,216,.3);"><i class="fa-solid fa-table-cells"></i> Vinculada a SheetFlow</span>
            <strong class="text-sm"><?php echo s($hojaSub->nombre); ?></strong>
            <a class="btn btn-secondary btn-sm" href="/sheetflow/abrir?proyecto=<?php echo (int)$proyecto->id; ?>&amp;id=<?php echo (int)$hojaSub->id; ?>"><i class="fa-solid fa-up-right-from-square"></i> Abrir tabla</a>
            <a class="btn btn-ghost btn-sm" href="/vplan/crear?proyecto=<?php echo (int)$proyecto->id; ?>&amp;sheetflow=<?php echo (int)$hojaSub->id; ?>"><i class="fa-solid fa-file-circle-plus"></i> Usar en vPlan</a>
            <form method="POST" action="/programa/subactividades/sheetflow/quitar" style="display:inline" data-confirm="¿Desvincular la tabla?|La tabla se conserva como una tabla normal de SheetFlow, pero dejará de sincronizarse con las subactividades. Las subactividades no se borran.|Desvincular">
                <input type="hidden" name="actividad_id" value="<?php echo s($actividad->id); ?>">
                <input type="hidden" name="hoja_version" value="<?php echo s($hojaVersion); ?>">
                <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-link-slash"></i> Desvincular</button>
            </form>
            <p class="text-muted text-sm" style="flex-basis:100%;margin:.25rem 0 0;">Los cambios de nombre, fechas y avance se sincronizan en los dos sentidos con la tabla. Las columnas extra se quedan en SheetFlow y vPlan. Si eliminas un renglón en la tabla, se elimina su subactividad.</p>
        <?php else: ?>
            <form method="POST" action="/programa/subactividades/sheetflow" style="display:inline">
                <input type="hidden" name="actividad_id" value="<?php echo s($actividad->id); ?>">
                <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-table-cells"></i> Generar tabla en SheetFlow</button>
            </form>
            <span class="text-muted text-sm">Crea una tabla vinculada con una fila por subactividad. Se puede editar en SheetFlow y en vPlan sin que se desincronice.</span>
        <?php endif; ?>
    </div>

    <?php if (!$totalSub): ?>
        <p class="text-muted">Esta actividad no tiene subactividades: su avance se toma del campo Progreso.</p>
    <?php else: ?>
        <p>Avance calculado: <strong><?php echo (int)$promedioSub; ?> %</strong> · promedio de <?php echo (int)$totalSub; ?> subactividad<?php echo $totalSub === 1 ? '' : 'es'; ?>.</p>
        <form method="POST" action="/programa/subactividades/editar">
            <input type="hidden" name="actividad_id" value="<?php echo s($actividad->id); ?>">
            <?php if ($hojaSub): ?><input type="hidden" name="hoja_version" value="<?php echo s($hojaVersion); ?>"><?php endif; ?>
            <div class="tabla-wrapper">
                <table class="tabla tabla-subactividades">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Avance</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subactividades as $sub): $sid = (int)$sub->id; ?>
                            <tr>
                                <td><input type="text" name="sub[<?php echo $sid; ?>][nombre]" value="<?php echo s($sub->nombre); ?>" maxlength="200" required aria-label="Nombre de la subactividad"></td>
                                <td><input type="date" name="sub[<?php echo $sid; ?>][fecha_inicio]" value="<?php echo s($sub->fecha_inicio); ?>" required aria-label="Fecha de inicio"></td>
                                <td><input type="date" name="sub[<?php echo $sid; ?>][fecha_fin]" value="<?php echo s($sub->fecha_fin); ?>" required aria-label="Fecha de fin"></td>
                                <td class="mono"><input type="number" name="sub[<?php echo $sid; ?>][status]" value="<?php echo (int)$sub->status; ?>" min="0" max="100" step="1" required aria-label="Avance (%)" style="width:7rem"> %</td>
                                <td class="acciones">
                                    <button type="submit" form="sub-del-<?php echo $sid; ?>" class="btn btn-xs btn-danger" title="Eliminar subactividad">✕</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Guardar subactividades</button>
        </form>
        <?php foreach ($subactividades as $sub): ?>
            <form method="POST" action="/programa/subactividades/eliminar" id="sub-del-<?php echo (int)$sub->id; ?>" style="display:inline" data-confirm="¿Eliminar subactividad?|Se quitará «<?php echo s($sub->nombre); ?>» y el avance de la actividad se recalculará. Esta acción no se puede deshacer.|Eliminar">
                <input type="hidden" name="id" value="<?php echo s($sub->id); ?>">
                <?php if ($hojaSub): ?><input type="hidden" name="hoja_version" value="<?php echo s($hojaVersion); ?>"><?php endif; ?>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>

    <h5 style="margin-top: 1.5rem;">Agregar subactividad</h5>
    <form method="POST" action="/programa/subactividades/crear" class="form-grid formulario-subactividad">
        <input type="hidden" name="actividad_id" value="<?php echo s($actividad->id); ?>">
        <?php if ($hojaSub): ?><input type="hidden" name="hoja_version" value="<?php echo s($hojaVersion); ?>"><?php endif; ?>
        <div class="campo campo-full">
            <label for="sub-nombre">Nombre *</label>
            <input type="text" id="sub-nombre" name="nombre" maxlength="200" required placeholder="Ej.: Instalación de tubería en el eje A">
        </div>
        <div class="campo">
            <label for="sub-inicio">Fecha de inicio *</label>
            <input type="date" id="sub-inicio" name="fecha_inicio" value="<?php echo s($actividad->fecha_inicio); ?>" required>
        </div>
        <div class="campo">
            <label for="sub-fin">Fecha de fin *</label>
            <input type="date" id="sub-fin" name="fecha_fin" value="<?php echo s($actividad->fecha_fin ?: $actividad->fecha_inicio); ?>" required>
        </div>
        <div class="campo">
            <label for="sub-status">Avance (%) *</label>
            <input type="number" id="sub-status" name="status" value="0" min="0" max="100" step="1" required>
        </div>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-secondary">+ Agregar subactividad</button>
        </div>
    </form>
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
