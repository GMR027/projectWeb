<?php
// Panel lateral del modo diseño: Propiedades · Capas · Insertar · Hoja. Variables: $proyecto, $lamina
use Classes\VplanDiseno;
$preset = $lamina->preset;
$vertical = $lamina->alto_mm > $lamina->ancho_mm;
$corto = fn(string $n): string => preg_split('/\s+(?=[0-9])/', $n)[0];   // «Tabloide 11×17"» → «Tabloide»
$pestanas = [['props', 'fa-sliders', 'Propiedades'], ['capas', 'fa-layer-group', 'Capas'], ['insertar', 'fa-plus', 'Insertar'], ['hoja', 'fa-file-lines', 'Hoja']];
$categorias = [['bloques', 'Bloques'], ['simbolos', 'Símbolos'], ['cajetines', 'Cajetines'], ['estampas', 'Estampas'], ['tablas', 'Tablas'], ['todo', 'Todo']];
/** Proporción (ancho/alto) de un preset, según la orientación elegida */
$prop = fn(array $p, bool $vert): float => $vert ? round($p[2] / max(1, $p[1]), 4) : round($p[1] / max(1, $p[2]), 4);
?>
<button type="button" class="pn-abrir no-print" id="vp-dis-panel-abrir" hidden data-etq="Panel" title="Mostrar el panel de diseño"><i class="fa-solid fa-sliders"></i></button>
<aside class="vp-dis-panel no-print" id="vp-dis-panel" aria-label="Herramientas del diseño">
    <div class="pn-cab-bloque">
    <header class="pn-cab">
        <span class="pn-cab-ico"><i class="fa-solid fa-pen-ruler"></i></span>
        <div class="pn-cab-txt"><b>Diseño</b><small><?php echo s($lamina->titulo); ?></small></div>
        <span class="pn-cab-acc"><button type="button" class="vp-ib vp-ib-sm" id="vp-dis-panel-tgl" title="Ocultar el panel para ver más hoja"><i class="fa-solid fa-angles-right"></i></button></span>
    </header>

    <div class="pn-tabs" role="tablist" aria-label="Secciones del panel">
        <?php foreach ($pestanas as [$k, $ic, $t]): ?>
            <button type="button" role="tab" data-ptab="<?php echo s($k); ?>" aria-selected="<?php echo $k === 'props' ? 'true' : 'false'; ?>"<?php echo $k === 'props' ? ' class="activo"' : ''; ?>>
                <i class="fa-solid <?php echo s($ic); ?>"></i><span><?php echo s($t); ?></span><?php if ($k === 'capas'): ?><span class="pn-cont" id="vp-cont-capas"></span><?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>
    </div>

    <section data-pan="props">
        <div class="pn-vacio" id="vp-dis-vacio"><i class="fa-regular fa-hand-pointer"></i><b>Nada seleccionado</b><p class="text-muted">Haz clic en un bloque de la hoja, o en una capa, para ver y cambiar sus propiedades.</p></div>
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
        <div class="vp-ins-top">
            <div class="pn-busca">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="vp-ins-todo" placeholder="Buscar en todo  ( / )" aria-label="Buscar en Insertar">
            </div>
            <div class="pn-chips" role="group" aria-label="Filtrar por categoría">
                <?php foreach ($categorias as [$k, $t]): ?>
                    <button type="button" class="pn-chip<?php echo $k === 'bloques' ? ' activo' : ''; ?>" data-cat="<?php echo s($k); ?>" aria-pressed="<?php echo $k === 'bloques' ? 'true' : 'false'; ?>"><?php echo s($t); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="vp-ins-nada text-sm text-muted" id="vp-ins-nada" hidden><i class="fa-solid fa-magnifying-glass"></i> Nada coincide con la búsqueda.</p>

        <section class="pn-secc" data-ins="recientes" hidden>
            <h4 class="pn-tit"><i class="fa-solid fa-clock-rotate-left"></i> Recientes</h4>
            <div class="pn-grid" id="vp-ins-recientes"></div>
        </section>

        <section class="pn-secc" data-ins="bloques">
            <h4 class="pn-tit"><i class="fa-solid fa-shapes"></i> Bloques <small class="text-muted">clic = centro de la hoja · arrastra = donde lo sueltes</small></h4>
            <div class="pn-grid" id="vp-ins-bloques"></div>
        </section>

        <section class="pn-secc" data-ins="simbolos">
            <h4 class="pn-tit"><i class="fa-solid fa-icons"></i> Símbolos</h4>
            <p class="text-sm text-muted">Un clic (o arrastrar) coloca una copia en la hoja. Las simbologías las creas con la herramienta de <a href="/simbolos?proyecto=<?php echo s($proyecto->id); ?>" target="_blank" rel="noopener">Simbologías</a> y aparecen aquí.</p>
            <button type="button" class="btn btn-sm btn-ghost" id="vp-sim-todas" hidden title="Inserta todas las simbologías del proyecto"><i class="fa-solid fa-layer-group"></i> Insertar todas las simbologías</button>
            <div id="vp-sim-estado" class="text-sm text-muted" aria-live="polite"></div>
            <div id="vp-sim-lista"></div>
        </section>

        <section class="pn-secc" data-ins="cajetines">
            <h4 class="pn-tit"><i class="fa-solid fa-table-list"></i> Cajetines</h4>
            <div id="vp-sim-cajetines" class="pn-grid"></div>
        </section>

        <section class="pn-secc" data-ins="estampas">
            <h4 class="pn-tit"><i class="fa-solid fa-wand-magic-sparkles"></i> Estampas inteligentes</h4>
            <p class="text-sm text-muted">Bloques que se llenan solos con datos del proyecto (fecha, avance, actividades del día…).</p>
            <div id="vp-sim-estampas" class="pn-grid"></div>
        </section>

        <section class="pn-secc" data-ins="tablas">
            <h4 class="pn-tit"><i class="fa-solid fa-table"></i> Tablas adicionales</h4>
            <p class="text-sm text-muted">Tablas del proyecto creadas en vPlan. Las que están <strong>enlazadas a una hoja de SheetFlow</strong> se actualizan solas.</p>
            <a href="/vplan/tablas/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-ghost vp-ins-nueva"><i class="fa-solid fa-plus"></i> Crear tabla nueva</a>
            <div id="vp-ins-tablas" class="vp-ins-tablas"></div>
        </section>

        <section class="pn-secc" data-ins="tablas-sf">
            <h4 class="pn-tit"><i class="fa-solid fa-table-cells"></i> Tablas SheetFlow</h4>
            <p class="text-sm text-muted">Hojas de <a href="/sheetflow?proyecto=<?php echo s($proyecto->id); ?>" target="_blank" rel="noopener">SheetFlow</a> que todavía no están en el vPlan. Al insertarlas se crea la tabla y quedan <strong>enlazadas</strong>: sus datos se leen en vivo y se actualizan solos.</p>
            <div id="vp-ins-tablas-sf" class="vp-ins-tablas"></div>
        </section>
    </section>

    <section data-pan="hoja" hidden>
        <h4 class="vp-prop-tit"><i class="fa-solid fa-expand"></i> Tamaño de hoja</h4>
        <div class="vp-hoj-presets" id="vp-dis-presets" role="group" aria-label="Tamaño de hoja">
            <?php foreach (VplanDiseno::PRESETS as $k => $p): ?>
                <button type="button" class="vp-hoj-preset<?php echo $preset === $k ? ' activo' : ''; ?>" data-preset="<?php echo s($k); ?>" data-w="<?php echo (int)$p[1]; ?>" data-h="<?php echo (int)$p[2]; ?>" aria-pressed="<?php echo $preset === $k ? 'true' : 'false'; ?>">
                    <span class="vp-hoj-fig"><i style="--ar:<?php echo $prop($p, $vertical); ?>"></i></span>
                    <b><?php echo s($corto($p[0])); ?></b>
                    <small><?php echo $vertical ? (int)$p[2] : (int)$p[1]; ?> × <?php echo $vertical ? (int)$p[1] : (int)$p[2]; ?> mm</small>
                </button>
            <?php endforeach; ?>
            <button type="button" class="vp-hoj-preset<?php echo $preset === 'personalizado' ? ' activo' : ''; ?>" data-preset="personalizado" aria-pressed="<?php echo $preset === 'personalizado' ? 'true' : 'false'; ?>">
                <span class="vp-hoj-fig"><i class="vp-hoj-fig-libre"><span class="fa-solid fa-pen-ruler"></span></i></span>
                <b>Personalizada</b>
                <small>tú eliges</small>
            </button>
        </div>
        <select id="vp-dis-preset" hidden>
            <?php foreach (VplanDiseno::PRESETS as $k => $p): ?><option value="<?php echo s($k); ?>"<?php echo $preset === $k ? ' selected' : ''; ?>><?php echo s($p[0]); ?></option><?php endforeach; ?>
            <option value="personalizado"<?php echo $preset === 'personalizado' ? ' selected' : ''; ?>>Personalizada…</option>
        </select>

        <h4 class="vp-prop-tit"><i class="fa-solid fa-arrows-left-right"></i> Orientación</h4>
        <div class="vp-hoj-ori" role="group" aria-label="Orientación">
            <button type="button" class="vp-hoj-orib<?php echo $vertical ? '' : ' activo'; ?>" data-ori="horizontal" aria-pressed="<?php echo $vertical ? 'false' : 'true'; ?>"><i class="fa-solid fa-arrows-left-right"></i> Horizontal</button>
            <button type="button" class="vp-hoj-orib<?php echo $vertical ? ' activo' : ''; ?>" data-ori="vertical" aria-pressed="<?php echo $vertical ? 'true' : 'false'; ?>"><i class="fa-solid fa-arrows-up-down"></i> Vertical</button>
        </div>
        <select id="vp-dis-orientacion" hidden><option value="horizontal">Horizontal</option><option value="vertical"<?php echo $vertical ? ' selected' : ''; ?>>Vertical</option></select>

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

        <div class="vp-prop-sec">
            <h4 class="vp-cap-tit"><i class="fa-solid fa-rotate-left"></i> Recovery <small class="text-muted">columnas de las tablas de tareas</small></h4>
            <p class="text-sm text-muted">Cada recovery es un par de columnas (inicio y fin) en las tablas de tareas de la lámina. Las fechas capturadas se conservan aunque quites la columna.</p>
            <ul id="vp-ins-recovery" class="vp-capas-lista"></ul>
            <div class="vp-dis-fila">
                <button type="button" class="btn btn-sm btn-primary" id="vp-rec-mas"><i class="fa-solid fa-plus"></i> Agregar recovery</button>
                <button type="button" class="btn btn-sm btn-danger" id="vp-rec-menos"><i class="fa-solid fa-minus"></i> Quitar el último</button>
            </div>
        </div>
    </section>
</aside>
