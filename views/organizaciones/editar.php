<?php
/** Edición de una organización: plan, estado, fechas y datos fiscales (solo admin). */
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-building"></i> <?php echo s($org->nombre); ?></h2>
        <p class="text-muted"><?php echo $propietario ? 'Propietario: ' . s($propietario->nombre) . ' · ' . s($propietario->email) : 'Sin propietario'; ?></p>
    </div>
    <a href="/organizaciones" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <div class="grid-proyectos" style="margin-bottom:1.6rem">
        <div><span class="text-muted text-sm">Plan</span><br><strong><?php echo s($plan['nombre']); ?></strong></div>
        <div><span class="text-muted text-sm">Obras activas</span><br><strong><?php echo (int)$obras; ?> / <?php echo $limites['obras'] === null ? '∞' : (int)$limites['obras']; ?></strong></div>
        <div><span class="text-muted text-sm">Asientos</span><br><strong><?php echo (int)$asientos; ?> / <?php echo $limites['usuarios'] === null ? '∞' : (int)$limites['usuarios']; ?></strong></div>
        <div><span class="text-muted text-sm">Almacenamiento</span><br><strong><?php echo \Classes\Almacenamiento::formato((int)$almacenamiento); ?> / <?php echo $limite_almacenamiento === null ? 'sin tope' : \Classes\Almacenamiento::formato((int)$limite_almacenamiento); ?></strong></div>
    </div>

    <form method="POST" class="formulario">
        <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
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
                <label for="plan_id">Plan</label>
                <select id="plan_id" name="plan_id">
                    <?php foreach ($planes as $p): ?>
                        <option value="<?php echo s($p['clave']); ?>" <?php echo $org->planClave() === $p['clave'] ? 'selected' : ''; ?>>
                            <?php echo s($p['nombre']); ?> · <?php echo s(\Classes\Plan::precio($p['clave'])); ?> MXN/mes
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="estado_plan">Estado</label>
                <select id="estado_plan" name="estado_plan">
                    <?php foreach (['trial' => 'Prueba', 'activo' => 'Activo', 'suspendido' => 'Suspendido', 'cancelado' => 'Cancelado'] as $k => $et): ?>
                        <option value="<?php echo s($k); ?>" <?php echo ($org->estado_plan ?? 'activo') === $k ? 'selected' : ''; ?>><?php echo s($et); ?></option>
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
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar organización</button>
    </form>
</div>

<div class="card" style="margin-top:1.6rem">
    <h3 class="card-titulo"><i class="fa-solid fa-receipt"></i> Pagos manuales</h3>
    <p class="text-muted text-sm">Registra aquí los pagos recibidos (transferencia, depósito, efectivo). El CFDI se emite fuera de la plataforma; guarda su UUID para tener el historial completo.</p>
    <?php if (isset($_GET['pago'])): ?>
        <p class="text-sm" style="color:<?php echo $_GET['pago'] === 'ok' ? 'var(--primary)' : 'var(--danger, #ef4444)'; ?>">
            <?php echo $_GET['pago'] === 'ok' ? 'Pago registrado.' : 'No se pudo registrar el pago (revisa el monto).'; ?>
        </p>
    <?php endif; ?>
    <form method="POST" class="formulario">
        <input type="hidden" name="accion" value="pago">
        <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
        <div class="form-grid">
            <div class="campo">
                <label for="pago_fecha">Fecha del pago</label>
                <input type="date" id="pago_fecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="campo">
                <label for="pago_monto">Monto (MXN)</label>
                <input type="number" id="pago_monto" name="monto" min="0.01" step="0.01" required>
            </div>
            <div class="campo">
                <label for="pago_ini">Periodo desde</label>
                <input type="date" id="pago_ini" name="periodo_inicio">
            </div>
            <div class="campo">
                <label for="pago_fin">Periodo hasta</label>
                <input type="date" id="pago_fin" name="periodo_fin">
            </div>
            <div class="campo">
                <label for="pago_metodo">Método</label>
                <select id="pago_metodo" name="metodo">
                    <option value="transferencia">Transferencia</option>
                    <option value="deposito">Depósito</option>
                    <option value="efectivo">Efectivo</option>
                    <option value="tarjeta">Tarjeta</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
            <div class="campo">
                <label for="pago_ref">Referencia</label>
                <input type="text" id="pago_ref" name="referencia" maxlength="100">
            </div>
            <div class="campo">
                <label for="pago_uuid">UUID del CFDI</label>
                <input type="text" id="pago_uuid" name="cfdi_uuid" maxlength="50">
            </div>
            <div class="campo">
                <label for="pago_notas">Notas</label>
                <input type="text" id="pago_notas" name="notas" maxlength="255">
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Registrar pago</button>
    </form>

    <?php if (!empty($pagos)): ?>
        <table class="tabla" style="margin-top:1.6rem">
            <thead><tr><th>Fecha</th><th class="text-right">Monto</th><th class="hide-m">Periodo</th><th class="hide-m">Método</th><th class="hide-m">Referencia / CFDI</th></tr></thead>
            <tbody>
            <?php foreach ($pagos as $pg): ?>
                <tr>
                    <td class="mono"><?php echo s($pg['fecha']); ?></td>
                    <td class="mono text-right">$<?php echo number_format((float)$pg['monto'], 2); ?></td>
                    <td class="hide-m mono text-sm"><?php echo s($pg['periodo_inicio'] ?: '—'); ?> → <?php echo s($pg['periodo_fin'] ?: '—'); ?></td>
                    <td class="hide-m"><?php echo s(ucfirst((string)$pg['metodo'])); ?></td>
                    <td class="hide-m text-sm"><?php echo s($pg['referencia']); ?><?php if ($pg['cfdi_uuid']): ?><br><span class="mono"><?php echo s($pg['cfdi_uuid']); ?></span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:1.6rem">
    <h3 class="card-titulo"><i class="fa-solid fa-right-left"></i> Transferir propiedad</h3>
    <p class="text-muted text-sm">El propietario actual queda como gestor (crea y edita obras, pero no factura ni administra equipo). Solo el administrador puede hacer la transferencia.</p>
    <?php if (isset($_GET['transferido'])): ?><p class="text-sm" style="color:var(--primary)">Propiedad transferida.</p><?php endif; ?>
    <form method="POST" action="/organizaciones/transferir" class="formulario">
        <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
        <div class="form-grid">
            <div class="campo">
                <label for="nuevo_propietario_id">Nuevo propietario</label>
                <select id="nuevo_propietario_id" name="nuevo_propietario_id" required>
                    <option value="">— Selecciona un usuario de la organización —</option>
                    <?php foreach (($usuarios_org ?? []) as $uo): ?>
                        <?php if ((int)$uo->id === (int)$org->propietario_id || $uo->rol_org === 'visitante') continue; ?>
                        <option value="<?php echo (int)$uo->id; ?>"><?php echo s($uo->nombre); ?> · <?php echo s($uo->email); ?> (<?php echo s($uo->rol_org); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-ghost" data-confirm="¿Transferir la propiedad?|El propietario actual quedará como gestor y el nuevo usuario podrá facturar y administrar el equipo."><i class="fa-solid fa-right-left"></i> Transferir propiedad</button>
    </form>
</div>
