<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title>ProjectWeb · <?php echo s($titulo ?? 'Programas de obra'); ?></title>
    <meta name="description" content="ProjectWeb: programa de obra con Gantt, 4WLA, Last Planner, vPlan, curvas de productividad, MSR, reporte diario y exportación a PDF, Excel y MS Project.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/build/css/landing.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/landing.css'); ?>">
</head>
<body class="lp">
    <?php echo $contenido; ?>
</body>
</html>
