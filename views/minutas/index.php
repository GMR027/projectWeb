<?php $puede = puede_gestionar_proyecto($proyecto); $desc = puede_descargar(); $id = s($proyecto->id); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-handshake"></i> Minutas</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($minutas); ?> minuta(s)</p>
    </div>
    <div class="la-controles">
        <a href="/minutas/contratista?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-helmet-safety"></i> Personal del contratista</a>
        <a href="/reportes/personal?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-users"></i> Personal del proyecto</a>
        <?php if ($puede): ?><a href="/minutas/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary">+ Nueva minuta</a><?php endif; ?>
    </div>
</div>
<div class="card">
    <?php if (empty($minutas)): ?>
        <p class="text-muted">Aún no hay minutas.<?php echo $puede ? ' Crea la primera con «Nueva minuta».' : ''; ?></p>
    <?php else: ?>
        <?php $carpetas = array_values(array_diff(array_keys($porDepto), [\Classes\Carpetas::SIN])); ?>
        <?php foreach ($porDepto as $depto => $lista): ?>
        <h3 style="margin:1.6rem 0 .6rem"><i class="fa-solid fa-folder-open"></i> <?php echo s($depto); ?> <small class="text-muted">(<?php echo count($lista); ?>)</small>
            <?php if ($puede && $depto !== \Classes\Carpetas::SIN): ?><button type="button" class="btn btn-sm btn-ghost" data-carpeta-renombrar="<?php echo s($depto); ?>" title="Renombrar carpeta"><i class="fa-solid fa-pen"></i></button><?php endif; ?></h3>
        <div class="tabla-wrapper"><table class="tabla" <?php echo pag_attr($proyecto); ?> data-sin-buscador>
            <thead><tr><th class="hide-m">Fecha</th><th>Minuta</th><th class="hide-m">Importancia</th><th class="hide-m">Temas</th><th class="hide-m">Compromisos</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $m): ?>
                <tr>
                    <td class="mono hide-m" style="white-space:nowrap"><?php echo s(fecha_corta_es($m->fecha)); ?></td>
                    <td><?php echo s($m->nombre); ?></td>
                    <td class="hide-m"><span class="badge bit-<?php echo s($m->importancia); ?>"><?php echo s(\Model\Bitacora::IMPORTANCIAS[$m->importancia] ?? $m->importancia); ?></span></td>
                    <td class="mono hide-m"><?php echo count($m->getTemas()); ?></td>
                    <td class="mono hide-m"><?php echo count($m->getCompromisos()); ?></td>
                    <td class="acciones">
                        <a href="/minutas/ver?proyecto=<?php echo $id; ?>&id=<?php echo s($m->id); ?>" class="btn btn-sm btn-primary">Ver</a>
                        <?php if ($desc): ?><a href="/minutas/pdf?proyecto=<?php echo $id; ?>&id=<?php echo s($m->id); ?>" class="btn btn-sm btn-ghost hide-m"><i class="fa-solid fa-file-pdf"></i> PDF</a><?php endif; ?>
                        <?php if ($puede): ?><span class="hide-m">
                            <?php echo \Classes\Carpetas::selector('minutas', (int)$m->id, (string)$m->departamento, $carpetas); ?>
                            <a href="/minutas/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($m->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                            <form method="POST" action="/minutas/eliminar" style="display:inline" data-confirm="¿Eliminar minuta?|Se borrará «<?php echo s($m->nombre); ?>» con sus temas y compromisos. Esta acción no se puede deshacer.|Eliminar">
                                <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                                <input type="hidden" name="id" value="<?php echo s($m->id); ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($puede && !empty($minutas)) { $herramienta = 'minutas'; include __DIR__ . '/../templates/carpetas-ui.php'; } ?>
