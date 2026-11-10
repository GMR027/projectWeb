<?php
/**
 * Ficha de la organización (solo admin): resumen, clientes, equipo, obras y pagos.
 * Aquí se agregan los clientes (propietario/gestor) y los visitantes/colaboradores
 * de la empresa; los permisos de cada uno siguen saliendo de proyecto_accesos.
 */
$limUsuarios = $limites['usuarios'] ?? null;
$limVisitantes = $limites['visitantes'] ?? null;
$sinPropietario = !$propietario;
$permiteGestores = !empty($plan['permite_gestores']);
$equipoLleno = $limVisitantes !== null && count($equipo) >= (int)$limVisitantes;
$gestores = array_values(array_filter($clientes, fn($u) => (string)$u->rol_org === 'gestor'));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-building"></i> <?php echo s($org->nombre); ?></h2>
        <p class="text-muted">
            <?php echo s($plan['nombre']); ?> ·
            <?php echo s($org->estadoEtiqueta()); ?>
            <?php if ($org->estaArchivada()): ?> · <span class="badge">Archivada</span><?php endif; ?>
            <?php if ($org->rfc): ?> · <span class="mono"><?php echo s($org->rfc); ?></span><?php endif; ?>
        </p>
    </div>
    <div class="acciones">
        <a href="/organizaciones/editar?id=<?php echo (int)$org->id; ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-pen"></i> Editar</a>
        <a href="/organizaciones" class="btn btn-sm btn-ghost">← Volver</a>
    </div>
</div>

<div class="pestanas" role="tablist" aria-label="Secciones de la organización">
    <a href="#resumen" class="activo"><i class="fa-solid fa-chart-pie"></i> Resumen</a>
    <a href="#clientes"><i class="fa-solid fa-user-tie"></i> Clientes</a>
    <a href="#equipo"><i class="fa-solid fa-user-group"></i> Visitantes y colaboradores</a>
    <a href="#obras"><i class="fa-solid fa-building"></i> Obras</a>
    <a href="#pagos"><i class="fa-solid fa-receipt"></i> Pagos</a>
</div>

<section id="resumen" class="card" style="margin-top:1.2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-chart-pie"></i> Resumen</h3>
    <div class="grid-proyectos">
        <div><span class="text-muted text-sm">Propietario</span><br>
            <strong><?php echo $propietario ? s($propietario->nombre) : '—'; ?></strong>
            <?php if ($propietario): ?><br><span class="text-muted text-sm"><?php echo s($propietario->email); ?></span><?php endif; ?>
        </div>
        <div><span class="text-muted text-sm">Obras activas</span><br><strong><?php echo (int)$obras_activas; ?> / <?php echo $limites['obras'] === null ? '∞' : (int)$limites['obras']; ?></strong></div>
        <div><span class="text-muted text-sm">Asientos usados</span><br><strong><?php echo (int)$asientos; ?> / <?php echo $limUsuarios === null ? '∞' : (int)$limUsuarios; ?></strong></div>
        <div><span class="text-muted text-sm">Visitantes</span><br><strong><?php echo (int)$visitantes; ?> / <?php echo $limVisitantes === null ? '∞' : (int)$limVisitantes; ?></strong></div>
        <div><span class="text-muted text-sm">Almacenamiento</span><br><strong><?php echo \Classes\Almacenamiento::formato((int)$almacenamiento); ?> / <?php echo $limite_almacenamiento === null ? 'sin tope' : \Classes\Almacenamiento::formato((int)$limite_almacenamiento); ?></strong></div>
        <div><span class="text-muted text-sm">Facturación</span><br>
            <span class="text-sm"><?php echo s($org->correo_facturacion ?: '—'); ?></span><br>
            <?php if ($org->telefono): ?><span class="text-muted text-sm"><?php echo s($org->telefono); ?></span><?php endif; ?>
        </div>
    </div>
    <?php if (($org->estado_plan ?? '') === 'trial' && $org->diasTrial() !== null): ?>
        <p class="text-muted text-sm" style="margin-top:1rem">Quedan <?php echo max(0, (int)$org->diasTrial()); ?> día(s) de prueba.</p>
    <?php endif; ?>
    <div style="display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1.4rem">
        <?php if ($org->estaArchivada()): ?>
            <form method="POST" action="/organizaciones/desarchivar" class="inline">
                <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
                <button type="submit" class="btn btn-sm btn-ghost"><i class="fa-solid fa-box-open"></i> Desarchivar</button>
            </form>
        <?php else: ?>
            <form method="POST" action="/organizaciones/archivar" class="inline">
                <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
                <button type="submit" class="btn btn-sm btn-ghost" data-confirm="¿Archivar la organización?|Dejará de aparecer en la lista y en los selectores. Sus obras, usuarios y pagos se conservan."
                    <?php echo $obras_activas > 0 ? 'disabled title="Archiva primero sus obras activas"' : ''; ?>>
                    <i class="fa-solid fa-box-archive"></i> Archivar
                </button>
            </form>
        <?php endif; ?>
        <?php if ($puede_eliminarse): ?>
            <form method="POST" action="/organizaciones/eliminar" class="inline">
                <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
                <button type="submit" class="btn btn-sm btn-danger" data-confirm="¿Eliminar la organización?|Solo es posible porque no tiene obras, usuarios ni pagos. Esta acción no se puede deshacer.">
                    <i class="fa-solid fa-trash"></i> Eliminar
                </button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section id="clientes" class="card" style="margin-top:1.6rem">
    <h3 class="card-titulo"><i class="fa-solid fa-user-tie"></i> Clientes de la organización</h3>
    <p class="text-muted text-sm">El cliente propietario administra la empresa, su equipo y su facturación. Los gestores crean y editan obras, pero no administran equipo ni pagos.</p>

    <div style="display:flex; flex-wrap:wrap; gap:.6rem; margin-bottom:1.2rem">
        <?php if ($sinPropietario): ?>
            <a href="/usuarios/crear?contexto=organizacion&amp;org=<?php echo (int)$org->id; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> Agregar cliente propietario</a>
        <?php elseif ($permiteGestores): ?>
            <a href="/usuarios/crear?contexto=organizacion&amp;org=<?php echo (int)$org->id; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> Agregar gestor</a>
        <?php else: ?>
            <span class="text-muted text-sm">Este plan no permite gestores: para sumar otro cliente hay que subir a Pro o superior.</span>
        <?php endif; ?>
    </div>

    <?php if ($clientes): ?>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th class="hide-m">Correo</th><th>Papel</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($clientes as $u): ?>
                <tr>
                    <td><?php echo s($u->nombre); ?></td>
                    <td class="hide-m mono text-sm"><?php echo s($u->email); ?></td>
                    <td><span class="badge"><?php echo s($u->rol_org === 'gestor' ? 'Gestor' : 'Propietario'); ?></span></td>
                    <td class="acciones">
                        <a href="/usuarios/editar?id=<?php echo (int)$u->id; ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <?php if ((string)$u->rol_org !== 'propietario'): ?>
                            <form method="POST" action="/organizaciones/quitar-miembro" class="inline">
                                <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
                                <input type="hidden" name="usuario_id" value="<?php echo (int)$u->id; ?>">
                                <button type="submit" class="btn btn-sm btn-ghost" data-confirm="¿Sacar a <?php echo s($u->nombre); ?> de la organización?|Dejará de ver las obras de la empresa.">Quitar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">Todavía no tiene clientes. Crea el propietario para que la empresa quede operativa.</p>
    <?php endif; ?>

    <?php if ($propietario && $gestores): ?>
        <details style="margin-top:1.2rem">
            <summary class="text-sm">Transferir la propiedad</summary>
            <form method="POST" action="/organizaciones/transferir" class="formulario" style="margin-top:.8rem">
                <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
                <div class="form-grid">
                    <div class="campo">
                        <label for="nuevo_propietario_id">Nuevo propietario</label>
                        <select id="nuevo_propietario_id" name="nuevo_propietario_id" required>
                            <option value="">— Selecciona un gestor —</option>
                            <?php foreach ($gestores as $g): ?>
                                <option value="<?php echo (int)$g->id; ?>"><?php echo s($g->nombre); ?> · <?php echo s($g->email); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-ghost" data-confirm="¿Transferir la propiedad?|El propietario actual quedará como gestor."><i class="fa-solid fa-right-left"></i> Transferir</button>
            </form>
        </details>
    <?php endif; ?>
</section>

<section id="equipo" class="card" style="margin-top:1.6rem">
    <h3 class="card-titulo"><i class="fa-solid fa-user-group"></i> Visitantes y colaboradores</h3>
    <p class="text-muted text-sm">El visitante solo ve las obras y herramientas que se le asignen. El colaborador puede editar en las herramientas marcadas, nunca borrar.</p>

    <div style="display:flex; flex-wrap:wrap; gap:.6rem; margin-bottom:1.2rem">
        <?php if ($equipoLleno): ?>
            <span class="text-muted text-sm">Se alcanzó el tope de <?php echo (int)$limVisitantes; ?> visitantes del plan.</span>
        <?php else: ?>
            <a href="/usuarios/crear?contexto=equipo&amp;org=<?php echo (int)$org->id; ?>&amp;tipo=visitante" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> Nuevo visitante</a>
            <a href="/usuarios/crear?contexto=equipo&amp;org=<?php echo (int)$org->id; ?>&amp;tipo=colaborador" class="btn btn-sm btn-ghost"><i class="fa-solid fa-user-pen"></i> Nuevo colaborador</a>
        <?php endif; ?>
    </div>

    <?php if ($equipo): ?>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th class="hide-m">Correo</th><th>Tipo</th><th class="hide-m">Obras</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($equipo as $u): $obrasU = \Model\ProyectoAcceso::proyectosDe((int)$u->id); ?>
                <tr>
                    <td><?php echo s($u->nombre); ?></td>
                    <td class="hide-m mono text-sm"><?php echo s($u->email); ?></td>
                    <td><span class="badge"><?php echo s($u->rol_org === 'colaborador' ? 'Colaborador' : 'Visitante'); ?></span></td>
                    <td class="hide-m mono"><?php echo count($obrasU); ?></td>
                    <td class="acciones">
                        <a href="/usuarios/editar?id=<?php echo (int)$u->id; ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/organizaciones/quitar-miembro" class="inline">
                            <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
                            <input type="hidden" name="usuario_id" value="<?php echo (int)$u->id; ?>">
                            <button type="submit" class="btn btn-sm btn-ghost" data-confirm="¿Sacar a <?php echo s($u->nombre); ?> de la organización?|Perderá el acceso a las obras de la empresa.">Quitar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">Sin visitantes ni colaboradores todavía.</p>
    <?php endif; ?>
</section>

<section id="obras" class="card" style="margin-top:1.6rem">
    <h3 class="card-titulo"><i class="fa-solid fa-building"></i> Obras</h3>
    <?php if ($obras): ?>
        <table class="tabla">
            <thead><tr><th>Obra</th><th class="hide-m">Código</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($obras as $p): ?>
                <tr>
                    <td><?php echo s($p->nombre); ?></td>
                    <td class="hide-m mono text-sm"><?php echo s($p->codigo); ?></td>
                    <td><span class="badge"><?php echo $p->estaArchivado() ? 'Archivada' : 'Activa'; ?></span></td>
                    <td class="acciones"><a href="/programa?proyecto=<?php echo (int)$p->id; ?>" class="btn btn-sm btn-ghost">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">La organización todavía no tiene obras. Un cliente propietario o gestor puede crearlas.</p>
    <?php endif; ?>
</section>

<section id="pagos" class="card" style="margin-top:1.6rem">
    <h3 class="card-titulo"><i class="fa-solid fa-receipt"></i> Pagos manuales</h3>
    <p class="text-muted text-sm">Registra aquí los pagos recibidos (transferencia, depósito, efectivo). El CFDI se emite fuera de la plataforma; guarda su UUID para tener el historial completo.</p>
    <?php if (isset($_GET['pago'])): ?>
        <p class="text-sm" style="color:<?php echo $_GET['pago'] === 'ok' ? 'var(--primary)' : 'var(--danger, #ef4444)'; ?>">
            <?php echo $_GET['pago'] === 'ok' ? 'Pago registrado.' : 'No se pudo registrar el pago (revisa el monto).'; ?>
        </p>
    <?php endif; ?>
    <form method="POST" action="/organizaciones/editar" class="formulario">
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
</section>
