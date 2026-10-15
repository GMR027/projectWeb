<?php
$carriles = [
    'listas' => ['Puedes planificar ya', 'fa-circle-check', 'lista', 'Materiales comprados (aunque falte la entrega) y sin restricciones.'],
    'bloqueadas' => ['Bloqueadas', 'fa-ban', 'bloqueada', 'Falta comprar material o hay una restricción activa.'],
];
$q = fn(array $extra = []) => '?' . http_build_query(array_merge(['proyecto' => $proyecto->id], $extra));
$urlAct = fn($id) => "/programa/actividades/editar?proyecto={$proyecto->id}&id=" . (int)$id;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-diagram-next"></i> Planificador</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo (int)$datos['total']; ?> actividad(es) pendientes · organiza qué hacer según el material disponible</p>
    </div>
</div>

<form method="GET" class="card plan-filtros">
    <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
    <label>Ventana
        <select name="semanas" onchange="this.form.submit()">
            <?php foreach ([2 => '2 semanas', 4 => '4 semanas', 8 => '8 semanas', 12 => '12 semanas', 0 => 'Todo el programa'] as $k => $e): ?>
                <option value="<?php echo $k; ?>"<?php echo $semanas === $k ? ' selected' : ''; ?>><?php echo $e; ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Partida
        <select name="partida" onchange="this.form.submit()">
            <option value="">Todas</option>
            <?php foreach ($partidas as $p): ?><option value="<?php echo s($p->id); ?>"<?php echo $partidaId == $p->id ? ' selected' : ''; ?>><?php echo s($p->codigo . ' — ' . $p->nombre); ?></option><?php endforeach; ?>
        </select>
    </label>
    <label class="campo-check"><input type="checkbox" name="solo" value="1" <?php echo $solo ? 'checked' : ''; ?> onchange="this.form.submit()"> Solo actividades con materiales vinculados</label>
</form>

<?php if ($datos['sinMateriales'] > 0): ?>
    <p class="text-muted text-sm plan-aviso"><i class="fa-solid fa-circle-info"></i> <?php echo (int)$datos['sinMateriales']; ?> de <?php echo (int)$datos['total']; ?> actividades no tienen materiales vinculados (y no están marcadas «sin material»): se cuentan como listas. Un material cuenta como disponible en cuanto está comprado. Vincúlalos desde «Editar actividad → Gestionar materiales».</p>
<?php endif; ?>

<div class="plan-lanes">
    <?php foreach ($carriles as $k => [$titulo, $ico, $cls, $ayuda]): ?>
        <section class="plan-lane plan-<?php echo $cls; ?>">
            <header><h3><i class="fa-solid <?php echo $ico; ?>"></i> <?php echo $titulo; ?> <b><?php echo count($datos[$k]); ?></b></h3><small><?php echo $ayuda; ?></small></header>
            <?php if (!$datos[$k]): ?><p class="text-muted text-sm">Nada por aquí.</p><?php endif; ?>
            <?php foreach ($datos[$k] as $a): ?>
                <article class="plan-card">
                    <a class="plan-titulo" href="<?php echo s($urlAct($a['id'])); ?>"><span class="mono"><?php echo s($a['codigo']); ?></span> <?php echo s($a['nombre']); ?></a>
                    <div class="plan-meta">
                        <span class="mono"><?php echo formatear_fecha($a['fecha_inicio'], 'd/m'); ?> → <?php echo formatear_fecha($a['fecha_fin'], 'd/m'); ?></span>
                        <span><?php echo (int)$a['progreso']; ?>%</span>
                        <span class="text-muted"><?php echo s($a['pcod']); ?></span>
                    </div>
                    <?php foreach ($a['motivos'] as $m): ?><p class="plan-motivo n<?php echo (int)$m['n']; ?>"><?php echo s($m['t']); ?></p><?php endforeach; ?>
                    <?php if (!$a['vinculada']): ?><p class="plan-motivo n0">Sin materiales vinculados</p><?php endif; ?>
                    <?php if ($a['nivel'] > 0): ?>
                        <p class="plan-libera"><?php echo $a['libera'] ? 'Se libera ~ ' . s($a['libera']) . ($a['sin_fecha'] ? ' (hay pendientes sin fecha)' : '') : 'Sin fecha de liberación'; ?></p>
                    <?php endif; ?>
                    <?php if ($a['vencida'] && $a['nivel'] > 0): ?><p class="plan-motivo n2">Ya debió iniciar el <?php echo formatear_fecha($a['fecha_inicio'], 'd/m'); ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>

<div class="card plan-inversa">
    <h3><i class="fa-solid fa-boxes-stacked"></i> Tengo estos materiales comprados: ¿qué puedo adelantar?</h3>
    <?php if (!$datos['inversa']): ?>
        <p class="text-muted">Ningún material comprado está vinculado a actividades pendientes todavía.</p>
    <?php else: ?>
        <?php foreach ($datos['inversa'] as $m): ?>
            <div class="plan-mat">
                <div class="plan-mat-cab">
                    <?php if ($m['imagen']): ?><img class="mat-thumb" src="/uploads/materiales/<?php echo s($m['imagen']); ?>" alt=""><?php else: ?><span class="mat-thumb mat-thumb-vacio"><i class="fa-regular fa-image"></i></span><?php endif; ?>
                    <div><strong><?php echo s($m['nombre']); ?></strong><br><small class="text-muted"><?php echo s($m['entrega']); ?></small></div>
                </div>
                <ul>
                    <?php foreach ($m['actividades'] as $a): ?>
                        <li><a href="<?php echo s($urlAct($a['id'])); ?>"><span class="plan-dot plan-d-<?php echo s($a['estado']); ?>"></span> <span class="mono"><?php echo s($a['codigo']); ?></span> <?php echo s($a['nombre']); ?></a>
                            <small class="text-muted"> · inicia <?php echo s($a['inicio']); ?><?php echo $a['futura'] && $a['estado'] === 'lista' ? ' · puedes adelantarla' : ''; ?><?php echo $a['estado'] !== 'lista' ? ' · aún depende de otros materiales o restricciones' : ''; ?></small></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
