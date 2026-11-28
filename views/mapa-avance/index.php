<?php // Variables: $proyecto, $calc, $actividades, $historial, $mapeos, $puede, $mensaje
$pid = (int)$proyecto->id;
$aj = $calc['ajustes'];
$map = [];
foreach ($mapeos as $m) $map[$m['lamina_id'] . '|' . $m['capa_id']] = (int)$m['actividad_id'];
$nomAct = fn($id) => isset($actividades[$id]) ? $actividades[$id]['codigo'] . ' ' . mb_substr($actividades[$id]['nombre'], 0, 60) : '#' . $id;
$nLaminas = count($calc['laminas']);
$nMarcas = array_sum(array_column($calc['laminas'], 'marcas'));
$nActs = count($calc['actividades']);
$sinAsignar = count(array_filter($calc['laminas'], fn($l) => !empty($l['sin_asignar'])));
$diferencias = [];
foreach ($calc['actividades'] as $id => $x) {
    if (isset($actividades[$id]) && $x['pct'] !== null) $diferencias[] = $x['pct'] - (int)$actividades[$id]['progreso'];
}
$difMedia = $diferencias ? round(array_sum($diferencias) / count($diferencias), 1) : null;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-map-location-dot"></i> <?php echo s(t('Mapa de avance')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · el <strong>color</strong> de las marcas de las láminas se traduce a avance: <span class="mapa-sw rojo"></span> rojo = no avanzado, <span class="mapa-sw amarillo"></span> amarillo = a medias, <span class="mapa-sw verde"></span> verde = terminado. Las líneas pesan por su longitud y las figuras cerradas por cantidad.</p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-sheet-plastic"></i><b><?php echo $nLaminas; ?></b> láminas</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-palette"></i><b><?php echo $nMarcas; ?></b> marcas con color</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-list-check"></i><b><?php echo $nActs; ?></b> actividades calculadas</span>
        </div>
    </div>
    <div class="inc-acciones"><a href="/vplan?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-sheet-plastic"></i> Láminas</a></div>
</div>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>" role="status"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-sheet-plastic" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Láminas</small><strong><?php echo $nLaminas; ?></strong><span><?php echo $nMarcas; ?> marcas con color</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Actividades calculadas</small><strong><?php echo $nActs; ?></strong><span>con avance derivado del plano</span></span>
    </div>
    <div class="suite-kpi <?php echo $sinAsignar ? 'is-warning' : 'is-success'; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-link-slash" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Láminas sin asignar</small><strong><?php echo $sinAsignar; ?></strong><span>con color pero sin actividad ligada</span></span>
    </div>
    <div class="suite-kpi <?php echo $difMedia === null ? '' : ($difMedia < 0 ? 'is-danger' : 'is-success'); ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Diferencia media</small><strong><?php echo $difMedia === null ? '—' : ($difMedia > 0 ? '+' : '') . $difMedia . ' pts'; ?></strong><span>planos vs avance del programa</span></span>
    </div>
</div>

<?php if ($puede): ?>
<div class="suite-panel">
    <div class="suite-panel-cab"><div><h3><i class="fa-solid fa-palette"></i> Cómo se traduce el color</h3><p class="text-muted text-sm">Ajusta cuánto vale cada color y calcula propuestas de avance.</p></div></div>
    <div class="suite-panel-cuerpo">
    <form method="post" action="/mapa-avance/ajustes" class="inc-filtros">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label><span class="mapa-sw rojo"></span> Rojo vale <input type="number" name="rojo" min="0" max="100" value="<?php echo (int)$aj['rojo']; ?>" style="width:7rem"> %</label>
        <label><span class="mapa-sw amarillo"></span> Amarillo vale <input type="number" name="amarillo" min="0" max="100" value="<?php echo (int)$aj['amarillo']; ?>" style="width:7rem"> %</label>
        <label><span class="mapa-sw verde"></span> Verde vale <input type="number" name="verde" min="0" max="100" value="<?php echo (int)$aj['verde']; ?>" style="width:7rem"> %</label>
        <button class="btn btn-sm btn-ghost" type="submit">Guardar</button>
    </form>
    <p class="text-muted text-sm">Ejemplo: una lámina con 100 m de línea roja, 200 m amarilla y 300 m verde da (100·0 + 200·50 + 300·100) ÷ 600 = 66.7 %. Cualquier otro color (morado, azul, naranja, gris…) se ignora y no afecta el cálculo. Las marcas se asignan a la actividad vinculada a la lámina o, si prefieres, por <em>capa</em> (abajo).</p>
    <form method="post" action="/mapa-avance/calcular" class="inc-filtros" data-confirmar="Se guardará la foto de hoy y se crearán propuestas de avance. No se cambia nada hasta que aceptes. ¿Continuar?">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label>Proponer cambios cuando la diferencia sea de al menos <input type="number" name="umbral" min="1" max="50" value="5" style="width:6rem"> puntos</label>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-calculator"></i> Calcular y proponer avance</button>
    </form>
    </div>
</div>
<?php endif; ?>

<?php if (!$calc['laminas']): ?>
    <div class="suite-panel"><?php echo estado_vacio('fa-map-location-dot', 'Aún no hay láminas', 'Crea láminas en Vplan, dibuja líneas y círculos sobre el plano y píntalos de rojo, amarillo o verde según el avance de cada zona.', ['texto' => 'Ir a Vplan', 'url' => '/vplan?proyecto=' . $pid, 'icono' => 'fa-sheet-plastic']); ?></div>
<?php else: ?>
    <?php foreach ($calc['laminas'] as $l): ?>
        <div class="suite-panel">
            <div class="suite-panel-cab">
            <div class="prg-cab">
                <h3 class="m-0"><?php echo s($l['titulo']); ?></h3>
                <span class="text-muted"><?php echo (int)$l['marcas']; ?> marcas con color · <span class="mapa-sw rojo"></span><?php echo (int)$l['rojo']; ?> <span class="mapa-sw amarillo"></span><?php echo (int)$l['amarillo']; ?> <span class="mapa-sw verde"></span><?php echo (int)$l['verde']; ?></span>
                <strong class="mapa-pct"><?php echo $l['pct'] !== null ? number_format($l['pct'], 1) . ' %' : 'sin marcas de color'; ?></strong>
                <a class="btn btn-sm btn-ghost" href="/vplan/ver?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$l['id']; ?>">Abrir lámina</a>
            </div>
            </div>
            <div class="suite-panel-cuerpo">
            <?php if ($l['sin_asignar']): ?><p class="prg-aviso"><i class="fa-solid fa-triangle-exclamation"></i> Esta lámina tiene marcas de color pero ninguna actividad asignada: vincula actividades a la lámina o asigna una capa abajo.</p><?php endif; ?>
            <?php if ($l['capas']): ?>
            <div class="prg-tabla-wrap"><table class="prg-tabla">
                <thead><tr><th>Capa</th><th class="num">Rojo</th><th class="num">Amarillo</th><th class="num">Verde</th><th class="num">Avance</th><th>Actividad asignada</th></tr></thead>
                <tbody><?php foreach ($l['capas'] as $c): $sel = $map[$l['id'] . '|' . $c['id']] ?? 0; ?>
                    <tr><td><?php echo s($c['nombre']); ?></td><td class="num"><?php echo (int)$c['rojo']; ?></td><td class="num"><?php echo (int)$c['amarillo']; ?></td><td class="num"><?php echo (int)$c['verde']; ?></td><td class="num"><?php echo $c['pct'] !== null ? number_format($c['pct'], 1) . ' %' : '—'; ?></td>
                        <td><?php if ($puede): ?>
                            <form method="post" action="/mapa-avance/mapear" style="display:flex;gap:.4rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="lamina" value="<?php echo (int)$l['id']; ?>"><input type="hidden" name="capa" value="<?php echo s($c['id']); ?>">
                                <select name="actividad" aria-label="Actividad de la capa" onchange="this.form.submit()"><option value="0">(la(s) de la lámina)</option><?php echo opciones_actividades($actividades, $sel, 50); ?></select></form>
                            <?php else: echo $sel ? s($nomAct($sel)) : '(la(s) de la lámina)'; endif; ?></td></tr>
                <?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($calc['actividades']): ?>
    <div class="suite-panel">
        <div class="suite-panel-cab"><div><h3><i class="fa-solid fa-list-check"></i> Avance por actividad según los planos</h3><p class="text-muted text-sm">Compara el porcentaje derivado de las marcas con el registrado en el programa.</p></div></div>
        <div class="suite-panel-cuerpo">
        <div class="prg-tabla-wrap"><table class="prg-tabla">
            <thead><tr><th>Actividad</th><th class="num">Programa</th><th class="num">Planos</th><th class="num">Diferencia</th><th>Últimos días</th></tr></thead>
            <tbody><?php foreach ($calc['actividades'] as $id => $x): $a = $actividades[$id] ?? null; if (!$a || $x['pct'] === null) continue; $d = round($x['pct'] - (int)$a['progreso'], 1); ?>
                <tr><td><strong><?php echo s($a['codigo']); ?></strong> <?php echo s(mb_substr($a['nombre'], 0, 70)); ?></td><td class="num"><?php echo (int)$a['progreso']; ?> %</td><td class="num"><?php echo number_format($x['pct'], 1); ?> %</td>
                    <td class="num <?php echo $d > 0 ? 'prg-mejor' : ($d < 0 ? 'prg-peor' : ''); ?>"><?php echo $d > 0 ? '+' : ''; ?><?php echo $d; ?></td>
                    <td class="text-sm"><?php echo s(implode(' · ', array_map(fn($f, $v) => date('d/m', strtotime($f)) . ': ' . $v . '%', array_keys($historial[$id] ?? []), $historial[$id] ?? [])) ?: '—'); ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>
