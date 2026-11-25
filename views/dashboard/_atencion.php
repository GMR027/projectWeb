<?php
/** Actividades de hoy y atrasadas. Variables: $datos, $proyectoInicio, $fechaCorta */
$filasHoy = $datos['hoy'] ?? [];
$filasAtrasadas = $datos['atrasadas'] ?? [];
$nHoy = (int)($datos['n_hoy'] ?? count($filasHoy));
$nAtrasadas = (int)($datos['n_atrasadas'] ?? count($filasAtrasadas));
$tabInicial = $nHoy > 0 ? 'hoy' : ($nAtrasadas > 0 ? 'atrasadas' : 'hoy');
$hoyISO = date('Y-m-d');

$pintarLista = function (array $filas, string $tipo) use ($fechaCorta, $hoyISO) {
    if (!$filas) {
        $texto = $tipo === 'hoy' ? 'Nada programado para hoy.' : 'Sin actividades atrasadas.';
        echo '<div class="inicio-vacio-lista"><i class="fa-regular fa-circle-check" aria-hidden="true"></i><p>' . s(t($texto)) . '</p></div>';
        return;
    }
    echo '<ul class="inicio-lista">';
    foreach ($filas as $f) {
        $prog = max(0, min(100, (int)($f['progreso'] ?? 0)));
        $diasTarde = $tipo === 'atrasadas' ? (int)floor((strtotime($hoyISO) - strtotime((string)$f['fecha_fin'])) / 86400) : 0;
        $meta = $tipo === 'atrasadas'
            ? ($diasTarde === 1 ? t('hace 1 día') : sprintf(t('hace %d días'), max(1, $diasTarde)))
            : t('en curso');
        ?>
        <li class="inicio-fila inicio-fila--<?php echo s($tipo); ?>">
            <span class="inicio-fila-ico"><i class="fa-solid <?php echo $tipo === 'hoy' ? 'fa-sun' : 'fa-triangle-exclamation'; ?>" aria-hidden="true"></i></span>
            <div class="inicio-fila-txt">
                <a href="/programa?proyecto=<?php echo (int)$f['proyecto_id']; ?>#fila-a<?php echo (int)$f['id']; ?>">
                    <span class="mono"><?php echo s($f['codigo']); ?></span> <?php echo s($f['nombre']); ?>
                </a>
                <small><span class="inicio-punto"></span><?php echo s($f['proyecto']); ?> · <?php echo s($meta); ?> · <?php echo s($fechaCorta($f['fecha_fin'])); ?></small>
            </div>
            <div class="inicio-fila-avance">
                <span class="mono"><?php echo $prog; ?>%</span>
                <span class="inicio-mini-barra"><i style="width:<?php echo $prog; ?>%"></i></span>
            </div>
        </li>
        <?php
    }
    echo '</ul>';
};
?>
<section class="card inicio-panel" id="atencion">
    <header class="inicio-panel-cab">
        <div>
            <h3><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?php echo s(t('Requiere atención')); ?></h3>
            <p class="text-muted"><?php echo s(t('Lo que se ejecuta hoy y lo que ya venció en tus obras.')); ?></p>
        </div>
        <?php if ($proyectoInicio): ?>
            <a class="inicio-panel-accion" href="/programa?proyecto=<?php echo (int)$proyectoInicio->id; ?>" data-inicio-proyecto-enlace><?php echo s(t('Ver en Programa')); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        <?php endif; ?>
    </header>
    <div class="inicio-tabs" role="tablist" aria-label="<?php echo s(t('Filtrar actividades')); ?>">
        <button type="button" class="inicio-tab<?php echo $tabInicial === 'hoy' ? ' activo' : ''; ?>" id="inicio-tab-hoy"
                role="tab" aria-selected="<?php echo $tabInicial === 'hoy' ? 'true' : 'false'; ?>" aria-controls="inicio-panel-hoy"
                data-inicio-tab-btn="hoy">
            <i class="fa-solid fa-sun" aria-hidden="true"></i> <?php echo s(t('Hoy')); ?> <span><?php echo $nHoy; ?></span>
        </button>
        <button type="button" class="inicio-tab<?php echo $tabInicial === 'atrasadas' ? ' activo' : ''; ?>" id="inicio-tab-atrasadas"
                role="tab" aria-selected="<?php echo $tabInicial === 'atrasadas' ? 'true' : 'false'; ?>" aria-controls="inicio-panel-atrasadas"
                data-inicio-tab-btn="atrasadas">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?php echo s(t('Atrasadas')); ?> <span><?php echo $nAtrasadas; ?></span>
        </button>
    </div>
    <div class="inicio-panel-cuerpo">
        <div id="inicio-panel-hoy" role="tabpanel" aria-labelledby="inicio-tab-hoy" data-inicio-panel="hoy"<?php echo $tabInicial === 'hoy' ? '' : ' hidden'; ?>>
            <?php $pintarLista($filasHoy, 'hoy'); ?>
        </div>
        <div id="inicio-panel-atrasadas" role="tabpanel" aria-labelledby="inicio-tab-atrasadas" data-inicio-panel="atrasadas"<?php echo $tabInicial === 'atrasadas' ? '' : ' hidden'; ?>>
            <?php $pintarLista($filasAtrasadas, 'atrasadas'); ?>
        </div>
    </div>
</section>
