<?php
/** Accesos rápidos. Variables: $proyectoInicio */
?>
<section class="card inicio-panel">
    <header class="inicio-panel-cab">
        <div>
            <h3><i class="fa-solid fa-bolt" aria-hidden="true"></i> <?php echo s(t('Accesos rápidos')); ?></h3>
            <p class="text-muted"><?php echo s(t('Lo que más se usa, a un clic.')); ?></p>
        </div>
    </header>
    <div class="inicio-panel-cuerpo">
        <div class="inicio-atajos">
            <?php if (puede_crear_proyecto()): ?>
                <a href="/proyectos/crear" class="inicio-atajo inicio-atajo--primario"><i class="fa-solid fa-plus" aria-hidden="true"></i><span><?php echo s(t('Nuevo proyecto')); ?></span></a>
            <?php endif; ?>
            <?php if ($proyectoInicio): ?>
                <a href="<?php echo s(url_inicio_proyecto((int)$proyectoInicio->id)); ?>" class="inicio-atajo"><i class="fa-solid fa-folder-open" aria-hidden="true"></i><span><?php echo s(t('Abrir última obra')); ?></span></a>
                <a href="/programa?proyecto=<?php echo (int)$proyectoInicio->id; ?>" class="inicio-atajo"><i class="fa-solid fa-chart-gantt" aria-hidden="true"></i><span><?php echo s(t('Programa')); ?></span></a>
                <a href="/reportes?proyecto=<?php echo (int)$proyectoInicio->id; ?>" class="inicio-atajo"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><span><?php echo s(t('Reporte diario')); ?></span></a>
                <a href="/exportar?proyecto=<?php echo (int)$proyectoInicio->id; ?>" class="inicio-atajo"><i class="fa-solid fa-file-export" aria-hidden="true"></i><span><?php echo s(t('Exportar')); ?></span></a>
            <?php endif; ?>
            <?php if (!is_visitante()): ?>
                <a href="/plantillas-proyecto" class="inicio-atajo"><i class="fa-solid fa-copy" aria-hidden="true"></i><span><?php echo s(t('Plantillas')); ?></span></a>
            <?php endif; ?>
            <a href="/proyectos" class="inicio-atajo"><i class="fa-solid fa-building" aria-hidden="true"></i><span><?php echo s(t('Todos los proyectos')); ?></span></a>
            <a href="/manual" class="inicio-atajo"><i class="fa-solid fa-book-open" aria-hidden="true"></i><span><?php echo s(t('Manual')); ?></span></a>
            <a href="/novedades" class="inicio-atajo"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i><span><?php echo s(t('Novedades')); ?></span></a>
        </div>
    </div>
</section>
