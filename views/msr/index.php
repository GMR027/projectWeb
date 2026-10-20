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
$tinta = function ($hex) { $h = ltrim($hex, '#'); $l = (0.299 * hexdec(substr($h, 0, 2)) + 0.587 * hexdec(substr($h, 2, 2)) + 0.114 * hexdec(substr($h, 4, 2))) / 255; return $l > 0.62 ? '#111111' : '#ffffff'; };
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
            <?php foreach ($columnas as $col): $c = $col['k']; $t = $col['n']; if (!str_starts_with($c, 'fecha')) continue; ?>
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

<?php $encEst = $msr->encabezado(); ?>
<?php if ($encEst): [$Wu, $Hu] = \Classes\Estampas::dimensiones($encEst->config()); ?>
<div class="msr-encabezado-est" id="msr-enc-est" style="width:100%;margin-bottom:1.2rem">
    <div id="msr-enc-int" style="position:relative;width:<?php echo round($Wu, 2); ?>pt;height:<?php echo round($Hu, 2); ?>pt;transform-origin:0 0">
        <?php echo \Classes\Estampas::renderGuardada($encEst, $proyecto, null, ['lamina_n' => 1, 'lamina_total' => 1], 'pt', 1.0); ?>
    </div>
</div>
<script>
(function () {   // el encabezado se escala para ocupar el ancho de la página, igual que en el PDF
    var ext = document.getElementById('msr-enc-est'), int = document.getElementById('msr-enc-int');
    if (!ext || !int) return;
    function ajustar() {
        var w = int.offsetWidth, h = int.offsetHeight; if (!w) return;
        var k = ext.clientWidth / w;
        int.style.transform = 'scale(' + k + ')';
        ext.style.height = (h * k) + 'px';
    }
    ajustar(); window.addEventListener('resize', ajustar); window.addEventListener('load', ajustar);
})();
</script>
<?php else: ?>
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
<?php endif; ?>

<div class="msr-leyendas">
    <?php if (!$encEst): ?>
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
    <?php endif; ?>
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
<table class="msr-tabla" id="msr-tabla" data-orden-url="/api/msr/orden" data-proyecto="<?php echo (int)$proyecto->id; ?>" data-msr="<?php echo (int)$msr->id; ?>">
    <thead>
        <?php if ($grupos): ?>
        <tr class="msr-grupo hide-m">
            <?php if ($puedeEditar): ?><th class="no-print"></th><?php endif; ?>
            <?php foreach ($grupos as $g): ?><th colspan="<?php echo (int)$g['span']; ?>"<?php echo $g['n'] === '' ? ' style="background:var(--bg-secondary)"' : ' style="background:' . s($g['c']) . ';color:' . $tinta($g['c']) . '"'; ?>><?php echo s($g['n']); ?></th><?php endforeach; ?>
        </tr>
        <?php endif; ?>
        <tr>
            <?php if ($puedeEditar): ?><th class="no-print msr-th-mv" title="Subir o bajar el material dentro de su partida">↕</th><?php endif; ?>
            <?php foreach ($columnas as $col): ?>
                <th class="<?php echo $col['hide'] ? 'hide-m' : ''; ?>" style="background:<?php echo s($col['c']); ?>;color:<?php echo $tinta($col['c']); ?>" data-col="<?php echo s($col['k']); ?>" data-g="<?php echo s($col['g']); ?>"<?php echo $puedeEditar ? ' draggable="true" title="Doble clic para cambiar título y color · arrastra o usa las flechas para mover"' : ''; ?>>
                    <?php if ($puedeEditar): ?><button type="button" class="msr-mv msr-mv-l no-print" data-dir="-1" aria-label="Mover a la izquierda">‹</button><?php endif; ?>
                    <span class="msr-th-t"><?php echo s($col['n']); ?></span>
                    <?php if ($puedeEditar): ?><button type="button" class="msr-mv msr-mv-r no-print" data-dir="1" aria-label="Mover a la derecha">›</button><?php endif; ?>
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php $nCols = max(1, count($columnas)) + ($puedeEditar ? 1 : 0); ?>
    <?php if (!$items): ?>
        <tr><td colspan="<?php echo $nCols; ?>" class="text-muted" style="padding:2rem">Sin renglones todavía. Crea un paquete de submittal con materiales<?php echo $puedeEditar ? ' o importa tu MSR en Excel' : ''; ?>.</td></tr>
    <?php endif; ?>
    <?php foreach ($secciones as $nombreSec => $lista): ?>
        <?php if ($nombreSec !== ''): $cs = $coloresSec[$nombreSec] ?? ''; ?><tr class="msr-seccion" data-sec="<?php echo s($nombreSec); ?>" data-color="<?php echo s($cs); ?>"<?php echo $puedeEditar ? ' title="Doble clic para cambiar el título y el color de la partida"' : ''; ?>><td colspan="<?php echo $nCols; ?>"<?php echo $cs ? ' style="background:' . s($cs) . ';color:' . $tinta($cs) . '"' : ''; ?>><?php echo s($nombreSec); ?></td></tr><?php endif; ?>
        <?php foreach ($lista as $it): $dias = $it->diasEntrega(); ?>
            <tr data-status="<?php echo s($it->status); ?>" data-prov="<?php echo s(mb_strtolower($it->proveedor)); ?>" data-id="<?php echo (int)$it->id; ?>" data-mat="<?php echo (int)$it->material_id; ?>" data-sub="<?php echo (int)$it->submittal_id; ?>">
                <?php if ($puedeEditar): ?><td class="no-print msr-mover"><button type="button" class="msr-ord" data-dir="-1" aria-label="Subir material" title="Subir">▲</button><button type="button" class="msr-ord" data-dir="1" aria-label="Bajar material" title="Bajar">▼</button></td><?php endif; ?>
                <?php foreach ($columnas as $col): $c = $col['k']; $hm = $col['hide'] ? ' hide-m' : ''; ?>
                    <?php if ($c === 'imagen'): ?>
                        <td class="msr-img<?php echo $hm; ?>"><?php if ($it->mat_imagen): ?><img src="/uploads/materiales/<?php echo s($it->mat_imagen); ?>" alt=""><?php endif; ?></td>
                    <?php elseif ($c === 'sub_numero'): ?>
                        <td class="mono<?php echo $hm; ?>"><?php echo s($it->sub_numero); ?></td>
                    <?php elseif ($c === 'referencia'): ?>
                        <td class="mono<?php echo $hm; ?>"><?php echo s($it->mat_referencia); ?></td>
                    <?php elseif ($c === 'descripcion'): ?>
                        <td class="msr-desc<?php echo $hm; ?>"><?php echo s($it->mat_nombre); ?></td>
                    <?php elseif ($c === 'status'): ?>
                        <td>
                        <?php if ($puedeEditar): ?>
                            <button type="button" class="msr-st msr-st-<?php echo s($it->status); ?> msr-st-btn" data-id="<?php echo (int)$it->id; ?>" title="Clic para cambiar: P → A → AN → RA"><?php echo $etqStatus[$it->status] ?? s($it->status); ?></button>
                        <?php else: ?>
                            <span class="msr-st msr-st-<?php echo s($it->status); ?>"><?php echo $etqStatus[$it->status] ?? s($it->status); ?></span>
                        <?php endif; ?>
                        </td>
                    <?php elseif ($c === 'dias'): ?>
                        <td class="mono msr-dias<?php echo $hm; ?> <?php echo $it->esCritico($umbral) ? 'msr-crit' : ''; ?>"><?php echo $dias !== null ? number_format($dias, 2) : ''; ?></td>
                    <?php elseif (str_starts_with($c, 'fecha')):
                        $atr = ($c === 'fecha_compra_prog' && $it->compraAtrasada()) || ($c === 'fecha_entrega_prog' && $it->entregaAtrasada()); ?>
                        <td class="<?php echo $atr ? 'msr-atraso' : ''; ?>" data-celda="<?php echo $c; ?>"><?php echo $celdaFecha($it, $c); ?></td>
                    <?php elseif (str_starts_with($c, 'txt_')): ?>
                        <td><?php echo $puedeEditar ? '<input type="text" class="msr-input msr-texto" data-id="' . (int)$it->id . '" data-campo="' . s($c) . '" value="' . s($it->extra($c)) . '">' : s($it->extra($c)); ?></td>
                    <?php else: ?>
                        <td class="<?php echo trim(($c === 'proveedor' ? 'msr-prov ' : '') . $hm); ?>"><?php echo $celdaTexto($it, $c); ?></td>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($puedeEditar): ?>
<dialog id="msr-col-dlg" class="no-print">
    <form method="dialog" id="msr-col-form">
        <h3>Columna</h3>
        <label for="msr-col-n">Título</label>
        <input type="text" id="msr-col-n" maxlength="60" autocomplete="off">
        <label for="msr-col-g">Grupo (barra superior; vacío = sin barra)</label>
        <input type="text" id="msr-col-g" maxlength="60" autocomplete="off" list="msr-grupos-lista" placeholder="Elige un grupo o escribe uno nuevo">
        <datalist id="msr-grupos-lista"><?php foreach (array_unique(array_filter(array_column($columnas, 'g'))) as $gn): ?><option value="<?php echo s($gn); ?>"><?php endforeach; ?></datalist>
        <label for="msr-col-c">Color del encabezado</label>
        <input type="color" id="msr-col-c">
        <div class="acciones"><button type="button" class="btn btn-ghost btn-sm" id="msr-col-cancelar">Cancelar</button><button type="submit" class="btn btn-primary btn-sm">Guardar</button></div>
    </form>
</dialog>
<dialog id="msr-sec-dlg" class="no-print">
    <form method="dialog" id="msr-sec-form">
        <h3>Editar partida</h3>
        <p class="msr-sec-sub" id="msr-sec-sub"></p>
        <div class="msr-sec-prev" id="msr-sec-prev"></div>
        <label for="msr-sec-n">Título</label>
        <input type="text" id="msr-sec-n" maxlength="200" autocomplete="off" required>
        <label for="msr-sec-c">Color de la barra</label>
        <div class="msr-sec-colores" id="msr-sec-colores">
            <?php foreach (['#3F3F3F', '#17365D', '#0070C0', '#00B050', '#C0504D', '#E46C0A', '#7030A0', '#7F7F7F'] as $sw): ?><button type="button" class="msr-sw" data-c="<?php echo $sw; ?>" style="background:<?php echo $sw; ?>" aria-label="Color <?php echo $sw; ?>"></button><?php endforeach; ?>
            <input type="color" id="msr-sec-c" value="#3f3f3f">
            <button type="button" class="btn btn-ghost btn-sm" id="msr-sec-quitar">Sin color</button>
        </div>
        <p class="msr-sec-err" id="msr-sec-err" role="alert"></p>
        <div class="acciones"><button type="button" class="btn btn-ghost btn-sm" id="msr-sec-cancelar">Cancelar</button><button type="submit" class="btn btn-primary btn-sm">Guardar</button></div>
    </form>
</dialog>
<?php endif; ?>
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
            if (dias) { dias.textContent = d.dias === null ? '' : Number(d.dias).toFixed(2); dias.classList.toggle('msr-crit', d.critico); }
            const cc = tr.querySelector('[data-celda="fecha_compra_prog"]'), ce = tr.querySelector('[data-celda="fecha_entrega_prog"]');
            if (cc) cc.classList.toggle('msr-atraso', d.atrasoCompra);
            if (ce) ce.classList.toggle('msr-atraso', d.atrasoEntrega);
        } catch (_) { inp.classList.add('msr-error'); }
    });

    // Los títulos se congelan justo debajo de la barra de grupos (altura real de esa fila)
    const fijarCabecera = () => { const g = tabla.querySelector('.msr-grupo'); tabla.style.setProperty('--msr-grupo-h', (g ? g.getBoundingClientRect().height : 0) + 'px'); };
    fijarCabecera(); window.addEventListener('resize', fijarCabecera);
    // ---- Columnas: mover (flechas o arrastrando) y editar título/color (doble clic) ----
    const ths = () => [...tabla.querySelectorAll('thead th[data-col]')];
    const hayGrupos = <?php echo $grupos ? 'true' : 'false'; ?>;   // la barra de grupos depende del orden: se recarga para rehacerla
    const tinta = hex => { const h = hex.replace('#', ''); const l = (0.299 * parseInt(h.slice(0, 2), 16) + 0.587 * parseInt(h.slice(2, 4), 16) + 0.114 * parseInt(h.slice(4, 6), 16)) / 255; return l > 0.62 ? '#111111' : '#ffffff'; };
    const moverCeldas = (de, a) => {   // mueve la columna de..a en el encabezado y en cada renglón
        const o = tabla.querySelector('.msr-th-mv') ? 1 : 0;   // la columna de ▲▼ va a la izquierda y no se mueve
        tabla.querySelectorAll('tr').forEach(tr => {
            const cs = [...tr.children];
            if (cs.length < Math.max(de, a) + 1 + o || tr.classList.contains('msr-seccion') || tr.classList.contains('msr-grupo') || (tr.children.length === 1)) return;
            const [c] = cs.splice(de + o, 1); cs.splice(a + o, 0, c); tr.replaceChildren(...cs);
        });
    };
    const guardarOrden = async (de, a) => {
        const fila = ths(); const orden = fila.map(t => t.dataset.col); const [k] = orden.splice(de, 1); orden.splice(a, 0, k);
        const d = await post(tabla.dataset.ordenUrl, { proyecto: tabla.dataset.proyecto, id: tabla.dataset.msr, orden: orden.join(',') });
        if (!d.success) return;
        if (hayGrupos) { location.reload(); return; }
        moverCeldas(de, a);
    };
    tabla.querySelector('thead').addEventListener('click', e => {
        const b = e.target.closest('.msr-mv'); if (!b) return;
        const th = b.closest('th'), i = ths().indexOf(th), j = i + (+b.dataset.dir);
        if (j >= 0 && j < ths().length) guardarOrden(i, j).catch(() => {});
    });
    let arrastrada = null;
    tabla.querySelector('thead').addEventListener('dragstart', e => { const th = e.target.closest('th[data-col]'); if (!th) return; arrastrada = th; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', th.dataset.col); });
    tabla.querySelector('thead').addEventListener('dragover', e => { if (arrastrada && e.target.closest('th[data-col]')) e.preventDefault(); });
    tabla.querySelector('thead').addEventListener('drop', e => {
        const dest = e.target.closest('th[data-col]');
        if (!arrastrada || !dest || dest === arrastrada) return;
        e.preventDefault();
        const de = ths().indexOf(arrastrada), a = ths().indexOf(dest); arrastrada = null;
        guardarOrden(de, a).catch(() => {});
    });
    const dlg = document.getElementById('msr-col-dlg');
    if (dlg) {
        let editando = null;
        tabla.querySelector('thead').addEventListener('dblclick', e => {
            const th = e.target.closest('th[data-col]'); if (!th || e.target.closest('.msr-mv')) return;
            editando = th;
            document.getElementById('msr-col-n').value = th.querySelector('.msr-th-t').textContent.trim();
            document.getElementById('msr-col-g').value = th.dataset.g || '';
            const rgb = getComputedStyle(th).backgroundColor.match(/\d+/g) || [30, 58, 95];
            document.getElementById('msr-col-c').value = '#' + rgb.slice(0, 3).map(n => (+n).toString(16).padStart(2, '0')).join('');
            dlg.showModal(); document.getElementById('msr-col-n').select();
        });
        document.getElementById('msr-col-cancelar').addEventListener('click', () => dlg.close());
        document.getElementById('msr-col-form').addEventListener('submit', async e => {
            e.preventDefault();
            if (!editando) return;
            const th = editando, n = document.getElementById('msr-col-n').value.trim(), c = document.getElementById('msr-col-c').value;
            try {
                const d = await post('/api/msr/columna', { proyecto: tabla.dataset.proyecto, id: tabla.dataset.msr, k: th.dataset.col, n, c, g: document.getElementById('msr-col-g').value.trim() });
                if (!d.success) return;
                th.querySelector('.msr-th-t').textContent = d.n; th.style.background = d.c; th.style.color = tinta(d.c);
                const g = document.getElementById('msr-col-g').value.trim();
                if (hayGrupos || g !== (th.dataset.g || '')) { location.reload(); return; }
            } catch (_) {}
            dlg.close();
        });
    }
    // ---- Subir/bajar un material dentro de su partida ----
    tabla.addEventListener('click', async e => {
        const b = e.target.closest('.msr-ord'); if (!b) return;
        const tr = b.closest('tr'), dir = +b.dataset.dir;
        let vec = dir < 0 ? tr.previousElementSibling : tr.nextElementSibling;
        while (vec && vec.dataset.id && vec.style.display === 'none') vec = dir < 0 ? vec.previousElementSibling : vec.nextElementSibling;
        if (!vec || !vec.dataset.id) return;   // tope de la partida
        b.disabled = true;
        try {
            const d = await post('/api/msr/mover', { proyecto: tabla.dataset.proyecto, id: tabla.dataset.msr, item: tr.dataset.id, con: vec.dataset.id });
            if (d.success) { if (dir < 0) vec.before(tr); else vec.after(tr); tr.scrollIntoView({ block: 'nearest' }); }
        } catch (_) {}
        b.disabled = false;
    });
    // ---- Partida: título y color (doble clic en su barra) ----
    const dlgS = document.getElementById('msr-sec-dlg');
    if (dlgS) {
        let barra = null, color = '';
        const $ = id => document.getElementById(id);
        const base = '#3f3f3f';
        const pintar = () => {   // vista previa en vivo + swatch activo
            const c = color || base;
            $('msr-sec-prev').textContent = $('msr-sec-n').value.trim() || 'Título de la partida';
            $('msr-sec-prev').style.background = c; $('msr-sec-prev').style.color = tinta(c);
            $('msr-sec-c').value = c;
            dlgS.querySelectorAll('.msr-sw').forEach(b => b.classList.toggle('on', b.dataset.c.toLowerCase() === color.toLowerCase()));
        };
        tabla.addEventListener('dblclick', e => {
            const tr = e.target.closest('tr.msr-seccion'); if (!tr) return;
            barra = tr; color = tr.dataset.color || '';
            const n = (() => { let c = 0, x = tr.nextElementSibling; while (x && x.dataset.id) { c++; x = x.nextElementSibling; } return c; })();
            $('msr-sec-sub').textContent = n + (n === 1 ? ' material en esta partida' : ' materiales en esta partida');
            $('msr-sec-n').value = tr.dataset.sec; $('msr-sec-err').textContent = '';
            pintar(); dlgS.showModal(); $('msr-sec-n').select();
        });
        $('msr-sec-n').addEventListener('input', pintar);
        $('msr-sec-c').addEventListener('input', e => { color = e.target.value; pintar(); });
        $('msr-sec-colores').addEventListener('click', e => { const b = e.target.closest('.msr-sw'); if (b) { color = b.dataset.c; pintar(); } });
        $('msr-sec-quitar').addEventListener('click', () => { color = ''; pintar(); });
        $('msr-sec-cancelar').addEventListener('click', () => dlgS.close());
        dlgS.addEventListener('click', e => { if (e.target === dlgS) dlgS.close(); });   // clic fuera
        $('msr-sec-form').addEventListener('submit', async e => {
            e.preventDefault();
            if (!barra) return;
            const sb = e.submitter; if (sb) sb.disabled = true;
            try {
                const d = await post('/api/msr/partida', { proyecto: tabla.dataset.proyecto, id: tabla.dataset.msr, viejo: barra.dataset.sec, titulo: $('msr-sec-n').value, color });
                if (!d.success) { $('msr-sec-err').textContent = d.error || 'No se pudo guardar'; return; }
                barra.dataset.sec = d.titulo; barra.dataset.color = d.color;
                const td = barra.firstElementChild; td.textContent = d.titulo;
                td.style.background = d.color; td.style.color = d.color ? tinta(d.color) : '';
                dlgS.close();
            } catch (_) { $('msr-sec-err').textContent = 'Error de conexión'; }
            finally { if (sb) sb.disabled = false; }
        });
    }
})();
</script>
