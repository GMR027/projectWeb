<?php
/** KPIs del inicio. Variables: $totalProyectos, $proyectosActivos, $datos, $proyectoInicio */
$nHoy = (int)($datos['n_hoy'] ?? 0);
$nAtrasadas = (int)($datos['n_atrasadas'] ?? 0);
$nRestricciones = (int)($datos['n_restricciones'] ?? 0);
$urlRestricciones = $proyectoInicio ? '/programa?proyecto=' . (int)$proyectoInicio->id : '/proyectos';
?>
<div class="inicio-kpis">
    <a class="inicio-kpi" href="/proyectos">
        <span class="inicio-kpi-ico"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
        <span class="inicio-kpi-txt">
            <span class="inicio-kpi-label"><?php echo s(t('Proyectos')); ?></span>
            <strong class="inicio-kpi-valor"><?php echo (int)$totalProyectos; ?></strong>
            <span class="inicio-kpi-pista"><?php echo (int)$proyectosActivos; ?> <?php echo s(t('en ejecución')); ?></span>
        </span>
    </a>
    <a class="inicio-kpi" href="#atencion" data-inicio-tab="hoy">
        <span class="inicio-kpi-ico"><i class="fa-solid fa-sun" aria-hidden="true"></i></span>
        <span class="inicio-kpi-txt">
            <span class="inicio-kpi-label"><?php echo s(t('Actividades de hoy')); ?></span>
            <strong class="inicio-kpi-valor"><?php echo $nHoy; ?></strong>
            <span class="inicio-kpi-pista"><?php echo s(t('programadas en tus obras')); ?></span>
        </span>
    </a>
    <a class="inicio-kpi<?php echo $nAtrasadas ? ' inicio-kpi--danger' : ''; ?>" href="#atencion" data-inicio-tab="atrasadas">
        <span class="inicio-kpi-ico"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
        <span class="inicio-kpi-txt">
            <span class="inicio-kpi-label"><?php echo s(t('Atrasadas')); ?></span>
            <strong class="inicio-kpi-valor"><?php echo $nAtrasadas; ?></strong>
            <span class="inicio-kpi-pista"><?php echo s(t('vencidas sin terminar')); ?></span>
        </span>
    </a>
    <a class="inicio-kpi<?php echo $nRestricciones ? ' inicio-kpi--warn' : ''; ?>" href="<?php echo s($urlRestricciones); ?>">
        <span class="inicio-kpi-ico"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
        <span class="inicio-kpi-txt">
            <span class="inicio-kpi-label"><?php echo s(t('Restricciones activas')); ?></span>
            <strong class="inicio-kpi-valor"><?php echo $nRestricciones; ?></strong>
            <span class="inicio-kpi-pista"><?php echo s(t('por liberar en obra')); ?></span>
        </span>
    </a>
</div>
