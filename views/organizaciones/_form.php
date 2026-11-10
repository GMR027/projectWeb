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
    <div class="campo">
        <label for="plan_id">Plan</label>
        <select id="plan_id" name="plan_id">
            <?php foreach ($planes as $p): ?>
                <option value="<?php echo s($p['clave']); ?>" <?php echo $org->planClave() === $p['clave'] ? 'selected' : ''; ?>>
                    <?php echo s($p['nombre']); ?> · <?php echo s(\Classes\Plan::precio($p['clave'])); ?> MXN/mes
                </option>
            <?php endforeach; ?>
        </select>
        <small class="text-muted">Si ya tiene clientes, el plan nuevo no puede dejar sus asientos por debajo de los usuarios actuales.</small>
    </div>
    <div class="campo">
        <label for="estado_plan">Estado</label>
        <select id="estado_plan" name="estado_plan">
            <?php foreach (['trial' => 'Prueba', 'activo' => 'Activo', 'suspendido' => 'Suspendido', 'cancelado' => 'Cancelado'] as $k => $et): ?>
                <option value="<?php echo s($k); ?>" <?php echo ($org->estado_plan ?? 'trial') === $k ? 'selected' : ''; ?>><?php echo s($et); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo">
        <label for="plan_inicio">Inicio del plan</label>
        <input type="date" id="plan_inicio" name="plan_inicio" value="<?php echo s($org->plan_inicio); ?>">
    </div>
    <div class="campo">
        <label for="plan_fin">Fin del plan</label>
        <input type="date" id="plan_fin" name="plan_fin" value="<?php echo s($org->plan_fin); ?>">
    </div>
    <div class="campo">
        <label for="gracia_hasta">Gracia hasta</label>
        <input type="date" id="gracia_hasta" name="gracia_hasta" value="<?php echo s($org->gracia_hasta); ?>">
        <small class="text-muted">Si se deja vacío, al terminar el plan se dan 30 días de gracia.</small>
    </div>
    <div class="campo">
        <label for="precio_legado">Precio legacy (MXN/mes)</label>
        <input type="number" id="precio_legado" name="precio_legado" min="0" step="0.01" value="<?php echo s($org->precio_legado); ?>">
        <small class="text-muted">Solo para clientes con precio anterior. No cambia el plan ni los límites.</small>
    </div>
</div>
