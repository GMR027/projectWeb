<?php
$pid = s($proyecto->id);
$r = $cmp['resumen'];
$filas = $soloCambios ? array_values(array_filter($cmp['filas'], fn($f) => $f['estado'] !== 'igual')) : $cmp['filas'];
$signo = fn(int $n) => ($n > 0 ? '+' : '') . $n;
// Escala del Gantt: de la primera a la última fecha (línea base o actual)
$fechas = [];
foreach ($filas as $f) foreach (['base_inicio', 'base_fin', 'inicio', 'fin'] as $k) if ($f[$k]) $fechas[] = $f[$k];
$min = $fechas ? min($fechas) : date('Y-m-d');
$max = $fechas ? max($fechas) : date('Y-m-d');
$total = max(1, (int)((strtotime($max) - strtotime($min)) / 86400) + 1);
$pos = fn(string $d) => round((strtotime($d) - strtotime($min)) / 86400 / $total * 100, 3);
$ancho = fn(string $a, string $b) => max(0.6, round(((strtotime($b) - strtotime($a)) / 86400 + 1) / $total * 100, 3));
$causas = ['oc' => 'Orden de cambio', 'material' => 'Material en riesgo', 'sin_justificar' => 'Sin justificar'];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-timeline"></i> <?php echo s($linea->nombre); ?> <small class="text-muted">contra el programa de hoy</small></h2>
        <p class="text-muted">Línea base guardada el <?php echo formatear_fecha(substr((string)$linea->creado, 0, 10)); ?> · <?php echo (int)$linea->total_actividades; ?> actividades</p>
    </div>
    <a href="/lineas-base?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Líneas base</a>
</div>

<div class="cb-kpis">
    <div class="cb-kpi <?php echo $r['d_fin'] > 0 ? 'cb-kpi-alerta' : ''; ?>"><small>Fecha de término</small><strong><?php echo $signo((int)$r['d_fin']); ?> d</strong><span><?php echo $r['fin_base'] ? formatear_fecha($r['fin_base']) : '—'; ?> → <?php echo $r['fin_actual'] ? formatear_fecha($r['fin_actual']) : '—'; ?> (días hábiles)</span></div>
    <div class="cb-kpi"><small>Actividades movidas</small><strong><?php echo (int)$r['movida']; ?></strong><span><?php echo (int)$r['nueva']; ?> nueva(s) · <?php echo (int)$r['eliminada']; ?> eliminada(s)</span></div>
    <div class="cb-kpi"><small>Justificadas</small><strong><?php echo (int)$r['oc'] + (int)$r['material']; ?></strong><span><?php echo (int)$r['oc']; ?> por orden de cambio · <?php echo (int)$r['material']; ?> por material</span></div>
    <div class="cb-kpi <?php echo $r['sin_justificar'] ? 'cb-kpi-alerta' : ''; ?>"><small>Sin justificar</small><strong><?php echo (int)$r['sin_justificar']; ?></strong><span>cambios sin orden de cambio</span></div>
</div>

<?php if (!empty($cmp['contrato'])): ?>
<div class="card">
    <h3>Contra el contrato</h3>
    <p class="text-muted">Fechas contractuales tomadas de los documentos (Ensamblador). Un desfase positivo significa que el programa queda después de la fecha del contrato.</p>
    <table class="tabla">
        <thead><tr><th>Fecha contractual</th><th>Contrato</th><th>Línea base</th><th>Programa de hoy</th><th>Penalización</th></tr></thead>
        <tbody>
        <?php foreach ($cmp['contrato'] as $c):
            $celda = fn(?string $f, ?int $d) => $f ? formatear_fecha($f) . ' <small class="' . ($d > 0 ? 'cb-tag cb-tag-critica' : 'text-muted') . '">' . ($d > 0 ? '+' : '') . $d . ' d</small>' : '—';
        ?>
            <tr>
                <td><?php echo s($c['nombre']); ?><?php echo $c['actividad'] ? ' <small class="text-muted">(' . s($c['actividad']) . ')</small>' : ''; ?></td>
                <td><?php echo formatear_fecha($c['fecha']); ?></td>
                <td><?php echo $celda($c['base'], $c['d_base']); ?></td>
                <td><?php echo $celda($c['actual'], $c['d_actual']); ?></td>
                <td><?php echo $c['penalizacion'] !== '' ? s($c['penalizacion']) : '—'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($r['criticas_entran'] || $r['criticas_salen']): ?>
<div class="card">
    <h3>Cambios en la ruta crítica</h3>
    <ul class="cb-ul">
        <?php foreach ($r['criticas_entran'] as $f): ?><li><span class="cb-tag cb-tag-critica">Entra</span> <?php echo s($f['codigo'] . ' · ' . $f['nombre']); ?></li><?php endforeach; ?>
        <?php foreach ($r['criticas_salen'] as $f): ?><li><span class="cb-tag cb-tag-ok">Sale</span> <?php echo s($f['codigo'] . ' · ' . $f['nombre']); ?></li><?php endforeach; ?>
    </ul>
</div>
<?php elseif (!$r['hay_ruta_critica']): ?>
<p class="text-muted">La ruta crítica no se compara porque las dependencias de obra están apagadas o sin relaciones.</p>
<?php endif; ?>

<div class="card">
    <div class="cb-barra-herr">
        <h3>Programa: línea base vs actual</h3>
        <a href="/lineas-base/comparar?proyecto=<?php echo $pid; ?>&id=<?php echo s($linea->id); ?><?php echo $soloCambios ? '&todas=1' : ''; ?>" class="btn btn-sm btn-ghost"><?php echo $soloCambios ? 'Mostrar todas las actividades' : 'Mostrar solo las que cambiaron'; ?></a>
    </div>
    <p class="cb-leyenda"><span class="cb-lg cb-lg-base"></span> línea base <span class="cb-lg cb-lg-act"></span> programa actual <span class="cb-lg cb-lg-crit"></span> en ruta crítica</p>
    <?php if (empty($filas)): ?>
        <p class="text-muted">El programa de hoy es igual a la línea base.</p>
    <?php else: ?>
    <div class="cb-gantt" style="--dias: <?php echo $total; ?>">
        <div class="cb-gantt-eje"><span><?php echo formatear_fecha($min); ?></span><span><?php echo formatear_fecha($max); ?></span></div>
        <?php foreach ($filas as $f): ?>
        <div class="cb-gantt-fila cb-e-<?php echo s($f['estado']); ?>">
            <div class="cb-gantt-etq" title="<?php echo s($f['partida']); ?>">
                <span class="mono"><?php echo s($f['codigo']); ?></span> <?php echo s($f['nombre']); ?>
                <small>
                    <?php if ($f['estado'] === 'movida'): ?>
                        inicio <?php echo $signo($f['d_inicio']); ?> d · fin <?php echo $signo($f['d_fin']); ?> d<?php echo $f['d_dur'] ? ' · duración ' . $signo($f['d_dur']) . ' d' : ''; ?>
                    <?php else: echo $f['estado'] === 'nueva' ? 'Nueva' : ($f['estado'] === 'eliminada' ? 'Eliminada' : 'Sin cambios'); endif; ?>
                    <?php if ($f['causa']): ?> · <span class="cb-tag cb-tag-<?php echo $f['causa'] === 'sin_justificar' ? 'critica' : 'ok'; ?>"><?php echo s($causas[$f['causa']]); ?><?php echo $f['detalle'] && $f['causa'] === 'oc' ? ' ' . s($f['detalle']) : ''; ?></span><?php endif; ?>
                </small>
            </div>
            <div class="cb-gantt-pista">
                <?php if ($f['base_inicio']): ?><span class="cb-barra cb-barra-base" style="left:<?php echo $pos($f['base_inicio']); ?>%;width:<?php echo $ancho($f['base_inicio'], $f['base_fin']); ?>%"></span><?php endif; ?>
                <?php if ($f['inicio']): ?><span class="cb-barra cb-barra-act<?php echo $f['critica_ahora'] ? ' cb-barra-crit' : ''; ?>" style="left:<?php echo $pos($f['inicio']); ?>%;width:<?php echo $ancho($f['inicio'], $f['fin']); ?>%"></span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($materiales): ?>
<div class="card">
    <h3>Materiales cuyas fechas cambiaron</h3>
    <table class="tabla">
        <thead><tr><th>Material</th><th>Compra (base → hoy)</th><th>Entrega (base → hoy)</th><th class="hide-m">Cantidad</th></tr></thead>
        <tbody>
        <?php foreach ($materiales as $m): $f = fn($d) => $d ? formatear_fecha($d) : '—'; ?>
            <tr><td><?php echo s($m['nombre']); ?></td><td class="mono"><?php echo $f($m['base_compra']) . ' → ' . $f($m['compra']); ?></td><td class="mono"><?php echo $f($m['base_entrega']) . ' → ' . $f($m['entrega']); ?></td>
                <td class="mono hide-m"><?php echo $m['base_cantidad'] !== null ? s(rtrim(rtrim($m['base_cantidad'], '0'), '.')) : '—'; ?> → <?php echo $m['cantidad'] !== null ? s(rtrim(rtrim($m['cantidad'], '0'), '.')) : '—'; ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
