'use strict';
// Diálogo de confirmación común de la app de escritorio (en lugar de confirm() del navegador).
// window.confirmarDesktop({ titulo, mensaje, aceptar, cancelar, peligro }) -> Promise<boolean>
(() => {
  let fondo = null, titulo = null, mensaje = null, aceptar = null, cancelar = null;
  let resolver = null, anterior = null;

  const construir = () => {
    if (fondo) return;
    fondo = document.createElement('div');
    fondo.className = 'dlg-fondo';
    fondo.hidden = true;
    fondo.innerHTML = '<div class="dlg-caja" role="dialog" aria-modal="true" aria-labelledby="dlg-titulo" aria-describedby="dlg-mensaje">'
      + '<div class="dlg-icono" aria-hidden="true">!</div>'
      + '<h2 class="dlg-titulo" id="dlg-titulo"></h2>'
      + '<p class="dlg-mensaje" id="dlg-mensaje"></p>'
      + '<div class="dlg-acciones"><button type="button" class="sec" data-cancelar>Cancelar</button><button type="button" data-aceptar>Aceptar</button></div>'
      + '</div>';
    document.body.appendChild(fondo);
    titulo = fondo.querySelector('.dlg-titulo');
    mensaje = fondo.querySelector('.dlg-mensaje');
    aceptar = fondo.querySelector('[data-aceptar]');
    cancelar = fondo.querySelector('[data-cancelar]');

    const cerrar = (ok) => {
      fondo.hidden = true;
      fondo.classList.remove('abierto');
      const r = resolver;
      resolver = null;
      if (anterior && typeof anterior.focus === 'function') anterior.focus();
      anterior = null;
      if (r) r(ok);
    };

    cancelar.addEventListener('click', () => cerrar(false));
    aceptar.addEventListener('click', () => cerrar(true));
    fondo.addEventListener('click', (e) => { if (e.target === fondo) cerrar(false); });
    fondo.addEventListener('keydown', (e) => {
      if (e.key !== 'Tab') return;
      if (e.shiftKey && document.activeElement === cancelar) { e.preventDefault(); aceptar.focus(); }
      else if (!e.shiftKey && document.activeElement === aceptar) { e.preventDefault(); cancelar.focus(); }
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && fondo && !fondo.hidden) { e.preventDefault(); cerrar(false); }
    });
  };

  window.confirmarDesktop = (opciones = {}) => new Promise((resolve) => {
    construir();
    if (resolver) {
      const previo = resolver;
      resolver = null;
      previo(false);
    }
    anterior = document.activeElement;
    titulo.textContent = opciones.titulo || '¿Continuar?';
    mensaje.textContent = opciones.mensaje || '';
    aceptar.textContent = opciones.aceptar || 'Aceptar';
    cancelar.textContent = opciones.cancelar || 'Cancelar';
    fondo.dataset.tono = opciones.peligro ? 'peligro' : 'info';
    fondo.hidden = false;
    fondo.classList.add('abierto');
    resolver = resolve;
    aceptar.focus();
  });
})();
