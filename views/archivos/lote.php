<?php
$id = s($proyecto->id);
$accept = '.' . implode(',.', $extensiones);
$maxMb = (int)round($maxBytes / 1048576);
$maxLoteMb = (int)round($maxLoteBytes / 1048576);
?>
<?php require_once __DIR__ . '/../templates/herramientas-proyecto.php'; pestanas_herramienta('archivo', '/archivos', $proyecto); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-layer-group"></i> Subir varios archivos</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · elige varios archivos y aplica la misma fecha, categoría, carpeta, nombre y revisión, o ajusta cada uno por separado.</p>
    </div>
    <a href="/archivos?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Volver a Archivo</a>
</div>
<div class="card arch-lote">
    <form method="POST" action="/archivos/lote?proyecto=<?php echo $id; ?>" enctype="multipart/form-data" id="arch-lote-form">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">

        <div class="campo arch-lote-picker">
            <label for="arch-lote-input">Archivos * (puedes elegir varios)</label>
            <input type="file" id="arch-lote-input" name="archivos[]" multiple required accept="<?php echo s($accept); ?>">
            <small class="text-muted">Hasta <?php echo (int)$maxLote; ?> archivos, <?php echo (int)$maxMb; ?> MB por archivo y <?php echo (int)$maxLoteMb; ?> MB en total. Permitidos: PDF, Word, Excel, PowerPoint, XML, LibreOffice, imágenes, planos y modelos (AutoCAD, Revit, IFC, SketchUp…), zip, rar y 7z. Cada archivo se revisa antes de guardarse.</small>
        </div>

        <div class="arch-lote-comun">
            <div class="arch-lote-comun-cab">
                <h3><i class="fa-solid fa-wand-magic-sparkles"></i> Aplicar a todos</h3>
                <p class="text-sm text-muted">Marca una casilla para usar el mismo valor en todos los archivos; desmárcala para editarlo en cada renglón.</p>
            </div>
            <div class="arch-lote-reglas">
                <div class="arch-lote-regla">
                    <label for="arch-lote-fecha-on"><input type="checkbox" id="arch-lote-fecha-on" name="aplicar_fecha" checked> Misma fecha</label>
                    <input type="date" id="arch-lote-fecha" name="fecha_comun" value="<?php echo s($fechaHoy); ?>" aria-label="Fecha para todos">
                </div>
                <div class="arch-lote-regla">
                    <label for="arch-lote-categoria-on"><input type="checkbox" id="arch-lote-categoria-on" name="aplicar_categoria" checked> Misma categoría</label>
                    <select id="arch-lote-categoria" name="categoria_comun" aria-label="Categoría para todos">
                        <option value="">— Sin categoría —</option>
                        <?php foreach ($categorias as $c): ?><option value="<?php echo s($c->id); ?>"><?php echo s($c->nombre); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="arch-lote-regla">
                    <label for="arch-lote-carpeta-on"><input type="checkbox" id="arch-lote-carpeta-on" name="aplicar_carpeta" checked> Misma carpeta</label>
                    <select id="arch-lote-carpeta" name="carpeta_comun" aria-label="Carpeta para todos">
                        <option value="">— Sin carpeta —</option>
                        <?php foreach ($carpetas as $c): ?><option value="<?php echo s($c->id); ?>" <?php echo $carpetaInicial && (int)$carpetaInicial === (int)$c->id ? 'selected' : ''; ?>><?php echo s($c->nombre); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="arch-lote-regla">
                    <label for="arch-lote-revision-on"><input type="checkbox" id="arch-lote-revision-on" name="aplicar_revision" checked> Mismo número de revisión</label>
                    <input type="text" id="arch-lote-revision" name="revision_comun" maxlength="30" placeholder="Ej: 0, A, 02" aria-label="Revisión para todos">
                </div>
                <div class="arch-lote-regla arch-lote-regla-check">
                    <label for="arch-lote-nombre-original"><input type="checkbox" id="arch-lote-nombre-original" name="nombre_original" checked> Nombrar como el archivo original</label>
                    <small class="text-muted">Usa el nombre del archivo sin la extensión. Si lo desmarcas, escribe un nombre por archivo.</small>
                </div>
            </div>
        </div>

        <div class="arch-lote-tabla-wrap" id="arch-lote-lista" hidden>
            <div class="arch-lote-resumen" id="arch-lote-resumen"></div>
            <table class="tabla arch-lote-tabla">
                <thead>
                    <tr><th>Archivo</th><th>Nombre</th><th>Categoría</th><th>Carpeta</th><th>Fecha</th><th>Rev.</th><th></th></tr>
                </thead>
                <tbody id="arch-lote-filas"></tbody>
            </table>
        </div>

        <p class="arch-lote-aviso text-sm" id="arch-lote-aviso" role="status"></p>

        <div class="arch-lote-acciones">
            <a href="/archivos?proyecto=<?php echo $id; ?>" class="btn btn-ghost">Cancelar</a>
            <button type="button" class="btn btn-ghost" id="arch-lote-limpiar"><i class="fa-solid fa-eraser"></i> Quitar todos</button>
            <button type="submit" class="btn btn-primary" id="arch-lote-subir"><i class="fa-solid fa-upload"></i> Subir archivos</button>
        </div>
    </form>
</div>
<?php if (!$categorias || !$carpetas): ?>
<div class="alerta alerta-info text-sm">
    <?php if (!$categorias): ?><span>No hay categorías todavía. <a href="/archivos/categorias/crear?proyecto=<?php echo $id; ?>">Crea la primera</a> si quieres clasificar los archivos.</span><?php endif; ?>
    <?php if (!$carpetas): ?><span>Crea carpetas desde <a href="/archivos?proyecto=<?php echo $id; ?>">Archivo</a> para organizar el lote.</span><?php endif; ?>
</div>
<?php endif; ?>
<script>
window.ARCH_LOTE = <?php echo json_encode([
    'maxArchivos' => (int)$maxLote,
    'maxBytes' => (int)$maxBytes,
    'maxLoteBytes' => (int)$maxLoteBytes,
    'hoy' => $fechaHoy,
    'carpetaInicial' => $carpetaInicial ? (string)$carpetaInicial : '',
    'categorias' => array_map(fn($c) => ['id' => (int)$c->id, 'nombre' => $c->nombre], $categorias),
    'carpetas' => array_map(fn($c) => ['id' => (int)$c->id, 'nombre' => $c->nombre], $carpetas),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="/vendor/archivo-lote.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/archivo-lote.js'); ?>"></script>
