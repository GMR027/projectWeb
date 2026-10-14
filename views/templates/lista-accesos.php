<?php
/**
 * Lista de casillas con buscador y selección masiva.
 * Variables: $items [['id','titulo','detalle']], $nombreCampo, $marcados (ids)
 */
$marcados = array_map('intval', $marcados ?? []);
usort($items, fn($a, $b) => in_array($b['id'], $marcados, true) <=> in_array($a['id'], $marcados, true));
$uid = 'ac' . substr(md5($nombreCampo), 0, 6);
?>
<div class="accesos" id="<?php echo $uid; ?>">
    <div class="accesos-barra">
        <input type="search" class="accesos-buscar" placeholder="Buscar..." autocomplete="off">
        <button type="button" class="btn btn-ghost btn-sm" data-accion="todos">Marcar visibles</button>
        <button type="button" class="btn btn-ghost btn-sm" data-accion="ninguno">Quitar visibles</button>
        <span class="text-muted text-sm accesos-contador"></span>
    </div>
    <div class="accesos-lista">
        <?php foreach ($items as $it): ?>
            <label class="acceso-item" data-texto="<?php echo s(mb_strtolower($it['titulo'] . ' ' . $it['detalle'])); ?>">
                <input type="checkbox" name="<?php echo s($nombreCampo); ?>[]" value="<?php echo s($it['id']); ?>" <?php echo in_array((int)$it['id'], $marcados, true) ? 'checked' : ''; ?>>
                <span class="mono"><?php echo s($it['detalle']); ?></span> <?php echo s($it['titulo']); ?>
            </label>
        <?php endforeach; ?>
        <?php if (empty($items)): ?><p class="text-muted text-sm">No hay elementos para asignar.</p><?php endif; ?>
    </div>
</div>
<script>
(function () {
    const raiz = document.getElementById('<?php echo $uid; ?>');
    const buscar = raiz.querySelector('.accesos-buscar');
    const items = [...raiz.querySelectorAll('.acceso-item')];
    const contador = raiz.querySelector('.accesos-contador');
    const visibles = () => items.filter(i => i.style.display !== 'none');
    const actualizar = () => {
        const marcados = items.filter(i => i.querySelector('input').checked).length;
        contador.textContent = marcados + ' de ' + items.length + ' seleccionados';
    };
    buscar.addEventListener('input', () => {
        const q = buscar.value.trim().toLowerCase();
        items.forEach(i => { i.style.display = !q || i.dataset.texto.includes(q) ? '' : 'none'; });
    });
    raiz.addEventListener('click', e => {
        const a = e.target.dataset.accion;
        if (!a) return;
        visibles().forEach(i => { i.querySelector('input').checked = a === 'todos'; });
        actualizar();
    });
    raiz.addEventListener('change', actualizar);
    actualizar();
})();
</script>
