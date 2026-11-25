/* Landing pública de ProjectWeb: mejoras progresivas, sin dependencias. */
(function () {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------- aparición suave ----------
    (function () {
        var els = document.querySelectorAll('.lp-rev');
        if (!els.length) return;
        if (!('IntersectionObserver' in window) || reduce) {
            els.forEach(function (e) { e.classList.add('in'); });
            return;
        }
        var io = new IntersectionObserver(function (es) {
            es.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
            });
        }, { threshold: 0.12 });
        els.forEach(function (e) { io.observe(e); });
    })();

    // ---------- contadores ----------
    (function () {
        var nums = document.querySelectorAll('.lp-stats b[data-n]');
        if (!nums.length || !('IntersectionObserver' in window) || reduce) return;
        var io = new IntersectionObserver(function (es) {
            es.forEach(function (e) {
                if (!e.isIntersecting) return;
                io.unobserve(e.target);
                var el = e.target;
                var fin = parseInt(el.dataset.n, 10) || 0;
                var t0 = performance.now();
                (function paso(t) {
                    var p = Math.min(1, (t - t0) / 1100);
                    el.textContent = Math.round(fin * (1 - Math.pow(1 - p, 3))) + (p === 1 ? (el.dataset.suf || '') : '');
                    if (p < 1) requestAnimationFrame(paso);
                })(t0);
            });
        }, { threshold: 0.6 });
        nums.forEach(function (n) { io.observe(n); });
    })();

    // ---------- menú móvil ----------
    document.querySelectorAll('.lp-links a').forEach(function (a) {
        a.addEventListener('click', function () {
            var chk = document.getElementById('lp-menu');
            if (chk) chk.checked = false;
        });
    });

    // ---------- recorrido del producto ----------
    (function () {
        var tabs = Array.prototype.slice.call(document.querySelectorAll('.lp-tab'));
        var imgs = Array.prototype.slice.call(document.querySelectorAll('.lp-shots img'));
        var txt = document.getElementById('lp-tour-txt');
        var url = document.getElementById('lp-url');
        if (!tabs.length || !imgs.length) return;
        var textos = tabs.map(function (t) { return t.dataset.texto || t.textContent.trim(); });
        var actual = 0;
        var timer = null;
        var sec = document.getElementById('recorrido');

        function ir(i, manual) {
            actual = i;
            tabs.forEach(function (t, k) {
                var on = k === i;
                t.classList.toggle('on', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            imgs.forEach(function (m, k) { m.classList.toggle('on', k === i); });
            if (txt) txt.textContent = textos[i] || '';
            if (url && imgs[i]) url.textContent = imgs[i].dataset.url || '';
            if (manual && timer) { clearInterval(timer); timer = null; }
        }

        tabs.forEach(function (t, i) { t.addEventListener('click', function () { ir(i, true); }); });

        if (sec && 'IntersectionObserver' in window && !reduce) {
            new IntersectionObserver(function (es) {
                es.forEach(function (e) {
                    if (e.isIntersecting && !timer && !sec.dataset.manual) {
                        timer = setInterval(function () { ir((actual + 1) % tabs.length); }, 5200);
                    } else if (!e.isIntersecting && timer) {
                        clearInterval(timer); timer = null;
                    }
                });
            }, { threshold: 0.35 }).observe(sec);
            tabs.forEach(function (t) { t.addEventListener('click', function () { sec.dataset.manual = '1'; }); });
        }
    })();

    // ---------- explorador de funciones ----------
    (function () {
        var root = document.getElementById('lp-explorer');
        if (!root) return;
        var tabs = Array.prototype.slice.call(root.querySelectorAll('.lp-chip-btn'));
        var cards = Array.prototype.slice.call(root.querySelectorAll('.lp-tool'));
        var busca = document.getElementById('lp-busca');
        var count = document.getElementById('lp-explorer-count');
        var vacio = document.getElementById('lp-explorer-vacio');
        var filtro = root.dataset.filtroInicial || 'todas';

        function normalizar(s) {
            return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function aplicar() {
            var q = normalizar(busca ? busca.value : '').trim();
            var visibles = 0;
            cards.forEach(function (c) {
                var okFrente = filtro === 'todas' || c.dataset.frente === filtro;
                var texto = normalizar(c.dataset.nombre + ' ' + c.dataset.detalle);
                var okTexto = !q || q.split(/\s+/).every(function (p) { return texto.indexOf(p) !== -1; });
                var ver = okFrente && okTexto;
                c.hidden = !ver;
                if (ver) visibles++;
            });
            tabs.forEach(function (t) {
                var on = t.dataset.filtro === filtro;
                t.classList.toggle('on', on);
                t.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            if (count) count.textContent = 'Mostrando ' + visibles + ' herramienta' + (visibles === 1 ? '' : 's');
            if (vacio) vacio.hidden = visibles !== 0;
        }

        tabs.forEach(function (t) {
            t.addEventListener('click', function () { filtro = t.dataset.filtro || 'todas'; aplicar(); });
        });
        if (busca) busca.addEventListener('input', aplicar);

        document.querySelectorAll('[data-filtro-frente]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var f = a.dataset.filtroFrente;
                if (!f) return;
                e.preventDefault();
                filtro = f;
                if (busca) busca.value = '';
                aplicar();
                var destino = document.getElementById('funciones');
                if (destino) destino.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            });
        });

        aplicar();
    })();

    // ---------- calculadora de ahorro ----------
    (function () {
        var root = document.getElementById('lp-calc');
        if (!root) return;
        var costos = {};
        var planes = [];
        try { costos = JSON.parse(root.dataset.costos || '{}'); } catch (_) {}
        try { planes = JSON.parse(root.dataset.planes || '[]'); } catch (_) {}
        var rolEls = Array.prototype.slice.call(root.querySelectorAll('[data-rol]'));
        var obras = document.getElementById('lp-obras');
        var plan = document.getElementById('lp-plan-calc');
        var trad = document.getElementById('lp-trad');
        var pw = document.getElementById('lp-pw');
        var ahorro = document.getElementById('lp-ahorro');
        var pct = document.getElementById('lp-pct');
        var desglose = document.getElementById('lp-desglose');

        function mxn(n) { return '$' + Math.round(n).toLocaleString('es-MX'); }
        function valor(clave) {
            var out = root.querySelector('[data-valor="' + clave + '"]');
            return Math.max(0, Math.min(100, parseInt(out ? out.textContent : '0', 10) || 0));
        }
        function planPara(usuarios, obrasN) {
            for (var i = 0; i < planes.length; i++) {
                var p = planes[i];
                var okU = p.usuarios === null || p.usuarios === undefined || usuarios <= p.usuarios;
                var okO = p.obras === null || p.obras === undefined || obrasN <= p.obras;
                if (okU && okO) return p;
            }
            return planes[planes.length - 1] || null;
        }
        function calcular() {
            var usuarios = 0;
            var mensual = 0;
            var filas = [];
            rolEls.forEach(function (el) {
                var clave = el.dataset.rol;
                var n = valor(clave);
                var costo = parseInt(costos[clave], 10) || 0;
                usuarios += n;
                var sub = n * costo;
                mensual += sub;
                if (n > 0) {
                    var nombre = el.querySelector('strong') ? el.querySelector('strong').textContent : clave;
                    filas.push('<li><span>' + nombre + ' · ' + n + '</span><b>' + mxn(sub) + '/mes</b></li>');
                }
            });
            if (usuarios === 0) {
                usuarios = 1;
                mensual = parseInt(costos.direccion, 10) || 0;
                filas = ['<li><span>Dirección / PM · 1</span><b>' + mxn(mensual) + '/mes</b></li>'];
            }
            var obrasN = Math.max(1, Math.min(500, parseInt(obras ? obras.value : '1', 10) || 1));
            var anual = mensual * 12;
            var p = planPara(usuarios, obrasN);
            var costoPw = p ? p.precio_anual : 0;
            var ahorroN = Math.max(0, anual - costoPw);
            var pctN = anual > 0 ? Math.round(ahorroN / anual * 100) : 0;

            if (plan) plan.textContent = p ? p.nombre : 'A cotizar';
            if (trad) trad.textContent = mxn(anual);
            if (pw) pw.textContent = mxn(costoPw);
            if (ahorro) ahorro.textContent = mxn(ahorroN);
            if (pct) pct.textContent = pctN + '% menos';
            if (desglose) desglose.innerHTML = filas.join('') || '<li><span>Sin licencias que reemplazar</span><b>$0/mes</b></li>';
        }

        rolEls.forEach(function (el) {
            var out = el.querySelector('[data-valor]');
            el.querySelectorAll('[data-paso]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var n = Math.max(0, Math.min(100, valor(el.dataset.rol) + parseInt(b.dataset.paso, 10)));
                    if (out) out.textContent = String(n);
                    calcular();
                });
            });
        });
        if (obras) obras.addEventListener('input', calcular);
        var boton = document.getElementById('lp-calcular');
        if (boton) boton.addEventListener('click', function () {
            calcular();
            var res = document.getElementById('lp-resultado');
            if (res) res.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
        });
        calcular();
    })();

    // ---------- formulario de contacto ----------
    (function () {
        var f = document.getElementById('lp-form');
        if (!f) return;
        var t0 = Date.now();
        var err = document.getElementById('lp-err');
        var btn = document.getElementById('lp-send');
        var selPlan = document.getElementById('lp-plan');

        document.querySelectorAll('[data-plan]').forEach(function (a) {
            a.addEventListener('click', function () {
                if (selPlan && a.dataset.plan) selPlan.value = a.dataset.plan;
            });
        });

        if (selPlan) {
            var q = new URLSearchParams(window.location.search);
            var pre = q.get('plan') || (q.get('interes') === 'servicio' ? 'Plataforma + seguimiento semanal' : '');
            if (pre && Array.prototype.some.call(selPlan.options, function (o) { return o.value === pre; })) {
                selPlan.value = pre;
            }
        }

        f.addEventListener('submit', function (e) {
            e.preventDefault();
            if (err) err.hidden = true;
            var t = document.getElementById('lp-t');
            if (t) t.value = String(t0);
            if (!f.checkValidity()) { f.reportValidity(); return; }
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando…';
            }
            fetch('/contacto', { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) {
                        f.hidden = true;
                        var ok = document.getElementById('lp-ok');
                        if (ok) ok.hidden = false;
                        return;
                    }
                    if (err) {
                        err.innerHTML = (d.errores || ['No se pudo enviar. Intenta de nuevo.']).map(function (x) {
                            return '<div>' + String(x).replace(/[<>&]/g, function (c) { return ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' })[c]; }) + '</div>';
                        }).join('');
                        err.hidden = false;
                    }
                })
                .catch(function () {
                    if (err) { err.textContent = 'No se pudo enviar. Revisa tu conexión e intenta de nuevo.'; err.hidden = false; }
                })
                .finally(function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar mensaje';
                    }
                });
        });

        if (new URLSearchParams(window.location.search).get('contacto') === 'ok') {
            f.hidden = true;
            var ok = document.getElementById('lp-ok');
            if (ok) ok.hidden = false;
        }
    })();
})();
