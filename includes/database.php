<?php
// includes/database.php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$name = $_ENV['DB_NAME'] ?? 'project';

try {
    $db = new mysqli($host, $user, $pass, $name);
    $db->set_charset('utf8mb4');
} catch (\Throwable $e) {
    // No se muestra la contraseña ni la traza al visitante; el detalle queda en el log del servidor
    error_log('Conexión a la base de datos: ' . $e->getMessage());
    http_response_code(500);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'No se pudo conectar con la base de datos. Revisa DB_HOST, DB_USER, DB_PASS y DB_NAME en .env.' . PHP_EOL);
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
    die('No se pudo conectar con la base de datos. Revisa la configuración del servidor (DB_HOST, DB_USER, DB_PASS y DB_NAME).');
}
