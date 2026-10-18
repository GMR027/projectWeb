<?php $q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$grafico->id; ?>
<div class="page-header">
    <div>
        <h2><?php echo archivo_icono_html('png'); ?> <?php echo s($grafico->nombre); ?></h2>
        <p class="text-muted"><?php echo s(formatear_fecha($grafico->creado, 'd/m/Y H:i')); ?> · <?php echo (int)$grafico->ancho; ?> × <?php echo (int)$grafico->alto; ?> px</p>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar): ?><a href="/graficos/abrir?<?php echo $q; ?>" class="btn btn-primary"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
        <?php $hayPng = array_filter($laminas, fn($l) => $l->marcas_png); ?>
        <?php if ($puedeDescargar && $hayPng): ?>
            <details class="gr-descargar">
                <summary class="btn btn-ghost"><i class="fa-solid fa-download"></i> Descargar</summary>
                <div class="gr-descargar-menu">
                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=png"><i class="fa-regular fa-image"></i> <?php echo count($laminas) > 1 ? 'PNG (ZIP con todas las láminas)' : 'PNG'; ?></a>
                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>
                    <a href="/graficos/descargar?<?php echo $q; ?>&formato=odp"><i class="fa-regular fa-file-powerpoint"></i> Presentación .odp</a>
                </div>
            </details>
        <?php endif; ?>
        <?php if ($puedeEditar && $hayPng): ?><a href="/graficos/abrir?<?php echo $q; ?>#exportar" class="btn btn-ghost"><i class="fa-solid fa-file-export"></i> Otro formato o tamaño</a><?php endif; ?>
        <a href="/graficos?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← Gráficos</a>
    </div>
</div>
<div class="card gr-vista">
    <?php if ($hayPng): ?>
        <div class="gr-laminas-galeria" style="<?php echo count($hayPng) === 1 ? 'display:block' : ''; ?>">
        <?php foreach ($laminas as $i => $l): if (!$l->marcas_png) continue; ?>
            <?php $lkW = $lkH = 0.0; $enl = \Classes\EnlacesLamina::de($l->marcas_json, $lkW, $lkH); ?>
            <figure><div class="vp-lk-wrap"><img src="/uploads/vplan/<?php echo s($l->marcas_png); ?>" alt="<?php echo s($grafico->nombre . ' · ' . $l->titulo($i + 1)); ?>" loading="lazy"><?php echo \Classes\EnlacesLamina::html($enl, $lkW, $lkH); ?></div><?php if (count($laminas) > 1): ?><figcaption><?php echo ($i + 1) . '. ' . s($l->titulo($i + 1)); ?></figcaption><?php endif; ?></figure>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">Este gráfico aún no tiene contenido guardado.<?php echo $puedeEditar ? ' Ábrelo con «Editar» y pulsa «Guardar».' : ''; ?></p>
    <?php endif; ?>
</div>
