<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-boxes-packing"></i> Submittals</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($paquetes); ?> paquete(s)</p>
    </div>
    <a href="/submittals/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">+ Nuevo paquete</a>
</div>
<div class="card">
    <?php if (empty($paquetes)): ?>
        <p class="text-muted">Aún no hay paquetes. Crea uno y elige los materiales del catálogo.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>No. submittal</th><th class="hide-m">Fecha</th><th class="hide-m">Especialidad</th><th class="hide-m">Departamento</th><th class="hide-m">Para</th><th class="hide-m">Materiales</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($paquetes as $p): ?>
                <tr>
                    <td class="mono"><a href="/submittals/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($p->id); ?>"><?php echo s($p->numero_submittal); ?></a></td>
                    <td class="mono hide-m"><?php echo formatear_fecha($p->fecha); ?></td>
                    <td class="hide-m"><?php echo s($p->especialidad); ?></td>
                    <td class="hide-m"><?php echo s($p->departamento); ?></td>
                    <td class="hide-m"><?php echo s($p->para); ?></td>
                    <td class="mono hide-m"><?php echo $conteos[(int)$p->id] ?? 0; ?></td>
                    <td class="acciones">
                        <a href="/submittals/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($p->id); ?>" class="btn btn-sm btn-ghost">Ver</a>
                        <a href="/submittals/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($p->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/submittals/eliminar" style="display:inline" data-confirm="¿Eliminar paquete?|Se quitarán también sus renglones del MSR.">
                            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                            <input type="hidden" name="id" value="<?php echo s($p->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
