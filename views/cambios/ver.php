<?php
$pid = s($proyecto->id);
$msg = $_SESSION['cambios_msg'] ?? null; unset($_SESSION['cambios_msg']);
$etqAct = fn($id) => $id && isset($actividades[(int)$id]) ? $actividades[(int)$id]['codigo'] . ' · ' . $actividades[(int)$id]['nombre'] : '';
$matNombres = array_column(array_map(fn($m) => ['id' => $m->id, 'n' => $m->nombre], $materiales), 'n', 'id');
?>
<div class="page-header">
    <div>
        <h2><span class="mono"><?php echo s($orden->numero); ?></span> · <?php echo s($orden->titulo); ?></h2>
        <p class="text-muted"><?php echo formatear_fecha($orden->fecha); ?><?php echo $orden->solicitante ? ' · ' . s($orden->solicitante) : ''; ?>
            <?php if ((float)$orden->impacto_costo || (int)$orden->impacto_dias): ?> · Impacto: <?php echo (float)$orden->impacto_costo ? '$' . number_format((float)$orden->impacto_costo, 2) : ''; ?> <?php echo (int)$orden->impacto_dias ? (int)$orden->impacto_dias . ' día(s)' : ''; ?><?php endif; ?></p>
    </div>
    <div class="cb-acciones">
        <?php if ($editar): ?><a href="/cambios/editar?proyecto=<?php echo $pid; ?>&id=<?php echo s($orden->id); ?>" class="btn btn-ghost">Editar</a><?php endif; ?>
        <a href="/cambios?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Volver</a>
    </div>
</div>
<?php if ($msg): ?><div class="alerta <?php echo $msg['ok'] ? 'exito' : 'error'; ?>"><?php echo s($msg['texto']); ?></div><?php endif; ?>

<div class="card">
    <div class="cb-flujo" role="group" aria-label="Estado de la orden" data-id="<?php echo s($orden->id); ?>">
        <?php foreach (\Model\OrdenCambio::ESTADOS as $k => $etq): ?>
            <button type="button" class="cb-paso cb-oc-<?php echo $k; ?><?php echo $orden->estado === $k ? ' activo' : ''; ?>" data-estado="<?php echo $k; ?>"<?php echo $editar ? '' : ' disabled'; ?>><?php echo s($etq); ?></button>
        <?php endforeach; ?>
    </div>
    <?php if (trim((string)$orden->descripcion) !== ''): ?><p class="cb-desc"><?php echo nl2br(s($orden->descripcion)); ?></p><?php endif; ?>
    <?php if ($orden->actividad_id): ?><p class="text-muted"><i class="fa-solid fa-link"></i> Actividad: <?php echo s($etqAct($orden->actividad_id)); ?></p><?php endif; ?>
</div>

<div class="card">
    <h3>Renglones del cambio</h3>
    <?php if (empty($partidas)): ?>
        <p class="text-muted">Aún no hay renglones. Agrega lo que se instala, retira, reubica, desmonta o construye.</p>
    <?php else: ?>
        <div class="cb-lista">
        <?php foreach ($partidas as $c):
            $permitidos = \Model\CambioPartida::estadosDe($c->tipo, (bool)$c->requiere_retiro); ?>
            <article class="cb-renglon cb-r-<?php echo s($c->estado); ?>">
                <header>
                    <span class="cb-tipo"><?php echo s(\Model\CambioPartida::TIPOS[$c->tipo] ?? $c->tipo); ?></span>
                    <?php if ($c->retiroPendiente()): ?><span class="cb-tag cb-tag-alta">retiro pendiente</span><?php endif; ?>
                </header>
                <h4><?php echo s($c->descripcion); ?><?php echo $c->cantidad !== null ? ' <span class="mono text-muted">(' . s(rtrim(rtrim(number_format((float)$c->cantidad, 2, '.', ''), '0'), '.') . ' ' . $c->unidad) . ')</span>' : ''; ?></h4>
                <p class="text-muted">
                    <?php if ($c->ubicacion_original || $c->ubicacion_nueva): ?><i class="fa-solid fa-location-dot"></i> <?php echo s($c->ubicacion_original ?: '—'); ?><?php echo $c->ubicacion_nueva ? ' → ' . s($c->ubicacion_nueva) : ''; ?> · <?php endif; ?>
                    <?php if ($c->fecha_prevista): ?>Previsto <?php echo formatear_fecha($c->fecha_prevista); ?> · <?php endif; ?>
                    <?php if ($c->fecha_ejecucion): ?>Ejecutado <?php echo formatear_fecha($c->fecha_ejecucion); ?> · <?php endif; ?>
                    <?php if ($c->requiere_retiro): ?>Retiro <?php echo $c->fecha_retiro_real ? 'realizado ' . formatear_fecha($c->fecha_retiro_real) : ($c->fecha_retiro_prevista ? 'previsto ' . formatear_fecha($c->fecha_retiro_prevista) : 'sin programar'); ?> · <?php endif; ?>
                    <?php if ($c->actividad_id): ?><i class="fa-solid fa-link"></i> <?php echo s($etqAct($c->actividad_id)); ?> · <?php endif; ?>
                    <?php if ($c->material_id): ?><i class="fa-solid fa-cube"></i> <?php echo s($matNombres[$c->material_id] ?? ''); ?><?php endif; ?>
                </p>
                <?php if ($c->notas): ?><p class="text-muted"><?php echo s($c->notas); ?></p><?php endif; ?>
                <footer>
                    <div class="cb-pasos">
                        <?php foreach ($permitidos as $est):
                            $etq = $est === 'ejecutado' ? \Model\CambioPartida::etiquetaEjecutado($c->tipo) : \Model\CambioPartida::ESTADOS[$est]; ?>
                            <button type="button" class="cb-paso<?php echo $c->estado === $est ? ' activo' : ''; ?><?php echo $c->estado === $est || !$editar ? '' : ' cb-estado'; ?>" data-id="<?php echo s($c->id); ?>" data-estado="<?php echo $est; ?>" data-actualizado="<?php echo s($c->actualizado); ?>" data-etiqueta="<?php echo s($etq . ': ' . $c->descripcion); ?>"<?php echo $editar ? '' : ' disabled'; ?>><?php echo s($etq); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($editar): ?>
                    <div class="cb-herr">
                        <button type="button" class="btn btn-sm btn-ghost" data-editar="<?php echo s($c->id); ?>">Editar</button>
                        <form method="POST" action="/cambios/partida/eliminar" style="display:inline" data-confirm="¿Eliminar el renglón?|No se puede deshacer.">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo s($c->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">✕</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </footer>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($editar): ?>
<div class="card" id="cb-form-partida">
    <h3 id="cb-form-titulo">Agregar renglón</h3>
    <form method="POST" action="/cambios/partida" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <input type="hidden" name="orden_id" value="<?php echo s($orden->id); ?>">
        <input type="hidden" name="id" value="">
        <div class="form-grid">
            <div class="campo"><label for="p-tipo">Tipo *</label>
                <select id="p-tipo" name="tipo"><?php foreach (\Model\CambioPartida::TIPOS as $k => $etq): ?><option value="<?php echo $k; ?>"><?php echo s($etq); ?></option><?php endforeach; ?></select></div>
            <div class="campo campo-full"><label for="p-descripcion">Descripción *</label><input type="text" id="p-descripcion" name="descripcion" required placeholder="Ej.: Cableado UTP provisional hacia caseta"></div>
            <div class="campo"><label for="p-orig">Ubicación original</label><input type="text" id="p-orig" name="ubicacion_original"></div>
            <div class="campo"><label for="p-nueva">Ubicación nueva</label><input type="text" id="p-nueva" name="ubicacion_nueva"></div>
            <div class="campo"><label for="p-cant">Cantidad</label><input type="number" step="0.01" id="p-cant" name="cantidad"></div>
            <div class="campo"><label for="p-unidad">Unidad</label><input type="text" id="p-unidad" name="unidad" placeholder="m, pza, m²…"></div>
            <div class="campo"><label for="p-prev">Fecha prevista</label><input type="date" id="p-prev" name="fecha_prevista"></div>
            <div class="campo"><label for="p-retiro">Retiro previsto</label><input type="date" id="p-retiro" name="fecha_retiro_prevista"><small class="text-muted">Solo si requiere retiro</small></div>
            <div class="campo campo-check"><label><input type="checkbox" name="requiere_retiro" value="1" id="p-req"> Requiere retiro posterior (siempre activo en instalación temporal)</label></div>
            <div class="campo"><label for="p-act">Actividad afectada</label>
                <select id="p-act" name="actividad_id"><option value="">— Ninguna —</option><?php echo opciones_actividades($actividades); ?></select></div>
            <div class="campo"><label for="p-mat">Material</label>
                <select id="p-mat" name="material_id"><option value="">— Ninguno —</option><?php foreach ($materiales as $m): ?><option value="<?php echo s($m->id); ?>"><?php echo s($m->nombre); ?></option><?php endforeach; ?></select></div>
            <div class="campo campo-full"><label for="p-notas">Notas</label><input type="text" id="p-notas" name="notas"></div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar renglón</button>
        <button type="button" class="btn btn-ghost" id="cb-cancelar" hidden>Cancelar edición</button>
    </form>
</div>
<script>
(() => {
    const datos = <?php echo json_encode(array_map(fn($c) => ['id' => (int)$c->id, 'tipo' => $c->tipo, 'descripcion' => $c->descripcion, 'ubicacion_original' => $c->ubicacion_original, 'ubicacion_nueva' => $c->ubicacion_nueva, 'cantidad' => $c->cantidad, 'unidad' => $c->unidad,
        'fecha_prevista' => $c->fecha_prevista, 'fecha_retiro_prevista' => $c->fecha_retiro_prevista, 'requiere_retiro' => (int)$c->requiere_retiro, 'actividad_id' => $c->actividad_id, 'material_id' => $c->material_id, 'notas' => $c->notas], $partidas), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    const f = document.querySelector('#cb-form-partida form'), titulo = document.getElementById('cb-form-titulo'), cancelar = document.getElementById('cb-cancelar');
    document.querySelectorAll('[data-editar]').forEach(b => b.addEventListener('click', () => {
        const d = datos.find(x => x.id == b.dataset.editar);
        if (!d) return;
        f.id.value = d.id;
        ['tipo', 'descripcion', 'ubicacion_original', 'ubicacion_nueva', 'cantidad', 'unidad', 'fecha_prevista', 'fecha_retiro_prevista', 'actividad_id', 'material_id', 'notas'].forEach(k => { f[k].value = d[k] ?? ''; });
        f.requiere_retiro.checked = !!d.requiere_retiro;
        titulo.textContent = 'Editar renglón'; cancelar.hidden = false;
        f.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));
    cancelar.addEventListener('click', () => { f.reset(); f.id.value = ''; titulo.textContent = 'Agregar renglón'; cancelar.hidden = true; });
})();
</script>
<?php endif; ?>
<script>
document.querySelectorAll('.cb-flujo .cb-paso').forEach(b => b.addEventListener('click', async () => {
    if (b.classList.contains('activo')) return;
    try {
        const r = await fetch('/api/cambios/estado', { method: 'POST', body: new URLSearchParams({ proyecto: new URLSearchParams(location.search).get('proyecto') || '', id: b.parentElement.dataset.id, estado: b.dataset.estado }) });
        if ((await r.json()).success) location.reload();
    } catch (_) { window.avisoModal('Sin conexión', 'El cambio de estado de la orden requiere conexión.'); }
}));
</script>
<?php include __DIR__ . '/_estado-js.php'; ?>
