<?php // Variables: $proyecto, $acts, $dias, $totalDias, $totalFotos, $pagina, $porPagina, $filtros, $partidas, $puedeEditar
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fechaLarga = function (string $f) use ($meses) { $t = strtotime($f); return date('d', $t) . ' de ' . $meses[(int)date('n', $t)] . ' de ' . date('Y', $t); };
$q = fn(array $extra = []) => '/evidencias?' . http_build_query(array_filter(array_merge(['proyecto' => $proyecto->id], $filtros, $extra), fn($v) => $v !== '' && $v !== 0 && $v !== null));
$nombreAct = fn(int $id) => isset($acts[$id]) ? $acts[$id]['partida']->codigo . ' · ' . $acts[$id]['actividad']->nombre : null;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-camera-retro"></i> <?php echo s(t('Evidencias')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · línea de tiempo con las fotos de los reportes diarios y las subidas a una actividad</p>
    </div>
    <?php if ($puedeEditar): ?><button type="button" class="btn btn-primary" id="ev-subir-btn"><i class="fa-solid fa-camera"></i> <?php echo s(t('Agregar evidencia')); ?></button><?php endif; ?>
</div>

<?php if ($puedeEditar): ?>
<div class="card" id="ev-subir" hidden>
    <h3>Nueva evidencia</h3>
    <form method="POST" action="/evidencias/subir" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <div class="form-grid">
            <div class="campo campo-full"><label for="ev-act">Actividad *</label>
                <select id="ev-act" name="actividad_id" required>
                    <option value="">— Selecciona —</option>
                    <?php foreach ($partidas as $p): ?>
                        <optgroup label="● <?php echo s($p->codigo . ' · ' . $p->nombre); ?>" style="background:<?php echo s($p->color_bg ?: '#64748b'); ?>;color:#fff">
                            <?php foreach ($p->getActividades() as $a): ?><option value="<?php echo (int)$a->id; ?>" style="background:<?php echo s($p->color_bg ?: '#64748b'); ?>33;color:inherit"<?php echo (int)$filtros['actividad'] === (int)$a->id ? ' selected' : ''; ?>><?php echo s($a->codigo . ' · ' . $a->nombre); ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label for="ev-fecha"><?php echo s(t('Fecha')); ?></label><input type="date" id="ev-fecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>"></div>
            <div class="campo"><label for="ev-fotos">Fotos * (hasta 10, 15 MB c/u)</label><input type="file" id="ev-fotos" name="fotos[]" accept="image/*" multiple required></div>
            <div class="campo"><label for="ev-depto">Departamento</label><input type="text" id="ev-depto" name="departamento" maxlength="100" list="ev-l-deptos" autocomplete="off"><datalist id="ev-l-deptos"><?php foreach ($departamentos as $d): ?><option value="<?php echo s($d); ?>"><?php endforeach; ?></datalist></div>
            <div class="campo"><label for="ev-cat">Categoría</label><input type="text" id="ev-cat" name="categoria" maxlength="100" list="ev-l-cats" autocomplete="off"><datalist id="ev-l-cats"><?php foreach ($categorias as $c): ?><option value="<?php echo s($c); ?>"><?php endforeach; ?></datalist></div>
            <div class="campo campo-full"><label for="ev-desc">Descripción (se aplica a todas las fotos)</label><input type="text" id="ev-desc" name="descripcion" maxlength="300" placeholder="Ej: Colado de losa, nivel 2"></div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Subir</button>
    </form>
</div>
<?php endif; ?>

<div class="card hide-m">
    <form method="GET" action="/evidencias" class="ev-filtros">
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <div><label class="text-sm text-muted"><?php echo s(t('Partida')); ?></label><br>
            <select name="partida"><option value=""><?php echo s(t('Todas')); ?></option><?php foreach ($partidas as $p): ?><option value="<?php echo (int)$p->id; ?>"<?php echo (int)$filtros['partida'] === (int)$p->id ? ' selected' : ''; ?>><?php echo s($p->codigo . ' · ' . $p->nombre); ?></option><?php endforeach; ?></select></div>
        <div><label class="text-sm text-muted"><?php echo s(t('Actividad')); ?></label><br>
            <select name="actividad"><option value=""><?php echo s(t('Todas')); ?></option>
                <?php foreach ($partidas as $p): ?><optgroup label="● <?php echo s($p->codigo); ?>" style="background:<?php echo s($p->color_bg ?: '#64748b'); ?>;color:#fff"><?php foreach ($p->getActividades() as $a): ?><option value="<?php echo (int)$a->id; ?>" style="background:<?php echo s($p->color_bg ?: '#64748b'); ?>33;color:inherit"<?php echo (int)$filtros['actividad'] === (int)$a->id ? ' selected' : ''; ?>><?php echo s($a->codigo . ' · ' . mb_strimwidth($a->nombre, 0, 50, '…')); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
            </select></div>
        <?php foreach (['departamento' => ['Departamento', $departamentos], 'categoria' => ['Categoría', $categorias]] as $k => [$et, $op]): if (!$op) continue; ?>
        <div><label class="text-sm text-muted"><?php echo s(t($et)); ?></label><br>
            <select name="<?php echo $k; ?>"><option value=""><?php echo s(t('Todos')); ?></option><?php foreach ($op as $o): ?><option value="<?php echo s($o); ?>"<?php echo $filtros[$k] === $o ? ' selected' : ''; ?>><?php echo s($o); ?></option><?php endforeach; ?></select></div>
        <?php endforeach; ?>
        <div><label class="text-sm text-muted"><?php echo s(t('Desde')); ?></label><br><input type="date" name="desde" value="<?php echo s($filtros['desde']); ?>"></div>
        <div><label class="text-sm text-muted"><?php echo s(t('Hasta')); ?></label><br><input type="date" name="hasta" value="<?php echo s($filtros['hasta']); ?>"></div>
        <div><label class="text-sm text-muted"><?php echo s(t('Origen')); ?></label><br>
            <select name="origen"><option value=""><?php echo s(t('Todos')); ?></option><option value="reportes"<?php echo $filtros['origen'] === 'reportes' ? ' selected' : ''; ?>><?php echo s(t('Reportes diarios')); ?></option><option value="subidas"<?php echo $filtros['origen'] === 'subidas' ? ' selected' : ''; ?>><?php echo s(t('Subidas a actividades')); ?></option></select></div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> <?php echo s(t('Filtrar')); ?></button>
        <a href="/evidencias?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost"><?php echo s(t('Limpiar')); ?></a>
    </form>
    <p class="text-muted text-sm" style="margin-top:1rem"><?php echo (int)$totalFotos; ?> foto(s) en <?php echo (int)$totalDias; ?> día(s)</p>
</div>

<?php if (!$dias): ?>
    <div class="card"><p class="text-muted">No hay evidencias con estos filtros. Las fotos de los <a href="/reportes?proyecto=<?php echo (int)$proyecto->id; ?>">reportes diarios</a> aparecen aquí solas<?php echo $puedeEditar ? ', y puedes subir más con «Agregar evidencia»' : ''; ?>.</p></div>
<?php else: ?>
<div class="ev-linea" <?php echo pag_attr($proyecto); ?>>
    <?php foreach ($dias as $fecha => $g): $n = array_sum(array_map(fn($r) => count($r['fotos']), $g['reportes'] ?? [])) + count($g['subidas'] ?? []); ?>
        <section class="ev-dia">
            <div class="ev-dia-cab"><span class="ev-punto"></span><h3><?php echo s($fechaLarga($fecha)); ?></h3><span class="text-muted text-sm"><?php echo $n; ?> foto(s)</span></div>
            <?php foreach (($g['reportes'] ?? []) as $r): ?>
                <article class="ev-bloque">
                    <header>
                        <span class="badge hide-m"><i class="fa-solid fa-clipboard-list"></i> <?php echo s(t('Reporte diario')); ?></span>
                        <?php if (trim((string)$r['area']) !== ''): ?><strong class="hide-m"><?php echo s($r['area']); ?></strong><?php endif; ?>
                        <a class="text-sm ev-ver" href="/reportes/ver?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$r['id']; ?>"><?php echo s(t('Ver reporte')); ?></a>
                    </header>
                    <div class="ev-chips"><?php foreach ($r['acts'] as $aid): if ($nm = $nombreAct($aid)): ?><a class="ev-chip" href="<?php echo s($q(['actividad' => $aid, 'partida' => '', 'pagina' => ''])); ?>"><?php echo s(mb_strimwidth($nm, 0, 60, '…')); ?></a><?php endif; endforeach; ?></div>
                    <?php if (trim((string)$r['comentarios']) !== ''): ?><p class="text-sm text-muted hide-m"><?php echo s(mb_strimwidth($r['comentarios'], 0, 220, '…')); ?></p><?php endif; ?>
                    <div class="ev-fotos"><?php foreach ($r['fotos'] as $f): ?><img src="/uploads/reportes/<?php echo s($f); ?>" data-visor="/uploads/reportes/<?php echo s($f); ?>" alt="Foto del reporte" loading="lazy"><?php endforeach; ?></div>
                </article>
            <?php endforeach; ?>
            <?php if (!empty($g['subidas'])): ?>
                <article class="ev-bloque">
                    <header><span class="badge hide-m"><i class="fa-solid fa-camera"></i> <?php echo s(t('Subidas a actividades')); ?></span></header>
                    <div class="ev-fotos ev-fotos-sub">
                        <?php foreach ($g['subidas'] as $e): ?>
                            <figure>
                                <img src="<?php echo s($e->url()); ?>" data-visor="<?php echo s($e->url()); ?>" alt="<?php echo s($e->descripcion ?: 'Evidencia'); ?>" loading="lazy">
                                <figcaption>
                                    <?php if ($e->descripcion !== ''): ?><span class="hide-m"><?php echo s($e->descripcion); ?></span><?php endif; ?>
                                    <?php foreach (['departamento' => $e->departamento, 'categoria' => $e->categoria] as $k => $v): if ($v !== ''): ?><a class="ev-chip hide-m" href="<?php echo s($q([$k => $v, 'pagina' => ''])); ?>"><?php echo s($v); ?></a><?php endif; endforeach; ?>
                                    <?php if ($e->actividad_id && ($nm = $nombreAct((int)$e->actividad_id))): ?><a class="ev-chip" href="<?php echo s($q(['actividad' => (int)$e->actividad_id, 'partida' => '', 'pagina' => ''])); ?>"><?php echo s(mb_strimwidth($nm, 0, 50, '…')); ?></a><?php endif; ?>
                                    <?php if ($puedeEditar): ?>
                                        <form class="hide-m" method="POST" action="/evidencias/eliminar" data-confirm="¿Eliminar esta foto?|Se borra el archivo. Esta acción no se puede deshacer.|Sí, eliminar">
                                            <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$e->id; ?>">
                                            <button type="submit" class="btn btn-sm btn-ghost" title="Eliminar"><i class="fa-solid fa-trash-can"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
<?php if ($totalDias > $porPagina): ?>
    <div class="ev-paginas">
        <?php if ($pagina > 1): ?><a class="btn btn-ghost" href="<?php echo s($q(['pagina' => $pagina - 1])); ?>">← Más recientes</a><?php endif; ?>
        <span class="text-muted">Página <?php echo (int)$pagina; ?> de <?php echo (int)ceil($totalDias / $porPagina); ?></span>
        <?php if ($pagina * $porPagina < $totalDias): ?><a class="btn btn-ghost" href="<?php echo s($q(['pagina' => $pagina + 1])); ?>">Más antiguas →</a><?php endif; ?>
    </div>
<?php endif; ?>
<?php endif; ?>

<script>
(function () { const b = document.getElementById('ev-subir-btn'), c = document.getElementById('ev-subir'); if (b && c) b.addEventListener('click', () => { c.hidden = !c.hidden; if (!c.hidden) c.scrollIntoView({ behavior: 'smooth', block: 'center' }); }); <?php if ($filtros['actividad'] && $puedeEditar && !empty($_GET['nueva'])): ?>if (c) c.hidden = false;<?php endif; ?> })();
</script>
