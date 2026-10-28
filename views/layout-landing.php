<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title>ProjectWeb · <?php echo s($titulo ?? 'Programas de obra'); ?></title>
    <meta name="description" content="ProjectWeb: programa de obra con Gantt, 4WLA, Last Planner, vPlan, órdenes de cambio, líneas base, Redactor, SheetFlow, MSR, reporte diario y exportación a PDF, Excel y MS Project.">
    <link rel="stylesheet" href="/vendor/fuentes/fuentes.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fuentes/fuentes.css'); ?>">
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fontawesome/css/all.min.css'); ?>">
    <link rel="stylesheet" href="/build/css/landing.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/landing.css'); ?>">
</head>
<body class="lp">
    <?php echo $contenido; ?>
</body>
</html>
