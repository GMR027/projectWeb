<?php
/** Plantillas de proyecto. Variables: $proyecto (o null), $disponible, $plantillas, $usuario, $vacio, $mensaje, $catalogo, $borrador */
$esAdmin = ($usuario['rol'] ?? '') === 'admin';
$catalogo = $catalogo ?? [];
$borrador = $borrador ?? null;
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

<?php if ($borrador): $bd = $borrador['datos']; $fuente = $bd['meta']['fuente'] ?? [];
    $actsPorPartida = []; foreach ($bd['actividades'] as $a) $actsPorPartida[(int)$a['partida']][] = $a; ?>
<section class="card" id="pp-borrador">
    <h3>Revisar la plantilla propuesta</h3>
    <p class="text-muted"><?php echo s($fuente['tipo'] === 'documento' ? 'Desde el documento «' . (string)($fuente['nombre'] ?? '') . '»' : 'Desde el tipo de obra «' . (string)($fuente['nombre'] ?? '') . '»'); ?><?php echo isset($fuente['confianza']) ? ' · ' . (int)$fuente['confianza'] . ' % de coincidencia' : ''; ?>. Ajusta nombres y duraciones antes de guardarla.</p>
    <?php if (!empty($borrador['aviso'])): ?><div class="alerta alerta-aviso"><?php echo s($borrador['aviso']); ?></div><?php endif; ?>
    <form method="POST" action="/plantillas-proyecto/guardar-borrador" class="formulario">
        <input type="hidden" name="token" value="<?php echo s($borrador['token']); ?>">
        <?php if ($proyecto): ?><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="pp-b-nombre"><?php echo s(t('Nombre de la plantilla')); ?></label><input type="text" id="pp-b-nombre" name="nombre" maxlength="150" required value="<?php echo s($borrador['nombre']); ?>"></div>
            <div class="campo"><label for="pp-b-desc"><?php echo s(t('Descripción')); ?></label><input type="text" id="pp-b-desc" name="descripcion" maxlength="500" value="<?php echo s($borrador['descripcion']); ?>"></div>
            <?php if ($esAdmin): ?><label class="campo-check campo-full"><input type="checkbox" name="compartida" value="1"> <?php echo s(t('Compartir con todos los usuarios que pueden crear proyectos')); ?></label><?php endif; ?>
        </div>
        <div class="tabla-wrapper"><table class="tabla">
            <thead><tr><th><?php echo s(t('Actividad')); ?></th><th><?php echo s(t('Duración (días hábiles)')); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($bd['partidas'] as $pa): ?>
                <tr><th colspan="3"><?php echo s($pa['codigo'] . ' ' . $pa['nombre']); ?></th></tr>
                <?php foreach ($actsPorPartida[(int)$pa['ref']] ?? [] as $a): $ref = (int)$a['ref']; ?>
                <tr>
                    <td><input type="text" name="act[<?php echo $ref; ?>][nombre]" maxlength="200" required value="<?php echo s($a['nombre']); ?>" aria-label="<?php echo s(t('Actividad')); ?>"></td>
                    <td><input type="number" name="act[<?php echo $ref; ?>][duracion_dias]" min="1" max="365" required value="<?php echo (int)$a['duracion_dias']; ?>" aria-label="<?php echo s(t('Duración')); ?>"></td>
                    <td><label class="campo-check"><input type="checkbox" name="quitar[<?php echo $ref; ?>]" value="1"> <?php echo s(t('Quitar')); ?></label></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="fila">
            <button class="btn btn-primary" type="submit" name="accion" value="guardar"><i class="fa-solid fa-floppy-disk"></i> <?php echo s(t('Guardar plantilla')); ?></button>
            <button class="btn btn-ghost" type="submit" name="accion" value="descartar" formnovalidate><?php echo s(t('Descartar borrador')); ?></button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <h3><i class="fa-solid fa-layer-group"></i> <?php echo s(t('Empezar desde un tipo de obra')); ?></h3>
    <p class="text-muted"><?php echo s(t('Crea una plantilla con las partidas, actividades y duraciones típicas de un proceso constructivo. Después podrás ajustarla antes de guardarla.')); ?></p>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th><?php echo s(t('Tipo de obra')); ?></th><th class="hide-m"><?php echo s(t('Contenido')); ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($catalogo as $clave => $t): $nActs = array_sum(array_map(fn($p) => count($p[2]), $t['partidas'])); ?>
            <tr>
                <td><strong><?php echo s($t['nombre']); ?></strong><br><small class="text-muted"><?php echo s($t['descripcion']); ?></small></td>
                <td class="hide-m text-sm"><?php echo count($t['partidas']); ?> partidas · <?php echo (int)$nActs; ?> actividades</td>
                <td class="acciones">
                    <form method="POST" action="/plantillas-proyecto/desde-tipo-obra" style="display:inline">
                        <input type="hidden" name="clave" value="<?php echo s($clave); ?>">
                        <?php if ($proyecto): ?><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><?php endif; ?>
                        <button class="btn btn-sm btn-primary" type="submit"><?php echo s(t('Usar como plantilla')); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="card">
    <h3><i class="fa-solid fa-file-lines"></i> <?php echo s(t('Leer un proceso constructivo')); ?></h3>
    <p class="text-muted"><?php echo s(t('Sube el pliego, la memoria o el proceso constructivo (PDF, Word, PowerPoint, OpenDocument o texto). El sistema busca el tipo de obra que corresponde y propone su plantilla; no se envía nada a internet ni a un modelo de IA.')); ?></p>
    <form method="POST" action="/plantillas-proyecto/procesar" enctype="multipart/form-data" class="formulario">
        <?php if ($proyecto): ?><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><?php endif; ?>
        <div class="campo"><label for="pp-doc"><?php echo s(t('Documento del proceso')); ?></label><input type="file" id="pp-doc" name="documento" accept=".pdf,.docx,.doc,.pptx,.odt,.txt,.md" required>
            <small class="text-muted"><?php echo s(t('Si el proceso está en una página web, descarga el PDF o guarda la página y súbela aquí. Un PDF escaneado sin texto no se puede leer.')); ?></small></div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> <?php echo s(t('Analizar documento')); ?></button>
    </form>
</section>

<?php if ($esAdmin): ?>
<section class="card">
    <h3><i class="fa-solid fa-wand-magic-sparkles"></i> <?php echo s(t('Leer un proceso con IA (app de escritorio)')); ?></h3>
    <p class="text-muted"><?php echo s(t('Para procesos que no están en el catálogo, la app de escritorio puede leer cualquier documento con IA y proponer la plantilla completa.')); ?></p>
    <ol class="text-sm">
        <li><?php echo s(t('Crea un proyecto vacío y ábrelo en el Ensamblador de la app de escritorio.')); ?></li>
        <li><?php echo s(t('Sube el documento y elige «Plantilla de proyecto».')); ?></li>
        <li><?php echo s(t('Revisa y aplica la propuesta, y pulsa «Guardar como plantilla».')); ?></li>
    </ol>
</section>
<?php endif; ?>

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
                <td class="hide-m text-sm"><?php echo (int)($r['partidas'] ?? 0); ?> partidas · <?php echo (int)($r['actividades'] ?? 0); ?> actividades · <?php echo (int)($r['dependencias'] ?? 0); ?> dependencias<?php echo !empty($r['materiales']) ? ' · ' . (int)$r['materiales'] . ' materiales' : ''; ?><?php echo !empty($r['campos']) ? ' · ' . (int)$r['campos'] . ' campos' : ''; ?><br><small class="text-muted">Duración: <?php echo (int)($r['dias'] ?? 0); ?> días<?php echo !empty($r['fuente']) ? ' · ' . s($r['fuente']) : ''; ?></small></td>
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
