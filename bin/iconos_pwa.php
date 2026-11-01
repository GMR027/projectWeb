<?php
/**
 * Genera los iconos de la PWA (manifest.webmanifest) con GD, sin depender de archivos externos.
 * Uso: php bin/iconos_pwa.php
 * Salida: public/build/img/pwa/icon-192.png, icon-512.png, apple-touch-icon.png
 */
$destino = __DIR__ . '/../public/build/img/pwa';
if (!is_dir($destino)) mkdir($destino, 0775, true);

/** Rectángulo redondeado relleno */
function pw_rect_redondeado($im, $x1, $y1, $x2, $y2, $r, $color): void
{
    $r = max(0, min($r, (int)(min($x2 - $x1, $y2 - $y1) / 2)));
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
}

function pw_icono(int $tam, string $ruta): void
{
    $k = $tam / 512;
    $im = imagecreatetruecolor($tam, $tam);
    imagesavealpha($im, true);
    imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);

    $fondo   = imagecolorallocate($im, 15, 23, 42);      // --bg-primary (oscuro)
    $naranja = imagecolorallocate($im, 249, 115, 22);    // --primary
    $claro   = imagecolorallocate($im, 241, 245, 249);   // casi blanco

    // Fondo redondeado
    pw_rect_redondeado($im, 0, 0, $tam - 1, $tam - 1, (int)(96 * $k), $fondo);

    // Casco de obra: cúpula + ala
    $cx = (int)(256 * $k);
    imagefilledarc($im, $cx, (int)(214 * $k), (int)(226 * $k), (int)(196 * $k), 180, 360, $claro, IMG_ARC_PIE);
    pw_rect_redondeado($im, (int)(126 * $k), (int)(200 * $k), (int)(386 * $k), (int)(236 * $k), (int)(18 * $k), $claro);
    // Cresta del casco en naranja
    pw_rect_redondeado($im, (int)(236 * $k), (int)(128 * $k), (int)(276 * $k), (int)(176 * $k), (int)(14 * $k), $naranja);

    // Barras tipo programa/Gantt
    $barras = [[92, 300, 120], [230, 300, 186], [368, 300, 236]];
    foreach ($barras as [$x, $y, $alto]) {
        pw_rect_redondeado($im, (int)($x * $k), (int)($y * $k), (int)(($x + 52) * $k), (int)(($y + $alto) * $k), (int)(10 * $k), $naranja);
    }

    imagepng($im, $ruta);
    imagedestroy($im);
}

pw_icono(192, $destino . '/icon-192.png');
pw_icono(512, $destino . '/icon-512.png');
pw_icono(180, $destino . '/apple-touch-icon.png');
echo "Iconos generados en public/build/img/pwa/\n";
