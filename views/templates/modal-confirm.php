<!-- Modal de confirmación reutilizable (eliminar, etc.) -->
<div class="modal-overlay" id="modal-confirm" aria-hidden="true">
    <div class="modal-card ventana-modal" role="dialog" aria-modal="true" aria-labelledby="modal-confirm-titulo">
        <header class="ventana-cab">
            <span class="modal-icono ventana-icono" aria-hidden="true">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </span>
            <div class="ventana-cab-txt">
                <span class="ventana-eyebrow">Confirmación</span>
                <h3 class="modal-titulo" id="modal-confirm-titulo">¿Eliminar registro?</h3>
            </div>
        </header>
        <div class="ventana-cuerpo">
            <p class="modal-mensaje" id="modal-confirm-mensaje">Esta acción no se puede deshacer.</p>
        </div>
        <div class="modal-acciones ventana-pie">
            <button type="button" class="btn btn-ghost" id="modal-confirm-cancelar">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button type="button" class="btn btn-ghost" id="modal-confirm-extra" hidden></button>
            <button type="button" class="btn btn-danger" id="modal-confirm-aceptar">
                <i class="fa-solid fa-trash-can"></i> Sí, eliminar
            </button>
        </div>
    </div>
</div>

<!-- Modal para pedir un dato (sustituye a prompt()) -->
<div class="modal-overlay" id="modal-pedir" aria-hidden="true">
    <div class="modal-card ventana-modal" role="dialog" aria-modal="true" aria-labelledby="modal-pedir-titulo">
        <header class="ventana-cab">
            <span class="modal-icono ventana-icono" aria-hidden="true">
                <i class="fa-solid fa-keyboard"></i>
            </span>
            <div class="ventana-cab-txt">
                <span class="ventana-eyebrow">Dato requerido</span>
                <h3 class="modal-titulo" id="modal-pedir-titulo">Escribe un dato</h3>
            </div>
        </header>
        <div class="ventana-cuerpo">
            <p class="modal-mensaje" id="modal-pedir-mensaje"></p>
            <label class="modal-campo" id="modal-pedir-campo">
                <span id="modal-pedir-etiqueta"></span>
                <input type="text" id="modal-pedir-input" autocomplete="off">
            </label>
            <p class="modal-error" id="modal-pedir-error" role="alert" hidden></p>
        </div>
        <div class="modal-acciones ventana-pie">
            <button type="button" class="btn btn-ghost" id="modal-pedir-cancelar">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button type="button" class="btn btn-primary" id="modal-pedir-aceptar">
                <i class="fa-solid fa-check"></i> Aceptar
            </button>
        </div>
    </div>
</div>
