<?php // Variables: $proyecto, $filtros, $lista, $fichas, $mensaje
use Classes\Informes;
$pid = (int)$proyecto->id;
$tipos = [];
foreach ($fichas as $f) $tipos[$f['tipo']] = $f['titulo'];
$grupos = ['campo' => 'Campo y reportes', 'planeacion' => 'Planeación', 'documentos' => 'Documentos', 'cierre' => 'Entrega y cierre', 'seguridad' => 'Seguridad'];
?>
<?php $pestana = 'informes'; include __DIR__ . '/../centro-ia/_pestanas.php'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-lines"></i> <?php echo s(t('Informes de IA')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · reporte diario, resumen semanal y demás informes. Los números los calcula el sistema; la IA solo redacta. Todo nace como <strong>borrador</strong>.</p>
    </div>
</div>

<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<div class="card prg-seccion">
    <h3>Generar un informe</h3>
    <p class="text-muted text-sm">Aquí se genera <strong>sin IA</strong> (datos reales y texto de plantilla). Para que la IA redacte, usa la app de escritorio: <em>Herramientas IA → Informes</em>.</p>
    <div class="inf-gen">
        <?php foreach ($fichas as $f): ?>
            <form method="post" action="/informes-ia/generar" class="inf-gen-item">
                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="herramienta" value="<?php echo s($f['id']); ?>">
                <strong><?php echo s($f['titulo']); ?></strong>
                <small class="text-muted"><?php echo s($f['descripcion']); ?></small>
                <?php foreach ($f['parametros'] as $p): if (!empty($p['oculto'])) continue; ?>
                    <label><?php echo s($p['etiqueta']); ?>
                        <?php if ($p['tipo'] === 'fecha'): ?><input type="date" name="p[<?php echo s($p['nombre']); ?>]" value="<?php echo s($p['defecto'] ?? ''); ?>">
                        <?php elseif ($p['tipo'] === 'numero'): ?><input type="number" name="p[<?php echo s($p['nombre']); ?>]" value="<?php echo s($p['defecto'] ?? ''); ?>" min="<?php echo s($p['min'] ?? 0); ?>" max="<?php echo s($p['max'] ?? ''); ?>">
                        <?php elseif ($p['tipo'] === 'opcion'): ?><select name="p[<?php echo s($p['nombre']); ?>]"><?php foreach ($p['opciones'] as $k => $n): ?><option value="<?php echo s($k); ?>"<?php echo ($p['defecto'] ?? '') == $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select>
                        <?php elseif (($p['max'] ?? 0) > 500): ?><textarea name="p[<?php echo s($p['nombre']); ?>]" rows="4"><?php echo s($p['defecto'] ?? ''); ?></textarea>
                        <?php else: ?><input type="text" name="p[<?php echo s($p['nombre']); ?>]" value="<?php echo s($p['defecto'] ?? ''); ?>" maxlength="<?php echo (int)($p['max'] ?? 200); ?>"><?php endif; ?>
                    </label>
                <?php endforeach; ?>
                <button class="btn btn-sm btn-primary" type="submit">Generar sin IA</button>
            </form>
        <?php endforeach; ?>
    </div>
</div>

<form method="get" action="/informes-ia" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label>Tipo <select name="tipo" onchange="this.form.submit()"><option value="">Todos</option><?php foreach ($tipos as $k => $n): ?><option value="<?php echo s($k); ?>"<?php echo $filtros['tipo'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Estado <select name="estado" onchange="this.form.submit()"><option value="">Todos</option><?php foreach (Informes::ESTADOS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['estado'] === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
</form>

<?php if (!$lista): ?>
    <div class="card prg-vacio"><h3>Aún no hay informes</h3><p class="text-muted">Genera uno arriba o desde la app de escritorio.</p></div>
<?php else: ?>
    <div class="card prg-tabla-wrap">
        <table class="prg-tabla">
            <thead><tr><th>Informe</th><th>Tipo</th><th>Redacción</th><th>Estado</th><th>Creado</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $x): ?>
                <tr>
                    <td><a class="nombre-archivo" href="/informes-ia/ver?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$x['id']; ?>"><?php echo archivo_icono_html('docx', 'Informe'); ?><strong><?php echo s($x['titulo']); ?></strong></a><?php echo $x['aplicado_en'] ? '<br><small class="text-muted"><i class="fa-solid fa-check"></i> aplicado ' . s(date('d/m/Y', strtotime($x['aplicado_en']))) . '</small>' : ''; ?></td>
                    <td><?php echo s($tipos[$x['tipo']] ?? $x['tipo']); ?></td>
                    <td><?php echo $x['origen'] === 'ia' ? 'IA' . ($x['modelo'] ? '<br><small class="text-muted">' . s($x['modelo']) . '</small>' : '') : 'Automática'; ?></td>
                    <td><span class="hist-ev ev-<?php echo $x['estado'] === 'aprobado' ? 'aplicado' : ($x['estado'] === 'descartado' ? 'rechazado' : 'reabierto'); ?>"><?php echo s(Informes::ESTADOS[$x['estado']]); ?></span></td>
                    <td class="fecha"><?php echo s(date('d/m/Y H:i', strtotime($x['creado']))); ?><br><small class="text-muted"><?php echo s($x['creado_por']); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
