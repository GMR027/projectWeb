<?php
/** Cabecera del inicio. Variables: $usuario, $totalProyectos, $proyectosActivos, $datos, $personalizando */
$hora = (int)date('G');
$saludo = $hora < 12 ? t('Buenos días') : ($hora < 19 ? t('Buenas tardes') : t('Buenas noches'));
$nombre = explode(' ', trim((string)($usuario['nombre'] ?? '')), 2)[0];
$org = function_exists('organizacion_actual') ? organizacion_actual() : null;
$orgNombre = $org ? (string)($org->nombre ?? '') : '';
$planNombre = $org ? ($org->plan()['nombre'] ?? '') : '';
$atrasadas = (int)($datos['n_atrasadas'] ?? 0);
?>
<header class="inicio-hero">
    <div class="inicio-hero-cab">
        <div class="inicio-hero-txt">
            <span class="inicio-eyebrow"><i class="fa-solid fa-compass" aria-hidden="true"></i> <?php echo s(t('Inicio / Centro de mando')); ?></span>
            <h2 id="inicio-titulo"><?php echo s($nombre !== '' ? $saludo . ', ' . $nombre : $saludo); ?></h2>
            <p><?php echo s(t('Tu cartera de obras, lo que requiere atención y el acceso a cada herramienta.')); ?></p>
            <div class="inicio-chips">
                <span class="inicio-chip"><i class="fa-solid fa-building" aria-hidden="true"></i> <?php echo (int)$totalProyectos; ?> <?php echo s($totalProyectos === 1 ? t('proyecto') : t('proyectos')); ?></span>
                <span class="inicio-chip inicio-chip--ok"><i class="fa-solid fa-person-digging" aria-hidden="true"></i> <?php echo (int)$proyectosActivos; ?> <?php echo s(t('en ejecución')); ?></span>
                <?php if ($atrasadas): ?>
                    <span class="inicio-chip inicio-chip--danger"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?php echo $atrasadas; ?> <?php echo s(t('atrasadas')); ?></span>
                <?php endif; ?>
                <?php if ($orgNombre): ?>
                    <span class="inicio-chip"><i class="fa-solid fa-building" aria-hidden="true"></i> <?php echo s($orgNombre); ?></span>
                <?php endif; ?>
                <?php if ($planNombre): ?>
                    <span class="inicio-chip"><i class="fa-solid fa-gem" aria-hidden="true"></i> <?php echo s(t('Plan')); ?> <?php echo s($planNombre); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="inicio-hero-acciones">
            <?php if (puede_crear_proyecto()): ?>
                <a href="/proyectos/crear" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> <?php echo s(t('Nuevo proyecto')); ?></a>
            <?php endif; ?>
            <button type="button" class="btn btn-ghost" data-inicio-herramientas>
                <i class="fa-solid fa-grip" aria-hidden="true"></i> <?php echo s(t('Herramientas')); ?>
                <kbd class="inicio-kbd">⌘K</kbd>
            </button>
            <a href="/dashboard?personalizar=1#inicio-personalizar" class="btn btn-ghost" data-inicio-personalizar>
                <i class="fa-solid fa-sliders" aria-hidden="true"></i> <?php echo s(t('Personalizar inicio')); ?>
            </a>
        </div>
    </div>
    <div class="inicio-hero-pie">
        <span class="inicio-fecha"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?php echo s(fecha_larga_es()); ?></span>
        <span class="inicio-pista"><i class="fa-solid fa-keyboard" aria-hidden="true"></i> <?php echo s(t('Ctrl/⌘ + K abre todas las herramientas')); ?></span>
    </div>
</header>
