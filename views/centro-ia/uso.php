<?php // Variables: $proyecto, $dias, $u
use Classes\UsoIA;
$pid = (int)$proyecto->id;
$t = $u['total'];
$fmtN = fn($n) => number_format((float)$n, 0, '.', ',');
$periodos = [1 => 'Hoy y ayer', 7 => 'Últimos 7 días', 30 => 'Últimos 30 días', 90 => 'Últimos 90 días'];
$tabla = function (array $filas, string $clave, callable $nombre) use ($fmtN) { ?>
    <div class="prg-tabla-wrap">
        <table class="prg-tabla">
            <thead><tr><th><?php echo $clave === 'herramienta' ? 'Herramienta' : ($clave === 'modelo' ? 'Modelo' : 'Día'); ?></th><th>Llamadas</th><th>Errores</th><th>Tiempo total</th><th>Promedio</th><th>Más lenta</th><th>Texto enviado</th><th>Texto recibido</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td><?php echo $nombre($f); ?></td>
                    <td><?php echo (int)$f['llamadas']; ?></td>
                    <td><?php echo (int)$f['errores'] ?: '—'; ?></td>
                    <td><?php echo s(UsoIA::duracion((float)$f['ms'])); ?></td>
                    <td><?php echo s(UsoIA::duracion((float)$f['promedio_ms'])); ?></td>
                    <td><?php echo s(UsoIA::duracion((float)$f['max_ms'])); ?></td>
                    <td><?php echo $fmtN($f['entrada']); ?> car.</td>
                    <td><?php echo $fmtN($f['salida']); ?> car.</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php };
?>
<?php $pestana = 'uso'; include __DIR__ . '/../centro-ia/_pestanas.php'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-stopwatch"></i> <?php echo s(t('Uso de la IA')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · cuántas llamadas hizo cada herramienta al modelo, con cuál modelo y cuánto tardó. Lo informa la app de escritorio (el modelo corre en tu equipo); el texto se mide en caracteres porque no todos los modelos informan tokens.</p>
    </div>
    <form method="get" action="/uso-ia" class="inc-filtros">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label>Periodo <select name="dias" onchange="this.form.submit()"><?php foreach ($periodos as $d => $n): ?><option value="<?php echo $d; ?>"<?php echo $d === $dias ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    </form>
</div>

<?php if (empty($t['llamadas'])): ?>
    <div class="card prg-vacio"><h3>Sin llamadas registradas</h3><p class="text-muted">Cuando uses las herramientas de IA desde la app de escritorio (con la sesión de administrador abierta), aquí aparecerá el detalle.</p></div>
<?php else: ?>
    <div class="prg-resumen inc-resumen">
        <div class="card"><small>Llamadas</small><strong><?php echo (int)$t['llamadas']; ?></strong></div>
        <div class="card"><small>Con error</small><strong><?php echo (int)$t['errores']; ?></strong></div>
        <div class="card"><small>Tiempo total</small><strong><?php echo s(UsoIA::duracion((float)$t['ms'])); ?></strong></div>
        <div class="card"><small>Promedio por llamada</small><strong><?php echo s(UsoIA::duracion((float)$t['promedio_ms'])); ?></strong></div>
    </div>
    <section class="card ia-bloque"><h3>Por herramienta</h3><?php $tabla($u['herramientas'], 'herramienta', fn($f) => s(UsoIA::nombre($f['herramienta']))); ?></section>
    <section class="card ia-bloque"><h3>Por modelo</h3><?php $tabla($u['modelos'], 'modelo', fn($f) => s($f['modelo'] ?: '—') . ($f['proveedor'] ? ' <small class="text-muted">· ' . s($f['proveedor']) . '</small>' : '')); ?></section>
    <section class="card ia-bloque"><h3>Por día</h3><?php $tabla($u['dias'], 'dia', fn($f) => s(date('d/m/Y', strtotime($f['dia'])))); ?></section>
<?php endif; ?>
