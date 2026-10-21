/* Actualización en vivo de tablas: consulta cada pocos segundos una huella de los datos y, si cambió, recarga la página
   (solo si nadie está escribiendo ni hay un diálogo abierto). Uso: window.TABLAS_VIVO = { url, datos: {campo: valor}, version }. */
(function () {
  const C = window.TABLAS_VIVO; if (!C) return;
  let version = C.version || null, ocupado = false;
  const escribiendo = () => { const a = document.activeElement; return a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) || document.querySelector('.modal-overlay.abierto, dialog[open]'); };
  async function revisar() {
    if (ocupado || document.hidden) return; ocupado = true;
    try {
      const r = await (await fetch(C.url, { method: 'POST', body: new URLSearchParams(C.datos || {}) })).json();
      if (!r.success) return;
      if (version === null) { version = r.version; return; }
      if (r.version !== version) {
        if (escribiendo()) return;   // se reintenta en el siguiente ciclo
        const y = window.scrollY; try { sessionStorage.setItem('tv-y', y); } catch (e) { /* sin almacenamiento */ }
        location.reload();
      }
    } catch (e) { /* sin conexión: se reintenta */ } finally { ocupado = false; }
  }
  try { const y = sessionStorage.getItem('tv-y'); if (y !== null) { sessionStorage.removeItem('tv-y'); window.addEventListener('load', () => window.scrollTo(0, +y)); } } catch (e) { /* nada */ }
  revisar(); setInterval(revisar, C.cada || 8000);
  document.addEventListener('visibilitychange', revisar); window.addEventListener('focus', revisar);
})();
