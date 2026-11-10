<?php
/**
 * Campos de organización y plan para el alta/edición de usuarios (admin).
 * Con $orgFija (alta desde la ficha de una organización) solo se muestra el papel
 * dentro de la empresa: la organización ya está decidida y no se puede cambiar aquí.
 */
$planes = $planes ?? [];
$organizaciones = $organizaciones ?? [];
$organizacion = $organizacion ?? null;
$orgFija = $orgFija ?? null;
$planSel = $organizacion ? $organizacion->planClave() : \Classes\Plan::POR_DEFECTO;
$rolOrgSel = in_array((string)($usuario->rol_org ?? ''), ['propietario', 'gestor'], true) ? (string)$usuario->rol_org : 'propietario';

if ($orgFija):
    $esEquipo = (string)($usuario->rol ?? '') === 'visitante';
    $papel = $esEquipo
        ? (($usuario->nivel_acceso ?? 'ver') === 'editar' ? 'Colaborador' : 'Visitante')
        : ($rolOrgSel === 'gestor' ? 'Gestor' : 'Propietario');
    $valorRol = $esEquipo
        ? (($usuario->nivel_acceso ?? 'ver') === 'editar' ? 'colaborador' : 'visitante')
        : $rolOrgSel;
    ?>
    <div class="campo" id="campo-rol-org">
        <label>Organización y papel</label>
        <p class="text-sm" style="margin:.2rem 0 0">
            <span class="badge"><?php echo s($papel); ?></span>
            en <strong><?php echo s($orgFija->nombre); ?></strong>
        </p>
        <small class="text-muted">Para cambiar la empresa, edita el usuario desde Usuarios. Un cliente que no tenga empresa puede asignarse aquí desde su ficha.</small>
        <input type="hidden" name="rol_org" id="rol_org" value="<?php echo s($valorRol); ?>">
        <input type="hidden" name="organizacion_id" value="<?php echo (int)$orgFija->id; ?>">
    </div>
    <?php
    return;
endif;
?>
<div class="campo" id="campo-rol-org">
    <label for="rol_org">Tipo de cuenta</label>
    <select id="rol_org" name="rol_org">
        <option value="propietario" <?php echo $rolOrgSel === 'propietario' ? 'selected' : ''; ?>>Propietario · crea y administra su organización</option>
        <option value="gestor" <?php echo $rolOrgSel === 'gestor' ? 'selected' : ''; ?>>Gestor · se une a una organización Pro o superior</option>
    </select>
    <small class="text-muted">El gestor crea y edita obras de la organización, pero no administra equipo ni facturación. El propietario siempre es uno.</small>
</div>
<div class="campo" id="campo-plan">
    <label for="plan_id">Plan de la organización</label>
    <select id="plan_id" name="plan_id">
        <?php foreach ($planes as $p): ?>
            <option value="<?php echo s($p['clave']); ?>" <?php echo $planSel === $p['clave'] ? 'selected' : ''; ?>>
                <?php echo s($p['nombre']); ?> · <?php echo s(\Classes\Plan::precio($p['clave'])); ?> MXN/mes ·
                <?php echo $p['obras'] === null ? 'obras ilimitadas' : (int)$p['obras'] . ' obras'; ?> ·
                <?php echo $p['usuarios'] === null ? 'usuarios ilimitados' : (int)$p['usuarios'] . ' usuarios'; ?>
            </option>
        <?php endforeach; ?>
        <?php if ($organizacion && $organizacion->planClave() === 'legacy'): ?>
            <option value="legacy" selected>Legacy (precio y límites actuales)</option>
        <?php endif; ?>
    </select>
    <small class="text-muted">El plan define obras activas, usuarios de edición y almacenamiento total. Se puede cambiar después en Organizaciones.</small>
</div>

<div class="campo" id="campo-org">
    <label for="organizacion_nombre">Nombre de la organización</label>
    <input type="text" id="organizacion_nombre" name="organizacion_nombre" maxlength="200"
        value="<?php echo s($organizacion->nombre ?? ($usuario->nombre ?? '')); ?>">
    <label for="razon_social" style="margin-top:1.2rem">Razón social</label>
    <input type="text" id="razon_social" name="razon_social" maxlength="200" value="<?php echo s($organizacion->razon_social ?? ''); ?>">
    <label for="rfc" style="margin-top:1.2rem">RFC</label>
    <input type="text" id="rfc" name="rfc" maxlength="20" value="<?php echo s($organizacion->rfc ?? ''); ?>">
    <label for="correo_facturacion" style="margin-top:1.2rem">Correo de facturación</label>
    <input type="email" id="correo_facturacion" name="correo_facturacion" maxlength="150" value="<?php echo s($organizacion->correo_facturacion ?? ($usuario->email ?? '')); ?>">
    <label for="telefono" style="margin-top:1.2rem">Teléfono</label>
    <input type="tel" id="telefono" name="telefono" maxlength="30" value="<?php echo s($organizacion->telefono ?? ''); ?>">
    <?php if ($organizacion): ?>
        <label for="estado_plan" style="margin-top:1.2rem">Estado del plan</label>
        <select id="estado_plan" name="estado_plan">
            <?php foreach (['trial' => 'Prueba', 'activo' => 'Activo', 'suspendido' => 'Suspendido', 'cancelado' => 'Cancelado'] as $k => $et): ?>
                <option value="<?php echo s($k); ?>" <?php echo ($organizacion->estado_plan ?? 'activo') === $k ? 'selected' : ''; ?>><?php echo s($et); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="plan_inicio" style="margin-top:1.2rem">Inicio del plan</label>
        <input type="date" id="plan_inicio" name="plan_inicio" value="<?php echo s($organizacion->plan_inicio ?? ''); ?>">
        <label for="plan_fin" style="margin-top:1.2rem">Fin del plan</label>
        <input type="date" id="plan_fin" name="plan_fin" value="<?php echo s($organizacion->plan_fin ?? ''); ?>">
        <label for="gracia_hasta" style="margin-top:1.2rem">Gracia hasta</label>
        <input type="date" id="gracia_hasta" name="gracia_hasta" value="<?php echo s($organizacion->gracia_hasta ?? ''); ?>">
        <label for="precio_legado" style="margin-top:1.2rem">Precio legacy (MXN/mes)</label>
        <input type="number" id="precio_legado" name="precio_legado" min="0" step="0.01" value="<?php echo s($organizacion->precio_legado ?? ''); ?>">
    <?php endif; ?>
</div>

<div class="campo" id="campo-org-vis">
    <label for="organizacion_id">Organización</label>
    <select id="organizacion_id" name="organizacion_id">
        <option value="">— Sin organización (visitante externo) —</option>
        <?php foreach ($organizaciones as $o): ?>
            <option value="<?php echo (int)$o->id; ?>" <?php echo (int)($usuario->organizacion_id ?? 0) === (int)$o->id ? 'selected' : ''; ?>><?php echo s($o->nombre); ?></option>
        <?php endforeach; ?>
    </select>
    <small class="text-muted">Obligatoria para un gestor; opcional para un visitante externo (no consume asientos y se le asigna proyecto por proyecto).</small>
</div>
