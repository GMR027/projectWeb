<?php
/** Contexto de la vista: carpeta abierta, sin carpeta, recientes o categoría. */
$contextoStats = $carpetaActual
    ? ($carpetasStats[(int)$carpetaActual->id] ?? ['total' => 0, 'bytes' => 0, 'ultima' => null])
    : ($carpeta === 0 ? $sinCarpetaStats : null);
$categoriaActual = null;
if ($filtro) {
    foreach ($categorias as $c) {
        if ((int)$c->id === (int)$filtro) { $categoriaActual = $c; break; }
    }
}
$urlSinFiltros = $urlArchivo(['carpeta' => null, 'vista' => null, 'categoria' => null]);
?>
<div class="archivo-contexto">
    <div class="archivo-migas">
        <a href="<?php echo s($urlSinFiltros); ?>">Archivo</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <?php if ($recientes): ?>
            <span>Recientes</span>
        <?php elseif ($carpetaActual): ?>
            <?php if ($categoriaActual): ?><span><?php echo s($categoriaActual->nombre); ?></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><?php endif; ?>
            <strong><?php echo s($carpetaActual->nombre); ?></strong>
        <?php elseif ($carpeta === 0): ?>
            <strong>Sin carpeta</strong>
        <?php elseif ($categoriaActual): ?>
            <span>Categoría: <?php echo s($categoriaActual->nombre); ?></span>
        <?php endif; ?>
    </div>

    <div class="archivo-contexto-cab">
        <span class="archivo-contexto-icono<?php echo $recientes ? ' is-reciente' : ''; ?>" aria-hidden="true">
            <i class="fa-solid <?php echo $recientes ? 'fa-clock-rotate-left' : ($carpeta === 0 ? 'fa-file-circle-question' : ($categoriaActual ? 'fa-tags' : 'fa-folder-open')); ?>"></i>
        </span>
        <div class="archivo-contexto-txt">
            <?php if ($recientes): ?>
                <h3>Recientes</h3>
                <p class="text-muted">Últimos <?php echo (int)$totalRecientes; ?> archivos subidos · ordenados por subida</p>
            <?php elseif ($carpetaActual): ?>
                <h3><?php echo s($carpetaActual->nombre); ?></h3>
                <p class="text-muted">
                    <?php echo (int)$contextoStats['total']; ?> archivo(s)
                    <span aria-hidden="true">·</span> <?php echo \Classes\Almacenamiento::formato((int)$contextoStats['bytes']); ?>
                    <?php if (!empty($contextoStats['ultima'])): ?>
                        <span aria-hidden="true">·</span> actualizada el <?php echo s(fecha_corta_es($contextoStats['ultima'])); ?>
                    <?php endif; ?>
                </p>
            <?php elseif ($carpeta === 0): ?>
                <h3>Sin carpeta</h3>
                <p class="text-muted">
                    <?php echo (int)$contextoStats['total']; ?> archivo(s) por clasificar
                    <span aria-hidden="true">·</span> <?php echo \Classes\Almacenamiento::formato((int)$contextoStats['bytes']); ?>
                </p>
            <?php else: ?>
                <h3>Categoría: <?php echo s($categoriaActual->nombre ?? 'Todas'); ?></h3>
                <p class="text-muted">Documentos clasificados en esta categoría.</p>
            <?php endif; ?>
        </div>
        <div class="archivo-contexto-acciones">
            <?php if ($carpetaActual && $puede): ?>
                <a href="/archivos/lote?proyecto=<?php echo $id; ?>&carpeta=<?php echo (int)$carpetaActual->id; ?>" class="btn btn-sm btn-ghost">
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i> Subir varios
                </a>
                <a href="/archivos/crear?proyecto=<?php echo $id; ?>&carpeta=<?php echo (int)$carpetaActual->id; ?>" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i> Subir archivo
                </a>
                <button type="button" class="btn btn-sm btn-ghost arch-renombrar"
                        data-id="<?php echo s($carpetaActual->id); ?>" data-nombre="<?php echo s($carpetaActual->nombre); ?>">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Renombrar
                </button>
                <?php if ($borra): ?>
                    <button type="button" class="btn btn-sm btn-ghost arch-borrar"
                            data-id="<?php echo s($carpetaActual->id); ?>" data-nombre="<?php echo s($carpetaActual->nombre); ?>" data-total="<?php echo (int)$contextoStats['total']; ?>">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i> Eliminar
                    </button>
                <?php endif; ?>
            <?php elseif ($carpeta === 0 && $puede): ?>
                <a href="/archivos/crear?proyecto=<?php echo $id; ?>" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i> Subir archivo
                </a>
            <?php endif; ?>
            <?php if ($categoriaActual): ?>
                <a href="<?php echo s($urlArchivo(['categoria' => null])); ?>" class="btn btn-sm btn-ghost">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i> Quitar categoría
                </a>
            <?php endif; ?>
            <?php if ($recientes): ?>
                <a href="<?php echo s($urlArchivo(['vista' => null])); ?>" class="btn btn-sm btn-ghost">
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i> Ver todos
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
