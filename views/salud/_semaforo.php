<?php
/** Semáforo de salud de un proyecto. Variables: $proyecto; opcional $saludCompacto (true = solo lo que requiere atención, para el inicio) */
$sal = \Classes\Salud::semaforo($proyecto);
$compacto = !empty($saludCompacto);
$rotulo = ['ok' => 'Todo en orden', 'aviso' => 'Requiere atención', 'critico' => 'Hay puntos urgentes'];
$mostrar = $compacto ? array_filter($sal['indicadores'], fn($i) => $i['nivel'] !== 'ok') : $sal['indicadores'];
?>
<div class="salud salud-<?php echo s($sal['nivel']); ?><?php echo $compacto ? ' salud-compacto' : ''; ?>">
    <div class="salud-cab"><span class="salud-luz" aria-hidden="true"></span><strong>Salud del proyecto</strong><span class="salud-estado"><?php echo s($rotulo[$sal['nivel']]); ?></span></div>
    <?php if ($mostrar): ?>
    <div class="salud-lista">
        <?php foreach ($mostrar as $i): ?>
            <a class="salud-item salud-<?php echo s($i['nivel']); ?>" href="<?php echo s($i['url']); ?>" title="<?php echo s($i['detalle']); ?>">
                <i class="fa-solid <?php echo s($i['icono']); ?>"></i>
                <span class="salud-valor mono"><?php echo (int)$i['valor']; ?></span>
                <span class="salud-titulo"><?php echo s($i['titulo']); ?></span>
                <?php if (!$compacto): ?><small><?php echo s($i['detalle']); ?></small><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
