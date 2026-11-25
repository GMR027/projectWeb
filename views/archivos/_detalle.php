<?php
/** Panel lateral de detalles del archivo (se rellena desde las tarjetas con JS). */
?>
<div class="archivo-detalle-fondo" id="archivo-detalle-fondo" aria-hidden="true"></div>
<aside class="archivo-detalle" id="archivo-detalle" role="dialog" aria-modal="true" aria-labelledby="archivo-detalle-titulo" aria-hidden="true" tabindex="-1">
    <div class="archivo-detalle-cab">
        <div>
            <span class="archivo-eyebrow">Archivo</span>
            <h3 id="archivo-detalle-titulo">Detalles del archivo</h3>
        </div>
        <button type="button" class="archivo-icono-btn" id="archivo-detalle-cerrar" aria-label="Cerrar detalles">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <div class="archivo-detalle-cuerpo">
        <div class="archivo-detalle-preview" id="archivo-detalle-preview"></div>
        <h4 id="archivo-detalle-nombre"></h4>
        <div class="archivo-detalle-chips" id="archivo-detalle-chips"></div>

        <div class="archivo-detalle-acciones">
            <a class="btn btn-primary" id="archivo-detalle-ver" href="#" hidden><i class="fa-regular fa-eye" aria-hidden="true"></i> Abrir en visor</a>
            <a class="btn btn-ghost" id="archivo-detalle-descargar" href="#" hidden><i class="fa-solid fa-download" aria-hidden="true"></i> Descargar</a>
        </div>

        <dl class="archivo-detalle-meta">
            <div><dt>Categoría</dt><dd id="archivo-detalle-categoria">—</dd></div>
            <div><dt>Carpeta</dt><dd id="archivo-detalle-carpeta">—</dd></div>
            <div><dt>Revisión</dt><dd id="archivo-detalle-revision">—</dd></div>
            <div><dt>Fecha</dt><dd id="archivo-detalle-fecha">—</dd></div>
            <div><dt>Tamaño</dt><dd id="archivo-detalle-tamano">—</dd></div>
            <div><dt>Subido por</dt><dd id="archivo-detalle-subido">—</dd></div>
            <div><dt>Nombre original</dt><dd class="mono" id="archivo-detalle-original">—</dd></div>
            <div id="archivo-detalle-ensamblado-fila" hidden><dt>Estado</dt><dd><span class="archivo-chip archivo-chip-ensamble"><i class="fa-solid fa-object-group" aria-hidden="true"></i> Ensamblado · se actualiza solo</span></dd></div>
        </dl>

        <div class="archivo-detalle-bloque" id="archivo-detalle-revisiones" hidden>
            <h5><i class="fa-solid fa-code-branch" aria-hidden="true"></i> Otras revisiones</h5>
            <ul class="archivo-detalle-revisiones" id="archivo-detalle-revisiones-lista"></ul>
        </div>

        <?php if ($puede): ?>
            <form method="POST" action="/archivos/mover" class="archivo-detalle-mover" id="archivo-detalle-mover">
                <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                <input type="hidden" name="ids[]" id="archivo-detalle-id" value="">
                <label for="archivo-detalle-carpeta">Mover a carpeta</label>
                <div class="archivo-detalle-mover-fila">
                    <select name="carpeta_id" id="archivo-detalle-carpeta">
                        <option value="">— Sin carpeta —</option>
                        <?php foreach ($carpetas as $k): ?><option value="<?php echo s($k->id); ?>"><?php echo s($k->nombre); ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-ghost"><i class="fa-solid fa-folder-tree" aria-hidden="true"></i> Mover</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="archivo-detalle-pie">
            <a class="btn btn-sm btn-ghost" id="archivo-detalle-editar" href="#" hidden><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar metadatos</a>
            <button type="button" class="btn btn-sm btn-danger" id="archivo-detalle-eliminar" hidden><i class="fa-solid fa-trash" aria-hidden="true"></i> Eliminar</button>
        </div>
    </div>
</aside>
