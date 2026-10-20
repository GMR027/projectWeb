<?php // Barra de herramientas, lienzo, propiedades y gestor de capas del editor de marcas (Konva). Variables: $config
// Lo usan el editor de marcas del vPlan y el Editor de gráficos. ?>
<div class="ve-hoja" id="ve-hoja">
<button type="button" class="ve-cerrar-ampliado" id="ve-cerrar-ampliado" aria-label="Cerrar vista ampliada" title="Cerrar"><i class="fa-solid fa-xmark"></i></button>
<?php
// Iconos de las herramientas (SVG de 24x24 con el color del texto; todos con el mismo trazo)
$svg = fn(string $d, string $extra = '') => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" ' . $extra . '>' . $d . '</svg>';
$ic = [
    'select' => '<path d="M5 3l14 7-6 2-2 6z" fill="currentColor" fill-opacity=".2"/>',
    'multi' => '<rect x="3" y="3" width="13" height="13" stroke-dasharray="3 3"/><path d="M12 12l9 3.500-4 1.500L15.500 21z" fill="currentColor" fill-opacity=".25"/>',
    'measure' => '<path d="M3 17L17 3l4 4L7 21z"/><path d="M7 13l2 2M10 10l2 2M13 7l2 2"/>',
    'al-izq' => '<path d="M4 3v18"/><rect x="7" y="6" width="13" height="4"/><rect x="7" y="14" width="7" height="4"/>',
    'al-cx' => '<path d="M12 3v18"/><rect x="5" y="6" width="14" height="4"/><rect x="8" y="14" width="8" height="4"/>',
    'al-der' => '<path d="M20 3v18"/><rect x="4" y="6" width="13" height="4"/><rect x="10" y="14" width="7" height="4"/>',
    'al-sup' => '<path d="M3 4h18"/><rect x="6" y="7" width="4" height="13"/><rect x="14" y="7" width="4" height="7"/>',
    'al-cy' => '<path d="M3 12h18"/><rect x="6" y="5" width="4" height="14"/><rect x="14" y="8" width="4" height="8"/>',
    'al-inf' => '<path d="M3 20h18"/><rect x="6" y="4" width="4" height="13"/><rect x="14" y="10" width="4" height="7"/>',
    'di-h-ini' => '<path d="M3 3v18M11 3v18M19 3v18"/><rect x="3" y="6" width="5" height="4"/><rect x="11" y="14" width="7" height="4"/>',
    'di-h-cen' => '<path d="M5 3v18M12 3v18M19 3v18"/><rect x="3" y="6" width="4" height="4"/><rect x="9" y="14" width="6" height="4"/><rect x="16" y="6" width="5" height="4"/>',
    'di-h-fin' => '<path d="M5 3v18M13 3v18M21 3v18"/><rect x="2" y="6" width="3" height="4"/><rect x="8" y="14" width="5" height="4"/>',
    'di-h-gap' => '<path d="M3 3v18M21 3v18"/><rect x="6" y="7" width="4" height="10"/><rect x="14" y="7" width="4" height="10"/>',
    'di-v-ini' => '<path d="M3 3h18M3 11h18M3 19h18"/><rect x="6" y="3" width="4" height="5"/><rect x="14" y="11" width="4" height="7"/>',
    'di-v-cen' => '<path d="M3 5h18M3 12h18M3 19h18"/><rect x="6" y="3" width="4" height="4"/><rect x="14" y="9" width="4" height="6"/>',
    'di-v-fin' => '<path d="M3 5h18M3 13h18M3 21h18"/><rect x="6" y="2" width="4" height="3"/><rect x="14" y="8" width="4" height="5"/>',
    'di-v-gap' => '<path d="M3 3h18M3 21h18"/><rect x="7" y="6" width="10" height="4"/><rect x="7" y="14" width="10" height="4"/>',
    'line' => '<path d="M4 20L20 4"/>',
    'dash' => '<path d="M4 20l3-3M10.5 13.5l3-3M17 7l3-3"/>',
    'dashdot' => '<path d="M4 20l3.5-3.5M11 13l.01.01M14 10l.01.01M17 7l3-3"/>',
    'dot' => '<path d="M5 19l.01.01M9 15l.01.01M13 11l.01.01M17 7l.01.01M20 4l.01.01" stroke-width="3.2"/>',
    'poly' => '<path d="M3 18l6-9 5 6 7-10"/><circle cx="3" cy="18" r="1.4" fill="currentColor"/><circle cx="9" cy="9" r="1.4" fill="currentColor"/><circle cx="14" cy="15" r="1.4" fill="currentColor"/><circle cx="21" cy="5" r="1.4" fill="currentColor"/>',
    'pencil' => '<path d="M4 20l1-4L16 5l3 3L8 19z"/><path d="M14 7l3 3"/>',
    'arrow' => '<path d="M4 20L20 4M20 4h-7M20 4v7"/>',
    'darrow' => '<path d="M5 19L19 5M19 5h-6M19 5v6M5 19h6M5 19v-6"/>',
    'rect' => '<rect x="4" y="5" width="16" height="14"/>',
    'rrect' => '<rect x="4" y="5" width="16" height="14" rx="5"/>',
    'ellipse' => '<ellipse cx="12" cy="12" rx="9" ry="7"/>',
    'triangle' => '<path d="M12 4l9 16H3z"/>',
    'diamond' => '<path d="M12 3l9 9-9 9-9-9z"/>',
    'pentagon' => '<path d="M12 3l9 7-3.5 11h-11L3 10z"/>',
    'hexagon' => '<path d="M7 4h10l5 8-5 8H7l-5-8z"/>',
    'star' => '<path d="M12 3l2.8 5.9 6.2.9-4.5 4.4 1.1 6.3L12 17.5l-5.6 3 1.1-6.3L3 9.8l6.2-.9z"/>',
    'cross' => '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
    'xmark' => '<path d="M5 5l14 14M19 5L5 19" stroke-width="3"/>',
    'check' => '<path d="M4 13l5 5L20 6" stroke-width="3"/>',
    'blockarrow' => '<path d="M3 9h9V4l9 8-9 8v-5H3z"/>',
    'chevron' => '<path d="M4 4h9l7 8-7 8H4l7-8z"/>',
    'trapezoid' => '<path d="M7 5h10l4 14H3z"/>',
    'octagon' => '<path d="M8 3h8l5 5v8l-5 5H8l-5-5V8z"/>',
    'parallelogram' => '<path d="M8 5h13l-5 14H3z"/>',
    'rtriangle' => '<path d="M4 4v16h16z"/>',
    'house' => '<path d="M12 3l9 6.5V20H3V9.5z"/>',
    'arrowleft' => '<path d="M21 9H12V4L3 12l9 8v-5h9z"/>',
    'arrowup' => '<path d="M9 21v-9H4l8-9 8 9h-5v9z"/>',
    'arrowdown' => '<path d="M9 3v9H4l8 9 8-9h-5V3z"/>',
    'arrowlr' => '<path d="M2 12l6-8v4h8V4l6 8-6 8v-4H8v4z"/>',
    'homeplate' => '<path d="M3 5h12l6 7-6 7H3z"/>',
    'star4' => '<path d="M12 2l2.6 7.4L22 12l-7.4 2.6L12 22l-2.6-7.4L2 12l7.4-2.6z"/>',
    'star6' => '<path d="M12 2l3 6.5 7-.5-3.5 6 3.5 6-7-.5L12 22l-3-6.5-7 .5 3.5-6L2 8l7 .5z"/>',
    'star8' => '<path d="M12 2l2 4.5 4.2-2.7-.7 4.9 4.9.7-2.7 4.2L22 12l-4.5 2 2.7 4.2-4.9.7.7 4.9-4.2-2.7L12 22l-2-4.5-4.2 2.7.7-4.9-4.9-.7L4.3 10 2 12l4.5-2-2.7-4.2 4.9-.7-.7-4.9L10 4.5z" transform="scale(.96) translate(.5 .5)"/>',
    'heart' => '<path d="M12 20.5C4 14.5 3 10.5 3 8.5A4.8 4.8 0 0 1 12 6.6 4.8 4.8 0 0 1 21 8.5c0 2-1 6-9 12z"/>',
    'moon' => '<path d="M16 3a9 9 0 1 0 5 15 7.5 7.5 0 0 1-5-15z"/>',
    'semicircle' => '<path d="M3 17a9 9 0 0 1 18 0z"/>',
    'bolt' => '<path d="M14 2L5 13h6l-2 9 9-12h-6z"/>',
    'drop' => '<path d="M12 3C8 9 5 12 5 15.5a7 7 0 0 0 14 0C19 12 16 9 12 3z"/>',
    'lshape' => '<path d="M5 4h6v9h8v7H5z"/>',
    'tshape' => '<path d="M3 4h18v6h-6v10H9V10H3z"/>',
    'ushape' => '<path d="M3 4h6v10h6V4h6v16H3z"/>',
    'callout' => '<path d="M3 4h18v12h-9l-4 5v-5H3z"/>',
    'flag' => '<path d="M5 3v18M5 4h15l-4 5 4 5H5"/>',
    'zone' => '<circle cx="12" cy="12" r="8.5" fill="currentColor" fill-opacity=".28"/>',
    'text' => '<path d="M5 7V4h14v3M12 4v16M9 20h6"/>',
    'table' => '<rect x="3" y="4" width="18" height="16" rx="1.5"/><path d="M3 9.5h18M3 14.5h18M9 4v16M15 4v16"/>',
    'restr' => '<path d="M12 2l10 10-10 10L2 12z" fill="#dc2626" stroke="#991b1b"/><text x="12" y="15.6" font-size="10" font-weight="700" text-anchor="middle" fill="#fff" stroke="none">1</text>',
    'pin' => '<circle cx="12" cy="12" r="9" fill="#0070c0" stroke="#fff" stroke-width="1.5"/><text x="12" y="15.6" font-size="10" font-weight="700" text-anchor="middle" fill="#fff" stroke="none">1</text>',
    'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    'v360' => '<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>',
    'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M3 18l6-5 4 3 3-3 5 4"/>',
    'undo' => '<path d="M8 4L4 8l4 4"/><path d="M4 8h10a6 6 0 0 1 0 12h-4"/>',
    'redo' => '<path d="M16 4l4 4-4 4"/><path d="M20 8H10a6 6 0 0 0 0 12h4"/>',
    'fit' => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
];
$tool = fn(string $t, string $titulo) => '<button type="button" data-tool="' . $t . '" title="' . htmlspecialchars($titulo) . '" aria-label="' . htmlspecialchars($titulo) . '">' . $svg($ic[$t]) . '</button>';
?>
<div class="ve-barra card">
    <div class="ve-grupo" data-nombre="Selección">
        <?php foreach ([['select', 'Seleccionar / mover (Esc). Mayús o Ctrl + clic suma marcas; Mayús + arrastrar dibuja un recuadro'], ['multi', 'Selección múltiple: arrastra un recuadro para elegir varias formas, textos y líneas y moverlas juntas']] as [$t, $ti]) echo $tool($t, $ti); ?>
    </div>
    <div class="ve-grupo" data-nombre="Líneas">
        <?php foreach ([['line', 'Línea continua'], ['dash', 'Línea discontinua'], ['dashdot', 'Línea trazo y punto'], ['dot', 'Línea punteada'],
            ['poly', 'Polilínea: clic en cada punto, doble clic o Enter para terminar'], ['pencil', 'Lápiz (trazo libre)'], ['arrow', 'Flecha'], ['darrow', 'Flecha doble']] as [$t, $ti]) echo $tool($t, $ti); ?>
    </div>
    <div class="ve-grupo" data-nombre="Formas">
        <?php foreach ([['rect', 'Rectángulo'], ['rrect', 'Rectángulo redondeado'], ['ellipse', 'Elipse / círculo'], ['triangle', 'Triángulo'], ['diamond', 'Rombo'], ['zone', 'Zona translúcida']] as [$t, $ti]) echo $tool($t, $ti); ?>
        <div class="ve-mas">
            <button type="button" id="ve-mas-formas" class="ve-mas-btn" title="Más formas" aria-label="Más formas" aria-haspopup="true" aria-expanded="false"><?php echo $svg($ic['star']); ?><svg viewBox="0 0 10 10" width="10" height="10" aria-hidden="true"><path d="M1 3l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            <div class="ve-mas-panel" id="ve-mas-panel" hidden>
                <?php foreach ([['pentagon', 'Pentágono'], ['hexagon', 'Hexágono'], ['star', 'Estrella'], ['cross', 'Cruz (+)'], ['xmark', 'Equis (X)'], ['check', 'Palomita'], ['blockarrow', 'Flecha de bloque'], ['chevron', 'Chevron'], ['trapezoid', 'Trapecio'], ['octagon', 'Octágono'], ['parallelogram', 'Paralelogramo'], ['rtriangle', 'Triángulo rectángulo'], ['house', 'Casa / pentágono con punta'], ['arrowleft', 'Flecha de bloque izquierda'], ['arrowup', 'Flecha de bloque arriba'], ['arrowdown', 'Flecha de bloque abajo'], ['arrowlr', 'Flecha de bloque doble horizontal'], ['homeplate', 'Etiqueta / pentágono flecha'], ['star4', 'Estrella de 4 puntas'], ['star6', 'Estrella de 6 puntas'], ['star8', 'Estrella de 8 puntas'], ['heart', 'Corazón'], ['moon', 'Luna creciente'], ['semicircle', 'Semicírculo'], ['bolt', 'Rayo'], ['drop', 'Gota'], ['lshape', 'Forma en L'], ['tshape', 'Forma en T'], ['ushape', 'Forma en U'], ['callout', 'Globo de texto'], ['flag', 'Bandera']] as [$t, $ti]) echo $tool($t, $ti); ?>
            </div>
        </div>
    </div>
    <?php
    // Cambiar el fondo (Editor) o el plano (vPlan): guarda lo hecho y abre la pantalla correspondiente
    $hrefFondo = isset($grafico) && isset($q) ? '/graficos/fondo?' . $q : (isset($lamina, $proyecto) ? '/vplan/editar?proyecto=' . $proyecto->id . '&id=' . $lamina->id : '');
    if ($hrefFondo): ?>
    <div class="ve-grupo" data-nombre="Fondo">
        <button type="button" id="ve-fondo" class="ve-txt" data-href="<?php echo s($hrefFondo); ?>" title="Guarda los cambios y abre la pantalla para cambiar el fondo<?php echo isset($grafico) ? ', su color o el tamaño de la lámina' : ' (reemplazar la imagen del plano)'; ?>"><?php echo $svg($ic['image']); ?> <?php echo isset($grafico) ? 'Cambiar fondo' : 'Cambiar plano'; ?></button>
    </div>
    <?php endif; ?>
    <div class="ve-grupo" data-nombre="Texto y marcadores">
        <?php foreach ([['text', 'Texto'], ['table', 'Tabla: arrastra para dibujarla; doble clic en una celda para escribir (Tab pasa a la siguiente)'], ['restr', 'Rombo de restricción numerado'], ['pin', 'Círculo numerado'], ['image', 'Insertar imagen'], ['link', 'Hipervínculo: dibuja un área sobre el plano y asígnale un enlace externo o una sección del proyecto'], ['v360', 'Vista 360: carga una foto 360 (equirectangular, 2:1), dibuja el área en el plano y se abrirá como vista interactiva en View360']] as [$t, $ti]) echo $tool($t, $ti); ?>
        <button type="button" id="ve-simbolos-btn" title="Símbolos: abre la biblioteca (cámaras, extintores, simbologías, cajetines…) para insertarlos de un clic" aria-label="Insertar símbolo"><?php echo $svg('<path d="M12 3l2.6 5.600 6.100.700-4.500 4.200 1.200 6L12 16.500 6.600 19.500l1.200-6L3.300 9.300l6.100-.7z"/>'); ?></button>
        <input type="file" id="ve-archivo" accept="image/*" hidden>
        <input type="file" id="ve-archivo-360" accept="image/jpeg,image/png,image/webp" hidden>
    </div>
    <div class="ve-grupo ve-grupo-ajustes" data-nombre="Medir">
        <?php echo $tool('measure', 'Medir: traza dos puntos. La primera vez indicas cuánto mide en la realidad y esa será la escala de las demás medidas'); ?>
        <span class="ve-mini text-muted" id="ve-escala-txt"></span>
        <label class="ve-mini">Unidad <select id="t-unidad" aria-label="Unidad de medida"><option value="mm">mm</option><option value="cm">cm</option><option value="m">m</option><option value="in">pulgadas</option></select></label>
        <button type="button" class="btn btn-sm btn-ghost" id="ve-escala-x" title="Quita la escala; las medidas dejan de calcularse">Quitar escala</button>
    </div>
    <div class="ve-grupo ve-grupo-ajustes" data-nombre="Cuadrícula">
        <label class="ve-mini"><input type="checkbox" id="t-grid"> Cuadrícula</label>
        <label class="ve-mini" title="Los puntos que dibujas y lo que mueves saltan a la cuadrícula"><input type="checkbox" id="t-snap"> Imán</label>
        <label class="ve-mini">Cada <input type="number" id="t-grid-n" min="2" max="5000" value="50" aria-label="Separación de la cuadrícula en píxeles"> px <span id="t-grid-eq" class="text-muted"></span></label>
        <label class="ve-mini"><input type="checkbox" id="t-reglas" checked> Reglas</label>
    </div>
    <div class="ve-grupo ve-grupo-ajustes" data-nombre="Trazo">
        <div class="ve-mini">Color <input type="color" id="t-stroke" value="#111111"></div>
        <label class="ve-mini">Grosor
            <input type="range" id="t-sw-r" min="1" max="60" value="4" aria-label="Grosor de línea">
            <input type="number" id="t-sw" min="1" max="60" value="4"> <span class="text-muted">px</span></label>
        <label class="ve-mini" title="Transparencia del trazo: 100 % es opaco; menos es más transparente">Opacidad
            <input type="range" id="t-op" min="5" max="100" step="5" value="100" aria-label="Opacidad de la línea">
            <input type="number" id="t-op-n" min="5" max="100" step="5" value="100"> <span class="text-muted">%</span></label>
        <label class="ve-mini ve-orto" title="Las líneas, flechas y polilíneas se trazan solo en horizontal o vertical (F8)"><input type="checkbox" id="t-orto"> Ortogonal <kbd>F8</kbd></label>
    </div>
    <div class="ve-grupo" data-nombre="Vista">
        <button type="button" id="ve-undo" title="Deshacer (Ctrl+Z)" aria-label="Deshacer"><?php echo $svg($ic['undo']); ?></button>
        <button type="button" id="ve-redo" title="Rehacer (Ctrl+Y)" aria-label="Rehacer"><?php echo $svg($ic['redo']); ?></button>
        <button type="button" id="ve-fit" title="Encuadrar plano" aria-label="Encuadrar plano"><?php echo $svg($ic['fit']); ?></button>
        <label class="ve-mini" title="Guarda solo, unos segundos después de tu último cambio"><input type="checkbox" id="t-auto" checked> Autoguardar</label>
        <span class="text-sm text-muted" id="ve-zoom"></span>
        <span class="text-sm text-muted" id="ve-contador"></span>
    </div>
    <div class="ve-grupo ve-solo-ampliado">
        <span id="ve-estado-amp" class="text-sm text-muted"></span>
        <button type="button" id="ve-guardar-amp" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    </div>
</div>

<div class="ve-area">
    <div id="ve-lienzo" class="ve-lienzo"><div id="ek-stage"></div><canvas id="ve-regla-h" class="ve-regla ve-regla-h"></canvas><canvas id="ve-regla-v" class="ve-regla ve-regla-v"></canvas><span id="ve-regla-esq" class="ve-regla-esq"></span></div>
    <div class="ve-lateral">
    <div class="ve-tabs card" role="tablist" aria-label="Paneles del editor">
        <button type="button" role="tab" data-tab="props" class="activa">Propiedades</button>
        <button type="button" role="tab" data-tab="capas">Capas</button>
        <?php if (!empty($config['laminas'])): ?><button type="button" role="tab" data-tab="laminas">Láminas</button><?php endif; ?>
        <button type="button" role="tab" data-tab="simbolos">Símbolos</button>
    </div>
    <div class="ve-tab-panel" data-panel="props">
    <aside id="ve-alinear" class="card ve-alinear" hidden>
        <h4>Alinear y agrupar <small class="text-muted" id="ve-sel-n"></small></h4>
        <div class="ve-f"><label for="al-rel">Alinear respecto a</label>
            <select id="al-rel"><option value="sel">La selección</option><option value="pag">La página</option><option value="prim">La primera elegida</option><option value="ult">La última elegida</option><option value="grande">La más grande</option><option value="chica">La más pequeña</option></select></div>
        <div class="ve-al-fila" role="group" aria-label="Alinear">
            <?php foreach ([['izq', 'Alinear a la izquierda'], ['cx', 'Centrar horizontalmente'], ['der', 'Alinear a la derecha'], ['sup', 'Alinear arriba'], ['cy', 'Centrar verticalmente'], ['inf', 'Alinear abajo']] as [$a, $t]): ?>
                <button type="button" data-al="<?php echo $a; ?>" title="<?php echo s($t); ?>" aria-label="<?php echo s($t); ?>"><?php echo $svg($ic['al-' . $a]); ?></button>
            <?php endforeach; ?>
        </div>
        <label class="text-sm text-muted ve-solo-multi">Distribuir (3 o más marcas)</label>
        <div class="ve-al-fila ve-solo-multi" role="group" aria-label="Distribuir">
            <?php foreach ([['h-ini', 'di-h-ini', 'Distribuir bordes izquierdos'], ['h-cen', 'di-h-cen', 'Distribuir centros horizontales'], ['h-fin', 'di-h-fin', 'Distribuir bordes derechos'], ['h-gap', 'di-h-gap', 'Mismo espacio horizontal entre marcas'],
                ['v-ini', 'di-v-ini', 'Distribuir bordes superiores'], ['v-cen', 'di-v-cen', 'Distribuir centros verticales'], ['v-fin', 'di-v-fin', 'Distribuir bordes inferiores'], ['v-gap', 'di-v-gap', 'Mismo espacio vertical entre marcas']] as [$a, $i, $t]): ?>
                <button type="button" data-di="<?php echo $a; ?>" title="<?php echo s($t); ?>" aria-label="<?php echo s($t); ?>"><?php echo $svg($ic[$i]); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="ve-btns">
            <button type="button" id="b-agrupar" class="btn btn-sm btn-ghost" title="Ctrl+G">Agrupar</button>
            <button type="button" id="b-desagrupar" class="btn btn-sm btn-ghost" title="Ctrl+Mayús+G">Desagrupar</button>
        </div>
    </aside>
    <aside id="ve-props" class="card ve-props">
        <h4>Propiedades</h4>
        <p class="ve-vacio text-muted text-sm">Selecciona una marca para editarla. Rueda del mouse = zoom; arrastra el fondo para desplazar el plano. Mayús al dibujar líneas = recta. Polilínea: doble clic o Enter para terminar. Las imágenes se redimensionan con las esquinas.</p>
        <div class="ve-campos" style="display:none">
            <div class="ve-f"><label>Color de línea</label><input type="color" id="p-stroke"></div>
            <div class="ve-f"><label>Relleno / color</label><input type="color" id="p-fill"></div>
            <div class="ve-f"><label class="ve-chk"><input type="checkbox" id="p-nofill"> Sin relleno</label></div>
            <div class="ve-f"><label>Grosor de línea</label>
                <div class="ve-rango"><input type="range" id="p-sw-r" min="1" max="80" step="1"><span><input type="number" id="p-sw" min="1" max="80"> px</span></div></div>
            <div class="ve-f"><label>Estilo de línea</label>
                <select id="p-dash"><option value="solid">Continua</option><option value="dash">Discontinua</option><option value="dashdot">Trazo y punto</option><option value="dot">Punteada</option></select></div>
            <div class="ve-f"><label>Opacidad del relleno</label>
                <div class="ve-rango"><input type="range" id="p-fa" min="0" max="100" step="5"><span><input type="number" id="p-fa-n" min="0" max="100" step="5">%</span></div></div>
            <div class="ve-f"><label>Opacidad total de la marca</label>
                <div class="ve-rango"><input type="range" id="p-op" min="5" max="100" step="5"><span><input type="number" id="p-op-n" min="5" max="100" step="5">%</span></div></div>
            <div class="ve-f"><label>Texto</label>
                <div class="ve-estilo-txt">
                    <button type="button" id="p-b" title="Negrita (Ctrl+B)" aria-label="Negrita"><b>B</b></button>
                    <button type="button" id="p-i" title="Cursiva (Ctrl+I)" aria-label="Cursiva"><i>I</i></button>
                    <button type="button" id="p-u" title="Subrayado (Ctrl+U)" aria-label="Subrayado"><u>U</u></button>
                    <button type="button" id="p-s" title="Tachado" aria-label="Tachado"><s>S</s></button>
                </div></div>
            <div class="ve-f"><label>Tipo de letra</label>
                <select id="p-ff"><?php foreach (['Arial', 'Verdana', 'Trebuchet MS', 'Georgia', 'Times New Roman', 'Courier New', 'Impact'] as $f): ?><option value="<?php echo s($f); ?>" style="font-family:'<?php echo s($f); ?>'"><?php echo s($f); ?></option><?php endforeach; ?></select></div>
            <div class="ve-f"><label>Alineación</label>
                <select id="p-al"><option value="left">Izquierda</option><option value="center">Centro</option><option value="right">Derecha</option></select></div>
            <div class="ve-f"><label>Tamaño de letra</label><input type="number" id="p-fs" min="6" max="400"></div>
            <div class="ve-f"><label>Unidad de esta medida</label><select id="p-unit"><option value="mm">mm</option><option value="cm">cm</option><option value="m">m</option><option value="in">pulgadas</option></select>
                <p class="text-sm" style="margin-top:.4rem">Mide: <b id="p-medida"></b></p></div>
            <div class="ve-f"><button type="button" id="p-ref" class="btn btn-sm btn-ghost" title="Indica cuánto mide en la realidad este segmento y recalcula la escala">Usar como referencia…</button></div>
            <div class="ve-f"><label>Tamaño de la imagen (ancho × alto, px)</label>
                <div class="ve-par"><input type="number" id="p-iw" min="8" aria-label="Ancho"> <span>×</span> <input type="number" id="p-ih" min="8" aria-label="Alto"></div>
                <small class="text-muted">Mantiene la proporción; también puedes arrastrar las esquinas.</small></div>
            <div class="ve-f"><label class="ve-chk"><input type="checkbox" id="p-bg-on"> Fondo del texto</label></div>
            <div class="ve-f"><label>Color del fondo</label><input type="color" id="p-bg"></div>
            <div class="ve-f"><label>Filas y columnas</label>
                <div class="ve-par"><input type="number" id="p-rows" min="1" max="30" aria-label="Filas"> <span>×</span> <input type="number" id="p-cols" min="1" max="15" aria-label="Columnas"></div></div>
            <div class="ve-f"><label class="ve-chk"><input type="checkbox" id="p-hdr"> Primera fila como encabezado</label></div>
            <div class="ve-f"><label>Color del encabezado</label><input type="color" id="p-hfill"></div>
            <div class="ve-f"><label>Color del texto</label><input type="color" id="p-tc"></div>
            <div class="ve-f"><label>Alto de filas / ancho de columnas</label>
                <div class="ve-par"><input type="number" id="p-rh" min="8" aria-label="Alto de fila"> <span>×</span> <input type="number" id="p-cw" min="10" aria-label="Ancho de columna"></div>
                <small class="text-muted">Doble clic en una celda para escribir; Tab pasa a la siguiente.</small></div>
<div class="ve-f ve-celdas" id="p-celdas">
                <label>Celdas seleccionadas: <b id="p-celdas-n">0</b></label>
                <p class="text-muted text-sm" id="p-celdas-vacio">Haz clic en una celda de la tabla; con Mayús o Ctrl suma más celdas.</p>
                <div id="p-celdas-cont">
                    <div class="ve-btns">
                        <button type="button" id="b-c-fila" class="btn btn-sm btn-ghost">Toda la fila</button>
                        <button type="button" id="b-c-col" class="btn btn-sm btn-ghost">Toda la columna</button>
                        <button type="button" id="b-c-todo" class="btn btn-sm btn-ghost">Todas</button>
                        <button type="button" id="b-c-nada" class="btn btn-sm btn-ghost">Ninguna</button>
                    </div>
                    <label style="margin-top:1rem">Relleno de las celdas</label>
                    <div class="ve-par"><input type="color" id="p-cf"> <button type="button" id="p-cf-x" class="btn btn-sm btn-ghost" title="Vuelve al color general de la tabla">Quitar</button></div>
                    <label style="margin-top:1rem">Color del texto de las celdas</label>
                    <div class="ve-par"><input type="color" id="p-ct"> <button type="button" id="p-ct-x" class="btn btn-sm btn-ghost" title="Vuelve al color de texto general">Quitar</button></div>
                </div>
            </div>
                        <div class="ve-f"><label>Número de restricción</label>
                <input type="number" id="p-n" min="1" max="99" list="lista-restr">
                <datalist id="lista-restr"><?php foreach ($config['restricciones'] as $r): ?><option value="<?php echo (int)$r['n']; ?>"><?php echo s($r['descripcion']); ?></option><?php endforeach; ?></datalist></div>
            <div class="ve-f ve-enlace" id="p-lk-360"><label>Foto 360</label>
                <img id="p-lk-360-img" alt="Foto 360" style="width:100%;border-radius:.6rem;border:1px solid var(--border);display:block;margin-bottom:.6rem">
                <button type="button" id="p-lk-360-cambiar" class="btn btn-sm btn-ghost"><i class="fa-solid fa-image"></i> Cambiar foto 360</button>
                <small class="text-muted" id="p-lk-360-med" style="display:block;margin-top:.4rem"></small></div>
            <div class="ve-f ve-enlace" id="p-lk-tipo-f"><label>Tipo de enlace</label>
                <select id="p-lk-tipo"><option value="ext">Enlace externo (Google Maps, web…)</option><option value="int">Sección del proyecto</option></select></div>
            <div class="ve-f ve-enlace" id="p-lk-ext"><label>Dirección (URL)</label><input type="url" id="p-lk-url" maxlength="500" placeholder="https://maps.app.goo.gl/…" autocomplete="off"></div>
            <div class="ve-f ve-enlace" id="p-lk-int"><label>Destino</label><select id="p-lk-dest"></select></div>
            <div class="ve-f ve-enlace"><label>Icono</label>
                <select id="p-lk-icon"><option value="link">🔗 Enlace</option><option value="globe">🌐 Vista 360</option><option value="map">📍 Ubicación</option><option value="doc">📄 Documento</option><option value="photo">📷 Foto</option><option value="video">▶ Video</option><option value="flag">🚩 Bandera</option><option value="info">ℹ Información (i)</option><option value="star">★ Estrella</option><option value="num">① Número</option><option value="none">Sin icono (solo área)</option></select></div>
            <div class="ve-f ve-enlace"><label>Número</label><input type="number" id="p-lk-num" min="1" max="999"></div>
            <div class="ve-f ve-enlace"><label>Color del icono</label><input type="color" id="p-lk-color"></div>
            <div class="ve-f ve-enlace"><label>Tamaño del icono</label>
                <div class="ve-rango"><input type="range" id="p-lk-size-r" min="6" max="120" step="1" aria-label="Tamaño del icono"><span><input type="number" id="p-lk-size" min="6" max="200"> px</span></div></div>
            <div class="ve-f ve-enlace"><label class="ve-chk"><input type="checkbox" id="p-lk-show"> Incluir el icono al exportar PNG / JPG / WebP</label></div>
            <div class="ve-f ve-enlace"><label>Etiqueta (texto de ayuda)</label><input type="text" id="p-lk-label" maxlength="120" placeholder="Ubicación de la obra"></div>
            <div class="ve-f ve-enlace"><a id="p-lk-abrir" class="btn btn-sm btn-ghost" href="#" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square"></i> Probar enlace</a>
                <p class="text-muted text-sm" style="margin-top:.4rem">En la lámina el icono siempre se ve; en el PDF nunca sale (solo queda el enlace externo clicable). Las imágenes lo incluyen si activas la casilla.</p></div>
            <div class="ve-f"><label>Comentario</label><textarea id="p-coment" rows="3" maxlength="600" placeholder="Nota sobre esta marca (se ve como una insignia amarilla; no sale en la imagen exportada)"></textarea></div>
            <div class="ve-f"><label>Capa</label><select id="p-capa"></select></div>
            <div class="ve-f"><label>Ligar a actividad (color automático)</label>
                <select id="p-act"><option value="">— Sin ligar —</option>
                    <?php foreach ($config['actividades'] as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo (int)$a['seq'] . '. ' . s(mb_strimwidth($a['nombre'], 0, 40, '…')); ?></option><?php endforeach; ?>
                </select></div>
            <div class="ve-btns">
                <button type="button" id="b-front" class="btn btn-sm btn-ghost">Al frente</button>
                <button type="button" id="b-back" class="btn btn-sm btn-ghost">Al fondo</button>
                <button type="button" id="b-dup" class="btn btn-sm btn-ghost">Duplicar</button>
                <button type="button" id="b-del" class="btn btn-sm btn-danger">Borrar</button>
            </div>
        </div>
        <h4 style="margin-top:1.6rem">Comentarios <small class="text-muted" id="ve-coment-n"></small></h4>
        <ul id="ve-coment-lista" class="ve-coment-lista"></ul>
        <p class="text-muted text-sm" style="margin-top:1.2rem">Atajos: Supr borrar · Ctrl+C / V copiar y pegar · Ctrl+D duplicar · Ctrl+Z / Y deshacer y rehacer · Ctrl+S guardar · flechas mueven (Mayús = 10 px).</p>
    </aside>
    </div>
    <div class="ve-tab-panel" data-panel="capas" hidden>
    <aside id="ve-capas" class="card ve-capas">
        <div class="ve-capas-cab"><h4>Capas</h4><button type="button" id="ve-capa-nueva" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> Nueva capa</button></div>
        <ul id="ve-capas-lista" class="ve-capas-lista"></ul>
        <p class="text-muted text-sm">Lo que dibujes cae en la capa activa (resaltada). El ojo la oculta, también del PNG guardado; el candado impide tocar sus marcas. Doble clic en el nombre para renombrarla; si no le pones nombre se llama «Capa N».</p>
    </aside>
    </div>
    <?php if (!empty($config['laminas'])): ?>
    <div class="ve-tab-panel" data-panel="laminas" hidden>
    <aside class="card ve-laminas">
        <div class="ve-capas-cab"><h4>Láminas <small class="text-muted" id="ve-lam-total"></small></h4></div>
        <div class="ve-btns">
            <button type="button" id="ve-lam-nueva" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> Nueva</button>
            <button type="button" id="ve-lam-dup" class="btn btn-sm btn-ghost"><i class="fa-regular fa-copy"></i> Duplicar</button>
            <button type="button" id="ve-lam-del" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> Eliminar</button>
        </div>
        <ul id="ve-lam-lista" class="ve-lam-lista"></ul>
        <p class="text-muted text-sm">Todas tienen el mismo tamaño y fondo. Arrastra para reordenar · doble clic en el nombre para renombrar · RePág / AvPág para cambiar de lámina.</p>
    </aside>
    </div>
    <?php endif; ?>
    <div class="ve-tab-panel" data-panel="simbolos" id="ve-tab-simbolos" hidden>
    <aside class="card ve-simbolos">
        <div class="ve-capas-cab"><h4>Símbolos</h4><?php if (!empty($config['urlSimbolos'])): ?><a href="<?php echo s($config['urlSimbolos']); ?>" class="btn btn-sm btn-ghost" target="_blank" rel="noopener"><i class="fa-solid fa-gear"></i> Administrar</a><?php endif; ?></div>
        <div class="ve-btns" style="margin-bottom:1rem">
            <button type="button" class="btn btn-sm btn-primary" id="sim-importar" title="Sube un .zip de biblioteca o varias imágenes (png, jpg, webp, gif, svg)"><i class="fa-solid fa-file-import"></i> Importar símbolos</button>
            <a class="btn btn-sm btn-ghost" href="/simbolos/exportar?proyecto=<?php echo (int)$config['proyecto']; ?>" title="Descarga la biblioteca del proyecto en un .zip"><i class="fa-solid fa-file-export"></i> Exportar</a>
            <input type="file" id="sim-importar-archivo" accept=".zip,image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg" multiple hidden>
        </div>
        <h5>Cajetines</h5>
        <div id="sim-cajetines" class="ve-btns" style="margin-bottom:1rem"></div>
        <div id="sim-sheetflow-caja" hidden>
            <h5>Tablas de SheetFlow</h5>
            <div id="sim-sheetflow" class="ve-btns" style="margin-bottom:1rem"></div>
        </div>
        <input type="search" id="sim-buscar" placeholder="Buscar símbolo o gráfico…" aria-label="Buscar">
        <div id="sim-lista"></div>
        <h4 style="margin-top:1.2rem">Gráficos y simbologías del proyecto</h4>
        <div class="ve-btns" style="margin-bottom:.8rem">
            <button type="button" class="btn btn-sm btn-primary" id="sim-gr-subir" title="Agrega una o varias imágenes de simbología desde tu equipo"><i class="fa-solid fa-plus"></i> Agregar simbologías</button>
            <button type="button" class="btn btn-sm btn-ghost" id="sim-gr-todas" title="Inserta todas las simbologías del proyecto"><i class="fa-solid fa-layer-group"></i> Insertar todas</button>
            <input type="file" id="sim-gr-archivo" accept="image/png,image/jpeg,image/webp,image/gif" multiple hidden>
        </div>
        <div id="sim-graficos" class="ve-sims"></div>
        <p class="text-muted text-sm">Un clic lo inserta en el centro de la vista, en la capa activa. Luego arrastra las esquinas para cambiar su tamaño.</p>
    </aside>
    </div>
    </div><!-- /ve-lateral -->
</div>
<!-- Pide una medida real (referencia de la escala) -->
<div class="modal-overlay" id="ve-modal-medida" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="mm-titulo">
        <h3 id="mm-titulo">Medida de referencia</h3>
        <p class="text-muted text-sm" id="mm-texto"></p>
        <div class="ve-par"><input type="number" id="mm-valor" min="0" step="any" aria-label="Medida real"> <select id="mm-unidad" aria-label="Unidad"><option value="mm">mm</option><option value="cm" selected>cm</option><option value="m">m</option><option value="in">pulgadas</option></select></div>
        <p class="bj-error text-sm" id="mm-error" hidden></p>
        <div class="modal-acciones"><button type="button" class="btn btn-ghost" id="mm-cancelar">Cancelar</button><button type="button" class="btn btn-primary" id="mm-ok"><i class="fa-solid fa-check"></i> Aceptar</button></div>
    </div>
</div>
</div><!-- /ve-hoja -->
