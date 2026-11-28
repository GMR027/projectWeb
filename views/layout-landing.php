<?php
$descripcion = $descripcion ?? 'ProjectWeb: programa de obra con Gantt, lookahead, Last Planner, Vplan, materiales, reportes diarios, documentos y control de cambios en una sola plataforma.';
$ogImage = $ogImage ?? '/build/img/landing/hero.jpg';
$appUrl = rtrim((string)(getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '')), '/');
$rutaActual = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
$canonical = $appUrl !== '' ? $appUrl . $rutaActual : $rutaActual;
$ogAbsoluta = $appUrl !== '' && !preg_match('#^https?://#', $ogImage) ? $appUrl . $ogImage : $ogImage;
$tituloCompleto = 'ProjectWeb · ' . ($titulo ?? 'Programas de obra');
$precioMinimo = (int)\Classes\Plan::de('obra')['precio_mensual'];
?>
<!DOCTYPE html>
<html lang="<?php echo idioma_actual(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title><?php echo s($tituloCompleto); ?></title>
    <meta name="description" content="<?php echo s($descripcion); ?>">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#0b132b">
    <link rel="canonical" href="<?php echo s($canonical); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="ProjectWeb">
    <meta property="og:locale" content="es_MX">
    <meta property="og:title" content="<?php echo s($tituloCompleto); ?>">
    <meta property="og:description" content="<?php echo s($descripcion); ?>">
    <meta property="og:url" content="<?php echo s($canonical); ?>">
    <meta property="og:image" content="<?php echo s($ogAbsoluta); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo s($tituloCompleto); ?>">
    <meta name="twitter:description" content="<?php echo s($descripcion); ?>">
    <meta name="twitter:image" content="<?php echo s($ogAbsoluta); ?>">
    <link rel="stylesheet" href="/vendor/fuentes/fuentes.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fuentes/fuentes.css'); ?>">
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fontawesome/css/all.min.css'); ?>">
    <link rel="stylesheet" href="/build/css/landing.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/landing.css'); ?>">
    <noscript><style>.lp-rev{opacity:1!important;transform:none!important}</style></noscript>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "ProjectWeb",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "description": <?php echo json_encode($descripcion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        "offers": {
            "@type": "Offer",
            "price": "<?php echo $precioMinimo; ?>",
            "priceCurrency": "MXN",
            "url": <?php echo json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        }
    }
    </script>
</head>
<body class="lp">
    <?php echo $contenido; ?>
    <script src="/vendor/landing.js?v=<?php echo filemtime(__DIR__ . '/../public/vendor/landing.js'); ?>" defer></script>
</body>
</html>
