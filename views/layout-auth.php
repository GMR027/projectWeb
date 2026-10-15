<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title><?php echo s($titulo ?? 'Iniciar Sesión'); ?> | ProjectWeb</title>
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
<body class="auth-body">
    <main class="auth-container">
        <?php include_once __DIR__ . '/templates/alertas.php'; ?>
        <?php echo $contenido; ?>
    </main>
    <script src="/build/js/bundle.min.js?v=<?php echo filemtime(__DIR__ . '/../public/build/js/bundle.min.js'); ?>"></script>
</body>
</html>
