<?php // Variables: $proyecto, $fuentes, $documentos, $abrir, $puedeGuardar, $aviso, $historial ?>
<?php
$pid = (int)$proyecto->id;
$fecha = fn($f) => $f ? date('d/m/Y H:i', strtotime($f)) : '—';
$totalDocs = count($documentos);
// Herramientas de dibujo: icono, nombre y tecla (se pintan todas en la paleta)
$herramientas = [
    'basico' => [
        'titulo' => 'Básico',
        'items' => [
            ['seleccion', 'fa-arrow-pointer', 'Seleccionar', 'V', 'Seleccionar y mover anotaciones'],
            ['mano', 'fa-hand', 'Mover vista', 'H', 'Desplazar el plano sin mover anotaciones'],
        ],
    ],
    'marcar' => [
        'titulo' => 'Marcar',
        'items' => [
            ['globo', 'fa-comment-dots', 'Globo', 'G', 'Globo numerado con texto'],
            ['texto', 'fa-font', 'Texto', 'T', 'Texto libre'],
            ['resaltador', 'fa-highlighter', 'Resaltador', '', 'Resaltador a mano alzada'],
            ['resaltado', 'fa-fill-drip', 'Zona', '', 'Rectángulo resaltado'],
            ['flecha', 'fa-arrow-right', 'Flecha', 'F', 'Flecha'],
            ['linea', 'fa-minus', 'Línea', 'L', 'Línea'],
            ['rect', 'fa-square', 'Rectángulo', 'R', 'Rectángulo'],
            ['elipse', 'fa-circle', 'Elipse', 'E', 'Elipse'],
            ['nube', 'fa-cloud', 'Nube', 'N', 'Nube de revisión'],
        ],
    ],
    'medir' => [
        'titulo' => 'Medir',
        'items' => [
            ['medir', 'fa-ruler', 'Medir', 'M', 'Medir una distancia'],
            ['area', 'fa-vector-square', 'Área', 'A', 'Área rectangular'],
            ['poligono', 'fa-draw-polygon', 'Polígono', 'P', 'Polígono: área y perímetro'],
            ['volumen', 'fa-cube', 'Volumen', 'O', 'Volumen: polígono por profundidad'],
        ],
    ],
    'insertar' => [
        'titulo' => 'Insertar',
        'items' => [
            ['imagen', 'fa-image', 'Imagen', '', 'Insertar una imagen'],
            ['estampa', 'fa-stamp', 'Estampa', '', 'Insertar una estampa inteligente'],
            ['firma', 'fa-signature', 'Firma', '', 'Insertar una firma'],
        ],
    ],
    'texto-pdf' => [
        'titulo' => 'Texto del PDF',
        'items' => [
            ['resaltado_texto', 'fa-text-height', 'Resaltar', '', 'Resaltar texto seleccionable'],
            ['subrayado', 'fa-underline', 'Subrayar', '', 'Subrayar texto seleccionable'],
            ['tachado', 'fa-strikethrough', 'Tachar', '', 'Tachar texto seleccionable'],
        ],
    ],
];
?>
<div class="page-header ant-cab">
    <div class="ant-cab-txt">
        <h2><i class="fa-solid fa-pen-nib"></i> <?php echo s(t('Anotador')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo $abrir ? 'Anota, mide y firma sobre el documento.' : 'Abre un plano o documento PDF y trabaja encima: anota, mide áreas y volúmenes, inserta imágenes, estampas y firmas, y descarga el PDF anotado.'; ?></p>
    </div>
    <div class="ant-cab-acc">
        <?php if ($abrir): ?>
            <span class="ant-badge" id="ant-badge-estado"><i class="fa-solid fa-file-pen"></i> <span><?php echo s($abrir['nombre']); ?></span></span>
            <a class="btn btn-sm btn-ghost" href="/anotador?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-arrow-left"></i> Documentos</a>
        <?php elseif ($puedeGuardar): ?>
            <a class="btn btn-sm btn-ghost" href="#ant-fuentes"><i class="fa-solid fa-file-circle-plus"></i> Nuevo documento</a>
        <?php endif; ?>
    </div>
</div>
<?php if (!empty($aviso)): ?><div class="alerta alerta-exito" role="status"><?php echo s($aviso); ?></div><?php endif; ?>
<?php if (!empty($_GET['error'])): ?><div class="alerta alerta-error" role="alert">No se pudo completar la acción: <?php echo s((string)$_GET['error']); ?></div><?php endif; ?>
<div class="alerta alerta-error" id="ant-error" role="alert" hidden></div>

<?php if (!$abrir): ?>
<div class="ant-inicio">
    <?php if ($puedeGuardar): ?>
    <section class="card ant-fuentes" id="ant-fuentes" aria-labelledby="ant-fuentes-tit">
        <div class="ant-secc-cab">
            <h3 id="ant-fuentes-tit"><i class="fa-solid fa-file-circle-plus"></i> Abrir o crear un documento</h3>
            <span class="text-sm text-muted">Elige una fuente del proyecto o sube un archivo nuevo</span>
        </div>
        <div class="ant-fuente-grid">
            <?php if ($fuentes): ?>
                <label>Del proyecto
                    <select id="ant-proy">
                        <option value="">— Elegir documento —</option>
                        <?php foreach ($fuentes as $g): ?>
                            <optgroup label="<?php echo s($g['grupo']); ?>">
                                <?php foreach ($g['items'] as $it): ?>
                                    <option value="<?php echo s($it['valor']); ?>" data-nombre="<?php echo s($it['nombre']); ?>" data-ext="<?php echo s($it['ext']); ?>"><?php echo s($it['nombre'] . ' (' . strtoupper($it['ext']) . ')'); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>Archivo externo
                <input type="file" id="ant-archivo" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,application/pdf,image/*">
            </label>
            <label>Nombre
                <input type="text" id="ant-nombre" maxlength="150" placeholder="Se toma del documento">
            </label>
            <button type="button" class="btn btn-primary" id="ant-abrir"><i class="fa-solid fa-arrow-right-to-bracket"></i> Abrir y anotar</button>
        </div>
        <p class="text-sm text-muted ant-nota-pie"><i class="fa-solid fa-circle-info"></i> Fuentes: PDF o imágenes del Archivo, láminas de vPlan, gráficos del Editor y documentos del Redactor. Los archivos externos se copian al proyecto al guardar.</p>
    </section>
    <?php endif; ?>

    <section class="card ant-lista" aria-labelledby="ant-lista-tit">
        <div class="ant-lista-cab">
            <h3 id="ant-lista-tit"><i class="fa-solid fa-folder-open"></i> Documentos guardados <span class="ant-cuenta"><?php echo $totalDocs; ?></span></h3>
            <?php if ($totalDocs > 0): ?>
                <div class="pn-busca ant-lista-busca">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="ant-buscar-doc" placeholder="Buscar por nombre, autor o fuente…" aria-label="Buscar documentos guardados">
                </div>
            <?php endif; ?>
        </div>
        <?php if (!$totalDocs): ?>
            <div class="pn-vacio ant-vacio-lista">
                <i class="fa-solid fa-pen-nib"></i>
                <b>Aún no hay documentos anotados</b>
                <p><?php echo $puedeGuardar ? 'Elige una fuente arriba y pulsa «Abrir y anotar» para empezar.' : 'Cuando alguien del equipo cree uno aparecerá aquí.'; ?></p>
            </div>
        <?php else: ?>
            <div class="ant-lista-grid" id="ant-lista-grid">
                <?php foreach ($documentos as $d): $t = $d->tarjeta(); ?>
                    <article class="ant-doc" data-buscar="<?php echo s(mb_strtolower($t['nombre'] . ' ' . $t['fuente'] . ' ' . $t['autor'] . ' ' . $t['descripcion'])); ?>">
                        <a class="ant-doc-mini" href="/anotador?proyecto=<?php echo $pid; ?>&amp;id=<?php echo $t['id']; ?>" title="Abrir <?php echo s($t['nombre']); ?>">
                            <?php if ($t['miniatura']): ?><img src="<?php echo s($t['miniatura']); ?>" alt="" loading="lazy"><?php else: ?><span class="ant-doc-sin"><i class="fa-solid fa-file-lines"></i></span><?php endif; ?>
                            <?php if ($t['finalizado']): ?><span class="ant-doc-sello" title="Firmado y bloqueado"><i class="fa-solid fa-signature"></i></span><?php endif; ?>
                        </a>
                        <div class="ant-doc-info">
                            <a href="/anotador?proyecto=<?php echo $pid; ?>&amp;id=<?php echo $t['id']; ?>" title="<?php echo s($t['nombre']); ?>"><strong><?php echo s($t['nombre']); ?></strong></a>
                            <?php if ($t['descripcion'] !== ''): ?><small class="ant-doc-desc"><?php echo s($t['descripcion']); ?></small><?php endif; ?>
                            <small><?php echo s($fecha($t['actualizado'] ?: $t['creado'])); ?><?php echo $t['autor'] !== '' ? ' · ' . s($t['autor']) : ''; ?> · <?php echo (int)$t['anotaciones']; ?> anot. · <?php echo (int)$t['paginas']; ?> pág.</small>
                            <small class="ant-doc-fuente"><?php echo s($t['fuente']); ?></small>
                        </div>
                        <div class="ant-doc-acc">
                            <a class="btn btn-sm btn-ghost" href="/anotador?proyecto=<?php echo $pid; ?>&amp;id=<?php echo $t['id']; ?>" title="Abrir" aria-label="Abrir <?php echo s($t['nombre']); ?>"><i class="fa-solid fa-folder-open"></i></a>
                            <?php if ($puedeGuardar): ?>
                            <form class="m-0" method="post" action="/anotador/duplicar">
                                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                <button class="btn btn-sm btn-ghost" type="submit" title="Duplicar documento" aria-label="Duplicar <?php echo s($t['nombre']); ?>"><i class="fa-solid fa-copy"></i></button>
                            </form>
                            <form method="post" action="/anotador/eliminar" data-confirmar="¿Eliminar «<?php echo s($t['nombre']); ?>» y sus imágenes y miniatura?" style="margin:0">
                                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                <button class="btn btn-sm btn-ghost" type="submit" title="Eliminar documento" aria-label="Eliminar <?php echo s($t['nombre']); ?>"><i class="fa-solid fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="text-sm text-muted" id="ant-buscar-vacia" hidden></p>
            <p class="text-sm text-muted ant-lista-pie"><i class="fa-solid fa-lock"></i> Solo quien creó un documento o el administrador pueden modificarlo.</p>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>

<div class="ant-app" id="ant-app" hidden>
    <div class="ant-barra" role="toolbar" aria-label="Vista, estilo y acciones">
        <div class="ant-grupo ant-grupo-nav">
            <a class="btn btn-sm btn-ghost ant-volver" href="/anotador?proyecto=<?php echo $pid; ?>" title="Volver a documentos" aria-label="Volver a documentos"><i class="fa-solid fa-arrow-left"></i><span class="ant-volver-txt">Documentos</span></a>
            <button type="button" class="btn btn-sm btn-ghost" id="ant-panel-btn" title="Mostrar u ocultar el panel" aria-label="Mostrar u ocultar el panel"><i class="fa-solid fa-table-columns"></i></button>
            <button type="button" class="btn btn-sm btn-ghost" id="ant-paginas-btn" title="Mostrar u ocultar las páginas" aria-label="Mostrar u ocultar las páginas"><i class="fa-solid fa-images"></i></button>
            <button type="button" class="btn btn-sm btn-ghost" id="ant-ajustar" title="Ajustar la página a la ventana" aria-label="Ajustar la página a la ventana"><i class="fa-solid fa-maximize"></i></button>
            <span class="ant-zoom">
                <button type="button" class="btn btn-xs btn-ghost" id="ant-zoom-menos" title="Reducir" aria-label="Reducir zoom"><i class="fa-solid fa-minus"></i></button>
                <output id="ant-zoom-val" aria-live="polite">100%</output>
                <button type="button" class="btn btn-xs btn-ghost" id="ant-zoom-mas" title="Ampliar" aria-label="Ampliar zoom"><i class="fa-solid fa-plus"></i></button>
            </span>
        </div>
        <div class="ant-grupo ant-estilo">
            <label class="ant-campo-inline" title="Color de la anotación"><span class="ant-solo-lectores">Color</span><input type="color" id="ant-color" value="#e11d48" aria-label="Color de la anotación"></label>
            <label class="ant-campo-inline" title="Grosor"><span class="ant-solo-lectores">Grosor</span>
                <select id="ant-grosor" aria-label="Grosor">
                    <option value="2">Fino</option><option value="5" selected>Normal</option><option value="10">Grueso</option><option value="16">Muy grueso</option>
                </select>
            </label>
            <label class="ant-mini" title="Relleno de formas"><input type="checkbox" id="ant-relleno"> Relleno</label>
            <label class="ant-mini ant-mini-opacidad" title="Opacidad de imágenes y firmas">Opacidad <input type="range" id="ant-opacidad" min="10" max="100" value="100" aria-label="Opacidad de imágenes y firmas"></label>
        </div>
        <div class="ant-grupo">
            <button type="button" class="btn btn-sm btn-ghost" id="ant-undo" title="Deshacer (Ctrl+Z)" aria-label="Deshacer"><i class="fa-solid fa-rotate-left"></i></button>
            <button type="button" class="btn btn-sm btn-ghost" id="ant-redo" title="Rehacer (Ctrl+Shift+Z)" aria-label="Rehacer"><i class="fa-solid fa-rotate-right"></i></button>
            <button type="button" class="btn btn-sm btn-ghost" id="ant-borrar" title="Eliminar lo seleccionado (Supr)" aria-label="Eliminar lo seleccionado"><i class="fa-solid fa-trash"></i></button>
            <button type="button" class="btn btn-sm btn-ghost" id="ant-ver" aria-pressed="true" title="Mostrar u ocultar las anotaciones" aria-label="Mostrar u ocultar las anotaciones"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div class="ant-grupo ant-grupo-fin">
            <span class="ant-guardado text-sm text-muted" id="ant-guardado">Sin guardar</span>
            <button type="button" class="btn btn-sm btn-ghost" id="ant-versiones" title="Historial de versiones" aria-label="Historial de versiones"><i class="fa-solid fa-clock-rotate-left"></i></button>
            <button type="button" class="btn btn-sm btn-primary" id="ant-guardar" title="Guardar el documento y sus anotaciones"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
            <button type="button" class="btn btn-sm btn-ghost" id="ant-exportar" title="Descargar el PDF anotado"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            <button type="button" class="btn btn-sm btn-ghost" id="ant-firmar-btn" title="Finalizar la firma y bloquear el documento"><i class="fa-solid fa-signature"></i> Finalizar firma</button>
        </div>
    </div>

    <div class="ant-paleta" role="toolbar" aria-label="Herramientas de anotación">
        <?php foreach ($herramientas as $grupo): ?>
            <div class="ant-grupo-paleta">
                <span class="ant-grupo-tit"><?php echo s($grupo['titulo']); ?></span>
                <div class="ant-grupo-herramientas">
                    <?php foreach ($grupo['items'] as [$tool, $ico, $etq, $tecla, $ayuda]): ?>
                        <button type="button" class="ant-tool<?php echo $tool === 'seleccion' ? ' activo' : ''; ?>" data-tool="<?php echo s($tool); ?>"
                            title="<?php echo s($ayuda . ($tecla !== '' ? ' (' . $tecla . ')' : '')); ?>" aria-label="<?php echo s($ayuda); ?>" aria-pressed="<?php echo $tool === 'seleccion' ? 'true' : 'false'; ?>">
                            <i class="fa-solid <?php echo s($ico); ?>" aria-hidden="true"></i><span><?php echo s($etq); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="ant-cuerpo" id="ant-cuerpo">
        <aside class="ant-paginas" id="ant-paginas" aria-label="Páginas">
            <div class="ant-paginas-cab"><strong>Páginas</strong><button type="button" class="btn btn-xs btn-ghost" id="ant-paginas-cerrar" title="Ocultar" aria-label="Ocultar páginas"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="ant-paginas-lista" id="ant-paginas-lista"></div>
        </aside>
        <div class="ant-vista" id="ant-vista" tabindex="0" aria-label="Documento. Rueda del ratón: zoom. Arrastrar con el botón central o la mano: desplazar.">
            <div class="ant-mundo" id="ant-mundo">
                <canvas id="ant-hoja"></canvas>
                <canvas id="ant-anot" aria-label="Anotaciones del documento"></canvas>
                <div class="ant-texto" id="ant-texto"></div>
            </div>
            <div class="ant-vista-vacia" id="ant-vacia"><i class="fa-solid fa-file-circle-plus"></i><span>Elige un documento para empezar</span></div>
        </div>
        <aside class="ant-panel" id="ant-panel" aria-label="Panel del Anotador">
            <div class="pn-cab">
                <span class="pn-cab-ico"><i class="fa-solid fa-list-check"></i></span>
                <span class="pn-cab-txt"><strong>Anotador</strong><small id="ant-panel-sub">Anotaciones</small></span>
                <span class="pn-cab-acc"><button type="button" class="btn btn-xs btn-ghost" id="ant-panel-cerrar" title="Ocultar panel" aria-label="Ocultar panel"><i class="fa-solid fa-chevron-right"></i></button></span>
            </div>
            <div class="pn-tabs" role="tablist">
                <button type="button" class="pn-tab activo" data-ptab="lista" role="tab" aria-selected="true"><i class="fa-solid fa-list-ol"></i> Anotaciones <span class="pn-cont" id="ant-cuenta">0</span></button>
                <button type="button" class="pn-tab" data-ptab="props" role="tab" aria-selected="false"><i class="fa-solid fa-sliders"></i> Propiedades</button>
                <button type="button" class="pn-tab" data-ptab="medidas" role="tab" aria-selected="false"><i class="fa-solid fa-ruler-combined"></i> Medidas</button>
                <button type="button" class="pn-tab" data-ptab="insertar" role="tab" aria-selected="false"><i class="fa-solid fa-plus"></i> Insertar</button>
            </div>
            <div class="ant-panel-cont" id="ant-p-lista" data-panel="lista">
                <div class="ant-filtros">
                    <button type="button" class="pn-chip activo" data-filtro="todas">Todas</button>
                    <button type="button" class="pn-chip" data-filtro="abiertas">Abiertas</button>
                    <button type="button" class="pn-chip" data-filtro="resueltas">Resueltas</button>
                    <label class="ant-mini"><input type="checkbox" id="ant-todas-paginas"> Todas las páginas</label>
                </div>
                <ol class="ant-lista-anot" id="ant-lista-anot"></ol>
                <p class="text-sm text-muted" id="ant-lista-vacia">Aún no hay anotaciones.</p>
                <section class="ant-detalle" id="ant-detalle" hidden>
                    <div class="ant-detalle-cab"><strong id="ant-det-titulo">Anotación</strong><button type="button" class="btn btn-xs btn-ghost" id="ant-det-cerrar" title="Cerrar" aria-label="Cerrar detalle"><i class="fa-solid fa-xmark"></i></button></div>
                    <p class="text-sm text-muted" id="ant-det-meta"></p>
                    <div class="ant-detalle-acc">
                        <button type="button" class="btn btn-xs btn-ghost" id="ant-det-estado"><i class="fa-solid fa-circle-check"></i> Resolver</button>
                        <button type="button" class="btn btn-xs btn-ghost" id="ant-det-grupo"><i class="fa-solid fa-object-group"></i> Agrupar</button>
                        <button type="button" class="btn btn-xs btn-ghost" id="ant-det-duplicar" title="Duplicar" aria-label="Duplicar anotación"><i class="fa-solid fa-copy"></i></button>
                        <button type="button" class="btn btn-xs btn-ghost" id="ant-det-punch" title="Crear un pendiente de Punch list" aria-label="Crear un pendiente de Punch list"><i class="fa-solid fa-screwdriver-wrench"></i></button>
                        <button type="button" class="btn btn-xs btn-ghost" id="ant-det-rfi" title="Crear un RFI" aria-label="Crear un RFI"><i class="fa-solid fa-circle-question"></i></button>
                    </div>
                    <div class="ant-notas" id="ant-det-notas"></div>
                    <form id="ant-nota-form" class="ant-nota-form">
                        <textarea id="ant-nota-texto" rows="2" maxlength="1000" placeholder="Responder o agregar una nota…"></textarea>
                        <button class="btn btn-sm btn-primary" type="submit" title="Agregar nota" aria-label="Agregar nota"><i class="fa-solid fa-paper-plane"></i></button>
                    </form>
                </section>
            </div>
            <div class="ant-panel-cont" id="ant-p-props" data-panel="props" hidden>
                <p class="text-sm text-muted" id="ant-props-vacio">Selecciona una anotación para editar sus propiedades.</p>
                <div id="ant-props-form" hidden>
                    <label class="ant-campo">Texto / contenido
                        <textarea id="ant-prop-texto" rows="3" maxlength="2000"></textarea>
                    </label>
                    <div class="ant-fila">
                        <label class="ant-campo">Color <input type="color" id="ant-prop-color"></label>
                        <label class="ant-campo">Grosor <input type="number" id="ant-prop-grosor" min="1" max="80" step="1"></label>
                    </div>
                    <label class="ant-campo ant-fila"><input type="checkbox" id="ant-prop-relleno"> Relleno</label>
                    <label class="ant-campo" id="ant-prop-profundidad-campo" hidden>Profundidad (m) <input type="number" id="ant-prop-profundidad" min="0" max="10000" step="0.01"></label>
                    <label class="ant-campo" id="ant-prop-etiqueta-campo" hidden>Etiqueta del polígono
                        <select id="ant-prop-etiqueta"><option value="area">Área</option><option value="perimetro">Área y perímetro</option></select>
                    </label>
                    <div class="ant-fila" id="ant-prop-firma-campos" hidden>
                        <label class="ant-campo">Nombre <input type="text" id="ant-prop-nombre" maxlength="150"></label>
                        <label class="ant-campo">Cargo <input type="text" id="ant-prop-cargo" maxlength="150"></label>
                    </div>
                    <div class="ant-fila" id="ant-prop-imagen-campos" hidden>
                        <label class="ant-campo">Giro (°) <input type="number" id="ant-prop-rot" min="-360" max="360" step="1"></label>
                        <label class="ant-campo">Opacidad <input type="range" id="ant-prop-opacidad" min="10" max="100"></label>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" id="ant-prop-aplicar"><i class="fa-solid fa-check"></i> Aplicar cambios</button>
                </div>
            </div>
            <div class="ant-panel-cont" id="ant-p-medidas" data-panel="medidas" hidden>
                <p class="text-sm text-muted" id="ant-medidas-vacio">Aún no hay medidas. Calibra la página y dibuja una línea, un área o un polígono.</p>
                <div class="ant-medidas-acc">
                    <button type="button" class="btn btn-sm btn-ghost" id="ant-calibrar"><i class="fa-solid fa-ruler-combined"></i> Calibrar página</button>
                    <span class="ant-escala text-sm text-muted" id="ant-escala">Sin escala</span>
                </div>
                <div class="ant-tabla-wrap">
                    <table class="ant-tabla" id="ant-medidas-tabla">
                        <thead><tr><th>Pág.</th><th>Tipo</th><th>Medida</th><th>Perímetro</th><th>Prof.</th><th>Volumen</th></tr></thead>
                        <tbody></tbody>
                        <tfoot><tr><th colspan="2">Totales</th><th id="ant-total-area">—</th><th colspan="2"></th><th id="ant-total-vol">—</th></tr></tfoot>
                    </table>
                </div>
                <div class="ant-medidas-acc">
                    <button type="button" class="btn btn-sm btn-ghost" id="ant-medidas-csv"><i class="fa-solid fa-file-csv"></i> CSV</button>
                    <button type="button" class="btn btn-sm btn-ghost" id="ant-medidas-copiar"><i class="fa-solid fa-copy"></i> Copiar</button>
                </div>
            </div>
            <div class="ant-panel-cont" id="ant-p-insertar" data-panel="insertar" hidden>
                <div class="pn-secc">
                    <h4 class="pn-tit"><i class="fa-solid fa-image"></i> Imagen</h4>
                    <p class="text-sm text-muted">Foto, detalle o captura del celular.</p>
                    <input type="file" id="ant-imagen-file" accept="image/*" capture="environment">
                    <button type="button" class="btn btn-sm btn-ghost" id="ant-imagen-subir"><i class="fa-solid fa-upload"></i> Subir imagen</button>
                    <div class="ant-miniaturas" id="ant-imagenes-lista"></div>
                </div>
                <div class="pn-secc">
                    <h4 class="pn-tit"><i class="fa-solid fa-signature"></i> Firma</h4>
                    <p class="text-sm text-muted">Dibuja, escribe o elige una firma guardada.</p>
                    <button type="button" class="btn btn-sm btn-ghost" id="ant-firma-nueva"><i class="fa-solid fa-pen-nib"></i> Nueva firma</button>
                    <div class="ant-firmas-lista" id="ant-firmas-lista"></div>
                </div>
                <div class="pn-secc">
                    <h4 class="pn-tit"><i class="fa-solid fa-stamp"></i> Estampas inteligentes</h4>
                    <p class="text-sm text-muted">Se llenan con datos del proyecto y se actualizan cuando cambian.</p>
                    <div class="ant-estampas-lista" id="ant-estampas-lista"></div>
                </div>
                <div class="pn-secc">
                    <h4 class="pn-tit"><i class="fa-solid fa-text-height"></i> Resaltar texto</h4>
                    <p class="text-sm text-muted" id="ant-texto-ayuda">Disponible en PDF con texto seleccionable. En planos escaneados usa el resaltador rectangular.</p>
                    <div class="ant-ins-acc">
                        <button type="button" class="btn btn-sm btn-ghost" data-tool="resaltado_texto"><i class="fa-solid fa-text-height"></i> Resaltar texto</button>
                        <button type="button" class="btn btn-sm btn-ghost" data-tool="subrayado"><i class="fa-solid fa-underline"></i> Subrayar</button>
                        <button type="button" class="btn btn-sm btn-ghost" data-tool="tachado"><i class="fa-solid fa-strikethrough"></i> Tachar</button>
                    </div>
                </div>
            </div>
        </aside>
    </div>
    <div class="ant-pie">
        <div class="ant-pag-nav">
            <button type="button" class="btn btn-xs btn-ghost" id="ant-pag-prev" title="Página anterior" aria-label="Página anterior"><i class="fa-solid fa-chevron-left"></i></button>
            <input type="number" id="ant-pag" min="1" value="1" aria-label="Página">
            <span class="text-sm text-muted" id="ant-pag-total">de 1</span>
            <button type="button" class="btn btn-xs btn-ghost" id="ant-pag-next" title="Página siguiente" aria-label="Página siguiente"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
        <span class="text-sm text-muted" id="ant-estado">Sin documento</span>
        <span class="text-sm text-muted" id="ant-escala-pie">Sin escala</span>
        <span class="ant-aviso-final" id="ant-finalizado" hidden><i class="fa-solid fa-lock"></i> Firmado y bloqueado</span>
        <button type="button" class="btn btn-xs btn-ghost" id="ant-reabrir" hidden title="Reabrir documento firmado (solo administrador)" aria-label="Reabrir documento firmado"><i class="fa-solid fa-lock-open"></i> Reabrir</button>
        <a class="btn btn-xs btn-ghost ant-pie-volver" href="/anotador?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-arrow-left"></i> Documentos</a>
    </div>
</div>

<textarea id="ant-txt" class="ant-txt" hidden aria-label="Texto de la anotación"></textarea>

<dialog id="ant-dlg-guardar" class="ant-dlg" aria-labelledby="ant-dlg-guardar-tit">
    <form method="dialog" id="ant-form-guardar">
        <h3 id="ant-dlg-guardar-tit"><i class="fa-solid fa-floppy-disk"></i> Guardar documento</h3>
        <label class="ant-campo">Nombre <input type="text" id="ant-g-nombre" maxlength="150" required></label>
        <label class="ant-campo">Descripción <textarea id="ant-g-desc" rows="2" maxlength="500"></textarea></label>
        <p class="text-sm text-muted">Se guarda una versión anterior cada vez que vuelvas a guardar (máximo 20).</p>
        <div class="ant-dlg-acc">
            <button type="button" class="btn btn-ghost" data-cerrar-dlg>Cancelar</button>
            <button type="button" class="btn btn-primary" id="ant-g-confirmar"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </div>
    </form>
</dialog>

<dialog id="ant-dlg-imagen" class="ant-dlg" aria-labelledby="ant-dlg-imagen-tit">
    <form method="dialog" id="ant-form-imagen">
        <h3 id="ant-dlg-imagen-tit"><i class="fa-solid fa-image"></i> Imagen para insertar</h3>
        <div class="ant-img-preview" id="ant-img-preview"><i class="fa-regular fa-image"></i></div>
        <label class="ant-campo">Archivo <input type="file" id="ant-img-file2" accept="image/*"></label>
        <div class="ant-dlg-acc">
            <button type="button" class="btn btn-ghost" data-cerrar-dlg>Cancelar</button>
            <button type="button" class="btn btn-primary" id="ant-img-confirmar"><i class="fa-solid fa-arrow-pointer"></i> Colocar en la página</button>
        </div>
    </form>
</dialog>

<dialog id="ant-dlg-firma" class="ant-dlg ant-dlg-firma" aria-labelledby="ant-dlg-firma-tit">
    <form method="dialog" id="ant-form-firma">
        <h3 id="ant-dlg-firma-tit"><i class="fa-solid fa-signature"></i> Nueva firma</h3>
        <div class="ant-firma-tabs" role="tablist">
            <button type="button" class="pn-chip activo" data-ftab="dibujar" role="tab" aria-selected="true">Dibujar</button>
            <button type="button" class="pn-chip" data-ftab="escribir" role="tab" aria-selected="false">Escribir</button>
        </div>
        <div id="ant-firma-dibujar">
            <canvas id="ant-firma-canvas" width="560" height="200" aria-label="Dibuja tu firma con el dedo o el ratón"></canvas>
            <button type="button" class="btn btn-xs btn-ghost" id="ant-firma-limpiar"><i class="fa-solid fa-eraser"></i> Limpiar</button>
        </div>
        <div id="ant-firma-escribir" hidden>
            <input type="text" id="ant-firma-texto" maxlength="60" placeholder="Escribe tu nombre">
            <select id="ant-firma-fuente" aria-label="Estilo de la firma">
                <option value="cursive">Manuscrita</option>
                <option value="serif">Serif</option>
                <option value="sans-serif">Sans serif</option>
            </select>
            <canvas id="ant-firma-vista" width="560" height="120" aria-label="Vista previa de la firma"></canvas>
        </div>
        <div class="ant-fila">
            <label class="ant-campo">Nombre <input type="text" id="ant-firma-nombre" maxlength="150"></label>
            <label class="ant-campo">Cargo <input type="text" id="ant-firma-cargo" maxlength="150"></label>
        </div>
        <p class="ant-firma-error text-sm" id="ant-firma-error" hidden role="alert"></p>
        <div class="ant-dlg-acc">
            <button type="button" class="btn btn-ghost" data-cerrar-dlg>Cancelar</button>
            <button type="button" class="btn btn-ghost" id="ant-firma-solo"><i class="fa-solid fa-arrow-pointer"></i> Usar sin guardar</button>
            <button type="button" class="btn btn-primary" id="ant-firma-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar y usar</button>
        </div>
    </form>
</dialog>

<dialog id="ant-dlg-profundidad" class="ant-dlg" aria-labelledby="ant-dlg-prof-tit">
    <form method="dialog" id="ant-form-profundidad">
        <h3 id="ant-dlg-prof-tit"><i class="fa-solid fa-cube"></i> Profundidad del volumen</h3>
        <p class="text-sm text-muted">El área del polígono se multiplica por esta profundidad para obtener el volumen.</p>
        <label class="ant-campo">Profundidad en metros <input type="number" id="ant-prof-valor" min="0.01" max="10000" step="0.01" value="0.10" required></label>
        <div class="ant-dlg-acc">
            <button type="button" class="btn btn-ghost" data-cerrar-dlg>Cancelar</button>
            <button type="button" class="btn btn-primary" id="ant-prof-confirmar">Calcular volumen</button>
        </div>
    </form>
</dialog>

<dialog id="ant-dlg-estampa" class="ant-dlg" aria-labelledby="ant-dlg-estampa-tit">
    <form method="dialog">
        <h3 id="ant-dlg-estampa-tit"><i class="fa-solid fa-stamp"></i> Estampa inteligente</h3>
        <div class="ant-estampas-dlg" id="ant-estampas-dlg"></div>
        <div class="ant-dlg-acc">
            <button type="button" class="btn btn-ghost" data-cerrar-dlg>Cancelar</button>
        </div>
    </form>
</dialog>

<dialog id="ant-dlg-versiones" class="ant-dlg" aria-labelledby="ant-dlg-versiones-tit">
    <form method="dialog">
        <h3 id="ant-dlg-versiones-tit"><i class="fa-solid fa-clock-rotate-left"></i> Historial de versiones</h3>
        <div id="ant-versiones-lista" class="ant-versiones-lista"></div>
        <div class="ant-dlg-acc"><button type="button" class="btn btn-ghost" data-cerrar-dlg>Cerrar</button></div>
    </form>
</dialog>

<script>
window.ANT_CONFIG = {
    proyecto: <?php echo $pid; ?>,
    usuario: <?php echo json_encode($_SESSION['nombre'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>,
    usuarioId: <?php echo (int)($_SESSION['id'] ?? 0); ?>,
    puedeGuardar: <?php echo $puedeGuardar ? 'true' : 'false'; ?>,
    admin: <?php echo is_admin() ? 'true' : 'false'; ?>,
    abrir: <?php echo json_encode($abrir, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>,
    historial: <?php echo (int)$historial; ?>,
    urls: {
        fuente: '/anotador/fuente', guardar: '/anotador/guardar', imagen: '/anotador/imagen', firmas: '/anotador/firmas', firma: '/anotador/firma', firmar: '/anotador/firmar', reabrir: '/anotador/reabrir', versiones: '/anotador/versiones', restaurar: '/anotador/restaurar', estampas: '/anotador/estampas', estampa: '/anotador/estampa'
    }
};
</script>
<script src="/vendor/pdfjs/pdf.min.js"></script>
<script src="/vendor/jspdf/jspdf.umd.min.js"></script>
<script src="/vendor/anotaciones.js"></script>
<script src="/vendor/gestos-lienzo.js"></script>
<script src="/vendor/anotador.js"></script>
