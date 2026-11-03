/* Modos de un lienzo (Ver · Acomodar · Dibujar) sin recargar la página.
   - Los botones/enlaces llevan data-lz-modo="ver|acomodar|dibujar".
   - Los paneles llevan data-lz-panel="ver|acomodar|dibujar" (el resto se oculta con [hidden]).
   - El cuerpo recibe data-modo-lienzo="<modo>" para que el CSS y los motores sepan cuál manda.
   - data-lz-url (opcional) actualiza la barra de direcciones con history.replaceState.
   - Cada cambio emite el evento «vp:modo» con { modo } (los motores re-miden su lienzo).
   Uso: public/index.php carga este archivo en las vistas que tengan modos. */
(function () {
  'use strict';

  const modos = () => Array.from(document.querySelectorAll('[data-lz-modo]'));
  const paneles = modo => Array.from(document.querySelectorAll('[data-lz-panel]'))
    .filter(p => (p.dataset.lzPanel || '').split(/\s+/).includes(modo));
  const panel = modo => paneles(modo)[0] || null;

  function marcar(modo) {
    modos().forEach(b => {
      const on = b.dataset.lzModo === modo;
      b.classList.toggle('activo', on);
      if (b.tagName === 'BUTTON') b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    document.querySelectorAll('[data-lz-panel]').forEach(p => {
      p.hidden = !(p.dataset.lzPanel || '').split(/\s+/).includes(modo);
    });
  }

  function ir(modo, { url = null, silencioso = false } = {}) {
    if (!modo) return;
    document.body.dataset.modoLienzo = modo;
    marcar(modo);
    if (url) { try { history.replaceState(null, '', url); } catch (_) { /* file:// o navegador viejo */ } }
    if (!silencioso) document.dispatchEvent(new CustomEvent('vp:modo', { detail: { modo: modo } }));
  }

  document.addEventListener('click', e => {
    const b = e.target.closest('[data-lz-modo]');
    if (!b || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
    const modo = b.dataset.lzModo;
    if (!modo || !panel(modo)) return;   // sin panel propio: es un enlace normal (otra herramienta)
    e.preventDefault();
    ir(modo, { url: b.dataset.lzUrl || (b.tagName === 'A' ? b.getAttribute('href') : null) });
  });

  // Estado inicial: el servidor pinta data-modo-lienzo y los [hidden]; si no, se deduce de la URL.
  document.addEventListener('DOMContentLoaded', () => {
    let modo = document.body.dataset.modoLienzo || '';
    if (!modo) {
      try { modo = new URLSearchParams(location.search).get('modo') || ''; } catch (_) { /* nada */ }
    }
    if (!modo) modo = document.querySelector('[data-lz-modo].activo')?.dataset.lzModo || 'ver';
    if (panel(modo)) ir(modo, { silencioso: true });
  });

  window.LienzoModos = { ir, actual: () => document.body.dataset.modoLienzo || '' };
})();
