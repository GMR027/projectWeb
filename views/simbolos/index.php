<?php // Variables: $proyecto, $simbolos, $predeterminados, $puedeEditar, $categorias ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-shapes"></i> <?php echo s(t('Símbolos')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · biblioteca para insertar en el vPlan y en el Editor</p>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar): ?>
            <button type="button" class="btn btn-primary" id="sim-imp-btn" title="Sube un .zip de biblioteca o varias imágenes"><i class="fa-solid fa-file-import"></i> <?php echo s(t('Importar símbolos')); ?></button>
            <input type="file" id="sim-imp-archivo" accept=".zip,image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg" multiple hidden>
        <?php endif; ?>
        <a href="/simbolos/exportar?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost"><i class="fa-solid fa-file-export"></i> <?php echo s(t('Exportar biblioteca')); ?></a>
        <a href="/graficos?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← Editor</a>
    </div>
</div>
<p class="text-muted text-sm" id="sim-imp-estado" aria-live="polite"></p>

<?php if ($puedeEditar): ?>
<div class="card">
    <h3><?php echo s(t('Nuevo símbolo')); ?></h3>
    <form method="POST" action="/simbolos/guardar" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <div class="form-grid">
            <div class="campo"><label for="sim-nombre">Nombre *</label><input type="text" id="sim-nombre" name="nombre" maxlength="100" placeholder="Ej: Cámara tipo bala" required></div>
            <div class="campo"><label for="sim-cat">Categoría</label><input type="text" id="sim-cat" name="categoria" maxlength="60" list="sim-cats" placeholder="Ej: Seguridad">
                <datalist id="sim-cats"><?php foreach ($categorias as $c): ?><option value="<?php echo s($c); ?>"><?php endforeach; ?></datalist></div>
            <div class="campo"><label for="sim-archivo">Imagen * (png con fondo transparente o svg funcionan mejor)</label><input type="file" id="sim-archivo" name="archivo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg" required></div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Agregar símbolo</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3><?php echo s(t('Del proyecto')); ?></h3>
    <?php if (!$simbolos): ?>
        <p class="text-muted">Aún no hay símbolos propios.<?php echo $puedeEditar ? ' Agrega el primero arriba; aparecerá en la pestaña «Símbolos» de los editores.' : ''; ?></p>
    <?php else: ?>
        <div class="sim-admin">
        <?php foreach ($simbolos as $sim): ?>
            <div class="sim-tarjeta">
                <div class="sim-img"><img src="<?php echo s($sim->url()); ?>" alt="" loading="lazy"></div>
                <div class="sim-nombre"><?php echo s($sim->nombre); ?></div>
                <div class="text-muted text-sm"><?php echo s($sim->categoria !== '' ? $sim->categoria : 'Sin categoría'); ?></div>
                <?php if ($puedeEditar): ?>
                <details>
                    <summary><?php echo s(t('Editar')); ?></summary>
                    <form method="POST" action="/simbolos/guardar" enctype="multipart/form-data" class="formulario">
                        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$sim->id; ?>">
                        <div class="campo"><label><?php echo s(t('Nombre')); ?></label><input type="text" name="nombre" maxlength="100" value="<?php echo s($sim->nombre); ?>" required></div>
                        <div class="campo"><label>Categoría</label><input type="text" name="categoria" maxlength="60" list="sim-cats" value="<?php echo s($sim->categoria); ?>"></div>
                        <div class="campo"><label>Cambiar imagen</label><input type="file" name="archivo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,.svg"></div>
                        <button type="submit" class="btn btn-sm btn-primary"><?php echo s(t('Guardar')); ?></button>
                    </form>
                </details>
                <form method="POST" action="/simbolos/eliminar" data-confirm="¿Eliminar «<?php echo s($sim->nombre); ?>»?|Las marcas que ya lo usan en tus dibujos no cambian.|Sí, eliminar">
                    <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$sim->id; ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> <?php echo s(t('Eliminar')); ?></button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card" id="simbologias">
    <h3><?php echo s(t('Simbologías')); ?></h3>
    <p class="text-muted text-sm">Las miniaturas son la imagen guardada: revísala aquí antes de usarla en una lámina. Leyendas (p. ej. «SYMBOLOGY FOR SCHEDULE») dibujadas con el Editor: formas, líneas, textos e imágenes. Se eligen en cada lámina del vPlan; si editas una, cambia en todas las láminas que la usan.</p>
    <?php if ($puedeEditar): ?>
    <div class="la-controles">
        <a href="/graficos/crear?proyecto=<?php echo (int)$proyecto->id; ?>&tipo=simbologia" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva simbología</a>
        <?php foreach (['rectangular' => ['Plantilla rectangular', 'fa-rectangle-list'], 'cuadrada' => ['Plantilla cuadrada', 'fa-square']] as $forma => [$etq, $ico]): ?>
        <form method="POST" action="/graficos/simbologia-estandar" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="forma" value="<?php echo $forma; ?>">
            <button type="submit" class="btn btn-ghost" title="Parte de las 4 filas actuales (COMPLETE, IN PROGRESS, ON HOLD, CONSTRAIN) y edítalas"><i class="fa-solid <?php echo $ico; ?>"></i> <?php echo $etq; ?> estándar</button></form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!$simbologias): ?>
        <p class="text-muted">Aún no hay simbologías. Mientras tanto, las láminas muestran la tabla estándar.</p>
    <?php else: ?>
        <div class="sim-admin">
        <?php foreach ($simbologias as $g): $img = $g->imagenSimbologia(); ?>
            <div class="sim-tarjeta">
                <div class="sim-img"><?php if ($img): ?><img src="/uploads/vplan/<?php echo s($img); ?>" alt="" loading="lazy"><?php else: ?><span class="text-sm" style="color:#475569;padding:.8rem;text-align:center">Sin imagen: ábrela en el Editor y guárdala</span><?php endif; ?></div>
                <div class="sim-nombre"><?php echo s($g->nombre); ?></div>
                <div class="text-muted text-sm"><?php echo (int)$g->ancho; ?> × <?php echo (int)$g->alto; ?> px</div>
                <?php if ($puedeEditar): ?>
                <a href="/graficos/abrir?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$g->id; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen-ruler"></i> Editar</a>
                <form method="POST" action="/graficos/duplicar" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>"><button type="submit" class="btn btn-sm btn-ghost"><i class="fa-regular fa-copy"></i> <?php echo s(t('Duplicar')); ?></button></form>
                <form method="POST" action="/graficos/tipo" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>"><input type="hidden" name="tipo" value="grafico"><button type="submit" class="btn btn-sm btn-ghost" title="Devolverla a la lista de gráficos del Editor">Pasar a gráfico</button></form>
                <form method="POST" action="/graficos/eliminar" data-confirm="¿Eliminar «<?php echo s($g->nombre); ?>»?|Las láminas del vPlan que la usan volverán a la tabla estándar.|Sí, eliminar">
                    <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$g->id; ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> <?php echo s(t('Eliminar')); ?></button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card" id="cajetines">
    <h3><?php echo s(t('Cajetines')); ?></h3>
    <p class="text-muted text-sm">Tabla con los datos del dibujo que se inserta con un clic desde la pestaña «Símbolos» de los editores. Una fila por línea: <code>Etiqueta | valor</code>. Variables: <code>{proyecto}</code> <code>{titulo}</code> <code>{fecha}</code> <code>{autor}</code> <code>{lamina}</code> <code>{total}</code> <code>{escala}</code>. Ya existe uno «Estándar» para todos los proyectos.</p>
    <?php if ($puedeEditar): ?>
    <form method="POST" action="/simbolos/cajetin" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <div class="form-grid">
            <div class="campo"><label>Nombre *</label><input type="text" name="nombre" maxlength="100" placeholder="Ej: Plano de instalaciones" required></div>
            <div class="campo"><label>Forma</label><select name="orientacion"><option value="vertical">Tabla de dos columnas (esquina inferior derecha)</option><option value="banda">Banda inferior (una columna por dato)</option></select></div>
            <div class="campo"><label>Ancho (% del dibujo)</label><input type="number" name="ancho" min="15" max="100" value="28"></div>
            <div class="campo campo-full"><label>Filas *</label><textarea name="filas" rows="5" required placeholder="Proyecto | {proyecto}&#10;Título | {titulo}&#10;Fecha | {fecha}&#10;Revisó |"></textarea></div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Agregar cajetín</button>
    </form>
    <?php endif; ?>
    <?php foreach ($cajetines as $c): ?>
        <details style="margin-top:1.2rem">
            <summary><strong><?php echo s($c->nombre); ?></strong> <span class="text-muted text-sm">· <?php echo $c->orientacion === 'banda' ? 'banda inferior' : 'tabla'; ?> · <?php echo (int)$c->ancho; ?> % · <?php echo count($c->filas()); ?> fila(s)</span></summary>
            <?php if ($puedeEditar): ?>
            <form method="POST" action="/simbolos/cajetin" class="formulario" style="margin-top:.8rem">
                <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$c->id; ?>">
                <div class="form-grid">
                    <div class="campo"><label><?php echo s(t('Nombre')); ?></label><input type="text" name="nombre" maxlength="100" value="<?php echo s($c->nombre); ?>" required></div>
                    <div class="campo"><label>Forma</label><select name="orientacion"><option value="vertical"<?php echo $c->orientacion === 'vertical' ? ' selected' : ''; ?>>Tabla de dos columnas</option><option value="banda"<?php echo $c->orientacion === 'banda' ? ' selected' : ''; ?>>Banda inferior</option></select></div>
                    <div class="campo"><label>Ancho (%)</label><input type="number" name="ancho" min="15" max="100" value="<?php echo (int)$c->ancho; ?>"></div>
                    <div class="campo campo-full"><label>Filas</label><textarea name="filas" rows="5" required><?php echo s($c->textoFilas()); ?></textarea></div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary"><?php echo s(t('Guardar')); ?></button>
            </form>
            <form method="POST" action="/simbolos/cajetin/eliminar" style="margin-top:.6rem" data-confirm="¿Eliminar el cajetín «<?php echo s($c->nombre); ?>»?|Los dibujos que ya lo usan no cambian.|Sí, eliminar">
                <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$c->id; ?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> <?php echo s(t('Eliminar')); ?></button>
            </form>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</div>

<div class="card">
    <h3><?php echo s(t('Predeterminados')); ?></h3>
    <p class="text-muted text-sm">Incluidos con la aplicación para todos los proyectos.</p>
    <div class="sim-admin">
        <?php foreach ($predeterminados as $sim): ?>
            <div class="sim-tarjeta">
                <div class="sim-img"><img src="<?php echo s($sim['src']); ?>" alt="" loading="lazy"></div>
                <div class="sim-nombre"><?php echo s($sim['nombre']); ?></div>
                <div class="text-muted text-sm"><?php echo s($sim['cat']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function () {
    const b = document.getElementById('sim-imp-btn'), f = document.getElementById('sim-imp-archivo'), est = document.getElementById('sim-imp-estado');
    if (!b) return;
    b.addEventListener('click', () => f.click());
    f.addEventListener('change', async () => {
        if (!f.files.length) return;
        const fd = new FormData(); fd.append('proyecto', <?php echo (int)$proyecto->id; ?>); [...f.files].forEach(a => fd.append('archivos[]', a));
        est.textContent = 'Importando…'; b.disabled = true;
        try {
            const d = await (await fetch('/api/simbolos/importar', { method: 'POST', body: fd })).json();
            if (!d.success) { est.textContent = 'Error: ' + (d.error || 'no se pudo importar'); b.disabled = false; return; }
            location.href = '/simbolos?proyecto=<?php echo (int)$proyecto->id; ?>&ok=1';
        } catch (e) { est.textContent = 'Error al importar'; b.disabled = false; }
        f.value = '';
    });
})();
</script>
