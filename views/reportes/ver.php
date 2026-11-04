<?php
$id = s($proyecto->id);
$puede = puede_gestionar_proyecto($proyecto);
$r = $reporte;
$cerrado = $r->estaCerrado();
$c = $parte['costos'];
$nomAct = function (int $idA) use ($parte): string {
    foreach ($parte['actividades'] as [$a]) if ((int)$a->id === $idA) return $a->codigo . ' · ' . $a->nombre;
    return '—';
};
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-clipboard-list"></i> Parte diario · <?php echo s(fecha_larga_es($r->fecha)); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->numero_trabajo ? ' · Trabajo ' . s($proyecto->numero_trabajo) : ''; ?>
            <span class="pd-chip <?php echo $cerrado ? 'pd-chip-cerrado' : ''; ?>"><i class="fa-solid <?php echo $cerrado ? 'fa-lock' : 'fa-pen'; ?>"></i> <?php echo $cerrado ? 'Cerrado' : 'Borrador'; ?></span>
        </p>
    </div>
    <div class="la-controles">
        <?php if ($puede && !$cerrado): ?><a href="/reportes/parte?proyecto=<?php echo $id; ?>&id=<?php echo (int)$r->id; ?>" class="btn btn-primary"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
        <a href="/reportes/parte/pdf?proyecto=<?php echo $id; ?>&id=<?php echo (int)$r->id; ?>" class="btn btn-ghost" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        <?php if ($puede_cerrar && !$cerrado): ?>
            <form method="POST" action="/reportes/parte/cerrar" data-confirm="Cerrar el parte|Al cerrarlo se actualiza el avance, se registran las horas y se agrega el resumen a la bitácora. Después solo se puede reabrir.|Cerrar parte">
                <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-lock"></i> Cerrar parte</button>
            </form>
        <?php elseif ($puede_cerrar && $cerrado): ?>
            <form method="POST" action="/reportes/parte/reabrir" data-confirm="Reabrir el parte|Se podrá editar de nuevo y el resumen de la bitácora se conserva.|Reabrir">
                <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-lock-open"></i> Reabrir</button>
            </form>
        <?php endif; ?>
        <a href="/reportes?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Reportes</a>
    </div>
</div>

<div class="pd-ver">
    <div class="pd-ver-principal">
        <div class="card">
            <div class="pd-resumen">
                <div><span>Fecha</span><b><?php echo s(fecha_corta_es($r->fecha)); ?></b></div>
                <div><span>Turno</span><b><?php echo s(\Model\ReporteDiario::TURNOS[$r->turno] ?? $r->turno); ?></b></div>
                <div><span>Área</span><b><?php echo s($r->area ?: '—'); ?></b></div>
                <div><span>Supervisor</span><b><?php echo s($r->supervisor ?: '—'); ?></b></div>
                <div><span>Clima</span><b><?php echo $r->clima_precip_mm !== null ? s($r->clima_precip_mm) . ' mm lluvia' : '—'; ?><?php echo $r->clima_tmax !== null ? ' · ' . s($r->clima_tmax) . '°/' . s($r->clima_tmin) . '°' : ''; ?></b></div>
                <div><span>Visitas</span><b><?php echo s($r->visitas ?: '—'); ?></b></div>
            </div>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-list-check"></i> Actividades y avance (<?php echo count($parte['actividades']); ?>)</h3>
            <?php if (!$parte['actividades']): ?><p class="text-muted">Sin actividades.</p><?php endif; ?>
            <ul class="pd-ver-acts">
                <?php foreach ($parte['actividades'] as [$a, $p]): ?>
                    <li>
                        <span class="mono"><?php echo s($a->codigo); ?></span>
                        <strong><?php echo s($a->nombre); ?></strong>
                        <small><?php echo s($p->nombre); ?> · <?php echo s(fecha_corta_es($a->fecha_inicio)); ?> → <?php echo s(fecha_corta_es($a->fecha_fin)); ?></small>
                        <span class="pd-avance-chip"><?php echo $a->esHito() ? 'Hito' : (int)$a->progreso . ' %'; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-users"></i> Personal (<?php echo count($parte['personal']); ?>)</h3>
            <?php if (!$parte['personal']): ?><p class="text-muted">Sin personal registrado.</p><?php else: ?>
                <div class="tabla-wrapper"><table class="tabla">
                    <thead><tr><th>Persona</th><th>Actividad</th><th>Horas</th><th>Extra</th><th>Cuadrilla</th><th>Observación</th></tr></thead>
                    <tbody>
                    <?php foreach ($parte['personal'] as $x): ?>
                        <tr><td><?php echo s($x->nombre ?: '—'); ?></td><td><?php echo s($nomAct((int)$x->actividad_id)); ?></td><td><?php echo s($x->horas_normal); ?></td><td><?php echo s($x->horas_extra); ?></td><td><?php echo s($x->cuadrilla ?: '—'); ?></td><td><?php echo s($x->observacion ?: '—'); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-truck"></i> Equipo (<?php echo count($parte['equipo']); ?>)</h3>
            <?php if (!$parte['equipo']): ?><p class="text-muted">Sin equipo registrado.</p><?php else: ?>
                <div class="tabla-wrapper"><table class="tabla">
                    <thead><tr><th>Equipo</th><th>Actividad</th><th>Horas</th><th>Operador</th><th>Importe</th></tr></thead>
                    <tbody>
                    <?php foreach ($parte['equipo'] as $x): ?>
                        <tr><td><?php echo s($x->nombre); ?></td><td><?php echo s($nomAct((int)$x->actividad_id)); ?></td><td><?php echo s($x->horas); ?></td><td><?php echo s($x->operador ?: '—'); ?></td><td>$<?php echo number_format((float)$x->importe, 2); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-cubes"></i> Materiales (<?php echo count($parte['materiales']); ?>)</h3>
            <?php if (!$parte['materiales']): ?><p class="text-muted">Sin materiales registrados.</p><?php else: ?>
                <div class="tabla-wrapper"><table class="tabla">
                    <thead><tr><th>Material</th><th>Movimiento</th><th>Cantidad</th><th>Actividad</th><th>Proveedor</th><th>Remisión</th></tr></thead>
                    <tbody>
                    <?php foreach ($parte['materiales'] as $x): ?>
                        <tr><td><?php echo s($x->nombre); ?></td><td><?php echo s(\Model\ReporteMaterial::TIPOS[$x->tipo] ?? $x->tipo); ?></td><td><?php echo s($x->cantidad); ?> <?php echo s($x->unidad); ?></td><td><?php echo s($nomAct((int)$x->actividad_id)); ?></td><td><?php echo s($x->proveedor ?: '—'); ?></td><td><?php echo s($x->remision ?: '—'); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-hourglass-half"></i> Tiempos muertos (<?php echo count($parte['tiempos']); ?>)</h3>
            <?php if (!$parte['tiempos']): ?><p class="text-muted">Sin tiempos muertos.</p><?php else: ?>
                <div class="tabla-wrapper"><table class="tabla">
                    <thead><tr><th>Actividad</th><th>Horas</th><th>Causa</th><th>Nota</th></tr></thead>
                    <tbody>
                    <?php foreach ($parte['tiempos'] as $x): ?>
                        <tr><td><?php echo s($nomAct((int)$x->actividad_id)); ?></td><td><?php echo s($x->horas); ?></td><td><?php echo s(\Model\ReporteTiempo::CAUSAS[$x->causa] ?? $x->causa); ?></td><td><?php echo s($x->nota ?: '—'); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-helmet-safety"></i> Seguridad</h3>
            <?php $seg = $parte['seguridad']; ?>
            <div class="pd-resumen">
                <div><span>Charla</span><b><?php echo s(($seg && $seg->charla_tema !== '') ? $seg->charla_tema : '—'); ?></b></div>
                <div><span>Asistentes</span><b><?php echo (int)($seg->asistentes ?? 0); ?></b></div>
                <div><span>EPP</span><b><?php echo !empty($seg->epp_ok) ? 'Completo' : 'No verificado'; ?></b></div>
            </div>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-camera-retro"></i> Fotos (<?php echo count($parte['fotos']); ?>)</h3>
            <?php if (!$parte['fotos']): ?><p class="text-muted">Este parte no tiene fotos.</p><?php endif; ?>
            <div class="pd-fotos">
                <?php foreach ($parte['fotos'] as $f): ?>
                    <a class="pd-foto" href="/uploads/reportes/<?php echo s($f->archivo); ?>" target="_blank" rel="noopener" data-visor="/uploads/reportes/<?php echo s($f->archivo); ?>">
                        <img src="/uploads/reportes/<?php echo s($f->archivo); ?>" alt="<?php echo s($f->leyenda ?: 'Foto del parte'); ?>" loading="lazy">
                        <?php if ($f->leyenda): ?><span class="pd-foto-leyenda"><?php echo s($f->leyenda); ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-comment"></i> Comentarios</h3>
            <p style="white-space:pre-wrap"><?php echo trim((string)$r->comentarios) !== '' ? s($r->comentarios) : '—'; ?></p>
        </div>
    </div>

    <aside class="pd-ver-lateral">
        <div class="card pd-costos-ver">
            <h3>Costo del día</h3>
            <div><span>Personal</span><b>$<?php echo number_format($c['personal'], 2); ?></b></div>
            <div><span>Equipo</span><b>$<?php echo number_format($c['equipo'], 2); ?></b></div>
            <div><span>Material consumido</span><b>$<?php echo number_format($c['material'], 2); ?></b></div>
            <div class="pd-costo-total"><span>Total</span><b>$<?php echo number_format($c['total'], 2); ?></b></div>
            <small class="text-muted"><?php echo s($c['horas_personal']); ?> h de personal · <?php echo s($c['horas_equipo']); ?> h de equipo · <?php echo s($c['horas_muertas']); ?> h muertas</small>
        </div>

        <?php if ($r->firma_imagen || $r->firma_nombre): ?>
            <div class="card">
                <h3>Firma</h3>
                <?php if ($r->firma_imagen): ?><img class="pd-firma-img" src="/uploads/reportes/<?php echo s($r->firma_imagen); ?>" alt="Firma del residente"><?php endif; ?>
                <p class="text-sm text-muted"><?php echo s($r->firma_nombre ?: '—'); ?></p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Registro</h3>
            <p class="text-sm text-muted">Creado por <?php echo s($r->creado_por ?: '—'); ?><?php if ($r->cerrado_en): ?><br>Cerrado el <?php echo s(date('d/m/Y H:i', strtotime($r->cerrado_en))); ?><?php endif; ?></p>
        </div>
    </aside>
</div>