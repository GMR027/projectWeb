<?php
/** Rail de vistas y carpetas del Archivo. */
$limiteEtiqueta = $limiteBytes > 0 ? \Classes\Almacenamiento::formato((int)$limiteBytes) : 'Sin límite';
$totalCarpetas = count($carpetas);
?>
<aside class="archivo-rail" id="archivo-rail" aria-label="Carpetas y vistas del archivo">
    <div class="archivo-rail-cab">
        <span><i class="fa-solid fa-box-archive" aria-hidden="true"></i> Archivero</span>
        <?php if ($puede): ?>
            <button type="button" class="archivo-icono-btn arch-nueva" title="Nueva carpeta" aria-label="Nueva carpeta">
                <i class="fa-solid fa-folder-plus" aria-hidden="true"></i>
            </button>
        <?php endif; ?>
    </div>

    <nav class="archivo-rail-vistas" aria-label="Vistas del archivo">
        <a href="<?php echo s($urlArchivo(['carpeta' => null, 'vista' => null])); ?>"
           class="<?php echo $carpeta === null && !$recientes ? 'activa' : ''; ?>"
           <?php echo $carpeta === null && !$recientes ? 'aria-current="page"' : ''; ?>>
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <span>Todos los archivos</span>
            <em><?php echo (int)$totalArchivos; ?></em>
        </a>
        <a href="<?php echo s($urlArchivo(['carpeta' => 'sin', 'vista' => null])); ?>"
           data-carpeta-id="0"
           class="<?php echo $carpeta === 0 ? 'activa' : ''; ?>"
           <?php echo $carpeta === 0 ? 'aria-current="page"' : ''; ?>>
            <i class="fa-regular fa-file" aria-hidden="true"></i>
            <span>Sin carpeta</span>
            <em><?php echo (int)$sinCarpeta; ?></em>
        </a>
        <a href="<?php echo s($urlArchivo(['carpeta' => null, 'vista' => 'recientes'])); ?>"
           class="<?php echo $recientes ? 'activa' : ''; ?>"
           title="Últimos <?php echo (int)$totalRecientes; ?> archivos subidos"
           <?php echo $recientes ? 'aria-current="page"' : ''; ?>>
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            <span>Recientes</span>
            <em><?php echo (int)$totalRecientes; ?></em>
        </a>
    </nav>

    <div class="archivo-rail-seccion">
        <div class="archivo-rail-cab2">
            <span>Carpetas</span>
            <em><?php echo $totalCarpetas; ?></em>
        </div>
        <ul class="archivo-rail-carpetas">
            <?php foreach ($carpetas as $k): $kid = (int)$k->id; $kst = $carpetasStats[$kid] ?? ['total' => 0, 'bytes' => 0, 'ultima' => null]; ?>
                <li class="archivo-rail-carpeta">
                    <a href="<?php echo s($urlCarpeta($kid)); ?>"
                       data-carpeta-id="<?php echo $kid; ?>"
                       class="<?php echo $carpetaActual && (int)$carpetaActual->id === $kid ? 'activa' : ''; ?>"
                       <?php echo $carpetaActual && (int)$carpetaActual->id === $kid ? 'aria-current="page"' : ''; ?>>
                        <i class="fa-solid fa-folder" aria-hidden="true"></i>
                        <span><?php echo s($k->nombre); ?></span>
                        <em><?php echo (int)$kst['total']; ?></em>
                    </a>
                    <?php if ($puede): ?>
                        <span class="archivo-rail-acciones">
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
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$carpetas): ?>
            <p class="archivo-rail-vacio">Crea carpetas (planos, compras, calidad…) para ordenar los documentos.</p>
        <?php endif; ?>
        <?php if ($puede): ?>
            <button type="button" class="archivo-rail-nueva arch-nueva">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Nueva carpeta
            </button>
        <?php endif; ?>
    </div>

    <div class="archivo-rail-almacen">
        <div class="archivo-rail-almacen-cab">
            <span><i class="fa-solid fa-hard-drive" aria-hidden="true"></i> Espacio de archivos</span>
            <em><?php echo $limiteBytes > 0 ? number_format((float)$pct, 1) . ' %' : '—'; ?></em>
        </div>
        <div class="archivo-progreso">
            <span style="width:<?php echo (float)$pct; ?>%"></span>
        </div>
        <p><?php echo \Classes\Almacenamiento::formato((int)$totalBytes); ?> de <?php echo s($limiteEtiqueta); ?></p>
    </div>
</aside>
