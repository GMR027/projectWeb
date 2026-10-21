// ============================================================
// Copiar y pegar fechas con Ctrl+C / Ctrl+V
// ------------------------------------------------------------
// - Ctrl+C sobre una fecha visible (tablas, Gantt, programa,
//   vPlan, etc.) copia el día completo en formato ISO (AAAA-MM-DD),
//   aunque solo se haya seleccionado una parte del texto.
// - Ctrl+V sobre cualquier campo de fecha (input type="date" o
//   campos de texto que son fechas) acepta AAAA-MM-DD, DD/MM/AAAA,
//   DD-MM-AA, «20 de octubre de 2026», «20 oct 2026»… y dispara
//   input/change para que la página guarde como siempre.
// - No interfiere con SheetFlow ni con el Redactor (tienen su
//   propia copia/pegado de celdas y texto).
// ============================================================
(() => {
  'use strict';

  // ---------- Lectura de fechas ----------
  const MESES = {};
  [
    [1, 'enero ene january jan'],
    [2, 'febrero feb february'],
    [3, 'marzo mar march'],
    [4, 'abril abr april apr'],
    [5, 'mayo may'],
    [6, 'junio jun june'],
    [7, 'julio jul july'],
    [8, 'agosto ago august aug'],
    [9, 'septiembre setiembre sep sept september'],
    [10, 'octubre oct october'],
    [11, 'noviembre nov november'],
    [12, 'diciembre dic december dec'],
  ].forEach(([numero, nombres]) => nombres.split(' ').forEach((n) => { MESES[n] = numero; }));

  const DIAS = 'lunes|martes|miercoles|jueves|viernes|sabado|domingo|monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun';

  const dos = (n) => String(n).padStart(2, '0');

  /** Comprueba que la fecha exista (sin 31 de febrero) y la devuelve como AAAA-MM-DD. */
  function isoValido(anio, mes, dia) {
    let a = Number(anio);
    const m = Number(mes);
    const d = Number(dia);
    if (!Number.isInteger(a) || !Number.isInteger(m) || !Number.isInteger(d)) return null;
    if (a < 100) a = a >= 70 ? 1900 + a : 2000 + a;
    if (a < 1900 || a > 2200 || m < 1 || m > 12 || d < 1 || d > 31) return null;
    const fecha = new Date(Date.UTC(a, m - 1, d));
    if (fecha.getUTCFullYear() !== a || fecha.getUTCMonth() !== m - 1 || fecha.getUTCDate() !== d) return null;
    return `${a}-${dos(m)}-${dos(d)}`;
  }

  const sinAcentos = (t) => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

  function mesDe(nombre) {
    return MESES[sinAcentos(nombre).replace(/\.$/, '')] || null;
  }

  /**
   * Convierte a AAAA-MM-DD lo que suele haber en el portapapeles:
   * 2026-10-20 · 20/10/2026 · 20-10-26 · 20.10.2026 · 20 de octubre de 2026 ·
   * 20 oct 2026 · lunes, 20 de octubre de 2026 · October 20, 2026 · 2026-10-20T14:30:00Z.
   * Devuelve null si no es una fecha real.
   */
  function parsearFecha(texto) {
    if (texto == null) return null;
    let t = String(texto).replace(/\u00a0/g, ' ').trim();
    if (!t || t.length > 80) return null;

    t = sinAcentos(t).replace(/\s+/g, ' ');
    t = t.replace(/[.,;:]+$/, '').trim();
    // Quita la hora de un ISO completo o de un «20/10/2026 14:30».
    t = t.replace(/[t ]+\d{1,2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:z|[+-]\d{2}:?\d{2})?$/, '').trim();
    // Quita el día de la semana («lunes, », «martes 20…») y artículos («el 20…»).
    t = t.replace(new RegExp(`^(?:${DIAS})[,.]?\\s+`), '').replace(/^(?:el|del)\s+/, '').trim();

    // ISO: 2026-10-20
    let m = /^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/.exec(t);
    if (m) return isoValido(m[1], m[2], m[3]);

    // Numérica: 20/10/2026 · 20-10-26 · 20.10.2026 · 10/20/2026
    m = /^(\d{1,2})([\/.-])(\d{1,2})\2(\d{2,4})$/.exec(t);
    if (m) {
      let dia = Number(m[1]);
      let mes = Number(m[3]);
      if (dia > 31 || mes > 31) return null;
      if (mes > 12 && dia <= 12) [dia, mes] = [mes, dia];   // venía en formato mes/día
      return isoValido(m[4], mes, dia);
    }

    // Con nombre de mes: «20 de octubre de 2026», «20 oct 2026», «20-oct-26», «20/oct/2026»
    m = /^(\d{1,2})(?:\s+de\s+|\s+del\s+|[\s\/.-]+)([a-z]{3,})\.?(?:\s+de\s+|\s+del\s+|[\s\/.-]+)(\d{2,4})$/.exec(t);
    if (m) {
      const mes = mesDe(m[2]);
      if (mes) return isoValido(m[3], mes, m[1]);
    }

    // Nombre de mes primero: «octubre 20, 2026», «October 20, 2026»
    m = /^([a-z]{3,})\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{2,4})$/.exec(t);
    if (m) {
      const mes = mesDe(m[1]);
      if (mes) return isoValido(m[3], mes, m[2]);
    }

    return null;
  }

  const isoALocal = (iso) => {
    const p = String(iso).split('-');
    return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : iso;
  };

  // ---------- Marcar las fechas visibles ----------
  // El texto que es exactamente una fecha (sin hora) queda con data-fecha="AAAA-MM-DD"
  // para poder copiarlo con un clic + Ctrl+C, sin necesidad de seleccionar el texto.
  const ETIQUETAS_IGNORADAS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT', 'SELECT', 'OPTION', 'BUTTON', 'SVG', 'CANVAS', 'CODE', 'PRE']);
  const ZONAS_IGNORADAS = '#sf-area, .sf-area, .rd-hoja, [contenteditable=""], [contenteditable="true"], [data-sin-fechas]';

  function zonaIgnorada(el) {
    return !!(el && el.closest && el.closest(ZONAS_IGNORADAS));
  }

  function marcarNodoTexto(nodo) {
    const padre = nodo.parentElement;
    if (!padre || ETIQUETAS_IGNORADAS.has(padre.tagName)) return;
    const texto = nodo.nodeValue.trim();
    // Solo fechas sueltas: si lleva hora se deja el copiado normal del texto.
    if (!texto || texto.length > 42 || /\d{1,2}:\d{2}/.test(texto)) return;
    const iso = parsearFecha(texto);
    if (!iso) return;
    if (zonaIgnorada(padre)) return;
    if (!padre.dataset.fecha) padre.classList.add('fecha-copiable');
    padre.dataset.fecha = iso;   // si el texto cambió, se actualiza el valor copiable
  }

  /** Quita la marca si el texto de la fecha cambió y ya no es una fecha. */
  function refrescarPadre(padre) {
    if (!padre || padre.dataset.fecha === undefined) return;
    const textos = Array.from(padre.childNodes)
      .filter((n) => n.nodeType === Node.TEXT_NODE)
      .map((n) => n.nodeValue.trim())
      .filter(Boolean);
    const iso = textos.map(parsearFecha).find(Boolean);
    if (iso) {
      padre.dataset.fecha = iso;
    } else {
      delete padre.dataset.fecha;
      padre.classList.remove('fecha-copiable');
    }
  }

  function escanear(raiz) {
    if (!raiz) return;
    if (raiz.nodeType === Node.TEXT_NODE) { marcarNodoTexto(raiz); return; }
    if (raiz.nodeType !== Node.ELEMENT_NODE) return;
    if (ETIQUETAS_IGNORADAS.has(raiz.tagName) || zonaIgnorada(raiz)) return;

    const caminante = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT, {
      acceptNode(n) {
        const p = n.parentElement;
        if (!p || ETIQUETAS_IGNORADAS.has(p.tagName)) return NodeFilter.FILTER_REJECT;
        return NodeFilter.FILTER_ACCEPT;
      },
    });
    let nodo;
    while ((nodo = caminante.nextNode())) marcarNodoTexto(nodo);
  }

  // ---------- Copiar ----------
  let ultimaFecha = null;   // último elemento con fecha en el que se hizo clic

  document.addEventListener('pointerdown', (e) => {
    ultimaFecha = e.target instanceof Element ? e.target.closest('[data-fecha]') : null;
  }, true);

  function isoDeFechaActiva() {
    const el = document.activeElement;
    if (!el || !el.matches) return null;
    if (el.matches('input[type="date"]') && el.value) return el.value;
    if (el.matches('input[data-fecha], textarea[data-fecha]')) return parsearFecha(el.value);
    return null;
  }

  function elementoFechaSeleccion() {
    const sel = window.getSelection();
    if (!sel || !sel.rangeCount || sel.isCollapsed) return null;
    const rango = sel.getRangeAt(0);
    const inicio = rango.startContainer.nodeType === Node.ELEMENT_NODE ? rango.startContainer : rango.startContainer.parentElement;
    const el = inicio && inicio.closest ? inicio.closest('[data-fecha]') : null;
    if (!el || !el.contains(rango.endContainer)) return null;   // la selección sale de la fecha: no se toca
    return el;
  }

  function destello(el, clase) {
    if (!el || !el.classList) return;
    el.classList.add(clase);
    clearTimeout(el._fechaDestello);
    el._fechaDestello = setTimeout(() => el.classList.remove(clase), 900);
  }

  document.addEventListener('copy', (e) => {
    const activo = document.activeElement;
    // SheetFlow y Redactor manejan su propia copia: no interferir.
    if (activo && activo.closest && activo.closest(ZONAS_IGNORADAS)) return;

    let iso = isoDeFechaActiva();
    let origen = iso ? activo : null;

    if (!iso) {
      const elegido = elementoFechaSeleccion();
      if (elegido) {
        iso = elegido.dataset.fecha || parsearFecha(elegido.textContent);
        origen = elegido;
      }
    }

    // Solo se usa el último clic si no hay nada seleccionado: así Ctrl+A o una
    // selección de texto normal siguen copiando lo que el usuario eligió.
    const sel = window.getSelection();
    const haySeleccion = !!(sel && sel.rangeCount && !sel.isCollapsed);
    const enCampo = !!(activo && activo.matches && activo.matches('input, textarea, select, [contenteditable="true"], [contenteditable=""]'));
    if (!iso && !haySeleccion && !enCampo && ultimaFecha && ultimaFecha.isConnected) {
      iso = ultimaFecha.dataset.fecha || parsearFecha(ultimaFecha.textContent);
      origen = ultimaFecha;
    }
    if (!iso) return;
    if (!e.clipboardData) return;

    e.clipboardData.setData('text/plain', iso);
    e.preventDefault();
    destello(origen, 'fecha-copiada');
  });

  // ---------- Pegar ----------
  function esCampoFecha(campo) {
    if (!campo || !campo.matches) return false;
    if (campo.matches('input[type="date"]')) return true;
    if (campo.dataset && campo.dataset.fecha !== undefined) return true;
    const tipo = (campo.getAttribute('type') || 'text').toLowerCase();
    if (tipo !== 'text' && tipo !== 'search') return false;
    const pista = [campo.name, campo.id, campo.getAttribute('aria-label'), campo.placeholder].filter(Boolean).join(' ');
    if (/(hora|time|duracion|duración)/i.test(pista)) return false;
    if (/dd[\/.-]mm[\/.-](?:aaaa|aa)|aaaa-mm-dd/i.test(campo.placeholder || '')) return true;
    return /(?:^|[^a-z])(fecha|date|periodo)(?:[^a-z]|$)/i.test(pista);
  }

  function valorParaCampo(campo, iso) {
    if (campo.matches('input[type="date"]')) return iso;
    const actual = String(campo.value || '').trim();
    if (campo.dataset.formato === 'iso' || /^\d{4}-\d{2}-\d{2}$/.test(actual)) return iso;
    return isoALocal(iso);
  }

  document.addEventListener('paste', (e) => {
    const objetivo = e.target instanceof Element ? e.target : null;
    if (!objetivo || zonaIgnorada(objetivo)) return;

    const campo = objetivo.closest('input, textarea');
    if (!campo || campo.disabled || campo.readOnly || !esCampoFecha(campo)) return;

    const texto = ((e.clipboardData || window.clipboardData)?.getData('text/plain') || '').trim();
    // Solo una fecha: si viene una tabla o un texto largo, se deja el pegado normal.
    if (!texto || texto.length > 60 || /\n/.test(texto)) return;

    const iso = parsearFecha(texto);
    if (!iso) return;

    const valor = valorParaCampo(campo, iso);
    e.preventDefault();
    if (campo.value === valor) return;

    campo.value = valor;
    campo.dispatchEvent(new Event('input', { bubbles: true }));
    campo.dispatchEvent(new Event('change', { bubbles: true }));
    destello(campo, 'fecha-pegada');
  });

  // ---------- Arranque ----------
  function iniciar() {
    escanear(document.body);
    new MutationObserver((cambios) => {
      for (const cambio of cambios) {
        if (cambio.type === 'characterData') {
          refrescarPadre(cambio.target.parentElement);
          marcarNodoTexto(cambio.target);
        } else {
          for (const nodo of cambio.addedNodes) escanear(nodo);
        }
      }
    }).observe(document.body, { childList: true, subtree: true, characterData: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar, { once: true });
  } else {
    iniciar();
  }

  // Disponible para otras pantallas que quieran reutilizar la lectura de fechas.
  window.Fechas = { parsear: parsearFecha, aLocal: isoALocal, marcar: escanear };
})();
