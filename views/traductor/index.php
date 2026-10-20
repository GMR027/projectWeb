<?php // Variables: $proyecto, $configurado, $puedeDocumentos ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-language"></i> <?php echo s(t('Traductor')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · traduce de español a inglés o de inglés a español lo que escribas o un documento completo.</p>
    </div>
</div>

<?php if (!$configurado): ?>
    <div class="card trd-aviso">
        <h3 class="card-titulo"><i class="fa-solid fa-plug-circle-xmark"></i> Traductor no disponible</h3>
        <p>Este servidor no tiene instalado el motor de traducción. <?php echo is_admin() ? 'Ejecuta <code>bin/instalar_traductor.sh</code> (descarga unos 400 MB una sola vez) o define <code>TRADUCTOR_URL</code> apuntando a un servidor LibreTranslate. Más detalle en <code>docs/traductor.md</code>.' : 'Avisa al administrador.'; ?></p>
    </div>
<?php endif; ?>

<div class="card trd" id="trd" data-proyecto="<?php echo (int)$proyecto->id; ?>" data-max="<?php echo \Classes\Traductor::MAX_TEXTO; ?>">
    <div class="trd-direccion">
        <select id="trd-de" aria-label="Idioma de origen"><option value="es">Español</option><option value="en">English</option></select>
        <button type="button" class="btn btn-sm btn-ghost" id="trd-cambiar" title="Intercambiar idiomas" aria-label="Intercambiar idiomas"><i class="fa-solid fa-right-left"></i></button>
        <select id="trd-a" aria-label="Idioma de destino"><option value="es">Español</option><option value="en" selected>English</option></select>
    </div>

    <div class="trd-pestanas" role="tablist">
        <button type="button" class="btn btn-sm btn-primary" data-pestana="texto" role="tab" aria-selected="true"><i class="fa-solid fa-keyboard"></i> Texto</button>
        <?php if ($puedeDocumentos): ?>
            <button type="button" class="btn btn-sm btn-ghost" data-pestana="documento" role="tab" aria-selected="false"><i class="fa-solid fa-file-arrow-up"></i> Documento</button>
        <?php endif; ?>
    </div>

    <section id="trd-texto">
        <div class="trd-cajas">
            <label>Texto original
                <textarea id="trd-entrada" rows="12" maxlength="<?php echo \Classes\Traductor::MAX_TEXTO; ?>" placeholder="Escribe o pega el texto…"></textarea>
                <small class="text-muted"><span id="trd-cuenta">0</span> / <?php echo number_format(\Classes\Traductor::MAX_TEXTO); ?></small>
            </label>
            <label>Traducción
                <textarea id="trd-salida" rows="12" readonly placeholder="Aquí aparece la traducción"></textarea>
                <small><button type="button" class="btn btn-sm btn-ghost" id="trd-copiar"><i class="fa-regular fa-copy"></i> Copiar</button></small>
            </label>
        </div>
        <div class="trd-acciones">
            <button type="button" class="btn btn-primary" id="trd-traducir"<?php echo $configurado ? '' : ' disabled'; ?>><i class="fa-solid fa-language"></i> Traducir</button>
            <button type="button" class="btn btn-ghost" id="trd-limpiar">Limpiar</button>
            <span class="text-sm text-muted" id="trd-estado" role="status"></span>
        </div>
    </section>

    <?php if ($puedeDocumentos): ?>
        <section id="trd-documento" hidden>
            <label class="trd-archivo">Documento a traducir
                <input type="file" id="trd-archivo" accept=".pdf,.docx,.doc,.odt,.rtf,.xlsx,.xls,.ods,.csv,.pptx,.ppt,.odp,.txt,.md">
            </label>
            <p class="text-sm text-muted">PDF, Word (.docx, .doc), Writer (.odt), RTF, Excel (.xlsx, .xls), Calc (.ods), CSV, PowerPoint (.pptx, .ppt), Impress (.odp) y TXT · hasta 20 MB.
                Se conserva el formato de párrafos, tablas y hojas. Un PDF se entrega como PDF nuevo con el texto traducido (sin el diseño original) y no funciona con PDF escaneados.</p>
            <div class="trd-acciones">
                <button type="button" class="btn btn-primary" id="trd-doc-btn"<?php echo $configurado ? '' : ' disabled'; ?>><i class="fa-solid fa-file-circle-check"></i> Traducir y descargar</button>
                <span class="text-sm text-muted" id="trd-doc-estado" role="status"></span>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
(function () {
    const $ = id => document.getElementById(id), raiz = $('trd'), proyecto = raiz.dataset.proyecto;
    const de = $('trd-de'), a = $('trd-a');
    // Los dos selectores siempre son idiomas distintos
    de.addEventListener('change', () => { if (de.value === a.value) a.value = de.value === 'es' ? 'en' : 'es'; });
    a.addEventListener('change', () => { if (de.value === a.value) de.value = a.value === 'es' ? 'en' : 'es'; });
    $('trd-cambiar').addEventListener('click', () => {
        [de.value, a.value] = [a.value, de.value];
        if ($('trd-salida').value) { $('trd-entrada').value = $('trd-salida').value; $('trd-salida').value = ''; cuenta(); }
    });
    document.querySelectorAll('[data-pestana]').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('[data-pestana]').forEach(x => {
            const on = x === b;
            x.classList.toggle('btn-primary', on); x.classList.toggle('btn-ghost', !on); x.setAttribute('aria-selected', on);
            const s = $('trd-' + x.dataset.pestana); if (s) s.hidden = !on;
        });
    }));
    const cuenta = () => { $('trd-cuenta').textContent = $('trd-entrada').value.length; };
    $('trd-entrada').addEventListener('input', cuenta);
    $('trd-limpiar').addEventListener('click', () => { $('trd-entrada').value = ''; $('trd-salida').value = ''; $('trd-estado').textContent = ''; cuenta(); });
    $('trd-copiar').addEventListener('click', async () => {
        const t = $('trd-salida').value; if (!t) return;
        try { await navigator.clipboard.writeText(t); } catch (e) { $('trd-salida').select(); document.execCommand('copy'); }
        $('trd-estado').textContent = 'Copiado';
    });
    const datos = extra => { const f = new FormData(); f.append('proyecto', proyecto); f.append('de', de.value); f.append('a', a.value); for (const k in extra) f.append(k, extra[k]); return f; };

    $('trd-traducir').addEventListener('click', async () => {
        const t = $('trd-entrada').value; if (!t.trim()) { $('trd-estado').textContent = 'Escribe un texto.'; return; }
        const btn = $('trd-traducir'); btn.disabled = true; $('trd-estado').textContent = 'Traduciendo…';
        try {
            const r = await fetch('/api/traductor/texto', { method: 'POST', body: datos({ texto: t }) });
            const j = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(j.error || 'No se pudo traducir.');
            $('trd-salida').value = j.texto; $('trd-estado').textContent = 'Listo';
        } catch (e) { $('trd-estado').textContent = e.message; }
        btn.disabled = false;
    });

    const docBtn = $('trd-doc-btn');
    if (docBtn) docBtn.addEventListener('click', async () => {
        const f = $('trd-archivo').files[0], est = $('trd-doc-estado');
        if (!f) { est.textContent = 'Elige un documento.'; return; }
        docBtn.disabled = true; est.textContent = 'Traduciendo el documento… puede tardar unos minutos.';
        try {
            const r = await fetch('/api/traductor/documento', { method: 'POST', body: datos({ archivo: f }) });
            if (!r.ok) { const j = await r.json().catch(() => ({})); throw new Error(j.error || 'No se pudo traducir el documento.'); }
            const nombre = decodeURIComponent(r.headers.get('X-Nombre-Archivo') || 'traduccion');
            const url = URL.createObjectURL(await r.blob());
            const l = document.createElement('a'); l.href = url; l.download = nombre; document.body.appendChild(l); l.click(); l.remove();
            setTimeout(() => URL.revokeObjectURL(url), 5000);
            est.textContent = 'Listo: ' + nombre;
        } catch (e) { est.textContent = e.message; }
        docBtn.disabled = false;
    });
})();
</script>
