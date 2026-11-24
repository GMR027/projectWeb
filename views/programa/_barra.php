<?php
// Enlaces que conservan los filtros actuales y cambian solo lo indicado.
$prgUrlCon = function (array $cambios = [], array $quitar = []) {
    $q = $_GET;
    foreach ($quitar as $k) unset($q[$k]);
    foreach ($cambios as $k => $v) {
        if ($v === '' || $v === null) unset($q[$k]); else $q[$k] = $v;
    }
    return '/programa?' . http_build_query($q);
};

$chipsFiltro = [];
if ($texto !== '') $chipsFiltro[] = ['etq' => t('Buscar') . ': ' . $texto, 'url' => $prgUrlCon([], ['q'])];
if (!empty($filtros['partida'])) {
    $nombrePartida = '';
    foreach ($partidasFiltro as $p) if ((int)$p['id'] === (int)$filtros['partida']) { $nombrePartida = $p['codigo'] . ' · ' . $p['nombre']; break; }
    $chipsFiltro[] = ['etq' => t('Partida') . ': ' . $nombrePartida, 'url' => $prgUrlCon([], ['partida'])];
}
$etiqStatus = ['no_iniciada' => 'No iniciada', 'en_proceso' => 'En proceso', 'completada' => 'Completada'];
if (!empty($filtros['status'])) $chipsFiltro[] = ['etq' => t('Estatus') . ': ' . ($etiqStatus[$filtros['status']] ?? $filtros['status']), 'url' => $prgUrlCon([], ['status'])];
$etiqPrioridad = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'];
if (!empty($filtros['prioridad'])) $chipsFiltro[] = ['etq' => t('Prioridad') . ': ' . ($etiqPrioridad[$filtros['prioridad']] ?? $filtros['prioridad']), 'url' => $prgUrlCon([], ['prioridad'])];
if (!empty($filtros['restriccion'])) $chipsFiltro[] = ['etq' => $filtros['restriccion'] === 'con' ? t('Con restricción') : t('Sin restricción'), 'url' => $prgUrlCon([], ['restriccion'])];
if (!empty($filtros['riesgo'])) $chipsFiltro[] = ['etq' => t('Solo en riesgo'), 'url' => $prgUrlCon([], ['riesgo'])];
foreach ($camposCp as $cf) {
    $vf = $filtrosCp[$cf['id']] ?? '';
    if ($vf === '' || $vf === null) continue;
    $chipsFiltro[] = ['etq' => $cf['nombre'] . ': ' . ($vf === '__vacio' ? t('(vacío)') : $vf), 'url' => $prgUrlCon([], ['cp'])];
}

$vistaActual = $vista ?? 'ambos';
$nFiltros = count($chipsFiltro);
$mostrando = count($filas);
$total = (int)($totalFilas ?? $mostrando);
?>
<div class="prg-cmd-wrap">
    <form method="GET" action="/programa" class="prg-cmd" id="prg-cmd">
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <input type="hidden" name="sub" value="<?php echo $mostrarSub ? '1' : '0'; ?>">
        <?php if (!empty($filtros['status'])): ?><input type="hidden" name="status" value="<?php echo s($filtros['status']); ?>"><?php endif; ?>
        <?php if ($vistaActual !== 'ambos'): ?><input type="hidden" name="vista" value="<?php echo s($vistaActual); ?>"><?php endif; ?>
        <div class="prg-cmd-principal">
            <label class="prg-busca" for="prg-q">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="prg-q" name="q" value="<?php echo s($texto); ?>" placeholder="<?php echo s(t('Buscar código, actividad o responsable…')); ?>" autocomplete="off">
                <?php if ($texto !== ''): ?><a class="prg-busca-x" href="<?php echo s($prgUrlCon([], ['q'])); ?>" title="<?php echo s(t('Quitar búsqueda')); ?>" aria-label="<?php echo s(t('Quitar búsqueda')); ?>"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
            </label>

            <div class="prg-estados" role="group" aria-label="<?php echo s(t('Filtrar por estatus')); ?>">
                <?php foreach (['' => 'Todas', 'no_iniciada' => 'No iniciada', 'en_proceso' => 'En proceso', 'completada' => 'Completada'] as $kEstado => $etqEstado): ?>
                    <a href="<?php echo s($prgUrlCon(['status' => $kEstado], ['foco'])); ?>" class="prg-estado<?php echo (string)$filtros['status'] === (string)$kEstado ? ' activo' : ''; ?>">
                        <i class="prg-punto prg-punto-<?php echo $kEstado === '' ? 'todas' : s($kEstado); ?>" aria-hidden="true"></i><?php echo s(t($etqEstado)); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <details class="prg-filtros-detalles"<?php echo $nFiltros ? ' open' : ''; ?>>
                <summary class="btn btn-ghost prg-filtros-btn">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i> <?php echo s(t('Filtros')); ?>
                    <?php if ($nFiltros): ?><span class="prg-badge"><?php echo (int)$nFiltros; ?></span><?php endif; ?>
                </summary>
                <div class="prg-filtros-panel">
                    <div class="prg-filtros-grid">
                        <div class="campo">
                            <label for="prg-f-partida"><?php echo s(t('Partida')); ?></label>
                            <select id="prg-f-partida" name="partida">
                                <option value=""><?php echo s(t('Todas')); ?></option>
                                <?php foreach ($partidasFiltro as $p): ?>
                                    <option value="<?php echo s($p['id']); ?>"<?php echo (int)$filtros['partida'] === (int)$p['id'] ? ' selected' : ''; ?>><?php echo s($p['codigo'] . ' · ' . $p['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="prg-f-prioridad"><?php echo s(t('Prioridad')); ?></label>
                            <select id="prg-f-prioridad" name="prioridad">
                                <option value=""><?php echo s(t('Todas')); ?></option>
                                <?php foreach (['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'] as $k => $etq): ?>
                                    <option value="<?php echo $k; ?>"<?php echo $filtros['prioridad'] === $k ? ' selected' : ''; ?>><?php echo s(t($etq)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="prg-f-restriccion"><?php echo s(t('Restricción')); ?></label>
                            <select id="prg-f-restriccion" name="restriccion">
                                <option value=""><?php echo s(t('Todas')); ?></option>
                                <option value="con"<?php echo $filtros['restriccion'] === 'con' ? ' selected' : ''; ?>><?php echo s(t('Con restricción activa')); ?></option>
                                <option value="sin"<?php echo $filtros['restriccion'] === 'sin' ? ' selected' : ''; ?>><?php echo s(t('Sin restricción')); ?></option>
                            </select>
                        </div>
                        <div class="campo prg-campo-check">
                            <label class="campo-check" for="prg-f-riesgo">
                                <input type="checkbox" id="prg-f-riesgo" name="riesgo" value="1"<?php echo $filtros['riesgo'] === '1' ? ' checked' : ''; ?>>
                                <?php echo s(t('Solo en riesgo (30 días)')); ?>
                            </label>
                        </div>
                        <?php foreach ($camposCp as $cf): $vf = $filtrosCp[$cf['id']] ?? ''; ?>
                            <div class="campo">
                                <label for="prg-f-cp<?php echo (int)$cf['id']; ?>"><?php echo s($cf['nombre']); ?></label>
                                <?php if ($cf['tipo'] === 'lista'): ?>
                                    <select id="prg-f-cp<?php echo (int)$cf['id']; ?>" name="cp[<?php echo (int)$cf['id']; ?>]"><option value=""><?php echo s(t('Todos')); ?></option>
                                        <?php foreach ($cf['opciones'] as $o): ?><option<?php echo $vf === $o ? ' selected' : ''; ?>><?php echo s($o); ?></option><?php endforeach; ?>
                                        <option value="__vacio"<?php echo $vf === '__vacio' ? ' selected' : ''; ?>><?php echo s(t('(vacío)')); ?></option></select>
                                <?php else: ?>
                                    <input type="<?php echo $cf['tipo'] === 'fecha' ? 'date' : 'search'; ?>" id="prg-f-cp<?php echo (int)$cf['id']; ?>" name="cp[<?php echo (int)$cf['id']; ?>]" value="<?php echo s($vf); ?>" placeholder="<?php echo s(t('Filtrar')); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="prg-filtros-pie">
                        <a href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>&vista=<?php echo s($vistaActual); ?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> <?php echo s(t('Limpiar filtros')); ?></a>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> <?php echo s(t('Aplicar filtros')); ?></button>
                    </div>
                </div>
            </details>

            <span class="prg-resultados" aria-live="polite">
                <?php if ($mostrando < $total): ?>
                    <strong><?php echo (int)$mostrando; ?></strong> / <?php echo (int)$total; ?> <?php echo s(t('filas')); ?>
                <?php else: ?>
                    <strong><?php echo (int)$total; ?></strong> <?php echo s(t('filas')); ?>
                <?php endif; ?>
            </span>

            <div class="prg-vistas" role="group" aria-label="<?php echo s(t('Cambiar vista')); ?>">
                <?php foreach (['tabla' => 'Tabla', 'gantt' => 'Gantt', 'ambos' => 'Ambos'] as $kVista => $etqVista): ?>
                    <a href="<?php echo s($prgUrlCon(['vista' => $kVista], ['foco'])); ?>" class="prg-vista<?php echo $vistaActual === $kVista ? ' activo' : ''; ?>"<?php echo $vistaActual === $kVista ? ' aria-current="page"' : ''; ?>>
                        <?php echo s(t($etqVista)); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </form>

    <?php if ($chipsFiltro): ?>
        <div class="prg-chips" aria-label="<?php echo s(t('Filtros activos')); ?>">
            <span class="prg-chips-etq"><?php echo s(t('Filtros activos')); ?>:</span>
            <?php foreach ($chipsFiltro as $chip): ?>
                <a class="prg-chip-filtro" href="<?php echo s($chip['url']); ?>">
                    <?php echo s($chip['etq']); ?> <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </a>
            <?php endforeach; ?>
            <a class="prg-chips-limpiar" href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>&vista=<?php echo s($vistaActual); ?>"><?php echo s(t('Limpiar todo')); ?></a>
        </div>
    <?php endif; ?>
</div>
