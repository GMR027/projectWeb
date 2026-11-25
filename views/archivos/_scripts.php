<?php
$archivosJs = __DIR__ . '/../../public/vendor/archivos.js';
$archivosJsVersion = is_file($archivosJs) ? filemtime($archivosJs) : time();
?>
<script>
window.ARCHIVO = <?php echo json_encode([
    'proyecto' => (int)$proyecto->id,
    'puede' => (bool)$puede,
    'vistaInicial' => $vistaInicial,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="/vendor/archivos.js?v=<?php echo $archivosJsVersion; ?>"></script>
