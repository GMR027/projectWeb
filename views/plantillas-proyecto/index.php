<?php
/** Plantillas de proyecto. Variables: $proyecto (o null), $disponible, $plantillas, $usuario, $vacio, $mensaje */
$esAdmin = ($usuario['rol'] ?? '') === 'admin';
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-copy"></i> <?php echo s(t('Plantillas de proyecto')); ?></h2>
        <p class="text-muted"><?php echo s(t('Guarda la estructura de un proyecto terminado (partidas, actividades, dependencias, jornada, diseños, campos, catálogos y materiales) y arranca los siguientes desde ahí. No se copia el avance, los reportes, las fotos ni los costos.')); ?></p>
    </div>
    <a href="/proyectos" class="btn btn-ghost"><?php echo s(t('← Proyectos')); ?></a>
</div>
<?php if (!$disponible): ?>
    <div class="card"><p><?php echo s(t('Falta aplicar la migración')); ?> <code>database/migracion_personalizacion.sql</code>.</p></div>
<?php return; endif; ?>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<?php if ($proyecto): ?>
<section class="card">
    <h3>Guardar «<?php echo s($proyecto->nombre); ?>» como plantilla</h3>
    <form method="POST" action="/plantillas-proyecto/guardar" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <div class="form-grid">
            <div class="campo"><label for="pp-nombre"><?php echo s(t('Nombre de la plantilla')); ?></label><input type="text" id="pp-nombre" name="nombre" maxlength="150" required value="<?php echo s($proyecto->nombre); ?>"></div>
            <div class="campo"><label for="pp-desc"><?php echo s(t('Descripción')); ?></label><input type="text" id="pp-desc" name="descripcion" maxlength="500" placeholder="Para qué tipo de obra sirve"></div>
            <?php if ($esAdmin): ?><label class="campo-check campo-full"><input type="checkbox" name="compartida" value="1"> <?php echo s(t('Compartir con todos los usuarios que pueden crear proyectos')); ?></label><?php endif; ?>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?php echo s(t('Guardar plantilla')); ?></button>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <h3><?php echo s(t('Plantillas disponibles')); ?></h3>
    <?php if (!$plantillas): ?><p class="text-muted"><?php echo s(t('Aún no hay plantillas. Abre un proyecto terminado y usa «Guardar como plantilla» en sus Ajustes.')); ?></p><?php endif; ?>
    <div class="tabla-wrapper"><table class="tabla">
        <?php if ($plantillas): ?><thead><tr><th><?php echo s(t('Plantilla')); ?></th><th class="hide-m"><?php echo s(t('Contenido')); ?></th><th class="hide-m"><?php echo s(t('Autor')); ?></th><th></th></tr></thead><?php endif; ?>
        <tbody>
        <?php foreach ($plantillas as $pl): $r = json_decode((string)$pl['resumen_json'], true) ?: []; ?>
            <tr>
                <td><strong><?php echo s($pl['nombre']); ?></strong><?php echo (int)$pl['compartida'] ? ' <span class="cb-tag">Compartida</span>' : ''; ?><br><small class="text-muted"><?php echo s($pl['descripcion'] ?: ('De: ' . $pl['origen'])); ?></small></td>
                <td class="hide-m text-sm"><?php echo (int)($r['partidas'] ?? 0); ?> partidas · <?php echo (int)($r['actividades'] ?? 0); ?> actividades · <?php echo (int)($r['dependencias'] ?? 0); ?> dependencias<?php echo !empty($r['materiales']) ? ' · ' . (int)$r['materiales'] . ' materiales' : ''; ?><?php echo !empty($r['campos']) ? ' · ' . (int)$r['campos'] . ' campos' : ''; ?><br><small class="text-muted">Duración: <?php echo (int)($r['dias'] ?? 0); ?> días</small></td>
                <td class="hide-m text-sm"><?php echo s($pl['autor'] ?? '—'); ?><br><small class="text-muted"><?php echo formatear_fecha(substr($pl['creado'], 0, 10)); ?></small></td>
                <td class="acciones">
                    <?php if ($proyecto && $vacio): ?>
                        <form method="POST" action="/plantillas-proyecto/aplicar" style="display:inline" data-confirm="¿Aplicar esta plantilla?|Se cargará su estructura en «<?php echo s($proyecto->nombre); ?>», que está vacío. Las fechas se cuentan desde el inicio del proyecto.|Aplicar">
                            <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$pl['id']; ?>">
                            <button class="btn btn-sm btn-primary" type="submit"><?php echo s(t('Aplicar a este proyecto')); ?></button></form>
                    <?php endif; ?>
                    <?php if ($esAdmin || (int)$pl['usuario_id'] === (int)$usuario['id']): ?>
                        <form method="POST" action="/plantillas-proyecto/eliminar" style="display:inline" data-confirm="¿Eliminar la plantilla?|Los proyectos ya creados con ella no cambian.|Eliminar">
                            <input type="hidden" name="id" value="<?php echo (int)$pl['id']; ?>"><?php if ($proyecto): ?><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><?php endif; ?>
                            <button class="btn btn-sm btn-danger" type="submit">✕</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if ($proyecto && !$vacio && $plantillas): ?><p class="text-sm text-muted"><?php echo s(t('Una plantilla solo se aplica a un proyecto vacío. Para usarla, crea un proyecto nuevo y elígela en el formulario.')); ?></p><?php endif; ?>
</section>
