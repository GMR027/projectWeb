<?php
// Guarda la foto diaria del espacio usado (la corre el CronJob de Kubernetes; también sirve a mano):
//   php database/snapshot_almacenamiento.php
require_once __DIR__ . '/../includes/app.php';

$archivos = Classes\Volumen::archivos();
$bd = Classes\Volumen::baseDatos();
Classes\Volumen::registrarHoy($archivos['usado'], $bd['usado']);
echo date('c') . " archivos={$archivos['usado']} bd={$bd['usado']}\n";
