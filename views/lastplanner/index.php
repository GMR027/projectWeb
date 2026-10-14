<div class="page-header no-print">
    <div>
        <h2><i class="fa-solid fa-note-sticky"></i> Last Planner</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($tarjetas); ?> post-it(s)</p>
    </div>
</div>
<?php if (!$partida && empty($todas)): ?>
    <div class="card"><p class="text-muted">Este proyecto aún no tiene partidas en el programa de obra.</p></div>
<?php else: ?>
<form method="GET" class="card lp-filtros">
    <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
    <div class="campo"><label for="partida">Partida</label>
        <select id="partida" name="partida" onchange="this.form.submit()">
            <option value="todas" <?php echo !empty($todas) ? 'selected' : ''; ?>>★ Mostrar todas (todas las partidas)</option>
            <?php foreach ($partidas as $p): ?><option value="<?php echo s($p->id); ?>" <?php echo ($partida && (int)$p->id === (int)$partida->id) ? 'selected' : ''; ?>><?php echo s($p->codigo . ' · ' . $p->nombre); ?></option><?php endforeach; ?>
        </select></div>
</form>
<p class="text-muted text-sm lp-leyenda"><i class="lp-rombo lp-verde">◆</i> Actividad finalizada &nbsp; <i class="lp-rombo lp-amarillo">◆</i> Actividad en proceso &nbsp; <i class="lp-rombo lp-rojo">◆</i> Actividad con restricción activa &nbsp; · Los post-it se calculan con el programa de obra: si cambian las fechas, se ajustan solos. Para imprimirlos usa <a href="/exportar?proyecto=<?php echo s($proyecto->id); ?>">Exportar</a>.</p>

<?php if (!$tarjetas): ?>
    <div class="card"><p class="text-muted">No hay actividades en este proyecto.</p></div>
<?php else: ?>
    <div class="lp-grid">
        <?php $logo = $proyecto->logo ?: $proyecto->logoEmpresa(); foreach ($tarjetas as $t) include __DIR__ . '/tarjeta.php'; ?>
    </div>
<?php endif; ?>
<?php endif; ?>
