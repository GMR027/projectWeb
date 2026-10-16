<?php use Classes\CurvaProductividad as CP; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-chart-line"></i> Curvas de productividad</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($curvas); ?> curva(s)</p>
    </div>
    <div class="la-controles">
        <a href="/curvas/s?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-chart-line"></i> Curva S del programa</a>
        <a href="/curvas/maestra?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-chart-area"></i> Gráfica maestra</a>
        <?php if ($puedeEditar): ?><a href="/curvas/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">+ Nueva curva</a><?php endif; ?>
    </div>
</div>
<div class="card">
    <?php if (empty($curvas)): ?>
        <p class="text-muted">Aún no hay curvas. Crea una para medir, por ejemplo, el cableado o la tubería.</p>
    <?php else: ?>
    <table class="tabla">
        <thead><tr><th>Curva</th><th class="hide-m">Material</th><th class="hide-m">Cantidad</th><th class="hide-m">Tipo</th><th class="hide-m">Inicio</th><th class="hide-m">Fin</th><th class="hide-m">Semanas</th><th class="hide-m">Avance real</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($curvas as $c): $r = $resumen[$c->id]; ?>
            <tr>
                <td><a href="/curvas/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($c->id); ?>"><?php echo s($c->nombre); ?></a></td>
                <td class="hide-m"><?php echo s($c->mat_nombre); ?></td>
                <td class="mono hide-m"><?php echo number_format((int)$c->cantidad); ?> <?php echo s($c->unidad); ?></td>
                <td class="hide-m"><?php echo s(CP::TIPOS[$c->tipo_curva] ?? $c->tipo_curva); ?></td>
                <td class="mono hide-m"><?php echo formatear_fecha($c->fecha_inicio); ?></td>
                <td class="mono hide-m"><?php echo formatear_fecha($c->fecha_fin); ?></td>
                <td class="mono hide-m"><?php echo (int)$r['periodos']; ?></td>
                <td class="mono hide-m"><?php echo $r['avance_real'] === null ? '—' : $r['avance_real'] . '%'; ?></td>
                <td class="acciones">
                    <a href="/curvas/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($c->id); ?>" class="btn btn-sm btn-ghost">Ver</a>
                    <?php if ($puedeEditar): ?>
                        <a href="/curvas/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($c->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/curvas/eliminar" style="display:inline" data-confirm="¿Eliminar curva?|Se perderán también los datos reales capturados.">
                            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                            <input type="hidden" name="id" value="<?php echo s($c->id); ?>">
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
