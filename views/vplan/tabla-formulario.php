<?php
use Model\VplanTabla;
$esNueva = !$tabla->id;
$campos = $tabla->campos();
$cols = $tabla->columnasOtro();
$cfgPunch = $tabla->configPunch();
$contratistasPunch = \Classes\Punch::contratistas((int)$proyecto->id);
?>
<div class="page-header">
    <h2><?php echo $esNueva ? 'Nueva tabla adicional' : 'Editar tabla: ' . s($tabla->titulo); ?></h2>
    <a href="/vplan/tablas?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Tablas</a>
</div>
<?php if (!$esNueva && $tabla->hoja()): ?>
<div class="card">
    <i class="fa-solid fa-link"></i> <strong>Vinculada a <a href="/sheetflow/abrir?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo (int)$tabla->sheetflow_id; ?>"><?php echo s($tabla->hoja()->nombre); ?></a> (SheetFlow).</strong>
    Columnas y renglones se leen en vivo de esa tabla; lo que edites en la lámina se escribe allá. Aquí solo cambias el título y el nombre en el catálogo.
</div>
<?php endif; ?>
<?php if (!$esNueva && $tabla->tipo === 'punch'): ?>
<div class="card">
    <strong>Esta tabla es manual y no está conectada al módulo Punch.</strong> Puedes convertirla: cada renglón pasa a ser un pendiente del Punch list (la fecha «fin» es la fecha límite y el % se traduce a estado; el inicio se pierde) y la tabla muestra esos pendientes con fotos, responsable, etc.
    <form method="POST" action="/vplan/tablas/convertir-punch" data-confirm="¿Convertir al Punch list?|Se crearán los pendientes y la tabla quedará conectada al módulo Punch. No se puede deshacer.">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>"><input type="hidden" name="id" value="<?php echo s($tabla->id); ?>">
        <button type="submit" class="btn btn-sm btn-primary" style="margin-top:.8rem">Convertir a Punch list conectado</button>
    </form>
</div>
<?php endif; ?>
<div class="card">
    <form method="POST" action="/vplan/tablas/<?php echo $esNueva ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if (!$esNueva): ?><input type="hidden" name="id" value="<?php echo s($tabla->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="titulo">Título impreso en la lámina *</label><input type="text" id="titulo" name="titulo" value="<?php echo s($tabla->titulo); ?>" required placeholder="Ej: PUNCH LIST / DETALLES PENDIENTES"></div>
            <div class="campo"><label for="nombre_interno">Nombre en el catálogo</label><input type="text" id="nombre_interno" name="nombre_interno" value="<?php echo s($tabla->nombre_interno); ?>" maxlength="150" placeholder="Ej: Punch list · Zona norte"><small class="text-muted">Solo para distinguirla en la lista; no se imprime. Si lo dejas vacío se usa el título.</small></div>
            <div class="campo">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" <?php echo $esNueva ? '' : 'disabled'; ?>>
                    <?php foreach (VplanTabla::TIPOS as $k => $t): ?><option value="<?php echo $k; ?>" <?php echo $tabla->tipo === $k ? 'selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?>
                </select>
                <?php if (!$esNueva): ?><small class="text-muted">El tipo no se puede cambiar una vez creada la tabla.</small><?php endif; ?>
            </div>
            <div class="campo campo-full" id="bloque-punch" style="<?php echo $tabla->tipo === 'punchlist' ? '' : 'display:none'; ?>">
                <p class="text-muted text-sm">Esta tabla es una <strong>vista</strong> del módulo <a href="/punch?proyecto=<?php echo s($proyecto->id); ?>">Punch list</a>: los pendientes no se copian, se leen en vivo y lo que cambies en la lámina (estado, severidad, fecha, responsable…) se guarda en el pendiente. Elige qué ver y qué no.</p>
                <label>Columnas visibles (hasta <?php echo \Classes\PunchVista::MAX_COLUMNAS; ?>)</label>
                <div class="vp-cols vp-cols-punch">
                    <?php foreach (\Classes\PunchVista::COLUMNAS as $k => [$etq, $tp]): ?>
                        <label class="check"><input type="checkbox" name="punch[columnas][]" value="<?php echo s($k); ?>" <?php echo in_array($k, $cfgPunch['columnas'], true) ? 'checked' : ''; ?>> <?php echo $tp === 'foto' ? '<i class="fa-solid fa-image"></i> ' : ''; echo s($etq); ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="form-grid" style="margin-top:1rem">
                    <div class="campo"><label for="p-estado">Estado</label>
                        <select id="p-estado" name="punch[estado]">
                            <option value="pendientes" <?php echo $cfgPunch['estado'] === 'pendientes' ? 'selected' : ''; ?>>Solo pendientes (abiertos, en proceso, por verificar)</option>
                            <option value="todos" <?php echo $cfgPunch['estado'] === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <?php foreach (\Classes\Punch::ESTADOS as $k => $e): ?><option value="<?php echo $k; ?>" <?php echo $cfgPunch['estado'] === $k ? 'selected' : ''; ?>><?php echo s($e); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="campo"><label for="p-sev">Severidad</label>
                        <select id="p-sev" name="punch[severidad]"><option value="">Todas</option>
                            <?php foreach (\Classes\Punch::SEVERIDADES as $k => $e): ?><option value="<?php echo $k; ?>" <?php echo $cfgPunch['severidad'] === $k ? 'selected' : ''; ?>><?php echo s($e); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="campo"><label for="p-con">Contratista</label>
                        <select id="p-con" name="punch[contratista]"><option value="">Todos</option>
                            <?php foreach ($contratistasPunch as $c): ?><option value="<?php echo s($c); ?>" <?php echo $cfgPunch['contratista'] === $c ? 'selected' : ''; ?>><?php echo s($c); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="campo"><label for="p-cat">Categoría</label><input type="text" id="p-cat" name="punch[categoria]" value="<?php echo s($cfgPunch['categoria']); ?>" maxlength="60" placeholder="Todas"></div>
                    <div class="campo"><label for="p-lim">Máximo de renglones</label><input type="number" id="p-lim" name="punch[limite]" min="1" max="200" value="<?php echo (int)$cfgPunch['limite']; ?>"></div>
                </div>
                <input type="hidden" name="punch[ids]" value="<?php echo s(json_encode($cfgPunch['ids'])); ?>">
                <?php if ($cfgPunch['ids']): ?><small class="text-muted">Esta vista está limitada a <?php echo count($cfgPunch['ids']); ?> pendientes concretos (los de una tabla manual convertida).</small><?php endif; ?>
            </div>
            <div class="campo campo-full" id="bloque-columnas" style="<?php echo $tabla->tipo === 'otro' ? '' : 'display:none'; ?>">
                <label>Columnas (hasta 8)</label>
                <div class="vp-cols">
                    <?php for ($i = 0; $i < 8; $i++): ?><input type="text" name="columnas[]" maxlength="40" value="<?php echo s($cols[$i] ?? ''); ?>" placeholder="Columna <?php echo $i + 1; ?>"><?php endfor; ?>
                </div>
            </div>
        </div>

        <?php if (!$esNueva && $campos && !$tabla->hoja() && !$tabla->esPunchVista()): ?>
            <div class="vp-renglones-cab">
                <h4>Renglones <span class="text-muted text-sm">(<span id="vp-total"><?php echo count($filas); ?></span>)</span></h4>
                <button type="button" class="btn btn-ghost btn-sm" id="vp-agregar">+ Agregar renglón</button>
            </div>
            <?php if ($tabla->tipo === 'restricciones'): ?>
                <p class="text-muted text-sm">Aquí solo se capturan restricciones manuales. Las restricciones activas del programa se agregan solas, numeradas, en cada lámina.</p>
            <?php endif; ?>
            <?php
            // Un campo del renglón: $i = índice (o __I__ en la plantilla), $f = datos
            $campoHtml = function (string $k, string $etq, string $tipo, $i, array $f) {
                $nombre = "filas[$i][$k]";
                $ancho = in_array($k, ['descripcion', 'elemento'], true) ? ' vp-ancho' : '';
                $v = s($f[$k] ?? '');
                $h = '<div class="campo' . $ancho . '"><label>' . s($etq) . '</label>';
                if ($tipo === 'estado') {
                    $h .= '<select name="' . $nombre . '">';
                    foreach (\Model\VplanTabla::ESTADOS as $ek => $el) $h .= '<option value="' . $ek . '"' . (($f[$k] ?? 'proceso') === $ek ? ' selected' : '') . '>' . s($el) . '</option>';
                    $h .= '</select>';
                } elseif ($k === 'descripcion') {
                    $h .= '<textarea name="' . $nombre . '" rows="1">' . $v . '</textarea>';
                } else {
                    $h .= '<input type="' . ($tipo === 'number' ? 'number' : $tipo) . '"' . ($tipo === 'number' ? ' min="0" max="100" placeholder="0 a 100"' : '') . ' name="' . $nombre . '" value="' . $v . '">';
                }
                return $h . '</div>';
            };
            ?>
            <div id="vp-filas" class="vp-filas">
                <?php foreach ($filas as $i => $f): ?>
                    <div class="vp-fila">
                        <div class="vp-fila-cab"><span class="mono">Renglón <b class="n"><?php echo $i + 1; ?></b></span><button type="button" class="btn btn-sm btn-danger vp-quitar" title="Quitar renglón">✕ Quitar</button></div>
                        <div class="vp-fila-grid"><?php foreach ($campos as $k => [$etq, $tipo]) echo $campoHtml($k, $etq, $tipo, $i, $f); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-muted text-sm vp-vacio" id="vp-vacio" style="<?php echo $filas ? 'display:none' : ''; ?>">Aún no hay renglones. Usa «Agregar renglón».</p>
            <template id="vp-plantilla">
                <div class="vp-fila">
                    <div class="vp-fila-cab"><span class="mono">Renglón <b class="n"></b></span><button type="button" class="btn btn-sm btn-danger vp-quitar" title="Quitar renglón">✕ Quitar</button></div>
                    <div class="vp-fila-grid"><?php foreach ($campos as $k => [$etq, $tipo]) echo $campoHtml($k, $etq, $tipo, '__I__', []); ?></div>
                </div>
            </template>
        <?php elseif ($esNueva): ?>
            <p class="text-muted text-sm">Guarda la tabla y después podrás capturar sus renglones.</p>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><?php echo $esNueva ? 'Crear tabla' : 'Guardar tabla'; ?></button>
    </form>
</div>
<script>
(function () {
    const tipo = document.getElementById('tipo'), bloque = document.getElementById('bloque-columnas'), bloquePunch = document.getElementById('bloque-punch');
    tipo.addEventListener('change', () => { bloque.style.display = tipo.value === 'otro' ? '' : 'none'; bloquePunch.style.display = tipo.value === 'punchlist' ? '' : 'none'; });
    const cont = document.getElementById('vp-filas'), plantilla = document.getElementById('vp-plantilla');
    if (!cont || !plantilla) return;
    const vacio = document.getElementById('vp-vacio'), total = document.getElementById('vp-total');
    const renumerar = () => {
        [...cont.children].forEach((fila, i) => {
            fila.querySelector('.n').textContent = i + 1;
            fila.querySelectorAll('[name^="filas["]').forEach(el => { el.name = el.name.replace(/^filas\[[^\]]*\]/, 'filas[' + i + ']'); });
        });
        total.textContent = cont.children.length;
        vacio.style.display = cont.children.length ? 'none' : '';
    };
    document.getElementById('vp-agregar').addEventListener('click', () => {
        cont.insertAdjacentHTML('beforeend', plantilla.innerHTML.replaceAll('__I__', cont.children.length));
        renumerar();
        const ult = cont.lastElementChild.querySelector('input, textarea'); if (ult) ult.focus();
    });
    cont.addEventListener('click', e => { if (e.target.closest('.vp-quitar')) { e.target.closest('.vp-fila').remove(); renumerar(); } });
})();
</script>
