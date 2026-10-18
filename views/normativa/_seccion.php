<?php
/**
 * Sección «Normativa y especificaciones» de los formularios de actividad y de material.
 * Variables: $proyecto, $tipoEntidad ('actividad'|'material'), $entidadId (null al crear)
 * Los vínculos manuales viajan en el propio formulario (normativa_doc[], normativa_ref[], normativa_nota[]); lo demás usa fetch (sin formularios anidados).
 */
$pidN = (int)$proyecto->id;
$docsN = \Classes\Normativa::listar($pidN);
$vinculosN = !empty($entidadId) ? \Classes\Normativa::deEntidad($pidN, $tipoEntidad, (int)$entidadId) : [];
$puedeN = puede_gestionar_proyecto($proyecto);
if (!$puedeN && !$vinculosN) return;
?>
<div class="campo campo-full normativa-seccion" id="normativa-seccion" data-proyecto="<?php echo $pidN; ?>" data-tipo="<?php echo s($tipoEntidad); ?>" data-entidad="<?php echo (int)($entidadId ?? 0); ?>">
    <h3><i class="fa-solid fa-scale-balanced"></i> Normativa y especificaciones</h3>
    <?php if (!empty($entidadId)):
        $refN = (string)(int)$entidadId; $dataN = \Classes\Revisiones::elementos($pidN, $tipoEntidad);
        $estN = null; foreach ($dataN as $x) if ($x['ref'] === $refN) { $estN = $x; break; } ?>
        <?php if ($estN): ?>
        <div class="rev-fila" data-proyecto="<?php echo $pidN; ?>">
            <strong>Cumplimiento:</strong> <?php echo \Classes\Revisiones::chip($tipoEntidad, $refN, $estN, puede_revisar($proyecto), 'Cumplimiento'); ?>
            <span class="rev-pie"><?php echo puede_revisar($proyecto) ? 'Haz clic para marcar: correcto, requiere revisión o no cumple.' : 'Solo lectura.'; ?></span>
            <a class="text-sm" href="/revisiones?proyecto=<?php echo $pidN; ?>&pestana=historial&tipo=<?php echo s($tipoEntidad); ?>&ref=<?php echo $refN; ?>" target="_top">Historial</a>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($vinculosN): ?>
        <ul class="norm-lista">
            <?php foreach ($vinculosN as $v): ?>
                <li data-id="<?php echo (int)$v['id']; ?>" class="<?php echo $v['estado'] === 'propuesta' ? 'norm-propuesta' : ''; ?>">
                    <div>
                        <strong><?php echo s($v['doc'] ?: ($v['referencia'] ?: 'Referencia')); ?></strong>
                        <?php echo ($v['clausula'] ?? '') !== '' ? ' · ' . s($v['clausula']) : ''; ?><?php echo $v['pagina'] ? ' · pág. ' . (int)$v['pagina'] : ''; ?><?php echo $v['referencia'] !== '' && $v['doc'] ? ' · ' . s($v['referencia']) : ''; ?>
                        <?php if ($v['estado'] === 'propuesta'): ?><span class="prg-sev">Propuesta</span><?php endif; ?>
                        <?php if ($v['requisito']): ?><small class="text-muted" style="display:block"><?php echo s($v['requisito']); ?></small><?php elseif ($v['ftexto']): ?><small class="text-muted" style="display:block"><?php echo s(mb_substr($v['ftitulo'] . '. ' . $v['ftexto'], 0, 220)); ?>…</small><?php endif; ?>
                        <?php if ($v['nota'] !== ''): ?><small class="text-muted" style="display:block">Nota: <?php echo s($v['nota']); ?></small><?php endif; ?>
                    </div>
                    <?php if ($puedeN): ?><span>
                        <?php if ($v['estado'] === 'propuesta'): ?><button type="button" class="btn btn-sm btn-primary" data-norm="aceptada">Aplica</button><button type="button" class="btn btn-sm btn-ghost" data-norm="rechazada">No aplica</button>
                        <?php else: ?><button type="button" class="btn btn-sm btn-ghost" data-norm="quitar" title="Desvincular"><i class="fa-solid fa-xmark"></i></button><?php endif; ?>
                    </span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($puedeN): ?>
        <div class="norm-filas" id="norm-filas"></div>
        <div class="norm-acciones">
            <button type="button" class="btn btn-sm btn-ghost" id="norm-agregar"><i class="fa-solid fa-plus"></i> Agregar normativa a mano</button>
            <?php if (!empty($entidadId)): ?><button type="button" class="btn btn-sm btn-ghost" id="norm-sugerir" title="Busca en los documentos cargados (sección CSI y palabras, español e inglés); con IA usa la app de escritorio"><i class="fa-solid fa-wand-magic-sparkles"></i> Sugerir de los documentos</button><?php endif; ?>
            <a class="text-sm" href="/normativa?proyecto=<?php echo $pidN; ?>" target="_top"><?php echo count($docsN); ?> documento(s) cargado(s)</a>
            <span class="text-muted text-sm" id="norm-msg"></span>
        </div>
        <small class="text-muted"><?php echo empty($entidadId) ? 'Se guardan al crear.' : 'Las filas nuevas se guardan con el botón Guardar del formulario.'; ?> Elige el documento (opcional) y escribe la referencia: cláusula, artículo o sección.</small>
        <template id="norm-plantilla">
            <div class="norm-fila">
                <select name="normativa_doc[]"><option value="0">(sin documento)</option><?php foreach ($docsN as $d): ?><option value="<?php echo (int)$d['id']; ?>"><?php echo s($d['titulo']); ?></option><?php endforeach; ?></select>
                <input type="text" name="normativa_ref[]" maxlength="200" placeholder="Referencia: cláusula, artículo, sección…">
                <input type="text" name="normativa_nota[]" maxlength="500" placeholder="Nota (opcional)">
                <button type="button" class="btn btn-sm btn-ghost" data-norm-quitar-fila aria-label="Quitar fila">✕</button>
            </div>
        </template>
    <?php endif; ?>
</div>
<script>
    (function () {
        var raiz = document.getElementById('normativa-seccion'); if (!raiz) return;
        var p = raiz.dataset.proyecto, post = function (url, campos) { var d = new FormData(); d.append('proyecto', p); Object.keys(campos).forEach(function (k) { d.append(k, campos[k]); }); return fetch(url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }); };
        var msg = document.getElementById('norm-msg');
        var add = document.getElementById('norm-agregar'), filas = document.getElementById('norm-filas'), tpl = document.getElementById('norm-plantilla');
        if (add) add.addEventListener('click', function () { filas.appendChild(tpl.content.cloneNode(true)); });
        raiz.addEventListener('click', function (e) {
            var q = e.target.closest('[data-norm-quitar-fila]'); if (q) { q.closest('.norm-fila').remove(); return; }
            var b = e.target.closest('button[data-norm]'); if (!b) return;
            var li = b.closest('li'), id = li.dataset.id;
            (b.dataset.norm === 'quitar' ? post('/normativa/quitar', { id: id }) : post('/normativa/resolver', { id: id, estado: b.dataset.norm })).then(function (j) { if (j.success) location.reload(); else if (msg) msg.textContent = j.mensaje || 'No se pudo'; });
        });
        var sug = document.getElementById('norm-sugerir');
        if (sug) sug.addEventListener('click', function () { msg.textContent = 'Buscando…'; post('/normativa/sugerir', { tipo: raiz.dataset.tipo, entidad: raiz.dataset.entidad }).then(function (j) { msg.textContent = j.propuestas ? j.propuestas + ' propuesta(s) nueva(s)' : 'No se encontraron cláusulas nuevas'; if (j.propuestas) setTimeout(function () { location.reload(); }, 600); }); });
    })();
</script>
