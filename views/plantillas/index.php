<?php // Variables: $proyecto, $actual, $catalogo, $puede, $partidas, $mensaje, $historial, $identificacion
use Classes\Punch;
use Classes\Seguridad;
$pid = (int)$proyecto->id;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-layer-group"></i> <?php echo s(t('Tipo de obra')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · al elegir el tipo de obra, las herramientas de IA conocen el oficio (secuencia, plazos, errores comunes), el sistema avisa cuando una etapa arranca antes que la anterior, y puedes sembrar una base de partidas y actividades.</p>
    </div>
</div>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<?php if ($puede): ?>
<form method="post" action="/plantillas/elegir" class="card inc-filtros"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label>Tipo de obra <select name="plantilla"><option value="">(ninguno)</option><?php foreach ($catalogo as $k => $t): ?><option value="<?php echo s($k); ?>"<?php echo $actual && $actual['clave'] === $k ? ' selected' : ''; ?>><?php echo s($t['nombre']); ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary" type="submit">Guardar</button>
    <a class="btn btn-ghost" href="/plantillas?proyecto=<?php echo $pid; ?>&identificar=1"><i class="fa-solid fa-wand-magic-sparkles"></i> Identificar tipo de obra</a></form>

<?php if ($identificacion): $top = array_key_first($identificacion['ranking']); $r = $identificacion['ranking'][$top] ?? null; ?>
<div class="card prg-seccion"><h3>Resultado de la identificación</h3>
    <?php if (!$r || $identificacion['total'] === 0): ?>
        <p class="text-muted">Todavía no hay suficientes datos (nombre, partidas, actividades o materiales) para deducir el tipo de obra. Elígelo a mano arriba.</p>
    <?php else: ?>
        <p>Por lo capturado en el proyecto parece <strong><?php echo s($catalogo[$top]['nombre']); ?></strong> (<?php echo (int)$r['porcentaje']; ?> % de coincidencia).</p>
        <ul><?php foreach ($identificacion['ranking'] as $k => $x): if ($x['puntos'] === 0) continue; ?><li><?php echo s($catalogo[$k]['nombre']); ?>: <?php echo (int)$x['porcentaje']; ?> % <small class="text-muted">· <?php echo s(implode(', ', array_slice($x['indicios'], 0, 6))); ?></small></li><?php endforeach; ?></ul>
        <form method="post" action="/plantillas/elegir"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="plantilla" value="<?php echo s($top); ?>"><input type="hidden" name="origen" value="identificado"><input type="hidden" name="confianza" value="<?php echo (int)$r['porcentaje']; ?>"><input type="hidden" name="indicios" value="<?php echo s(implode(', ', array_slice($r['indicios'], 0, 8))); ?>">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> Guardar como tipo de obra</button></form>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (!$actual): ?>
    <div class="card prg-vacio"><h3>Sin tipo de obra</h3><p class="text-muted">Elige uno arriba para ver qué aporta. Disponibles: <?php echo s(implode(', ', array_map(fn($t) => $t['nombre'], $catalogo))); ?>.</p></div>
<?php else: ?>
    <div class="card prg-seccion"><h3><?php echo s($actual['nombre']); ?></h3><p><?php echo s($actual['descripcion']); ?></p>
        <h4>Qué aporta a este proyecto</h4>
        <ul>
            <li><strong>Contexto para la IA:</strong> el Programador, las Inconsistencias y el Análisis de materiales reciben el contexto del oficio, lo que ayuda sobre todo a los modelos pequeños.</li>
            <li><strong>Regla de secuencia:</strong> el sistema propone una relación cuando una actividad de una etapa empieza antes que la etapa anterior de su partida (en Inconsistencias).</li>
            <li><strong>Inspección en el punch list:</strong> <?php echo s(Punch::PLANTILLAS[$actual['punch']]['nombre']); ?>.</li>
            <li><strong>Permisos de seguridad habituales:</strong> <?php echo s(implode(', ', array_map(fn($k) => Seguridad::TIPOS[$k]['nombre'], $actual['permisos']))); ?>.</li>
        </ul>
        <h4>Secuencia típica</h4><p><?php echo s(implode(' → ', array_column($actual['etapas'], 0))); ?></p>
        <h4>Submittals habituales</h4><ul><?php foreach ($actual['submittals'] as $x): ?><li><?php echo s($x); ?></li><?php endforeach; ?></ul>
        <h4>Materiales críticos y plazo típico</h4><p><?php echo s(implode(' · ', array_map(fn($c) => ucfirst($c[0]) . ': ~' . $c[1] . ' días', $actual['criticos']))); ?></p>
        <h4>Contexto que recibe la IA</h4><p class="text-muted text-sm"><?php echo s($actual['contexto']); ?></p>
    </div>
    <div class="card prg-seccion"><h3>Base de partidas y actividades</h3>
        <ul><?php foreach ($actual['partidas'] as [$c, $n, $acts]): ?><li><strong><?php echo s($c . ' ' . $n); ?></strong>: <?php echo s(implode('; ', array_map(fn($a) => $a[0] . ' (' . $a[1] . ' d)', $acts))); ?></li><?php endforeach; ?></ul>
        <?php if ($puede): if ($partidas > 0): ?><p class="text-muted">Este proyecto ya tiene <?php echo (int)$partidas; ?> partida(s): la base solo se puede crear en un proyecto vacío.</p>
        <?php else: ?><form method="post" action="/plantillas/aplicar" data-confirmar="Se crearán las partidas y actividades de la base a partir de la fecha de inicio del proyecto. ¿Continuar?"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Crear la base en este proyecto</button></form><?php endif; endif; ?>
    </div>
<?php endif; ?>

<?php if ($historial): ?>
<div class="card prg-seccion"><h3>Registro de tipos de obra</h3>
    <ul><?php foreach ($historial as $h): ?><li><strong><?php echo s($catalogo[$h['plantilla']]['nombre'] ?? $h['plantilla']); ?></strong> · <?php echo $h['origen'] === 'identificado' ? 'identificado (' . (int)$h['confianza'] . ' %)' : 'elegido a mano'; ?> · <?php echo s($h['usuario']); ?> · <small class="text-muted"><?php echo s($h['creado']); ?><?php echo $h['indicios'] !== '' ? ' · ' . s($h['indicios']) : ''; ?></small></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
