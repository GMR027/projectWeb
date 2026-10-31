<?php
// Pestaña «Materiales» de la barra superior del editor de marcas de vPlan.
// Variables: $config (con materiales), $tool, $svg, $ic.
$lineasMat = [['line', 'Línea continua'], ['dash', 'Línea discontinua'], ['dashdot', 'Línea trazo y punto'], ['dot', 'Línea punteada'],
    ['poly', 'Polilínea: clic en cada punto, doble clic o Enter para terminar'], ['pencil', 'Lápiz (trazo libre)'], ['arrow', 'Flecha'], ['darrow', 'Flecha doble']];
$formasMat = [['rect', 'Rectángulo'], ['rrect', 'Rectángulo redondeado'], ['ellipse', 'Elipse / círculo'], ['triangle', 'Triángulo'], ['diamond', 'Rombo'], ['polygon', 'Polígono: clic en cada vértice y doble clic, Enter o clic en el primer punto para cerrar'], ['zone', 'Zona translúcida']];
$masFormasMat = [['pentagon', 'Pentágono'], ['hexagon', 'Hexágono'], ['star', 'Estrella'], ['cross', 'Cruz (+)'], ['xmark', 'Equis (X)'], ['check', 'Palomita'], ['blockarrow', 'Flecha de bloque'], ['chevron', 'Chevron'], ['trapezoid', 'Trapecio'], ['octagon', 'Octágono'], ['parallelogram', 'Paralelogramo'], ['rtriangle', 'Triángulo rectángulo'], ['house', 'Casa / pentágono con punta'], ['arrowleft', 'Flecha de bloque izquierda'], ['arrowup', 'Flecha de bloque arriba'], ['arrowdown', 'Flecha de bloque abajo'], ['arrowlr', 'Flecha de bloque doble horizontal'], ['homeplate', 'Etiqueta / pentágono flecha'], ['star4', 'Estrella de 4 puntas'], ['star6', 'Estrella de 6 puntas'], ['star8', 'Estrella de 8 puntas'], ['heart', 'Corazón'], ['moon', 'Luna creciente'], ['semicircle', 'Semicírculo'], ['bolt', 'Rayo'], ['drop', 'Gota'], ['lshape', 'Forma en L'], ['tshape', 'Forma en T'], ['ushape', 'Forma en U'], ['callout', 'Globo de texto'], ['flag', 'Bandera']];
?>
<div class="ve-grupo" data-pan="materiales" data-nombre="Línea de materiales">
    <?php foreach ($lineasMat as [$t, $ti]) echo $tool($t, $ti); ?>
</div>
<div class="ve-grupo" data-pan="materiales" data-nombre="Forma de materiales">
    <?php foreach ($formasMat as [$t, $ti]) echo $tool($t, $ti); ?>
    <div class="ve-mas">
        <button type="button" id="mat-mas-formas" class="ve-mas-btn" title="Más formas" aria-label="Más formas" aria-haspopup="true" aria-expanded="false"><?php echo $svg($ic['star']); ?><svg viewBox="0 0 10 10" width="10" height="10" aria-hidden="true"><path d="M1 3l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <div class="ve-mas-panel" id="mat-mas-panel" hidden>
            <?php foreach ($masFormasMat as [$t, $ti]) echo $tool($t, $ti); ?>
        </div>
    </div>
</div>
<div class="ve-grupo" data-pan="materiales" data-nombre="Tabla de instalación">
    <button type="button" class="ve-txt" id="mat-crear-barra" title="Crea (o abre) la tabla de instalación de materiales de esta lámina en SheetFlow"><i class="fa-solid fa-table-list"></i> Tabla de materiales</button>
    <button type="button" id="mat-sincronizar-barra" class="ve-txt" title="Agrega a la tabla las actividades y subactividades nuevas de las marcas"><i class="fa-solid fa-rotate"></i> Sincronizar</button>
</div>
