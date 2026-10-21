<?php // Variables: $proyecto, $filtros, $lista, $analisis, $contratistas, $actividades, $puede, $mensaje
use Classes\Punch;
$pid = (int)$proyecto->id;
$fmt = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$sevCls = ['critica' => 'critica', 'alta' => 'alta', 'media' => 'media', 'baja' => 'baja'];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-clipboard-check"></i> <?php echo s(t('Punch list')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · pendientes de calidad con responsable, fecha límite y fotos antes/después. Haz una inspección con lista de verificación y cada falla se vuelve un pendiente.</p>
    </div>
    <div class="inc-acciones">
        <?php if ($puede): ?><a class="btn btn-primary" href="/punch/inspeccion?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-list-check"></i> Nueva inspección</a><?php endif; ?>
        <a class="btn btn-ghost" href="/punch/pdf?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        <?php if (is_admin()): ?><a class="btn btn-ghost" href="/informes-ia?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-file-lines"></i> Informes de IA</a><?php endif; ?>
    </div>
</div>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<div class="prg-resumen inc-resumen">
    <div class="card"><small>Pendientes</small><strong><?php echo (int)$analisis['pendientes']; ?></strong><span>de <?php echo (int)$analisis['total']; ?></span></div>
    <div class="card"><small>Vencidos</small><strong class="<?php echo $analisis['vencidos'] ? 'prg-peor' : ''; ?>"><?php echo (int)$analisis['vencidos']; ?></strong></div>
    <div class="card"><small>Cerrados</small><strong class="prg-mejor"><?php echo (int)$analisis['cerrados']; ?></strong></div>
    <div class="card"><small>Antigüedad media de los pendientes</small><strong><?php echo $analisis['edad'] !== null ? $analisis['edad'] . ' d' : '—'; ?></strong></div>
</div>

<?php if ($analisis['total']): ?>
<details class="card prg-seccion">
    <summary><strong>Dónde se repiten los pendientes</strong> (para atacar la causa, no el síntoma)</summary>
    <div class="inf-gen" style="margin-top:1rem">
        <?php foreach (['categorias' => 'Por categoría', 'contratistas' => 'Por contratista', 'ubicaciones' => 'Por ubicación'] as $k => $tit): ?>
            <div><h4><?php echo $tit; ?></h4><table class="prg-tabla"><thead><tr><th></th><th class="num">Total</th><th class="num">Pendientes</th><th class="num">Graves</th></tr></thead><tbody>
                <?php foreach ($analisis[$k] as $r): ?><tr><td><?php echo s($r['clave']); ?></td><td class="num"><?php echo (int)$r['n']; ?></td><td class="num"><?php echo (int)$r['pendientes']; ?></td><td class="num"><?php echo (int)$r['graves']; ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<?php if ($puede): ?>
<details class="card prg-seccion" id="categorias-punch"><summary><strong>Categorías</strong> <small class="text-muted">· crea, renombra o elimina las categorías de los pendientes</small></summary>
    <?php $catsUso = \Classes\Catalogo::categoriasConUso((int)$pid, 'punch'); ?>
    <ul class="hist-lista" style="margin-top:1rem">
        <?php foreach ($catsUso as $nom => $inf): ?>
            <li style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
                <form method="post" action="/punch/categoria" style="display:flex;gap:.4rem;margin:0"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="anterior" value="<?php echo s($nom); ?>">
                    <input type="text" name="nombre" value="<?php echo s($nom); ?>" maxlength="60" required><button class="btn btn-sm btn-ghost" type="submit">Renombrar</button></form>
                <small class="text-muted"><?php echo (int)$inf['usos']; ?> pendiente(s)</small>
                <form method="post" action="/punch/categoria/eliminar" data-confirmar="<?php echo s($inf['usos'] ? '¿Eliminar «' . $nom . '»? Los ' . $inf['usos'] . ' pendiente(s) que la usan quedarán sin categoría.' : '¿Eliminar la categoría «' . $nom . '»?'); ?>" style="margin:0"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="nombre" value="<?php echo s($nom); ?>"><button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i></button></form>
            </li>
        <?php endforeach; ?>
        <?php if (!$catsUso): ?><li class="text-muted">Aún no hay categorías. Crea la primera o carga las de las plantillas de inspección.</li><?php endif; ?>
    </ul>
    <form method="post" action="/punch/categoria" class="inc-filtros" style="margin-top:1rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label>Nueva categoría <input type="text" name="nombre" maxlength="60" required></label>
        <button class="btn btn-primary" type="submit">Crear categoría</button></form>
    <form method="post" action="/punch/categorias/sugeridas" style="margin-top:.8rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><button class="btn btn-sm btn-ghost" type="submit">Cargar las categorías de las plantillas de inspección</button></form>
</details>
<details class="card prg-seccion"><summary><strong>Registrar un pendiente</strong></summary>
    <form method="post" action="/punch/guardar" enctype="multipart/form-data" class="inc-filtros" style="margin-top:1rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label style="flex:2">Pendiente <input type="text" name="titulo" required maxlength="200"></label>
        <label>Ubicación <input type="text" name="ubicacion" maxlength="200"></label>
        <label>Categoría <input type="text" name="categoria" maxlength="60" list="cats"><datalist id="cats"><?php foreach (Punch::categoriasDeProyecto((int)$pid) as $c): ?><option value="<?php echo s($c); ?>"><?php endforeach; ?></datalist></label>
        <label>Severidad <select name="severidad"><?php foreach (Punch::SEVERIDADES as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $k === 'media' ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
        <label>Responsable <input type="text" name="responsable" maxlength="150"></label>
        <label>Contratista <input type="text" name="contratista" maxlength="150" list="ctr"><datalist id="ctr"><?php foreach ($contratistas as $c): ?><option value="<?php echo s($c); ?>"><?php endforeach; ?></datalist></label>
        <label>Fecha límite <input type="date" name="fecha_limite"></label>
        <label>Actividad <select name="actividad_id"><option value="0">(ninguna)</option><?php echo opciones_actividades($actividades, null, 45); ?></select></label>
        <label style="flex-basis:100%">Descripción <textarea name="descripcion" rows="2" maxlength="3000"></textarea></label>
        <label>Foto «antes» <input type="file" name="foto_antes" accept="image/*"></label>
        <button class="btn btn-primary" type="submit">Registrar</button></form></details>
<?php endif; ?>

<form method="get" action="/punch" class="card inc-filtros"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label>Estado <select name="estado" onchange="this.form.submit()"><option value="pendientes"<?php echo $filtros['estado'] === 'pendientes' ? ' selected' : ''; ?>>Pendientes</option><option value="">Todos</option><?php foreach (Punch::ESTADOS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['estado'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Severidad <select name="severidad" onchange="this.form.submit()"><option value="">Todas</option><?php foreach (Punch::SEVERIDADES as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['severidad'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Contratista <select name="contratista" onchange="this.form.submit()"><option value="">Todos</option><?php foreach ($contratistas as $c): ?><option<?php echo $filtros['contratista'] === $c ? ' selected' : ''; ?>><?php echo s($c); ?></option><?php endforeach; ?></select></label>
    <label style="flex:1">Buscar <input type="search" name="q" value="<?php echo s($filtros['q']); ?>" placeholder="Título o ubicación"></label><button class="btn btn-ghost" type="submit">Filtrar</button>
    <?php if ($filtros['contratista'] !== ''): ?><a class="btn btn-sm btn-ghost" href="/punch/pdf?proyecto=<?php echo $pid; ?>&contratista=<?php echo urlencode($filtros['contratista']); ?>">PDF de <?php echo s($filtros['contratista']); ?></a><?php endif; ?></form>

<?php if (!$lista): ?><div class="card prg-vacio"><h3>Sin pendientes con estos filtros</h3><p class="text-muted">Registra uno arriba o haz una inspección.</p></div>
<?php else: ?><ul class="inc-lista" <?php echo pag_attr($proyecto); ?>><?php foreach ($lista as $x): ?>
    <li class="card inc-item sev-<?php echo $sevCls[$x['severidad']]; ?><?php echo in_array($x['estado'], ['cerrado', 'rechazado'], true) ? ' est-aceptada' : ''; ?>">
        <div class="inc-cab"><span class="prg-sev"><?php echo s(Punch::SEVERIDADES[$x['severidad']]); ?></span><a href="/punch/ver?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$x['id']; ?>"><strong><?php echo s($x['titulo']); ?></strong></a>
            <small class="text-muted"><?php echo s(Punch::ESTADOS[$x['estado']]); ?><?php echo $x['categoria'] ? ' · ' . s($x['categoria']) : ''; ?><?php echo $x['ubicacion'] ? ' · ' . s($x['ubicacion']) : ''; ?><?php echo $x['responsable'] || $x['contratista'] ? ' · ' . s($x['responsable'] ?: $x['contratista']) : ''; ?> · límite <?php echo s($fmt($x['fecha_limite'])); ?><?php echo $x['vencido'] ? ' <b class="prg-peor">VENCIDO</b>' : ''; ?></small></div>
        <?php if ($puede && in_array($x['estado'], ['abierto', 'en_proceso', 'corregido'], true)): ?><div class="inc-botones">
            <?php foreach (['abierto' => [['en_proceso', 'Empezar']], 'en_proceso' => [['corregido', 'Marcar corregido']], 'corregido' => [['cerrado', 'Verificado: cerrar'], ['abierto', 'Reabrir']]][$x['estado']] as [$e, $n]): ?>
                <form method="post" action="/punch/estado" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>"><input type="hidden" name="estado" value="<?php echo $e; ?>"><button class="btn btn-sm <?php echo $e === 'cerrado' ? 'btn-primary' : 'btn-ghost'; ?>" type="submit"><?php echo $n; ?></button></form>
            <?php endforeach; ?></div><?php endif; ?>
    </li><?php endforeach; ?></ul><?php endif; ?>
