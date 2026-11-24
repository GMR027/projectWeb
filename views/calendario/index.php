<?php
$puedeGestionar = puede_gestionar_proyecto($proyecto);
$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$mes = (int)date('n', strtotime($mesActual . '-01'));
$anio = date('Y', strtotime($mesActual . '-01'));
$mesAnterior = date('Y-m', strtotime($mesActual . '-01 -1 month'));
$mesSiguiente = date('Y-m', strtotime($mesActual . '-01 +1 month'));
$mesHoy = date('Y-m');

$primerDia = new DateTime($mesActual . '-01');
$ultimoDia = clone $primerDia;
$ultimoDia->modify('last day of this month');
$diasEnMes = (int)$ultimoDia->format('d');
$primerDiaSemana = (int)$primerDia->format('N');

$actividadesPorDia = [];
foreach ($actividades as $item) {
    foreach ($item['dias_en_mes'] as $dia) $actividadesPorDia[$dia][] = $item;
}
$festivosMap = [];
foreach ($festivos as $f) $festivosMap[$f->fecha] = $f;

$diasHabiles = 0;
$nFestivosMes = 0;
for ($dia = 1; $dia <= $diasEnMes; $dia++) {
    $fecha = $mesActual . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
    if ($calendario->esHabil($fecha)) $diasHabiles++;
    if (isset($festivosMap[$fecha])) $nFestivosMes++;
}
$nActividades = count(array_filter($actividades, fn($i) => ($i['tipo'] ?? '') === 'actividad'));
$nSubactividades = count($actividades) - $nActividades;
$nCompletadas = count(array_filter($actividades, fn($i) => $i['actividad']->status === 'completada'));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-calendar-days"></i> Calendario del Proyecto</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s($proyecto->codigo); ?> · <?php echo $meses[$mes]; ?> <?php echo $anio; ?></p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-briefcase"></i><b><?php echo $diasHabiles; ?></b> días hábiles</span>
            <span class="herr-chip<?php echo $nFestivosMes ? '' : ' herr-chip-suave'; ?>"><i class="fa-solid fa-ban"></i><b><?php echo $nFestivosMes; ?></b> festivo<?php echo $nFestivosMes === 1 ? '' : 's'; ?></span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-list-check"></i><b><?php echo $nActividades; ?></b> actividades<?php echo $nSubactividades ? ' · ' . $nSubactividades . ' sub' : ''; ?></span>
        </div>
    </div>
    <div class="la-controles">
        <?php if ($puedeGestionar && !is_visitante()): ?><a href="/compartir?proyecto=<?php echo s($proyecto->id); ?>#calendario" class="btn btn-ghost" title="Suscribe tu celular al calendario del proyecto (.ics)"><i class="fa-solid fa-calendar-plus"></i> Suscribir (.ics)</a><?php endif; ?>
        <a href="/ajustes?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-gear"></i> Ajustes</a>
        <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Programa</a>
    </div>
</div>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Días hábiles</small><strong><?php echo $diasHabiles; ?></strong><span>en <?php echo $meses[$mes]; ?> según la jornada del proyecto</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Actividades del mes</small><strong><?php echo $nActividades; ?></strong><span><?php echo $nSubactividades; ?> subactividad<?php echo $nSubactividades === 1 ? '' : 'es'; ?> incluidas</span></span>
    </div>
    <div class="suite-kpi <?php echo $nCompletadas === $nActividades && $nActividades ? 'is-success' : ''; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Completadas</small><strong><?php echo $nCompletadas; ?></strong><span>de <?php echo $nActividades; ?> actividad<?php echo $nActividades === 1 ? '' : 'es'; ?></span></span>
    </div>
    <div class="suite-kpi <?php echo $nFestivosMes ? 'is-warning' : ''; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-ban" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Festivos</small><strong><?php echo $nFestivosMes; ?></strong><span>días no laborables en el mes</span></span>
    </div>
</div>

<div class="cal-toolbar">
    <div class="cal-toolbar-mes"><i class="fa-regular fa-calendar"></i> <?php echo $meses[$mes]; ?> <small><?php echo $anio; ?></small></div>
    <div class="cal-toolbar-grupo">
        <form method="get" action="/calendario" class="m-0">
            <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
            <input type="hidden" name="mes" value="<?php echo s($mesActual); ?>">
            <input type="hidden" name="sub" value="0">
            <label class="campo-check"><input type="checkbox" name="sub" value="1"<?php echo !empty($mostrarSub) ? ' checked' : ''; ?> onchange="this.form.submit()"> Subactividades</label>
        </form>
        <a href="/calendario?proyecto=<?php echo s($proyecto->id); ?>&mes=<?php echo s($mesAnterior); ?>" class="btn btn-sm btn-ghost" aria-label="Mes anterior"><i class="fa-solid fa-chevron-left"></i> Anterior</a>
        <?php if ($mesActual !== $mesHoy): ?><a href="/calendario?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-secondary">Hoy</a><?php endif; ?>
        <a href="/calendario?proyecto=<?php echo s($proyecto->id); ?>&mes=<?php echo s($mesSiguiente); ?>" class="btn btn-sm btn-ghost" aria-label="Mes siguiente">Siguiente <i class="fa-solid fa-chevron-right"></i></a>
        <?php if ($puedeGestionar): ?>
            <button type="button" class="btn btn-sm btn-primary" id="cal-crear-actividad" data-url="/programa/actividades/crear?proyecto=<?php echo s($proyecto->id); ?>&modal=1"><i class="fa-solid fa-plus"></i> Crear actividad</button>
        <?php endif; ?>
    </div>
</div>

<div class="suite-panel">
    <div class="cal-panel-cab">
        <h3><i class="fa-regular fa-calendar"></i> <?php echo $meses[$mes]; ?> <?php echo $anio; ?></h3>
        <div class="cal-leyenda">
            <span class="is-no"><i></i> No iniciada</span>
            <span class="is-proceso"><i></i> En proceso</span>
            <span class="is-hecha"><i></i> Completada</span>
            <span class="is-festivo"><i></i> Festivo</span>
        </div>
    </div>
    <div class="suite-panel-cuerpo">
    <div class="calendario-mes">
        <div class="calendario-grid">
            <div class="calendario-dia-header">Lun</div>
            <div class="calendario-dia-header">Mar</div>
            <div class="calendario-dia-header">Mié</div>
            <div class="calendario-dia-header">Jue</div>
            <div class="calendario-dia-header">Vie</div>
            <div class="calendario-dia-header">Sáb</div>
            <div class="calendario-dia-header">Dom</div>

            <?php
            for ($i = 1; $i < $primerDiaSemana; $i++) {
                echo '<div class="calendario-dia calendario-dia-vacio"></div>';
            }

            for ($dia = 1; $dia <= $diasEnMes; $dia++) {
                $fechaActual = $mesActual . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
                $esHoy = $fechaActual === date('Y-m-d');
                $esFestivo = isset($festivosMap[$fechaActual]);
                $esHabil = $calendario->esHabil($fechaActual);
                $tieneActividades = isset($actividadesPorDia[$dia]);

                $clases = ['calendario-dia'];
                if ($esHoy) $clases[] = 'calendario-dia-hoy';
                if ($esFestivo) $clases[] = 'calendario-dia-festivo';
                if (!$esHabil) $clases[] = 'calendario-dia-inhabil';
                if ($tieneActividades) $clases[] = 'calendario-dia-con-actividades';
                ?>
                <div class="<?php echo implode(' ', $clases); ?>">
                    <div class="dia-numero"><?php echo $dia; ?></div>
                    <?php if ($esFestivo): ?>
                        <div class="dia-badge" title="<?php echo s($festivosMap[$fechaActual]->motivo); ?>">
                            <i class="fa-solid fa-ban"></i> <?php echo s(substr($festivosMap[$fechaActual]->motivo, 0, 15)); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($tieneActividades): ?>
                        <div class="dia-actividades">
                            <?php foreach (array_slice($actividadesPorDia[$dia], 0, 2) as $item): $esSub = ($item['tipo'] ?? '') === 'subactividad'; ?>
                                <a href="<?php echo $esSub ? '/programa/subactividades/editar?id=' . (int)$item['actividad']->id . '&volver=programa' : '/programa/actividades/editar?id=' . (int)$item['actividad']->id; ?>"
                                   <?php echo $puedeGestionar && !$esSub ? 'data-editar-actividad="' . (int)$item['actividad']->id . '"' : ''; ?>
                                   class="dia-actividad status-<?php echo s($item['actividad']->status); ?><?php echo $esSub ? ' dia-actividad-sub' : ''; ?>"
                                   title="<?php echo s($item['actividad']->codigo . ': ' . $item['actividad']->nombre . ' (' . $item['actividad']->progreso . '%)'); ?>">
                                    <div class="dia-actividad-codigo"><?php echo s($item['actividad']->codigo); ?></div>
                                    <div class="dia-actividad-nombre"><?php echo $esSub ? '↳ ' : ''; ?><?php echo s($item['actividad']->nombre); ?></div>
                                    <div class="dia-actividad-progreso"><?php echo s($item['actividad']->progreso); ?>%</div>
                                </a>
                            <?php endforeach; ?>
                            <?php if (count($actividadesPorDia[$dia]) > 2): ?>
                                <div class="dia-actividad-mas">+<?php echo count($actividadesPorDia[$dia]) - 2; ?> más</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
    </div>
</div>

<?php if (!empty($actividades)): ?>
<div class="suite-panel">
    <div class="cal-tabla-cab">
        <h4><i class="fa-solid fa-list-check"></i> Actividades y subactividades del mes <span class="suite-chip is-primary"><?php echo count($actividades); ?></span></h4>
        <span class="text-muted text-sm">Haz clic en una actividad para editarla en una ventana.</span>
    </div>
    <div class="suite-panel-cuerpo">
        <div class="tabla-wrapper">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th class="hide-m">Partida</th>
                        <th class="hide-m">Progreso</th>
                        <th>Status</th>
                        <th class="hide-m">Días en este mes</th>
                        <?php if (puede_gestionar_proyecto($proyecto)): ?><th>Acciones</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actividades as $item): $esSub = ($item['tipo'] ?? '') === 'subactividad'; ?>
                        <tr class="<?php echo $esSub ? 'cal-fila-sub' : ''; ?>">
                            <td class="mono"><?php echo s($item['actividad']->codigo); ?></td>
                            <td><?php echo $esSub ? '↳ ' : ''; ?><?php echo s($item['actividad']->nombre); ?></td>
                            <td class="text-sm text-muted hide-m"><?php echo s($item['partida']->nombre); ?></td>
                            <td class="mono hide-m"><?php echo s($item['actividad']->progreso); ?>%</td>
                            <td>
                                <span class="badge-status status-<?php echo s($item['actividad']->status); ?>">
                                    <?php echo s(ucfirst(str_replace('_', ' ', $item['actividad']->status))); ?>
                                </span>
                            </td>
                            <td class="mono hide-m"><?php echo s(implode(', ', $item['dias_en_mes'])); ?></td>
                            <?php if (puede_gestionar_proyecto($proyecto)): ?>
                                <td>
                                    <?php if ($esSub): ?>
                                        <a href="/programa/subactividades/editar?id=<?php echo s($item['actividad']->id); ?>&volver=programa" class="btn btn-xs btn-ghost"><i class="fa-solid fa-pen"></i> Editar</a>
                                    <?php else: ?>
                                        <a href="/programa/actividades/editar?id=<?php echo s($item['actividad']->id); ?>" data-editar-actividad="<?php echo (int)$item['actividad']->id; ?>" class="btn btn-xs btn-ghost"><i class="fa-solid fa-pen"></i> Editar</a>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="suite-panel">
    <?php echo estado_vacio('fa-calendar-xmark', 'Sin actividades este mes', 'No hay actividades programadas en este mes. Cambia de mes o crea una actividad para verla aquí.', $puedeGestionar ? ['texto' => 'Crear actividad', 'url' => '#', 'icono' => 'fa-plus'] : null); ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var b = document.querySelector('.suite-panel .estado-vacio .btn-primary');
    if (b && b.getAttribute('href') === '#') { b.addEventListener('click', function (e) { e.preventDefault(); var c = document.getElementById('cal-crear-actividad'); if (c) c.click(); }); }
});
</script>
<?php endif; ?>

<?php if ($puedeGestionar): ?>
<div class="modal-overlay" id="cal-modal" aria-hidden="true">
    <div class="modal-card cal-modal-card" role="dialog" aria-modal="true" aria-labelledby="cal-modal-titulo">
        <div class="cal-modal-cab">
            <h3 id="cal-modal-titulo">Actividad</h3>
            <button type="button" class="btn btn-sm btn-ghost" id="cal-modal-cerrar" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p class="text-muted text-sm" id="cal-modal-cargando">Cargando…</p>
        <iframe id="cal-modal-frame" title="Formulario de actividad"></iframe>
    </div>
</div>
<script>
(function () {
    const modal = document.getElementById('cal-modal'), frame = document.getElementById('cal-modal-frame');
    const titulo = document.getElementById('cal-modal-titulo'), cargando = document.getElementById('cal-modal-cargando');
    const proyecto = <?php echo (int)$proyecto->id; ?>;
    const abrir = (url, texto) => {
        titulo.textContent = texto;
        cargando.hidden = false;
        frame.hidden = true;
        frame.src = url;
        modal.classList.add('abierto'); modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('cal-modal-abierto');
    };
    const cerrar = () => {
        modal.classList.remove('abierto'); modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('cal-modal-abierto');
        frame.src = 'about:blank';
    };
    frame.addEventListener('load', () => { if (frame.src !== 'about:blank') { cargando.hidden = true; frame.hidden = false; } });

    document.getElementById('cal-crear-actividad').addEventListener('click', e => abrir(e.currentTarget.dataset.url, 'Nueva Actividad'));
    // Clic en una actividad (del calendario o de la lista): se edita en el modal; con Ctrl/clic central sigue abriendo la página completa
    document.addEventListener('click', e => {
        const a = e.target.closest('[data-editar-actividad]');
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        abrir('/programa/actividades/editar?id=' + encodeURIComponent(a.dataset.editarActividad) + '&modal=1', 'Editar Actividad');
    });
    document.getElementById('cal-modal-cerrar').addEventListener('click', cerrar);
    modal.addEventListener('click', e => { if (e.target === modal) cerrar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('abierto')) cerrar(); });

    // El formulario avisa al guardar: se recarga el calendario en el mismo mes
    window.addEventListener('message', e => {
        if (e.origin !== location.origin || e.source !== frame.contentWindow || !e.data || e.data.tipo !== 'actividad-guardada') return;
        location.reload();
    });
})();
</script>
<?php endif; ?>
