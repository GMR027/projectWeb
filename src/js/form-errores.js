/* Errores de formulario junto al campo: lee #errores-campo (lo pinta templates/alertas.php),
   marca el campo con aria-invalid y añade el mensaje enlazado con aria-describedby. */
(function () {
  'use strict';
  var origen = document.getElementById('errores-campo');
  if (!origen) return;
  var errores;
  try { errores = JSON.parse(origen.textContent || '{}'); } catch (e) { return; }
  if (!errores || typeof errores !== 'object') return;

  var escapar = function (t) {
    return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var primero = null;

  var buscarCampo = function (nombre) {
    var n = (window.CSS && CSS.escape) ? CSS.escape(nombre) : nombre.replace(/"/g, '\\"');
    return document.querySelector('[name="' + n + '"], [name="' + n + '[]"], #' + n);
  };

  Object.keys(errores).forEach(function (campo) {
    var input = buscarCampo(campo);
    if (!input) return;
    var envoltura = input.closest('.campo, .campo-check, .campo-archivo, .form-grupo, .campo-form') || input.parentElement;
    var id = 'err-campo-' + campo.replace(/[^a-zA-Z0-9_-]/g, '-');
    var anterior = document.getElementById(id);
    if (anterior) anterior.remove();
    var aviso = document.createElement('p');
    aviso.className = 'campo-error';
    aviso.id = id;
    aviso.setAttribute('role', 'alert');
    aviso.innerHTML = escapar(errores[campo]);
    if (envoltura && envoltura !== input) envoltura.insertAdjacentElement('afterend', aviso);
    else input.insertAdjacentElement('afterend', aviso);
    input.setAttribute('aria-invalid', 'true');
    var descrito = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    if (descrito.indexOf(id) < 0) descrito.push(id);
    input.setAttribute('aria-describedby', descrito.join(' '));
    if (!primero) primero = input;
    // Al corregir el campo desaparece el aviso y el estado de error
    var limpiar = function () {
      input.removeAttribute('aria-invalid');
      var ids = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (x) { return x && x !== id; });
      if (ids.length) input.setAttribute('aria-describedby', ids.join(' '));
      else input.removeAttribute('aria-describedby');
      var p = document.getElementById(id);
      if (p) p.remove();
      input.removeEventListener('input', limpiar);
      input.removeEventListener('change', limpiar);
    };
    input.addEventListener('input', limpiar);
    input.addEventListener('change', limpiar);
  });

  if (primero && typeof primero.focus === 'function') {
    primero.focus({ preventScroll: false });
    if (typeof primero.scrollIntoView === 'function') primero.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }
})();
