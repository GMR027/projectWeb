<?php // Barra del modo diseño. Variables: $proyecto, $lamina ?>
<div class="vp-dis-barra no-print" id="vp-dis-barra">
    <button type="button" class="btn btn-primary" id="vp-dis-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar diseño</button>
    <a href="/vplan/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($lamina->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-right-from-bracket"></i> Salir</a>
    <?php if (!empty($conNav)): ?><button type="button" class="btn btn-ghost" data-vp-nav-toggle aria-pressed="true" title="Mostrar u ocultar las láminas para tener más espacio"><i class="fa-solid fa-table-columns"></i> Láminas</button><?php endif; ?>
    <span class="vp-dis-estado" id="vp-dis-msg" aria-live="polite"></span>
    <span class="vp-barra-sep"></span>
    <div class="vp-tgls" role="group" aria-label="Ayudas de dibujo">
        <button type="button" class="vp-tgl" data-opt="rejilla" aria-pressed="true" title="Ajustar a rejilla de 0.5 %"><i class="fa-solid fa-border-all"></i> Rejilla</button>
        <button type="button" class="vp-tgl" data-opt="iman" aria-pressed="true" title="Imán: los bloques se pegan a las guías y a los bordes y centro de la hoja"><i class="fa-solid fa-magnet"></i> Imán</button>
        <button type="button" class="vp-tgl" data-opt="guias" aria-pressed="true" title="Mostrar u ocultar todas las guías"><i class="fa-solid fa-grip-lines"></i> Guías</button>
        <button type="button" class="vp-tgl" data-opt="reglas" aria-pressed="true" title="Reglas en cm; arrastra desde ellas para crear guías"><i class="fa-solid fa-ruler-combined"></i> Reglas</button>
    </div>
    <span class="vp-barra-sep"></span>
    <div class="vp-tgls" role="group" aria-label="Historial">
        <button type="button" class="vp-ib" id="vp-dis-deshacer" title="Deshacer (Ctrl+Z)" aria-label="Deshacer" disabled><i class="fa-solid fa-rotate-left"></i></button>
        <button type="button" class="vp-ib" id="vp-dis-rehacer" title="Rehacer (Ctrl+Y)" aria-label="Rehacer" disabled><i class="fa-solid fa-rotate-right"></i></button>
    </div>
    <button type="button" class="vp-ib" id="vp-dis-ayuda" title="Atajos y trucos del modo diseño" aria-label="Atajos y ayuda"><i class="fa-regular fa-circle-question"></i></button>
    <button type="button" class="btn btn-ghost" data-tour="vplan-diseno" title="Recorrer la herramienta paso a paso (se muestra sola la primera vez)"><i class="fa-solid fa-circle-play"></i> <?php echo s(t('Guía rápida')); ?></button>
</div>
