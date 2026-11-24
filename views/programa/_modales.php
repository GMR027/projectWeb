<?php if ($puedeGestionar): ?>
<!-- Cambio rápido de fechas (acción de fila y móvil) -->
<div class="modal-overlay prg-modal-fechas" id="modal-fechas" aria-hidden="true">
    <div class="modal-card prg-fechas-card" role="dialog" aria-modal="true" aria-labelledby="mf-titulo">
        <div class="prg-modal-cab">
            <span class="prg-modal-icono"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i></span>
            <div class="prg-modal-cab-txt">
                <span class="prg-modal-eyebrow"><?php echo s(t('Programación')); ?></span>
                <h3 id="mf-titulo"><?php echo s(t('Cambiar fechas')); ?></h3>
                <p class="text-muted text-sm" id="mf-actividad"></p>
            </div>
            <button type="button" class="btn btn-ghost prg-modal-x" id="mf-cerrar" aria-label="<?php echo s(t('Cerrar')); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="prg-fechas-grid">
            <label class="campo">
                <span><?php echo s(t('Fecha de inicio')); ?> <b aria-hidden="true">*</b></span>
                <input type="date" id="mf-inicio">
            </label>
            <span class="prg-fechas-flecha" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
            <label class="campo">
                <span><?php echo s(t('Fecha de fin')); ?> <b aria-hidden="true">*</b></span>
                <input type="date" id="mf-fin">
            </label>
        </div>
        <div class="prg-fechas-resumen">
            <span class="prg-fechas-duracion" id="mf-duracion"><i class="fa-solid fa-clock" aria-hidden="true"></i> —</span>
            <small><?php echo s(t('El fin se recalcula con el calendario del proyecto.')); ?></small>
        </div>
        <div class="prg-fechas-rapidas" aria-label="<?php echo s(t('Ajustes rápidos')); ?>">
            <button type="button" class="btn btn-ghost btn-sm" data-fecha-rapida="hoy"><?php echo s(t('Hoy')); ?></button>
            <button type="button" class="btn btn-ghost btn-sm" data-fecha-rapida="dia"><?php echo s(t('+1 día')); ?></button>
            <button type="button" class="btn btn-ghost btn-sm" data-fecha-rapida="semana"><?php echo s(t('+1 semana')); ?></button>
            <span class="prg-fechas-nota" id="mf-nota" hidden></span>
        </div>
        <p class="prg-error" id="mf-error" hidden></p>
        <div class="prg-modal-pie">
            <button type="button" class="btn btn-ghost" id="mf-cancelar"><?php echo s(t('Cancelar')); ?></button>
            <button type="button" class="btn btn-primary" id="mf-guardar"><i class="fa-solid fa-check" aria-hidden="true"></i> <?php echo s(t('Guardar fechas')); ?></button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Formulario de actividad, partida o subactividad en modal -->
<?php if ($puedeGestionar): ?>
<div class="modal-overlay prg-modal-actividad" id="prg-modal-actividad" aria-hidden="true">
    <div class="modal-card prg-modal-card" role="dialog" aria-modal="true" aria-labelledby="prg-modal-titulo">
        <div class="prg-modal-cab">
            <div class="prg-modal-cab-txt">
                <span class="prg-modal-eyebrow"><?php echo s(t('Programa de obra')); ?> · <?php echo s($proyecto->codigo); ?></span>
                <h3 id="prg-modal-titulo"><?php echo s(t('Actividad')); ?></h3>
            </div>
            <div class="prg-modal-cab-acciones">
                <a href="#" class="btn btn-ghost btn-sm" id="prg-modal-completo" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> <span class="hide-m"><?php echo s(t('Página completa')); ?></span></a>
                <button type="button" class="btn btn-ghost prg-modal-x" id="prg-modal-cerrar" aria-label="<?php echo s(t('Cerrar')); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
        </div>
        <div class="prg-modal-cargando" id="prg-modal-cargando"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> <?php echo s(t('Cargando…')); ?></div>
        <iframe id="prg-modal-frame" title="<?php echo s(t('Formulario de la actividad')); ?>"></iframe>
    </div>
</div>
<?php endif; ?>

<?php if ($puedeGestionar): ?>
<dialog id="prg-dlg-columna" class="prg-dialogo">
    <form method="dialog" id="prg-form-columna">
        <div class="prg-dialogo-cab">
            <div><span class="prg-modal-eyebrow"><?php echo s(t('Tabla WBS')); ?></span><h3><?php echo s(t('Renombrar columna')); ?></h3></div>
            <button type="button" class="btn btn-ghost prg-modal-x" id="prg-col-cerrar" aria-label="<?php echo s(t('Cerrar')); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <label class="campo"><?php echo s(t('Nombre de la columna')); ?>
            <input type="text" id="prg-col-nombre" maxlength="60" autocomplete="off">
        </label>
        <p class="text-sm text-muted"><?php echo s(t('Vacío = nombre de siempre. También puedes arrastrar los encabezados para cambiar el orden.')); ?></p>
        <div class="prg-dialogo-pie">
            <button type="button" class="btn btn-ghost" id="prg-col-cancelar"><?php echo s(t('Cancelar')); ?></button>
            <button type="submit" class="btn btn-primary"><?php echo s(t('Guardar')); ?></button>
        </div>
    </form>
</dialog>
<?php endif; ?>
