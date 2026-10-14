<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-import"></i> Importar programa desde XML / POD</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?></p>
    </div>
    <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Programa</a>
</div>

<?php if (!empty($_GET['error'])): ?><div class="alerta alerta-error"><?php echo s($_GET['error']); ?></div><?php endif; ?>

<?php if (empty($previa)): ?>
<div class="card">
    <p class="text-muted text-sm">Sube un programa en <strong>XML de MS Project (MSPDI)</strong> o un <strong>.pod</strong> en XML (como los que exporta este sistema). Las tareas de nivel 1 se convierten en partidas y el resto en actividades. Antes de guardar verás una vista previa y lo importado <strong>se suma</strong> al programa actual, sin borrar nada.</p>
    <ul class="text-muted text-sm" style="margin:1rem 0 1.6rem 2rem; list-style:disc">
        <li>Se importan: nombre, fechas de inicio y fin, % de avance, hitos, prioridad, notas y la estructura por niveles.</li>
        <li>No se importan: predecesoras, recursos, costos ni calendarios (la duración se recalcula con el calendario de este proyecto).</li>
        <li>Los .pod o .mpp binarios no se pueden leer: expórtalos como XML desde ProjectLibre o MS Project.</li>
    </ul>
    <form method="POST" action="/programa/importar?proyecto=<?php echo s($proyecto->id); ?>" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <div class="campo"><label for="archivo">Archivo .xml o .pod</label><input type="file" id="archivo" name="archivo" accept=".xml,.pod" required></div>
        <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
        <button type="submit" class="btn btn-primary">Analizar archivo</button>
    </form>
</div>
<?php else: $r = $previa['resumen']; ?>
<div class="card">
    <h3>Vista previa</h3>
    <p><strong><?php echo (int)$r['partidas']; ?></strong> partida(s) · <strong><?php echo (int)$r['actividades']; ?></strong> actividad(es) · <strong><?php echo (int)$r['hitos']; ?></strong> hito(s)</p>
    <?php if ($previa['avisos']): ?>
        <details open><summary class="text-sm"><?php echo count($previa['avisos']); ?> aviso(s)</summary>
            <ul class="text-sm text-muted"><?php foreach ($previa['avisos'] as $a): ?><li><?php echo s($a); ?></li><?php endforeach; ?></ul>
        </details>
    <?php endif; ?>
    <div class="la-controles" style="margin:1rem 0">
        <form method="POST" action="/programa/importar/confirmar">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
            <input type="hidden" name="token" value="<?php echo s($previa['token']); ?>">
            <button type="submit" class="btn btn-primary">Confirmar importación</button>
        </form>
        <form method="POST" action="/programa/importar/cancelar">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
            <input type="hidden" name="token" value="<?php echo s($previa['token']); ?>">
            <button type="submit" class="btn btn-ghost">Cancelar</button>
        </form>
    </div>
    <div class="tabla-wrapper">
        <table class="tabla">
            <thead><tr><th>Código</th><th>Nombre</th><th>Inicio</th><th>Fin</th><th>Avance</th></tr></thead>
            <tbody>
            <?php $mostradas = 0; foreach ($previa['partidas'] as $p): ?>
                <tr class="fila-partida"><td class="mono"><?php echo s($p['codigo']); ?></td><td colspan="4"><strong><?php echo s($p['nombre']); ?></strong> <span class="text-muted text-sm">· <?php echo count($p['actividades']); ?> actividades</span></td></tr>
                <?php foreach ($p['actividades'] as $a): if ($mostradas++ >= 80) break; ?>
                    <tr><td class="mono"><?php echo s($a['codigo']); ?></td><td><?php echo $a['hito'] ? '◆ ' : ''; ?><?php echo s($a['nombre']); ?></td>
                        <td class="mono"><?php echo formatear_fecha($a['inicio']); ?></td><td class="mono"><?php echo formatear_fecha($a['hito'] ? $a['inicio'] : $a['fin']); ?></td><td class="mono"><?php echo (int)$a['avance']; ?>%</td></tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($r['actividades'] > 80): ?><p class="text-muted text-sm">Mostrando 80 de <?php echo (int)$r['actividades']; ?> actividades.</p><?php endif; ?>
</div>
<?php endif; ?>
