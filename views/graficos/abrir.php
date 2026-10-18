<?php // Variables: $proyecto, $grafico, $config
use Model\Grafico;
$q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$grafico->id; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-pen-ruler"></i> <span id="gr-titulo"><?php echo s($grafico->nombre); ?></span></h2>
        <p class="text-muted"><?php echo (int)$grafico->ancho; ?> × <?php echo (int)$grafico->alto; ?> px · <?php echo s(Grafico::FONDOS[$grafico->fondo] ?? ''); ?></p>
    </div>
    <div class="la-controles">
        <form id="gr-renombrar" class="gr-renombrar" autocomplete="off">
            <input type="text" id="gr-nombre" value="<?php echo s($grafico->nombre); ?>" maxlength="150" aria-label="Nombre del gráfico">
            <button type="submit" class="btn btn-ghost" title="Cambiar el nombre"><i class="fa-solid fa-check"></i></button>
        </form>
        <span id="ve-estado" class="text-sm text-muted"></span>
        <button type="button" id="ve-guardar" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?php echo s(t('Guardar')); ?></button>
        <?php if ($grafico->tipo === 'simbologia'): ?><a href="/simbolos?proyecto=<?php echo (int)$proyecto->id; ?>#simbologias" class="btn btn-ghost">← Simbologías</a>
        <?php else: ?><a href="/graficos?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← Gráficos</a><?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../vplan/_editor-lienzo.php'; ?>

<section class="card vp-acciones" id="vp-acciones">
    <h3><?php echo s(t('Acciones')); ?></h3>
    <div class="vp-acciones-menu">
        <button type="button" class="btn btn-ghost" id="ve-ampliar"><i class="fa-solid fa-expand"></i> <?php echo s(t('Ampliar')); ?></button>
        <button type="button" class="btn btn-ghost" id="ve-presentar" title="Pantalla completa, solo las láminas"><i class="fa-solid fa-play"></i> <?php echo s(t('Presentar')); ?></button>
        <a href="/graficos/ver?<?php echo $q; ?>" class="btn btn-ghost"><i class="fa-regular fa-eye"></i> <?php echo s(t('Ver')); ?></a>
        <a href="/graficos/fondo?<?php echo $q; ?>" class="btn btn-ghost"><i class="fa-solid fa-image"></i> Tamaño y fondo</a>
        <?php if (puede_descargar()): ?>
            <details class="gr-descargar">
                <summary class="btn btn-ghost"><i class="fa-solid fa-download"></i> Descargar lo guardado</summary>
                <div class="gr-descargar-menu">
                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=png"><i class="fa-regular fa-image"></i> PNG (ZIP si hay varias láminas)</a>
                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>
                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=odp"><i class="fa-regular fa-file-powerpoint"></i> Presentación .odp</a>
                </div>
            </details>
        <?php endif; ?>
    </div>
</section>

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
    </div>
</section>

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
