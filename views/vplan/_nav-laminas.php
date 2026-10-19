<?php
// Navegador de láminas del vPlan (miniaturas agrupadas, como la lista de documentos del Redactor).
// Variables: $proyecto, $lamina, $paquete, $navLaminas, $modoDiseno, $puedeEditar
$hayGrupos = false;
$porGrupo = [];
foreach ($navLaminas as $n) {
    $g = trim((string)$n['lamina']->grupo);
    if ($g !== '') $hayGrupos = true;
    $porGrupo[$g][] = $n;
}
uksort($porGrupo, fn($a, $b) => ($a === '' ? -1 : ($b === '' ? 1 : 0)));
$urlDe = fn($id) => '/vplan/ver?proyecto=' . (int)$proyecto->id . '&id=' . (int)$id . ($modoDiseno ? '&diseno=1' : '');
?>
<aside class="vp-nav no-print" id="vp-nav" aria-label="Láminas del vPlan">
    <div class="vp-nav-cab">
        <strong title="<?php echo s($paquete ? $paquete->nombre : 'Láminas'); ?>"><i class="fa-solid fa-layer-group"></i> <?php echo s($paquete ? $paquete->nombre : 'Láminas'); ?> <small class="text-muted">(<?php echo count($navLaminas); ?>)</small></strong>
        <button type="button" class="vp-ib vp-ib-sm" id="vp-nav-cerrar" title="Ocultar el panel de láminas" aria-label="Ocultar láminas"><i class="fa-solid fa-angles-left"></i></button>
    </div>
    <?php if (count($navLaminas) > 6): ?><input type="search" id="vp-nav-buscar" class="vp-nav-buscar" placeholder="Buscar lámina…" aria-label="Buscar lámina"><?php endif; ?>
    <div class="vp-nav-lista">
    <?php foreach ($porGrupo as $g => $lista): ?>
        <?php if ($hayGrupos): ?><h4 class="vp-nav-grupo"><?php echo $g === '' ? 'Sin grupo' : s($g); ?></h4><?php endif; ?>
        <?php foreach ($lista as $n): $h = $n['lamina']; $act = (int)$h->id === (int)$lamina->id; ?>
        <a class="vp-nav-item<?php echo $act ? ' activa' : ''; ?><?php echo (int)$h->visible ? '' : ' oculta'; ?>" href="<?php echo $urlDe($h->id); ?>" data-titulo="<?php echo s(mb_strtolower($h->titulo . ' ' . $h->grupo)); ?>"<?php echo $act ? ' aria-current="page"' : ''; ?>>
            <span class="vp-mh" style="aspect-ratio:<?php echo (int)$h->ancho_mm; ?> / <?php echo (int)$h->alto_mm; ?>">
                <?php foreach ($n['bloques'] as $b): if ($b['oculto']) continue; ?>
                    <i class="vp-mh-b vp-mh-<?php echo s($b['tipo']); ?>" style="left:<?php echo $b['x']; ?>%;top:<?php echo $b['y']; ?>%;width:<?php echo $b['w']; ?>%;height:<?php echo $b['h']; ?>%"><?php if ($b['tipo'] === 'plano' && $n['img']): ?><img src="/uploads/vplan/<?php echo s($n['img']); ?>" alt="" loading="lazy"><?php endif; ?></i>
                <?php endforeach; ?>
            </span>
            <span class="vp-nav-txt"><b><?php echo s($h->titulo); ?></b><small><?php echo (int)$h->ancho_mm; ?> × <?php echo (int)$h->alto_mm; ?> mm<?php echo (int)$h->visible ? '' : ' · oculta'; ?></small></span>
        </a>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </div>
    <?php if ($puedeEditar && $paquete): ?>
        <a class="btn btn-sm btn-ghost vp-nav-nueva" href="/vplan/crear?proyecto=<?php echo (int)$proyecto->id; ?>&paquete=<?php echo (int)$paquete->id; ?>"><i class="fa-solid fa-plus"></i> Nueva lámina</a>
    <?php endif; ?>
</aside>
<button type="button" class="vp-nav-abrir no-print" id="vp-nav-abrir" title="Mostrar las láminas del vPlan" aria-label="Mostrar láminas" hidden><i class="fa-solid fa-layer-group"></i></button>
<script>
(function () {
    const shell = document.getElementById('vp-shell'), abrir = document.getElementById('vp-nav-abrir');
    const poner = oculto => {
        shell.classList.toggle('vp-nav-oculta', oculto);
        abrir.hidden = !oculto;
        document.querySelectorAll('[data-vp-nav-toggle]').forEach(b => b.setAttribute('aria-pressed', oculto ? 'false' : 'true'));
        try { localStorage.setItem('vp-nav-oculta', oculto ? '1' : '0'); } catch (_) {}
    };
    let ini = null;
    try { ini = localStorage.getItem('vp-nav-oculta'); } catch (_) {}
    poner(ini === null ? <?php echo $modoDiseno ? 'true' : 'false'; ?> : ini === '1');
    document.getElementById('vp-nav-cerrar').addEventListener('click', () => poner(true));
    abrir.addEventListener('click', () => poner(false));
    document.querySelectorAll('[data-vp-nav-toggle]').forEach(b => b.addEventListener('click', () => poner(!shell.classList.contains('vp-nav-oculta'))));
    const buscar = document.getElementById('vp-nav-buscar');
    if (buscar) buscar.addEventListener('input', () => {
        const t = buscar.value.trim().toLowerCase();
        document.querySelectorAll('#vp-nav .vp-nav-item').forEach(a => a.hidden = t !== '' && !a.dataset.titulo.includes(t));
    });
    const act = document.querySelector('#vp-nav .vp-nav-item.activa');
    if (act) act.scrollIntoView({ block: 'nearest' });
})();
</script>
