<?php
/**
 * Barra de aplicación (56 px): identidad, selector de proyecto, búsqueda,
 * notificaciones, tema y cuenta. Sustituye a la antigua topbar con título,
 * periodo y diez botones sueltos. El título de la página vive en el contenido.
 */
$proyectoNav = $proyectoSidebar ?? null;
$nombreUsuario = $_SESSION['nombre'] ?? 'Invitado';
$rolUsuario = $_SESSION['rol'] ?? 'visitante';
$correoUsuario = $_SESSION['email'] ?? '';
$partesNombre = preg_split('/\s+/', trim((string)$nombreUsuario)) ?: [];
$inicialesUsuario = '';
foreach ($partesNombre as $parte) {
    if ($parte === '') continue;
    $inicialesUsuario .= mb_strtoupper(mb_substr($parte, 0, 1));
    if (mb_strlen($inicialesUsuario) >= 2) break;
}
$estadoProyecto = ['Sin estado', 'espera'];
$periodoProyecto = null;
if ($proyectoNav) {
    $hoyISO = date('Y-m-d');
    if (($proyectoNav->fecha_inicio ?? '') > $hoyISO) $estadoProyecto = ['Por iniciar', 'espera'];
    elseif (($proyectoNav->fecha_fin ?? '') && $proyectoNav->fecha_fin < $hoyISO) $estadoProyecto = ['Finalizada', 'fin'];
    else $estadoProyecto = ['En ejecución', 'ok'];
    if (!empty($proyectoNav->fecha_inicio) && !empty($proyectoNav->fecha_fin)) {
        $inicioP = new DateTime($proyectoNav->fecha_inicio);
        $finP = new DateTime($proyectoNav->fecha_fin);
        $diffP = $inicioP->diff($finP);
        $periodoProyecto = [
            'inicio' => $inicioP->format('d/m/Y'),
            'fin' => $finP->format('d/m/Y'),
            'dias' => (int)$diffP->days,
            'meses' => (int)$diffP->m + (int)$diffP->y * 12,
            'resto' => (int)$diffP->d,
        ];
    }
}
$partesProyecto = preg_split('/\s+/', trim((string)($proyectoNav->nombre ?? ''))) ?: [];
$inicialesProyecto = '';
foreach ($partesProyecto as $parte) {
    if ($parte === '') continue;
    $inicialesProyecto .= mb_strtoupper(mb_substr($parte, 0, 1));
    if (mb_strlen($inicialesProyecto) >= 2) break;
}

// Campana de notificaciones: novedades de la plataforma, actividades de hoy y acciones rápidas.
// $accionesRapidas lo calcula el sidebar con el permiso del usuario: el cliente no ve las del admin.
$notifRol = $_SESSION['rol'] ?? 'visitante';
$notifNovedades = \Classes\Novedades::para($notifRol, (int)($_SESSION['id'] ?? 0));
$notifSinVer = \Classes\Novedades::sinVer($notifNovedades);
$notifProyectoId = (int)($proyectoSidebar->id ?? 0);
// Solo «actividades de hoy» (2 consultas). El resto de acciones rápidas se cargan al abrir el menú.
$notifHoy = null;
if ($notifProyectoId) {
    try { $notifHoy = \Classes\AccionesRapidas::seccionHoy($notifProyectoId); } catch (\Throwable $e) { $notifHoy = null; }
}
$notifAcciones = [];
// Alertas por tendencia del tablero de portafolio (la tabla se llena al abrir /portafolio o con bin/portafolio_alertas.php)
$notifPortafolio = [];
if (in_array($notifRol, ['admin', 'cliente'], true)) {
    try { $notifPortafolio = \Classes\Portafolio::alertasRecientes(['id' => (int)($_SESSION['id'] ?? 0), 'rol' => $notifRol], 3); } catch (\Throwable $e) { $notifPortafolio = []; }
}
$notifTotal = $notifSinVer + (int)($notifHoy['total'] ?? 0) + count($notifPortafolio);
?>
<header class="appbar">
    <h1 class="sr-only"><?php echo s(t($titulo ?? 'ProjectWeb')); ?></h1>
    <div class="appbar-izq">
        <button type="button" id="btn-menu" class="appbar-btn appbar-menu-btn" aria-label="<?php echo s(t('Abrir menú general')); ?>" aria-expanded="false" aria-controls="sidebar">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <a href="/dashboard" class="appbar-marca" aria-label="ProjectWeb · <?php echo s(t('Inicio')); ?>">
            <i class="fa-solid fa-helmet-safety" aria-hidden="true"></i>
            <span class="appbar-marca-txt">ProjectWeb</span>
        </a>
        <?php if ($proyectoNav): ?>
            <div class="appbar-proyecto-wrap">
                <button type="button" class="appbar-proyecto" id="appbar-proyecto-btn" aria-expanded="false" aria-controls="appbar-proyecto-panel">
                    <span class="appbar-estado appbar-estado--<?php echo s($estadoProyecto[1]); ?>" aria-hidden="true"></span>
                    <span class="appbar-proyecto-txt">
                        <strong><?php echo s($proyectoNav->nombre); ?></strong>
                        <small class="mono"><?php echo s($proyectoNav->codigo); ?></small>
                    </span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="appbar-panel appbar-proyecto-panel" id="appbar-proyecto-panel" hidden>
                    <div class="appbar-proyecto-cab">
                        <?php if (!empty($proyectoNav->logo)): ?>
                            <img src="/build/img/logos/<?php echo s($proyectoNav->logo); ?>" alt="" class="appbar-proyecto-logo">
                        <?php else: ?>
                            <span class="appbar-proyecto-monograma" aria-hidden="true"><?php echo s($inicialesProyecto ?: 'PW'); ?></span>
                        <?php endif; ?>
                        <div>
                            <strong><?php echo s($proyectoNav->nombre); ?></strong>
                            <small class="mono"><?php echo s($proyectoNav->codigo); ?> · <?php echo s(t($estadoProyecto[0])); ?></small>
                        </div>
                    </div>
                    <?php if ($periodoProyecto): ?>
                        <dl class="appbar-proyecto-datos">
                            <dt><?php echo s(t('Inicio')); ?></dt><dd class="mono"><?php echo s($periodoProyecto['inicio']); ?></dd>
                            <dt><?php echo s(t('Fin')); ?></dt><dd class="mono"><?php echo s($periodoProyecto['fin']); ?></dd>
                            <dt><?php echo s(t('Duración')); ?></dt>
                            <dd class="mono"><?php echo (int)$periodoProyecto['dias']; ?> <?php echo s(t('días')); ?> · <?php echo (int)$periodoProyecto['meses']; ?> <?php echo s(t('meses')); ?><?php echo $periodoProyecto['resto'] > 0 ? ' y ' . (int)$periodoProyecto['resto'] . ' ' . s(t('días')) : ''; ?></dd>
                        </dl>
                    <?php endif; ?>
                    <div class="appbar-panel-acciones">
                        <a href="/proyectos/ver?id=<?php echo (int)$proyectoNav->id; ?>"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i> <?php echo s(t('Salud del proyecto')); ?></a>
                        <a href="/ajustes?proyecto=<?php echo (int)$proyectoNav->id; ?>"><i class="fa-solid fa-gear" aria-hidden="true"></i> <?php echo s(t('Ajustes de proyecto')); ?></a>
                        <a href="/proyectos"><i class="fa-solid fa-building" aria-hidden="true"></i> <?php echo s(t('Ver todos los proyectos')); ?></a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="appbar-centro">
        <?php if ($proyectoNav): ?>
            <form method="get" action="/buscar" class="appbar-buscar" role="search">
                <input type="hidden" name="proyecto" value="<?php echo (int)$proyectoNav->id; ?>">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="q" placeholder="<?php echo s(t('Buscar en el proyecto…')); ?>" aria-label="<?php echo s(t('Buscar en el proyecto')); ?>">
            </form>
        <?php endif; ?>
    </div>
    <div class="appbar-der">
        <?php if ($proyectoNav): ?>
            <button type="button" class="appbar-btn appbar-buscar-btn" id="appbar-buscar-btn" aria-expanded="false" aria-controls="appbar-buscar-movil" aria-label="<?php echo s(t('Buscar en el proyecto')); ?>">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </button>
            <button type="button" class="appbar-btn appbar-herramientas" data-nav-todas
                    title="<?php echo s(t('Todas las herramientas')); ?>" aria-label="<?php echo s(t('Todas las herramientas')); ?>">
                <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
            </button>
        <?php endif; ?>
        <a href="/manual<?php echo $proyectoNav ? '?proyecto=' . (int)$proyectoNav->id : ''; ?>" class="appbar-btn appbar-ayuda" title="<?php echo s(t('Manual de usuario')); ?>" aria-label="<?php echo s(t('Manual de usuario')); ?>">
            <i class="fa-solid fa-book-open" aria-hidden="true"></i>
        </a>
        <div class="notif" id="notif">
            <button type="button" id="notif-btn" class="appbar-btn notif-btn" title="<?php echo s(t('Notificaciones')); ?>" aria-label="<?php echo s(t('Notificaciones')); ?>" aria-expanded="false" aria-controls="notif-panel">
                <i class="fa-solid fa-bell" aria-hidden="true"></i>
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

                    <?php if ($notifPortafolio): ?>
                        <section class="notif-sec">
                            <h4><i class="fa-solid fa-table-columns"></i> <?php echo s(t('Alertas de portafolio')); ?> <b class="notif-chip"><?php echo count($notifPortafolio); ?></b></h4>
                            <ul class="notif-lista">
                                <?php foreach ($notifPortafolio as $al): ?>
                                    <li><a href="/portafolio">
                                        <i class="fa-solid <?php echo $al['nivel'] === 'critico' ? 'fa-triangle-exclamation' : 'fa-arrow-trend-down'; ?>"></i>
                                        <span class="notif-txt">
                                            <strong><?php echo s($al['proyecto']); ?></strong>
                                            <small><?php echo s($al['mensaje']); ?></small>
                                        </span>
                                    </a></li>
                                <?php endforeach; ?>
                            </ul>
                            <a class="notif-ver" href="/portafolio"><?php echo s(t('Ver el tablero')); ?> →</a>
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

                    <?php if (!$notifNovedades && !$notifHoy && !$notifAcciones && !$notifPortafolio): ?>
                        <p class="notif-vacio"><?php echo s(t('Sin notificaciones por ahora.')); ?></p>
                    <?php endif; ?>
                </div>
                <div class="notif-pie">
                    <a href="/novedades"><?php echo s(t('Novedades de la plataforma')); ?></a>
                    <?php if ($notifProyectoId): ?> · <a href="/acciones?proyecto=<?php echo $notifProyectoId; ?>"><?php echo s(t('Ver acciones rápidas')); ?></a><?php endif; ?>
                </div>
            </div>
        </div>
        <button type="button" class="appbar-btn appbar-tema" data-tema-toggle title="<?php echo s(t('Cambiar tema')); ?>" aria-label="<?php echo s(t('Cambiar tema')); ?>">
            <i class="fa-solid fa-moon" aria-hidden="true"></i>
        </button>
        <div class="appbar-usuario-wrap">
            <button type="button" class="appbar-avatar" id="appbar-avatar-btn" aria-expanded="false" aria-controls="appbar-menu"
                    title="<?php echo s($nombreUsuario); ?> · <?php echo s(t(ucfirst($rolUsuario))); ?>"
                    aria-label="<?php echo s(t('Cuenta de {nombre}', ['nombre' => $nombreUsuario])); ?>">
                <?php echo s($inicialesUsuario ?: 'PW'); ?>
            </button>
            <div class="appbar-panel appbar-menu" id="appbar-menu" hidden>
                <div class="appbar-menu-cab">
                    <span class="appbar-avatar appbar-avatar--grande" aria-hidden="true"><?php echo s($inicialesUsuario ?: 'PW'); ?></span>
                    <div>
                        <strong><?php echo s($nombreUsuario); ?></strong>
                        <small><?php echo s(t(ucfirst($rolUsuario))); ?><?php echo $correoUsuario ? ' · ' . s($correoUsuario) : ''; ?></small>
                    </div>
                </div>
                <nav class="appbar-menu-lista" aria-label="<?php echo s(t('Cuenta')); ?>">
                    <a href="/proyectos"><i class="fa-solid fa-building" aria-hidden="true"></i> <?php echo s(t($rolUsuario === 'cliente' ? 'Mis Proyectos' : 'Proyectos')); ?></a>
                    <a href="/cuenta/sesiones"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> <?php echo s(t('Sesiones y dispositivos')); ?></a>
                    <a href="/manual<?php echo $proyectoNav ? '?proyecto=' . (int)$proyectoNav->id : ''; ?>"><i class="fa-solid fa-book-open" aria-hidden="true"></i> <?php echo s(t('Manual de usuario')); ?></a>
                    <?php if (is_admin()): ?>
                        <a href="/sistema"><i class="fa-solid fa-gear" aria-hidden="true"></i> <?php echo s(t('Ajustes de app')); ?></a>
                    <?php endif; ?>
                    <?php if ($rolUsuario === 'cliente'): ?>
                        <a href="/soporte"><i class="fa-solid fa-headset" aria-hidden="true"></i> <?php echo s(t('Soporte')); ?></a>
                    <?php endif; ?>
                </nav>
                <div class="appbar-menu-idioma">
                    <span><?php echo s(t('Idioma')); ?></span>
                    <form method="POST" action="/idioma" class="idioma-sel" title="<?php echo s(t('Idioma de la interfaz')); ?>">
                        <input type="hidden" name="volver" value="<?php echo s($_SERVER['REQUEST_URI'] ?? '/'); ?>">
                        <?php foreach (IDIOMAS as $cod => $nom): ?><button type="submit" name="idioma" value="<?php echo s($cod); ?>" class="<?php echo idioma_actual() === $cod ? 'activo' : ''; ?>" aria-label="<?php echo s($nom); ?>"><?php echo strtoupper($cod); ?></button><?php endforeach; ?>
                    </form>
                </div>
                <div class="appbar-menu-tema">
                    <span><?php echo s(t('Tema')); ?></span>
                    <button type="button" class="btn btn-ghost btn-sm" data-tema-toggle>
                        <i class="fa-solid fa-moon" aria-hidden="true"></i> <?php echo s(t('Cambiar tema')); ?>
                    </button>
                </div>
                <form method="POST" action="/logout" class="appbar-menu-salir">
                    <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> <?php echo s(t('Salir')); ?></button>
                </form>
            </div>
        </div>
    </div>
    <?php if ($proyectoNav): ?>
        <div class="appbar-buscar-movil" id="appbar-buscar-movil" hidden>
            <form method="get" action="/buscar" role="search">
                <input type="hidden" name="proyecto" value="<?php echo (int)$proyectoNav->id; ?>">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="q" placeholder="<?php echo s(t('Buscar en el proyecto…')); ?>" aria-label="<?php echo s(t('Buscar en el proyecto')); ?>" autofocus>
                <button type="button" class="appbar-btn" data-buscar-cerrar aria-label="<?php echo s(t('Cerrar')); ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </form>
        </div>
    <?php endif; ?>
</header>
