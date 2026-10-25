<?php // Variables: $proyecto, $simbolos, $predeterminados, $puedeEditar, $categorias ?>
<?php require_once __DIR__ . '/../templates/herramientas-proyecto.php'; pestanas_herramienta('editor', '/simbolos', $proyecto); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-shapes"></i> <?php echo s(t('Símbolos')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · biblioteca para insertar en el vPlan y en el Editor</p>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar): ?>
            <button type="button" class="btn btn-primary" id="sim-imp-btn" title="Sube un .zip de biblioteca o varias imágenes"><i class="fa-solid fa-file-import"></i> <?php echo s(t('Importar símbolos')); ?></button>
            <input type="file" id="sim-imp-archivo" accept=".zip,image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg" multiple hidden>
        <?php endif; ?>
        <a href="/simbolos/exportar?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost"><i class="fa-solid fa-file-export"></i> <?php echo s(t('Exportar biblioteca')); ?></a>
        <button type="button" class="btn btn-ghost" data-tour="simbolos" title="Recorrer la biblioteca paso a paso (se muestra solo la primera vez)"><i class="fa-solid fa-circle-play"></i> <?php echo s(t('Guía rápida')); ?></button>
        <a href="/graficos?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← Editor</a>
    </div>
</div>
<p class="text-muted text-sm" id="sim-imp-estado" aria-live="polite"></p>

<?php
$cats = [];
foreach ($simbolos as $x) { $c = $x->categoria !== '' ? $x->categoria : 'Sin categoría'; $cats[$c] = ($cats[$c] ?? 0) + 1; }
ksort($cats);
$catsPre = [];
foreach ($predeterminados as $x) { $catsPre[$x['cat']] = ($catsPre[$x['cat']] ?? 0) + 1; }
ksort($catsPre);
// El tercer valor es la entrada del glosario (includes/funciones.php): la burbuja de cada pestaña define su término
$pestanas = [
    'proyecto' => ['Del proyecto', 'fa-folder-open', count($simbolos), ''],
    'predeterminados' => ['Predeterminados', 'fa-shapes', count($predeterminados), ''],
    'simbologias' => ['Simbologías', 'fa-list-check', count($simbologias), 'simbologia'],
    'cajetines' => ['Cajetines', 'fa-table-cells-large', count($cajetines), 'cajetin'],
    'estampas' => ['Estampas inteligentes', 'fa-wand-magic-sparkles', count($estampas), 'estampa'],
];
?>
<div class="sim-tabs" role="tablist" id="sim-tabs">
    <?php foreach ($pestanas as $k => [$etq, $ico, $n, $gl]): ?>
        <button type="button" role="tab" class="sim-tab" data-tab="<?php echo $k; ?>" aria-controls="sim-p-<?php echo $k; ?>"<?php echo $gl !== '' ? glosario($gl) : ''; ?>><i class="fa-solid <?php echo $ico; ?>"></i> <span><?php echo s(t($etq)); ?></span> <em><?php echo (int)$n; ?></em></button>
    <?php endforeach; ?>
</div>

<section class="sim-panel" id="sim-p-proyecto" role="tabpanel" hidden>
    <div class="sim-barra">
        <div class="pn-busca"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" class="sim-buscar" data-lista="sim-l-proyecto" placeholder="Buscar símbolo…" aria-label="Buscar símbolo"></div>
        <div class="sim-chips" data-lista="sim-l-proyecto"><?php foreach ($cats as $c => $n): ?><button type="button" class="sim-chip" data-cat="<?php echo s($c); ?>"><?php echo s($c); ?> <em><?php echo (int)$n; ?></em></button><?php endforeach; ?></div>
        <?php if ($puedeEditar): ?><?php endif; ?>
    </div>
    <?php if ($puedeEditar): ?>
<details class="sim-nuevo" id="sim-nuevo"><summary class="btn btn-primary"><i class="fa-solid fa-plus"></i> <?php echo s(t('Nuevo símbolo')); ?></summary>
    <div class="sim-nuevo-in">
    <form method="POST" action="/simbolos/guardar" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <div class="form-grid">
            <div class="campo"><label for="sim-nombre">Nombre *</label><input type="text" id="sim-nombre" name="nombre" maxlength="100" placeholder="Ej: Cámara tipo bala" required></div>
            <div class="campo"><label for="sim-cat">Categoría</label><input type="text" id="sim-cat" name="categoria" maxlength="60" list="sim-cats" placeholder="Ej: Seguridad">
                <datalist id="sim-cats"><?php foreach ($categorias as $c): ?><option value="<?php echo s($c); ?>"><?php endforeach; ?></datalist></div>
            <div class="campo"><label for="sim-archivo">Imagen * (png con fondo transparente o svg funcionan mejor)</label><input type="file" id="sim-archivo" name="archivo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg" required></div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Agregar símbolo</button>
    </form>
    </div>
</details>
<?php endif; ?>


    <?php if (!$simbolos): ?>
        <?php echo estado_vacio('fa-shapes', 'Aún no hay símbolos propios', 'Los símbolos se insertan con un clic en el vPlan y en el Editor. Trae los tuyos con «Importar símbolos» (arriba) o usa los incluidos con la aplicación en la pestaña «Predeterminados».', null, null, '', 'estado-vacio-sm'); ?>
    <?php else: ?>
        <div class="sim-admin" id="sim-l-proyecto">
        <?php foreach ($simbolos as $sim): $usos = (int)($usosSimbolos[$sim->id] ?? 0); ?>
            <div class="sim-tarjeta" data-cat="<?php echo s($sim->categoria !== '' ? $sim->categoria : 'Sin categoría'); ?>" data-q="<?php echo s(mb_strtolower($sim->nombre . ' ' . $sim->categoria)); ?>">
                <div class="sim-img"><img src="<?php echo s($sim->url()); ?>" alt="" loading="lazy"></div>
                <div class="sim-nombre"><?php echo s($sim->nombre); ?></div>
                <div class="text-muted text-sm"><?php echo s($sim->categoria !== '' ? $sim->categoria : 'Sin categoría'); ?></div>
                <?php if ($usos): ?><span class="sim-uso" title="Aparece en <?php echo $usos; ?> lámina(s) del proyecto"><i class="fa-solid fa-layer-group"></i> <?php echo $usos; ?></span><?php endif; ?>
                <?php if ($puedeEditar): ?>
                <div class="sim-tarjeta-acc">
                    <button type="button" class="btn btn-sm btn-primary sim-editar" data-id="<?php echo (int)$sim->id; ?>" data-nombre="<?php echo s($sim->nombre); ?>" data-cat="<?php echo s($sim->categoria); ?>"><i class="fa-solid fa-pen"></i> <?php echo s(t('Editar')); ?></button>
                    <form method="POST" action="/simbolos/eliminar" data-confirm="¿Eliminar «<?php echo s($sim->nombre); ?>»?|Las marcas que ya lo usan en tus dibujos no cambian.|Sí, eliminar">
                        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$sim->id; ?>">
                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> <?php echo s(t('Eliminar')); ?></button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="text-muted sim-vacio" hidden>Ningún símbolo coincide con la búsqueda.</p>
</section>

<section class="sim-panel" id="sim-p-simbologias" role="tabpanel" hidden>
    <div id="simbologias"></div>
    <p class="text-muted text-sm">Las miniaturas son la imagen guardada: revísala aquí antes de usarla en una lámina. Leyendas (p. ej. «SYMBOLOGY FOR SCHEDULE») dibujadas con el Editor: formas, líneas, textos e imágenes. Se eligen en cada lámina del vPlan; si editas una, cambia en todas las láminas que la usan.</p>
    <?php if ($puedeEditar): ?>
    <div class="la-controles">
        <a href="/graficos/crear?proyecto=<?php echo (int)$proyecto->id; ?>&tipo=simbologia" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva simbología</a>
        <?php foreach (['rectangular' => ['Plantilla rectangular', 'fa-rectangle-list'], 'cuadrada' => ['Plantilla cuadrada', 'fa-square']] as $forma => [$etq, $ico]): ?>
        <form method="POST" action="/graficos/simbologia-estandar" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="forma" value="<?php echo $forma; ?>">
            <button type="submit" class="btn btn-ghost" title="Parte de las 4 filas actuales (COMPLETE, IN PROGRESS, ON HOLD, CONSTRAIN) y edítalas"><i class="fa-solid <?php echo $ico; ?>"></i> <?php echo $etq; ?> estándar</button></form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!$simbologias): ?>
        <?php echo estado_vacio('fa-list-check', 'Aún no hay simbologías', 'Una simbología es la leyenda del dibujo (COMPLETE, IN PROGRESS, ON HOLD…). Empieza con la plantilla estándar y edítala en el Editor.', null, null,
            $puedeEditar ? '<form method="POST" action="/graficos/simbologia-estandar" class="estado-vacio-acciones"><input type="hidden" name="proyecto" value="' . (int)$proyecto->id . '"><input type="hidden" name="forma" value="rectangular"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Crear la estándar</button><a class="btn btn-ghost" href="/simbolos?proyecto=' . (int)$proyecto->id . '&tour=1"><i class="fa-solid fa-circle-play"></i> Ver la guía</a></form>' : '', 'estado-vacio-sm'); ?>
    <?php else: ?>
        <div class="sim-admin">
        <?php foreach ($simbologias as $g): $img = $g->imagenSimbologia(); ?>
            <div class="sim-tarjeta">
                <div class="sim-img"><?php if ($img): ?><img src="/uploads/vplan/<?php echo s($img); ?>" alt="" loading="lazy"><?php else: ?><span class="text-sm" style="color:#475569;padding:.8rem;text-align:center">Sin imagen: ábrela en el Editor y guárdala</span><?php endif; ?></div>
                <div class="sim-nombre"><?php echo s($g->nombre); ?></div>
                <div class="text-muted text-sm"><?php echo (int)$g->ancho; ?> × <?php echo (int)$g->alto; ?> px</div>
                <?php if ($puedeEditar): ?>
                <a href="/graficos/abrir?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$g->id; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen-ruler"></i> Editar</a>
                <form method="POST" action="/graficos/duplicar" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>"><button type="submit" class="btn btn-sm btn-ghost"><i class="fa-regular fa-copy"></i> <?php echo s(t('Duplicar')); ?></button></form>
                <form method="POST" action="/graficos/tipo" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>"><input type="hidden" name="tipo" value="grafico"><button type="submit" class="btn btn-sm btn-ghost" title="Devolverla a la lista de gráficos del Editor">Pasar a gráfico</button></form>
                <form method="POST" action="/graficos/eliminar" data-confirm="¿Eliminar «<?php echo s($g->nombre); ?>»?|Las láminas del vPlan que la usan volverán a la tabla estándar.|Sí, eliminar">
                    <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> <?php echo s(t('Eliminar')); ?></button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="sim-panel" id="sim-p-cajetines" role="tabpanel" hidden>
    <div id="cajetines"></div>
    <p class="text-muted text-sm">El cajetín es la tablita con los datos del dibujo (proyecto, título, fecha, revisión…) que se inserta con un clic en el vPlan y en el Editor. Los datos se llenan solos, así que no hay que escribirlos en cada lámina. Ya existe uno «Estándar» para todos los proyectos. ¿Quieres algo con más libertad (semáforos, post-its, varios estilos)? Usa las <a href="#estampas" data-ir-tab="estampas">Estampas inteligentes</a>.</p>
    <?php if ($puedeEditar): ?>
    <details class="sim-nuevo"><summary class="btn btn-primary"<?php echo glosario('cajetin'); ?>><i class="fa-solid fa-plus"></i> Nuevo cajetín</summary><div class="sim-nuevo-in">
    <form method="POST" action="/simbolos/cajetin" class="formulario cj-form">
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <div class="form-grid">
            <div class="campo"><label>Nombre *</label><input type="text" name="nombre" maxlength="100" placeholder="Ej: Plano de instalaciones" required></div>
            <div class="campo"><label>Forma</label><select name="orientacion"><option value="vertical">Tabla de dos columnas (esquina inferior derecha)</option><option value="banda">Banda inferior (una columna por dato)</option></select></div>
            <div class="campo"><label>Ancho (% del dibujo)</label><input type="number" name="ancho" min="15" max="100" value="28"></div>
            <div class="campo campo-full"><label>Filas *</label><textarea name="filas" rows="5" required placeholder="Proyecto | {proyecto}&#10;Título | {titulo}&#10;Fecha | {fecha}&#10;Revisó |"></textarea></div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Agregar cajetín</button>
    </form>
    </div></details>
    <?php endif; ?>
    <?php foreach ($cajetines as $c): ?>
        <details style="margin-top:1.2rem">
            <summary><strong><?php echo s($c->nombre); ?></strong> <span class="text-muted text-sm">· <?php echo $c->orientacion === 'banda' ? 'banda inferior' : 'tabla'; ?> · <?php echo (int)$c->ancho; ?> % · <?php echo count($c->filas()); ?> fila(s)</span></summary>
            <?php if ($puedeEditar): ?>
            <form method="POST" action="/simbolos/cajetin" class="formulario cj-form" style="margin-top:.8rem">
                <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$c->id; ?>">
                <div class="form-grid">
                    <div class="campo"><label><?php echo s(t('Nombre')); ?></label><input type="text" name="nombre" maxlength="100" value="<?php echo s($c->nombre); ?>" required></div>
                    <div class="campo"><label>Forma</label><select name="orientacion"><option value="vertical"<?php echo $c->orientacion === 'vertical' ? ' selected' : ''; ?>>Tabla de dos columnas</option><option value="banda"<?php echo $c->orientacion === 'banda' ? ' selected' : ''; ?>>Banda inferior</option></select></div>
                    <div class="campo"><label>Ancho (%)</label><input type="number" name="ancho" min="15" max="100" value="<?php echo (int)$c->ancho; ?>"></div>
                    <div class="campo campo-full"><label>Filas</label><textarea name="filas" rows="5" required><?php echo s($c->textoFilas()); ?></textarea></div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary"><?php echo s(t('Guardar')); ?></button>
            </form>
            <form method="POST" action="/simbolos/cajetin/eliminar" style="margin-top:.6rem" data-confirm="¿Eliminar el cajetín «<?php echo s($c->nombre); ?>»?|Los dibujos que ya lo usan no cambian.|Sí, eliminar">
                <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$c->id; ?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> <?php echo s(t('Eliminar')); ?></button>
            </form>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>

<section class="sim-panel" id="sim-p-predeterminados" role="tabpanel" hidden>
    <p class="text-muted text-sm">Incluidos con la aplicación para todos los proyectos.</p>
    <div class="sim-barra">
        <div class="pn-busca"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" class="sim-buscar" data-lista="sim-l-pre" placeholder="Buscar símbolo…" aria-label="Buscar símbolo"></div>
        <div class="sim-chips" data-lista="sim-l-pre"><?php foreach ($catsPre as $c => $n): ?><button type="button" class="sim-chip" data-cat="<?php echo s($c); ?>"><?php echo s($c); ?> <em><?php echo (int)$n; ?></em></button><?php endforeach; ?></div>
        
    </div>
    <div class="sim-admin" id="sim-l-pre">
        <?php foreach ($predeterminados as $sim): ?>
            <div class="sim-tarjeta" data-cat="<?php echo s($sim['cat']); ?>" data-q="<?php echo s(mb_strtolower($sim['nombre'] . ' ' . $sim['cat'])); ?>">
                <div class="sim-img"><img src="<?php echo s($sim['src']); ?>" alt="" loading="lazy"></div>
                <div class="sim-nombre"><?php echo s($sim['nombre']); ?></div>
                <div class="text-muted sim-cat"><?php echo s($sim['cat']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="text-muted sim-vacio" hidden>Ningún símbolo coincide con la búsqueda.</p>
</section>
<?php // Láminas donde se puede probar una estampa (botón «Probar en una lámina» de cada tarjeta) ?>
<?php $laminasProbar = $puedeEditar ? \Model\VplanLamina::deProyecto((int)$proyecto->id) : []; ?>
<section class="sim-panel" id="sim-p-estampas" role="tabpanel" hidden>
    <div id="estampas"></div>
    <p class="text-muted text-sm">Una <strong>estampa</strong> es un bloque que se coloca en cualquier lámina del vPlan y <strong>se actualiza solo</strong>: está hecha de etiquetas, y cada etiqueta combina un texto antes (prefijo), un dato del proyecto que se llena solo (fecha, avance, actividad, restricciones…) y un texto después (sufijo). También puede traer semáforos y los <strong>post-its de las actividades del día</strong> del Last Planner.</p>
    <?php if ($puedeEditar): ?>
    <div class="est-barra">
        <button type="button" class="btn btn-primary" data-est="nueva"<?php echo glosario('estampa'); ?>><i class="fa-solid fa-plus"></i> Nueva estampa (lienzo)</button>
        <button type="button" class="btn btn-ghost" data-est="nueva-et" title="Lista de etiquetas, sin dibujo"><i class="fa-solid fa-list"></i> Nueva por etiquetas</button>
        <span class="text-sm text-muted">o empieza con un ejemplo:</span>
        <?php // Galería de plantillas con la estampa ya dibujada (con datos de ejemplo del proyecto) ?>
        <?php $ctxPlant = new \Classes\EstampaContexto($proyecto, null, ['lamina_n' => 1, 'lamina_total' => 1]); ?>
        <div class="est-plantillas">
        <?php foreach ($plantillasEstampa as $clave => $pl): $cfgP = \Classes\Estampas::limpiarConfig($pl['config'], (int)$proyecto->id);
            $W = round((float)($cfgP['w'] ?? 26) * 16); $H = round((float)($cfgP['h'] ?? 16) * 10.33); $zoom = min(.55, 340 / max(1, $W)); ?>
            <button type="button" class="est-plantilla" data-est="plantilla" data-clave="<?php echo s($clave); ?>" title="Copia esta plantilla al proyecto para editarla">
                <span class="est-miniatura"><span style="width:<?php echo $W; ?>px;height:<?php echo $H; ?>px;zoom:<?php echo round($zoom, 3); ?>"><?php echo \Classes\Estampas::render($cfgP, $ctxPlant, \Classes\Estampas::unidad('px', 1.3)); ?></span></span>
                <span class="est-plantilla-pie"><b><i class="fa-solid <?php echo str_starts_with($clave, 'lastplanner') || str_starts_with($clave, 'postit') ? 'fa-note-sticky' : 'fa-stamp'; ?>"></i> <?php echo s($pl['nombre']); ?></b>
                <small>copiar y editar</small></span>
            </button>
        <?php endforeach; ?>
        </div>
    </div>
    <div id="est-editor" class="est-editor card" hidden></div>
    <?php endif; ?>
    <?php if (!$estampas): ?>
        <?php echo estado_vacio('fa-wand-magic-sparkles', 'Aún no hay estampas en este proyecto', 'Una estampa es un bloque que se llena solo con datos del proyecto (fecha, avance, actividades del día…) y se coloca en cualquier lámina del vPlan. Empieza con uno de los ejemplos de abajo.', null, null,
            $puedeEditar ? '<div class="estado-vacio-acciones"><button type="button" class="btn btn-primary" data-est="nueva"><i class="fa-solid fa-plus"></i> Nueva estampa</button><a class="btn btn-ghost" href="/simbolos?proyecto=' . (int)$proyecto->id . '&tour=1"><i class="fa-solid fa-circle-play"></i> Ver la guía</a></div>' : '', 'estado-vacio-sm'); ?>
    <?php else:
        $ctxEst = new \Classes\EstampaContexto($proyecto, null, ['lamina_n' => 1, 'lamina_total' => 1]); ?>
        <div class="est-lista">
        <?php foreach ($estampas as $es): $c = $es->config(); $W = round((float)($c['w'] ?? 26) * 16); $H = round((float)($c['h'] ?? 16) * 10.33); $zoom = min(0.5, 380 / max(1, $W)); ?>
            <article class="est-card">
                <div class="est-miniatura"><div style="width:<?php echo $W; ?>px;height:<?php echo $H; ?>px;zoom:<?php echo round($zoom, 3); ?>"><?php echo \Classes\Estampas::renderGuardada($es, $proyecto, null, ['lamina_n' => 1, 'lamina_total' => 1], 'px', 1.3); ?></div></div>
                <div class="est-card-pie">
                    <strong><?php echo s($es->nombre); ?></strong>
                    <span class="text-sm text-muted"><?php echo count($c['etiquetas'] ?? []); ?> etiqueta(s) · <?php echo s(['lineas' => 'en líneas', 'tabla' => 'en tabla', 'banda' => 'en banda'][$c['disposicion'] ?? 'lineas'] ?? ''); ?></span>
                    <?php if ($puedeEditar): ?>
                    <div class="est-card-acc"><button type="button" class="btn btn-sm btn-primary" data-est="editar" data-id="<?php echo (int)$es->id; ?>"><i class="fa-solid fa-pen"></i> Editar</button>
                        <?php if (!empty($laminasProbar)): ?><button type="button" class="btn btn-sm btn-ghost est-probar" data-id="<?php echo (int)$es->id; ?>" data-nombre="<?php echo s($es->nombre); ?>" title="La inserta en una lámina que elijas, lista para colocar"><i class="fa-solid fa-sheet-plastic"></i> Probar en una lámina</button><?php endif; ?>
                        <button type="button" class="btn btn-sm btn-ghost" data-est="duplicar" data-id="<?php echo (int)$es->id; ?>"><i class="fa-regular fa-copy"></i> Duplicar</button>
                        <button type="button" class="btn btn-sm btn-danger" data-est="eliminar" data-id="<?php echo (int)$es->id; ?>" data-nombre="<?php echo s($es->nombre); ?>"><i class="fa-solid fa-trash-can"></i></button></div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php if (!empty($laminasProbar)): ?>
<!-- Modal «Probar en una lámina»: inserta la estampa en la lámina elegida (modo diseño) -->
<div class="modal-overlay" id="est-probar-modal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="est-probar-titulo" style="max-width:44rem">
        <div class="bj-cab"><h3 id="est-probar-titulo">Probar en una lámina</h3><button type="button" class="btn btn-sm btn-ghost" id="est-probar-x" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>
        <p class="text-sm text-muted" style="margin:0 1.6rem .6rem">Se abre la lámina en «Acomodar bloques» con <b id="est-probar-nombre"></b> ya insertada: colócala donde quieras y guarda.</p>
        <ul class="est-probar-lista" id="est-probar-lista"></ul>
    </div>
</div>
<script>
(function () {
    const m = document.getElementById('est-probar-modal'), lista = document.getElementById('est-probar-lista');
    if (!m || !lista) return;
    const laminas = <?php echo json_encode(array_map(fn($l) => ['id' => (int)$l->id, 'titulo' => $l->titulo], $laminasProbar), JSON_UNESCAPED_UNICODE); ?>;
    let id = 0;
    const cerrar = () => { m.classList.remove('abierto'); m.setAttribute('aria-hidden', 'true'); };
    document.addEventListener('click', e => {
        const b = e.target.closest('.est-probar');
        if (!b) return;
        e.preventDefault();
        id = +b.dataset.id;
        document.getElementById('est-probar-nombre').textContent = b.dataset.nombre || '';
        lista.innerHTML = laminas.map(l => '<li><a href="/vplan/ver?proyecto=<?php echo (int)$proyecto->id; ?>&id=' + l.id + '&diseno=1&insertar=estampa:' + id + '"><i class="fa-solid fa-sheet-plastic"></i> ' + l.titulo + ' <i class="fa-solid fa-arrow-right"></i></a></li>').join('');
        m.classList.add('abierto'); m.setAttribute('aria-hidden', 'false');
    });
    document.getElementById('est-probar-x').addEventListener('click', cerrar);
    m.addEventListener('click', e => { if (e.target === m) cerrar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && m.classList.contains('abierto')) cerrar(); });
})();
</script>
<?php endif; ?>
<?php if ($puedeEditar): ?>
<!-- Modal de edición de símbolo (la tarjeta ya no despliega un formulario: se edita aquí) -->
<div class="modal-overlay" id="sim-modal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="sim-modal-titulo" style="max-width:46rem">
        <div class="bj-cab"><h3 id="sim-modal-titulo">Editar símbolo</h3><button type="button" class="btn btn-sm btn-ghost" id="sim-modal-x" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST" action="/simbolos/guardar" enctype="multipart/form-data" class="formulario" id="sim-modal-form">
            <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
            <input type="hidden" name="id" id="sim-modal-id" value="">
            <div class="form-grid">
                <div class="campo"><label for="sim-modal-nombre">Nombre *</label><input type="text" id="sim-modal-nombre" name="nombre" maxlength="100" required></div>
                <div class="campo"><label for="sim-modal-cat">Categoría</label><input type="text" id="sim-modal-cat" name="categoria" maxlength="60" list="sim-cats"></div>
                <div class="campo campo-full"><label for="sim-modal-archivo">Cambiar imagen (png con fondo transparente o svg funcionan mejor)</label><input type="file" id="sim-modal-archivo" name="archivo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg"></div>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-ghost" id="sim-modal-cancelar"><i class="fa-solid fa-xmark"></i> <?php echo s(t('Cancelar')); ?></button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> <?php echo s(t('Guardar')); ?></button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const m = document.getElementById('sim-modal');
    if (!m) return;
    const abrir = b => {
        document.getElementById('sim-modal-id').value = b.dataset.id;
        document.getElementById('sim-modal-nombre').value = b.dataset.nombre || '';
        document.getElementById('sim-modal-cat').value = b.dataset.cat || '';
        document.getElementById('sim-modal-archivo').value = '';
        m.classList.add('abierto'); m.setAttribute('aria-hidden', 'false');
        document.getElementById('sim-modal-nombre').focus();
    };
    const cerrar = () => { m.classList.remove('abierto'); m.setAttribute('aria-hidden', 'true'); };
    document.addEventListener('click', e => {
        const b = e.target.closest('.sim-editar');
        if (b) { e.preventDefault(); abrir(b); }
    });
    document.getElementById('sim-modal-x').addEventListener('click', cerrar);
    document.getElementById('sim-modal-cancelar').addEventListener('click', cerrar);
    m.addEventListener('click', e => { if (e.target === m) cerrar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && m.classList.contains('abierto')) cerrar(); });
})();
</script>
<?php endif; ?>
<?php
$ctxDatos = new \Classes\EstampaContexto($proyecto);
$partidasEst = [];
foreach ($proyecto->getPartidas() as $pt) $partidasEst[] = ['id' => (int)$pt->id, 'codigo' => (string)$pt->codigo, 'nombre' => (string)$pt->nombre];
$actsEst = [];
foreach ($ctxDatos->actividades() as $ida => $ar) $actsEst[] = ['id' => $ida, 'codigo' => (string)$ar['codigo'], 'nombre' => (string)$ar['nombre']];
$personalEst = array_map(fn($x) => ['id' => (int)$x->id, 'nombre' => (string)$x->nombre], \Model\Personal::consultarSQL("SELECT * FROM personal WHERE proyecto_id = " . (int)$proyecto->id . " ORDER BY nombre"));
$camposEst = [];
foreach (\Classes\Estampas::CAMPOS as $kc => [$gc, $ec, $tc, $xc]) $camposEst[] = [$kc, $gc, $ec, $tc, $xc];
$fechasEst = [];
foreach (\Classes\Estampas::FORMATOS_FECHA as $kf => $ef) $fechasEst[] = [$kf, $ef];
?>
<script>
window.EST_DATA = <?php echo json_encode(['proyecto' => (int)$proyecto->id, 'grupos' => \Classes\Estampas::GRUPOS, 'campos' => $camposEst, 'semaforo' => \Classes\Estampas::SEMAFORO, 'camposPostit' => \Classes\Estampas::CAMPOS_POSTIT,
    'formatosFecha' => $fechasEst, 'partidas' => $partidasEst, 'actividades' => $actsEst, 'personal' => $personalEst, 'estampas' => array_map(fn($x) => $x->aArray(), $estampas)], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>
<script src="/vendor/kit-edicion.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/kit-edicion.js'); ?>"></script>
<script src="/vendor/estampas-editor.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/estampas-editor.js'); ?>"></script>
<script>
(function () {
    const b = document.getElementById('sim-imp-btn'), f = document.getElementById('sim-imp-archivo'), est = document.getElementById('sim-imp-estado');
    if (!b) return;
    b.addEventListener('click', () => f.click());
    f.addEventListener('change', async () => {
        if (!f.files.length) return;
        const fd = new FormData(); fd.append('proyecto', <?php echo (int)$proyecto->id; ?>); [...f.files].forEach(a => fd.append('archivos[]', a));
        est.textContent = 'Importando…'; b.disabled = true;
        try {
            const d = await (await fetch('/api/simbolos/importar', { method: 'POST', body: fd })).json();
            if (!d.success) { est.textContent = 'Error: ' + (d.error || 'no se pudo importar'); b.disabled = false; return; }
            location.href = '/simbolos?proyecto=<?php echo (int)$proyecto->id; ?>&ok=1';
        } catch (e) { est.textContent = 'Error al importar'; b.disabled = false; }
        f.value = '';
    });
})();
</script>
<script>
(function () {
    const tabs = [...document.querySelectorAll('.sim-tab')], paneles = [...document.querySelectorAll('.sim-panel')];
    const validas = tabs.map(t => t.dataset.tab);
    let guardada = ''; try { guardada = localStorage.getItem('sim-tab') || ''; } catch (e) {}
    function ir(k, guardar) {
        if (!validas.includes(k)) k = validas[0];
        tabs.forEach(t => { const on = t.dataset.tab === k; t.classList.toggle('on', on); t.setAttribute('aria-selected', on); });
        paneles.forEach(p => { p.hidden = p.id !== 'sim-p-' + k; });
        if (guardar) { try { localStorage.setItem('sim-tab', k); } catch (e) {} }
    }
    tabs.forEach(t => t.addEventListener('click', () => ir(t.dataset.tab, true)));
    document.addEventListener('click', e => { const l = e.target.closest('[data-ir-tab]'); if (l) { e.preventDefault(); ir(l.dataset.irTab, true); window.scrollTo(0, 0); } });
    const h = location.hash.replace('#', '');
    ir(validas.includes(h) ? h : guardada, false);
    // Filtro por texto y categoría de cada lista de símbolos
    document.querySelectorAll('.sim-barra').forEach(barra => {
        const buscar = barra.querySelector('.sim-buscar'); if (!buscar) return;
        const lista = document.getElementById(buscar.dataset.lista); if (!lista) return;   // la lista puede no existir (p. ej. el proyecto no tiene símbolos propios)
        const chips = [...barra.querySelectorAll('.sim-chip')];
        const vacio = lista.parentElement.querySelector('.sim-vacio'); let cat = '';
        const norm = x => x.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        function filtrar() {
            const q = norm(buscar.value.trim()).split(/\s+/).filter(Boolean); let n = 0;
            lista.querySelectorAll('.sim-tarjeta').forEach(c => {
                const ok = (!cat || c.dataset.cat === cat) && q.every(w => norm(c.dataset.q).includes(w));
                c.hidden = !ok; if (ok) n++;
            });
            if (vacio) vacio.hidden = n > 0;
        }
        buscar.addEventListener('input', filtrar);
        chips.forEach(c => c.addEventListener('click', () => { cat = cat === c.dataset.cat ? '' : c.dataset.cat; chips.forEach(x => x.classList.toggle('on', x.dataset.cat === cat)); filtrar(); }));
    });
})();
</script>
<script src="/vendor/tour.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/tour.js'); ?>"></script>
<script>
// Guía rápida de la biblioteca (Símbolos): las pestañas se abren con alEntrar para destacarlas
window.Tour.registrar('simbolos', {
    auto: true,
    pasos: [
        { icono: 'fa-shapes', titulo: 'La biblioteca del proyecto', texto: 'Aquí viven los símbolos que se insertan en el vPlan y en el Editor con un clic, y sus dos primos: los <b>cajetines</b> (la tablita de datos del dibujo) y las <b>estampas inteligentes</b> (bloques que se llenan solos).' },
        { sel: '#sim-tabs', icono: 'fa-table-columns', titulo: 'Cinco pestañas', texto: 'Los símbolos del proyecto y los incluidos con la aplicación, las simbologías (leyendas), los cajetines y las estampas. Cada una tiene su contador.' },
        { sel: '#sim-imp-btn', icono: 'fa-file-import', titulo: 'Importar en bloque', texto: 'Sube un .zip con varias imágenes (o varias sueltas) y quedan en la pestaña «Del proyecto», con categorías y buscador.' },
        { sel: '#sim-p-predeterminados', icono: 'fa-shapes', titulo: 'Predeterminados', texto: 'Los símbolos que ya vienen con la aplicación: cámaras, extintores, señalética… Se insertan igual que los tuyos.', alEntrar: () => document.querySelector('.sim-tab[data-tab="predeterminados"]')?.click() },
        { sel: '#sim-p-estampas', icono: 'fa-wand-magic-sparkles', titulo: 'Estampas inteligentes', texto: 'Se colocan en una lámina del vPlan y se actualizan solas: fecha, avance, restricciones, las actividades del día… Empieza con una de las plantillas.', alEntrar: () => document.querySelector('.sim-tab[data-tab="estampas"]')?.click() },
        { icono: 'fa-circle-check', titulo: 'Listo', texto: 'Los símbolos se usan desde el panel «Insertar» del modo diseño del vPlan y desde la pestaña de símbolos del Editor. Edita cualquier tarjeta con su botón «Editar».' }
    ]
});
</script>
