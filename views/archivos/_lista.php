<?php
/** Lista de archivos en cuadrícula o lista, con selección y barra de mover. */
$totalFiltrados = count($archivos);
$seccionTitulo = $recientes ? 'Últimos subidos'
    : ($carpetaActual ? 'Archivos en ' . $carpetaActual->nombre
    : ($carpeta === 0 ? 'Sin carpeta'
    : ($filtro ? 'Resultados por categoría' : 'Todos los archivos')));
$seccionIcono = $recientes ? 'fa-clock-rotate-left' : ($carpetaActual || $carpeta === 0 ? 'fa-folder-open' : 'fa-file-lines');
$seccionSub = $totalFiltrados . ' archivo(s)' . ($totalFiltrados !== (int)$totalArchivos && ($filtro || $recientes || $carpeta !== null) ? ' de ' . (int)$totalArchivos : '');
$vistaInicialLista = $vistaInicial;
$carpetaSubida = $carpetaActual ? '&carpeta=' . (int)$carpetaActual->id : '';
?>
<section class="archivo-seccion archivo-seccion-archivos" aria-labelledby="archivo-archivos-titulo">
    <div class="archivo-seccion-cab">
        <div>
            <h3 id="archivo-archivos-titulo"><i class="fa-solid <?php echo $seccionIcono; ?>" aria-hidden="true"></i> <?php echo s($seccionTitulo); ?></h3>
            <p class="text-muted text-sm"><?php echo s($seccionSub); ?></p>
        </div>
        <span class="archivo-contador" id="arch-resultados"><?php echo $totalFiltrados; ?> archivo(s)</span>
    </div>

    <?php if ($puede && $archivos): ?>
        <div class="archivo-lista-cab">
            <label class="archivo-seleccionar">
                <input type="checkbox" id="arch-todos" aria-label="Seleccionar todos los archivos visibles">
                <span>Seleccionar todos</span>
            </label>
            <span class="archivo-seleccion-ayuda text-muted text-sm">Puedes mover la selección a una carpeta.</span>
        </div>
    <?php endif; ?>

    <?php if (!$archivos): ?>
        <?php
        $__donde = $recientes ? ' en Recientes' : ($carpetaActual ? ' en esta carpeta' : ($carpeta === 0 ? ' sin carpeta' : ''));
        $__cat = $filtro ? ' en esta categoría' : '';
        echo estado_vacio(
            $recientes ? 'fa-clock-rotate-left' : 'fa-folder-open',
            'No hay archivos' . $__donde . $__cat,
            $puede ? 'Sube el primero con «Subir archivo» o cambia los filtros.' : 'Cuando el equipo suba documentos aparecerán aquí.',
            $puede ? ['texto' => 'Subir archivo', 'url' => '/archivos/crear?proyecto=' . $id . $carpetaSubida, 'icono' => 'fa-upload'] : null,
            null, '', 'estado-vacio-sm'
        );
        ?>
    <?php else: ?>
        <div class="archivos-lista vista-<?php echo $vistaInicialLista === 'lista' ? 'lista' : 'cuadricula'; ?>"
             id="arch-lista" role="list" <?php echo pag_attr($proyecto); ?> data-vista-inicial="<?php echo s($vistaInicialLista); ?>">
            <?php foreach ($archivos as $a):
                $ext = strtolower($a->extension());
                $tipoVista = $desc ? \Classes\VistaArchivo::tipo($ext) : null;
                $puedeFila = puede_gestionar_archivo($a, $proyecto);
                $catNombre = (string)($a->categoria_nombre ?? '');
                $carpetaNombre = $a->carpeta_id && isset($nombresCarpeta[(int)$a->carpeta_id]) ? $nombresCarpeta[(int)$a->carpeta_id] : '';
                $verVisor = $tipoVista ? '/visor?proyecto=' . $id . '&archivo=' . s($a->id) : '';
                $verDirecto = $desc ? '/archivos/ver?proyecto=' . $id . '&id=' . s($a->id) : '';
                $descUrl = $desc ? '/archivos/descargar?proyecto=' . $id . '&id=' . s($a->id) : '';
                $editUrl = $puedeFila ? '/archivos/editar?proyecto=' . $id . '&id=' . s($a->id) : '';
                $eliminar = $borra && $puedeFila;
                $esImagen = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','tif','tiff'], true);
                $buscar = mb_strtolower(implode(' ', array_filter([
                    (string)$a->nombre, (string)$a->archivo_original, $catNombre, $ext,
                    (string)($a->subido_por_nombre ?? ''), $carpetaNombre, (string)($a->numero_revision ?? ''),
                ])), 'UTF-8');
                $docKey = archivo_clave_documento((string)$a->nombre);
                $nombreSeguro = s((string)$a->nombre);
            ?>
            <article class="archivo-card<?php echo $puede ? ' con-seleccion' : ''; ?><?php echo $eliminar ? '' : ' sin-eliminar'; ?>"
                     role="listitem"
                     draggable="<?php echo $puede ? 'true' : 'false'; ?>"
                     data-id="<?php echo s($a->id); ?>"
                     data-buscar="<?php echo s($buscar); ?>"
                     data-doc-key="<?php echo s($docKey); ?>"
                     data-nombre="<?php echo $nombreSeguro; ?>"
                     data-original="<?php echo s((string)$a->archivo_original); ?>"
                     data-categoria="<?php echo s($catNombre); ?>"
                     data-carpeta="<?php echo s($carpetaNombre); ?>"
                     data-carpeta-id="<?php echo (int)($a->carpeta_id ?? 0); ?>"
                     data-revision="<?php echo s((string)($a->numero_revision ?? '')); ?>"
                     data-fecha="<?php echo s(fecha_corta_es($a->fecha)); ?>"
                     data-fecha-iso="<?php echo s((string)$a->fecha); ?>"
                     data-tamano="<?php echo s($a->tamanoLegible()); ?>"
                     data-subido="<?php echo s((string)($a->subido_por_nombre ?? '')); ?>"
                     data-ext="<?php echo s($ext); ?>"
                     data-ver="<?php echo s($verVisor); ?>"
                     data-preview="<?php echo s($esImagen ? $verDirecto : ''); ?>"
                     data-descargar="<?php echo s($descUrl); ?>"
                     data-editar="<?php echo s($editUrl); ?>"
                     data-eliminar="<?php echo $eliminar ? '1' : '0'; ?>"
                     data-ensamblado="<?php echo isset($ensamblados[(int)$a->id]) ? '1' : '0'; ?>">
                <?php if ($puede): ?>
                    <label class="archivo-check" title="Seleccionar <?php echo $nombreSeguro; ?>">
                        <input type="checkbox" class="arch-sel" name="ids[]" form="arch-mover" value="<?php echo s($a->id); ?>" aria-label="Seleccionar <?php echo $nombreSeguro; ?>">
                    </label>
                <?php endif; ?>

                <span class="archivo-tipo <?php echo s(archivo_icono_clase($ext)); ?>" aria-hidden="true">
                    <?php echo archivo_icono_html($ext, (string)$a->archivo_original); ?>
                    <em><?php echo s($ext !== '' ? strtoupper($ext) : 'FILE'); ?></em>
                </span>

                <div class="archivo-txt">
                    <h4 class="archivo-nombre">
                        <?php if ($verVisor): ?><a href="<?php echo s($verVisor); ?>"><?php echo $nombreSeguro; ?></a><?php else: ?><?php echo $nombreSeguro; ?><?php endif; ?>
                    </h4>
                    <p class="archivo-original mono" title="<?php echo s((string)$a->archivo_original); ?>"><?php echo s((string)$a->archivo_original); ?></p>
                    <div class="archivo-meta">
                        <?php if ($catNombre !== ''): ?><span class="archivo-chip archivo-chip-cat"><i class="fa-solid fa-tag" aria-hidden="true"></i> <?php echo s($catNombre); ?></span><?php endif; ?>
                        <?php if (!empty($a->numero_revision)): ?><span class="archivo-chip archivo-chip-rev">Rev. <?php echo s($a->numero_revision); ?></span><?php endif; ?>
                        <span class="archivo-chip"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?php echo s(fecha_corta_es($a->fecha)); ?></span>
                        <span class="archivo-chip mono"><i class="fa-solid fa-weight-hanging" aria-hidden="true"></i> <?php echo s($a->tamanoLegible()); ?></span>
                        <?php if (!empty($a->subido_por_nombre)): ?>
                            <span class="archivo-autor" title="Subido por <?php echo s($a->subido_por_nombre); ?>">
                                <span class="archivo-avatar" aria-hidden="true"><?php echo s(iniciales_nombre($a->subido_por_nombre)); ?></span>
                                <?php echo s($a->subido_por_nombre); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($carpeta === null && !$recientes && $carpetaNombre !== ''): ?>
                            <span class="archivo-chip archivo-chip-carpeta"><i class="fa-solid fa-folder" aria-hidden="true"></i> <?php echo s($carpetaNombre); ?></span>
                        <?php endif; ?>
                        <?php if (isset($ensamblados[(int)$a->id])): ?>
                            <span class="archivo-chip archivo-chip-ensamble" title="Se regenera solo cuando cambia alguno de sus documentos"><i class="fa-solid fa-object-group" aria-hidden="true"></i> Ensamblado</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="archivo-acciones">
                    <?php if ($verVisor): ?>
                        <a href="<?php echo s($verVisor); ?>" class="btn btn-sm btn-ghost" title="Ver <?php echo $nombreSeguro; ?>" aria-label="Ver <?php echo $nombreSeguro; ?>">
                            <i class="fa-regular fa-eye" aria-hidden="true"></i><span class="solo-movil">Ver</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($descUrl): ?>
                        <a href="<?php echo s($descUrl); ?>" class="btn btn-sm btn-ghost" title="Descargar <?php echo $nombreSeguro; ?>" aria-label="Descargar <?php echo $nombreSeguro; ?>">
                            <i class="fa-solid fa-download" aria-hidden="true"></i><span class="solo-movil">Descargar</span>
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-ghost archivo-detalle-btn" title="Detalles de <?php echo $nombreSeguro; ?>" aria-label="Detalles de <?php echo $nombreSeguro; ?>" aria-expanded="false">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i><span class="solo-movil">Detalles</span>
                    </button>
                    <?php if ($editUrl): ?>
                        <a href="<?php echo s($editUrl); ?>" class="btn btn-sm btn-ghost hide-m" title="Editar metadatos de <?php echo $nombreSeguro; ?>" aria-label="Editar <?php echo $nombreSeguro; ?>">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($eliminar): ?>
                        <form method="POST" action="/archivos/eliminar" class="archivo-eliminar" data-confirm="¿Eliminar archivo?|Se borrará «<?php echo $nombreSeguro; ?>» del servidor. Esta acción no se puede deshacer.|Eliminar">
                            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                            <input type="hidden" name="id" value="<?php echo s($a->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar <?php echo $nombreSeguro; ?>" aria-label="Eliminar <?php echo $nombreSeguro; ?>">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i><span class="solo-movil">Eliminar</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
            <div class="archivos-sin-resultados sin-resultados" id="arch-sin-resultados" hidden>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <p><strong>No hay coincidencias.</strong> Prueba con otro nombre, categoría o autor.</p>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if ($puede && $archivos): ?>
<div class="archivo-bulk" id="archivo-bulk" hidden>
    <form method="POST" action="/archivos/mover" id="arch-mover" class="archivo-bulk-form">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <span class="archivo-bulk-info"><strong id="arch-n">0</strong> seleccionado(s)</span>
        <select name="carpeta_id" aria-label="Mover a carpeta">
            <option value="">— Sin carpeta —</option>
            <?php foreach ($carpetas as $k): ?><option value="<?php echo s($k->id); ?>"><?php echo s($k->nombre); ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary" id="arch-mover-btn" disabled><i class="fa-solid fa-folder-tree" aria-hidden="true"></i> Mover</button>
        <button type="button" class="btn btn-sm btn-ghost" id="arch-limpiar">Limpiar</button>
    </form>
</div>
<?php endif; ?>

<?php if ($puede): ?>
<a href="/archivos/crear?proyecto=<?php echo $id; ?><?php echo $carpetaSubida; ?>" class="archivo-fab" title="Subir archivo" aria-label="Subir archivo">
    <i class="fa-solid fa-upload" aria-hidden="true"></i><span class="archivo-fab-txt">Subir</span>
</a>
<?php endif; ?>
