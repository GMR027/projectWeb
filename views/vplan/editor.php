<?php // Variables: $proyecto, $lamina, $config ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-pen-ruler"></i> Marcas del plano</h2>
        <p class="text-muted"><?php echo s($lamina->titulo); ?> · <?php echo s($proyecto->nombre); ?></p>
    </div>
    <div class="la-controles">
        <span id="ve-estado" class="text-sm text-muted"></span>
        <button type="button" id="ve-guardar" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        <a href="/vplan/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($lamina->id); ?>" class="btn btn-ghost">← Lámina</a>
    </div>
</div>

<div class="ve-barra card">
    <div class="ve-grupo" title="Seleccionar y líneas">
        <button type="button" data-tool="select" class="activo" title="Seleccionar / mover (Esc)"><i class="fa-solid fa-arrow-pointer"></i></button>
        <button type="button" data-tool="line" title="Línea continua"><i class="fa-solid fa-minus"></i></button>
        <button type="button" data-tool="dash" title="Línea discontinua"><span class="ve-ico">- - -</span></button>
        <button type="button" data-tool="dashdot" title="Línea trazo y punto"><span class="ve-ico">-·-</span></button>
        <button type="button" data-tool="dot" title="Línea punteada"><span class="ve-ico">···</span></button>
        <button type="button" data-tool="poly" title="Polilínea: clic en cada punto, doble clic o Enter para terminar"><i class="fa-solid fa-draw-polygon"></i></button>
        <button type="button" data-tool="pencil" title="Lápiz (trazo libre)"><i class="fa-solid fa-pencil"></i></button>
        <button type="button" data-tool="arrow" title="Flecha"><i class="fa-solid fa-arrow-right-long"></i></button>
        <button type="button" data-tool="darrow" title="Flecha doble"><i class="fa-solid fa-left-right"></i></button>
    </div>
    <div class="ve-grupo" title="Formas">
        <button type="button" data-tool="rect" title="Rectángulo"><i class="fa-regular fa-square"></i></button>
        <button type="button" data-tool="rrect" title="Rectángulo redondeado"><i class="fa-regular fa-square" style="border-radius:.5rem"></i></button>
        <button type="button" data-tool="ellipse" title="Elipse / círculo"><i class="fa-regular fa-circle"></i></button>
        <button type="button" data-tool="triangle" title="Triángulo"><i class="fa-solid fa-play" style="transform:rotate(-90deg)"></i></button>
        <button type="button" data-tool="diamond" title="Rombo"><i class="fa-regular fa-gem"></i></button>
        <button type="button" data-tool="pentagon" title="Pentágono"><span class="ve-ico">⬟</span></button>
        <button type="button" data-tool="hexagon" title="Hexágono"><span class="ve-ico">⬢</span></button>
        <button type="button" data-tool="star" title="Estrella"><i class="fa-regular fa-star"></i></button>
        <button type="button" data-tool="cross" title="Cruz (+)"><i class="fa-solid fa-plus"></i></button>
        <button type="button" data-tool="xmark" title="Equis (X)"><i class="fa-solid fa-xmark"></i></button>
        <button type="button" data-tool="check" title="Palomita"><i class="fa-solid fa-check"></i></button>
        <button type="button" data-tool="blockarrow" title="Flecha de bloque"><i class="fa-solid fa-arrow-right"></i></button>
        <button type="button" data-tool="chevron" title="Chevron"><i class="fa-solid fa-angles-right"></i></button>
        <button type="button" data-tool="trapezoid" title="Trapecio"><i class="fa-solid fa-caret-up" style="font-size:2rem"></i></button>
        <button type="button" data-tool="zone" title="Zona translúcida"><i class="fa-solid fa-circle" style="opacity:.4"></i></button>
    </div>
    <div class="ve-grupo" title="Texto, marcadores e imágenes">
        <button type="button" data-tool="text" title="Texto"><i class="fa-solid fa-font"></i></button>
        <button type="button" data-tool="restr" title="Rombo de restricción numerado"><span class="ve-rombo">1</span></button>
        <button type="button" data-tool="pin" title="Círculo numerado"><span class="ve-pin">1</span></button>
        <button type="button" data-tool="image" title="Insertar imagen"><i class="fa-regular fa-image"></i></button>
        <input type="file" id="ve-archivo" accept="image/*" hidden>
    </div>
    <div class="ve-grupo">
        <label class="ve-mini">Color <input type="color" id="t-stroke" value="#111111"></label>
        <label class="ve-mini">Grosor <input type="number" id="t-sw" min="1" max="60" value="4"></label>
    </div>
    <div class="ve-grupo">
        <button type="button" id="ve-undo" title="Deshacer (Ctrl+Z)"><i class="fa-solid fa-rotate-left"></i></button>
        <button type="button" id="ve-redo" title="Rehacer (Ctrl+Y)"><i class="fa-solid fa-rotate-right"></i></button>
        <button type="button" id="ve-fit" title="Encuadrar plano"><i class="fa-solid fa-expand"></i></button>
        <span class="text-sm text-muted" id="ve-zoom"></span>
        <span class="text-sm text-muted" id="ve-contador"></span>
    </div>
</div>

<div class="ve-area">
    <div id="ve-lienzo" class="ve-lienzo"><div id="ek-stage"></div></div>
    <aside id="ve-props" class="card ve-props">
        <h4>Propiedades</h4>
        <p class="ve-vacio text-muted text-sm">Selecciona una marca para editarla. Rueda del mouse = zoom; arrastra el fondo para desplazar el plano. Mayús al dibujar líneas = recta. Polilínea: doble clic o Enter para terminar. Las imágenes se redimensionan con las esquinas.</p>
        <div class="ve-campos" style="display:none">
            <div class="ve-f"><label>Color de línea</label><input type="color" id="p-stroke"></div>
            <div class="ve-f"><label>Relleno / color</label><input type="color" id="p-fill"></div>
            <div class="ve-f"><label class="ve-chk"><input type="checkbox" id="p-nofill"> Sin relleno</label></div>
            <div class="ve-f"><label>Grosor</label><input type="number" id="p-sw" min="1" max="60"></div>
            <div class="ve-f"><label>Estilo de línea</label>
                <select id="p-dash"><option value="solid">Continua</option><option value="dash">Discontinua</option><option value="dashdot">Trazo y punto</option><option value="dot">Punteada</option></select></div>
            <div class="ve-f"><label>Opacidad del relleno</label>
                <div class="ve-rango"><input type="range" id="p-fa" min="0" max="100" step="5"><span><input type="number" id="p-fa-n" min="0" max="100" step="5">%</span></div></div>
            <div class="ve-f"><label>Opacidad total de la marca</label>
                <div class="ve-rango"><input type="range" id="p-op" min="5" max="100" step="5"><span><input type="number" id="p-op-n" min="5" max="100" step="5">%</span></div></div>
            <div class="ve-f"><label>Tamaño de letra</label><input type="number" id="p-fs" min="6" max="400"></div>
            <div class="ve-f"><label>Número de restricción</label>
                <input type="number" id="p-n" min="1" max="99" list="lista-restr">
                <datalist id="lista-restr"><?php foreach ($config['restricciones'] as $r): ?><option value="<?php echo (int)$r['n']; ?>"><?php echo s($r['descripcion']); ?></option><?php endforeach; ?></datalist></div>
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
        <p class="text-muted text-sm" style="margin-top:1.2rem">Atajos: Supr borrar · Ctrl+C / V copiar y pegar · Ctrl+D duplicar · Ctrl+Z / Y deshacer y rehacer · Ctrl+S guardar · flechas mueven (Mayús = 10 px).</p>
    </aside>
</div>

<script>window.VPLAN_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;</script>
<script src="/vendor/konva.min.js"></script>
<script src="/vendor/vplan-editor.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/vplan-editor.js'); ?>"></script>
