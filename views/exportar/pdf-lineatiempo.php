<?php
// Variables desde LineatiempoPdfExporter: $proyecto, $lt, $filtros, $partidasSel y la geometría
// ($etq, $eje, $cab, $ppd, $filaGrp, $filaAct, $conIconos, $meses, $MESES, $canvasAlto, $altoFilas,
//  $anchoPx, $altoPx). Todo posicionado en px como la vista del navegador.
//
// NOTA DE DOMPDF (medido): pone la línea base en "top + line-height" (no centra el texto en la
// caja) y NO respeta box-sizing, así que aquí no hay paddings verticales: la línea base de cada
// texto se calcula con  line-height = altoDeseado/2 + 0.26·fuente  para que quede centrado.
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
$idxDe = fn($f) => (int)round((strtotime($f) - strtotime($lt['inicio'])) / 86400);
$estados = ['no_iniciada' => ['#ef4444', '×'], 'en_proceso' => ['#d97706', '◐'], 'completada' => ['#22c55e', '✓']];
$filtrosTexto = \Classes\Exportadores\LineatiempoPdfExporter::textoFiltros($filtros, $partidasSel);

// Métrica de ancho de texto (misma tabla que la estimación de la vista): sirve para recortar
// etiquetas y para no meter en la barra un texto que no cabe.
$anchoDe = function (string $ch): float {
    if ($ch === ' ') return 0.31;
    if ($ch >= '0' && $ch <= '9') return 0.64;
    if ($ch >= 'A' && $ch <= 'Z') return 0.70;
    if ($ch >= 'a' && $ch <= 'z') return 0.57;
    if (mb_strpos('·•.,:;\'"|!¡()[]', $ch) !== false) return 0.32;
    if (mb_strpos('áéóúüñÁÉÓÚÜÑ', $ch) !== false) return 0.60;
    if (mb_strpos('íÍì', $ch) !== false) return 0.30;
    if (mb_strpos('—–-/_', $ch) !== false) return 0.60;
    return 0.9;   // símbolos (◆ ✓ ⚠ ✕ ◐)
};
$anchoTexto = function (string $s, float $px, float $extra = 1.0) use ($anchoDe): float {
    $w = 0.0;
    $n = mb_strlen($s);
    for ($i = 0; $i < $n; $i++) $w += $anchoDe(mb_substr($s, $i, 1));
    return $w * $px * $extra;
};
// Recorta $s (agregando «…») para que encaje en $max px
$recortar = function (string $s, float $max, float $px, float $extra = 1.0) use ($anchoTexto): string {
    if ($anchoTexto($s, $px, $extra) <= $max) return $s;
    $n = mb_strlen($s);
    for ($i = $n; $i > 1; $i--) {
        $c = mb_substr($s, 0, $i - 1) . '…';
        if ($anchoTexto($c, $px, $extra) <= $max) return $c;
    }
    return '…';
};
// Espacios no-partibles: Dompdf puede ignorar white-space:nowrap y partir la etiqueta en dos
// líneas (medido: el chip quedaba en la 1 y el texto en la 2, encimando la fila de abajo);
// sin puntos de quiebre el texto no puede saltarse de línea y, si se pasa, se recorta con overflow.
$nb = fn(string $s) => str_replace(' ', "\u{00A0}", $s);

// Eje: número de día con tipografía ajustada al ancho de la celda (2 dígitos ≈ 1.27 em + aire)
$fd = round(($ppd - 2) / 1.27, 1);
$verNumeros = $fd >= 5.5;
$anchoTira = $anchoPx - $etq;         // ancho de las filas de fondo tras la columna de etiquetas
$leyenda = [
    ['#3b82f6', '', 'Color de la partida'],
    ['#d4d4d8', '', 'Día no hábil'],
    ['#ff5a1f', '', 'Hoy'],
    ['#ef4444', '×', 'No iniciada'],
    ['#d97706', '◐', 'En proceso'],
    ['#22c55e', '✓', 'Completada'],
    ['#dc2626', '⚠', 'Con restricción'],
    ['#111827', '◆', 'Hito'],
];
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #111827; }
    .lienzo { position: relative; }
    .abs { position: absolute; overflow: hidden; white-space: nowrap; }

    /* Cabecera */
    .cab-tit { font-size: 17px; font-weight: bold; color: #0B132B; }
    .cab-sub { font-size: 10px; color: #475569; margin-top: 2px; }
    .cab-filtros { font-size: 9px; color: #334155; margin-top: 3px; }
    .cab-filtros b { color: #0B132B; }
    .leyenda { margin-top: 5px; font-size: 8.5px; color: #334155; }
    .ley { display: inline-block; margin-right: 12px; }
    .ley i { display: inline-block; width: 10px; height: 10px; margin-right: 3px; vertical-align: -1px; }
    .ley s { display: inline-block; width: 11px; height: 11px; border-radius: 6px; text-align: center; line-height: 7.6px; color: #fff; font-size: 8px; text-decoration: none; margin-right: 3px; }

    /* Eje: banda de mes (22) + celdas de día (40) = $eje. Sin paddings: solo anchos y líneas base */
    .eje-mes { height: 22px; background: #212942; color: #fff; font-size: 9px; font-weight: bold; text-align: center; line-height: 13px; border-right: 1px solid #3b4666; }
    .eje-dia { height: 40px; background: #f1f5f9; border-right: 1px solid #e2e8f0; text-align: center; color: #475569; }
    .eje-dia b { color: #0f172a; }
    .eje-dia.nohab { background: #d4d4d8; }
    .eje-dia.hoy { background: #ff5a1f; color: #fff; } .eje-dia.hoy b { color: #fff; }
    .lab-cab { background: #212942; color: #fff; font-size: 9.5px; font-weight: bold; line-height: 14px; padding-left: 8px; }

    /* Diagrama (color sólido: Dompdf no pinta rgba() con decimal inicial ".14") */
    .fondo { background: #ededed; }
    .hoy-linea { width: 2px; background: #ff5a1f; }

    /* Etiquetas (izquierda) */
    .lab-grp { font-size: 9.5px; font-weight: bold; color: #0f172a; background: #eef0f5; border-bottom: 1px solid #d8dce6; padding: 0 6px; }
    .lab-grp i { display: inline-block; width: 10px; height: 10px; margin-right: 5px; vertical-align: -1px; }
    .lab { font-size: 9px; color: #1f2937; padding-left: 14px; }
    .lab b { color: #0f172a; }
    .warn { color: #dc2626; font-weight: bold; }

    /* Filas del lienzo y barras */
    .grp { background: #e9ecf3; border-bottom: 1px solid #d8dce6; font-size: 9px; font-weight: bold; color: #334155; padding: 0 6px; }
    .grp i { display: inline-block; width: 10px; height: 10px; margin-right: 5px; vertical-align: -1px; }
    .fila { border-bottom: 1px solid #eef1f6; }
    .barra { position: absolute; overflow: hidden; border-radius: 3px; }
    .barra .fill { position: absolute; left: 0; top: 0; bottom: 0; }
    .barra .txt { position: absolute; left: 0; text-align: center; color: #fff; font-weight: bold; text-shadow: 0 1px 2px rgba(0, 0, 0, .55); }
    .chip { position: absolute; width: 16px; height: 16px; border-radius: 8px; color: #fff; font-size: 10px; text-align: center; font-weight: bold; }
    .hito { position: absolute; color: #111827; font-size: 15px; text-align: center; }
    .icono { position: absolute; border-radius: 12px; }
</style></head>
<body>
<div class="lienzo" style="width:<?php echo $anchoPx; ?>px;height:<?php echo $altoPx; ?>px">

    <!-- ===================== Cabecera ===================== -->
    <div class="abs" style="left:12px;top:14px;width:<?php echo $anchoPx - 24; ?>px;white-space:normal;overflow:visible">
        <div class="cab-tit"><?php echo $h($proyecto->nombre); ?> — Línea de tiempo activa</div>
        <div class="cab-sub"><?php echo trim($proyecto->codigo . ' · ' . $proyecto->ubicacion, ' ·'); ?> · Del <?php echo date('d/m/Y', strtotime($lt['inicio'])); ?> al <?php echo date('d/m/Y', strtotime($lt['fin'])); ?> · Generado <?php echo date('d/m/Y H:i'); ?></div>
        <div class="cab-filtros"><b>Filtros:</b> <?php echo $h($filtrosTexto); ?> · <b><?php echo (int)$lt['total']; ?></b> actividades<?php echo $lt['conRestriccion'] ? ' (<span class="warn">' . (int)$lt['conRestriccion'] . ' con restricción</span>)' : ''; ?></div>
        <div class="leyenda">
            <?php foreach ($leyenda as [$c, $glifo, $texto]): ?>
                <span class="ley"><?php if ($glifo !== '' && $glifo !== '◆'): ?><s style="background:<?php echo $c; ?>"><?php echo $glifo; ?></s><?php else: ?><i style="background:<?php echo $c; ?>"></i><?php endif; ?><?php echo $h($texto); ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===================== Eje de tiempo ===================== -->
    <div class="abs" style="left:0;top:<?php echo $cab; ?>px;width:<?php echo $anchoPx; ?>px;height:<?php echo $eje; ?>px;overflow:visible">
        <div class="abs lab-cab" style="left:0;top:0;width:<?php echo $etq - 8; ?>px;height:<?php echo $eje; ?>px">Actividad / partida</div>
        <?php foreach ($meses as $m): ?>
            <div class="abs eje-mes" style="left:<?php echo $etq + $m['i0'] * $ppd; ?>px;top:0;width:<?php echo max(2, ($m['i1'] - $m['i0'] + 1) * $ppd - 1); ?>px"><?php echo $h($nb($MESES[$m['mes']] . ' ' . $m['anio'])); ?></div>
        <?php endforeach; ?>
        <?php foreach ($lt['dias'] as $i => $d):
            $clases = 'abs eje-dia' . ($d['habil'] ? '' : ' nohab') . ($d['fecha'] === $lt['hoy'] ? ' hoy' : '');
            // Línea base centrada en la celda de 40 px (top + line-height, como hace Dompdf)
            $lhDia = round(20 + 0.26 * $fd, 1);
        ?>
            <div class="<?php echo $clases; ?>" style="left:<?php echo $etq + $i * $ppd; ?>px;top:22px;width:<?php echo max(1, $ppd - 1); ?>px;line-height:<?php echo $lhDia; ?>px;font-size:<?php echo $fd; ?>px"><?php echo $verNumeros ? '<b>' . (int)$d['nro'] . '</b>' : ''; ?></div>
        <?php endforeach; ?>
    </div>

    <!-- ===================== Diagrama ===================== -->
    <div class="abs" style="left:0;top:<?php echo $cab + $eje; ?>px;width:<?php echo $anchoPx; ?>px;height:<?php echo $altoFilas; ?>px;overflow:visible">

        <!-- Fondo: columnas de días no hábiles y línea de hoy (alto fijo: bottom no funciona en Dompdf) -->
        <?php foreach ($lt['dias'] as $i => $d): if ($d['habil']) continue; ?>
            <div class="abs fondo" style="left:<?php echo $etq + $i * $ppd; ?>px;top:0;width:<?php echo $ppd; ?>px;height:<?php echo $altoFilas; ?>px"></div>
        <?php endforeach; ?>
        <?php $hoyIdx = $idxDe($lt['hoy']); ?>
        <?php if ($hoyIdx >= 0 && $hoyIdx < count($lt['dias'])): ?>
            <div class="abs hoy-linea" style="left:<?php echo $etq + $hoyIdx * $ppd + (int)($ppd / 2); ?>px;top:0;height:<?php echo $altoFilas; ?>px"></div>
        <?php endif; ?>

        <?php $y = 0; ?>
        <?php foreach ($lt['partidas'] as $p):
            $acts = array_values(array_filter($lt['actividades'], fn($a) => (int)$a['partida_id'] === (int)$p['id']));
            if (!$acts) continue;
            $color = $p['color'] ?: '#3b82f6';
            $lhGrp = round($filaGrp / 2 + 0.26 * 9.5, 1);   // texto centrado en la banda de partida
        ?>
            <!-- Partida (holgura del 15 % por ser negrita y espacios no-partibles: nunca parte a 2 líneas) -->
            <div class="abs lab-grp" style="left:0;top:<?php echo $y; ?>px;width:238px;height:<?php echo $filaGrp - 1; ?>px;line-height:<?php echo $lhGrp; ?>px;white-space:nowrap"><i style="background:<?php echo $h($color); ?>"></i><?php echo $h($nb($recortar($p['codigo'] . ' · ' . $p['nombre'], 238 - 15 - ($p['restricciones'] ? 30 : 0) - 4, 9.5, 1.15))); ?><?php echo $p['restricciones'] ? '<span class="warn">&nbsp;⚠&nbsp;' . (int)$p['restricciones'] . '</span>' : ''; ?></div>
            <div class="abs grp" style="left:<?php echo $etq; ?>px;top:<?php echo $y; ?>px;width:<?php echo $anchoTira; ?>px;height:<?php echo $filaGrp - 1; ?>px;line-height:<?php echo $lhGrp; ?>px;white-space:nowrap"><i style="background:<?php echo $h($color); ?>"></i><?php echo $h($nb($recortar($p['codigo'] . ' · ' . $p['nombre'], $anchoTira - 30, 9, 1.15))); ?></div>
            <?php $y += $filaGrp; ?>

            <?php foreach ($acts as $a):
                $altoBarra = min(22, max(10, $filaAct - 8));
                $yBarra = $y + (int)(($filaAct - $altoBarra) / 2);
                [$i0, $i1] = [max(0, $idxDe($a['fecha_inicio'])), min(count($lt['dias']) - 1, $idxDe($a['fecha_fin']))];
                if ($i1 < $i0) $i1 = $i0;
                $x0 = $etq + $i0 * $ppd;
                $anchoBarra = ($i1 - $i0 + 1) * $ppd;
                [$chipColor, $chipGlifo] = $estados[$a['status']] ?? $estados['no_iniciada'];

                // Etiqueta: código + nombre recortado al espacio real (con holguras: la negrita
                // del código pesa ~15 % más que la métrica base)
                $warnW = $a['restriccion'] ? 14 : 0;
                $presupuesto = 236 - $warnW - $anchoTexto($a['codigo'], 9, 1.15) - 6;
                $nombre = $recortar((string)$a['nombre'], $presupuesto, 9, 1.06);

                // Texto de la barra: tamaño ajustado para que quepa dentro (si no, no se dibuja)
                $txtBarra = $a['hito'] ? '' : $a['codigo'] . ' (' . (int)$a['duracion_dias'] . ' d)';
                $ftxt = 8;
                if ($txtBarra !== '') {
                    $nec = $anchoTexto($txtBarra, 8, 1.08) * 1.1;
                    $ftxt = min(8, floor((($anchoBarra - 4) * 8 / max(1, $nec)) * 10) / 10);
                    if ($ftxt < 6) $txtBarra = '';
                }
                $lhBarra = round($altoBarra / 2 + 0.26 * $ftxt, 1);   // texto centrado en la barra
                $lhChip = round(8 + 0.26 * 10, 1);                     // glifo dentro del disco de 16 px
                $lhLab = $filaAct <= 40 ? round($filaAct / 2 + 0.26 * 9, 1) : 16;
            ?>
                <!-- Etiqueta -->
                <div class="abs lab" style="left:0;top:<?php echo $y; ?>px;width:236px;height:<?php echo $filaAct; ?>px;line-height:<?php echo $lhLab; ?>px;white-space:nowrap"><b><?php echo $h($nb((string)$a['codigo'])); ?></b>&nbsp;<?php echo $h($nb($nombre)); ?><?php echo $a['restriccion'] ? '<span class="warn">&nbsp;⚠</span>' : ''; ?></div>
                <div class="abs fila" style="left:<?php echo $etq; ?>px;top:<?php echo $y; ?>px;width:<?php echo $anchoTira; ?>px;height:<?php echo $filaAct - 1; ?>px"></div>

                <?php if ($a['hito']): ?>
                    <!-- Hito: rombo sobre su día -->
                    <div class="abs hito" style="left:<?php echo $x0; ?>px;top:<?php echo $yBarra; ?>px;width:<?php echo max($ppd, 16); ?>px;height:<?php echo $altoBarra; ?>px;line-height:<?php echo round($altoBarra / 2 + 0.26 * 15, 1); ?>px">◆</div>
                <?php else: ?>
                    <!-- Barra con su avance -->
                    <div class="barra" style="left:<?php echo $x0; ?>px;top:<?php echo $yBarra; ?>px;width:<?php echo $anchoBarra; ?>px;height:<?php echo $altoBarra; ?>px;background:<?php echo $h($color); ?>">
                        <?php if ((int)$a['progreso'] > 0): ?><span class="fill" style="width:<?php echo min(100, (int)$a['progreso']); ?>%;background:<?php echo \Classes\Exportadores\LineatiempoPdfExporter::claro($color); ?>"></span><?php endif; ?>
                        <?php if ($txtBarra !== ''): ?><span class="txt" style="width:100%;line-height:<?php echo $lhBarra; ?>px;font-size:<?php echo $ftxt; ?>px"><?php echo $h($nb($txtBarra)); ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Chips a la derecha de la barra: estado y restricción (si no caben al borde, se recogen como en la vista) -->
                <?php
                $anchoChips = $a['restriccion'] ? 41 : 16;
                $xCip = $x0 + $anchoBarra + 5;
                $maxCip = $etq + count($lt['dias']) * $ppd + 48 - $anchoChips;
                if ($xCip > $maxCip) $xCip = max($x0 + 2, $maxCip);
                $yChip = $y + (int)(($filaAct - 16) / 2);
                ?>
                <div class="chip" style="left:<?php echo $xCip; ?>px;top:<?php echo $yChip; ?>px;background:<?php echo $chipColor; ?>;line-height:<?php echo $lhChip; ?>px"><?php echo $chipGlifo; ?></div>
                <?php if ($a['restriccion']): ?>
                    <div class="chip" style="left:<?php echo $xCip + 25; ?>px;top:<?php echo $yChip; ?>px;background:#dc2626;line-height:<?php echo $lhChip; ?>px">⚠</div>
                <?php endif; ?>

                <!-- Icono de actividad (si la casilla está activada) -->
                <?php if ($conIconos && !empty($a['icono'])):
                    $img = \Classes\Exportadores\MsrPdfExporter::imagenBase64(basename((string)$a['icono']), 'build/img/actividades-iconos');
                    if ($img): ?>
                        <img class="icono" src="<?php echo $img; ?>" style="left:<?php echo $x0 + (int)($anchoBarra / 2) - 24; ?>px;top:<?php echo $y + 2; ?>px;width:40px;height:40px">
                    <?php endif; ?>
                <?php endif; ?>

                <?php $y += $filaAct; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>
</body></html>
