<?php
/**
 * Bloque «Acciones rápidas» del menú lateral. Lo pinta:
 *  - views/templates/sidebar.php (carga diferida desde /api/acciones)
 *  - Controllers\AccionesController::fragmento() (respuesta de esa carga)
 * Variables: $accionesRapidas (secciones), $proyectoSidebar (proyecto).
 */
if (!empty($accionesRapidas)): ?>
    <?php $totalAcciones = array_sum(array_column($accionesRapidas, 'total')); ?>
    <div class="nav-section"><?php echo s(t('Acciones rápidas')); ?></div>
    <?php if ($totalAcciones > 5): ?>
        <a href="/acciones?proyecto=<?php echo (int)$proyectoSidebar->id; ?>" target="_blank" rel="noopener" class="nav-link acc-todas">
            <i class="fa-solid fa-up-right-from-square nav-icon"></i>
            <span><?php echo s(t('Ver todas en otra pestaña')); ?></span>
            <b class="nav-badge"><?php echo (int)$totalAcciones; ?></b>
        </a>
    <?php endif; ?>
    <?php foreach ($accionesRapidas as $sec): ?>
        <details class="acc-sec"<?php echo $sec['abierta'] && $sec['total'] ? ' open' : ''; ?>>
            <summary class="<?php echo $sec['total'] ? '' : 'vacia'; ?>">
                <i class="fa-solid <?php echo s($sec['icono']); ?> nav-icon"></i>
                <span><?php echo s($sec['titulo']); ?></span>
                <?php if ($sec['total']): ?><b class="nav-badge"><?php echo (int)$sec['total']; ?></b><?php endif; ?>
            </summary>
            <?php if (!$sec['items']): ?>
                <p class="acc-vacio"><?php echo s(t('Nada pendiente')); ?></p>
            <?php else: ?>
                <ul class="acc-lista">
                    <?php foreach ($sec['items'] as $it): ?>
                        <li><a href="<?php echo s($it['url']); ?>">
                            <span class="acc-titulo"><?php echo !empty($it['wbs']) ? '<span class="mono">' . s($it['wbs']) . '</span> · ' : ''; echo s($it['titulo']); ?></span>
                            <span class="acc-sub"><?php if ($it['chip']): ?><em class="acc-chip acc-<?php echo s(strtolower(str_replace('/', '', $it['chip']))); ?>"><?php echo s($it['chip']); ?></em> <?php endif; ?><?php echo s($it['sub']); ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($sec['total'] > count($sec['items'])): ?>
                    <a class="acc-mas" href="/acciones?proyecto=<?php echo (int)$proyectoSidebar->id; ?>#acc-<?php echo s($sec['clave']); ?>" target="_blank" rel="noopener">Ver todas (<?php echo (int)$sec['total']; ?>) →</a>
                <?php endif; ?>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
<?php endif; ?>
