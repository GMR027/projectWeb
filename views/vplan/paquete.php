<?php
// Variables: $proyecto, $paquete, $laminas, $grupos, $puedeEditar
$tab = 'laminas';
$q = 'proyecto=' . (int)$proyecto->id;
// Láminas agrupadas por su grupo (disciplina, nivel…), conservando el orden; las sin grupo van primero
$porGrupo = [];
foreach ($laminas as $l) $porGrupo[trim((string)$l->grupo)][] = $l;
uksort($porGrupo, fn($a, $b) => ($a === '' ? -1 : ($b === '' ? 1 : 0)));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-map-location-dot"></i> <?php echo s($paquete->nombre); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($laminas); ?> lámina(s)</p>
    </div>
    <div class="la-controles">
        <a href="/vplan?<?php echo $q; ?>" class="btn btn-ghost">← vPlans</a>
        <a href="/exportar?<?php echo $q; ?>&vplan=<?php echo s($paquete->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-file-pdf"></i> Exportar PDF</a>
        <?php if ($puedeEditar): ?><a href="/vplan/crear?<?php echo $q; ?>&paquete=<?php echo s($paquete->id); ?>" class="btn btn-primary">+ Nueva lámina</a><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/_tabs.php'; ?>

<?php if (!$laminas): ?>
    <div class="card"><p class="text-muted">Este vPlan aún no tiene láminas. <?php echo $puedeEditar ? 'Crea una por área: cada una lleva su plano, sus tablas, su título y su simbología.' : ''; ?></p></div>
<?php else: ?>
<?php if ($puedeEditar): ?><p class="text-sm text-muted vp-ayuda">Usa el ojo para mostrar u ocultar una lámina (lo oculto no se ve ni se exporta, pero se conserva). «Grupo» las reúne por disciplina, nivel o lo que prefieras.</p><?php endif; ?>
<datalist id="vp-grupos"><?php foreach ($grupos as $g): ?><option value="<?php echo s($g); ?>"><?php endforeach; ?></datalist>
<?php foreach ($porGrupo as $g => $lista): ?>
<div class="card vp-grupo">
    <?php if ($g !== '' || count($porGrupo) > 1): ?>
    <div class="vp-grupo-cab">
        <h3><?php echo $g === '' ? 'Sin grupo' : s($g); ?> <small class="text-muted">(<?php echo count($lista); ?>)</small></h3>
        <?php if ($puedeEditar && $g !== ''): ?>
        <form method="POST" action="/vplan/paquete/grupo-visible" style="display:inline-flex;gap:.4rem">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>"><input type="hidden" name="id" value="<?php echo s($paquete->id); ?>"><input type="hidden" name="grupo" value="<?php echo s($g); ?>">
            <button type="submit" name="visible" value="1" class="btn btn-sm btn-ghost" title="Mostrar todas las del grupo"><i class="fa-solid fa-eye"></i> Mostrar grupo</button>
            <button type="submit" name="visible" value="0" class="btn btn-sm btn-ghost" title="Ocultar todas las del grupo"><i class="fa-solid fa-eye-slash"></i> Ocultar grupo</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <table class="tabla vp-paginas">
        <tbody>
        <?php foreach ($lista as $l): $img = $l->marcas_png ?: $l->plano; $oculta = !(int)$l->visible; ?>
            <tr class="<?php echo $oculta ? 'vp-oculta' : ''; ?>" data-lamina="<?php echo s($l->id); ?>">
                <?php if ($puedeEditar): ?>
                <td class="vp-ojo-celda"><button type="button" class="vp-ojo btn btn-sm btn-ghost" data-lamina="<?php echo s($l->id); ?>" aria-pressed="<?php echo $oculta ? 'false' : 'true'; ?>" title="<?php echo $oculta ? 'Oculta: pulsa para mostrar' : 'Visible: pulsa para ocultar'; ?>"><i class="fa-solid <?php echo $oculta ? 'fa-eye-slash' : 'fa-eye'; ?>"></i></button></td>
                <?php endif; ?>
                <td class="hide-m"><?php if ($img): ?><img class="vp-mini" src="/uploads/vplan/<?php echo s($img); ?>" alt=""><?php endif; ?></td>
                <td>
                    <a class="nombre-archivo" href="/vplan/ver?<?php echo $q; ?>&id=<?php echo s($l->id); ?>"><?php echo archivo_icono_html('png', 'Lámina del vPlan'); ?> <?php echo s($l->titulo); ?></a>
                    <div class="text-sm text-muted"><?php echo (int)$l->ancho_mm; ?> × <?php echo (int)$l->alto_mm; ?> mm<?php echo $l->supervisor ? ' · ' . s($l->supervisor) : ''; ?><?php echo $oculta ? ' · <strong>oculta</strong>' : ''; ?></div>
                </td>
                <?php if ($puedeEditar): ?>
                <td class="hide-m"><input type="text" class="vp-grupo-in" list="vp-grupos" value="<?php echo s($l->grupo); ?>" placeholder="Grupo" maxlength="100" aria-label="Grupo de <?php echo s($l->titulo); ?>" data-lamina="<?php echo s($l->id); ?>"></td>
                <?php endif; ?>
                <td class="acciones">
                    <div class="vp-acc">
                        <a href="/vplan/ver?<?php echo $q; ?>&id=<?php echo s($l->id); ?>" class="btn btn-sm btn-primary"><i class="fa-regular fa-eye"></i> Abrir</a>
                    <?php if ($puedeEditar): ?>
                        <span class="vp-seg" role="group" aria-label="Orden">
                        <?php foreach ([['subir', 'fa-arrow-up', 'Subir'], ['bajar', 'fa-arrow-down', 'Bajar']] as [$dir, $ico, $tt]): ?>
                            <form method="POST" action="/vplan/lamina/mover"><input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>"><input type="hidden" name="id" value="<?php echo s($l->id); ?>"><input type="hidden" name="dir" value="<?php echo $dir; ?>"><button type="submit" class="vp-ib" title="<?php echo $tt; ?>" aria-label="<?php echo $tt; ?>"><i class="fa-solid <?php echo $ico; ?>"></i></button></form>
                        <?php endforeach; ?>
                        </span>
                        <a href="/vplan/editar?<?php echo $q; ?>&id=<?php echo s($l->id); ?>" class="vp-ib" title="Editar lámina (título, imágenes, supervisor)" aria-label="Editar"><i class="fa-solid fa-pen"></i></a>
                        <form method="POST" action="/vplan/lamina/duplicar" title="Copia el diseño, las tablas y la simbología; el plano se sube aparte"><input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>"><input type="hidden" name="id" value="<?php echo s($l->id); ?>"><button type="submit" class="vp-ib" aria-label="Duplicar"><i class="fa-regular fa-copy"></i></button></form>
                        <form method="POST" action="/vplan/eliminar" data-confirm="¿Eliminar lámina?|Se borrarán sus imágenes y las marcas dibujadas.|Sí, eliminar">
                            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>"><input type="hidden" name="id" value="<?php echo s($l->id); ?>">
                            <button type="submit" class="vp-ib vp-ib-peligro" title="Eliminar lámina" aria-label="Eliminar"><i class="fa-solid fa-trash-can"></i></button>
                        </form>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($puedeEditar): ?>
<form method="POST" action="/vplan/paquete/renombrar" class="card vp-nuevo-paquete">
    <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>"><input type="hidden" name="id" value="<?php echo s($paquete->id); ?>">
    <label for="vp-pq-nombre" class="text-sm">Nombre de este vPlan:</label>
    <input type="text" id="vp-pq-nombre" name="nombre" value="<?php echo s($paquete->nombre); ?>" maxlength="200" required>
    <button type="submit" class="btn btn-ghost btn-sm">Renombrar</button>
</form>
<script>
(function () {
    const post = (datos) => fetch('/api/vplan/lamina/ajustar', { method: 'POST', body: new URLSearchParams(datos) }).then(r => r.json());
    document.addEventListener('click', async e => {
        const b = e.target.closest('.vp-ojo');
        if (!b) return;
        const fila = b.closest('tr'), oculta = !fila.classList.contains('vp-oculta');
        b.disabled = true;
        try {
            const d = await post({ lamina: b.dataset.lamina, visible: oculta ? 0 : 1 });
            if (d.success) {
                fila.classList.toggle('vp-oculta', !d.visible);
                b.setAttribute('aria-pressed', d.visible ? 'true' : 'false');
                b.title = d.visible ? 'Visible: pulsa para ocultar' : 'Oculta: pulsa para mostrar';
                b.querySelector('i').className = 'fa-solid ' + (d.visible ? 'fa-eye' : 'fa-eye-slash');
            }
        } finally { b.disabled = false; }
    });
    document.addEventListener('change', async e => {
        const i = e.target.closest('.vp-grupo-in');
        if (!i) return;
        i.classList.remove('msr-error');
        try {
            const d = await post({ lamina: i.dataset.lamina, grupo: i.value });
            if (!d.success) i.classList.add('msr-error'); else i.value = d.grupo;
        } catch (_) { i.classList.add('msr-error'); }
    });
})();
</script>
<?php endif; ?>
