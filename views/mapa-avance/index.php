<?php // Variables: $proyecto, $calc, $actividades, $historial, $mapeos, $puede, $mensaje
$pid = (int)$proyecto->id;
$aj = $calc['ajustes'];
$map = [];
foreach ($mapeos as $m) $map[$m['lamina_id'] . '|' . $m['capa_id']] = (int)$m['actividad_id'];
$nomAct = fn($id) => isset($actividades[$id]) ? $actividades[$id]['codigo'] . ' ' . mb_substr($actividades[$id]['nombre'], 0, 60) : '#' . $id;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-map-location-dot"></i> <?php echo s(t('Mapa de avance')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · el <strong>color</strong> de las marcas que dibujas en las láminas (líneas, círculos, muros, columnas…) se traduce a avance: <span class="mapa-sw rojo"></span> rojo = no avanzado, <span class="mapa-sw amarillo"></span> amarillo = a medias, <span class="mapa-sw verde"></span> verde = terminado. Las líneas pesan por su longitud y las figuras cerradas por cantidad.</p>
    </div>
    <div class="inc-acciones"><a href="/vplan?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-sheet-plastic"></i> Láminas</a></div>
</div>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<?php if ($puede): ?>
<div class="card prg-seccion">
    <h3>Cómo se traduce el color</h3>
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
<?php endif; ?>

<?php if (!$calc['laminas']): ?>
    <div class="card prg-vacio"><h3>Aún no hay láminas</h3><p class="text-muted">Crea láminas en vPlan, dibuja líneas y círculos sobre el plano y píntalos de rojo, amarillo o verde según el avance de cada zona.</p></div>
<?php else: ?>
    <?php foreach ($calc['laminas'] as $l): ?>
        <div class="card prg-seccion">
            <div class="prg-cab">
                <h3 style="margin:0"><?php echo s($l['titulo']); ?></h3>
                <span class="text-muted"><?php echo (int)$l['marcas']; ?> marcas con color · <span class="mapa-sw rojo"></span><?php echo (int)$l['rojo']; ?> <span class="mapa-sw amarillo"></span><?php echo (int)$l['amarillo']; ?> <span class="mapa-sw verde"></span><?php echo (int)$l['verde']; ?></span>
                <strong class="mapa-pct"><?php echo $l['pct'] !== null ? number_format($l['pct'], 1) . ' %' : 'sin marcas de color'; ?></strong>
                <a class="btn btn-sm btn-ghost" href="/vplan/ver?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$l['id']; ?>">Abrir lámina</a>
            </div>
            <?php if ($l['sin_asignar']): ?><p class="prg-aviso"><i class="fa-solid fa-triangle-exclamation"></i> Esta lámina tiene marcas de color pero ninguna actividad asignada: vincula actividades a la lámina o asigna una capa abajo.</p><?php endif; ?>
            <?php if ($l['capas']): ?>
            <div class="prg-tabla-wrap"><table class="prg-tabla">
                <thead><tr><th>Capa</th><th class="num">Rojo</th><th class="num">Amarillo</th><th class="num">Verde</th><th class="num">Avance</th><th>Actividad asignada</th></tr></thead>
                <tbody><?php foreach ($l['capas'] as $c): $sel = $map[$l['id'] . '|' . $c['id']] ?? 0; ?>
                    <tr><td><?php echo s($c['nombre']); ?></td><td class="num"><?php echo (int)$c['rojo']; ?></td><td class="num"><?php echo (int)$c['amarillo']; ?></td><td class="num"><?php echo (int)$c['verde']; ?></td><td class="num"><?php echo $c['pct'] !== null ? number_format($c['pct'], 1) . ' %' : '—'; ?></td>
                        <td><?php if ($puede): ?>
                            <form method="post" action="/mapa-avance/mapear" style="display:flex;gap:.4rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="lamina" value="<?php echo (int)$l['id']; ?>"><input type="hidden" name="capa" value="<?php echo s($c['id']); ?>">
                                <select name="actividad" onchange="this.form.submit()"><option value="0">(la(s) de la lámina)</option><?php echo opciones_actividades($actividades, $sel, 50); ?></select></form>
                            <?php else: echo $sel ? s($nomAct($sel)) : '(la(s) de la lámina)'; endif; ?></td></tr>
                <?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($calc['actividades']): ?>
    <div class="card prg-seccion">
        <h3>Avance por actividad según los planos</h3>
        <div class="prg-tabla-wrap"><table class="prg-tabla">
            <thead><tr><th>Actividad</th><th class="num">Programa</th><th class="num">Planos</th><th class="num">Diferencia</th><th>Últimos días</th></tr></thead>
            <tbody><?php foreach ($calc['actividades'] as $id => $x): $a = $actividades[$id] ?? null; if (!$a || $x['pct'] === null) continue; $d = round($x['pct'] - (int)$a['progreso'], 1); ?>
                <tr><td><strong><?php echo s($a['codigo']); ?></strong> <?php echo s(mb_substr($a['nombre'], 0, 70)); ?></td><td class="num"><?php echo (int)$a['progreso']; ?> %</td><td class="num"><?php echo number_format($x['pct'], 1); ?> %</td>
                    <td class="num <?php echo $d > 0 ? 'prg-mejor' : ($d < 0 ? 'prg-peor' : ''); ?>"><?php echo $d > 0 ? '+' : ''; ?><?php echo $d; ?></td>
                    <td class="text-sm"><?php echo s(implode(' · ', array_map(fn($f, $v) => date('d/m', strtotime($f)) . ': ' . $v . '%', array_keys($historial[$id] ?? []), $historial[$id] ?? [])) ?: '—'); ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
    </div>
    <?php endif; ?>
<?php endif; ?>
