<?php
/**
 * Acciones compartidas de los vínculos de normativa (Aplica / No aplica / Desvincular / Aceptar todas).
 * Se activa en cualquier contenedor con data-norm-raiz y data-proyecto; solo para el administrador.
 */
if (!is_admin()) return;
?>
<script>
(function () {
    function post(pid, url, campos) {
        var d = new FormData();
        d.append('proyecto', pid);
        Object.keys(campos).forEach(function (k) { d.append(k, campos[k]); });
        return fetch(url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    async function resolver(raiz, vinc, accion) {
        var msg = vinc.querySelector('.norm-msg, .inc-msg');
        var pid = raiz.dataset.proyecto;
        var j;
        try {
            j = await post(pid, accion === 'quitar' ? '/normativa/quitar' : '/normativa/resolver',
                accion === 'quitar' ? { id: vinc.dataset.id } : { id: vinc.dataset.id, estado: accion });
        } catch (x) {
            j = { success: false, mensaje: 'Error de conexión' };
        }
        if (msg) msg.textContent = j.mensaje || (j.success ? 'Listo' : 'No se pudo');
        if (j.success) {
            vinc.classList.add('hecha');
            if (raiz.dataset.recargar) setTimeout(function () { location.reload(); }, 700);
            else setTimeout(function () { vinc.remove(); }, 900);
        }
        return j.success;
    }

    document.querySelectorAll('[data-norm-raiz]').forEach(function (raiz) {
        if (raiz.dataset.normListo) return;
        raiz.dataset.normListo = '1';
        raiz.addEventListener('click', async function (e) {
            var todas = e.target.closest('button[data-norm-todas]');
            if (todas) {
                var elem = todas.closest('.norm-elem');
                var botones = elem ? elem.querySelectorAll('.norm-vinculo[data-estado="propuesta"] button[data-norm="aceptada"]') : [];
                if (!botones.length) return;
                if (!confirm('¿Aceptar ' + botones.length + ' propuesta(s) de este elemento?')) return;
                todas.disabled = true;
                for (var i = 0; i < botones.length; i++) {
                    botones[i].disabled = true;
                    await resolver(raiz, botones[i].closest('.norm-vinculo'), 'aceptada');
                }
                location.reload();
                return;
            }

            var sug = e.target.closest('[data-norm-sugerir]');
            if (sug) {
                var msg = sug.closest('.norm-elem').querySelector('.norm-msg');
                sug.disabled = true;
                if (msg) msg.textContent = 'Buscando…';
                try {
                    var j = await post(raiz.dataset.proyecto, '/normativa/sugerir', { tipo: sug.dataset.tipo, entidad: sug.dataset.entidad });
                    if (msg) msg.textContent = j.propuestas ? j.propuestas + ' propuesta(s) nueva(s)' : 'No se encontraron cláusulas nuevas';
                    if (j.propuestas) setTimeout(function () { location.reload(); }, 700);
                    else sug.disabled = false;
                } catch (x) {
                    if (msg) msg.textContent = 'Error de conexión';
                    sug.disabled = false;
                }
                return;
            }

            var b = e.target.closest('button[data-norm]');
            if (!b) return;
            var vinc = b.closest('.norm-vinculo');
            if (!vinc) return;
            b.disabled = true;
            var ok = await resolver(raiz, vinc, b.dataset.norm);
            if (!ok) b.disabled = false;
        });
    });
})();
</script>
