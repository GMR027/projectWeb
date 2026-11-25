<?php
$puede = puede_gestionar_proyecto($proyecto);
$desc = puede_descargar();
$id = s($proyecto->id);
$borra = puede_eliminar_contenido($proyecto);   // admin y cliente que gestiona; el colaborador no borra
$carpetaActual = null;
foreach ($carpetas as $k) if ($carpeta && (int)$k->id === (int)$carpeta) $carpetaActual = $k;

$recientes = ($vista ?? null) === 'recientes';
$carpetasStats = $carpetasStats ?? [];
$sinCarpetaStats = $sinCarpetaStats ?? ['total' => 0, 'bytes' => 0, 'ultima' => null];
$totalBytes = (int)($totalBytes ?? 0);
$limiteBytes = (int)($limiteBytes ?? 0);
$pct = $limiteBytes > 0 ? min(100, round($totalBytes / $limiteBytes * 100, 1)) : 0;
$ordenActual = $orden ?? 'fecha_desc';
$vistaInicial = ($carpeta !== null || $recientes) ? 'lista' : 'cuadricula';
$nombresCarpeta = [];
foreach ($carpetas as $k) $nombresCarpeta[(int)$k->id] = $k->nombre;
$carpetaParam = $carpeta === null ? null : ($carpeta === 0 ? 'sin' : (string)$carpeta);
$vistaParam = $recientes ? 'recientes' : null;

/** URL del archivo conservando proyecto, carpeta, categoría, orden y vista rápida. */
$urlArchivo = function (array $over = []) use ($id, $carpetaParam, $filtro, $ordenActual, $vistaParam) {
    $base = [
        'proyecto' => $id,
        'carpeta' => $carpetaParam,
        'categoria' => $filtro ? (string)$filtro : null,
        'orden' => $ordenActual !== 'fecha_desc' ? $ordenActual : null,
        'vista' => $vistaParam,
    ];
    $params = array_filter(array_merge($base, $over), fn($v) => $v !== null && $v !== '');
    return '/archivos?' . http_build_query($params);
};
$urlCarpeta = fn($c) => $urlArchivo(['carpeta' => $c === null ? null : ($c === 0 ? 'sin' : (string)$c), 'vista' => null]);

$mostrarCarpetas = $carpeta === null && !$recientes && !$filtro;
$mostrarContexto = $carpeta !== null || $recientes || (bool)$filtro;
$carpetaSubida = $carpetaActual ? '&carpeta=' . (int)$carpetaActual->id : '';
?>
<?php require __DIR__ . '/_hero.php'; ?>

<?php require_once __DIR__ . '/../templates/herramientas-proyecto.php'; pestanas_herramienta('archivo', '/archivos', $proyecto); ?>

<?php if (!empty($mensaje)): ?>
<div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>" role="status">
    <p><?php echo s($mensaje['texto']); ?></p>
    <?php if (!empty($mensaje['detalles'])): ?>
        <ul class="text-sm"><?php foreach ($mensaje['detalles'] as $detalle): ?><li><?php echo s($detalle); ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="archivo-page" data-vista-inicial="<?php echo s($vistaInicial); ?>">
    <div class="archivo-cuerpo">
        <?php require __DIR__ . '/_rail.php'; ?>
        <div class="archivo-principal">
            <?php if ($mostrarContexto): ?>
                <?php require __DIR__ . '/_contexto.php'; ?>
            <?php endif; ?>
            <?php require __DIR__ . '/_barra.php'; ?>
            <?php if ($mostrarCarpetas): ?>
                <?php require __DIR__ . '/_carpetas.php'; ?>
            <?php endif; ?>
            <?php require __DIR__ . '/_lista.php'; ?>
        </div>
    </div>

    <?php require __DIR__ . '/_detalle.php'; ?>
    <?php if ($puede): ?>
        <?php require __DIR__ . '/_modales.php'; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_scripts.php'; ?>
