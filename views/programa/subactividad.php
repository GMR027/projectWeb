<?php
/** @var \Model\Proyecto $proyecto @var \Model\Actividad $actividad @var \Model\Subactividad $subactividad @var array $alertas */
$partida = \Model\Partida::find((int)$actividad->partida_id);
$volver = ($volver ?? 'programa') === 'actividad' ? 'actividad' : 'programa';
$cal = new \Classes\Calendario((int)$proyecto->id, (int)($subactividad->calendario_id ?? 0));
$duracion = $subactividad->duracion($cal);
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-list-tree"></i> <?php echo $subactividad->id ? 'Editar' : 'Nueva'; ?> subactividad</h2>
        <p class="text-muted"><?php echo s($actividad->codigo . ' · ' . $actividad->nombre); ?><?php if ($partida): ?> · <?php echo s($partida->codigo . ' · ' . $partida->nombre); ?><?php endif; ?></p>
    </div>
    <a href="<?php echo $volver === 'actividad' ? '/programa/actividades/editar?id=' . (int)$actividad->id . '#subactividades' : '/programa?proyecto=' . (int)$proyecto->id . '&sub=1'; ?>" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
    <p class="text-muted text-sm">
        El código se genera solo a partir de la actividad (<code><?php echo s($actividad->codigo); ?>.1</code>, <code><?php echo s($actividad->codigo); ?>.2</code>…).
        La duración se calcula con el calendario y no se escribe a mano. El avance de la actividad será el promedio de sus subactividades.
    </p>
    <form method="POST" action="/programa/subactividades/guardar" enctype="multipart/form-data" class="formulario" id="form-subactividad">
        <input type="hidden" name="id" value="<?php echo (int)($subactividad->id ?? 0); ?>">
        <input type="hidden" name="actividad_id" value="<?php echo (int)$actividad->id; ?>">
        <input type="hidden" name="volver" value="<?php echo s($volver); ?>">
        <div class="form-grid">
            <div class="campo campo-full">
                <label for="nombre">Nombre de la subactividad *</label>
                <input type="text" id="nombre" name="nombre" maxlength="200" value="<?php echo s($subactividad->nombre); ?>" required>
            </div>
            <div class="campo campo-full">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="3"><?php echo s($subactividad->descripcion ?? ''); ?></textarea>
            </div>
            <div class="campo">
                <label for="calendario_id">Calendario</label>
                <select id="calendario_id" name="calendario_id">
                    <option value="">General del proyecto</option>
                    <?php foreach (($calendarios ?? []) as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"<?php echo (int)($subactividad->calendario_id ?? 0) === (int)$c['id'] ? ' selected' : ''; ?>><?php echo s($c['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="frente_id">Frente (línea de balance)</label>
                <select id="frente_id" name="frente_id">
                    <option value="">— Sin frente —</option>
                    <?php foreach (($frentes ?? []) as $fr): ?>
                        <option value="<?php echo (int)$fr['id']; ?>"<?php echo (int)($subactividad->frente_id ?? 0) === (int)$fr['id'] ? ' selected' : ''; ?>><?php echo s($fr['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="fecha_inicio">Fecha de inicio *</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo s($subactividad->fecha_inicio); ?>" required>
            </div>
            <div class="campo">
                <label for="fecha_fin">Fecha de fin *</label>
                <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo s($subactividad->fecha_fin); ?>" required>
            </div>
            <div class="campo campo-full">
                <label class="campo-check"><input type="checkbox" id="hito" name="hito" value="1"<?php echo (int)($subactividad->hito ?? 0) === 1 ? ' checked' : ''; ?>> Es un hito (duración 0: empieza y termina el mismo día)</label>
                <small class="text-muted">Duración calculada: <strong><?php echo number_format($duracion, 1); ?> d</strong> con el calendario elegido.</small>
            </div>
            <div class="campo">
                <label for="progreso">Avance (%)</label>
                <input type="number" id="progreso" name="progreso" min="0" max="100" step="1" value="<?php echo (int)$subactividad->progreso; ?>" required>
            </div>
            <div class="campo">
                <label for="prioridad">Prioridad</label>
                <select id="prioridad" name="prioridad">
                    <?php foreach (\Model\Subactividad::PRIORIDADES as $p): ?>
                        <option value="<?php echo s($p); ?>"<?php echo $subactividad->prioridad === $p ? ' selected' : ''; ?>><?php echo s(ucfirst($p)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="responsable">Responsable</label>
                <input type="text" id="responsable" name="responsable" maxlength="150" value="<?php echo s($subactividad->responsable ?? ''); ?>">
            </div>
            <div class="campo">
                <label for="orden">Orden</label>
                <input type="number" id="orden" name="orden" min="0" value="<?php echo (int)($subactividad->orden ?? 0); ?>">
            </div>
            <div class="campo">
                <label for="fecha_inicio_real">Inicio real</label>
                <input type="date" id="fecha_inicio_real" name="fecha_inicio_real" value="<?php echo s($subactividad->fecha_inicio_real ?? ''); ?>">
            </div>
            <div class="campo">
                <label for="fecha_fin_real">Fin real</label>
                <input type="date" id="fecha_fin_real" name="fecha_fin_real" value="<?php echo s($subactividad->fecha_fin_real ?? ''); ?>">
            </div>
            <div class="campo">
                <label for="duracion_restante">Duración restante (días hábiles)</label>
                <input type="number" id="duracion_restante" name="duracion_restante" min="0" step="0.5" value="<?php echo s($subactividad->duracion_restante ?? ''); ?>">
            </div>
            <div class="campo">
                <label for="restriccion_tipo">Restricción de fecha</label>
                <select id="restriccion_tipo" name="restriccion_tipo">
                    <?php foreach (['ninguna' => 'Ninguna', 'no_antes' => 'No antes de', 'no_despues' => 'No después de', 'terminar_antes' => 'Debe terminar antes de'] as $k => $etq): ?>
                        <option value="<?php echo s($k); ?>"<?php echo ($subactividad->restriccion_tipo ?? 'ninguna') === $k ? ' selected' : ''; ?>><?php echo s($etq); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="restriccion_fecha">Fecha de la restricción</label>
                <input type="date" id="restriccion_fecha" name="restriccion_fecha" value="<?php echo s($subactividad->restriccion_fecha ?? ''); ?>">
            </div>
            <div class="campo campo-full">
                <label for="restriccion_motivo">Motivo de la restricción</label>
                <input type="text" id="restriccion_motivo" name="restriccion_motivo" maxlength="500" value="<?php echo s($subactividad->restriccion_motivo ?? ''); ?>">
            </div>
            <div class="campo">
                <label for="rec_inicio">Inicio de recuperación</label>
                <input type="date" id="rec_inicio" name="rec_inicio" value="<?php echo s($subactividad->rec_inicio ?? ''); ?>">
            </div>
            <div class="campo">
                <label for="rec_fin">Fin de recuperación</label>
                <input type="date" id="rec_fin" name="rec_fin" value="<?php echo s($subactividad->rec_fin ?? ''); ?>">
            </div>
            <div class="campo campo-full">
                <label class="campo-check"><input type="checkbox" name="pta" value="1"<?php echo (int)($subactividad->pta ?? 1) === 1 ? ' checked' : ''; ?>> PTA revisada (Lookahead)</label>
                <label class="campo-check"><input type="checkbox" name="sin_material" value="1"<?php echo (int)($subactividad->sin_material ?? 0) === 1 ? ' checked' : ''; ?>> No requiere material</label>
                <label class="campo-check"><input type="checkbox" name="fecha_fija" value="1"<?php echo (int)($subactividad->fecha_fija ?? 0) === 1 ? ' checked' : ''; ?>> Fecha fija (las dependencias no la mueven)</label>
            </div>
            <div class="campo">
                <label for="libre_texto">Campo libre (texto)</label>
                <input type="text" id="libre_texto" name="libre_texto" maxlength="255" value="<?php echo s($subactividad->libre_texto ?? ''); ?>">
            </div>
            <div class="campo">
                <label for="libre_numero">Campo libre (número)</label>
                <input type="number" id="libre_numero" name="libre_numero" step="any" value="<?php echo s($subactividad->libre_numero ?? ''); ?>">
            </div>
            <div class="campo">
                <label for="libre_fecha">Campo libre (fecha)</label>
                <input type="date" id="libre_fecha" name="libre_fecha" value="<?php echo s($subactividad->libre_fecha ?? ''); ?>">
            </div>
            <div class="campo campo-full">
                <label for="icono">Icono de la subactividad</label>
                <?php if (!empty($subactividad->icono)): ?>
                    <p><img src="/build/img/actividades-iconos/<?php echo s(basename($subactividad->icono)); ?>" alt="" style="width:2.4rem;height:2.4rem;border-radius:50%;object-fit:cover"> <label class="campo-check"><input type="checkbox" name="quitar_icono" value="1"> Quitar icono</label></p>
                <?php endif; ?>
                <input type="file" id="icono" name="icono" accept="image/png,image/jpeg,image/webp,image/gif">
                <small class="text-muted">Opcional: imagen pequeña que se muestra en el programa y la línea de tiempo.</small>
            </div>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar subactividad</button>
            <a href="<?php echo $volver === 'actividad' ? '/programa/actividades/editar?id=' . (int)$actividad->id . '#subactividades' : '/programa?proyecto=' . (int)$proyecto->id . '&sub=1'; ?>" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
<script>
(function () {
    const h = document.getElementById('hito'), fin = document.getElementById('fecha_fin'), ini = document.getElementById('fecha_inicio');
    if (!h || !fin || !ini) return;
    const alternar = () => {
        if (h.checked) { fin.value = ini.value; fin.disabled = true; }
        else { fin.disabled = false; }
    };
    h.addEventListener('change', alternar);
    ini.addEventListener('change', () => { if (h.checked) fin.value = ini.value; });
    alternar();
})();
</script>
