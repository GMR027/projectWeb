<?php // Variables: $proyecto, $tab, $datos, $conteos, $ok, $error
$id = (int)$proyecto->id;
$tabs = \Controllers\DirectorioController::TABS;
$sinUso = fn(array $usos) => $usos ? implode(' · ', array_map(fn($h, $n) => s($h) . ' ' . (int)$n, array_keys($usos), $usos)) : '—';
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-address-book"></i> Directorio</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?><span class="hide-m"> · captura aquí una vez; Bitácora, Minutas, Redactor, SheetFlow, Submittals, Evidencias, Punch, Archivo y Materiales ofrecen estas listas</span></p>
    </div>
</div>
<nav class="centro-ia-tabs dir-tabs no-print" aria-label="Directorio">
    <?php foreach ($tabs as $k => [$nombre, $ic]): ?>
        <a href="/directorio?proyecto=<?php echo $id; ?>&tab=<?php echo $k; ?>" class="centro-ia-tabs__tab<?php echo $k === $tab ? ' activa' : ''; ?>"<?php echo $k === $tab ? ' aria-current="page"' : ''; ?>><i class="fa-solid <?php echo $ic; ?>"></i> <?php echo s($nombre); ?><?php if (isset($conteos[$k])): ?> <span class="text-muted">(<?php echo (int)$conteos[$k]; ?>)</span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($ok): ?><div class="alerta alerta-exito"><?php echo s($ok); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alerta alerta-error"><?php echo s($error); ?></div><?php endif; ?>

<?php if ($tab === 'personal'): ?>
<div class="card">
    <div class="la-controles" style="margin-bottom:1.2rem"><a href="/reportes/personal/crear?proyecto=<?php echo $id; ?>&volver=directorio" class="btn btn-primary">+ Nueva persona</a>
        <span class="text-muted text-sm">Se elige en Reporte diario, Minutas y como responsable de actividades.</span></div>
    <?php if (!$datos['personal']): ?><p class="text-muted">Aún no hay personal del proyecto.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla tabla-directorio"><thead><tr><th class="hide-m">Foto</th><th>Nombre</th><th class="hide-m">Tipo</th><th class="hide-m">Departamento</th><th class="hide-m">Cargo</th><th class="hide-m">Teléfono</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($datos['personal'] as $p): $c = $datos['contactos'][(int)$p->id] ?? []; ?>
        <tr>
            <td class="hide-m"><?php if ($p->imagen): ?><img class="rd-avatar" src="/uploads/personal/<?php echo s($p->imagen); ?>" alt=""><?php else: ?><span class="rd-avatar rd-avatar-vacio"><i class="fa-solid fa-user"></i></span><?php endif; ?></td>
            <td><strong><?php echo s($p->nombre); ?></strong><?php echo !empty($c['notas']) ? ' <i class="fa-regular fa-note-sticky text-muted" title="Tiene notas"></i>' : ''; ?>
                <div class="solo-movil text-sm text-muted"><?php echo s(implode(' · ', array_filter([\Model\Personal::TIPOS[$c['tipo'] ?? ''] ?? '', $p->departamento, $c['cargo'] ?? '']))); ?></div>
                <?php if (!empty($c['telefono'])): ?><a class="solo-movil text-sm mono" href="tel:<?php echo s(preg_replace('/[^0-9+]/', '', $c['telefono'])); ?>"><i class="fa-solid fa-phone"></i> <?php echo s($c['telefono']); ?></a><?php endif; ?></td>
            <td class="hide-m"><?php echo s(\Model\Personal::TIPOS[$c['tipo'] ?? ''] ?? '—'); ?></td><td class="hide-m"><?php echo s($p->departamento) ?: '—'; ?></td>
            <td class="hide-m"><?php echo s($c['cargo'] ?? '') ?: '—'; ?></td><td class="hide-m"><?php echo s($c['telefono'] ?? '') ?: '—'; ?></td>
            <td class="acciones">
                <a href="/reportes/personal/editar?proyecto=<?php echo $id; ?>&id=<?php echo (int)$p->id; ?>&volver=directorio" class="btn btn-sm btn-ghost">Editar</a>
                <form method="POST" action="/reportes/personal/eliminar" style="display:inline" data-confirm="¿Eliminar persona?|Se quitará del catálogo y de los reportes donde aparece.|Eliminar">
                    <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$p->id; ?>"><input type="hidden" name="volver" value="directorio">
                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<?php elseif ($tab === 'contratista'): ?>
<div class="card">
    <div class="la-controles" style="margin-bottom:1.2rem"><a href="/minutas/contratista/crear?proyecto=<?php echo $id; ?>&volver=directorio" class="btn btn-primary">+ Nueva persona</a>
        <span class="text-muted text-sm">Contratista o cliente: gente de otras empresas que asiste a las juntas.</span></div>
    <?php if (!$datos['contratista']): ?><p class="text-muted">Aún no hay personal del contratista.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla tabla-directorio"><thead><tr><th>Nombre</th><th class="hide-m">Puesto</th><th class="hide-m">Empresa</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($datos['contratista'] as $p): ?>
        <tr>
            <td><strong><?php echo s($p->nombre); ?></strong><div class="solo-movil text-sm text-muted"><?php echo s(implode(' · ', array_filter([$p->puesto, $p->empresa]))); ?></div></td><td class="hide-m"><?php echo s($p->puesto) ?: '—'; ?></td><td class="hide-m"><?php echo s($p->empresa) ?: '—'; ?></td>
            <td class="acciones">
                <a href="/minutas/contratista/editar?proyecto=<?php echo $id; ?>&id=<?php echo (int)$p->id; ?>&volver=directorio" class="btn btn-sm btn-ghost">Editar</a>
                <form method="POST" action="/minutas/contratista/eliminar" style="display:inline" data-confirm="¿Eliminar persona?|Se quitará del catálogo y de las minutas donde aparece.|Eliminar">
                    <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$p->id; ?>"><input type="hidden" name="volver" value="directorio">
                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<?php elseif ($tab === 'departamentos'): ?>
<div class="card">
    <p class="text-muted text-sm">Un mismo departamento sirve de carpeta en Minutas, Redactor y SheetFlow, y de filtro en Bitácora, Submittals, Evidencias y Personal. Al renombrarlo cambia en todas; si escribes el nombre de otro existente, se unen.</p>
    <form method="POST" action="/directorio/departamento" class="la-controles" style="margin:1.2rem 0">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <input type="text" name="nombre" maxlength="100" placeholder="Nuevo departamento (ej. Ingeniería)" required>
        <button type="submit" class="btn btn-primary">+ Agregar</button>
    </form>
    <?php if (!$datos['departamentos']): ?><p class="text-muted">Aún no hay departamentos.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla tabla-directorio"><thead><tr><th>Nombre</th><th class="hide-m">Dónde se usa</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($datos['departamentos'] as $nombre => $d): ?>
        <tr>
            <td><form method="POST" action="/directorio/departamento" class="la-controles">
                <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="anterior" value="<?php echo s($nombre); ?>">
                <input type="text" name="nombre" maxlength="100" value="<?php echo s($nombre); ?>" required><button type="submit" class="btn btn-sm btn-ghost">Guardar</button>
            </form></td>
            <td class="hide-m text-sm"><?php echo $sinUso($d['usos']); ?><?php echo $d['en_catalogo'] ? '' : ' <span class="text-muted">(solo en uso)</span>'; ?></td>
            <td class="acciones">
                <form method="POST" action="/directorio/departamento/eliminar" style="display:inline" data-confirm="¿Eliminar departamento?|Solo se puede si ninguna herramienta lo usa.">
                    <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="nombre" value="<?php echo s($nombre); ?>">
                    <button type="submit" class="btn btn-sm btn-danger"<?php echo $d['total'] ? ' disabled title="En uso"' : ''; ?>>✕</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<?php elseif ($tab === 'categorias'): ?>
<?php foreach ($datos['categorias'] as $ambito => $c): ?>
<div class="card">
    <h3><?php echo s($c['titulo']); ?></h3>
    <form method="POST" action="/directorio/categoria" class="la-controles" style="margin:1rem 0">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="ambito" value="<?php echo s($ambito); ?>">
        <input type="text" name="nombre" maxlength="100" placeholder="Nueva categoría" required>
        <button type="submit" class="btn btn-primary">+ Agregar</button>
    </form>
    <?php if (!$c['lista']): ?><p class="text-muted">Aún no hay categorías.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla tabla-directorio"><thead><tr><th>Nombre</th><th class="hide-m">Usos</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($c['lista'] as $nombre => $d): ?>
        <tr>
            <td><form method="POST" action="/directorio/categoria" class="la-controles">
                <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="ambito" value="<?php echo s($ambito); ?>"><input type="hidden" name="anterior" value="<?php echo s($nombre); ?>">
                <input type="text" name="nombre" maxlength="100" value="<?php echo s($nombre); ?>" required><button type="submit" class="btn btn-sm btn-ghost"><?php echo $d['en_catalogo'] ? 'Guardar' : 'Agregar'; ?></button>
            </form></td>
            <td class="hide-m text-sm"><?php echo (int)$d['usos']; ?><?php echo !empty($d['sugerida']) ? ' <span class="text-muted">(de las plantillas de inspección)</span>' : (!$d['en_catalogo'] ? ' <span class="text-muted">(solo en uso)</span>' : ''); ?></td>
            <td class="acciones">
                <?php if ($d['en_catalogo']): ?>
                <form method="POST" action="/directorio/categoria/eliminar" style="display:inline" data-confirm="¿Eliminar categoría?|Solo se puede si nada la usa.">
                    <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="ambito" value="<?php echo s($ambito); ?>"><input type="hidden" name="nombre" value="<?php echo s($nombre); ?>">
                    <button type="submit" class="btn btn-sm btn-danger"<?php echo $d['usos'] ? ' disabled title="En uso"' : ''; ?>>✕</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<?php endforeach; ?>

<?php else: ?>
<div class="card">
    <p class="text-muted text-sm">Se eligen en Materiales y Submittals.</p>
    <form method="POST" action="/especialidades/guardar" class="la-controles" style="margin:1.2rem 0">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="volver" value="directorio">
        <input type="text" name="nombre" maxlength="100" placeholder="Nueva especialidad (ej. Estructura)" required>
        <button type="submit" class="btn btn-primary">+ Agregar</button>
    </form>
    <?php if (!$datos['especialidades']): ?><p class="text-muted">Aún no hay especialidades.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla tabla-directorio"><thead><tr><th>Nombre</th><th class="hide-m">Materiales</th><th class="hide-m">Submittals</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($datos['especialidades'] as $e): ?>
        <tr>
            <td><form method="POST" action="/especialidades/guardar" class="la-controles">
                <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="volver" value="directorio"><input type="hidden" name="id" value="<?php echo (int)$e->id; ?>">
                <input type="text" name="nombre" maxlength="100" value="<?php echo s($e->nombre); ?>" required><button type="submit" class="btn btn-sm btn-ghost">Guardar</button>
            </form></td>
            <td class="mono hide-m"><?php echo (int)$e->n_materiales; ?></td><td class="mono hide-m"><?php echo (int)$e->n_submittals; ?></td>
            <td class="acciones">
                <form method="POST" action="/especialidades/eliminar" style="display:inline" data-confirm="¿Eliminar especialidad?|Solo se puede si ningún material ni submittal la usa.">
                    <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="volver" value="directorio"><input type="hidden" name="id" value="<?php echo (int)$e->id; ?>">
                    <button type="submit" class="btn btn-sm btn-danger">✕</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<?php endif; ?>
