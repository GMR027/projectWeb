<?php
// includes/app.php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/idioma.php';

use Model\ActiveRecord;

// Zona horaria de la aplicación (php.ini trae UTC y adelanta el día por la tarde-noche en México).
// Se puede cambiar con APP_TIMEZONE en el .env; por defecto la del servidor (America/Mazatlan).
// Cookie de sesión: no se envía en peticiones cruzadas (SameSite=Lax) ni es legible desde JavaScript
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    ]);
}
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Mazatlan');

ActiveRecord::setDB($db);
