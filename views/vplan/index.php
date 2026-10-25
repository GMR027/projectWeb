<?php $tab = 'laminas'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-sheet-plastic"></i> vPlan · Plan visual</h2><?php // el glosario está en la pestaña y en la ayuda de la herramienta ?>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($paquetes); ?> vPlan</p>
    </div>
    <div class="la-controles">
        <a href="/exportar?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-file-pdf"></i> Exportar PDF</a>
    </div>
</div>
<?php include __DIR__ . '/_tabs.php'; ?>

<?php if ($puedeEditar): ?>
<form method="POST" action="/vplan/paquete/crear" class="card vp-nuevo-paquete">
    <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
    <label for="vp-np-nombre" class="text-sm"<?php echo glosario('vplan'); ?>>Nuevo vPlan:</label>
    <input type="text" id="vp-np-nombre" name="nombre" maxlength="200" placeholder="Ej: Videovigilancia, Fase 2, Torre A…" required>
    <button type="submit" class="btn btn-primary btn-sm">+ Crear vPlan</button>
    <span class="text-sm text-muted">Cada vPlan reúne las láminas (una por área) que quieras gestionar juntas.</span>
</form>
<?php endif; ?>

<?php if (!$paquetes): ?>
    <div class="card"><?php echo estado_vacio('fa-sheet-plastic', 'Aún no hay vPlan', 'Un vPlan reúne las láminas (una por área) que quieres gestionar juntas: plano, tablas y programa en una sola hoja.', $puedeEditar ? ['texto' => 'Crear el primer vPlan', 'url' => '#vp-np-nombre', 'icono' => 'fa-plus'] : null, $puedeEditar ? ['texto' => 'Ver el manual', 'url' => '/manual' . ($proyecto ? '?proyecto=' . (int)$proyecto->id : '') . '#vplan', 'icono' => 'fa-book-open'] : null); ?></div>
<?php else: ?>
<div class="vp-paquetes">
    <?php foreach ($paquetes as $p): ?>
    <div class="card vp-paquete">
        <a class="vp-paquete-portada" href="/vplan/paquete?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($p->id); ?>" aria-label="Abrir <?php echo s($p->nombre); ?>">
            <?php foreach ($portadas[(int)$p->id] ?? [] as $img): ?><img src="/uploads/vplan/<?php echo s($img); ?>" alt=""><?php endforeach; ?>
            <?php if (empty($portadas[(int)$p->id])): ?><i class="fa-solid fa-sheet-plastic"></i><?php endif; ?>
        </a>
        <div class="vp-paquete-cuerpo">
        <h3><a href="/vplan/paquete?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($p->id); ?>"><?php echo s($p->nombre); ?></a></h3>
        <div class="vp-paquete-meta">
            <span class="vp-chip-meta"><i class="fa-solid fa-layer-group"></i> <?php echo (int)$p->total; ?> lámina<?php echo (int)$p->total === 1 ? '' : 's'; ?></span>
            <?php if ($puedeEditar && (int)$p->visibles !== (int)$p->total): ?><span class="vp-chip-meta"><i class="fa-regular fa-eye"></i> <?php echo (int)$p->visibles; ?> visible<?php echo (int)$p->visibles === 1 ? '' : 's'; ?></span><?php endif; ?>
        </div>
        <div class="vp-acc">
            <a href="/vplan/paquete?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($p->id); ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-folder-open"></i> Abrir</a>
            <?php if ($puedeEditar): ?>
            <button type="button" class="vp-ib vp-renombrar-btn" title="Cambiar el título" aria-label="Cambiar el título de <?php echo s($p->nombre); ?>"><i class="fa-solid fa-pen"></i></button>
            <form method="POST" action="/vplan/paquete/eliminar" style="display:inline" data-confirm="¿Eliminar el vPlan «<?php echo s($p->nombre); ?>»?|Se borrarán sus <?php echo (int)$p->total; ?> lámina(s) con sus imágenes y marcas. Esta acción no se puede deshacer.|Sí, eliminar">
                <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>"><input type="hidden" name="id" value="<?php echo s($p->id); ?>">
                <button type="submit" class="vp-ib vp-ib-peligro" title="Eliminar vPlan" aria-label="Eliminar <?php echo s($p->nombre); ?>"><i class="fa-solid fa-trash-can"></i></button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($puedeEditar): ?>
        <form method="POST" action="/vplan/paquete/renombrar" class="vp-renombrar" hidden>
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>"><input type="hidden" name="id" value="<?php echo s($p->id); ?>"><input type="hidden" name="volver" value="lista">
            <input type="text" name="nombre" value="<?php echo s($p->nombre); ?>" maxlength="200" aria-label="Nombre del vPlan" required>
            <button type="submit" class="vp-ib vp-ib-ok" title="Guardar el título"><i class="fa-solid fa-check"></i></button>
            <button type="button" class="vp-ib vp-renombrar-cancelar" title="Cancelar"><i class="fa-solid fa-xmark"></i></button>
        </form>
        <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<script>
document.addEventListener('click', e => {
    const abre = e.target.closest('.vp-renombrar-btn'), cierra = e.target.closest('.vp-renombrar-cancelar');
    if (!abre && !cierra) return;
    const card = e.target.closest('.vp-paquete'), f = card.querySelector('.vp-renombrar');
    f.hidden = !abre;
    card.querySelector('.vp-acc').hidden = !!abre;
    if (abre) f.querySelector('input[name=nombre]').select();
});
</script>
