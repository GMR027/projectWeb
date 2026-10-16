<?php // Variables: $proyecto, $grafico, $lienzo, $laminas, $puedeEditar. Solo lectura: sin herramientas de edición ?>
<link rel="stylesheet" href="/vendor/psv/core.css">
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-street-view"></i> <?php echo s($grafico->nombre); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s(formatear_fecha($grafico->fecha)); ?><?php echo $grafico->version !== '' ? ' · versión ' . s($grafico->version) : ''; ?></p>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar && $grafico->grafico_id): ?><a href="/graficos/abrir?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$grafico->grafico_id; ?>" class="btn btn-ghost"><i class="fa-solid fa-pen-ruler"></i> Editar</a><?php endif; ?>
        <a href="/view360?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← View360</a>
    </div>
</div>
<?php
$hojas = [];
foreach ($laminas as $i => $l) {
    $img = $l->marcas_png ?: ($i === 0 ? $lienzo->fondo_imagen : null);   // sin guardar aún: se muestra el fondo
    if ($img) $hojas[] = [$l, $img, $i];
}
?>
<?php if (!$hojas): ?>
    <div class="card"><p class="text-muted">Este gráfico aún no tiene contenido.</p></div>
<?php else: ?>
<div class="v360-area">
<div class="v360-lienzo">
<?php foreach ($hojas as [$l, $img, $i]): ?>
    <?php $lkW = $lkH = 0.0; $enl = $l->marcas_png ? \Classes\EnlacesLamina::de($l->marcas_json, $lkW, $lkH) : []; ?>
    <div class="card gr-vista">
        <?php if (count($hojas) > 1): ?><h3 class="card-titulo"><?php echo s($l->titulo($i + 1)); ?></h3><?php endif; ?>
        <div class="vp-lk-wrap"><img src="/uploads/vplan/<?php echo s($img); ?>" alt="<?php echo s($grafico->nombre); ?>"><?php echo \Classes\EnlacesLamina::html($enl, $lkW, $lkH); ?></div>
    </div>
<?php endforeach; ?>
</div>
<aside class="card v360-panel" id="v360-panel" aria-label="Vistas 360 abiertas">
    <h3 class="card-titulo"><i class="fa-solid fa-street-view"></i> Vistas 360</h3>
    <p class="text-muted text-sm" id="v360-vacio">Haz clic en un hipervínculo de vista 360 del gráfico para abrirla aquí.</p>
    <div id="v360-lista"></div>
</aside>
</div>
<div class="v360-pantalla" id="v360-pantalla" hidden role="dialog" aria-modal="true" aria-label="Vista 360 a pantalla completa">
    <div class="v360-pantalla-barra"><strong id="v360-pantalla-t"></strong><button type="button" class="btn btn-sm btn-ghost" id="v360-pantalla-x"><i class="fa-solid fa-xmark"></i> Cerrar</button></div>
    <div class="v360-pantalla-cuerpo" id="v360-pantalla-cuerpo"></div>
</div>
<script type="importmap">{"imports": {"three": "/vendor/psv/three.module.min.js", "@photo-sphere-viewer/core": "/vendor/psv/core.js"}}</script>
<script type="module">
import { Viewer } from '@photo-sphere-viewer/core';
const lista = document.getElementById('v360-lista'), vacio = document.getElementById('v360-vacio');
const pantalla = document.getElementById('v360-pantalla'), cuerpo = document.getElementById('v360-pantalla-cuerpo');
const abiertas = new Map();
let visor = null;
const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function abrirPanel(a) {   // agrega (o resalta) la vista en el panel derecho
    const src = a.dataset.v360, nombre = a.dataset.nombre || 'Vista 360', clave = src;
    if (abiertas.has(clave)) { abiertas.get(clave).scrollIntoView({ behavior: 'smooth', block: 'nearest' }); return; }
    const el = document.createElement('div'); el.className = 'v360-item';
    el.innerHTML = '<div class="v360-item-t">' + esc(nombre) + '</div>' +
        '<div class="v360-mini"><img src="' + esc(src) + '" alt="' + esc(nombre) + '"><span class="v360-badge"><i class="fa-solid fa-street-view"></i> 360° · arrastra para mirar</span></div>' +
        '<div class="v360-item-b"><button type="button" class="btn btn-sm btn-primary" data-ver><i class="fa-solid fa-expand"></i> Ver</button><button type="button" class="btn btn-sm btn-ghost" data-cerrar><i class="fa-solid fa-xmark"></i> Cerrar</button></div>';
    el.querySelector('[data-ver]').addEventListener('click', () => verPantalla(src, nombre));
    el.querySelector('[data-cerrar]').addEventListener('click', () => { if (el._visor) { try { el._visor.destroy(); } catch (e) {} } el.remove(); abiertas.delete(clave); vacio.hidden = abiertas.size > 0; });
    lista.appendChild(el); abiertas.set(clave, el); vacio.hidden = true;
    // visor vivo dentro de la tarjeta: se puede arrastrar para mirar alrededor (la miniatura queda de fondo mientras carga)
    const mini = el.querySelector('.v360-mini'), cont = document.createElement('div'); cont.className = 'v360-mini-visor'; mini.insertBefore(cont, mini.querySelector('.v360-badge'));
    try {
        el._visor = new Viewer({ container: cont, panorama: src, navbar: ['zoom'], loadingTxt: '', mousewheelCtrlKey: true, touchmoveTwoFingers: true, defaultZoomLvl: 40, minFov: 30, maxFov: 100 });
        el._visor.addEventListener('ready', () => { const i = mini.querySelector('img'); if (i) i.style.visibility = 'hidden'; }, { once: true });
    } catch (e) { /* sin WebGL: queda la imagen fija */ }
}

function verPantalla(src, nombre) {   // visor 360 a pantalla completa
    document.getElementById('v360-pantalla-t').textContent = nombre;
    pantalla.hidden = false; document.body.classList.add('vp-sin-scroll');
    cuerpo.innerHTML = '';
    try {
        visor = new Viewer({ container: cuerpo, panorama: src, caption: nombre, loadingTxt: 'Cargando vista 360…', navbar: ['zoom', 'move', 'caption'], touchmoveTwoFingers: false, mousewheelCtrlKey: false });
    } catch (e) { cuerpo.innerHTML = '<p style="color:#fff;padding:2rem">No se pudo abrir la vista 360.</p>'; }
    if (pantalla.requestFullscreen) pantalla.requestFullscreen().catch(() => {});
}
function cerrarPantalla() {
    if (pantalla.hidden) return;
    if (visor) { try { visor.destroy(); } catch (e) {} visor = null; }
    cuerpo.innerHTML = ''; pantalla.hidden = true; document.body.classList.remove('vp-sin-scroll');
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
}
document.querySelector('.v360-lienzo').addEventListener('click', e => {
    const a = e.target.closest('.vp-lk-360'); if (!a) return;
    e.preventDefault(); abrirPanel(a);
});
document.getElementById('v360-pantalla-x').addEventListener('click', cerrarPantalla);
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarPantalla(); });
document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) cerrarPantalla(); });
</script>
<?php endif; ?>

<section class="card vp-acciones no-print" id="v360-acciones">
    <h3>Acciones</h3>
    <div class="vp-acciones-menu">
        <?php if ($puedeEditar): ?><button type="button" class="btn btn-primary" id="vp-nota-nueva"><i class="fa-solid fa-note-sticky"></i> Crear nota</button><?php endif; ?>
    </div>
    <div class="vp-notas">
        <h4>Notas</h4>
        <p class="text-muted" id="vp-notas-vacio" hidden>Aún no hay notas en este gráfico.</p>
        <ul class="vp-notas-lista" id="vp-notas-lista"></ul>
    </div>
</section>

<?php if ($puedeEditar): ?>
<div class="modal-overlay" id="vp-nota-modal" aria-hidden="true">
    <form class="modal-card vp-nota-card" id="vp-nota-form" role="dialog" aria-modal="true" aria-labelledby="vp-nota-titulo">
        <h3 class="modal-titulo" id="vp-nota-titulo">Crear nota</h3>
        <p class="text-muted text-sm vp-nota-fecha">Fecha: <strong id="vp-nota-fecha"></strong> <span id="vp-nota-auto">(se pone automáticamente)</span></p>
        <div class="campo">
            <label for="vp-nota-descripcion">Descripción *</label>
            <textarea id="vp-nota-descripcion" rows="5" maxlength="<?php echo (int)\Model\View360Nota::MAX_LARGO; ?>" required></textarea>
        </div>
        <p class="msr-error-txt text-sm" id="vp-nota-error" hidden></p>
        <div class="modal-acciones">
            <button type="button" class="btn btn-ghost" id="vp-nota-cancelar"><i class="fa-solid fa-xmark"></i> Cancelar</button>
            <button type="submit" class="btn btn-primary" id="vp-nota-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
(function () {
    const grafico = <?php echo (int)$grafico->id; ?>;
    const puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    const notas = <?php echo json_encode($notas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(r => r.json());
    const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const lista = document.getElementById('vp-notas-lista'), vacio = document.getElementById('vp-notas-vacio');
    const pintar = () => {
        vacio.hidden = notas.length > 0;
        lista.innerHTML = notas.map(n =>
            '<li class="vp-nota" data-id="' + n.id + '">' +
            '<div class="vp-nota-cab"><span class="vp-nota-fecha-txt"><i class="fa-regular fa-calendar"></i> ' + esc(n.fecha) + '</span>' +
            (puedeEditar ? '<span class="vp-nota-acc"><button type="button" class="btn btn-sm btn-ghost" data-nota="editar"><i class="fa-solid fa-pen"></i> Editar</button>' +
                '<button type="button" class="btn btn-sm btn-danger" data-nota="borrar"><i class="fa-solid fa-trash-can"></i> Borrar</button></span>' : '') +
            '</div><p class="vp-nota-texto">' + esc(n.descripcion) + '</p></li>'
        ).join('');
    };
    pintar();
    if (!puedeEditar) return;

    const modal = document.getElementById('vp-nota-modal'), form = document.getElementById('vp-nota-form');
    const area = document.getElementById('vp-nota-descripcion'), err = document.getElementById('vp-nota-error');
    const btnGuardar = document.getElementById('vp-nota-guardar');
    let editando = null;
    const abrirModal = nota => {
        editando = nota || null;
        document.getElementById('vp-nota-titulo').textContent = nota ? 'Editar nota' : 'Crear nota';
        const hoy = new Date(), p = n => String(n).padStart(2, '0');
        document.getElementById('vp-nota-fecha').textContent = nota ? nota.fecha : p(hoy.getDate()) + '/' + p(hoy.getMonth() + 1) + '/' + hoy.getFullYear();
        document.getElementById('vp-nota-auto').hidden = !!nota;
        area.value = nota ? nota.descripcion : '';
        err.hidden = true;
        modal.classList.add('abierto'); modal.setAttribute('aria-hidden', 'false');
        area.focus();
    };
    const cerrarModal = () => { modal.classList.remove('abierto'); modal.setAttribute('aria-hidden', 'true'); editando = null; };
    document.getElementById('vp-nota-nueva').addEventListener('click', () => abrirModal());
    document.getElementById('vp-nota-cancelar').addEventListener('click', cerrarModal);
    modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('abierto')) cerrarModal(); });

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!area.value.trim()) { err.textContent = 'La descripción es obligatoria'; err.hidden = false; return; }
        btnGuardar.disabled = true;
        try {
            const datos = { grafico, descripcion: area.value };
            if (editando) datos.id = editando.id;
            const d = await post('/api/view360/nota/guardar', datos);
            if (!d.success) { err.textContent = d.error || 'No se pudo guardar'; err.hidden = false; return; }
            if (editando) notas[notas.findIndex(n => n.id === editando.id)] = d.nota; else notas.unshift(d.nota);
            pintar(); cerrarModal();
        } catch (_) { err.textContent = 'Error de conexión'; err.hidden = false; }
        finally { btnGuardar.disabled = false; }
    });

    lista.addEventListener('click', async e => {
        const b = e.target.closest('[data-nota]');
        if (!b) return;
        const nota = notas.find(n => n.id === +b.closest('.vp-nota').dataset.id);
        if (!nota) return;
        if (b.dataset.nota === 'editar') return abrirModal(nota);
        const ok = await window.confirmarModal({ titulo: '¿Borrar esta nota?', mensaje: 'Esta acción no se puede deshacer.', aceptar: 'Sí, borrar', iconoBoton: 'fa-trash-can' });
        if (!ok) return;
        const d = await post('/api/view360/nota/eliminar', { grafico, id: nota.id });
        if (d.success) { notas.splice(notas.indexOf(nota), 1); pintar(); }
    });
})();
</script>
