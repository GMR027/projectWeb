<?php
$limiteVisitantes = $limiteVisitantes ?? \Model\Usuario::MAX_VISITANTES_CLIENTE;
$limiteColaboradores = $limiteColaboradores ?? \Model\Usuario::MAX_COLABORADORES_CLIENTE;
$llenoV = count($visitantes) >= $limiteVisitantes;
$llenoC = count($colaboradores) >= $limiteColaboradores;
$total = count($visitantes) + count($colaboradores);
?>
<div class="page-header">
    <div>
        <h2>Mi equipo</h2>
        <p class="text-muted">Personas que entran a tus proyectos · <?php echo $total; ?> en total</p>
    </div>
    <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <?php if (!$llenoV): ?><a href="/usuarios/crear?tipo=visitante" class="btn btn-ghost">+ Nuevo visitante</a><?php endif; ?>
        <?php if (!$llenoC): ?><a href="/usuarios/crear?tipo=colaborador" class="btn btn-primary">+ Nuevo colaborador</a><?php endif; ?>
    </div>
</div>

<div class="card">
    <p class="text-muted text-sm" style="margin-bottom:1.6rem">
        <strong>Visitante:</strong> solo ve y descarga, no cambia nada. <strong>Colaborador:</strong> crea y edita en las herramientas que le marques, pero <strong>no puede borrar</strong> (eso queda siempre para ti). Tú decides a qué proyectos entra cada uno.
    </p>

    <div style="display:flex;gap:1.2rem;flex-wrap:wrap;margin-bottom:1.6rem">
        <span class="badge">Visitantes: <?php echo count($visitantes); ?> de <?php echo (int)$limiteVisitantes; ?></span>
        <span class="badge">Colaboradores: <?php echo count($colaboradores); ?> de <?php echo (int)$limiteColaboradores; ?></span>
    </div>

    <?php
    $secciones = [
        ['visitante', $visitantes, $llenoV, (int)$limiteVisitantes, 'Visitantes', 'Solo lectura: ven los proyectos que les asignes, no editan ni borran.'],
        ['colaborador', $colaboradores, $llenoC, (int)$limiteColaboradores, 'Colaboradores', 'Editan en las herramientas que les marques; el borrado sigue siendo tuyo.'],
    ];
    foreach ($secciones as [$clave, $lista, $lleno, $limite, $nombre, $ayuda]):
        $ir = $clave === 'colaborador' ? '+ Nuevo colaborador' : '+ Nuevo visitante';
    ?>
    <div style="margin-bottom:2rem">
        <h3 class="card-titulo" style="margin-bottom:.6rem"><i class="fa-solid <?php echo $clave === 'colaborador' ? 'fa-user-pen' : 'fa-eye'; ?>"></i> <?php echo s($nombre); ?></h3>
        <p class="text-muted text-sm" style="margin-bottom:1rem"><?php echo s($ayuda); ?> · <?php echo count($lista); ?> de <?php echo $limite; ?></p>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th class="hide-m">Email</th><th class="hide-m">Proyectos</th><th class="hide-m">Herramientas</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($lista as $u): ?>
                    <tr>
                        <td><?php echo s($u->nombre); ?></td>
                        <td class="hide-m"><?php echo s($u->email); ?></td>
                        <td class="mono hide-m"><?php echo count(\Model\ProyectoAcceso::proyectosDe((int)$u->id)); ?></td>
                        <td class="hide-m text-sm"><?php
                            $ver = $u->herramientasPermitidas();
                            $editar = $u->herramientasEdicionPermitidas();
                            echo $ver === null ? 'Todas' : count($ver) . '';
                            if ($clave === 'colaborador') echo ' · edita ' . count($editar) . ($u->puede_archivos_ajenos ? ' · archivos ajenos' : '');
                        ?></td>
                        <td class="acciones">
                            <a href="/usuarios/editar?id=<?php echo s($u->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                            <form method="POST" action="/usuarios/eliminar" style="display:inline" data-confirm="¿Eliminar <?php echo $clave; ?>?|Perderá el acceso al sistema. Esta acción no se puede deshacer.">
                                <input type="hidden" name="id" value="<?php echo s($u->id); ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$lista): ?><tr><td colspan="5" class="text-muted">Aún no has creado <?php echo $clave === 'colaborador' ? 'colaboradores' : 'visitantes'; ?>.<?php if (!$lleno): ?> <a href="/usuarios/crear?tipo=<?php echo $clave; ?>">Crea el primero</a>.<?php endif; ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>
