<?php
/**
 * Historial de cambios del proyecto (sección Administración).
 * Variables: $proyecto, $pestana, $filtros, $filas, $conteos, $total, $pagina, $paginas, $usuarios, $herramientas, $puede
 */
use Classes\HistorialCambios;
use Controllers\HistorialCambiosController as Ctrl;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/historial?' . http_build_query(array_filter(['proyecto' => $pid] + $q + $filtros, fn($v) => $v !== '' && $v !== null));
$fmt = fn($d) => date('d/m/Y H:i', strtotime($d));
$hayFiltro = array_filter($filtros, fn($v) => $v !== '') !== [];
$exportar = fn(string $formato) => '/historial/exportar?' . http_build_query(array_filter(['proyecto' => $pid, 'pestana' => $pestana, 'formato' => $formato] + $filtros, fn($v) => $v !== ''));
$vacio = match ($pestana) {
    'aceptado' => ['Sin cambios aceptados', 'Cuando el administrador o el cliente acepten un cambio, queda aquí guardado con quién lo aceptó.'],
    'revertido' => ['Sin cambios revertidos', 'Si se cancela un cambio, el registro original aparece aquí junto con la nota de lo que se revirtió.'],
    default => ['No hay cambios pendientes', 'Todo lo que se cambie en las herramientas del proyecto aparecerá aquí para revisarlo: fecha, descripción, herramienta y qué hacer con él.'],
};
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-clock-rotate-left"></i> <?php echo s(t('Historial de cambios')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · todos los cambios hechos en el proyecto, con su fecha, su descripción y la herramienta donde se hicieron. <strong>El registro no se puede editar.</strong><?php if (is_admin()): ?> Lo que hizo la IA está en <a href="/historial-cambios?proyecto=<?php echo $pid; ?>">Auditoría de la IA</a>.<?php endif; ?></p>
    </div>
    <div class="inc-acciones">
        <?php if ($puede && $pestana === 'pendiente' && $conteos['pendiente']): ?>
            <button type="button" class="btn btn-ghost" id="hist-todo"><i class="fa-solid fa-check-double"></i> <?php echo s(t('Aceptar todo')); ?></button>
        <?php endif; ?>
        <?php if (puede_descargar()): ?>
            <a class="btn btn-ghost" href="<?php echo s($exportar('xlsx')); ?>"><i class="fa-solid fa-file-excel"></i> Excel</a>
            <a class="btn btn-ghost" href="<?php echo s($exportar('pdf')); ?>"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        <?php endif; ?>
    </div>
</div>

<nav class="centro-ia-tabs" aria-label="<?php echo s(t('Historial de cambios')); ?>">
    <?php foreach (Ctrl::PESTANAS as $k => [$icono, $nombre]): ?>
        <a href="<?php echo s($url(['pestana' => $k, 'pagina' => ''])); ?>" class="centro-ia-tabs__tab<?php echo $k === $pestana ? ' activa' : ''; ?>"<?php echo $k === $pestana ? ' aria-current="page"' : ''; ?>>
            <i class="fa-solid <?php echo $icono; ?>"></i> <?php echo s(t($nombre)); ?> <span class="hist-num" data-contador="<?php echo s($k); ?>"><?php echo (int)$conteos[$k]; ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<div id="hist-msg" class="alerta alerta-exito" hidden></div>

<?php if (!$puede): ?>
    <p class="text-muted text-sm"><i class="fa-solid fa-eye"></i> Solo lectura: aceptar o cancelar cambios lo hacen el administrador y el cliente dueño del proyecto.</p>
<?php endif; ?>

<form method="get" action="/historial" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="hidden" name="pestana" value="<?php echo s($pestana); ?>">
    <label>Herramienta <select name="herramienta" onchange="this.form.submit()"><option value="">Todas</option><?php foreach ($herramientas as $k => $n): ?><option value="<?php echo s($k); ?>"<?php echo $filtros['herramienta'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Evento <select name="evento" onchange="this.form.submit()"><option value="">Todos</option><?php foreach (HistorialCambios::EVENTOS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['evento'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Quién lo hizo <select name="usuario" onchange="this.form.submit()"><option value="">Todos</option><?php foreach ($usuarios as $u): ?><option<?php echo $filtros['usuario'] === $u ? ' selected' : ''; ?>><?php echo s($u); ?></option><?php endforeach; ?></select></label>
    <label>Desde <input type="date" name="desde" value="<?php echo s($filtros['desde']); ?>" onchange="this.form.submit()"></label>
    <label>Hasta <input type="date" name="hasta" value="<?php echo s($filtros['hasta']); ?>" onchange="this.form.submit()"></label>
    <label>Buscar <input type="search" name="q" value="<?php echo s($filtros['q']); ?>" placeholder="Descripción o elemento" autocomplete="off"></label>
    <button class="btn btn-sm btn-ghost" type="submit">Filtrar</button>
    <?php if ($hayFiltro): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($url(['pagina' => ''])); ?>">Quitar filtros</a><?php endif; ?>
</form>

<p class="text-muted"><?php echo (int)$total; ?> cambio(s)<?php echo $paginas > 1 ? " · página $pagina de $paginas" : ''; ?></p>

<?php if (!$filas): ?>
    <div class="card prg-vacio"><h3><?php echo s($vacio[0]); ?></h3><p class="text-muted"><?php echo s($vacio[1]); ?></p></div>
<?php else: ?>
    <div class="card prg-tabla-wrap">
        <table class="prg-tabla hist-tabla">
            <thead><tr><th><?php echo s(t('Fecha')); ?></th><th><?php echo s(t('Descripción del cambio')); ?></th><th><?php echo s(t('Herramienta')); ?></th><th><?php echo s(t('Acciones')); ?></th></tr></thead>
            <tbody>
            <?php foreach ($filas as $x): $esPendiente = $x['estado'] === 'pendiente'; ?>
                <tr data-id="<?php echo (int)$x['id']; ?>">
                    <td class="fecha"><?php echo s($fmt($x['creado'])); ?></td>
                    <td>
                        <strong class="hist-desc"><?php echo s($x['resumen']); ?></strong><br>
                        <small class="text-muted"><?php echo s($x['usuario'] ?: 'Sin usuario'); ?><?php echo $x['entidad_nombre'] !== '' ? ' · ' . s($x['entidad_nombre']) : ''; ?><?php echo $x['estado'] !== 'pendiente' && $x['estado_por'] !== '' ? ' · ' . s(t(HistorialCambios::ESTADOS[$x['estado']] ?? $x['estado'])) . ' por ' . s($x['estado_por']) : ''; ?></small>
                        <?php if ($x['antes'] !== null || $x['despues'] !== null): ?>
                            <details class="hist-det"><summary>Antes / después</summary>
                                <div class="hist-par">
                                    <div><b>Antes</b><pre><?php echo s($x['antes'] !== null ? json_encode($x['antes'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '—'); ?></pre></div>
                                    <div><b>Después</b><pre><?php echo s($x['despues'] !== null ? json_encode($x['despues'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '—'); ?></pre></div>
                                </div>
                            </details>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="bib-herr"><?php echo s($herramientas[$x['herramienta']] ?? $x['herramienta']); ?></span>
                        <br><span class="hist-ev ev-<?php echo s($x['evento']); ?>"><?php echo s(HistorialCambios::EVENTOS[$x['evento']] ?? $x['evento']); ?></span>
                    </td>
                    <td class="hist-acciones">
                        <a class="btn btn-sm btn-ghost" href="<?php echo s($x['enlace']); ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver detalle</a>
                        <?php if ($puede && $esPendiente): ?>
                            <button type="button" class="btn btn-sm btn-primary" data-aceptar><i class="fa-solid fa-check"></i> Aceptar</button>
                            <button type="button" class="btn btn-sm btn-danger" data-cancelar><i class="fa-solid fa-rotate-left"></i> Cancelar</button>
                        <?php else: ?>
                            <span class="hist-estado est-<?php echo s($x['estado']); ?>"><?php echo s(t(HistorialCambios::ESTADOS[$x['estado']] ?? $x['estado'])); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginas > 1): ?>
        <nav class="hist-pag">
            <?php if ($pagina > 1): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($url(['pagina' => $pagina - 1])); ?>">← Anterior</a><?php endif; ?>
            <span>Página <?php echo $pagina; ?> de <?php echo $paginas; ?></span>
            <?php if ($pagina < $paginas): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($url(['pagina' => $pagina + 1])); ?>">Siguiente →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<script>
(function () {
    var PID = <?php echo $pid; ?>;
    var PESTANA = '<?php echo s($pestana); ?>';
    var msg = document.getElementById('hist-msg');

    function decir(texto, tono) {
        if (!msg || !texto) return;
        msg.hidden = false;
        msg.className = 'alerta alerta-' + (tono || 'exito');
        msg.textContent = texto;
    }
    function contador(estado) { return document.querySelector('[data-contador="' + estado + '"]'); }
    function moverContador(desde, hacia, n) {
        var a = contador(desde), b = contador(hacia);
        if (a) a.textContent = Math.max(0, parseInt(a.textContent, 10) - n);
        if (b) b.textContent = parseInt(b.textContent, 10) + n;
    }
    function quitar(fila) {
        fila.style.transition = 'opacity .3s'; fila.style.opacity = '0';
        setTimeout(function () {
            fila.remove();
            var cuerpo = document.querySelector('.hist-tabla tbody');
            if (cuerpo && !cuerpo.children.length) location.reload();
        }, 320);
    }

    // Mensaje que deja «Aceptar todo» antes de recargar la página
    try {
        var guardado = sessionStorage.getItem('hist-msg');
        if (guardado) { sessionStorage.removeItem('hist-msg'); decir(guardado, 'exito'); }
    } catch (e) { /* sin storage */ }

    function post(ruta, datos) {
        var d = new FormData();
        d.append('proyecto', PID);
        Object.keys(datos).forEach(function (k) { d.append(k, datos[k]); });
        return fetch(ruta, { method: 'POST', body: d, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return { success: false, error: 'Sin conexión con el servidor' }; });
    }

    document.querySelectorAll('[data-aceptar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var fila = btn.closest('tr');
            btn.disabled = true;
            post('/api/historial/aceptar', { id: fila.dataset.id }).then(function (j) {
                if (!j.success) { btn.disabled = false; decir(j.error || 'No se pudo aceptar el cambio', 'error'); return; }
                decir(j.mensaje, 'exito');
                moverContador('pendiente', 'aceptado', 1);
                quitar(fila);
            });
        });
    });

    document.querySelectorAll('[data-cancelar]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var fila = btn.closest('tr');
            var si = await window.confirmarModal({
                titulo: '¿Cancelar este cambio?',
                mensaje: 'Los datos del proyecto vuelven a como estaban antes de ese cambio. El registro original queda guardado como «revertido».',
                aceptar: 'Cancelar el cambio', tono: 'peligro', iconoBoton: 'fa-rotate-left'
            });
            if (!si) return;
            btn.disabled = true;
            post('/api/historial/cancelar', { id: fila.dataset.id }).then(function (j) {
                if (!j.success) { btn.disabled = false; decir(j.error || 'No se pudo cancelar el cambio', 'error'); return; }
                decir((j.mensaje || 'Cambio cancelado') + (j.nota ? ' · ' + j.nota : ''), 'exito');
                moverContador('pendiente', 'revertido', 1);
                quitar(fila);
            });
        });
    });

    var todo = document.getElementById('hist-todo');
    if (todo) todo.addEventListener('click', async function () {
        var n = parseInt(contador('pendiente') ? contador('pendiente').textContent : '0', 10);
        var si = await window.confirmarModal({
            titulo: '¿Aceptar ' + n + ' cambio(s)?',
            mensaje: 'Pasan a «Cambios aceptados» y salen de esta lista. Los datos del proyecto no cambian.',
            aceptar: 'Aceptar todos'
        });
        if (!si) return;
        todo.disabled = true;
        post('/api/historial/aceptar', { todo: '1' }).then(function (j) {
            if (!j.success) { todo.disabled = false; decir(j.error || 'No se pudieron aceptar los cambios', 'error'); return; }
            try { sessionStorage.setItem('hist-msg', j.mensaje || 'Cambios aceptados'); } catch (e) { /* nada */ }
            location.reload();
        });
    });
})();
</script>
