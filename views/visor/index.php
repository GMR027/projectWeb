<?php
/**
 * Visor de archivos: lista lo que hay en el Archivo del proyecto y lo muestra en la misma ventana.
 * Variables: $proyecto, $categorias, $filtro, $carpetas, $carpeta, $sinCarpeta, $archivos,
 *            $totalArchivos, $ver (Archivo), $puede (puede_descargar), $buscar
 */
$id = (int)$proyecto->id;
$nombresCarpeta = [];
foreach ($carpetas as $k) $nombresCarpeta[(int)$k->id] = $k->nombre;
$nombreCarpeta = fn(?int $c) => $c === null ? null : ($nombresCarpeta[$c] ?? null);
$valorCarpeta = fn(?int $c) => $c === null ? '' : ($c === 0 ? 'sin' : $c);
$q = fn(array $extra = []) => '/visor?' . http_build_query(array_filter(array_merge([
    'proyecto' => $id,
    'carpeta' => $valorCarpeta($carpeta),
    'categoria' => $filtro ?: '',
    'buscar' => $buscar,
], $extra), fn($v) => $v !== '' && $v !== null));
$urlVer = fn($a) => $a ? '/archivos/ver?proyecto=' . $id . '&id=' . (int)$a->id : '';
$tipoVer = $ver ? \Classes\VistaArchivo::tipo($ver->extension()) : null;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-eye"></i> <?php echo s(t('Visor')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · mira los archivos del Archivo sin salir de esta ventana</p>
    </div>
    <div class="la-controles">
        <a href="/archivos?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-folder-open"></i> <?php echo s(t('Ir al Archivo')); ?></a>
    </div>
</div>

<div class="visor">
    <aside class="visor-lateral card">
        <form method="GET" action="/visor" class="visor-filtros">
            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
            <?php if ($carpeta !== null): ?><input type="hidden" name="carpeta" value="<?php echo s($valorCarpeta($carpeta)); ?>"><?php endif; ?>
            <?php if ($filtro): ?><input type="hidden" name="categoria" value="<?php echo (int)$filtro; ?>"><?php endif; ?>
            <div class="campo"><label for="vs-buscar"><?php echo s(t('Buscar')); ?></label>
                <input type="search" id="vs-buscar" name="buscar" value="<?php echo s($buscar); ?>" placeholder="Nombre del archivo…"></div>
            <div class="campo"><label for="vs-cat"><?php echo s(t('Categoría')); ?></label>
                <select id="vs-cat" name="categoria" onchange="this.form.submit()">
                    <option value=""><?php echo s(t('Todas las categorías')); ?></option>
                    <?php foreach ($categorias as $c): ?><option value="<?php echo s($c->id); ?>" <?php echo (int)$filtro === (int)$c->id ? 'selected' : ''; ?>><?php echo s($c->nombre); ?></option><?php endforeach; ?>
                </select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> <?php echo s(t('Filtrar')); ?></button>
            <?php if ($buscar !== '' || $filtro): ?><a href="<?php echo s($q(['buscar' => '', 'categoria' => '', 'archivo' => null])); ?>" class="btn btn-ghost btn-sm"><?php echo s(t('Limpiar')); ?></a><?php endif; ?>
        </form>

        <ul class="visor-carpetas">
            <li><a href="<?php echo s($q(['carpeta' => '', 'archivo' => null])); ?>" class="<?php echo $carpeta === null ? 'activa' : ''; ?>"><i class="fa-solid fa-layer-group"></i> <span><?php echo s(t('Todos')); ?></span> <em><?php echo (int)$totalArchivos; ?></em></a></li>
            <li><a href="<?php echo s($q(['carpeta' => 'sin', 'archivo' => null])); ?>" class="<?php echo $carpeta === 0 ? 'activa' : ''; ?>"><i class="fa-regular fa-file"></i> <span><?php echo s(t('Sin carpeta')); ?></span> <em><?php echo (int)$sinCarpeta; ?></em></a></li>
            <?php foreach ($carpetas as $k): ?>
                <li><a href="<?php echo s($q(['carpeta' => (int)$k->id, 'archivo' => null])); ?>" class="<?php echo $carpeta === (int)$k->id ? 'activa' : ''; ?>"><i class="fa-solid fa-folder"></i> <span><?php echo s($k->nombre); ?></span> <em><?php echo (int)$k->total; ?></em></a></li>
            <?php endforeach; ?>
        </ul>

        <div class="visor-lista" id="vs-lista">
            <?php if (!$archivos): ?>
                <p class="text-muted text-sm"><?php echo s(t('No hay archivos con estos filtros.')); ?></p>
            <?php else: ?>
                <?php foreach ($archivos as $i => $a): ?>
                    <?php $activo = $ver && (int)$ver->id === (int)$a->id; ?>
                    <a class="visor-item <?php echo $activo ? 'activo' : ''; ?>" href="<?php echo s($q(['archivo' => (int)$a->id])); ?>" data-indice="<?php echo $i; ?>">
                        <span class="visor-item-ico"><?php echo archivo_icono_html($a->extension()); ?></span>
                        <span class="visor-item-txt">
                            <strong><?php echo s($a->nombre); ?></strong>
                            <span class="text-sm text-muted mono"><?php echo s($a->archivo_original); ?> · <?php echo s($a->tamanoLegible()); ?></span>
                            <span class="text-sm text-muted"><?php echo s(fecha_corta_es($a->fecha)); ?><?php echo $a->categoria_nombre ? ' · ' . s($a->categoria_nombre) : ''; ?><?php echo $nombreCarpeta((int)$a->carpeta_id) ? ' · ' . s($nombreCarpeta((int)$a->carpeta_id)) : ''; ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <section class="visor-principal card">
        <div class="visor-barra">
            <button type="button" class="btn btn-sm btn-ghost" id="vs-ant" title="Anterior" aria-label="Archivo anterior" disabled><i class="fa-solid fa-chevron-left"></i></button>
            <button type="button" class="btn btn-sm btn-ghost" id="vs-sig" title="Siguiente" aria-label="Archivo siguiente" disabled><i class="fa-solid fa-chevron-right"></i></button>
            <div class="visor-barra-nombre">
                <?php if ($ver): ?>
                    <?php echo archivo_icono_html($ver->extension()); ?>
                    <strong><?php echo s($ver->nombre); ?></strong>
                    <span class="text-sm text-muted mono"><?php echo s($ver->archivo_original); ?></span>
                <?php else: ?>
                    <span class="text-muted"><?php echo s(t('Ningún archivo seleccionado')); ?></span>
                <?php endif; ?>
            </div>
            <div class="la-controles">
                <?php if ($ver && $puede): ?>
                    <a href="<?php echo s($urlVer($ver)); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost"><i class="fa-solid fa-arrow-up-right-from-square"></i> <?php echo s(t('Abrir en otra pestaña')); ?></a>
                    <a href="/archivos/descargar?proyecto=<?php echo $id; ?>&id=<?php echo (int)$ver->id; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-download"></i> <?php echo s(t('Descargar')); ?></a>
                <?php endif; ?>
            </div>
        </div>

        <div class="visor-marco" id="vs-marco">
            <?php if ($ver && $puede && $tipoVer): ?>
                <iframe src="<?php echo s($urlVer($ver)); ?>" title="<?php echo s($ver->nombre); ?>" id="vs-frame" allowfullscreen></iframe>
            <?php elseif ($ver && !$puede): ?>
                <div class="visor-vacio">
                    <i class="fa-solid fa-lock"></i>
                    <p><?php echo s(t('Necesitas permiso de descarga (administrador o cliente) para ver los archivos.')); ?></p>
                </div>
            <?php elseif ($ver && !$tipoVer): ?>
                <div class="visor-vacio">
                    <i class="fa-solid fa-file-circle-question"></i>
                    <p><?php echo s(t('Este archivo no se puede mostrar en el navegador.')); ?></p>
                    <p class="text-sm text-muted"><?php echo s(t('Descárgalo para abrirlo en tu equipo.')); ?></p>
                    <?php if ($puede): ?><a href="/archivos/descargar?proyecto=<?php echo $id; ?>&id=<?php echo (int)$ver->id; ?>" class="btn btn-primary"><i class="fa-solid fa-download"></i> <?php echo s(t('Descargar')); ?></a><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="visor-vacio">
                    <i class="fa-regular fa-eye"></i>
                    <p><?php echo s(t('Elige un archivo de la lista para verlo aquí.')); ?></p>
                    <?php if (!$archivos): ?><p class="text-sm text-muted"><?php echo s(t('Todavía no hay archivos en el Archivo del proyecto.')); ?> <a href="/archivos?proyecto=<?php echo $id; ?>"><?php echo s(t('Subir el primero')); ?></a>.</p><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
(() => {
    const items = [...document.querySelectorAll('.visor-item')];
    const marco = document.getElementById('vs-marco');
    const frame = document.getElementById('vs-frame');
    const ant = document.getElementById('vs-ant');
    const sig = document.getElementById('vs-sig');
    if (!items.length) return;
    const ir = (i) => { if (i >= 0 && i < items.length) items[i].click(); };
    const actual = items.findIndex((e) => e.classList.contains('activo'));
    const mover = (delta) => ir(actual + delta);
    ant.disabled = actual <= 0;
    sig.disabled = actual < 0 || actual >= items.length - 1;
    ant.addEventListener('click', () => mover(-1));
    sig.addEventListener('click', () => mover(1));
    document.addEventListener('keydown', (e) => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
        if (e.key === 'ArrowLeft') mover(-1);
        if (e.key === 'ArrowRight') mover(1);
    });
    // El visor de PDF del navegador carga despacio: mostramos un aviso mientras tanto
    if (frame) frame.addEventListener('load', () => {});
})();
</script>
