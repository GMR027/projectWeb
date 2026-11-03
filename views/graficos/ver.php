<?php $q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$grafico->id;
$hayPng = array_filter($laminas, fn($l) => $l->marcas_png);
$lzAcciones = [];
if ($puedeEditar) $lzAcciones[] = ['url' => '/graficos/abrir?' . $q, 'texto' => s(t('Editar')), 'icono' => 'fa-pen', 'clase' => 'btn btn-primary'];
if ($puedeDescargar && $hayPng) {
    $lzAcciones[] = ['tipo' => 'html', 'html' => '<details class="gr-descargar"><summary class="btn btn-ghost"><i class="fa-solid fa-download"></i> ' . s(t('Descargar')) . '</summary><div class="gr-descargar-menu">'
        . '<a href="/graficos/descargar?' . $q . '&formato=png"><i class="fa-regular fa-image"></i> ' . (count($laminas) > 1 ? 'PNG (ZIP con todas las láminas)' : 'PNG') . '</a>'
        . '<a href="/graficos/descargar?' . $q . '&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>'
        . '<a href="/graficos/descargar?' . $q . '&formato=odp"><i class="fa-regular fa-file-powerpoint"></i> ' . s(t('Presentación .odp')) . '</a>'
        . '</div></details>'];
}
if ($puedeEditar && $hayPng) $lzAcciones[] = ['url' => '/graficos/abrir?' . $q . '#exportar', 'texto' => s(t('Otro formato o tamaño')), 'icono' => 'fa-file-export'];
$lz = [
    'icono' => 'fa-pen-ruler',
    'titulo' => $grafico->nombre,
    'subtitulo' => formatear_fecha($grafico->creado, 'd/m/Y H:i') . ' · ' . (int)$grafico->ancho . ' × ' . (int)$grafico->alto . ' px',
    'chips' => [
        ['icono' => 'fa-calendar', 'texto' => s(formatear_fecha($grafico->creado, 'd/m/Y'))],
        ['icono' => 'fa-expand', 'texto' => '<b>' . (int)$grafico->ancho . ' × ' . (int)$grafico->alto . '</b> px'],
        ['icono' => 'fa-layer-group', 'texto' => '<b>' . count($laminas) . '</b> ' . (count($laminas) === 1 ? 'lámina' : 'láminas')],
    ],
    'acciones' => array_merge($lzAcciones, [['url' => '/graficos?proyecto=' . (int)$proyecto->id, 'texto' => '← ' . s(t('Gráficos'))]]),
];
include __DIR__ . '/../lienzo/_cabecera.php';
?>
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
