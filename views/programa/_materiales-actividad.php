<?php
/**
 * Sección «Materiales» del formulario de actividad.
 * Variables: $catalogo [['id','nombre','ref','esp','img','estado']], $seleccion [material_id, …], $roles [material_id => instala|consume], $gestionarMateriales (bool)
 */
$seleccion = $seleccion ?? [];
$roles = $roles ?? [];
$catalogo = $catalogo ?? [];
?>
<div class="campo campo-full">
    <label class="campo-check"><input type="checkbox" id="sin_material" name="sin_material" value="1" <?php echo !empty($actividad->sin_material) ? 'checked' : ''; ?>> Actividad sin material</label>
    <small class="text-muted">Márcala si esta actividad no requiere material: dejará de aparecer en «Actividades sin materiales» de Acciones rápidas.</small>
</div>
<div class="campo campo-full">
    <label class="campo-check"><input type="checkbox" id="gestionar_materiales" name="gestionar_materiales" value="1" <?php echo !empty($gestionarMateriales) ? 'checked' : ''; ?>> Gestionar materiales</label>
    <small class="text-muted">Indica qué materiales del catálogo necesita esta actividad. Sirve para saber si puede ejecutarse (Planificador) y para la fecha de instalación del MSR.</small>
</div>
<div class="campo campo-full" id="seccion-materiales" <?php echo empty($gestionarMateriales) ? 'hidden' : ''; ?>>
    <h3>Materiales</h3>
    <div class="accesos msr-partida">
        <div class="accesos-barra">
            <input type="search" id="mat-buscar" placeholder="Buscar material del catálogo…" autocomplete="off">
            <span class="text-muted text-sm" id="mat-contador"></span>
        </div>
        <div class="accesos-lista" id="mat-lista"></div>
    </div>
    <small class="text-muted">La fecha de instalación (prog.) del MSR será la fecha de inicio más temprana entre las actividades que usan el material. <strong>Instala</strong>: la actividad coloca el material; <strong>Consume</strong>: solo lo usa o lo transforma.</small>
</div>
<script>
(function () {
    const catalogo = <?php echo json_encode($catalogo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    const sel = new Set(<?php echo json_encode(array_values($seleccion), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>);
    const roles = <?php echo json_encode((object)$roles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    const chk = document.getElementById('gestionar_materiales'), sec = document.getElementById('seccion-materiales');
    const lista = document.getElementById('mat-lista'), buscar = document.getElementById('mat-buscar'), contador = document.getElementById('mat-contador');
    const estados = { entregado: ['Entregado', '#10b981'], comprado: ['Comprado', '#f59e0b'], pendiente: ['Sin comprar', '#dc2626'] };
    const el = (tag, attrs = {}, hijos = []) => {
        const e = document.createElement(tag);
        Object.entries(attrs).forEach(([k, v]) => k === 'text' ? e.textContent = v : e.setAttribute(k, v));
        hijos.forEach(h => e.append(h));
        return e;
    };
    const filas = [...catalogo].sort((a, b) => sel.has(b.id) - sel.has(a.id)).map(m => {
        const marcado = sel.has(m.id);
        const cb = el('input', { type: 'checkbox', name: 'materiales[]', value: m.id });
        cb.checked = marcado;
        const foto = m.img ? el('img', { class: 'mat-thumb', src: '/uploads/materiales/' + m.img, alt: '', loading: 'lazy' }) : el('span', { class: 'mat-thumb mat-thumb-vacio' }, [el('i', { class: 'fa-regular fa-image' })]);
        const [txt, color] = estados[m.estado] || estados.pendiente;
        const badge = el('em', { text: txt, style: `font-style:normal;font-size:1.1rem;font-weight:700;color:${color}` });
        const rol = el('select', { name: 'roles[' + m.id + ']', class: 'mat-rol', 'aria-label': 'Rol de ' + m.nombre, title: 'Instala: la actividad coloca el material · Consume: solo lo usa' });
        [['instala', 'Instala'], ['consume', 'Consume']].forEach(([v, t]) => { const o = el('option', { value: v, text: t }); rol.append(o); });
        rol.value = roles[m.id] || 'instala';
        rol.disabled = !marcado;
        cb.addEventListener('change', () => { rol.disabled = !cb.checked; contar(); });
        const lbl = el('label', { class: 'acceso-item' }, [cb, foto, document.createTextNode(' '), el('span', { class: 'mono', text: m.ref || '' }), document.createTextNode(' ' + m.nombre + (m.cant ? ' (' + m.cant + ')' : '') + ' · '), badge, rol]);
        lbl.dataset.texto = (m.nombre + ' ' + m.ref + ' ' + m.esp).toLowerCase();
        lista.append(lbl);
        return lbl;
    });
    if (!catalogo.length) lista.append(el('p', { class: 'text-muted text-sm', text: 'El catálogo de materiales está vacío. Agrega materiales primero.' }));
    const contar = () => contador.textContent = filas.filter(l => l.querySelector('input[type=checkbox]').checked).length + ' seleccionado(s)';
    buscar.addEventListener('input', () => {
        const q = buscar.value.trim().toLowerCase();
        filas.forEach(l => l.style.display = !q || l.dataset.texto.includes(q) ? '' : 'none');
    });
    const sin = document.getElementById('sin_material');
    const alternar = () => {
        if (sin.checked) { chk.checked = false; sec.hidden = true; }
        chk.disabled = sin.checked;
    };
    chk.addEventListener('change', () => { sec.hidden = !chk.checked; });
    sin.addEventListener('change', alternar);
    alternar();
    contar();
})();
</script>
