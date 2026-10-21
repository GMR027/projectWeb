<?php
$puede = puede_gestionar_proyecto($proyecto); $desc = puede_descargar(); $id = s($proyecto->id);
$carpetaActual = null;
foreach ($carpetas as $k) if ($carpeta && (int)$k->id === (int)$carpeta) $carpetaActual = $k;
$urlCarpeta = fn($c) => '/archivos?proyecto=' . $id . ($c !== null ? '&carpeta=' . $c : '') . ($filtro ? '&categoria=' . (int)$filtro : '');
?>
<?php require_once __DIR__ . '/../templates/herramientas-proyecto.php'; pestanas_herramienta('archivo', '/archivos', $proyecto); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-folder-open"></i> Archivo</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($archivos); ?> archivo(s)</p>
    </div>
    <div class="la-controles">
        <form method="GET" class="buscador-inline">
            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
            <?php if ($carpeta !== null): ?><input type="hidden" name="carpeta" value="<?php echo $carpeta === 0 ? 'sin' : (int)$carpeta; ?>"><?php endif; ?>
            <select name="categoria" onchange="this.form.submit()" aria-label="Filtrar por categoría">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $c): ?><option value="<?php echo s($c->id); ?>" <?php echo (int)$filtro === (int)$c->id ? 'selected' : ''; ?>><?php echo s($c->nombre); ?></option><?php endforeach; ?>
            </select>
        </form>
        <a href="/archivos/categorias?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-tags"></i> Categorías</a>
        <?php if ($puede): ?><a href="/archivos/crear?proyecto=<?php echo $id; ?><?php echo $carpetaActual ? '&carpeta=' . (int)$carpetaActual->id : ''; ?>" class="btn btn-primary">+ Subir archivo</a><?php endif; ?>
    </div>
</div>
<?php $nombresCarpeta = []; foreach ($carpetas as $k) $nombresCarpeta[(int)$k->id] = $k->nombre; ?>
<div class="archivero">
    <aside class="archivero-lateral card">
        <div class="archivero-titulo">
            <span><i class="fa-solid fa-box-archive"></i> Archivero</span>
            <?php if ($puede): ?><button type="button" class="btn btn-sm btn-primary" id="arch-nueva" title="Nueva carpeta"><i class="fa-solid fa-folder-plus"></i></button><?php endif; ?>
        </div>
        <ul class="archivero-carpetas">
            <li><a href="<?php echo s($urlCarpeta(null)); ?>" class="<?php echo $carpeta === null ? 'activa' : ''; ?>"><i class="fa-solid fa-layer-group"></i> <span>Todos</span> <em><?php echo (int)$totalArchivos; ?></em></a></li>
            <li><a href="<?php echo s($urlCarpeta('sin')); ?>" class="<?php echo $carpeta === 0 ? 'activa' : ''; ?>"><i class="fa-regular fa-file"></i> <span>Sin carpeta</span> <em><?php echo (int)$sinCarpeta; ?></em></a></li>
            <?php foreach ($carpetas as $k): ?>
                <li>
                    <a href="<?php echo s($urlCarpeta((int)$k->id)); ?>" class="<?php echo $carpetaActual && (int)$carpetaActual->id === (int)$k->id ? 'activa' : ''; ?>"><i class="fa-solid fa-folder"></i> <span><?php echo s($k->nombre); ?></span> <em><?php echo (int)$k->total; ?></em></a>
                    <?php if ($puede): ?>
                        <button type="button" class="btn-icono arch-renombrar" data-id="<?php echo s($k->id); ?>" data-nombre="<?php echo s($k->nombre); ?>" title="Renombrar" aria-label="Renombrar carpeta"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="btn-icono arch-borrar" data-id="<?php echo s($k->id); ?>" data-nombre="<?php echo s($k->nombre); ?>" data-total="<?php echo (int)$k->total; ?>" title="Eliminar" aria-label="Eliminar carpeta"><i class="fa-solid fa-trash"></i></button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$carpetas): ?><p class="text-sm text-muted">Crea carpetas (compras, ingeniería, calidad…) para organizar tus archivos.</p><?php endif; ?>
    </aside>
    <div class="archivero-principal">
        <?php if ($puede && $archivos): ?>
        <form method="POST" action="/archivos/mover" class="archivero-mover" id="arch-mover">
            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
            <span class="text-sm text-muted"><strong id="arch-n">0</strong> seleccionado(s)</span>
            <select name="carpeta_id" aria-label="Mover a">
                <option value="">— Sin carpeta —</option>
                <?php foreach ($carpetas as $k): ?><option value="<?php echo s($k->id); ?>"><?php echo s($k->nombre); ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary" id="arch-mover-btn" disabled><i class="fa-solid fa-folder-tree"></i> Mover a la carpeta</button>
        </form>
        <?php endif; ?>
<div class="card archivero-lista">
    <?php if (empty($archivos)): ?>
        <p class="text-muted">No hay archivos<?php echo $carpeta !== null ? ($carpeta === 0 ? ' sin carpeta' : ' en esta carpeta') : ''; ?><?php echo $filtro ? ' en esta categoría' : ''; ?>.<?php echo $puede ? ' Sube el primero con «Subir archivo».' : ''; ?></p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><?php if ($puede): ?><th class="col-sel"><input type="checkbox" id="arch-todos" aria-label="Seleccionar todos"></th><?php endif; ?><th class="hide-m">Fecha</th><th class="hide-m">Categoría</th><th>Nombre del archivo</th><th class="hide-m">Rev.</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($archivos as $a): ?>
                <tr>
                    <?php if ($puede): ?><td class="col-sel"><input type="checkbox" class="arch-sel" value="<?php echo s($a->id); ?>" aria-label="Seleccionar <?php echo s($a->nombre); ?>"></td><?php endif; ?>
                    <td class="mono hide-m" style="white-space:nowrap"><?php echo s(fecha_corta_es($a->fecha)); ?></td>
                    <td class="hide-m"><?php echo s($a->categoria_nombre ?: '—'); ?></td>
                    <td class="arch-nombre">
                        <?php echo archivo_icono_html($a->extension()); ?> <?php echo s($a->nombre); ?>
                        <div class="text-sm text-muted mono"><?php echo s($a->archivo_original); ?> · <?php echo s($a->tamanoLegible()); ?></div>
                        <?php if (isset($ensamblados[(int)$a->id])): ?><div class="text-sm"><span class="badge-carpeta" title="Se regenera sola cuando cambia alguno de sus documentos"><i class="fa-solid fa-object-group"></i> Ensamblado · se actualiza solo</span></div><?php endif; ?>
                        <?php if ($carpeta === null && $a->carpeta_id && isset($nombresCarpeta[(int)$a->carpeta_id])): ?><div class="text-sm"><span class="badge-carpeta"><i class="fa-solid fa-folder"></i> <?php echo s($nombresCarpeta[(int)$a->carpeta_id]); ?></span></div><?php endif; ?>
                        <div class="solo-movil text-sm text-muted"><?php echo s(fecha_corta_es($a->fecha)); ?><?php echo $a->categoria_nombre ? ' · ' . s($a->categoria_nombre) : ''; ?><?php echo $a->numero_revision ? ' · Rev. ' . s($a->numero_revision) : ''; ?></div>
                    </td>
                    <td class="mono hide-m"><?php echo s($a->numero_revision ?: '—'); ?></td>
                    <td class="acciones">
                        <?php if ($desc && \Classes\VistaArchivo::tipo($a->extension())): ?><a href="/archivos/ver?proyecto=<?php echo $id; ?>&id=<?php echo s($a->id); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost"><i class="fa-regular fa-eye"></i> Ver</a><?php endif; ?>
                        <?php if ($desc): ?><a href="/archivos/descargar?proyecto=<?php echo $id; ?>&id=<?php echo s($a->id); ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-download"></i> Descargar</a><?php endif; ?>
                        <?php if ($puede): ?>
                            <a href="/archivos/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($a->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                            <form method="POST" action="/archivos/eliminar" style="display:inline" data-confirm="¿Eliminar archivo?|Se borrará «<?php echo s($a->nombre); ?>» del servidor. Esta acción no se puede deshacer.|Eliminar">
                                <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                                <input type="hidden" name="id" value="<?php echo s($a->id); ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
    </div>
</div>
<?php if ($puede): ?>
<div class="modal-overlay" id="arch-modal-carpeta" aria-hidden="true">
    <form class="modal-card" method="POST" action="/archivos/carpetas/guardar" role="dialog" aria-modal="true" aria-labelledby="arch-carpeta-titulo">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <input type="hidden" name="id" id="arch-carpeta-id" value="">
        <h3 class="modal-titulo" id="arch-carpeta-titulo">Nueva carpeta</h3>
        <div class="campo"><label for="arch-carpeta-nombre">Nombre *</label><input type="text" id="arch-carpeta-nombre" name="nombre" maxlength="100" required placeholder="Ej: Compras, Ingeniería, Calidad"></div>
        <div class="modal-acciones">
            <button type="button" class="btn btn-ghost" data-cerrar><i class="fa-solid fa-xmark"></i> Cancelar</button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </div>
    </form>
</div>
<div class="modal-overlay" id="arch-modal-borrar" aria-hidden="true">
    <form class="modal-card" method="POST" action="/archivos/carpetas/eliminar" role="dialog" aria-modal="true" aria-labelledby="arch-borrar-titulo">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <input type="hidden" name="id" id="arch-borrar-id" value="">
        <h3 class="modal-titulo" id="arch-borrar-titulo">Eliminar carpeta</h3>
        <p class="text-muted" id="arch-borrar-msg"></p>
        <div class="archivero-opciones">
            <label><input type="radio" name="modo" value="solo" checked> <span><strong>Solo la carpeta</strong><br><small class="text-muted">Los archivos no se borran: vuelven a «Sin carpeta».</small></span></label>
            <label id="arch-borrar-todo"><input type="radio" name="modo" value="todo"> <span><strong>La carpeta y su contenido</strong><br><small class="text-muted">Se borran también sus <span id="arch-borrar-n"></span> archivo(s) del servidor. No se puede deshacer.</small></span></label>
        </div>
        <div class="modal-acciones">
            <button type="button" class="btn btn-ghost" data-cerrar><i class="fa-solid fa-xmark"></i> Cancelar</button>
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Eliminar</button>
        </div>
    </form>
</div>
<script>
(() => {
    const $ = (id) => document.getElementById(id);
    const abrir = (m) => { m.classList.add('abierto'); m.setAttribute('aria-hidden', 'false'); };
    const cerrar = (m) => { m.classList.remove('abierto'); m.setAttribute('aria-hidden', 'true'); };
    document.querySelectorAll('#arch-modal-carpeta, #arch-modal-borrar').forEach((m) => {
        m.addEventListener('click', (e) => { if (e.target === m || e.target.closest('[data-cerrar]')) cerrar(m); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrar(m); });
    });
    const mc = $('arch-modal-carpeta');
    const abrirCarpeta = (id, nombre) => {
        $('arch-carpeta-id').value = id;
        $('arch-carpeta-nombre').value = nombre;
        $('arch-carpeta-titulo').textContent = id ? 'Renombrar carpeta' : 'Nueva carpeta';
        abrir(mc);
        $('arch-carpeta-nombre').focus();
    };
    $('arch-nueva')?.addEventListener('click', () => abrirCarpeta('', ''));
    document.querySelectorAll('.arch-renombrar').forEach((b) => b.addEventListener('click', () => abrirCarpeta(b.dataset.id, b.dataset.nombre)));
    const mb = $('arch-modal-borrar');
    document.querySelectorAll('.arch-borrar').forEach((b) => b.addEventListener('click', () => {
        const n = parseInt(b.dataset.total, 10) || 0;
        $('arch-borrar-id').value = b.dataset.id;
        $('arch-borrar-msg').textContent = `¿Qué hacer con la carpeta «${b.dataset.nombre}»?` + (n ? '' : ' Está vacía.');
        $('arch-borrar-n').textContent = n;
        $('arch-borrar-todo').style.display = n ? '' : 'none';
        mb.querySelector('input[value="solo"]').checked = true;
        abrir(mb);
    }));
    // selección múltiple para mover
    const checks = [...document.querySelectorAll('.arch-sel')], todos = $('arch-todos'), form = $('arch-mover');
    const actualizar = () => {
        const sel = checks.filter((c) => c.checked);
        if (!form) return;
        $('arch-n').textContent = sel.length;
        $('arch-mover-btn').disabled = !sel.length;
        form.querySelectorAll('input[name="ids[]"]').forEach((i) => i.remove());
        sel.forEach((c) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = c.value; form.appendChild(i); });
        if (todos) todos.checked = sel.length === checks.length && checks.length > 0;
    };
    checks.forEach((c) => c.addEventListener('change', actualizar));
    todos?.addEventListener('change', () => { checks.forEach((c) => { if (c.closest('tr').style.display !== 'none') c.checked = todos.checked; }); actualizar(); });
})();
</script>
<?php endif; ?>
