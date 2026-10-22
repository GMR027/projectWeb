<?php // Variables: $proyecto, $herramienta, $info [título, entidad, ruta, ordenable, obligatorias, catálogo], $columnas (todas; activas primero), $error
[$titulo, $entidad, $ruta, $ordenable, $obligatorias] = $info;
$pid = (int)$proyecto->id;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-table-columns"></i> Diseñar <?php echo s(mb_strtolower($titulo)); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · elige qué columnas lleva la lista, cómo se llaman<?php echo $ordenable ? ' y en qué orden' : ''; ?></p>
    </div>
    <a href="<?php echo s($ruta); ?>?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← <?php echo s($titulo); ?></a>
</div>
<?php if ($error): ?><div class="alerta alerta-error"><?php echo s($error); ?></div><?php endif; ?>
<div class="card">
    <form method="POST" action="/lista/diseno" class="formulario" id="lista-diseno">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <input type="hidden" name="herramienta" value="<?php echo s($herramienta); ?>">
        <small class="text-muted"><?php echo s(t('Marca las columnas que quieres ver y ponles el nombre que prefieras')); ?><?php echo $ordenable ? s(t('; ordénalas con ☰ o las flechas')) : s(t(' (en esta herramienta el orden de las columnas es fijo)')); ?>.
            <?php echo s(t('Siempre están:')); ?> <strong><?php echo s(implode(', ', array_map(fn($k) => t($info[5][$k][0]), $obligatorias))); ?></strong>.
            <?php echo s(t('Los campos con')); ?> <span class="msr-origen msr-origen-dato">Propio</span> <?php echo s(t('son')); ?> <a href="/campos?proyecto=<?php echo $pid; ?>"><?php echo s(t('campos personalizados')); ?></a> <?php echo s(t('del proyecto.')); ?></small>
        <ul id="cols-lista" class="msr-cols">
            <?php foreach ($columnas as $c): $oblig = in_array($c['k'], $obligatorias, true); ?>
                <li<?php echo $ordenable ? ' draggable="true"' : ''; ?> data-k="<?php echo s($c['k']); ?>">
                    <span class="col-asa" title="<?php echo $ordenable ? 'Arrastra para mover' : 'Orden fijo'; ?>"><?php echo $ordenable ? '☰' : '·'; ?></span>
                    <input type="checkbox" class="col-on"<?php echo $c['activa'] ? ' checked' : ''; ?><?php echo $oblig ? ' disabled title="Siempre visible"' : ''; ?> aria-label="Mostrar <?php echo s($c['generico']); ?>">
                    <input type="hidden" data-f="k" value="<?php echo s($c['k']); ?>">
                    <input type="text" data-f="n" maxlength="60" value="<?php echo $c['propio'] ? s($c['n']) : ''; ?>" placeholder="<?php echo s($c['generico']); ?>" title="Nombre de la columna (vacío = <?php echo s($c['generico']); ?>)">
                    <?php if (str_starts_with($c['k'], 'cp_')): ?><span class="msr-origen msr-origen-dato">Propio</span><?php endif; ?>
                    <?php if ($ordenable): ?>
                        <button type="button" class="btn btn-sm btn-ghost" data-mover="-1" title="Mover antes">↑</button>
                        <button type="button" class="btn btn-sm btn-ghost" data-mover="1" title="Mover después">↓</button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p style="margin-top:2rem"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?php echo s(t('Guardar diseño')); ?></button>
            <button type="submit" name="restablecer" value="1" class="btn btn-ghost" formnovalidate data-confirm="¿Restablecer el diseño?|Vuelven las columnas y los nombres de siempre."><?php echo s(t('Restablecer')); ?></button>
            <a href="<?php echo s($ruta); ?>?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><?php echo s(t('Cancelar')); ?></a></p>
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
    lista.querySelectorAll('li[draggable]').forEach((li) => {
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
})();
</script>
