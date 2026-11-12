<?php
/** Importar catálogo. Variables: $proyecto, $datos, $resultado, $error, $campos */
$pid = (int)$proyecto->id;
$moneda = fn($n) => '$' . number_format((float)$n, 2);
$num = fn($v, $d = 3) => $v === null ? '—' : number_format((float)$v, $d);
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-import"></i> Importar catálogo de conceptos</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · CSV, Excel (.xlsx) o LibreOffice (.ods)</p>
    </div>
    <div><a href="/conceptos?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Catálogo</a></div>
</div>

<?php echo aviso_esquema_catalogo(); ?>
<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php endif; ?>

<?php if ($resultado): ?>
    <div class="card">
        <h3><i class="fa-solid fa-circle-check"></i> Importación terminada</h3>
        <div class="cb-kpis" style="margin-top:1rem">
            <div class="cb-kpi"><small>Conceptos nuevos</small><strong><?php echo (int)$resultado['creados']; ?></strong></div>
            <div class="cb-kpi"><small>Actualizados</small><strong><?php echo (int)$resultado['actualizados']; ?></strong></div>
            <div class="cb-kpi"><small>Partidas creadas</small><strong><?php echo (int)$resultado['partidas']; ?></strong></div>
            <div class="cb-kpi <?php echo $resultado['rechazos'] ? 'cb-kpi-alerta' : ''; ?>"><small>Renglones rechazados</small><strong><?php echo count($resultado['rechazos']); ?></strong></div>
        </div>
        <?php if ($resultado['avisos']): ?>
            <h4>Avisos</h4><ul class="text-sm"><?php foreach ($resultado['avisos'] as $a): ?><li><?php echo s($a); ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($resultado['rechazos']): ?>
            <h4>Rechazos</h4><ul class="text-sm"><?php foreach ($resultado['rechazos'] as $a): ?><li class="cs-mal"><?php echo s($a); ?></li><?php endforeach; ?></ul>
            <p class="text-muted text-sm">Corrige el archivo y vuelve a importarlo: las claves que ya entraron se actualizan sin duplicarse.</p>
        <?php endif; ?>
        <div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1rem">
            <a class="btn btn-primary" href="/conceptos?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-list-check"></i> Ver el catálogo</a>
            <a class="btn btn-ghost" href="/conceptos/importar?proyecto=<?php echo $pid; ?>">Importar otro archivo</a>
            <a class="btn btn-ghost" href="/costos?proyecto=<?php echo $pid; ?>&pestana=resumen">Ir a Costos</a>
        </div>
    </div>

<?php elseif ($datos): ?>
    <div class="card">
        <h3><i class="fa-solid fa-table-list"></i> Revisa el mapeo y la vista previa</h3>
        <p class="text-muted text-sm">Se leyeron <strong><?php echo count($datos['filas']); ?></strong> renglón(es): <strong><?php echo (int)$datos['analisis']['validas']; ?></strong> listo(s) para importar<?php echo $datos['analisis']['rechazos'] ? ' y ' . count($datos['analisis']['rechazos']) . ' con problemas' : ''; ?>. Si falta una partida se crea; si la clave ya existe se actualiza conservando sus vínculos con actividades.</p>
        <?php if ($datos['avisos']): ?><div class="alerta alerta-aviso"><?php echo s(implode(' ', $datos['avisos'])); ?></div><?php endif; ?>

        <form method="POST" action="/conceptos/importar/aplicar" class="co-form">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <input type="hidden" name="token" value="<?php echo s($datos['token']); ?>">
            <h4>Columnas del archivo</h4>
            <div class="co-campos">
                <?php foreach ($campos as $campo => $etq): ?>
                <label><?php echo s($etq); ?><select name="map[<?php echo s($campo); ?>]">
                    <option value="">— No usar —</option>
                    <?php foreach ($datos['encabezados'] as $i => $h): ?><option value="<?php echo (int)$i; ?>"<?php echo (string)($datos['map'][$campo] ?? '') === (string)$i ? ' selected' : ''; ?>><?php echo s($h !== '' ? $h : 'Columna ' . ($i + 1)); ?></option><?php endforeach; ?>
                </select></label>
                <?php endforeach; ?>
            </div>
            <p class="text-muted text-sm">Si cambias el mapeo, la importación usará las columnas nuevas (la vista previa de abajo corresponde al mapeo detectado).</p>

            <h4>Vista previa (primeros 12)</h4>
            <div class="tabla-wrapper"><table class="tabla">
                <thead><tr><th>Partida</th><th>Clave</th><th>Descripción</th><th>Unidad</th><th class="num">Cantidad</th><th class="num">P. unitario</th><th class="num">Importe</th><th></th></tr></thead>
                <tbody>
                <?php foreach (array_slice($datos['analisis']['filas'], 0, 12) as $f): ?>
                    <tr>
                        <td><?php echo s($f['partida'] ?: '—'); ?></td>
                        <td class="mono"><?php echo s($f['clave'] ?: '—'); ?></td>
                        <td><?php echo s($f['descripcion']); ?></td>
                        <td><?php echo s($f['unidad'] ?: '—'); ?></td>
                        <td class="num mono"><?php echo $num($f['cantidad']); ?></td>
                        <td class="num mono"><?php echo $f['precio_unitario'] === null ? '—' : $moneda($f['precio_unitario']); ?></td>
                        <td class="num mono"><?php echo $f['importe'] === null ? '—' : $moneda($f['importe']); ?></td>
                        <td><?php if ($f['errores']): ?><span class="cs-mal text-sm"><?php echo s(implode(', ', $f['errores'])); ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>

            <?php if ($datos['analisis']['rechazos']): ?>
                <h4>Renglones con problemas</h4>
                <ul class="text-sm"><?php foreach ($datos['analisis']['rechazos'] as $r): ?><li class="cs-mal"><?php echo s($r); ?></li><?php endforeach; ?></ul>
            <?php endif; ?>

            <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;margin-top:1rem">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-import"></i> Aplicar importación</button>
                <a class="btn btn-ghost" href="/conceptos/importar?proyecto=<?php echo $pid; ?>">Subir otro archivo</a>
            </div>
        </form>
    </div>

<?php else: ?>
    <div class="card">
        <form method="POST" action="/conceptos/importar?proyecto=<?php echo $pid; ?>" enctype="multipart/form-data" class="co-form">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <label>Archivo del catálogo *<input type="file" name="archivo" accept=".csv,.xlsx,.ods" required></label>
            <p class="text-muted text-sm">La primera fila debe tener los encabezados. Se reconocen, entre otros: <code>Partida</code>, <code>Clave</code>, <code>Descripción</code> o <code>Concepto</code>, <code>Unidad</code>, <code>Cantidad</code>, <code>Precio unitario</code> y <code>Importe</code>. Si solo traes importe, el precio se calcula como importe ÷ cantidad.</p>
            <p class="text-muted text-sm">No se crean actividades: los conceptos quedan en el catálogo y después los repartes o generas la actividad desde la lista.</p>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Leer el archivo</button>
        </form>
    </div>
    <div class="card">
        <h4>Formato sugerido</h4>
        <div class="tabla-wrapper"><table class="tabla">
            <thead><tr><th>Partida</th><th>Clave</th><th>Descripción</th><th>Unidad</th><th>Cantidad</th><th>Precio unitario</th><th>Importe</th></tr></thead>
            <tbody><tr><td>01 Preliminares</td><td>PRE-01</td><td>Limpieza y trazo del terreno</td><td>m²</td><td>1,250</td><td>18.50</td><td>23,125.00</td></tr></tbody>
        </table></div>
    </div>
<?php endif; ?>
