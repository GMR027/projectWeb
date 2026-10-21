/* Notas de una lámina de vPlan o de un gráfico de View360: lista, modal para crear/editar y borrado.
   window.Notas.iniciar({ campo: 'lamina' | 'grafico', id, puedeEditar, notas, urlGuardar, urlEliminar }). El modal viene de views/templates/_notas-modal.php */
(function () {
    'use strict';
    const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(r => r.json());

    function iniciar({ campo, id, puedeEditar, notas, urlGuardar, urlEliminar }) {
        const lista = document.getElementById('vp-notas-lista'), vacio = document.getElementById('vp-notas-vacio');
        const pintar = () => {
            vacio.hidden = notas.length > 0;
            lista.innerHTML = notas.map(n =>
                '<li class="vp-nota" data-id="' + n.id + '">' +
                '<div class="vp-nota-cab"><span class="vp-nota-fecha-txt"><i class="fa-regular fa-calendar"></i> ' + esc(n.fecha) + '</span>' +
                (puedeEditar ? '<span class="vp-nota-acc"><button type="button" class="btn btn-sm btn-ghost" data-nota="editar"><i class="fa-solid fa-pen"></i> Editar</button>' +
                    '<button type="button" class="btn btn-sm btn-danger" data-nota="borrar"><i class="fa-solid fa-trash-can"></i> Borrar</button></span>' : '') +
                '</div><p class="vp-nota-texto">' + esc(n.descripcion) + '</p></li>'
            ).join('');
        };
        pintar();
        if (!puedeEditar) return;

        const modal = document.getElementById('vp-nota-modal'), form = document.getElementById('vp-nota-form');
        const area = document.getElementById('vp-nota-descripcion'), err = document.getElementById('vp-nota-error');
        const btnGuardar = document.getElementById('vp-nota-guardar');
        let editando = null;
        const abrirModal = nota => {
            editando = nota || null;
            document.getElementById('vp-nota-titulo').textContent = nota ? 'Editar nota' : 'Crear nota';
            const hoy = new Date(), p = n => String(n).padStart(2, '0');
            document.getElementById('vp-nota-fecha').textContent = nota ? nota.fecha : p(hoy.getDate()) + '/' + p(hoy.getMonth() + 1) + '/' + hoy.getFullYear();
            document.getElementById('vp-nota-auto').hidden = !!nota;
            area.value = nota ? nota.descripcion : '';
            err.hidden = true;
            modal.classList.add('abierto'); modal.setAttribute('aria-hidden', 'false');
            area.focus();
        };
        const cerrarModal = () => { modal.classList.remove('abierto'); modal.setAttribute('aria-hidden', 'true'); editando = null; };
        document.getElementById('vp-nota-nueva').addEventListener('click', () => abrirModal());
        document.getElementById('vp-nota-cancelar').addEventListener('click', cerrarModal);
        modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('abierto')) cerrarModal(); });

        form.addEventListener('submit', async e => {
            e.preventDefault();
            if (!area.value.trim()) { err.textContent = 'La descripción es obligatoria'; err.hidden = false; return; }
            btnGuardar.disabled = true;
            try {
                const datos = { [campo]: id, descripcion: area.value };
                if (editando) datos.id = editando.id;
                const d = await post(urlGuardar, datos);
                if (!d.success) { err.textContent = d.error || 'No se pudo guardar'; err.hidden = false; return; }
                if (editando) notas[notas.findIndex(n => n.id === editando.id)] = d.nota; else notas.unshift(d.nota);
                pintar(); cerrarModal();
            } catch (_) { err.textContent = 'Error de conexión'; err.hidden = false; }
            finally { btnGuardar.disabled = false; }
        });

        lista.addEventListener('click', async e => {
            const b = e.target.closest('[data-nota]');
            if (!b) return;
            const nota = notas.find(n => n.id === +b.closest('.vp-nota').dataset.id);
            if (!nota) return;
            if (b.dataset.nota === 'editar') return abrirModal(nota);
            const ok = await window.confirmarModal({ titulo: '¿Borrar esta nota?', mensaje: 'Esta acción no se puede deshacer.', aceptar: 'Sí, borrar', iconoBoton: 'fa-trash-can' });
            if (!ok) return;
            const d = await post(urlEliminar, { [campo]: id, id: nota.id });
            if (d.success) { notas.splice(notas.indexOf(nota), 1); pintar(); }
        });
    }

    window.Notas = { iniciar };
})();
