<?php // Variables: $proyecto, $resumen, $requeridos, $climaPermisos, $permisos, $estado, $incidentes, $actividades, $puede, $admin, $mensaje
use Classes\Seguridad;
$pid = (int)$proyecto->id;
$fmt = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$tipos = Seguridad::tipos($pid);
$nTipos = array_map(fn($t) => $t['nombre'], $tipos);
$catalogo = array_map(fn($t) => $t['medidas'], $tipos);
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-helmet-safety"></i> <?php echo s(t('Seguridad y permisos de trabajo')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · permisos de trabajo (altura, caliente, espacios confinados, excavación, eléctrico, izaje) con su lista de medidas y vigencia, y registro de incidentes. El sistema avisa qué actividades próximas exigen permiso y cuáles no lo tienen. <strong>Es un registro de apoyo: no sustituye la firma ni el cumplimiento de la normativa aplicable.</strong></p>
    </div>
    <?php if (is_admin()): ?><div class="inc-acciones"><a href="/informes-ia?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-file-lines"></i> Informes de IA</a></div><?php endif; ?>
</div>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<div class="prg-resumen inc-resumen">
    <div class="card"><small>Permisos vigentes</small><strong><?php echo (int)$resumen['vigentes']; ?></strong></div>
    <div class="card"><small>Por aprobar</small><strong><?php echo (int)$resumen['por_aprobar']; ?></strong></div>
    <div class="card"><small>Vencidos sin cerrar</small><strong class="<?php echo $resumen['vencidos'] ? 'prg-peor' : ''; ?>"><?php echo (int)$resumen['vencidos']; ?></strong></div>
    <div class="card"><small>Actividades (7 días) sin permiso</small><strong class="<?php echo $resumen['faltan'] ? 'prg-peor' : ''; ?>"><?php echo (int)$resumen['faltan']; ?></strong></div>
    <div class="card"><small>Incidentes abiertos</small><strong><?php echo (int)$resumen['incidentes_abiertos']; ?></strong></div>
</div>

<?php if (!empty($climaPermisos)): ?>
<div class="card prg-seccion">
    <h3><i class="fa-solid fa-cloud-showers-heavy"></i> Permisos en días de lluvia o viento (próximos 7 días)</h3>
    <p class="text-muted text-sm">Según el pronóstico del proyecto, estos días no son buenos para trabajos en altura, izaje, excavación, eléctricos o en caliente. Revisa si conviene reprogramar o suspender el permiso.</p>
    <div class="prg-tabla-wrap"><table class="prg-tabla"><thead><tr><th>Fecha</th><th>Pronóstico</th><th>Permisos vigentes ese día</th></tr></thead><tbody>
        <?php foreach ($climaPermisos as $c): ?>
            <tr><td class="fecha"><?php echo s($fmt($c['fecha'])); ?></td><td><?php echo s(ucfirst($c['condicion'])); ?> (<?php echo s($c['precip']); ?> mm · <?php echo s($c['viento']); ?> km/h)</td>
                <td><?php echo s(implode('; ', array_map(fn($p) => ($nTipos[$p['tipo']] ?? $p['tipo']) . ' — ' . mb_substr($p['titulo'], 0, 60) . ($p['estado'] === 'solicitado' ? ' (por aprobar)' : ''), $c['permisos']))); ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>

<div class="card prg-seccion">
    <h3>Permisos que exigen las actividades de los próximos 14 días</h3>
    <?php if (!$requeridos): ?><p class="text-muted">Ninguna actividad próxima parece requerir un permiso especial (se detecta por palabras de su nombre: azotea, soldadura, zanja, tablero, grúa…).</p>
    <?php else: ?><div class="prg-tabla-wrap"><table class="prg-tabla"><thead><tr><th>Actividad</th><th>Inicio</th><th>Permiso requerido</th><th>Situación</th></tr></thead><tbody>
        <?php foreach ($requeridos as $r): $a = $r['actividad']; ?>
            <tr><td><strong><?php echo s($a['codigo']); ?></strong> <?php echo s(mb_substr($a['nombre'], 0, 70)); ?></td><td class="fecha"><?php echo s($fmt($a['fecha_inicio'])); ?></td><td><?php echo s($nTipos[$r['tipo']]); ?></td>
                <td><span class="hist-ev ev-<?php echo $r['estado'] === 'ok' ? 'aplicado' : ($r['estado'] === 'tramite' ? 'reabierto' : 'rechazado'); ?>"><?php echo ['ok' => 'Con permiso aprobado', 'tramite' => 'En trámite', 'falta' => 'Falta el permiso'][$r['estado']]; ?></span>
                    <?php if ($r['estado'] === 'falta' && $puede): ?> <button type="button" class="btn btn-sm btn-ghost" data-nuevo="<?php echo s(json_encode(['tipo' => $r['tipo'], 'actividad' => (int)$a['id'], 'titulo' => $nTipos[$r['tipo']] . ': ' . mb_substr($a['nombre'], 0, 120), 'desde' => $a['fecha_inicio'], 'hasta' => $a['fecha_fin']], JSON_UNESCAPED_UNICODE)); ?>">Crear permiso</button><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<?php if ($puede): ?>
<details class="card prg-seccion" id="nuevo-permiso">
    <summary><strong>Solicitar un permiso de trabajo</strong></summary>
    <form method="post" action="/seguridad/permiso" class="inc-filtros" style="margin-top:1rem">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label>Tipo <select name="tipo" id="p-tipo"><?php foreach ($nTipos as $k => $n): ?><option value="<?php echo s($k); ?>"><?php echo s($n); ?></option><?php endforeach; ?></select></label>
        <label>Nombre de permiso <input type="text" name="nombre_permiso" id="p-nombre" maxlength="150" placeholder="Ej. PT-014 Montaje de andamio torre B"></label>
        <label style="flex:2">Trabajo a realizar <input type="text" name="titulo" id="p-titulo" maxlength="200" required></label>
        <label>Actividad <select name="actividad_id" id="p-act"><option value="0">(ninguna)</option><?php echo opciones_actividades($actividades); ?></select></label>
        <label>Área <input type="text" name="area" maxlength="150"></label>
        <label>Responsable <input type="text" name="responsable" maxlength="150"></label>
        <label>Contratista <input type="text" name="contratista" maxlength="150"></label>
        <label>Desde <input type="date" name="vigente_desde" id="p-desde" value="<?php echo date('Y-m-d'); ?>" required></label>
        <label>Hasta <input type="date" name="vigente_hasta" id="p-hasta" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required></label>
        <label style="flex-basis:100%">Descripción del trabajo <textarea name="descripcion" rows="2" maxlength="3000"></textarea></label>
        <label style="flex-basis:100%">Riesgos identificados <textarea name="riesgos" rows="2" maxlength="3000"></textarea></label>
        <label style="flex-basis:100%">Medidas del tipo elegido <small class="text-muted" id="p-medidas"></small></label>
        <label style="flex-basis:100%">Medidas adicionales (una por línea) <textarea name="medidas_extra" rows="2"></textarea></label>
        <button class="btn btn-primary" type="submit">Solicitar permiso</button>
    </form>
</details>
<script>
    (function () {
        var cat = <?php echo json_encode($catalogo, JSON_UNESCAPED_UNICODE); ?>, t = document.getElementById('p-tipo'), m = document.getElementById('p-medidas');
        function pinta() { m.textContent = ' · ' + (cat[t.value] || []).join(' · '); }
        t.addEventListener('change', pinta); pinta();
        document.querySelectorAll('[data-nuevo]').forEach(function (b) { b.addEventListener('click', function () {
            var d = JSON.parse(b.dataset.nuevo); t.value = d.tipo; pinta(); document.getElementById('p-titulo').value = d.titulo; document.getElementById('p-act').value = d.actividad;
            document.getElementById('p-desde').value = d.desde; document.getElementById('p-hasta').value = d.hasta; var det = document.getElementById('nuevo-permiso'); det.open = true; det.scrollIntoView({ behavior: 'smooth' });
        }); });
    })();
</script>

<details class="card prg-seccion" id="tipos-permiso">
    <summary><strong>Tipos de permiso</strong> <small class="text-muted">· <?php echo count($tipos); ?> disponibles (los fijos no se editan; crea los tuyos)</small></summary>
    <ul class="hist-lista" style="margin-top:1rem">
        <?php foreach ($tipos as $k => $t): ?><li><strong><?php echo s($t['nombre']); ?></strong> <small class="text-muted">· <?php echo empty($t['propio']) ? 'fijo' : 'propio'; ?> · <?php echo count($t['medidas']); ?> medidas<?php echo $t['palabras'] ? ' · detecta: ' . s(implode(', ', array_slice($t['palabras'], 0, 6))) . (count($t['palabras']) > 6 ? '…' : '') : ''; ?></small>
            <?php if (!empty($t['propio'])): ?><form method="post" action="/seguridad/tipo/eliminar" data-confirmar="¿Eliminar este tipo de permiso?" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></li><?php endforeach; ?>
    </ul>
    <form method="post" action="/seguridad/tipo" class="inc-filtros" style="margin-top:1rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label>Nombre del tipo <input type="text" name="nombre" maxlength="120" required placeholder="Ej. Trabajo sobre cuerpo de agua"></label>
        <label style="flex-basis:100%">Palabras que lo detectan en las actividades (una por línea, opcional) <textarea name="palabras" rows="2"></textarea></label>
        <label style="flex-basis:100%">Medidas de seguridad del tipo (una por línea; si queda vacío se usan las generales) <textarea name="medidas" rows="3"></textarea></label>
        <button class="btn btn-primary" type="submit">Crear tipo</button></form>
</details>
<?php endif; ?>

<div class="card prg-seccion">
    <div class="prg-cab"><h3>Permisos</h3>
        <form method="get" action="/seguridad" style="margin:0"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><select name="estado" onchange="this.form.submit()"><option value="">Todos</option><?php foreach (Seguridad::ESTADOS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $estado === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></form></div>
    <?php if (!$permisos): ?><p class="text-muted">No hay permisos con este filtro.</p><?php endif; ?>
    <ul class="inc-lista">
        <?php foreach ($permisos as $x): ?>
            <li class="card inc-item sev-<?php echo $x['vencido'] ? 'critica' : ($x['por_vencer'] ? 'media' : ($x['estado'] === 'aprobado' ? 'baja' : 'media')); ?>">
                <div class="inc-cab"><span class="prg-sev"><?php echo s(Seguridad::ESTADOS[$x['estado']]); ?></span><strong><?php echo s($x['nombre_permiso'] !== '' ? $x['nombre_permiso'] . ' · ' : ''); ?><?php echo s($x['titulo']); ?></strong>
                    <small class="text-muted"><?php echo s($nTipos[$x['tipo']] ?? $x['tipo']); ?> · <?php echo s($fmt($x['vigente_desde'])); ?> al <?php echo s($fmt($x['vigente_hasta'])); ?><?php echo $x['vencido'] ? ' · VENCIDO' : ($x['por_vencer'] ? ' · vence pronto' : ''); ?><?php echo $x['origen'] === 'ia' ? ' · borrador de IA' : ''; ?></small></div>
                <p class="text-sm"><?php echo $x['act_codigo'] ? 'Actividad ' . s($x['act_codigo']) . ' · ' : ''; ?><?php echo s(trim($x['area'] . ' · ' . $x['responsable'] . ' · ' . $x['contratista'], ' ·')); ?></p>
                <form method="post" action="/seguridad/medidas"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>">
                    <details <?php echo in_array($x['estado'], ['solicitado', 'borrador'], true) ? 'open' : ''; ?>><summary><?php echo count(array_filter($x['medidas'], fn($m) => !empty($m['ok']))); ?> de <?php echo count($x['medidas']); ?> medidas verificadas</summary>
                        <ul class="hist-lista"><?php foreach ($x['medidas'] as $i => $m): ?><li><label class="campo-check"><input type="checkbox" name="ok[<?php echo $i; ?>]" value="1" <?php echo !empty($m['ok']) ? 'checked' : ''; ?> <?php echo $puede ? '' : 'disabled'; ?>> <?php echo s($m['texto']); ?></label></li><?php endforeach; ?></ul>
                        <?php if ($puede): ?><button class="btn btn-sm btn-ghost" type="submit">Guardar verificación</button><?php endif; ?></details></form>
                <div class="inc-botones">
                    <a class="btn btn-sm btn-ghost" href="/seguridad/permiso/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$x['id']; ?>"><i class="fa-solid fa-file-pdf"></i> PDF para firmar</a>
                    <?php foreach (['borrador' => ['solicitado', 'Solicitar'], 'solicitado' => ['aprobado', 'Aprobar'], 'aprobado' => ['cerrado', 'Cerrar (trabajo terminado)']] as $desde => [$a, $n]): if ($x['estado'] !== $desde) continue; if ($a === 'aprobado' && !$admin) continue; if (!$puede) continue; ?>
                        <form method="post" action="/seguridad/permiso/estado" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>"><input type="hidden" name="estado" value="<?php echo $a; ?>"><button class="btn btn-sm btn-primary" type="submit"><?php echo $n; ?></button></form>
                    <?php endforeach; ?>
                    <?php if ($x['estado'] === 'solicitado' && $admin): ?><form method="post" action="/seguridad/permiso/estado" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>"><input type="hidden" name="estado" value="rechazado"><button class="btn btn-sm btn-ghost" type="submit">Rechazar</button></form><?php endif; ?>
                    <?php if ($puede && in_array($x['estado'], ['borrador', 'solicitado', 'rechazado'], true)): ?><form method="post" action="/seguridad/permiso/eliminar" data-confirmar="¿Eliminar este permiso?" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>"><button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?>
                    <?php if ($x['resuelto_por']): ?><small class="text-muted"><?php echo s($x['resuelto_por'] . ' · ' . date('d/m/Y H:i', strtotime($x['resuelto_en']))); ?></small><?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="card prg-seccion">
    <h3>Incidentes y condiciones inseguras</h3>
    <?php if ($puede): ?><details><summary>Registrar un incidente</summary>
        <form method="post" action="/seguridad/incidente" class="inc-filtros" style="margin-top:1rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <label>Fecha <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required></label>
            <label>Tipo <select name="tipo"><?php foreach (Seguridad::INCIDENTES as $k => $n): ?><option value="<?php echo $k; ?>"><?php echo s($n); ?></option><?php endforeach; ?></select></label>
            <label>Severidad <select name="severidad"><option value="baja">Baja</option><option value="media" selected>Media</option><option value="alta">Alta</option><option value="critica">Crítica</option></select></label>
            <label>Área <input type="text" name="area" maxlength="150"></label>
            <label>Actividad <select name="actividad_id"><option value="0">(ninguna)</option><?php echo opciones_actividades($actividades); ?></select></label>
            <label style="flex-basis:100%">Qué pasó <textarea name="descripcion" rows="2" required maxlength="3000"></textarea></label>
            <label style="flex-basis:100%">Acciones tomadas <textarea name="acciones" rows="2" maxlength="3000"></textarea></label>
            <button class="btn btn-primary" type="submit">Registrar</button></form></details><?php endif; ?>
    <?php if (!$incidentes): ?><p class="text-muted">Sin incidentes registrados.</p><?php else: ?><div class="prg-tabla-wrap"><table class="prg-tabla"><thead><tr><th>Fecha</th><th>Tipo</th><th>Severidad</th><th>Qué pasó</th><th>Estado</th></tr></thead><tbody>
        <?php foreach ($incidentes as $i): ?><tr><td class="fecha"><?php echo s($fmt($i['fecha'])); ?></td><td><?php echo s(Seguridad::INCIDENTES[$i['tipo']]); ?></td><td><?php echo s(ucfirst($i['severidad'])); ?></td>
            <td><?php echo s(mb_substr($i['descripcion'], 0, 200)); ?><?php echo $i['area'] ? '<br><small class="text-muted">' . s($i['area']) . '</small>' : ''; ?><?php echo $i['acciones'] ? '<br><small class="text-muted">→ ' . s(mb_substr($i['acciones'], 0, 160)) . '</small>' : ''; ?></td>
            <td><?php if ($i['estado'] === 'abierto' && $puede): ?><form method="post" action="/seguridad/incidente/cerrar" style="display:flex;gap:.4rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>"><input type="text" name="acciones" placeholder="Acción de cierre" maxlength="500"><button class="btn btn-sm btn-ghost" type="submit">Cerrar</button></form><?php else: echo s(ucfirst($i['estado'])); endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
