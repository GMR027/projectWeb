<?php // Variables: $proyecto, $actual, $catalogo, $puede, $partidas, $mensaje, $historial, $identificacion
use Classes\Punch;
use Classes\Seguridad;
$pid = (int)$proyecto->id;
$nEtapas = $actual ? count($actual['etapas']) : 0;
$nSubmittals = $actual ? count($actual['submittals']) : 0;
$nCriticos = $actual ? count($actual['criticos']) : 0;
$nBase = $actual ? count($actual['partidas']) : 0;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-layer-group"></i> <?php echo s(t('Tipo de obra')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · al elegir el tipo de obra, las herramientas de IA conocen el oficio (secuencia, plazos, errores comunes), el sistema avisa cuando una etapa arranca antes que la anterior, y puedes sembrar una base de partidas y actividades.</p>
        <div class="herr-meta">
            <?php if ($actual): ?>
                <span class="herr-chip"><i class="fa-solid fa-helmet-safety"></i> <b><?php echo s($actual['nombre']); ?></b></span>
                <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-list-ol"></i><b><?php echo $nEtapas; ?></b> etapas</span>
                <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-file-circle-check"></i><b><?php echo $nSubmittals; ?></b> submittals</span>
                <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-boxes-packing"></i><b><?php echo $nCriticos; ?></b> materiales críticos</span>
            <?php else: ?>
                <span class="herr-chip"><i class="fa-solid fa-circle-question"></i> sin tipo de obra</span>
                <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-list"></i><b><?php echo count($catalogo); ?></b> tipos disponibles</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>" role="status"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-list-ol" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Etapas</small><strong><?php echo $nEtapas; ?></strong><span><?php echo $actual ? 'secuencia típica del oficio' : 'elige un tipo para verlas'; ?></span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-file-circle-check" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Submittals habituales</small><strong><?php echo $nSubmittals; ?></strong><span>documentos que suele pedir la obra</span></span>
    </div>
    <div class="suite-kpi <?php echo $nCriticos ? 'is-warning' : ''; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-boxes-packing" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Materiales críticos</small><strong><?php echo $nCriticos; ?></strong><span>con plazo típico de entrega</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Base de partidas</small><strong><?php echo $actual ? $nBase : '—'; ?></strong><span><?php echo (int)$partidas > 0 ? 'el proyecto ya tiene ' . (int)$partidas . ' partida(s)' : 'lista para sembrar el programa'; ?></span></span>
    </div>
</div>

<?php if ($puede): ?>
<div class="suite-panel tipo-elegir">
    <div class="suite-panel-cab">
        <div>
            <h3><i class="fa-solid fa-wand-magic-sparkles"></i> Elegir el tipo de obra</h3>
            <p class="text-muted text-sm">El tipo de obra alimenta el contexto de la IA, la regla de secuencia y las plantillas de punch y seguridad.</p>
        </div>
        <a class="btn btn-sm btn-ghost" href="/plantillas?proyecto=<?php echo $pid; ?>&identificar=1"><i class="fa-solid fa-magnifying-glass-chart"></i> Identificar automáticamente</a>
    </div>
    <div class="suite-panel-cuerpo">
        <form method="post" action="/plantillas/elegir" class="suite-form">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <div class="suite-form-inline">
                <label>Tipo de obra
                    <select name="plantilla">
                        <option value="">(ninguno)</option>
                        <?php foreach ($catalogo as $k => $t): ?><option value="<?php echo s($k); ?>"<?php echo $actual && $actual['clave'] === $k ? ' selected' : ''; ?>><?php echo s($t['nombre']); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($identificacion): $top = array_key_first($identificacion['ranking']); $r = $identificacion['ranking'][$top] ?? null; ?>
<div class="suite-panel tipo-identificado">
    <div class="suite-panel-cab">
        <div>
            <h3><i class="fa-solid fa-magnifying-glass-chart"></i> Resultado de la identificación</h3>
            <p class="text-muted text-sm">Se calcula con el nombre, las partidas, las actividades y los materiales capturados.</p>
        </div>
        <?php if ($r && $identificacion['total'] > 0): ?><span class="suite-chip is-primary"><i class="fa-solid fa-bullseye"></i> <?php echo (int)$r['porcentaje']; ?> % de coincidencia</span><?php endif; ?>
    </div>
    <div class="suite-panel-cuerpo">
        <?php if (!$r || $identificacion['total'] === 0): ?>
            <p class="text-muted">Todavía no hay suficientes datos (nombre, partidas, actividades o materiales) para deducir el tipo de obra. Elígelo a mano arriba.</p>
        <?php else: ?>
            <p>Por lo capturado en el proyecto parece <strong><?php echo s($catalogo[$top]['nombre']); ?></strong>. Estos son los tipos que más se acercan:</p>
            <ul class="tipo-ranking">
                <?php foreach ($identificacion['ranking'] as $k => $x): if ($x['puntos'] === 0) continue; ?>
                    <li>
                        <span class="tipo-rank-nombre"><?php echo s($catalogo[$k]['nombre']); ?></span>
                        <span class="tipo-rank-barra"><i style="width:<?php echo max(2, (int)$x['porcentaje']); ?>%"></i></span>
                        <span class="tipo-rank-pct"><?php echo (int)$x['porcentaje']; ?> %</span>
                        <small class="text-muted" style="grid-column:1/-1"><?php echo s(implode(' · ', array_slice($x['indicios'], 0, 6))); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="/plantillas/elegir" class="suite-form">
                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
                <input type="hidden" name="plantilla" value="<?php echo s($top); ?>">
                <input type="hidden" name="origen" value="identificado">
                <input type="hidden" name="confianza" value="<?php echo (int)$r['porcentaje']; ?>">
                <input type="hidden" name="indicios" value="<?php echo s(implode(', ', array_slice($r['indicios'], 0, 8))); ?>">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> Guardar como tipo de obra</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (!$actual): ?>
    <div class="suite-panel">
        <div class="suite-panel-cab"><h3><i class="fa-solid fa-layer-group"></i> Tipos de obra disponibles</h3></div>
        <div class="suite-panel-cuerpo">
            <div class="tipo-catalogo">
                <?php foreach ($catalogo as $k => $t): ?>
                    <article class="tipo-catalogo-card">
                        <h4><?php echo s($t['nombre']); ?></h4>
                        <p class="text-muted text-sm"><?php echo s($t['descripcion']); ?></p>
                        <?php if ($puede): ?>
                        <form method="post" action="/plantillas/elegir">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="plantilla" value="<?php echo s($k); ?>">
                            <button class="btn btn-sm btn-ghost" type="submit">Elegir este tipo</button>
                        </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="tipo-grid">
        <div class="suite-panel tipo-sec">
            <div class="suite-panel-cab">
                <div>
                    <h3><i class="fa-solid fa-helmet-safety"></i> <?php echo s($actual['nombre']); ?></h3>
                    <p class="text-muted text-sm">Cómo se trabaja este oficio y qué aporta al proyecto.</p>
                </div>
                <span class="suite-chip is-primary"><?php echo s($actual['clave']); ?></span>
            </div>
            <div class="suite-panel-cuerpo">
                <p><?php echo s($actual['descripcion']); ?></p>

                <h4>Qué aporta a este proyecto</h4>
                <ul>
                    <li><strong>Contexto para la IA:</strong> el Programador, las Inconsistencias y el Análisis de materiales reciben el contexto del oficio, lo que ayuda sobre todo a los modelos pequeños.</li>
                    <li><strong>Regla de secuencia:</strong> el sistema propone una relación cuando una actividad de una etapa empieza antes que la etapa anterior de su partida (en Inconsistencias).</li>
                    <li><strong>Inspección en el punch list:</strong> <?php echo s(Punch::PLANTILLAS[$actual['punch']]['nombre']); ?>.</li>
                    <li><strong>Permisos de seguridad habituales:</strong> <?php echo s(implode(', ', array_map(fn($k) => Seguridad::TIPOS[$k]['nombre'], $actual['permisos']))); ?>.</li>
                </ul>

                <h4>Secuencia típica</h4>
                <ul class="tipo-etapas">
                    <?php foreach ($actual['etapas'] as $et): ?><li><?php echo s($et[0]); ?></li><?php endforeach; ?>
                </ul>

                <h4>Contexto que recibe la IA</h4>
                <p class="text-muted text-sm"><?php echo s($actual['contexto']); ?></p>
            </div>
        </div>

        <div class="suite-panel tipo-sec">
            <div class="suite-panel-cab"><h3><i class="fa-solid fa-clipboard-list"></i> Documentos y materiales</h3></div>
            <div class="suite-panel-cuerpo">
                <h4>Submittals habituales</h4>
                <?php if (!$actual['submittals']): ?><p class="text-muted">Sin submittals definidos.</p><?php else: ?>
                    <ul><?php foreach ($actual['submittals'] as $x): ?><li><?php echo s($x); ?></li><?php endforeach; ?></ul>
                <?php endif; ?>

                <h4>Materiales críticos y plazo típico</h4>
                <?php if (!$actual['criticos']): ?><p class="text-muted">Sin materiales críticos definidos.</p><?php else: ?>
                    <ul><?php foreach ($actual['criticos'] as $c): ?><li><strong><?php echo s(ucfirst($c[0])); ?></strong> · ~<?php echo (int)$c[1]; ?> días</li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="suite-panel tipo-sec">
        <div class="suite-panel-cab">
            <div>
                <h3><i class="fa-solid fa-diagram-project"></i> Base de partidas y actividades</h3>
                <p class="text-muted text-sm">Se puede crear solo en un proyecto vacío, a partir de la fecha de inicio del proyecto.</p>
            </div>
            <span class="suite-chip"><?php echo $nBase; ?> partida<?php echo $nBase === 1 ? '' : 's'; ?></span>
        </div>
        <div class="suite-panel-cuerpo">
            <ul>
                <?php foreach ($actual['partidas'] as [$c, $n, $acts]): ?>
                    <li><strong><?php echo s($c . ' ' . $n); ?></strong>: <?php echo s(implode('; ', array_map(fn($a) => $a[0] . ' (' . $a[1] . ' d)', $acts))); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($puede): ?>
                <?php if ($partidas > 0): ?>
                    <p class="text-muted"><i class="fa-solid fa-circle-info"></i> Este proyecto ya tiene <?php echo (int)$partidas; ?> partida(s): la base solo se puede crear en un proyecto vacío.</p>
                <?php else: ?>
                    <form method="post" action="/plantillas/aplicar" data-confirmar="Se crearán las partidas y actividades de la base a partir de la fecha de inicio del proyecto. ¿Continuar?">
                        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Crear la base en este proyecto</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($historial): ?>
<div class="suite-panel">
    <div class="suite-panel-cab"><h3><i class="fa-solid fa-clock-rotate-left"></i> Registro de tipos de obra</h3></div>
    <div class="suite-panel-cuerpo">
        <ul class="tipo-historial">
            <?php foreach ($historial as $h): ?>
                <li>
                    <strong><?php echo s($catalogo[$h['plantilla']]['nombre'] ?? $h['plantilla']); ?></strong>
                    <span class="text-muted text-sm"><?php echo $h['origen'] === 'identificado' ? 'identificado (' . (int)$h['confianza'] . ' %)' : 'elegido a mano'; ?> · <?php echo s($h['usuario']); ?></span>
                    <small class="text-muted"><?php echo s($h['creado']); ?><?php echo $h['indicios'] !== '' ? ' · ' . s($h['indicios']) : ''; ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
