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
        <h2><i class="fa-solid fa-clock-rotate-left"></i> <?php echo s(t('Auditoría de la IA')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · cada cambio que aplican, revierten o rechazan las herramientas de IA queda registrado aquí (quién, cuándo, qué modelo y los valores de antes y después). <strong>El registro no se puede editar.</strong> Los cambios hechos a mano están en <a href="/historial?proyecto=<?php echo $pid; ?>">Historial de cambios</a>.</p>
    </div>
    <div class="inc-acciones">
        <a class="btn btn-ghost" href="/historial-cambios/exportar?<?php echo s($q(['formato' => 'xlsx'])); ?>"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a class="btn btn-ghost" href="/historial-cambios/exportar?<?php echo s($q(['formato' => 'pdf'])); ?>"><i class="fa-solid fa-file-pdf"></i> PDF</a>
    </div>
</div>

<details class="card ia-tanda" id="tanda">
    <summary><strong><i class="fa-solid fa-rotate-left"></i> Revertir lo que hizo la IA</strong> <span class="text-muted text-sm">— de golpe, desde una hora</span></summary>
    <p class="text-muted text-sm">Deshace los cambios aplicados desde la fecha y hora que elijas (inconsistencias, análisis de materiales, partidas del Programador y normativa vinculada), del más reciente al más antiguo. Primero ves la lista y eliges qué revertir; cada reversión queda en este historial.</p>
    <div class="ia-tanda__form">
        <label>Desde <input type="datetime-local" id="tanda-desde" value="<?php echo date('Y-m-d') . 'T00:00'; ?>"></label>
        <button type="button" class="btn btn-sm btn-ghost" data-tanda-rapido="hoy">Hoy</button>
        <button type="button" class="btn btn-sm btn-ghost" data-tanda-rapido="1">Última hora</button>
        <button type="button" class="btn btn-sm btn-ghost" data-tanda-rapido="24">Últimas 24 h</button>
        <button type="button" class="btn btn-primary btn-sm" id="tanda-ver"><i class="fa-solid fa-list-check"></i> Ver qué se revertiría</button>
    </div>
    <div id="tanda-res" aria-live="polite"></div>
</details>
<script>
    (function () {
        var desde = document.getElementById('tanda-desde'), res = document.getElementById('tanda-res'), pid = '<?php echo $pid; ?>';
        var local = function (d) { var z = d.getTimezoneOffset() * 60000; return new Date(d - z).toISOString().slice(0, 16); };
        var esc = function (t) { var e = document.createElement('div'); e.textContent = t == null ? '' : t; return e.innerHTML; };
        document.querySelectorAll('[data-tanda-rapido]').forEach(function (b) {
            b.addEventListener('click', function () {
                var v = b.dataset.tandaRapido, d = new Date();
                if (v === 'hoy') d.setHours(0, 0, 0, 0); else d = new Date(d.getTime() - Number(v) * 3600000);
                desde.value = local(d);
                document.getElementById('tanda-ver').click();
            });
        });
        function enviar(extra) {
            var d = new FormData();
            d.append('proyecto', pid); d.append('desde', desde.value);
            Object.keys(extra).forEach(function (k) { [].concat(extra[k]).forEach(function (v) { d.append(k + (Array.isArray(extra[k]) ? '[]' : ''), v); }); });
            return fetch('/historial-cambios/revertir', { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        }
        document.getElementById('tanda-ver').addEventListener('click', function () {
            res.textContent = 'Buscando…';
            enviar({ modo: 'vista' }).then(function (j) {
                if (!j.success) { res.textContent = j.error || 'No se pudo consultar'; return; }
                if (!j.items.length) { res.innerHTML = '<p class="text-muted">No hay cambios de la IA por revertir desde esa hora (o ya se revirtieron).</p>'; return; }
                res.innerHTML = '<ul class="ia-tanda__lista">' + j.items.map(function (x) {
                    return '<li><label><input type="checkbox" value="' + x.id + '" checked> <span><b>' + esc(x.resumen) + '</b><br><small class="text-muted">' + esc(x.creado) + ' · ' + esc(x.herramienta) + (x.entidad ? ' · ' + esc(x.entidad) : '') + '</small></span></label></li>';
                }).join('') + '</ul><button type="button" class="btn btn-primary" id="tanda-ok"><i class="fa-solid fa-rotate-left"></i> Revertir lo marcado</button> <span class="inc-msg" id="tanda-msg"></span>';
                document.getElementById('tanda-ok').addEventListener('click', async function () {
                    var ids = [].map.call(res.querySelectorAll('input:checked'), function (c) { return c.value; });
                    if (!ids.length) return;
                    if (!await window.confirmarModal({ titulo: '¿Revertir ' + ids.length + ' cambio(s)?', mensaje: 'Los datos del proyecto volverán a como estaban antes de que la IA los cambiara. Las propuestas vuelven a quedar pendientes.', aceptar: 'Revertir', tono: 'peligro' })) return;
                    this.disabled = true;
                    enviar({ modo: 'aplicar', ids: ids }).then(function (r) {
                        document.getElementById('tanda-msg').textContent = r.mensaje || r.error || '';
                        if (r.success) setTimeout(function () { location.reload(); }, 1800);
                    });
                });
            }).catch(function () { res.textContent = 'Error de conexión'; });
        });
    })();
</script>

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
            <?php foreach ($filas as $x): $link = $enlaceEntidad($x); $elemento = \Classes\CambiosLegibles::elemento($x['entidad_tipo'], (int)$x['entidad_id'], $x['entidad_nombre'], $pid); ?>
                <tr>
                    <td class="fecha"><?php echo s($fmt($x['creado'])); ?></td>
                    <td><?php echo s($x['usuario'] ?: '—'); ?></td>
                    <td><?php echo s(Auditoria::HERRAMIENTAS[$x['herramienta']] ?? $x['herramienta']); ?><?php echo $x['modelo'] ? '<br><small class="text-muted">' . s($x['modelo']) . '</small>' : ''; ?></td>
                    <td><span class="hist-ev ev-<?php echo s($x['evento']); ?>"><?php echo s(Auditoria::EVENTOS[$x['evento']] ?? $x['evento']); ?></span></td>
                    <td><?php if ($x['entidad_tipo'] !== ''): ?><small class="text-muted"><?php echo s($x['entidad_tipo']); ?></small><br><?php echo $link ? '<a href="' . s($link) . '">' . s($elemento) . '</a>' : s($elemento); ?> <a class="text-sm" title="Historial de esta entidad" href="/historial-cambios?proyecto=<?php echo $pid; ?>&entidad_tipo=<?php echo s($x['entidad_tipo']); ?>&entidad_id=<?php echo (int)$x['entidad_id']; ?>"><i class="fa-solid fa-clock-rotate-left"></i></a><?php else: ?>—<?php endif; ?></td>
                    <td>
                        <?php echo s($x['resumen']); ?>
                        <?php if ($x['antes'] !== null || $x['despues'] !== null): ?>
                            <details class="hist-det"><summary>Antes / después</summary>
                                <?php $antes = $x['antes']; $despues = $x['despues']; include __DIR__ . '/../templates/cambios-antes-despues.php'; ?>
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
