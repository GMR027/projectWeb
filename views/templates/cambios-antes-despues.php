<?php
/** Antes/después de un cambio en lenguaje llano (nunca JSON). Variables: $antes, $despues (arrays o null), $pid */
$filasCmp = \Classes\CambiosLegibles::comparar($antes, $despues, (int)$pid);
?>
<?php if (!$filasCmp): ?>
    <p class="text-muted text-sm">Sin detalle de campos.</p>
<?php else: ?>
    <table class="hist-cmp">
        <thead><tr><th>Campo</th><?php if ($antes !== null): ?><th>Antes</th><?php endif; ?><?php if ($despues !== null): ?><th>Después</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($filasCmp as [$campo, $valorAntes, $valorDespues]): ?>
            <tr><th scope="row"><?php echo s($campo); ?></th><?php if ($antes !== null): ?><td><?php echo s($valorAntes); ?></td><?php endif; ?><?php if ($despues !== null): ?><td><?php echo s($valorDespues); ?></td><?php endif; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
