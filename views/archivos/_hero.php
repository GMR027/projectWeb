<?php
/** Cabecera del explorador de Archivo. */
$limiteEtiqueta = $limiteBytes > 0 ? \Classes\Almacenamiento::formato((int)$limiteBytes) : 'Sin límite';
$pctEtiqueta = $limiteBytes > 0 ? number_format((float)$pct, 1) . ' %' : '—';
?>
<header class="archivo-hero">
    <div class="archivo-hero-cab">
        <span class="archivo-hero-icono" aria-hidden="true"><i class="fa-solid fa-folder-open"></i></span>
        <div class="archivo-hero-txt">
            <p class="archivo-eyebrow">Documentos / Archivo</p>
            <h2>Archivo de obra</h2>
            <p class="text-muted">
                <?php echo s($proyecto->nombre); ?>
                <span aria-hidden="true">·</span> <?php echo (int)$totalArchivos; ?> documento(s)
                <span aria-hidden="true">·</span> <?php echo count($carpetas); ?> carpeta(s)
            </p>
        </div>
        <div class="archivo-hero-acciones">
            <a href="/archivos/categorias?proyecto=<?php echo $id; ?>" class="btn btn-ghost">
                <i class="fa-solid fa-tags" aria-hidden="true"></i> Categorías
            </a>
            <?php if ($puede): ?>
                <a href="/archivos/lote?proyecto=<?php echo $id; ?><?php echo $carpetaActual ? '&carpeta=' . (int)$carpetaActual->id : ''; ?>" class="btn btn-ghost">
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i> Subir varios
                </a>
                <a href="/archivos/crear?proyecto=<?php echo $id; ?><?php echo $carpetaActual ? '&carpeta=' . (int)$carpetaActual->id : ''; ?>" class="btn btn-primary">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i> Subir archivo
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="archivo-kpis">
        <div class="suite-kpi">
            <span class="suite-kpi-icono"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
            <span class="suite-kpi-txt"><small>Documentos</small><strong><?php echo (int)$totalArchivos; ?></strong><span>en este proyecto</span></span>
        </div>
        <div class="suite-kpi is-info">
            <span class="suite-kpi-icono"><i class="fa-solid fa-folder" aria-hidden="true"></i></span>
            <span class="suite-kpi-txt"><small>Carpetas</small><strong><?php echo count($carpetas); ?></strong><span>para organizar el archivo</span></span>
        </div>
        <div class="suite-kpi">
            <span class="suite-kpi-icono"><i class="fa-solid fa-tags" aria-hidden="true"></i></span>
            <span class="suite-kpi-txt"><small>Categorías</small><strong><?php echo count($categorias); ?></strong><span>clasificación de documentos</span></span>
        </div>
        <div class="suite-kpi <?php echo $sinCarpeta ? 'is-warning' : 'is-success'; ?>">
            <span class="suite-kpi-icono"><i class="fa-regular fa-file" aria-hidden="true"></i></span>
            <span class="suite-kpi-txt"><small>Sin carpeta</small><strong><?php echo (int)$sinCarpeta; ?></strong><span>documentos por clasificar</span></span>
        </div>
        <div class="archivo-kpi-almacen<?php echo $limiteBytes > 0 && $totalBytes > $limiteBytes ? ' is-danger' : ''; ?>">
            <div class="archivo-kpi-almacen-cab">
                <span><i class="fa-solid fa-hard-drive" aria-hidden="true"></i> Archivos</span>
                <strong><?php echo s($pctEtiqueta); ?></strong>
            </div>
            <div class="archivo-progreso" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)round((float)$pct); ?>" aria-label="Uso de espacio de archivos">
                <span style="width:<?php echo (float)$pct; ?>%"></span>
            </div>
            <p><b><?php echo \Classes\Almacenamiento::formato((int)$totalBytes); ?></b> de <?php echo s($limiteEtiqueta); ?> del límite del proyecto</p>
        </div>
    </div>
</header>
