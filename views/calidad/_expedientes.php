<?php
/** Expedientes de calidad por partida. Variables: $proyecto, $pid, $expedientes, $puedeEditar */
?>
<div class="card">
    <h3><i class="fa-solid fa-folder-open"></i> Expedientes de calidad por partida</h3>
    <p class="text-muted text-sm">Cada expediente reúne los ITP, puntos, ensayos y no conformidades de la partida. Se puede consultar vivo o cerrar en un PDF firmado que queda congelado.</p>
    <?php if (!$expedientes): ?><p class="text-muted">Aún no hay partidas para expedientes.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Partida</th><th class="num">Planes</th><th class="num">Puntos</th><th class="num">Ensayos</th><th class="num">NC abiertas</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($expedientes as $x): $pa = $x['partida']; $r = $x['resumen']; $es = $x['estado']; ?>
            <tr>
                <td><span class="mono"><?php echo s($pa->codigo); ?></span> <?php echo s($pa->nombre); ?></td>
                <td class="num"><?php echo (int)$r['planes']; ?></td>
                <td class="num"><?php echo (int)$r['cumplen']; ?>/<?php echo (int)$r['puntos']; ?></td>
                <td class="num"><?php echo (int)$r['ensayos']; ?><?php if ((int)$r['rechazados']): ?><br><small class="text-danger"><?php echo (int)$r['rechazados']; ?> rechazado(s)</small><?php endif; ?></td>
                <td class="num"><?php echo (int)$r['nc_abiertas']; ?><?php if ((float)$r['costo_nc'] > 0): ?><br><small class="text-muted">$<?php echo number_format((float)$r['costo_nc'], 2); ?></small><?php endif; ?></td>
                <td><?php if ($es['estado'] === 'cerrado'): ?><span class="badge badge-cliente"><i class="fa-solid fa-lock"></i> Cerrado</span><?php if ($es['cerrado_en']): ?><br><small class="text-muted"><?php echo s(formatear_fecha(substr((string)$es['cerrado_en'], 0, 10), 'd/m/Y')); ?></small><?php endif; ?><?php else: ?><span class="badge badge-visitante">Borrador</span><?php endif; ?></td>
                <td class="acciones" style="white-space:nowrap">
                    <a class="btn btn-xs btn-ghost" href="/calidad/expediente/pdf?proyecto=<?php echo $pid; ?>&partida=<?php echo (int)$pa->id; ?>" title="PDF" aria-label="PDF"><i class="fa-solid fa-file-pdf"></i></a>
                    <?php if ($puedeEditar && $es['estado'] !== 'cerrado'): ?>
                        <form class="inline" method="POST" action="/calidad/expediente/cerrar" data-confirm="Cerrar expediente|Se generará un PDF firmado y quedará congelado. Después solo el admin o el dueño pueden reabrirlo.|Cerrar y firmar">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="partida" value="<?php echo (int)$pa->id; ?>">
                            <button class="btn btn-xs btn-primary" type="submit" title="Cerrar y firmar" aria-label="Cerrar y firmar"><i class="fa-solid fa-signature"></i></button>
                        </form>
                    <?php elseif ($es['estado'] === 'cerrado' && (is_admin() || es_propietario($proyecto))): ?>
                        <form class="inline" method="POST" action="/calidad/expediente/reabrir" data-confirm="Reabrir expediente|El expediente volverá a borrador y el PDF firmado se descartará.|Reabrir">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="partida" value="<?php echo (int)$pa->id; ?>">
                            <button class="btn btn-xs btn-ghost" type="submit" title="Reabrir" aria-label="Reabrir"><i class="fa-solid fa-lock-open"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
