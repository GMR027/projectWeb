<?php
// Clima automático (Integraciones → Clima automático). Uso (cron, todos los días a las 6 pm; sin cron se hace solo en la primera visita después de la hora):
//   0 18 * * * php /ruta/al/proyecto/bin/clima_programado.php
// Opciones: --forzar (corre ahora aunque no toque o ya se haya hecho hoy).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/app.php';

use Classes\ClimaAuto;
use Classes\Telegram;

try {
    $r = ClimaAuto::ejecutarSiToca(in_array('--forzar', $argv ?? [], true));
    if ($r === null) { echo '[' . date('c') . "] Nada que hacer (no toca o ya está hecho)\n"; exit; }
    foreach ($r as $pid => $x) echo '[' . date('c') . "] Proyecto $pid: " . ($x['ok'] ? '' : 'ERROR ') . $x['mensaje'] . "\n";
    Telegram::vaciar();   // envía ya los avisos de Telegram en cola
    if (!$r) echo '[' . date('c') . "] Ningún proyecto tiene el clima automático activo\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('c') . '] Error: ' . $e->getMessage() . "\n");
    exit(1);
}
