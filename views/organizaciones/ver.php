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
$activa = ($org->estado_plan ?? '') === 'activo';
$enPrueba = ($org->estado_plan ?? '') === 'trial';
$diasPrueba = $org->diasTrial();
$tope = fn(string $k) => ($limites[$k] ?? null) === null ? '' : (string)(int)$limites[$k];
$porObra = (int)($limites['almacenamiento_por_obra_mb'] ?? 300);
$pctObras = $limites['obras'] ? min(100, (int)$obras_activas / max(1, (int)$limites['obras']) * 100) : 0;
$pctAsientos = $limUsuarios ? min(100, (int)$asientos / max(1, (int)$limUsuarios) * 100) : 0;
$pctVisitantes = $limVisitantes ? min(100, (int)$visitantes / max(1, (int)$limVisitantes) * 100) : 0;
$pctAlm = $limite_almacenamiento ? min(100, (int)$almacenamiento / max(1, (int)$limite_almacenamiento) * 100) : 0;
$riesgos = [];
if ($sinPropietario) $riesgos[] = ['warning', 'fa-user-slash', 'La organización no tiene cliente propietario. Agrégalo para que pueda administrar su equipo y facturación.'];
if ($enPrueba && $diasPrueba !== null && $diasPrueba <= 7) $riesgos[] = ['warning', 'fa-hourglass-half', 'Quedan ' . max(0, (int)$diasPrueba) . ' día(s) de prueba. Activa el plan antes de que termine.'];
if ($pctAsientos >= 90) $riesgos[] = ['danger', 'fa-users', 'Los asientos están al ' . number_format($pctAsientos, 0) . ' % del tope. Revisa el plan o libera asientos.'];
if ($pctObras >= 90) $riesgos[] = ['danger', 'fa-building', 'Las obras activas están al ' . number_format($pctObras, 0) . ' % del tope.'];
if ($pctAlm >= 90) $riesgos[] = ['danger', 'fa-hard-drive', 'El almacenamiento está al ' . number_format($pctAlm, 0) . ' % del tope de la empresa.'];
if ($org->estaArchivada()) $riesgos[] = ['info', 'fa-box-archive', 'Esta organización está archivada: no aparece en selectores, pero conserva obras, usuarios y pagos.'];
?>
<div class="page-header">
    <div>
        <span class="adm-eyebrow">Ficha de organización</span>
        <h2><i class="fa-solid fa-building"></i> <?php echo s($org->nombre); ?></h2>
        <p class="text-muted">
            <?php echo s($plan['nombre']); ?> ·
            <?php echo s($org->estadoEtiqueta()); ?>
            <?php if ($org->estaArchivada()): ?> · <span class="badge">Archivada</span><?php endif; ?>
            <?php if ($org->rfc): ?> · <span class="mono"><?php echo s($org->rfc); ?></span><?php endif; ?>
        </p>
        <div class="adm-meta">
            <span><i class="fa-solid fa-user-tie"></i> <b><?php echo $propietario ? s($propietario->nombre) : 'Sin propietario'; ?></b></span>
            <span><i class="fa-solid fa-helmet-safety"></i> <b><?php echo (int)$obras_activas; ?></b> obras activas</span>
            <span><i class="fa-solid fa-users"></i> <b><?php echo (int)$asientos; ?></b> asientos</span>
            <span><i class="fa-solid fa-eye"></i> <b><?php echo (int)$visitantes; ?></b> visitantes</span>
            <span><i class="fa-solid fa-hard-drive"></i> <b><?php echo \Classes\Almacenamiento::formato((int)$almacenamiento); ?></b></span>
        </div>
    </div>
    <div class="la-controles">
        <a href="/organizaciones/editar?id=<?php echo (int)$org->id; ?>" class="btn btn-secondary"><i class="fa-solid fa-id-card"></i> Datos de la empresa</a>
        <a href="/organizaciones" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

<?php if (!empty($_GET['guardado'])): ?>
    <div class="alerta alerta-exito" role="status"><i class="fa-solid fa-circle-check"></i> Cambios guardados.</div>
<?php endif; ?>
<?php $msgError = $_GET['error'] ?? ''; if (is_string($msgError) && $msgError !== ''): ?>
    <div class="alerta alerta-error" role="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo s($msgError); ?></div>
<?php endif; ?>

<?php foreach ($riesgos as [$tono, $icono, $texto]): ?>
    <div class="ctl-riesgo <?php echo $tono === 'danger' ? 'es-peligro' : ''; ?>" <?php echo $tono === 'info' ? 'style="border-left-color:var(--secondary);background:color-mix(in srgb, var(--secondary) 7%, var(--surface))"' : ''; ?>>
        <i class="fa-solid <?php echo $icono; ?>"></i>
        <div><strong><?php echo $tono === 'danger' ? 'Atención' : ($tono === 'info' ? 'Información' : 'Por revisar'); ?>:</strong> <?php echo s($texto); ?></div>
    </div>
<?php endforeach; ?>

<div class="cb-kpis">
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-helmet-safety"></i></span>
        <small>Obras activas</small>
        <strong><?php echo (int)$obras_activas; ?><span class="text-muted" style="font-size:1.4rem">/<?php echo $limites['obras'] === null ? '∞' : (int)$limites['obras']; ?></span></strong>
        <div class="ctl-uso-barra <?php echo $pctObras >= 90 ? 'es-alerta' : ($pctObras >= 70 ? 'es-aviso' : ''); ?>"><span style="width:<?php echo number_format($pctObras, 1, '.', ''); ?>%"></span></div>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-users"></i></span>
        <small>Asientos usados</small>
        <strong><?php echo (int)$asientos; ?><span class="text-muted" style="font-size:1.4rem">/<?php echo $limUsuarios === null ? '∞' : (int)$limUsuarios; ?></span></strong>
        <div class="ctl-uso-barra <?php echo $pctAsientos >= 90 ? 'es-alerta' : ($pctAsientos >= 70 ? 'es-aviso' : ''); ?>"><span style="width:<?php echo number_format($pctAsientos, 1, '.', ''); ?>%"></span></div>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-eye"></i></span>
        <small>Visitantes</small>
        <strong><?php echo (int)$visitantes; ?><span class="text-muted" style="font-size:1.4rem">/<?php echo $limVisitantes === null ? '∞' : (int)$limVisitantes; ?></span></strong>
        <div class="ctl-uso-barra <?php echo $pctVisitantes >= 90 ? 'es-alerta' : ($pctVisitantes >= 70 ? 'es-aviso' : ''); ?>"><span style="width:<?php echo number_format($pctVisitantes, 1, '.', ''); ?>%"></span></div>
    </div>
    <div class="cb-kpi">
        <span class="adm-kpi-icono"><i class="fa-solid fa-hard-drive"></i></span>
        <small>Almacenamiento</small>
        <strong class="mono"><?php echo \Classes\Almacenamiento::formato((int)$almacenamiento); ?></strong>
        <div class="ctl-uso-barra es-info <?php echo $pctAlm >= 90 ? 'es-alerta' : ($pctAlm >= 70 ? 'es-aviso' : ''); ?>"><span style="width:<?php echo number_format($pctAlm, 1, '.', ''); ?>%"></span></div>
        <span><?php echo $limite_almacenamiento === null ? 'Sin tope total' : 'de ' . \Classes\Almacenamiento::formato((int)$limite_almacenamiento); ?></span>
    </div>
</div>
<div class="pestanas" role="tablist" aria-label="Secciones de la organización">
    <a href="#plan" class="activo"><i class="fa-solid fa-layer-group"></i> Plan y estado</a>
    <a href="#resumen"><i class="fa-solid fa-chart-pie"></i> Resumen</a>
    <a href="#clientes"><i class="fa-solid fa-user-tie"></i> Clientes</a>
    <a href="#equipo"><i class="fa-solid fa-user-group"></i> Visitantes y colaboradores</a>
    <a href="#obras"><i class="fa-solid fa-building"></i> Obras</a>
    <a href="#pagos"><i class="fa-solid fa-receipt"></i> Pagos</a>
</div>

<section id="plan" class="card" style="margin-top:1.2rem" <?php echo atributos_ayuda(['ayuda' => 'Activa o suspende la empresa y cambia su plan y sus topes. Suspender deja toda la empresa en solo lectura.', 'manual' => 'organizaciones', 'label' => 'Plan y estado'], true); ?>>
    <h3 class="card-titulo"><i class="fa-solid fa-layer-group"></i> Plan y estado</h3>

    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:1rem; margin-bottom:1.4rem">
        <div>
            <span class="text-muted text-sm">Estado</span><br>
            <span class="badge <?php echo s($org->estadoBadge()); ?>" style="font-size:1.3rem"><?php echo s($org->estadoEtiqueta()); ?></span>
            <?php if ($enPrueba && $diasPrueba !== null): ?>
                <span class="text-muted text-sm"> · quedan <?php echo max(0, (int)$diasPrueba); ?> día(s) de prueba</span>
            <?php endif; ?>
        </div>
        <form method="POST" action="/organizaciones/estado" class="inline"
            <?php echo !$activa ? '' : 'data-confirm="¿Suspender la empresa?|Quedará en solo lectura: podrá consultar y exportar, pero no crear ni editar. Podrás reactivarla cuando quieras."'; ?>>
            <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
            <input type="hidden" name="estado" value="<?php echo $activa ? 'suspendido' : 'activo'; ?>">
            <button type="submit" class="btn <?php echo $activa ? 'btn-ghost' : 'btn-primary'; ?>">
                <i class="fa-solid <?php echo $activa ? 'fa-pause' : 'fa-play'; ?>"></i>
                <?php echo $activa ? 'Suspender' : ($enPrueba ? 'Activar plan' : 'Activar'); ?>
            </button>
        </form>
        <span class="text-muted text-sm">Suspender no borra nada. Al activar se quitan la prueba y las fechas de vencimiento.</span>
    </div>

    <form method="POST" action="/organizaciones/plan" class="formulario ctl-form" id="org-plan-form">
        <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
        <div class="form-grid">
            <div class="campo">
                <label for="org-plan_id">Plan</label>
                <select id="org-plan_id" name="plan_id">
                    <?php foreach ($planes as $p): ?>
                        <option value="<?php echo s($p['clave']); ?>"
                            data-obras="<?php echo $p['obras'] === null ? '' : (int)$p['obras']; ?>"
                            data-usuarios="<?php echo $p['usuarios'] === null ? '' : (int)$p['usuarios']; ?>"
                            data-visitantes="<?php echo $p['visitantes'] === null ? '' : (int)$p['visitantes']; ?>"
                            data-almacenamiento-gb="<?php echo $p['almacenamiento_gb'] === null ? '' : (int)$p['almacenamiento_gb']; ?>"
                            data-almacenamiento-por-obra-mb="<?php echo (int)$p['almacenamiento_por_obra_mb']; ?>"
                            <?php echo $org->planClave() === $p['clave'] ? 'selected' : ''; ?>>
                            <?php echo s($p['nombre']); ?> · <?php echo s(\Classes\Plan::precio($p['clave'])); ?> MXN/mes
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Al cambiar de plan se rellenan los topes con los del plan; después puedes ajustarlos.</small>
            </div>
        </div>

        <h4 style="margin:1.2rem 0 .4rem">Topes de la empresa</h4>
        <p class="text-muted text-sm" style="margin-top:0">Deja un campo vacío para que no tenga límite. No puedes dejar un tope por debajo de lo que la empresa ya usa.</p>
        <div class="form-grid">
            <div class="campo">
                <label for="tope-obras">Obras activas</label>
                <input type="number" id="tope-obras" name="topes[obras]" min="0" max="1000000" step="1" value="<?php echo s($tope('obras')); ?>" placeholder="Sin límite">
                <small class="text-muted">En uso: <?php echo (int)$obras_activas; ?></small>
            </div>
            <div class="campo">
                <label for="tope-usuarios">Personas que editan</label>
                <input type="number" id="tope-usuarios" name="topes[usuarios]" min="0" max="1000000" step="1" value="<?php echo s($tope('usuarios')); ?>" placeholder="Sin límite">
                <small class="text-muted">Incluye al propietario y a los gestores. En uso: <?php echo (int)$asientos; ?></small>
            </div>
            <div class="campo">
                <label for="tope-visitantes">Visitantes</label>
                <input type="number" id="tope-visitantes" name="topes[visitantes]" min="0" max="1000000" step="1" value="<?php echo s($tope('visitantes')); ?>" placeholder="Sin límite">
                <small class="text-muted">En uso: <?php echo (int)$visitantes; ?></small>
            </div>
            <div class="campo">
                <label for="tope-gb">Almacenamiento total (GB)</label>
                <input type="number" id="tope-gb" name="topes[almacenamiento_gb]" min="0" max="1000000" step="1" value="<?php echo s($tope('almacenamiento_gb')); ?>" placeholder="Sin límite">
                <small class="text-muted">En uso: <?php echo \Classes\Almacenamiento::formato((int)$almacenamiento); ?></small>
            </div>
            <div class="campo">
                <label for="tope-obra-mb">Almacenamiento por obra (MB)</label>
                <input type="number" id="tope-obra-mb" name="topes[almacenamiento_por_obra_mb]" min="1" max="100000" step="1" value="<?php echo (int)$porObra; ?>" required>
                <small class="text-muted">Espacio de cada obra (archivos, fotos, planos y fichas).</small>
            </div>
        </div>
        <div class="ctl-form-pie" style="display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1.2rem">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar plan y topes</button>
            <button type="button" class="btn btn-ghost" id="org-plan-aplicar"><i class="fa-solid fa-wand-magic-sparkles"></i> Aplicar límites del plan</button>
        </div>
    </form>
</section>

<script>
(function () {
    const f = document.getElementById('org-plan-form');
    if (!f) return;
    const sel = f.querySelector('[name="plan_id"]');
    const campos = {
        obras: 'obras', usuarios: 'usuarios', visitantes: 'visitantes',
        almacenamiento_gb: 'almacenamiento-gb', almacenamiento_por_obra_mb: 'almacenamiento-por-obra-mb'
    };
    const aplicar = () => {
        const o = sel && sel.options[sel.selectedIndex];
        if (!o) return;
        Object.entries(campos).forEach(([campo, dato]) => {
            const input = f.querySelector('[name="topes[' + campo + ']"]');
            if (input) input.value = o.dataset[dato] ?? '';
        });
    };
    if (sel) sel.addEventListener('change', aplicar);
    const b = document.getElementById('org-plan-aplicar');
    if (b) b.addEventListener('click', e => { e.preventDefault(); aplicar(); });
})();
</script>

<section id="resumen" class="card" style="margin-top:1.2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-chart-pie"></i> Resumen</h3>
    <div class="ctl-grid">
        <div class="ctl-aside-card">
            <h4><i class="fa-solid fa-user-tie"></i> Propietario</h4>
            <?php if ($propietario): ?>
                <div class="ctl-identidad">
                    <span class="ctl-avatar"><?php echo s(iniciales_nombre($propietario->nombre)); ?></span>
                    <div class="ctl-id-txt"><strong><?php echo s($propietario->nombre); ?></strong><span class="ctl-id-sub"><?php echo s($propietario->email); ?></span></div>
                </div>
            <?php else: ?>
                <p class="text-muted text-sm">Sin propietario asignado.</p>
            <?php endif; ?>
            <div class="ctl-resumen-linea"><span>Gestores</span><strong><?php echo count($gestores); ?></strong></div>
        </div>
        <div class="ctl-aside-card">
            <h4><i class="fa-solid fa-chart-simple"></i> Uso de la empresa</h4>
            <div class="ctl-resumen-linea"><span>Obras activas</span><strong><?php echo (int)$obras_activas; ?> / <?php echo $limites['obras'] === null ? '∞' : (int)$limites['obras']; ?></strong></div>
            <div class="ctl-resumen-linea"><span>Asientos</span><strong><?php echo (int)$asientos; ?> / <?php echo $limUsuarios === null ? '∞' : (int)$limUsuarios; ?></strong></div>
            <div class="ctl-resumen-linea"><span>Visitantes</span><strong><?php echo (int)$visitantes; ?> / <?php echo $limVisitantes === null ? '∞' : (int)$limVisitantes; ?></strong></div>
            <div class="ctl-resumen-linea"><span>Almacenamiento</span><strong class="mono"><?php echo \Classes\Almacenamiento::formato((int)$almacenamiento); ?></strong></div>
        </div>
        <div class="ctl-aside-card">
            <h4><i class="fa-solid fa-receipt"></i> Facturación</h4>
            <p class="text-sm"><?php echo s($org->correo_facturacion ?: 'Sin correo de facturación'); ?></p>
            <?php if ($org->telefono): ?><p class="text-sm mono"><?php echo s($org->telefono); ?></p><?php endif; ?>
            <div class="ctl-resumen-linea"><span>Plan</span><strong><?php echo s($plan['nombre']); ?></strong></div>
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
