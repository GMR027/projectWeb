<?php $id = s($proyecto->id); $puede = puede_gestionar_proyecto($proyecto); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-handshake"></i> <?php echo s($minuta->nombre); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->numero_trabajo ? ' · Trabajo ' . s($proyecto->numero_trabajo) : ''; ?></p>
    </div>
    <div class="la-controles">
        <?php if (puede_descargar()): ?><a href="/minutas/pdf?proyecto=<?php echo $id; ?>&id=<?php echo s($minuta->id); ?>" class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> PDF</a><?php endif; ?>
        <?php if ($puede): ?><a href="/minutas/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($minuta->id); ?>" class="btn btn-ghost">Editar</a><?php endif; ?>
        <a href="/minutas?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Minutas</a>
    </div>
</div>
<div class="rd-ver">
    <div class="rd-principal">
        <div class="card">
            <dl class="ficha-datos">
                <div><dt>Fecha</dt><dd><?php echo s(fecha_larga_es($minuta->fecha)); ?></dd></div>
                <div><dt>Importancia</dt><dd><span class="badge bit-<?php echo s($minuta->importancia); ?>"><?php echo s(\Model\Bitacora::IMPORTANCIAS[$minuta->importancia] ?? ''); ?></span></dd></div>
                <div><dt>Proyecto</dt><dd><?php echo s($proyecto->nombre); ?></dd></div>
                <div><dt>Número de trabajo</dt><dd><?php echo s($proyecto->numero_trabajo ?: '—'); ?></dd></div>
            </dl>
        </div>
        <div class="card">
            <h3>Temas vistos (<?php echo count($temas); ?>)</h3>
            <?php if (!$temas): ?><p class="text-muted">Sin temas registrados.</p><?php else: ?>
                <table class="tabla"><thead><tr><th>Tema</th><th>Responsable</th><th>Departamento</th></tr></thead><tbody>
                <?php foreach ($temas as $t): ?><tr><td style="white-space:pre-wrap"><?php echo s($t['tema'] ?? ''); ?></td><td><?php echo s($t['responsable'] ?? '') ?: '—'; ?></td><td><?php echo s($t['departamento'] ?? '') ?: '—'; ?></td></tr><?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
        <?php if ($compromisos): ?>
        <div class="card">
            <h3>Compromisos (<?php echo count($compromisos); ?>)</h3>
            <table class="tabla"><thead><tr><th>Compromiso</th><th>Responsable</th><th>Departamento</th></tr></thead><tbody>
            <?php foreach ($compromisos as $t): ?><tr><td style="white-space:pre-wrap"><?php echo s($t['tema'] ?? ''); ?></td><td><?php echo s($t['responsable'] ?? '') ?: '—'; ?></td><td><?php echo s($t['departamento'] ?? '') ?: '—'; ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
        <?php endif; ?>
    </div>
    <aside class="rd-lateral card">
        <h3>Personal del contratista (<?php echo count($contratista); ?>)</h3>
        <?php if (!$contratista): ?><p class="text-muted">Sin asistentes.</p><?php endif; ?>
        <?php foreach ($contratista as $p): ?>
            <div class="rd-persona"><span class="rd-avatar-vacio"><i class="fa-solid fa-helmet-safety"></i></span><div><strong><?php echo s($p->nombre); ?></strong><div class="text-sm text-muted"><?php echo s(trim($p->puesto . ($p->puesto && $p->empresa ? ' · ' : '') . $p->empresa)) ?: '—'; ?></div></div></div>
        <?php endforeach; ?>
        <h3 style="margin-top:2rem">Personal del proyecto (<?php echo count($personal); ?>)</h3>
        <?php if (!$personal): ?><p class="text-muted">Sin asistentes.</p><?php endif; ?>
        <?php foreach ($personal as $p): ?>
            <div class="rd-persona">
                <?php if ($p->imagen): ?><img src="/uploads/personal/<?php echo s($p->imagen); ?>" alt=""><?php else: ?><span class="rd-avatar-vacio"><i class="fa-solid fa-user"></i></span><?php endif; ?>
                <div><strong><?php echo s($p->nombre); ?></strong><div class="text-sm text-muted"><?php echo s($p->departamento ?: '—'); ?></div></div>
            </div>
        <?php endforeach; ?>
    </aside>
</div>
