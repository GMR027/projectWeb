<?php /** Modales de carpeta (solo se incluyen si el usuario puede gestionar). */ ?>
<div class="modal-overlay" id="arch-modal-carpeta" aria-hidden="true">
    <form class="modal-card ventana-modal" method="POST" action="/archivos/carpetas/guardar" role="dialog" aria-modal="true" aria-labelledby="arch-carpeta-titulo">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <input type="hidden" name="id" id="arch-carpeta-id" value="">
        <header class="ventana-cab">
            <span class="ventana-icono" aria-hidden="true"><i class="fa-solid fa-folder-plus"></i></span>
            <div class="ventana-cab-txt">
                <span class="ventana-eyebrow">Archivero</span>
                <h3 class="modal-titulo" id="arch-carpeta-titulo">Nueva carpeta</h3>
                <p>Organiza los documentos por disciplina, etapa o proveedor.</p>
            </div>
            <button type="button" class="ventana-x" data-cerrar aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <div class="ventana-cuerpo">
            <div class="campo"><label for="arch-carpeta-nombre">Nombre *</label><input type="text" id="arch-carpeta-nombre" name="nombre" maxlength="100" required placeholder="Ej: Compras, Ingeniería, Calidad"></div>
        </div>
        <div class="modal-acciones ventana-pie">
            <button type="button" class="btn btn-ghost" data-cerrar><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar carpeta</button>
        </div>
    </form>
</div>

<div class="modal-overlay" id="arch-modal-borrar" aria-hidden="true">
    <form class="modal-card ventana-modal" method="POST" action="/archivos/carpetas/eliminar" role="dialog" aria-modal="true" aria-labelledby="arch-borrar-titulo">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <input type="hidden" name="id" id="arch-borrar-id" value="">
        <header class="ventana-cab">
            <span class="ventana-icono" aria-hidden="true"><i class="fa-solid fa-folder-minus"></i></span>
            <div class="ventana-cab-txt">
                <span class="ventana-eyebrow">Archivero</span>
                <h3 class="modal-titulo" id="arch-borrar-titulo">Eliminar carpeta</h3>
                <p>Elige si quieres conservar los archivos o borrarlos también.</p>
            </div>
            <button type="button" class="ventana-x" data-cerrar aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <div class="ventana-cuerpo">
            <p class="text-muted" id="arch-borrar-msg"></p>
            <div class="archivo-opciones">
                <label><input type="radio" name="modo" value="solo" checked> <span><strong>Solo la carpeta</strong><br><small class="text-muted">Los archivos no se borran: vuelven a «Sin carpeta».</small></span></label>
                <label id="arch-borrar-todo"><input type="radio" name="modo" value="todo"> <span><strong>La carpeta y su contenido</strong><br><small class="text-muted">Se borran también sus <span id="arch-borrar-n"></span> archivo(s) del servidor. No se puede deshacer.</small></span></label>
            </div>
        </div>
        <div class="modal-acciones ventana-pie">
            <button type="button" class="btn btn-ghost" data-cerrar><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancelar</button>
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash" aria-hidden="true"></i> Eliminar carpeta</button>
        </div>
    </form>
</div>
