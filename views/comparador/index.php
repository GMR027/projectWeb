<?php // Variables: $proyecto, $fuentes (Archivo, vPlan, Editor y Redactor), $guardados, $abrir, $puedeGuardar, $aviso, $publico, $token, $historial ?>
<?php
$pid = (int)$proyecto->id;
$publico = !empty($publico);
$fecha = fn($f) => $f ? date('d/m/Y H:i', strtotime($f)) : '—';
?>
<div class="page-header cmp-cab">
    <div>
        <h2><i class="fa-solid fa-code-compare"></i> <?php echo s($publico ? 'Comparación de documentos' : t('Comparador de documentos')); ?></h2>
        <?php if (!$publico): ?>
            <p class="text-muted"><?php echo s($proyecto->nombre); ?> · compara dos documentos: PDF o imágenes del Archivo, láminas de vPlan y del Editor, y documentos del Redactor (en PDF), además de archivos externos. Anota, mide, revisa diferencias y guarda o comparte la comparación.</p>
        <?php else: ?>
            <p class="text-muted"><?php echo s($proyecto->nombre); ?> · vista de solo lectura de «<?php echo s($abrir['nombre'] ?? 'comparación'); ?>». Las anotaciones se ven, pero no se pueden modificar.</p>
        <?php endif; ?>
    </div>
    <?php if ($publico): ?><span class="cmp-badge-solo"><i class="fa-solid fa-eye"></i> Solo lectura</span><?php endif; ?>
</div>
<?php if (!empty($aviso)): ?><div class="alerta alerta-exito" role="status"><?php echo s($aviso); ?></div><?php endif; ?>

<?php if ($puedeGuardar): ?>
<details class="card cmp-guardados" <?php echo (!$abrir && count($guardados) <= 3) ? 'open' : ''; ?>>
    <summary class="cmp-guardados-cab">
        <h3><i class="fa-solid fa-floppy-disk"></i> Comparaciones guardadas <span class="cmp-cuenta"><?php echo count($guardados); ?></span></h3>
        <small class="text-muted">Quien las creó o el administrador pueden modificarlas</small>
    </summary>
    <?php if (!$guardados): ?>
        <p class="text-muted cmp-vacio"><i class="fa-solid fa-code-compare"></i> Aún no hay comparaciones guardadas. Compara dos documentos y pulsa <strong>Guardar</strong>.</p>
    <?php else: ?>
        <div class="cmp-guardados-grid">
            <?php foreach ($guardados as $g): ?>
                <article class="cmp-guardado">
                    <a class="cmp-guardado-mini" href="/comparador?proyecto=<?php echo $pid; ?>&guardado=<?php echo (int)$g['id']; ?>" title="Abrir <?php echo s($g['nombre']); ?>">
                        <?php if ($g['miniatura']): ?><img src="<?php echo s($g['miniatura']); ?>" alt="" loading="lazy"><?php else: ?><span class="cmp-guardado-sin"><i class="fa-solid fa-code-compare"></i></span><?php endif; ?>
                    </a>
                    <div class="cmp-guardado-info">
                        <a href="/comparador?proyecto=<?php echo $pid; ?>&guardado=<?php echo (int)$g['id']; ?>" title="<?php echo s($g['nombre']); ?>"><strong><?php echo s($g['nombre']); ?></strong></a>
                        <small><?php echo s($fecha($g['actualizado'] ?: $g['creado'])); ?><?php echo $g['autor'] !== '' ? ' · ' . s($g['autor']) : ''; ?> · <?php echo (int)$g['anotaciones']; ?> anot.<?php echo $g['abiertas'] ? ' · <b class="cmp-abiertas">' . (int)$g['abiertas'] . ' abierta(s)</b>' : ''; ?></small>
                    </div>
                    <div class="cmp-guardado-acc">
                        <a class="btn btn-sm btn-ghost" href="/comparador?proyecto=<?php echo $pid; ?>&guardado=<?php echo (int)$g['id']; ?>" title="Abrir"><i class="fa-solid fa-folder-open"></i></a>
                        <a class="btn btn-sm btn-ghost" href="/comparador?proyecto=<?php echo $pid; ?>&guardado=<?php echo (int)$g['id']; ?>&historial=1" title="Historial de versiones"><i class="fa-solid fa-clock-rotate-left"></i></a>
                        <form method="post" action="/comparador/duplicar" style="margin:0">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>"><input type="hidden" name="volver" value="1">
                            <button class="btn btn-sm btn-ghost" type="submit" title="Duplicar comparación"><i class="fa-solid fa-copy"></i></button>
                        </form>
                        <form method="post" action="/comparador/eliminar" data-confirmar="¿Eliminar la comparación «<?php echo s($g['nombre']); ?>» y sus documentos externos guardados?" style="margin:0">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>"><input type="hidden" name="volver" value="1">
                            <button class="btn btn-sm btn-ghost" type="submit" title="Eliminar comparación"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</details>
<?php endif; ?>

<?php if (!$publico): ?>
<div class="card cmp-fuentes">
    <?php foreach (['a' => ['Documento A', 'base (fondo)'], 'b' => ['Documento B', 'el que se superpone y se mueve']] as $k => [$nombreCampo, $nota]): ?>
        <fieldset class="cmp-fuente" data-slot="<?php echo $k; ?>">
            <legend><?php echo s($nombreCampo); ?> <small class="text-muted">· <?php echo s($nota); ?></small></legend>
            <?php if ($fuentes): ?>
                <label>Del proyecto
                    <select class="cmp-proy" data-slot="<?php echo $k; ?>">
                        <option value="">— Elegir documento —</option>
                        <?php foreach ($fuentes as $g): ?>
                            <optgroup label="<?php echo s($g['grupo']); ?>">
                                <?php foreach ($g['items'] as $it): ?>
                                    <option value="<?php echo s($it['valor']); ?>" data-nombre="<?php echo s($it['nombre']); ?>" data-ext="<?php echo s($it['ext']); ?>" data-tipo="<?php echo s(explode(':', $it['valor'])[0]); ?>"<?php echo !empty($it['prev']) ? ' data-prev="' . s($it['prev']) . '" data-prev-nombre="' . s($it['prev_nombre'] ?? '') . '"' : ''; ?>><?php echo s($it['nombre'] . ' (' . strtoupper($it['ext']) . ')'); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($k === 'a'): ?>
                    <button type="button" class="btn btn-xs btn-ghost cmp-prev" id="cmp-prev" hidden title="Carga en B la revisión inmediatamente anterior del mismo documento"><i class="fa-solid fa-clock-rotate-left"></i> Usar la revisión anterior en B</button>
                <?php endif; ?>
            <?php endif; ?>
            <label>Externo
                <input type="file" class="cmp-ext" data-slot="<?php echo $k; ?>" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,application/pdf,image/*">
            </label>
            <div class="cmp-pag" hidden>
                <label>Página
                    <span class="cmp-pag-nav">
                        <button type="button" class="btn btn-xs btn-ghost cmp-pag-prev" data-slot="<?php echo $k; ?>" title="Página anterior"><i class="fa-solid fa-chevron-left"></i></button>
                        <input type="number" class="cmp-pagina" data-slot="<?php echo $k; ?>" min="1" value="1">
                        <button type="button" class="btn btn-xs btn-ghost cmp-pag-next" data-slot="<?php echo $k; ?>" title="Página siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                    </span>
                </label>
                <span class="text-muted cmp-total"></span>
            </div>
            <span class="text-sm text-muted cmp-estado" data-slot="<?php echo $k; ?>">Sin documento</span>
        </fieldset>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card cmp-publico-fuentes">
    <span><strong>A</strong> <?php echo s($abrir['a']['nombre'] ?? '—'); ?></span>
    <i class="fa-solid fa-arrow-right"></i>
    <span><strong>B</strong> <?php echo s($abrir['b']['nombre'] ?? '—'); ?></span>
</div>
<?php endif; ?>

<div class="card cmp-panel" id="cmp" hidden>
    <div class="cmp-barra">
        <div class="cmp-grupo" role="group" aria-label="Modo de comparación">
            <button type="button" class="btn btn-sm btn-primary" data-modo="slider" title="Deslizador: A a un lado y B al otro"><i class="fa-solid fa-arrows-left-right-to-line"></i> Deslizador</button>
            <button type="button" class="btn btn-sm btn-ghost" data-modo="fade" title="Fundido entre A y B"><i class="fa-solid fa-circle-half-stroke"></i> Fundido</button>
            <button type="button" class="btn btn-sm btn-ghost" data-modo="diff" title="Diferencias: lo igual se apaga y lo distinto se ilumina"><i class="fa-solid fa-not-equal"></i> Diferencias</button>
        </div>
        <label class="cmp-mini" id="cmp-dir-l">Dirección
            <select id="cmp-dir"><option value="v">Vertical |</option><option value="h">Horizontal —</option></select></label>
        <label class="cmp-mini" id="cmp-fade-l" hidden>Mezcla
            <input type="range" id="cmp-fade" min="0" max="100" value="50"></label>
        <span class="cmp-mini cmp-diff-ctl" id="cmp-diff-l" hidden>
            <label class="cmp-mini">Tolerancia <input type="range" id="cmp-diff-tol" min="0" max="100" value="24"></label>
            <output id="cmp-diff-pct" class="cmp-diff-pct" title="Porcentaje del área que cambió">—</output>
            <span class="cmp-grupo">
                <button type="button" class="btn btn-xs btn-ghost" id="cmp-diff-prev" title="Cambio anterior"><i class="fa-solid fa-chevron-up"></i></button>
                <button type="button" class="btn btn-xs btn-ghost" id="cmp-diff-next" title="Cambio siguiente"><i class="fa-solid fa-chevron-down"></i></button>
                <span class="text-sm text-muted" id="cmp-diff-n">0 / 0</span>
            </span>
        </span>
        <div class="cmp-grupo" role="group" aria-label="Emparejar B">
            <button type="button" class="btn btn-sm btn-ghost" id="cmp-mover" aria-pressed="false" title="Arrastra el documento B para alinearlo con A"><i class="fa-solid fa-up-down-left-right"></i> Mover B</button>
            <button type="button" class="btn btn-sm btn-ghost" id="cmp-ajustar" title="Escala B al tamaño de A"><i class="fa-solid fa-expand"></i> Ajustar B a A</button>
            <button type="button" class="btn btn-sm btn-ghost" id="cmp-reset-b" title="Quita traslado, escala y giro de B"><i class="fa-solid fa-rotate-left"></i> Restablecer B</button>
            <button type="button" class="btn btn-sm btn-ghost" id="cmp-intercambiar" title="Cambia A por B"><i class="fa-solid fa-right-left"></i></button>
        </div>
        <label class="cmp-mini">Escala B <input type="number" id="cmp-esc" min="5" max="1000" step="1" value="100">%</label>
        <label class="cmp-mini">Giro B <input type="number" id="cmp-rot" min="-180" max="180" step="0.1" value="0">°</label>
        <label class="cmp-mini">X <input type="number" id="cmp-bx" step="1" value="0"></label>
        <label class="cmp-mini">Y <input type="number" id="cmp-by" step="1" value="0"></label>
        <button type="button" class="btn btn-sm btn-ghost" id="cmp-encuadrar" title="Ver ambos completos"><i class="fa-solid fa-maximize"></i> Encuadrar</button>
        <button type="button" class="btn btn-sm btn-ghost" id="cmp-paginas" title="Analizar las páginas de los PDF y saltar a las que cambiaron"><i class="fa-solid fa-table-cells"></i> Páginas</button>
    </div>
    <?php if (!$publico): ?>
    <div class="cmp-barra cmp-anot-barra">
        <button type="button" class="btn btn-sm btn-ghost" id="cmp-anot-toggle" aria-pressed="false" title="Dibuja globos, textos, flechas, medidas, resaltados y nubes sobre la comparación"><i class="fa-solid fa-pen-to-square"></i> Anotar</button>
        <div class="cmp-anot-herr" id="cmp-anot-herr" hidden>
            <div class="cmp-grupo" role="group" aria-label="Herramienta de anotación">
                <button type="button" class="btn btn-sm btn-primary cmp-anot-tool" data-anot="seleccion" title="Seleccionar y mover anotaciones (V)"><i class="fa-solid fa-arrow-pointer"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="globo" title="Globo numerado con texto (G)"><i class="fa-solid fa-comment-dots"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="texto" title="Texto libre (T)"><i class="fa-solid fa-font"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="resaltador" title="Resaltador a mano alzada"><i class="fa-solid fa-highlighter"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="resaltado" title="Rectángulo resaltado"><i class="fa-solid fa-fill-drip"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="flecha" title="Flecha (F)"><i class="fa-solid fa-arrow-right"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="linea" title="Línea (L)"><i class="fa-solid fa-minus"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="rect" title="Rectángulo (R)"><i class="fa-regular fa-square"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="elipse" title="Elipse (E)"><i class="fa-regular fa-circle"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="nube" title="Nube de revisión (N)"><i class="fa-solid fa-cloud"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="medir" title="Medir una distancia con la escala del plano (M)"><i class="fa-solid fa-ruler"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="area" title="Medir un área con la escala del plano (A)"><i class="fa-solid fa-vector-square"></i></button>
                <button type="button" class="btn btn-sm btn-ghost cmp-anot-tool" data-anot="mano" title="Mover la vista sin salir de Anotar"><i class="fa-solid fa-hand"></i></button>
            </div>
            <div class="cmp-grupo cmp-anot-estilo">
                <input type="color" id="cmp-anot-color" value="#e11d48" title="Color de la anotación">
                <span class="cmp-anot-swatches" aria-label="Colores rápidos">
                    <?php foreach (['#e11d48', '#f97316', '#facc15', '#22c55e', '#0ea5e9', '#ffffff', '#111827'] as $col): ?><button type="button" class="cmp-anot-swatch" data-color="<?php echo $col; ?>" style="--c:<?php echo $col; ?>" title="Color <?php echo $col; ?>"></button><?php endforeach; ?>
                </span>
                <label class="cmp-mini">Grosor
                    <select id="cmp-anot-grosor"><option value="2">Fino</option><option value="5" selected>Normal</option><option value="10">Grueso</option><option value="16">Muy grueso</option></select></label>
                <label class="cmp-mini cmp-anot-relleno"><input type="checkbox" id="cmp-anot-relleno"> Relleno</label>
                <button type="button" class="btn btn-xs btn-ghost" id="cmp-calibrar" title="Dibuja una distancia conocida del plano y escribe su medida real"><i class="fa-solid fa-ruler-combined"></i> Calibrar</button>
                <span class="cmp-escala-info text-sm text-muted" id="cmp-escala-info">Sin escala</span>
            </div>
            <div class="cmp-grupo">
                <button type="button" class="btn btn-sm btn-ghost" id="cmp-anot-undo" title="Deshacer (Ctrl+Z)"><i class="fa-solid fa-rotate-left"></i></button>
                <button type="button" class="btn btn-sm btn-ghost" id="cmp-anot-redo" title="Rehacer (Ctrl+Shift+Z)"><i class="fa-solid fa-rotate-right"></i></button>
                <button type="button" class="btn btn-sm btn-ghost" id="cmp-anot-borrar" title="Eliminar la anotación seleccionada"><i class="fa-solid fa-trash"></i></button>
                <button type="button" class="btn btn-sm btn-ghost" id="cmp-anot-ver" aria-pressed="true" title="Mostrar u ocultar las anotaciones"><i class="fa-solid fa-eye"></i> <span>Ver</span></button>
                <button type="button" class="btn btn-sm btn-ghost" id="cmp-anot-lista-btn" aria-pressed="false" title="Lista de anotaciones"><i class="fa-solid fa-list-ol"></i> <span id="cmp-anot-cuenta">0</span></button>
                <button type="button" class="btn btn-sm btn-ghost" id="cmp-presentar" title="Modo presentación: pantalla completa y salto entre anotaciones"><i class="fa-solid fa-tv"></i></button>
            </div>
        </div>
        <span class="text-sm text-muted cmp-anot-ayuda" id="cmp-anot-ayuda">Activa Anotar y elige una herramienta.</span>
    </div>
    <?php endif; ?>

    <div class="cmp-vista" id="cmp-vista" tabindex="0" aria-label="Zona de comparación. Rueda del mouse: zoom. Arrastrar: desplazar. En pantalla táctil: pellizca para acercar y usa dos dedos para mover.">
        <div class="cmp-capa cmp-capa-a" id="cmp-capa-a"><div class="cmp-mundo" id="cmp-mundo-a"><canvas id="cmp-ca"></canvas></div></div>
        <div class="cmp-capa cmp-capa-b" id="cmp-capa-b"><div class="cmp-mundo" id="cmp-mundo-b"><canvas id="cmp-cb"></canvas></div></div>
        <div class="cmp-capa cmp-capa-diff" id="cmp-capa-diff" hidden><div class="cmp-mundo" id="cmp-mundo-diff"><canvas id="cmp-cd"></canvas></div></div>
        <div class="cmp-capa cmp-capa-anot"><canvas id="cmp-anot" aria-label="Anotaciones de la comparación"></canvas></div>
        <div class="cmp-div" id="cmp-div" role="slider" tabindex="0" aria-label="Posición del deslizador" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50"><span><i class="fa-solid fa-arrows-left-right"></i></span></div>
        <span class="cmp-etq cmp-etq-a">A</span><span class="cmp-etq cmp-etq-b">B</span>
        <aside class="cmp-anot-lista" id="cmp-anot-lista" hidden aria-label="Lista de anotaciones">
            <div class="cmp-anot-lista-cab"><strong>Anotaciones</strong><button type="button" class="btn btn-xs btn-ghost" id="cmp-anot-cerrar" title="Cerrar lista"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="cmp-anot-filtros" role="group" aria-label="Filtrar anotaciones">
                <button type="button" class="cmp-chip activo" data-filtro="todas">Todas</button>
                <button type="button" class="cmp-chip" data-filtro="abiertas">Abiertas</button>
                <button type="button" class="cmp-chip" data-filtro="resueltas">Resueltas</button>
                <label class="cmp-mini"><input type="checkbox" id="cmp-anot-todas-paginas"> Todas las páginas</label>
            </div>
            <ol id="cmp-anot-items"></ol>
            <p class="text-sm text-muted" id="cmp-anot-vacio">Aún no hay anotaciones.</p>
            <section class="cmp-anot-detalle" id="cmp-anot-detalle" hidden>
                <div class="cmp-anot-detalle-cab">
                    <strong id="cmp-det-titulo">Anotación</strong>
                    <button type="button" class="btn btn-xs btn-ghost" id="cmp-det-cerrar" title="Ocultar detalle"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <p class="text-sm text-muted" id="cmp-det-meta"></p>
                <div class="cmp-grupo">
                    <button type="button" class="btn btn-xs btn-ghost" id="cmp-det-estado"><i class="fa-solid fa-circle-check"></i> Resolver</button>
                    <button type="button" class="btn btn-xs btn-ghost" id="cmp-det-grupo" title="Mover juntas las anotaciones seleccionadas"><i class="fa-solid fa-object-group"></i> Agrupar</button>
                    <button type="button" class="btn btn-xs btn-ghost" id="cmp-det-punch" title="Crear un pendiente de punch list con esta anotación"><i class="fa-solid fa-screwdriver-wrench"></i> Punch</button>
                    <button type="button" class="btn btn-xs btn-ghost" id="cmp-det-rfi" title="Abrir un RFI con esta anotación"><i class="fa-solid fa-circle-question"></i> RFI</button>
                    <button type="button" class="btn btn-xs btn-ghost" id="cmp-det-mover" title="Mover la anotación a otra página"><i class="fa-solid fa-arrow-right-arrow-left"></i> Página</button>
                </div>
                <div id="cmp-det-notas" class="cmp-notas"></div>
                <form class="cmp-nota-form" id="cmp-nota-form">
                    <textarea id="cmp-nota-texto" rows="2" maxlength="1000" placeholder="Responder o dejar una nota…"></textarea>
                    <button class="btn btn-xs btn-primary" type="submit"><i class="fa-solid fa-reply"></i> Enviar</button>
                </form>
            </section>
        </aside>
        <textarea id="cmp-anot-txt" class="cmp-anot-txt" hidden aria-label="Texto de la anotación"></textarea>
        <div class="cmp-presentacion" id="cmp-presentacion" hidden>
            <span id="cmp-pres-info">—</span>
            <span class="cmp-grupo">
                <button type="button" class="btn btn-sm btn-ghost" id="cmp-pres-prev" title="Anotación anterior (←)"><i class="fa-solid fa-chevron-left"></i></button>
                <button type="button" class="btn btn-sm btn-ghost" id="cmp-pres-next" title="Anotación siguiente (→)"><i class="fa-solid fa-chevron-right"></i></button>
                <button type="button" class="btn btn-sm btn-ghost" id="cmp-pres-salir" title="Salir (Esc)"><i class="fa-solid fa-xmark"></i></button>
            </span>
        </div>
    </div>
    <?php if ($puedeGuardar): ?>
    <div class="cmp-barra cmp-acciones">
        <strong><i class="fa-solid fa-floppy-disk"></i> Comparación</strong>
        <button type="button" class="btn btn-sm btn-primary" id="cmp-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        <button type="button" class="btn btn-sm btn-ghost" id="cmp-versiones" title="Historial de versiones"><i class="fa-solid fa-clock-rotate-left"></i> Historial</button>
        <button type="button" class="btn btn-sm btn-ghost" id="cmp-compartir" title="Enlace de solo lectura y envío por Telegram"><i class="fa-solid fa-share-nodes"></i> Compartir</button>
        <span class="text-sm text-muted" id="cmp-guardar-estado"><?php echo $abrir ? 'Abriendo «' . s($abrir['nombre']) . '»…' : 'Guarda los documentos, la vista y las anotaciones para revisarlas después.'; ?></span>
    </div>
    <?php endif; ?>
    <div class="cmp-barra cmp-exportar">
        <strong><i class="fa-solid fa-file-pdf"></i> Exportar</strong>
        <label class="cmp-mini">Tamaño
            <select id="cmp-pdf-fmt">
                <optgroup label="Oficina">
                    <option value="215.9x279.4" selected>Carta (8.5 × 11 in)</option>
                    <option value="215.9x355.6">Oficio / Legal (8.5 × 14 in)</option>
                    <option value="210x297">A4</option>
                    <option value="297x420">A3</option>
                    <option value="279.4x431.8">Tabloide (11 × 17 in)</option>
                </optgroup>
                <optgroup label="Construcción / impresión">
                    <option value="457.2x609.6">Arch C (18 × 24 in)</option>
                    <option value="609.6x914.4">Arch D (24 × 36 in)</option>
                    <option value="914.4x1219.2">Arch E (36 × 48 in)</option>
                    <option value="594x841">A1</option>
                    <option value="841x1189">A0</option>
                    <option value="600x900">90 × 60 cm</option>
                </optgroup>
                <option value="custom">Personalizado…</option>
            </select></label>
        <span id="cmp-pdf-cust" class="cmp-mini" hidden>
            <input type="number" id="cmp-pdf-w" min="5" max="1500" step="0.1" value="60" aria-label="Ancho en cm"> ×
            <input type="number" id="cmp-pdf-h" min="5" max="1500" step="0.1" value="90" aria-label="Alto en cm"> cm</span>
        <label class="cmp-mini">Orientación
            <select id="cmp-pdf-ori"><option value="auto">Automática</option><option value="l">Horizontal</option><option value="p">Vertical</option></select></label>
        <label class="cmp-mini">Qué exportar
            <select id="cmp-pdf-que"><option value="ambos">A y B como se ven ahora (modo, deslizador, mezcla)</option><option value="a">Solo A</option><option value="b">Solo B (con su alineación)</option></select></label>
        <label class="cmp-mini">Área
            <select id="cmp-pdf-cont"><option value="vista">Vista actual (zoom y posición)</option><option value="todo">Documento A completo</option></select></label>
        <label class="cmp-mini"><input type="checkbox" id="cmp-pdf-anot" checked> Incluir anotaciones, medidas y leyenda</label>
        <button type="button" class="btn btn-sm btn-primary" id="cmp-pdf-btn"><i class="fa-solid fa-file-pdf"></i> Descargar PDF</button>
        <button type="button" class="btn btn-sm btn-ghost" id="cmp-informe" title="Resumen de la revisión: cambios, anotaciones y medidas"><i class="fa-solid fa-clipboard-list"></i> Informe</button>
        <span class="text-sm text-muted" id="cmp-pdf-estado"></span>
    </div>
    <p class="text-sm text-muted">Rueda o pellizco = zoom · arrastrar (o dos dedos) = mover · «Mover B» alinea B con A · toca el deslizador y usa ← → · <kbd>Ctrl</kbd>+<kbd>C</kbd>/<kbd>V</kbd> copia anotaciones y <kbd>Supr</kbd> las borra.</p>
</div>

<dialog id="cmp-guardar-dlg" class="cmp-dlg">
    <form id="cmp-guardar-form" onsubmit="return false">
        <h3><i class="fa-solid fa-floppy-disk"></i> Guardar comparación</h3>
        <label>Nombre
            <input type="text" id="cmp-nombre" maxlength="150" required placeholder="Ej. Plano eléctrico · Rev. A vs Rev. B">
        </label>
        <label>Nota <small class="text-muted">(opcional)</small>
            <textarea id="cmp-desc" rows="2" maxlength="500" placeholder="Qué se está revisando"></textarea>
        </label>
        <label id="cmp-copia-l" hidden><input type="checkbox" id="cmp-copia"> Guardar como copia nueva</label>
        <p class="text-sm text-muted" id="cmp-guardar-aviso"></p>
        <div class="cmp-dlg-acc">
            <button type="button" class="btn btn-ghost" id="cmp-cancelar">Cancelar</button>
            <button type="button" class="btn btn-primary" id="cmp-confirmar-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </div>
    </form>
</dialog>

<dialog id="cmp-compartir-dlg" class="cmp-dlg">
    <form onsubmit="return false">
        <h3><i class="fa-solid fa-share-nodes"></i> Compartir comparación</h3>
        <p class="text-sm text-muted">El enlace abre una <strong>vista de solo lectura</strong> sin necesidad de iniciar sesión. Cualquiera que lo tenga puede ver los documentos y las anotaciones; revócalo cuando quieras.</p>
        <label>Enlace de solo lectura
            <span class="cmp-copiar">
                <input type="text" id="cmp-enlace" readonly placeholder="Pulsa «Crear enlace»">
                <button type="button" class="btn btn-sm btn-ghost" id="cmp-copiar-enlace" title="Copiar enlace"><i class="fa-solid fa-copy"></i></button>
            </span>
        </label>
        <div class="cmp-dlg-acc" style="justify-content: flex-start">
            <button type="button" class="btn btn-sm btn-primary" id="cmp-crear-enlace"><i class="fa-solid fa-link"></i> Crear enlace</button>
            <button type="button" class="btn btn-sm btn-ghost" id="cmp-revocar-enlace"><i class="fa-solid fa-link-slash"></i> Revocar</button>
        </div>
        <hr>
        <label>Mensaje para Telegram <small class="text-muted">(opcional)</small>
            <textarea id="cmp-tg-mensaje" rows="2" maxlength="600" placeholder="Ej. Revisen el cambio de la columna B antes del viernes."></textarea>
        </label>
        <label class="cmp-mini"><input type="checkbox" id="cmp-tg-pdf" checked> Adjuntar el PDF de la comparación</label>
        <p class="text-sm text-muted" id="cmp-compartir-estado"></p>
        <div class="cmp-dlg-acc">
            <button type="button" class="btn btn-ghost" id="cmp-compartir-cerrar">Cerrar</button>
            <button type="button" class="btn btn-primary" id="cmp-enviar-telegram"><i class="fa-brands fa-telegram"></i> Enviar por Telegram</button>
        </div>
    </form>
</dialog>

<dialog id="cmp-versiones-dlg" class="cmp-dlg">
    <div class="cmp-ver">
        <h3><i class="fa-solid fa-clock-rotate-left"></i> Historial de versiones</h3>
        <p class="text-sm text-muted">Cada vez que guardas se conserva la versión anterior. Restaurar también guarda el estado actual, así que puedes deshacer.</p>
        <div id="cmp-versiones-lista" class="cmp-ver-lista"></div>
        <div class="cmp-dlg-acc">
            <button type="button" class="btn btn-ghost" id="cmp-versiones-cerrar">Cerrar</button>
        </div>
    </div>
</dialog>

<dialog id="cmp-paginas-dlg" class="cmp-dlg cmp-dlg-ancho">
    <div class="cmp-ver">
        <h3><i class="fa-solid fa-table-cells"></i> Páginas y cambios</h3>
        <p class="text-sm text-muted">Compara cada página de A con la misma página de B. Sirve para revisar un plano de varias hojas y saltar directo a las que cambiaron.</p>
        <div class="cmp-dlg-acc" style="justify-content: flex-start">
            <button type="button" class="btn btn-sm btn-primary" id="cmp-analizar-paginas"><i class="fa-solid fa-magnifying-glass"></i> Analizar páginas</button>
            <button type="button" class="btn btn-sm btn-ghost" id="cmp-exportar-cambios" disabled title="PDF con una hoja por cada página que cambió"><i class="fa-solid fa-file-export"></i> PDF de cambios</button>
            <span class="text-sm text-muted" id="cmp-analizar-estado"></span>
        </div>
        <div id="cmp-paginas-grid" class="cmp-paginas-grid"></div>
        <div class="cmp-dlg-acc"><button type="button" class="btn btn-ghost" id="cmp-paginas-cerrar">Cerrar</button></div>
    </div>
</dialog>

<dialog id="cmp-informe-dlg" class="cmp-dlg cmp-dlg-ancho">
    <div class="cmp-ver">
        <h3><i class="fa-solid fa-clipboard-list"></i> Informe de revisión</h3>
        <div id="cmp-informe-cuerpo" class="cmp-informe"></div>
        <div class="cmp-dlg-acc">
            <button type="button" class="btn btn-ghost" id="cmp-informe-cerrar">Cerrar</button>
            <button type="button" class="btn btn-primary" id="cmp-informe-pdf"><i class="fa-solid fa-file-pdf"></i> Descargar PDF</button>
        </div>
    </div>
</dialog>

<script src="/vendor/pdfjs/pdf.min.js"></script>
<script src="/vendor/jspdf/jspdf.umd.min.js"></script>
<script src="/vendor/anotaciones.js"></script>
<script src="/vendor/gestos-lienzo.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/gestos-lienzo.js'); ?>"></script>
<script>window.CMP_CONFIG = { proyecto: <?php echo (int)$proyecto->id; ?>, nombreProyecto: <?php echo json_encode($proyecto->nombre, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>, usuario: <?php echo json_encode($_SESSION['nombre'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>, puedeGuardar: <?php echo $puedeGuardar ? 'true' : 'false'; ?>, publico: <?php echo $publico ? 'true' : 'false'; ?>, token: <?php echo json_encode($publico ? ($token ?? '') : '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>, worker: '/vendor/pdfjs/pdf.worker.min.js' };
window.CMP_GUARDADO = <?php echo json_encode($abrir, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.CMP_HISTORIAL = <?php echo (int)($historial ?? 0); ?>;</script>
<script src="/vendor/comparador.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/comparador.js'); ?>"></script>
