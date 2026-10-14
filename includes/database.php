<?php
// includes/database.php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$name = $_ENV['DB_NAME'] ?? 'project';

$db = new mysqli($host, $user, $pass, $name);
$db->set_charset('utf8mb4');

if ($db->connect_error) {
    die('Error de conexión (' . $db->connect_errno . ') ' . $db->connect_error);
}
