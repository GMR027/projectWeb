<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title><?php echo s($titulo ?? 'Proyecto'); ?> | ProjectWeb</title>
    <link rel="stylesheet" href="/vendor/fuentes/fuentes.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fuentes/fuentes.css'); ?>">
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fontawesome/css/all.min.css'); ?>">
    <link rel="stylesheet" href="/build/css/app.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/app.css'); ?>">
    <script>
        (function() {
            const tema = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', tema);
        })();
    </script>
</head>
<body class="publico-body">
    <main class="publico">
        <?php echo $contenido; ?>
        <footer class="publico-pie text-muted text-sm">Vista de solo lectura · ProjectWeb</footer>
    </main>
</body>
</html>
