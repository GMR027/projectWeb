<?php // Variables: $proyecto, $filtros, $items, $conteo
use Classes\CentroIA;
$pid = (int)$proyecto->id;
$sevNombre = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$accionNombre = ['nota' => 'Sin cambio de datos', 'ajustar_fechas' => 'Cambia fechas', 'sincronizar_status' => 'Cambia estatus', 'ajustar_progreso' => 'Cambia avance', 'recalcular_partida' => 'Recalcula partida', 'marcar_comprado' => 'Marca material comprado',
    'resolver_restriccion' => 'Resuelve restricción', 'quitar_dependencia' => 'Quita relación', 'ajustar_duracion' => 'Cambia duración', 'renombrar_actividad' => 'Renombra la actividad', 'editar_descripcion' => 'Edita la descripción', 'agregar_dia_no_habil' => 'Marca día no hábil', 'agregar_dependencia' => 'Agrega una relación', 'renombrar_material' => 'Renombra el material', 'sincronizar_msr_material' => 'Actualiza fechas del MSR', 'sincronizar_msr_submittal' => 'Actualiza estatus del MSR',
    'sincronizar_instalacion' => 'Recalcula instalación del MSR', 'aceptar_partida' => 'Cambia el programa', 'vincular_normativa' => 'Vincula normativa'];
$url = fn(array $c) => '/propuestas-ia?' . http_build_query(array_filter(['proyecto' => $pid] + $c + $filtros, fn($v) => $v !== ''));
$total = array_sum($conteo);
?>
<?php $pestana = 'propuestas'; include __DIR__ . '/../centro-ia/_pestanas.php'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-inbox"></i> <?php echo s(t('Propuestas de IA')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · todo lo que las herramientas de IA proponen y sigue pendiente, en un solo lugar. <strong>Nada se aplica sin que lo aceptes.</strong></p>
    </div>
</div>

<div class="prg-resumen inc-resumen">
    <a class="card" href="<?php echo s($url(['herramienta' => ''])); ?>"><small>Pendientes en total</small><strong><?php echo (int)$total; ?></strong></a>
    <?php foreach (CentroIA::HERRAMIENTAS as $k => $n): ?>
        <a class="card" href="<?php echo s($url(['herramienta' => $k])); ?>"><small><?php echo s($n); ?></small><strong><?php echo (int)$conteo[$k]; ?></strong></a>
    <?php endforeach; ?>
</div>

<form method="get" action="/propuestas-ia" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label>Herramienta <select name="herramienta" onchange="this.form.submit()"><option value="">Todas</option><?php foreach (CentroIA::HERRAMIENTAS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['herramienta'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Severidad <select name="severidad" onchange="this.form.submit()"><option value="">Todas</option><?php foreach ($sevNombre as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['severidad'] === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
    <label>Detectada por <select name="origen" onchange="this.form.submit()"><option value="">Todas</option><option value="sistema"<?php echo $filtros['origen'] === 'sistema' ? ' selected' : ''; ?>>Sistema</option><option value="ia"<?php echo $filtros['origen'] === 'ia' ? ' selected' : ''; ?>>IA</option></select></label>
    <input type="search" data-filtra=".inc-lista" placeholder="Buscar…" aria-label="Buscar propuestas" autocomplete="off">
</form>

<?php if (!$items): ?>
    <div class="card prg-vacio">
        <h3><?php echo $total ? 'Nada con estos filtros' : 'No hay propuestas pendientes'; ?></h3>
        <p class="text-muted">Las propuestas aparecen aquí cuando ejecutas un análisis en la app de escritorio (Herramientas IA) o pulsas «Revisar ahora» en Inconsistencias o en el Análisis de materiales.</p>
    </div>
<?php else: ?>
    <ul class="inc-lista">
        <?php foreach ($items as $x): ?>
            <li class="card inc-item sev-<?php echo s($x['severidad']); ?> est-pendiente" data-herramienta="<?php echo s($x['herramienta']); ?>" data-id="<?php echo (int)$x['id']; ?>">
                <div class="inc-cab">
                    <span class="prg-sev"><?php echo s($sevNombre[$x['severidad']]); ?></span>
                    <strong><?php echo s($x['titulo']); ?></strong>
                    <small class="text-muted"><?php echo s(CentroIA::HERRAMIENTAS[$x['herramienta']]); ?> · <?php echo s($x['categoria']); ?> · <?php echo $x['origen'] === 'ia' ? 'detectada por la IA' : 'detectada por el sistema'; ?></small>
                </div>
                <p><?php echo s($x['descripcion']); ?></p>
                <div class="inc-solucion">
                    <span class="inc-tag"><?php echo s($accionNombre[$x['accion']] ?? $x['accion']); ?></span>
                    <span><strong>Solución propuesta:</strong> <?php echo s($x['solucion']); ?></span>
                </div>
                <div class="inc-botones">
                    <?php if ($x['resoluble']): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-accion="aceptar"><i class="fa-solid fa-check"></i> <?php echo $x['accion'] === 'nota' ? 'Marcar como atendida' : 'Aceptar y aplicar'; ?></button>
                        <button type="button" class="btn btn-sm btn-ghost" data-accion="rechazar"><i class="fa-solid fa-xmark"></i> Rechazar</button>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-ghost" href="<?php echo s($x['url']); ?>"><?php echo $x['resoluble'] ? 'Ver en su herramienta' : 'Abrir y revisar'; ?> →</a>
                    <span class="inc-msg" aria-live="polite"></span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <script>
        document.querySelector('.inc-lista').addEventListener('click', function (e) {
            var b = e.target.closest('button[data-accion]');
            if (!b) return;
            var li = b.closest('.inc-item'), msg = li.querySelector('.inc-msg'), accion = b.dataset.accion;
            if (accion === 'aceptar' && !li.querySelector('.inc-tag').textContent.startsWith('Sin cambio') && !confirm('Se modificarán datos del proyecto con esta solución. ¿Aceptar?')) return;
            var d = new FormData();
            d.append('proyecto', '<?php echo $pid; ?>'); d.append('herramienta', li.dataset.herramienta); d.append('id', li.dataset.id);
            li.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
            fetch('/propuestas-ia/' + accion, { method: 'POST', body: d, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    msg.textContent = j.mensaje || '';
                    if (j.success) { li.classList.add('hecha'); setTimeout(function () { li.remove(); }, 1400); }
                    else li.querySelectorAll('button').forEach(function (x) { x.disabled = false; });
                })
                .catch(function () { msg.textContent = 'Error de conexión'; li.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); });
        });
    </script>
<?php endif; ?>
