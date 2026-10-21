<div class="page-header">
    <h2><i class="fa-solid fa-table-cells"></i> Nueva tabla</h2>
    <a href="/sheetflow?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
    <form method="POST" action="/sheetflow/crear" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <div class="form-grid sf-datos">
            <div class="campo sf-d-nombre"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($hoja->nombre); ?>" placeholder="Ej: Control de materiales · Cimentación" required autofocus></div>
            <div class="campo sf-d-fecha"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($hoja->fecha); ?>" required></div>
            <div class="campo sf-d-depto">
                <label for="departamento">Departamento</label>
                <input type="text" id="departamento" name="departamento" maxlength="100" list="sf-deptos" value="<?php echo s($hoja->departamento); ?>" placeholder="Ej: Compras" autocomplete="off">
                <datalist id="sf-deptos"><?php foreach ($departamentos as $dp): ?><option value="<?php echo s($dp); ?>"><?php endforeach; ?></datalist>
                            </div>
            <div class="campo sf-d-desc"><label for="descripcion">Descripción</label><input type="text" id="descripcion" name="descripcion" maxlength="300" value="<?php echo s($hoja->descripcion); ?>" placeholder="Opcional: para qué sirve la tabla"></div>
        </div>
        <h3 class="mt-2">¿Cómo quieres empezar?</h3>
        <div class="rd-plantillas">
            <label class="rd-plantilla"><input type="radio" name="plantilla" value="" checked><span><i class="fa-regular fa-file"></i><strong>En blanco</strong><small>4 columnas × 10 renglones</small></span></label>
            <label class="rd-plantilla"><input type="radio" name="plantilla" value="inteligente"><span><i class="fa-solid fa-wand-magic-sparkles"></i><strong>Tabla inteligente</strong><small>Define columnas con tipo y color; se llena desde vPlan</small></span></label>
            <?php foreach ($plantillas as $k => [$n, $desc, $ico]): ?>
                <label class="rd-plantilla"><input type="radio" name="plantilla" value="<?php echo s($k); ?>"><span><i class="fa-solid <?php echo s($ico); ?>"></i><strong><?php echo s($n); ?></strong><small><?php echo s($desc); ?></small></span></label>
            <?php endforeach; ?>
        </div>
        <div class="campo campo-full mt-2 sf-int" id="sf-inteligente" hidden>
            <div class="sf-int-tit"><strong>Diseña las columnas</strong><span class="text-sm text-muted">Se llenan desde vPlan y se pueden usar en el Editor y el Redactor.</span></div>
            <div class="sf-int-previa" id="sf-int-previa" aria-label="Vista previa del encabezado"></div>
            <div class="sf-int-cab"><span>#</span><span>Nombre</span><span>Tipo</span><span>Color</span><span></span></div>
            <div id="sf-int-cols" class="sf-int-cols"></div>
            <div class="sf-int-pie">
                <div class="sf-int-rapido"><span class="text-sm text-muted">Agregar:</span><span id="sf-int-rapido"></span></div>
                <label class="text-sm">Renglones iniciales <input type="number" name="int_filas" id="sf-int-filas" min="1" max="200" value="10" style="width:7rem"></label>
            </div>
            <input type="hidden" name="int_cols" id="sf-int-json">
        </div>
        <div class="campo campo-full mt-2" id="sf-bloque-archivo">
            <label for="archivo">…o importa un archivo (.sheetflow, .xlsx, .ods o .csv)</label>
            <input type="file" id="archivo" name="archivo" accept=".sheetflow,.xlsx,.ods,.csv,text/csv">
            <small class="text-muted">Se usa la primera hoja; la primera fila son los encabezados. Si eliges un archivo, se ignora la plantilla. Máx. 25 MB, 30 columnas y 1 000 renglones.</small>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-table-cells"></i> Crear y abrir</button>
    </form>
</div>
<script>
(function () {
    const TIPOS = <?php echo json_encode(\Classes\Sheetflow::TIPOS, JSON_UNESCAPED_UNICODE); ?>;
    const caja = document.getElementById('sf-int-cols'), json = document.getElementById('sf-int-json'), panel = document.getElementById('sf-inteligente');
    const esc = t => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const ICO = { texto: 'font', numero: 'hashtag', moneda: 'dollar-sign', porcentaje: 'percent', fecha: 'calendar-day', lista: 'list', casilla: 'square-check', semaforo: 'traffic-light' };
    const previa = document.getElementById('sf-int-previa'), rapido = document.getElementById('sf-int-rapido');
    const cols = [{ n: 'Concepto', t: 'texto', op: '', bg: '#334155' }, { n: 'Fecha', t: 'fecha', op: '', bg: '#334155' }, { n: 'Estado', t: 'semaforo', op: '', bg: '#334155' }];
    rapido.innerHTML = Object.keys(TIPOS).map(k => '<button type="button" class="btn btn-sm btn-ghost" data-a="' + k + '"><i class="fa-solid fa-' + ICO[k] + '"></i> ' + esc(TIPOS[k]) + '</button>').join('');
    rapido.addEventListener('click', e => {
        const b = e.target.closest('button'); if (!b || cols.length >= 30) return;
        cols.push({ n: TIPOS[b.dataset.a], t: b.dataset.a, op: b.dataset.a === 'lista' ? 'Sí, No' : '', bg: cols.length ? cols[cols.length - 1].bg : '#334155' });
        pintar(); const u = caja.querySelectorAll('.sf-n'); u[u.length - 1].focus(); u[u.length - 1].select();
    });
    function pintar() {
        caja.innerHTML = cols.map((c, i) => '<div class="sf-int-col" data-i="' + i + '">' +
            '<span class="sf-int-num">' + (i + 1) + '</span>' +
            '<input type="text" class="sf-n" maxlength="60" placeholder="Nombre" value="' + esc(c.n) + '">' +
            '<select class="sf-t">' + Object.keys(TIPOS).map(k => '<option value="' + k + '"' + (k === c.t ? ' selected' : '') + '>' + esc(TIPOS[k]) + '</option>').join('') + '</select>' +
            '<input type="color" class="sf-c" title="Color del encabezado" value="' + esc(c.bg) + '">' +
            '<span class="sf-int-acc">' +
            '<button type="button" class="btn btn-sm btn-ghost" data-m="-1"' + (i ? '' : ' disabled') + ' title="Mover a la izquierda"><i class="fa-solid fa-arrow-up"></i></button>' +
            '<button type="button" class="btn btn-sm btn-ghost" data-m="1"' + (i < cols.length - 1 ? '' : ' disabled') + ' title="Mover a la derecha"><i class="fa-solid fa-arrow-down"></i></button>' +
            '<button type="button" class="btn btn-sm btn-ghost" data-q="1" title="Quitar"' + (cols.length > 1 ? '' : ' disabled') + '><i class="fa-solid fa-trash-can"></i></button></span>' +
            (c.t === 'lista' ? '<input type="text" class="sf-o" placeholder="Opciones separadas por comas: Pendiente, En curso, Listo" value="' + esc(c.op) + '">' : '') +
            '</div>').join('');
        guardar();
    }
    function guardar() {
        json.value = JSON.stringify(cols.map(c => ({ n: c.n, t: c.t, bg: c.bg, op: c.t === 'lista' ? c.op.split(',').map(x => x.trim()).filter(Boolean) : [] })));
        previa.innerHTML = cols.map(c => '<span style="background:' + esc(c.bg) + '"><i class="fa-solid fa-' + ICO[c.t] + '"></i> ' + esc(c.n || 'Sin nombre') + '</span>').join('');
    }
    caja.addEventListener('input', e => {
        const f = e.target.closest('.sf-int-col'); if (!f) return;
        const c = cols[+f.dataset.i];
        if (e.target.classList.contains('sf-n')) c.n = e.target.value;
        else if (e.target.classList.contains('sf-o')) c.op = e.target.value;
        else if (e.target.classList.contains('sf-c')) c.bg = e.target.value;
        else if (e.target.classList.contains('sf-t')) { c.t = e.target.value; pintar(); return; }
        guardar();
    });
    caja.addEventListener('click', e => {
        const b = e.target.closest('button'); if (!b) return;
        const i = +b.closest('.sf-int-col').dataset.i;
        if (b.dataset.q) cols.splice(i, 1);
        else { const j = i + +b.dataset.m; [cols[i], cols[j]] = [cols[j], cols[i]]; }
        pintar();
    });
    document.querySelectorAll('input[name=plantilla]').forEach(r => r.addEventListener('change', () => { panel.hidden = document.querySelector('input[name=plantilla]:checked').value !== 'inteligente'; }));
    pintar();
})();
</script>
