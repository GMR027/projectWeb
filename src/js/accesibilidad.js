// ============================================================
// Accesibilidad: nombre para los botones que solo llevan un ícono
// ============================================================
// Muchas pantallas usan botones con solo un <i> (papelera, lápiz, ojo…). Un lector de pantalla
// anuncia «botón» y no sabe qué hace. Aquí se les pone un nombre entendible a partir del ícono,
// sin tocar el HTML de cada vista, y se repite cuando el JavaScript agrega contenido nuevo.
(function () {
  const NOMBRES = [
    ['fa-trash', 'Eliminar'], ['fa-pen', 'Editar'], ['fa-edit', 'Editar'],
    ['fa-eye-slash', 'Ocultar'], ['fa-eye', 'Ver'],
    ['fa-download', 'Descargar'], ['fa-upload', 'Subir'], ['fa-print', 'Imprimir'],
    ['fa-copy', 'Duplicar'], ['fa-clone', 'Duplicar'], ['fa-file-pdf', 'PDF'],
    ['fa-xmark', 'Cerrar'], ['fa-times', 'Cerrar'], ['fa-close', 'Cerrar'],
    ['fa-plus', 'Agregar'], ['fa-minus', 'Quitar'],
    ['fa-arrow-up', 'Mover arriba'], ['fa-arrow-down', 'Mover abajo'],
    ['fa-chevron-left', 'Anterior'], ['fa-chevron-right', 'Siguiente'],
    ['fa-rotate', 'Actualizar'], ['fa-arrows-rotate', 'Actualizar'],
    ['fa-gear', 'Configurar'], ['fa-sliders', 'Ajustes'],
    ['fa-check', 'Aceptar'], ['fa-floppy-disk', 'Guardar'], ['fa-save', 'Guardar'],
    ['fa-link', 'Copiar enlace'], ['fa-share-nodes', 'Compartir'],
    ['fa-ellipsis-vertical', 'Más opciones'], ['fa-ellipsis', 'Más opciones'],
    ['fa-magnifying-glass', 'Buscar'], ['fa-search', 'Buscar'],
    ['fa-filter', 'Filtrar'], ['fa-sort', 'Ordenar'],
    ['fa-expand', 'Ampliar'], ['fa-compress', 'Reducir'],
    ['fa-play', 'Reproducir'], ['fa-pause', 'Pausar'],
    ['fa-lock', 'Bloquear'], ['fa-lock-open', 'Desbloquear'],
    ['fa-circle-info', 'Información'], ['fa-circle-question', 'Ayuda'],
  ];

  const nombreDe = (el) => {
    const titulo = (el.getAttribute('title') || '').trim();
    if (titulo) return titulo;
    const icono = el.querySelector('i[class*="fa-"]');
    if (icono) {
      const clases = icono.className.split(/\s+/);
      for (const [clave, nombre] of NOMBRES) {
        if (clases.some(c => c === clave || c.startsWith(clave + '-'))) return nombre;
      }
    }
    return el.tagName === 'A' ? 'Abrir' : 'Acción';
  };

  const etiquetar = (raiz) => {
    raiz.querySelectorAll('button, a[href]').forEach((el) => {
      if (el.getAttribute('aria-hidden') === 'true') return;
      if (el.hasAttribute('aria-label') || el.hasAttribute('aria-labelledby') || el.hasAttribute('title')) return;
      if ((el.textContent || '').trim() !== '') return;
      if (el.querySelector('img[alt]:not([alt=""])')) return;
      el.setAttribute('aria-label', nombreDe(el));
    });
  };

  const iniciar = () => {
    if (!document.body) return;
    etiquetar(document.body);
    // Contenido que llega por fetch/AJAX (tablas, paneles, modales…)
    let pendiente = false;
    new MutationObserver(() => {
      if (pendiente) return;
      pendiente = true;
      requestAnimationFrame(() => { pendiente = false; etiquetar(document.body); });
    }).observe(document.body, { childList: true, subtree: true });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();
})();
