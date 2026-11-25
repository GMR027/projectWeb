<?php
$enlaceUsuarios = function (array $cambios = []) use ($q, $rol): string {
    $params = array_merge(['q' => $q, 'rol' => $rol], $cambios);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '/usuarios' . ($params ? '?' . http_build_query($params) : '');
};
$totalFiltrado = (int)$total;
$textoTotal = $totalFiltrado . ' usuario' . ($totalFiltrado === 1 ? '' : 's');
?>
<div class="page-header">
    <div>
        <span class="adm-eyebrow">Administración de cuentas</span>
        <h2><i class="fa-solid fa-users-gear"></i> Usuarios y accesos</h2>
        <p class="text-muted">Cuentas, roles y organizaciones; quién entra a la plataforma y con qué permisos.</p>
        <div class="adm-meta">
            <span><i class="fa-solid fa-user-check"></i> <b><?php echo (int)$conteos['total']; ?></b> cuentas</span>
            <span><i class="fa-solid fa-building"></i> <b><?php echo (int)$organizaciones; ?></b> organizaciones</span>
            <span><i class="fa-solid fa-user-pen"></i> <b><?php echo (int)$conteos['colaborador']; ?></b> colaboradores</span>
        </div>
    </div>
    <div class="la-controles">
        <a href="/organizaciones" class="btn btn-secondary"><i class="fa-solid fa-building-user"></i> Organizaciones</a>
        <a href="/usuarios/crear" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo usuario</a>
    </div>
</div>

<?php if (!empty($_GET['organizacion']) && $_GET['organizacion'] === 'error'): ?>
    <div class="alerta alerta-error" role="alert">
        <strong>El usuario se creó, pero su organización no.</strong>
        Revisa que la migración de planes esté aplicada (<code class="mono">database/migracion_planes_organizaciones.sql</code> y <code class="mono">php bin/migrar_organizaciones.php</code>) y vuelve a crear la organización desde <a href="/organizaciones">Organizaciones</a>.
    </div>
<?php endif; ?>

<div class="cb-kpis">
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-users"></i></span>
        <small>Total de cuentas</small>
        <strong><?php echo (int)$conteos['total']; ?></strong>
        <span>Administradores, clientes y su equipo</span>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-user-shield"></i></span>
        <small>Administradores</small>
        <strong><?php echo (int)$conteos['admin']; ?></strong>
        <span>Acceso completo a la plataforma</span>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-user-tie"></i></span>
        <small>Clientes</small>
        <strong><?php echo (int)$conteos['cliente']; ?></strong>
        <span>Gestionan sus obras y su equipo</span>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-user-group"></i></span>
        <small>Visitantes y colaboradores</small>
        <strong><?php echo (int)$conteos['visitante']; ?></strong>
        <span><?php echo (int)$conteos['colaborador']; ?> pueden editar herramientas</span>
    </div>
</div>

<form method="GET" action="/usuarios" class="ctl-toolbar" role="search">
    <div class="ctl-busca">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" name="q" value="<?php echo s($q); ?>" placeholder="Buscar por nombre o correo…" autocomplete="off" aria-label="Buscar usuarios">
    </div>
    <?php if ($rol !== ''): ?><input type="hidden" name="rol" value="<?php echo s($rol); ?>"><?php endif; ?>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
    <?php if ($q !== '' || $rol !== ''): ?><a href="/usuarios" class="btn btn-ghost"><i class="fa-solid fa-xmark"></i> Limpiar</a><?php endif; ?>
    <span class="text-muted text-sm"><?php echo s($textoTotal); ?><?php echo $q !== '' ? ' con «' . s($q) . '»' : ''; ?></span>
</form>

<nav class="ctl-pills" aria-label="Filtrar usuarios por rol" style="margin-bottom:1.2rem">
    <a href="<?php echo s($enlaceUsuarios(['rol' => ''])); ?>" class="<?php echo $rol === '' ? 'activo' : ''; ?>"<?php echo $rol === '' ? ' aria-current="page"' : ''; ?>>Todos <span class="ctl-pill-n"><?php echo (int)$conteos['total']; ?></span></a>
    <a href="<?php echo s($enlaceUsuarios(['rol' => 'admin'])); ?>" class="<?php echo $rol === 'admin' ? 'activo' : ''; ?>"<?php echo $rol === 'admin' ? ' aria-current="page"' : ''; ?>>Admin <span class="ctl-pill-n"><?php echo (int)$conteos['admin']; ?></span></a>
    <a href="<?php echo s($enlaceUsuarios(['rol' => 'cliente'])); ?>" class="<?php echo $rol === 'cliente' ? 'activo' : ''; ?>"<?php echo $rol === 'cliente' ? ' aria-current="page"' : ''; ?>>Clientes <span class="ctl-pill-n"><?php echo (int)$conteos['cliente']; ?></span></a>
    <a href="<?php echo s($enlaceUsuarios(['rol' => 'visitante'])); ?>" class="<?php echo $rol === 'visitante' ? 'activo' : ''; ?>"<?php echo $rol === 'visitante' ? ' aria-current="page"' : ''; ?>>Visitantes <span class="ctl-pill-n"><?php echo (int)$conteos['visitante']; ?></span></a>
</nav>

<div class="card">
    <div class="tabla-wrapper">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th class="hide-m">Organización</th>
                    <th class="hide-m">Último acceso</th>
                    <th class="hide-m">Uso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <?php $org = \Model\Organizacion::deUsuario((int)$u->id); ?>
                    <tr>
                        <td>
                            <div class="ctl-identidad">
                                <span class="ctl-avatar"><?php echo s(iniciales_nombre($u->nombre)); ?></span>
                                <div class="ctl-id-txt">
                                    <strong><?php echo s($u->nombre); ?></strong>
                                    <span class="ctl-id-sub"><?php echo s($u->email); ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo s($u->rol); ?>"><?php echo s(ucfirst($u->rol)); ?></span>
                            <?php if (!empty($u->rol_org) && $u->rol_org !== 'visitante'): ?>
                                <span class="badge"><?php echo s(ucfirst($u->rol_org)); ?></span>
                            <?php elseif ($u->rol === 'visitante' && ($u->nivel_acceso ?? 'ver') === 'editar'): ?>
                                <span class="badge">Colaborador</span>
                            <?php endif; ?>
                            <div class="solo-movil text-sm text-muted"><?php echo $org ? s($org->nombre) : 'Sin organización'; ?></div>
                        </td>
                        <td class="hide-m">
                            <?php if ($org): ?>
                                <?php echo s($org->nombre); ?><br>
                                <span class="badge"><?php echo s($org->plan()['nombre']); ?></span>
                                <span class="badge <?php echo s($org->estadoBadge()); ?>"><?php echo s($org->estadoEtiqueta()); ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-m mono text-sm"><?php echo !empty($u->ultimo_acceso) ? s(date('d/m/Y H:i', strtotime($u->ultimo_acceso))) : '—'; ?></td>
                        <td class="hide-m">
                            <?php if ($org): $lim = $org->limites(); $obras = (int)$org->contarObrasActivas(); $asientos = (int)$org->contarAsientos();
                                $pctObras = $lim['obras'] ? min(100, $obras / max(1, (int)$lim['obras']) * 100) : 0;
                                $pctAsientos = $lim['usuarios'] ? min(100, $asientos / max(1, (int)$lim['usuarios']) * 100) : 0; ?>
                                <div class="ctl-uso">
                                    <div class="ctl-uso-cab"><span>Obras</span><strong><?php echo $obras; ?>/<?php echo $lim['obras'] === null ? '∞' : (int)$lim['obras']; ?></strong></div>
                                    <div class="ctl-uso-barra <?php echo $pctObras >= 90 ? 'es-alerta' : ($pctObras >= 70 ? 'es-aviso' : ''); ?>"><span style="width:<?php echo number_format($pctObras, 1, '.', ''); ?>%"></span></div>
                                    <div class="ctl-uso-cab"><span>Asientos</span><strong><?php echo $asientos; ?>/<?php echo $lim['usuarios'] === null ? '∞' : (int)$lim['usuarios']; ?></strong></div>
                                    <div class="ctl-uso-barra <?php echo $pctAsientos >= 90 ? 'es-alerta' : ($pctAsientos >= 70 ? 'es-aviso' : ''); ?>"><span style="width:<?php echo number_format($pctAsientos, 1, '.', ''); ?>%"></span></div>
                                    <span class="text-muted text-sm"><?php echo \Classes\Almacenamiento::formato(\Classes\Almacenamiento::usadoCuenta((int)$org->id)); ?> usados</span>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="acciones">
                            <a href="/usuarios/editar?id=<?php echo s($u->id); ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-pen"></i> Editar</a>
                            <?php if ($u->id != $_SESSION['id']): ?>
                                <form class="inline" method="POST" action="/usuarios/eliminar" data-confirm="¿Eliminar usuario?|El usuario perderá el acceso al sistema. Esta acción no se puede deshacer.">
                                    <input type="hidden" name="id" value="<?php echo s($u->id); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$usuarios): ?>
                    <tr>
                        <td colspan="6">
                            <div class="ctl-empty">
                                <i class="fa-solid fa-user-slash"></i>
                                <strong>No se encontraron usuarios</strong>
                                <p><?php echo $q !== '' || $rol !== '' ? 'Prueba con otra búsqueda o cambia el filtro de rol.' : 'Crea la primera cuenta con «Nuevo usuario».'; ?></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginas > 1): ?>
        <nav class="paginacion" aria-label="Paginación">
            <?php if ($pagina > 1): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($enlaceUsuarios(['pagina' => $pagina - 1])); ?>">← Anterior</a><?php endif; ?>
            <span class="text-muted text-sm">Página <?php echo (int)$pagina; ?> de <?php echo (int)$paginas; ?></span>
            <?php if ($pagina < $paginas): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($enlaceUsuarios(['pagina' => $pagina + 1])); ?>">Siguiente →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
