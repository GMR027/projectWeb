<?php
/** Enlaces de solo lectura. Variables: $proyecto, $disponible, $clientes, $calendarios, $error, $ok */
use Classes\Enlaces;

$pid = (int)$proyecto->id;
$estado = fn($e) => (int)$e['revocado'] ? 'Revocado' : ((int)$e['caducado'] ? 'Caducó' : 'Activo');
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-share-nodes"></i> Compartir en solo lectura</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · enlaces sin usuario ni contraseña: cualquiera que tenga el enlace lo ve; revócalo cuando ya no haga falta</p>
    </div>
</div>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_fase3.sql</code> para compartir enlaces.</p></div>
<?php return; endif; ?>
<?php if ($error !== ''): ?><div class="alerta alerta-error"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito">Listo.</div><?php endif; ?>

<?php
$tabla = function (array $lista, string $vacio) use ($pid, $estado) { ?>
    <?php if (!$lista): ?><p class="text-muted"><?php echo s($vacio); ?></p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Nombre</th><th>Enlace</th><th class="hide-m">Vence</th><th class="hide-m">Accesos</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $e): $u = Enlaces::url($e); ?>
            <tr>
                <td><?php echo s($e['nombre'] ?: '—'); ?><br><small class="text-muted"><?php echo s($e['creado_por']); ?> · <?php echo formatear_fecha(substr($e['creado'], 0, 10)); ?></small></td>
                <td><?php if ($e['activo']): ?>
                    <div class="co-enlace"><input type="text" readonly value="<?php echo s($u); ?>" onfocus="this.select()" aria-label="Enlace"><button type="button" class="btn btn-sm btn-ghost" data-copiar="<?php echo s($u); ?>"><i class="fa-regular fa-copy"></i> Copiar</button><?php if ($e['tipo'] === 'ics'): ?><a class="btn btn-sm btn-ghost" href="<?php echo s(preg_replace('#^https?://#', 'webcal://', $u)); ?>" title="Abre la app de calendario del teléfono o la computadora"><i class="fa-solid fa-calendar-plus"></i> Suscribir</a><?php endif; ?></div>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                <td class="mono hide-m"><?php echo $e['expira'] ? formatear_fecha(substr($e['expira'], 0, 10)) : 'No vence'; ?></td>
                <td class="mono hide-m"><?php echo (int)$e['accesos']; ?><?php echo $e['ultimo_acceso'] ? '<br><small class="text-muted">' . formatear_fecha(substr($e['ultimo_acceso'], 0, 10)) . '</small>' : ''; ?></td>
                <td><span class="cb-tag<?php echo $e['activo'] ? '' : ' cb-tag-critica'; ?>"><?php echo $estado($e); ?></span></td>
                <td class="acciones"><?php if ($e['activo']): ?>
                    <form method="POST" action="/compartir/revocar" style="display:inline" data-confirm="¿Revocar este enlace?|Quien lo tenga dejará de ver el proyecto.">
                        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$e['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">Revocar</button>
                    </form><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
<?php }; ?>

<section class="card">
    <h3><i class="fa-solid fa-user-tie"></i> Enlace para el cliente</h3>
    <p class="text-muted text-sm">Una página con el avance, las fotos y los reportes que elijas, sin crear usuario. No muestra costos, personal ni documentos. Caduca solo.</p>
    <form method="POST" action="/compartir/crear" class="co-form">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="tipo" value="cliente">
        <div class="co-campos">
            <label>Para quién (nota interna)<input type="text" name="nombre" maxlength="150" placeholder="Dirección del cliente, supervisión…"></label>
            <label>Dura<select name="dias"><?php foreach (Enlaces::DIAS as $d => $etq): ?><option value="<?php echo $d; ?>"<?php echo $d === 15 ? ' selected' : ''; ?>><?php echo $etq; ?></option><?php endforeach; ?></select></label>
        </div>
        <p class="co-checks"><?php foreach (Enlaces::SECCIONES as $k => $etq): ?><label class="co-check"><input type="checkbox" name="secciones[]" value="<?php echo $k; ?>" checked> <?php echo s($etq); ?></label><?php endforeach; ?></p>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-link"></i> Crear enlace</button>
    </form>
    <?php $tabla($clientes, 'Aún no has creado enlaces para el cliente.'); ?>
</section>

<section class="card" id="calendario">
    <h3><i class="fa-solid fa-calendar-plus"></i> Calendario para el celular (.ics)</h3>
    <p class="text-muted text-sm">Suscríbete desde Google Calendar, el calendario del iPhone u Outlook con «Agregar calendario desde URL». Trae las actividades pendientes de la semana pasada a las próximas 4 semanas, todos los hitos y la fecha límite de los RFI abiertos, y se actualiza solo. No caduca: se corta revocándolo.</p>
    <form method="POST" action="/compartir/crear" class="co-form">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="tipo" value="ics">
        <div class="co-campos"><label>Para quién (nota interna)<input type="text" name="nombre" maxlength="150" placeholder="Mi celular, residente…"></label></div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-link"></i> Crear suscripción</button>
    </form>
    <?php $tabla($calendarios, 'Aún no hay suscripciones.'); ?>
</section>
<script>
document.querySelectorAll('[data-copiar]').forEach(b => b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.copiar); const t = b.innerHTML; b.textContent = 'Copiado'; setTimeout(() => b.innerHTML = t, 1500); }
    catch (_) { b.previousElementSibling.select(); }
}));
</script>
