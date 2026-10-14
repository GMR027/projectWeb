<?php
use Model\VplanTabla;
$esNueva = !$tabla->id;
$campos = $tabla->campos();
$cols = $tabla->columnasOtro();
?>
<div class="page-header">
    <h2><?php echo $esNueva ? 'Nueva tabla adicional' : 'Editar tabla: ' . s($tabla->titulo); ?></h2>
    <a href="/vplan/tablas?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Tablas</a>
</div>
<div class="card">
    <form method="POST" action="/vplan/tablas/<?php echo $esNueva ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if (!$esNueva): ?><input type="hidden" name="id" value="<?php echo s($tabla->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="titulo">Título impreso en la lámina *</label><input type="text" id="titulo" name="titulo" value="<?php echo s($tabla->titulo); ?>" required placeholder="Ej: PUNCH LIST / INSTALACION DE DISPOSITIVOS"></div>
            <div class="campo"><label for="nombre_interno">Nombre en el catálogo</label><input type="text" id="nombre_interno" name="nombre_interno" value="<?php echo s($tabla->nombre_interno); ?>" maxlength="150" placeholder="Ej: Punch list · Área M,N"><small class="text-muted">Solo para distinguirla en la lista; no se imprime. Si lo dejas vacío se usa el título.</small></div>
            <div class="campo">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" <?php echo $esNueva ? '' : 'disabled'; ?>>
                    <?php foreach (VplanTabla::TIPOS as $k => $t): ?><option value="<?php echo $k; ?>" <?php echo $tabla->tipo === $k ? 'selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?>
                </select>
                <?php if (!$esNueva): ?><small class="text-muted">El tipo no se puede cambiar una vez creada la tabla.</small><?php endif; ?>
            </div>
            <div class="campo campo-full" id="bloque-columnas" style="<?php echo $tabla->tipo === 'otro' ? '' : 'display:none'; ?>">
                <label>Columnas (hasta 5)</label>
                <div class="vp-cols">
                    <?php for ($i = 0; $i < 5; $i++): ?><input type="text" name="columnas[]" maxlength="40" value="<?php echo s($cols[$i] ?? ''); ?>" placeholder="Columna <?php echo $i + 1; ?>"><?php endfor; ?>
                </div>
            </div>
        </div>

        <?php if (!$esNueva && $campos): ?>
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
                    $h .= '<textarea name="' . $nombre . '" rows="2">' . $v . '</textarea>';
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
    const tipo = document.getElementById('tipo'), bloque = document.getElementById('bloque-columnas');
    tipo.addEventListener('change', () => { bloque.style.display = tipo.value === 'otro' ? '' : 'none'; });
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
