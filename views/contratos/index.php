<?php
/** Contratos, fianzas, seguros y licencias. Variables: $proyecto, $disponible, $filtro, $lista, $resumen, $editar, $abrirForm, $archivos, $puedeEditar, $ok, $error */
use Classes\Contratos;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/contratos?' . http_build_query(['proyecto' => $pid] + $q);
$e = $editar;
$situaciones = [
    'vigente' => ['Vigente', 'cliente'], 'por_vencer' => ['Por vencer', 'visitante'],
    'vencido' => ['Vencido', 'admin'], 'cerrado' => ['Cerrado', 'visitante'],
];
$dinero = fn($m, $mon) => number_format((float)$m, 2) . ' ' . s($mon);
$proximos = array_values(array_filter($lista, fn($c) => !empty($c['vencimiento']) && $c['situacion'] !== 'cerrado'));
usort($proximos, fn($a, $b) => (int)$a['dias'] <=> (int)$b['dias']);
$proximos = array_slice($proximos, 0, 5);
?>
<div class="page-header">
    <div>
        <span class="adm-eyebrow">Administración de obra</span>
        <h2><i class="fa-solid fa-file-signature"></i> Contratos, fianzas y seguros</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · vencimientos, montos y documentos</p>
        <div class="adm-meta">
            <span><i class="fa-solid fa-circle-check"></i> <b><?php echo (int)($resumen['vigente'] ?? 0); ?></b> vigentes</span>
            <span><i class="fa-solid fa-clock"></i> <b><?php echo (int)($resumen['por_vencer'] ?? 0); ?></b> por vencer</span>
            <span><i class="fa-solid fa-triangle-exclamation"></i> <b><?php echo (int)($resumen['vencido'] ?? 0); ?></b> vencidos</span>
        </div>
    </div>
    <?php if ($puedeEditar && $disponible): ?><a href="<?php echo s($url(['nuevo' => 1])); ?>" class="btn btn-primary">+ Nuevo registro</a><?php endif; ?>
</div>

<?php if (es_demo()): ?>
<div class="alerta alerta-info">
    <strong>Demo · Contratos, fianzas y seguros.</strong> Hay 7 registros que cubren las cuatro situaciones: <em>vigente</em>, <em>por vencer</em>, <em>vencido</em> y <em>cerrado</em>.
    La situación se calcula con la fecha de vencimiento y los <strong>días de aviso</strong> de cada registro. Prueba los filtros de arriba y abre el clip de un documento del Archivo. En tu proyecto puedes crear y editar registros.
    <a href="/manual#contratos">Ver en el manual</a>.
</div>
<?php endif; ?>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_calidad_equipo_contratos_cierre.sql</code> para usar Contratos.</p></div>
<?php return; endif; ?>

<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

<div class="cb-kpis">
    <div class="cb-kpi"><span class="adm-kpi-icono"><i class="fa-solid fa-circle-check"></i></span><small>Vigentes</small><strong><?php echo (int)$resumen['vigente']; ?></strong><span>Sin vencimiento próximo</span></div>
    <div class="cb-kpi <?php echo $resumen['por_vencer'] ? 'cb-kpi-alerta' : ''; ?>"><span class="adm-kpi-icono"><i class="fa-solid fa-clock"></i></span><small>Por vencer</small><strong><?php echo (int)$resumen['por_vencer']; ?></strong><span>Dentro del aviso configurado</span></div>
    <div class="cb-kpi <?php echo $resumen['vencido'] ? 'cb-kpi-alerta' : ''; ?>"><span class="adm-kpi-icono"><i class="fa-solid fa-triangle-exclamation"></i></span><small>Vencidos</small><strong><?php echo (int)$resumen['vencido']; ?></strong><span>Requieren renovación o cierre</span></div>
    <div class="cb-kpi is-info"><span class="adm-kpi-icono"><i class="fa-solid fa-layer-group"></i></span><small>Registros</small><strong><?php echo (int)$resumen['vigente'] + (int)$resumen['por_vencer'] + (int)$resumen['vencido'] + (int)$resumen['cerrado']; ?></strong><span>Contratos, fianzas, seguros y licencias</span></div>
</div>

<?php if ($puedeEditar && $abrirForm): ?>
<form method="POST" action="/contratos/guardar" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="hidden" name="id" value="<?php echo $e ? (int)$e['id'] : 0; ?>">
    <h3><?php echo $e ? 'Editar registro' : 'Nuevo contrato, fianza o seguro'; ?></h3>
    <div class="co-campos">
        <label>Tipo<select name="tipo"><?php foreach (Contratos::TIPOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($e['tipo'] ?? 'contrato') === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></label>
        <label>Número<input type="text" name="numero" maxlength="80" value="<?php echo s($e['numero'] ?? ''); ?>"></label>
        <label class="co-ancho">Contraparte<input type="text" name="contraparte" maxlength="200" value="<?php echo s($e['contraparte'] ?? ''); ?>" placeholder="Proveedor, aseguradora, dependencia…"></label>
        <label class="co-ancho">Objeto<input type="text" name="objeto" maxlength="300" value="<?php echo s($e['objeto'] ?? ''); ?>" placeholder="Suministro, obra, seguro de responsabilidad…"></label>
        <label>Monto<input type="number" name="monto" min="0" step="0.01" value="<?php echo s($e['monto'] ?? '0'); ?>"></label>
        <label>Moneda<select name="moneda"><?php foreach (Contratos::MONEDAS as $m): ?><option value="<?php echo $m; ?>"<?php echo ($e['moneda'] ?? 'MXN') === $m ? ' selected' : ''; ?>><?php echo $m; ?></option><?php endforeach; ?></select></label>
        <label>Inicio<input type="date" name="inicio" value="<?php echo s($e['inicio'] ?? ''); ?>"></label>
        <label>Vencimiento<input type="date" name="vencimiento" value="<?php echo s($e['vencimiento'] ?? ''); ?>"></label>
        <label>Aviso previo (días)<input type="number" name="aviso_dias" min="0" max="365" value="<?php echo s($e['aviso_dias'] ?? 30); ?>"></label>
        <label>Estado<select name="estado"><option value="vigente"<?php echo ($e['estado'] ?? 'vigente') === 'vigente' ? ' selected' : ''; ?>>Vigente</option><option value="cerrado"<?php echo ($e['estado'] ?? '') === 'cerrado' ? ' selected' : ''; ?>>Cerrado</option></select></label>
        <label class="co-ancho">Documento en el Archivo (opcional)<select name="archivo_id"><option value="0">— Sin documento —</option><?php foreach ($archivos as $a): ?><option value="<?php echo (int)$a->id; ?>"<?php echo (int)($e['archivo_id'] ?? 0) === (int)$a->id ? ' selected' : ''; ?>><?php echo s($a->nombre . ($a->numero_revision ? ' · rev. ' . $a->numero_revision : '')); ?></option><?php endforeach; ?></select></label>
        <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"><?php echo s($e['notas'] ?? ''); ?></textarea></label>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="<?php echo s($url()); ?>" class="btn btn-ghost">Cancelar</a>
</form>
<?php endif; ?>

<div class="cb-filtros">
    <a href="<?php echo s($url()); ?>" class="cb-chip<?php echo $filtro === '' ? ' activo' : ''; ?>">Todos <b><?php echo (int)$resumen['vigente'] + (int)$resumen['por_vencer'] + (int)$resumen['vencido'] + (int)$resumen['cerrado']; ?></b></a>
    <?php foreach ($situaciones as $k => [$etq, ]): ?><a href="<?php echo s($url(['situacion' => $k])); ?>" class="cb-chip<?php echo $filtro === $k ? ' activo' : ''; ?>"><?php echo s($etq); ?> <b><?php echo (int)$resumen[$k]; ?></b></a><?php endforeach; ?>
</div>

<div class="adm-split">
<div class="card adm-panel">
    <div class="adm-panel-cab">
        <div>
            <h3><i class="fa-solid fa-table-list"></i> Registros</h3>
            <p><?php echo count($lista); ?> registro(s)<?php echo $filtro !== '' ? ' con el filtro activo' : ' en total'; ?> · vencimientos, montos y documentos.</p>
        </div>
        <span class="cb-tag"><?php echo count($situaciones); ?> situaciones</span>
    </div>
    <div class="adm-panel-cuerpo">
    <?php if (!$lista): ?><?php echo estado_vacio('fa-file-signature',
        $filtro !== '' ? 'Ningún registro coincide con el filtro' : 'Aún no hay contratos, fianzas ni seguros',
        $filtro !== '' ? 'Prueba con otro tipo o situación.' : 'Registra los contratos, fianzas, seguros y licencias con sus vencimientos.',
        $filtro !== '' || !$puedeEditar || !$disponible ? null : ['texto' => 'Nuevo registro', 'url' => $url(['nuevo' => 1]), 'icono' => 'fa-plus'],
        null, '', 'estado-vacio-sm'); ?><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla" <?php echo pag_attr($proyecto); ?>>
        <thead><tr><th>Tipo / número</th><th>Contraparte</th><th class="hide-m">Objeto</th><th class="num">Monto</th><th>Vencimiento</th><th>Situación</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $c): [$etq, $badge] = $situaciones[$c['situacion']]; ?>
            <tr>
                <td><strong><?php echo s(Contratos::TIPOS[$c['tipo']] ?? $c['tipo']); ?></strong><?php if ($c['numero']): ?><br><small class="text-muted"><?php echo s($c['numero']); ?></small><?php endif; ?></td>
                <td><?php echo s($c['contraparte'] ?: '—'); ?></td>
                <td class="hide-m text-sm"><?php echo s($c['objeto']); ?></td>
                <td class="num mono"><?php echo $dinero($c['monto'], $c['moneda']); ?></td>
                <td class="mono"><?php echo $c['vencimiento'] ? s(formatear_fecha($c['vencimiento'], 'd/m/Y')) : '—'; ?><?php if ($c['vencimiento'] && $c['situacion'] !== 'cerrado'): ?><br><small class="text-muted"><?php echo (int)$c['dias']; ?> día(s)</small><?php endif; ?></td>
                <td><span class="badge badge-<?php echo $badge; ?>"><?php echo s($etq); ?></span></td>
                <td class="acciones">
                    <?php if ($c['archivo_id']): ?><a class="btn btn-xs btn-ghost" href="/archivos/ver?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$c['archivo_id']; ?>" title="Ver documento"><i class="fa-solid fa-paperclip"></i></a><?php endif; ?>
                    <?php if ($puedeEditar): ?>
                        <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['editar' => $c['id']])); ?>" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        <form class="inline" method="POST" action="/contratos/eliminar" data-confirm="¿Eliminar el registro?|Esta acción no se puede deshacer.|Eliminar">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                            <button class="btn btn-xs btn-ghost" type="submit" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
    </div>
</div>
<aside class="adm-stack" aria-label="Vencimientos y cumplimiento">
    <div class="card adm-panel">
        <div class="adm-panel-cab">
            <div>
                <h3><i class="fa-solid fa-calendar-days"></i> Próximos vencimientos</h3>
                <p>Los registros abiertos que primero pierden vigencia.</p>
            </div>
        </div>
        <div class="adm-panel-cuerpo">
            <?php if (!$proximos): ?>
                <p class="text-muted text-sm m-0">No hay vencimientos próximos con el filtro actual.</p>
            <?php else: ?>
                <ul class="co-venc">
                    <?php foreach ($proximos as $c): ?>
                        <li class="<?php echo $c['situacion'] === 'vencido' ? 'es-vencido' : ($c['situacion'] === 'por_vencer' ? 'es-aviso' : ''); ?>">
                            <div>
                                <strong><?php echo s($c['contraparte'] ?: Contratos::TIPOS[$c['tipo']] ?? $c['tipo']); ?></strong>
                                <small><?php echo s(Contratos::TIPOS[$c['tipo']] ?? $c['tipo']); ?><?php echo $c['numero'] ? ' · ' . s($c['numero']) : ''; ?></small>
                            </div>
                            <span>
                                <b class="mono"><?php echo s(formatear_fecha($c['vencimiento'], 'd/m/Y')); ?></b>
                                <small><?php echo (int)$c['dias'] < 0 ? abs((int)$c['dias']) . ' días vencido' : (int)$c['dias'] . ' días'; ?></small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="card adm-panel">
        <div class="adm-panel-cab">
            <div><h3><i class="fa-solid fa-shield-halved"></i> Cobertura</h3></div>
        </div>
        <div class="adm-panel-cuerpo">
            <div class="adm-dato-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin:0">
                <div><small>Vigentes</small><b><?php echo (int)($resumen['vigente'] ?? 0); ?></b></div>
                <div><small>Por vencer</small><b><?php echo (int)($resumen['por_vencer'] ?? 0); ?></b></div>
                <div><small>Vencidos</small><b><?php echo (int)($resumen['vencido'] ?? 0); ?></b></div>
                <div><small>Cerrados</small><b><?php echo (int)$resumen['cerrado']; ?></b></div>
            </div>
            <p class="text-sm text-muted" style="margin:.9rem 0 0">Los documentos se enlazan desde el Archivo; el aviso previo de cada registro decide cuándo aparece «Por vencer».</p>
        </div>
    </div>
</aside>
</div>
