<?php
/** Campos de la organización, compartidos por el alta y la edición (solo admin). */
?>
<div class="form-grid">
    <div class="campo">
        <label for="nombre">Nombre de la organización</label>
        <input type="text" id="nombre" name="nombre" maxlength="200" value="<?php echo s($org->nombre); ?>" required>
    </div>
    <div class="campo">
        <label for="razon_social">Razón social</label>
        <input type="text" id="razon_social" name="razon_social" maxlength="200" value="<?php echo s($org->razon_social); ?>">
    </div>
    <div class="campo">
        <label for="rfc">RFC</label>
        <input type="text" id="rfc" name="rfc" maxlength="20" value="<?php echo s($org->rfc); ?>">
    </div>
    <div class="campo">
        <label for="correo_facturacion">Correo de facturación</label>
        <input type="email" id="correo_facturacion" name="correo_facturacion" maxlength="150" value="<?php echo s($org->correo_facturacion); ?>">
    </div>
    <div class="campo">
        <label for="telefono">Teléfono</label>
        <input type="tel" id="telefono" name="telefono" maxlength="30" value="<?php echo s($org->telefono); ?>">
    </div>
    <div class="campo">
        <label for="logo">Logo de la empresa</label>
        <input type="file" id="logo" name="logo" accept="image/*">
        <?php if (!empty($org->logo)): ?>
            <img src="/build/img/logos/<?php echo s($org->logo); ?>" alt="Logo de <?php echo s($org->nombre); ?>" style="max-width:9rem; margin-top:.6rem;">
        <?php endif; ?>
    </div>
</div>
