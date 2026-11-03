<?php
use Model\VplanTabla;
$fd = fn($f) => $f ? formatear_fecha($f) : '';
$q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$lamina->id;
$volver = $paquete ? '/vplan/paquete?proyecto=' . (int)$proyecto->id . '&id=' . (int)$paquete->id : '/vplan?proyecto=' . (int)$proyecto->id;
$conNav = count($navLaminas) > 1 || ($paquete && $puedeEditar);
// Un solo espacio de trabajo con tres modos: Ver · Acomodar bloques · Dibujar sobre el plano.
// El servidor pinta el modo inicial (enlaces directos y sin JS) y lienzo-modos.js cambia sin recargar.
$modoInicial = $modoInicial ?? 'ver';
$puedeDibujar = $puedeEditar && !empty($lamina->plano) && !empty($config);
if ($modoInicial === 'dibujar' && !$puedeDibujar) $modoInicial = 'ver';
if ($modoInicial === 'acomodar' && !$puedeEditar) $modoInicial = 'ver';
$modoDiseno = $modoInicial === 'acomodar';
$urlModo = fn(string $m) => '/vplan/ver?' . $q . ($m === 'ver' ? '' : '&modo=' . $m);
$lzModos = [['id' => 'ver', 'texto' => t('Ver'), 'icono' => 'fa-eye', 'url' => $urlModo('ver'), 'activo' => $modoInicial === 'ver']];
if ($puedeEditar) {
    $lzModos[] = ['id' => 'acomodar', 'texto' => t('Acomodar'), 'icono' => 'fa-vector-square', 'url' => $urlModo('acomodar'), 'activo' => $modoInicial === 'acomodar'];
}
if ($puedeDibujar) {
    $lzModos[] = ['id' => 'dibujar', 'texto' => t('Dibujar'), 'icono' => 'fa-pen-ruler', 'url' => $urlModo('dibujar'), 'activo' => $modoInicial === 'dibujar'];
}
$lzExtra = '<button type="button" class="lz-modo" id="vp-tareas-btn" aria-expanded="false" title="' . s(t('Ver la lista de tareas de la lámina')) . '"><i class="fa-solid fa-list-check"></i> ' . s(t('Tareas')) . '</button>';
if ($puedeEditar) {
    $lzExtra .= '<button type="button" class="lz-modo" id="vp-nota-nueva" title="' . s(t('Crear una nota en esta lámina')) . '"><i class="fa-solid fa-note-sticky"></i> ' . s(t('Nota')) . '</button>';
    $lzExtra .= '<a class="lz-modo" href="/vplan/editar?' . $q . '"' . glosario('lamina') . '><i class="fa-solid fa-sliders"></i> ' . s(t('Datos')) . '</a>';
}
$lz = [
    'icono' => 'fa-sheet-plastic',
    'titulo' => $lamina->titulo,
    'subtitulo' => $proyecto->nombre . ($paquete ? ' · ' . $paquete->nombre : '') . ' · ' . (int)$lamina->ancho_mm . ' × ' . (int)$lamina->alto_mm . ' mm',
    'chips' => array_merge(
        [['icono' => 'fa-ruler-combined', 'texto' => '<b>' . (int)$lamina->ancho_mm . ' × ' . (int)$lamina->alto_mm . '</b> mm']],
        [['icono' => 'fa-layer-group', 'texto' => '<b>' . count($navLaminas) . '</b> ' . (count($navLaminas) === 1 ? 'lámina' : 'láminas')]],
        !empty($lamina->plano) ? [['icono' => 'fa-image', 'texto' => 'plano de fondo', 'suave' => true]] : []
    ),
    'volver' => ['url' => $volver, 'texto' => $paquete ? $paquete->nombre : 'Láminas'],
    'modos' => $lzModos,
    'modo_bar_extra' => $lzExtra,
];
include __DIR__ . '/../lienzo/_cabecera.php';
?>

<div class="vp-barra-pest no-print" data-lz-panel="ver"<?php echo $modoInicial !== 'ver' ? ' hidden' : ''; ?>>
    <?php if ($conNav): ?><button type="button" class="btn btn-ghost" data-vp-nav-toggle aria-pressed="true" title="Mostrar u ocultar las láminas para tener más espacio"><i class="fa-solid fa-table-columns"></i> Láminas</button><?php endif; ?>
    <div class="vp-buscar no-print">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" id="vp-buscar" placeholder="Buscar tarea, tabla o texto…" aria-label="Buscar en la lámina" autocomplete="off">
        <div class="vp-buscar-lista" id="vp-buscar-lista" hidden></div>
    </div>
    <button type="button" class="btn btn-ghost" id="vp-ampliar"><i class="fa-solid fa-expand"></i> Ampliar</button>
    <div class="vp-zoom no-print" role="group" aria-label="Zoom de la lámina">
        <button type="button" class="vp-zb" id="vp-zoom-menos" title="Alejar" aria-label="Alejar"><i class="fa-solid fa-minus"></i></button>
        <span class="vp-zoom-val" id="vp-zoom-val" title="Tamaño de la lámina en pantalla (100 % = tamaño real, como en el PDF). En tablet o celular, pellizca con dos dedos">100 %</span>
        <button type="button" class="vp-zb" id="vp-zoom-mas" title="Acercar" aria-label="Acercar"><i class="fa-solid fa-plus"></i></button>
        <button type="button" class="btn btn-ghost" id="vp-zoom-fit" title="Ajustar la lámina al ancho disponible">Ajustar</button>
        <button type="button" class="btn btn-ghost" id="vp-zoom-real" title="Ver la lámina a su tamaño real (igual que el PDF)">Real</button>
    </div>
</div>

<?php if ($puedeDibujar): ?>
<div class="vp-barra-pest no-print lz-panel" data-lz-panel="dibujar"<?php echo $modoInicial !== 'dibujar' ? ' hidden' : ''; ?>>
    <span id="ve-estado" class="text-sm text-muted" aria-live="polite"></span>
    <button type="button" id="ve-guardar" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?php echo s(t('Guardar')); ?></button>
    <button type="button" class="btn btn-ghost" data-tour="editor-canvas" title="Recorrer el editor paso a paso (se muestra solo la primera vez)"><i class="fa-solid fa-circle-play"></i> <?php echo s(t('Guía rápida')); ?></button>
    <button type="button" class="btn btn-ghost" id="ve-ampliar"><i class="fa-solid fa-expand"></i> <?php echo s(t('Ampliar')); ?></button>
    <?php if ($conNav): ?><a class="btn btn-ghost" href="<?php echo $urlModo('ver'); ?>" data-lz-modo="ver" data-lz-url="<?php echo $urlModo('ver'); ?>"><i class="fa-solid fa-table-columns"></i> <?php echo s(t('Láminas')); ?></a><?php endif; ?>
</div>
<?php endif; ?>

<div class="vp-tareas no-print" id="vp-tareas" hidden></div>

<script>
(function () {
    // Modo inicial pintado por el servidor: el CSS y los motores lo leen del body.
    document.body.dataset.modoLienzo = <?php echo json_encode($modoInicial, JSON_HEX_TAG); ?>;
    // El motor de «Acomodar» se carga la primera vez que se entra (en Ver no hace falta y ahorra trabajo).
    var disenoSrc = <?php echo json_encode('/vendor/vplan-diseno.js?v=' . filemtime(__DIR__ . '/../../public/vendor/vplan-diseno.js'), JSON_HEX_TAG); ?>;
    var disenoCargando = false;
    function cargarDiseno(alCargar) {
        if (window.__vpDiseno) { window.__vpDiseno.activar(); if (alCargar) alCargar(); return; }
        if (!window.VP_DISENO) { document.addEventListener('DOMContentLoaded', function () { cargarDiseno(alCargar); }, { once: true }); return; }
        if (disenoCargando) return;
        disenoCargando = true;
        var s = document.createElement('script');
        s.src = disenoSrc;
        s.onload = function () {
            disenoCargando = false;
            if (window.__vpDiseno) { window.__vpDiseno.activar(); if (alCargar) alCargar(); }
        };
        s.onerror = function () { disenoCargando = false; };
        document.head.appendChild(s);
    }

    // Lista de tareas: se abre debajo de la hoja (en móvil reemplaza la vista si no cabe).
    var bt = document.getElementById('vp-tareas-btn'), lista = document.getElementById('vp-tareas');
    if (bt && lista) bt.addEventListener('click', function () {
        lista.hidden = !lista.hidden;
        bt.setAttribute('aria-expanded', lista.hidden ? 'false' : 'true');
        bt.classList.toggle('activo', !lista.hidden);
        if (!lista.hidden) {
            document.dispatchEvent(new CustomEvent('vp:pestana', { detail: { pest: 'tareas' } }));
            lista.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    // Al cambiar de modo, acomodar el motor que entra (reglas, escala del lienzo, Konva).
    document.addEventListener('vp:modo', function (e) {
        var m = e.detail && e.detail.modo, shell = document.getElementById('vp-shell'), sheet = document.getElementById('vp-sheet'), wrap = document.querySelector('.vp-dis-wrap');
        if (sheet) sheet.classList.toggle('vp-diseno', m === 'acomodar');
        if (shell) {
            shell.classList.toggle('vp-shell-diseno', m === 'acomodar');
            if (m === 'acomodar') shell.classList.remove('vp-sin-reglas');
        }
        if (wrap) wrap.classList.toggle('vp-dis-on', m === 'acomodar');
        document.querySelectorAll('.estado-vacio-hoja').forEach(function (x) { x.hidden = m === 'acomodar'; });
        if (m === 'acomodar') {
            cargarDiseno(function () {
                if (window.Tour && !window.Tour.yaVisto('vplan-diseno')) setTimeout(function () { window.Tour.iniciar('vplan-diseno'); }, 260);
            });
        }
        if (m === 'dibujar') {
            requestAnimationFrame(function () {
                if (window.__vplan) window.__vplan.encuadrar();
                window.dispatchEvent(new Event('resize'));
                // La hoja y el navegador se ocultan; subir a la barra de modos para no quedarse «abajo».
                var barra = document.querySelector('.lz-modos');
                if (barra) {
                    var offset = window.matchMedia('(max-width: 48rem)').matches ? 72 : 8;   // barra superior fija en móvil
                    window.scrollTo({ top: Math.max(0, barra.getBoundingClientRect().top + window.scrollY - offset), behavior: 'auto' });
                }
            });
            if (window.Tour && !window.Tour.yaVisto('editor-canvas')) setTimeout(function () { window.Tour.iniciar('editor-canvas'); }, 420);
        }
        if (m === 'ver') requestAnimationFrame(function () { window.dispatchEvent(new Event('resize')); });
        // La hoja pudo estar oculta (Dibujar): al mostrarla se vuelve a ajustar la letra de las tablas.
        setTimeout(function () {
            if (window.vplanAjustar) window.vplanAjustar();
            if (window.vplanAjustar) window.dispatchEvent(new Event('resize'));
        }, 90);
    });

    // Si la página abre directamente en «Dibujar», el editor termina de montar y se encuadra.
    document.addEventListener('vplan:listo', function () {
        if (document.body.dataset.modoLienzo === 'dibujar' && window.__vplan) window.__vplan.encuadrar();
    });
})();
</script>
<script src="/vendor/lienzo-modos.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/lienzo-modos.js'); ?>"></script>

<?php if (!(int)$lamina->visible): ?><div class="alerta alerta-error no-print">Esta lámina está <strong>oculta</strong>: no aparece en las listas de los visitantes ni en el PDF. Muéstrala desde el gestor del vPlan.</div><?php endif; ?>
<?php if ($puedeEditar && $desactualizada): ?>
<div class="alerta alerta-error no-print">El avance del programa cambió desde que se dibujaron las marcas: el plano está desactualizado.
    <a href="/vplan/ver?<?php echo $q; ?>&modo=dibujar&auto=1" class="btn btn-sm btn-primary" style="margin-left:1rem">Actualizar plano</a></div>
<?php endif; ?>

<?php if ($puedeEditar) include __DIR__ . '/_diseno-barra.php'; ?>
<div class="vp-shell<?php echo $conNav ? ' vp-con-nav' : ''; ?><?php echo $modoDiseno ? ' vp-shell-diseno' : ' vp-sin-reglas'; ?>" id="vp-shell">
<?php if ($conNav) include __DIR__ . '/_nav-laminas.php'; ?>
<div class="vp-dis-wrap<?php echo $modoDiseno ? ' vp-dis-on' : ''; ?>" data-lz-panel="ver acomodar"<?php echo $modoInicial === 'dibujar' ? ' hidden' : ''; ?>>
<div class="vp-reglas" id="vp-reglas">
    <div class="vp-regla-esq"></div>
    <div class="vp-regla-celda vp-regla-celda-x"><canvas class="vp-regla" id="vp-regla-x" title="Arrastra hacia la hoja para crear una guía horizontal"></canvas></div>
    <div class="vp-regla-celda vp-regla-celda-y"><canvas class="vp-regla" id="vp-regla-y" title="Arrastra hacia la hoja para crear una guía vertical"></canvas></div>
    <div class="vp-reglas-hoja">
<div class="vp-hoja" id="vp-hoja">
<button type="button" class="vp-cerrar-ampliado no-print" id="vp-cerrar-ampliado" aria-label="Cerrar vista ampliada" title="Cerrar"><i class="fa-solid fa-xmark"></i></button>
<?php include __DIR__ . '/_hoja.php'; ?>
<?php // Hoja sin nada: el usuario nuevo ve por dónde empezar, no una hoja en blanco
if ($puedeEditar && !$modoDiseno && empty($bloques)): ?>
<div class="estado-vacio estado-vacio-hoja no-print">
    <i class="fa-solid fa-wand-sparkles"></i>
    <b><?php echo s(t('Esta hoja está vacía')); ?></b>
    <p><?php echo s(t('Acomoda los bloques (tablas del programa, estampas, símbolos…) y sube el plano de fondo. Se hace en dos minutos.')); ?></p>
    <div class="estado-vacio-acciones">
        <a class="btn btn-primary" href="<?php echo $urlModo('acomodar'); ?>&amp;tour=1"><i class="fa-solid fa-vector-square"></i> <?php echo s(t('Acomodar bloques')); ?></a>
        <a class="btn btn-ghost" href="/vplan/editar?<?php echo $q; ?>"><i class="fa-regular fa-image"></i> <?php echo s(t('Subir el plano')); ?></a>
        <a class="btn btn-ghost" href="/manual<?php echo $proyecto ? '?proyecto=' . (int)$proyecto->id : ''; ?>#vplan"><i class="fa-regular fa-circle-question"></i> <?php echo s(t('Guía de 1 minuto')); ?></a>
    </div>
</div>
<?php endif; ?>
</div><!-- /vp-hoja -->
    </div>
</div>
<?php if ($puedeEditar) include __DIR__ . '/_diseno-panel.php'; ?>
</div>
</div><!-- /vp-shell -->

<?php if ($puedeDibujar): ?>
<div class="lz-panel" id="vp-modo-dibujar" data-lz-panel="dibujar"<?php echo $modoInicial !== 'dibujar' ? ' hidden' : ''; ?>>
    <?php include __DIR__ . '/_editor-lienzo.php'; ?>
</div>
<?php endif; ?>

<div class="vp-zoom-flot no-print" data-lz-panel="ver acomodar" role="group" aria-label="Zoom y navegación de la lámina"<?php echo $modoInicial === 'dibujar' ? ' hidden' : ''; ?>>
    <button type="button" data-vpz="menos" title="Alejar" aria-label="Alejar"><i class="fa-solid fa-minus"></i></button>
    <span id="vp-zoom-flot-val" class="vp-zoom-flot-val">100 %</span>
    <button type="button" data-vpz="mas" title="Acercar" aria-label="Acercar"><i class="fa-solid fa-plus"></i></button>
    <button type="button" data-vpz="fit" title="Ajustar la lámina al ancho" aria-label="Ajustar"><i class="fa-solid fa-arrows-to-dot"></i></button>
    <button type="button" data-vpz="centro" title="Centrar la lámina" aria-label="Centrar"><i class="fa-solid fa-crosshairs"></i></button>
</div>

<section class="card vp-acciones no-print" id="vp-acciones" data-lz-panel="ver acomodar"<?php echo $modoInicial === 'dibujar' ? ' hidden' : ''; ?>>
    <div class="vp-notas">
        <h4><i class="fa-solid fa-note-sticky"></i> Notas</h4>
        <p class="text-muted" id="vp-notas-vacio" hidden>Aún no hay notas en esta lámina.</p>
        <ul class="vp-notas-lista" id="vp-notas-lista"></ul>
    </div>
</section>

<?php if ($puedeEditar): $maxLargo = \Model\VplanNota::MAX_LARGO; include __DIR__ . '/../templates/_notas-modal.php'; endif; ?>

<script src="/vendor/notas.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/notas.js'); ?>"></script>
<script>
(function () {
    const lamina = <?php echo (int)$lamina->id; ?>;
    const puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    const notas = <?php echo json_encode($notas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    // ---- Ampliar: la lámina a pantalla completa, con botón de cerrar abajo a la izquierda ----
    const hoja = document.getElementById('vp-hoja');
    // Se amplía la página (main), no solo la lámina: así siguen viéndose las paletas, los menús,
    // las hojas inferiores y los modales, que cuelgan fuera de la lámina.
    const escena = document.getElementById('contenido-principal') || hoja;
    const enDiseno = () => document.body.dataset.modoLienzo === 'acomodar';
    const modalConfirm = document.getElementById('modal-confirm');   // se mueve dentro de la lámina para que se vea a pantalla completa
    let barraAmp = null, panelAmp = null;
    const abrir = () => {
        hoja.classList.add('vp-ampliado');
        document.body.classList.add('vp-sin-scroll', 'vp-en-ampliado');
        if (modalConfirm) hoja.appendChild(modalConfirm);
        const zoomFlot = document.querySelector('.vp-zoom-flot');
        if (zoomFlot && window.CapaSuperior) CapaSuperior.adoptar(zoomFlot);
        // En modo diseño la barra y el panel quedan dentro de la lámina: si no, la vista ampliada los taparía.
        if (enDiseno()) {
            const barra = document.getElementById('vp-dis-barra');
            if (barra && !barra.classList.contains('vp-dis-barra-amp')) {
                barraAmp = { nodo: barra, padre: barra.parentNode, sig: barra.nextSibling };
                hoja.insertBefore(barra, hoja.firstChild); barra.classList.add('vp-dis-barra-amp');
            }
            const panel = document.getElementById('vp-dis-panel');
            if (panel && !panel.classList.contains('en-hoja') && !panel.classList.contains('vp-dis-panel-amp')) {
                panelAmp = { nodo: panel, padre: panel.parentNode, sig: panel.nextSibling };
                hoja.appendChild(panel); panel.classList.add('vp-dis-panel-amp');
            }
        }
        if (escena.requestFullscreen) escena.requestFullscreen().catch(() => {});   // si el navegador no lo permite, queda a pantalla completa de la página
    };
    const cerrar = () => {
        if (!hoja.classList.contains('vp-ampliado')) return;
        hoja.classList.remove('vp-ampliado');
        document.body.classList.remove('vp-sin-scroll', 'vp-en-ampliado');
        if (modalConfirm && modalConfirm.parentNode === hoja) document.body.appendChild(modalConfirm);
        if (barraAmp) { barraAmp.nodo.classList.remove('vp-dis-barra-amp'); barraAmp.padre.insertBefore(barraAmp.nodo, barraAmp.sig); barraAmp = null; }
        if (panelAmp) { panelAmp.nodo.classList.remove('vp-dis-panel-amp'); panelAmp.padre.insertBefore(panelAmp.nodo, panelAmp.sig); panelAmp = null; }
        if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
    };
    document.getElementById('vp-ampliar').addEventListener('click', abrir);
    document.getElementById('vp-cerrar-ampliado').addEventListener('click', cerrar);
    document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) cerrar(); });   // Esc del navegador
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && hoja.classList.contains('vp-ampliado')) cerrar(); });

    // ---- Notas ----
    window.Notas.iniciar({ campo: 'lamina', id: lamina, puedeEditar, notas, urlGuardar: '/api/vplan/nota/guardar', urlEliminar: '/api/vplan/nota/eliminar' });

    // ---- Buscar dentro de la lámina: lleva la vista al bloque y lo resalta ----
    const buscar = document.getElementById('vp-buscar'), listaBuscar = document.getElementById('vp-buscar-lista');
    if (buscar && listaBuscar) {
        const icoTipo = { tareas: 'fa-list-check', tabla: 'fa-table', titulo: 'fa-heading', texto: 'fa-font', plano: 'fa-map', logo: 'fa-copyright', estampa: 'fa-wand-magic-sparkles', cajetin: 'fa-table-list', imagen: 'fa-shapes', simbologia: 'fa-icons' };
        let hits = [];
        const bloques = [...document.querySelectorAll('#vp-sheet .vp-blk')].map(el => ({ el: el, tipo: el.dataset.tipo || '', txt: (el.textContent || '').replace(/\s+/g, ' ').trim() })).filter(x => x.txt);
        const cerrarBuscar = () => { listaBuscar.hidden = true; listaBuscar.innerHTML = ''; hits = []; };
        const irA = b => {
            cerrarBuscar(); buscar.value = '';
            b.el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
            b.el.classList.remove('vp-resaltar'); void b.el.offsetWidth; b.el.classList.add('vp-resaltar');
            setTimeout(() => b.el.classList.remove('vp-resaltar'), 2200);
        };
        buscar.addEventListener('input', () => {
            const q = buscar.value.trim().toLowerCase();
            if (q.length < 2) { cerrarBuscar(); return; }
            hits = bloques.filter(x => x.txt.toLowerCase().includes(q)).slice(0, 8);
            listaBuscar.innerHTML = hits.length
                ? hits.map((b, i) => '<button type="button" data-i="' + i + '"><i class="fa-solid ' + (icoTipo[b.tipo] || 'fa-square') + '" aria-hidden="true"></i><span>' + b.txt.slice(0, 90).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])) + '</span></button>').join('')
                : '<p class="text-muted">Sin resultados</p>';
            listaBuscar.hidden = false;
        });
        listaBuscar.addEventListener('click', e => { const b = e.target.closest('button'); if (b) irA(hits[+b.dataset.i]); });
        buscar.addEventListener('keydown', e => {
            if (e.key === 'Escape') { cerrarBuscar(); buscar.blur(); }
            if (e.key === 'Enter') { const b = listaBuscar.querySelector('button'); if (b) b.click(); }
        });
        document.addEventListener('click', e => { if (!e.target.closest('.vp-buscar')) cerrarBuscar(); });
    }
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // (Se espera al DOM completo: gestos-lienzo.js y hoja-inferior.js se cargan al final de la página.)
    // ---- Ficha de tarea en hoja inferior + vista «Tareas» ----
    const editable = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    const laminaId = <?php echo (int)$lamina->id; ?>;
    const sheet = document.getElementById('vp-sheet'), lista = document.getElementById('vp-tareas');
    if (!sheet || !window.HojaInferior) return;
    const tactil = !!(window.GestosLienzo && GestosLienzo.esTactil());
    const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(r => r.json());
    const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const etiqueta = c => {
        if (c === 'inicio') return 'Inicio';
        if (c === 'fin') return 'Fin';
        if (c === 'pct') return 'Avance';
        const m = /^rec-(\d+)-(inicio|fin)$/.exec(c);
        if (m) return 'Recovery ' + String(m[1]).padStart(2, '0') + ' · ' + (m[2] === 'inicio' ? 'inicio' : 'fin');
        return c;
    };

    function camposDeFila(tr) {
        const inputs = [...tr.querySelectorAll('input, textarea')];
        if (inputs.length) return inputs.map(inp => ({
            inp: inp,
            etiqueta: inp.dataset.titulo || (inp.classList.contains('vp-fecha-act') ? (inp.dataset.tipo === 'inicio' ? 'Inicio' : 'Fin') : inp.classList.contains('vp-progreso-act') ? 'Avance (%)' : 'Dato'),
            tipo: inp.type === 'date' ? 'date' : inp.type === 'number' ? 'number' : 'text',
            valor: inp.value,
        }));
        return [...tr.querySelectorAll('td[data-campo]')].filter(td => td.dataset.campo !== 'tarea').map(td => ({
            etiqueta: etiqueta(td.dataset.campo), tipo: 'texto', valor: td.textContent.trim(), soloLectura: true,
        }));
    }

    function tareas() {
        const vistos = new Set();
        return [...sheet.querySelectorAll('tr[data-actividad]')].map(tr => ({
            id: tr.dataset.actividad,
            nombre: (tr.dataset.tarea || '').trim() || 'Tarea',
            campos: camposDeFila(tr),
            fila: tr,
        })).filter(t => {
            if (vistos.has(t.id)) return false;   // la misma tarea puede salir en dos bloques
            vistos.add(t.id);
            return true;
        });
    }
    let listaTareas = [];

    async function guardar(campo, valor) {
        const inp = campo.inp;
        if (!inp || campo.soloLectura) return { success: true };
        try {
            let d;
            if (inp.classList.contains('vp-fecha-act')) {
                d = await post('/api/actividad/actualizar-fecha', { actividad_id: inp.dataset.actividad, tipo: inp.dataset.tipo, fecha: valor });
                if (d.success) {
                    const tr = inp.closest('tr');
                    const ini = tr.querySelector('[data-tipo=inicio]'), fin = tr.querySelector('[data-tipo=fin]');
                    if (ini) ini.value = d.fecha_inicio;
                    if (fin) fin.value = d.fecha_fin;
                }
            } else if (inp.classList.contains('vp-progreso-act')) {
                d = await post('/api/actividad/actualizar-progreso', { actividad_id: inp.dataset.actividad, progreso: valor });
                if (d.success) {
                    inp.value = d.progreso;
                    const td = inp.closest('td');
                    td.classList.toggle('vp-verde', d.progreso >= 100);
                    td.classList.toggle('vp-amarillo', d.progreso > 0 && d.progreso < 100);
                }
            } else if (inp.classList.contains('vp-col-texto')) {
                d = await post('/api/vplan/columna-texto', { lamina: laminaId, actividad: inp.dataset.actividad, col: inp.dataset.col, valor: valor });
            } else if (inp.classList.contains('vp-prg-campo')) {
                d = await post('/api/programa/campo', { actividad_id: inp.dataset.actividad, campo: inp.dataset.campo, valor: valor });
            } else if (inp.classList.contains('vp-rec') && inp.dataset.n) {
                d = await post('/api/vplan/recovery', { lamina: laminaId, actividad: inp.dataset.actividad, n: inp.dataset.n, campo: inp.dataset.campo, valor: valor });
            } else {
                d = { success: true };
            }
            if (d && d.success) {
                if (!inp.classList.contains('vp-progreso-act')) inp.value = valor;
                inp.classList.remove('msr-error');
            } else if (inp) inp.classList.add('msr-error');
            return d || { success: false };
        } catch (_) {
            if (inp) inp.classList.add('msr-error');
            return { success: false };
        }
    }

    const hoja = HojaInferior.crear('vp-tarea');

    function abrir(id) {
        const t = listaTareas.find(x => x.id === String(id));
        if (!t) return;
        sheet.querySelectorAll('tr.vp-fila-sel').forEach(tr => tr.classList.remove('vp-fila-sel'));
        t.fila.classList.add('vp-fila-sel');

        const caja = document.createElement('div');
        caja.className = 'vp-tarea-ficha';
        const estado = document.createElement('p');
        estado.className = 'vp-tarea-estado'; estado.setAttribute('role', 'status');
        estado.textContent = editable ? 'Los cambios se guardan al instante.' : 'Vista de consulta.';
        const campos = document.createElement('div');
        campos.className = 'vp-tarea-campos';
        const controles = {};

        t.campos.forEach(c => {
            const f = document.createElement('label');
            f.className = 'vp-tarea-campo';
            const ttl = document.createElement('span'); ttl.textContent = c.etiqueta;
            let control;
            if (c.soloLectura) {
                control = document.createElement('b'); control.textContent = c.valor || '—';
            } else {
                control = document.createElement('input');
                control.type = c.tipo; control.value = c.valor; control.autocomplete = 'off';
                control.setAttribute('aria-label', c.etiqueta);
                if (c.tipo === 'number' && c.etiqueta.indexOf('Avance') === 0) { control.min = '0'; control.max = '100'; control.step = '1'; }
                control.addEventListener('change', async () => {
                    estado.textContent = 'Guardando…'; estado.classList.remove('mal');
                    const d = await guardar(c, control.value);
                    const bien = !!(d && d.success);
                    estado.textContent = bien ? 'Guardado' : 'No se pudo guardar';
                    estado.classList.toggle('mal', !bien);
                    if (bien) {
                        c.valor = control.value;
                        if (d.fecha_inicio !== undefined && controles['Inicio']) controles['Inicio'].value = d.fecha_inicio;
                        if (d.fecha_fin !== undefined && controles['Fin']) controles['Fin'].value = d.fecha_fin;
                        pintarLista();
                    }
                });
                controles[c.etiqueta] = control;
            }
            f.append(ttl, control);
            campos.appendChild(f);
        });
        if (!t.campos.length) { const p = document.createElement('p'); p.className = 'text-muted'; p.textContent = 'Esta tarea no tiene datos editables en esta lámina.'; campos.appendChild(p); }
        caja.append(estado, campos);

        const i = listaTareas.indexOf(t), acciones = [];
        if (i > 0) acciones.push({ texto: 'Anterior', clase: 'btn-ghost', icono: 'fa-chevron-left', alHacer: () => ir(i - 1) });
        if (i < listaTareas.length - 1) acciones.push({ texto: 'Siguiente', clase: 'btn-ghost', icono: 'fa-chevron-right', alHacer: () => ir(i + 1) });

        hoja.abrir({
            titulo: t.nombre, nodo: caja, posicion: 'media', enfocar: false, acciones: acciones,
            alCerrar: () => t.fila.classList.remove('vp-fila-sel'),
        });
        t.fila.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
    }
    function ir(i) { const t = listaTareas[i]; if (t) abrir(t.id); }

    function pintarLista() {
        if (!lista) return;
        const fecha = v => /^\d{4}-\d{2}-\d{2}$/.test(v || '') ? v.split('-').reverse().join('/') : v;
        lista.innerHTML = listaTareas.length ? listaTareas.map(t => {
            const d = {};
            t.campos.forEach(c => { d[c.etiqueta] = c.soloLectura ? c.valor : (c.inp ? c.inp.value : c.valor); });
            const pct = d['Avance (%)'] || d['Avance'] || '';
            return '<button type="button" class="vp-tarea-item" data-id="' + esc(t.id) + '">' +
                '<span class="vp-tarea-item-nombre">' + esc(t.nombre) + '</span>' +
                '<span class="vp-tarea-item-meta">' + (d['Inicio'] ? esc(fecha(d['Inicio'])) + ' → ' + esc(fecha(d['Fin'] || '')) : '') + '</span>' +
                '<span class="vp-tarea-item-pct">' + esc(String(pct).replace('%', '').trim() || '0') + '%</span>' +
                '</button>';
        }).join('') : '<p class="text-muted" style="padding:1.6rem">Esta lámina no tiene tareas del programa.</p>';
    }

    // En táctil la tabla del lienzo no se edita dentro: se toca y se abre la ficha con campos de tamaño normal.
    if (tactil && editable) {
        sheet.addEventListener('pointerdown', e => {
            const inp = e.target.closest('tr[data-actividad] input, tr[data-actividad] textarea');
            if (!inp) return;
            e.preventDefault(); e.stopPropagation();
            abrir(inp.closest('tr[data-actividad]').dataset.actividad);
        }, true);
        sheet.querySelectorAll('tr[data-actividad] input, tr[data-actividad] textarea').forEach(i => { i.readOnly = true; i.tabIndex = -1; });
        sheet.classList.add('vp-tactil');
    }
    if (tactil) sheet.addEventListener('click', e => {
        if (e.target.closest('a, button, input, textarea')) return;
        const tr = e.target.closest('tr[data-actividad]');
        if (tr) { e.preventDefault(); abrir(tr.dataset.actividad); }
    });
    if (lista) lista.addEventListener('click', e => { const b = e.target.closest('.vp-tarea-item'); if (b) abrir(b.dataset.id); });

    listaTareas = tareas();
    pintarLista();
    document.addEventListener('vp:pestana', e => { if (e.detail && e.detail.pest === 'tareas') pintarLista(); });

    // ---- Deslizar para cambiar de lámina (solo cuando la hoja cabe a lo ancho) ----
    const zona = document.getElementById('vp-hoja');
    if (zona && tactil) {
        let sw = null;
        zona.addEventListener('touchstart', e => {
            if (e.touches.length !== 1) { sw = null; return; }
            const t = e.touches[0];
            if (t.clientX < 24) { sw = null; return; }   // no interferir con el gesto «atrás» del navegador
            sw = { x: t.clientX, y: t.clientY, t: Date.now() };
        }, { passive: true });
        zona.addEventListener('touchend', e => {
            if (!sw) return;
            const t = e.changedTouches[0], dx = t.clientX - sw.x, dy = t.clientY - sw.y;
            const ajustada = zona.scrollWidth <= zona.clientWidth + 4;
            const rapido = Date.now() - sw.t < 700 && Math.abs(dx) > 70 && Math.abs(dy) < 50;
            sw = null;
            if (!ajustada || !rapido) return;
            const activa = document.querySelector('.vp-nav-item.activa');
            if (!activa) return;
            const items = [...document.querySelectorAll('.vp-nav-item')];
            const i = items.indexOf(activa) + (dx < 0 ? 1 : -1);
            if (i >= 0 && i < items.length) location.href = items[i].href;
        }, { passive: true });
    }
});
</script>
<?php if ($puedeEditar): ?>
<script>
(function () {
    const lamina = <?php echo (int)$lamina->id; ?>;
    const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(r => r.json());

    // Recovery de las tareas del programa
    document.querySelectorAll('.vp-rec:not(.vp-fecha-act):not(.vp-progreso-act):not(.vp-col-texto)').forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/vplan/recovery', { lamina, actividad: inp.dataset.actividad, n: inp.dataset.n, campo: inp.dataset.campo, valor: inp.value });
            if (!d.success) inp.classList.add('msr-error');
        } catch (_) { inp.classList.add('msr-error'); }
    }));

    // Columnas de texto propias de las tablas de tareas
    document.querySelectorAll('.vp-col-texto').forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/vplan/columna-texto', { lamina, actividad: inp.dataset.actividad, col: inp.dataset.col, valor: inp.value });
            if (!d.success) inp.classList.add('msr-error');
        } catch (_) { inp.classList.add('msr-error'); }
    }));

    // Campos del programa (responsable, recuperación de la actividad, libres): se guardan en el programa de obra
    document.querySelectorAll('.vp-prg-campo').forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/programa/campo', { actividad_id: inp.dataset.actividad, campo: inp.dataset.campo, valor: inp.value });
            if (!d.success) { inp.classList.add('msr-error'); inp.title = d.error || ''; }
        } catch (_) { inp.classList.add('msr-error'); }
    }));

    // Inicio y fin de la tarea: son los mismos datos del programa de obra (se guardan ahí y se reflejan en todos los formatos)
    document.querySelectorAll('.vp-fecha-act').forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        if (!inp.value) { inp.classList.add('msr-error'); return; }
        try {
            const d = await post('/api/actividad/actualizar-fecha', { actividad_id: inp.dataset.actividad, tipo: inp.dataset.tipo, fecha: inp.value });
            if (!d.success) { inp.classList.add('msr-error'); return; }
            const fila = inp.closest('tr');
            fila.querySelector('[data-tipo=inicio]').value = d.fecha_inicio;
            fila.querySelector('[data-tipo=fin]').value = d.fecha_fin;
        } catch (_) { inp.classList.add('msr-error'); }
    }));

    // % de avance de la tarea: mismo dato del programa de obra
    document.querySelectorAll('.vp-progreso-act').forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/actividad/actualizar-progreso', { actividad_id: inp.dataset.actividad, progreso: inp.value });
            if (!d.success) { inp.classList.add('msr-error'); return; }
            inp.value = d.progreso;
            const td = inp.closest('td');
            td.classList.toggle('vp-verde', d.progreso >= 100);
            td.classList.toggle('vp-amarillo', d.progreso > 0 && d.progreso < 100);
        } catch (_) { inp.classList.add('msr-error'); }
    }));

    // Tablas adicionales: editar en línea, agregar y quitar renglones
    const colorPct = td => {
        const v = td.querySelector('input').value;
        td.classList.toggle('vp-verde', v !== '' && +v >= 100);
        td.classList.toggle('vp-amarillo', v !== '' && +v > 0 && +v < 100);
    };
    const renumerar = tabla => {
        const base = +tabla.dataset.base || 0;
        [...tabla.tBodies[0].rows].forEach((tr, i) => {
            const n = tr.querySelector('.vp-n');
            if (!n) return;
            const b = n.querySelector('b');
            const manuales = [...tabla.tBodies[0].rows].filter(r => r.dataset.indice !== undefined);
            if (b) b.textContent = base + manuales.indexOf(tr) + 1; else n.textContent = i + 1;
        });
    };
    document.addEventListener('change', async e => {
        const inp = e.target.closest('.vp-in');
        if (!inp) return;
        const tr = inp.closest('tr'), tabla = inp.closest('table');
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/vplan/fila', { tabla: tabla.dataset.tabla, accion: 'set', indice: tr.dataset.indice, campo: inp.dataset.campo, valor: inp.type === 'checkbox' ? (inp.checked ? '1' : '') : inp.value });
            if (!d.success) { inp.classList.add('msr-error'); return; }
            if (inp.dataset.campo === 'pct') { inp.value = d.valor; colorPct(inp.closest('td')); }
            if (inp.dataset.campo === 'estado') tr.className = tr.className.replace(/vp-est-\w+/, 'vp-est-' + inp.value);
        } catch (_) { inp.classList.add('msr-error'); }
    });
    document.addEventListener('click', async e => {
        const agregar = e.target.closest('.vp-agregar-dato');
        if (agregar) {
            const tabla = document.querySelector('table.vp-adic[data-tabla="' + agregar.dataset.tabla + '"]');
            agregar.disabled = true;
            try {
                const d = await post('/api/vplan/fila', { tabla: tabla.dataset.tabla, accion: 'add', base: tabla.dataset.base });
                if (d.success) {
                    tabla.tBodies[0].insertAdjacentHTML('beforeend', d.html);
                    renumerar(tabla);
                    const nuevo = tabla.tBodies[0].lastElementChild.querySelector('input:not([type=date]), input');
                    if (nuevo) nuevo.focus();
                }
            } finally { agregar.disabled = false; }
            return;
        }
        const del = e.target.closest('.vp-del');
        if (del) {
            const tr = del.closest('tr'), tabla = del.closest('table');
            const esSub = tabla.dataset.subactividades === '1';
            const mensaje = esSub
                ? 'Se quitará este renglón de la tabla y también su subactividad. El avance de la actividad se recalculará. Esta acción no se puede deshacer.'
                : 'Se quitará este renglón de la tabla. Esta acción no se puede deshacer.';
            const ok = await window.confirmarModal({ titulo: '¿Quitar este dato?', mensaje, aceptar: 'Sí, quitar', iconoBoton: 'fa-trash-can' });
            if (!ok) return;
            const d = await post('/api/vplan/fila', { tabla: tabla.dataset.tabla, accion: 'del', indice: tr.dataset.indice });
            if (d.success) {
                // los renglones posteriores bajan un índice
                [...tabla.tBodies[0].rows].forEach(r => { if (r.dataset.indice !== undefined && +r.dataset.indice > +tr.dataset.indice) r.dataset.indice = +r.dataset.indice - 1; });
                tr.remove(); renumerar(tabla);
            }
        }
    });
    document.querySelectorAll('.vp-pct').forEach(colorPct);

    // Los textos largos se ajustan a su contenido (sin cortarse)
    const crecer = el => { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; };
    const crecerTodos = () => document.querySelectorAll('.vp-ta').forEach(crecer);
    document.addEventListener('input', e => { if (e.target.classList && e.target.classList.contains('vp-ta')) crecer(e.target); });
    new MutationObserver(crecerTodos).observe(document.querySelector('.vp-sheet'), { childList: true, subtree: true });
    window.addEventListener('load', crecerTodos); crecerTodos();
})();
</script>
<?php endif; ?>

<?php // La actualización en vivo de tablas se salta sola cuando el lienzo no está en modo Ver ?>
<script>window.TABLAS_VIVO = { url: '/api/vplan/tablas-version', datos: { lamina: <?php echo (int)$lamina->id; ?> } };</script>
<script src="/vendor/tablas-vivo.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/tablas-vivo.js'); ?>"></script>
<script src="/vendor/vplan-ajuste.js?v=<?php echo filemtime(__DIR__ . "/../../public/vendor/vplan-ajuste.js"); ?>"></script>
<?php // gestos-lienzo, capa-superior y hoja-inferior también los carga el editor de marcas; no se repiten ?>
<?php if (!$puedeDibujar): ?>
<script src="/vendor/gestos-lienzo.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/gestos-lienzo.js'); ?>"></script>
<script src="/vendor/capa-superior.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/capa-superior.js'); ?>"></script>
<script src="/vendor/hoja-inferior.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/hoja-inferior.js'); ?>"></script>
<?php endif; ?>
<script src="/vendor/vplan-zoom.js?v=<?php echo filemtime(__DIR__ . "/../../public/vendor/vplan-zoom.js"); ?>"></script>
<?php if ($puedeEditar): ?>
<script>
window.VP_DISENO = <?php echo json_encode([
    'lamina' => (int)$lamina->id, 'proyecto' => (int)$proyecto->id, 'volver' => '/vplan/ver?proyecto=' . $proyecto->id . '&id=' . $lamina->id,
    'bloques' => array_map(fn($b) => $b->aArray(), $bloques),
    'programaCols' => \Classes\ProgramaColumnas::titulosVplan($proyecto),   // campos del programa que se pueden agregar a una tabla de tareas
    'partidas' => $partidasDiseno,
    'presets' => \Classes\VplanDiseno::PRESETS,
    'tamano' => ['preset' => $lamina->preset, 'ancho_mm' => (int)$lamina->ancho_mm, 'alto_mm' => (int)$lamina->alto_mm],
    'tablas' => array_reduce($tablas, function ($c, $t) { $c[(int)$t['tabla']->id] = ['titulo' => $t['tabla']->etiqueta()]; return $c; }, []),
    'tipos' => \Model\VplanBloque::TIPOS,
    'guias' => $lamina->guias(),
    'catalogo' => $catalogoTablas,
    'biblioteca' => $biblioteca,
    'recovery' => ['n' => (int)$lamina->recovery_n, 'max' => \Model\VplanLamina::MAX_RECOVERY],
    'etiquetasRecovery' => array_map(fn($n) => \Classes\Vplan::etiquetaRecovery($n), range(1, \Model\VplanLamina::MAX_RECOVERY)),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="/vendor/kit-edicion.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/kit-edicion.js'); ?>"></script>
<?php if ($modoDiseno): ?>
<script src="/vendor/vplan-diseno.js?v=<?php echo filemtime(__DIR__ . "/../../public/vendor/vplan-diseno.js"); ?>"></script>
<?php endif; ?>
<?php endif; ?>
<script src="/vendor/tour.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/tour.js'); ?>"></script>
<script>
// Guía rápida del «Acomodar bloques»: se muestra sola la primera vez (y con el botón de la barra o ?tour=1).
// Los pasos que destacan una pestaña del panel la abren antes con alEntrar.
window.Tour.registrar('vplan-diseno', {
    auto: <?php echo $modoDiseno ? 'true' : 'false'; ?>,
    pasos: [
        { icono: 'fa-vector-square', titulo: 'Acomodar bloques', texto: 'Aquí se arma la lámina: cada bloque (tablas del programa, estampas, títulos…) se <b>arrastra</b> para moverlo y se estira por sus <b>esquinas</b>. Nada se guarda hasta que pulsas «Guardar diseño».' },
        { sel: '#vp-dis-guardar', icono: 'fa-floppy-disk', titulo: 'Guardar diseño', texto: 'Guarda la posición y el tamaño de todos los bloques. También con <b>Ctrl + S</b>. Antes de salirte te avisa si algo quedó sin guardar.' },
        { sel: '.vp-tgls', icono: 'fa-magnet', titulo: 'Rejilla, imán y reglas', texto: 'Con el <b>imán</b> los bloques se pegan a las guías y a los bordes de la hoja; arrastra desde las <b>reglas</b> para crear guías.' },
        { sel: '#vp-dis-panel .pn-tabs', icono: 'fa-plus', titulo: 'Panel: Insertar', texto: 'La pestaña «Insertar» trae todo lo que puede llevar una lámina: tablas, estampas, símbolos, cajetines… Un clic lo pone en el centro; arrastrándolo lo dejas donde quieras.', alEntrar: () => document.querySelector('#vp-dis-panel [data-ptab="insertar"]')?.click() },
        { sel: '#vp-dis-panel .pn-tabs', icono: 'fa-sliders', titulo: 'Panel: Propiedades', texto: 'Haz clic en cualquier bloque de la hoja y aquí aparecen su posición exacta, su contenido, su visibilidad y sus acciones (duplicar, al frente, quitar…).', alEntrar: () => document.querySelector('#vp-dis-panel [data-ptab="props"]')?.click() },
        { icono: 'fa-wand-magic-sparkles', titulo: 'Listo para armar la lámina', texto: 'Deshacer con <b>Ctrl + Z</b>, rehacer con <b>Ctrl + Y</b>, quitar con <b>Supr</b>. El botón <b>?</b> de la barra abre los trucos, y «Guía rápida» vuelve a mostrar este recorrido.' }
    ]
});
</script>
<?php if ($puedeDibujar) include __DIR__ . '/_editor-scripts.php'; ?>
