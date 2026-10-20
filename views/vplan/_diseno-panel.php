<?php
// Panel lateral del modo diseño: Propiedades · Capas · Insertar · Hoja. Variables: $proyecto, $lamina
use Classes\VplanDiseno;
$preset = $lamina->preset;
$vertical = $lamina->alto_mm > $lamina->ancho_mm;
?>
<aside class="vp-dis-panel no-print" aria-label="Herramientas del diseño">
    <div class="vp-ptabs" role="tablist">
        <button type="button" role="tab" class="activo" data-ptab="props"><i class="fa-solid fa-sliders"></i> Propiedades</button>
        <button type="button" role="tab" data-ptab="capas"><i class="fa-solid fa-layer-group"></i> Capas <span class="vp-cont" id="vp-cont-capas"></span></button>
        <button type="button" role="tab" data-ptab="insertar"><i class="fa-solid fa-plus"></i> Insertar</button>
        <button type="button" role="tab" data-ptab="hoja"><i class="fa-regular fa-file"></i> Hoja</button>
    </div>

    <section data-pan="props">
        <div class="vp-prop-vacio" id="vp-dis-vacio"><i class="fa-regular fa-hand-pointer"></i><b>Nada seleccionado</b><p class="text-muted">Haz clic en un bloque de la hoja, o en una capa, para ver y cambiar sus propiedades.</p></div>
        <div id="vp-dis-cuerpo" hidden>
            <header class="vp-prop-cab">
                <span class="vp-prop-ico"><i class="fa-solid fa-square" id="vp-dis-ico"></i></span>
                <div class="vp-prop-nom"><h3 id="vp-dis-tipo"></h3><small id="vp-dis-tipo2"></small></div>
            </header>

            <div class="vp-prop-sec">
                <h4 class="vp-prop-tit"><i class="fa-solid fa-up-down-left-right"></i> Posición y tamaño</h4>
                <div class="vp-dis-pos">
                    <?php foreach ([['x', 'X', 'fa-arrows-left-right'], ['y', 'Y', 'fa-arrows-up-down'], ['w', 'Ancho', 'fa-left-right'], ['h', 'Alto', 'fa-up-down']] as [$k, $n, $ic]): ?>
                        <div class="campo vp-num"><label for="vp-pos-<?php echo $k; ?>"><?php echo $n; ?></label>
                            <div class="vp-num-in"><input type="number" id="vp-pos-<?php echo $k; ?>" step="0.1" min="0"><span>cm</span></div></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="vp-prop-sec">
                <h4 class="vp-prop-tit"><i class="fa-solid fa-layer-group"></i> Orden y acciones</h4>
                <div class="vp-prop-acc">
                    <button type="button" class="vp-pb" id="vp-dis-frente" title="Pasar al frente"><i class="fa-solid fa-arrow-up-from-bracket"></i><span>Al frente</span></button>
                    <button type="button" class="vp-pb" id="vp-dis-fondo" title="Enviar al fondo"><i class="fa-solid fa-arrow-down-short-wide"></i><span>Al fondo</span></button>
                    <button type="button" class="vp-pb" id="vp-dis-duplicar" title="Duplicar este bloque"><i class="fa-regular fa-copy"></i><span>Duplicar</span></button>
                    <button type="button" class="vp-pb vp-pb-peligro" id="vp-dis-quitar" title="Quitar este bloque de la lámina"><i class="fa-solid fa-trash-can"></i><span>Quitar</span></button>
                </div>
            </div>

            <div class="vp-prop-sec">
                <h4 class="vp-prop-tit"><i class="fa-regular fa-eye"></i> Visibilidad</h4>
                <label class="vp-sw"><input type="checkbox" id="vp-c-oculto"><span class="vp-sw-pista"></span><span class="vp-sw-txt"><b>Oculto</b><small>No se ve ni se exporta</small></span></label>
                <label class="vp-sw"><input type="checkbox" id="vp-c-bloqueado"><span class="vp-sw-pista"></span><span class="vp-sw-txt"><b>Bloqueado</b><small>No se puede mover ni cambiar de tamaño</small></span></label>
            </div>

            <div class="vp-prop-sec">
                <h4 class="vp-prop-tit"><i class="fa-solid fa-pen-to-square"></i> Contenido</h4>
                <div id="vp-dis-campos"></div>
            </div>
        </div>
        <div id="vp-dis-guia-cuerpo" hidden>
            <h3>Guía <span id="vp-guia-tipo"></span></h3>
            <p class="text-sm text-muted">Arrastra la línea en la hoja o escribe su posición. Los bloques se pegan a ella cuando el imán está activo.</p>
            <div class="campo"><label for="vp-guia-pos">Posición (cm desde <span id="vp-guia-desde"></span>)</label><input type="number" id="vp-guia-pos" step="0.1"></div>
            <div class="vp-dis-fila"><button type="button" class="btn btn-sm btn-danger" id="vp-guia-quitar"><i class="fa-solid fa-trash-can"></i> Quitar guía</button></div>
        </div>
    </section>

    <section data-pan="capas" hidden>
        <div class="vp-cap-herr">
            <button type="button" class="btn btn-sm btn-ghost" id="vp-guia-nueva-v" title="Agrega una guía vertical en el centro"><i class="fa-solid fa-grip-lines-vertical"></i> Guía vertical</button>
            <button type="button" class="btn btn-sm btn-ghost" id="vp-guia-nueva-h" title="Agrega una guía horizontal en el centro"><i class="fa-solid fa-grip-lines"></i> Guía horizontal</button>
        </div>
        <h4 class="vp-cap-tit"><i class="fa-solid fa-shapes"></i> Objetos <small class="text-muted">arriba = al frente</small></h4>
        <ul id="vp-dis-capas" class="vp-capas-lista"></ul>
        <h4 class="vp-cap-tit"><i class="fa-solid fa-ruler-combined"></i> Guías <span class="vp-cap-acc">
            <button type="button" class="vp-ib vp-ib-sm" id="vp-guias-ojo" title="Mostrar u ocultar todas"><i class="fa-solid fa-eye"></i></button>
            <button type="button" class="vp-ib vp-ib-sm vp-ib-peligro" id="vp-guias-borrar" title="Quitar todas las guías"><i class="fa-solid fa-trash-can"></i></button></span></h4>
        <ul id="vp-dis-guias" class="vp-capas-lista"></ul>
        <p class="text-sm text-muted" id="vp-guias-vacio">Sin guías. Arrástralas desde las reglas o agrégalas con los botones de arriba.</p>
    </section>

    <section data-pan="insertar" hidden>
        <h4 class="vp-cap-tit"><i class="fa-solid fa-shapes"></i> Bloques</h4>
        <div class="vp-ins-grid" id="vp-ins-bloques"></div>

        <h4 class="vp-cap-tit"><i class="fa-solid fa-icons"></i> Símbolos</h4>
        <p class="text-sm text-muted">Un clic coloca una copia en el centro de la hoja. Las simbologías las creas con la herramienta de <a href="/simbolos?proyecto=<?php echo s($proyecto->id); ?>" target="_blank" rel="noopener">Simbologías</a> y aparecen aquí.</p>
        <button type="button" class="btn btn-sm btn-ghost" id="vp-sim-todas" hidden title="Inserta todas las simbologías del proyecto"><i class="fa-solid fa-layer-group"></i> Insertar todas las simbologías</button>
        <input type="search" id="vp-sim-buscar" placeholder="Buscar símbolo…" aria-label="Buscar símbolo">
        <div id="vp-sim-estado" class="text-sm text-muted" aria-live="polite"></div>
        <div id="vp-sim-lista"></div>
        <h5 class="vp-ins-grupo">Cajetines</h5>
        <div id="vp-sim-cajetines" class="vp-ins-grid"></div>
        <h5 class="vp-ins-grupo">Estampas inteligentes</h5>
        <p class="text-sm text-muted">Bloques que se llenan solos con datos del proyecto (fecha, avance, actividades del día…).</p>
        <div id="vp-sim-estampas" class="vp-ins-grid"></div>

        <h4 class="vp-cap-tit"><i class="fa-solid fa-table"></i> Tablas adicionales</h4>
        <input type="search" id="vp-ins-buscar" placeholder="Buscar tabla…" aria-label="Buscar tabla">
        <div id="vp-ins-tablas" class="vp-ins-tablas"></div>
        <a href="/vplan/tablas/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-ghost vp-ins-nueva"><i class="fa-solid fa-plus"></i> Crear tabla nueva</a>

        <h4 class="vp-cap-tit"><i class="fa-solid fa-rotate-left"></i> Recovery</h4>
        <p class="text-sm text-muted">Cada recovery es un par de columnas (inicio y fin) en las tablas de tareas de la lámina. Las fechas capturadas se conservan aunque quites la columna.</p>
        <ul id="vp-ins-recovery" class="vp-capas-lista"></ul>
        <div class="vp-dis-fila">
            <button type="button" class="btn btn-sm btn-primary" id="vp-rec-mas"><i class="fa-solid fa-plus"></i> Agregar recovery</button>
            <button type="button" class="btn btn-sm btn-danger" id="vp-rec-menos"><i class="fa-solid fa-minus"></i> Quitar el último</button>
        </div>
    </section>

    <section data-pan="hoja" hidden>
        <div class="campo"><label for="vp-dis-preset">Tamaño de hoja</label>
            <select id="vp-dis-preset">
                <?php foreach (VplanDiseno::PRESETS as $k => $p): ?><option value="<?php echo s($k); ?>"<?php echo $preset === $k ? ' selected' : ''; ?>><?php echo s($p[0]); ?></option><?php endforeach; ?>
                <option value="personalizado"<?php echo $preset === 'personalizado' ? ' selected' : ''; ?>>Personalizada…</option>
            </select></div>
        <div class="campo"><label for="vp-dis-orientacion">Orientación</label>
            <select id="vp-dis-orientacion"><option value="horizontal">Horizontal</option><option value="vertical"<?php echo $vertical ? ' selected' : ''; ?>>Vertical</option></select></div>
        <div id="vp-dis-custom" class="vp-dis-fila" hidden>
            <input type="number" id="vp-dis-ancho" min="10" step="0.1" style="width:8rem" value="<?php echo (int)$lamina->ancho_mm; ?>" aria-label="Ancho">
            ×
            <input type="number" id="vp-dis-alto" min="10" step="0.1" style="width:8rem" value="<?php echo (int)$lamina->alto_mm; ?>" aria-label="Alto">
            <select id="vp-dis-unidad" aria-label="Unidad"><option value="mm">mm</option><option value="cm">cm</option><option value="in">pulgadas</option></select>
        </div>
        <p class="text-sm text-muted">Medidas: <strong id="vp-dis-medidas"><?php echo (int)$lamina->ancho_mm; ?> × <?php echo (int)$lamina->alto_mm; ?> mm</strong></p>
        <form method="POST" action="/vplan/diseno/restablecer" data-confirm="¿Volver al diseño estándar?|Los bloques vuelven a su lugar de siempre (se conservan las partidas del primer bloque de tareas y las tablas). Los bloques de texto y los extras se pierden.|Sí, restablecer">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>"><input type="hidden" name="id" value="<?php echo s($lamina->id); ?>">
            <button type="submit" class="btn btn-sm btn-ghost"><i class="fa-solid fa-rotate-left"></i> Restablecer diseño estándar</button>
        </form>
    </section>
</aside>
