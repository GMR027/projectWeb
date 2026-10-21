<?php
// Guía «Por dónde empezar». Variables: $pdeProyecto (Proyecto), $pdeModo ('guiado'|'lista'), $pdeEnModal (bool)
$pdePid = (int)$pdeProyecto->id;
$pdePasos = \Classes\PorDondeEmpezar::pasos($pdePid, is_admin());
[$pdeHechos, $pdeTotal] = \Classes\PorDondeEmpezar::avance($pdePasos);
$pdePct = $pdeTotal ? round($pdeHechos / $pdeTotal * 100) : 0;
$pdeInput = fn($nombre, $etiqueta, $extra = '') => '<label class="pde-campo"><span>' . $etiqueta . '</span><input name="' . $nombre . '" ' . $extra . '></label>';
?>
<div class="pde" data-proyecto="<?php echo $pdePid; ?>" data-modo="<?php echo s($pdeModo); ?>">
    <div class="pde-cabecera">
        <div class="pde-progreso" title="<?php echo $pdePct; ?> %"><span style="width: <?php echo $pdePct; ?>%"></span></div>
        <p class="text-muted text-sm"><strong class="pde-hechos"><?php echo $pdeHechos; ?></strong> de <?php echo $pdeTotal; ?> pasos listos · todo es opcional, puedes volver cuando quieras</p>
        <div class="pde-modos" role="group" aria-label="Modo">
            <?php foreach (\Classes\PorDondeEmpezar::MODOS as $clave => $nombre): ?>
                <button type="button" class="btn btn-ghost<?php echo $pdeModo === $clave ? ' activo' : ''; ?>" data-pde-modo="<?php echo $clave; ?>"><i class="fa-solid <?php echo $clave === 'guiado' ? 'fa-forward-step' : 'fa-list'; ?>"></i> <?php echo s($nombre); ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="pde-mensaje" role="status" hidden></div>
    <div class="pde-pasos">
    <?php foreach ($pdePasos as $i => $p): ?>
        <section class="pde-paso<?php echo $p['hecho'] ? ' hecho' : ''; ?>" data-paso="<?php echo s($p['id']); ?>">
            <header>
                <i class="fa-solid <?php echo s($p['icono']); ?>"></i>
                <h4><?php echo ($i + 1) . '. ' . s($p['titulo']); ?><?php echo !empty($p['solo_admin']) ? ' <small class="text-muted">(solo administrador)</small>' : ''; ?></h4>
                <span class="pde-estado"><?php echo $p['hecho'] ? '<i class="fa-solid fa-circle-check"></i> ' . (int)$p['cuenta'] . ' ' . s($p['unidad']) : 'Pendiente'; ?></span>
            </header>
            <p class="text-muted text-sm"><?php echo s($p['porque']); ?></p>
            <?php
            $f = fn($api, $campos, $boton, $multipart = false) => '<form class="pde-form" data-api="/api/empezar/' . $api . '"' . ($multipart ? ' enctype="multipart/form-data"' : '') . '>' . $campos . '<button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> ' . $boton . '</button></form>';
            switch ($p['id']) {
                case 'calendario':
                    echo $f('festivo', $pdeInput('fecha', 'Fecha', 'type="date" required') . $pdeInput('motivo', 'Motivo', 'maxlength="150" required placeholder="Ej: Día del trabajo"'), 'Agregar festivo');
                    break;
                case 'programa':
                    echo $f('partida', $pdeInput('codigo', 'Código', 'maxlength="20" required placeholder="1.0"') . $pdeInput('nombre', 'Nombre de la partida', 'maxlength="200" required placeholder="Ej: Preliminares"'), 'Crear partida');
                    break;
                case 'equipo':
                    echo $f('persona', $pdeInput('nombre', 'Nombre', 'maxlength="150" required') . $pdeInput('departamento', 'Departamento o cargo', 'maxlength="100"'), 'Agregar persona');
                    break;
                case 'materiales':
                    echo $f('material', $pdeInput('nombre', 'Material', 'maxlength="255" required') . $pdeInput('especialidad', 'Especialidad', 'maxlength="100"') . $pdeInput('cantidad', 'Cantidad', 'inputmode="decimal"') . $pdeInput('unidad', 'Unidad', 'maxlength="30"'), 'Agregar material');
                    break;
                case 'documentos':
                    echo $f('documento', $pdeInput('nombre', 'Nombre (opcional)', 'maxlength="200"') . '<label class="pde-campo"><span>Archivo</span><input type="file" name="archivo" required></label>', 'Subir archivo', true);
                    break;
                case 'contrato':
                    echo $f('contrato', '<label class="pde-campo"><span>Tipo</span><select name="tipo"><option value="inicio">Inicio</option><option value="fin">Fin</option><option value="hito">Hito</option></select></label>' . $pdeInput('nombre', 'Nombre (opcional)', 'maxlength="200"') . $pdeInput('fecha', 'Fecha', 'type="date" required') . $pdeInput('penalizacion', 'Penalización (opcional)', 'maxlength="200"'), 'Agregar fecha');
                    break;
            }
            ?>
            <?php if ($p['herramienta']): ?>
                <a class="pde-enlace" href="<?php echo s($p['herramienta'][1]); ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir: <?php echo s($p['herramienta'][0]); ?></a>
            <?php else: ?>
                <p class="text-sm">Instala la aplicación de escritorio desde esta misma página de Ajustes y abre <em>Ensamblador</em> en las herramientas de IA.</p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    </div>
    <div class="pde-nav">
        <button type="button" class="btn btn-ghost" data-pde-ant><i class="fa-solid fa-arrow-left"></i> Anterior</button>
        <span class="pde-pos text-muted text-sm"></span>
        <button type="button" class="btn btn-secondary" data-pde-sig>Siguiente <i class="fa-solid fa-arrow-right"></i></button>
    </div>
</div>
