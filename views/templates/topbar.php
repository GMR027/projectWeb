<header class="topbar">
<?php
// Campana de notificaciones: novedades de la plataforma, actividades de hoy y acciones rápidas.
// $accionesRapidas lo calcula el sidebar con el permiso del usuario: el cliente no ve las del admin.
$notifRol = $_SESSION['rol'] ?? 'visitante';
$notifNovedades = \Classes\Novedades::para($notifRol, (int)($_SESSION['id'] ?? 0));
$notifSinVer = \Classes\Novedades::sinVer($notifNovedades);
$notifProyectoId = (int)($proyectoSidebar->id ?? 0);
$notifHoy = null;
$notifAcciones = [];
foreach (($accionesRapidas ?? []) as $sec) {
    if ($sec['clave'] === 'hoy') { $notifHoy = $sec; continue; }
    if ((int)$sec['total'] > 0) $notifAcciones[] = $sec;
}
$notifTotal = $notifSinVer + (int)($notifHoy['total'] ?? 0);
?>
    <div class="topbar-left">
        <h1 class="page-title"><?php echo s(t($titulo ?? '')); ?></h1>
        <?php if (isset($proyecto)): ?>
            <div class="proyecto-periodo">
                <?php
                $inicio = new DateTime($proyecto->fecha_inicio);
                $fin = new DateTime($proyecto->fecha_fin);
                $diff = $inicio->diff($fin);
                $totalDias = $diff->days;
                $meses = $diff->m + ($diff->y * 12);
                $diasRestantes = $diff->d;
                ?>
                <span class="periodo-item">
                    <i class="fa-solid fa-calendar-day"></i>
                    <strong><?php echo $totalDias; ?></strong> días totales
                </span>
                <span class="periodo-separator">•</span>
                <span class="periodo-item">
                    <i class="fa-solid fa-calendar-week"></i>
                    <strong><?php echo $meses; ?></strong> meses<?php echo $diasRestantes > 0 ? " y <strong>$diasRestantes</strong> días" : ''; ?>
                </span>
                <span class="periodo-separator">•</span>
                <span class="periodo-item">
                    <i class="fa-solid fa-play"></i>
                    <?php echo $inicio->format('d/m/Y'); ?>
                </span>
                <span class="periodo-arrow">→</span>
                <span class="periodo-item">
                    <i class="fa-solid fa-flag-checkered"></i>
                    <?php echo $fin->format('d/m/Y'); ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
    <div class="topbar-right">
        <?php if (isset($proyecto) && $proyecto instanceof \Model\Proyecto && !es_demo()): ?>
            <form method="get" action="/buscar" class="topbar-buscar" role="search">
                <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
                <input type="search" name="q" placeholder="<?php echo s(t('Buscar en el proyecto')); ?>" aria-label="<?php echo s(t('Buscar en el proyecto')); ?>">
            </form>
        <?php endif; ?>
        <a href="/proyectos" class="btn btn-ghost btn-sm topbar-icono" title="<?php echo s(t('Proyectos')); ?>" aria-label="<?php echo s(t('Proyectos')); ?>">
            <i class="fa-solid fa-house"></i>
        </a>
        <div class="notif" id="notif">
            <button type="button" id="notif-btn" class="btn btn-ghost btn-sm topbar-icono notif-btn" title="<?php echo s(t('Notificaciones')); ?>" aria-label="<?php echo s(t('Notificaciones')); ?>" aria-expanded="false" aria-controls="notif-panel">
                <i class="fa-solid fa-bell"></i>
                <span class="notif-badge" id="notif-badge" data-hoy="<?php echo (int)($notifHoy['total'] ?? 0); ?>" data-nov="<?php echo (int)$notifSinVer; ?>"<?php echo $notifTotal ? '' : ' hidden'; ?>><?php echo $notifTotal > 9 ? '9+' : (int)$notifTotal; ?></span>
            </button>
            <div class="notif-panel" id="notif-panel" hidden>
                <div class="notif-cab">
                    <strong><i class="fa-solid fa-bell"></i> <?php echo s(t('Notificaciones')); ?></strong>
                    <?php if ($notifTotal): ?><span class="notif-cab-chip" id="notif-cab-chip" data-sufijo="<?php echo s(t('nuevas')); ?>"><?php echo $notifTotal > 9 ? '9+' : (int)$notifTotal; ?> <?php echo s(t('nuevas')); ?></span><?php endif; ?>
                </div>
                <div class="notif-cuerpo">
                    <?php if ($notifNovedades): ?>
                        <section class="notif-sec">
                            <h4><i class="fa-solid fa-bullhorn"></i> <?php echo s(t('Novedades de la plataforma')); ?><?php if ($notifSinVer): ?> <b class="notif-chip" data-nov-chip><?php echo (int)$notifSinVer; ?> <?php echo s(t('sin ver')); ?></b><?php endif; ?></h4>
                            <ul class="notif-lista">
                                <?php foreach (array_slice($notifNovedades, 0, 3) as $nv): [$etqNv, $icoNv] = \Classes\Novedades::tipo($nv['tipo']); ?>
                                    <li class="notif-nov<?php echo $nv['nueva'] ? ' nueva' : ''; ?>">
                                        <a href="/novedades#<?php echo s(\Classes\Novedades::ancla($nv)); ?>">
                                            <i class="fa-solid <?php echo s($icoNv); ?>"></i>
                                            <span class="notif-txt">
                                                <strong><?php echo s($nv['titulo']); ?></strong>
                                                <small><?php echo s($etqNv); ?> · <?php echo s(formatear_fecha(substr($nv['fecha'], 0, 10), 'd/m')); ?></small>
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <a class="notif-ver" href="/novedades"><?php echo s(t('Ver todas las novedades')); ?> (<?php echo count($notifNovedades); ?>) →</a>
                        </section>
                    <?php endif; ?>

                    <?php if ($notifHoy): ?>
                        <section class="notif-sec">
                            <h4><i class="fa-solid fa-sun"></i> <?php echo s(t('Actividades de hoy')); ?> <b class="notif-chip"><?php echo (int)$notifHoy['total']; ?></b></h4>
                            <?php if (!$notifHoy['items']): ?>
                                <p class="notif-vacio"><?php echo s(t('Nada programado para hoy.')); ?></p>
                            <?php else: ?>
                                <ul class="notif-lista">
                                    <?php foreach (array_slice($notifHoy['items'], 0, 4) as $itHoy): ?>
                                        <li><a href="<?php echo s($itHoy['url']); ?>">
                                            <span class="notif-txt">
                                                <strong><?php echo s($itHoy['titulo']); ?></strong>
                                                <small><?php echo s($itHoy['sub']); ?></small>
                                            </span>
                                            <?php if ($itHoy['chip']): ?><em class="acc-chip acc-<?php echo s(strtolower(str_replace('/', '', $itHoy['chip']))); ?>"><?php echo s($itHoy['chip']); ?></em><?php endif; ?>
                                        </a></li>
                                    <?php endforeach; ?>
                                </ul>
                                <a class="notif-ver" href="/programa?proyecto=<?php echo $notifProyectoId; ?>"><?php echo s(t('Ver el programa')); ?> →</a>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <?php if ($notifAcciones): ?>
                        <section class="notif-sec">
                            <h4><i class="fa-solid fa-bolt"></i> <?php echo s(t('Acciones rápidas')); ?></h4>
                            <ul class="notif-lista notif-acciones">
                                <?php foreach ($notifAcciones as $sec): ?>
                                    <li><a href="/acciones?proyecto=<?php echo $notifProyectoId; ?>#acc-<?php echo s($sec['clave']); ?>">
                                        <i class="fa-solid <?php echo s($sec['icono']); ?>"></i>
                                        <span class="notif-txt"><strong><?php echo s($sec['titulo']); ?></strong></span>
                                        <b class="notif-chip"><?php echo (int)$sec['total']; ?></b>
                                    </a></li>
                                <?php endforeach; ?>
                            </ul>
                            <a class="notif-ver" href="/acciones?proyecto=<?php echo $notifProyectoId; ?>"><?php echo s(t('Ver acciones rápidas')); ?> →</a>
                        </section>
                    <?php endif; ?>

                    <?php if (!$notifNovedades && !$notifHoy && !$notifAcciones): ?>
                        <p class="notif-vacio"><?php echo s(t('Sin notificaciones por ahora.')); ?></p>
                    <?php endif; ?>
                </div>
                <div class="notif-pie">
                    <a href="/novedades"><?php echo s(t('Novedades de la plataforma')); ?></a>
                </div>
            </div>
        </div>
        <button id="toggle-theme" class="btn btn-ghost btn-sm topbar-icono" title="Cambiar tema" aria-label="Cambiar tema">
            <i class="fa-solid fa-moon"></i>
        </button>
        <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
        <a href="/sistema" class="btn btn-ghost btn-sm topbar-icono" title="<?php echo s(t('Ajustes de app')); ?>" aria-label="<?php echo s(t('Ajustes de app')); ?>">
            <i class="fa-solid fa-gear"></i>
        </a>
        <?php endif; ?>
        <div class="user-info">
            <span class="user-name"><?php echo s($_SESSION['nombre'] ?? 'Invitado'); ?></span>
            <span class="user-role badge badge-<?php echo s($_SESSION['rol'] ?? 'visitante'); ?>">
                <?php echo s(t(ucfirst($_SESSION['rol'] ?? 'visitante'))); ?>
            </span>
        </div>
        <form method="POST" action="/idioma" class="idioma-sel" title="<?php echo s(t('Idioma')); ?>">
            <input type="hidden" name="volver" value="<?php echo s($_SERVER['REQUEST_URI'] ?? '/'); ?>">
            <?php foreach (IDIOMAS as $cod => $nom): ?><button type="submit" name="idioma" value="<?php echo $cod; ?>" class="<?php echo idioma_actual() === $cod ? 'activo' : ''; ?>" aria-label="<?php echo s($nom); ?>"><?php echo strtoupper($cod); ?></button><?php endforeach; ?>
        </form>
        <a href="/logout" class="btn btn-ghost btn-sm topbar-salir" title="<?php echo s(t('Salir')); ?>">
            <i class="fa-solid fa-right-from-bracket"></i> <span class="hide-m"><?php echo s(t('Salir')); ?></span>
        </a>
    </div>
</header>
