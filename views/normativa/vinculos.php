<?php // Variables: $proyecto, $filtros, $lista, $puede
$pid = (int)$proyecto->id;
$origen = ['manual' => 'Manual', 'csi' => 'Sección CSI exacta', 'palabras' => 'Coincidencia de palabras', 'ia' => 'IA'];
$est = $filtros['estado'] ?? 'propuesta';
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-link"></i> <?php echo s(t('Vínculos de normativa')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · qué cláusulas aplican a qué materiales y actividades. Las propuestas (por sección CSI, palabras o IA) se aceptan o rechazan aquí; los aceptados aparecen en el formulario de cada elemento.</p>
    </div>
    <div class="inc-acciones"><a href="/normativa?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Documentos</a></div>
</div>
<form method="get" action="/normativa/vinculos" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label>Estado <select name="estado" onchange="this.form.submit()"><?php foreach (['propuesta' => 'Por revisar', 'aceptada' => 'Aceptados', 'rechazada' => 'Rechazados'] as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $est === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
    <label>Elemento <select name="tipo" onchange="this.form.submit()"><option value="">Todos</option><option value="material"<?php echo $filtros['entidad_tipo'] === 'material' ? ' selected' : ''; ?>>Materiales</option><option value="actividad"<?php echo $filtros['entidad_tipo'] === 'actividad' ? ' selected' : ''; ?>>Actividades</option></select></label>
    <input type="search" data-filtra=".inc-lista" placeholder="Buscar…" autocomplete="off">
</form>
<?php if (!$lista): ?>
    <div class="card prg-vacio"><h3>Nada en esta vista</h3><p class="text-muted">Las propuestas aparecen al cargar documentos y ejecutar «Aplicar normativa y especificaciones» en la app de escritorio (con IA), o al pulsar «Sugerir» en el formulario de un material o actividad.</p></div>
<?php else: ?>
    <ul class="inc-lista"><?php foreach ($lista as $x): ?>
        <li class="card inc-item sev-<?php echo $x['origen'] === 'csi' ? 'media' : 'baja'; ?> est-<?php echo $x['estado'] === 'propuesta' ? 'pendiente' : s($x['estado']); ?>" data-id="<?php echo (int)$x['id']; ?>">
            <div class="inc-cab"><span class="prg-sev"><?php echo s($x['entidad_tipo'] === 'material' ? 'Material' : 'Actividad'); ?></span><strong><?php echo s($x['entidad']); ?></strong>
                <small class="text-muted"><?php echo s($origen[$x['origen']] ?? $x['origen']); ?><?php echo $x['origen'] !== 'manual' && $x['origen'] !== 'ia' ? ' · ' . (float)$x['puntaje'] . '%' : ''; ?><?php echo $x['modelo'] ? ' · ' . s($x['modelo']) : ''; ?></small></div>
            <p><strong><?php echo s($x['doc'] ?: 'Referencia'); ?></strong><?php echo $x['clausula'] !== null && $x['clausula'] !== '' ? ' · ' . s($x['clausula']) : ''; ?><?php echo $x['pagina'] ? ' · pág. ' . (int)$x['pagina'] : ''; ?><?php echo $x['idioma'] ? ' · ' . strtoupper(s($x['idioma'])) : ''; ?><?php echo $x['referencia'] !== '' ? ' · ' . s($x['referencia']) : ''; ?></p>
            <?php if ($x['requisito']): ?><div class="inc-solucion"><span><strong>Requisito (resumen en español):</strong> <?php echo s($x['requisito']); ?></span></div><?php endif; ?>
            <?php if ($x['ftexto']): ?><details><summary>Texto original de la cláusula</summary><p><?php echo s(trim($x['ftitulo'] . '. ' . $x['ftexto'])); ?></p></details><?php endif; ?>
            <?php if ($x['nota'] !== ''): ?><p class="text-muted"><?php echo s($x['nota']); ?></p><?php endif; ?>
            <div class="inc-botones">
                <?php if ($x['documento_id']): ?><a class="btn btn-sm btn-ghost" target="_blank" rel="noopener" href="/normativa/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$x['documento_id']; ?>#page=<?php echo (int)($x['pagina'] ?: 1); ?>">Abrir el PDF</a><?php endif; ?>
                <?php if ($puede && $x['estado'] === 'propuesta'): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-accion="aceptada"><i class="fa-solid fa-check"></i> Aplica: vincular</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-accion="rechazada"><i class="fa-solid fa-xmark"></i> No aplica</button>
                <?php elseif ($puede && $x['estado'] === 'aceptada'): ?>
                    <button type="button" class="btn btn-sm btn-ghost" data-quitar="1">Desvincular</button>
                <?php endif; ?>
                <span class="inc-msg" aria-live="polite"></span>
            </div>
        </li>
    <?php endforeach; ?></ul>
    <script>
        document.querySelector('.inc-lista').addEventListener('click', function (e) {
            var b = e.target.closest('button[data-accion],button[data-quitar]');
            if (!b) return;
            var li = b.closest('.inc-item'), msg = li.querySelector('.inc-msg');
            var d = new FormData(); d.append('proyecto', '<?php echo $pid; ?>'); d.append('id', li.dataset.id);
            if (b.dataset.accion) d.append('estado', b.dataset.accion);
            li.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
            fetch(b.dataset.quitar ? '/normativa/quitar' : '/normativa/resolver', { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
                msg.textContent = j.mensaje || (j.success ? 'Listo' : 'No se pudo');
                if (j.success) { li.classList.add('hecha'); setTimeout(function () { li.remove(); }, 1100); } else li.querySelectorAll('button').forEach(function (x) { x.disabled = false; });
            }).catch(function () { msg.textContent = 'Error de conexión'; li.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); });
        });
    </script>
<?php endif; ?>
