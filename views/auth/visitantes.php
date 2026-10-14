<?php $lleno = count($usuarios) >= $maximo; ?>
<div class="page-header">
    <div>
        <h2>Mis Visitantes</h2>
        <p class="text-muted">Usuarios de solo lectura para tus proyectos · <?php echo count($usuarios); ?> de <?php echo (int)$maximo; ?> creados</p>
    </div>
    <?php if (!$lleno): ?><a href="/usuarios/crear" class="btn btn-primary">+ Nuevo visitante</a><?php endif; ?>
</div>

<div class="card">
    <p class="text-muted text-sm" style="margin-bottom:1.6rem">Un visitante solo puede <strong>ver</strong> los proyectos que le asignes: no puede editar ni descargar. Puedes tener hasta <?php echo (int)$maximo; ?>.</p>
    <table class="tabla">
        <thead>
            <tr><th>Nombre</th><th class="hide-m">Email</th><th class="hide-m">Proyectos asignados</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?php echo s($u->nombre); ?></td>
                    <td class="hide-m"><?php echo s($u->email); ?></td>
                    <td class="mono hide-m"><?php echo count(\Model\ProyectoAcceso::proyectosDe((int)$u->id)); ?></td>
                    <td class="acciones">
                        <a href="/usuarios/editar?id=<?php echo s($u->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/usuarios/eliminar" style="display:inline" data-confirm="¿Eliminar visitante?|Perderá el acceso al sistema. Esta acción no se puede deshacer.">
                            <input type="hidden" name="id" value="<?php echo s($u->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$usuarios): ?><tr><td colspan="4" class="text-muted">Aún no has creado visitantes.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
