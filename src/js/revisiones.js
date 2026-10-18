// ============================================================
// Revisiones: chip ✅ ⚠ ❌ ⚪ con menú; guarda por proyecto y deja historial (POST /api/revisiones/marcar)
// ============================================================
(() => {
  const OPCIONES = [
    ['correcto', 'fa-circle-check', 'Correcto'],
    ['revisar', 'fa-triangle-exclamation', 'Requiere revisión'],
    ['no_cumple', 'fa-circle-xmark', 'No cumple'],
    ['sin_evaluar', 'fa-circle', 'Sin evaluar'],
  ];
  const ICONO = { correcto: 'fa-circle-check', revisar: 'fa-triangle-exclamation', no_cumple: 'fa-circle-xmark', sin_evaluar: 'fa-circle' };
  const NOMBRE = { correcto: 'Correcto', revisar: 'Requiere revisión', no_cumple: 'No cumple', sin_evaluar: 'Sin evaluar' };
  let menu = null;

  const cerrar = () => { if (menu) { menu.remove(); menu = null; } };
  const proyectoDe = chip => chip.closest('[data-proyecto]')?.dataset.proyecto || new URLSearchParams(location.search).get('proyecto');

  function pintar(chip, e) {
    chip.className = 'rev-chip rev-' + e.veredicto + (e.revisado ? ' rev-ok' : '') + (e.cambio ? ' rev-cambio' : '');
    chip.dataset.veredicto = e.veredicto; chip.dataset.revisado = e.revisado ? '1' : '0'; chip.dataset.nota = e.nota || '';
    const ojo = e.cambio ? '<i class="fa-solid fa-rotate"></i>' : e.revisado ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-regular fa-eye-slash"></i>';
    chip.innerHTML = '<i class="fa-solid ' + ICONO[e.veredicto] + '"></i><span class="rev-vistos" aria-hidden="true">' + ojo + '</span>';
    chip.title = NOMBRE[e.veredicto] + (e.revisado ? ' · revisado' : ' · sin revisar') + (e.cambio ? ' · cambió después de revisarse' : '') + (e.usuario ? ' (' + e.usuario + ')' : '') + (e.nota ? ' — ' + e.nota : '');
    const fila = chip.closest('tr'), celda = fila && fila.querySelector('[data-rev-nota]');
    if (celda) celda.textContent = e.nota || '';
  }

  async function guardar(chip, campos) {
    const d = new FormData();
    d.append('proyecto', proyectoDe(chip)); d.append('tipo', chip.dataset.tipo); d.append('ref', chip.dataset.ref);
    for (const k in campos) d.append(k, campos[k]);
    try {
      const r = await fetch('/api/revisiones/marcar', { method: 'POST', body: d, credentials: 'same-origin' });
      const j = await r.json();
      if (!j.success) { alert(j.error || 'No se pudo guardar'); return; }
      pintar(chip, j.estado);
      document.dispatchEvent(new CustomEvent('revision:guardada', { detail: { chip, estado: j.estado, resumen: j.resumen } }));
    } catch (_) { alert('No se pudo guardar: revisa la conexión.'); }
  }

  function abrir(chip) {
    cerrar();
    menu = document.createElement('div'); menu.className = 'rev-menu'; menu.setAttribute('role', 'menu');
    for (const [v, ic, txt] of OPCIONES) {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'rev-op-' + v + (chip.dataset.veredicto === v ? ' on' : '');
      b.innerHTML = '<i class="fa-solid ' + ic + '"></i> ' + txt; b.dataset.v = v; menu.append(b);
    }
    menu.append(document.createElement('hr'));
    const rv = document.createElement('button'); rv.type = 'button'; rv.dataset.rv = chip.dataset.revisado === '1' ? '0' : '1';
    rv.innerHTML = chip.dataset.revisado === '1' ? '<i class="fa-regular fa-eye-slash"></i> Marcar como sin revisar' : '<i class="fa-solid fa-eye"></i> Marcar como revisado'; menu.append(rv);
    const nota = document.createElement('input'); nota.type = 'text'; nota.maxLength = 500; nota.placeholder = 'Nota (Enter para guardar)'; nota.value = chip.dataset.nota || ''; menu.append(nota);
    menu.addEventListener('click', ev => {
      const b = ev.target.closest('button'); if (!b) return;
      const campos = { nota: nota.value };
      if (b.dataset.v) campos.veredicto = b.dataset.v; else campos.revisado = b.dataset.rv;
      cerrar(); guardar(chip, campos);
    });
    nota.addEventListener('keydown', ev => { if (ev.key === 'Enter') { ev.preventDefault(); const n = nota.value; cerrar(); guardar(chip, { nota: n }); } if (ev.key === 'Escape') cerrar(); });
    document.body.append(menu);
    const r = chip.getBoundingClientRect();
    menu.style.top = (window.scrollY + r.bottom + 6) + 'px';
    menu.style.left = Math.max(8, Math.min(window.scrollX + r.left, window.scrollX + document.documentElement.clientWidth - menu.offsetWidth - 8)) + 'px';
    menu.querySelector('button.on, button')?.focus();
  }

  document.addEventListener('click', e => {
    const chip = e.target.closest('[data-rev-chip]');
    if (chip) { e.preventDefault(); menu && menu.dataset.de === chip.dataset.ref ? cerrar() : (abrir(chip), menu.dataset.de = chip.dataset.ref); return; }
    if (menu && !e.target.closest('.rev-menu')) cerrar();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrar(); });
})();
