<?php
/** Acuse público. Variables: $r, $token, $ok, $error */
$estados = ['recibido' => 'Recibido conforme', 'observado' => 'Recibido con observaciones', 'rechazado' => 'No recibido'];
?>
<div class="card" style="max-width:78rem;margin:2rem auto">
    <h2 style="margin-top:0"><i class="fa-solid fa-paper-plane"></i> Acuse de recibo</h2>
    <?php if (!$r): ?>
        <div class="alerta alerta-error"><?php echo s($error ?: 'El enlace de acuse no es válido.'); ?></div>
    <?php else: ?>
        <p><strong><?php echo s($r['numero']); ?></strong> · <?php echo s($r['asunto']); ?></p>
        <p class="text-muted text-sm"><?php echo s($r['proyecto']); ?> · Enviado el <?php echo s(formatear_fecha($r['fecha'], 'd/m/Y')); ?> por <?php echo s($r['nombre']); ?></p>
        <?php if ($r['fecha_limite']): ?><p class="text-muted text-sm">Fecha límite para acusar: <strong><?php echo s(formatear_fecha($r['fecha_limite'], 'd/m/Y')); ?></strong></p><?php endif; ?>
        <?php if (trim((string)$r['mensaje']) !== ''): ?><div class="alerta alerta-info"><?php echo nl2br(s($r['mensaje'])); ?></div><?php endif; ?>

        <h3>Documentos</h3>
        <table class="tabla"><thead><tr><th>Documento</th><th>Revisión</th></tr></thead><tbody>
            <?php foreach ($r['documentos'] as $d): ?><tr><td><?php echo s($d['nombre']); ?></td><td class="mono"><?php echo s($d['revision'] ?: '—'); ?></td></tr><?php endforeach; ?>
        </tbody></table>

        <?php if ($r['estado'] === 'pendiente' && $ok === ''): ?>
            <?php if ($error !== ''): ?><div class="alerta alerta-error"><?php echo s($error); ?></div><?php endif; ?>
            <form method="POST" action="/transmittals/acuse" class="co-form" style="margin-top:1rem;border:0;padding:0">
                <input type="hidden" name="t" value="<?php echo s($token); ?>">
                <div class="co-campos">
                    <label>Tu nombre *<input type="text" name="nombre" maxlength="150" required value="<?php echo s($r['nombre']); ?>"></label>
                    <label>Resultado *<select name="estado" required><option value="">— Elige —</option><?php foreach ($estados as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo s($v); ?></option><?php endforeach; ?></select></label>
                    <label class="co-ancho">Observaciones<textarea name="comentario" rows="4" maxlength="5000" placeholder="Si recibes con observaciones, escríbelas aquí"></textarea></label>
                </div>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-signature"></i> Firmar acuse</button>
                <p class="text-muted text-sm">Al firmar se guarda tu nombre, la fecha, la hora y la IP como evidencia de recibo.</p>
            </form>
        <?php else: ?>
            <?php if ($ok !== ''): ?><div class="alerta alerta-exito"><?php echo s($ok); ?></div><?php endif; ?>
            <?php if ($r['estado'] !== 'pendiente'): ?>
                <div class="alerta alerta-info"><strong>Acuse registrado:</strong> <?php echo s($estados[$r['estado']] ?? $r['estado']); ?>
                    <?php if ($r['acuse_fecha']): ?> · <?php echo s(formatear_fecha(substr((string)$r['acuse_fecha'], 0, 10), 'd/m/Y')); ?><?php endif; ?>
                    <?php if ($r['acuse_nombre']): ?> · <?php echo s($r['acuse_nombre']); ?><?php endif; ?>
                </div>
                <?php if (trim((string)$r['acuse_comentario']) !== ''): ?><p><strong>Observaciones:</strong> <?php echo nl2br(s($r['acuse_comentario'])); ?></p><?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
