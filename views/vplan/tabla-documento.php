<?php $tab = 'tablas'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-import"></i> Tabla en base a documento</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · sube un archivo y revisa la tabla antes de crearla</p>
    </div>
    <a href="/vplan/tablas?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Tablas</a>
</div>
<?php include __DIR__ . '/_tabs.php'; ?>
<div class="card">
    <div class="vp-dis-fila">
        <label for="td-archivo"><i class="fa-solid fa-file-arrow-up"></i> <strong>Documento</strong></label>
        <input type="file" id="td-archivo" accept=".xlsx,.xls,.ods,.csv,.docx,.pdf,.png,.jpg,.jpeg,.webp">
        <button type="button" class="btn btn-primary" id="td-leer">Leer documento</button>
    </div>
    <p class="text-muted text-sm">
        Se leen <strong>Excel, CSV, ODS, Word (.docx)</strong> y <strong>PDF con texto</strong> sin IA: el sistema detecta las columnas.
        <?php if ($ocr): ?>Las imágenes se leen con OCR.<?php else: ?>Las <strong>imágenes y PDF escaneados</strong> necesitan OCR (no instalado en este servidor)<?php echo $esAdmin ? ' o la IA de la app de escritorio.' : '; pide al administrador que los procese con IA.'; ?><?php endif; ?>
        <?php if ($esAdmin): ?><br><i class="fa-solid fa-wand-magic-sparkles"></i> <strong>Con IA</strong> (fotos, escaneos, formatos difíciles): app de escritorio → Herramientas IA → <em>Tabla desde documento</em>. Funciona igual: revisas la tabla y la creas.<?php endif; ?>
    </p>
    <div id="td-error" class="alerta alerta-error" hidden></div>
    <div id="td-avisos" class="text-sm text-muted"></div>
</div>
<div class="card" id="td-previa" hidden>
    <div class="vp-dis-fila">
        <label class="campo" style="flex:2"><span>Nombre de la tabla *</span><input type="text" id="td-nombre" maxlength="140"></label>
        <span class="text-muted text-sm" id="td-resumen"></span>
    </div>
    <p class="text-muted text-sm">Corrige lo que haga falta: la primera fila es el encabezado. Usa ✕ para quitar un renglón o una columna. La tabla del vPlan toma las primeras 8 columnas y 200 renglones.</p>
    <div class="td-scroll"><table class="tabla td-tabla" id="td-tabla"></table></div>
    <div class="vp-tab-acc-fija">
        <button type="button" class="btn btn-ghost btn-sm" id="td-mas">+ Renglón</button>
        <button type="button" class="btn btn-primary" id="td-crear">Crear tabla</button>
    </div>
</div>
<script>
(function () {
    const pid = <?php echo (int)$proyecto->id; ?>;
    const $ = id => document.getElementById(id);
    let filas = [], origen = '';
    const error = m => { $('td-error').hidden = !m; $('td-error').textContent = m || ''; };
    const pinta = () => {
        const nc = Math.max(1, ...filas.map(f => f.length));
        filas.forEach(f => { while (f.length < nc) f.push(''); });
        let h = '<thead><tr><th></th>';
        for (let c = 0; c < nc; c++) h += '<th><input data-f="0" data-c="' + c + '" value="' + esc(filas[0][c]) + '"><button type="button" class="td-x" data-col="' + c + '" title="Quitar columna">✕</button></th>';
        h += '</tr></thead><tbody>';
        for (let r = 1; r < filas.length; r++) {
            h += '<tr><td><button type="button" class="td-x" data-fila="' + r + '" title="Quitar renglón">✕</button></td>';
            for (let c = 0; c < nc; c++) h += '<td><input data-f="' + r + '" data-c="' + c + '" value="' + esc(filas[r][c]) + '"></td>';
            h += '</tr>';
        }
        $('td-tabla').innerHTML = h + '</tbody>';
        $('td-resumen').textContent = nc + ' columnas · ' + (filas.length - 1) + ' renglones' + (nc > 8 ? ' (el vPlan usará las primeras 8 columnas)' : '');
    };
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    $('td-tabla').addEventListener('input', e => { const i = e.target; if (i.dataset.f !== undefined) filas[+i.dataset.f][+i.dataset.c] = i.value; });
    $('td-tabla').addEventListener('click', e => {
        const b = e.target.closest('.td-x'); if (!b) return;
        if (b.dataset.fila) filas.splice(+b.dataset.fila, 1);
        else if (filas[0].length > 1) filas.forEach(f => f.splice(+b.dataset.col, 1));
        pinta();
    });
    $('td-mas').addEventListener('click', () => { filas.push(new Array(filas[0].length).fill('')); pinta(); });
    $('td-leer').addEventListener('click', async () => {
        const a = $('td-archivo').files[0];
        error(''); $('td-avisos').textContent = ''; $('td-previa').hidden = true;
        if (!a) return error('Elige un documento.');
        const fd = new FormData(); fd.append('proyecto', pid); fd.append('archivo', a);
        $('td-leer').disabled = true; $('td-leer').textContent = 'Leyendo…';
        try {
            const d = await (await fetch('/api/vplan/documento/leer', { method: 'POST', body: fd })).json();
            if (!d.success) return error(d.error || 'No se pudo leer el documento');
            $('td-avisos').textContent = [...(d.avisos || [])].join(' ');
            if (!d.filas.length) return error($('td-avisos').textContent || 'No se encontró ninguna tabla.');
            filas = d.filas; origen = a.name;
            $('td-nombre').value = a.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').slice(0, 140);
            $('td-avisos').textContent = 'Leído por «' + d.metodo + '». ' + $('td-avisos').textContent;
            $('td-previa').hidden = false; pinta();
        } catch (_) { error('No se pudo leer el documento'); }
        finally { $('td-leer').disabled = false; $('td-leer').textContent = 'Leer documento'; }
    });
    $('td-crear').addEventListener('click', async () => {
        error('');
        if (!$('td-nombre').value.trim()) return error('Escribe un nombre para la tabla.');
        $('td-crear').disabled = true;
        try {
            const d = await (await fetch('/api/vplan/documento/crear', { method: 'POST', body: new URLSearchParams({ proyecto: pid, nombre: $('td-nombre').value, origen, metodo: 'motor', filas: JSON.stringify(filas) }) })).json();
            if (d.success) location.href = d.url; else error(d.error || 'No se pudo crear la tabla');
        } catch (_) { error('No se pudo crear la tabla'); }
        finally { $('td-crear').disabled = false; }
    });
})();
</script>
