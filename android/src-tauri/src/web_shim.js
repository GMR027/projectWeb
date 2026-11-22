/* Project Web · adaptador para las herramientas web abiertas dentro de la app Android.
 *
 * Se inyecta SOLO en la ventana de la herramienta (HerramientaWebActivity); la web normal
 * no lo carga. Su trabajo es cubrir lo que el WebView de Android no hace por sí solo:
 *   • abrir en la misma pestaña los enlaces target="_blank" y window.open;
 *   • mandar al puente nativo las descargas blob:/data: y los enlaces con download;
 *   • interceptar las exportaciones POST del Centro de Exportación;
 *   • pedir a la app que descargue los PDFs que el WebView no puede mostrar;
 *   • ofrecer un acceso visible para volver a la app nativa.
 *
 * Solo agrega controles dentro de esta ventana; no cambia el HTML ni el JavaScript
 * de la plataforma para los usuarios que entran desde un navegador normal.
 */
(function () {
  'use strict';

  if (window.__pwAppShim) return;
  window.__pwAppShim = true;

  var LIMITE_BYTES = 40 * 1024 * 1024;   // 40 MB; arriba de esto se sugiere el navegador

  // La app ya tiene su propio logout y el aviso de descarga del APK sobra aquí.
  // Los accesos para volver a la app viven solo en esta ventana.
  try {
    var estilo = document.createElement('style');
    estilo.textContent =
      'form[action="/logout"], .banner-android { display: none !important; }' +
      '.pw-volver-app-nav { width: 100%; border: 0; background: var(--primary-soft, rgba(249,115,22,.12)); color: var(--text, #e2e8f0); font-family: inherit; text-align: left; cursor: pointer; }' +
      '.pw-volver-app-nav:hover { background: var(--primary-glow, rgba(249,115,22,.25)); color: var(--text, #e2e8f0); }' +
      '.pw-volver-app-nav .nav-icon { color: var(--primary, #f97316); }' +
      '.pw-volver-app-flotante { position: fixed; top: .9rem; right: .9rem; z-index: 950; display: inline-flex; align-items: center; gap: .6rem; padding: .9rem 1.3rem; border: 1px solid var(--primary-line, rgba(249,115,22,.45)); border-radius: 10rem; background: var(--primary, #f97316); color: var(--on-primary, #0f172a); font-family: inherit; font-size: 1.3rem; font-weight: 600; line-height: 1; box-shadow: 0 6px 18px rgba(2,6,23,.4); cursor: pointer; }' +
      '.pw-volver-app-flotante[hidden] { display: none !important; }' +
      '.pw-volver-app-flotante:active { transform: scale(.97); }' +
      '@media (max-width: 48rem) { .pw-volver-app-flotante { width: 4.4rem; height: 4.4rem; padding: 0; justify-content: center; } .pw-volver-app-flotante span { display: none; } }';
    (document.head || document.documentElement).appendChild(estilo);
  } catch (_) { /* sin estilos: la página sigue funcionando */ }

  /** Cierra la herramienta y devuelve el control a MainActivity (la app nativa). */
  function volverALaApp() {
    var p = puente();
    if (p && p.volver) { p.volver(); return; }
    if (history.length > 1) history.back();
    else location.href = '/dashboard';
  }

  function puente() {
    return window.ProjectWebAndroid || null;
  }

  function aviso(texto) {
    var p = puente();
    if (p && p.error) p.error(String(texto || 'No se pudo completar la descarga.'));
  }

  function esBlob(url) {
    return /^(blob:|data:)/i.test(url || '');
  }

  function guardarBlob(url, nombre, tipo) {
    var p = puente();
    if (!p || !p.guardar) { location.href = url; return; }
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('No se pudo leer el archivo.');
        return r.blob();
      })
      .then(function (b) {
        if (b.size > LIMITE_BYTES) {
          throw new Error('El archivo es muy grande para guardarlo desde la app; ábrelo en el navegador.');
        }
        var lector = new FileReader();
        lector.onload = function () {
          var d = String(lector.result || '');
          var coma = d.indexOf(',');
          p.guardar(coma >= 0 ? d.slice(coma + 1) : '', nombre, b.type || tipo || 'application/octet-stream');
        };
        lector.onerror = function () { aviso('No se pudo leer el archivo.'); };
        lector.readAsDataURL(b);
      })
      .catch(function (e) { aviso(e && e.message ? e.message : e); });
  }

  function guardarRespuesta(r, nombre) {
    var p = puente();
    if (!p || !p.guardar) throw new Error('El puente de descargas no está disponible.');
    return r.blob().then(function (b) {
      if (b.size > LIMITE_BYTES) {
        throw new Error('El archivo es muy grande para guardarlo desde la app; ábrelo en el navegador.');
      }
      var lector = new FileReader();
      lector.onload = function () {
        var d = String(lector.result || '');
        var coma = d.indexOf(',');
        p.guardar(coma >= 0 ? d.slice(coma + 1) : '', nombre, b.type || 'application/octet-stream');
      };
      lector.onerror = function () { aviso('No se pudo leer el archivo.'); };
      lector.readAsDataURL(b);
    });
  }

  function nombreDeEnlace(a) {
    var n = (a.getAttribute('download') || '').trim();
    return n || 'descarga';
  }

  function esPdf(url) {
    if (/(\.pdf($|[?#])|(\/archivos\/ver|\/normativa\/pdf|\/reportes\/parte\/pdf|\/punch\/pdf|\/rfi\/pdf|\/calidad\/expediente\/pdf|\/materiales\/ficha|\/redactor\/descargar|\/sheetflow\/descargar|\/ensamblador-documentos\/descargar)(\?|$))/i.test(url || '')) return true;
    return /[?&](inline=1|formato=pdf)(&|$)/i.test(url || '');
  }

  // Un solo manejador de clics. El orden importa: descarga → PDF → target="_blank".
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented) return;
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) return;
    var href = a.href || '';
    if (!href || /^(javascript:|#)/i.test(href)) return;

    if (a.hasAttribute('download') || esBlob(href)) {
      e.preventDefault();
      if (esBlob(href)) guardarBlob(href, nombreDeEnlace(a), '');
      else location.href = href;   // lo toma el DownloadListener nativo
      return;
    }

    if (esPdf(href)) {
      var p = puente();
      if (p && p.abrirPdf) { e.preventDefault(); p.abrirPdf(href); return; }
    }

    if (a.target && a.target !== '_self') {
      e.preventDefault();
      location.href = href;   // la política nativa decide si se queda o se abre fuera
    }
  }, true);

  // window.open: misma pestaña para el sitio; el resto lo decide la política nativa.
  var abrirOriginal = window.open;
  window.open = function (url, nombre, caracteristicas) {
    if (!url) return null;
    try {
      var u = new URL(url, location.href);
      if (u.protocol === 'http:' || u.protocol === 'https:') {
        if (esPdf(u.href)) {
          var p = puente();
          if (p && p.abrirPdf) { p.abrirPdf(u.href); return null; }
        }
        location.href = u.href;
        return null;
      }
      if (u.protocol === 'blob:' || u.protocol === 'data:') {
        guardarBlob(u.href, 'descarga', '');
        return null;
      }
    } catch (_) { /* URL no válida: se deja al WebView */ }
    return abrirOriginal ? abrirOriginal.call(window, url, nombre, caracteristicas) : null;
  };

  // window.print() no existe en el WebView; se manda al cuadro de impresión de Android.
  window.print = function () {
    var p = puente();
    if (p && p.imprimir) p.imprimir(document.title || 'Project Web');
  };

  // Formularios que responden con un archivo (Centro de Exportación).
  // Se escucha en burbuja para que el listener propio de la página agregue antes los
  // campos de nomenclatura; luego se evita la navegación y se guarda la respuesta.
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || !f.matches || e.defaultPrevented) return;
    if (!f.matches('form[action="/exportar/generar"], form[action="/exportar/todo"]')) return;
    e.preventDefault();

    var accion = f.getAttribute('action') || location.pathname;
    var fd = new FormData(f);
    fetch(accion, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) {
        var tipo = r.headers.get('Content-Type') || '';
        var disp = r.headers.get('Content-Disposition') || '';
        if (!r.ok || (!/attachment/i.test(disp) && !/zip|pdf|sheet|octet|xml|text\/csv/i.test(tipo))) {
          throw new Error('No se pudo generar el archivo. Revisa los datos e inténtalo de nuevo.');
        }
        var m = /filename="([^"]+)"/i.exec(disp) || /filename\*=UTF-8''([^;]+)/i.exec(disp);
        var nombre = 'exportacion';
        if (m) { try { nombre = decodeURIComponent(m[1]); } catch (_) { nombre = m[1]; } }
        return guardarRespuesta(r, nombre);
      })
      .catch(function (err) { aviso(err && err.message ? err.message : err); });
  }, false);

  // El logout web cerraría la sesión del WebView y dejaría la app nativa abierta:
  // mejor volver a la app; el logout real está en Ajustes de la app.
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || !f.matches || !f.matches('form[action="/logout"]')) return;
    e.preventDefault();
    var p = puente();
    if (p && p.volver) p.volver();
    else location.href = '/dashboard';
  }, true);

  // Un formulario con target="_blank" tampoco abriría una ventana nueva en Android.
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f && f.target && f.target !== '_self') f.target = '_self';
  }, true);

  // Accesos visibles para regresar a la app nativa:
  //  • botón en la cabecera, en el lugar del logout que ya está oculto;
  //  • entrada al inicio del menú lateral;
  //  • botón flotante cuando la cabecera sale de la pantalla (o si la página no tiene).
  function iniciarVolver() {
    if (document.getElementById('pw-volver-app-flotante')) return;

    var cabecera = document.querySelector('.topbar-right');
    if (cabecera && !cabecera.querySelector('.pw-volver-app-cabecera')) {
      var botonCabecera = document.createElement('button');
      botonCabecera.type = 'button';
      botonCabecera.className = 'btn btn-ghost btn-sm topbar-salir pw-volver-app-cabecera';
      botonCabecera.title = 'Volver a la app';
      botonCabecera.setAttribute('aria-label', 'Volver a la app');
      botonCabecera.innerHTML = '<i class="fa-solid fa-arrow-left"></i> <span class="hide-m">Volver a la app</span>';
      botonCabecera.addEventListener('click', volverALaApp);
      var salir = cabecera.querySelector('form[action="/logout"]');
      if (salir && salir.parentNode === cabecera) cabecera.insertBefore(botonCabecera, salir);
      else cabecera.appendChild(botonCabecera);
    }

    var menu = document.querySelector('.sidebar-nav');
    if (menu && !menu.querySelector('.pw-volver-app-nav')) {
      var itemMenu = document.createElement('button');
      itemMenu.type = 'button';
      itemMenu.className = 'nav-link pw-volver-app-nav';
      itemMenu.innerHTML = '<i class="fa-solid fa-arrow-left nav-icon"></i><span>Volver a la app</span>';
      itemMenu.addEventListener('click', volverALaApp);
      menu.insertBefore(itemMenu, menu.firstChild);
    }

    if (!document.body) return;
    var flotante = document.createElement('button');
    flotante.type = 'button';
    flotante.id = 'pw-volver-app-flotante';
    flotante.className = 'pw-volver-app-flotante';
    flotante.title = 'Volver a la app';
    flotante.setAttribute('aria-label', 'Volver a la app');
    flotante.innerHTML = '<i class="fa-solid fa-house"></i><span>Volver a la app</span>';
    flotante.addEventListener('click', volverALaApp);
    flotante.hidden = true;
    document.body.appendChild(flotante);

    var barra = document.querySelector('.topbar');
    if (!barra) {
      flotante.hidden = false;   // páginas sin cabecera (visor, login…)
      return;
    }

    if ('IntersectionObserver' in window) {
      var observador = new IntersectionObserver(function (entradas) {
        flotante.hidden = !!(entradas[0] && entradas[0].isIntersecting);
      }, { threshold: 0 });
      observador.observe(barra);
    } else {
      var revisar = function () {
        flotante.hidden = barra.getBoundingClientRect().bottom > 0;
      };
      window.addEventListener('scroll', revisar, { passive: true });
      revisar();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarVolver);
  } else {
    iniciarVolver();
  }
})();
