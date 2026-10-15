<?php
$fd = fn($f) => fecha_corta_es($f);
$cols = [
    'fecha_envio_requerida' => 'Fecha requerida de envío', 'no_submittal_contratista' => 'No. submittal ' . ($proyecto->contratista ?: 'contratista'),
    'fecha_envio_real' => 'Fecha real de envío', 'fecha_respuesta' => 'Fecha de respuesta',
];
$colsEntrega = [
    'fecha_compra_prog' => 'Compra (prog.)', 'fecha_instalacion_prog' => 'Instalación (prog.)', 'fecha_compra_real' => 'Compra (real)',
    'fecha_entrega_prog' => 'Entrega (prog.)', 'fecha_entrega_real' => 'Entrega (real)',
];
$etqStatus = ['A' => 'A', 'AN' => 'AN', 'P' => 'P', 'RA' => 'RA'];
$celdaFecha = function ($it, $c) use ($puedeEditar, $fd) {
    if ($puedeEditar) {
        return '<input type="date" class="msr-input" data-id="' . (int)$it->id . '" data-campo="' . $c . '" value="' . s($it->$c) . '">';
    }
    return '<span class="mono" data-campo="' . $c . '" data-v="' . s($it->$c) . '">' . s($fd($it->$c)) . '</span>';
};
$celdaTexto = function ($it, $c) use ($puedeEditar) {
    return $puedeEditar
        ? '<input type="text" class="msr-input msr-texto" data-id="' . (int)$it->id . '" data-campo="' . $c . '" value="' . s($it->$c) . '">'
        : s($it->$c);
};
?>
<div class="page-header no-print">
    <div>
        <h2><i class="fa-solid fa-truck-ramp-box"></i> <?php echo s($msr->nombre); ?></h2>
        <p class="text-muted"><?php echo count($items); ?> renglón(es) · <?php echo s($proyecto->nombre); ?><?php echo $msr->descripcion ? ' · ' . s($msr->descripcion) : ''; ?></p>
    </div>
    <div class="la-controles">
        <a href="/msr?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost btn-sm">← Mis MSR</a>
        <select id="f-status" class="btn btn-ghost btn-sm" aria-label="Filtrar por status">
            <option value="">Todos los status</option><option value="A">A</option><option value="AN">AN</option><option value="P">P</option><option value="RA">RA</option>
        </select>
        <select id="f-fechas" class="btn btn-ghost btn-sm" aria-label="Filtrar por fechas sin asignar">
            <option value="">Todas las fechas</option>
            <option value="programadas">Sin alguna fecha programada</option>
            <?php foreach (($cols + $colsEntrega) as $c => $t): if (!str_starts_with($c, 'fecha')) continue; ?>
                <option value="<?php echo $c; ?>">Sin <?php echo s(mb_strtolower($t)); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="f-alerta" class="btn btn-ghost btn-sm" aria-label="Filtrar por alertas">
            <option value="">Sin filtro de alertas</option>
            <option value="critico">Entrega crítica</option>
            <option value="atrasado">Fechas atrasadas</option>
        </select>
        <select id="f-prov" class="btn btn-ghost btn-sm" aria-label="Filtrar por proveedor">
            <option value="">Todos los proveedores</option>
            <?php foreach ($proveedores as $pv): ?><option value="<?php echo s(mb_strtolower($pv)); ?>"><?php echo s($pv); ?></option><?php endforeach; ?>
        </select>
        <?php if ($puedeEditar): ?>
            <a href="/msr/importar?proyecto=<?php echo s($proyecto->id); ?>&msr=<?php echo s($msr->id); ?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-file-import"></i> Importar Excel</a>
            <a href="/submittals/crear?proyecto=<?php echo s($proyecto->id); ?>&msr=<?php echo s($msr->id); ?>" class="btn btn-primary btn-sm">+ Submittal</a>
        <?php endif; ?>
    </div>
</div>

<div class="card msr-cabecera">
    <div class="msr-logo"><?php if ($proyecto->contratista_logo): ?><img src="/build/img/logos/<?php echo s($proyecto->contratista_logo); ?>" alt=""><?php endif; ?></div>
    <div class="msr-titulo"><strong>MSR -MATERIAL STATUS REPORT</strong><strong>REPORTE DEL STATUS DE LOS MATERIALES</strong></div>
    <div class="msr-logo msr-logo-caja"><?php if ($logoEmpresa): ?><img src="/build/img/logos/<?php echo s($logoEmpresa); ?>" alt=""><?php endif; ?></div>
    <table class="msr-datos">
        <tr><td colspan="2" style="text-align:right"><b>FECHA: <?php echo s(fecha_larga_es()); ?></b></td></tr>
        <tr><td>CLIENTE:</td><td><b><?php echo s($proyecto->contratista); ?></b></td></tr>
        <tr><td>PROYECTO:</td><td><b><?php echo s($proyecto->nombre); ?></b></td></tr>
        <tr><td>JOB No.</td><td><b><?php echo s($proyecto->numero_trabajo); ?></b></td></tr>
        <tr><td>LOCALIZACIÓN:</td><td><b><?php echo s($proyecto->ubicacion); ?></b></td></tr>
    </table>
</div>

<div class="msr-leyendas">
    <div>
        <div class="msr-ley-titulo">STATUS SUBMITTAL</div>
        <table class="msr-ley">
            <tr><td class="msr-st-A">A</td><td>Aprobado</td></tr>
            <tr><td class="msr-st-AN">AN</td><td>Aprobado con anotaciones</td></tr>
            <tr><td class="msr-st-P">P</td><td>En revisión</td></tr>
            <tr><td class="msr-st-RA">RA</td><td>Resubmitar para aprobación</td></tr>
        </table>
    </div>
    <div>
        <table class="msr-ley">
            <tr><td colspan="2"><b>Identificación tiempo de entrega</b></td></tr>
            <tr><td class="msr-crit-sw"></td><td>Tiempo de entrega de Material Crítico (≥ <?php echo $umbral; ?> días)</td></tr>
        </table>
        <span class="msr-atraso-nota text-sm"><b class="msr-atraso">fecha</b> Atrasada (sin fecha real)</span>
    </div>
    <?php if ($puedeEditar): ?>
        <form method="POST" action="/msr/umbral" class="msr-umbral">
            <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
            <input type="hidden" name="msr" value="<?php echo s($msr->id); ?>">
            <label class="text-sm">Crítico desde (días) <input type="number" name="umbral" min="1" value="<?php echo $umbral; ?>"></label>
            <button class="btn btn-ghost btn-sm" type="submit">Aplicar</button>
        </form>
    <?php endif; ?>
</div>

<div class="la-wrap msr-wrap">
<table class="msr-tabla" id="msr-tabla">
    <thead>
        <tr class="msr-grupo hide-m"><th colspan="9" class="h-navy">STATUS DEL SUBMITTAL</th><th colspan="8" class="h-azul">STATUS DE ENTREGA DEL MATERIAL O EQUIPO</th></tr>
        <tr>
            <th class="h-navy">ITEM PROLOG</th><th class="h-navy hide-m">Subcontractor submittal number</th><th class="h-navy hide-m">Spec Number</th><th class="h-navy">DESCRIPCIÓN</th>
            <th class="h-negro">FECHA REQUERIDA DE ENVIO DE SUBMITTAL (PROG)</th>
            <th class="h-gris hide-m">No. SUBMITTAL <?php echo s(mb_strtoupper($proyecto->contratista ?: 'CONTRATISTA')); ?></th><th class="h-gris">Real Submittal Date</th><th class="h-gris">AnswerDate</th><th class="h-gris">Status</th>
            <th class="h-azul hide-m">Delivery Time Schedule (Days)</th><th class="h-azul">FECHA COMPRA DE MATERIAL (PROG)</th><th class="h-azul">FECHA DE INSTALACION (PROG)</th>
            <th class="h-azulosc">FECHA DE COMPRA (REAL)</th><th class="h-azulosc">FECHA DE ENTREGA (PROG)</th><th class="h-azulosc">FECHA DE ENTREGA (REAL)</th>
            <th class="h-azul hide-m">DATOS DEL PROVEEDOR</th><th class="h-navy">OBSERVACIONES</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$items): ?>
        <tr><td colspan="17" class="text-muted" style="padding:2rem">Sin renglones todavía. Crea un paquete de submittal con materiales<?php echo $puedeEditar ? ' o importa tu MSR en Excel' : ''; ?>.</td></tr>
    <?php endif; ?>
    <?php foreach ($secciones as $nombreSec => $lista): ?>
        <?php if ($nombreSec !== ''): ?><tr class="msr-seccion"><td colspan="17"><?php echo s($nombreSec); ?></td></tr><?php endif; ?>
        <?php foreach ($lista as $it): $dias = $it->diasEntrega(); ?>
            <tr data-status="<?php echo s($it->status); ?>" data-prov="<?php echo s(mb_strtolower($it->proveedor)); ?>" data-id="<?php echo (int)$it->id; ?>" data-mat="<?php echo (int)$it->material_id; ?>" data-sub="<?php echo (int)$it->submittal_id; ?>">
                <td class="msr-img"><?php if ($it->mat_imagen): ?><img src="/uploads/materiales/<?php echo s($it->mat_imagen); ?>" alt=""><?php endif; ?></td>
                <td class="mono hide-m"><?php echo s($it->sub_numero); ?></td>
                <td class="mono hide-m"><?php echo s($it->mat_referencia); ?></td>
                <td class="msr-desc"><?php echo s($it->mat_nombre); ?></td>
                <?php foreach ($cols as $c => $t): ?>
                    <td<?php echo $c === 'no_submittal_contratista' ? ' class="hide-m"' : ''; ?>><?php echo str_starts_with($c, 'fecha') ? $celdaFecha($it, $c) : $celdaTexto($it, $c); ?></td>
                <?php endforeach; ?>
                <td>
                    <?php if ($puedeEditar): ?>
                        <button type="button" class="msr-st msr-st-<?php echo s($it->status); ?> msr-st-btn" data-id="<?php echo (int)$it->id; ?>" title="Clic para cambiar: P → A → AN → RA"><?php echo $etqStatus[$it->status] ?? s($it->status); ?></button>
                    <?php else: ?>
                        <span class="msr-st msr-st-<?php echo s($it->status); ?>"><?php echo $etqStatus[$it->status] ?? s($it->status); ?></span>
                    <?php endif; ?>
                </td>
                <td class="mono hide-m msr-dias <?php echo $it->esCritico($umbral) ? 'msr-crit' : ''; ?>"><?php echo $dias !== null ? number_format($dias, 2) : ''; ?></td>
                <?php foreach ($colsEntrega as $c => $t):
                    $atr = ($c === 'fecha_compra_prog' && $it->compraAtrasada()) || ($c === 'fecha_entrega_prog' && $it->entregaAtrasada()); ?>
                    <td class="<?php echo $atr ? 'msr-atraso' : ''; ?>" data-celda="<?php echo $c; ?>"><?php echo $celdaFecha($it, $c); ?></td>
                <?php endforeach; ?>
                <td class="msr-prov hide-m"><?php echo $celdaTexto($it, 'proveedor'); ?></td>
                <td><?php echo $celdaTexto($it, 'observaciones'); ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<script>
(function () {
    const umbral = <?php echo (int)$umbral; ?>;
    const tabla = document.getElementById('msr-tabla');
    const fs = document.getElementById('f-status'), fp = document.getElementById('f-prov');
    const ff = document.getElementById('f-fechas'), fa = document.getElementById('f-alerta');
    const PROGRAMADAS = ['fecha_envio_requerida', 'fecha_compra_prog', 'fecha_instalacion_prog', 'fecha_entrega_prog'];
    const vacia = (tr, c) => {
        const e = tr.querySelector('[data-campo="' + c + '"]');
        return !!e && !(e.value !== undefined && e.tagName === 'INPUT' ? e.value : e.dataset.v);
    };
    const filtrar = () => tabla.querySelectorAll('tbody tr[data-status]').forEach(tr => {
        const okFecha = !ff.value || (ff.value === 'programadas' ? PROGRAMADAS.some(c => vacia(tr, c)) : vacia(tr, ff.value));
        const okAlerta = !fa.value || (fa.value === 'critico' ? !!tr.querySelector('.msr-dias.msr-crit') : !!tr.querySelector('.msr-atraso'));
        tr.style.display = (!fs.value || tr.dataset.status === fs.value) && (!fp.value || tr.dataset.prov === fp.value) && okFecha && okAlerta ? '' : 'none';
    });
    [fs, fp, ff, fa].forEach(s => s.addEventListener('change', filtrar));

    const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(r => r.json());
    const etq = { A: 'A', AN: 'AN', P: 'P', RA: 'RA' };

    tabla.addEventListener('click', async e => {
        const b = e.target.closest('.msr-st-btn');
        if (!b) return;
        try {
            const d = await post('/api/msr/status', { id: b.dataset.id });
            if (!d.success) return;
            b.className = 'msr-st msr-st-' + d.status + ' msr-st-btn';
            b.textContent = etq[d.status];
            b.closest('tr').dataset.status = d.status;
            // El status es del submittal: se actualizan sus demás renglones (y la AnswerDate)
            if (d.compartido && tabla.querySelectorAll('tr[data-sub="' + b.closest('tr').dataset.sub + '"]').length > 1) { location.reload(); return; }
            const fr = b.closest('tr').querySelector('[data-campo="fecha_respuesta"]');
            if (fr && d.fecha_respuesta !== undefined) fr.value = d.fecha_respuesta || '';
            filtrar();
        } catch (_) {}
    });

    tabla.addEventListener('change', async e => {
        const inp = e.target.closest('.msr-input');
        if (!inp) return;
        const tr = inp.closest('tr');
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/msr/campo', { id: inp.dataset.id, campo: inp.dataset.campo, valor: inp.value });
            if (!d.success) { inp.classList.add('msr-error'); return; }
            if (d.compartido && tabla.querySelectorAll('tr[data-' + d.compartido.attr + '="' + d.compartido.id + '"]').length > 1) { location.reload(); return; }
            if (inp.dataset.campo === 'proveedor') tr.dataset.prov = inp.value.toLowerCase();
            const dias = tr.querySelector('.msr-dias');
            dias.textContent = d.dias === null ? '' : Number(d.dias).toFixed(2);
            dias.classList.toggle('msr-crit', d.critico);
            tr.querySelector('[data-celda="fecha_compra_prog"]').classList.toggle('msr-atraso', d.atrasoCompra);
            tr.querySelector('[data-celda="fecha_entrega_prog"]').classList.toggle('msr-atraso', d.atrasoEntrega);
        } catch (_) { inp.classList.add('msr-error'); }
    });
})();
</script>
