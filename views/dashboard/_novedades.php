<?php
/** Novedades de la plataforma. Variable: $novedades */
?>
<section class="card inicio-panel">
    <header class="inicio-panel-cab">
        <div>
            <h3><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> <?php echo s(t('Novedades')); ?></h3>
            <p class="text-muted"><?php echo s(t('Lo último de la plataforma.')); ?></p>
        </div>
        <a class="inicio-panel-accion" href="/novedades"><?php echo s(t('Ver todas')); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </header>
    <div class="inicio-panel-cuerpo">
        <?php if (!$novedades): ?>
            <div class="inicio-vacio-lista"><i class="fa-regular fa-bell" aria-hidden="true"></i><p><?php echo s(t('No hay novedades por ahora.')); ?></p></div>
        <?php else: ?>
            <ul class="inicio-novedades">
                <?php foreach ($novedades as $n): [$etq, $ico] = \Classes\Novedades::tipo($n['tipo'] ?? ''); ?>
                    <li class="inicio-novedad">
                        <span class="inicio-novedad-ico"><i class="fa-solid <?php echo s($ico); ?>" aria-hidden="true"></i></span>
                        <div>
                            <a href="/novedades#<?php echo s(\Classes\Novedades::ancla($n)); ?>"><strong><?php echo s($n['titulo']); ?></strong></a>
                            <small><?php echo s($etq); ?> · <?php echo s(formatear_fecha(substr($n['fecha'], 0, 10), 'd/m')); ?><?php echo !empty($n['nueva']) ? ' <span class="inicio-tag">' . s(t('Nueva')) . '</span>' : ''; ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
