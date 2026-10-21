<?php // Modal de crear/editar nota (lo usan vPlan y View360 con public/vendor/notas.js). Variable: $maxLargo ?>
<div class="modal-overlay" id="vp-nota-modal" aria-hidden="true">
    <form class="modal-card vp-nota-card" id="vp-nota-form" role="dialog" aria-modal="true" aria-labelledby="vp-nota-titulo">
        <h3 class="modal-titulo" id="vp-nota-titulo">Crear nota</h3>
        <p class="text-muted text-sm vp-nota-fecha">Fecha: <strong id="vp-nota-fecha"></strong> <span id="vp-nota-auto">(se pone automáticamente)</span></p>
        <div class="campo">
            <label for="vp-nota-descripcion">Descripción *</label>
            <textarea id="vp-nota-descripcion" rows="5" maxlength="<?php echo (int)$maxLargo; ?>" required></textarea>
        </div>
        <p class="msr-error-txt text-sm" id="vp-nota-error" hidden></p>
        <div class="modal-acciones">
            <button type="button" class="btn btn-ghost" id="vp-nota-cancelar"><i class="fa-solid fa-xmark"></i> Cancelar</button>
            <button type="submit" class="btn btn-primary" id="vp-nota-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </div>
    </form>
</div>
