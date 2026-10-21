<?php // Variables: $proyecto, $filtros, $lista, $comparacion, $cmp, $mensaje
use Classes\CentroIA;
$pid = (int)$proyecto->id;
$sevNombre = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$etiqueta = fn($x) => ($x['nombre'] !== '' ? $x['nombre'] : 'Análisis') . ' · ' . date('d/m/Y H:i', strtotime($x['creado']));
$comparables = array_filter($lista, fn($x) => $x['comparable']);
$ref = [];
foreach ($lista as $x) $ref[$x['herramienta'] . ':' . $x['id']] = $etiqueta($x);
?>
<?php $pestana = 'biblioteca'; include __DIR__ . '/../centro-ia/_pestanas.php'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-book-bookmark"></i> <?php echo s(t('Biblioteca de análisis')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · el archivo de todo lo que han analizado las herramientas de IA en este proyecto.</p>
    </div>
</div>

<?php
$totalGuardadas = count(array_filter($lista, fn($x) => $x['guardada']));
$totalPend = array_sum(array_map(fn($x) => (int)$x['pendientes'], $lista));
?>
<section class="card bib-intro">
    <h3><i class="fa-solid fa-circle-info"></i> ¿Para qué sirve?</h3>
    <p>Cada vez que corres <strong>Inconsistencias</strong>, <strong>Análisis de materiales</strong>, el <strong>Programador</strong> o <strong>Normativa</strong>, el resultado se guarda aquí. Desde esta página puedes:</p>
    <ul class="bib-pasos">
        <li><i class="fa-solid fa-folder-open"></i><span><strong>Reabrir</strong> un análisis anterior (clic en su nombre) para seguir aceptando o rechazando hallazgos.</span></li>
        <li><i class="fa-solid fa-bookmark"></i><span><strong>Guardar ★</strong> los que quieras conservar: los demás se borran solos cuando hay otros más recientes.</span></li>
        <li><i class="fa-solid fa-code-compare"></i><span><strong>Comparar</strong> dos análisis de la misma herramienta para ver qué problemas son nuevos, cuáles siguen y cuáles ya se resolvieron.</span></li>
        <li><i class="fa-solid fa-trash"></i><span><strong>Eliminar</strong> los que ya no necesitas. Lo que ya aplicaste al proyecto no se deshace.</span></li>
    </ul>
</section>

<div class="prg-resumen bib-resumen">
    <div class="card"><small>Análisis</small><strong><?php echo count($lista); ?></strong><span>en esta vista</span></div>
    <div class="card"><small>Guardados ★</small><strong><?php echo $totalGuardadas; ?></strong><span>no se borran solos</span></div>
    <div class="card"><small>Hallazgos pendientes</small><strong><?php echo $totalPend; ?></strong><span>por aceptar o rechazar</span></div>
</div>

<?php if (!empty($mensaje)): ?><div class="alerta alerta-exito"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<form method="get" action="/biblioteca-ia" class="card inc-filtros bib-filtros">
    <strong class="bib-titulo"><i class="fa-solid fa-filter"></i> Filtrar</strong>
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label>Herramienta <select name="herramienta" onchange="this.form.submit()"><option value="">Todas</option><?php foreach (CentroIA::HERRAMIENTAS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['herramienta'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label class="campo-check"><input type="checkbox" name="guardadas" value="1" <?php echo $filtros['solo_guardadas'] ? 'checked' : ''; ?> onchange="this.form.submit()"> Solo guardados ★</label>
</form>

<?php if (count($comparables) > 1): ?>
<form method="get" action="/biblioteca-ia" class="card inc-filtros bib-filtros">
    <strong class="bib-titulo"><i class="fa-solid fa-code-compare"></i> Comparar dos análisis</strong>
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label>Comparar en <select name="cmp_herramienta" id="cmp-h"><?php foreach (['inconsistencias', 'materiales'] as $k): ?><option value="<?php echo $k; ?>"<?php echo $cmp['herramienta'] === $k ? ' selected' : ''; ?>><?php echo s(CentroIA::HERRAMIENTAS[$k]); ?></option><?php endforeach; ?></select></label>
    <label>Análisis anterior <select name="a" id="cmp-a"><?php foreach ($comparables as $x): ?><option data-h="<?php echo s($x['herramienta']); ?>" value="<?php echo (int)$x['id']; ?>"<?php echo $cmp['a'] === $x['id'] && $cmp['herramienta'] === $x['herramienta'] ? ' selected' : ''; ?>><?php echo s($etiqueta($x)); ?></option><?php endforeach; ?></select></label>
    <label>Análisis nuevo <select name="b" id="cmp-b"><?php foreach ($comparables as $x): ?><option data-h="<?php echo s($x['herramienta']); ?>" value="<?php echo (int)$x['id']; ?>"<?php echo $cmp['b'] === $x['id'] && $cmp['herramienta'] === $x['herramienta'] ? ' selected' : ''; ?>><?php echo s($etiqueta($x)); ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-code-compare"></i> Comparar</button>
</form>
<script>
    (function () {
        var h = document.getElementById('cmp-h'), sels = [document.getElementById('cmp-a'), document.getElementById('cmp-b')];
        function filtra() { sels.forEach(function (s) { var primera = null; Array.prototype.forEach.call(s.options, function (o) { var ok = o.dataset.h === h.value; o.hidden = !ok; o.disabled = !ok; if (ok && !primera) primera = o; }); if (s.selectedOptions[0] && s.selectedOptions[0].disabled && primera) s.value = primera.value; }); }
        h.addEventListener('change', filtra); filtra();
    })();
</script>
<?php endif; ?>

<?php if ($comparacion): ?>
    <div class="card prg-seccion">
        <h3>Comparación</h3>
        <p class="text-muted">Entre «<?php echo s($ref[$cmp['herramienta'] . ':' . $cmp['a']] ?? '#' . $cmp['a']); ?>» (anterior) y «<?php echo s($ref[$cmp['herramienta'] . ':' . $cmp['b']] ?? '#' . $cmp['b']); ?>» (nuevo).</p>
        <div class="prg-resumen">
            <div class="card"><small>Nuevos</small><strong class="prg-peor"><?php echo count($comparacion['nuevos']); ?></strong><span>aparecen solo en el nuevo</span></div>
            <div class="card"><small>Persisten</small><strong><?php echo count($comparacion['persisten']); ?></strong><span>siguen en ambos</span></div>
            <div class="card"><small>Ya no aparecen</small><strong class="prg-mejor"><?php echo count($comparacion['desaparecen']); ?></strong><span>resueltos o dejaron de detectarse</span></div>
        </div>
        <?php foreach (['nuevos' => 'Nuevos', 'persisten' => 'Persisten', 'desaparecen' => 'Ya no aparecen'] as $k => $tit): if (!$comparacion[$k]) continue; ?>
            <details<?php echo $k === 'nuevos' ? ' open' : ''; ?>><summary><?php echo $tit; ?> (<?php echo count($comparacion[$k]); ?>)</summary>
                <ul class="hist-lista"><?php foreach (array_slice($comparacion[$k], 0, 100) as $x): ?><li><span class="prg-sev"><?php echo s($sevNombre[$x['severidad']] ?? $x['severidad']); ?></span> <?php echo s($x['titulo']); ?> <small class="text-muted">· <?php echo s($x['estado']); ?></small></li><?php endforeach; ?></ul>
            </details>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$lista): ?>
    <div class="card prg-vacio"><h3>Aún no hay análisis</h3><p class="text-muted">Cada análisis que ejecutes con las herramientas de IA se guarda aquí.</p></div>
<?php else: ?>
    <div class="card prg-tabla-wrap">
        <table class="prg-tabla">
            <thead><tr><th>Análisis</th><th>Herramienta</th><th>Modelo</th><th class="num" title="Hallazgos sin resolver">Pendientes</th><th class="num" title="Hallazgos que ya aplicaste">Aceptadas</th><th class="num" title="Hallazgos descartados">Rechazadas</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $x): ?>
                <tr<?php echo $x['guardada'] ? ' class="bib-fav"' : ''; ?>>
                    <td><?php echo $x['guardada'] ? '<i class="fa-solid fa-bookmark prg-guardada"></i> ' : ''; ?><a href="<?php echo s($x['url']); ?>"><strong><?php echo s($etiqueta($x)); ?></strong></a><?php echo isset($x['nota']) ? '<br><small class="text-muted">' . s($x['nota']) . '</small>' : ''; ?></td>
                    <td><span class="bib-herr"><?php echo s(CentroIA::HERRAMIENTAS[$x['herramienta']] ?? $x['herramienta']); ?></span></td>
                    <td><small><?php echo s($x['modelo'] ?: '—'); ?></small></td>
                    <td class="num"><span class="bib-chip<?php echo (int)$x['pendientes'] ? ' pend' : ''; ?>"><?php echo (int)$x['pendientes']; ?></span></td><td class="num"><span class="bib-chip ok"><?php echo (int)$x['aceptadas']; ?></span></td><td class="num"><span class="bib-chip no"><?php echo (int)$x['rechazadas']; ?></span></td>
                    <td class="prg-acepta">
                        <form method="post" action="/biblioteca-ia/guardar" class="bib-form">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="herramienta" value="<?php echo s($x['herramienta']); ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>">
                            <?php if ($x['guardada']): ?>
                                <button class="btn btn-sm btn-ghost" type="submit" name="quitar" value="1">Quitar ★</button>
                            <?php else: ?>
                                <input type="text" name="nombre" maxlength="150" aria-label="Nombre" placeholder="Nombre para guardar" value="<?php echo s('Análisis ' . date('d/m/Y', strtotime($x['creado']))); ?>">
                                <button class="btn btn-sm btn-primary" type="submit"><i class="fa-regular fa-bookmark"></i> Guardar</button>
                            <?php endif; ?>
                        </form>
                        <form method="post" action="/biblioteca-ia/eliminar" data-confirmar="¿Eliminar este análisis? Los cambios ya aplicados al proyecto se conservan, pero ya no podrás revertirlos desde la herramienta.">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="herramienta" value="<?php echo s($x['herramienta']); ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>">
                            <button class="btn btn-sm btn-ghost" type="submit" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
