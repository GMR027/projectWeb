<?php
$id = s($proyecto->id);
$n = fn($v, int $d = 2) => $v === null ? '—' : number_format((float)$v, $d);
$total = array_sum(array_map(fn($c) => (float)$c['importe'], $cantidades));
$icono = ['critica' => 'fa-circle-exclamation', 'aviso' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'];
$tipos = ['inicio' => 'Inicio', 'fin' => 'Término', 'hito' => 'Hito'];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-contract"></i> Datos de obra</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · lo que el Ensamblador guardó de los documentos del proyecto</p>
    </div>
    <div class="la-controles"><a href="/lineas-base?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-timeline"></i> Líneas base</a></div>
</div>

<?php foreach ($alertas as $a): ?>
    <div class="alerta <?php echo $a['nivel'] === 'critica' ? 'alerta-error' : 'alerta-aviso'; ?>"><i class="fa-solid <?php echo $icono[$a['nivel']] ?? 'fa-circle-info'; ?>"></i> <?php echo s($a['texto']); ?></div>
<?php endforeach; ?>

<div class="card">
    <h3>Fechas contractuales</h3>
    <?php if (!$fechas): ?><p class="text-muted">Aún no hay fechas contractuales. Sube el contrato en el Ensamblador (app de escritorio) con la opción de contrato.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Tipo</th><th>Nombre</th><th>Fecha</th><th class="hide-m">Penalización</th><th class="hide-m">Origen</th></tr></thead>
            <tbody>
            <?php foreach ($fechas as $f): ?>
                <tr><td><?php echo s($tipos[$f['tipo']] ?? $f['tipo']); ?></td><td><?php echo s($f['nombre']); ?></td><td class="mono"><?php echo s(fecha_corta_es($f['fecha'])); ?></td><td class="hide-m"><?php echo s($f['penalizacion'] ?: '—'); ?></td><td class="hide-m text-muted"><?php echo s($f['origen']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Responsables por actividad</h3>
    <?php if (!$responsables): ?><p class="text-muted">Sin responsables asignados desde documentos.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Actividad</th><th>Responsable</th><th class="hide-m">Cargo</th><th class="hide-m">Empresa</th></tr></thead>
            <tbody>
            <?php foreach ($responsables as $r): ?>
                <tr><td><span class="mono"><?php echo s($r['codigo']); ?></span> <?php echo s($r['actividad']); ?></td><td><?php echo s($r['persona']); ?></td><td class="hide-m"><?php echo s($r['cargo'] ?: '—'); ?></td><td class="hide-m"><?php echo s($r['empresa'] ?: '—'); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Cantidades de obra<?php if ($total > 0): ?> <small class="text-muted">· importe total $<?php echo $n($total); ?></small><?php endif; ?></h3>
    <?php if (!$cantidades): ?><p class="text-muted">Sin catálogo de conceptos cargado.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Clave</th><th>Concepto</th><th class="hide-m">Actividad</th><th>Cantidad</th><th class="hide-m">Importe</th></tr></thead>
            <tbody>
            <?php foreach ($cantidades as $c): ?>
                <tr><td class="mono"><?php echo s($c['clave'] ?: '—'); ?></td><td><?php echo s($c['concepto']); ?></td><td class="hide-m"><span class="mono"><?php echo s($c['codigo']); ?></span> <?php echo s($c['actividad']); ?></td><td class="mono"><?php echo $n($c['cantidad'], 3) . ' ' . s($c['unidad']); ?></td><td class="mono hide-m"><?php echo $c['importe'] === null ? '—' : '$' . $n($c['importe']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($contactos !== null): ?>
<div class="card">
    <h3>Directorio de contactos</h3>
    <?php if (!$contactos): ?><p class="text-muted">Sin contactos guardados desde documentos.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th class="hide-m">Cargo</th><th class="hide-m">Empresa</th><th>Teléfono</th><th>Correo</th></tr></thead>
            <tbody>
            <?php foreach ($contactos as $c): ?>
                <tr><td><?php echo s($c['nombre']); ?></td><td class="hide-m"><?php echo s($c['cargo'] ?: '—'); ?></td><td class="hide-m"><?php echo s($c['empresa'] ?: '—'); ?></td><td class="mono"><?php echo s($c['telefono'] ?: '—'); ?></td><td><?php echo $c['correo'] ? '<a href="mailto:' . s($c['correo']) . '">' . s($c['correo']) . '</a>' : '—'; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>
