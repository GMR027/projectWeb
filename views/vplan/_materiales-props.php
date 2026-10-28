<?php // Sección «Materiales» del panel de Propiedades y selector de actividades/subactividades. ?>
<div class="ve-mat-props" id="p-mat-seccion" style="display:none">
    <h5><i class="fa-solid fa-cubes"></i> Materiales de esta marca</h5>
    <ul class="mat-chips" id="p-mat-lista"></ul>
    <p class="text-muted text-sm" id="p-mat-vacio">Sin actividades asignadas. Agrega una actividad o subactividad para relacionar la forma con el programa.</p>
    <div class="ve-btns">
        <button type="button" class="btn btn-sm btn-primary" id="p-mat-agregar"><i class="fa-solid fa-plus"></i> Agregar actividad</button>
        <button type="button" class="btn btn-sm btn-ghost" id="p-mat-convertir">Convertir en marca de material</button>
    </div>

    <div id="p-mat-imagenes" style="margin-top:1.2rem">
        <label class="ve-chk"><input type="checkbox" id="p-mat-mostrar"> Mostrar imágenes de los materiales</label>
        <div class="ve-f" id="p-mat-tam-f">
            <label for="p-mat-tam">Tamaño de las imágenes</label>
            <div class="ve-rango"><input type="range" id="p-mat-tam" min="24" max="400" step="2" value="64" aria-label="Tamaño de las imágenes"><span><input type="number" id="p-mat-tam-n" min="24" max="400" step="2"> px</span></div>
            <small class="text-muted">Se aplica al instante; puedes ver el resultado mientras mueves el control.</small>
        </div>
        <div class="ve-f" id="p-mat-pos-f">
            <label for="p-mat-pos">Dónde se ven</label>
            <select id="p-mat-pos">
                <option value="centro">En el centro de la forma</option>
                <option value="borde">Sobre el borde superior</option>
            </select>
            <small class="text-muted">En las líneas se colocan siempre arriba, centradas sobre el trazo.</small>
        </div>
        <label class="ve-chk" style="margin-top:.8rem"><input type="checkbox" id="p-mat-color-estado"> Colorear la marca según el estado del programa</label>
        <p class="text-muted text-sm" id="p-mat-img-info"></p>
    </div>
</div>
