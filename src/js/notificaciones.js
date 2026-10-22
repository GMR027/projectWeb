// Campana de notificaciones de la barra superior (novedades, actividades de hoy y acciones rápidas).
// El contenido lo pinta el servidor (views/templates/topbar.php); aquí solo se abre y se cierra,
// y al abrirla se marcan las novedades como vistas para que el contador quede al día.
(() => {
  const raiz = document.getElementById('notif');
  const btn = document.getElementById('notif-btn');
  const panel = document.getElementById('notif-panel');
  const badge = document.getElementById('notif-badge');
  if (!raiz || !btn || !panel) return;

  let marcadas = false;

  const pintarBadge = (n) => {
    if (!badge) return;
    badge.hidden = n <= 0;
    badge.textContent = n > 9 ? '9+' : String(n);
  };

  const abrir = () => {
    panel.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    marcarLeidas();
  };
  const cerrar = () => {
    panel.hidden = true;
    btn.setAttribute('aria-expanded', 'false');
  };
  const alternar = () => (panel.hidden ? abrir() : cerrar());

  const marcarLeidas = () => {
    if (marcadas) return;
    marcadas = true;
    const sinVer = parseInt(badge?.dataset.nov || '0', 10);
    if (!sinVer) return;

    fetch('/novedades/vistas', { method: 'POST', headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : null))
      .then((d) => {
        if (!d || !d.success) { marcadas = false; return; }
        const hoy = parseInt(badge?.dataset.hoy || '0', 10);
        if (badge) badge.dataset.nov = '0';
        pintarBadge(hoy);

        raiz.querySelectorAll('.notif-nov.nueva').forEach((li) => li.classList.remove('nueva'));
        raiz.querySelectorAll('[data-nov-chip]').forEach((ch) => ch.remove());

        const cab = document.getElementById('notif-cab-chip');
        if (cab) {
          if (hoy > 0) { cab.textContent = (hoy > 9 ? '9+' : hoy) + ' ' + (cab.dataset.sufijo || ''); }
          else { cab.remove(); }
        }
      })
      .catch(() => { marcadas = false; });
  };

  btn.addEventListener('click', (e) => { e.stopPropagation(); alternar(); });
  panel.addEventListener('click', (e) => e.stopPropagation());
  document.addEventListener('click', () => { if (!panel.hidden) cerrar(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) { cerrar(); btn.focus(); } });
})();
