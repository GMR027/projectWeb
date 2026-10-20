<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-import"></i> Importar MSR desde Excel</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · se importa en <strong><?php echo s($msr->nombre); ?></strong></p>
    </div>
    <a href="/msr/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($msr->id); ?>" class="btn btn-ghost">← <?php echo s($msr->nombre); ?></a>
</div>

<?php if (empty($previa)): ?>
<div class="card">
    <p class="text-muted text-sm">Sube un MSR en .xlsx con el formato estándar (encabezado «Spec Number» en la columna C). Se crean los materiales con su foto en miniatura, un paquete por número de submittal y los renglones del MSR. Antes de guardar verás una vista previa.</p>
    <form method="POST" action="/msr/importar?proyecto=<?php echo s($proyecto->id); ?>&msr=<?php echo s($msr->id); ?>" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <input type="hidden" name="msr" value="<?php echo s($msr->id); ?>">
        <div class="campo"><label for="archivo">Archivo .xlsx</label><input type="file" id="archivo" name="archivo" accept=".xlsx" required></div>
        <button type="submit" class="btn btn-primary">Analizar archivo</button>
    </form>
</div>
<?php else: $r = $previa['resumen']; ?>
<div class="card">
    <h3>Vista previa</h3>
    <p><strong><?php echo $r['renglones']; ?></strong> renglones · <strong><?php echo $r['submittals']; ?></strong> submittal(s) · <strong><?php echo $r['materiales']; ?></strong> materiales · <strong><?php echo $r['imagenes']; ?></strong> fotos detectadas</p>
    <?php if ($previa['avisos']): ?>
        <details><summary class="text-sm"><?php echo count($previa['avisos']); ?> aviso(s)</summary>
            <ul class="text-sm text-muted"><?php foreach (array_slice($previa['avisos'], 0, 50) as $a): ?><li><?php echo s($a); ?></li><?php endforeach; ?></ul>
        </details>
    <?php endif; ?>
    <?php if (!empty($nuevas)): ?>
    <div class="card" style="margin:1rem 0">
        <strong>Partidas del Excel que no existen en este MSR</strong>
        <p class="text-muted text-sm">Por defecto se crean como partidas nuevas. Si ya tienes una con otro nombre (por ejemplo, la renombraste), elige a cuál unir sus materiales.</p>
        <?php foreach ($nuevas as $i => $nombre): ?>
            <div class="campo" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
                <span style="min-width:18rem">«<?php echo s($nombre); ?>» →</span>
                <select name="union[<?php echo $i; ?>][a]" form="form-confirmar">
                    <option value="">Crear partida nueva</option>
                    <?php foreach ($existentes as $ex): ?><option value="<?php echo s($ex); ?>">Unir a «<?php echo s($ex); ?>»</option><?php endforeach; ?>
                </select>
                <input type="hidden" name="union[<?php echo $i; ?>][de]" value="<?php echo s($nombre); ?>" form="form-confirmar">
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="la-controles" style="margin:1rem 0">
        <form method="POST" action="/msr/importar/confirmar" id="form-confirmar">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <input type="hidden" name="msr" value="<?php echo s($msr->id); ?>">
            <input type="hidden" name="token" value="<?php echo s($previa['token']); ?>">
            <button type="submit" class="btn btn-primary">Confirmar importación</button>
        </form>
        <form method="POST" action="/msr/importar/cancelar">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <input type="hidden" name="msr" value="<?php echo s($msr->id); ?>">
            <input type="hidden" name="token" value="<?php echo s($previa['token']); ?>">
            <button type="submit" class="btn btn-ghost">Cancelar</button>
        </form>
    </div>
    <div class="la-wrap">
    <table class="tabla">
        <thead><tr><th>Sección</th><th>Submittal</th><th>Spec</th><th>Descripción</th><th>St</th><th>Compra prog.</th><th>Entrega prog.</th><th>Proveedor</th><th>Foto</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($previa['filas'], 0, 40) as $f): ?>
            <tr>
                <td><?php echo s($f['seccion']); ?></td><td class="mono"><?php echo s($f['submittal']); ?></td>
                <td class="mono"><?php echo s($f['referencia']); ?></td><td><?php echo s($f['nombre']); ?></td>
                <td><?php echo s($f['status']); ?></td>
                <td class="mono"><?php echo $f['fecha_compra_prog'] ? formatear_fecha($f['fecha_compra_prog']) : ''; ?></td>
                <td class="mono"><?php echo $f['fecha_entrega_prog'] ? formatear_fecha($f['fecha_entrega_prog']) : ''; ?></td>
                <td><?php echo s($f['proveedor']); ?></td><td><?php echo $f['imagen'] ? '✔' : ''; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if (count($previa['filas']) > 40): ?><p class="text-muted text-sm">Mostrando 40 de <?php echo count($previa['filas']); ?> renglones.</p><?php endif; ?>
</div>
<?php endif; ?>
