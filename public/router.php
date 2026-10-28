<?php
/**
 * Router para el servidor de desarrollo: `php -S localhost:8000 router.php` desde public/.
 * Hace lo mismo que Apache: las fotos y planos de /uploads/ pasan por index.php (con permiso),
 * y el resto de archivos existentes se sirven tal cual.
 */
$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($ruta, '/uploads/')) {
    require __DIR__ . '/index.php';
    return true;
}
if ($ruta !== '/' && is_file(__DIR__ . $ruta)) {
    return false;   // deja que el servidor entregue el CSS, JS, imágenes públicas del build, etc.
}
require __DIR__ . '/index.php';
return true;
