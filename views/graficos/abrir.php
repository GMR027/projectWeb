<?php // Variables: $proyecto, $grafico, $config
use Model\Grafico;
$q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$grafico->id;
$lz = [
    'icono' => 'fa-pen-ruler',
    'titulo_html' => '<span id="gr-titulo">' . s($grafico->nombre) . '</span>',
    'subtitulo' => (int)$grafico->ancho . ' × ' . (int)$grafico->alto . ' px · ' . (Grafico::FONDOS[$grafico->fondo] ?? ''),
    'acciones' => [
        ['tipo' => 'html', 'html' => '<form id="gr-renombrar" class="gr-renombrar" autocomplete="off"><input type="text" id="gr-nombre" value="' . s($grafico->nombre) . '" maxlength="150" aria-label="' . s(t('Nombre del gráfico')) . '"><button type="submit" class="btn btn-ghost" title="' . s(t('Cambiar el nombre')) . '"><i class="fa-solid fa-check"></i></button></form>'],
        ['tipo' => 'html', 'html' => '<span id="ve-estado" class="text-sm text-muted" aria-live="polite"></span>'],
        ['tipo' => 'html', 'html' => '<button type="button" id="ve-guardar" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> ' . s(t('Guardar')) . '</button>'],
        // Exportar: lo rápido (PNG/PDF/.odp guardados) y «Con opciones…» (formato, tamaño, calidad) en un solo menú
        ['tipo' => 'html', 'html' => !puede_descargar() ? '' : '<details class="gr-descargar"><summary class="btn btn-ghost" title="' . s(t('Descargar el gráfico (guarda antes lo pendiente)')) . '"><i class="fa-solid fa-file-export"></i> ' . s(t('Exportar')) . '</summary><div class="gr-descargar-menu">'
            . '<a href="/graficos/descargar?' . $q . '&formato=png"><i class="fa-regular fa-image"></i> PNG (ZIP si hay varias láminas)</a>'
            . '<a href="/graficos/descargar?' . $q . '&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>'
            . '<a href="/graficos/descargar?' . $q . '&formato=odp"><i class="fa-regular fa-file-powerpoint"></i> ' . s(t('Presentación .odp')) . '</a>'
            . '<button type="button" id="gr-exportar-abrir"><i class="fa-solid fa-sliders"></i> ' . s(t('Con opciones…')) . ' <small class="text-muted">' . s(t('formato, tamaño, transparencia')) . '</small></button>'
            . '</div></details>'],
        // El resto de acciones, agrupadas para no saturar la cabecera
        ['tipo' => 'html', 'html' => '<details class="gr-descargar"><summary class="btn btn-ghost" title="' . s(t('Más acciones')) . '" aria-label="' . s(t('Más acciones')) . '"><i class="fa-solid fa-ellipsis-vertical"></i></summary><div class="gr-descargar-menu">'
            . '<button type="button" id="ve-ampliar"><i class="fa-solid fa-expand"></i> ' . s(t('Ampliar')) . '</button>'
            . '<button type="button" id="ve-presentar" title="' . s(t('Pantalla completa, solo las láminas')) . '"><i class="fa-solid fa-play"></i> ' . s(t('Presentar')) . '</button>'
            . '<a href="/graficos/ver?' . $q . '"><i class="fa-regular fa-eye"></i> ' . s(t('Ver')) . '</a>'
            . '<a href="/graficos/fondo?' . $q . '"><i class="fa-solid fa-image"></i> ' . s(t('Tamaño y fondo')) . '</a>'
            . '<button type="button" data-tour="editor-canvas"><i class="fa-solid fa-circle-play"></i> ' . s(t('Guía rápida')) . '</button>'
            . '</div></details>'],
        ['url' => $grafico->tipo === 'simbologia' ? '/simbolos?proyecto=' . (int)$proyecto->id . '#simbologias' : '/graficos?proyecto=' . (int)$proyecto->id, 'texto' => $grafico->tipo === 'simbologia' ? '← ' . s(t('Simbologías')) : '← ' . s(t('Editor'))],
    ],
];
include __DIR__ . '/../lienzo/_cabecera.php';
?>
<script>document.body.classList.add('gr-editor-page'); document.body.dataset.modoLienzo = 'dibujar';</script>
<?php include __DIR__ . '/../vplan/_editor-lienzo.php'; ?>

<dialog class="gr-exportar-dlg" id="gr-exportar-dlg">
<section class="card gr-exportar" id="exportar">
    <h3><i class="fa-solid fa-file-export"></i> <?php echo s(t('Exportar')); ?></h3>
    <p class="text-muted text-sm">Genera una copia del gráfico <strong>tal como está ahora</strong> (aunque no lo hayas guardado) en el formato y tamaño que necesites. El contenido se ajusta sin deformarse.</p>
    <div class="form-grid">
        <div class="campo">
            <label for="ex-formato">Formato</label>
            <select id="ex-formato">
                <option value="png">PNG (sin pérdida, admite transparencia)</option>
                <option value="jpg">JPG (ligero)</option>
                <option value="webp">WebP (ligero, admite transparencia)</option>
                <option value="pdf">PDF (una página del tamaño del gráfico)</option>
                <option value="odp">Presentación .odp (OpenOffice / LibreOffice / PowerPoint)</option>
            </select>
        </div>
        <div class="campo" id="ex-alcance-campo" hidden>
            <label for="ex-alcance">Qué exportar</label>
            <select id="ex-alcance">
                <option value="todas">Todas las láminas (PNG/JPG/WebP en un ZIP · PDF y .odp de varias páginas)</option>
                <option value="actual">Solo la lámina abierta</option>
            </select>
            <small class="text-muted">En PDF y .odp con todas las láminas se usa lo guardado, a su tamaño original.</small>
        </div>
        <div class="campo">
            <label for="ex-preset"><?php echo s(t('Tamaño')); ?></label>
            <select id="ex-preset">
                <option value="<?php echo (int)$grafico->ancho . 'x' . (int)$grafico->alto; ?>">Original (<?php echo (int)$grafico->ancho; ?> × <?php echo (int)$grafico->alto; ?>)</option>
                <option value="<?php echo (int)round($grafico->ancho / 2) . 'x' . (int)round($grafico->alto / 2); ?>">Mitad</option>
                <option value="<?php echo (int)$grafico->ancho * 2 . 'x' . (int)$grafico->alto * 2; ?>">Doble</option>
                <?php foreach (Grafico::TAMANOS as $grupo => $lista): ?>
                    <optgroup label="<?php echo s($grupo); ?>">
                        <?php foreach ($lista as [$etq, $w, $h]): ?><option value="<?php echo (int)$w . 'x' . (int)$h; ?>"><?php echo s($etq); ?></option><?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
                <option value="custom">Personalizado…</option>
            </select>
        </div>
        <div class="campo">
            <label>Ancho × alto (px)</label>
            <div class="ve-par"><input type="number" id="ex-ancho" min="16" max="10000" value="<?php echo (int)$grafico->ancho; ?>" aria-label="Ancho"> <span>×</span> <input type="number" id="ex-alto" min="16" max="10000" value="<?php echo (int)$grafico->alto; ?>" aria-label="Alto"></div>
            <label class="campo-check"><input type="checkbox" id="ex-bloq" checked> Mantener la proporción</label>
            <small id="ex-info" class="text-muted"></small>
        </div>
        <div class="campo" id="ex-calidad-bloque" hidden>
            <label for="ex-calidad">Calidad: <b id="ex-calidad-v">92%</b></label>
            <input type="range" id="ex-calidad" min="40" max="100" value="92">
        </div>
        <div class="campo">
            <label class="campo-check"><input type="checkbox" id="ex-transp"> Fondo transparente <span class="text-muted">(solo PNG y WebP; quita el fondo de la plantilla)</span></label>
        </div>
    </div>
    <div class="gr-exportar-pie">
        <button type="button" class="btn btn-primary" id="ex-btn"><i class="fa-solid fa-download"></i> <?php echo s(t('Exportar')); ?></button>
        <span class="text-sm text-muted" id="ex-estado"></span>
        <button type="button" class="btn btn-ghost" id="gr-exportar-cerrar"><?php echo s(t('Cerrar')); ?></button>
    </div>
</section>
</dialog>

<script>
(function () {
    const f = document.getElementById('gr-renombrar'), i = document.getElementById('gr-nombre');
    f.addEventListener('submit', async e => {
        e.preventDefault();
        const d = await fetch('/api/graficos/renombrar', { method: 'POST', body: new URLSearchParams({ lamina: <?php echo (int)$grafico->id; ?>, nombre: i.value }) }).then(r => r.json()).catch(() => ({ success: false, error: 'Error de conexión' }));
        const est = document.getElementById('ve-estado');
        if (d.success) { document.getElementById('gr-titulo').textContent = d.nombre; i.value = d.nombre; window.VPLAN_CONFIG.nombre = d.nombre; est.textContent = 'Nombre guardado ✔'; }
        else est.textContent = 'Error: ' + (d.error || 'no se pudo renombrar');
    });

    // Descargar después de guardar: antes se bajaba la última imagen guardada y parecía en blanco
    // si el dibujo era reciente o la lámina aún no se había guardado.
    document.addEventListener('click', async e => {
        const a = e.target.closest('.gr-descargar-menu a[href*="/graficos/descargar"]');
        if (!a || !window.__vplan || typeof window.__vplan.guardar !== 'function') return;
        e.preventDefault();
        if (a.dataset.descargando) return;
        a.dataset.descargando = '1';
        const est = document.getElementById('ve-estado'), antes = est ? est.textContent : '';
        if (est) est.textContent = 'Guardando antes de descargar…';
        const ok = await window.__vplan.guardar();
        delete a.dataset.descargando;
        if (ok === false) { if (est) est.textContent = antes; return; }
        window.location.href = a.href;
    });

    // Exportar con opciones: el panel vive en un diálogo (también se abre con el anclaje #exportar)
    const dlg = document.getElementById('gr-exportar-dlg');
    const abrirDlg = () => { document.querySelectorAll('details.gr-descargar[open]').forEach(d => d.removeAttribute('open')); if (dlg && !dlg.open) dlg.showModal(); };
    const btnAbrir = document.getElementById('gr-exportar-abrir');
    if (btnAbrir) btnAbrir.addEventListener('click', abrirDlg);
    const btnCerrar = document.getElementById('gr-exportar-cerrar');
    if (btnCerrar) btnCerrar.addEventListener('click', () => dlg.close());
    if (dlg) dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
    if (location.hash === '#exportar') abrirDlg();
    // Los menús desplegables de la cabecera se cierran al elegir o al hacer clic fuera
    document.addEventListener('click', e => {
        document.querySelectorAll('details.gr-descargar[open]').forEach(d => {
            if (!d.contains(e.target) || e.target.closest('a, button:not(summary)')) d.removeAttribute('open');
        });
    });
})();
</script>
<div class="ve-presentacion" id="ve-presentacion" hidden role="dialog" aria-label="Presentación">
    <img alt="">
    <div class="ve-pres-barra">
        <button type="button" data-pres="ant" aria-label="Anterior"><i class="fa-solid fa-chevron-left"></i></button>
        <span class="ve-pres-n"></span>
        <button type="button" data-pres="sig" aria-label="Siguiente"><i class="fa-solid fa-chevron-right"></i></button>
        <button type="button" data-pres="cerrar" aria-label="Salir" title="Salir (Esc)"><i class="fa-solid fa-xmark"></i></button>
    </div>
</div>
<?php include __DIR__ . '/../vplan/_editor-scripts.php'; ?>
