<?php
$nGraficos = count($graficos);
$nLaminas = 0; foreach ($graficos as $g) $nLaminas += max(1, (int)$g->num_laminas);
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-pen-ruler"></i> <?php echo s(t('Editor')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s(t('dibuja croquis, diagramas, leyendas y planos libres')); ?></p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-pen-ruler"></i><b><?php echo $nGraficos; ?></b> <?php echo $nGraficos === 1 ? 'gráfico' : 'gráficos'; ?></span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-layer-group"></i><b><?php echo $nLaminas; ?></b> láminas</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-file-export"></i> PNG · PDF · ODP</span>
        </div>
    </div>
    <div class="la-controles">
        <a href="/graficos/categorias?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-tags"></i> <?php echo s(t('Categorías')); ?></a>
        <span class="vp-seg" role="group" aria-label="Vista de la lista">
            <button type="button" class="btn btn-sm btn-ghost" id="gr-vista-galeria" title="Tarjetas con la portada de cada gráfico"><i class="fa-solid fa-table-cells-large"></i> <span class="hide-m">Galería</span></button>
            <button type="button" class="btn btn-sm btn-ghost" id="gr-vista-tabla" title="Lista detallada"><i class="fa-solid fa-table-list"></i> <span class="hide-m">Lista</span></button>
        </span>
        <?php if ($puedeEditar): ?><a href="/graficos/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary btn-nuevo-grafico"<?php echo glosario('grafico'); ?>><i class="fa-solid fa-plus"></i> <?php echo s(t('Nuevo gráfico')); ?></a><?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/herramientas-proyecto.php'; pestanas_herramienta('planos', '/graficos', $proyecto); ?>

<?php if (!$graficos): ?>
    <div class="suite-panel"><?php echo estado_vacio('fa-pen-ruler', 'Aún no hay gráficos', 'Croquis, diagramas de conexiones, leyendas y planos que se dibujan con formas, líneas, textos y símbolos. Se exportan a PNG, PDF o presentación.',
        $puedeEditar ? ['texto' => 'Dibujar el primer gráfico', 'url' => '/graficos/crear?proyecto=' . s($proyecto->id), 'icono' => 'fa-plus'] : null,
        ['texto' => 'Biblioteca de símbolos', 'url' => '/simbolos?proyecto=' . s($proyecto->id), 'icono' => 'fa-shapes']); ?></div>
<?php else: ?>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Gráficos</small><strong><?php echo $nGraficos; ?></strong><span>croquis, diagramas y planos</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Láminas</small><strong><?php echo $nLaminas; ?></strong><span>hojas dentro de los gráficos</span></span>
    </div>
    <div class="suite-kpi <?php echo $categorias ? '' : 'is-warning'; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-tags" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Categorías</small><strong><?php echo count($categorias); ?></strong><span>filtros de la galería</span></span>
    </div>
    <div class="suite-kpi is-success">
        <span class="suite-kpi-icono"><i class="fa-solid fa-file-export" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Exportación</small><strong>PNG · PDF</strong><span>también presentación .odp</span></span>
    </div>
</div>

<?php if (!empty($categorias)): ?>
<?php // ---- Chips de categoría: filtran la galería y la lista sin recargar ---- ?>
<div class="pn-chips gr-cat-chips no-print" role="group" aria-label="<?php echo s(t('Filtrar por categoría')); ?>">
    <button type="button" class="pn-chip activo" data-cat="">Todo</button>
    <?php foreach ($categorias as $c): ?><button type="button" class="pn-chip" data-cat="<?php echo (int)$c->id; ?>"><?php echo s($c->nombre); ?></button><?php endforeach; ?>
    <button type="button" class="pn-chip" data-cat="0">Sin categoría</button>
</div>
<?php endif; ?>

<?php // ---- Galería: tarjetas con la portada y las acciones en un menú ---- ?>
<div class="gr-galeria" id="gr-galeria">
    <?php foreach ($graficos as $g): $q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$g->id; ?>
    <article class="gr-gal-card" data-categoria="<?php echo (int)$g->categoria_id; ?>">
        <a class="gr-gal-img" href="/graficos/ver?<?php echo $q; ?>" aria-label="Ver <?php echo s($g->nombre); ?>">
            <?php if ($g->portada): ?><img src="/uploads/vplan/<?php echo s($g->portada); ?>" alt="" loading="lazy"><?php else: ?><i class="fa-regular fa-image"></i><?php endif; ?>
            <?php if ((int)$g->num_laminas > 1): ?><span class="gr-gal-n"><?php echo (int)$g->num_laminas; ?> láminas</span><?php endif; ?>
        </a>
        <div class="gr-gal-cuerpo">
            <a class="gr-gal-titulo" href="/graficos/ver?<?php echo $q; ?>"><?php echo s($g->nombre); ?></a>
            <div class="text-sm text-muted"><?php echo s(formatear_fecha($g->creado, 'd/m/Y')); ?><?php echo $g->categoria_nombre ? ' · ' . s($g->categoria_nombre) : ''; ?></div>
            <div class="gr-gal-acc">
                <a href="/graficos/ver?<?php echo $q; ?>" class="btn btn-sm btn-ghost"><i class="fa-regular fa-eye"></i> <?php echo s(t('Ver')); ?></a>
                <?php if ($puedeEditar): ?><a href="/graficos/abrir?<?php echo $q; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen"></i> <?php echo s(t('Editar')); ?></a><?php endif; ?>
                <details class="gr-gal-menu">
                    <summary class="vp-ib" title="Más acciones"><i class="fa-solid fa-ellipsis-vertical"></i></summary>
                    <div class="gr-gal-menu-in">
                        <?php if ($puedeDescargar && $g->portada): ?>
                            <a href="/graficos/descargar?<?php echo $q; ?>&formato=png"><i class="fa-regular fa-image"></i> <?php echo (int)$g->num_laminas > 1 ? 'PNG (ZIP con todas las láminas)' : 'PNG'; ?></a>
                            <a href="/graficos/descargar?<?php echo $q; ?>&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>
                            <a href="/graficos/descargar?<?php echo $q; ?>&formato=odp"><i class="fa-regular fa-file-powerpoint"></i> <?php echo s(t('Presentación')); ?> .odp</a>
                        <?php endif; ?>
                        <?php if ($puedeEditar): ?>
                            <form method="POST" action="/graficos/duplicar"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>"><button type="submit"><i class="fa-regular fa-copy"></i> <?php echo s(t('Duplicar')); ?></button></form>
                            <form method="POST" action="/graficos/tipo"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>"><input type="hidden" name="tipo" value="simbologia"><button type="submit" title="Pasa este gráfico a Símbolos → Simbologías para usarlo en las láminas del Vplan"<?php echo glosario('simbologia'); ?>><i class="fa-solid fa-list-check"></i> Guardar como simbología</button></form>
                            <form method="POST" action="/graficos/eliminar" data-confirm="¿Eliminar «<?php echo s($g->nombre); ?>»?|Se borrarán también sus imágenes. Esta acción no se puede deshacer.|Sí, eliminar"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>"><button type="submit" class="peligro"><i class="fa-solid fa-trash-can"></i> <?php echo s(t('Eliminar')); ?></button></form>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
</div>

<?php // ---- Lista detallada (vista alternativa) ---- ?>
<div class="suite-panel" id="gr-tabla" hidden>
        <div class="tabla-wrapper">
        <table class="tabla tabla-editor" <?php echo pag_attr($proyecto); ?>>
            <thead><tr><th><?php echo s(t('Fecha')); ?></th><th><?php echo s(t('Nombre')); ?></th><th class="hide-m"><?php echo s(t('Categoría')); ?></th><th class="hide-m"><?php echo s(t('Tamaño')); ?></th><th><?php echo s(t('Acciones')); ?></th></tr></thead>
            <tbody>
            <?php foreach ($graficos as $g): $q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$g->id; ?>
                <tr data-categoria="<?php echo (int)$g->categoria_id; ?>">
                    <td class="mono"><?php echo s(formatear_fecha($g->creado, 'd/m/Y H:i')); ?></td>
                    <td>
                        <div class="gr-nombre">
                            <?php if ($g->portada): ?><img class="gr-mini" src="/uploads/vplan/<?php echo s($g->portada); ?>" alt="" loading="lazy"><?php else: ?><span class="gr-mini gr-mini-vacio"><i class="fa-regular fa-image"></i></span><?php endif; ?>
                            <div><strong><?php echo archivo_icono_html('png', 'Gráfico (imagen)'); ?> <?php echo s($g->nombre); ?></strong><?php if ((int)$g->num_laminas > 1): ?><br><small class="text-muted"><?php echo (int)$g->num_laminas; ?> láminas</small><?php endif; ?></div>
                        </div>
                    </td>
                    <td class="hide-m"><?php echo $g->categoria_nombre ? s($g->categoria_nombre) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="mono hide-m"><?php echo (int)$g->ancho; ?> × <?php echo (int)$g->alto; ?> px</td>
                    <td class="acciones">
                        <a href="/graficos/ver?<?php echo $q; ?>" class="btn btn-sm btn-ghost"><i class="fa-regular fa-eye"></i> <?php echo s(t('Ver')); ?></a>
                        <?php if ($puedeEditar): ?><a href="/graficos/abrir?<?php echo $q; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen"></i> <?php echo s(t('Editar')); ?></a><?php endif; ?>
                        <?php if ($puedeDescargar && $g->portada): ?>
                            <details class="gr-descargar">
                                <summary class="btn btn-sm btn-ghost"><i class="fa-solid fa-download"></i> <?php echo s(t('Descargar')); ?></summary>
                                <div class="gr-descargar-menu">
                                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=png"><i class="fa-regular fa-image"></i> <?php echo (int)$g->num_laminas > 1 ? 'PNG (ZIP con todas las láminas)' : 'PNG'; ?></a>
                                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>
                                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=odp"><i class="fa-regular fa-file-powerpoint"></i> Presentación .odp</a>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if ($puedeEditar): ?>
                            <form class="inline" method="POST" action="/graficos/duplicar">
                                <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>">
                                <button type="submit" class="btn btn-sm btn-ghost"><i class="fa-regular fa-copy"></i> <?php echo s(t('Duplicar')); ?></button>
                            </form>
                            <form class="inline" method="POST" action="/graficos/tipo">
                                <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>"><input type="hidden" name="tipo" value="simbologia">
                                <button type="submit" class="btn btn-sm btn-ghost" title="Pasa este gráfico a Símbolos → Simbologías para usarlo en las láminas del Vplan"><i class="fa-solid fa-list-check"></i> Guardar como simbología</button>
                            </form>
                            <form method="POST" action="/graficos/eliminar" style="display:inline" data-confirm="¿Eliminar «<?php echo s($g->nombre); ?>»?|Se borrarán también sus imágenes. Esta acción no se puede deshacer.|Sí, eliminar">
                                <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> <?php echo s(t('Eliminar')); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
</div>
<?php endif; ?>
<script>
(function () {
    const galeria = document.getElementById('gr-galeria'), tabla = document.getElementById('gr-tabla'),
        bG = document.getElementById('gr-vista-galeria'), bT = document.getElementById('gr-vista-tabla');
    if (!galeria || !bG || !bT) return;
    const ver = k => {
        galeria.hidden = k !== 'galeria';
        if (tabla) tabla.hidden = k === 'galeria';
        bG.classList.toggle('btn-primary', k === 'galeria');
        bT.classList.toggle('btn-primary', k !== 'galeria');
        try { localStorage.setItem('gr-vista', k); } catch (e) { /* sin almacenamiento */ }
    };
    bG.addEventListener('click', () => ver('galeria'));
    bT.addEventListener('click', () => ver('lista'));
    let inicial = 'galeria'; try { inicial = localStorage.getItem('gr-vista') || 'galeria'; } catch (e) { /* idem */ }
    ver(inicial);
    // El menú ⋮ de cada tarjeta se cierra al hacer clic fuera
    document.addEventListener('click', e => {
        document.querySelectorAll('.gr-gal-menu[open]').forEach(m => { if (!m.contains(e.target)) m.removeAttribute('open'); });
    });
    // Chips de categoría: filtran la galería y la lista (se recuerda por proyecto)
    const chips = document.querySelectorAll('.gr-cat-chips [data-cat]');
    if (chips.length) {
        const clave = 'gr-cat-<?php echo (int)$proyecto->id; ?>';
        const filtrar = cat => {
            chips.forEach(c => c.classList.toggle('activo', c.dataset.cat === cat));
            document.querySelectorAll('[data-categoria]').forEach(el => { el.hidden = cat !== '' && el.dataset.categoria !== cat; });
            try { localStorage.setItem(clave, cat); } catch (e) { /* sin almacenamiento */ }
        };
        chips.forEach(c => c.addEventListener('click', () => filtrar(c.dataset.cat)));
        let ini = ''; try { ini = localStorage.getItem(clave) || ''; } catch (e) { /* idem */ }
        if (ini && [...chips].some(c => c.dataset.cat === ini)) filtrar(ini);
    }
})();
</script>
