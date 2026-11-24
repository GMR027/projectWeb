<?php
/** Línea de balance. Variables: $proyecto, $disponible, $frentes, $tipos, $datos, $partidas, $puedeEditar, $ok, $error */
use Classes\LineaBalance;
$pid = (int)$proyecto->id;
$f = $datos['frentes'] ?? [];
$lineas = $datos['lineas'] ?? [];
$rango = $datos['rango'] ?? null;
$idxF = [];
foreach ($f as $i => $fr) $idxF[(int)$fr['id']] = $i;
$nFrentes = count($f);
$nOficios = count($lineas);
$nConflictos = count($datos['conflictos'] ?? []);
$takt = $datos['takt'] ?? null;
?>
<div class="balance-page">
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-chart-column"></i> Línea de balance (takt)</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · avance por frentes y oficios, con alertas de cruce</p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-layer-group"></i> <b><?php echo $nFrentes; ?></b> frentes</span>
            <span class="herr-chip"><i class="fa-solid fa-chart-line"></i> <b><?php echo $nOficios; ?></b> oficios</span>
            <?php if ($nConflictos): ?><span class="herr-chip" style="border-color:var(--warning);color:var(--warning-ink)"><i class="fa-solid fa-triangle-exclamation"></i> <b><?php echo $nConflictos; ?></b> cruces</span><?php endif; ?>
        </div>
    </div>
    <div class="la-controles"><a href="/programa?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Programa</a></div>
</div>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Frentes</small><strong><?php echo $nFrentes; ?></strong><span>pisos, tramos o anillos</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Oficios</small><strong><?php echo $nOficios; ?></strong><span>líneas de trabajo</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Takt</small><strong><?php echo $takt === null ? '—' : (int)$takt . ' d'; ?></strong><span>ritmo entre frentes</span></span>
    </div>
    <div class="suite-kpi <?php echo $nConflictos ? 'is-warning' : 'is-success'; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Cruces</small><strong><?php echo $nConflictos; ?></strong><span><?php echo $nConflictos ? 'revisar secuencia' : 'sin interferencias'; ?></span></span>
    </div>
</div>

<?php if (es_demo()): ?><div class="alerta alerta-info"><strong>Demo · Línea de balance.</strong> Para obra repetitiva (pisos, túneles, tramos): cada línea es un oficio y cada punto un frente. Si una línea posterior empieza antes de que termine la anterior, aparece un conflicto. <a href="/manual#balance">Ver en el manual</a>.</div><?php endif; ?>

<?php if (!$disponible): ?><div class="card"><p>Falta aplicar la migración <code>database/migracion_frentes_tipos.sql</code> para usar la línea de balance.</p></div></div><?php return; endif; ?>
<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

<div class="bal-config">
<div class="card bal-card">
    <div class="suite-panel-cab"><h3><i class="fa-solid fa-layer-group"></i> Frentes</h3></div>
    <div class="suite-panel-cuerpo">
    <p class="text-muted text-sm">Pisos, tramos, túneles o anillos. El orden manda en la línea de balance.</p>
    <?php if ($puedeEditar): ?>
    <?php if ($tipos): ?>
    <form method="POST" action="/balance/frente" class="bal-inline">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label>Nombre *<input type="text" name="nombre" maxlength="150" required placeholder="Piso 1, Tramo A…"></label>
        <label>Tipo<select name="tipo_id"><?php foreach ($tipos as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo s($t['nombre']); ?></option><?php endforeach; ?></select></label>
        <label>Orden<input type="number" name="orden" min="0" value="<?php echo count($f) + 1; ?>"></label>
        <label>Color<input type="color" name="color" value="#00b4d8"></label>
        <button class="btn btn-primary" type="submit">+ Agregar frente</button>
    </form>
    <?php else: ?><div class="alerta alerta-aviso">Crea al menos un <strong>tipo de frente</strong> abajo antes de agregar frentes.</div><?php endif; ?>
    <?php endif; ?>
    <?php if (!$f): ?><p class="text-muted"><?php echo $puedeEditar ? 'Aún no hay frentes. Crea el primero para dibujar la línea.' : 'Aún no hay frentes definidos para esta obra.'; ?></p><?php else: ?>
    <div class="bal-frentes-lista"><?php foreach ($f as $fr): ?><span class="badge badge-cliente" style="border-left:4px solid <?php echo s($fr['color']); ?>" title="Tipo: <?php echo s($fr['tipo_nombre'] ?? '—'); ?>"><?php echo s($fr['nombre']); ?><?php if (!empty($fr['tipo_nombre'])): ?> <small class="text-muted">(<?php echo s($fr['tipo_nombre']); ?>)</small><?php endif; ?> · <?php echo (int)$fr['actividades']; ?> act.<?php if ($puedeEditar && (int)$fr['actividades'] === 0): ?><form class="inline" method="POST" action="/balance/frente/eliminar" data-confirm="Eliminar frente|No tiene actividades; se quitará de la línea de balance.|Eliminar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$fr['id']; ?>"><button class="btn btn-xs btn-ghost" type="submit" title="Eliminar" aria-label="Eliminar"><i class="fa-solid fa-xmark"></i></button></form><?php endif; ?></span><?php endforeach; ?></div>
    <?php endif; ?>
    </div>
</div>

<?php if ($puedeEditar): ?>
<div class="card bal-card">
    <div class="suite-panel-cab">
        <div><h3><i class="fa-solid fa-tags"></i> Tipos de frente</h3>
        <p class="text-muted text-sm" style="margin:.2rem 0 0">Crea tus propios tipos (manzana, bloque, eje, cámara, nivel…), no solo los base. El orden decide cómo se listan al crear un frente.</p></div>
        <span class="badge badge-cliente"><?php echo count($tipos); ?> tipo(s)</span>
    </div>
    <div class="suite-panel-cuerpo">
    <form method="POST" action="/balance/tipo" class="bal-inline">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label>Nombre *<input type="text" name="nombre" maxlength="60" required placeholder="Manzana, Bloque, Eje…"></label>
        <label>Orden<input type="number" name="orden" min="0" value="<?php echo count($tipos) + 1; ?>"></label>
        <label>Color<input type="color" name="color" value="#00b4d8"></label>
        <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-plus"></i> Agregar tipo</button>
    </form>
    <?php if ($tipos): ?>
    <?php $conteo = []; foreach ($f as $fr) if (!empty($fr['tipo_id'])) $conteo[(int)$fr['tipo_id']] = ($conteo[(int)$fr['tipo_id']] ?? 0) + 1; ?>
    <div class="tabla-wrapper"><table class="tabla tabla-ancha bal-tipos">
        <thead><tr><th>Tipo</th><th>Editar</th><th class="num">Frentes</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tipos as $t): $idT = (int)$t['id']; ?>
            <tr>
                <td><span style="display:inline-block;width:.7rem;height:.7rem;border-radius:50%;background:<?php echo s($t['color']); ?>;margin-right:.4rem"></span><strong><?php echo s($t['nombre']); ?></strong></td>
                <td>
                    <form class="inline" method="POST" action="/balance/tipo/editar" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
                        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
                        <input type="hidden" name="id" value="<?php echo $idT; ?>">
                        <input type="text" name="nombre" maxlength="60" required value="<?php echo s($t['nombre']); ?>" aria-label="Nombre del tipo" style="min-width:9rem">
                        <label class="text-sm">Orden <input type="number" name="orden" min="0" value="<?php echo (int)$t['orden']; ?>" style="width:4.5rem"></label>
                        <input type="color" name="color" value="<?php echo s($t['color']); ?>" aria-label="Color del tipo">
                        <button class="btn btn-xs btn-primary" type="submit" title="Guardar cambios" aria-label="Guardar cambios"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
                    </form>
                </td>
                <td class="num mono"><?php echo (int)($conteo[$idT] ?? 0); ?></td>
                <td>
                    <?php if ((int)($conteo[$idT] ?? 0) === 0): ?>
                    <form class="inline" method="POST" action="/balance/tipo/eliminar" data-confirm="Eliminar tipo|Ningún frente lo usa; se quitará del catálogo.|Eliminar">
                        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
                        <input type="hidden" name="id" value="<?php echo $idT; ?>">
                        <button class="btn btn-xs btn-ghost" type="submit" title="Eliminar tipo" aria-label="Eliminar tipo"><i class="fa-solid fa-trash-can"></i></button>
                    </form>
                    <?php else: ?><span class="text-muted text-sm">en uso</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="solo-movil text-muted text-sm"><i class="fa-solid fa-arrows-left-right"></i> Desliza la tabla para editar nombre, orden y color.</p>
    <?php else: ?><p class="text-muted">Aún no hay tipos. Crea el primero para poder agregar frentes.</p><?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div>

<?php if ($puedeEditar && $f): ?>
<form method="POST" action="/balance/generar" class="card co-form suite-form" id="bal-generador">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <h3><i class="fa-solid fa-wand-magic-sparkles"></i> Generar actividades por frente</h3>
    <p class="text-muted text-sm">Crea una actividad del mismo oficio en cada frente, espaciadas por el takt.</p>
    <div class="co-campos">
        <label>Partida / oficio *<select name="partida_id" required><option value="">— Elige —</option><?php foreach ($partidas as $p): ?><option value="<?php echo (int)$p->id; ?>"><?php echo s($p->codigo . ' ' . $p->nombre); ?></option><?php endforeach; ?></select></label>
        <label>Nombre del oficio *<input type="text" name="nombre" required maxlength="200" placeholder="Estructura, instalaciones, acabados…"></label>
        <label>Prefijo de código<input type="text" name="codigo" maxlength="40" placeholder="Se usa el código de la partida"></label>
        <label>Duración por frente (días hábiles)<input type="number" name="duracion" min="1" value="5"></label>
        <label>Takt (días entre frentes)<input type="number" name="takt" min="0" value="5"></label>
        <label>Inicio del primer frente<input type="date" name="inicio" value="<?php echo date('Y-m-d'); ?>" required></label>
        <label class="co-ancho">Frentes a generar</label>
        <div class="co-ancho" style="display:flex;gap:1rem;flex-wrap:wrap"><?php foreach ($f as $fr): ?><label class="co-check"><input type="checkbox" name="frentes[]" value="<?php echo (int)$fr['id']; ?>" checked> <?php echo s($fr['nombre']); ?></label><?php endforeach; ?></div>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Generar actividades</button>
</form>
<?php endif; ?>

<div class="card bal-chart">
    <div class="bal-chart-cab">
        <div><h3><i class="fa-solid fa-chart-column"></i> Diagrama de línea de balance</h3><p class="text-muted text-sm">Frentes en vertical, tiempo en horizontal. Cada línea es un oficio.</p></div>
        <?php if ($lineas): ?><div class="bal-leyenda"><?php foreach ($lineas as $l): ?><span><i style="background:<?php echo s($l['color']); ?>"></i><?php echo s($l['nombre']); ?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
    <?php if (!$rango || !$lineas): ?>
        <?php echo $puedeEditar
            ? estado_vacio('fa-chart-column', 'Aún no hay actividades ligadas a frentes', 'Genera un oficio abajo o asigna frentes a las actividades del Programa.', ['texto' => 'Generar actividades', 'url' => '#bal-generador', 'icono' => 'fa-wand-magic-sparkles'])
            : estado_vacio('fa-chart-column', 'Aún no hay actividades ligadas a frentes', 'Cuando el equipo asigne frentes a las actividades del Programa, aquí aparecerá la línea de balance.'); ?>
    <?php else:
        $w = 1120; $h = max(240, count($f) * 52 + 80);
        $x0 = 180; $x1 = $w - 30;
        $ini = strtotime($rango['inicio']); $fin = strtotime($rango['fin']);
        $dias = max(1, ($fin - $ini) / 86400);
        $x = fn($d) => $x0 + ((strtotime($d) - $ini) / 86400) / $dias * ($x1 - $x0);
        $y = fn($fid) => 45 + ($idxF[$fid] ?? 0) * 52;
    ?>
    <div class="bal-chart-scroll"><svg class="bal-svg" viewBox="0 0 <?php echo $w; ?> <?php echo $h; ?>" width="100%" height="<?php echo $h; ?>" role="img" aria-label="Línea de balance">
        <?php foreach ($f as $fr): $yy = $y((int)$fr['id']); ?><line x1="<?php echo $x0; ?>" y1="<?php echo $yy; ?>" x2="<?php echo $x1; ?>" y2="<?php echo $yy; ?>" stroke="currentColor" stroke-opacity=".18"/><text x="12" y="<?php echo $yy + 4; ?>" font-size="12" fill="currentColor"><?php echo s($fr['nombre']); ?></text><?php endforeach; ?>
        <?php $mes = strtotime(date('Y-m-01', $ini)); while ($mes <= $fin): $xx = $x(date('Y-m-d', $mes)); ?><line x1="<?php echo $xx; ?>" y1="30" x2="<?php echo $xx; ?>" y2="<?php echo $h - 30; ?>" stroke="currentColor" stroke-opacity=".09"/><text x="<?php echo $xx + 3; ?>" y="<?php echo $h - 10; ?>" font-size="10" fill="currentColor"><?php echo date('M Y', $mes); ?></text><?php $mes = strtotime('+1 month', $mes); endwhile; ?>
        <?php foreach ($lineas as $l): $pts = []; foreach ($l['puntos'] as $pt) $pts[] = $x($pt['inicio']) . ',' . $y($pt['frente_id']); ?>
            <polyline points="<?php echo implode(' ', $pts); ?>" fill="none" stroke="<?php echo s($l['color']); ?>" stroke-width="3" stroke-linejoin="round"/>
            <?php foreach ($l['puntos'] as $pt): ?><circle cx="<?php echo $x($pt['inicio']); ?>" cy="<?php echo $y($pt['frente_id']); ?>" r="6" fill="<?php echo s($l['color']); ?>" stroke="var(--surface)" stroke-width="2"><title><?php echo s($l['nombre'] . ' · ' . $pt['frente'] . ': ' . formatear_fecha($pt['inicio']) . ' → ' . formatear_fecha($pt['fin']) . ' (' . $pt['progreso'] . '%)'); ?></title></circle><?php endforeach; ?>
            <?php $ultimo = end($l['puntos']); ?><text x="<?php echo $x($ultimo['fin']) + 8; ?>" y="<?php echo $y($ultimo['frente_id']) + 4; ?>" font-size="11" fill="<?php echo s($l['color']); ?>"><?php echo s($l['nombre']); ?></text>
        <?php endforeach; ?>
    </svg></div>
    <p class="text-muted text-sm bal-chart-pie">Takt estimado: <b><?php echo $datos['takt'] === null ? '—' : (int)$datos['takt'] . ' días'; ?></b>. Cada círculo es un frente; la línea es el oficio.</p>
    <?php endif; ?>
</div>

<?php if (!empty($datos['conflictos'])): ?>
<div class="card bal-conflictos">
    <div class="bal-chart-cab">
        <div><h3><i class="fa-solid fa-triangle-exclamation text-danger"></i> Cruces entre oficios</h3><p class="text-muted text-sm">El sistema avisa; puede ser una decisión consciente de trabajar en paralelo.</p></div>
        <span class="badge badge-pdf"><?php echo count($datos['conflictos']); ?> cruce(s)</span>
    </div>
    <div class="tabla-wrapper"><table class="tabla tabla-ancha"><thead><tr><th>Frente</th><th>Oficio anterior</th><th>Termina</th><th>Oficio posterior</th><th>Empieza</th><th>Desfase</th></tr></thead><tbody>
        <?php foreach ($datos['conflictos'] as $c): $dif = (int)round((strtotime($c['inicio_despues']) - strtotime($c['fin_antes'])) / 86400); ?><tr><td><?php echo s($c['frente']); ?></td><td><?php echo s($c['antes']); ?></td><td class="mono"><?php echo s(formatear_fecha($c['fin_antes'], 'd/m/Y')); ?></td><td><?php echo s($c['despues']); ?></td><td class="mono text-danger"><?php echo s(formatear_fecha($c['inicio_despues'], 'd/m/Y')); ?></td><td class="mono <?php echo $dif < 0 ? 'text-danger' : 'text-warning'; ?>"><?php echo $dif > 0 ? '+' : ''; ?><?php echo $dif; ?> d</td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if (!empty($datos['huecos'])): ?><div class="alerta alerta-aviso"><strong>Frentes sin actividad:</strong> <?php echo s(implode(', ', $datos['huecos'])); ?>.</div><?php endif; ?>
</div>
