<div class="page-header">
    <div>
        <h2>Usuarios del Sistema</h2>
        <p class="text-muted">Gestión de cuentas con roles (admin / cliente / visitante)</p>
    </div>
    <a href="/usuarios/crear" class="btn btn-primary">+ Nuevo Usuario</a>
</div>

<?php if (!empty($_GET['organizacion']) && $_GET['organizacion'] === 'error'): ?>
    <div class="card" style="border-left:3px solid var(--danger, #ef4444)">
        <p class="text-sm" role="alert"><strong>El usuario se creó, pero su organización no.</strong>
            Revisa que la migración de planes esté aplicada (<code class="mono">database/migracion_planes_organizaciones.sql</code> y <code class="mono">php bin/migrar_organizaciones.php</code>) y vuelve a crear la organización desde <a href="/organizaciones">Organizaciones</a>.</p>
    </div>
<?php endif; ?>

<form method="GET" action="/usuarios" class="usuarios-buscar" role="search">
    <div class="usuarios-buscar-caja">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?php echo s($q); ?>" placeholder="Buscar por nombre o correo..." autocomplete="off" aria-label="Buscar usuarios">
    </div>
    <button type="submit" class="btn btn-primary">Buscar</button>
    <?php if ($q !== ''): ?><a href="/usuarios" class="btn btn-ghost">Limpiar</a><?php endif; ?>
    <span class="text-muted text-sm"><?php echo (int)$total; ?> usuario<?php echo $total === 1 ? '' : 's'; ?><?php echo $q !== '' ? ' encontrado' . ($total === 1 ? '' : 's') : ''; ?></span>
</form>

<div class="card">
    <table class="tabla">
        <thead>
            <tr>
                <th class="hide-m">ID</th>
                <th>Nombre</th>
                <th class="hide-m">Email</th>
                <th class="hide-m">Último acceso</th>
                <th class="hide-m">Rol</th>
                <th class="hide-m">Organización</th>
                <th class="hide-m">Plan</th>
                <th class="hide-m">Uso</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td class="mono hide-m"><?php echo s($u->id); ?></td>
                    <td><?php echo s($u->nombre); ?></td>
                    <td class="hide-m"><?php echo s($u->email); ?></td>
                    <td class="hide-m mono text-sm"><?php echo !empty($u->ultimo_acceso) ? s(date('d/m/Y H:i', strtotime($u->ultimo_acceso))) : '—'; ?></td>
                    <td><span class="badge badge-<?php echo s($u->rol); ?>"><?php echo s(ucfirst($u->rol)); ?></span><?php if (!empty($u->rol_org) && $u->rol_org !== 'visitante'): ?> <span class="badge"><?php echo s(ucfirst($u->rol_org)); ?></span><?php elseif ($u->rol === 'visitante' && ($u->nivel_acceso ?? 'ver') === 'editar'): ?> <span class="badge">Colaborador</span><?php endif; ?></td>
                    <?php $org = \Model\Organizacion::deUsuario((int)$u->id); ?>
                    <td class="hide-m"><?php echo $org ? s($org->nombre) : '—'; ?></td>
                    <td class="hide-m"><?php if ($org): ?><span class="badge"><?php echo s($org->plan()['nombre']); ?></span> <span class="badge <?php echo s($org->estadoBadge()); ?>"><?php echo s($org->estadoEtiqueta()); ?></span><?php else: ?>—<?php endif; ?></td>
                    <td class="mono hide-m"><?php if ($org): $lim = $org->limites(); ?><?php echo (int)$org->contarObrasActivas(); ?>/<?php echo $lim['obras'] === null ? '∞' : (int)$lim['obras']; ?> obras · <?php echo (int)$org->contarAsientos(); ?>/<?php echo $lim['usuarios'] === null ? '∞' : (int)$lim['usuarios']; ?> usuarios<br><span class="text-sm"><?php echo \Classes\Almacenamiento::formato(\Classes\Almacenamiento::usadoCuenta((int)$org->id)); ?></span><?php else: ?>—<?php endif; ?></td>
                    <td class="acciones">
                        <a href="/usuarios/editar?id=<?php echo s($u->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <?php if ($u->id != $_SESSION['id']): ?>
                            <form class="inline" method="POST" action="/usuarios/eliminar" data-confirm="¿Eliminar usuario?|El usuario perderá el acceso al sistema. Esta acción no se puede deshacer.">
                                <input type="hidden" name="id" value="<?php echo s($u->id); ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$usuarios): ?><tr><td colspan="9" class="text-muted">No se encontraron usuarios<?php echo $q !== '' ? ' para «' . s($q) . '»' : ''; ?>.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php if ($paginas > 1): $url = fn($p) => '/usuarios?' . http_build_query(array_filter(['q' => $q, 'pagina' => $p > 1 ? $p : null])); ?>
        <nav class="paginacion" aria-label="Paginación">
            <?php if ($pagina > 1): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($url($pagina - 1)); ?>">← Anterior</a><?php endif; ?>
            <span class="text-muted text-sm">Página <?php echo (int)$pagina; ?> de <?php echo (int)$paginas; ?></span>
            <?php if ($pagina < $paginas): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($url($pagina + 1)); ?>">Siguiente →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
