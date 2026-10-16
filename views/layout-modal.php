<?php /* Layout mínimo para formularios que se muestran dentro de un modal (iframe), p. ej. desde el calendario */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo s($titulo ?? 'Programa de Obra'); ?> | ProjectWeb</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/build/css/app.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/app.css'); ?>">
    <script>
        (function() {
            let tema = 'dark';
            try { tema = localStorage.getItem('theme') || 'dark'; } catch (_) {}
            document.documentElement.setAttribute('data-theme', tema);
        })();
    </script>
</head>
<body class="body-modal">
    <main class="modal-contenido">
        <?php include_once __DIR__ . '/templates/alertas.php'; ?>
        <?php echo $contenido; ?>
    </main>
    <?php include_once __DIR__ . '/templates/modal-confirm.php'; ?>
    <script src="/build/js/bundle.min.js?v=<?php echo filemtime(__DIR__ . '/../public/build/js/bundle.min.js'); ?>"></script>
</body>
</html>
