<?php
/** Tarjetas de las carpetas del proyecto (vista raíz). */
?>
<section class="archivo-seccion archivo-seccion-carpetas" aria-labelledby="archivo-carpetas-titulo">
    <div class="archivo-seccion-cab">
        <div>
            <h3 id="archivo-carpetas-titulo"><i class="fa-solid fa-folder-tree" aria-hidden="true"></i> Carpetas</h3>
            <p class="text-muted text-sm">Organiza los documentos por disciplina, etapa o proveedor.</p>
        </div>
        <div class="archivo-seccion-cab-acciones">
            <span class="suite-chip"><?php echo count($carpetas); ?></span>
            <?php if ($puede): ?>
                <button type="button" class="btn btn-sm btn-ghost arch-nueva">
                    <i class="fa-solid fa-folder-plus" aria-hidden="true"></i> Nueva carpeta
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$carpetas): ?>
        <div class="archivo-carpetas-vacio">
            <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
            <p><strong>Aún no hay carpetas.</strong> Crea una para separar planos, compras, calidad o contratos.</p>
            <?php if ($puede): ?>
                <button type="button" class="btn btn-sm btn-primary arch-nueva"><i class="fa-solid fa-plus" aria-hidden="true"></i> Crear la primera</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="archivo-carpetas-grid">
            <?php foreach ($carpetas as $k): $kid = (int)$k->id; $kst = $carpetasStats[$kid] ?? ['total' => 0, 'bytes' => 0, 'ultima' => null]; ?>
                <article class="archivo-carpeta-card" data-carpeta-id="<?php echo $kid; ?>">
                    <a class="archivo-carpeta-link" href="<?php echo s($urlCarpeta($kid)); ?>">
                        <span class="archivo-carpeta-icono" aria-hidden="true"><i class="fa-solid fa-folder"></i></span>
                        <span class="archivo-carpeta-txt">
                            <strong><?php echo s($k->nombre); ?></strong>
                            <small><?php echo (int)$kst['total']; ?> archivo(s) <span aria-hidden="true">·</span> <?php echo \Classes\Almacenamiento::formato((int)$kst['bytes']); ?></small>
                            <em><?php echo !empty($kst['ultima']) ? 'Actualizada el ' . s(fecha_corta_es($kst['ultima'])) : 'Carpeta vacía'; ?></em>
                        </span>
                        <i class="fa-solid fa-chevron-right archivo-carpeta-flecha" aria-hidden="true"></i>
                    </a>
                    <?php if ($puede): ?>
                        <div class="archivo-carpeta-acciones">
                            <button type="button" class="archivo-icono-btn arch-renombrar"
                                    data-id="<?php echo s($k->id); ?>" data-nombre="<?php echo s($k->nombre); ?>"
                                    title="Renombrar carpeta" aria-label="Renombrar carpeta «<?php echo s($k->nombre); ?>»">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                            </button>
                            <?php if ($borra): ?>
                                <button type="button" class="archivo-icono-btn arch-borrar"
                                        data-id="<?php echo s($k->id); ?>" data-nombre="<?php echo s($k->nombre); ?>" data-total="<?php echo (int)$kst['total']; ?>"
                                        title="Eliminar carpeta" aria-label="Eliminar carpeta «<?php echo s($k->nombre); ?>»">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
