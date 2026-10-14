<div class="page-header">
    <div>
        <h2>Usuarios del Sistema</h2>
        <p class="text-muted">Gestión de cuentas con roles (admin / cliente / visitante)</p>
    </div>
    <a href="/usuarios/crear" class="btn btn-primary">+ Nuevo Usuario</a>
</div>

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
                <th>Rol</th>
                <th class="hide-m">Proyectos</th>
                <th class="hide-m">Espacio / proyecto</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td class="mono hide-m"><?php echo s($u->id); ?></td>
                    <td><?php echo s($u->nombre); ?></td>
                    <td class="hide-m"><?php echo s($u->email); ?></td>
                    <td><span class="badge badge-<?php echo s($u->rol); ?>"><?php echo s(ucfirst($u->rol)); ?></span></td>
                    <td class="mono hide-m"><?php echo $u->rol === 'cliente' ? proyectos_creados_por((int)$u->id) . ' / ' . (int)$u->limite_proyectos : '—'; ?></td>
                    <td class="mono hide-m"><?php echo $u->rol === 'cliente' ? \Classes\Almacenamiento::formato((int)$u->limite_almacenamiento_mb * 1048576) : '—'; ?></td>
                    <td class="acciones">
                        <a href="/usuarios/editar?id=<?php echo s($u->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <?php if ($u->id != $_SESSION['id']): ?>
                            <form method="POST" action="/usuarios/eliminar" style="display:inline" data-confirm="¿Eliminar usuario?|El usuario perderá el acceso al sistema. Esta acción no se puede deshacer.">
                                <input type="hidden" name="id" value="<?php echo s($u->id); ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$usuarios): ?><tr><td colspan="7" class="text-muted">No se encontraron usuarios<?php echo $q !== '' ? ' para «' . s($q) . '»' : ''; ?>.</td></tr><?php endif; ?>
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
