<?php use Model\Grafico;
$edicion = !empty($edicion);
$esSimb = ($grafico->tipo ?? '') === 'simbologia';   // «Tamaño y fondo» de un gráfico ya creado ?>
<div class="page-header">
    <h2><i class="fa-solid fa-pen-ruler"></i> <?php echo $edicion ? 'Tamaño y fondo · ' . s($grafico->nombre) : ($esSimb ? 'Nueva simbología' : 'Nuevo gráfico'); ?></h2>
    <a href="<?php echo $edicion ? '/graficos/abrir?proyecto=' . s($proyecto->id) . '&id=' . (int)$grafico->id : ($esSimb ? '/simbolos?proyecto=' . s($proyecto->id) . '#simbologias' : '/graficos?proyecto=' . s($proyecto->id)); ?>" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <form method="POST" action="/graficos/<?php echo $edicion ? 'fondo' : 'crear'; ?>" class="formulario" id="gr-form" enctype="multipart/form-data">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if ($edicion): ?><input type="hidden" name="id" value="<?php echo (int)$grafico->id; ?>"><?php endif; ?>
        <?php if ($esSimb && !$edicion): ?><input type="hidden" name="tipo" value="simbologia"><?php endif; ?>
        <?php if (!$edicion): ?><div class="form-grid">
            <div class="campo campo-full">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($grafico->nombre); ?>" placeholder="<?php echo $esSimb ? 'Ej: Simbología del schedule' : 'Ej: Diagrama de conexiones'; ?>" required autofocus>
                <small class="text-muted">Fecha: <strong><?php echo date('d/m/Y'); ?></strong> (se pone automáticamente)</small>
            </div>
            <div class="campo campo-full">
                <label for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id">
                    <option value="">— Sin categoría —</option>
                    <?php foreach ($categorias ?? [] as $c): ?><option value="<?php echo (int)$c->id; ?>"<?php echo (int)$grafico->categoria_id === (int)$c->id ? ' selected' : ''; ?>><?php echo s($c->nombre); ?></option><?php endforeach; ?>
                </select>
                <small class="text-muted"><a href="/graficos/categorias?proyecto=<?php echo s($proyecto->id); ?>">Administrar categorías</a> · sirven para clasificar y filtrar los gráficos</small>
            </div>
        </div><?php endif; ?>
        <?php if ($edicion): ?><div class="form-grid">
            <div class="campo campo-full">
                <label for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id">
                    <option value="">— Sin categoría —</option>
                    <?php foreach ($categorias ?? [] as $c): ?><option value="<?php echo (int)$c->id; ?>"<?php echo (int)$grafico->categoria_id === (int)$c->id ? ' selected' : ''; ?>><?php echo s($c->nombre); ?></option><?php endforeach; ?>
                </select>
                <small class="text-muted"><a href="/graficos/categorias?proyecto=<?php echo s($proyecto->id); ?>">Administrar categorías</a> · sirven para clasificar y filtrar los gráficos</small>
            </div>
        </div><?php endif; ?>

        <h3 class="mt-2">Plantilla</h3>
        <h4 class="gr-sub">Tamaño</h4>
        <div class="form-grid">
            <div class="campo">
                <label for="gr-tamano">Formato común</label>
                <select id="gr-tamano">
                    <?php foreach (Grafico::TAMANOS as $grupo => $lista): ?>
                        <optgroup label="<?php echo s($grupo); ?>">
                            <?php foreach ($lista as $clave => [$etq, $w, $h]): ?><option value="<?php echo (int)$w . 'x' . (int)$h; ?>"<?php echo $clave === ($esSimb ? 'simb-schedule' : 'fullhd') ? ' selected' : ''; ?>><?php echo s($etq); ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                    <option value="custom">Personalizado</option>
                </select>
            </div>
            <div class="campo">
                <label>Orientación</label>
                <div class="gr-orient" role="group" aria-label="Orientación">
                    <button type="button" class="btn btn-ghost activo" data-orient="h"><i class="fa-solid fa-arrows-left-right"></i> Horizontal</button>
                    <button type="button" class="btn btn-ghost" data-orient="v"><i class="fa-solid fa-arrows-up-down"></i> Vertical</button>
                </div>
            </div>
            <div class="campo">
                <label>Ancho × alto (px)</label>
                <div class="ve-par"><input type="number" id="ancho" name="ancho" min="<?php echo Grafico::LADO_MIN; ?>" max="<?php echo Grafico::LADO_MAX; ?>" value="<?php echo (int)$grafico->ancho; ?>" required aria-label="Ancho"> <span>×</span> <input type="number" id="alto" name="alto" min="<?php echo Grafico::LADO_MIN; ?>" max="<?php echo Grafico::LADO_MAX; ?>" value="<?php echo (int)$grafico->alto; ?>" required aria-label="Alto"></div>
                <small class="text-muted" id="gr-resumen"></small>
            </div>
            <?php if ($edicion): ?>
            <div class="campo">
                <label>Si cambia el tamaño, el contenido…</label>
                <label class="campo-check"><input type="radio" name="ancla" value="centro" checked> se centra en el lienzo nuevo</label>
                <label class="campo-check"><input type="radio" name="ancla" value="esquina"> se queda en la esquina superior izquierda</label>
                <small class="text-muted">Vale para todas las láminas; todas comparten tamaño y fondo.</small>
            </div>
            <?php endif; ?>
        </div>

        <h4 class="gr-sub">Fondo</h4>
        <div class="gr-fondos" role="radiogroup" aria-label="Fondo de la plantilla">
            <?php foreach (Grafico::FONDOS as $clave => $nombre): ?>
                <label class="gr-fondo<?php echo $grafico->fondo === $clave ? ' activo' : ''; ?>">
                    <input type="radio" name="fondo" value="<?php echo s($clave); ?>" <?php echo $grafico->fondo === $clave ? 'checked' : ''; ?>>
                    <canvas width="240" height="150" data-fondo="<?php echo s($clave); ?>"></canvas>
                    <span class="gr-fondo-nombre"><?php echo $clave === 'imagen' ? '<i class="fa-regular fa-image"></i> ' : ''; echo s($nombre); ?><?php if ($clave === 'imagen'): ?> <small class="text-muted" id="gr-img-estado">· elige y ajusta</small><?php endif; ?></span>
                    <?php if ($clave === 'liso'): ?><input type="color" name="fondo_color" id="fondo_color" value="<?php echo s($grafico->fondo_color); ?>" aria-label="Color del fondo liso" title="Color del fondo liso"><?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>

        <input type="hidden" name="fondo_config" id="fondo_config" value="">

        <!-- Fondo personalizado: la imagen se elige y se ajusta en esta ventana (su archivo viaja con el formulario) -->
        <div class="modal-overlay" id="fi-modal" aria-hidden="true">
            <div class="modal-card fi-card" role="dialog" aria-modal="true" aria-labelledby="fi-titulo">
                <div class="bj-cab"><h3 id="fi-titulo">Fondo con imagen propia</h3><button type="button" class="btn btn-sm btn-ghost" id="fi-cerrar" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>
                <div class="fi-cuerpo">
                    <div class="fi-vista">
                        <canvas id="fi-canvas" width="560" height="315"></canvas>
                        <p class="text-muted text-sm" id="fi-nota">Elige una imagen para ver cómo queda en el lienzo.</p>
                    </div>
                    <div class="fi-ajustes">
                        <div class="campo">
                            <label for="fondo_imagen">Imagen (jpg, png, webp o gif · hasta 20 MB)</label>
                            <input type="file" id="fondo_imagen" name="fondo_imagen" accept="image/*">
                        </div>
                        <fieldset id="fi-controles" disabled>
                            <div class="campo">
                                <label for="fi-modo">Ajuste al formato</label>
                                <select id="fi-modo">
                                    <option value="cubrir">Cubrir: llena el lienzo (recorta lo que sobre)</option>
                                    <option value="contener">Contener: se ve completa (con bandas)</option>
                                    <option value="estirar">Estirar al tamaño del lienzo</option>
                                    <option value="mosaico">Mosaico: repetir la imagen</option>
                                </select>
                            </div>
                            <div id="fi-g-pos">
                                <div class="campo"><label>Zoom: <b id="fi-escala-v">100%</b></label><input type="range" id="fi-escala" min="20" max="400" value="100"></div>
                                <div class="campo"><label>Posición horizontal: <b id="fi-px-v">50%</b></label><input type="range" id="fi-px" min="0" max="100" value="50"></div>
                                <div class="campo"><label>Posición vertical: <b id="fi-py-v">50%</b></label><input type="range" id="fi-py" min="0" max="100" value="50"></div>
                            </div>
                            <div id="fi-g-mosaico" hidden>
                                <div class="campo"><label for="fi-repx">Repeticiones horizontales</label><input type="number" id="fi-repx" min="1" max="60" value="4"></div>
                                <div class="campo"><label for="fi-repy">Repeticiones verticales</label><input type="number" id="fi-repy" min="0" max="60" value="0"><small class="text-muted">0 = automático (mantiene la proporción de la imagen)</small></div>
                            </div>
                            <div class="campo"><label>Opacidad: <b id="fi-op-v">100%</b></label><input type="range" id="fi-op" min="5" max="100" value="100"></div>
                            <div class="campo"><label for="fi-color">Color de fondo <span class="text-muted">(bandas y zonas transparentes)</span></label><input type="color" id="fi-color" value="#ffffff"></div>
                            <button type="button" class="btn btn-sm btn-ghost" id="fi-reset"><i class="fa-solid fa-rotate-left"></i> Restablecer ajustes</button>
                        </fieldset>
                    </div>
                </div>
                <p class="bj-error text-sm" id="fi-error" hidden></p>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-ghost" id="fi-cancelar"><i class="fa-solid fa-xmark"></i> Cancelar</button>
                    <button type="button" class="btn btn-primary" id="fi-aplicar" disabled><i class="fa-solid fa-check"></i> Usar este fondo</button>
                </div>
            </div>
        </div>

        <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
        <p class="bj-error text-sm" id="gr-error-fondo" hidden></p>
        <button type="submit" class="btn btn-primary"><?php echo $edicion ? 'Aplicar y volver al editor' : 'Crear y abrir en el editor'; ?></button>
    </form>
</div>

<script src="/vendor/editor-fondos.js"></script>
<script>
(function () {
    const $ = id => document.getElementById(id);
    const ancho = $('ancho'), alto = $('alto'), tam = $('gr-tamano'), color = $('fondo_color');
    let orient = 'h';

    // Tamaño: un formato común rellena ancho y alto (en vertical se invierten)
    const aplicar = () => {
        const m = /^(\d+)x(\d+)$/.exec(tam.value); if (!m) return;
        const a = +m[1], b = +m[2], largo = Math.max(a, b), corto = Math.min(a, b);
        ancho.value = orient === 'h' ? largo : corto; alto.value = orient === 'h' ? corto : largo;
        resumen();
    };
    const resumen = () => { $('gr-resumen').textContent = (+ancho.value && +alto.value) ? ((+ancho.value) * (+alto.value) / 1e6).toFixed(1) + ' MP · ' + (+ancho.value >= +alto.value ? 'horizontal' : 'vertical') : ''; };
    tam.addEventListener('change', aplicar);
    document.querySelectorAll('[data-orient]').forEach(b => b.addEventListener('click', () => {
        orient = b.dataset.orient;
        document.querySelectorAll('[data-orient]').forEach(x => x.classList.toggle('activo', x === b));
        if (tam.value !== 'custom') aplicar();
        else { const a = ancho.value; ancho.value = alto.value; alto.value = a; resumen(); }
    }));
    [ancho, alto].forEach(i => i.addEventListener('input', () => { tam.value = 'custom'; orient = +ancho.value >= +alto.value ? 'h' : 'v'; document.querySelectorAll('[data-orient]').forEach(x => x.classList.toggle('activo', x.dataset.orient === orient)); resumen(); }));

    // Vistas previas de los fondos (el mismo dibujo que usa el editor)
    const marcar = () => document.querySelectorAll('.gr-fondo').forEach(l => l.classList.toggle('activo', l.querySelector('input[name=fondo]').checked));
    const pintar = () => document.querySelectorAll('canvas[data-fondo]').forEach(c => {
        if (c.dataset.fondo === 'imagen') return;
        window.VPFondos.pintar(c.getContext('2d'), c.width, c.height, c.dataset.fondo, color.value);
    });
    pintar();
    color.addEventListener('input', () => { document.querySelector('input[name=fondo][value=liso]').checked = true; marcar(); pintar(); });
    document.querySelectorAll('input[name=fondo]').forEach(r => r.addEventListener('change', marcar));

    // ---- Fondo personalizado ----
    const M = id => document.getElementById(id), modal = M('fi-modal'), cv = M('fi-canvas'), archivo = M('fondo_imagen');
    const tarjeta = document.querySelector('canvas[data-fondo=imagen]'), radioImg = document.querySelector('input[name=fondo][value=imagen]');
    const PRED = { modo: 'cubrir', escala: 1, posX: 50, posY: 50, repX: 4, repY: 0, opacidad: 100, color: '#ffffff' };
    let cfg = { ...PRED }, aplicada = null, previo = 'liso', img = new Image(), urlImg = null;   // aplicada: configuración confirmada (o null)

    const leer = () => ({ modo: M('fi-modo').value, escala: +M('fi-escala').value / 100, posX: +M('fi-px').value, posY: +M('fi-py').value,
        repX: Math.max(1, Math.min(60, +M('fi-repx').value || 1)), repY: Math.max(0, Math.min(60, +M('fi-repy').value || 0)), opacidad: +M('fi-op').value, color: M('fi-color').value });
    const escribir = c => { M('fi-modo').value = c.modo; M('fi-escala').value = Math.round(c.escala * 100); M('fi-px').value = c.posX; M('fi-py').value = c.posY; M('fi-repx').value = c.repX; M('fi-repy').value = c.repY; M('fi-op').value = c.opacidad; M('fi-color').value = c.color; etiquetas(); };
    const etiquetas = () => {
        M('fi-escala-v').textContent = M('fi-escala').value + '%'; M('fi-px-v').textContent = M('fi-px').value + '%'; M('fi-py-v').textContent = M('fi-py').value + '%'; M('fi-op-v').textContent = M('fi-op').value + '%';
        const mosaico = M('fi-modo').value === 'mosaico'; M('fi-g-mosaico').hidden = !mosaico; M('fi-g-pos').hidden = mosaico || M('fi-modo').value === 'estirar';
    };
    // Vista previa con la misma proporción que el lienzo
    const dibujar = (canvas, maxW, maxH, c) => {
        const W = +ancho.value || 1920, H = +alto.value || 1080, k = Math.min(maxW / W, maxH / H);
        canvas.width = Math.max(10, Math.round(W * k)); canvas.height = Math.max(10, Math.round(H * k));
        window.VPFondos.dibujarImagen(canvas.getContext('2d'), canvas.width, canvas.height, img.complete && img.naturalWidth ? img : null, c);
    };
    const redibujar = () => { etiquetas(); dibujar(cv, 560, 340, leer()); };
    const miniatura = () => {   // vista de la tarjeta, centrada en un fondo neutro
        if (!aplicada) return;
        const x = tarjeta.getContext('2d'), t = document.createElement('canvas'); x.fillStyle = '#0f172a'; x.fillRect(0, 0, 240, 150);
        dibujar(t, 232, 142, aplicada); x.drawImage(t, (240 - t.width) / 2, (150 - t.height) / 2);
    };
    const abrir = () => { previo = [...document.querySelectorAll('input[name=fondo]')].find(r => r.checked && r !== radioImg)?.value || previo; M('fi-error').hidden = true; modal.classList.add('abierto'); modal.setAttribute('aria-hidden', 'false'); redibujar(); };
    const cerrar = () => { modal.classList.remove('abierto'); modal.setAttribute('aria-hidden', 'true'); };
    const cancelar = () => {
        cerrar();
        if (!aplicada) { archivo.value = ''; img = new Image(); M('fi-controles').disabled = true; M('fi-aplicar').disabled = true; const r = document.querySelector('input[name=fondo][value=' + previo + ']'); if (r) r.checked = true; marcar(); }
        else escribir(aplicada);   // vuelve a los ajustes confirmados
    };
    // Clic en la tarjeta (también cuando ya está elegida) abre la ventana
    document.querySelector('.gr-fondo input[value=imagen]').closest('.gr-fondo').addEventListener('click', () => setTimeout(() => { if (radioImg.checked) abrir(); }, 0));
    archivo.addEventListener('change', () => {
        const f = archivo.files[0], e = M('fi-error'); e.hidden = true;
        if (!f) return;
        if (!f.type.startsWith('image/')) { e.textContent = 'El archivo no es una imagen'; e.hidden = false; archivo.value = ''; return; }
        if (f.size > 20 * 1024 * 1024) { e.textContent = 'La imagen pesa más de 20 MB'; e.hidden = false; archivo.value = ''; return; }
        if (urlImg) URL.revokeObjectURL(urlImg);
        urlImg = URL.createObjectURL(f); img = new Image();
        img.onload = () => { M('fi-controles').disabled = false; M('fi-aplicar').disabled = false; M('fi-nota').textContent = 'Se ajusta automáticamente a ' + (+ancho.value || 0) + ' × ' + (+alto.value || 0) + ' px; cambia el ajuste para personalizarlo.'; if (!aplicada) escribir({ ...PRED }); redibujar(); };
        img.src = urlImg;
    });
    ['fi-modo', 'fi-escala', 'fi-px', 'fi-py', 'fi-repx', 'fi-repy', 'fi-op', 'fi-color'].forEach(id => M(id).addEventListener('input', redibujar));
    M('fi-reset').addEventListener('click', () => { escribir({ ...PRED }); redibujar(); });
    M('fi-aplicar').addEventListener('click', () => {
        aplicada = leer(); M('fondo_config').value = JSON.stringify(aplicada);
        M('gr-img-estado').textContent = '· ' + (archivo.files[0] ? archivo.files[0].name : 'lista'); miniatura(); cerrar(); marcar();
    });
    modal.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault(); });   // Enter dentro de la ventana no envía el formulario
    M('fi-cancelar').addEventListener('click', cancelar); M('fi-cerrar').addEventListener('click', cancelar);
    modal.addEventListener('click', e => { if (e.target === modal) cancelar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('abierto')) cancelar(); });
    [ancho, alto].forEach(i => i.addEventListener('input', () => { miniatura(); if (modal.classList.contains('abierto')) redibujar(); }));
    // Con «Imagen propia» elegida, el formulario exige haber confirmado una imagen
    M('gr-form').addEventListener('submit', e => {
        if (radioImg.checked && !aplicada) { e.preventDefault(); const m = M('gr-error-fondo'); m.textContent = 'Elige y confirma la imagen del fondo personalizado, o escoge otro fondo.'; m.hidden = false; abrir(); }
    });
    <?php if ($edicion && $grafico->fondo === 'imagen' && $grafico->fondo_imagen): ?>
    // Edición: el fondo con imagen propia ya guardado se conserva (no hace falta volver a subirlo)
    (() => {
        const conf = <?php echo json_encode(Grafico::limpiarConfigFondo($grafico->fondo_config), JSON_HEX_TAG); ?>;
        img = new Image();
        img.onload = () => { M('fi-controles').disabled = false; M('fi-aplicar').disabled = false; escribir(conf); aplicada = conf; M('fondo_config').value = JSON.stringify(conf); M('gr-img-estado').textContent = '· actual'; miniatura(); M('fi-nota').textContent = 'Imagen actual del fondo; elige otra para reemplazarla.'; };
        img.src = <?php echo json_encode('/uploads/vplan/' . $grafico->fondo_imagen); ?>;
    })();
    <?php endif; ?>
    // Tarjeta de imagen propia: antes de elegir, un marcador
    (() => { const x = tarjeta.getContext('2d'); x.fillStyle = '#1e293b'; x.fillRect(0, 0, 240, 150); x.strokeStyle = '#64748b'; x.setLineDash([6, 5]); x.strokeRect(10, 10, 220, 130); x.fillStyle = '#94a3b8'; x.font = '600 15px sans-serif'; x.textAlign = 'center'; x.fillText('Subir imagen…', 120, 80); })();
    <?php if ($grafico->id === null && empty($_POST)): ?>aplicar();<?php else: ?>resumen();<?php endif; ?>
    <?php if ($edicion): ?>tam.value = 'custom';<?php endif; ?>
})();
</script>
