// ============================================================
// «Por dónde empezar»: modo paso a paso / lista, captura rápida por fetch y cierre del modal
// ============================================================
(() => {
  document.querySelectorAll('.pde').forEach(raiz => {
    const pasos = [...raiz.querySelectorAll('.pde-paso')];
    const msg = raiz.querySelector('.pde-mensaje');
    let actual = Math.max(0, pasos.findIndex(p => !p.classList.contains('hecho')));
    let modo = raiz.dataset.modo;

    const pintar = () => {
      raiz.dataset.modo = modo;
      raiz.querySelectorAll('[data-pde-modo]').forEach(b => b.classList.toggle('activo', b.dataset.pdeModo === modo));
      pasos.forEach((p, i) => { p.hidden = modo === 'guiado' && i !== actual; });
      raiz.querySelector('.pde-nav').hidden = modo !== 'guiado';
      raiz.querySelector('[data-pde-ant]').disabled = actual === 0;
      raiz.querySelector('[data-pde-sig]').disabled = actual === pasos.length - 1;
      raiz.querySelector('.pde-pos').textContent = 'Paso ' + (actual + 1) + ' de ' + pasos.length;
    };
    const avisar = (texto, error) => { msg.hidden = false; msg.textContent = texto; msg.className = 'pde-mensaje ' + (error ? 'error' : 'ok'); };
    const actualizar = d => {
      raiz.querySelector('.pde-hechos').textContent = d.avance[0];
      raiz.querySelector('.pde-progreso span').style.width = (d.avance[1] ? d.avance[0] / d.avance[1] * 100 : 0) + '%';
      d.pasos.forEach(p => {
        const el = raiz.querySelector('[data-paso="' + p.id + '"]');
        if (!el) return;
        el.classList.toggle('hecho', p.hecho);
        el.querySelector('.pde-estado').innerHTML = p.hecho ? '<i class="fa-solid fa-circle-check"></i> ' + p.cuenta + ' ' + p.unidad : 'Pendiente';
      });
    };

    raiz.addEventListener('click', e => {
      const m = e.target.closest('[data-pde-modo]');
      if (m) { modo = m.dataset.pdeModo; pintar(); }
      if (e.target.closest('[data-pde-ant]') && actual > 0) { actual--; pintar(); }
      if (e.target.closest('[data-pde-sig]') && actual < pasos.length - 1) { actual++; pintar(); }
    });
    raiz.addEventListener('submit', async e => {
      const f = e.target.closest('.pde-form');
      if (!f) return;
      e.preventDefault();
      const boton = f.querySelector('button[type=submit]');
      boton.disabled = true;
      try {
        const d = new FormData(f);
        d.append('proyecto', raiz.dataset.proyecto);
        const r = await fetch(f.dataset.api, { method: 'POST', body: d });
        const j = await r.json();
        if (!j.success) { avisar(j.error || 'No se pudo guardar', true); return; }
        f.reset();
        actualizar(j);
        avisar(j.mensaje, false);
      } catch (err) {
        avisar('No se pudo conectar con el servidor', true);
      } finally {
        boton.disabled = false;
      }
    });
    pintar();
  });

  const modal = document.getElementById('pde-modal');
  if (modal) {
    document.getElementById('pde-cerrar').addEventListener('click', () => {
      modal.remove();
      const u = new URL(location.href);
      u.searchParams.delete('inicio');
      history.replaceState(null, '', u);
    });
  }
})();
