<?php
/** Catálogo de conceptos. Variables: $proyecto, $partidas, $lista, $resumen, $cargos, $editar, $busca, $partidaFiltro, $nuevo, $ok, $error */
use Classes\Conceptos;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/conceptos?' . http_build_query(['proyecto' => $pid] + $q);
$moneda = fn($n) => '$' . number_format((float)$n, 2);
$n = fn($v, $d = 2) => number_format((float)$v, $d);
$r = $resumen['partidas'] ?? [];
$sin = $resumen['sinPartida'] ?? ['conceptos' => 0, 'suma' => 0.0, 'programado' => 0.0, 'pendiente' => 0.0];
$totConceptos = array_sum(array_column($r, 'conceptos')) + (int)$sin['conceptos'];
$totSuma = array_sum(array_column($r, 'suma')) + (float)$sin['suma'];
$totProg = array_sum(array_column($r, 'programado')) + (float)$sin['programado'];
$totPend = array_sum(array_column($r, 'pendiente')) + (float)$sin['pendiente'];
$factor = \Classes\CargosProyecto::factor($cargos);
$puedeBorrar = puede_borrar_contenido($proyecto);
$e = $editar ?? [];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-list-check"></i> Catálogo de conceptos</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · conceptos con cantidad y precio unitario, ligados a partidas y actividades</p>
    </div>
    <div class="la-controles">
        <a href="/costos?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-sack-dollar"></i> Costos</a>
        <a href="/exportar?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-file-export"></i> Exportar</a>
    </div>
</div>

<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div>
<?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

<?php echo aviso_esquema_catalogo(); ?>
<?php if (!Conceptos::disponible()): return; endif; ?>

<div class="cb-kpis">
    <div class="cb-kpi"><small>Conceptos</small><strong><?php echo (int)$totConceptos; ?></strong><span><?php echo count($partidas); ?> partida(s) en el programa</span></div>
    <div class="cb-kpi"><small>Presupuesto directo</small><strong><?php echo $moneda($totSuma); ?></strong><span>cantidad × precio unitario</span></div>
    <div class="cb-kpi"><small>Programado</small><strong><?php echo $moneda($totProg); ?></strong><span><?php echo $totSuma > 0 ? number_format($totProg / $totSuma * 100, 1) . ' % del catálogo' : 'sin cantidades ligadas'; ?></span></div>
    <div class="cb-kpi <?php echo $totPend > 0 ? 'cb-kpi-alerta' : ''; ?>"><small title="Conceptos que todavía no se reparten en actividades del programa">Pendiente de programar</small><strong><?php echo $moneda($totPend); ?></strong><span>aún sin actividad que lo ejecute</span></div>
    <div class="cb-kpi"><small>Cargos del proyecto</small><strong><?php echo $factor !== 1.0 ? number_format(($factor - 1) * 100, 2) . ' %' : '—'; ?></strong><span>indirectos <?php echo $n($cargos['indirectos'], 2); ?> % · utilidad <?php echo $n($cargos['utilidad'], 2); ?> % · IVA <?php echo $n($cargos['iva'], 2); ?> %<?php echo $cargos['iva_en_bac'] ? ' (en el BAC)' : ''; ?></span></div>
</div>

<div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <a class="btn btn-primary" href="<?php echo s($url(['nuevo' => 1, 'partida' => $partidaFiltro ?: null])); ?>"><i class="fa-solid fa-plus"></i> Nuevo concepto</a>
    <a class="btn btn-ghost" href="/conceptos/importar?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-file-import"></i> Importar Excel/CSV</a>
    <a class="btn btn-ghost" href="/conceptos/exportar?proyecto=<?php echo $pid; ?>&formato=pdf"><i class="fa-solid fa-file-pdf"></i> PDF</a>
    <a class="btn btn-ghost" href="/conceptos/exportar?proyecto=<?php echo $pid; ?>&formato=xlsx"><i class="fa-solid fa-file-excel"></i> Excel</a>
    <a class="btn btn-ghost" href="/conceptos/exportar?proyecto=<?php echo $pid; ?>&formato=ods"><i class="fa-solid fa-file-lines"></i> ODS</a>
</div>

<?php if ($nuevo): ?>
<div class="card">
    <h3><i class="fa-solid fa-pen-to-square"></i> <?php echo $editar ? 'Editar concepto' : 'Nuevo concepto'; ?></h3>
    <form method="POST" action="/conceptos/guardar" class="co-form">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <input type="hidden" name="id" value="<?php echo (int)($e['id'] ?? 0); ?>">
        <div class="co-campos">
            <label>Partida<select name="partida_id">
                <option value="">— Sin partida (general) —</option>
                <?php foreach ($partidas as $p): ?><option value="<?php echo (int)$p->id; ?>"<?php echo (int)($e['partida_id'] ?? $partidaFiltro) === (int)$p->id ? ' selected' : ''; ?>><?php echo s($p->codigo . ' — ' . $p->nombre); ?></option><?php endforeach; ?>
            </select></label>
            <label>Clave<input type="text" name="clave" maxlength="60" value="<?php echo s($e['clave'] ?? ''); ?>" placeholder="Ej.: CIT-04 (única en el proyecto)"></label>
            <label class="co-ancho">Descripción *<input type="text" name="descripcion" maxlength="255" required value="<?php echo s($e['descripcion'] ?? ''); ?>" placeholder="Ej.: Losa de cimentación de sala blanca"></label>
            <label>Unidad<input type="text" name="unidad" maxlength="30" list="unidades-conceptos" value="<?php echo s($e['unidad'] ?? ''); ?>" placeholder="m³, m², kg, ml, pza…"></label>
            <label>Cantidad (contractual)<input type="number" name="cantidad" min="0" step="0.001" value="<?php echo s((string)($e['cantidad'] ?? '')); ?>" required></label>
            <label>Precio unitario<input type="number" name="precio_unitario" min="0" step="0.01" value="<?php echo s((string)($e['precio_unitario'] ?? '')); ?>" required></label>
            <label>Notas<input type="text" name="notas" maxlength="500" value="<?php echo s($e['notas'] ?? ''); ?>"></label>
        </div>
        <datalist id="unidades-conceptos"><option value="m³"><option value="m²"><option value="m"><option value="ml"><option value="kg"><option value="ton"><option value="pza"><option value="lote"><option value="sal"><option value="jor"></datalist>
        <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar concepto</button>
            <a class="btn btn-ghost" href="<?php echo s($url()); ?>">Cancelar</a>
            <?php if ($editar && (int)($e['programado_qty'] ?? 0) > 0): ?><span class="text-muted text-sm">Ya está ligado a actividades: <?php echo Conceptos::cantidad($e['programado_qty'], $e['unidad']); ?>. Cambiar el precio recalcula el presupuesto.</span><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <form method="GET" action="/conceptos" class="co-form" style="margin-bottom:1rem">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <div class="co-campos">
            <label>Partida<select name="partida">
                <option value="0">— Todas —</option>
                <?php foreach ($partidas as $p): ?><option value="<?php echo (int)$p->id; ?>"<?php echo $partidaFiltro === (int)$p->id ? ' selected' : ''; ?>><?php echo s($p->codigo . ' — ' . $p->nombre); ?></option><?php endforeach; ?>
            </select></label>
            <label>Buscar<input type="search" name="q" value="<?php echo s($busca); ?>" placeholder="Clave o descripción"></label>
            <label>&nbsp;<button class="btn btn-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button></label>
        </div>
    </form>

    <?php if (!$lista): ?>
        <p class="text-muted">Todavía no hay conceptos<?php echo $busca !== '' || $partidaFiltro ? ' con esos filtros' : ''; ?>. Crea el primero o <a href="/conceptos/importar?proyecto=<?php echo $pid; ?>">importa tu catálogo</a>.</p>
    <?php else: ?>
    <div class="tabla-wrapper"><table class="tabla co-tabla">
        <thead><tr><th>Partida</th><th>Clave</th><th>Concepto</th><th>Unidad</th><th class="num">Cantidad</th><th class="num">P. unitario</th><th class="num">Importe</th><th class="num hide-m">Programado</th><th class="num">Pendiente</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $c): ?>
            <tr>
                <td><?php if ($c['partida_id']): ?><span class="co-punto" style="background:<?php echo s($c['color'] ?: '#64748b'); ?>"></span> <span class="mono"><?php echo s($c['partida_codigo']); ?></span> <?php echo s($c['partida']); ?><?php else: ?><em class="text-muted">Sin partida</em><?php endif; ?></td>
                <td class="mono"><?php echo s($c['clave'] ?: '—'); ?></td>
                <td><?php echo s($c['descripcion']); ?><?php if ($c['excedido']): ?><br><span class="badge badge-pdf" title="Lo programado supera la cantidad del catálogo"><i class="fa-solid fa-triangle-exclamation"></i> Excedido</span><?php elseif ($c['actividades'] > 0 && $c['pendiente_qty'] <= 0.0005): ?><br><span class="text-success text-sm"><i class="fa-solid fa-circle-check"></i> Programado por completo</span><?php endif; ?><?php if ($c['notas'] !== ''): ?><br><small class="text-muted"><?php echo s($c['notas']); ?></small><?php endif; ?></td>
                <td><?php echo s($c['unidad'] ?: '—'); ?></td>
                <td class="num mono"><?php echo $n($c['cantidad'], 3); ?></td>
                <td class="num mono"><?php echo $moneda($c['precio_unitario']); ?></td>
                <td class="num mono"><?php echo $moneda($c['importe']); ?></td>
                <td class="num mono hide-m"><?php echo $moneda($c['importe_programado']); ?><br><small class="text-muted"><?php echo $n($c['pct'], 1); ?> % · <?php echo (int)$c['actividades']; ?> act.</small></td>
                <td class="num mono <?php echo $c['pendiente'] > 0 ? 'co-aviso' : ''; ?>"><?php echo $c['pendiente'] > 0 ? $moneda($c['pendiente']) : '—'; ?></td>
                <td class="acciones" style="white-space:nowrap">
                    <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['editar' => (int)$c['id']])); ?>" title="Editar" aria-label="Editar"><i class="fa-solid fa-pen"></i></a>
                    <?php if ($c['partida_id'] && $c['pendiente_qty'] > 0.0005): ?>
                        <form class="inline" method="POST" action="/conceptos/repartir" data-confirm="Repartir concepto|Se repartirán <?php echo s(Conceptos::cantidad($c['pendiente_qty'], $c['unidad'])); ?> entre las actividades de la partida, por duración.|Repartir">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                            <button class="btn btn-xs btn-ghost" type="submit" title="Repartir entre las actividades de la partida" aria-label="Repartir"><i class="fa-solid fa-share-nodes"></i></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($c['partida_id']): ?>
                        <form class="inline" method="POST" action="/conceptos/actividad">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                            <button class="btn btn-xs btn-ghost" type="submit" title="Crear una actividad con lo pendiente" aria-label="Crear actividad"><i class="fa-solid fa-calendar-plus"></i></button>
                        </form>
                    <?php endif; ?>
                    <?php if ((int)$c['actividades'] === 0 && $puedeBorrar): ?>
                        <form class="inline" method="POST" action="/conceptos/eliminar" data-confirm="Eliminar concepto|Se borrará «<?php echo s($c['descripcion']); ?>» del catálogo.|Eliminar">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                            <button class="btn btn-xs btn-ghost text-danger" type="submit" title="Eliminar" aria-label="Eliminar"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="4">Total catálogo</th><th class="num mono"></th><th class="num mono"></th><th class="num mono"><?php echo $moneda($totSuma); ?></th><th class="num mono hide-m"><?php echo $moneda($totProg); ?></th><th class="num mono"><?php echo $moneda($totPend); ?></th><th></th></tr></tfoot>
    </table></div>
    <?php endif; ?>
    <p class="text-muted text-sm" style="margin-top:1rem">El <strong>importe</strong> es cantidad × precio unitario. <strong>Programado</strong> es lo que ya ejecutan las actividades; lo que falta se reparte desde aquí o desde <em>Editar actividad → Conceptos de la actividad</em>. Si lo programado supera la cantidad del catálogo se avisa y se pide un motivo. Los <strong>cargos del proyecto</strong> (indirectos, financiamiento, utilidad e IVA) se configuran en <a href="/costos?proyecto=<?php echo $pid; ?>&pestana=resumen">Costos → Resumen</a>.</p>
</div>
