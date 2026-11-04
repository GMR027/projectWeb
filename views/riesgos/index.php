<?php
/**
 * Registro de riesgos. Variables: $proyecto, $disponible, $filtros, $lista, $resumen, $matriz,
 * $riesgo, $editar, $abrirForm, $actividades, $puedeEditar, $puedeEliminar, $ok, $error
 */
use Classes\Riesgos;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/riesgos?' . http_build_query(['proyecto' => $pid] + $q);
$e = $editar;
$prob = (int)($e['probabilidad'] ?? 2);
$imp = (int)($e['impacto'] ?? 2);
$sev = Riesgos::severidad($prob, $imp);
$hoy = date('Y-m-d');

// Actividades agrupadas por partida, para el selector del formulario
$porPartida = [];
foreach ($actividades as $a) $porPartida[$a['partida_codigo'] . ' · ' . $a['partida']][] = $a;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-triangle-exclamation"></i> Riesgos</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · lo que puede pasar y qué vamos a hacer al respecto</p>
    </div>
    <?php if ($disponible): ?>
    <div>
        <?php if ($puedeEditar): ?><a href="<?php echo s($url(['nuevo' => 1])); ?>" class="btn btn-primary">+ Nuevo riesgo</a><?php endif; ?>
        <?php if (puede_descargar()): ?><a href="/riesgos/pdf?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-file-pdf"></i> PDF</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (es_demo()): ?>
<div class="alerta alerta-info">
    <strong>Demo · Registro de riesgos.</strong> Esta lista reúne lo que puede pasar en la obra con su probabilidad, impacto, responsable y plan de acción.
    La severidad sale de multiplicar probabilidad × impacto: verde (1-3), amarillo (4-6) y rojo (7-9).
    Un riesgo rojo ligado a una actividad también enciende la etiqueta «En riesgo» del programa y el semáforo del Inicio.
    <a href="/manual#riesgos">Ver en el manual</a>.
</div>
<?php endif; ?>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_riesgos.sql</code> para usar el registro de riesgos.</p></div>
<?php return; endif; ?>

<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

<div class="rg-kpis">
    <a class="rg-kpi rg-kpi-rojo" href="<?php echo s($url(['sev' => 'rojo'])); ?>">
        <small>Riesgos altos</small><strong><?php echo (int)$resumen['rojos']; ?></strong><span>Abiertos con severidad 7-9</span>
    </a>
    <a class="rg-kpi rg-kpi-amarillo" href="<?php echo s($url(['sev' => 'amarillo'])); ?>">
        <small>Riesgos medios</small><strong><?php echo (int)$resumen['amarillos']; ?></strong><span>Abiertos con severidad 4-6</span>
    </a>
    <a class="rg-kpi" href="<?php echo s($url(['estado' => 'en_tratamiento'])); ?>">
        <small>En tratamiento</small><strong><?php echo (int)$resumen['tratamiento']; ?></strong><span>Con una acción en marcha</span>
    </a>
    <a class="rg-kpi <?php echo $resumen['vencidos'] ? 'rg-kpi-rojo' : ''; ?>" href="<?php echo s($url()); ?>">
        <small>Compromisos vencidos</small><strong><?php echo (int)$resumen['vencidos']; ?></strong><span>La fecha ya pasó y sigue abierto</span>
    </a>
</div>

<div class="rg-tablero">
    <div class="card rg-matriz-card">
        <h3><i class="fa-solid fa-table-cells"></i> Matriz de riesgos</h3>
        <p class="text-muted text-sm">Cada casilla cuenta los riesgos abiertos. Toca un color para filtrar la lista.</p>
        <table class="rg-matriz">
            <thead><tr><th></th><th>Impacto bajo</th><th>Impacto medio</th><th>Impacto alto</th></tr></thead>
            <tbody>
            <?php foreach ([3, 2, 1] as $probFila): ?>
                <tr>
                    <th>Prob. <?php echo s(mb_strtolower(Riesgos::PROBABILIDADES[$probFila])); ?></th>
                    <?php foreach ([1, 2, 3] as $impCol): $sv = Riesgos::severidad($probFila, $impCol); $nv = Riesgos::nivel($sv); $n = (int)($matriz[$probFila][$impCol] ?? 0); ?>
                        <td class="rg-celda rg-<?php echo $nv; ?>">
                            <a href="<?php echo s($url(['sev' => $nv])); ?>" title="<?php echo s(Riesgos::NIVELES[$nv] . ' · ' . $n . ' riesgo(s)'); ?>">
                                <b><?php echo $sv; ?></b>
                                <small><?php echo $n ? $n . ' riesgo' . ($n > 1 ? 's' : '') : '—'; ?></small>
                            </a>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card rg-guia">
        <h3><i class="fa-solid fa-circle-info"></i> Cómo leerlo</h3>
        <ul class="rg-guia-lista">
            <li><span class="rg-punto rg-rojo"></span><b>Rojo (7-9):</b> se atiende esta semana; si está ligado a una actividad, la marca «En riesgo».</li>
            <li><span class="rg-punto rg-amarillo"></span><b>Amarillo (4-6):</b> vigilar y tener un plan.</li>
            <li><span class="rg-punto rg-verde"></span><b>Verde (1-3):</b> se acepta o se monitorea.</li>
        </ul>
        <p class="text-sm text-muted">Cuando el riesgo ocurre, el botón <b>Materializar</b> lo convierte en restricción de su actividad: no se captura dos veces.</p>
    </div>
</div>

<?php if ($riesgo): $r = $riesgo; ?>
<div class="card rg-detalle">
    <div class="rg-detalle-cab">
        <div>
            <span class="rg-badge rg-<?php echo s($r['nivel']); ?>"><i class="fa-solid fa-circle"></i> <?php echo (int)$r['severidad']; ?>/9 · <?php echo s(Riesgos::NIVELES[$r['nivel']]); ?></span>
            <span class="rg-cat"><?php echo s(Riesgos::CATEGORIAS[$r['categoria']] ?? $r['categoria']); ?></span>
            <h3><?php echo s($r['titulo']); ?></h3>
        </div>
        <div class="rg-detalle-acciones">
            <?php if ($puedeEditar): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($url(['editar' => (int)$r['id']])); ?>"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="<?php echo s($url()); ?>">Cerrar</a>
        </div>
    </div>
    <div class="rg-detalle-grid">
        <div><small>Responsable</small><b><?php echo s($r['responsable']); ?></b></div>
        <div><small>Compromiso</small><b class="<?php echo $r['fecha_compromiso'] && $r['fecha_compromiso'] < $hoy && Riesgos::abierto($r) ? 'text-danger' : ''; ?>"><?php echo $r['fecha_compromiso'] ? s(formatear_fecha($r['fecha_compromiso'], 'd/m/Y')) : 'Sin fecha'; ?></b></div>
        <div><small>Estrategia</small><b><?php echo s(Riesgos::ESTRATEGIAS[$r['estrategia']] ?? $r['estrategia']); ?></b></div>
        <div><small>Estado</small><b><?php echo s(Riesgos::ESTADOS[$r['estado']] ?? $r['estado']); ?></b></div>
        <?php if ($r['act_codigo']): ?><div><small>Actividad</small><b><?php echo s($r['act_codigo'] . ' · ' . $r['act_nombre']); ?></b></div><?php endif; ?>
        <?php if (!empty($r['restriccion_id'])): ?><div><small>Restricción</small><b>#<?php echo (int)$r['restriccion_id']; ?> (materializado)</b></div><?php endif; ?>
    </div>
    <?php if (trim((string)$r['descripcion']) !== ''): ?><p class="rg-texto"><?php echo nl2br(s($r['descripcion'])); ?></p><?php endif; ?>
    <?php if (trim((string)$r['plan_accion']) !== ''): ?>
        <div class="rg-plan"><small>Plan de acción</small><p><?php echo nl2br(s($r['plan_accion'])); ?></p></div>
    <?php endif; ?>
    <?php if ($puedeEditar && Riesgos::abierto($r) && $r['actividad_id'] && empty($r['restriccion_id'])): ?>
        <form method="POST" action="/riesgos/materializar" class="inline" data-confirm="Materializar riesgo|Se creará una restricción activa en la actividad ligada y el riesgo pasará a «materializado».|Materializar">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
            <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-bolt"></i> Materializar como restricción</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($abrirForm && $puedeEditar): ?>
<form method="POST" action="/riesgos/guardar" class="card rg-form co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="hidden" name="id" value="<?php echo $e ? (int)$e['id'] : 0; ?>">
    <h3><?php echo $e ? 'Editar riesgo' : 'Nuevo riesgo'; ?></h3>
    <div class="co-campos">
        <label class="co-ancho">Qué riesgo es <span class="req">*</span>
            <input type="text" name="titulo" maxlength="250" required placeholder="Ej.: lluvia en la temporada de colados" value="<?php echo s($e['titulo'] ?? ''); ?>">
        </label>
        <label class="co-ancho">Descripción
            <textarea name="descripcion" rows="2" maxlength="5000" placeholder="Qué puede pasar y a qué afecta"><?php echo s($e['descripcion'] ?? ''); ?></textarea>
        </label>
        <label>Categoría
            <select name="categoria"><?php foreach (Riesgos::CATEGORIAS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($e['categoria'] ?? 'tecnico') === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select>
        </label>
        <label>Quién responde <span class="req">*</span>
            <input type="text" name="responsable" maxlength="150" required placeholder="Una persona, no un departamento" value="<?php echo s($e['responsable'] ?? ''); ?>">
        </label>
        <label>Estrategia
            <select name="estrategia"><?php foreach (Riesgos::ESTRATEGIAS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($e['estrategia'] ?? 'mitigar') === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select>
        </label>
        <label>Estado
            <select name="estado"><?php foreach (Riesgos::ESTADOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($e['estado'] ?? 'identificado') === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select>
        </label>
        <label>Compromiso
            <input type="date" name="fecha_compromiso" value="<?php echo s($e['fecha_compromiso'] ?? ''); ?>">
        </label>
        <label>Actividad ligada (opcional)
            <select name="actividad_id">
                <option value="0">— Riesgo de toda la obra —</option>
                <?php foreach ($porPartida as $partida => $acts): ?>
                    <optgroup label="<?php echo s($partida); ?>">
                        <?php foreach ($acts as $a): ?><option value="<?php echo (int)$a['id']; ?>"<?php echo (int)($e['actividad_id'] ?? 0) === (int)$a['id'] ? ' selected' : ''; ?>><?php echo s($a['codigo'] . ' · ' . $a['nombre']); ?></option><?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="co-ancho">Plan de acción
            <textarea name="plan_accion" rows="2" maxlength="5000" placeholder="La acción concreta y para cuándo"><?php echo s($e['plan_accion'] ?? ''); ?></textarea>
        </label>
    </div>

    <div class="rg-sev">
        <div class="rg-sev-grupo">
            <small>Probabilidad <span class="req">*</span></small>
            <?php foreach (Riesgos::PROBABILIDADES as $k => $t): ?>
                <label class="rg-opcion"><input type="radio" name="probabilidad" value="<?php echo $k; ?>"<?php echo $prob === $k ? ' checked' : ''; ?> required> <?php echo s($t); ?></label>
            <?php endforeach; ?>
        </div>
        <div class="rg-sev-grupo">
            <small>Impacto <span class="req">*</span></small>
            <?php foreach (Riesgos::IMPACTOS as $k => $t): ?>
                <label class="rg-opcion"><input type="radio" name="impacto" value="<?php echo $k; ?>"<?php echo $imp === $k ? ' checked' : ''; ?> required> <?php echo s($t); ?></label>
            <?php endforeach; ?>
        </div>
        <div class="rg-sev-resultado rg-<?php echo Riesgos::nivel($sev); ?>" id="rg-sev" aria-live="polite">
            <small>Severidad</small><strong><?php echo $sev; ?>/9</strong><span><?php echo s(Riesgos::NIVELES[Riesgos::nivel($sev)]); ?></span>
        </div>
    </div>

    <div class="rg-form-pie">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar riesgo</button>
        <a class="btn btn-ghost" href="<?php echo s($url()); ?>">Cancelar</a>
    </div>
</form>
<script>
(function () {
    const form = document.querySelector('.rg-form');
    const salida = document.getElementById('rg-sev');
    if (!form || !salida) return;
    const nombres = { 1: 'Bajo', 2: 'Medio', 3: 'Alto' };
    const pintar = () => {
        const p = Number((form.querySelector('input[name="probabilidad"]:checked') || {}).value || 0);
        const i = Number((form.querySelector('input[name="impacto"]:checked') || {}).value || 0);
        if (!p || !i) return;
        const sev = p * i;
        const nivel = sev >= 7 ? 'rojo' : (sev >= 4 ? 'amarillo' : 'verde');
        salida.className = 'rg-sev-resultado rg-' + nivel;
        salida.querySelector('strong').textContent = sev + '/9';
        salida.querySelector('span').textContent = nombres[nivel === 'rojo' ? 3 : (nivel === 'amarillo' ? 2 : 1)];
    };
    form.addEventListener('change', e => { if (e.target.name === 'probabilidad' || e.target.name === 'impacto') pintar(); });
})();
</script>
<?php endif; ?>

<div class="card">
    <form method="GET" action="/riesgos" class="rg-filtros">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label class="rg-busca"><i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="q" value="<?php echo s($filtros['q']); ?>" placeholder="Buscar riesgo o responsable…" aria-label="Buscar riesgo">
        </label>
        <select name="sev" aria-label="Severidad">
            <option value="">Toda severidad</option>
            <?php foreach (Riesgos::NIVELES as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $filtros['sev'] === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?>
        </select>
        <select name="estado" aria-label="Estado">
            <option value="">Todo estado</option>
            <?php foreach (Riesgos::ESTADOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $filtros['estado'] === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?>
        </select>
        <select name="cat" aria-label="Categoría">
            <option value="">Toda categoría</option>
            <?php foreach (Riesgos::CATEGORIAS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $filtros['cat'] === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Filtrar</button>
        <?php if (array_filter($filtros)): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($url()); ?>">Limpiar</a><?php endif; ?>
    </form>

    <?php if (!$lista): ?>
        <div class="rg-vacio">
            <i class="fa-solid fa-shield-halved"></i>
            <h3><?php echo array_filter($filtros) ? 'Ningún riesgo coincide con el filtro' : 'Todavía no hay riesgos registrados'; ?></h3>
            <p class="text-muted"><?php echo array_filter($filtros) ? 'Prueba con otro filtro o limpia la búsqueda.' : 'Empieza por los de siempre: lluvia, permisos, proveedor único, acceso al sitio y mano de obra.'; ?></p>
            <?php if (!$filtros['q'] && !$filtros['sev'] && !$filtros['estado'] && !$filtros['cat'] && $puedeEditar): ?>
                <a class="btn btn-primary" href="<?php echo s($url(['nuevo' => 1])); ?>">+ Agregar el primer riesgo</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="tabla-wrapper">
        <table class="tabla rg-tabla">
            <thead>
                <tr><th>Severidad</th><th>Riesgo</th><th>Responsable</th><th>Compromiso</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lista as $r): $vencido = $r['fecha_compromiso'] && $r['fecha_compromiso'] < $hoy && Riesgos::abierto($r); ?>
                <tr class="<?php echo $vencido ? 'rg-vencido' : ''; ?>">
                    <td><span class="rg-badge rg-<?php echo s($r['nivel']); ?>" title="<?php echo s(Riesgos::NIVELES[$r['nivel']]); ?>"><b><?php echo (int)$r['severidad']; ?></b><small>/9</small></span></td>
                    <td>
                        <a class="rg-titulo" href="<?php echo s($url(['ver' => (int)$r['id']])); ?>"><?php echo s($r['titulo']); ?></a>
                        <small class="text-muted"><?php echo s(Riesgos::CATEGORIAS[$r['categoria']] ?? $r['categoria']); ?><?php echo $r['act_codigo'] ? ' · ' . s($r['act_codigo'] . ' ' . $r['act_nombre']) : ''; ?></small>
                    </td>
                    <td class="text-sm"><?php echo s($r['responsable']); ?></td>
                    <td class="text-sm mono <?php echo $vencido ? 'text-danger' : ''; ?>"><?php echo $r['fecha_compromiso'] ? s(formatear_fecha($r['fecha_compromiso'], 'd/m/Y')) : '—'; ?><?php if ($vencido): ?><br><small>vencido</small><?php endif; ?></td>
                    <td><span class="rg-estado rg-estado-<?php echo s($r['estado']); ?>"><?php echo s(Riesgos::ESTADOS[$r['estado']] ?? $r['estado']); ?></span></td>
                    <td class="rg-acciones">
                        <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['ver' => (int)$r['id']])); ?>" title="Ver detalle" aria-label="Ver detalle"><i class="fa-solid fa-eye"></i></a>
                        <?php if ($puedeEditar): ?>
                            <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['editar' => (int)$r['id']])); ?>" title="Editar" aria-label="Editar"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if ($puedeEditar && Riesgos::abierto($r) && $r['actividad_id'] && empty($r['restriccion_id'])): ?>
                            <form class="inline" method="POST" action="/riesgos/materializar" data-confirm="Materializar riesgo|Se creará una restricción activa en la actividad ligada.|Materializar">
                                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                <button class="btn btn-xs btn-ghost" type="submit" title="Convertir en restricción" aria-label="Convertir en restricción"><i class="fa-solid fa-bolt"></i></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($puedeEliminar): ?>
                            <form class="inline" method="POST" action="/riesgos/eliminar" data-confirm="Eliminar riesgo|Se borrará el riesgo y su seguimiento. La restricción ya creada no se borra.|Eliminar">
                                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                <button class="btn btn-xs btn-ghost text-danger" type="submit" title="Eliminar" aria-label="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
