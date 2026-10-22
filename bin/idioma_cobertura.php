<?php
// Cobertura de la traducción: textos t('…') del código que no están en includes/lang/en.php.
// Uso: php bin/idioma_cobertura.php [--lista]   (sale con código 1 si falta alguno)
$raiz = dirname(__DIR__);
$dic = require $raiz . '/includes/lang/en.php';
$faltan = [];
$total = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $ruta = $f->getPathname();
    if (!str_ends_with($ruta, '.php') || preg_match('#/(vendor|node_modules|storage|public/build|desktop|tests)/#', $ruta)) continue;
    $src = file_get_contents($ruta);
    if (!preg_match_all("/\\bt\\((?:'((?:[^'\\\\]|\\\\.)*)'|\"((?:[^\"\\\\]|\\\\.)*)\")/", $src, $m, PREG_SET_ORDER)) continue;
    foreach ($m as $x) {
        $txt = stripcslashes($x[1] !== '' ? $x[1] : ($x[2] ?? ''));
        if ($txt === '') continue;
        $total++;
        $base = str_contains($txt, ' - ') ? explode(' - ', $txt, 2)[0] : $txt;   // los textos «Sección - Proyecto» se traducen por la primera parte
        if (!isset($dic[$txt]) && !isset($dic[$base])) $faltan[$txt][] = substr($ruta, strlen($raiz) + 1);
    }
}
echo count($dic) . " traducciones · $total usos de t() · " . count($faltan) . " texto(s) sin traducir\n";
if (in_array('--lista', $argv, true) || $faltan) foreach ($faltan as $t => $fs) echo "  - " . $t . "   (" . $fs[0] . ")\n";
exit($faltan ? 1 : 0);
