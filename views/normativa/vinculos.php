<?php // Variables: $proyecto, $filtros, $lista, $puede
$pid = (int)$proyecto->id;
$origen = ['manual' => 'Manual', 'csi' => 'Sección CSI', 'palabras' => 'Coincidencia de palabras', 'ia' => 'IA'];
$est = $filtros['estado'] ?? 'propuesta';
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-link"></i> <?php echo s(t('Vínculos de normativa')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · qué cláusulas aplican a qué materiales y actividades. Las propuestas (por sección CSI, palabras o IA) se aceptan o rechazan aquí; los aceptados aparecen en el formulario de cada elemento.</p>
    </div>
    <div class="inc-acciones">
        <a href="/normativa/por-elemento?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-list-check"></i> Por material y actividad</a>
        <a href="/normativa?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Documentos</a>
    </div>
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
    <ul class="inc-lista" data-norm-raiz data-proyecto="<?php echo $pid; ?>"><?php foreach ($lista as $x): $c = \Classes\Normativa::cita($x); ?>
        <li class="card inc-item norm-vinculo sev-<?php echo $x['origen'] === 'csi' ? 'media' : 'baja'; ?> est-<?php echo $x['estado'] === 'propuesta' ? 'pendiente' : s($x['estado']); ?>" data-id="<?php echo (int)$x['id']; ?>" data-estado="<?php echo s($x['estado']); ?>">
            <div class="inc-cab"><span class="prg-sev"><?php echo s($x['entidad_tipo'] === 'material' ? 'Material' : 'Actividad'); ?></span><strong><?php echo s($x['entidad']); ?></strong>
                <small class="text-muted"><?php echo s($origen[$x['origen']] ?? $x['origen']); ?><?php echo $x['origen'] !== 'manual' && $x['origen'] !== 'ia' ? ' · ' . (float)$x['puntaje'] . '%' : ''; ?><?php echo $x['modelo'] ? ' · ' . s($x['modelo']) : ''; ?></small></div>
            <p><strong><?php echo s($c['documento'] ?: 'Referencia'); ?></strong><?php echo $c['codigo'] !== '' ? ' · ' . s($c['codigo']) : ''; ?><?php echo $c['seccion'] !== '' ? ' · ' . s($c['seccion']) : ''; ?><?php echo $c['clausula'] !== '' ? ' · ' . s($c['clausula']) : ''; ?><?php echo $c['pagina'] ? ' · pág. ' . (int)$c['pagina'] : ''; ?><?php echo $c['idioma'] !== '' ? ' · ' . strtoupper(s($c['idioma'])) : ''; ?><?php echo $x['referencia'] !== '' ? ' · ' . s($x['referencia']) : ''; ?></p>
            <?php if ($x['requisito']): ?><div class="inc-solucion"><span><strong>Requisito (resumen en español):</strong> <?php echo s($x['requisito']); ?></span></div><?php endif; ?>
            <?php if ($x['ftexto']): ?><details><summary>Texto original de la cláusula</summary><p><?php echo s(trim($x['ftitulo'] . '. ' . $x['ftexto'])); ?></p></details><?php endif; ?>
            <?php if ($x['nota'] !== ''): ?><p class="text-muted"><?php echo s($x['nota']); ?></p><?php endif; ?>
            <div class="inc-botones">
                <?php if ($x['documento_id']): ?><a class="btn btn-sm btn-ghost" target="_blank" rel="noopener" href="/normativa/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$x['documento_id']; ?>#page=<?php echo (int)($c['pagina'] ?: 1); ?>">Abrir el PDF<?php echo $c['pagina'] ? ' en la página ' . (int)$c['pagina'] : ''; ?></a><?php endif; ?>
                <?php if ($puede && $x['estado'] === 'propuesta'): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-norm="aceptada"><i class="fa-solid fa-check"></i> Aplica: vincular</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-norm="rechazada"><i class="fa-solid fa-xmark"></i> No aplica</button>
                <?php elseif ($puede && $x['estado'] === 'aceptada'): ?>
                    <button type="button" class="btn btn-sm btn-ghost" data-norm="quitar">Desvincular</button>
                <?php endif; ?>
                <span class="inc-msg norm-msg" aria-live="polite"></span>
            </div>
        </li>
    <?php endforeach; ?></ul>
    <?php include __DIR__ . '/_resolver-js.php'; ?>
<?php endif; ?>
