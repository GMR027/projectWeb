<?php $esNuevo = !$msr->id; ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo MSR' : 'Editar ' . s($msr->nombre); ?></h2>
    <a href="/msr?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/msr/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <input type="hidden" name="modo" value="personalizado">
        <datalist id="grupos-lista"></datalist>
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($msr->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($msr->nombre); ?>" required></div>
            <div class="campo"><label for="descripcion">Descripción</label><input type="text" id="descripcion" name="descripcion" maxlength="500" value="<?php echo s($msr->descripcion); ?>"></div>
            <?php if (Model\Msr::tieneEncabezado()): ?>
            <div class="campo"><label for="encabezado_id">Encabezado del reporte</label>
                <select id="encabezado_id" name="encabezado_id">
                    <option value="0" <?php echo !(int)$msr->encabezado_id ? 'selected' : ''; ?>>Encabezado de siempre (sin estampa)<?php echo !$encabezados ? ' · aún no hay estampas de lienzo: crea una en Estampas inteligentes' : ''; ?></option>
                    <?php foreach ($encabezados as $enc): ?><option value="<?php echo (int)$enc->id; ?>" <?php echo (int)$msr->encabezado_id === (int)$enc->id ? 'selected' : ''; ?>><?php echo s($enc->nombre); ?></option><?php endforeach; ?>
                </select>
                <small class="text-muted">Es un cajetín hecho con estampas inteligentes (logos, título, fecha, cliente, proyecto, job, localización…). Créalo o edítalo en <a href="/simbolos?proyecto=<?php echo s($proyecto->id); ?>#estampas">Símbolos › Estampas inteligentes</a> con la plantilla «Encabezado de MSR».</small></div>
            <?php endif; ?>
        </div>

        <h3 class="mt-2">Columnas del reporte</h3>
        <div id="cols-editor">
            <small class="text-muted">Arma tu reporte: marca las columnas que quieres ver, ponles el nombre y el color que prefieras. Cada una funciona igual, solo cambia cómo se llama y cómo se ve. Empieza con las columnas de siempre. Arrástralas (☰) o usa las flechas para moverlas; en el reporte también puedes arrastrar los encabezados.</small>
            <ul id="cols-lista" class="msr-cols"></ul>
            <button type="button" id="add-col-texto" class="btn btn-ghost btn-sm">+ Agregar columna de texto</button>
        </div>

        <h3 class="mt-2">Partidas</h3>
        <small class="text-muted">Secciones propias del MSR (ej. «Material de instalación», «Documentos») con materiales del catálogo. Los renglones de tus paquetes de submittal se agregan aparte desde Submittals.</small>
        <div id="partidas"></div>
        <button type="button" id="add-partida" class="btn btn-ghost btn-sm" style="margin:1rem 0">+ Agregar partida</button>
        <br>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
<script>
(function () {
    const catalogo = <?php echo json_encode($catalogo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    const iniciales = <?php echo json_encode($partidas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    const cont = document.getElementById('partidas');
    let n = 0;
    const el = (tag, attrs = {}, hijos = []) => {
        const e = document.createElement(tag);
        Object.entries(attrs).forEach(([k, v]) => k === 'text' ? e.textContent = v : e.setAttribute(k, v));
        hijos.forEach(h => e.append(h));
        return e;
    };
    const agregar = (p = { id: 0, titulo: '', materiales: [] }) => {
        const i = n++;
        const marcados = new Set(p.materiales);
        const contador = el('span', { class: 'text-muted text-sm' });
        const titulo = el('input', { type: 'text', name: `partidas[${i}][titulo]`, maxlength: 150, placeholder: 'Título de la partida (ej. Material de instalación)', value: p.titulo });
        const buscar = el('input', { type: 'search', placeholder: 'Buscar material del catálogo…', autocomplete: 'off' });
        const lista = el('div', { class: 'accesos-lista' });
        const items = [...catalogo].sort((a, b) => marcados.has(b.id) - marcados.has(a.id)).map(m => {
            const cb = el('input', { type: 'checkbox', name: `partidas[${i}][materiales][]`, value: m.id });
            cb.checked = marcados.has(m.id);
            const foto = m.img ? el('img', { class: 'mat-thumb', src: '/uploads/materiales/' + m.img, alt: '', loading: 'lazy' }) : el('span', { class: 'mat-thumb mat-thumb-vacio' }, [el('i', { class: 'fa-regular fa-image' })]);
            const lbl = el('label', { class: 'acceso-item' }, [cb, foto, document.createTextNode(' '), el('span', { class: 'mono', text: m.ref || '' }), document.createTextNode(' ' + m.nombre + (m.esp ? ' · ' + m.esp : ''))]);
            lbl.dataset.texto = (m.nombre + ' ' + m.ref + ' ' + m.esp).toLowerCase();
            lista.append(lbl);
            return lbl;
        });
        if (!catalogo.length) lista.append(el('p', { class: 'text-muted text-sm', text: 'El catálogo de materiales está vacío.' }));
        const actualizar = () => contador.textContent = items.filter(l => l.querySelector('input').checked).length + ' material(es)';
        buscar.addEventListener('input', () => {
            const q = buscar.value.trim().toLowerCase();
            items.forEach(l => l.style.display = !q || l.dataset.texto.includes(q) ? '' : 'none');
        });
        lista.addEventListener('change', actualizar);
        const color = el('input', { type: 'color', name: `partidas[${i}][color]`, value: p.color || '#1e3a5f', title: 'Color del título de la partida' });
        const sinColor = el('input', { type: 'hidden', name: `partidas[${i}][color]`, value: '' });
        const usarColor = el('label', { class: 'text-sm', title: 'Si no lo marcas se usa el color de siempre' }, [(() => { const c = el('input', { type: 'checkbox' }); c.checked = !!p.color; c.addEventListener('change', () => { color.disabled = !c.checked; sinColor.disabled = c.checked; }); color.disabled = !c.checked; sinColor.disabled = c.checked; return c; })(), document.createTextNode(' Color ')]);
        const quitar = el('button', { type: 'button', class: 'btn btn-sm btn-danger', title: 'Quitar partida', text: '✕' });
        const bloque = el('div', { class: 'card msr-partida', style: 'margin-top:1rem' }, [
            el('input', { type: 'hidden', name: `partidas[${i}][id]`, value: p.id }),
            el('div', { class: 'la-controles' }, [titulo, usarColor, color, sinColor, quitar]),
            el('div', { class: 'accesos' }, [el('div', { class: 'accesos-barra' }, [buscar, contador]), lista]),
        ]);
        quitar.addEventListener('click', () => bloque.remove());
        cont.append(bloque);
        actualizar();
        return titulo;
    };
    iniciales.forEach(agregar);

    // ---- Columnas del reporte ----
    const columnas = <?php echo json_encode($columnasForm, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    const lista = document.getElementById('cols-lista'), editor = document.getElementById('cols-editor');
    let nTxt = Math.max(0, ...columnas.filter(c => c.texto).map(c => +c.k.slice(4) || 0));
    const listaGrupos = () => {
        const nombres = [...new Set([...lista.querySelectorAll('.col-grupo')].map(i => i.value.trim()).filter(Boolean))];
        document.getElementById('grupos-lista').replaceChildren(...nombres.map(n => { const o = document.createElement('option'); o.value = n; return o; }));
    };
    const renumerar = () => [...lista.children].forEach((li, i) => li.querySelectorAll('[data-f]').forEach(inp => { inp.name = `columnas[${i}][${inp.dataset.f}]`; inp.disabled = !li.querySelector('.col-on').checked; }));
    const fila = c => {
        const on = el('input', { type: 'checkbox', class: 'col-on' }); on.checked = c.activa;
        const k = el('input', { type: 'hidden', 'data-f': 'k', value: c.k });
        const n = el('input', { type: 'text', 'data-f': 'n', maxlength: 60, placeholder: c.generico, value: c.n || '', title: 'Nombre de la columna (vacío = ' + c.generico + ')' });
        const gr = el('input', { type: 'text', 'data-f': 'g', maxlength: 60, class: 'col-grupo', list: 'grupos-lista', placeholder: 'Grupo (barra superior)', value: c.g || '', title: 'Grupo: las columnas contiguas con el mismo grupo se unen bajo una barra con su nombre. Vacío = sin barra' });
        const col = el('input', { type: 'color', 'data-f': 'c', value: c.c || c.color, title: 'Color del encabezado' });
        const asa = el('span', { class: 'col-asa', title: 'Arrastra para mover', text: '☰' });
        const sube = el('button', { type: 'button', class: 'btn btn-sm btn-ghost', title: 'Mover antes', text: '↑' });
        const baja = el('button', { type: 'button', class: 'btn btn-sm btn-ghost', title: 'Mover después', text: '↓' });
        const o = c.origen || ['texto', 'Texto'];
        const insignia = el('span', { class: 'msr-origen msr-origen-' + o[0], title: o[0] === 'dato' ? 'Dato vinculado a otra herramienta (' + o[1] + '): se llena solo' : o[0] === 'calculo' ? 'Se calcula automáticamente' : 'Columna de texto: la escribes tú', text: o[0] === 'dato' ? '🔗 ' + o[1] : o[0] === 'calculo' ? 'ƒ Cálculo' : '✎ Texto' });
        const hijos = [asa, on, k, n, gr, insignia, col, sube, baja];
        if (c.texto) { const x = el('button', { type: 'button', class: 'btn btn-sm btn-danger', title: 'Quitar columna de texto', text: '✕' }); x.addEventListener('click', () => { li.remove(); renumerar(); }); hijos.push(x); }
        const li = el('li', { draggable: 'true' }, hijos);
        li.dataset.base = c.generico;
        on.addEventListener('change', renumerar);
        gr.addEventListener('change', () => {   // al elegir un grupo, la columna se junta con las de ese grupo
            const g = gr.value.trim();
            if (g) { const otras = [...lista.children].filter(x => x !== li && x.querySelector('.col-grupo').value.trim() === g); if (otras.length) otras[otras.length - 1].after(li); }
            renumerar(); listaGrupos();
        });
        sube.addEventListener('click', () => { if (li.previousElementSibling) li.previousElementSibling.before(li); renumerar(); });
        baja.addEventListener('click', () => { if (li.nextElementSibling) li.nextElementSibling.after(li); renumerar(); });
        li.addEventListener('dragstart', e => { li.classList.add('arrastrando'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', c.k); });
        li.addEventListener('dragend', () => { li.classList.remove('arrastrando'); renumerar(); });
        return li;
    };
    columnas.forEach(c => lista.append(fila(c)));
    lista.addEventListener('dragover', e => {
        e.preventDefault();
        const mov = lista.querySelector('.arrastrando'); if (!mov) return;
        const sig = [...lista.children].filter(x => x !== mov).find(x => e.clientY < x.getBoundingClientRect().top + x.offsetHeight / 2);
        sig ? sig.before(mov) : lista.append(mov);
    });
    document.getElementById('add-col-texto').addEventListener('click', () => {
        const li = fila({ k: 'txt_' + (++nTxt), n: '', c: '', generico: 'Texto', color: '#6b7280', activa: true, texto: true });
        lista.append(li); renumerar(); li.querySelector('[data-f="n"]').focus();
    });
    renumerar(); listaGrupos();
    document.getElementById('add-partida').addEventListener('click', () => agregar().focus());
})();
</script>
