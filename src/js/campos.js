// Campos personalizados: al cambiar el valor de una celda .cp-input se guarda en el proyecto (Fase 4.2)
(() => {
  document.addEventListener('change', async (e) => {
    const inp = e.target.closest('.cp-input');
    if (!inp) return;
    const proyecto = new URLSearchParams(location.search).get('proyecto') || '';
    inp.classList.remove('cp-error');
    try {
      const r = await fetch('/api/campos/valor', { method: 'POST', body: new URLSearchParams({ proyecto, campo: inp.dataset.campo, registro: inp.dataset.registro, valor: inp.value }) });
      const d = await r.json();
      if (!d.success) throw new Error(d.error || 'No se pudo guardar');
      inp.classList.add('cp-guardado');
      setTimeout(() => inp.classList.remove('cp-guardado'), 900);
    } catch (err) {
      inp.classList.add('cp-error');
      if (window.avisoModal) window.avisoModal('No se pudo guardar', err.message);
    }
  });
})();
