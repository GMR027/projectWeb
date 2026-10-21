<?php
// Un renglón de una tabla adicional.
// Variables: $bloque ['tabla','campos'], $fila (datos + n + _i), $puedeEditar
$t = $bloque['tabla'];
$campos = $bloque['campos'];
$editable = $puedeEditar && isset($fila['_i']);   // los renglones de restricciones del programa no se editan aquí
$fd = fn($f) => $f ? formatear_fecha($f) : '';
$meta = $t->metaColumnas();   // tipos de las columnas de una tabla vinculada a SheetFlow
$ed = function (string $k, string $tipo, string $extra = '') use ($fila, $editable, $fd, $meta) {
    $v = $fila[$k] ?? '';
    $m = $meta[$k] ?? null;
    if ($m && $m['t'] === 'foto') {   // foto del punch: miniatura (solo lectura)
        $raw = $fila['v' . substr($k, 1)] ?? '';
        return $raw !== '' ? '<img class="vp-foto" src="/uploads/punch/' . s(basename($raw)) . '" alt="">' : '';
    }
    if ($m && !empty($m['ro'])) return s($v);
    if ($m && in_array($m['t'], ['semaforo', 'casilla'], true) && !$editable) {   // de solo lectura: el semáforo conserva su color
        $raw = $fila['v' . substr($k, 1)] ?? '';
        return $m['t'] === 'semaforo' && $raw !== '' ? '<span class="vp-semaforo vp-sem-' . s($raw) . '">' . s($v) . '</span>' : s($v);
    }
    if ($m && $editable && ($m['t'] !== 'texto')) {
        $raw = $fila['v' . substr($k, 1)] ?? $v;
        $dato = 'class="vp-in vp-in-sf" data-campo="' . $k . '"';
        switch ($m['t']) {
            case 'fecha': return '<input type="date" ' . $dato . ' value="' . s($raw) . '">';
            case 'numero': case 'moneda': case 'porcentaje': return '<input type="number" step="any" ' . $dato . ' value="' . s($raw) . '">';
            case 'casilla': return '<input type="checkbox" ' . $dato . ' value="1"' . ($raw === '1' ? ' checked' : '') . '>';
            case 'semaforo': case 'lista':
                $ops = $m['t'] === 'semaforo' ? \Classes\Sheetflow::SEMAFORO : ($m['op'] === array_values($m['op']) ? array_combine($m['op'], $m['op']) : $m['op']);
                $h = '<select ' . $dato . '><option value=""></option>';
                foreach ($ops as $ok => $ol) $h .= '<option value="' . s($ok) . '"' . ($raw === (string)$ok ? ' selected' : '') . '>' . s($ol) . '</option>';
                return $h . '</select>';
        }
    }
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
    <?php foreach ($campos as $k => $c): $ta = ($meta[$k]['t'] ?? '') === 'casilla' || ($meta[$k]['t'] ?? '') === 'semaforo'; ?><td<?php echo $ta ? ' class="c"' : ''; ?>><?php echo $ed($k, 'text'); ?></td><?php endforeach; ?>
<?php endif; ?>
<?php if ($puedeEditar): ?><td class="c no-print vp-acc"><?php if ($editable && $t->tipo !== 'punchlist'): ?><button type="button" class="vp-del" title="Quitar este dato">✕</button><?php endif; ?></td><?php endif; ?>
</tr>
