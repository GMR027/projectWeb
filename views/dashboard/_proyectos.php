<?php
/** Proyectos recientes. Variables: $proyectos, $inicio, $fechaCorta */
$hoyISO = date('Y-m-d');
$total = count($proyectos);
$visibles = array_slice($proyectos, 0, (int)$inicio['proyectos_n']);
?>
<section class="card inicio-panel">
    <header class="inicio-panel-cab">
        <div>
            <h3><i class="fa-solid fa-building" aria-hidden="true"></i> <?php echo s(t('Proyectos recientes')); ?></h3>
            <p class="text-muted"><?php echo $total; ?> <?php echo s($total === 1 ? t('obra a tu cargo') : t('obras a tu cargo')); ?></p>
        </div>
        <a class="inicio-panel-accion" href="/proyectos"><?php echo s(t('Ver todos')); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </header>
    <?php if (!$proyectos): ?>
        <div class="inicio-panel-cuerpo">
            <div class="inicio-vacio-lista"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><p><?php echo s(t('Aún no tienes proyectos. Crea el primero para empezar.')); ?></p></div>
        </div>
    <?php else: ?>
        <div class="inicio-proyectos">
            <?php foreach ($visibles as $proyecto):
                $prog = max(0, min(100, (int)$proyecto->getProgreso()));
                $archivado = method_exists($proyecto, 'estaArchivado') && $proyecto->estaArchivado();
                if ($archivado) {
                    $estado = ['Archivada', 'archivada'];
                } elseif (!empty($proyecto->fecha_inicio) && $proyecto->fecha_inicio > $hoyISO) {
                    $estado = ['Por iniciar', 'espera'];
                } elseif (!empty($proyecto->fecha_fin) && $proyecto->fecha_fin < $hoyISO) {
                    $estado = ['Finalizada', 'fin'];
                } else {
                    $estado = ['En ejecución', 'ok'];
                }
                $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($proyecto->color ?? '')) ? $proyecto->color : 'var(--primary)';
            ?>
            <article class="inicio-proyecto" style="--pc: <?php echo s($color); ?>">
                <div class="inicio-proyecto-cab">
                    <span class="inicio-proyecto-codigo mono"><?php echo s($proyecto->codigo); ?></span>
                    <span class="inicio-estado inicio-estado--<?php echo s($estado[1]); ?>"><span aria-hidden="true"></span><?php echo s($estado[0]); ?></span>
                </div>
                <h4><a href="<?php echo s(url_inicio_proyecto((int)$proyecto->id)); ?>"><?php echo s($proyecto->nombre); ?></a></h4>
                <?php if (!empty($proyecto->ubicacion)): ?>
                    <p class="inicio-proyecto-ubi"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo s($proyecto->ubicacion); ?></p>
                <?php endif; ?>
                <div class="inicio-progreso">
                    <span><?php echo s(t('Avance general')); ?></span>
                    <strong class="mono"><?php echo $prog; ?>%</strong>
                </div>
                <div class="barra-progreso"><div class="barra-fill" style="width: <?php echo $prog; ?>%"></div></div>
                <div class="inicio-proyecto-fechas">
                    <span><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> <?php echo s($fechaCorta($proyecto->fecha_inicio)); ?></span>
                    <span><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i> <?php echo s($fechaCorta($proyecto->fecha_fin)); ?></span>
                </div>
                <?php $saludCompacto = true; include __DIR__ . '/../salud/_semaforo.php'; ?>
                <?php include __DIR__ . '/../revisiones/_mini.php'; ?>
                <div class="inicio-proyecto-acciones">
                    <a href="<?php echo s(url_inicio_proyecto((int)$proyecto->id)); ?>" class="btn btn-sm btn-primary"><?php echo s(t('Abrir')); ?></a>
                    <a href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-chart-gantt" aria-hidden="true"></i> <?php echo s(t('Programa')); ?></a>
                    <?php if (puede_descargar()): ?>
                        <a href="/ajustes?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-sm btn-ghost" title="<?php echo s(t('Ajustes del proyecto')); ?>" aria-label="<?php echo s(t('Ajustes del proyecto')); ?>"><i class="fa-solid fa-gear" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
            <?php unset($proyecto); ?>
        </div>
        <?php if ($total > count($visibles)): ?>
            <div class="inicio-panel-pie">
                <a href="/proyectos" class="btn btn-sm btn-ghost"><?php echo s(t('Ver todos los proyectos')); ?> (<?php echo $total; ?>) <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
