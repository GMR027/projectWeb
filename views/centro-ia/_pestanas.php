<?php // Pestañas del Centro de IA. Variables: $proyecto, $pestana (resumen|propuestas|informes|grabaciones|historial|biblioteca|uso|calidad)
$pidTab = (int)$proyecto->id;
$tabsCentro = [
    'resumen' => ['/resumen-ia', 'fa-gauge-high', 'Resumen'],
    'propuestas' => ['/propuestas-ia', 'fa-inbox', 'Propuestas'],
    'informes' => ['/informes-ia', 'fa-file-lines', 'Informes'],
    'grabaciones' => ['/grabaciones', 'fa-microphone', 'Grabaciones'],
    'historial' => ['/historial-cambios', 'fa-clock-rotate-left', 'Historial de cambios'],
    'biblioteca' => ['/biblioteca-ia', 'fa-book-bookmark', 'Biblioteca de análisis'],
    'uso' => ['/uso-ia', 'fa-stopwatch', 'Uso de la IA'],
    'calidad' => ['/calidad-ia', 'fa-ranking-star', 'Calidad de la IA'],
];
?>
<nav class="centro-ia-tabs" aria-label="Centro de IA">
    <span class="centro-ia-tabs__titulo"><i class="fa-solid fa-robot"></i> Centro de IA</span>
    <?php foreach ($tabsCentro as $k => [$ruta, $icono, $nombre]): ?>
        <a href="<?php echo s($ruta . '?proyecto=' . $pidTab); ?>" class="centro-ia-tabs__tab<?php echo $k === $pestana ? ' activa' : ''; ?>"<?php echo $k === $pestana ? ' aria-current="page"' : ''; ?>><i class="fa-solid <?php echo $icono; ?>"></i> <?php echo s($nombre); ?></a>
    <?php endforeach; ?>
</nav>
