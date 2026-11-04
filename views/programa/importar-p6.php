<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-import"></i> Importar desde Primavera P6</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · XML de P6 o XER</p>
    </div>
    <div style="display:flex;gap:.5rem">
        <a href="/programa/importar?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost btn-sm">MS Project / POD</a>
        <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost btn-sm">← Programa</a>
    </div>
</div>

<?php if (!empty($_GET['error'])): ?><div class="alerta alerta-error" role="alert"><?php echo s($_GET['error']); ?></div><?php endif; ?>

<?php if (empty($previa)): ?>
<div class="card">
    <p class="text-muted text-sm">Sube el programa que exporta <strong>Primavera P6</strong>: el <strong>XML de P6</strong> (<code>APIBusinessObjects</code>) o el <strong>XER</strong> (<code>.xer</code>). Se leen la estructura <strong>WBS</strong> (partidas), las <strong>actividades</strong>, las <strong>relaciones</strong> (FS/SS/FF/SF con su espera) y el <strong>% de avance</strong>. Antes de guardar verás una vista previa y lo importado <strong>se suma</strong> al programa actual.</p>
    <ul class="text-muted text-sm" style="margin:1rem 0 1.6rem 2rem; list-style:disc">
        <li>WBS → partidas · Activities → actividades · Relationships → dependencias.</li>
        <li>Por defecto las fechas se recalculan con el calendario del proyecto (festivos y jornada). Puedes conservar las del archivo.</li>
        <li>Si un código ya existe, por defecto se <strong>actualiza</strong> esa actividad en vez de duplicarla.</li>
        <li>Las tareas de resumen WBS y las relaciones con actividades inexistentes se omiten y se avisan.</li>
    </ul>
    <form method="POST" action="/programa/importar/p6?proyecto=<?php echo s($proyecto->id); ?>" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <div class="form-grid">
            <div class="campo campo-full"><label for="archivo">Archivo .xml o .xer</label><input type="file" id="archivo" name="archivo" accept=".xml,.xer" required></div>
            <div class="campo"><label for="recalcular">Fechas</label>
                <select id="recalcular" name="recalcular">
                    <option value="1" selected>Recalcular con el calendario del proyecto (recomendado)</option>
                    <option value="0">Conservar las fechas del archivo tal cual</option>
                </select>
            </div>
            <div class="campo"><label for="duplicados">Si el código ya existe</label>
                <select id="duplicados" name="duplicados">
                    <option value="actualizar" selected>Actualizar la actividad existente</option>
                    <option value="saltar">Saltarla</option>
                    <option value="crear">Crear otra con código nuevo</option>
                </select>
            </div>
        </div>
        <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
        <button type="submit" class="btn btn-primary">Analizar archivo</button>
    </form>
</div>
<?php else: $r = $previa['resumen']; ?>
<div class="card">
    <h3>Vista previa · <?php echo s($r['formato']); ?></h3>
    <p><strong><?php echo (int)$r['partidas']; ?></strong> partida(s) · <strong><?php echo (int)$r['actividades']; ?></strong> actividad(es) · <strong><?php echo (int)$r['dependencias']; ?></strong> dependencia(s) · <strong><?php echo (int)$r['hitos']; ?></strong> hito(s)</p>
    <p class="text-sm text-muted">Se crearán <strong><?php echo (int)$r['nuevas']; ?></strong> actividad(es), se actualizarán <strong><?php echo (int)$r['actualizadas']; ?></strong> y se omitirán <strong><?php echo (int)$r['omitidas']; ?></strong>.</p>
    <?php if ($previa['avisos']): ?>
        <details open><summary class="text-sm"><?php echo count($previa['avisos']); ?> aviso(s)</summary>
            <ul class="text-sm text-muted"><?php foreach ($previa['avisos'] as $a): ?><li><?php echo s($a); ?></li><?php endforeach; ?></ul>
        </details>
    <?php endif; ?>
    <div class="la-controles" style="margin:1rem 0">
        <form method="POST" action="/programa/importar/p6/confirmar">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
            <input type="hidden" name="token" value="<?php echo s($previa['token']); ?>">
            <button type="submit" class="btn btn-primary">Confirmar importación</button>
        </form>
        <form method="POST" action="/programa/importar/p6/cancelar">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
            <input type="hidden" name="token" value="<?php echo s($previa['token']); ?>">
            <button type="submit" class="btn btn-ghost">Cancelar</button>
        </form>
    </div>
    <div class="tabla-wrapper">
        <table class="tabla">
            <thead><tr><th>Código</th><th>Nombre</th><th>Inicio</th><th>Fin</th><th class="num">Días</th><th class="num">Avance</th></tr></thead>
            <tbody>
            <?php $mostradas = 0; foreach ($previa['partidas'] as $p): ?>
                <tr class="fila-partida"><td class="mono"><?php echo s($p['codigo']); ?></td><td colspan="5"><strong><?php echo s($p['nombre']); ?></strong> <span class="text-muted text-sm">· <?php echo count($p['actividades']); ?> actividades</span></td></tr>
                <?php foreach ($p['actividades'] as $a): if ($mostradas++ >= 100) break; ?>
                    <tr><td class="mono"><?php echo s($a['codigo']); ?></td><td><?php echo $a['hito'] ? '◆ ' : ''; ?><?php echo s($a['nombre']); ?></td>
                        <td class="mono"><?php echo s(formatear_fecha($a['inicio'])); ?></td><td class="mono"><?php echo s(formatear_fecha($a['fin'])); ?></td>
                        <td class="num mono"><?php echo $a['hito'] ? '—' : (int)$a['duracion']; ?></td><td class="num mono"><?php echo (int)$a['avance']; ?>%</td></tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($r['actividades'] > 100): ?><p class="text-muted text-sm">Mostrando 100 de <?php echo (int)$r['actividades']; ?> actividades.</p><?php endif; ?>
</div>
<?php endif; ?>
