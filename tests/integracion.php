<?php
// Pruebas de integración con una base de datos real (vacía, solo para el test).
// Uso local: DB_HOST=127.0.0.1 DB_PORT=3307 DB_USER=root DB_PASS= DB_NAME=project_ci php tests/integracion.php
// En CI la BD viene de un servicio MySQL efímero; aquí se borra y se recrea desde database/schema.sql.

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/idioma.php';
require __DIR__ . '/../includes/funciones.php';

use Classes\LoginLimite;
use Model\ActiveRecord;
use Model\Usuario;

$fallos = 0;
$ok = function (string $nombre, bool $cond) use (&$fallos) { echo ($cond ? '  ok   ' : '  FALLA ') . $nombre . "\n"; if (!$cond) $fallos++; };

// ---------- conexión y esquema ----------
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$name = getenv('DB_NAME') ?: 'project_ci';
$sock = getenv('DB_SOCKET') ?: null;
if ($sock) $host = 'localhost';

$admin = new mysqli($host, $user, $pass, '', $port, $sock);
if ($admin->connect_error) { fwrite(STDERR, "No se pudo conectar a MySQL ({$admin->connect_error})\n"); exit(1); }
echo "Preparando BD '$name' desde database/schema.sql\n";
$admin->query("DROP DATABASE IF EXISTS `$name`");

$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$schema = str_replace(['CREATE DATABASE IF NOT EXISTS project', 'USE project;'], ["CREATE DATABASE IF NOT EXISTS `$name`", "USE `$name`;"], $schema);
$ok('el esquema completo se aplica sin errores', $admin->multi_query($schema));
$pendientes = true;
while ($pendientes && $admin->more_results()) $pendientes = $admin->next_result();
if ($admin->errno) { echo "  ERROR SQL: {$admin->error}\n"; $fallos++; }

$GLOBALS['db'] = new mysqli($host, $user, $pass, $name, $port, $sock);
$GLOBALS['db']->set_charset('utf8mb4');
ActiveRecord::setDB($GLOBALS['db']);

// ---------- seed del administrador ----------
echo "Seed del administrador\n";
$u = Usuario::where('email', 'admin@po.local');
$ok('existe el admin del seed', $u !== null && $u->rol === 'admin' && (int)$u->confirmado === 1);
$ok('el password del seed es «admin123»', $u !== null && $u->comprobarPassword('admin123'));
$ok('un password distinto falla', $u !== null && !$u->comprobarPassword('otra-cosa'));

// ---------- login_intentos / LoginLimite ----------
echo "Límite de intentos de acceso\n";
$ok('la tabla login_intentos existe', LoginLimite::disponible());
$ip = '203.0.113.10';
$email = 'admin@po.local';
$ok('al inicio no hay espera', LoginLimite::espera($ip, $email) === 0);
for ($i = 0; $i < LoginLimite::MAX_INTENTOS - 1; $i++) LoginLimite::fallo($ip, $email);
$ok('con ' . (LoginLimite::MAX_INTENTOS - 1) . ' fallos aún no hay bloqueo', LoginLimite::espera($ip, $email) === 0);
LoginLimite::fallo($ip, $email);
$espera = LoginLimite::espera($ip, $email);
$ok('al llegar al máximo hay bloqueo (≈ ' . LoginLimite::BLOQUEO . ' s)', $espera > 0 && $espera <= LoginLimite::BLOQUEO);
$fila = $GLOBALS['db']->query("SELECT intentos FROM login_intentos WHERE ip = '$ip' AND email = '$email'")->fetch_assoc();
$ok('el contador registra todos los fallos', (int)$fila['intentos'] === LoginLimite::MAX_INTENTOS);
$ok('otro correo desde la misma IP no está bloqueado', LoginLimite::espera($ip, 'otro@correo.mx') === 0);
$ok('otra IP con el mismo correo no está bloqueada', LoginLimite::espera('203.0.113.11', $email) === 0);
LoginLimite::exito($ip, $email);
$ok('el acceso correcto limpia el bloqueo', LoginLimite::espera($ip, $email) === 0);

$GLOBALS['db']->query("INSERT INTO login_intentos (ip, email, intentos, ultimo, bloqueado_hasta) VALUES ('198.51.100.9', 'viejo@x.mx', 3, " . (time() - 90000) . ', 0)');
LoginLimite::limpiar();
$ok('limpiar() borra registros de más de un día', $GLOBALS['db']->query("SELECT COUNT(*) n FROM login_intentos WHERE email = 'viejo@x.mx'")->fetch_assoc()['n'] == 0);

// ---------- jornada por defecto y calendario ----------
echo "Modelo básico\n";
$proy = new Model\Proyecto(['codigo' => 'CI-1', 'nombre' => 'Prueba CI', 'fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-12-31', 'usuario_id' => (int)$u->id]);
$r = $proy->guardar();
$ok('crear proyecto', (bool)$r['resultado']);
$proy->id = $r['id'];
$proy->inicializarJornadaDefault();
$jornada = Model\CalendarioJornada::whereAll('proyecto_id', $proy->id);
$ok('la jornada por defecto crea 7 días', count($jornada) === 7);
$cal = new Classes\Calendario((int)$proy->id);
// 2026-11-02 es lunes; 5 días hábiles (L-V=1) terminan el viernes 6
$ok('calcularFechaFin: 5 días hábiles desde el lunes terminan el viernes', $cal->calcularFechaFin('2026-11-02', 5) === '2026-11-06');
// Con medio sábado: 5.5 días no son enteros, pero 6 días pasan de L-V al lunes siguiente
$ok('calcularFechaFin: con sábado medio, 6 días caen al lunes siguiente', $cal->calcularFechaFin('2026-11-02', 6) === '2026-11-09');
$ok('un festivo se salta', (function () use ($proy) {
    (new Model\CalendarioFestivo(['proyecto_id' => $proy->id, 'fecha' => '2026-11-03', 'descripcion' => 'test']))->guardar();
    $cal2 = new Classes\Calendario((int)$proy->id);
    // 2 días hábiles: lunes 2 (queda 1) + martes es festivo → miércoles 4
    return $cal2->calcularFechaFin('2026-11-02', 2) === '2026-11-04';
})());

echo $fallos ? "\n$fallos prueba(s) fallaron\n" : "\nIntegración OK\n";
exit($fallos ? 1 : 0);
