<?php
// La hoja de la lámina: un lienzo del tamaño elegido con sus bloques posicionados en %.
// Variables: $proyecto, $lamina, $bloques, $porBloque, $tablasPorId, $nRec, $puedeEditar, $modoDiseno
use Classes\VplanDiseno;
use Classes\Vplan;
use Model\VplanBloque;

$fd = fn($f) => $f ? formatear_fecha($f) : '';
$cq = fn(float $pt) => round($pt / 1224.57 * 100, 4) . 'cqw';   // pt de referencia (tabloide) -> ancho del lienzo
$nRec = (int)$nRec;
$planoImg = $lamina->marcas_png ?: $lamina->plano;
$lkW = $lkH = 0.0;
$enlaces = $lamina->marcas_png ? \Classes\EnlacesLamina::de($lamina->marcas_json, $lkW, $lkH) : [];
$editarFila = fn($t, $n, $campo) => $puedeEditar
    ? '<input type="date" class="vp-rec" data-actividad="' . (int)$t['actividad']->id . '" data-n="' . $n . '" data-campo="' . $campo . '" value="' . s($t['rec'][$n - 1][$campo]) . '">'
    : s($fd($t['rec'][$n - 1][$campo]));
$alinear = ['izquierda' => 'flex-start', 'centro' => 'center', 'derecha' => 'flex-end'];
$tam = function (VplanBloque $b, float $defecto) use ($cq): string {
    $c = $b->config();
    return 'font-size:' . $cq(!empty($c['tamano']) ? (float)$c['tamano'] : $defecto) . ';' . (!empty($c['negrita']) ? 'font-weight:700;' : '') . (!empty($c['color']) ? 'color:' . s($c['color']) . ';' : '');
};
/** Tamaño (en % del bloque) de una imagen que se ajusta sin deformarse dentro de su bloque */
$ajuste = function (?string $archivo, VplanBloque $b) use ($lamina): ?array {
    $ruta = $archivo ? \Classes\Imagenes::CARPETA_VPLAN . '/' . basename($archivo) : null;
    $dim = $ruta && is_file($ruta) ? @getimagesize($ruta) : false;
    if (!$dim || $dim[0] < 1 || $dim[1] < 1) return null;
    $caja = ((float)$b->w * (float)$lamina->ancho_mm) / max(0.01, (float)$b->h * (float)$lamina->alto_mm);
    $img = $dim[0] / $dim[1];
    return $img > $caja ? [100.0, round($caja / $img * 100, 3)] : [round($img / $caja * 100, 3), 100.0];
};
?>
<div class="vp-lamina vp-sheet<?php echo $modoDiseno ? ' vp-diseno' : ''; ?>" id="vp-sheet" style="aspect-ratio:<?php echo (int)$lamina->ancho_mm; ?> / <?php echo (int)$lamina->alto_mm; ?>;--vp-ratio:<?php echo round($lamina->ancho_mm / max(1, $lamina->alto_mm), 4); ?>" data-ancho="<?php echo (int)$lamina->ancho_mm; ?>" data-alto="<?php echo (int)$lamina->alto_mm; ?>">
<?php foreach ($bloques as $b):
    $cfg = $b->config();
    $estilo = 'left:' . (float)$b->x . '%;top:' . (float)$b->y . '%;width:' . (float)$b->w . '%;height:' . (float)$b->h . '%;z-index:' . (int)$b->z . ';';
    $porId = spl_object_id($b);
    if ($b->tipo === 'tabla' && !isset($tablasPorId[(int)($cfg['tabla_id'] ?? 0)])) continue;
    $oculto = !empty($cfg['oculto']);
    if ($oculto && !$modoDiseno) continue;   // oculto: no se ve (en modo diseño se ve atenuado para poder mostrarlo)
?>
    <div class="vp-blk vp-b-<?php echo s($b->tipo); ?><?php echo $oculto ? ' vp-b-oculto' : ''; ?>" data-id="<?php echo (int)$b->id; ?>" data-tipo="<?php echo s($b->tipo); ?>" style="<?php echo $estilo; ?>">
    <?php switch ($b->tipo):
        case 'titulo': case 'supervisor': case 'fecha': case 'texto':
            $texto = Vplan::textoDeBloque($b, $lamina, $proyecto);
            $lineas = $texto === '' ? [] : explode("\n", $texto);
            $propio = trim((string)($cfg['texto'] ?? '')) !== '';
            $def = ['titulo' => 20, 'supervisor' => 7.5, 'fecha' => 7.5, 'texto' => 10][$b->tipo];
            $al = $cfg['alinear'] ?? ['titulo' => 'centro', 'supervisor' => 'izquierda', 'fecha' => 'derecha', 'texto' => 'izquierda'][$b->tipo];
            $pie = in_array($b->tipo, ['supervisor', 'fecha'], true);
            ?>
            <div class="vp-txt" style="justify-content:<?php echo $alinear[$al]; ?>;text-align:<?php echo $al === 'centro' ? 'center' : ($al === 'derecha' ? 'right' : 'left'); ?>;<?php echo $b->tipo === 'titulo' ? 'font-weight:700;' : ''; ?><?php echo $pie && empty($cfg['color']) ? 'color:#dc2626;' : ''; ?><?php echo $tam($b, $def); ?>">
                <div><?php foreach ($lineas as $i => $ln): ?><div<?php echo ($b->tipo === 'titulo' && !$propio && $i === 1) ? ' style="font-size:1.1em"' : ''; ?>><?php echo s($ln); ?></div><?php endforeach; ?></div>
            </div>
            <?php break;
        case 'logo':
            $logo = ($cfg['cual'] ?? '') === 'empresa' ? $proyecto->logoEmpresa() : $proyecto->contratista_logo;
            $al = $cfg['alinear'] ?? (($cfg['cual'] ?? '') === 'empresa' ? 'derecha' : 'izquierda');
            ?>
            <div class="vp-img" style="justify-content:<?php echo $alinear[$al]; ?>"><?php if ($logo): ?><img src="/build/img/logos/<?php echo s($logo); ?>" alt=""><?php endif; ?></div>
            <?php break;
        case 'plano':
            $fit = $ajuste($planoImg, $b);
            if ($puedeEditar && $lamina->plano && !$modoDiseno): ?>
                <div class="vp-plano-esq no-print"><a href="/vplan/editor?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($lamina->id); ?>" class="vp-plano-editar" title="Abrir el editor de marcas de este plano"><i class="fa-solid fa-pen-ruler"></i> Editar marcas</a></div>
            <?php endif;
            if ($fit): ?>
                <div class="vp-plano-ajuste" style="left:<?php echo round((100 - $fit[0]) / 2, 3); ?>%;top:<?php echo round((100 - $fit[1]) / 2, 3); ?>%;width:<?php echo $fit[0]; ?>%;height:<?php echo $fit[1]; ?>%">
                    <?php if ($enlaces): ?><div class="vp-lk-wrap"><?php endif; ?><img src="/uploads/vplan/<?php echo s($planoImg); ?>" alt="Plano"><?php if ($enlaces): echo \Classes\EnlacesLamina::html($enlaces, $lkW, $lkH); ?></div><?php endif; ?>
                </div>
            <?php else: ?><p class="vp-vacio-blk">Sin imagen de plano. Súbela en «Editar lámina».</p><?php endif;
            break;
        case 'simbologia':
            $fit = $ajuste($lamina->simbologia, $b); ?>
            <div class="vp-img" style="align-items:flex-end;justify-content:flex-start"><?php if ($lamina->simbologia): ?><img src="/uploads/vplan/<?php echo s($lamina->simbologia); ?>" alt="Simbología"><?php endif; ?></div>
            <?php break;
        case 'schedule':
            $simbSchedule = $lamina->imagenSimbologiaSchedule(); ?>
            <?php if ($simbSchedule): ?>
                <div class="vp-img" style="align-items:flex-end;justify-content:flex-end"><img src="/uploads/vplan/<?php echo s($simbSchedule); ?>" alt="Simbología del schedule"></div>
            <?php else: ?>
                <div class="vp-img" style="align-items:flex-end;justify-content:flex-end;font-size:<?php echo $cq(7); ?>">
                <table class="vp-schedule">
                    <thead><tr><th colspan="2">SYMBOLOGY FOR SCHEDULE</th></tr></thead>
                    <tbody>
                        <tr><td class="vp-verde"></td><td>COMPLETE</td></tr>
                        <tr><td class="vp-amarillo"></td><td>IN PROGRESS</td></tr>
                        <tr><td class="vp-rojo"></td><td>ON HOLD</td></tr>
                        <tr><td class="vp-rombo-celda"><span class="vp-rombo vp-rombo-sm"></span></td><td>CONSTRAIN</td></tr>
                    </tbody>
                </table></div>
            <?php endif;
            break;
        case 'tareas':
            $td = $porBloque[$porId] ?? ['grupos' => [], 'subtitulos' => false, 'titulo' => '', 'color' => Vplan::colorTabla(null), 'seq' => 'continuo'];
            [, $f, $p] = VplanDiseno::ESCALAS[VplanDiseno::escalaTareas($lamina, $b, $td, $nRec)];
            $cols = 6 + 2 * $nRec;
            $rs = $nRec ? 2 : 1;
            $cw = VplanDiseno::columnasTareas(VplanDiseno::anchoRefCm($b), $nRec, $f, $p);
            ?>
            <div class="vp-tab" style="font-size:<?php echo $cq($f); ?>;--vp-pad:<?php echo $cq($p); ?>">
            <table class="vp-tabla vp-programa" style="--vp-c:<?php echo s($td['color'][0]); ?>;--vp-t:<?php echo s($td['color'][1]); ?>">
                <colgroup><?php foreach ($cw as $w): ?><col style="width:<?php echo $w; ?>%"><?php endforeach; ?></colgroup>
                <thead>
                    <tr><th colspan="<?php echo $cols; ?>" class="vp-adic-titulo"><?php echo s($td['titulo'] ?: 'SIN ACTIVIDADES'); ?></th></tr>
                    <tr>
                        <th rowspan="<?php echo $rs; ?>" style="width:<?php echo $cw[0]; ?>%"># SEQ</th><th rowspan="<?php echo $rs; ?>" class="vp-task" style="width:<?php echo $cw[1]; ?>%">TASK</th><th rowspan="<?php echo $rs; ?>" style="width:<?php echo $cw[2]; ?>%">START</th><th rowspan="<?php echo $rs; ?>" style="width:<?php echo $cw[3]; ?>%">FINISH</th>
                        <?php for ($r = 1; $r <= $nRec; $r++): ?><th colspan="2"><?php echo Vplan::etiquetaRecovery($r); ?></th><?php endfor; ?>
                        <th rowspan="<?php echo $rs; ?>" style="width:<?php echo $cw[4 + 2 * $nRec]; ?>%">%</th><th rowspan="<?php echo $rs; ?>" style="width:<?php echo $cw[5 + 2 * $nRec]; ?>%">CONSTRAINS</th>
                    </tr>
                    <?php if ($nRec): ?><tr><?php for ($r = 1; $r <= $nRec; $r++): ?><th style="width:<?php echo $cw[2 + 2 * $r]; ?>%">START</th><th style="width:<?php echo $cw[3 + 2 * $r]; ?>%">FINISH</th><?php endfor; ?></tr><?php endif; ?>
                </thead>
                <tbody>
                <?php foreach ($td['grupos'] as $g): ?>
                    <?php if ($td['subtitulos']): ?><tr class="vp-subtitulo"><td colspan="<?php echo $cols; ?>"><?php echo s(mb_strtoupper($g['partida']->nombre)); ?></td></tr><?php endif; ?>
                    <?php foreach ($g['tareas'] as $t): $a = $t['actividad']; $pg = (int)$a->progreso; ?>
                    <tr>
                        <td class="c"><?php echo (int)$t['seq']; ?></td>
                        <td><?php echo s($a->nombre); ?></td>
                        <td class="c"><?php echo $puedeEditar ? '<input type="date" class="vp-rec vp-fecha-act" data-actividad="' . (int)$a->id . '" data-tipo="inicio" value="' . s($a->fecha_inicio) . '" title="Misma fecha que el programa de obra">' : s($fd($a->fecha_inicio)); ?></td>
                        <td class="c"><?php echo $puedeEditar ? '<input type="date" class="vp-rec vp-fecha-act" data-actividad="' . (int)$a->id . '" data-tipo="fin" value="' . s($a->fecha_fin) . '" title="Misma fecha que el programa de obra">' : s($fd($a->fecha_fin)); ?></td>
                        <?php for ($r = 1; $r <= $nRec; $r++): ?><td class="c<?php echo $r > 1 ? ' vp-rojo-txt' : ''; ?>"><?php echo $editarFila($t, $r, 'inicio'); ?></td><td class="c<?php echo $r > 1 ? ' vp-rojo-txt' : ''; ?>"><?php echo $editarFila($t, $r, 'fin'); ?></td><?php endfor; ?>
                        <td class="c vp-pct-act <?php echo $pg >= 100 ? 'vp-verde' : ($pg > 0 ? 'vp-amarillo' : ''); ?>"><?php echo $puedeEditar ? '<input type="number" min="0" max="100" step="1" class="vp-rec vp-progreso-act" data-actividad="' . (int)$a->id . '" value="' . $pg . '" title="Mismo avance que el programa de obra">' : $pg . '%'; ?></td>
                        <td class="c"><?php if ($t['restricciones']): foreach ($t['restricciones'] as $n): ?><span class="vp-rombo"><b><?php echo (int)$n; ?></b></span><?php endforeach; elseif ($t['estado'] === 'completo'): ?>COMPLETE<?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <?php if (!$td['grupos']): ?><tr><td colspan="<?php echo $cols; ?>" class="text-muted">Sin actividades. En modo diseño, elige las partidas de este bloque.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
            <?php break;
        case 'tabla':
            $bloque = $tablasPorId[(int)$cfg['tabla_id']];
            [, $f, $p] = VplanDiseno::ESCALAS[VplanDiseno::escalaTabla($lamina, $b, $bloque)]; ?>
            <div class="vp-tab" style="font-size:<?php echo $cq($f); ?>;--vp-pad:<?php echo $cq($p); ?>">
                <?php include __DIR__ . '/_tabla-adicional.php'; ?>
            </div>
            <?php break;
        case 'imagen':
            $arch = (string)($cfg['archivo'] ?? '');
            $al = $cfg['alinear'] ?? 'centro'; ?>
            <div class="vp-img" style="justify-content:<?php echo $alinear[$al] ?? 'center'; ?>"><?php if ($arch !== ''): ?><img src="/uploads/vplan/<?php echo s($arch); ?>" alt="<?php echo s($cfg['nombre'] ?? 'Símbolo'); ?>"><?php else: ?><p class="vp-vacio-blk">Sin imagen</p><?php endif; ?></div>
            <?php break;
        case 'cajetin':
            $cj = Vplan::cajetinDeBloque($b, $lamina, $proyecto); ?>
            <div class="vp-tab" style="font-size:<?php echo $cq(!empty($cfg['tamano']) ? (float)$cfg['tamano'] : 7); ?>">
            <table class="vp-tabla vp-cajetin">
                <?php if ($cj['banda']): ?>
                <thead><tr><?php foreach ($cj['filas'] as $f): ?><th><?php echo s($f[0]); ?></th><?php endforeach; ?></tr></thead>
                <tbody><tr><?php foreach ($cj['filas'] as $f): ?><td class="c"><?php echo s($f[1]); ?></td><?php endforeach; ?></tr></tbody>
                <?php else: ?>
                <tbody><?php foreach ($cj['filas'] as $f): ?><tr><th style="width:38%"><?php echo s($f[0]); ?></th><td><?php echo s($f[1]); ?></td></tr><?php endforeach; ?></tbody>
                <?php endif; ?>
            </table></div>
            <?php break;
        case 'estampa':
            $est = \Model\Estampa::disponible() ? \Model\Estampa::find((int)($cfg['estampa_id'] ?? 0)) : null;
            if ($est && (int)$est->proyecto_id === (int)$proyecto->id) echo \Classes\Estampas::renderGuardada($est, $proyecto, $lamina, Vplan::opcionesEstampa($cfg), 'cq');
            else echo '<p class="vp-vacio-blk">Estampa no disponible</p>';
            break;
    endswitch; ?>
    </div>
<?php endforeach; ?>
</div>
