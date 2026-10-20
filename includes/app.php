<?php
// includes/app.php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/idioma.php';

use Model\ActiveRecord;

// Zona horaria de la aplicación (php.ini trae UTC y adelanta el día por la tarde-noche en México).
// Se puede cambiar con APP_TIMEZONE en el .env; por defecto la del servidor (America/Mazatlan).
// Cookie de sesión: no se envía en peticiones cruzadas (SameSite=Lax) ni es legible desde JavaScript
// La app de escritorio (UA con "ProjectWebDesktop") conserva la sesión hasta que el usuario cierre sesión:
// cookie persistente de 1 año y archivos de sesión propios, para que el GC de la web (24 min) no los borre.
$esApp = str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'ProjectWebDesktop');
if ($esApp && session_status() === PHP_SESSION_NONE) {
    $dirSes = __DIR__ . '/../storage/sesiones-app';
    if (!is_dir($dirSes)) @mkdir($dirSes, 0700, true);
    if (is_dir($dirSes) && is_writable($dirSes)) session_save_path($dirSes);
    ini_set('session.gc_maxlifetime', '31536000');
}
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $esApp ? 31536000 : 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    ]);
}
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Mazatlan');

ActiveRecord::setDB($db);
