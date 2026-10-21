<?php
// Respaldo programado. Uso (cron, todos los días a las 11 pm):
//   0 23 * * * php /ruta/al/proyecto/bin/respaldo_programado.php
// Opciones: --forzar (hace el respaldo aunque no toque). Conserva solo los últimos N automáticos (por defecto 5).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/app.php';

use Classes\Respaldo;
use Classes\RespaldoProyecto;

$forzar = in_array('--forzar', $argv ?? [], true);
// Respaldo de cada proyecto: 30 23 * * * php .../bin/respaldo_programado.php --proyectos
if (in_array('--proyectos', $argv ?? [], true)) {
    try {
        $n = RespaldoProyecto::ejecutarSiToca($forzar);
        echo "[" . date('c') . "] " . ($n === null ? 'Nada que hacer' : "Proyectos respaldados: $n") . "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "[" . date('c') . "] Error: " . $e->getMessage() . "\n");
        exit(1);
    }
    exit;
}
try {
    $f = Respaldo::ejecutarSiToca($forzar);
    echo $f ? "[" . date('c') . "] Respaldo creado: $f\n" : "[" . date('c') . "] Nada que hacer (no toca o ya está hecho)\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[" . date('c') . "] Error: " . $e->getMessage() . "\n");
    exit(1);
}
