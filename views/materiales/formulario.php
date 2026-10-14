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
                <input type="text" id="especialidad" name="especialidad" value="<?php echo s($material->especialidad); ?>" list="lista-esp">
                <datalist id="lista-esp">
                    <?php foreach (array_unique(array_filter(array_map(fn($m) => $m->especialidad, \Model\Material::deProyecto((int)$proyecto->id)))) as $e): ?>
                        <option value="<?php echo s($e); ?>">
                    <?php endforeach; ?>
                </datalist>
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
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
