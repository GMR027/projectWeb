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
if (session_status() === PHP_SESSION_NONE) {
    // Endurecimiento de la sesión: identificador propio, solo los generados por el servidor y sin
    // aceptar un id que llegue por la URL (fijación de sesión).
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('PWSESSID');
}
if ($esApp && session_status() === PHP_SESSION_NONE) {
    $dirSes = __DIR__ . '/../storage/sesiones-app';
    if (!is_dir($dirSes)) @mkdir($dirSes, 0700, true);
    if (is_dir($dirSes) && is_writable($dirSes)) session_save_path($dirSes);
    ini_set('session.gc_maxlifetime', '31536000');
}
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $esApp ? 31536000 : 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => es_https(),
    ]);
}
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Mazatlan');

// Cabeceras de seguridad para todas las respuestas de la aplicación (no en CLI ni en archivos ya servidos).
cabeceras_seguridad();

// Errores no controlados: página amable + referencia en el log (nunca la consulta ni la traza al usuario)
if (PHP_SAPI !== 'cli') {
    set_exception_handler('manejar_error_no_controlado');
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            manejar_error_no_controlado(new \ErrorException($e['message'], 0, $e['type'], $e['file'], $e['line']));
        }
    });
}

ActiveRecord::setDB($db);
