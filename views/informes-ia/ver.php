<?php // Variables: $proyecto, $inf, $mensaje
use Classes\Informes;
$pid = (int)$proyecto->id;
$aplicar = $inf['contenido']['aplicar'] ?? null;
$destino = ['reporte_diario' => 'Crear el reporte diario', 'minuta' => 'Crear la minuta', 'bitacora' => 'Crear la entrada de bitácora', 'punch' => 'Crear los pendientes del punch list'][$aplicar['tipo'] ?? ''] ?? null;
?>
<?php $pestana = 'informes'; include __DIR__ . '/../centro-ia/_pestanas.php'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-lines"></i> <?php echo s($inf['titulo']); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo $inf['origen'] === 'ia' ? 'redactado con IA' . ($inf['modelo'] ? ' (' . s($inf['modelo']) . ')' : '') . ': revísalo antes de usarlo' : 'generado automáticamente, sin IA'; ?> · <?php echo s(date('d/m/Y H:i', strtotime($inf['creado']))); ?></p>
    </div>
    <div class="inc-acciones">
        <a class="btn btn-ghost" href="/informes-ia/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$inf['id']; ?>"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        <a href="/informes-ia?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Informes</a>
    </div>
</div>

<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<div class="card inf-barra">
    <span>Estado: <strong><?php echo s(Informes::ESTADOS[$inf['estado']]); ?></strong><?php echo $inf['aplicado_en'] ? ' · aplicado el ' . s(date('d/m/Y H:i', strtotime($inf['aplicado_en']))) . ' (' . s($inf['aplicado_ref']) . ')' : ''; ?></span>
    <?php if ($destino && !$inf['aplicado_en']): ?>
        <form method="post" action="/informes-ia/aplicar" onsubmit="return confirm('<?php echo s($destino); ?> con el contenido de este informe?')"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$inf['id']; ?>"><button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-check"></i> <?php echo s($destino); ?></button></form>
    <?php endif; ?>
    <?php foreach (['aprobado' => 'Aprobar', 'descartado' => 'Descartar', 'borrador' => 'Volver a borrador'] as $e => $n): if ($e === $inf['estado']) continue; ?>
        <form method="post" action="/informes-ia/estado"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$inf['id']; ?>"><input type="hidden" name="estado" value="<?php echo $e; ?>"><button class="btn btn-sm btn-ghost" type="submit"><?php echo $n; ?></button></form>
    <?php endforeach; ?>
    <form method="post" action="/informes-ia/eliminar" onsubmit="return confirm('¿Eliminar este informe?')"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$inf['id']; ?>"><button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i></button></form>
</div>

<div class="card inf-doc"><?php echo Informes::html($inf['contenido']); ?></div>
