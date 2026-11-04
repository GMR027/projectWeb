// Parte diario de campo: filas dinámicas, costo del día, fotos, firma y cola sin conexión.
(function () {
    'use strict';
    const form = document.getElementById('pd-form');
    if (!form) return;

    const datos = (() => { try { return JSON.parse(document.getElementById('pd-datos').textContent || '{}'); } catch (_) { return {}; } })();
    const $ = (s, r = form) => r.querySelector(s);
    const $$ = (s, r = form) => [...r.querySelectorAll(s)];
    const num = v => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
    const money = n => '$' + (Math.round(n * 100) / 100).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // ---------- filas dinámicas ----------
    const grupos = {
        personal: { cont: '#pd-personal', tpl: '#tpl-personal', contador: '#pd-c-personal' },
        equipo: { cont: '#pd-equipo', tpl: '#tpl-equipo', contador: '#pd-c-equipo' },
        material: { cont: '#pd-materiales', tpl: '#tpl-material', contador: '#pd-c-material' },
        tiempo: { cont: '#pd-tiempos', tpl: '#tpl-tiempo', contador: '#pd-c-tiempos' },
    };
    const actualizarContadores = () => {
        Object.values(grupos).forEach(g => { const c = $(g.contador); if (c) c.textContent = $$('.pd-fila', $(g.cont)).length; });
        const cActs = $('#pd-c-acts');
        if (cActs) cActs.textContent = $$('input[name="actividades[]"]:checked').length;
    };
    document.addEventListener('click', e => {
        const add = e.target.closest('[data-pd-add]');
        if (add) {
            const g = grupos[add.dataset.pdAdd];
            if (!g) return;
            const tpl = $(g.tpl);
            if (!tpl) return;
            $(g.cont).appendChild(tpl.content.cloneNode(true));
            actualizarContadores(); calcular();
            const nueva = $(g.cont).lastElementChild;
            nueva?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }
        const quitar = e.target.closest('.pd-quitar');
        if (quitar) {
            const fila = quitar.closest('.pd-fila');
            if (fila) { fila.remove(); actualizarContadores(); calcular(); }
        }
    });

    // ---------- actividades ----------
    const acts = () => $$('input[name="actividades[]"]');
    document.addEventListener('change', e => {
        if (e.target.matches('input[name="actividades[]"]')) {
            const fila = e.target.closest('.pd-act');
            fila?.classList.toggle('pd-act-sel', e.target.checked);
            const av = fila?.querySelector('input[name^="avance"]');
            // Hitos, subactividades y actividades con no conformidad abierta quedan fijos desde PHP
            if (av) av.disabled = !e.target.checked || av.dataset.fijo === '1' || av.dataset.congelado === '1';
            actualizarContadores();
        }
    });

    const busca = $('#pd-busca-act');
    busca?.addEventListener('input', () => {
        const q = busca.value.trim().toLowerCase();
        $$('.pd-act').forEach(f => { f.hidden = !!q && !(f.dataset.texto || '').includes(q); });
    });

    // ---------- costo del día ----------
    function calcular() {
        let personal = 0, equipo = 0, material = 0, horas = 0, muertas = 0;
        $$('#pd-personal .pd-fila').forEach(f => {
            const sel = f.querySelector('select[name="personal_id[]"]');
            const t = datos.personal?.[sel?.value] || 0;
            const h = num(f.querySelector('input[name="personal_horas[]"]')?.value);
            const ex = num(f.querySelector('input[name="personal_extra[]"]')?.value);
            personal += h * t + ex * t * 1.5;
            horas += h + ex;
        });
        $$('#pd-equipo .pd-fila').forEach(f => {
            const sel = f.querySelector('select[name="equipo_id[]"]');
            equipo += num(f.querySelector('input[name="equipo_horas[]"]')?.value) * (datos.equipo?.[sel?.value] || 0);
        });
        $$('#pd-materiales .pd-fila').forEach(f => {
            const sel = f.querySelector('select[name="material_id[]"]');
            const tipo = f.querySelector('select[name="material_tipo[]"]')?.value;
            if (tipo !== 'consumido') return;
            material += num(f.querySelector('input[name="material_cantidad[]"]')?.value) * (datos.materiales?.[sel?.value] || 0);
        });
        $$('#pd-tiempos .pd-fila').forEach(f => { muertas += num(f.querySelector('input[name="tiempo_horas[]"]')?.value); });
        const set = (id, v) => { const el = $(id); if (el) el.textContent = v; };
        set('#pd-costo-personal', money(personal));
        set('#pd-costo-equipo', money(equipo));
        set('#pd-costo-material', money(material));
        set('#pd-costo-total', money(personal + equipo + material));
        set('#pd-horas', (Math.round(horas * 100) / 100).toString());
        set('#pd-horas-muertas', (Math.round(muertas * 100) / 100).toString());
    }
    form.addEventListener('input', calcular);
    form.addEventListener('change', calcular);

    // Avance topado por calidad: se corrige en el campo (el servidor lo recorta igual) y se avisa en la fila
    form.addEventListener('change', e => {
        const inp = e.target;
        if (!inp.matches('input[name^="avance"]')) return;
        const tope = Number(inp.dataset.tope || 100);
        if (Number(inp.value) > tope) {
            inp.value = String(tope);
            const aviso = inp.closest('.pd-act')?.querySelector('.pd-tope-aviso');
            if (aviso) aviso.hidden = false;
        }
    });

    // ---------- fotos ----------
    const inputFotos = $('#fotos');
    const previa = $('#pd-previa');
    let archivos = [];
    const geo = { lat: '', lng: '' };
    if (navigator.geolocation) navigator.geolocation.getCurrentPosition(
        p => { geo.lat = p.coords.latitude.toFixed(7); geo.lng = p.coords.longitude.toFixed(7); },
        () => {}, { enableHighAccuracy: false, timeout: 4000, maximumAge: 600000 }
    );

    function pintarPrevia() {
        if (!previa) return;
        previa.innerHTML = '';
        archivos.forEach((file, i) => {
            const caja = document.createElement('div');
            caja.className = 'pd-previa-item';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = '';
            caja.appendChild(img);
            const leyenda = document.createElement('input');
            leyenda.type = 'text'; leyenda.name = 'foto_leyenda[' + i + ']'; leyenda.maxLength = 255;
            leyenda.placeholder = 'Leyenda de la foto';
            caja.appendChild(leyenda);
            const act = document.createElement('select');
            act.name = 'foto_actividad[' + i + ']';
            act.innerHTML = '<option value="0">— Actividad —</option>' + acts().map(a => {
                const l = a.closest('.pd-act')?.querySelector('.pd-act-txt')?.textContent?.trim() || a.value;
                return '<option value="' + a.value + '"' + (a.checked ? ' selected' : '') + '>' + l.replace(/[<>&]/g, '') + '</option>';
            }).join('');
            caja.appendChild(act);
            const lat = document.createElement('input'); lat.type = 'hidden'; lat.name = 'foto_lat[' + i + ']'; lat.value = geo.lat; caja.appendChild(lat);
            const lng = document.createElement('input'); lng.type = 'hidden'; lng.name = 'foto_lng[' + i + ']'; lng.value = geo.lng; caja.appendChild(lng);
            const tom = document.createElement('input'); tom.type = 'hidden'; tom.name = 'foto_tomada[' + i + ']'; tom.value = new Date().toISOString().slice(0, 19).replace('T', ' '); caja.appendChild(tom);
            previa.appendChild(caja);
        });
        const c = $('#pd-c-fotos'); if (c) c.textContent = $$('.pd-foto', form).length + archivos.length;
    }
    inputFotos?.addEventListener('change', () => {
        const nuevos = [...inputFotos.files];
        if (!nuevos.length) return;
        archivos = archivos.concat(nuevos).slice(0, 30);
        try {   // mantener todos en el input para que viajen juntos
            const dt = new DataTransfer();
            archivos.forEach(f => dt.items.add(f));
            inputFotos.files = dt.files;
        } catch (_) {}
        pintarPrevia();
    });

    // ---------- firma ----------
    const canvas = $('#pd-firma');
    const firmaData = $('#firma_data');
    let ctx = null, dibujando = false, hayFirma = false;
    if (canvas) {
        ctx = canvas.getContext('2d');
        let ajustado = false;
        const ajustar = () => {
            const ratio = window.devicePixelRatio || 1;
            const ancho = canvas.clientWidth || 700;
            canvas.width = Math.max(200, ancho) * ratio;
            canvas.height = 220 * ratio;
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--text')?.trim() || '#111';
            ajustado = true;
        };
        if (canvas.clientWidth) ajustar();
        const detalleFirma = canvas.closest('details');
        detalleFirma?.addEventListener('toggle', () => { if (detalleFirma.open && !ajustado && canvas.clientWidth) ajustar(); });
        const punto = e => {
            const r = canvas.getBoundingClientRect();
            const t = e.touches?.[0] || e;
            return { x: t.clientX - r.left, y: t.clientY - r.top };
        };
        const empezar = e => { e.preventDefault(); dibujando = true; hayFirma = true; const p = punto(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); };
        const mover = e => { if (!dibujando) return; e.preventDefault(); const p = punto(e); ctx.lineTo(p.x, p.y); ctx.stroke(); };
        const terminar = () => { dibujando = false; };
        canvas.addEventListener('pointerdown', empezar);
        canvas.addEventListener('pointermove', mover);
        window.addEventListener('pointerup', terminar);
        canvas.addEventListener('pointercancel', terminar);
        $('#pd-firma-limpiar')?.addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hayFirma = false;
            if (firmaData) firmaData.value = '';
        });
    }

    // ---------- guardar / offline / cierre ----------
    $('[data-pd-cerrar]')?.addEventListener('click', e => {
        if (!window.confirmarModal) return;   // sin modal, se envía normal
        e.preventDefault();
        window.confirmarModal({
            titulo: 'Cerrar el parte',
            mensaje: 'Al cerrarlo se actualiza el avance, se registran las horas y se agrega el resumen a la bitácora. Después solo se puede reabrir desde el detalle.',
            aceptar: 'Cerrar parte',
        }).then(ok => { if (ok) form.requestSubmit(e.target); });
    });

    form.addEventListener('submit', async e => {
        // La firma se serializa siempre antes de enviar
        if (canvas && hayFirma && firmaData) firmaData.value = canvas.toDataURL('image/png');
        const uuid = form.querySelector('[name="offline_uuid"]');
        if (uuid && !uuid.value && window.crypto?.randomUUID) uuid.value = crypto.randomUUID();

        // Sin conexión: se encola el parte (sin fotos) y se sube al volver la red
        if (!navigator.onLine && window.POOffline?.activo && window.POOffline.activo()) {
            e.preventDefault();
            const fd = new FormData(form);
            // Pares [clave, valor]: conserva los campos repetidos (actividades[], personal_id[]…)
            const cuerpo = [];
            fd.forEach((v, k) => { if (!(v instanceof File)) cuerpo.push([k, v]); });
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (token) cuerpo.push(['_csrf', token]);
            const fecha = fd.get('fecha');
            const proyecto = fd.get('proyecto');
            if (archivos.length) alert('Sin conexión no se pueden subir fotos. El parte se guardará y podrás agregarlas al volver la red.');
            try {
                await window.POOffline.encolar({ url: '/reportes/parte', cuerpo, etiqueta: 'Parte diario ' + fecha });
                form.innerHTML = '<div class="card"><h3>Parte guardado sin conexión</h3><p class="text-muted">Se subirá solo cuando vuelva la red. Puedes seguir trabajando.</p><p><a class="btn btn-primary" href="/reportes?proyecto=' + encodeURIComponent(proyecto) + '">Volver a reportes</a></p></div>';
            } catch (_) { alert('No se pudo guardar sin conexión. Revisa el espacio del navegador.'); }
            return;
        }
    });

    // ---------- arranque ----------
    actualizarContadores();
    calcular();
    pintarPrevia();
})();
