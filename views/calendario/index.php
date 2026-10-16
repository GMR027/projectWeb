<?php $puedeGestionar = puede_gestionar_proyecto($proyecto); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-calendar-days"></i> Calendario del Proyecto</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s($proyecto->codigo); ?></p>
    </div>
    <div>
        <a href="/ajustes?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">
            <i class="fa-solid fa-gear"></i> Ajustes
        </a>
        <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">
            <i class="fa-solid fa-arrow-left"></i> Programa
        </a>
    </div>
</div>

<div class="card">
    <div class="calendario-header">
        <h3 class="card-titulo">
            <?php
            $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            $mes = (int)date('n', strtotime($mesActual . '-01'));
            $anio = date('Y', strtotime($mesActual . '-01'));
            echo $meses[$mes] . ' ' . $anio;
            ?>
        </h3>
        <div class="calendario-nav">
            <?php
            $mesAnterior = date('Y-m', strtotime($mesActual . '-01 -1 month'));
            $mesSiguiente = date('Y-m', strtotime($mesActual . '-01 +1 month'));
            $mesHoy = date('Y-m');
            ?>
            <?php if ($puedeGestionar): ?>
                <button type="button" class="btn btn-sm btn-primary" id="cal-crear-actividad" data-url="/programa/actividades/crear?proyecto=<?php echo s($proyecto->id); ?>&modal=1">
                    <i class="fa-solid fa-plus"></i> Crear Actividad
                </button>
            <?php endif; ?>
            <a href="/calendario?proyecto=<?php echo s($proyecto->id); ?>&mes=<?php echo s($mesAnterior); ?>" class="btn btn-sm btn-ghost">
                <i class="fa-solid fa-chevron-left"></i> Anterior
            </a>
            <?php if ($mesActual !== $mesHoy): ?>
                <a href="/calendario?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-secondary">Hoy</a>
            <?php endif; ?>
            <a href="/calendario?proyecto=<?php echo s($proyecto->id); ?>&mes=<?php echo s($mesSiguiente); ?>" class="btn btn-sm btn-ghost">
                Siguiente <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
    </div>

    <?php
    $primerDia = new DateTime($mesActual . '-01');
    $ultimoDia = clone $primerDia;
    $ultimoDia->modify('last day of this month');
    $diasEnMes = (int)$ultimoDia->format('d');
    $primerDiaSemana = (int)$primerDia->format('N');

    $actividadesPorDia = [];
    foreach ($actividades as $item) {
        foreach ($item['dias_en_mes'] as $dia) {
            if (!isset($actividadesPorDia[$dia])) {
                $actividadesPorDia[$dia] = [];
            }
            $actividadesPorDia[$dia][] = $item;
        }
    }

    $festivosMap = [];
    foreach ($festivos as $f) {
        $festivosMap[$f->fecha] = $f;
    }
    ?>

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
                            <?php foreach (array_slice($actividadesPorDia[$dia], 0, 2) as $item): ?>
                                <a href="/programa/actividades/editar?id=<?php echo s($item['actividad']->id); ?>"
                                   <?php echo $puedeGestionar ? 'data-editar-actividad="' . (int)$item['actividad']->id . '"' : ''; ?>
                                   class="dia-actividad status-<?php echo s($item['actividad']->status); ?>"
                                   title="<?php echo s($item['actividad']->codigo . ': ' . $item['actividad']->nombre . ' (' . $item['actividad']->progreso . '%)'); ?>">
                                    <div class="dia-actividad-codigo"><?php echo s($item['actividad']->codigo); ?></div>
                                    <div class="dia-actividad-nombre"><?php echo s($item['actividad']->nombre); ?></div>
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

    <?php if (!empty($actividades)): ?>
        <h4 style="margin-top: 2rem;">
            <i class="fa-solid fa-list-check"></i> Actividades del Mes (<?php echo count($actividades); ?>)
        </h4>
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
                    <?php foreach ($actividades as $item): ?>
                        <tr>
                            <td class="mono"><?php echo s($item['actividad']->codigo); ?></td>
                            <td><?php echo s($item['actividad']->nombre); ?></td>
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
                                    <a href="/programa/actividades/editar?id=<?php echo s($item['actividad']->id); ?>" data-editar-actividad="<?php echo (int)$item['actividad']->id; ?>" class="btn btn-xs btn-ghost">
                                        <i class="fa-solid fa-pen"></i> Editar
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted" style="margin-top: 2rem;">No hay actividades programadas en este mes.</p>
    <?php endif; ?>
</div>

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
