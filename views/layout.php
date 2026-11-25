<?php
// Navegación: datos una sola vez por página (barra, riel, panel y menú lateral).
require_once __DIR__ . '/templates/herramientas-proyecto.php';
require_once __DIR__ . '/templates/navegacion-global.php';
require_once __DIR__ . '/templates/nav-contexto.php';
require_once __DIR__ . '/templates/nav-riel.php';
require_once __DIR__ . '/templates/nav-panel.php';
require_once __DIR__ . '/templates/nav-inferior.php';

// Proyecto de la barra: el de la vista o el ?proyecto= (se consulta una sola vez).
$proyectoCtx = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
$proyectoSidebar = null;
if (isset($proyecto) && $proyecto instanceof \Model\Proyecto) {
    $proyectoSidebar = $proyecto;
} elseif ($proyectoCtx) {
    $pNav = \Model\Proyecto::find($proyectoCtx);
    if ($pNav && puede_ver_proyecto($pNav)) $proyectoSidebar = $pNav;
}

// Tema del proyecto (Ajustes → Apariencia del proyecto). Sin proyecto manda la preferencia del usuario.
$temaModoProyecto = null;   // 'light' | 'dark' | null (el que elige cada usuario)
$temaPaletaProyecto = \Classes\TemaProyecto::POR_DEFECTO;
$temaCssProyecto = '';
$nombresHerramientas = false;   // barra compacta: nombre junto al icono (Ajustes → Apariencia)
$menuModoProyecto = 'clasico';  // clasico = barra compacta; panel = riel lateral
$ayudaBurbujas = true;          // mensajes emergentes de ayuda (Ajustes → Apariencia)
if ($proyectoSidebar) {
    $temaModoProyecto = \Classes\TemaProyecto::temaForzado($proyectoSidebar->tema_modo ?? 'usuario');
    $temaPaletaProyecto = \Classes\TemaProyecto::normPaleta($proyectoSidebar->tema_paleta ?? 'naranja');
    $temaCssProyecto = \Classes\TemaProyecto::css($temaPaletaProyecto, \Classes\TemaProyecto::opciones($proyectoSidebar));
    $nombresHerramientas = \Classes\TemaProyecto::nombresHerramientas($proyectoSidebar->nombres_herramientas ?? 0);
    $menuModoProyecto = \Classes\TemaProyecto::normMenu($proyectoSidebar->menu_superior ?? 'clasico');
    $ayudaBurbujas = \Classes\TemaProyecto::ayudasVisibles($proyectoSidebar->ayuda_burbujas ?? 1);
}
$menuGlobal = menu_global_nav();
$gruposHerramientasProyecto = $proyectoSidebar ? herramientas_proyecto($proyectoSidebar) : [];
$navActiva = nav_activa($gruposHerramientasProyecto);
$navClaveActiva = $navActiva[0];
$navGrupoActivo = (int)($navActiva[2] ?? 0);
$clasesHtml = [];
if ($nombresHerramientas) $clasesHtml[] = 'herr-con-nombres';
$clasesHtml[] = $menuModoProyecto === 'panel' ? 'menu-panel' : 'menu-compacto';
if (!$ayudaBurbujas) $clasesHtml[] = 'sin-ayuda-burbujas';
?>
<!DOCTYPE html>
<html lang="<?php echo idioma_actual(); ?>" data-paleta="<?php echo s($temaPaletaProyecto); ?>"<?php echo $clasesHtml ? ' class="' . s(implode(' ', $clasesHtml)) . '"' : ''; ?><?php if ($temaModoProyecto) echo ' data-tema-fijo="' . s($temaModoProyecto) . '"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/build/img/pwa/apple-touch-icon.png">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ProjectWeb">
    <title><?php echo s($titulo ?? 'Programa de Obra'); ?> | ProjectWeb</title>
    <link rel="stylesheet" href="/vendor/fuentes/fuentes.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fuentes/fuentes.css'); ?>">
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fontawesome/css/all.min.css'); ?>">
    <?php
    // CSS dividido: las vistas mapeadas cargan app-base + su hoja; el resto, app.css completo.
    $__cssVista = css_paginas_de_vista($__vista ?? null);
    $__cssPaginasOk = $__cssVista !== null;
    foreach (($__cssVista ?? []) as $__css) {
        if (!is_file(__DIR__ . '/../public/build/css/paginas/' . $__css . '.css')) { $__cssPaginasOk = false; break; }
    }
    if (!is_file(__DIR__ . '/../public/build/css/app-movil.css')) $__cssPaginasOk = false;
    ?>
    <?php if (!$__cssPaginasOk): ?>
    <link rel="stylesheet" href="/build/css/app.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/app.css'); ?>">
    <?php else: ?>
    <link rel="stylesheet" href="/build/css/app-base.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/app-base.css'); ?>">
    <?php foreach ($__cssVista as $__css): ?>
    <link rel="stylesheet" href="/build/css/paginas/<?php echo s($__css); ?>.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/paginas/' . $__css . '.css'); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="/build/css/app-movil.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/app-movil.css'); ?>">
    <?php endif; ?>
    <?php if ($temaCssProyecto): ?><style><?php echo $temaCssProyecto; ?></style><?php endif; ?>
    <script>
        // Tema elegido por el usuario (guardado en localStorage).
        // Por defecto: oscuro. Si el proyecto fija el tema (Ajustes), manda el proyecto.
        (function() {
            const fijo = document.documentElement.getAttribute('data-tema-fijo');
            const tema = fijo || localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', tema);
            if (window.top !== window.self) document.documentElement.classList.add('embebido');   // panel del divisor de ventana
        })();
    </script>
</head>
<body data-vista="<?php echo s(str_replace('/', '-', $__vista ?? '')); ?>"<?php echo es_modal() ? ' class="body-modal"' : ''; ?>>
    <div class="barra-progreso" id="pw-progreso" aria-hidden="true"></div>
    <a class="saltar-contenido" href="#contenido-principal">Saltar al contenido</a>
    <?php if (es_demo()): ?>
        <div class="demo-banner">
            <span><i class="fa-solid fa-eye"></i> Estás viendo el <strong>demo</strong> con datos de ejemplo · solo lectura</span>
            <span class="demo-banner-acciones">
                <a href="/inicio#planes">Ver planes</a>
                <form method="POST" action="/logout" class="form-inline"><button type="submit" class="demo-banner-salir enlace-boton">Salir del demo</button></form>
            </span>
        </div>
    <?php endif; ?>
    <?php
    // Aviso de plan: trial, solo lectura o almacenamiento al 80 %/100 %.
    $orgBanner = is_auth() ? organizacion_actual() : null;
    if ($orgBanner):
        $soloBanner = $orgBanner->soloLectura();
        $trialBanner = ($orgBanner->estado_plan ?? '') === 'trial';
        $diasBanner = $orgBanner->diasTrial();
        $limBanner = $orgBanner->limites();
        $almTotalBanner = \Classes\Almacenamiento::limiteCuenta((int)$orgBanner->id);
        $almUsadoBanner = (int)($orgBanner->almacenamiento_bytes ?? 0);
        if ($almTotalBanner !== null && $almUsadoBanner <= 0) {
            $almUsadoBanner = \Classes\Almacenamiento::usadoCuenta((int)$orgBanner->id);
        }
        $almPctBanner = $almTotalBanner ? min(100, $almUsadoBanner / $almTotalBanner * 100) : 0;
    ?>
    <?php if ($soloBanner || $trialBanner || $almPctBanner >= 80): ?>
        <div class="demo-banner" role="status">
            <span>
                <i class="fa-solid <?php echo $soloBanner ? 'fa-lock' : ($trialBanner ? 'fa-hourglass-half' : 'fa-hard-drive'); ?>"></i>
                <?php if ($soloBanner): ?>
                    Tu organización está en <strong>solo lectura</strong>. Consulta y exporta, pero no se pueden hacer cambios.
                <?php elseif ($trialBanner): ?>
                    Plan <strong><?php echo s($orgBanner->plan()['nombre']); ?></strong> en prueba<?php echo $diasBanner !== null ? ' · quedan ' . max(0, (int)$diasBanner) . ' día(s)' : ''; ?>.
                <?php else: ?>
                    Almacenamiento de la organización al <strong><?php echo number_format($almPctBanner, 0); ?> %</strong> (<?php echo \Classes\Almacenamiento::formato($almUsadoBanner); ?> de <?php echo \Classes\Almacenamiento::formato((int)$almTotalBanner); ?>).
                <?php endif; ?>
            </span>
            <span class="demo-banner-acciones">
                <?php if (is_admin()): ?>
                    <a href="/organizaciones/editar?id=<?php echo (int)$orgBanner->id; ?>">Gestionar plan</a>
                <?php else: ?>
                    <a href="/soporte">Solicitar ampliación</a>
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>
    <?php endif; ?>
    <div class="app-container">
        <?php include_once __DIR__ . '/templates/appbar.php'; ?>
        <?php include_once __DIR__ . '/templates/sidebar.php'; ?>
        <div class="app-cuerpo">
            <?php if ($menuModoProyecto === 'panel' && $proyectoSidebar && $gruposHerramientasProyecto): ?>
                <?php navegacion_riel($gruposHerramientasProyecto, $navClaveActiva, $navGrupoActivo, $proyectoSidebar); ?>
            <?php endif; ?>
            <div class="app-principal">
                <?php if ($menuModoProyecto !== 'panel'): ?>
                    <?php if ($proyectoSidebar && $gruposHerramientasProyecto): ?>
                        <?php navegacion_proyecto($proyectoSidebar, $gruposHerramientasProyecto, $navClaveActiva, $navGrupoActivo); ?>
                    <?php else: ?>
                        <?php navegacion_global($menuGlobal); ?>
                    <?php endif; ?>
                <?php endif; ?>
                <main class="main-content" id="contenido-principal">
                    <?php include_once __DIR__ . '/templates/alertas.php'; ?>
                    <?php echo $contenido; ?>
                </main>
            </div>
        </div>
    </div>
    <?php if ($proyectoSidebar && $gruposHerramientasProyecto): ?>
        <?php navegacion_panel($gruposHerramientasProyecto, $navClaveActiva, $proyectoSidebar); ?>
    <?php endif; ?>
    <?php navegacion_inferior($proyectoSidebar); ?>
    <div id="sidebar-fondo" class="sidebar-fondo"></div>
    <?php include_once __DIR__ . '/templates/modal-confirm.php'; ?>
    <?php include __DIR__ . '/templates/pde-modal.php'; ?>
    <script src="/vendor/paletas.js?v=<?php echo filemtime(__DIR__ . '/../public/vendor/paletas.js'); ?>"></script>
    <script src="/vendor/capa-superior.js?v=<?php echo filemtime(__DIR__ . '/../public/vendor/capa-superior.js'); ?>"></script>
    <script src="/vendor/divisor.js?v=<?php echo filemtime(__DIR__ . '/../public/vendor/divisor.js'); ?>"></script>
    <script src="/vendor/selector-color.js?v=<?php echo filemtime(__DIR__ . '/../public/vendor/selector-color.js'); ?>"></script>
    <script src="/build/js/bundle.min.js?v=<?php echo filemtime(__DIR__ . '/../public/build/js/bundle.min.js'); ?>"></script>
<?php if (is_auth() && es_colaborador()): ?>
<script>
// El colaborador no borra: se ocultan los formularios de borrado (el servidor además los rechaza).
(function () {
    const selector = 'form[action*="/eliminar"], form[action*="/borrar"], form[action*="/quitar"]';
    const quitar = raiz => raiz.querySelectorAll && raiz.querySelectorAll(selector).forEach(f => f.remove());
    quitar(document);
    new MutationObserver(lista => lista.forEach(m => m.addedNodes.forEach(n => { if (n.nodeType === 1) { if (n.matches && n.matches(selector)) n.remove(); else quitar(n); } })))
        .observe(document.body, { childList: true, subtree: true });
})();
</script>
<?php endif; ?>
</body>
</html>
