// ============================================================
// Burbujas de ayuda: cualquier elemento con data-ayuda="qué hace" muestra, a los 200 ms de pasar el cursor
// (o de enfocarlo con el teclado), una burbuja con la descripción y, si trae data-manual="/manual#seccion",
// un botón que abre el manual en esa sección. data-ayuda-titulo agrega un encabezado a la burbuja.
// La burbuja se queda abierta mientras el cursor esté sobre ella, para poder pulsar el botón.
// ============================================================
(() => {
  const RETARDO = 200;       // ms antes de mostrar
  const CIERRE = 150;        // ms de gracia para pasar del elemento a la burbuja
  let burbuja = null, objetivo = null, tMostrar = 0, tOcultar = 0;

  const crear = () => {
    const b = document.createElement('div');
    b.className = 'burbuja-ayuda';
    b.setAttribute('role', 'tooltip');
    b.id = 'burbuja-ayuda';
    b.hidden = true;
    b.addEventListener('mouseenter', () => clearTimeout(tOcultar));
    b.addEventListener('mouseleave', () => programarOcultar());
    document.body.appendChild(b);
    return b;
  };

  const posicionar = () => {
    if (!burbuja || !objetivo) return;
    const r = objetivo.getBoundingClientRect(), w = burbuja.offsetWidth, h = burbuja.offsetHeight, m = 8;
    // Preferimos a la derecha del elemento (menú lateral); si no cabe, debajo; si tampoco, arriba
    let x = r.right + m, y = r.top + r.height / 2 - h / 2, lado = 'derecha';
    if (x + w > innerWidth - m) {
      x = Math.min(Math.max(m, r.left + r.width / 2 - w / 2), innerWidth - w - m);
      y = r.bottom + m; lado = 'abajo';
      if (y + h > innerHeight - m) { y = r.top - h - m; lado = 'arriba'; }
    }
    y = Math.min(Math.max(m, y), innerHeight - h - m);
    burbuja.style.left = x + 'px';
    burbuja.style.top = y + 'px';
    burbuja.dataset.lado = lado;
  };

  const mostrar = el => {
    if (!document.body.contains(el)) return;
    burbuja ||= crear();
    objetivo = el;
    burbuja.textContent = '';
    if (el.dataset.ayudaTitulo) {
      const t = document.createElement('strong');
      t.className = 'burbuja-titulo';
      t.textContent = el.dataset.ayudaTitulo;
      burbuja.appendChild(t);
    }
    const p = document.createElement('p');
    p.textContent = el.dataset.ayuda;
    burbuja.appendChild(p);
    if (el.dataset.manual) {
      const a = document.createElement('a');
      a.className = 'burbuja-manual';
      a.href = el.dataset.manual;
      a.innerHTML = '<i class="fa-solid fa-book-open"></i> Ver en el manual';
      burbuja.appendChild(a);
    }
    burbuja.hidden = false;
    el.setAttribute('aria-describedby', burbuja.id);
    posicionar();
  };

  const ocultar = () => {
    clearTimeout(tMostrar); clearTimeout(tOcultar);
    if (objetivo) objetivo.removeAttribute('aria-describedby');
    if (burbuja) burbuja.hidden = true;
    objetivo = null;
  };
  const programarOcultar = () => { clearTimeout(tOcultar); tOcultar = setTimeout(ocultar, CIERRE); };

  const programar = el => {
    clearTimeout(tMostrar); clearTimeout(tOcultar);
    if (objetivo === el && burbuja && !burbuja.hidden) return;
    tMostrar = setTimeout(() => mostrar(el), RETARDO);
  };

  const de = e => e.target instanceof Element ? e.target.closest('[data-ayuda]') : null;

  document.addEventListener('mouseover', e => {
    if (e.pointerType === 'touch') return;
    const el = de(e);
    if (el) programar(el);
  });
  document.addEventListener('mouseout', e => {
    const el = de(e);
    if (!el || el.contains(e.relatedTarget)) return;
    clearTimeout(tMostrar);
    programarOcultar();
  });
  document.addEventListener('focusin', e => { const el = de(e); if (el) programar(el); });
  document.addEventListener('focusout', e => {
    const el = de(e);
    if (el && !(burbuja && burbuja.contains(e.relatedTarget))) { clearTimeout(tMostrar); programarOcultar(); }
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') ocultar(); });
  document.addEventListener('click', e => { if (!e.target.closest?.('.burbuja-ayuda')) ocultar(); }, true);
  addEventListener('scroll', ocultar, true);
  addEventListener('resize', ocultar);
})();
