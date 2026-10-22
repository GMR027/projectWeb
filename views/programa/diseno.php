<?php // Variables: $proyecto, $error, $columnas (todas las del catálogo; activas primero), $indicador
use Classes\ProgramaColumnas as PC;
$nomPrograma = trim((string)($proyecto->programa_nombre ?? ''));
$tiposTxt = ['texto' => 'Texto', 'fecha' => 'Fecha', 'numero' => 'Número', 'actividad' => 'Actividad', 'calculado' => 'Se calcula', 'acciones' => 'Botones', 'imagen' => 'Imagen', 'cp' => 'Campo propio'];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-table-columns"></i> Diseñar programa de obra</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · elige qué columnas lleva la tabla del programa, cómo se llaman y en qué orden</p>
    </div>
    <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Programa</a>
</div>
<?php if ($error): ?><div class="alerta alerta-error"><?php echo s($error); ?></div><?php endif; ?>
<div class="card">
    <form method="POST" action="/programa/diseno" class="formulario" id="prg-diseno">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <div class="form-grid">
            <div class="campo campo-full"><label for="programa_nombre">Nombre del programa de obra</label>
                <input type="text" id="programa_nombre" name="programa_nombre" maxlength="200" value="<?php echo s($nomPrograma); ?>" placeholder="<?php echo s($proyecto->nombre); ?>">
                <small class="text-muted">Vacío = el nombre del proyecto.</small></div>
        </div>

        <h3 class="mt-2">Columnas</h3>
        <small class="text-muted">Marca las columnas que quieres ver, ponles el nombre que prefieras y ordénalas con ☰ o las flechas. <strong>Nombre</strong> y <strong>Acciones</strong> siempre están. «Predecesora» y «Sucesora» usan las dependencias de obra (ruta crítica incluida); los tres campos libres puedes renombrarlos como quieras.</small>
        <ul id="cols-lista" class="msr-cols">
            <?php foreach ($columnas as $c): $oblig = in_array($c['k'], PC::OBLIGATORIAS, true); ?>
                <li draggable="true" data-k="<?php echo s($c['k']); ?>">
                    <span class="col-asa" title="Arrastra para mover">☰</span>
                    <input type="checkbox" class="col-on"<?php echo $c['activa'] ? ' checked' : ''; ?><?php echo $oblig ? ' disabled title="Siempre visible"' : ''; ?> aria-label="Mostrar <?php echo s($c['generico']); ?>">
                    <input type="hidden" data-f="k" value="<?php echo s($c['k']); ?>">
                    <input type="text" data-f="n" maxlength="60" value="<?php echo $c['propio'] ? s($c['n']) : ''; ?>" placeholder="<?php echo s($c['generico']); ?>" title="Nombre de la columna (vacío = <?php echo s($c['generico']); ?>)">
                    <span class="msr-origen msr-origen-<?php echo $c['tipo'] === 'calculado' ? 'calculo' : ($c['tipo'] === 'texto' || $c['tipo'] === 'numero' || $c['tipo'] === 'fecha' ? 'texto' : 'dato'); ?>"><?php echo s($tiposTxt[$c['tipo']] ?? ''); ?></span>
                    <button type="button" class="btn btn-sm btn-ghost" data-mover="-1" title="Mover antes">↑</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-mover="1" title="Mover después">↓</button>
                </li>
            <?php endforeach; ?>
        </ul>

        <h3 class="mt-2">Indicador condicional</h3>
        <small class="text-muted">Una columna «Indicador» muestra un punto de color según el valor del dato que elijas. Las reglas se revisan en orden y manda la primera que coincide (por ejemplo: 0 rojo, de 1 a 50 amarillo, de 51 a 100 verde).</small>
        <div class="form-grid" style="margin-top:1rem">
            <div class="campo"><label for="indicador_campo">Dato que evalúa</label>
                <select id="indicador_campo" name="indicador_campo">
                    <?php foreach (PC::CAMPOS_INDICADOR as $k => $etq): ?><option value="<?php echo s($k); ?>"<?php echo $indicador['campo'] === $k ? ' selected' : ''; ?>><?php echo s($etq); ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div id="prg-reglas" class="prg-reglas"></div>
        <button type="button" class="btn btn-ghost btn-sm" id="prg-regla-add">+ Agregar regla</button>
        <div class="prg-ind-previa text-sm text-muted" id="prg-ind-previa" aria-live="polite"></div>

        <p style="margin-top:2rem"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar diseño</button>
            <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">Cancelar</a></p>
    </form>
</div>
<script>
(() => {
    const lista = document.getElementById('cols-lista');
    const renumerar = () => [...lista.children].forEach((li, i) => {
        const on = li.querySelector('.col-on');
        li.querySelectorAll('[data-f]').forEach((inp) => { inp.name = `columnas[${i}][${inp.dataset.f}]`; inp.disabled = !(on.checked || on.disabled); });
    });
    lista.addEventListener('change', (e) => { if (e.target.classList.contains('col-on')) renumerar(); });
    lista.addEventListener('click', (e) => {
        const b = e.target.closest('[data-mover]'); if (!b) return;
        const li = b.closest('li'), d = +b.dataset.mover;
        if (d < 0 && li.previousElementSibling) li.previousElementSibling.before(li);
        if (d > 0 && li.nextElementSibling) li.nextElementSibling.after(li);
        renumerar();
    });
    lista.querySelectorAll('li').forEach((li) => {
        li.addEventListener('dragstart', (e) => { li.classList.add('arrastrando'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', li.dataset.k); });
        li.addEventListener('dragend', () => { li.classList.remove('arrastrando'); renumerar(); });
    });
    lista.addEventListener('dragover', (e) => {
        e.preventDefault();
        const mov = lista.querySelector('.arrastrando'); if (!mov) return;
        const sig = [...lista.children].filter((x) => x !== mov).find((x) => e.clientY < x.getBoundingClientRect().top + x.offsetHeight / 2);
        sig ? sig.before(mov) : lista.append(mov);
    });
    renumerar();

    // ---- reglas del indicador ----
    const cont = document.getElementById('prg-reglas'), previa = document.getElementById('prg-ind-previa');
    const reglas = <?php echo json_encode($indicador['reglas'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    const el = (tag, attrs = {}, hijos = []) => { const e = document.createElement(tag); Object.entries(attrs).forEach(([k, v]) => k === 'text' ? e.textContent = v : e.setAttribute(k, v)); hijos.forEach((h) => e.append(h)); return e; };
    const renumReglas = () => {
        [...cont.children].forEach((row, i) => row.querySelectorAll('[data-r]').forEach((inp) => { inp.name = `reglas[${i}][${inp.dataset.r}]`; }));
        previa.replaceChildren(...[...cont.children].map((row) => {
            const [mn, mx, col] = ['min', 'max', 'color'].map((k) => row.querySelector(`[data-r="${k}"]`).value);
            return el('span', { class: 'prg-chip' }, [el('span', { class: 'prg-ind', style: `--ind:${col}` }), document.createTextNode(` ${mn === mx ? mn : mn + ' a ' + mx}`)]);
        }));
    };
    const agregar = (r = { min: 0, max: 100, color: '#94a3b8' }) => {
        const row = el('div', { class: 'prg-regla' }, [
            el('label', { class: 'text-sm' }, [document.createTextNode('Desde '), el('input', { type: 'number', step: 'any', 'data-r': 'min', value: r.min, required: '' })]),
            el('label', { class: 'text-sm' }, [document.createTextNode('hasta '), el('input', { type: 'number', step: 'any', 'data-r': 'max', value: r.max, required: '' })]),
            el('input', { type: 'color', 'data-r': 'color', value: r.color, title: 'Color' }),
        ]);
        const quitar = el('button', { type: 'button', class: 'btn btn-sm btn-danger', title: 'Quitar regla', text: '✕' });
        quitar.addEventListener('click', () => { row.remove(); renumReglas(); });
        row.append(quitar);
        row.addEventListener('input', renumReglas);
        cont.append(row); renumReglas();
    };
    reglas.forEach(agregar);
    document.getElementById('prg-regla-add').addEventListener('click', () => agregar());
})();
</script>
