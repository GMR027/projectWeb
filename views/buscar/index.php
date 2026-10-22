<?php // Variables: $proyecto, $q, $grupos, $valida
$pid = (int)$proyecto->id;
$total = array_sum(array_map(fn($g) => count($g['items']), $grupos));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-magnifying-glass"></i> Buscar en el proyecto</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · busca en el programa, materiales, submittals, minutas, bitácora, reportes, archivo, punch, órdenes de cambio, permisos, Redactor y normativa. Todas las palabras deben aparecer; no importan acentos ni mayúsculas.</p>
    </div>
</div>

<form method="get" action="/buscar" class="buscar-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="search" name="q" value="<?php echo s($q); ?>" placeholder="Ej. cámara fachada, NOM-001, submittal 12…" autofocus aria-label="Buscar">
    <button type="submit" class="btn btn-primary">Buscar</button>
</form>

<?php if ($q === ''): ?>
    <p class="text-muted">Escribe lo que buscas (mínimo 2 letras por palabra).</p>
<?php elseif (!$valida): ?>
    <p class="text-muted">Escribe al menos una palabra de 2 letras o más.</p>
<?php elseif (!$grupos): ?>
    <div class="card empty-state"><p>No encontré nada para «<?php echo s($q); ?>».</p></div>
<?php else: ?>
    <p class="text-muted text-sm"><?php echo (int)$total; ?> resultado(s) mostrados en <?php echo count($grupos); ?> herramienta(s).</p>
    <?php foreach ($grupos as $g): ?>
        <div class="card buscar-grupo">
            <h3><i class="fa-solid <?php echo s($g['icono']); ?>"></i> <?php echo s($g['titulo']); ?><?php if ($g['hay_mas']): ?> <small class="text-muted">(los primeros <?php echo count($g['items']); ?>; afina la búsqueda para ver los demás)</small><?php endif; ?></h3>
            <ul class="buscar-lista">
                <?php foreach ($g['items'] as $it): ?>
                    <li><a href="<?php echo s($it['url']); ?>"><strong><?php echo s($it['titulo']); ?></strong><?php if ($it['detalle'] !== ''): ?><span class="text-sm text-muted"><?php echo s($it['detalle']); ?></span><?php endif; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
