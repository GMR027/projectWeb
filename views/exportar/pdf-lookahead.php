<?php
// Variables: $proyecto, $semanas, $tipos, $filas, $lunes, $logoBase64
$colorX = ['completada' => '#16a34a', 'en_proceso' => '#b7791f', 'no_iniciada' => '#555555', 'restriccion' => '#dc2626'];
$nDias = count($semanas) * 7;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 22px 20px 26px 20px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 6.5pt; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th, td { border: 0.5pt solid #444; padding: 1.5pt 1pt; text-align: center; overflow: hidden; }
    .esp th { padding: 0; border: 0; height: 0; line-height: 0; font-size: 0; }
    .banner { background: #0a2a6b; color: #fff; font-size: 11pt; font-weight: bold; padding: 5pt; }
    .info { text-align: left; background: #ddd; font-weight: bold; font-size: 8pt; }
    .info small { font-weight: normal; font-size: 7pt; }
    .sem { background: #0b6fc0; color: #fff; font-size: 6pt; }
    .sem-act { background: #0a8fb0; }
    .mes { background: #fff; font-size: 5.5pt; }
    .dia { background: #ddd; font-size: 5.5pt; }
    .inh { background: #cfcfcf; }
    .cab { background: #ddd; font-weight: bold; }
    .tipo { background: #0b6fc0; padding: 0; }
    .partida td { background: #cfcfcf; font-weight: bold; text-align: left; text-transform: uppercase; }
    .id { font-size: 6pt; }
    .nom { text-align: left; }
    .x { font-weight: bold; font-size: 7pt; }
    .ok { color: #16a34a; font-weight: bold; }
    .no { color: #dc2626; font-weight: bold; }
    .c100 { background: #22c55e; font-weight: bold; }
    .cpar { background: #facc15; font-weight: bold; }
    .atr { color: #dc2626; }
    .foot { margin-top: 6pt; font-size: 6pt; color: #555; }
</style>
</head>
<body>
<table>
    <thead>
        <tr class="esp">
            <th width="32" style="width:32pt"></th><th width="262" style="width:262pt"></th>
            <?php for ($i = 0; $i < $nDias; $i++): ?><th width="15" style="width:15pt"></th><?php if ($i === 6): ?><th width="24" style="width:24pt"></th><th width="17" style="width:17pt"></th><?php endif; ?><?php endfor; ?>
            <?php foreach ($tipos as $t): ?><th width="19" style="width:19pt"></th><?php endforeach; ?>
        </tr>
        <tr>
            <th colspan="2" class="info">
                <?php if ($logoContratista): ?><img src="<?php echo $logoContratista; ?>" style="height:22pt; float:right; margin-left:6pt;"><?php endif; ?>
                <?php if ($logoBase64): ?><img src="<?php echo $logoBase64; ?>" style="height:22pt; float:right;"><?php endif; ?>
                <?php echo s($proyecto->nombre); ?><br>
                <small><strong>Inicio:</strong> <?php echo date('d/m/Y', strtotime($proyecto->fecha_inicio)); ?> &nbsp; <strong>Fin:</strong> <?php echo date('d/m/Y', strtotime($proyecto->fecha_fin)); ?></small><br>
                <small><strong>Generado:</strong> <?php echo date('d/m/Y H:i'); ?></small>
            </th>
            <th colspan="<?php echo $nDias + 2; ?>" class="banner">4 Weeks Look Ahead · <?php echo s($proyecto->nombre); ?></th>
            <th colspan="<?php echo count($tipos); ?>" class="banner" style="font-size:8pt;">Limitaciones</th>
        </tr>
        <tr>
            <th class="cab">ID<br>(EDT)</th>
            <th class="cab" style="font-size:8pt;">ACTIVIDAD</th>
            <?php foreach ($semanas as $i => $sem): ?>
                <th colspan="7" class="sem <?php echo $i === 0 ? 'sem-act' : ''; ?>"><?php echo s($sem['etiqueta']); ?> · S<?php echo s($sem['iso']); ?><br><span style="font-size:5pt"><?php echo s($sem['mes']); ?></span></th>
                <?php if ($i === 0): ?><th class="cab" rowspan="2">%<br>Comp</th><th class="cab" rowspan="2">PTA</th><?php endif; ?>
            <?php endforeach; ?>
            <?php foreach ($tipos as $t): ?>
                <th class="tipo" rowspan="2"><img src="<?php echo $tiposImg[$t->id]['src']; ?>" style="width:<?php echo round($tiposImg[$t->id]['ancho'], 1); ?>pt; height:<?php echo round($tiposImg[$t->id]['alto'], 1); ?>pt;"></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <th class="cab"></th><th class="cab"></th>
            <?php foreach ($semanas as $sem): foreach ($sem['dias'] as $d): ?>
                <th class="dia <?php echo !$d['habil'] ? 'inh' : ''; ?>"><?php echo s($d['numero']); ?><br><?php echo s($d['letra']); ?></th>
            <?php endforeach; endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($filas as $bloque): $partida = $bloque['partida']; ?>
            <tr class="partida">
                <td class="id"><?php echo s($partida->codigo); ?></td>
                <td colspan="<?php echo $nDias + 3 + count($tipos); ?>"><?php echo s($partida->nombre); ?></td>
            </tr>
            <?php foreach ($bloque['actividades'] as $f): $act = $f['actividad']; ?>
                <?php $color = $colorX[$f['con_restriccion'] ? 'restriccion' : $act->status] ?? '#555'; $prog = (int)$act->progreso; ?>
                <tr>
                    <td class="id"><?php echo s($act->codigo); ?></td>
                    <td class="nom <?php echo $f['atrasada'] ? 'atr' : ''; ?>"><?php echo s($act->nombre); ?></td>
                    <?php foreach ($semanas as $i => $sem): ?>
                        <?php foreach ($sem['dias'] as $d): $on = $f['celdas'][$d['fecha']] ?? false; ?>
                            <td class="<?php echo !$d['habil'] ? 'inh' : ''; ?> <?php echo $on ? 'x' : ''; ?>" <?php echo $on ? 'style="color:' . $color . '"' : ''; ?>><?php echo $on ? 'X' : '&nbsp;'; ?></td>
                        <?php endforeach; ?>
                        <?php if ($i === 0): ?>
                            <td class="<?php echo $prog >= 100 ? 'c100' : ($prog > 0 ? 'cpar' : ''); ?>"><?php echo $prog; ?>%</td>
                            <td class="<?php echo $f['pta_ok'] ? 'ok' : 'no'; ?>"><?php echo $f['pta_ok'] ? 'OK' : '!'; ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php foreach ($tipos as $t): $on = isset($f['tipos'][$t->id]); ?>
                        <td <?php echo $on ? 'style="color:' . s($t->color) . ';font-size:8pt"' : ''; ?>><?php echo $on ? '&#9679;' : '&nbsp;'; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </tbody>
</table>
<div class="foot">X = día programado · PTA: OK = lista para ejecutarse, ! = pendiente · Semana actual desde el <?php echo $lunes->format('d/m/Y'); ?></div>
</body>
</html>
