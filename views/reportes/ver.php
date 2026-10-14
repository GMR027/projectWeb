<?php $id = s($proyecto->id); $puede = puede_gestionar_proyecto($proyecto); $fotos = $reporte->getFotos(); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-clipboard-list"></i> Reporte diario · <?php echo s(fecha_larga_es($reporte->fecha)); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->numero_trabajo ? ' · Trabajo ' . s($proyecto->numero_trabajo) : ''; ?></p>
    </div>
    <div class="la-controles">
        <?php if ($puede): ?><a href="/reportes/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($reporte->id); ?>" class="btn btn-ghost">Editar</a><?php endif; ?>
        <a href="/reportes?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Reportes</a>
    </div>
</div>

<div class="rd-ver">
    <div class="rd-principal">
        <div class="card">
            <dl class="ficha-datos">
                <div><dt>Fecha</dt><dd><?php echo s(fecha_corta_es($reporte->fecha)); ?></dd></div>
                <div><dt>Proyecto</dt><dd><?php echo s($proyecto->nombre); ?></dd></div>
                <div><dt>Número de trabajo</dt><dd><?php echo s($proyecto->numero_trabajo ?: '—'); ?></dd></div>
                <div><dt>Área</dt><dd><?php echo s($reporte->area ?: '—'); ?></dd></div>
            </dl>
        </div>

        <div class="card">
            <h3>Actividades del programa</h3>
            <?php if (!$actividades): ?><p class="text-muted">Sin actividades.</p><?php endif; ?>
            <ul class="rd-acts">
                <?php foreach ($actividades as [$a, $p]): ?>
                    <li>
                        <span class="mono"><?php echo s($a->codigo); ?></span> <strong><?php echo s($a->nombre); ?></strong>
                        <div class="text-sm text-muted"><?php echo s($p->nombre); ?> · <?php echo s(fecha_corta_es($a->fecha_inicio)); ?> → <?php echo s(fecha_corta_es($a->fecha_fin)); ?> · <?php echo $a->esHito() ? 'Hito' : (int)$a->progreso . ' %'; ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card">
            <h3>Comentarios</h3>
            <p style="white-space:pre-wrap"><?php echo trim((string)$reporte->comentarios) !== '' ? s($reporte->comentarios) : '—'; ?></p>
        </div>

        <div class="card">
            <h3>Fotos (<?php echo count($fotos); ?>)</h3>
            <?php if (!$fotos): ?><p class="text-muted">Este reporte no tiene fotos.</p><?php endif; ?>
            <div class="rd-fotos">
                <?php foreach ($fotos as $f): ?>
                    <a class="rd-foto" href="/uploads/reportes/<?php echo s($f); ?>" target="_blank" rel="noopener"><img src="/uploads/reportes/<?php echo s($f); ?>" alt="Foto del reporte" loading="lazy"></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <aside class="rd-lateral card">
        <h3>Personal (<?php echo count($personal); ?>)</h3>
        <?php if (!$personal): ?><p class="text-muted">Sin personal registrado.</p><?php endif; ?>
        <?php foreach ($personal as $p): ?>
            <div class="rd-persona">
                <?php if ($p->imagen): ?><img src="/uploads/personal/<?php echo s($p->imagen); ?>" alt=""><?php else: ?><span class="rd-avatar-vacio"><i class="fa-solid fa-user"></i></span><?php endif; ?>
                <div><strong><?php echo s($p->nombre); ?></strong><div class="text-sm text-muted"><?php echo s($p->departamento ?: '—'); ?></div></div>
            </div>
        <?php endforeach; ?>
    </aside>
</div>
