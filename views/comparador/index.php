<?php // Variables: $proyecto, $archivos (pdf e imágenes del proyecto) ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-code-compare"></i> <?php echo s(t('Comparador de documentos')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · compara dos imágenes o PDF con un deslizador. Los archivos externos se quedan en tu navegador; no se suben.</p>
    </div>
</div>

<div class="card cmp-fuentes">
    <?php foreach (['a' => ['Documento A', 'base (fondo)'], 'b' => ['Documento B', 'el que se superpone y se mueve']] as $k => [$titulo, $nota]): ?>
        <fieldset class="cmp-fuente" data-slot="<?php echo $k; ?>">
            <legend><?php echo s($titulo); ?> <small class="text-muted">· <?php echo s($nota); ?></small></legend>
            <?php if ($archivos): ?>
                <label>Del proyecto
                    <select class="cmp-proy" data-slot="<?php echo $k; ?>">
                        <option value="">— Elegir archivo —</option>
                        <?php foreach ($archivos as $a): ?>
                            <option value="<?php echo (int)$a->id; ?>" data-nombre="<?php echo s($a->nombre ?: $a->archivo_original); ?>" data-ext="<?php echo s($a->extension()); ?>"><?php echo s(($a->nombre ?: $a->archivo_original) . ($a->numero_revision !== '' && $a->numero_revision !== null ? ' · Rev. ' . $a->numero_revision : '') . ' (' . strtoupper($a->extension()) . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>Externo
                <input type="file" class="cmp-ext" data-slot="<?php echo $k; ?>" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,application/pdf,image/*">
            </label>
            <label class="cmp-pag" hidden>Página
                <input type="number" class="cmp-pagina" data-slot="<?php echo $k; ?>" min="1" value="1"> <span class="text-muted cmp-total"></span>
            </label>
            <span class="text-sm text-muted cmp-estado" data-slot="<?php echo $k; ?>">Sin documento</span>
        </fieldset>
    <?php endforeach; ?>
</div>

<div class="card cmp-panel" id="cmp" hidden>
    <div class="cmp-barra">
        <div class="cmp-grupo" role="group" aria-label="Modo de comparación">
            <button type="button" class="btn btn-sm btn-primary" data-modo="slider" title="Deslizador: A a un lado y B al otro"><i class="fa-solid fa-arrows-left-right-to-line"></i> Deslizador</button>
            <button type="button" class="btn btn-sm btn-ghost" data-modo="fade" title="Fundido entre A y B"><i class="fa-solid fa-circle-half-stroke"></i> Fundido</button>
            <button type="button" class="btn btn-sm btn-ghost" data-modo="diff" title="Diferencia: lo igual se ve negro, lo distinto se ilumina"><i class="fa-solid fa-not-equal"></i> Diferencias</button>
        </div>
        <label class="cmp-mini" id="cmp-dir-l">Dirección
            <select id="cmp-dir"><option value="v">Vertical |</option><option value="h">Horizontal —</option></select></label>
        <label class="cmp-mini" id="cmp-fade-l" hidden>Mezcla
            <input type="range" id="cmp-fade" min="0" max="100" value="50"></label>
        <div class="cmp-grupo" role="group" aria-label="Emparejar B">
            <button type="button" class="btn btn-sm btn-ghost" id="cmp-mover" aria-pressed="false" title="Arrastra el documento B para alinearlo con A"><i class="fa-solid fa-up-down-left-right"></i> Mover B</button>
            <button type="button" class="btn btn-sm btn-ghost" id="cmp-ajustar" title="Escala B al tamaño de A"><i class="fa-solid fa-expand"></i> Ajustar B a A</button>
            <button type="button" class="btn btn-sm btn-ghost" id="cmp-reset-b" title="Quita traslado, escala y giro de B"><i class="fa-solid fa-rotate-left"></i> Restablecer B</button>
            <button type="button" class="btn btn-sm btn-ghost" id="cmp-intercambiar" title="Cambia A por B"><i class="fa-solid fa-right-left"></i></button>
        </div>
        <label class="cmp-mini">Escala B <input type="number" id="cmp-esc" min="5" max="1000" step="1" value="100">%</label>
        <label class="cmp-mini">Giro B <input type="number" id="cmp-rot" min="-180" max="180" step="0.1" value="0">°</label>
        <label class="cmp-mini">X <input type="number" id="cmp-bx" step="1" value="0"></label>
        <label class="cmp-mini">Y <input type="number" id="cmp-by" step="1" value="0"></label>
        <button type="button" class="btn btn-sm btn-ghost" id="cmp-encuadrar" title="Ver ambos completos"><i class="fa-solid fa-maximize"></i> Encuadrar</button>
    </div>
    <div class="cmp-vista" id="cmp-vista" tabindex="0" aria-label="Zona de comparación. Rueda del mouse: zoom. Arrastrar: desplazar (o mover B si «Mover B» está activo)">
        <div class="cmp-capa cmp-capa-a"><div class="cmp-mundo" id="cmp-mundo-a"><canvas id="cmp-ca"></canvas></div></div>
        <div class="cmp-capa cmp-capa-b" id="cmp-capa-b"><div class="cmp-mundo" id="cmp-mundo-b"><canvas id="cmp-cb"></canvas></div></div>
        <div class="cmp-div" id="cmp-div" role="slider" tabindex="0" aria-label="Posición del deslizador" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50"><span><i class="fa-solid fa-arrows-left-right"></i></span></div>
        <span class="cmp-etq cmp-etq-a">A</span><span class="cmp-etq cmp-etq-b">B</span>
    </div>
    <div class="cmp-barra cmp-exportar">
        <strong><i class="fa-solid fa-file-pdf"></i> Exportar a PDF</strong>
        <label class="cmp-mini">Tamaño
            <select id="cmp-pdf-fmt">
                <optgroup label="Oficina">
                    <option value="215.9x279.4" selected>Carta (8.5 × 11 in)</option>
                    <option value="215.9x355.6">Oficio / Legal (8.5 × 14 in)</option>
                    <option value="210x297">A4</option>
                    <option value="297x420">A3</option>
                    <option value="279.4x431.8">Tabloide (11 × 17 in)</option>
                </optgroup>
                <optgroup label="Construcción / impresión">
                    <option value="457.2x609.6">Arch C (18 × 24 in)</option>
                    <option value="609.6x914.4">Arch D (24 × 36 in)</option>
                    <option value="914.4x1219.2">Arch E (36 × 48 in)</option>
                    <option value="594x841">A1</option>
                    <option value="841x1189">A0</option>
                    <option value="600x900">90 × 60 cm</option>
                </optgroup>
                <option value="custom">Personalizado…</option>
            </select></label>
        <span id="cmp-pdf-cust" class="cmp-mini" hidden>
            <input type="number" id="cmp-pdf-w" min="5" max="1500" step="0.1" value="60" aria-label="Ancho en cm"> ×
            <input type="number" id="cmp-pdf-h" min="5" max="1500" step="0.1" value="90" aria-label="Alto en cm"> cm</span>
        <label class="cmp-mini">Orientación
            <select id="cmp-pdf-ori"><option value="auto">Automática</option><option value="l">Horizontal</option><option value="p">Vertical</option></select></label>
        <label class="cmp-mini">Qué exportar
            <select id="cmp-pdf-que"><option value="ambos">A y B como se ven ahora (modo, deslizador, mezcla)</option><option value="a">Solo A</option><option value="b">Solo B (con su alineación)</option></select></label>
        <label class="cmp-mini">Área
            <select id="cmp-pdf-cont"><option value="vista">Vista actual (zoom y posición)</option><option value="todo">Documento A completo</option></select></label>
        <button type="button" class="btn btn-sm btn-primary" id="cmp-pdf-btn"><i class="fa-solid fa-file-pdf"></i> Descargar PDF</button>
        <span class="text-sm text-muted" id="cmp-pdf-estado"></span>
    </div>
    <p class="text-sm text-muted">Rueda = zoom · arrastrar = desplazar · activa «Mover B» para alinear B con A (flechas del teclado para afinar, Mayús = 10 px) · el deslizador también se mueve con ← →.</p>
</div>

<script src="/vendor/pdfjs/pdf.min.js"></script>
<script src="/vendor/jspdf/jspdf.umd.min.js"></script>
<script>window.CMP_CONFIG = { proyecto: <?php echo (int)$proyecto->id; ?>, nombreProyecto: <?php echo json_encode($proyecto->nombre, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>, usuario: <?php echo json_encode($_SESSION['nombre'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>, worker: '/vendor/pdfjs/pdf.worker.min.js' };</script>
<script src="/vendor/comparador.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/comparador.js'); ?>"></script>
