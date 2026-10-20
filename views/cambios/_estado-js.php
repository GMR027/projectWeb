<script>
// Botones de estado de renglones. Sin conexión (y con el modo activado en Ajustes) el cambio queda en la cola y se sube al volver la red.
document.addEventListener('click', async e => {
    const b = e.target.closest('.cb-estado');
    if (!b || b.disabled) return;
    const datos = new URLSearchParams({ proyecto: new URLSearchParams(location.search).get('proyecto') || '', id: b.dataset.id, estado: b.dataset.estado, base: b.dataset.actualizado || '' });
    b.disabled = true;
    try {
        const r = await fetch('/api/cambios/partida-estado', { method: 'POST', body: datos });
        const d = await r.json();
        if (d.success) return location.reload();
        if (d.conflicto) { window.avisoModal('Renglón modificado', 'Este renglón cambió mientras tanto (ahora está «' + d.actual.estado + '»). Recarga la página para verlo.'); }
        else window.avisoModal('No se pudo cambiar el estado', d.error || 'No se pudo cambiar el estado');
    } catch (_) {
        if (window.POOffline && POOffline.activo()) {
            await POOffline.encolar({ url: '/api/cambios/partida-estado', cuerpo: Object.fromEntries(datos), etiqueta: b.dataset.etiqueta || 'Cambio de estado' });
            b.textContent = 'En cola (sin conexión)';
            return;
        }
        window.avisoModal('Sin conexión', 'Activa el modo sin conexión en Ajustes para guardar los cambios y subirlos después.');
    }
    b.disabled = false;
});
</script>
