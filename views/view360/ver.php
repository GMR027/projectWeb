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

<?php if ($puedeEditar): $maxLargo = \Model\View360Nota::MAX_LARGO; include __DIR__ . '/../templates/_notas-modal.php'; endif; ?>

<script src="/vendor/notas.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/notas.js'); ?>"></script>
<script>
window.Notas.iniciar({
    campo: 'grafico', id: <?php echo (int)$grafico->id; ?>, puedeEditar: <?php echo $puedeEditar ? 'true' : 'false'; ?>,
    notas: <?php echo json_encode($notas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    urlGuardar: '/api/view360/nota/guardar', urlEliminar: '/api/view360/nota/eliminar',
});
</script>
