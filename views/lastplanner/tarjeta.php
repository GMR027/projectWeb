<?php
// Variables: $t (tarjeta), $logo (nombre de archivo del logo del proyecto)
$dias = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
$meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$ts = strtotime($t['fecha']);
?>
<div class="lp-tarjeta">
    <div class="lp-fila lp-sup">
        <div class="lp-caja"><span class="lp-rot">PARTIDA:</span><strong><?php echo $t['partida'] ? s($t['partida']->nombre) : 'TODAS LAS PARTIDAS'; ?></strong></div>
        <div class="lp-caja"><span class="lp-rot">FECHA:</span><strong class="lp-fecha"><?php echo date('d/m/Y', $ts); ?></strong></div>
    </div>
    <div class="lp-cuerpo">
        <div class="lp-cab"><span>ACTIVIDAD:</span><span>DÍAS PARA TERMINAR:</span></div>
        <?php foreach ($t['items'] as $it): ?>
            <div class="lp-act">
                <span class="lp-nom">
                    <?php if ($it['completada']): ?><i class="lp-rombo lp-verde" title="Actividad finalizada">◆</i><?php endif; ?>
                    <?php if ($it['proceso']): ?><i class="lp-rombo lp-amarillo" title="Actividad en proceso">◆</i><?php endif; ?>
                    <?php if ($it['restriccion']): ?><i class="lp-rombo lp-rojo" title="Con restricción">◆</i><?php endif; ?>
                    <?php if (!empty($it['partida'])): ?><span class="lp-cod" title="<?php echo s($it['partida']->nombre); ?>"><?php echo s($it['partida']->codigo); ?></span><?php endif; ?>
                    <?php echo s($it['actividad']->nombre); ?>
                </span>
                <span class="lp-dias"><?php echo (int)$it['dias']; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="lp-fila lp-inf">
        <div class="lp-caja"><span class="lp-rot">COMPAÑÍA:</span><?php if ($logo): ?><img src="/build/img/logos/<?php echo s($logo); ?>" alt=""><?php endif; ?></div>
        <div class="lp-caja lp-indicador">
            <span class="lp-ind"><i class="lp-rombo lp-verde">◆</i> Finalizada</span>
            <span class="lp-ind"><i class="lp-rombo lp-amarillo">◆</i> En proceso</span>
            <span class="lp-ind"><i class="lp-rombo lp-rojo">◆</i> Con restricción</span>
            <?php if ($t['partes'] > 1): ?><em class="lp-parte"><?php echo $t['parte'] . '/' . $t['partes']; ?></em><?php endif; ?>
        </div>
    </div>
</div>
