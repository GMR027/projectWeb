<?php
$limiteVisitantes = $limiteVisitantes ?? \Model\Usuario::MAX_VISITANTES_CLIENTE;
$limiteColaboradores = $limiteColaboradores ?? \Model\Usuario::MAX_COLABORADORES_CLIENTE;
$ilimitadoV = $ilimitadoVisitantes ?? ($limiteVisitantes === null);
$ilimitadoC = $ilimitadoColaboradores ?? ($limiteColaboradores === null);
$llenoV = !$ilimitadoV && count($visitantes) >= (int)$limiteVisitantes;
$llenoC = !$ilimitadoC && count($colaboradores) >= (int)$limiteColaboradores;
$total = count($visitantes) + count($colaboradores);
$pctV = $ilimitadoV ? 0 : min(100, count($visitantes) / max(1, (int)$limiteVisitantes) * 100);
$pctC = $ilimitadoC ? 0 : min(100, count($colaboradores) / max(1, (int)$limiteColaboradores) * 100);
?>
<div class="page-header">
    <div>
        <span class="adm-eyebrow">Equipo de la cuenta</span>
        <h2><i class="fa-solid fa-user-group"></i> Mi equipo</h2>
        <p class="text-muted">Personas que entran a tus proyectos. Tú decides su nivel de acceso y a qué obras entran.</p>
        <div class="adm-meta">
            <span><i class="fa-solid fa-users"></i> <b><?php echo (int)$total; ?></b> personas</span>
            <span><i class="fa-solid fa-eye"></i> <b><?php echo count($visitantes); ?></b> visitantes</span>
            <span><i class="fa-solid fa-user-pen"></i> <b><?php echo count($colaboradores); ?></b> colaboradores</span>
        </div>
    </div>
    <div class="la-controles">
        <?php if (!$llenoV): ?><a href="/usuarios/crear?tipo=visitante" class="btn btn-ghost"><i class="fa-solid fa-plus"></i> Nuevo visitante</a><?php endif; ?>
        <?php if (!$llenoC): ?><a href="/usuarios/crear?tipo=colaborador" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo colaborador</a><?php endif; ?>
    </div>
</div>

<div class="cb-kpis">
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-users"></i></span>
        <small>Personas en tu equipo</small>
        <strong><?php echo (int)$total; ?></strong>
        <span>Visitantes y colaboradores</span>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-eye"></i></span>
        <small>Visitantes</small>
        <strong><?php echo count($visitantes); ?></strong>
        <span><?php echo $ilimitadoV ? 'Sin límite en tu plan' : 'de ' . (int)$limiteVisitantes . ' disponibles'; ?></span>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-user-pen"></i></span>
        <small>Colaboradores</small>
        <strong><?php echo count($colaboradores); ?></strong>
        <span><?php echo $ilimitadoC ? 'Sin límite en tu plan' : 'de ' . (int)$limiteColaboradores . ' disponibles'; ?></span>
    </div>
</div>

<div class="card">
    <p class="text-muted text-sm" style="margin-bottom:1.4rem">
        <strong>Visitante:</strong> solo ve y descarga, no cambia nada. <strong>Colaborador:</strong> crea y edita en las herramientas que le marques, pero <strong>no puede borrar</strong> (eso queda siempre para ti). Los accesos por obra se asignan en Ajustes → Accesos o al editar a la persona.
    </p>

    <?php
    $secciones = [
        ['visitante', $visitantes, $llenoV, $limiteVisitantes, $ilimitadoV, $pctV, 'Visitantes', 'fa-eye', 'Solo lectura: ven los proyectos que les asignes, no editan ni borran.'],
        ['colaborador', $colaboradores, $llenoC, $limiteColaboradores, $ilimitadoC, $pctC, 'Colaboradores', 'fa-user-pen', 'Editan en las herramientas que les marques; el borrado sigue siendo tuyo.'],
    ];
    foreach ($secciones as [$clave, $lista, $lleno, $limite, $ilimitado, $pct, $nombre, $icono, $ayuda]):
        $ir = $clave === 'colaborador' ? '+ Nuevo colaborador' : '+ Nuevo visitante';
    ?>
    <div class="adm-panel" style="margin-bottom:1.6rem">
        <div class="adm-panel-cab" style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <h3 class="card-titulo" style="margin:0"><i class="fa-solid <?php echo $icono; ?>"></i> <?php echo s($nombre); ?></h3>
                <p class="text-muted text-sm" style="margin:.25rem 0 0"><?php echo s($ayuda); ?></p>
            </div>
            <div style="min-width:18rem">
                <div class="ctl-uso-cab"><span>Uso</span><strong><?php echo count($lista); ?><?php echo $ilimitado ? '' : '/' . (int)$limite; ?></strong></div>
                <div class="ctl-uso-barra <?php echo $pct >= 90 ? 'es-alerta' : ($pct >= 70 ? 'es-aviso' : ''); ?>"><span style="width:<?php echo number_format($pct, 1, '.', ''); ?>%"></span></div>
            </div>
        </div>
        <div class="adm-panel-cuerpo" style="padding:0">
            <div class="tabla-wrapper">
                <table class="tabla">
                    <thead><tr><th>Persona</th><th class="hide-m">Proyectos</th><th class="hide-m">Herramientas</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($lista as $u): ?>
                            <?php $ver = $u->herramientasPermitidas(); $editar = $u->herramientasEdicionPermitidas(); ?>
                            <tr>
                                <td>
                                    <div class="ctl-identidad">
                                        <span class="ctl-avatar ctl-avatar-suave"><?php echo s(iniciales_nombre($u->nombre)); ?></span>
                                        <div class="ctl-id-txt">
                                            <strong><?php echo s($u->nombre); ?></strong>
                                            <span class="ctl-id-sub"><?php echo s($u->email); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="mono hide-m"><?php echo count(\Model\ProyectoAcceso::proyectosDe((int)$u->id)); ?></td>
                                <td class="hide-m text-sm"><?php
                                    echo $ver === null ? 'Todas' : count($ver) . ' visibles';
                                    if ($clave === 'colaborador') echo ' · edita ' . count($editar) . ($u->puede_archivos_ajenos ? ' · archivos ajenos' : '');
                                ?></td>
                                <td class="acciones">
                                    <a href="/usuarios/editar?id=<?php echo s($u->id); ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-pen"></i> Editar</a>
                                    <form method="POST" action="/usuarios/eliminar" class="inline" data-confirm="¿Eliminar <?php echo $clave; ?>?|Perderá el acceso al sistema. Esta acción no se puede deshacer.">
                                        <input type="hidden" name="id" value="<?php echo s($u->id); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$lista): ?>
                            <tr><td colspan="4">
                                <div class="ctl-empty">
                                    <i class="fa-solid <?php echo $icono; ?>"></i>
                                    <strong>Aún no hay <?php echo $clave === 'colaborador' ? 'colaboradores' : 'visitantes'; ?></strong>
                                    <p>Usa «<?php echo $ir; ?>» para dar acceso a una persona de tu equipo.</p>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
