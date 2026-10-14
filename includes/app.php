<?php
// includes/app.php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/funciones.php';

use Model\ActiveRecord;

// Zona horaria de la aplicación (php.ini trae UTC y adelanta el día por la tarde-noche en México).
// Se puede cambiar con APP_TIMEZONE en el .env; por defecto la del servidor (America/Mazatlan).
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Mazatlan');

ActiveRecord::setDB($db);
