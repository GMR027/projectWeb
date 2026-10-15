<?php $esNuevo = !$msr->id; ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo MSR' : 'Editar ' . s($msr->nombre); ?></h2>
    <a href="/msr?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/msr/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($msr->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($msr->nombre); ?>" required></div>
            <div class="campo"><label for="descripcion">Descripción</label><input type="text" id="descripcion" name="descripcion" maxlength="500" value="<?php echo s($msr->descripcion); ?>"></div>
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
        const quitar = el('button', { type: 'button', class: 'btn btn-sm btn-danger', title: 'Quitar partida', text: '✕' });
        const bloque = el('div', { class: 'card msr-partida', style: 'margin-top:1rem' }, [
            el('input', { type: 'hidden', name: `partidas[${i}][id]`, value: p.id }),
            el('div', { class: 'la-controles' }, [titulo, quitar]),
            el('div', { class: 'accesos' }, [el('div', { class: 'accesos-barra' }, [buscar, contador]), lista]),
        ]);
        quitar.addEventListener('click', () => bloque.remove());
        cont.append(bloque);
        actualizar();
        return titulo;
    };
    iniciales.forEach(agregar);
    document.getElementById('add-partida').addEventListener('click', () => agregar().focus());
})();
</script>
