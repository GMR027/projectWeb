<?php $q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$documento->id; [$aw, $ah] = $documento->medidas(); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-regular fa-file-lines"></i> <?php echo s($documento->nombre); ?></h2>
        <p class="text-muted"><?php echo s(formatear_fecha($documento->fecha, 'd/m/Y')); ?><?php echo $documento->departamento !== '' ? ' · ' . s($documento->departamento) : ''; ?></p>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar): ?><a href="/redactor/abrir?<?php echo $q; ?>" class="btn btn-primary hide-m"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
        <?php if ($puedeDescargar): ?>
            <details class="gr-descargar">
                <summary class="btn btn-ghost"><i class="fa-solid fa-download"></i> Descargar</summary>
                <div class="gr-descargar-menu">
                    <a href="/redactor/descargar?<?php echo $q; ?>&formato=docx"><i class="fa-regular fa-file-word"></i> Word (.docx)</a>
                    <a href="/redactor/descargar?<?php echo $q; ?>&formato=odt"><i class="fa-regular fa-file-lines"></i> LibreOffice (.odt)</a>
                    <a href="/redactor/descargar?<?php echo $q; ?>&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>
                    <a href="/redactor/descargar?<?php echo $q; ?>&formato=pdf&inline=1" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Imprimir</a>
                    <?php if ($comentarios): ?><label class="rd-opc"><input type="checkbox" id="rd-x-coment"> Incluir comentarios al exportar</label><?php endif; ?>
                </div>
            </details>
        <?php endif; ?>
        <a href="/redactor?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← Documentos</a>
    </div>
</div>
<div class="rd-fondo">
    <article class="rd-hoja rd-lectura" style="width:<?php echo round($aw * 37.795, 1); ?>px;padding:<?php echo (float)$documento->margen_sup; ?>cm <?php echo (float)$documento->margen_der; ?>cm <?php echo (float)$documento->margen_inf; ?>cm <?php echo (float)$documento->margen_izq; ?>cm">
        <?php echo $documento->contenido !== '' ? $documento->contenido : '<p class="text-muted">Documento vacío.</p>'; ?>
    </article>
</div>
<?php if ($sellos): ?>
<div class="card rd-sellos-ver">
    <h3><i class="fa-solid fa-stamp"></i> Firmas electrónicas</h3>
    <?php foreach ($sellos as $f): ?>
        <p><strong><?php echo s($f['nombre']); ?></strong><?php echo $f['cargo'] !== '' ? ' · ' . s($f['cargo']) : ''; ?> · <?php echo s(date('d/m/Y H:i', strtotime($f['firmado']))); ?>
            <span class="<?php echo $f['valido'] ? 'rd-sello-ok' : 'rd-sello-mal'; ?>"><?php echo $f['valido'] ? '✔ Íntegro' : '⚠ Modificado después de firmar'; ?></span>
            <small class="text-muted mono">huella <?php echo s(substr($f['huella'], 0, 16)); ?>…</small></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($comentarios): ?>
<div class="card rd-comentarios-ver">
    <h3><i class="fa-regular fa-comments"></i> Comentarios (<?php echo count($comentarios); ?>)</h3>
    <?php foreach ($comentarios as $c): ?>
        <div class="rd-com-item<?php echo !empty($c['resuelto']) ? ' resuelto' : ''; ?>">
            <?php if (!empty($c['cita'])): ?><blockquote>«<?php echo s($c['cita']); ?>»</blockquote><?php endif; ?>
            <p><strong><?php echo s($c['autor']); ?></strong> <small class="text-muted"><?php echo s($c['fecha'] ? date('d/m/Y H:i', strtotime($c['fecha'])) : ''); ?></small><?php echo !empty($c['resuelto']) ? ' <span class="badge">Resuelto</span>' : ''; ?></p>
            <p><?php echo nl2br(s($c['texto'])); ?></p>
            <?php foreach ($c['respuestas'] ?? [] as $r): ?><p class="rd-resp">↳ <strong><?php echo s($r['autor']); ?></strong>: <?php echo s($r['texto']); ?></p><?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<script>
(function () {
    var c = document.getElementById('rd-x-coment');
    if (!c) return;
    c.addEventListener('change', function () {
        document.querySelectorAll('.gr-descargar-menu a').forEach(function (a) { a.href = a.href.replace(/&comentarios=1/, '') + (c.checked ? '&comentarios=1' : ''); });
    });
})();
</script>
