<!-- Modal de confirmación reutilizable (eliminar, etc.) -->
<div class="modal-overlay" id="modal-confirm" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-confirm-titulo">
        <div class="modal-icono">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 class="modal-titulo" id="modal-confirm-titulo">¿Eliminar registro?</h3>
        <p class="modal-mensaje" id="modal-confirm-mensaje">Esta acción no se puede deshacer.</p>
        <div class="modal-acciones">
            <button type="button" class="btn btn-ghost" id="modal-confirm-cancelar">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button type="button" class="btn btn-primary" id="modal-confirm-extra" hidden></button>
            <button type="button" class="btn btn-danger" id="modal-confirm-aceptar">
                <i class="fa-solid fa-trash-can"></i> Sí, eliminar
            </button>
        </div>
    </div>
</div>
