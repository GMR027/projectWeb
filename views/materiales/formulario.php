<?php $esNuevo = !$material->id; ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo material' : 'Editar material'; ?></h2>
    <a href="/materiales?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <form method="POST" action="/materiales/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($material->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo">
                <label for="nombre">Nombre del material *</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo s($material->nombre); ?>" required>
            </div>
            <div class="campo">
                <label for="referencia">Referencia (Spec Number)</label>
                <input type="text" id="referencia" name="referencia" value="<?php echo s($material->referencia); ?>">
            </div>
            <div class="campo">
                <label for="especialidad">Especialidad</label>
                <?php $catalogo = \Model\Especialidad::nombres((int)$proyecto->id); if ($material->especialidad && !in_array($material->especialidad, $catalogo, true)) $catalogo[] = $material->especialidad; ?>
                <select id="especialidad" name="especialidad">
                    <option value="">— Sin especialidad —</option>
                    <?php foreach ($catalogo as $e): ?>
                        <option value="<?php echo s($e); ?>"<?php echo $material->especialidad === $e ? ' selected' : ''; ?>><?php echo s($e); ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted"><a href="/especialidades?proyecto=<?php echo s($proyecto->id); ?>">Administrar especialidades</a></small>
            </div>
            <div class="campo">
                <label for="certificado_calidad">Certificado calidad</label>
                <input type="text" id="certificado_calidad" name="certificado_calidad" maxlength="255" value="<?php echo s($material->certificado_calidad); ?>">
            </div>
            <div class="campo">
                <label for="link_web">Link web</label>
                <input type="url" id="link_web" name="link_web" value="<?php echo s($material->link_web); ?>" placeholder="https://…">
            </div>
            <div class="campo">
                <label for="cantidad">Cantidad</label>
                <input type="number" id="cantidad" name="cantidad" min="0" step="0.01" value="<?php echo $material->cantidad !== null && $material->cantidad !== '' ? s(rtrim(rtrim((string)$material->cantidad, '0'), '.')) : ''; ?>" placeholder="Opcional">
            </div>
            <div class="campo">
                <label for="unidad">Unidad</label>
                <input type="text" id="unidad" name="unidad" maxlength="30" value="<?php echo s($material->unidad); ?>" placeholder="pza, m, kg…">
            </div>
            <div class="campo">
                <label for="imagen">Imagen (se guarda en miniatura)</label>
                <input type="file" id="imagen" name="imagen" accept="image/*">
                <?php if ($material->imagen): ?>
                    <img class="mat-thumb" src="/uploads/materiales/<?php echo s($material->imagen); ?>" alt="" style="margin-top:.5rem">
                    <label class="text-sm"><input type="checkbox" name="quitar_imagen" value="1"> Quitar imagen</label>
                <?php endif; ?>
            </div>
            <div class="campo">
                <label for="ficha_tecnica">Ficha técnica (PDF)</label>
                <input type="file" id="ficha_tecnica" name="ficha_tecnica" accept="application/pdf">
                <small class="text-muted">Se guarda como <span class="mono">nombre-del-material-especialidad.pdf</span></small>
                <?php if ($material->ficha_tecnica): ?>
                    <div class="text-sm"><a href="/materiales/ficha?id=<?php echo s($material->id); ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> <?php echo s($material->ficha_tecnica); ?></a>
                    · <label><input type="checkbox" name="quitar_ficha" value="1"> Quitar</label></div>
                <?php endif; ?>
            </div>
        </div>
        <h3 class="mt-2">Logística y entrega</h3>
        <small class="text-muted">Estas fechas se reflejan automáticamente en el MSR.</small>
        <div class="form-grid">
            <div class="campo">
                <label for="compra">Compra</label>
                <select id="compra" name="compra">
                    <?php foreach (\Model\Material::COMPRA as $k => $etq): ?>
                        <option value="<?php echo $k; ?>"<?php echo $material->compra === $k ? ' selected' : ''; ?>><?php echo $etq; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($material->fecha_compra_real): ?><small class="text-muted">Compra real: <?php echo formatear_fecha($material->fecha_compra_real); ?></small><?php endif; ?>
            </div>
            <div class="campo">
                <label for="fecha_compra_prog">Fecha compra de material (prog.)</label>
                <input type="date" id="fecha_compra_prog" name="fecha_compra_prog" value="<?php echo s($material->fecha_compra_prog); ?>">
            </div>
            <div class="campo">
                <label for="fecha_entrega_prog">Fecha de entrega programada</label>
                <input type="date" id="fecha_entrega_prog" name="fecha_entrega_prog" value="<?php echo s($material->fecha_entrega_prog); ?>">
            </div>
            <div class="campo">
                <label for="fecha_entrega_real">Fecha de entrega real</label>
                <input type="date" id="fecha_entrega_real" name="fecha_entrega_real" value="<?php echo s($material->fecha_entrega_real); ?>">
                <small class="text-muted">También se llena con el botón «Entregado» del listado.</small>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
