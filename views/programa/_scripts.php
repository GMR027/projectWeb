<?php
$prgConfig = [
    'proyecto' => (int)$proyecto->id,
    'vista' => $vista ?? 'ambos',
    'hoyCol' => 0,
    'depFlechas' => $depFlechas ?? [],
    'puedeGestionar' => $puedeGestionar,
    'puedeBorrar' => $puedeBorrar,
];
// Columna de la semana actual, para la línea de «Hoy» del Gantt.
$hoyJS = date('Y-m-d');
foreach (($semanas ?? []) as $iSem => $semJS) {
    if ($hoyJS >= $semJS['inicio'] && $hoyJS <= $semJS['fin']) { $prgConfig['hoyCol'] = $iSem + 1; break; }
}
?>
<script>
window.PRG = <?php echo json_encode($prgConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>
<script src="/vendor/programa.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/programa.js'); ?>"></script>
