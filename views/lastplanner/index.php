<div class="page-header no-print">
    <div>
        <h2><i class="fa-solid fa-note-sticky"></i> Last Planner</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($tarjetas); ?> post-it(s)</p>
    </div>
</div>
<?php if (!$partida && empty($todas)): ?>
    <div class="card"><p class="text-muted">Este proyecto aún no tiene partidas en el programa de obra.</p></div>
<?php else: ?>
<?php if (puede_gestionar_proyecto($proyecto)): ?>
<form method="POST" action="/lastplanner/plantilla" class="card lp-filtros">
    <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
    <div class="campo"><label for="lp-plantilla">Plantilla del post-it</label>
        <select id="lp-plantilla" name="estampa" onchange="this.form.submit()">
            <option value="0" <?php echo !$plantilla ? 'selected' : ''; ?>>Clásica (7 × 7 cm)</option>
            <?php foreach ($estampasLienzo as $e): ?><option value="<?php echo (int)$e->id; ?>" <?php echo ($plantilla && (int)$plantilla->id === (int)$e->id) ? 'selected' : ''; ?>>Estampa · <?php echo s($e->nombre); ?></option><?php endforeach; ?>
        </select></div>
    <div class="campo"><a class="btn btn-ghost btn-sm" href="/simbolos?proyecto=<?php echo s($proyecto->id); ?><?php echo $plantilla ? '&editar=' . (int)$plantilla->id : ''; ?>#estampas"><i class="fa-solid fa-pen-ruler"></i> <?php echo $plantilla ? 'Editar la plantilla en el editor de estampas' : 'Crear o cargar una plantilla en el editor de estampas'; ?></a></div>
    <p class="text-muted text-sm">La plantilla elegida reemplaza al post-it clásico en pantalla y en el PDF. No se modifica aquí: todos los cambios se hacen en el editor de estampas.</p>
</form>
<?php endif; ?>
<form method="GET" class="card lp-filtros">
    <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
    <div class="campo"><label for="partida">Partida</label>
        <select id="partida" name="partida" onchange="this.form.submit()">
            <option value="todas" <?php echo !empty($todas) ? 'selected' : ''; ?>>★ Mostrar todas (todas las partidas)</option>
            <?php foreach ($partidas as $p): ?><option value="<?php echo s($p->id); ?>" <?php echo ($partida && (int)$p->id === (int)$partida->id) ? 'selected' : ''; ?>><?php echo s($p->codigo . ' · ' . $p->nombre); ?></option><?php endforeach; ?>
        </select></div>
    <div class="campo"><label for="restriccion">Filtro</label>
        <select id="restriccion" name="restriccion" onchange="this.form.submit()">
            <option value="">Todos los post-it</option>
            <option value="1" <?php echo !empty($soloRestriccion) ? 'selected' : ''; ?>>Solo con restricción</option>
        </select></div>
    <div class="campo"><label for="lp-zoom">Tamaño <span id="lp-zoom-val">100%</span></label>
        <input type="range" id="lp-zoom" min="30" max="150" step="5" value="100"></div>
</form>
<p class="text-muted text-sm lp-leyenda"><i class="lp-rombo lp-verde">◆</i> Actividad finalizada &nbsp; <i class="lp-rombo lp-amarillo">◆</i> Actividad en proceso &nbsp; <i class="lp-rombo lp-rojo">◆</i> Actividad con restricción activa &nbsp; · Los post-it se calculan con el programa de obra: si cambian las fechas, se ajustan solos. Para imprimirlos usa <a href="/exportar?proyecto=<?php echo s($proyecto->id); ?>">Exportar</a>.</p>

<?php if (!$tarjetas): ?>
    <div class="card"><p class="text-muted">No hay actividades en este proyecto.</p></div>
<?php else: ?>
    <div class="lp-grid">
        <?php if ($plantilla): $pc = $plantilla->config(); [$Wu, $Hu] = \Classes\Estampas::dimensiones($pc); $K = 2.2;
            foreach ($tarjetas as $t): ?>
            <div class="lp-tarjeta-est" style="width:<?php echo round($Wu * $K); ?>px;height:<?php echo round($Hu * $K); ?>px">
                <?php echo \Classes\Estampas::renderGuardada($plantilla, $proyecto, null, ['corte' => $t['fecha'], 'partida' => $t['partida'] ? (int)$t['partida']->id : 0, 'max' => \Classes\LastPlanner::POR_TARJETA, 'offset' => ($t['parte'] - 1) * \Classes\LastPlanner::POR_TARJETA, 'lamina_n' => 1, 'lamina_total' => 1], 'px', $K); ?>
            </div>
        <?php endforeach; else: $logo = $proyecto->logo ?: $proyecto->logoEmpresa(); foreach ($tarjetas as $t) include __DIR__ . '/tarjeta.php'; endif; ?>
    </div>
<?php endif; ?>
<script>
(function () {
    var r = document.getElementById('lp-zoom'), g = document.querySelector('.lp-grid'), v = document.getElementById('lp-zoom-val');
    if (!r) return;
    try { var s = localStorage.getItem('lp-zoom'); if (s) r.value = s; } catch (e) {}
    function aplicar() {
        if (g) g.style.zoom = r.value / 100;
        v.textContent = r.value + '%';
        try { localStorage.setItem('lp-zoom', r.value); } catch (e) {}
    }
    r.addEventListener('input', aplicar); aplicar();
})();
</script>
<?php endif; ?>
