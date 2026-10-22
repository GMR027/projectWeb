<?php
/**
 * Lista de novedades de la plataforma.
 * La usan /novedades y Ajustes → «Novedades de la plataforma».
 * Espera $novedades: array de Classes\Novedades::para() (con la marca `nueva`).
 */
$novedades = $novedades ?? [];
?>
<?php if (!$novedades): ?>
    <p class="text-muted">No hay novedades publicadas.</p>
<?php else: ?>
    <div class="nov-lista">
        <?php foreach ($novedades as $nv): [$etq, $ico] = \Classes\Novedades::tipo($nv['tipo']); ?>
            <article class="nov-item<?php echo !empty($nv['nueva']) ? ' nueva' : ''; ?>" id="<?php echo s(\Classes\Novedades::ancla($nv)); ?>">
                <div class="nov-cab">
                    <span class="nov-tipo nov-<?php echo s($nv['tipo']); ?>"><i class="fa-solid <?php echo s($ico); ?>"></i> <?php echo s($etq); ?></span>
                    <time class="nov-fecha" datetime="<?php echo s(substr($nv['fecha'], 0, 10)); ?>"><?php echo s(formatear_fecha(substr($nv['fecha'], 0, 10), 'd/m/Y')); ?></time>
                    <?php if (!empty($nv['nueva'])): ?><span class="nov-nueva">Nueva</span><?php endif; ?>
                </div>
                <h4 class="nov-titulo"><?php echo s($nv['titulo']); ?></h4>
                <p class="nov-texto"><?php echo s($nv['texto']); ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
