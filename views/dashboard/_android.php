<?php
/** Aviso compacto de la app Android. Variables: $apk_android */
?>
<section class="card inicio-android" id="banner-android">
    <span class="inicio-android-ico"><i class="fa-brands fa-android" aria-hidden="true"></i></span>
    <div class="inicio-android-txt">
        <strong><?php echo s(t('Lleva Project Web a la obra')); ?></strong>
        <p><?php echo s(t('Reporta desde campo, incluso sin conexión.')); ?>
            <span class="mono text-muted">v<?php echo s($apk_android['version']); ?> · <?php echo number_format($apk_android['tam'] / 1048576, 1); ?> MB</span></p>
    </div>
    <a href="/api/android/descargar" class="btn btn-primary btn-sm"><i class="fa-solid fa-download" aria-hidden="true"></i> <?php echo s(t('Descargar app')); ?></a>
    <form method="POST" action="/dashboard/banner-android" class="inicio-android-cerrar">
        <button type="submit" aria-label="<?php echo s(t('Cerrar aviso')); ?>" title="<?php echo s(t('Cerrar aviso')); ?>">×</button>
    </form>
</section>
