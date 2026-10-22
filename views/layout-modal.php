<?php /* Layout mínimo para formularios que se muestran dentro de un modal (iframe), p. ej. desde el calendario */ ?>
<?php
// Mismo tema de proyecto que el layout principal
$temaModoProyecto = null;
$temaPaletaProyecto = \Classes\TemaProyecto::POR_DEFECTO;
$temaCssProyecto = '';
if (isset($proyecto) && $proyecto instanceof \Model\Proyecto) {
    $temaModoProyecto = \Classes\TemaProyecto::normModo($proyecto->tema_modo ?? 'usuario');
    $temaPaletaProyecto = \Classes\TemaProyecto::normPaleta($proyecto->tema_paleta ?? 'naranja');
    if ($temaModoProyecto === 'usuario') $temaModoProyecto = null;
    $temaCssProyecto = \Classes\TemaProyecto::css($temaPaletaProyecto);
}
?>
<!DOCTYPE html>
<html lang="es" data-paleta="<?php echo s($temaPaletaProyecto); ?>"<?php if ($temaModoProyecto) echo ' data-tema-fijo="' . s($temaModoProyecto) . '"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo s($titulo ?? 'Programa de Obra'); ?> | ProjectWeb</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
