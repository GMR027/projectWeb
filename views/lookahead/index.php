<?php
$nombreFecha = fn(\DateTime $d) => $d->format('d/m/Y');
$totalCols = count($semanas) * 7;
$pta = $config->pta();
$nSig = (int)$config->semanas;
$encEst = $config->encabezado();
$puedeEditar = puede_gestionar_proyecto($proyecto);
?>
<div class="page-header no-print">
    <div>
        <h2><i class="fa-solid fa-binoculars"></i> <?php echo s($config->nombreHerramienta()); ?> · semana actual + <?php echo $nSig; ?></h2>
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
        <?php if ($puedeEditar): ?>
        <span class="la-semanas-ctl" title="Semanas visibles después de la actual (no se imprime)">
            <button type="button" class="btn btn-ghost btn-sm" id="la-menos" <?php echo $nSig <= \Model\LookaheadConfig::MIN_SEMANAS ? 'disabled' : ''; ?> aria-label="Quitar una semana"><i class="fa-solid fa-minus"></i></button>
            <span class="la-semanas-n"><?php echo $nSig; ?> sem.</span>
            <button type="button" class="btn btn-ghost btn-sm" id="la-mas" <?php echo $nSig >= \Model\LookaheadConfig::MAX_SEMANAS ? 'disabled' : ''; ?> aria-label="Agregar una semana"><i class="fa-solid fa-plus"></i></button>
        </span>
        <?php endif; ?>
    </div>
</div>

<?php if ($puedeEditar): ?>
<details class="la-ajustes no-print">
    <summary><i class="fa-solid fa-sliders"></i> Ajustes del <?php echo s($config->nombreHerramienta()); ?></summary>
    <div class="la-ajustes-grid">
        <label>Nombre de la herramienta<input type="text" id="la-nombre" maxlength="60" value="<?php echo s($config->nombre); ?>" placeholder="Lookahead"></label>
        <label>Nombre de la columna PTA<input type="text" id="la-pta-nombre" maxlength="20" value="<?php echo s($config->pta_nombre); ?>" placeholder="PTA"></label>
        <label>Título del banner<input type="text" id="la-titulo" maxlength="150" value="<?php echo s($config->titulo); ?>" placeholder="<?php echo s(\Model\LookaheadConfig::TITULO_DEFECTO); ?>"><small class="text-muted">Variables: {semanas} {proyecto} {codigo}</small></label>
        <label>Plantilla de encabezado (cajetín con logos)
            <select id="la-encabezado">
                <option value="0">Encabezado básico (logos y nombre del proyecto)</option>
                <?php foreach ($encabezados as $enc): ?><option value="<?php echo (int)$enc->id; ?>" <?php echo (int)$config->encabezado_id === (int)$enc->id ? 'selected' : ''; ?>><?php echo s($enc->nombre); ?></option><?php endforeach; ?>
            </select>
            <small class="text-muted">Se crea en <a href="/simbolos?proyecto=<?php echo s($proyecto->id); ?>#estampas">Símbolos › Estampas inteligentes</a>. Se dibuja sobre la tabla en pantalla y PDF.</small>
        </label>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="la-guardar-ajustes">Guardar ajustes</button>

    <h4 class="mt-2">Limitaciones</h4>
    <p class="text-muted text-sm">Son las mismas del programa de obra, Last Planner y demás herramientas: renombrarlas o agregarlas aquí las cambia en todos los proyectos.</p>
    <div id="la-limitaciones">
        <?php foreach ($tipos as $t): ?>
        <div class="la-lim-fila" data-id="<?php echo (int)$t->id; ?>">
            <input type="color" value="<?php echo s($t->color); ?>" aria-label="Color">
            <input type="text" maxlength="50" value="<?php echo s($t->nombre); ?>" aria-label="Nombre">
            <button type="button" class="btn btn-ghost btn-sm" data-accion="guardar" title="Guardar"><i class="fa-solid fa-floppy-disk"></i></button>
            <button type="button" class="btn btn-ghost btn-sm" data-accion="eliminar" title="Quitar"><i class="fa-solid fa-trash"></i></button>
        </div>
        <?php endforeach; ?>
        <div class="la-lim-fila" data-id="0">
            <input type="color" value="#FF5A1F" aria-label="Color">
            <input type="text" maxlength="50" placeholder="Nueva limitación" aria-label="Nombre">
            <button type="button" class="btn btn-ghost btn-sm" data-accion="guardar" title="Agregar"><i class="fa-solid fa-plus"></i></button>
        </div>
    </div>
</details>
<?php endif; ?>

<?php if ($encEst): [$Wu, $Hu] = \Classes\Estampas::dimensiones($encEst->config()); ?>
<div class="la-encabezado-est" style="position:relative;width:<?php echo round($Wu, 2); ?>pt;height:<?php echo round($Hu, 2); ?>pt;max-width:100%;overflow:auto">
    <?php echo \Classes\Estampas::renderGuardada($encEst, $proyecto, null, ['lamina_n' => 1, 'lamina_total' => 1], 'pt', 1.0); ?>
</div>
<?php endif; ?>

<div class="la-wrap">
    <table class="la-tabla">
        <thead>
            <tr class="la-titulo">
                <th rowspan="4" class="la-id">ID<br>(EDT)</th>
                <th rowspan="2" colspan="1" class="la-act-cab">
                    <?php if (!$encEst): ?>
                    <span class="la-logos">
                        <?php if ($le = $proyecto->logoEmpresa()): ?><img src="/build/img/logos/<?php echo s($le); ?>" alt="" class="la-logo"><?php endif; ?>
                        <?php if ($proyecto->contratista_logo): ?><img src="/build/img/logos/<?php echo s($proyecto->contratista_logo); ?>" alt="<?php echo s($proyecto->contratista); ?>" class="la-logo"><?php endif; ?>
                    </span>
                    <strong><?php echo s($proyecto->nombre); ?></strong>
                    <span>Fecha de actualización: <?php echo date('d/m/Y'); ?></span>
                    <?php endif; ?>
                </th>
                <th colspan="<?php echo $totalCols + 2; ?>" class="la-banner hide-m"><?php echo s($config->tituloBanner($proyecto)); ?></th>
                <th colspan="<?php echo count($tipos); ?>" rowspan="2" class="la-banner la-banner-lim hide-m">Limitaciones</th>
            </tr>
            <tr class="la-semanas">
                <?php foreach ($semanas as $i => $sem): ?>
                    <th colspan="7" class="hide-m <?php echo $i === 0 ? 'la-actual' : ''; ?>"><?php echo s($sem['etiqueta']); ?> · S<?php echo s($sem['iso']); ?></th>
                    <?php if ($i === 0): ?><th rowspan="3" class="la-comp">% Comp</th><th rowspan="3" class="la-pta hide-m"><span><?php echo s($pta); ?></span></th><?php endif; ?>
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
    <span><strong><?php echo s($pta); ?></strong>: OK = lista para ejecutarse, ! = pendiente<?php echo $puedeEditar ? ' (clic para alternar)' : ''; ?></span>
</div>

<?php if ($puedeEditar): ?>
<script>
(() => {
    const pid = <?php echo (int)$proyecto->id; ?>;
    const post = async (url, datos) => {
        const cuerpo = new URLSearchParams({proyecto_id: pid, ...datos});
        const r = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: cuerpo});
        const d = await r.json();
        if (!d.success) throw new Error(d.error || 'No se pudo guardar');
        return d;
    };
    const guardar = async (datos, recargar = true) => {
        try { await post('/api/lookahead/config', datos); if (recargar) location.reload(); }
        catch (e) { window.avisoModal('No se pudo guardar', e.message); }
    };
    const n = <?php echo $nSig; ?>;
    document.getElementById('la-menos')?.addEventListener('click', () => guardar({semanas: n - 1}));
    document.getElementById('la-mas')?.addEventListener('click', () => guardar({semanas: n + 1}));
    document.getElementById('la-guardar-ajustes')?.addEventListener('click', () => guardar({
        nombre: document.getElementById('la-nombre').value,
        pta_nombre: document.getElementById('la-pta-nombre').value,
        titulo: document.getElementById('la-titulo').value,
        encabezado_id: document.getElementById('la-encabezado').value,
    }));
    document.getElementById('la-limitaciones')?.addEventListener('click', async (ev) => {
        const b = ev.target.closest('button[data-accion]');
        if (!b) return;
        const fila = b.closest('.la-lim-fila');
        const [color, nombre] = fila.querySelectorAll('input');
        try {
            await post('/api/lookahead/limitacion', {id: fila.dataset.id, nombre: nombre.value, color: color.value, accion: b.dataset.accion});
            location.reload();
        } catch (e) { window.avisoModal('Limitaciones', e.message); }
    });
})();
</script>
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
            window.avisoModal('No se pudo actualizar <?php echo s($pta); ?>', e.message);
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
<?php endif; ?>
