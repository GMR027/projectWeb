<?php
$esNuevo = !$minuta->id; $id = s($proyecto->id);
$itemsCo = []; foreach ($contratista as $pid => $p) $itemsCo[] = ['id' => $pid, 'titulo' => $p->nombre, 'detalle' => trim($p->empresa . ' ' . $p->puesto) ?: '—'];
$itemsPe = []; foreach ($personal as $pid => $p) $itemsPe[] = ['id' => $pid, 'titulo' => $p->nombre, 'detalle' => $p->departamento ?: '—'];
$sugerencias = array_values(array_unique(array_merge(array_map(fn($p) => $p->nombre, $contratista), array_map(fn($p) => $p->nombre, $personal))));
$bloques = ['temas' => ['Temas vistos', $minuta->getTemas(), 'Tema'], 'compromisos' => ['Compromisos (opcional)', $minuta->getCompromisos(), 'Compromiso']];
?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nueva minuta' : 'Editar minuta'; ?></h2>
    <a href="/minutas?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/minutas/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario" id="form-minuta">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($minuta->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($minuta->fecha); ?>" required></div>
            <div class="campo">
                <label for="importancia">Nivel de importancia *</label>
                <select id="importancia" name="importancia">
                    <?php foreach (\Model\Bitacora::IMPORTANCIAS as $k => $v): ?><option value="<?php echo s($k); ?>" <?php echo $minuta->importancia === $k ? 'selected' : ''; ?>><?php echo s($v); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="departamento">Carpeta (departamento)</label>
                <input type="text" id="departamento" name="departamento" maxlength="100" list="min-deptos" value="<?php echo s($minuta->departamento); ?>" placeholder="Sin departamento">
                <datalist id="min-deptos"><?php foreach ($departamentos as $dn): ?><option value="<?php echo s($dn); ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="campo campo-full"><label for="nombre">Nombre de la minuta *</label><input type="text" id="nombre" name="nombre" maxlength="200" value="<?php echo s($minuta->nombre); ?>" required></div>
        </div>

        <div class="campo">
            <label>Personal del contratista</label>
            <?php $items = $itemsCo; $nombreCampo = 'contratista'; $marcados = $minuta->getContratista(); include __DIR__ . '/../templates/lista-accesos.php'; ?>
            <?php if (!$contratista): ?><small class="text-muted">Aún no hay personal del contratista. <a href="/minutas/contratista/crear?proyecto=<?php echo $id; ?>">Agrégalo aquí</a>.</small><?php endif; ?>
        </div>
        <div class="campo">
            <label>Personal del proyecto</label>
            <?php $items = $itemsPe; $nombreCampo = 'personal'; $marcados = $minuta->getPersonal(); include __DIR__ . '/../templates/lista-accesos.php'; ?>
            <?php if (!$personal): ?><small class="text-muted">Aún no hay personal del proyecto. <a href="/reportes/personal/crear?proyecto=<?php echo $id; ?>">Agrégalo aquí</a>.</small><?php endif; ?>
        </div>

        <datalist id="mi-sugerencias"><?php foreach ($sugerencias as $n): ?><option value="<?php echo s($n); ?>"><?php endforeach; ?></datalist>
        <?php foreach ($bloques as $campo => [$titulo, $lista, $etiqueta]): ?>
            <div class="campo mi-bloque" data-campo="<?php echo $campo; ?>" data-items="<?php echo s(json_encode($lista, JSON_UNESCAPED_UNICODE)); ?>">
                <label><?php echo s($titulo); ?></label>
                <div class="mi-entrada">
                    <input type="text" class="mi-tema" maxlength="500" placeholder="<?php echo s($etiqueta); ?>">
                    <input type="text" class="mi-resp" maxlength="150" list="mi-sugerencias" placeholder="Responsable">
                    <select class="mi-depto">
                        <option value="">— Departamento —</option>
                        <?php foreach ($departamentos as $d): ?><option value="<?php echo s($d); ?>"><?php echo s($d); ?></option><?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-ghost mi-agregar">+ Agregar</button>
                </div>
                <table class="tabla mi-lista" hidden>
                    <thead><tr><th><?php echo s($etiqueta); ?></th><th>Responsable</th><th>Departamento</th><th></th></tr></thead>
                    <tbody></tbody>
                </table>
                <p class="text-muted text-sm mi-vacio">Aún no hay elementos agregados.</p>
            </div>
        <?php endforeach; ?>
        <?php if (!$departamentos): ?><small class="text-muted">Los departamentos salen de la <a href="/bitacora/departamentos?proyecto=<?php echo $id; ?>">bitácora</a>; aún no hay ninguno.</small><?php endif; ?>

        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
<script>
(function () {
    const bloques = [...document.querySelectorAll('.mi-bloque')];
    bloques.forEach(b => {
        const campo = b.dataset.campo;
        const tema = b.querySelector('.mi-tema'), resp = b.querySelector('.mi-resp'), depto = b.querySelector('.mi-depto');
        const tabla = b.querySelector('.mi-lista'), cuerpo = tabla.querySelector('tbody'), vacio = b.querySelector('.mi-vacio');
        const refrescar = () => { const n = cuerpo.children.length; tabla.hidden = !n; vacio.hidden = !!n; };
        const agregar = (d) => {
            const tr = document.createElement('tr');
            [['tema', d.tema], ['responsable', d.responsable || ''], ['departamento', d.departamento || '']].forEach(([k, v], i) => {
                const td = document.createElement('td');
                td.textContent = v || '—';
                const h = document.createElement('input');
                h.type = 'hidden'; h.name = campo + '[' + k + '][]'; h.value = v || '';
                td.appendChild(h); tr.appendChild(td);
            });
            const td = document.createElement('td');
            const q = document.createElement('button');
            q.type = 'button'; q.className = 'btn btn-sm btn-danger'; q.textContent = 'Quitar';
            q.addEventListener('click', () => { tr.remove(); refrescar(); });
            td.appendChild(q); tr.appendChild(td);
            cuerpo.appendChild(tr); refrescar();
        };
        const desdeEntrada = () => {
            if (!tema.value.trim()) { tema.focus(); return false; }
            agregar({ tema: tema.value.trim(), responsable: resp.value.trim(), departamento: depto.value });
            tema.value = ''; resp.value = ''; depto.value = ''; tema.focus();
            return true;
        };
        b.querySelector('.mi-agregar').addEventListener('click', desdeEntrada);
        tema.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); desdeEntrada(); } });
        b._pendiente = () => { if (tema.value.trim()) desdeEntrada(); };
        JSON.parse(b.dataset.items || '[]').forEach(agregar);
        refrescar();
    });
    // Lo escrito y no agregado todavía se agrega al guardar
    document.getElementById('form-minuta').addEventListener('submit', () => bloques.forEach(b => b._pendiente()));
})();
</script>
