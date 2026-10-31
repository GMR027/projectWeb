<?php
// Incluir helper de breadcrumb
require_once __DIR__ . '/templates/breadcrumb.php';

// Tema del proyecto (Ajustes → Apariencia del proyecto). Sin proyecto manda la preferencia del usuario.
$temaModoProyecto = null;   // 'light' | 'dark' | null (el que elige cada usuario)
$temaPaletaProyecto = \Classes\TemaProyecto::POR_DEFECTO;
$temaCssProyecto = '';
$nombresHerramientas = false;   // barra superior de herramientas: nombre bajo cada icono (Ajustes → Apariencia)
$menuModoProyecto = 'clasico';  // barra superior: clásico, panel (menú lateral fijo) o pestañas
if (isset($proyecto) && $proyecto instanceof \Model\Proyecto) {
    $temaModoProyecto = \Classes\TemaProyecto::temaForzado($proyecto->tema_modo ?? 'usuario');
    $temaPaletaProyecto = \Classes\TemaProyecto::normPaleta($proyecto->tema_paleta ?? 'naranja');
    $temaCssProyecto = \Classes\TemaProyecto::css($temaPaletaProyecto);
    $nombresHerramientas = \Classes\TemaProyecto::nombresHerramientas($proyecto->nombres_herramientas ?? 0);
    $menuModoProyecto = \Classes\TemaProyecto::normMenu($proyecto->menu_superior ?? 'clasico');
}
$clasesHtml = [];
if ($nombresHerramientas) $clasesHtml[] = 'herr-con-nombres';
if ($menuModoProyecto !== 'clasico') $clasesHtml[] = 'menu-' . $menuModoProyecto;
?>
<!DOCTYPE html>
<html lang="<?php echo idioma_actual(); ?>" data-paleta="<?php echo s($temaPaletaProyecto); ?>"<?php echo $clasesHtml ? ' class="' . s(implode(' ', $clasesHtml)) . '"' : ''; ?><?php if ($temaModoProyecto) echo ' data-tema-fijo="' . s($temaModoProyecto) . '"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title><?php echo s($titulo ?? 'Programa de Obra'); ?> | ProjectWeb</title>
    <link rel="stylesheet" href="/vendor/fuentes/fuentes.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fuentes/fuentes.css'); ?>">
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fontawesome/css/all.min.css'); ?>">
    <link rel="stylesheet" href="/build/css/app.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/app.css'); ?>">
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
<body>
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
    <div class="app-container">
        <?php include_once __DIR__ . '/templates/sidebar.php'; ?>
        <main class="main-content" id="contenido-principal">
            <?php include_once __DIR__ . '/templates/topbar.php'; ?>
            <?php if (isset($proyecto)): ?>
                <?php
                $pagina_actual = '';
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                if (str_contains($uri, '/dependencias')) $pagina_actual = 'dependencias';
                elseif (str_contains($uri, '/programa')) $pagina_actual = 'programa';
                elseif (str_contains($uri, '/lookahead')) $pagina_actual = 'lookahead';
                elseif (str_contains($uri, '/vplan')) $pagina_actual = 'vplan';
                elseif (str_contains($uri, '/lastplanner')) $pagina_actual = 'lastplanner';
                elseif (str_contains($uri, '/reportes')) $pagina_actual = 'reportes';
                elseif (str_contains($uri, '/bitacora')) $pagina_actual = 'bitacora';
                elseif (str_contains($uri, '/minutas')) $pagina_actual = 'minutas';
                elseif (str_contains($uri, '/archivos')) $pagina_actual = 'archivos';
                elseif (str_contains($uri, '/curvas')) $pagina_actual = 'curvas';
                elseif (str_contains($uri, '/msr')) $pagina_actual = 'msr';
                elseif (str_contains($uri, '/datos-obra')) $pagina_actual = 'datos-obra';
                elseif (str_contains($uri, '/historial-cambios')) $pagina_actual = 'historial-cambios';
                elseif (str_contains($uri, '/lineas-base')) $pagina_actual = 'lineas-base';
                elseif (str_contains($uri, '/cambios')) $pagina_actual = 'cambios';
                elseif (str_contains($uri, '/planificador')) $pagina_actual = 'planificador';
                elseif (str_contains($uri, '/seguridad')) $pagina_actual = 'seguridad';
                elseif (str_contains($uri, '/punch')) $pagina_actual = 'punch';
                elseif (str_contains($uri, '/mapa-avance')) $pagina_actual = 'mapa-avance';
                elseif (str_contains($uri, '/plantillas')) $pagina_actual = 'plantillas';
                elseif (str_contains($uri, '/normativa')) $pagina_actual = 'normativa';
                elseif (str_contains($uri, '/programador')) $pagina_actual = 'programador';
                elseif (str_contains($uri, '/inconsistencias')) $pagina_actual = 'inconsistencias';
                elseif (str_contains($uri, '/analisis-materiales')) $pagina_actual = 'analisis-materiales';
                elseif (str_contains($uri, '/propuestas-ia')) $pagina_actual = 'propuestas-ia';
                elseif (str_contains($uri, '/revisiones')) $pagina_actual = 'revisiones';
                elseif (str_contains($uri, '/especialidades')) $pagina_actual = 'especialidades';
                elseif (str_contains($uri, '/informes-ia')) $pagina_actual = 'informes-ia';
                elseif (str_contains($uri, '/grabaciones')) $pagina_actual = 'grabaciones';
                elseif (str_contains($uri, '/historial-cambios')) $pagina_actual = 'historial-cambios';
                elseif (str_contains($uri, '/biblioteca-ia')) $pagina_actual = 'biblioteca-ia';
                elseif (str_contains($uri, '/acciones')) $pagina_actual = 'acciones';
                elseif (str_contains($uri, '/submittals')) $pagina_actual = 'submittals';
                elseif (str_contains($uri, '/materiales')) $pagina_actual = 'materiales';
                elseif (str_contains($uri, '/calendario')) $pagina_actual = 'calendario';
                elseif (str_contains($uri, '/graficos')) $pagina_actual = 'editor';
                elseif (str_contains($uri, '/redactor')) $pagina_actual = 'redactor';
                elseif (str_contains($uri, '/sheetflow')) $pagina_actual = 'sheetflow';
                elseif (str_contains($uri, '/simbolos')) $pagina_actual = 'simbolos';
                elseif (str_contains($uri, '/view360')) $pagina_actual = 'view360';
                elseif (str_contains($uri, '/comparador')) $pagina_actual = 'comparador';
                elseif (str_contains($uri, '/traductor')) $pagina_actual = 'traductor';
                elseif (str_contains($uri, '/evidencias')) $pagina_actual = 'evidencias';
                elseif (str_contains($uri, '/ajustes')) $pagina_actual = 'ajustes';
                elseif (str_contains($uri, '/exportar')) $pagina_actual = 'exportar';
                breadcrumb_proyecto($proyecto, $pagina_actual, $menuModoProyecto);
                ?>
            <?php endif; ?>
            <?php include_once __DIR__ . '/templates/alertas.php'; ?>
            <?php echo $contenido; ?>
        </main>
    </div>
    <button type="button" id="btn-menu" class="btn-menu-movil" aria-label="Abrir menú" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    <div id="sidebar-fondo" class="sidebar-fondo"></div>
    <?php include_once __DIR__ . '/templates/modal-confirm.php'; ?>
    <?php include __DIR__ . '/templates/pde-modal.php'; ?>
    <script src="/vendor/paletas.js?v=<?php echo filemtime(__DIR__ . '/../public/vendor/paletas.js'); ?>"></script>
    <script src="/vendor/divisor.js?v=<?php echo filemtime(__DIR__ . '/../public/vendor/divisor.js'); ?>"></script>
    <script src="/vendor/selector-color.js?v=<?php echo filemtime(__DIR__ . '/../public/vendor/selector-color.js'); ?>"></script>
    <script src="/build/js/bundle.min.js?v=<?php echo filemtime(__DIR__ . '/../public/build/js/bundle.min.js'); ?>"></script>
</body>
</html>
