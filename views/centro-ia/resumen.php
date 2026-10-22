<?php // Variables: $proyecto, $horas, $r, $mensaje
use Classes\Auditoria;
use Classes\CentroIA;
use Classes\ResumenIA;
use Classes\Revisiones;
use Classes\UsoIA;
use Classes\VigilanciaServidor;
$pid = (int)$proyecto->id;
$fmt = fn($d) => date('d/m/Y H:i', strtotime($d));
$c = $r['cambios'];
$p = $r['pendientes'];
$v = $r['vigilancia'];
$vs = $r['vigilancia_servidor'];
$prox = VigilanciaServidor::proxima($vs);
$sevNombre = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$u = $r['uso'];
?>
<?php $pestana = 'resumen'; include __DIR__ . '/../centro-ia/_pestanas.php'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-gauge-high"></i> <?php echo s(t('Resumen de IA')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · qué cambió, qué espera tu aprobación, qué herramienta quedó desactualizada y qué detectó la vigilancia. Es una pantalla: no se envía ningún correo.</p>
    </div>
    <form method="get" action="/resumen-ia" class="inc-filtros">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label>Periodo <select name="horas" onchange="this.form.submit()"><?php foreach (ResumenIA::PERIODOS as $h => $n): ?><option value="<?php echo $h; ?>"<?php echo $h === $horas ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    </form>
</div>

<?php if ($mensaje): ?><div class="card ia-aviso"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<div class="prg-resumen inc-resumen">
    <a class="card" href="/historial-cambios?proyecto=<?php echo $pid; ?>&desde=<?php echo s(date('Y-m-d', strtotime($r['desde']))); ?>"><small>Cambios aplicados por la IA</small><strong><?php echo (int)($c['conteo']['aplicado'] ?? 0); ?></strong></a>
    <a class="card" href="/propuestas-ia?proyecto=<?php echo $pid; ?>"><small>Esperan tu aprobación</small><strong><?php echo (int)$p['total']; ?></strong></a>
    <a class="card" href="#herramientas"><small>Herramientas por actualizar</small><strong><?php echo count($r['herramientas']); ?></strong></a>
    <a class="card" href="#vigilancia"><small>Riesgos nuevos de la vigilancia</small><strong><?php echo (int)(($v['total_nuevos'] ?? 0) + count($v['cambios'] ?? [])); ?></strong></a>
    <a class="card" href="/uso-ia?proyecto=<?php echo $pid; ?>"><small>Llamadas al modelo</small><strong><?php echo (int)($u['llamadas'] ?? 0); ?></strong></a>
</div>

<section class="card ia-bloque">
    <h3><i class="fa-solid fa-clock-rotate-left"></i> Qué cambió</h3>
    <?php if (!array_sum($c['conteo'])): ?>
        <p class="text-muted">La IA no aplicó, revirtió ni rechazó nada en este periodo.</p>
    <?php else: ?>
        <p>
            <?php foreach (Auditoria::EVENTOS as $k => $n): if (empty($c['conteo'][$k])) continue; ?>
                <span class="hist-ev ev-<?php echo s($k); ?>"><?php echo s($n); ?>: <?php echo (int)$c['conteo'][$k]; ?></span>
            <?php endforeach; ?>
        </p>
        <ul class="ia-lista">
            <?php foreach ($c['recientes'] as $x): ?>
                <li><span class="hist-ev ev-<?php echo s($x['evento']); ?>"><?php echo s(Auditoria::EVENTOS[$x['evento']] ?? $x['evento']); ?></span> <?php echo s($x['resumen']); ?> <small class="text-muted">· <?php echo s(Auditoria::HERRAMIENTAS[$x['herramienta']] ?? $x['herramienta']); ?> · <?php echo s($fmt($x['creado'])); ?><?php echo $x['usuario'] ? ' · ' . s($x['usuario']) : ''; ?></small></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p>
        <a class="btn btn-sm btn-ghost" href="/historial-cambios?proyecto=<?php echo $pid; ?>">Ver el historial completo →</a>
        <?php if ($c['revertibles']): ?><a class="btn btn-sm btn-ghost" href="/historial-cambios?proyecto=<?php echo $pid; ?>#tanda"><i class="fa-solid fa-rotate-left"></i> <?php echo (int)$c['revertibles']; ?> se pueden revertir de una vez</a><?php endif; ?>
    </p>
</section>

<section class="card ia-bloque">
    <h3><i class="fa-solid fa-inbox"></i> Qué espera aprobación</h3>
    <?php if (!$p['total']): ?>
        <p class="text-muted">No hay propuestas pendientes.</p>
    <?php else: ?>
        <p><strong><?php echo (int)$p['total']; ?></strong> pendiente(s)
            <?php foreach (CentroIA::HERRAMIENTAS as $k => $n): if (empty($p['por_herramienta'][$k])) continue; ?> · <?php echo s($n); ?>: <?php echo (int)$p['por_herramienta'][$k]; ?><?php endforeach; ?>.
            <?php if ($p['bajo_riesgo']): ?><a href="/propuestas-ia?proyecto=<?php echo $pid; ?>&riesgo=simple"><?php echo (int)$p['bajo_riesgo']; ?> son de bajo riesgo</a> y se pueden aceptar por lote.<?php endif; ?></p>
        <?php if ($p['graves']): ?>
            <ul class="ia-lista">
                <?php foreach ($p['graves'] as $x): ?><li><span class="prg-sev"><?php echo s($sevNombre[$x['severidad']]); ?></span> <?php echo s($x['titulo']); ?> <small class="text-muted">· <?php echo s(CentroIA::HERRAMIENTAS[$x['herramienta']]); ?></small></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
    <p><a class="btn btn-sm btn-ghost" href="/propuestas-ia?proyecto=<?php echo $pid; ?>">Abrir la bandeja →</a></p>
</section>

<section class="card ia-bloque" id="herramientas">
    <h3><i class="fa-solid fa-rotate"></i> Herramientas sin ejecutar o desactualizadas</h3>
    <?php if (!$r['herramientas']): ?>
        <p class="text-muted">Todas las herramientas que has usado están al día con los datos actuales del proyecto.</p>
    <?php else: ?>
        <ul class="ia-lista">
            <?php foreach ($r['herramientas'] as $h): [$etq, $icono] = Revisiones::ESTADOS_HERRAMIENTA[$h['estado']]; ?>
                <li><i class="fa-solid <?php echo $icono; ?>"></i> <strong><?php echo s($h['titulo']); ?></strong> <small class="text-muted">· <?php echo $h['estado'] === 'sin_ejecutar' ? 'nunca se ha ejecutado' : 'última vez ' . s($fmt($h['ejecutada'])) . ' · los datos cambiaron después'; ?></small></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p><a class="btn btn-sm btn-ghost" href="/revisiones?proyecto=<?php echo $pid; ?>">Ver Revisiones →</a></p>
</section>

<section class="card ia-bloque" id="vigilancia">
    <h3><i class="fa-solid fa-satellite-dish"></i> Vigilancia del proyecto</h3>
    <?php if (!$v): ?>
        <p class="text-muted">Todavía no se ha hecho ninguna comprobación. Actívala abajo o pulsa «Comprobar ahora».</p>
    <?php else: ?>
        <p class="text-muted text-sm">Última comprobación: <?php echo s($fmt($v['revisado'])); ?> (reglas del sistema, sin IA). Lo nuevo se compara con el último estado que alguien vio.</p>
        <?php if (!$v['total_nuevos'] && !$v['cambios']): ?>
            <p>Sin novedades.</p>
        <?php else: ?>
            <ul class="ia-lista">
                <?php foreach ($v['cambios'] as $x): ?><li><span class="prg-sev"><?php echo s($sevNombre[$x['severidad']] ?? $x['severidad']); ?></span> <strong><?php echo s($x['titulo']); ?></strong> <small class="text-muted"><?php echo s($x['descripcion']); ?></small></li><?php endforeach; ?>
                <?php foreach (array_slice($v['hallazgos'], 0, 8) as $x): ?><li><span class="prg-sev"><?php echo s($sevNombre[$x['severidad']] ?? $x['severidad']); ?></span> <?php echo s($x['titulo']); ?> <small class="text-muted">· <?php echo s($x['categoria']); ?></small></li><?php endforeach; ?>
            </ul>
            <?php if ($v['total_nuevos'] > 8): ?><p class="text-muted text-sm">y <?php echo (int)($v['total_nuevos'] - 8); ?> más…</p><?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    <form method="post" action="/resumen-ia/vigilancia" class="ia-vig">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label class="ia-vig__activa"><input type="checkbox" name="activa" value="1"<?php echo $vs['activa'] ? ' checked' : ''; ?>> Vigilar en el servidor (sin depender de la app de escritorio)</label>
        <label>Cada <select name="minutos"><?php foreach (VigilanciaServidor::MINUTOS as $m => $n): ?><option value="<?php echo $m; ?>"<?php echo $vs['minutos'] === $m ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-sm btn-primary" type="submit" name="accion" value="guardar">Guardar</button>
        <button class="btn btn-sm btn-ghost" type="submit" name="accion" value="ahora"><i class="fa-solid fa-magnifying-glass"></i> Comprobar ahora</button>
        <?php if ($v && ($v['total_nuevos'] || $v['cambios'])): ?><button class="btn btn-sm btn-ghost" type="submit" name="accion" value="visto"><i class="fa-solid fa-eye"></i> Marcar como visto</button><?php endif; ?>
    </form>
    <p class="text-muted text-sm">
        <?php echo $vs['activa'] ? 'Activa' . ($vs['ultima'] ? ' · última vez ' . s($fmt($vs['ultima'])) . ($vs['origen'] === 'cron' ? ' (tarea programada)' : ' (al abrir esta pantalla)') : '') . ($prox ? ' · próxima: ' . ($prox <= time() ? 'en cuanto corra la tarea o entres aquí' : s(date('d/m/Y H:i', $prox))) : '') : 'Apagada.'; ?>
        Para que corra aunque nadie entre, agrega al cron del servidor:
        <code style="display:block;margin-top:.5rem;user-select:all">*/15 * * * * php <?php echo s(realpath(__DIR__ . '/../../bin/vigilancia_programada.php') ?: '/ruta/al/proyecto/bin/vigilancia_programada.php'); ?></code>
        Cada proyecto respeta su propio intervalo; sin cron, la comprobación se hace la primera vez que entras aquí después de vencido el intervalo.
    </p>
</section>

<section class="card ia-bloque">
    <h3><i class="fa-solid fa-stopwatch"></i> Uso de la IA</h3>
    <?php if (empty($u['llamadas'])): ?>
        <p class="text-muted">No hay llamadas al modelo registradas en este periodo (las informa la app de escritorio).</p>
    <?php else: ?>
        <p><strong><?php echo (int)$u['llamadas']; ?></strong> llamada(s) · tiempo total <?php echo s(UsoIA::duracion((float)$u['ms'])); ?> · promedio <?php echo s(UsoIA::duracion((float)$u['promedio_ms'])); ?><?php echo (int)$u['errores'] ? ' · <strong>' . (int)$u['errores'] . ' con error</strong>' : ''; ?></p>
    <?php endif; ?>
    <p><a class="btn btn-sm btn-ghost" href="/uso-ia?proyecto=<?php echo $pid; ?>">Ver el medidor completo →</a></p>
</section>
