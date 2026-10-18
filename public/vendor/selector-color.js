(function () {
  'use strict';
  // Selector de color con paletas, gotero y copia de valor para todos los input[type=color] de la app (public/vendor/paletas.js).
  // Un input se omite con data-sin-paleta.
  const PALETA = [['#111111', 'Negro'], ['#4b5563', 'Gris oscuro'], ['#9ca3af', 'Gris'], ['#ffffff', 'Blanco'],
    ['#dc2626', 'Rojo'], ['#991b1b', 'Rojo oscuro'], ['#f97316', 'Naranja'], ['#d97706', 'Ámbar'],
    ['#facc15', 'Amarillo'], ['#84cc16', 'Lima'], ['#059669', 'Verde'], ['#00b4d8', 'Turquesa'],
    ['#0070c0', 'Azul'], ['#1e3a8a', 'Azul marino'], ['#7c3aed', 'Morado'], ['#ec4899', 'Rosa']];
  // Paletas ampliadas (public/vendor/paletas.js): básicos por gama, maestra Pantone y colores propios del usuario
  const P = window.VP_PALETAS || { gamas: [], basicos: [], maestra: [] };
  const LS_PROPIOS = 'vp_colores_propios', MAX_PROPIOS = 80;
  const leerPropios = () => { try { const v = JSON.parse(localStorage.getItem(LS_PROPIOS) || '[]'); return Array.isArray(v) ? v.filter(h => /^#[0-9a-f]{6}$/i.test(h)) : []; } catch (e) { return []; } };
  const guardarPropios = l => { try { localStorage.setItem(LS_PROPIOS, JSON.stringify(l)); } catch (e) { /* sin almacenamiento: la paleta vale solo en esta sesión */ } };
  const vista = { tab: 'basicos', gama: 'Rápidos', gamaM: 'Todas' };   // se recuerda entre selectores
  const swatch = (hex, titulo, activo) => {
    const b = document.createElement('button'); b.type = 'button'; b.className = 've-sw' + (activo ? ' activo' : ''); b.style.background = hex; b.title = titulo; b.dataset.c = hex; b.setAttribute('aria-label', titulo);
    return b;
  };
  const chips = (opciones, actual, onSel) => {
    const f = document.createElement('div'); f.className = 've-chips';
    opciones.forEach(o => { const c = document.createElement('button'); c.type = 'button'; c.className = 've-chip' + (o === actual ? ' activo' : ''); c.textContent = o; c.addEventListener('click', e => { e.stopPropagation(); onSel(o); }); f.appendChild(c); });
    return f;
  };
  // Cada selector de color recibe un botón con la paleta; el selector nativo queda para colores personalizados
  function iniciar() {
    document.querySelectorAll('input[type=color]').forEach(inp => {
      if (inp.closest('.ve-color') || inp.dataset.sinPaleta !== undefined) return;
      const w = document.createElement('span'); w.className = 've-color';
      inp.parentNode.insertBefore(w, inp); w.appendChild(inp);
      const b = document.createElement('button', {}); b.type = 'button'; b.className = 've-paleta-btn'; b.title = 'Paleta de colores: básicos, maestra Pantone y mis colores'; b.setAttribute('aria-label', 'Paleta de colores');
      b.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".6"/><circle cx="17.5" cy="10.5" r=".6"/><circle cx="8.5" cy="7.5" r=".6"/><circle cx="6.5" cy="12.5" r=".6"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg><span>Paleta</span>';
      const pop = document.createElement('div'); pop.className = 've-paleta'; pop.hidden = true;
      w.appendChild(b);
      const host = () => inp.closest('dialog') || document.body;   // dentro de un <dialog> modal solo se ve lo que cuelga de él
      const colocar = () => {   // flotante y ajustada a la pantalla: no la recorta ningún overflow ni se sale por los bordes
        const m = 8, vw = document.documentElement.clientWidth, vh = window.innerHeight, r = b.getBoundingClientRect();
        pop.style.maxHeight = ''; pop.style.width = Math.min(360, vw - 2 * m) + 'px';
        const w2 = pop.offsetWidth, h = pop.offsetHeight;
        let left = Math.min(Math.max(m, r.right - w2), vw - w2 - m);
        if (r.left + w2 <= vw - m) left = Math.max(m, r.left);
        const abajo = vh - r.bottom - m, arriba = r.top - m;
        let top;
        if (h <= abajo) top = r.bottom + 6;
        else if (h <= arriba) top = r.top - h - 6;
        else { const sitioAbajo = abajo >= arriba; const alto = Math.max(160, sitioAbajo ? abajo : arriba); pop.style.maxHeight = alto + 'px'; top = sitioAbajo ? r.bottom + 6 : Math.max(m, r.top - alto - 6); }
        pop.style.left = left + 'px'; pop.style.top = top + 'px';
      };
      const abrir = () => { host().appendChild(pop); pop.hidden = false; colocar(); };
      pop._cerrar = () => { pop.hidden = true; };
      pop._colocar = colocar; pop._dueno = w;
      let quitar = false, busca = '';
      const actual = () => inp.value.toLowerCase();
      const aplicar = (hex, guardar) => {
        inp.value = hex;
        inp.dispatchEvent(new Event('input', { bubbles: true })); inp.dispatchEvent(new Event('change', { bubbles: true }));
        if (guardar) { const l = leerPropios(); if (!l.includes(hex)) { l.unshift(hex); guardarPropios(l.slice(0, MAX_PROPIOS)); } }   // lo tomado con el gotero queda en Mis colores
        pop.hidden = true;
      };
      const pintar = () => {
        pop.textContent = '';
        const tabs = document.createElement('div'); tabs.className = 've-pestanas'; tabs.setAttribute('role', 'tablist');
        [['basicos', 'Básicos'], ['maestra', 'Maestra'], ['propios', 'Mis colores']].forEach(([k, t]) => {
          const x = document.createElement('button'); x.type = 'button'; x.className = 've-pest' + (vista.tab === k ? ' activo' : ''); x.textContent = t; x.setAttribute('role', 'tab'); x.setAttribute('aria-selected', vista.tab === k);
          x.addEventListener('click', e => { e.stopPropagation(); vista.tab = k; quitar = false; pintar(); });
          tabs.appendChild(x);
        });
        pop.appendChild(tabs);
        const grid = document.createElement('div'); grid.className = 've-sws';
        if (vista.tab === 'basicos') {
          const gamas = ['Rápidos', ...P.basicos.map(g => g[0])];
          pop.appendChild(chips(gamas, vista.gama, g => { vista.gama = g; pintar(); }));
          const lista = vista.gama === 'Rápidos' ? PALETA : (P.basicos.find(g => g[0] === vista.gama) || [0, []])[1].map(h => [h, vista.gama + ' ' + h]);
          lista.forEach(([hex, n]) => grid.appendChild(swatch(hex, n + ' ' + hex, hex === actual())));
        } else if (vista.tab === 'maestra') {
          pop.appendChild(chips(['Todas', ...P.gamas], vista.gamaM, g => { vista.gamaM = g; pintar(); }));
          const q = document.createElement('input'); q.type = 'search'; q.className = 've-buscar'; q.placeholder = 'Buscar por nombre o número (ej. 185)'; q.value = busca; q.setAttribute('aria-label', 'Buscar color Pantone');
          q.addEventListener('input', () => { busca = q.value; llenar(); }); q.addEventListener('click', e => e.stopPropagation());
          pop.appendChild(q);
          var llenar = () => {
            grid.textContent = '';
            const t = busca.trim().toLowerCase(); let n = 0;
            P.maestra.forEach(([hex, nom, gi]) => {
              if (vista.gamaM !== 'Todas' && P.gamas[gi] !== vista.gamaM) return;
              if (t && nom.toLowerCase().indexOf(t) < 0) return;
              n++; grid.appendChild(swatch(hex, 'Pantone ' + nom + ' ' + hex, hex === actual()));
            });
            cuenta.textContent = n + ' colores';
          };
          var cuenta = document.createElement('div'); cuenta.className = 've-cuenta';
        } else {
          const propios = leerPropios();
          if (!propios.length) { const v = document.createElement('p'); v.className = 've-vacio'; v.textContent = 'Aún no guardas colores. Elige uno y pulsa «Guardar este color».'; grid.className = ''; grid.appendChild(v); }
          propios.forEach(hex => grid.appendChild(swatch(hex, (quitar ? 'Quitar ' : '') + hex, hex === actual())));
        }
        pop.appendChild(grid);
        if (vista.tab === 'maestra') { pop.appendChild(cuenta); llenar(); }
        const pie = document.createElement('div'); pie.className = 've-paleta-pie';
        const g = document.createElement('button'); g.type = 'button'; g.className = 've-pie-btn'; g.dataset.acc = 'guardar'; g.textContent = '＋ Guardar este color';
        g.title = 'Añade el color actual (' + actual() + ') a Mis colores'; pie.appendChild(g);
        if (vista.tab === 'propios' && leerPropios().length) { const q2 = document.createElement('button'); q2.type = 'button'; q2.className = 've-pie-btn' + (quitar ? ' activo' : ''); q2.dataset.acc = 'quitar'; q2.textContent = quitar ? 'Listo' : 'Quitar colores'; pie.appendChild(q2); }
        if (window.EyeDropper) {
          const gt = document.createElement('button'); gt.type = 'button'; gt.className = 've-pie-btn'; gt.dataset.acc = 'gotero'; gt.textContent = '💧 Gotero';
          gt.title = 'Toma cualquier color de la pantalla (no hay que copiar su valor)'; pie.appendChild(gt);
        }
        const cp = document.createElement('button'); cp.type = 'button'; cp.className = 've-pie-btn'; cp.dataset.acc = 'copiar'; cp.textContent = actual();
        cp.title = 'Copiar el valor hexadecimal'; pie.appendChild(cp);
        pop.appendChild(pie);
        if (!pop.hidden) colocar();
      };
      b.addEventListener('click', e => {
        e.stopPropagation();
        document.querySelectorAll('.ve-paleta').forEach(x => { if (x !== pop) x.hidden = true; });
        if (pop.hidden) { quitar = false; abrir(); pintar(); } else pop.hidden = true;
      });
      pop.addEventListener('click', e => {
        e.stopPropagation();
        const acc = e.target.closest('[data-acc]');
        if (acc) {
          if (acc.dataset.acc === 'quitar') { quitar = !quitar; pintar(); return; }
          if (acc.dataset.acc === 'copiar') { try { navigator.clipboard.writeText(actual()); acc.textContent = '¡Copiado!'; setTimeout(() => { acc.textContent = actual(); }, 900); } catch (_) {} return; }
          if (acc.dataset.acc === 'gotero') {
            pop.hidden = true;
            new EyeDropper().open().then(r => { aplicar(r.sRGBHex.toLowerCase(), true); }).catch(() => {});
            return;
          }
          const l = leerPropios(), h = actual();
          if (!l.includes(h)) { l.unshift(h); guardarPropios(l.slice(0, MAX_PROPIOS)); }
          vista.tab = 'propios'; quitar = false; pintar(); return;
        }
        const x = e.target.closest('.ve-sw'); if (!x) return;
        if (vista.tab === 'propios' && quitar) { guardarPropios(leerPropios().filter(h => h !== x.dataset.c)); pintar(); return; }
        aplicar(x.dataset.c, false);
      });
    });
    const cerrarTodas = () => document.querySelectorAll('.ve-paleta').forEach(x => { x.hidden = true; });
    const reubicar = e => { if (e && e.target && e.target.closest && e.target.closest('.ve-paleta')) return; document.querySelectorAll('.ve-paleta:not([hidden])').forEach(x => x._colocar && x._colocar()); };
    document.addEventListener('click', e => { if (!e.target.closest('.ve-color, .ve-paleta')) cerrarTodas(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarTodas(); });
    window.addEventListener('resize', reubicar); window.addEventListener('scroll', reubicar, true);
  }

  window.VPColor = { iniciar };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
