<?php // Selector de actividades/subactividades para una marca de material ?>
<div class="modal-overlay" id="ve-mat-modal" aria-hidden="true">
    <div class="modal-card ve-mat-modal-card" role="dialog" aria-modal="true" aria-labelledby="ve-mat-modal-titulo">
        <h3 id="ve-mat-modal-titulo">Actividades y subactividades</h3>
        <p class="text-muted text-sm">Marca lo que representa esta línea o forma. Puedes elegir varias actividades y varias subactividades a la vez.</p>
        <div class="pn-busca">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="mat-modal-buscar" placeholder="Buscar por partida, código o nombre…" aria-label="Buscar actividad">
        </div>
        <div class="mat-modal-lista" id="mat-modal-lista"></div>
        <p class="bj-error text-sm" id="mat-modal-error" hidden></p>
        <div class="modal-acciones">
            <button type="button" class="btn btn-ghost" id="mat-modal-cancelar">Cancelar</button>
            <button type="button" class="btn btn-primary" id="mat-modal-ok"><i class="fa-solid fa-check"></i> Aplicar</button>
        </div>
    </div>
</div>
