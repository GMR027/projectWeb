<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-headset"></i> <?php echo s(t('Soporte')); ?></h2>
        <p class="text-muted">Escribe tu duda o lo que necesitas; el administrador recibirá tu mensaje en su bandeja de entrada.</p>
    </div>
    <a href="/dashboard" class="btn btn-ghost">← Inicio</a>
</div>

<?php if (isset($_GET['enviado'])): ?><div class="alerta alerta-exito">Mensaje enviado. Te responderemos lo antes posible.</div><?php endif; ?>
<?php if (isset($_GET['editado'])): ?><div class="alerta alerta-exito">Mensaje actualizado.</div><?php endif; ?>

<div class="card" style="margin-bottom:2rem">
    <h3 class="card-titulo">Nuevo mensaje</h3>
    <form method="POST" action="/soporte/crear" class="formulario">
        <div class="campo">
            <label>Nombre de usuario</label>
            <input type="text" value="<?php echo s($_SESSION['nombre'] ?? ''); ?>" disabled>
        </div>
        <div class="campo">
            <label for="tema">Tema</label>
            <input type="text" id="tema" name="tema" maxlength="200" value="<?php echo s($soporte->tema); ?>" placeholder="Ej. No puedo exportar mi programa" required>
        </div>
        <div class="campo">
            <label for="informacion">Información</label>
            <textarea id="informacion" name="informacion" rows="6" maxlength="5000" placeholder="Cuéntanos tu duda o el soporte que necesitas" required><?php echo s($soporte->informacion); ?></textarea>
        </div>
        <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Enviar</button>
    </form>
</div>

<div class="card">
    <h3 class="card-titulo">Mis mensajes</h3>
    <table class="tabla">
        <thead><tr><th>Tema</th><th class="hide-m">Información</th><th class="hide-m"><?php echo s(t('Fecha')); ?></th><th>Estado</th><th><?php echo s(t('Acciones')); ?></th></tr></thead>
        <tbody>
        <?php foreach ($mensajes as $m): ?>
            <tr>
                <td><?php echo s($m->tema); ?></td>
                <td class="hide-m text-muted"><?php echo s(mb_strimwidth($m->informacion, 0, 90, '…')); ?></td>
                <td class="mono hide-m"><?php echo s(substr($m->creado, 0, 16)); ?></td>
                <td><span class="badge <?php echo $m->leido ? 'badge-cliente' : 'badge-visitante'; ?>"><?php echo $m->leido ? 'Visto' : 'Enviado'; ?></span>
                    <?php $nuevas = count(array_filter($respuestas[$m->id] ?? [], fn($r) => !$r['visto'])); ?>
                    <span class="badge badge-nueva" data-nuevas="<?php echo (int)$m->id; ?>"<?php echo $nuevas ? '' : ' hidden'; ?>><i class="fa-solid fa-bell"></i> <?php echo $nuevas === 1 ? 'Respuesta nueva' : $nuevas . ' respuestas nuevas'; ?></span></td>
                <td class="acciones">
                    <button type="button" class="btn btn-sm btn-success" data-respuesta="<?php echo (int)$m->id; ?>"><i class="fa-solid fa-comment-dots"></i> Respuesta<?php echo !empty($respuestas[$m->id]) ? ' (' . count($respuestas[$m->id]) . ')' : ''; ?></button>
                    <a href="/soporte/editar?id=<?php echo s($m->id); ?>" class="btn btn-sm btn-ghost"><?php echo s(t('Editar')); ?></a>
                    <form method="POST" action="/soporte/eliminar" style="display:inline" data-confirm="¿Eliminar mensaje?|Se quitará también de la bandeja del administrador.">
                        <input type="hidden" name="id" value="<?php echo s($m->id); ?>">
                        <button type="submit" class="btn btn-sm btn-danger"><?php echo s(t('Eliminar')); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$mensajes): ?><tr><td colspan="5" class="text-muted">Aún no has enviado mensajes.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="sop-resp" aria-hidden="true">
    <div class="modal-card bj-card" role="dialog" aria-modal="true" aria-labelledby="sop-resp-titulo">
        <div class="bj-cab"><h3 id="sop-resp-titulo">Respuesta</h3><button type="button" class="btn btn-sm btn-ghost" id="sop-resp-cerrar" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>
        <p class="text-muted text-sm" id="sop-resp-tema"></p>
        <p class="text-muted" id="sop-resp-vacio" hidden>Aún no hay respuesta del administrador.</p>
        <ul class="bj-respuestas" id="sop-resp-lista"></ul>
        <div class="modal-acciones"><button type="button" class="btn btn-ghost" id="sop-resp-cerrar2"><i class="fa-solid fa-xmark"></i> Cerrar</button></div>
    </div>
</div>
<script>
(function () {
    const respuestas = <?php echo json_encode($respuestas ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const temas = <?php echo json_encode(array_column(array_map(fn($m) => ['id' => $m->id, 't' => $m->tema], $mensajes), 't', 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const modal = document.getElementById('sop-resp');
    const cerrar = () => { modal.classList.remove('abierto'); modal.setAttribute('aria-hidden', 'true'); };
    document.addEventListener('click', e => {
        const b = e.target.closest('[data-respuesta]');
        if (!b) return;
        const lista = respuestas[b.dataset.respuesta] || [];
        document.getElementById('sop-resp-tema').textContent = 'Tu mensaje: ' + (temas[b.dataset.respuesta] || '');
        document.getElementById('sop-resp-vacio').hidden = lista.length > 0;
        document.getElementById('sop-resp-lista').innerHTML = lista.map(r =>
            '<li class="bj-respuesta"><div class="bj-respuesta-cab"><strong>' + esc(r.titulo) + '</strong><span class="mono text-sm text-muted">' + esc(r.fecha) + '</span></div>' +
            '<p class="msg-cuerpo">' + esc(r.descripcion) + '</p>' +
            '<div class="bj-fotos">' + r.fotos.map(f => '<a href="' + esc(f.url) + '" target="_blank" rel="noopener"><img src="' + esc(f.url) + '" alt="" class="bj-foto"></a>').join('') + '</div></li>').join('');
        modal.classList.add('abierto'); modal.setAttribute('aria-hidden', 'false');
        // Al abrirlas quedan como vistas: se quitan los avisos
        const marca = document.querySelector('[data-nuevas="' + b.dataset.respuesta + '"]');
        if (lista.some(r => !r.visto)) {
            lista.forEach(r => r.visto = true);
            fetch('/api/soporte/vistas', { method: 'POST', body: new URLSearchParams({ id: b.dataset.respuesta }) }).then(r => r.json()).then(d => {
                if (!d.success) return;
                if (marca) marca.hidden = true;
                document.querySelectorAll('.sidebar a[href="/soporte"] .nav-badge').forEach(n => d.nuevas ? n.textContent = d.nuevas : n.remove());
            }).catch(() => {});
        }
    });
    ['sop-resp-cerrar', 'sop-resp-cerrar2'].forEach(id => document.getElementById(id).addEventListener('click', cerrar));
    modal.addEventListener('click', e => { if (e.target === modal) cerrar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('abierto')) cerrar(); });
})();
</script>
