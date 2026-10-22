<?php
/** Inicio. Variables: $usuario, $proyectos, $totalProyectos, $inicio [bloques, proyectos_n, propia], $activos, $datos, $novedades, $personalizando */
use Classes\InicioUsuario;
$fechaCorta = fn($f) => $f ? date('d/m/y', strtotime($f)) : '—';
?>
<div class="dashboard-hero">
    <h2><?php echo s(t('Bienvenido, {nombre}', ['nombre' => explode(' ', $usuario['nombre'])[0]])); ?></h2>
    <p class="text-muted"><?php echo s(t('Panel de control de programas de obra')); ?>
        <a href="/dashboard?personalizar=1#inicio-personalizar" class="btn btn-sm btn-ghost" style="margin-left:1rem"><i class="fa-solid fa-sliders"></i> <?php echo s(t('Personalizar inicio')); ?></a></p>
</div>

<details class="card inicio-personalizar" id="inicio-personalizar"<?php echo $personalizando ? ' open' : ''; ?>>
    <summary><strong><?php echo s(t('Personalizar mi inicio')); ?></strong> <small class="text-muted"><?php echo s(t('· elige qué bloques ves y en qué orden (solo afecta tu cuenta)')); ?></small></summary>
    <form method="POST" action="/inicio/guardar" class="formulario" id="inicio-form">
        <ul id="inicio-bloques" class="msr-cols">
            <?php foreach ($inicio['bloques'] as $b): [$tit, $desc] = InicioUsuario::BLOQUES[$b['k']]; ?>
                <li draggable="true">
                    <span class="col-asa" title="Arrastra para mover">☰</span>
                    <input type="hidden" data-f="k" value="<?php echo s($b['k']); ?>">
                    <label class="campo-check" style="flex:1"><input type="checkbox" data-f="on" value="1"<?php echo $b['on'] ? ' checked' : ''; ?>> <strong><?php echo s($tit); ?></strong> <small class="text-muted">· <?php echo s($desc); ?></small></label>
                    <button type="button" class="btn btn-sm btn-ghost" data-mover="-1" title="Subir">↑</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-mover="1" title="Bajar">↓</button>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="campo" style="max-width:24rem"><label for="proyectos_n"><?php echo s(t('Proyectos que se muestran en «Proyectos recientes»')); ?></label>
            <select id="proyectos_n" name="proyectos_n"><?php foreach (InicioUsuario::CANTIDADES as $n): ?><option value="<?php echo $n; ?>"<?php echo $inicio['proyectos_n'] === $n ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></div>
        <p style="margin-top:1.4rem"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?php echo s(t('Guardar')); ?></button>
            <?php if ($inicio['propia']): ?><button type="submit" name="restablecer" value="1" class="btn btn-ghost" formnovalidate><?php echo s(t('Volver al inicio de siempre')); ?></button><?php endif; ?></p>
    </form>
</details>
<script>
(() => {
    const lista = document.getElementById('inicio-bloques');
    const renumerar = () => [...lista.children].forEach((li, i) => li.querySelectorAll('[data-f]').forEach((inp) => { inp.name = `bloques[${i}][${inp.dataset.f}]`; }));
    lista.addEventListener('click', (e) => {
        const b = e.target.closest('[data-mover]'); if (!b) return;
        const li = b.closest('li'), d = +b.dataset.mover;
        if (d < 0 && li.previousElementSibling) li.previousElementSibling.before(li);
        if (d > 0 && li.nextElementSibling) li.nextElementSibling.after(li);
        renumerar();
    });
    lista.querySelectorAll('li').forEach((li) => {
        li.addEventListener('dragstart', (e) => { li.classList.add('arrastrando'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', ''); });
        li.addEventListener('dragend', () => { li.classList.remove('arrastrando'); renumerar(); });
    });
    lista.addEventListener('dragover', (e) => {
        e.preventDefault();
        const mov = lista.querySelector('.arrastrando'); if (!mov) return;
        const sig = [...lista.children].filter((x) => x !== mov).find((x) => e.clientY < x.getBoundingClientRect().top + x.offsetHeight / 2);
        sig ? sig.before(mov) : lista.append(mov);
    });
    renumerar();
})();
</script>

<?php if (!$activos): ?>
    <div class="card empty-state"><p><?php echo s(t('No tienes ningún bloque activo. Abre «Personalizar inicio» para elegir qué ver.')); ?></p></div>
<?php endif; ?>

<?php foreach ($activos as $bloque): switch ($bloque): ?>
<?php case 'resumen': ?>
<div class="grid-stats">
    <div class="stat-card"><div class="stat-label"><?php echo s(t('Proyectos')); ?></div><div class="stat-valor"><?php echo s($totalProyectos); ?></div></div>
    <div class="stat-card"><div class="stat-label"><?php echo s(t('Actividades de hoy')); ?></div><div class="stat-valor"><?php echo (int)$datos['n_hoy']; ?></div></div>
    <div class="stat-card"><div class="stat-label"><?php echo s(t('Atrasadas')); ?></div><div class="stat-valor<?php echo $datos['n_atrasadas'] ? ' text-danger' : ''; ?>"><?php echo (int)$datos['n_atrasadas']; ?></div></div>
</div>
<?php break; case 'hoy': case 'atrasadas': $filas = $datos[$bloque]; ?>
<section class="card inicio-bloque">
    <h3><i class="fa-solid <?php echo $bloque === 'hoy' ? 'fa-sun' : 'fa-triangle-exclamation'; ?>"></i> <?php echo s(InicioUsuario::BLOQUES[$bloque][0]); ?> <small class="text-muted">· <?php echo (int)$datos[$bloque === 'hoy' ? 'n_hoy' : 'n_atrasadas']; ?></small></h3>
    <?php if (!$filas): ?><p class="text-muted"><?php echo $bloque === 'hoy' ? 'Nada programado para hoy.' : 'Sin actividades atrasadas. 👏'; ?></p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th><?php echo s(t('Actividad')); ?></th><th class="hide-m"><?php echo s(t('Proyecto')); ?></th><th class="hide-m"><?php echo s(t('Fin')); ?></th><th><?php echo s(t('Avance')); ?></th></tr></thead>
        <tbody><?php foreach ($filas as $f): ?>
            <tr>
                <td><a href="/programa?proyecto=<?php echo (int)$f['proyecto_id']; ?>#fila-a<?php echo (int)$f['id']; ?>"><span class="mono text-sm"><?php echo s($f['codigo']); ?></span> <?php echo s($f['nombre']); ?></a></td>
                <td class="hide-m"><?php echo s($f['proyecto']); ?></td>
                <td class="mono hide-m"><?php echo $fechaCorta($f['fecha_fin']); ?></td>
                <td class="mono"><?php echo (int)$f['progreso']; ?>%</td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php break; case 'novedades': ?>
<section class="card inicio-bloque">
    <h3><i class="fa-solid fa-bullhorn"></i> <?php echo s(t('Novedades de la plataforma')); ?></h3>
    <?php foreach ($novedades as $n): ?>
        <p><strong><?php echo s($n['titulo']); ?></strong><?php echo !empty($n['nueva']) ? ' <span class="cb-tag">Nueva</span>' : ''; ?> <small class="text-muted">· <?php echo formatear_fecha(substr($n['fecha'], 0, 10)); ?></small><br><span class="text-sm"><?php echo s($n['texto']); ?></span></p>
    <?php endforeach; ?>
    <a href="/novedades" class="btn btn-sm btn-ghost"><?php echo s(t('Ver todas')); ?></a>
</section>
<?php break; case 'atajos': ?>
<section class="card inicio-bloque">
    <h3><i class="fa-solid fa-bolt"></i> <?php echo s(t('Accesos rápidos')); ?></h3>
    <div class="inicio-atajos">
        <?php if (puede_crear_proyecto()): ?><a href="/proyectos/crear" class="btn btn-primary"><?php echo s(t('+ Nuevo proyecto')); ?></a><?php endif; ?>
        <?php if (!is_visitante()): ?><a href="/plantillas-proyecto" class="btn btn-ghost"><i class="fa-solid fa-copy"></i> <?php echo s(t('Plantillas de proyecto')); ?></a><?php endif; ?>
        <a href="/proyectos" class="btn btn-ghost"><i class="fa-solid fa-folder-open"></i> <?php echo s(t('Todos los proyectos')); ?></a>
        <a href="/manual" class="btn btn-ghost"><i class="fa-solid fa-book"></i> <?php echo s(t('Manual')); ?></a>
        <a href="/novedades" class="btn btn-ghost"><i class="fa-solid fa-bullhorn"></i> <?php echo s(t('Novedades')); ?></a>
    </div>
</section>
<?php break; case 'proyectos': ?>
<div class="page-header">
    <h3><?php echo s(t('Proyectos Recientes')); ?></h3>
    <?php if (puede_crear_proyecto()): ?>
        <a href="/proyectos/crear" class="btn btn-primary"><?php echo s(t('+ Nuevo Proyecto')); ?></a>
    <?php endif; ?>
</div>

<div class="grid-proyectos">
    <?php if (empty($proyectos)): ?>
        <div class="card empty-state">
            <p><?php echo is_visitante() ? 'Aún no tienes proyectos asignados.' : 'No hay proyectos registrados aún.'; ?></p>
            <?php if (puede_crear_proyecto()): ?>
                <a href="/proyectos/crear" class="btn btn-primary"><?php echo s(t('Crear el primero')); ?></a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach (array_slice($proyectos, 0, $inicio['proyectos_n']) as $proyecto): ?>
            <div class="card proyecto-card">
                <div class="proyecto-header" style="border-left-color: <?php echo s($proyecto->color); ?>">
                    <div class="proyecto-codigo mono"><?php echo s($proyecto->codigo); ?></div>
                    <h4><?php echo s($proyecto->nombre); ?></h4>
                    <p class="text-muted text-sm"><?php echo s($proyecto->ubicacion); ?></p>
                </div>
                <div class="proyecto-body">
                    <div class="progreso-row">
                        <span class="text-sm"><?php echo s(t('Avance General')); ?></span>
                        <span class="mono text-accent"><?php echo s($proyecto->getProgreso()); ?>%</span>
                    </div>
                    <div class="barra-progreso">
                        <div class="barra-fill" style="width: <?php echo s($proyecto->getProgreso()); ?>%"></div>
                    </div>
                    <div class="proyecto-meta">
                        <span class="text-sm">Inicio: <?php echo formatear_fecha($proyecto->fecha_inicio); ?></span>
                        <span class="text-sm">Fin: <?php echo formatear_fecha($proyecto->fecha_fin); ?></span>
                    </div>
                </div>
                <?php $saludCompacto = true; include __DIR__ . '/../salud/_semaforo.php'; ?>
                <?php include __DIR__ . '/../revisiones/_mini.php'; ?>
                <div class="proyecto-acciones">
                    <a href="<?php echo s(url_inicio_proyecto((int)$proyecto->id)); ?>" class="btn btn-sm btn-primary"><?php echo s(t('Ver Proyecto')); ?></a>
                    <?php if (puede_descargar()): ?>
                        <a href="/ajustes?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-ghost"><?php echo s(t('Ajustes')); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (count($proyectos) > $inicio['proyectos_n']): ?>
    <div class="text-center mt-3">
        <a href="/proyectos" class="btn btn-ghost">Ver todos (<?php echo s(count($proyectos)); ?>)</a>
    </div>
<?php endif; ?>

<?php break; endswitch; endforeach; ?>
