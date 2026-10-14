<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1a2e; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .header { display: table; width: 100%; margin-bottom: 16px; border-bottom: 3px solid #FF5A1F; padding-bottom: 8px; }
        .header-logo { display: table-cell; width: 80px; vertical-align: middle; }
        .header-logo img { max-width: 70px; }
        .header-text { display: table-cell; vertical-align: middle; }
        .header-text h1 { font-size: 18pt; color: #0B132B; }
        .header-text p { font-size: 9pt; color: #555; margin-top: 4px; }
        .header-meta { display: table-cell; text-align: right; vertical-align: middle; font-size: 8pt; color: #777; }
        h2 { font-size: 12pt; margin: 12px 0 8px 0; color: #0B132B; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8pt; }
        th { background: #0B132B; color: #fff; padding: 5px 4px; text-align: left; font-weight: bold; }
        td { padding: 4px; border-bottom: 1px solid #e0e0e0; vertical-align: top; }
        tr.partida td { background: #f0f0f5; font-weight: bold; }
        tr.actividad td:first-child { padding-left: 14px; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7pt; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 7pt; }
        .badge-completada { background: #d4edda; color: #155724; }
        .badge-en_proceso { background: #fff3cd; color: #856404; }
        .badge-no_iniciada { background: #e2e3e5; color: #383d41; }
        .restriccion-dot { color: #dc3545; font-size: 12pt; }
        .gantt { margin-top: 8px; }
        .gantt-table { width: 100%; border-collapse: collapse; font-size: 7pt; }
        .gantt-table th, .gantt-table td { border: 1px solid #ddd; padding: 2px; }
        .gantt-table th { background: #212942; color: #fff; font-size: 7pt; }
        .gantt-table-compacto { font-size: 6.5pt; }
        .gantt-table-compacto th, .gantt-table-compacto td { padding: 1px; }
        .gantt-table-compacto th { font-size: 6.5pt; }
        .fila-partida-gantt td { background: #eef0f5; font-weight: bold; }
        .barra { height: 12px; border-radius: 2px; position: relative; }
        .gantt-compacto .barra { height: 9px; }
        .barra-completada { background: #28a745; }
        .barra-en_proceso { background: #ffc107; }
        .barra-no_iniciada { background: #adb5bd; }
        .barra-partida { background: #212942; height: 8px; }
        .gantt-compacto .barra-partida { height: 6px; }
        .footer { position: fixed; bottom: 12px; left: 0; right: 0; text-align: center; font-size: 7pt; color: #888; }
    </style>
</head>
<body>
    <!-- Página 1: Resumen + tabla -->
    <div class="page">
        <div class="header">
            <div class="header-logo">
                <?php
                // Convertir logo a base64 para que Dompdf lo renderice.
                // Dompdf no soporta webp: se convierte a PNG en memoria con GD.
                $logoBase64 = null;
                if ($proyecto->logo) {
                    $rutaLogo = __DIR__ . '/../../public/build/img/logos/' . $proyecto->logo;
                    if (file_exists($rutaLogo)) {
                        $ext = strtolower(pathinfo($rutaLogo, PATHINFO_EXTENSION));
                        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
                            $logoBase64 = 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode(file_get_contents($rutaLogo));
                        } else {
                            // Convertir (webp, etc.) a PNG
                            $img = @imagecreatefromstring(file_get_contents($rutaLogo));
                            if ($img !== false) {
                                ob_start();
                                imagepng($img);
                                $logoBase64 = 'data:image/png;base64,' . base64_encode(ob_get_clean());
                                imagedestroy($img);
                            }
                        }
                    }
                }
                ?>
                <?php if ($logoBase64): ?>
                    <img src="<?php echo $logoBase64; ?>" alt="logo" width="60" height="60">
                <?php endif; ?>
            </div>
            <div class="header-text">
                <h1><?php echo htmlspecialchars($proyecto->nombre); ?></h1>
                <p><?php echo htmlspecialchars($proyecto->codigo); ?> · <?php echo htmlspecialchars($proyecto->ubicacion); ?></p>
            </div>
            <div class="header-meta">
                <div><strong>Inicio:</strong> <?php echo date('d/m/Y', strtotime($proyecto->fecha_inicio)); ?></div>
                <div><strong>Fin:</strong> <?php echo date('d/m/Y', strtotime($proyecto->fecha_fin)); ?></div>
                <div><strong>Generado:</strong> <?php echo date('d/m/Y H:i'); ?></div>
            </div>
        </div>

        <h2>Desglose WBS & Partidas</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:8%">WBS</th>
                    <th style="width:32%">Nombre</th>
                    <th style="width:9%">Inicio</th>
                    <th style="width:9%">Fin</th>
                    <th style="width:7%">Duración</th>
                    <th style="width:8%">Progreso</th>
                    <th style="width:11%">Status</th>
                    <th style="width:9%">Prioridad</th>
                    <th style="width:7%">Restric.</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($filas as $fila):
                    $esPartida = $fila['tipo'] === 'partida';
                    $dur = $esPartida ? '—' : (!empty($fila['hito']) ? 'Hito' : number_format($calendario->calcularDuracion($fila['fecha_inicio'], $fila['fecha_fin']), 1) . ' d');
                ?>
                    <tr class="<?php echo $esPartida ? 'partida' : 'actividad'; ?>">
                        <td class="mono"><?php echo htmlspecialchars($fila['codigo']); ?></td>
                        <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
                        <td class="mono"><?php echo $fila['fecha_inicio'] ? date('d/m/y', strtotime($fila['fecha_inicio'])) : '—'; ?></td>
                        <td class="mono"><?php echo $fila['fecha_fin'] ? date('d/m/y', strtotime($fila['fecha_fin'])) : '—'; ?></td>
                        <td class="mono"><?php echo $dur; ?></td>
                        <td class="mono"><?php echo (int)$fila['progreso']; ?>%</td>
                        <td>
                            <?php if (!$esPartida): ?>
                                <span class="badge badge-<?php echo $fila['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $fila['status'])); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $esPartida ? '—' : ucfirst($fila['prioridad']); ?></td>
                        <td style="text-align:center">
                            <?php if (!$esPartida && $fila['tiene_restriccion']): ?>
                                <span class="restriccion-dot">●</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Páginas de Gantt -->
    <?php foreach ($paginas as $numPagina => $columnasPagina): ?>
        <div class="page">
            <?php if (!$modoCompacto || $numPagina > 0): ?>
                <div class="header">
                    <div class="header-text">
                        <h1><?php echo htmlspecialchars($proyecto->nombre); ?> — Gantt</h1>
                        <p>
                            <?php if (count($paginas) > 1): ?>
                                Página <?php echo $numPagina + 1; ?> de <?php echo count($paginas); ?> ·
                            <?php endif; ?>
                            Vista <?php echo $modo === 'semanal' ? 'Semanal' : ($modo === 'quincenal' ? 'Quincenal' : 'Mensual'); ?>:
                            <?php echo reset($columnasPagina)['etiqueta']; ?> a <?php echo end($columnasPagina)['etiqueta']; ?>
                        </p>
                    </div>
                    <div class="header-meta">
                        <div><strong>Generado:</strong> <?php echo date('d/m/Y H:i'); ?></div>
                    </div>
                </div>
            <?php else: ?>
                <h2>Diagrama de Gantt — Vista <?php echo $modo === 'semanal' ? 'Semanal' : ($modo === 'quincenal' ? 'Quincenal' : 'Mensual'); ?></h2>
            <?php endif; ?>

            <div class="gantt <?php echo $modoCompacto ? 'gantt-compacto' : ''; ?>">
                <table class="gantt-table <?php echo $modoCompacto ? 'gantt-table-compacto' : ''; ?>">
                    <thead>
                        <tr>
                            <th style="width:<?php echo $modoCompacto ? '18%' : '24%'; ?>">Actividad</th>
                            <?php foreach ($columnasPagina as $col): ?>
                                <th><?php echo $col['etiqueta']; ?><br><small><?php echo $col['sub']; ?></small></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $indices = array_keys($columnasPagina);
                        $primera = min($indices);
                        $ultima = max($indices);
                        $totalCols = $ultima - $primera + 1;
                        foreach ($filas as $fila):
                            [$colI, $colF] = $fila['pdf_pos'];
                            // Solo mostrar si intersecta con las columnas de esta página
                            $pagColI = max($colI, $primera + 1);
                            $pagColF = min($colF, $ultima + 1);
                            $visible = $pagColF >= $pagColI;

                            $spanAntes = $visible ? ($pagColI - $primera - 1) : $totalCols;
                            $spanBarra = $visible ? ($pagColF - $pagColI + 1) : 0;
                            $spanDespues = $visible ? ($totalCols - $spanAntes - $spanBarra) : 0;

                            $esPartida = $fila['tipo'] === 'partida';
                            $clase = $esPartida ? 'barra-partida' : 'barra-' . $fila['status'];
                        ?>
                            <tr class="<?php echo $esPartida ? 'fila-partida-gantt' : ''; ?>">
                                <td>
                                    <span class="mono"><?php echo htmlspecialchars($fila['codigo']); ?></span>
                                    <?php echo htmlspecialchars(mb_substr($fila['nombre'], 0, $modoCompacto ? 35 : 45)); ?>
                                </td>
                                <?php if (!$visible): ?>
                                    <td colspan="<?php echo $totalCols; ?>"></td>
                                <?php else: ?>
                                    <?php if ($spanAntes > 0): ?>
                                        <td colspan="<?php echo $spanAntes; ?>"></td>
                                    <?php endif; ?>
                                    <td colspan="<?php echo $spanBarra; ?>" style="padding:1px;">
                                        <div class="barra <?php echo $clase; ?>" style="width:100%;"></div>
                                    </td>
                                    <?php if ($spanDespues > 0): ?>
                                        <td colspan="<?php echo $spanDespues; ?>"></td>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="footer">
        <?php echo htmlspecialchars($proyecto->nombre); ?> · Generado por ProjectWeb · <?php echo date('d/m/Y H:i'); ?>
    </div>
</body>
</html>
