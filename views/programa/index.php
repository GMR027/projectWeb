<?php
/**
 * Programa de obra: hero + KPIs, barra de filtros, tabla WBS y Gantt.
 * Las piezas viven en parciales para no convertir esta vista en un archivo de 70 KB.
 * Conserva los ganchos que usan app.js (_workspace, _movil y edición en línea).
 */
$puedeGestionar = puede_gestionar_proyecto($proyecto);
$puedeBorrar = puede_borrar_contenido($proyecto);
$texto = $texto ?? '';
$vista = $vista ?? 'ambos';
$resumen = $resumen ?? [];
$mostrarSub = !empty($mostrarSub);
$pg = $propuestasGuardadas ?? [];
$alertasObra = [];
try {
    $alertasObra = array_slice(\Classes\DatosObra::alertas((int)$proyecto->id), 0, 3);
} catch (\Throwable $e) {
    $alertasObra = [];
}
?>
<div class="prg">
    <?php include __DIR__ . '/_hero.php'; ?>
    <?php include __DIR__ . '/_barra.php'; ?>
    <?php if ($vista !== 'gantt') include __DIR__ . '/_tabla.php'; ?>
    <?php if ($vista !== 'tabla') include __DIR__ . '/_gantt.php'; ?>
    <?php include __DIR__ . '/_modales.php'; ?>
    <?php include __DIR__ . '/_scripts.php'; ?>
</div>
