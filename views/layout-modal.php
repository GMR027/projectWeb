<?php /* Layout mínimo para formularios que se muestran dentro de un modal (iframe), p. ej. desde el calendario */ ?>
<?php
// Mismo tema de proyecto que el layout principal
$temaModoProyecto = null;   // 'light' | 'dark' | null (el que elige cada usuario)
$temaPaletaProyecto = \Classes\TemaProyecto::POR_DEFECTO;
$temaCssProyecto = '';
if (isset($proyecto) && $proyecto instanceof \Model\Proyecto) {
    $temaModoProyecto = \Classes\TemaProyecto::temaForzado($proyecto->tema_modo ?? 'usuario');
    $temaPaletaProyecto = \Classes\TemaProyecto::normPaleta($proyecto->tema_paleta ?? 'naranja');
    $temaCssProyecto = \Classes\TemaProyecto::css($temaPaletaProyecto);
}
?>
<!DOCTYPE html>
<html lang="es" data-paleta="<?php echo s($temaPaletaProyecto); ?>"<?php if ($temaModoProyecto) echo ' data-tema-fijo="' . s($temaModoProyecto) . '"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo s($titulo ?? 'Programa de Obra'); ?> | ProjectWeb</title>
    <link rel="stylesheet" href="/vendor/fuentes/fuentes.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fuentes/fuentes.css'); ?>">
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css?v=<?php echo filemtime(__DIR__ . '/../public/vendor/fontawesome/css/all.min.css'); ?>">
    <link rel="stylesheet" href="/build/css/app.css?v=<?php echo filemtime(__DIR__ . '/../public/build/css/app.css'); ?>">
    <?php if ($temaCssProyecto): ?><style><?php echo $temaCssProyecto; ?></style><?php endif; ?>
    <script>
        (function() {
            let tema = 'dark';
            const fijo = document.documentElement.getAttribute('data-tema-fijo');
            if (fijo) { tema = fijo; }
            else { try { tema = localStorage.getItem('theme') || 'dark'; } catch (_) {} }
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
