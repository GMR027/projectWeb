<?php
$esNuevo = !$reporte->id; $id = s($proyecto->id);
$itemsActs = [];
foreach ($actividades as $aid => [$a, $p]) $itemsActs[] = ['id' => $aid, 'titulo' => $a->nombre, 'detalle' => $a->codigo];
$itemsPers = [];
foreach ($personal as $pid => $p) $itemsPers[] = ['id' => $pid, 'titulo' => $p->nombre, 'detalle' => $p->departamento ?: '—'];
?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo reporte diario' : 'Editar reporte diario'; ?></h2>
    <a href="/reportes?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <form method="POST" action="/reportes/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($reporte->id); ?>"><?php endif; ?>

        <div class="form-grid">
            <div class="campo"><label>Proyecto</label><input type="text" value="<?php echo s($proyecto->nombre); ?>" disabled></div>
            <div class="campo"><label>Número de trabajo</label><input type="text" value="<?php echo s($proyecto->numero_trabajo); ?>" disabled></div>
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($reporte->fecha); ?>" required></div>
            <div class="campo"><label for="area">Área</label><input type="text" id="area" name="area" maxlength="150" value="<?php echo s($reporte->area); ?>" placeholder="Ej: Zona norte"></div>
        </div>

        <div class="campo">
            <label>Actividades del programa * (una o varias)</label>
            <div class="rd-lista-grande">
            <?php $items = $itemsActs; $nombreCampo = 'actividades'; $marcados = $reporte->getActividades(); include __DIR__ . '/../templates/lista-accesos.php'; ?>
            </div>
        </div>

        <div class="campo">
            <label>Personal</label>
            <?php $items = $itemsPers; $nombreCampo = 'personal'; $marcados = $reporte->getPersonal(); include __DIR__ . '/../templates/lista-accesos.php'; ?>
            <?php if (!$personal): ?><small class="text-muted">Aún no hay personal. <a href="/reportes/personal/crear?proyecto=<?php echo $id; ?>">Agrégalo aquí</a>.</small><?php endif; ?>
        </div>

        <div class="campo">
            <label for="comentarios">Comentarios</label>
            <textarea id="comentarios" name="comentarios" rows="5"><?php echo s($reporte->comentarios); ?></textarea>
        </div>

        <div class="campo">
            <label for="fotos">Fotos de la actividad</label>
            <input type="file" id="fotos" name="fotos[]" accept="image/*" multiple>
            <small class="text-muted">Hasta 20 fotos por guardado (jpg, png, webp, gif; máx. 15 MB cada una).</small>
            <div class="rd-previa" id="previa"></div>
            <?php if ($reporte->getFotos()): ?>
                <p class="text-sm" style="margin-top:1rem">Fotos actuales: marca las que quieras quitar.</p>
                <div class="rd-fotos rd-fotos-edicion">
                    <?php foreach ($reporte->getFotos() as $f): ?>
                        <label class="rd-foto">
                            <img src="/uploads/reportes/<?php echo s($f); ?>" alt="" loading="lazy">
                            <span class="rd-quitar"><input type="checkbox" name="quitar_fotos[]" value="<?php echo s($f); ?>"> Quitar</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
<script>
document.getElementById('fotos').addEventListener('change', e => {
    const previa = document.getElementById('previa');
    previa.innerHTML = '';
    [...e.target.files].slice(0, 20).forEach(f => {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        previa.appendChild(img);
    });
});
</script>
