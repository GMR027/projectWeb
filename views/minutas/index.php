<?php
$puede = puede_gestionar_proyecto($proyecto); $desc = puede_descargar(); $id = s($proyecto->id);
$nMinutas = count($minutas);
$nTemas = 0; $nCompromisos = 0;
foreach ($minutas as $m) { $nTemas += count($m->getTemas()); $nCompromisos += count($m->getCompromisos()); }
$ultima = $minutas[0] ?? null;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-handshake"></i> Minutas</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · acuerdos de reunión, asistentes y compromisos por departamento.</p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-handshake"></i><b><?php echo $nMinutas; ?></b> minuta<?php echo $nMinutas === 1 ? '' : 's'; ?></span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-comments"></i><b><?php echo $nTemas; ?></b> temas</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-list-check"></i><b><?php echo $nCompromisos; ?></b> compromisos</span>
        </div>
    </div>
    <div class="la-controles">
        <a href="/minutas/contratista?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-helmet-safety"></i> Personal del contratista</a>
        <a href="/reportes/personal?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-users"></i> Personal del proyecto</a>
        <?php if ($puede): ?><a href="/minutas/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva minuta</a><?php endif; ?>
    </div>
</div>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-handshake" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Minutas</small><strong><?php echo $nMinutas; ?></strong><span>reuniones documentadas</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-comments" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Temas vistos</small><strong><?php echo $nTemas; ?></strong><span>puntos tratados en las minutas</span></span>
    </div>
    <div class="suite-kpi <?php echo $nCompromisos ? 'is-warning' : ''; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Compromisos</small><strong><?php echo $nCompromisos; ?></strong><span>acuerdos con responsable</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Última minuta</small><strong><?php echo $ultima ? fecha_corta_es($ultima->fecha) : '—'; ?></strong><span><?php echo $ultima ? s($ultima->nombre) : 'sin minutas'; ?></span></span>
    </div>
</div>

<?php if (empty($minutas)): ?>
    <div class="suite-panel">
        <?php echo estado_vacio('fa-handshake', 'Aún no hay minutas', $puede ? 'Crea la primera con «Nueva minuta»: temas, asistentes y compromisos quedan en un solo documento.' : 'Cuando el equipo registre reuniones, aquí aparecerán sus acuerdos y compromisos.', $puede ? ['texto' => 'Nueva minuta', 'url' => '/minutas/crear?proyecto=' . $id, 'icono' => 'fa-plus'] : null); ?>
    </div>
<?php else: ?>
    <?php $carpetas = array_values(array_diff(array_keys($porDepto), [\Classes\Carpetas::SIN])); ?>
    <?php foreach ($porDepto as $depto => $lista): ?>
    <div class="suite-panel">
        <div class="lb-tabla-cab">
            <h3>
                <i class="fa-solid <?php echo $depto === \Classes\Carpetas::SIN ? 'fa-folder' : 'fa-folder-open'; ?>"></i>
                <?php echo s($depto); ?>
                <span class="suite-chip"><?php echo count($lista); ?></span>
            </h3>
            <?php if ($puede && $depto !== \Classes\Carpetas::SIN): ?><button type="button" class="btn btn-sm btn-ghost" data-carpeta-renombrar="<?php echo s($depto); ?>" title="Renombrar carpeta"><i class="fa-solid fa-pen"></i> Renombrar</button><?php endif; ?>
        </div>
        <div class="suite-panel-cuerpo">
            <div class="tabla-wrapper">
                <table class="tabla" <?php echo pag_attr($proyecto); ?> data-sin-buscador>
                    <thead><tr><th class="hide-m">Fecha</th><th>Minuta</th><th class="hide-m">Importancia</th><th class="hide-m">Temas</th><th class="hide-m">Compromisos</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($lista as $m): ?>
                        <tr>
                            <td class="mono hide-m nowrap"><?php echo s(fecha_corta_es($m->fecha)); ?></td>
                            <td><a href="/minutas/ver?proyecto=<?php echo $id; ?>&id=<?php echo s($m->id); ?>"><strong><?php echo s($m->nombre); ?></strong></a></td>
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
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($puede && !empty($minutas)) { $herramienta = 'minutas'; include __DIR__ . '/../templates/carpetas-ui.php'; } ?>
