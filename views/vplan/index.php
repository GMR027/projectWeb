<?php $tab = 'laminas'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-map-location-dot"></i> vPlan · Plan visual</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($laminas); ?> lámina(s)</p>
    </div>
    <div class="la-controles">
        <a href="/exportar?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-file-pdf"></i> Exportar PDF</a>
        <?php if ($puedeEditar): ?><a href="/vplan/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">+ Nueva lámina</a><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/_tabs.php'; ?>
<div class="card">
    <?php if (empty($laminas)): ?>
        <p class="text-muted">Aún no hay láminas. Crea una por partida del programa de obra y sube el plano y su simbología.</p>
    <?php else: ?>
    <table class="tabla" data-buscable>
        <thead><tr><th class="hide-m">Plano</th><th>Lámina</th><th class="hide-m">Partida</th><th class="hide-m">Supervisor</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($laminas as $l): $img = $l->marcas_png ?: $l->plano; ?>
            <tr>
                <td class="hide-m"><?php if ($img): ?><img class="vp-mini" src="/uploads/vplan/<?php echo s($img); ?>" alt=""><?php endif; ?></td>
                <td><a class="nombre-archivo" href="/vplan/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($l->id); ?>"><?php echo archivo_icono_html('png', 'Lámina del vPlan'); ?> <?php echo s($l->titulo); ?></a></td>
                <td class="hide-m"><?php echo s($l->partida_nombre); ?></td>
                <td class="hide-m"><?php echo s($l->supervisor); ?></td>
                <td class="acciones">
                    <a href="/vplan/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($l->id); ?>" class="btn btn-sm btn-ghost">Ver</a>
                    <?php if ($puedeEditar): ?>
                        <a href="/vplan/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($l->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/vplan/eliminar" style="display:inline" data-confirm="¿Eliminar lámina?|Se borrarán sus imágenes y las marcas dibujadas.">
                            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                            <input type="hidden" name="id" value="<?php echo s($l->id); ?>">
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
