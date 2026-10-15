<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-truck-ramp-box"></i> MSR · Reportes de materiales</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($msrs); ?> MSR</p>
    </div>
    <?php if ($puedeEditar): ?>
        <a href="/msr/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">+ Nuevo MSR</a>
    <?php endif; ?>
</div>
<div class="card">
    <?php if (empty($msrs)): ?>
        <p class="text-muted">Aún no hay ningún MSR.<?php echo $puedeEditar ? ' Crea uno y asígnale paquetes de submittals, o impórtalo desde Excel dentro del MSR.' : ''; ?></p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th class="hide-m">Descripción</th><th class="hide-m">Submittals</th><th class="hide-m">Renglones</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($msrs as $m): ?>
                <tr>
                    <td><a href="/msr/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($m->id); ?>"><strong><?php echo s($m->nombre); ?></strong></a></td>
                    <td class="hide-m"><?php echo s($m->descripcion); ?></td>
                    <td class="mono hide-m"><?php echo (int)$m->n_submittals; ?></td>
                    <td class="mono hide-m"><?php echo (int)$m->n_renglones; ?></td>
                    <td class="acciones">
                        <a href="/msr/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($m->id); ?>" class="btn btn-sm btn-ghost">Ver</a>
                        <?php if ($puedeEditar): ?>
                            <a href="/msr/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($m->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                            <form method="POST" action="/msr/eliminar" style="display:inline" data-confirm="¿Eliminar MSR?|Se borrarán sus renglones. Los paquetes de submittal se conservan pero quedan «Sin MSR».">
                                <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                                <input type="hidden" name="id" value="<?php echo s($m->id); ?>">
                                <button type="submit" class="btn btn-sm btn-danger">✕</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
