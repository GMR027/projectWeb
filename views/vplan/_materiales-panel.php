<?php // Panel lateral «Materiales» del editor de marcas de vPlan. Variables: $config, $proyecto, $lamina ?>
<div class="ve-tab-panel" data-panel="materiales" hidden>
<aside class="card ve-materiales" id="ve-materiales">
    <div class="ve-capas-cab">
        <h4><i class="fa-solid fa-cubes"></i> Materiales <small class="text-muted" id="mat-contador"></small></h4>
    </div>
    <p class="text-muted text-sm">Dibuja líneas y formas en el plano y asígnales actividades o subactividades. La tabla de esta lámina se guarda en SheetFlow y se imprime como un bloque más.</p>

    <div id="mat-sin-tabla">
        <button type="button" class="btn btn-sm btn-primary" id="mat-crear"><i class="fa-solid fa-plus"></i> Crear tabla de materiales</button>
        <p class="text-muted text-sm" style="margin-top:.8rem">Se crea con una fila por actividad o subactividad marcada y las columnas <b>Material</b>, <b>Fecha de material</b> y <b>Status</b>. Después puedes agregar más columnas en SheetFlow.</p>
    </div>

    <div id="mat-con-tabla" hidden>
        <div class="ve-btns mat-acciones">
            <button type="button" class="btn btn-sm btn-primary" id="mat-sincronizar" title="Agrega las actividades y subactividades nuevas de las marcas"><i class="fa-solid fa-rotate"></i> Sincronizar</button>
            <a class="btn btn-sm btn-ghost" id="mat-sheetflow" href="#" target="_blank" rel="noopener"><i class="fa-solid fa-table-cells"></i> SheetFlow</a>
            <a class="btn btn-sm btn-ghost" id="mat-bloque" href="#"><i class="fa-solid fa-table-list"></i> Bloque en la lámina</a>
        </div>
        <p class="text-muted text-sm" id="mat-estado"></p>
        <details class="mat-refrescar">
            <summary>Traer fechas y estado del programa</summary>
            <p class="text-muted text-sm">Actualiza <b>Material</b>, <b>Fecha de material</b> y <b>Status</b> de los renglones vinculados. No toca las columnas extra.</p>
            <button type="button" class="btn btn-sm btn-ghost" id="mat-refrescar"><i class="fa-solid fa-arrows-rotate"></i> Actualizar columnas base</button>
        </details>

        <div class="pn-busca">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="mat-buscar" placeholder="Buscar material…" aria-label="Buscar material">
        </div>
        <div class="mat-resumen" id="mat-resumen"></div>
        <ul class="mat-leyenda" id="mat-leyenda"></ul>

        <div class="mat-tabla-cab">
            <h5>Tabla de la lámina</h5>
            <span class="text-muted text-sm" id="mat-tabla-info"></span>
        </div>
        <div class="mat-tabla-envoltura">
            <table class="mat-tabla" id="mat-tabla">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
        <p class="text-muted text-sm">Haz clic en una fila para seleccionar en el plano todas sus líneas y formas. Las columnas nuevas de SheetFlow aparecen aquí y, si caben, en el bloque de la lámina.</p>
    </div>
</aside>
</div>
