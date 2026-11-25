<?php // Variables: $proyecto, $documentos, $pendientes, $puede, $q, $resultados, $mensaje
use Classes\Almacenamiento;
use Classes\Normativa;
$pid = (int)$proyecto->id;
$estadoTxt = ['indexado' => 'Indexado', 'sin_texto' => 'Sin texto (escaneado)', 'error' => 'No se pudo leer'];
?>
<?php
$nDocs = count($documentos);
$nIndexados = count(array_filter($documentos, fn($d) => $d['estado'] === 'indexado'));
$nClausulas = array_sum(array_map(fn($d) => (int)$d['fragmentos'], $documentos));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-scale-balanced"></i> <?php echo s(t('Normativa y especificaciones')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · carga normas y especificaciones en PDF o Word (español o inglés, o mezclados). El sistema las lee, las parte por cláusulas y propone en qué materiales y actividades aplica cada una. Tú decides.</p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-file-pdf"></i><b><?php echo $nDocs; ?></b> documentos</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-list-check"></i><b><?php echo $nClausulas; ?></b> cláusulas</span>
            <?php if ($pendientes): ?><span class="herr-chip"><i class="fa-solid fa-link"></i><b><?php echo (int)$pendientes; ?></b> vínculos por revisar</span><?php endif; ?>
        </div>
    </div>
    <div class="inc-acciones">
        <a href="/normativa/por-elemento?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-list-check"></i> Por material y actividad</a>
        <a href="/normativa/vinculos?proyecto=<?php echo $pid; ?>" class="btn btn-primary"><i class="fa-solid fa-link"></i> Vínculos<?php echo $pendientes ? ' (' . (int)$pendientes . ' por revisar)' : ''; ?></a>
    </div>
</div>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Documentos</small><strong><?php echo $nDocs; ?></strong><span>normas y especificaciones</span></span>
    </div>
    <div class="suite-kpi is-success">
        <span class="suite-kpi-icono"><i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Indexados</small><strong><?php echo $nIndexados; ?></strong><span>leídos y partidos por cláusulas</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-list-ol" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Cláusulas</small><strong><?php echo $nClausulas; ?></strong><span>disponibles para buscar y vincular</span></span>
    </div>
    <div class="suite-kpi <?php echo $pendientes ? 'is-warning' : ''; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-link" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Vínculos por revisar</small><strong><?php echo (int)$pendientes; ?></strong><span>propuestas de materiales y actividades</span></span>
    </div>
</div>

<?php if ($puede): ?>
<div class="suite-panel">
    <div class="suite-panel-cab"><div><h3><i class="fa-solid fa-upload"></i> Cargar documentos</h3><p class="text-muted text-sm">Máximo <?php echo s(Almacenamiento::formato(Normativa::MAX_PDF)); ?> por archivo; el PDF debe tener texto seleccionable.</p></div></div>
    <div class="suite-panel-cuerpo">
    <form method="post" action="/normativa/subir" enctype="multipart/form-data" id="norm-lote">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label>Archivos PDF o Word (puedes elegir varios) <input type="file" id="norm-archivos" name="archivos[]" multiple required accept=".pdf,.docx,.doc,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/msword"></label>
        <div class="prg-tabla-wrap" id="norm-filas" hidden>
            <table class="prg-tabla"><thead><tr><th>Archivo</th><th>Título</th><th>Código</th><th>Tipo</th></tr></thead><tbody></tbody></table>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-upload"></i> Subir e indexar</button>
    </form>
    <template id="norm-tipos"><?php foreach (Normativa::TIPOS as $k => $n): ?><option value="<?php echo $k; ?>"><?php echo s($n); ?></option><?php endforeach; ?></template>
    <p class="text-muted text-sm">Máximo <?php echo s(Almacenamiento::formato(Normativa::MAX_PDF)); ?> por archivo. Deja título, código o tipo en «Automático» y se detectan del contenido (por ejemplo «SECCIÓN 27 15 00», NOM-…, NFPA…) o del nombre del archivo; escribe un valor para fijarlo a mano. El PDF debe tener texto seleccionable (los escaneados no se pueden leer: no hay OCR). Los .doc antiguos solo se leen si el servidor tiene LibreOffice; prefiere .docx.</p>
    <script>
    (function () {
        var inp = document.getElementById('norm-archivos'), cont = document.getElementById('norm-filas');
        if (!inp) return;
        var tbody = cont.querySelector('tbody'), tipos = document.getElementById('norm-tipos').innerHTML;
        inp.addEventListener('change', function () {
            tbody.innerHTML = '';
            Array.prototype.forEach.call(inp.files, function (f) {
                var tr = document.createElement('tr'), td = document.createElement('td');
                td.textContent = f.name; tr.appendChild(td);
                [['titulo', 'Automático', 200], ['codigo', 'Automático', 80]].forEach(function (c) {
                    var t = document.createElement('td'), i = document.createElement('input');
                    i.type = 'text'; i.name = c[0] + '[]'; i.maxLength = c[2]; i.placeholder = c[1]; t.appendChild(i); tr.appendChild(t);
                });
                var t = document.createElement('td'), sel = document.createElement('select');
                sel.name = 'tipo[]'; sel.innerHTML = '<option value="auto">Automático</option>' + tipos; t.appendChild(sel); tr.appendChild(t);
                tbody.appendChild(tr);
            });
            cont.hidden = !inp.files.length;
        });
    })();
    </script>
    </div>
</div>
<?php endif; ?>

<form method="get" action="/normativa" class="suite-toolbar">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label class="suite-busca"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo s($q); ?>" placeholder="Buscar en todos los documentos (español e inglés)…"></label>
    <button class="btn btn-ghost" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
</form>
<?php if ($q !== ''): ?>
    <div class="suite-panel"><div class="suite-panel-cab"><h3><i class="fa-solid fa-magnifying-glass"></i> Resultados para «<?php echo s($q); ?>»</h3></div><div class="suite-panel-cuerpo">
        <?php if (!$resultados): ?><p class="text-muted">Sin coincidencias suficientes.</p><?php else: ?>
            <ul class="inc-lista"><?php foreach ($resultados as $r): ?>
                <li class="card inc-item sev-baja"><div class="inc-cab"><strong><?php echo archivo_icono_html('pdf'); ?> <?php echo s($r['doc']); ?></strong><small class="text-muted"><?php echo s(($r['clausula'] !== '' ? $r['clausula'] . ' · ' : '') . 'pág. ' . $r['pagina'] . ' · ' . strtoupper($r['idioma']) . ' · coincidencia ' . $r['puntaje'] . '%'); ?></small></div>
                    <p><?php echo s(mb_substr(trim($r['titulo'] . '. ' . $r['texto']), 0, 420)); ?>…</p>
                    <a class="btn btn-sm btn-ghost" href="/normativa/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$r['documento_id']; ?>#page=<?php echo (int)$r['pagina']; ?>" target="_blank" rel="noopener">Abrir el PDF en la página <?php echo (int)$r['pagina']; ?></a></li>
            <?php endforeach; ?></ul>
        <?php endif; ?></div></div>
<?php endif; ?>

<?php if (!$documentos): ?>
    <div class="suite-panel"><?php echo estado_vacio('fa-scale-balanced', 'Aún no hay documentos', 'Sube las normas y especificaciones del proyecto. Después, la app de escritorio puede revisar los materiales y actividades con IA y proponer a cuáles aplican.'); ?></div>
<?php else: ?>
    <div class="suite-panel">
    <div class="suite-panel-cab"><div><h3><i class="fa-solid fa-book"></i> Documentos <span class="suite-chip"><?php echo $nDocs; ?></span></h3><p class="text-muted text-sm">Abre un documento para buscar en sus cláusulas.</p></div></div>
    <div class="suite-panel-cuerpo">
    <div class="prg-tabla-wrap"><table class="prg-tabla">
        <thead><tr><th>Documento</th><th>Tipo</th><th>Idioma</th><th class="num">Páginas</th><th class="num">Cláusulas</th><th>Estado</th><th></th></tr></thead>
        <tbody><?php foreach ($documentos as $d): ?>
            <tr>
                <td><a class="nombre-archivo" href="/normativa/documento?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$d['id']; ?>"><?php echo archivo_icono_html(pathinfo($d['archivo'], PATHINFO_EXTENSION), 'Documento'); ?><strong><?php echo s($d['titulo']); ?></strong></a><?php echo $d['codigo'] !== '' ? '<br><small class="text-muted">' . s($d['codigo']) . '</small>' : ''; ?></td>
                <td><?php echo s(Normativa::TIPOS[$d['tipo']]); ?></td><td><?php echo s(Normativa::IDIOMAS[$d['idioma']]); ?></td>
                <td class="num"><?php echo (int)$d['paginas']; ?></td><td class="num"><?php echo (int)$d['fragmentos']; ?></td>
                <td><span class="hist-ev ev-<?php echo $d['estado'] === 'indexado' ? 'aplicado' : 'rechazado'; ?>"><?php echo s($estadoTxt[$d['estado']]); ?></span><?php echo $d['nota'] !== '' ? '<br><small class="text-muted">' . s($d['nota']) . '</small>' : ''; ?></td>
                <td class="prg-acepta">
                    <a class="btn btn-sm btn-ghost" href="/normativa/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$d['id']; ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i></a>
                    <?php if ($puede): ?>
                        <form method="post" action="/normativa/reindexar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>"><button class="btn btn-sm btn-ghost" type="submit" title="Volver a leer el PDF"><i class="fa-solid fa-rotate"></i></button></form>
                        <form method="post" action="/normativa/eliminar" data-confirmar="¿Eliminar el documento, sus cláusulas y los vínculos que tenga?"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>"><button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i></button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody></table></div>
    </div>
    </div>
<?php endif; ?>
