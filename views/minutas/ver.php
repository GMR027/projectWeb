<?php
$id = s($proyecto->id); $puede = puede_gestionar_proyecto($proyecto);
$nAsistentes = count($contratista) + count($personal);
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-handshake"></i> <?php echo s($minuta->nombre); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->numero_trabajo ? ' · Trabajo ' . s($proyecto->numero_trabajo) : ''; ?> · <?php echo s(fecha_larga_es($minuta->fecha)); ?></p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-comments"></i><b><?php echo count($temas); ?></b> temas</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-list-check"></i><b><?php echo count($compromisos); ?></b> compromisos</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-users"></i><b><?php echo $nAsistentes; ?></b> asistentes</span>
        </div>
    </div>
    <div class="la-controles">
        <?php if (puede_descargar()): ?><a href="/minutas/pdf?proyecto=<?php echo $id; ?>&id=<?php echo s($minuta->id); ?>" class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> PDF</a><?php endif; ?>
        <?php if ($puede): ?><a href="/minutas/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($minuta->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
        <a href="/minutas?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Minutas</a>
    </div>
</div>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-comments" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Temas vistos</small><strong><?php echo count($temas); ?></strong><span>puntos tratados en la reunión</span></span>
    </div>
    <div class="suite-kpi <?php echo $compromisos ? 'is-warning' : 'is-success'; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Compromisos</small><strong><?php echo count($compromisos); ?></strong><span>acuerdos con responsable</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Asistentes</small><strong><?php echo $nAsistentes; ?></strong><span><?php echo count($contratista); ?> del contratista · <?php echo count($personal); ?> del proyecto</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-signal" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Importancia</small><strong><?php echo s(\Model\Bitacora::IMPORTANCIAS[$minuta->importancia] ?? '—'); ?></strong><span>nivel asignado a la minuta</span></span>
    </div>
</div>

<div class="rd-ver">
    <div class="rd-principal">
        <div class="suite-panel">
            <div class="suite-panel-cab"><h3><i class="fa-solid fa-circle-info"></i> Datos de la minuta</h3></div>
            <div class="suite-panel-cuerpo">
                <dl class="ficha-datos">
                    <div><dt>Fecha</dt><dd><?php echo s(fecha_larga_es($minuta->fecha)); ?></dd></div>
                    <div><dt>Importancia</dt><dd><span class="badge bit-<?php echo s($minuta->importancia); ?>"><?php echo s(\Model\Bitacora::IMPORTANCIAS[$minuta->importancia] ?? ''); ?></span></dd></div>
                    <div><dt>Proyecto</dt><dd><?php echo s($proyecto->nombre); ?></dd></div>
                    <div><dt>Número de trabajo</dt><dd><?php echo s($proyecto->numero_trabajo ?: '—'); ?></dd></div>
                </dl>
            </div>
        </div>
        <div class="suite-panel">
            <div class="suite-panel-cab">
                <h3><i class="fa-solid fa-comments"></i> Temas vistos <span class="suite-chip"><?php echo count($temas); ?></span></h3>
            </div>
            <div class="suite-panel-cuerpo">
                <?php if (!$temas): ?><p class="text-muted">Sin temas registrados.</p><?php else: ?>
                    <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Tema</th><th>Responsable</th><th>Departamento</th></tr></thead><tbody>
                    <?php foreach ($temas as $t): ?><tr><td style="white-space:pre-wrap"><?php echo s($t['tema'] ?? ''); ?></td><td><?php echo s($t['responsable'] ?? '') ?: '—'; ?></td><td><?php echo s($t['departamento'] ?? '') ?: '—'; ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($compromisos): ?>
        <div class="suite-panel">
            <div class="suite-panel-cab">
                <h3><i class="fa-solid fa-list-check"></i> Compromisos <span class="suite-chip is-warning"><?php echo count($compromisos); ?></span></h3>
            </div>
            <div class="suite-panel-cuerpo">
                <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Compromiso</th><th>Responsable</th><th>Departamento</th></tr></thead><tbody>
                <?php foreach ($compromisos as $t): ?><tr><td style="white-space:pre-wrap"><?php echo s($t['tema'] ?? ''); ?></td><td><?php echo s($t['responsable'] ?? '') ?: '—'; ?></td><td><?php echo s($t['departamento'] ?? '') ?: '—'; ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <aside class="rd-lateral suite-panel">
        <div class="suite-panel-cab"><h3><i class="fa-solid fa-users"></i> Asistentes</h3></div>
        <div class="suite-panel-cuerpo">
            <h4 class="text-muted text-sm" style="text-transform:uppercase;letter-spacing:.06em">Personal del contratista (<?php echo count($contratista); ?>)</h4>
            <?php if (!$contratista): ?><p class="text-muted">Sin asistentes.</p><?php endif; ?>
            <?php foreach ($contratista as $p): ?>
                <div class="rd-persona"><span class="rd-avatar-vacio"><i class="fa-solid fa-helmet-safety"></i></span><div><strong><?php echo s($p->nombre); ?></strong><div class="text-sm text-muted"><?php echo s(trim($p->puesto . ($p->puesto && $p->empresa ? ' · ' : '') . $p->empresa)) ?: '—'; ?></div></div></div>
            <?php endforeach; ?>
            <h4 class="text-muted text-sm" style="margin-top:1.6rem;text-transform:uppercase;letter-spacing:.06em">Personal del proyecto (<?php echo count($personal); ?>)</h4>
            <?php if (!$personal): ?><p class="text-muted">Sin asistentes.</p><?php endif; ?>
            <?php foreach ($personal as $p): ?>
                <div class="rd-persona">
                    <?php if ($p->imagen): ?><img src="/uploads/personal/<?php echo s($p->imagen); ?>" alt=""><?php else: ?><span class="rd-avatar-vacio"><i class="fa-solid fa-user"></i></span><?php endif; ?>
                    <div><strong><?php echo s($p->nombre); ?></strong><div class="text-sm text-muted"><?php echo s($p->departamento ?: '—'); ?></div></div>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>
</div>
