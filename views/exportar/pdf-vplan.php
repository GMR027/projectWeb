<?php
// Variables: $paginas, $proyecto, $logoC, $logoE (cada página trae su lámina, sus bloques y su tamaño de hoja)
use Model\VplanTabla;
$fd = fn($f) => $f ? date('d/m/Y', strtotime($f)) : '';
$meses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
$actualizado = $meses[(int)date('n')] . ' | ' . date('d') . ' | ' . date('Y');
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
    @page { margin: 0; }
    body { font-family: DejaVu Sans, sans-serif; color: #000; margin: 0; }
    .hoja { position: relative; overflow: hidden; page-break-after: always; }
    .hoja.ultima { page-break-after: auto; }
    .blk { position: absolute; }
    table { border-collapse: collapse; }
    .t { width: 100%; margin-bottom: 7pt; }
    .t th, .t td { border: 0.7pt solid #000; vertical-align: middle; }
    .t th { background: #5b82b4; color: #fff; text-align: center; font-weight: bold; }
    .c { text-align: center; }
    .tit { font-size: 1.25em; letter-spacing: 0.04em; }
    .punch th { background: #fff0c4; color: #000; } .sec th { background: #bfe3f7; color: #000; } .otro th { background: #d9d9d9; color: #000; }
    .t .subt td { background: #e2e8f0; font-weight: bold; }
    .verde { background: #059669; } .amarillo { background: #d97706; } .rojo { background: #dc2626; color: #fff; }
    .rojotxt { color: #dc2626; }
    .leg { border: 0.8pt solid #000; background: #fff; } .leg td { border: none; padding: 0 4pt; white-space: nowrap; } .leg td.sw { border: 0.7pt solid #000; width: 22pt; } .leg td.leg-tit { text-decoration: underline; font-weight: bold; padding: 2pt 4pt; border-bottom: 0.8pt solid #000; }
</style></head><body>
<?php
$fd = fn($f) => $f ? date('d/m/Y', strtotime($f)) : '';
$MM = 72 / 25.4;   // mm -> pt
foreach ($paginas as $pi => $d):
    $l = $d['lamina'];
    $K = \Classes\VplanDiseno::factor($l);
    $W = $l->ancho_mm * $MM; $H = $l->alto_mm * $MM;
    $nRec = (int)$d['nRec'];
    $alin = ['izquierda' => 'left', 'centro' => 'center', 'derecha' => 'right'];
    // Imagen ajustada al bloque sin deformarla; $ax/$ay = 0 pega a la izquierda/arriba, 0.5 centra, 1 a la derecha/abajo
    $ajustar = function (?string $archivo, string $src, float $bw, float $bh, float $ax, float $ay, string $extra = ''): string {
        $ruta = $archivo ? \Classes\Imagenes::CARPETA_VPLAN . '/' . basename($archivo) : null;
        $dim = $ruta && is_file($ruta) ? @getimagesize($ruta) : false;
        if (!$dim || !$dim[0] || !$dim[1]) return '';
        $k = min($bw / $dim[0], $bh / $dim[1]);
        $w = $dim[0] * $k; $h = $dim[1] * $k;
        return '<img src="' . $src . '" style="position:absolute;left:' . round(($bw - $w) * $ax, 2) . 'pt;top:' . round(($bh - $h) * $ay, 2) . 'pt;width:' . round($w, 2) . 'pt;height:' . round($h, 2) . 'pt;' . $extra . '">';
    };
?>
<div class="hoja <?php echo $pi === count($paginas) - 1 ? 'ultima' : ''; ?>" style="width:<?php echo floor($W); ?>pt;height:<?php echo floor($H) - 2; ?>pt">
<?php foreach ($d['bloques'] as $bi => $b):
    $cfg = $b->config();
    if (!empty($cfg['oculto'])) continue;
    $bw = (float)$b->w / 100 * $W; $bh = (float)$b->h / 100 * $H;
    $caja = 'left:' . round((float)$b->x / 100 * $W, 2) . 'pt;top:' . round((float)$b->y / 100 * $H, 2) . 'pt;width:' . round($bw, 2) . 'pt;height:' . round($bh, 2) . 'pt;';
    switch ($b->tipo):
        case 'titulo': case 'supervisor': case 'fecha': case 'texto':
            $texto = \Classes\Vplan::textoDeBloque($b, $l, $proyecto);
            $propio = trim((string)($cfg['texto'] ?? '')) !== '';
            $def = ['titulo' => 20, 'supervisor' => 7.5, 'fecha' => 7.5, 'texto' => 10][$b->tipo];
            $al = $cfg['alinear'] ?? ['titulo' => 'centro', 'supervisor' => 'izquierda', 'fecha' => 'derecha', 'texto' => 'izquierda'][$b->tipo];
            $pie = in_array($b->tipo, ['supervisor', 'fecha'], true);
            $fs = (!empty($cfg['tamano']) ? (float)$cfg['tamano'] : $def) * $K;
            $color = !empty($cfg['color']) ? $cfg['color'] : ($pie ? '#dc2626' : '#000');
            ?>
            <div class="blk" style="<?php echo $caja; ?>"><table style="width:<?php echo round($bw, 2); ?>pt;height:<?php echo round($bh, 2); ?>pt"><tr><td style="vertical-align:middle;text-align:<?php echo $alin[$al]; ?>;font-size:<?php echo round($fs, 2); ?>pt;line-height:1.1;color:<?php echo s($color); ?>;<?php echo ($b->tipo === 'titulo' || !empty($cfg['negrita'])) ? 'font-weight:bold;' : ''; ?>">
                <?php foreach (explode("\n", $texto) as $i => $ln): ?><div<?php echo ($b->tipo === 'titulo' && !$propio && $i === 1) ? ' style="font-size:' . round($fs * 1.1, 2) . 'pt"' : ''; ?>><?php echo s($ln); ?></div><?php endforeach; ?>
            </td></tr></table></div>
            <?php break;
        case 'logo':
            $es = ($cfg['cual'] ?? '') === 'empresa';
            $al = $cfg['alinear'] ?? ($es ? 'derecha' : 'izquierda');
            $src = $es ? $logoE : $logoC;
            $arch = $es ? $proyecto->logoEmpresa() : $proyecto->contratista_logo;
            if ($src && $arch):
                $ruta = __DIR__ . '/../../public/build/img/logos/' . basename($arch);
                $dim = is_file($ruta) ? @getimagesize($ruta) : false;
                if ($dim && $dim[0] && $dim[1]): $kk = min($bw / $dim[0], $bh / $dim[1]); $w = $dim[0] * $kk; $h = $dim[1] * $kk; ?>
                    <div class="blk" style="<?php echo $caja; ?>"><img src="<?php echo $src; ?>" style="position:absolute;left:<?php echo round(($bw - $w) * ['izquierda' => 0, 'centro' => 0.5, 'derecha' => 1][$al], 2); ?>pt;top:<?php echo round(($bh - $h) / 2, 2); ?>pt;width:<?php echo round($w, 2); ?>pt;height:<?php echo round($h, 2); ?>pt"></div>
                <?php endif;
            endif;
            break;
        case 'plano': ?>
            <div class="blk" style="<?php echo $caja; ?>"><?php echo $ajustar($l->marcas_png ?: $l->plano, (string)$d['plano'], $bw, $bh, 0.5, 0.5, 'border:0.8pt solid #444;'); ?></div>
            <?php break;
        case 'simbologia': ?>
            <div class="blk" style="<?php echo $caja; ?>"><?php echo $ajustar($l->simbologia, (string)$d['simb'], $bw, $bh, 0, 1); ?></div>
            <?php break;
        case 'imagen':
            $arch = (string)($cfg['archivo'] ?? '');
            $src = $arch !== '' ? \Classes\Imagenes::vplanBase64($arch, 1000) : null;
            $al = ['izquierda' => 0, 'centro' => 0.5, 'derecha' => 1][$cfg['alinear'] ?? 'centro'] ?? 0.5;
            if ($src): ?><div class="blk" style="<?php echo $caja; ?>"><?php echo $ajustar($arch, $src, $bw, $bh, $al, 0.5); ?></div><?php endif;
            break;
        case 'cajetin':
            $cj = \Classes\Vplan::cajetinDeBloque($b, $l, $proyecto);
            $fs = (!empty($cfg['tamano']) ? (float)$cfg['tamano'] : 7) * $K; ?>
            <div class="blk" style="<?php echo $caja; ?>font-size:<?php echo round($fs, 2); ?>pt"><table class="t" style="width:<?php echo round($bw, 2); ?>pt">
                <?php if ($cj['banda']): ?>
                <tr><?php foreach ($cj['filas'] as $f): ?><th><?php echo s($f[0]); ?></th><?php endforeach; ?></tr>
                <tr><?php foreach ($cj['filas'] as $f): ?><td class="c"><?php echo s($f[1]); ?></td><?php endforeach; ?></tr>
                <?php else: foreach ($cj['filas'] as $f): ?>
                <tr><th style="width:38%;text-align:left"><?php echo s($f[0]); ?></th><td><?php echo s($f[1]); ?></td></tr>
                <?php endforeach; endif; ?>
            </table></div>
            <?php break;
        case 'estampa':
            $est = \Model\Estampa::disponible() ? \Model\Estampa::find((int)($cfg['estampa_id'] ?? 0)) : null;
            if ($est && (int)$est->proyecto_id === (int)$proyecto->id): ?>
                <div class="blk" style="<?php echo $caja; ?>"><?php echo \Classes\Estampas::renderGuardada($est, $proyecto, $l, \Classes\Vplan::opcionesEstampa($cfg), 'pt', $K); ?></div>
            <?php endif;
            break;
        case 'schedule':
            if (!empty($d['simb_schedule'])): ?>
                <div class="blk" style="<?php echo $caja; ?>"><?php echo $ajustar($l->imagenSimbologiaSchedule(), $d['simb_schedule'], $bw, $bh, 1, 1); ?></div>
            <?php else: $fs = 7 * $K; ?>
                <div class="blk" style="<?php echo $caja; ?>"><table style="width:<?php echo round($bw, 2); ?>pt;height:<?php echo round($bh, 2); ?>pt"><tr><td style="vertical-align:bottom;text-align:right;padding:0"><table class="leg" align="right" style="font-size:<?php echo round($fs, 2); ?>pt;width:<?php echo round(5.6 / 2.54 * 72 * $K, 1); ?>pt">
                    <tr><td colspan="2" class="leg-tit">SYMBOLOGY FOR SCHEDULE</td></tr>
                    <tr><td class="sw verde"></td><td>COMPLETE</td></tr>
                    <tr><td class="sw amarillo"></td><td>IN PROGRESS</td></tr>
                    <tr><td class="sw rojo"></td><td>ON HOLD</td></tr>
                    <tr><td class="sw c"><img src="<?php echo \Classes\Exportadores\VplanPdfExporter::rombo(0); ?>" style="width:<?php echo round(9 * $K, 1); ?>pt"></td><td>CONSTRAIN</td></tr>
                </table></td></tr></table></div>
            <?php endif;
            break;
        case 'tareas':
        case 'tabla':
            if ($b->tipo === 'tareas') {
                $td = $d['porBloque'][spl_object_id($b)] ?? null;
                if (!$td) break;
                $idx = \Classes\VplanDiseno::escalaTareas($l, $b, $td, $nRec);
            } else {
                $bt = $d['tablasPorId'][(int)($cfg['tabla_id'] ?? 0)] ?? null;
                if (!$bt) break;
                [$fz, $pz] = \Classes\VplanDiseno::medidasTabla($l, $b, $bt, (int)($d['extra'] ?? 0));
            }
            if ($b->tipo === 'tareas') {
                $idx = min(count(\Classes\VplanDiseno::ESCALAS) - 1, $idx + (int)($d['extra'] ?? 0));
                [, $fz, $pz] = \Classes\VplanDiseno::ESCALAS[$idx];
            }
            $fs = round($fz * $K, 2); $pv = round($pz * $K, 2); $ph = round($pz * $K * 1.8 + 0.5, 2);
            ?>
            <style>.bk<?php echo $pi . '_' . $bi; ?> .t th, .bk<?php echo $pi . '_' . $bi; ?> .t td { padding: <?php echo $pv; ?>pt <?php echo $ph; ?>pt; }<?php if ($b->tipo === 'tareas'): ?> .bk<?php echo $pi . '_' . $bi; ?> th { background: <?php echo s($td['color'][0]); ?>; color: <?php echo s($td['color'][1]); ?>; }<?php endif; ?></style>
            <div class="blk bk<?php echo $pi . '_' . $bi; ?>" style="<?php echo $caja; ?>font-size:<?php echo $fs; ?>pt">
            <?php if ($b->tipo === 'tareas'): ?>
                <?php
                $orden = \Classes\VplanDiseno::ordenTareas($b->config());
                $ctx = array_column(\Classes\VplanDiseno::columnasTexto($b->config()), 'titulo', 'id');
                $cols = 0; foreach ($orden as $o) $cols += $o === 'rec' ? 2 * $nRec : 1;
                $rs = $nRec ? 2 : 1;
                $cw = \Classes\VplanDiseno::columnasTareas(\Classes\VplanDiseno::anchoRefCm($b), $nRec, $fz, $pz, $orden); $ci = 0;
                $fijas = ['seq' => '# SEQ', 'tarea' => 'TASK', 'inicio' => 'START', 'fin' => 'FINISH', 'pct' => '%', 'restr' => 'CONSTRAINS'];
                ?>
                <table class="t">
                    <tr><th colspan="<?php echo $cols; ?>" class="tit"><?php echo s($td['titulo']); ?></th></tr>
                    <tr><?php foreach ($orden as $o):
                        if ($o === 'rec') { for ($r = 1; $r <= $nRec; $r++): $ci += 2; ?><th colspan="2"><?php echo \Classes\Vplan::etiquetaRecovery($r); ?></th><?php endfor; continue; } ?>
                        <th rowspan="<?php echo $rs; ?>" style="width:<?php echo $cw[$ci++]; ?>%"><?php echo s($fijas[$o] ?? ($ctx[substr($o, 2)] ?? '')); ?></th>
                    <?php endforeach; ?></tr>
                    <?php if ($nRec): ?><tr><?php for ($r = 1; $r <= $nRec; $r++): ?><th>START</th><th>FINISH</th><?php endfor; ?></tr><?php endif; ?>
                    <?php foreach ($td['grupos'] as $g): ?>
                        <?php if ($td['subtitulos']): ?><tr class="subt"><td colspan="<?php echo $cols; ?>"><?php echo s(mb_strtoupper($g['partida']->nombre)); ?></td></tr><?php endif; ?>
                        <?php foreach ($g['tareas'] as $t): $a = $t['actividad']; $pg = (int)$a->progreso; ?>
                        <tr>
                            <?php foreach ($orden as $o):
                                switch ($o):
                                    case 'seq': ?><td class="c"><?php echo (int)$t['seq']; ?></td><?php break;
                                    case 'tarea': ?><td><?php echo s($a->nombre); ?></td><?php break;
                                    case 'inicio': ?><td class="c"><?php echo s($fd($a->fecha_inicio)); ?></td><?php break;
                                    case 'fin': ?><td class="c"><?php echo s($fd($a->fecha_fin)); ?></td><?php break;
                                    case 'rec': foreach ($t['rec'] as $r => $rc): ?><td class="c<?php echo $r > 0 ? ' rojotxt' : ''; ?>"><?php echo s($fd($rc['inicio'])); ?></td><td class="c<?php echo $r > 0 ? ' rojotxt' : ''; ?>"><?php echo s($fd($rc['fin'])); ?></td><?php endforeach; break;
                                    case 'pct': ?><td class="c <?php echo $pg >= 100 ? 'verde' : ($pg > 0 ? 'amarillo' : ''); ?>"><?php echo $pg; ?>%</td><?php break;
                                    case 'restr': ?><td class="c"><?php if ($t['restricciones']): foreach ($t['restricciones'] as $n): ?><img src="<?php echo \Classes\Exportadores\VplanPdfExporter::rombo((int)$n); ?>" style="width:<?php echo round($fz * 1.45 * $K, 1); ?>pt"> <?php endforeach; elseif ($t['estado'] === 'completo'): ?>COMPLETE<?php endif; ?></td><?php break;
                                    default: ?><td><?php echo s($t['textos'][substr($o, 2)] ?? ''); ?></td><?php
                                endswitch;
                            endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </table>
            <?php else: $b2 = $bt; $tb = $b2['tabla']; $campos = $b2['campos']; ?>
                <?php if ($tb->tipo === 'punch'): ?>
                    <table class="t punch">
                        <tr><th rowspan="2" colspan="2" class="tit"><?php echo s($tb->titulo); ?></th><th colspan="2">DATE</th><th rowspan="2" style="width:10%">%</th></tr>
                        <tr><th style="width:20%">START</th><th style="width:20%">FINISH</th></tr>
                        <?php foreach ($b2['filas'] as $f): $p = $f['pct'] ?? ''; ?>
                            <tr><td class="c" style="width:6%"><?php echo (int)$f['n']; ?></td><td><?php echo s($f['descripcion'] ?? ''); ?></td><td class="c"><?php echo s($fd($f['inicio'] ?? '')); ?></td><td class="c"><?php echo s($fd($f['fin'] ?? '')); ?></td>
                                <td class="c <?php echo $p !== '' && (int)$p >= 100 ? 'verde' : ($p !== '' && (int)$p > 0 ? 'amarillo' : ''); ?>"><?php echo $p === '' ? '' : (int)$p . '%'; ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php elseif ($tb->tipo === 'restricciones'): ?>
                    <table class="t">
                        <tr><th colspan="2" class="tit"><?php echo s($tb->titulo); ?></th></tr>
                        <?php foreach ($b2['filas'] as $f): ?>
                            <tr><td class="c" style="width:9%"><img src="<?php echo \Classes\Exportadores\VplanPdfExporter::rombo((int)$f['n']); ?>" style="width:<?php echo round($fz * 1.55 * $K, 1); ?>pt"></td><td><?php echo s($f['descripcion'] ?? ''); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php elseif ($tb->tipo === 'secundarias'): ?>
                    <table class="t sec">
                        <tr><th colspan="3" class="tit"><?php echo s($tb->titulo); ?></th></tr>
                        <tr><th style="width:50%">ELEMENTO</th><th style="width:25%">FECHA</th><th style="width:25%">FECHA (RECOVERY)</th></tr>
                        <?php foreach ($b2['filas'] as $f): $est = $f['estado'] ?? 'proceso'; $cl = ['completo' => 'verde', 'proceso' => 'amarillo', 'espera' => 'rojo'][$est] ?? ''; ?>
                            <tr class="<?php echo $cl; ?>"><td class="<?php echo $cl; ?>"><?php echo s($f['elemento'] ?? ''); ?></td><td class="c <?php echo $cl; ?>"><?php echo s($f['fecha'] ?? ''); ?></td><td class="c <?php echo $cl; ?>"><?php echo s($f['recovery'] ?? ''); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <?php $metaPdf = $tb->metaColumnas(); ?>
                    <table class="t otro">
                        <tr><th colspan="<?php echo count($campos) + 1; ?>" class="tit"><?php echo s($tb->titulo); ?></th></tr>
                        <tr><th style="width:8%">N.</th><?php foreach ($campos as [$etq]): ?><th><?php echo s($etq); ?></th><?php endforeach; ?></tr>
                        <?php foreach ($b2['filas'] as $f): ?>
                            <tr><td class="c"><?php echo (int)$f['n']; ?></td><?php foreach ($campos as $k => $c): ?><td<?php echo ($metaPdf[$k]['t'] ?? '') === 'foto' ? ' class="c"' : ''; ?>><?php
                                if (($metaPdf[$k]['t'] ?? '') === 'foto') { $mini = ($f['v' . substr($k, 1)] ?? '') !== '' ? \Classes\PunchVista::miniatura($f['v' . substr($k, 1)]) : null; echo $mini ? '<img src="' . $mini . '" style="height:' . round($fz * 3.4 * $K, 1) . 'pt">' : ''; }
                                else echo s($f[$k] ?? ''); ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
            </div>
            <?php break;
    endswitch;
endforeach; ?>
</div>
<?php endforeach; ?>
</body></html>
