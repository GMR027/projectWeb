<?php // Variables: $proyecto, $filtros, $filas, $total, $pagina, $paginas, $usuarios
use Classes\Auditoria;
$pid = (int)$proyecto->id;
$fmt = fn($d) => date('d/m/Y H:i', strtotime($d));
$enlaceEntidad = function (array $x) use ($pid) {
    return match ($x['entidad_tipo']) {
        'actividad' => '/programa/actividades/editar?id=' . (int)$x['entidad_id'],
        'material' => '/materiales/editar?id=' . (int)$x['entidad_id'],
        'partida' => '/programa?proyecto=' . $pid . '&partida=' . (int)$x['entidad_id'],
        'submittal' => '/submittals/ver?id=' . (int)$x['entidad_id'],
        default => null,
    };
};
$q = fn(array $c) => http_build_query(array_filter(['proyecto' => $pid] + $c + $filtros, fn($v) => $v !== '' && $v !== null));
?>
<?php $pestana = 'historial'; include __DIR__ . '/../centro-ia/_pestanas.php'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-clock-rotate-left"></i> <?php echo s(t('Historial de cambios')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · cada cambio que aplican, revierten o rechazan las herramientas de IA queda registrado aquí (quién, cuándo, qué modelo y los valores de antes y después). <strong>El registro no se puede editar.</strong></p>
    </div>
    <div class="inc-acciones">
        <a class="btn btn-ghost" href="/historial-cambios/exportar?<?php echo s($q(['formato' => 'xlsx'])); ?>"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a class="btn btn-ghost" href="/historial-cambios/exportar?<?php echo s($q(['formato' => 'pdf'])); ?>"><i class="fa-solid fa-file-pdf"></i> PDF</a>
    </div>
</div>

<form method="get" action="/historial-cambios" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <?php if ($filtros['entidad_tipo'] !== ''): ?><input type="hidden" name="entidad_tipo" value="<?php echo s($filtros['entidad_tipo']); ?>"><input type="hidden" name="entidad_id" value="<?php echo s($filtros['entidad_id']); ?>"><?php endif; ?>
    <label>Herramienta <select name="herramienta" onchange="this.form.submit()"><option value="">Todas</option><?php foreach (Auditoria::HERRAMIENTAS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['herramienta'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Evento <select name="evento" onchange="this.form.submit()"><option value="">Todos</option><?php foreach (Auditoria::EVENTOS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['evento'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Usuario <select name="usuario" onchange="this.form.submit()"><option value="">Todos</option><?php foreach ($usuarios as $u): ?><option<?php echo $filtros['usuario'] === $u ? ' selected' : ''; ?>><?php echo s($u); ?></option><?php endforeach; ?></select></label>
    <label>Desde <input type="date" name="desde" value="<?php echo s($filtros['desde']); ?>" onchange="this.form.submit()"></label>
    <label>Hasta <input type="date" name="hasta" value="<?php echo s($filtros['hasta']); ?>" onchange="this.form.submit()"></label>
    <label>Buscar <input type="search" name="q" value="<?php echo s($filtros['q']); ?>" placeholder="Resumen o entidad" autocomplete="off"></label>
    <button class="btn btn-sm btn-ghost" type="submit">Filtrar</button>
    <?php if ($filtros['entidad_tipo'] !== ''): ?><a class="btn btn-sm btn-ghost" href="/historial-cambios?proyecto=<?php echo $pid; ?>">Quitar filtro de «<?php echo s($filtros['entidad_tipo']); ?>»</a><?php endif; ?>
</form>

<p class="text-muted"><?php echo (int)$total; ?> registro(s)<?php echo $paginas > 1 ? " · página $pagina de $paginas" : ''; ?></p>

<?php if (!$filas): ?>
    <div class="card prg-vacio"><h3>Sin registros</h3><p class="text-muted">Aquí aparecerán los cambios que apliques o reviertas desde las herramientas de IA.</p></div>
<?php else: ?>
    <div class="card prg-tabla-wrap">
        <table class="prg-tabla hist-tabla">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Herramienta</th><th>Evento</th><th>Entidad</th><th>Resumen</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $x): $link = $enlaceEntidad($x); ?>
                <tr>
                    <td class="fecha"><?php echo s($fmt($x['creado'])); ?></td>
                    <td><?php echo s($x['usuario'] ?: '—'); ?></td>
                    <td><?php echo s(Auditoria::HERRAMIENTAS[$x['herramienta']] ?? $x['herramienta']); ?><?php echo $x['modelo'] ? '<br><small class="text-muted">' . s($x['modelo']) . '</small>' : ''; ?></td>
                    <td><span class="hist-ev ev-<?php echo s($x['evento']); ?>"><?php echo s(Auditoria::EVENTOS[$x['evento']] ?? $x['evento']); ?></span></td>
                    <td><?php if ($x['entidad_tipo'] !== ''): ?><small class="text-muted"><?php echo s($x['entidad_tipo']); ?></small><br><?php echo $link ? '<a href="' . s($link) . '">' . s($x['entidad_nombre']) . '</a>' : s($x['entidad_nombre']); ?> <a class="text-sm" title="Historial de esta entidad" href="/historial-cambios?proyecto=<?php echo $pid; ?>&entidad_tipo=<?php echo s($x['entidad_tipo']); ?>&entidad_id=<?php echo (int)$x['entidad_id']; ?>"><i class="fa-solid fa-clock-rotate-left"></i></a><?php else: ?>—<?php endif; ?></td>
                    <td>
                        <?php echo s($x['resumen']); ?>
                        <?php if ($x['antes'] !== null || $x['despues'] !== null): ?>
                            <details class="hist-det"><summary>Antes / después</summary>
                                <div class="hist-par"><div><b>Antes</b><pre><?php echo s($x['antes'] !== null ? json_encode($x['antes'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '—'); ?></pre></div>
                                <div><b>Después</b><pre><?php echo s($x['despues'] !== null ? json_encode($x['despues'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '—'); ?></pre></div></div>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginas > 1): ?>
        <nav class="hist-pag">
            <?php if ($pagina > 1): ?><a class="btn btn-sm btn-ghost" href="/historial-cambios?<?php echo s($q(['pagina' => $pagina - 1])); ?>">← Anterior</a><?php endif; ?>
            <span>Página <?php echo $pagina; ?> de <?php echo $paginas; ?></span>
            <?php if ($pagina < $paginas): ?><a class="btn btn-sm btn-ghost" href="/historial-cambios?<?php echo s($q(['pagina' => $pagina + 1])); ?>">Siguiente →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
