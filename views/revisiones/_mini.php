<?php
/** Resumen corto de revisiones de un proyecto (panel de inicio). Variable: $proyecto */
$rv = \Classes\Revisiones::resumen((int)$proyecto->id);
$mat = $rv['materiales']; $act = $rv['actividades']; $her = $rv['herramientas'];
if (!$mat['total'] && !$act['total']) return;
$linea = function (string $titulo, array $c, string $pest) use ($proyecto) { if (!$c['total']) return; ?>
    <a class="rev-mini" href="/revisiones?proyecto=<?php echo (int)$proyecto->id; ?>&pestana=<?php echo $pest; ?>">
        <span class="text-sm"><?php echo s($titulo); ?></span>
        <span class="rev-chip rev-ok" title="Revisados"><i class="fa-solid fa-eye"></i> <?php echo (int)$c['revisados']; ?>/<?php echo (int)$c['total']; ?></span>
        <?php if ($c['correcto']): ?><span class="rev-chip rev-correcto" title="Correctos"><i class="fa-solid fa-circle-check"></i> <?php echo (int)$c['correcto']; ?></span><?php endif; ?>
        <?php if ($c['revisar']): ?><span class="rev-chip rev-revisar" title="Requieren revisión"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo (int)$c['revisar']; ?></span><?php endif; ?>
        <?php if ($c['no_cumple']): ?><span class="rev-chip rev-no_cumple" title="No cumplen"><i class="fa-solid fa-circle-xmark"></i> <?php echo (int)$c['no_cumple']; ?></span><?php endif; ?>
        <?php if ($c['cambio']): ?><span class="rev-chip rev-cambio" title="Cambiaron después de revisarse"><i class="fa-solid fa-rotate"></i> <?php echo (int)$c['cambio']; ?></span><?php endif; ?>
    </a>
<?php }; ?>
<div class="rev-mini-caja">
    <?php $linea('Materiales', $mat, 'materiales'); $linea('Actividades', $act, 'actividades'); ?>
    <?php if (is_admin() && ($her['desactualizada'] || $her['sin_ejecutar'])): ?>
        <a class="rev-mini" href="/revisiones?proyecto=<?php echo (int)$proyecto->id; ?>&pestana=herramientas"><span class="text-sm">Herramientas de IA</span>
            <?php if ($her['desactualizada']): ?><span class="rev-chip rev-cambio"><i class="fa-solid fa-rotate"></i> <?php echo (int)$her['desactualizada']; ?> por actualizar</span><?php endif; ?>
            <?php if ($her['sin_ejecutar']): ?><span class="rev-chip"><i class="fa-regular fa-circle"></i> <?php echo (int)$her['sin_ejecutar']; ?> sin ejecutar</span><?php endif; ?></a>
    <?php endif; ?>
</div>
