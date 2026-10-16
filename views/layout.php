<?php
// Incluir helper de breadcrumb
require_once __DIR__ . '/templates/breadcrumb.php';
?>
<!DOCTYPE html>
<html lang="<?php echo idioma_actual(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title><?php echo s($titulo ?? 'Programa de Obra'); ?> | ProjectWeb</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/build/css/app.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/app.css'); ?>">
    <script>
        // Tema elegido por el usuario (guardado en localStorage).
        // Por defecto: oscuro.
        (function() {
            const tema = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', tema);
        })();
    </script>
</head>
<body>
    <?php if (es_demo()): ?>
        <div class="demo-banner">
            <span><i class="fa-solid fa-eye"></i> Estás viendo el <strong>demo</strong> con datos de ejemplo · solo lectura</span>
            <span class="demo-banner-acciones">
                <a href="/inicio#planes">Ver planes</a>
                <a href="/logout" class="demo-banner-salir">Salir del demo</a>
            </span>
        </div>
    <?php endif; ?>
    <div class="app-container">
        <?php include_once __DIR__ . '/templates/sidebar.php'; ?>
        <main class="main-content">
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
                elseif (str_contains($uri, '/submittals')) $pagina_actual = 'submittals';
                elseif (str_contains($uri, '/materiales')) $pagina_actual = 'materiales';
                elseif (str_contains($uri, '/calendario')) $pagina_actual = 'calendario';
                elseif (str_contains($uri, '/graficos')) $pagina_actual = 'editor';
                elseif (str_contains($uri, '/simbolos')) $pagina_actual = 'simbolos';
                elseif (str_contains($uri, '/view360')) $pagina_actual = 'view360';
                elseif (str_contains($uri, '/evidencias')) $pagina_actual = 'evidencias';
                elseif (str_contains($uri, '/ajustes')) $pagina_actual = 'ajustes';
                elseif (str_contains($uri, '/exportar')) $pagina_actual = 'exportar';
                breadcrumb_proyecto($proyecto, $pagina_actual);
                ?>
            <?php endif; ?>
            <?php include_once __DIR__ . '/templates/alertas.php'; ?>
            <?php echo $contenido; ?>
        </main>
    </div>
    <button type="button" id="btn-menu" class="btn-menu-movil" aria-label="Abrir menú" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    <div id="sidebar-fondo" class="sidebar-fondo"></div>
    <?php include_once __DIR__ . '/templates/modal-confirm.php'; ?>
    <script src="/build/js/bundle.min.js?v=<?php echo filemtime(__DIR__ . '/../public/build/js/bundle.min.js'); ?>"></script>
</body>
</html>
