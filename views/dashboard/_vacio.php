<?php
/** Primer arranque: el usuario aún no tiene proyectos. */
?>
<section class="card inicio-vacio">
    <div class="inicio-vacio-cab">
        <div class="inicio-vacio-txt">
            <span class="inicio-eyebrow"><i class="fa-solid fa-compass-drafting" aria-hidden="true"></i> <?php echo s(t('Inicio / Primeros pasos')); ?></span>
            <h3><?php echo s(t('Tu primera obra empieza aquí')); ?></h3>
            <p><?php echo s(t('Crea un proyecto para programar actividades, reportar desde campo y controlar el avance.')); ?></p>
            <div class="inicio-vacio-acciones">
                <?php if (puede_crear_proyecto()): ?>
                    <a href="/proyectos/crear?inicio=guiado" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> <?php echo s(t('Crear mi primer proyecto')); ?></a>
                <?php endif; ?>
                <a href="/plantillas-proyecto" class="btn btn-ghost"><i class="fa-solid fa-copy" aria-hidden="true"></i> <?php echo s(t('Ver plantillas')); ?></a>
            </div>
        </div>
        <div class="inicio-vacio-ilustracion" aria-hidden="true">
            <span class="inicio-vacio-sol"></span>
            <i class="fa-solid fa-helmet-safety"></i>
            <span class="inicio-vacio-linea inicio-vacio-linea--1"></span>
            <span class="inicio-vacio-linea inicio-vacio-linea--2"></span>
            <span class="inicio-vacio-linea inicio-vacio-linea--3"></span>
        </div>
    </div>
    <ol class="inicio-pasos">
        <li>
            <span class="inicio-paso-num">1</span>
            <div><strong><?php echo s(t('Crea tu proyecto')); ?></strong><small><?php echo s(t('Nombre, ubicación, fechas y jornada de trabajo.')); ?></small></div>
        </li>
        <li>
            <span class="inicio-paso-num">2</span>
            <div><strong><?php echo s(t('Carga tu programa')); ?></strong><small><?php echo s(t('Con una plantilla por tipo de obra, un archivo de Primavera P6 o capturando partidas.')); ?></small></div>
        </li>
        <li>
            <span class="inicio-paso-num">3</span>
            <div><strong><?php echo s(t('Invita a tu equipo')); ?></strong><small><?php echo s(t('Residentes y supervisores con permisos por herramienta.')); ?></small></div>
        </li>
    </ol>
    <div class="inicio-vacio-atajos">
        <a href="/proyectos/crear?inicio=guiado"><i class="fa-solid fa-compass" aria-hidden="true"></i><strong><?php echo s(t('Por dónde empezar')); ?></strong><small><?php echo s(t('La guía paso a paso.')); ?></small></a>
        <a href="/manual"><i class="fa-solid fa-book-open" aria-hidden="true"></i><strong><?php echo s(t('Manual de usuario')); ?></strong><small><?php echo s(t('Cada herramienta explicada.')); ?></small></a>
        <a href="/plantillas-proyecto"><i class="fa-solid fa-copy" aria-hidden="true"></i><strong><?php echo s(t('Plantillas de proyecto')); ?></strong><small><?php echo s(t('Empieza con una base.')); ?></small></a>
        <a href="/novedades"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i><strong><?php echo s(t('Novedades')); ?></strong><small><?php echo s(t('Lo último de Project Web.')); ?></small></a>
    </div>
</section>
