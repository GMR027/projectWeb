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
?>
<div class="campo" id="campo-herramientas">
    <label>Herramientas</label>
    <small class="text-muted">Marca lo que puede <strong>abrir</strong> y, si es colaborador, lo que además puede <strong>editar</strong>. Lo que no marques desaparece de su menú y no puede entrar por enlace. Borrar siempre queda reservado para ti.</small>
    <div class="herr-atajos">
        <button type="button" class="btn btn-sm btn-ghost" data-herr="todas">Ver todas</button>
        <button type="button" class="btn btn-sm btn-ghost" data-herr="ninguna">Ver ninguna</button>
        <button type="button" class="btn btn-sm btn-ghost" data-herr-editar="visibles">Editar lo que ve</button>
        <button type="button" class="btn btn-sm btn-ghost" data-herr-editar="ninguna">Editar nada</button>
    </div>
    <div class="herr-grid herr-grid-3">
        <span class="text-sm text-muted">Herramienta</span>
        <span class="text-sm text-muted">Puede ver</span>
        <span class="text-sm text-muted herr-col-editar"<?php echo $esColab ? '' : ' hidden'; ?>>Puede editar</span>
        <?php foreach (\Model\Usuario::HERRAMIENTAS as $clave => [$etq]): $puedeEditar = !in_array($clave, $noEditables, true); ?>
            <label class="herr-item herr-item-nombre"><?php echo s(t($etq)); ?></label>
            <label class="herr-item herr-col-ver"><input type="checkbox" class="herr-ver" name="herr[]" value="<?php echo s($clave); ?>"<?php echo $todas || in_array($clave, $visibles, true) ? ' checked' : ''; ?>> <span class="solo-movil">Ver</span></label>
            <?php if ($puedeEditar): ?>
                <label class="herr-item herr-col-editar"<?php echo $esColab ? '' : ' hidden'; ?>><input type="checkbox" class="herr-editar" name="herr_edit[]" value="<?php echo s($clave); ?>"<?php echo in_array($clave, $edicion, true) ? ' checked' : ''; ?>> <span class="solo-movil">Editar</span></label>
            <?php else: ?>
                <span class="herr-item herr-col-editar text-muted"<?php echo $esColab ? '' : ' hidden'; ?> title="Solo el dueño o el administrador">—</span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <label class="campo-check" style="margin-top:1rem" id="campo-archivos-ajenos"<?php echo $esColab ? '' : ' hidden'; ?>>
        <input type="checkbox" name="archivos_ajenos" value="1"<?php echo !empty($archivosAjenos) ? ' checked' : ''; ?>>
        Puede editar y reemplazar los archivos que subió el dueño (si no, solo toca los suyos)
    </label>
</div>
<script>
(function () {
    const caja = document.getElementById('campo-herramientas'); if (!caja) return;
    const ver = () => [...caja.querySelectorAll('.herr-ver')];
    const editar = () => [...caja.querySelectorAll('.herr-editar')];
    const colEditar = [...caja.querySelectorAll('.herr-col-editar')];
    const ajenos = document.getElementById('campo-archivos-ajenos');
    // ¿La cuenta es colaboradora? Lo dicen el selector de tipo del formulario o la propia lista de edición.
    const selector = document.querySelector('select[name="tipo"], select[name="nivel_acceso"]');
    const esColab = () => {
        if (!selector) return editar().some(c => c.checked);
        return selector.value === 'colaborador' || selector.value === 'editar';
    };
    const pintar = () => {
        const on = esColab();
        colEditar.forEach(e => { e.hidden = !on; });
        if (ajenos) ajenos.hidden = !on;
        ver().forEach(c => {
            const e = editar().find(x => x.value === c.value);
            if (!e) return;
            e.disabled = !c.checked;
            if (!c.checked) e.checked = false;
        });
    };
    caja.querySelectorAll('[data-herr]').forEach(b => b.addEventListener('click', () => {
        ver().forEach(c => { c.checked = b.dataset.herr === 'todas'; });
        pintar();
    }));
    caja.querySelectorAll('[data-herr-editar]').forEach(b => b.addEventListener('click', () => {
        editar().forEach(e => { e.checked = b.dataset.herrEditar === 'visibles' && !e.disabled; });
    }));
    caja.addEventListener('change', pintar);
    if (selector) selector.addEventListener('change', pintar);
    pintar();
})();
</script>
