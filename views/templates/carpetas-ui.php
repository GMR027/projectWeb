<?php // Diálogo y script comunes para renombrar carpetas y mover documentos. Variables: $herramienta, $proyecto ?>
<dialog id="carpeta-dlg" class="no-print">
    <form method="dialog" id="carpeta-form">
        <h3 id="carpeta-titulo">Carpeta</h3>
        <input type="text" id="carpeta-nombre" maxlength="100" autocomplete="off" required>
        <p class="text-sm" id="carpeta-err" role="alert" style="color:#f87171;min-height:1.6rem"></p>
        <div class="acciones"><button type="button" class="btn btn-ghost btn-sm" id="carpeta-cancelar">Cancelar</button><button type="submit" class="btn btn-primary btn-sm">Guardar</button></div>
    </form>
</dialog>
<script>
(function () {
    const dlg = document.getElementById('carpeta-dlg'), inp = document.getElementById('carpeta-nombre'), err = document.getElementById('carpeta-err');
    const base = { proyecto: <?php echo (int)$proyecto->id; ?>, herramienta: <?php echo json_encode($herramienta); ?> };
    const post = (url, d) => fetch(url, { method: 'POST', body: new URLSearchParams({ ...base, ...d }) }).then(r => r.json());
    let alAceptar = null;
    const pedir = (titulo, valor, fn) => { document.getElementById('carpeta-titulo').textContent = titulo; inp.value = valor; err.textContent = ''; alAceptar = fn; dlg.showModal(); inp.select(); };
    document.getElementById('carpeta-cancelar').addEventListener('click', () => { dlg.close(); document.querySelectorAll('.carpeta-mover').forEach(s => s.value = ''); });
    dlg.addEventListener('close', () => document.querySelectorAll('.carpeta-mover').forEach(s => s.value = ''));
    document.getElementById('carpeta-form').addEventListener('submit', async e => {
        e.preventDefault();
        try { const d = await alAceptar(inp.value.trim()); if (d && !d.success) { err.textContent = d.error || 'No se pudo guardar'; return; } location.reload(); }
        catch (_) { err.textContent = 'Error de conexión'; }
    });
    document.addEventListener('click', e => {
        const b = e.target.closest('[data-carpeta-renombrar]'); if (!b) return;
        const de = b.dataset.carpetaRenombrar;
        pedir('Renombrar carpeta', de, a => post('/carpetas/renombrar', { de, a }));
    });
    document.addEventListener('change', e => {
        const s = e.target.closest('.carpeta-mover'); if (!s || !s.value) return;
        const id = s.dataset.id;
        if (s.value === '__nueva') { pedir('Carpeta nueva', '', a => post('/carpetas/mover', { id, a })); return; }
        post('/carpetas/mover', { id, a: s.value }).then(d => { if (d.success) location.reload(); }).catch(() => {});
    });
})();
</script>
