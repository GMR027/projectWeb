<?php
/** Lista de organizaciones y su plan (solo admin). */
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-building"></i> Organizaciones</h2>
        <p class="text-muted">Empresas que contratan el plan. Aquí se crean, se les asignan clientes y se administra su facturación.</p>
    </div>
    <a href="/organizaciones/crear" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva organización</a>
</div>

<?php if (!empty($_GET['creada'])): ?><p class="text-sm" style="color:var(--primary)">Organización creada. Agrega su cliente propietario en la ficha.</p><?php endif; ?>
<?php if (!empty($_GET['eliminada'])): ?><p class="text-sm" style="color:var(--primary)">Organización eliminada.</p><?php endif; ?>
<?php if (!empty($_GET['recalculado'])): ?><p class="text-sm" style="color:var(--primary)">Almacenamiento recalculado.</p><?php endif; ?>

<div class="card" style="display:flex; flex-wrap:wrap; gap:.6rem; align-items:center; margin-bottom:1.2rem">
    <span class="text-sm"><?php echo count($filas); ?> organización(es)</span>
    <?php if ($verArchivadas): ?>
        <a href="/organizaciones" class="btn btn-sm btn-ghost"><i class="fa-solid fa-eye"></i> Ver solo activas</a>
    <?php else: ?>
        <a href="/organizaciones?archivadas=1" class="btn btn-sm btn-ghost"><i class="fa-solid fa-box-archive"></i> Ver archivadas</a>
    <?php endif; ?>
</div>

<div class="card">
    <table class="tabla">
        <thead>
            <tr>
                <th>Organización</th>
                <th class="hide-m">Propietario</th>
                <th>Plan</th>
                <th class="hide-m">Obras activas</th>
                <th class="hide-m">Asientos</th>
                <th class="hide-m">Almacenamiento</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($filas as $f): $org = $f['org']; $lim = $f['limites']; ?>
            <tr>
                <td>
                    <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>"><strong><?php echo s($org->nombre); ?></strong></a>
                    <?php if ($org->estaArchivada()): ?> <span class="badge">Archivada</span><?php endif; ?>
                    <?php if ($org->rfc): ?><br><span class="text-muted text-sm mono"><?php echo s($org->rfc); ?></span><?php endif; ?>
                </td>
                <td class="hide-m"><?php echo $f['propietario'] ? s($f['propietario']->nombre) . '<br><span class="text-muted text-sm">' . s($f['propietario']->email) . '</span>' : '<span class="text-muted text-sm">Sin propietario</span>'; ?></td>
                <td>
                    <span class="badge"><?php echo s($f['plan']['nombre']); ?></span>
                    <span class="badge <?php echo s($org->estadoBadge()); ?>"><?php echo s($org->estadoEtiqueta()); ?></span>
                    <?php if (($org->estado_plan ?? '') === 'trial' && $org->diasTrial() !== null): ?>
                        <br><span class="text-muted text-sm">Quedan <?php echo max(0, (int)$org->diasTrial()); ?> día(s)</span>
                    <?php endif; ?>
                </td>
                <td class="mono hide-m"><?php echo (int)$f['obras']; ?> / <?php echo $lim['obras'] === null ? '∞' : (int)$lim['obras']; ?></td>
                <td class="mono hide-m"><?php echo (int)$f['asientos']; ?> / <?php echo $lim['usuarios'] === null ? '∞' : (int)$lim['usuarios']; ?><br><span class="text-muted text-sm"><?php echo (int)$f['visitantes']; ?> visitantes</span></td>
                <td class="mono hide-m"><?php echo \Classes\Almacenamiento::formato((int)$f['almacenamiento']); ?> / <?php echo $f['limite_almacenamiento'] === null ? 'sin tope' : \Classes\Almacenamiento::formato((int)$f['limite_almacenamiento']); ?></td>
                <td class="acciones">
                    <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>" class="btn btn-sm btn-ghost">Ver</a>
                    <a href="/organizaciones/editar?id=<?php echo (int)$org->id; ?>" class="btn btn-sm btn-ghost">Editar</a>
                    <form class="inline" method="POST" action="/organizaciones/recalcular">
                        <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
                        <button type="submit" class="btn btn-sm btn-ghost" title="Recalcular el almacenamiento usado">Recalcular</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$filas): ?><tr><td colspan="7" class="text-muted"><?php echo $verArchivadas ? 'No hay organizaciones archivadas.' : 'Aún no hay organizaciones. Crea la primera con «Nueva organización».'; ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:1.6rem">
    <h3 class="card-titulo"><i class="fa-solid fa-layer-group"></i> Planes</h3>
    <div class="grid-proyectos">
        <?php foreach ($planes as $p): ?>
            <div class="card">
                <h4><?php echo s($p['nombre']); ?> <?php if (!empty($p['destacado'])): ?><span class="badge">Más popular</span><?php endif; ?></h4>
                <p class="text-muted text-sm"><?php echo s($p['lema']); ?></p>
                <p><strong><?php echo s(\Classes\Plan::precio($p['clave'])); ?></strong> MXN/mes · <strong><?php echo s(\Classes\Plan::precio($p['clave'], true)); ?></strong> al año</p>
                <ul class="text-sm">
                    <li><?php echo $p['obras'] === null ? 'Obras ilimitadas' : (int)$p['obras'] . ' obras activas'; ?></li>
                    <li><?php echo $p['usuarios'] === null ? 'Usuarios ilimitados' : (int)$p['usuarios'] . ' usuarios de edición'; ?></li>
                    <li>Visitantes <?php echo $p['visitantes'] === null ? 'ilimitados' : (int)$p['visitantes']; ?></li>
                    <li><?php echo $p['almacenamiento_gb'] === null ? 'Sin tope total' : (int)$p['almacenamiento_gb'] . ' GB'; ?> · <?php echo (int)$p['almacenamiento_por_obra_mb']; ?> MB por obra</li>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>
