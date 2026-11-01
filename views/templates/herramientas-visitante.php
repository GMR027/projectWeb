<?php
/**
 * Casillas de las herramientas de un visitante o colaborador.
 * Variables:
 *   $marcadas                 claves visibles (null = todas)
 *   $herramientasEdicionMarcadas  claves que además puede editar ([] = ninguna)
 *   $archivosAjenos           1 = puede editar/eliminar archivos subidos por el dueño
 *   $tipo                     'visitante' | 'colaborador' (opcional; si no, se deduce de las casillas)
 */
$todas = $marcadas === null;
$visibles = $todas ? array_keys(\Model\Usuario::HERRAMIENTAS) : (array)$marcadas;
$edicion = array_intersect(array_map('strval', (array)($herramientasEdicionMarcadas ?? [])), $visibles);
$noEditables = \Model\Usuario::HERRAMIENTAS_NO_EDITABLES;
$esColab = isset($tipo) ? ($tipo === 'colaborador') : !empty($edicion);
$grupos = \Model\Usuario::gruposHerramientas();
?>
<div class="campo campo-herramientas" id="campo-herramientas" role="group" aria-labelledby="herr-titulo">
    <div class="herr-cab">
        <div class="herr-cab-txt">
            <span class="herr-titulo" id="herr-titulo">Herramientas</span>
            <small class="text-muted">Marca lo que puede <strong>abrir</strong> y, si es colaborador, lo que además puede <strong>editar</strong>. Lo que no marques desaparece de su menú y no puede entrar por enlace. Borrar siempre queda reservado para ti.</small>
        </div>
        <div class="herr-resumen" aria-live="polite">
            <span class="herr-chip" data-resumen="ver"></span>
            <span class="herr-chip herr-chip-editar" data-resumen="editar"<?php echo $esColab ? '' : ' hidden'; ?>></span>
        </div>
    </div>

    <div class="herr-barra">
        <label class="herr-busca">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="herr-buscar" placeholder="Buscar herramienta…" autocomplete="off" aria-label="Buscar herramienta">
        </label>
        <div class="herr-atajos">
            <button type="button" class="btn btn-sm btn-ghost" data-herr="todas"><i class="fa-solid fa-check-double" aria-hidden="true"></i> Ver todas</button>
            <button type="button" class="btn btn-sm btn-ghost" data-herr="ninguna"><i class="fa-solid fa-ban" aria-hidden="true"></i> Ver ninguna</button>
            <button type="button" class="btn btn-sm btn-ghost herr-btn-editar" data-herr-editar="visibles"<?php echo $esColab ? '' : ' hidden'; ?>><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar lo que ve</button>
            <button type="button" class="btn btn-sm btn-ghost herr-btn-editar" data-herr-editar="ninguna"<?php echo $esColab ? '' : ' hidden'; ?>><i class="fa-solid fa-pen-slash" aria-hidden="true"></i> Editar nada</button>
        </div>
    </div>

    <div class="herr-lista<?php echo $esColab ? ' con-editar' : ''; ?>">
        <div class="herr-cabecera" aria-hidden="true">
            <span class="herr-col-ver">Ver</span>
            <span class="herr-col-editar">Editar</span>
            <span>Herramienta</span>
        </div>

        <?php foreach ($grupos as $seccion => $filas): ?>
            <section class="herr-grupo" data-grupo>
                <header class="herr-grupo-cab">
                    <label class="herr-celda herr-col-ver" title="Marcar todas las de <?php echo s($seccion); ?>">
                        <input type="checkbox" class="herr-grupo-check" aria-label="Marcar todas las de <?php echo s($seccion); ?> como visibles">
                        <span class="solo-movil">Ver</span>
                    </label>
                    <label class="herr-celda herr-col-editar" title="Editar todas las de <?php echo s($seccion); ?>">
                        <input type="checkbox" class="herr-grupo-editar-check" aria-label="Marcar todas las de <?php echo s($seccion); ?> como editables">
                        <span class="solo-movil">Editar</span>
                    </label>
                    <span class="herr-grupo-todo">
                        <strong><?php echo s($seccion); ?></strong>
                        <small class="herr-grupo-cuenta"></small>
                    </span>
                </header>
                <div class="herr-filas">
                    <?php foreach ($filas as $clave => $etiqueta): $puedeEditar = !in_array($clave, $noEditables, true); ?>
                        <div class="herr-fila" data-nombre="<?php echo s(mb_strtolower($etiqueta . ' ' . t($etiqueta) . ' ' . $clave)); ?>">
                            <label class="herr-celda herr-col-ver">
                                <input type="checkbox" class="herr-ver" name="herr[]" value="<?php echo s($clave); ?>" aria-label="Puede ver <?php echo s($etiqueta); ?>"<?php echo $todas || in_array($clave, $visibles, true) ? ' checked' : ''; ?>>
                                <span class="solo-movil">Ver</span>
                            </label>
                            <?php if ($puedeEditar): ?>
                                <label class="herr-celda herr-col-editar">
                                    <input type="checkbox" class="herr-editar" name="herr_edit[]" value="<?php echo s($clave); ?>" aria-label="Puede editar <?php echo s($etiqueta); ?>"<?php echo in_array($clave, $edicion, true) ? ' checked' : ''; ?>>
                                    <span class="solo-movil">Editar</span>
                                </label>
                            <?php else: ?>
                                <span class="herr-celda herr-col-editar herr-bloqueada" title="Solo el dueño o el administrador">—</span>
                            <?php endif; ?>
                            <span class="herr-nombre" title="<?php echo s($etiqueta); ?>"><?php echo s(t($etiqueta)); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <p class="herr-vacio" hidden>No hay herramientas que coincidan con la búsqueda.</p>
    </div>

    <label class="campo-check herr-archivos-ajenos" id="campo-archivos-ajenos"<?php echo $esColab ? '' : ' hidden'; ?>>
        <input type="checkbox" name="archivos_ajenos" value="1"<?php echo !empty($archivosAjenos) ? ' checked' : ''; ?>>
        Puede editar y reemplazar los archivos que subió el dueño (si no, solo toca los suyos)
    </label>
</div>
<script>
(function () {
    const caja = document.getElementById('campo-herramientas'); if (!caja) return;
    const lista = caja.querySelector('.herr-lista');
    const ver = () => [...caja.querySelectorAll('.herr-ver')];
    const editar = () => [...caja.querySelectorAll('.herr-editar')];
    const grupos = () => [...caja.querySelectorAll('[data-grupo]')];
    const ajenos = document.getElementById('campo-archivos-ajenos');
    const chipVer = caja.querySelector('[data-resumen="ver"]');
    const chipEditar = caja.querySelector('[data-resumen="editar"]');
    const busca = caja.querySelector('#herr-buscar');
    const botonesEditar = [...caja.querySelectorAll('.herr-btn-editar')];
    const vacio = caja.querySelector('.herr-vacio');

    // ¿La cuenta es colaboradora? Lo dicen el selector de tipo del formulario o la propia lista de edición.
    const selector = document.querySelector('select[name="tipo"], select[name="nivel_acceso"]');
    const esColab = () => {
        if (!selector) return caja.querySelectorAll('.herr-editar:checked').length > 0;
        return selector.value === 'colaborador' || selector.value === 'editar';
    };

    const contar = () => {
        const total = ver().length;
        const visibles = ver().filter(c => c.checked).length;
        const editables = editar().filter(c => c.checked && !c.disabled).length;
        if (chipVer) chipVer.textContent = visibles + ' de ' + total + ' visibles';
        if (chipEditar) chipEditar.textContent = editables + ' editables';
    };

    const pintarGrupo = (grupo) => {
        const cajas = [...grupo.querySelectorAll('.herr-ver')];
        const marcadas = cajas.filter(c => c.checked).length;
        const check = grupo.querySelector('.herr-grupo-check');
        if (check) {
            check.checked = marcadas > 0 && marcadas === cajas.length;
            check.indeterminate = marcadas > 0 && marcadas < cajas.length;
        }
        const cajasEditar = [...grupo.querySelectorAll('.herr-editar')];
        const editables = cajasEditar.filter(c => c.checked).length;
        const checkEditar = grupo.querySelector('.herr-grupo-editar-check');
        if (checkEditar) {
            checkEditar.checked = cajasEditar.length > 0 && editables === cajasEditar.length;
            checkEditar.indeterminate = editables > 0 && editables < cajasEditar.length;
            checkEditar.disabled = marcadas === 0;
        }
        const cuenta = grupo.querySelector('.herr-grupo-cuenta');
        if (cuenta) cuenta.textContent = marcadas + '/' + cajas.length;
    };

    const pintar = () => {
        const on = esColab();
        lista.classList.toggle('con-editar', on);
        if (ajenos) ajenos.hidden = !on;
        botonesEditar.forEach(b => { b.hidden = !on; });
        if (chipEditar) chipEditar.hidden = !on;
        ver().forEach(c => {
            const e = editar().find(x => x.value === c.value);
            if (!e) return;
            e.disabled = !c.checked;
            if (!c.checked) e.checked = false;
        });
        grupos().forEach(pintarGrupo);
        contar();
    };

    // Filtro por nombre: oculta filas y grupos sin coincidencias (sin distinguir mayúsculas ni tildes).
    const normal = (txt) => (txt || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const filtrar = () => {
        const q = normal(busca?.value);
        let alguno = false;
        grupos().forEach(grupo => {
            let visiblesGrupo = 0;
            grupo.querySelectorAll('.herr-fila').forEach(fila => {
                const coincide = !q || normal(fila.dataset.nombre).includes(q);
                fila.hidden = !coincide;
                if (coincide) visiblesGrupo++;
            });
            grupo.hidden = visiblesGrupo === 0;
            if (visiblesGrupo) alguno = true;
        });
        if (vacio) vacio.hidden = alguno;
    };

    caja.querySelectorAll('[data-herr]').forEach(b => b.addEventListener('click', () => {
        ver().forEach(c => { c.checked = b.dataset.herr === 'todas'; });
        pintar();
    }));
    caja.querySelectorAll('[data-herr-editar]').forEach(b => b.addEventListener('click', () => {
        editar().forEach(e => { e.checked = b.dataset.herrEditar === 'visibles' && !e.disabled; });
        contar();
    }));
    grupos().forEach(grupo => {
        grupo.querySelector('.herr-grupo-check')?.addEventListener('change', (ev) => {
            const marcar = ev.target.checked;
            grupo.querySelectorAll('.herr-ver').forEach(c => { c.checked = marcar; });
            pintar();
        });
        grupo.querySelector('.herr-grupo-editar-check')?.addEventListener('change', (ev) => {
            const marcar = ev.target.checked;
            grupo.querySelectorAll('.herr-editar').forEach(c => { if (!c.disabled) c.checked = marcar; });
            pintar();
        });
    });
    caja.addEventListener('change', (ev) => {
        if (!ev.target.classList.contains('herr-editar')) pintar();
    });
    if (busca) busca.addEventListener('input', filtrar);
    if (selector) selector.addEventListener('change', pintar);
    pintar();
})();
</script>
