/* Curso del administrador: progreso local por lección (sin servidor, sin datos compartidos). */
(function () {
    'use strict';
    var KEY = 'pw-curso-admin';
    var checks = Array.prototype.slice.call(document.querySelectorAll('[data-curso-check]'));
    if (!checks.length) return;

    var guardado = [];
    try { guardado = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { guardado = []; }
    var hechas = new Set(Array.isArray(guardado) ? guardado : []);

    function guardar() {
        try { localStorage.setItem(KEY, JSON.stringify(Array.from(hechas))); } catch (e) { /* modo privado */ }
    }

    function pintar() {
        var total = checks.length;
        var n = checks.filter(function (c) { return c.checked; }).length;
        var pct = total ? Math.round(n / total * 100) : 0;
        var barra = document.getElementById('curso-progreso');
        var texto = document.getElementById('curso-progreso-txt');
        if (barra) barra.style.width = pct + '%';
        if (texto) texto.textContent = pct + ' %';

        document.querySelectorAll('.curso-modulo').forEach(function (mod) {
            var propias = Array.prototype.slice.call(mod.querySelectorAll('[data-curso-check]'));
            var completa = propias.length > 0 && propias.every(function (c) { return c.checked; });
            var nav = document.querySelector('[data-nav-modulo="' + mod.id + '"]');
            if (nav) nav.classList.toggle('completado', completa);
        });
    }

    checks.forEach(function (c) {
        var id = c.getAttribute('data-curso-check');
        c.checked = hechas.has(id);
        var leccion = c.closest('.curso-leccion');
        if (leccion) leccion.classList.toggle('completada', c.checked);
        c.addEventListener('change', function () {
            if (c.checked) hechas.add(id); else hechas.delete(id);
            if (leccion) leccion.classList.toggle('completada', c.checked);
            guardar();
            pintar();
        });
    });

    var reiniciar = document.getElementById('curso-reiniciar');
    if (reiniciar) {
        reiniciar.addEventListener('click', function () {
            if (!window.confirm('¿Borrar tu avance del curso en este navegador?')) return;
            hechas.clear();
            checks.forEach(function (c) {
                c.checked = false;
                var leccion = c.closest('.curso-leccion');
                if (leccion) leccion.classList.remove('completada');
            });
            guardar();
            pintar();
        });
    }

    pintar();
})();
