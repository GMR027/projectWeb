<?php
/** Calidad de la IA. Variables: $proyecto, $cmp [modelos, historial, otras_versiones], $casos */
use Classes\PruebasIA;

$pid = (int)$proyecto->id;
$pestana = 'calidad';
include __DIR__ . '/_pestanas.php';
$modelos = $cmp['modelos'];
$color = fn(float $n) => $n >= 1 ? 'prg-mejor' : ($n > 0 ? '' : 'prg-peor');
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-ranking-star"></i> Calidad de la IA</h2>
        <p class="text-muted">Casos fijos —el mismo texto y la misma respuesta esperada— para comparar modelos o ver si uno empeora. Versión de los casos: <strong>v<?php echo PruebasIA::VERSION; ?></strong>. La nota la pone el servidor con reglas, sin IA.</p>
    </div>
</div>

<section class="card">
    <h3>Cómo correrla</h3>
    <p class="text-sm">En la <strong>app de escritorio</strong> → Herramientas IA → <em>Pruebas de calidad de la IA</em>: elige la conexión (modelo) y ejecuta. Repite con cada modelo que quieras comparar; aquí aparece la corrida más reciente de cada uno. Tarda un par de minutos con un modelo local.</p>
</section>

<?php if (!$modelos): ?>
    <div class="card prg-vacio"><h3>Aún no hay corridas con la versión actual</h3><p class="text-muted">Corre la prueba desde la app de escritorio.<?php echo $cmp['otras_versiones'] ? ' Hay ' . (int)$cmp['otras_versiones'] . ' corrida(s) de versiones anteriores de los casos (no se comparan).' : ''; ?></p></div>
<?php else: ?>
<section class="card">
    <h3>Comparación (última corrida de cada modelo)</h3>
    <div class="tabla-wrapper"><table class="tabla cal-matriz">
        <thead><tr><th>Caso</th><?php foreach ($modelos as $m => $f): ?><th><?php echo s($m); ?><br><small class="text-muted"><?php echo s(formatear_fecha(substr($f['fecha'], 0, 10))); ?><?php echo $f['suite'] !== 'todas' ? ' · parcial' : ''; ?></small></th><?php endforeach; ?></tr></thead>
        <tbody>
            <tr class="cal-total"><td><strong>Nota total</strong></td><?php foreach ($modelos as $f): ?><td><strong class="<?php echo $color($f['nota'] / 100); ?>"><?php echo (int)$f['nota']; ?> %</strong><br><small class="text-muted"><?php echo $f['ms_medio'] ? round($f['ms_medio'] / 1000, 1) . ' s por caso' : ''; ?><?php echo $f['corridas'] > 1 ? ' · ' . $f['corridas'] . ' corridas' : ''; ?></small></td><?php endforeach; ?></tr>
            <?php $suiteAnt = ''; foreach ($casos as $c): ?>
                <?php if ($c['suite'] !== $suiteAnt): $suiteAnt = $c['suite']; ?><tr class="cp-grupo"><td colspan="<?php echo 1 + count($modelos); ?>"><?php echo s(PruebasIA::SUITES[$c['suite']]); ?></td></tr><?php endif; ?>
                <tr><td><?php echo s($c['nombre']); ?></td>
                    <?php foreach ($modelos as $f): $n = $f['casos'][$c['id']] ?? null; ?><td class="mono <?php echo $n === null ? 'text-muted' : $color((float)$n); ?>"><?php echo $n === null ? '—' : ($n >= 1 ? '✅ ' : ($n > 0 ? '⚠ ' : '❌ ')) . round($n * 100) . ' %'; ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>

<?php if ($cmp['historial']): ?>
<section class="card">
    <h3>Historial de corridas</h3>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Cuándo</th><th>Modelo</th><th>Nota</th><th class="hide-m">Versión</th><th></th></tr></thead>
        <tbody><?php foreach ($cmp['historial'] as $h): ?>
            <tr><td class="mono text-sm"><?php echo s($h['fecha']); ?></td><td><?php echo s($h['modelo']); ?></td><td class="mono"><?php echo (int)$h['nota']; ?> %</td>
                <td class="mono hide-m"><?php echo 'v' . (int)$h['version']; ?><?php echo $h['version'] !== PruebasIA::VERSION ? ' <small class="text-muted">(no comparable)</small>' : ''; ?></td>
                <td class="acciones"><a class="btn btn-sm btn-ghost" href="/informes-ia/ver?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$h['id']; ?>">Ver detalle</a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</section>
<?php endif; ?>
