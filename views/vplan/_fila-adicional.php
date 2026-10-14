<?php
// Un renglón de una tabla adicional.
// Variables: $bloque ['tabla','campos'], $fila (datos + n + _i), $puedeEditar
$t = $bloque['tabla'];
$campos = $bloque['campos'];
$editable = $puedeEditar && isset($fila['_i']);   // los renglones de restricciones del programa no se editan aquí
$fd = fn($f) => $f ? formatear_fecha($f) : '';
$ed = function (string $k, string $tipo, string $extra = '') use ($fila, $editable, $fd) {
    $v = $fila[$k] ?? '';
    if (!$editable) return s($tipo === 'date' ? $fd($v) : ($tipo === 'number' && $v !== '' ? (int)$v . '%' : $v));
    if ($tipo === 'estado') {
        $h = '<select class="vp-in vp-in-estado" data-campo="' . $k . '">';
        foreach (\Model\VplanTabla::ESTADOS as $ek => $el) $h .= '<option value="' . $ek . '"' . (($v ?: 'proceso') === $ek ? ' selected' : '') . '>' . s($el) . '</option>';
        return $h . '</select>';
    }
    if ($tipo === 'text' && in_array($k, ['descripcion', 'elemento'], true)) {   // textos largos: se ven completos (crece solo)
        return '<textarea rows="1" class="vp-in vp-ta" data-campo="' . $k . '">' . s($v) . '</textarea>';
    }
    $tp = $tipo === 'number' ? 'number" min="0" max="100" step="1' : $tipo;
    return '<input type="' . $tp . '" class="vp-in ' . $extra . '" data-campo="' . $k . '" value="' . s($v) . '">';
};
$pct = $fila['pct'] ?? '';
$est = $fila['estado'] ?? 'proceso';
?>
<tr class="vp-fila-tr<?php echo $t->tipo === 'secundarias' ? ' vp-est-' . s($est) : ''; ?>" <?php echo $editable ? 'data-indice="' . (int)$fila['_i'] . '"' : ''; ?>>
<?php if ($t->tipo === 'punch'): ?>
    <td class="c vp-n"><?php echo (int)$fila['n']; ?></td>
    <td><?php echo $ed('descripcion', 'text'); ?></td>
    <td class="c"><?php echo $ed('inicio', 'date'); ?></td>
    <td class="c"><?php echo $ed('fin', 'date'); ?></td>
    <td class="c vp-pct <?php echo $pct !== '' && (int)$pct >= 100 ? 'vp-verde' : ($pct !== '' && (int)$pct > 0 ? 'vp-amarillo' : ''); ?>"><?php echo $ed('pct', 'number'); ?></td>
<?php elseif ($t->tipo === 'restricciones'): ?>
    <td class="vp-rombo-celda vp-n"><span class="vp-rombo"><b><?php echo (int)$fila['n']; ?></b></span></td>
    <td><?php echo $ed('descripcion', 'text'); ?></td>
<?php elseif ($t->tipo === 'secundarias'): ?>
    <td><?php echo $ed('elemento', 'text'); ?></td>
    <td class="c"><?php echo $ed('fecha', 'text'); ?></td>
    <td class="c"><?php echo $ed('recovery', 'text'); ?></td>
    <?php if ($puedeEditar): ?><td class="c no-print"><?php echo $editable ? $ed('estado', 'estado') : ''; ?></td><?php endif; ?>
<?php else: ?>
    <td class="c vp-n"><?php echo (int)$fila['n']; ?></td>
    <?php foreach ($campos as $k => $c): ?><td><?php echo $ed($k, 'text'); ?></td><?php endforeach; ?>
<?php endif; ?>
<?php if ($puedeEditar): ?><td class="c no-print vp-acc"><?php if ($editable): ?><button type="button" class="vp-del" title="Quitar este dato">✕</button><?php endif; ?></td><?php endif; ?>
</tr>
