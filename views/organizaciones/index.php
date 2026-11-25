<?php
/** Lista de organizaciones y su plan (solo admin). */
$enlaceOrg = function (array $cambios = []) use ($q, $estadoFiltro): string {
    $params = array_merge(['q' => $q, 'estado' => $estadoFiltro], $cambios);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '/organizaciones' . ($params ? '?' . http_build_query($params) : '');
};
$almTotal = (int)($resumen['almacenamiento'] ?? 0);
?>
<div class="page-header">
    <div>
        <span class="adm-eyebrow">Cartera de empresas</span>
        <h2><i class="fa-solid fa-building-user"></i> Organizaciones</h2>
        <p class="text-muted">Planes, topes, obras y facturación de cada empresa que contrata la plataforma.</p>
        <div class="adm-meta">
            <span><i class="fa-solid fa-circle-check"></i> <b><?php echo (int)$resumen['activas']; ?></b> activas</span>
            <span><i class="fa-solid fa-hourglass-half"></i> <b><?php echo (int)$resumen['trial']; ?></b> en prueba</span>
            <span><i class="fa-solid fa-circle-pause"></i> <b><?php echo (int)$resumen['suspendidas']; ?></b> suspendidas</span>
            <span><i class="fa-solid fa-box-archive"></i> <b><?php echo (int)$resumen['archivadas']; ?></b> archivadas</span>
        </div>
    </div>
    <div class="la-controles">
        <?php if ($verArchivadas): ?>
            <a href="/organizaciones" class="btn btn-ghost"><i class="fa-solid fa-eye"></i> Ver activas</a>
        <?php else: ?>
            <a href="/organizaciones?archivadas=1" class="btn btn-ghost"><i class="fa-solid fa-box-archive"></i> Ver archivadas</a>
        <?php endif; ?>
        <a href="/organizaciones/crear" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva organización</a>
    </div>
</div>

<?php if (!empty($_GET['creada'])): ?><div class="alerta alerta-exito" role="status"><i class="fa-solid fa-circle-check"></i> Organización creada. Agrega su cliente propietario en la ficha.</div><?php endif; ?>
<?php if (!empty($_GET['eliminada'])): ?><div class="alerta alerta-exito" role="status"><i class="fa-solid fa-circle-check"></i> Organización eliminada.</div><?php endif; ?>
<?php if (!empty($_GET['recalculado'])): ?><div class="alerta alerta-exito" role="status"><i class="fa-solid fa-circle-check"></i> Almacenamiento recalculado.</div><?php endif; ?>
<?php if (!empty($_GET['guardado'])): ?><div class="alerta alerta-exito" role="status"><i class="fa-solid fa-circle-check"></i> Cambios guardados.</div><?php endif; ?>
<?php $msgError = $_GET['error'] ?? ''; if (is_string($msgError) && $msgError !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($msgError); ?></div><?php endif; ?>

<div class="cb-kpis">
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-building"></i></span>
        <small>Empresas activas</small>
        <strong><?php echo (int)$resumen['activas']; ?></strong>
        <span><?php echo (int)$resumen['total']; ?> en la cartera</span>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-hourglass-half"></i></span>
        <small>En prueba</small>
        <strong><?php echo (int)$resumen['trial']; ?></strong>
        <span>Con días de trial por vencer</span>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-helmet-safety"></i></span>
        <small>Obras activas</small>
        <strong><?php echo (int)$resumen['obras']; ?></strong>
        <span>En todas las empresas</span>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-users"></i></span>
        <small>Asientos usados</small>
        <strong><?php echo (int)$resumen['asientos']; ?></strong>
        <span><?php echo (int)$resumen['visitantes']; ?> visitantes adicionales</span>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-hard-drive"></i></span>
        <small>Almacenamiento</small>
        <strong class="mono"><?php echo \Classes\Almacenamiento::formato($almTotal); ?></strong>
        <span>Archivos, fotos y planos</span>
    </div>
</div>

<form method="GET" action="/organizaciones" class="ctl-toolbar" role="search">
    <div class="ctl-busca">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" name="q" value="<?php echo s($q); ?>" placeholder="Buscar por empresa, RFC o propietario…" autocomplete="off" aria-label="Buscar organizaciones">
    </div>
    <?php if ($estadoFiltro !== ''): ?><input type="hidden" name="estado" value="<?php echo s($estadoFiltro); ?>"><?php endif; ?>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
    <?php if ($q !== '' || $estadoFiltro !== ''): ?><a href="/organizaciones" class="btn btn-ghost"><i class="fa-solid fa-xmark"></i> Limpiar</a><?php endif; ?>
    <span class="text-muted text-sm"><?php echo count($filas); ?> organización(es)</span>
</form>

<nav class="ctl-pills" aria-label="Filtrar organizaciones por estado" style="margin-bottom:1.2rem">
    <?php
    $filtros = [
        '' => ['Todas', $resumen['total']],
        'activo' => ['Activas', $resumen['activas']],
        'trial' => ['En prueba', $resumen['trial']],
        'suspendido' => ['Suspendidas', $resumen['suspendidas']],
        'archivada' => ['Archivadas', $resumen['archivadas']],
    ];
    foreach ($filtros as $clave => [$etiqueta, $n]): ?>
        <a href="<?php echo s($enlaceOrg(['estado' => $clave, 'archivadas' => $clave === 'archivada' ? 1 : null])); ?>" class="<?php echo $estadoFiltro === $clave ? 'activo' : ''; ?>"<?php echo $estadoFiltro === $clave ? ' aria-current="page"' : ''; ?>><?php echo s($etiqueta); ?> <span class="ctl-pill-n"><?php echo (int)$n; ?></span></a>
    <?php endforeach; ?>
</nav>

<div class="card">
    <div class="tabla-wrapper">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Organización</th>
                    <th class="hide-m">Propietario</th>
                    <th>Plan y estado</th>
                    <th class="hide-m">Obras</th>
                    <th class="hide-m">Asientos</th>
                    <th class="hide-m">Almacenamiento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): $org = $f['org']; $lim = $f['limites'];
                $pctObras = $lim['obras'] ? min(100, (int)$f['obras'] / max(1, (int)$lim['obras']) * 100) : 0;
                $pctAsientos = $lim['usuarios'] ? min(100, (int)$f['asientos'] / max(1, (int)$lim['usuarios']) * 100) : 0;
                $pctAlm = $f['limite_almacenamiento'] ? min(100, (int)$f['almacenamiento'] / max(1, (int)$f['limite_almacenamiento']) * 100) : 0;
            ?>
                <tr>
                    <td>
                        <div class="ctl-identidad">
                            <?php if (!empty($org->logo)): ?>
                                <span class="ctl-avatar"><img src="/build/img/logos/<?php echo s($org->logo); ?>" alt=""></span>
                            <?php else: ?>
                                <span class="ctl-avatar"><?php echo s(iniciales_nombre($org->nombre)); ?></span>
                            <?php endif; ?>
                            <div class="ctl-id-txt">
                                <strong><a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>" style="color:inherit"><?php echo s($org->nombre); ?></a></strong>
                                <span class="ctl-id-sub"><?php echo $org->rfc ? s($org->rfc) : 'Sin RFC'; ?></span>
                                <?php if ($org->estaArchivada()): ?><span class="badge">Archivada</span><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="hide-m">
                        <?php if ($f['propietario']): ?>
                            <div class="ctl-identidad">
                                <span class="ctl-avatar ctl-avatar-suave"><?php echo s(iniciales_nombre($f['propietario']->nombre)); ?></span>
                                <div class="ctl-id-txt">
                                    <strong><?php echo s($f['propietario']->nombre); ?></strong>
                                    <span class="ctl-id-sub"><?php echo s($f['propietario']->email); ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="text-muted text-sm">Sin propietario</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge"><?php echo s($f['plan']['nombre']); ?></span>
                        <span class="badge <?php echo s($org->estadoBadge()); ?>"><?php echo s($org->estadoEtiqueta()); ?></span>
                        <?php if (($org->estado_plan ?? '') === 'trial' && $org->diasTrial() !== null): ?>
                            <div class="text-muted text-sm">Quedan <?php echo max(0, (int)$org->diasTrial()); ?> día(s)</div>
                        <?php endif; ?>
                        <?php $activa = ($org->estado_plan ?? '') === 'activo'; ?>
                        <form method="POST" action="/organizaciones/estado" class="inline" style="margin-top:.35rem"
                            <?php echo $activa ? 'data-confirm="¿Suspender la empresa?|Quedará en solo lectura; podrás reactivarla cuando quieras."' : ''; ?>>
                            <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
                            <input type="hidden" name="volver" value="lista">
                            <input type="hidden" name="estado" value="<?php echo $activa ? 'suspendido' : 'activo'; ?>">
                            <button type="submit" class="btn btn-xs btn-ghost" title="<?php echo $activa ? 'Suspender la organización' : 'Activar la organización'; ?>">
                                <i class="fa-solid <?php echo $activa ? 'fa-pause' : 'fa-play'; ?>"></i> <?php echo $activa ? 'Suspender' : 'Activar'; ?>
                            </button>
                        </form>
                    </td>
                    <td class="hide-m">
                        <div class="ctl-uso">
                            <div class="ctl-uso-cab"><span>Activas</span><strong><?php echo (int)$f['obras']; ?>/<?php echo $lim['obras'] === null ? '∞' : (int)$lim['obras']; ?></strong></div>
                            <div class="ctl-uso-barra <?php echo $pctObras >= 90 ? 'es-alerta' : ($pctObras >= 70 ? 'es-aviso' : ''); ?>"><span style="width:<?php echo number_format($pctObras, 1, '.', ''); ?>%"></span></div>
                        </div>
                    </td>
                    <td class="hide-m">
                        <div class="ctl-uso">
                            <div class="ctl-uso-cab"><span>Personas</span><strong><?php echo (int)$f['asientos']; ?>/<?php echo $lim['usuarios'] === null ? '∞' : (int)$lim['usuarios']; ?></strong></div>
                            <div class="ctl-uso-barra <?php echo $pctAsientos >= 90 ? 'es-alerta' : ($pctAsientos >= 70 ? 'es-aviso' : ''); ?>"><span style="width:<?php echo number_format($pctAsientos, 1, '.', ''); ?>%"></span></div>
                            <span class="text-muted text-sm"><?php echo (int)$f['visitantes']; ?> visitantes</span>
                        </div>
                    </td>
                    <td class="hide-m">
                        <div class="ctl-uso">
                            <div class="ctl-uso-cab"><span>Usado</span><strong class="mono"><?php echo \Classes\Almacenamiento::formato((int)$f['almacenamiento']); ?></strong></div>
                            <div class="ctl-uso-barra es-info <?php echo $pctAlm >= 90 ? 'es-alerta' : ($pctAlm >= 70 ? 'es-aviso' : ''); ?>"><span style="width:<?php echo number_format($pctAlm, 1, '.', ''); ?>%"></span></div>
                            <span class="text-muted text-sm"><?php echo $f['limite_almacenamiento'] === null ? 'Sin tope' : 'de ' . \Classes\Almacenamiento::formato((int)$f['limite_almacenamiento']); ?></span>
                        </div>
                    </td>
                    <td class="acciones">
                        <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-eye"></i> Ver</a>
                        <a href="/organizaciones/editar?id=<?php echo (int)$org->id; ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-pen"></i> Editar</a>
                        <form class="inline" method="POST" action="/organizaciones/recalcular">
                            <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
                            <button type="submit" class="btn btn-sm btn-ghost" title="Recalcular el almacenamiento usado"><i class="fa-solid fa-rotate"></i> Recalcular</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$filas): ?>
                <tr><td colspan="7">
                    <div class="ctl-empty">
                        <i class="fa-solid fa-building-circle-exclamation"></i>
                        <strong><?php echo $verArchivadas ? 'No hay organizaciones archivadas' : 'Aún no hay organizaciones'; ?></strong>
                        <p><?php echo $verArchivadas ? 'Las empresas archivadas aparecen aquí y conservan sus obras, usuarios y pagos.' : 'Crea la primera empresa con «Nueva organización».'; ?></p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:1.4rem">
    <h3 class="card-titulo"><i class="fa-solid fa-layer-group"></i> Planes comerciales</h3>
    <p class="text-muted text-sm" style="margin-bottom:1rem">Referencia de topes y precios. El plan de cada empresa se cambia en su ficha, en «Plan y estado».</p>
    <div class="ctl-plan-grid">
        <?php foreach ($planes as $p): ?>
            <div class="ctl-plan-card<?php echo !empty($p['destacado']) ? ' destacado' : ''; ?>">
                <h4><?php echo s($p['nombre']); ?><?php if (!empty($p['destacado'])): ?> <span class="badge">Más popular</span><?php endif; ?></h4>
                <p><?php echo s($p['lema']); ?></p>
                <strong class="precio"><?php echo s(\Classes\Plan::precio($p['clave'])); ?> <small class="text-muted">MXN/mes</small></strong>
                <p class="text-sm"><?php echo s(\Classes\Plan::precio($p['clave'], true)); ?> al año</p>
                <ul>
                    <li><?php echo $p['obras'] === null ? 'Obras ilimitadas' : (int)$p['obras'] . ' obras activas'; ?></li>
                    <li><?php echo $p['usuarios'] === null ? 'Usuarios ilimitados' : (int)$p['usuarios'] . ' personas que editan'; ?></li>
                    <li>Visitantes <?php echo $p['visitantes'] === null ? 'ilimitados' : (int)$p['visitantes']; ?></li>
                    <li><?php echo $p['almacenamiento_gb'] === null ? 'Sin tope total' : (int)$p['almacenamiento_gb'] . ' GB'; ?> · <?php echo (int)$p['almacenamiento_por_obra_mb']; ?> MB por obra</li>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>
