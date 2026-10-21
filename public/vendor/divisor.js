// Divisor de ventana: parte la vista en 2-6 paneles, cada uno con su herramienta y su zoom.
// Los paneles son iframes de la misma app (sin sidebar: clase .embebido) y funcionan igual en la web y en la app de escritorio.
(function () {
  'use strict';
  if (window.top !== window.self) return;
  const CLAVE = 'divisor:v1', MAX = 6, ZMIN = 30, ZMAX = 200;
  const el = (t, c, h) => { const e = document.createElement(t); if (c) e.className = c; if (h != null) e.innerHTML = h; return e; };
  const ico = n => '<i class="fa-solid ' + n + '"></i>';

  let tree, panes, sig, raiz, lienzo, barra, herramientas = [];
  const iframes = new Map();   // id → elemento (nunca se mueven en el DOM: mover un iframe lo recarga)

  const guardar = () => { try { localStorage.setItem(CLAVE, JSON.stringify({ tree, panes, sig })); } catch (e) { /* sin almacenamiento */ } };
  function cargar() {
    try { const d = JSON.parse(localStorage.getItem(CLAVE) || 'null'); if (d && d.tree && d.panes) { tree = d.tree; panes = d.panes; sig = d.sig || 10; return; } } catch (e) { /* datos dañados */ }
    panes = { 1: { url: location.pathname + location.search, z: 100 }, 2: { url: '', z: 100 } }; sig = 3;
    tree = { d: 'x', r: .5, a: 1, b: 2 };
  }

  // ---- árbol ----
  const esHoja = n => typeof n === 'number';
  function disponer(n, r, hojas, divs) {
    if (esHoja(n)) { hojas.push({ id: n, r }); return; }
    if (n.d === 'x') { const wa = r.w * n.r; disponer(n.a, { x: r.x, y: r.y, w: wa, h: r.h }, hojas, divs); disponer(n.b, { x: r.x + wa, y: r.y, w: r.w - wa, h: r.h }, hojas, divs); }
    else { const ha = r.h * n.r; disponer(n.a, { x: r.x, y: r.y, w: r.w, h: ha }, hojas, divs); disponer(n.b, { x: r.x, y: r.y + ha, w: r.w, h: r.h - ha }, hojas, divs); }
    if (divs) divs.push({ n, r });
  }
  const ids = n => esHoja(n) ? [n] : ids(n.a).concat(ids(n.b));
  function mapear(n, f) { if (esHoja(n)) return f(n); return { d: n.d, r: n.r, a: mapear(n.a, f), b: mapear(n.b, f) }; }
  function quitar(n, id) {
    if (esHoja(n)) return n;
    if (n.a === id) return n.b; if (n.b === id) return n.a;
    return { d: n.d, r: n.r, a: quitar(n.a, id), b: quitar(n.b, id) };
  }
  const PRESETS = [
    ['1 ventana', 1, i => i[0]],
    ['2 ventanas lado a lado', 2, i => ({ d: 'x', r: .5, a: i[0], b: i[1] })],
    ['2 ventanas apiladas', 2, i => ({ d: 'y', r: .5, a: i[0], b: i[1] })],
    ['1 grande + 2', 3, i => ({ d: 'x', r: .6, a: i[0], b: { d: 'y', r: .5, a: i[1], b: i[2] } })],
    ['2 + 1 grande', 3, i => ({ d: 'x', r: .4, a: { d: 'y', r: .5, a: i[0], b: i[1] }, b: i[2] })],
    ['4 ventanas', 4, i => ({ d: 'y', r: .5, a: { d: 'x', r: .5, a: i[0], b: i[1] }, b: { d: 'x', r: .5, a: i[2], b: i[3] } })],
  ];
  function aplicarPreset(p) {
    const actuales = ids(tree), nuevos = [];
    for (let i = 0; i < p[1]; i++) { if (actuales[i] != null) nuevos.push(actuales[i]); else { panes[sig] = { url: '', z: 100 }; nuevos.push(sig++); } }
    actuales.slice(p[1]).forEach(descartar);
    tree = p[2](nuevos); pintar();
  }
  function descartar(id) { delete panes[id]; const f = iframes.get(id); if (f) { f.remove(); iframes.delete(id); } }

  function dividir(id, d) {
    if (ids(tree).length >= MAX) return;
    const nuevo = sig++; panes[nuevo] = { url: '', z: 100 };
    tree = mapear(tree, h => h === id ? { d, r: .5, a: id, b: nuevo } : h); pintar();
  }
  function cerrarPanel(id) {
    if (ids(tree).length <= 1) { cerrar(); return; }
    tree = quitar(tree, id); descartar(id); pintar();
  }

  // ---- herramientas disponibles (las mismas del menú lateral) ----
  function leerHerramientas() {
    const vistos = new Set(); herramientas = [];
    document.querySelectorAll('.sidebar-nav a.nav-link').forEach(a => {
      if (a.target === '_blank' || a.classList.contains('acc-todas')) return;
      const href = a.getAttribute('href'); if (!href || vistos.has(href)) return;
      vistos.add(href); herramientas.push({ url: href, nombre: a.textContent.trim().replace(/\s+/g, ' ') });
    });
    if (!herramientas.length) herramientas = [{ url: '/dashboard', nombre: 'Panel' }, { url: '/proyectos', nombre: 'Proyectos' }];
  }
  const ruta = u => { try { const x = new URL(u, location.href); return x.pathname + x.search; } catch (e) { return u; } };

  // ---- pintado ----
  function fabricarPanel(id) {
    const p = el('section', 'dv-panel'); p.dataset.id = id;
    const cab = el('header', 'dv-cab');
    const sel = el('select', 'dv-sel'); sel.setAttribute('aria-label', 'Herramienta de esta ventana');
    sel.addEventListener('change', () => { panes[id].url = sel.value; cargarFrame(id); guardar(); });
    const zoom = el('span', 'dv-zoom');
    const menos = el('button', '', '−'), pct = el('button', 'dv-pct', '100 %'), mas = el('button', '', '+');
    menos.title = 'Reducir'; mas.title = 'Ampliar'; pct.title = 'Restablecer al 100 %';
    const z = d => { panes[id].z = Math.max(ZMIN, Math.min(ZMAX, d === 0 ? 100 : panes[id].z + d)); aplicarZoom(id); guardar(); };
    menos.onclick = () => z(-10); mas.onclick = () => z(10); pct.onclick = () => z(0);
    zoom.append(menos, pct, mas);
    const bx = el('button', 'dv-ic', ico('fa-table-columns')), by = el('button', 'dv-ic', ico('fa-table-list')), bc = el('button', 'dv-ic dv-x', ico('fa-xmark'));
    bx.title = 'Dividir a la derecha'; by.title = 'Dividir hacia abajo'; bc.title = 'Cerrar esta ventana';
    [bx, by, bc].forEach(b => b.type = 'button'); [menos, pct, mas].forEach(b => b.type = 'button');
    bx.onclick = () => dividir(id, 'x'); by.onclick = () => dividir(id, 'y'); bc.onclick = () => cerrarPanel(id);
    cab.append(sel, zoom, bx, by, bc);
    const cuerpo = el('div', 'dv-cuerpo');
    cuerpo.append(el('p', 'dv-vacio', 'Elige una herramienta en el selector de arriba.'));
    p.append(cab, cuerpo); return p;
  }
  function llenarSelect(sel, url) {
    sel.innerHTML = ''; sel.append(new Option('— Elegir herramienta —', ''));
    const lista = herramientas.slice();
    if (url && !lista.some(h => ruta(h.url) === ruta(url))) lista.unshift({ url, nombre: ruta(url).split('?')[0] });
    lista.forEach(h => sel.append(new Option(h.nombre, h.url)));
    const m = lista.find(h => ruta(h.url) === ruta(url)); sel.value = m ? m.url : '';
  }
  function cargarFrame(id) {
    const panel = lienzo.querySelector('.dv-panel[data-id="' + id + '"]'); if (!panel) return;
    const cuerpo = panel.querySelector('.dv-cuerpo'), url = panes[id].url;
    let f = iframes.get(id);
    if (!url) { if (f) { f.remove(); iframes.delete(id); } if (!cuerpo.querySelector('.dv-vacio')) cuerpo.append(el('p', 'dv-vacio', 'Elige una herramienta en el selector de arriba.')); return; }
    cuerpo.querySelector('.dv-vacio')?.remove();
    if (!f) {
      f = el('iframe', 'dv-frame'); f.title = 'Ventana ' + id; iframes.set(id, f); cuerpo.append(f);
      f.addEventListener('load', () => {   // si el usuario navega dentro del panel, el selector lo sigue
        try { const u = f.contentWindow.location.pathname + f.contentWindow.location.search; panes[id].url = u; const sel = panel.querySelector('.dv-sel'); llenarSelect(sel, u); aplicarZoom(id); guardar(); } catch (e) { /* otro origen */ }
      });
    }
    f.src = url; aplicarZoom(id);
  }
  function aplicarZoom(id) {
    const panel = lienzo.querySelector('.dv-panel[data-id="' + id + '"]'), f = iframes.get(id), z = panes[id].z;
    if (panel) panel.querySelector('.dv-pct').textContent = z + ' %';
    if (!f) return; const k = z / 100;
    f.style.width = (100 / k) + '%'; f.style.height = (100 / k) + '%'; f.style.transform = 'scale(' + k + ')';
  }
  function pintar() {
    const hojas = [], divs = []; disponer(tree, { x: 0, y: 0, w: 1, h: 1 }, hojas, divs);
    const vivos = new Set(hojas.map(h => h.id));
    lienzo.querySelectorAll('.dv-panel').forEach(p => { if (!vivos.has(+p.dataset.id)) p.remove(); });
    lienzo.querySelectorAll('.dv-div').forEach(d => d.remove());
    hojas.forEach(h => {
      let p = lienzo.querySelector('.dv-panel[data-id="' + h.id + '"]');
      if (!p) { p = fabricarPanel(h.id); lienzo.append(p); llenarSelect(p.querySelector('.dv-sel'), panes[h.id].url); cargarFrame(h.id); }
      const s = p.style; s.left = h.r.x * 100 + '%'; s.top = h.r.y * 100 + '%'; s.width = h.r.w * 100 + '%'; s.height = h.r.h * 100 + '%';
      aplicarZoom(h.id);
    });
    divs.forEach(({ n, r }) => {
      const d = el('div', 'dv-div dv-' + n.d); d.setAttribute('role', 'separator'); d.title = 'Arrastra para cambiar el tamaño';
      if (n.d === 'x') { d.style.left = (r.x + r.w * n.r) * 100 + '%'; d.style.top = r.y * 100 + '%'; d.style.height = r.h * 100 + '%'; }
      else { d.style.top = (r.y + r.h * n.r) * 100 + '%'; d.style.left = r.x * 100 + '%'; d.style.width = r.w * 100 + '%'; }
      d.addEventListener('pointerdown', e => arrastrar(e, n, r));
      lienzo.append(d);
    });
    document.querySelectorAll('.dv-pre').forEach(b => b.classList.toggle('on', +b.dataset.n === hojas.length && b.dataset.sig === firma(tree)));
    guardar();
  }
  const firma = n => esHoja(n) ? 'h' : n.d + '(' + firma(n.a) + firma(n.b) + ')';
  function arrastrar(e, n, r) {
    e.preventDefault(); raiz.classList.add('dv-arrastrando');
    const caja = lienzo.getBoundingClientRect();
    // pintar() recrea los separadores: el seguimiento va en el lienzo, no en el separador
    const mover = ev => {
      const v = n.d === 'x' ? ((ev.clientX - caja.left) / caja.width - r.x) / r.w : ((ev.clientY - caja.top) / caja.height - r.y) / r.h;
      n.r = Math.max(.12, Math.min(.88, v)); pintar();
    };
    const fin = () => { document.removeEventListener('pointermove', mover); document.removeEventListener('pointerup', fin); document.removeEventListener('pointercancel', fin); raiz.classList.remove('dv-arrastrando'); guardar(); };
    document.addEventListener('pointermove', mover); document.addEventListener('pointerup', fin); document.addEventListener('pointercancel', fin);
  }

  // ---- abrir / cerrar ----
  function iconoPreset(p) {
    const hs = [], n = p[1], idsF = Array.from({ length: n }, (_, i) => i); disponer(p[2](idsF), { x: 0, y: 0, w: 1, h: 1 }, hs);
    return '<svg viewBox="0 0 28 20" width="28" height="20" aria-hidden="true">' + hs.map(h => '<rect x="' + (h.r.x * 28 + 1) + '" y="' + (h.r.y * 20 + 1) + '" width="' + (h.r.w * 28 - 2) + '" height="' + (h.r.h * 20 - 2) + '" rx="2"/>').join('') + '</svg>';
  }
  function construir() {
    raiz = el('div', 'dv-raiz'); raiz.setAttribute('role', 'dialog'); raiz.setAttribute('aria-label', 'Divisor de ventana');
    barra = el('div', 'dv-barra', '<strong>Divisor de ventana</strong>');
    const pres = el('span', 'dv-presets');
    PRESETS.forEach(p => {
      const sig2 = firma(p[2](Array.from({ length: p[1] }, (_, i) => i)));
      const b = el('button', 'dv-pre', iconoPreset(p)); b.type = 'button'; b.title = p[0]; b.dataset.n = p[1]; b.dataset.sig = sig2.replace(/\d+/g, 'h');
      b.onclick = () => aplicarPreset(p); pres.append(b);
    });
    const ayuda = el('span', 'dv-nota', 'Arrastra las divisiones para ajustar el tamaño · ' + ico('fa-table-columns') + ' / ' + ico('fa-table-list') + ' dividen una ventana');
    const salir = el('button', 'dv-salir', ico('fa-xmark') + ' Cerrar divisor'); salir.type = 'button'; salir.onclick = cerrar;
    barra.append(pres, ayuda, salir);
    lienzo = el('div', 'dv-lienzo');
    raiz.append(barra, lienzo);
  }
  function abrir() {
    if (raiz) return;
    leerHerramientas(); cargar(); construir(); document.body.append(raiz); document.body.classList.add('dv-abierto');
    // quita paneles guardados sin hoja en el árbol
    const vivos = new Set(ids(tree)); Object.keys(panes).forEach(k => { if (!vivos.has(+k)) delete panes[k]; });
    pintar(); document.addEventListener('keydown', alTecla);
  }
  function cerrar() {
    if (!raiz) return; guardar(); iframes.clear(); raiz.remove(); raiz = null; document.body.classList.remove('dv-abierto'); document.removeEventListener('keydown', alTecla);
  }
  const alTecla = e => { if (e.key === 'Escape' && !document.activeElement?.closest('select')) cerrar(); };

  function botonFlotante() {
    const b = el('button', 'dv-boton', ico('fa-table-columns')); b.type = 'button'; b.id = 'dv-boton';
    b.title = 'Dividir la vista en 2 o más ventanas'; b.setAttribute('aria-label', b.title);
    if (/ProjectWebDesktop/.test(navigator.userAgent)) b.classList.add('dv-escritorio');   // deja libre el globo del chat
    b.onclick = () => (raiz ? cerrar() : abrir());
    document.body.append(b);
  }
  if (document.querySelector('.app-container')) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', botonFlotante); else botonFlotante(); }
})();
