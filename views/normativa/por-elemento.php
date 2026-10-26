<?php // Variables: $proyecto, $filtros, $elementos, $puede
$pid = (int)$proyecto->id;
$origen = ['manual' => 'Manual', 'csi' => 'Sección CSI', 'palabras' => 'Palabras', 'ia' => 'IA'];
$totalApl = array_sum(array_column($elementos, 'aceptadas'));
$totalProp = array_sum(array_column($elementos, 'propuestas'));
$sinFiltros = ($filtros['tipo'] ?? '') === '' && ($filtros['estado'] ?? '') === '' && empty($filtros['sin']) && ($filtros['q'] ?? '') === '';
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-list-check"></i> Normativa por material y actividad</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · qué cláusulas aplican a cada material y a cada actividad, con el documento, la sección, la página y el requisito. Solo el administrador ve las propuestas y las sugerencias.</p>
    </div>
    <div class="inc-acciones">
        <a href="/normativa?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Documentos</a>
        <a href="/normativa/vinculos?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-link"></i> Vínculos</a>
    </div>
</div>

<form method="get" action="/normativa/por-elemento" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label>Elemento <select name="tipo" onchange="this.form.submit()">
        <option value="">Materiales y actividades</option>
        <option value="material"<?php echo $filtros['tipo'] === 'material' ? ' selected' : ''; ?>>Solo materiales</option>
        <option value="actividad"<?php echo $filtros['tipo'] === 'actividad' ? ' selected' : ''; ?>>Solo actividades</option>
    </select></label>
    <label>Estado <select name="estado" onchange="this.form.submit()">
        <option value="">Todas</option>
        <option value="aceptada"<?php echo $filtros['estado'] === 'aceptada' ? ' selected' : ''; ?>>Solo aplicables</option>
        <option value="propuesta"<?php echo $filtros['estado'] === 'propuesta' ? ' selected' : ''; ?>>Solo propuestas</option>
    </select></label>
    <label style="flex:1">Buscar <input type="search" name="q" value="<?php echo s($filtros['q']); ?>" placeholder="Material, actividad o partida…"></label>
    <label class="norm-check"><input type="checkbox" name="sin" value="1"<?php echo !empty($filtros['sin']) ? ' checked' : ''; ?> onchange="this.form.submit()"> Solo sin normativa aplicada</label>
    <button class="btn btn-ghost" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button>
    <?php if (!$sinFiltros): ?><a href="/normativa/por-elemento?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">Limpiar</a><?php endif; ?>
</form>

<p class="text-muted text-sm"><?php echo count($elementos); ?> elemento(s) · <?php echo $totalApl; ?> cláusula(s) aplicable(s) · <?php echo $totalProp; ?> propuesta(s). Se muestran hasta 200 elementos; usa los filtros para acotar.</p>

<?php if (!$elementos): ?>
    <div class="card prg-vacio"><h3>Nada que mostrar con estos filtros</h3>
        <p class="text-muted"><?php echo !empty($filtros['sin']) ? 'Todos los elementos de esta vista ya tienen normativa aplicada.' : 'Carga documentos y ejecuta «Aplicar normativa y especificaciones», o quita los filtros.'; ?></p>
    </div>
<?php else: ?>
    <div data-norm-raiz data-proyecto="<?php echo $pid; ?>" data-recargar="1">
    <ul class="inc-lista">
    <?php foreach ($elementos as $e): $ancla = $e['tipo'] . '-' . $e['id']; $ficha = $e['tipo'] === 'material' ? '/materiales/editar' : '/programa/actividades/editar'; ?>
        <li class="card inc-item norm-elem" id="<?php echo s($ancla); ?>">
            <div class="inc-cab">
                <span class="prg-sev"><?php echo $e['tipo'] === 'material' ? 'Material' : 'Actividad'; ?></span>
                <strong><?php echo s($e['nombre']); ?></strong>
                <?php if ($e['meta'] !== ''): ?><small class="text-muted"><?php echo s($e['meta']); ?></small><?php endif; ?>
                <small class="text-muted"><?php echo (int)$e['aceptadas']; ?> aplican · <?php echo (int)$e['propuestas']; ?> propuesta(s)</small>
            </div>
            <div class="inc-botones">
                <a class="btn btn-sm btn-ghost" href="<?php echo $ficha; ?>?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$e['id']; ?>"><i class="fa-solid fa-pen-to-square"></i> Abrir ficha</a>
                <?php if ($e['propuestas'] > 1): ?><button type="button" class="btn btn-sm btn-primary" data-norm-todas><i class="fa-solid fa-check-double"></i> Aceptar las <?php echo (int)$e['propuestas']; ?> propuestas</button><?php endif; ?>
                <?php if (!$e['vinculos']): ?><button type="button" class="btn btn-sm btn-ghost" data-norm-sugerir data-tipo="<?php echo s($e['tipo']); ?>" data-entidad="<?php echo (int)$e['id']; ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> Sugerir de los documentos</button><?php endif; ?>
                <span class="norm-msg inc-msg" aria-live="polite"></span>
            </div>
            <?php if (!$e['vinculos']): ?>
                <p class="text-muted"><i class="fa-solid fa-circle-info"></i> Sin normativa vinculada. Usa «Sugerir de los documentos» o agrega la normativa a mano desde la ficha.</p>
            <?php else: ?>
                <ul class="norm-lista">
                <?php foreach ($e['vinculos'] as $v): $c = \Classes\Normativa::cita($v); $ori = $origen[$v['origen']] ?? $v['origen']; ?>
                    <li class="norm-vinculo<?php echo $v['estado'] === 'propuesta' ? ' norm-propuesta' : ''; ?>" data-id="<?php echo (int)$v['id']; ?>" data-estado="<?php echo s($v['estado']); ?>">
                        <div>
                            <strong><?php echo s($c['documento'] ?: ($v['referencia'] ?: 'Referencia')); ?></strong>
                            <?php if ($c['codigo'] !== ''): ?> · <?php echo s($c['codigo']); ?><?php endif; ?>
                            <?php if ($c['seccion'] !== ''): ?> · <?php echo s($c['seccion']); ?><?php endif; ?>
                            <?php if ($c['clausula'] !== ''): ?> · <?php echo s($c['clausula']); ?><?php endif; ?>
                            <?php if ($c['pagina']): ?> · pág. <?php echo (int)$c['pagina']; ?><?php endif; ?>
                            <?php if ($c['idioma'] !== ''): ?> · <?php echo strtoupper(s($c['idioma'])); ?><?php endif; ?>
                            <?php if ($v['referencia'] !== '' && $c['documento'] !== ''): ?> · <?php echo s($v['referencia']); ?><?php endif; ?>
                            <span class="prg-sev"><?php echo $v['estado'] === 'propuesta' ? 'Propuesta · ' : 'Aplica · '; ?><?php echo s($ori); ?></span>
                            <?php if ($v['requisito']): ?><small class="text-muted" style="display:block"><?php echo s($v['requisito']); ?></small>
                            <?php elseif ($v['ftexto']): ?><small class="text-muted" style="display:block"><?php echo s(mb_substr(trim($v['ftitulo'] . '. ' . $v['ftexto']), 0, 260)); ?>…</small><?php endif; ?>
                            <?php if ($v['nota'] !== ''): ?><small class="text-muted" style="display:block">Nota: <?php echo s($v['nota']); ?></small><?php endif; ?>
                            <?php if ($v['documento_id'] && $c['pagina']): ?><a class="text-sm" href="/normativa/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$v['documento_id']; ?>#page=<?php echo (int)$c['pagina']; ?>" target="_blank" rel="noopener">Ver en el documento (pág. <?php echo (int)$c['pagina']; ?>)</a><?php endif; ?>
                        </div>
                        <span>
                            <?php if ($v['estado'] === 'propuesta'): ?><button type="button" class="btn btn-sm btn-primary" data-norm="aceptada">Aplica</button><button type="button" class="btn btn-sm btn-ghost" data-norm="rechazada">No aplica</button>
                            <?php else: ?><button type="button" class="btn btn-sm btn-ghost" data-norm="quitar" title="Desvincular"><i class="fa-solid fa-xmark"></i></button><?php endif; ?>
                            <span class="norm-msg text-sm text-muted" aria-live="polite"></span>
                        </span>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ul>
    </div>
    <?php include __DIR__ . '/_resolver-js.php'; ?>
<?php endif; ?>
