<?php
// Vigilancia del servidor (herramientas de IA). Uso (cron, cada 15 minutos; cada proyecto respeta su propio intervalo):
//   */15 * * * * php /ruta/al/proyecto/bin/vigilancia_programada.php
// Opciones: --forzar (comprueba todos los proyectos activos aunque no toque). Solo usa las reglas del sistema: no llama a ningún modelo.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/app.php';

use Classes\VigilanciaServidor;

try {
    $n = VigilanciaServidor::ejecutarPendientes('cron', in_array('--forzar', $argv ?? [], true));
    echo '[' . date('c') . '] ' . ($n ? "Proyectos comprobados: $n" : 'Nada que hacer') . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('c') . '] Error: ' . $e->getMessage() . "\n");
    exit(1);
}
