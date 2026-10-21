// ============================================================
// Paginador de listas
// - Un <table> o contenedor con data-paginar="N" muestra N registros por página (el N lo fija Ajustes del proyecto, 10 por defecto).
// - En una tabla pagina las filas de su <tbody>; en cualquier otro contenedor, sus hijos directos (data-paginar-items="selector" para elegir).
// - Respeta el buscador de listas: las filas con [hidden] no cuentan, y al cambiar el filtro se vuelve a paginar.
// ============================================================
(() => {
  const crear = (cont) => {
    const por = Math.max(1, parseInt(cont.dataset.paginar, 10) || 10);
    const tabla = cont.tagName === 'TABLE';
    const base = tabla ? cont.tBodies[0] : cont;
    if (!base) return;
    const todos = () => [...(tabla ? base.rows : cont.querySelectorAll(cont.dataset.paginarItems || ':scope > *'))]
      .filter((el) => !el.classList.contains('sin-resultados') && !el.classList.contains('paginador') && !el.querySelector(':scope > td[colspan]'));
    let pagina = 1;
    const nav = document.createElement('nav');
    nav.className = 'paginador';
    nav.setAttribute('aria-label', 'Paginación');
    (tabla ? (cont.parentElement.classList.contains('tabla-wrapper') ? cont.parentElement : cont) : cont).after(nav);

    const pintar = () => {
      const elegibles = todos().filter((el) => !el.hidden);
      const paginas = Math.max(1, Math.ceil(elegibles.length / por));
      pagina = Math.min(pagina, paginas);
      const ini = (pagina - 1) * por;
      todos().forEach((el) => el.classList.remove('pag-oculta'));
      elegibles.forEach((el, i) => el.classList.toggle('pag-oculta', i < ini || i >= ini + por));
      if (elegibles.length <= por) { nav.hidden = true; return; }
      nav.hidden = false;
      const nums = [];
      for (let p = 1; p <= paginas; p++) if (p === 1 || p === paginas || Math.abs(p - pagina) <= 1) nums.push(p);
      let html = `<span class="pag-info">${ini + 1}–${Math.min(ini + por, elegibles.length)} de ${elegibles.length}</span>`;
      html += `<button type="button" data-p="${pagina - 1}" ${pagina === 1 ? 'disabled' : ''} aria-label="Página anterior">‹</button>`;
      let previo = 0;
      nums.forEach((p) => {
        if (p - previo > 1) html += '<span class="pag-sep">…</span>';
        html += `<button type="button" data-p="${p}" class="${p === pagina ? 'activa' : ''}" ${p === pagina ? 'aria-current="page"' : ''}>${p}</button>`;
        previo = p;
      });
      html += `<button type="button" data-p="${pagina + 1}" ${pagina === paginas ? 'disabled' : ''} aria-label="Página siguiente">›</button>`;
      nav.innerHTML = html;
    };
    nav.addEventListener('click', (e) => {
      const b = e.target.closest('button[data-p]');
      if (!b || b.disabled) return;
      pagina = parseInt(b.dataset.p, 10);
      pintar();
      (tabla ? cont : nav).scrollIntoView({ block: 'nearest' });
    });
    let t;
    new MutationObserver(() => { clearTimeout(t); t = setTimeout(() => { pagina = 1; pintar(); }, 30); })
      .observe(base, { attributes: true, attributeFilter: ['hidden'], subtree: true });
    pintar();
  };

  document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-paginar]').forEach(crear));
})();
