<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-inbox"></i> Bandeja de entrada</h2>
        <p class="text-muted">Mensajes de contacto de la página principal y de soporte de tus clientes · <strong><?php echo (int)$sinLeer; ?></strong> sin leer</p>
    </div>
</div>

<div class="msg-tabs">
    <a href="/bandeja" class="<?php echo $filtro === 'todos' ? 'activo' : ''; ?>">Todos</a>
    <a href="/bandeja?tipo=contacto" class="<?php echo $filtro === 'contacto' ? 'activo' : ''; ?>"><i class="fa-solid fa-envelope"></i> Contacto</a>
    <a href="/bandeja?tipo=soporte" class="<?php echo $filtro === 'soporte' ? 'activo' : ''; ?>"><i class="fa-solid fa-headset"></i> Soporte</a>
</div>

<div class="card">
    <table class="tabla">
        <thead><tr><th>Tipo</th><th>De</th><th class="hide-m">Asunto</th><th class="hide-m">Fecha</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): $url = '/bandeja/ver?tipo=' . $it['tipo'] . '&id=' . (int)$it['id']; ?>
            <tr class="msg-fila <?php echo $it['leido'] ? '' : 'sin-leer'; ?>" data-tipo="<?php echo s($it['tipo']); ?>" data-id="<?php echo (int)$it['id']; ?>">
                <td><span class="badge <?php echo $it['tipo'] === 'contacto' ? 'badge-admin' : 'badge-cliente'; ?>"><?php echo $it['tipo'] === 'contacto' ? 'Contacto' : 'Soporte'; ?></span></td>
                <td><?php echo s($it['de']); ?></td>
                <td class="hide-m"><?php echo s($it['asunto']); ?></td>
                <td class="mono hide-m"><?php echo s(substr($it['fecha'], 0, 16)); ?></td>
                <td class="acciones">
                    <button type="button" class="btn btn-sm btn-ghost" data-abrir><i class="fa-regular fa-envelope-open"></i> Abrir</button>
                    <?php if ($it['tipo'] === 'soporte'): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-responder><i class="fa-solid fa-reply"></i> Responder<?php echo !empty($it['respuestas']) ? ' (' . count($it['respuestas']) . ')' : ''; ?></button>
                    <?php elseif ($it['correo']): ?>
                        <a class="btn btn-sm btn-primary" href="mailto:<?php echo s($it['correo']); ?>?subject=<?php echo rawurlencode('Re: Tu mensaje a ProjectWeb'); ?>"><i class="fa-solid fa-reply"></i> Responder</a>
                    <?php endif; ?>
                    <form method="POST" action="/bandeja/accion" style="display:inline" data-confirm="¿Eliminar mensaje?|Esta acción no se puede deshacer.">
                        <input type="hidden" name="tipo" value="<?php echo s($it['tipo']); ?>"><input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                        <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="volver" value="<?php echo s($filtro); ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="5" class="text-muted">No hay mensajes todavía.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal: ver el mensaje -->
<div class="modal-overlay" id="bj-ver" aria-hidden="true">
    <div class="modal-card bj-card" role="dialog" aria-modal="true" aria-labelledby="bj-ver-titulo">
        <div class="bj-cab"><h3 id="bj-ver-titulo">Mensaje</h3><button type="button" class="btn btn-sm btn-ghost" data-cerrar aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>
        <p class="text-muted text-sm" id="bj-ver-fecha"></p>
        <dl class="msg-meta" id="bj-ver-meta"></dl>
        <h4 id="bj-ver-cuerpo-titulo" class="card-titulo"></h4>
        <div class="msg-cuerpo" id="bj-ver-cuerpo"></div>
        <div class="modal-acciones bj-pie">
            <span id="bj-ver-responder"></span>
            <button type="button" class="btn btn-ghost" data-cerrar><i class="fa-solid fa-xmark"></i> Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal: responder (alta, edición y baja de respuestas) -->
<div class="modal-overlay" id="bj-resp" aria-hidden="true">
    <div class="modal-card bj-card" role="dialog" aria-modal="true" aria-labelledby="bj-resp-titulo">
        <div class="bj-cab"><h3 id="bj-resp-titulo">Responder</h3><button type="button" class="btn btn-sm btn-ghost" data-cerrar aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>
        <p class="text-muted text-sm" id="bj-resp-para"></p>
        <form id="bj-resp-form" class="formulario" enctype="multipart/form-data" novalidate>
            <p class="text-sm">Fecha: <strong id="bj-resp-fecha"></strong> <span class="text-muted" id="bj-resp-auto">(se pone automáticamente)</span></p>
            <div class="campo"><label for="bj-resp-titulo-in">Título *</label><input type="text" id="bj-resp-titulo-in" maxlength="200" required></div>
            <div class="campo"><label for="bj-resp-desc">Descripción *</label><textarea id="bj-resp-desc" rows="5" maxlength="5000" required></textarea></div>
            <div class="campo">
                <label>Adjunto (fotos, hasta 5)</label>
                <div class="bj-zona" id="bj-resp-zona" tabindex="0" role="button" aria-label="Seleccionar fotos">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span><strong>Seleccionar fotos</strong> o arrástralas aquí</span>
                    <small class="text-muted" id="bj-resp-contador">jpg, png, webp o gif · máx. 8 MB cada una</small>
                </div>
                <input type="file" id="bj-resp-fotos" accept="image/*" multiple hidden>
                <div class="bj-fotos-actuales" id="bj-resp-previas"></div>
            </div>
            <p class="bj-error text-sm" id="bj-resp-error" hidden></p>
            <div class="modal-acciones">
                <button type="button" class="btn btn-ghost" id="bj-resp-cancelar"><i class="fa-solid fa-xmark"></i> Cancelar</button>
                <button type="submit" class="btn btn-primary" id="bj-resp-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
            </div>
        </form>
        <h4 class="card-titulo bj-hist-titulo">Respuestas enviadas</h4>
        <p class="text-muted text-sm" id="bj-resp-vacio" hidden>Aún no has respondido este mensaje.</p>
        <ul class="bj-respuestas" id="bj-resp-lista"></ul>
    </div>
</div>

<script>
(function () {
    const items = <?php echo json_encode(array_map(fn($i) => $i + ['respuestas' => $i['respuestas'] ?? []], $items), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const porClave = new Map(items.map(i => [i.tipo + ':' + i.id, i]));
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const $ = id => document.getElementById(id);
    const post = (url, datos) => fetch(url, { method: 'POST', body: datos instanceof FormData ? datos : new URLSearchParams(datos) }).then(r => r.json());
    const abrirM = m => { m.classList.add('abierto'); m.setAttribute('aria-hidden', 'false'); };
    const cerrarM = m => { m.classList.remove('abierto'); m.setAttribute('aria-hidden', 'true'); };
    const verM = $('bj-ver'), respM = $('bj-resp');
    let actual = null;

    // ---- Ver mensaje ----
    const abrirMensaje = async it => {
        actual = it;
        $('bj-ver-titulo').textContent = it.titulo;
        $('bj-ver-fecha').textContent = 'Recibido el ' + it.fecha.slice(8, 10) + '/' + it.fecha.slice(5, 7) + '/' + it.fecha.slice(0, 4) + ' ' + it.fecha.slice(11, 16);
        $('bj-ver-meta').innerHTML = Object.entries(it.meta).map(([k, v]) => '<div><dt>' + esc(k) + '</dt><dd>' + esc(v) + '</dd></div>').join('');
        $('bj-ver-cuerpo-titulo').textContent = it.cuerpoTitulo;
        $('bj-ver-cuerpo').textContent = it.cuerpo;
        $('bj-ver-responder').innerHTML = it.tipo === 'soporte'
            ? '<button type="button" class="btn btn-primary" data-responder-desde-ver><i class="fa-solid fa-reply"></i> Responder</button>'
            : (it.correo ? '<a class="btn btn-primary" href="mailto:' + esc(it.correo) + '?subject=' + encodeURIComponent('Re: Tu mensaje a ProjectWeb') + '"><i class="fa-solid fa-reply"></i> Responder</a>' : '');
        abrirM(verM);
        if (!it.leido) {   // al abrirlo queda como leído
            try {
                const d = await post('/api/bandeja/leer', { tipo: it.tipo, id: it.id });
                if (d.success) { it.leido = true; document.querySelector('tr[data-tipo="' + it.tipo + '"][data-id="' + it.id + '"]')?.classList.remove('sin-leer'); }
            } catch (_) {}
        }
    };

    // ---- Responder ----
    let editando = null, quitar = new Set();
    const fotosHtml = r => r.fotos.map(f => '<a href="' + esc(f.url) + '" target="_blank" rel="noopener"><img src="' + esc(f.url) + '" alt="" class="bj-foto"></a>').join('');
    const pintarRespuestas = () => {
        const lista = actual.respuestas;
        $('bj-resp-vacio').hidden = lista.length > 0;
        $('bj-resp-lista').innerHTML = lista.map(r =>
            '<li class="bj-respuesta" data-id="' + r.id + '"><div class="bj-respuesta-cab"><strong>' + esc(r.titulo) + '</strong><span class="mono text-sm text-muted">' + esc(r.fecha) + '</span></div>' +
            '<p class="msg-cuerpo">' + esc(r.descripcion) + '</p><div class="bj-fotos">' + fotosHtml(r) + '</div>' +
            '<div class="bj-respuesta-acc"><button type="button" class="btn btn-sm btn-ghost" data-resp-editar><i class="fa-solid fa-pen"></i> Editar</button>' +
            '<button type="button" class="btn btn-sm btn-danger" data-resp-borrar><i class="fa-solid fa-trash-can"></i> Eliminar</button></div></li>').join('');
        const fila = document.querySelector('tr[data-tipo="soporte"][data-id="' + actual.id + '"] [data-responder]');
        if (fila) fila.innerHTML = '<i class="fa-solid fa-reply"></i> Responder' + (lista.length ? ' (' + lista.length + ')' : '');
    };
    // Fotos del formulario: las que ya tiene la respuesta (editando) y las nuevas elegidas, con vista previa
    const MAX_FOTOS = 5;
    let nuevas = [];   // [{ file, url }]
    const soltarNuevas = () => { nuevas.forEach(n => URL.revokeObjectURL(n.url)); nuevas = []; };
    const existentes = () => editando ? editando.fotos.filter(f => !quitar.has(f.id)) : [];
    const pintarActuales = () => {
        const ex = existentes();
        $('bj-resp-previas').innerHTML =
            ex.map(f => '<span class="bj-foto-quitable"><img src="' + esc(f.url) + '" alt="" class="bj-foto" data-visor="' + esc(f.url) + '"><button type="button" data-quitar-foto="' + f.id + '" aria-label="Quitar foto">✕</button></span>').join('') +
            nuevas.map((n, i) => '<span class="bj-foto-quitable"><img src="' + esc(n.url) + '" alt="" class="bj-foto" data-visor="' + esc(n.url) + '" title="' + esc(n.file.name) + '"><button type="button" data-quitar-nueva="' + i + '" aria-label="Quitar foto">✕</button></span>').join('');
        $('bj-resp-contador').textContent = (ex.length + nuevas.length) + ' de ' + MAX_FOTOS + ' fotos · jpg, png, webp o gif · máx. 8 MB cada una';
    };
    const agregarArchivos = lista => {
        const err = $('bj-resp-error');
        err.hidden = true;
        for (const f of lista) {
            if (!f.type.startsWith('image/')) { err.textContent = '«' + f.name + '» no es una imagen'; err.hidden = false; continue; }
            if (f.size > 8 * 1024 * 1024) { err.textContent = '«' + f.name + '» pesa más de 8 MB'; err.hidden = false; continue; }
            if (existentes().length + nuevas.length >= MAX_FOTOS) { err.textContent = 'Máximo ' + MAX_FOTOS + ' fotos por respuesta'; err.hidden = false; break; }
            if (nuevas.some(n => n.file.name === f.name && n.file.size === f.size && n.file.lastModified === f.lastModified)) continue;
            nuevas.push({ file: f, url: URL.createObjectURL(f) });
        }
        pintarActuales();
    };
    const prepararFormulario = r => {
        editando = r || null; quitar = new Set(); soltarNuevas();
        const hoy = new Date(), p = n => String(n).padStart(2, '0');
        $('bj-resp-fecha').textContent = r ? r.fecha : p(hoy.getDate()) + '/' + p(hoy.getMonth() + 1) + '/' + hoy.getFullYear();
        $('bj-resp-auto').hidden = !!r;
        $('bj-resp-titulo-in').value = r ? r.titulo : '';
        $('bj-resp-desc').value = r ? r.descripcion : '';
        $('bj-resp-error').hidden = true;
        $('bj-resp-guardar').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ' + (r ? 'Guardar cambios' : 'Guardar');
        pintarActuales();
    };
    const abrirRespuesta = it => {
        actual = it;
        $('bj-resp-para').textContent = 'Para: ' + it.meta['Usuario'] + ' · Tema: ' + it.asunto;
        prepararFormulario(null);
        pintarRespuestas();
        cerrarM(verM);
        abrirM(respM);
        $('bj-resp-titulo-in').focus();
    };

    $('bj-resp-previas').addEventListener('click', e => {
        const b = e.target.closest('[data-quitar-foto]'), n = e.target.closest('[data-quitar-nueva]');
        if (b) { quitar.add(+b.dataset.quitarFoto); pintarActuales(); }
        if (n) { const [q] = nuevas.splice(+n.dataset.quitarNueva, 1); URL.revokeObjectURL(q.url); $('bj-resp-error').hidden = true; pintarActuales(); }
    });
    // Zona de selección: clic, teclado o arrastrar y soltar
    const zona = $('bj-resp-zona'), input = $('bj-resp-fotos');
    zona.addEventListener('click', () => input.click());
    zona.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', () => { agregarArchivos(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach(ev => zona.addEventListener(ev, e => { e.preventDefault(); zona.classList.add('arrastrando'); }));
    ['dragleave', 'drop'].forEach(ev => zona.addEventListener(ev, e => { e.preventDefault(); zona.classList.remove('arrastrando'); }));
    zona.addEventListener('drop', e => agregarArchivos(e.dataTransfer.files));
    $('bj-resp-cancelar').addEventListener('click', () => { prepararFormulario(null); cerrarM(respM); });
    $('bj-resp-form').addEventListener('submit', async e => {
        e.preventDefault();
        const err = $('bj-resp-error'), btn = $('bj-resp-guardar');
        const titulo = $('bj-resp-titulo-in').value.trim(), desc = $('bj-resp-desc').value.trim();
        if (!titulo || !desc) { err.textContent = 'El título y la descripción son obligatorios'; err.hidden = false; return; }
        const fd = new FormData();
        fd.append('soporte_id', actual.id); fd.append('titulo', titulo); fd.append('descripcion', desc);
        if (editando) fd.append('id', editando.id);
        quitar.forEach(id => fd.append('quitar_fotos[]', id));
        nuevas.forEach(n => fd.append('fotos[]', n.file));
        btn.disabled = true;
        try {
            const d = await post('/api/bandeja/respuesta/guardar', fd);
            if (!d.success) { err.textContent = d.error || 'No se pudo guardar'; err.hidden = false; return; }
            const i = actual.respuestas.findIndex(r => r.id === d.respuesta.id);
            if (i >= 0) actual.respuestas[i] = d.respuesta; else actual.respuestas.push(d.respuesta);
            actual.leido = true;
            document.querySelector('tr[data-tipo="soporte"][data-id="' + actual.id + '"]')?.classList.remove('sin-leer');
            prepararFormulario(null);
            pintarRespuestas();
        } catch (_) { err.textContent = 'Error de conexión'; err.hidden = false; }
        finally { btn.disabled = false; }
    });
    $('bj-resp-lista').addEventListener('click', async e => {
        const li = e.target.closest('.bj-respuesta');
        if (!li) return;
        const r = actual.respuestas.find(x => x.id === +li.dataset.id);
        if (e.target.closest('[data-resp-editar]')) {
            prepararFormulario(r);
            $('bj-resp-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
            $('bj-resp-titulo-in').focus();
        } else if (e.target.closest('[data-resp-borrar]')) {
            const ok = await window.confirmarModal({ titulo: '¿Eliminar respuesta?', mensaje: 'El cliente dejará de verla. Esta acción no se puede deshacer.', aceptar: 'Sí, eliminar', iconoBoton: 'fa-trash-can' });
            if (!ok) return;
            const d = await post('/api/bandeja/respuesta/eliminar', { id: r.id });
            if (d.success) { actual.respuestas = actual.respuestas.filter(x => x !== r); if (editando && editando.id === r.id) prepararFormulario(null); pintarRespuestas(); }
        }
    });

    // ---- Disparadores de la tabla y cierre de modales ----
    document.querySelector('.tabla tbody').addEventListener('click', e => {
        const tr = e.target.closest('tr.msg-fila');
        if (!tr || e.target.closest('form, a')) return;
        const it = porClave.get(tr.dataset.tipo + ':' + tr.dataset.id);
        if (!it) return;
        if (e.target.closest('[data-responder]')) abrirRespuesta(it); else abrirMensaje(it);   // fila o «Abrir»
    });
    verM.addEventListener('click', e => { if (e.target.closest('[data-responder-desde-ver]')) abrirRespuesta(actual); });
    [verM, respM].forEach(m => {
        m.addEventListener('click', e => { if (e.target === m || e.target.closest('[data-cerrar]')) cerrarM(m); });
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') [verM, respM].forEach(m => { if (m.classList.contains('abierto') && !document.getElementById('modal-confirm').classList.contains('abierto')) cerrarM(m); }); });
})();
</script>
