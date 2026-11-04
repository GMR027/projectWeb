<?php
/** Incidentes con investigación y acciones. Variables: $proyecto, $pid, $incidentes, $actividades, $puede, $fmt */
use Classes\Seguridad;
?>
<?php if ($puede): ?>
<details class="card prg-seccion"><summary><strong>Registrar un incidente</strong> <small class="text-muted">· investigación y acciones</small></summary>
<form method="post" action="/seguridad/incidente" class="co-form" style="margin-top:1rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <div class="co-campos">
        <label>Fecha<input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required></label>
        <label>Tipo<select name="tipo"><?php foreach (Seguridad::INCIDENTES as $k => $n): ?><option value="<?php echo $k; ?>"><?php echo s($n); ?></option><?php endforeach; ?></select></label>
        <label>Severidad<select name="severidad"><option value="baja">Baja</option><option value="media" selected>Media</option><option value="alta">Alta</option><option value="critica">Crítica</option></select></label>
        <label>Área<input type="text" name="area" maxlength="150"></label>
        <label>Actividad<select name="actividad_id"><option value="0">(ninguna)</option><?php echo opciones_actividades($actividades); ?></select></label>
        <label>Días perdidos<input type="number" name="dias_perdidos" min="0" value="0"></label>
        <label>Costo<input type="text" inputmode="decimal" name="costo" placeholder="0.00"></label>
        <label>Investigado por<input type="text" name="investigado_por" maxlength="150"></label>
        <label class="co-ancho">Qué pasó *<textarea name="descripcion" rows="2" required maxlength="3000"></textarea></label>
        <label class="co-ancho">Acciones inmediatas<textarea name="acciones" rows="2" maxlength="3000"></textarea></label>
        <label>Causa inmediata<textarea name="causa_inmediata" rows="2" maxlength="3000"></textarea></label>
        <label>Causa raíz<textarea name="causa_raiz" rows="2" maxlength="3000"></textarea></label>
        <label class="co-ancho">Acciones correctivas<textarea name="acciones_correctivas" rows="2" maxlength="3000"></textarea></label>
    </div>
    <button class="btn btn-primary" type="submit">Registrar incidente</button>
</form>
</details>
<?php endif; ?>

<div class="card prg-seccion">
    <h3><i class="fa-solid fa-triangle-exclamation"></i> Incidentes y condiciones inseguras</h3>
    <?php if (!$incidentes): ?><p class="text-muted">Sin incidentes registrados.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Fecha</th><th>Tipo / severidad</th><th>Qué pasó / causa</th><th class="num">Días</th><th class="num">Costo</th><th>Estado</th><th></th></tr></thead><tbody>
        <?php foreach ($incidentes as $i): $acciones = Seguridad::accionesIncidente((int)$i['id']); ?>
            <tr><td class="mono"><?php echo s($fmt($i['fecha'])); ?></td><td><?php echo s(Seguridad::INCIDENTES[$i['tipo']]); ?><br><small class="text-muted"><?php echo s(ucfirst($i['severidad'])); ?></small></td>
                <td><?php echo s(mb_substr((string)$i['descripcion'], 0, 180)); ?><?php if ($i['causa_raiz']): ?><br><small class="text-muted"><b>Causa raíz:</b> <?php echo s(mb_substr((string)$i['causa_raiz'], 0, 160)); ?></small><?php endif; ?><?php if ($i['area']): ?><br><small class="text-muted"><?php echo s($i['area']); ?></small><?php endif; ?></td>
                <td class="num mono"><?php echo (int)$i['dias_perdidos']; ?></td><td class="num mono">$<?php echo number_format((float)$i['costo'], 2); ?></td><td><span class="badge badge-<?php echo $i['estado'] === 'cerrado' ? 'cliente' : 'admin'; ?>"><?php echo s(ucfirst($i['estado'])); ?></span></td>
                <td><details><summary class="text-sm">Abrir</summary>
                    <div class="text-sm" style="min-width:32rem">
                        <?php if ($i['acciones_correctivas']): ?><p><b>Acciones correctivas:</b> <?php echo nl2br(s($i['acciones_correctivas'])); ?></p><?php endif; ?>
                        <?php if ($acciones): ?><ul><?php foreach ($acciones as $a): ?><li><?php echo $a['fecha'] ? s($fmt($a['fecha'])) . ' · ' : ''; ?><?php echo s($a['accion']); ?><?php echo $a['verificada'] ? ' ✔' : ''; ?></li><?php endforeach; ?></ul><?php endif; ?>
                        <?php if ($puede && $i['estado'] === 'abierto'): ?>
                            <form method="post" action="/seguridad/incidente/accion" class="co-campos" style="margin-top:.5rem">
                                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>">
                                <label>Fecha<input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>"></label>
                                <label>Responsable<input type="text" name="responsable" maxlength="150"></label>
                                <label class="co-ancho">Acción<textarea name="accion" rows="2" required maxlength="5000"></textarea></label>
                                <label class="co-check"><input type="checkbox" name="verificada" value="1"> Verificada</label>
                                <button class="btn btn-xs btn-secondary" type="submit">Agregar acción</button>
                            </form>
                            <form method="post" action="/seguridad/incidente/cerrar" class="co-campos" style="margin-top:.5rem">
                                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>">
                                <label>Días perdidos<input type="number" name="dias_perdidos" min="0" value="<?php echo (int)$i['dias_perdidos']; ?>"></label>
                                <label>Costo<input type="text" inputmode="decimal" name="costo" value="<?php echo s(number_format((float)$i['costo'], 2, '.', '')); ?>"></label>
                                <label class="co-ancho">Acción de cierre<textarea name="acciones" rows="2" maxlength="1000"></textarea></label>
                                <button class="btn btn-xs btn-primary" type="submit">Cerrar incidente</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </details></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>
