<?php
$nombreFecha = fn(\DateTime $d) => $d->format('d/m/Y');
$totalCols = 5 * 7;
$puedeEditar = puede_gestionar_proyecto($proyecto);
?>
<div class="page-header no-print">
    <div>
        <h2><i class="fa-solid fa-binoculars"></i> 4WLA · Lookahead de 4 semanas</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s($proyecto->codigo); ?></p>
    </div>
    <div class="la-controles">
        <a href="/lookahead?proyecto=<?php echo s($proyecto->id); ?>&fecha=<?php echo s($anterior); ?>" class="btn btn-ghost btn-sm" title="Semana anterior"><i class="fa-solid fa-chevron-left"></i></a>
        <a href="/lookahead?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost btn-sm">Hoy</a>
        <a href="/lookahead?proyecto=<?php echo s($proyecto->id); ?>&fecha=<?php echo s($siguiente); ?>" class="btn btn-ghost btn-sm" title="Semana siguiente"><i class="fa-solid fa-chevron-right"></i></a>
        <?php
        // Selector rápido de mes: rango del proyecto, ampliado para incluir la semana mostrada
        $mesesNombre = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $desde = new \DateTime(min($proyecto->fecha_inicio, $lunes->format('Y-m-d')));
        $hasta = new \DateTime(max($proyecto->fecha_fin, $lunes->format('Y-m-d')));
        $cursor = new \DateTime($desde->format('Y-m-01'));
        $mesActual = $lunes->format('Y-m');
        ?>
        <select class="btn btn-ghost btn-sm la-mes" title="Ir a un mes" aria-label="Ir a un mes"
                onchange="location.href='/lookahead?proyecto=<?php echo s($proyecto->id); ?>&fecha=' + this.value">
            <?php while ($cursor <= $hasta): ?>
                <option value="<?php echo $cursor->format('Y-m-d'); ?>" <?php echo $cursor->format('Y-m') === $mesActual ? 'selected' : ''; ?>>
                    <?php echo s($mesesNombre[(int)$cursor->format('n')] . ' ' . $cursor->format('Y')); ?>
                </option>
                <?php $cursor->modify('+1 month'); ?>
            <?php endwhile; ?>
        </select>
    </div>
</div>

<div class="la-wrap">
    <table class="la-tabla">
        <thead>
            <tr class="la-titulo">
                <th rowspan="4" class="la-id">ID<br>(EDT)</th>
                <th rowspan="2" colspan="1" class="la-act-cab">
                    <span class="la-logos">
                        <?php if ($le = $proyecto->logoEmpresa()): ?><img src="/build/img/logos/<?php echo s($le); ?>" alt="" class="la-logo"><?php endif; ?>
                        <?php if ($proyecto->contratista_logo): ?><img src="/build/img/logos/<?php echo s($proyecto->contratista_logo); ?>" alt="<?php echo s($proyecto->contratista); ?>" class="la-logo"><?php endif; ?>
                    </span>
                    <strong><?php echo s($proyecto->nombre); ?></strong>
                    <span>Fecha de actualización: <?php echo date('d/m/Y'); ?></span>
                </th>
                <th colspan="<?php echo $totalCols + 2; ?>" class="la-banner hide-m">4 Weeks Look Ahead · <?php echo s($proyecto->nombre); ?></th>
                <th colspan="<?php echo count($tipos); ?>" rowspan="2" class="la-banner la-banner-lim hide-m">Limitaciones</th>
            </tr>
            <tr class="la-semanas">
                <?php foreach ($semanas as $i => $sem): ?>
                    <th colspan="7" class="hide-m <?php echo $i === 0 ? 'la-actual' : ''; ?>"><?php echo s($sem['etiqueta']); ?> · S<?php echo s($sem['iso']); ?></th>
                    <?php if ($i === 0): ?><th rowspan="3" class="la-comp">% Comp</th><th rowspan="3" class="la-pta hide-m"><span>PTA</span></th><?php endif; ?>
                <?php endforeach; ?>
            </tr>
            <tr class="la-meses">
                <th rowspan="2" class="la-act-cab la-act-titulo">ACTIVIDAD</th>
                <?php foreach ($semanas as $i => $sem): ?>
                    <th colspan="7" class="hide-m"><?php echo s($sem['mes']); ?></th>
                <?php endforeach; ?>
                <?php foreach ($tipos as $t): ?>
                    <th rowspan="2" class="la-tipo hide-m"><span><?php echo s($t->nombre); ?></span></th>
                <?php endforeach; ?>
            </tr>
            <tr class="la-dias">
                <?php foreach ($semanas as $i => $sem): foreach ($sem['dias'] as $d): ?>
                    <th class="<?php echo !$d['habil'] ? 'la-inhabil' : ''; ?> <?php echo $d['hoy'] ? 'la-hoy' : ''; ?> hide-m">
                        <span class="la-num"><?php echo s($d['numero']); ?></span>
                        <span class="la-letra"><?php echo s($d['letra']); ?></span>
                    </th>
                <?php endforeach; endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filas)): ?>
                <tr><td colspan="<?php echo 2 + $totalCols + 2 + count($tipos); ?>" class="text-center text-muted la-vacio">
                    Este proyecto aún no tiene actividades.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($filas as $bloque): $partida = $bloque['partida']; ?>
                <tr class="la-partida" <?php if ($partida->color_bg) echo 'style="--la-color:' . s($partida->color_bg) . '"'; ?>>
                    <td class="la-id"><?php echo s($partida->codigo); ?></td>
                    <td colspan="<?php echo 1 + $totalCols + 1 + count($tipos) + 1; ?>"><?php echo s($partida->nombre); ?></td>
                </tr>
                <?php foreach ($bloque['actividades'] as $fila): $act = $fila['actividad']; ?>
                    <tr class="la-actividad">
                        <td class="la-id"><?php echo s($act->codigo); ?></td>
                        <td class="la-nombre <?php echo $fila['atrasada'] ? 'la-atrasada' : ''; ?>">
                            <?php echo s($act->nombre); ?>
                            <?php if ($fila['atrasada']): ?><span class="la-tag">Atrasada</span><?php endif; ?>
                        </td>
                        <?php foreach ($semanas as $i => $sem): ?>
                            <?php foreach ($sem['dias'] as $d): ?>
                                <?php
                                $on = $fila['celdas'][$d['fecha']] ?? false;
                                $clase = (!$d['habil'] ? 'la-inhabil' : '') . ' hide-m';
                                if ($on) {
                                    $clase .= ' la-barra la-' . ($fila['con_restriccion'] ? 'restriccion' : $act->status);
                                }
                                ?>
                                <td class="<?php echo trim($clase); ?>"><?php echo $on ? 'X' : ''; ?></td>
                            <?php endforeach; ?>
                            <?php if ($i === 0): ?>
                                <td class="la-comp <?php echo (int)$act->progreso >= 100 ? 'la-comp-ok' : ((int)$act->progreso > 0 ? 'la-comp-parcial' : ''); ?>"><?php echo s((int)$act->progreso); ?>%</td>
                                <td class="la-pta-celda hide-m">
                                    <?php if ($puedeEditar): ?>
                                        <button type="button" class="la-pta-btn <?php echo $fila['pta_ok'] ? 'la-pta-ok' : 'la-pta-no'; ?>" data-actividad="<?php echo s($act->id); ?>" title="Clic para alternar OK / !"><?php echo $fila['pta_ok'] ? 'OK' : '!'; ?></button>
                                    <?php else: ?>
                                        <span class="<?php echo $fila['pta_ok'] ? 'la-pta-ok' : 'la-pta-no'; ?>"><?php echo $fila['pta_ok'] ? 'OK' : '!'; ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php foreach ($tipos as $t): ?>
                            <td class="la-lim hide-m <?php echo isset($fila['tipos'][$t->id]) ? 'la-lim-on' : ''; ?>" <?php if (isset($fila['tipos'][$t->id])) echo 'style="--tipo:' . s($t->color) . '" title="' . s($t->nombre) . '"'; ?>>
                                <?php echo isset($fila['tipos'][$t->id]) ? '●' : ''; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="la-leyenda no-print">
    <span class="la-x la-completada">X</span> Completada
    <span class="la-x la-en_proceso">X</span> En proceso
    <span class="la-x la-no_iniciada">X</span> No iniciada
    <span class="la-x la-restriccion">X</span> Con restricción activa
    <span>· Cada X marca un día programado</span>
    <span><strong>PTA</strong>: OK = lista para ejecutarse, ! = pendiente<?php echo $puedeEditar ? ' (clic para alternar)' : ''; ?></span>
</div>

<?php if ($puedeEditar): ?>
<script>
document.querySelectorAll('.la-pta-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
            const r = await fetch('/api/actividad/pta', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'actividad_id=' + encodeURIComponent(btn.dataset.actividad)
            });
            const d = await r.json();
            if (!d.success) throw new Error(d.error);
            btn.textContent = d.pta ? 'OK' : '!';
            btn.classList.toggle('la-pta-ok', !!d.pta);
            btn.classList.toggle('la-pta-no', !d.pta);
        } catch (e) {
            window.avisoModal('No se pudo actualizar PTA', e.message);
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
<?php endif; ?>
