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
// Sesión desde el principio: funciones como usuario_actual() la piden y en CLI ya habría salida (aviso de headers)
if (session_status() === PHP_SESSION_NONE) @session_start();

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

// ---------- esquema de dependencias de materiales ----------
echo "Dependencias de materiales (esquema)\n";
$ok('tabla material_dependencias', (bool)$GLOBALS['db']->query("SHOW TABLES LIKE 'material_dependencias'")->fetch_row());
$ok('tabla material_dependencias_cambios', (bool)$GLOBALS['db']->query("SHOW TABLES LIKE 'material_dependencias_cambios'")->fetch_row());
$ok('columna rol en actividad_materiales', (bool)$GLOBALS['db']->query("SHOW COLUMNS FROM actividad_materiales LIKE 'rol'")->fetch_row());
$ok('columna modo_dependencias_materiales en proyectos', (bool)$GLOBALS['db']->query("SHOW COLUMNS FROM proyectos LIKE 'modo_dependencias_materiales'")->fetch_row());

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


// ---------- Telegram: vinculación, avance y fotos por chat, avisos y clima automático ----------
echo "Telegram y clima automático\n";
putenv('TELEGRAM_BOT_TOKEN=123456:TOKEN-DE-PRUEBA'); $_ENV['TELEGRAM_BOT_TOKEN'] = '123456:TOKEN-DE-PRUEBA';
putenv('TELEGRAM_BOT_USERNAME=pw_prueba_bot'); $_ENV['TELEGRAM_BOT_USERNAME'] = 'pw_prueba_bot';
use Classes\Telegram;
use Classes\TelegramBot;
use Classes\ClimaAuto;
use Classes\Clima;
$db = $GLOBALS['db'];
$enviados = [];
$jpg = (function () { $im = imagecreatetruecolor(40, 30); ob_start(); imagejpeg($im); return ob_get_clean(); })();
Telegram::$http = function (string $m, array $p) use (&$enviados, $jpg) {
    if ($m === 'sendMessage') { $enviados[] = $p; return ['ok' => true]; }
    if ($m === 'getFile') return ['ok' => true, 'result' => ['file_path' => 'photos/' . $p['file_id'] . '.jpg', 'file_size' => strlen($jpg)]];
    if ($m === '__descargar') return ['bytes' => $jpg];
    return ['ok' => true, 'result' => []];
};
$ultimo = function () use (&$enviados) { $x = end($enviados); return $x ? $x['text'] : ''; };
$teclas = function () use (&$enviados) { $x = end($enviados); return array_merge(...($x['reply_markup']['inline_keyboard'] ?? [])); };
$upd = 0;
$msg = function (int $chat, string $texto, string $tipo = 'private', array $extra = []) use (&$upd) {
    return ['update_id' => ++$upd, 'message' => ['chat' => ['id' => $chat, 'type' => $tipo, 'title' => 'Grupo obra'], 'from' => ['first_name' => 'Prueba'], 'text' => $texto] + $extra];
};
$cb = function (int $chat, string $data) use (&$upd) { return ['update_id' => ++$upd, 'callback_query' => ['id' => 'q' . $upd, 'data' => $data, 'message' => ['chat' => ['id' => $chat, 'type' => 'private']]]]; };

$hoy = date('Y-m-d');
$db->query("INSERT INTO partidas (proyecto_id, codigo, nombre) VALUES ({$proy->id}, 'P1', 'Estructura')");
$parId = $db->insert_id;
$db->query("INSERT INTO actividades (partida_id, codigo, nombre, fecha_inicio, duracion_dias, fecha_fin) VALUES ($parId, '1.1', 'Colado de losa exterior', '" . date('Y-m-d', strtotime('-1 day')) . "', 6, '" . date('Y-m-d', strtotime('+5 days')) . "')");
$actId = $db->insert_id;
$db->query("INSERT INTO actividades (partida_id, codigo, nombre, fecha_inicio, duracion_dias, fecha_fin) VALUES ($parId, '1.2', 'Muros interiores', '$hoy', 3, '" . date('Y-m-d', strtotime('+2 days')) . "')");
$act2 = $db->insert_id;

$ok('el secreto del webhook se deriva del token (48 caracteres)', strlen(Telegram::secretoWebhook()) === 48 && Telegram::secretoWebhook() !== hash('sha256', 'x'));

// token en la base (cifrado): se guarda y se quita desde la interfaz, sin tocar el entorno ni el clúster
$mockBase = Telegram::$http;
$nuevoTok = '987654321:AAFDEMOGUARDADO_0123456789abcdefghijklmnop';
Telegram::$http = fn(string $m, array $p) => $m === 'getMe'
    ? ['ok' => true, 'result' => ['username' => 'projectweb_prueba_bot', 'first_name' => 'ProjectWeb']]
    : ['ok' => true, 'result' => []];
[$okTok, $msgTok] = Telegram::guardarToken($nuevoTok, 'Admin de pruebas');
Telegram::$http = $mockBase;
$ok('el token pasa la comprobación con getMe y queda activo', $okTok && Telegram::token() === $nuevoTok);
$ok('lo que se guarda en la tabla no es el token en claro', (string)$db->query('SELECT token_cifrado c FROM telegram_config WHERE id = 1')->fetch_assoc()['c'] !== $nuevoTok);
$cfgTok = Telegram::configGuardada();
$ok('en la pantalla solo se ven la máscara y el @del bot', $cfgTok['guardado'] && $cfgTok['origen'] === 'base' && $cfgTok['username'] === 'projectweb_prueba_bot'
    && str_contains($cfgTok['mascara'], 'mnop') && !str_contains($cfgTok['mascara'], 'DEMO') && $cfgTok['actualizado_por'] === 'Admin de pruebas');
$secConToken = Telegram::secretoWebhook();   // derivado del token que está en la base
$ok('un token con formato raro no se guarda', Telegram::guardarToken('no-es-token', 'x')[0] === false && Telegram::token() === $nuevoTok);
$ok('al quitarlo vuelve el token del entorno', Telegram::borrarToken() && Telegram::token() === '123456:TOKEN-DE-PRUEBA' && Telegram::configGuardada()['origen'] === 'entorno');
$ok('el secreto del webhook cambia con el token (hay que re-registrarlo)', $secConToken !== Telegram::secretoWebhook() && strlen($secConToken) === 48);
$ok('el comando se separa de su texto y del @bot', TelegramBot::comando('/Avance@pw_bot muro 3') === ['avance', 'muro 3'] && TelegramBot::comando('hola') === null);

// persona sin vincular
TelegramBot::procesar($msg(900, '/avance'));
$ok('un chat sin vincular recibe instrucciones y no hace nada', str_contains($ultimo(), 'Conectar mi Telegram'));

// vincular con código
$codigo = Telegram::crearCodigo((int)$u->id, 'usuario');
$ok('el enlace usa el bot y el código', Telegram::enlace($codigo, 'usuario') === "https://t.me/pw_prueba_bot?start=$codigo" && str_contains(Telegram::enlace($codigo, 'grupo'), '?startgroup='));
TelegramBot::procesar($msg(1001, "/start $codigo"));
$v = Telegram::vinculoDeUsuario((int)$u->id);
$ok('/start con código vincula el chat con la persona', $v && (int)$v['chat_id'] === 1001);
$ok('el código es de un solo uso', Telegram::codigo($codigo) === null);
TelegramBot::procesar($msg(1002, "/start $codigo"));
$ok('un código gastado no vincula otro chat', Telegram::vinculoDeChat(1002) === null && str_contains($ultimo(), 'ya no sirve'));

// avance con botones
TelegramBot::procesar($msg(1001, '/avance'));
$ok('/avance lista las actividades de hoy como botones', in_array("a:$actId", array_column($teclas(), 'callback_data'), true) && in_array("a:$act2", array_column($teclas(), 'callback_data'), true));
TelegramBot::procesar($cb(1001, "a:$actId"));
$ok('elegir la actividad ofrece los porcentajes', in_array("p:$actId:50", array_column($teclas(), 'callback_data'), true));
TelegramBot::procesar($msg(1001, '60'));
$a = Model\Actividad::find($actId);
$ok('escribir el número aplica el avance y el estatus', (int)$a->progreso === 60 && $a->status === 'en_proceso');
TelegramBot::procesar($msg(1001, '150'));
$ok('un porcentaje mayor a 100 se rechaza', (int)Model\Actividad::find($actId)->progreso === 60 && str_contains($ultimo(), '0 a 100'));
TelegramBot::procesar($cb(1001, "p:$actId:100"));
$a = Model\Actividad::find($actId);
$ok('el botón 100 % deja la actividad completada', (int)$a->progreso === 100 && $a->status === 'completada');
$fila = $db->query("SELECT progreso FROM actividad_avance WHERE actividad_id = $actId AND fecha = CURDATE()")->fetch_assoc();
$ok('queda el avance del día para la Curva S', $fila && (int)$fila['progreso'] === 100);

// repetición de Telegram (mismo update_id)
$antes = count($enviados);
$repetido = $cb(1001, "p:$act2:25");
TelegramBot::procesar($repetido); TelegramBot::procesar($repetido);
$ok('un update repetido se atiende una sola vez', count($enviados) === $antes + 1 && (int)Model\Actividad::find($act2)->progreso === 25);

// fotos
$ev0 = (int)$db->query("SELECT COUNT(*) n FROM evidencias WHERE proyecto_id = {$proy->id}")->fetch_assoc()['n'];
TelegramBot::procesar($msg(1001, '', 'private', ['photo' => [['file_id' => 'chica', 'file_size' => 10], ['file_id' => 'grande', 'file_size' => strlen($jpg)]], 'caption' => 'Armado listo']) + []);
$e1 = Model\Evidencia::deProyecto((int)$proy->id);
$ok('una foto con actividad ya elegida se guarda como evidencia', count($e1) === $ev0 + 1 && (int)$e1[0]->actividad_id === $act2 && $e1[0]->descripcion === 'Armado listo' && (int)$e1[0]->usuario_id === (int)$u->id);
TelegramBot::procesar($msg(1001, '/cancelar'));
TelegramBot::procesar($msg(1001, '', 'private', ['photo' => [['file_id' => 'otra', 'file_size' => strlen($jpg)]]]));
$ok('una foto sin actividad elegida pregunta con botones', in_array("f:$act2", array_column($teclas(), 'callback_data'), true));
TelegramBot::procesar($cb(1001, "f:$act2"));
$e2 = Model\Evidencia::deProyecto((int)$proy->id);
$ok('elegir la actividad guarda la foto pendiente', count($e2) === $ev0 + 2 && str_contains($ultimo(), 'foto(s) guardada'));
$ok('el archivo de la evidencia existe en disco', is_file(__DIR__ . '/../public/uploads/evidencias/' . $e2[0]->foto));
foreach ($e2 as $e) if ((int)$e->id > 0) { $e->eliminarArchivo(); $e->eliminar(); }   // solo las de esta prueba (BD recién creada)

// nota
TelegramBot::procesar($msg(1001, '/nota alta: falta concreto para el colado'));
$b = $db->query("SELECT importancia, descripcion FROM bitacora WHERE proyecto_id = {$proy->id} ORDER BY id DESC LIMIT 1")->fetch_assoc();
$ok('/nota crea la entrada de bitácora con su importancia', $b && $b['importancia'] === 'alta' && str_starts_with($b['descripcion'], 'falta concreto para el colado') && str_contains($b['descripcion'], 'Telegram'));

// permisos: un cliente con acceso pero que no es dueño solo ve
$cli = new Model\Usuario(['nombre' => 'Cliente CI', 'email' => 'cliente@ci.local', 'rol' => 'cliente', 'confirmado' => 1]);
$cli->password = password_hash('x', PASSWORD_DEFAULT);
$rc = $cli->guardar(); $cliId = (int)$rc['id'];
(new Model\ProyectoAcceso(['proyecto_id' => $proy->id, 'usuario_id' => $cliId]))->guardar();
$cc = Telegram::crearCodigo($cliId, 'usuario');
TelegramBot::procesar($msg(2001, "/start $cc"));
TelegramBot::procesar($msg(2001, '/avance'));
$ok('un cliente que no es dueño no puede reportar avance', str_contains($ultimo(), 'dueño del proyecto') && (int)Model\Actividad::find($act2)->progreso === 25);
TelegramBot::procesar($cb(2001, "p:$act2:90"));
$ok('ni forzando el botón', (int)Model\Actividad::find($act2)->progreso === 25);
TelegramBot::procesar($msg(2001, '/hoy'));
$ok('pero sí puede consultar /hoy', str_contains($ultimo(), 'Muros interiores'));
$visId = (int)(new Model\Usuario(['nombre' => 'Vis', 'email' => 'vis@ci.local', 'rol' => 'visitante', 'confirmado' => 1, 'password' => 'x']))->guardar()['id'];
$cv = Telegram::crearCodigo($visId, 'usuario');
TelegramBot::procesar($msg(3001, "/start $cv"));
$ok('un visitante no puede vincular Telegram', Telegram::vinculoDeUsuario($visId) === null);

// grupo y avisos
$cg = Telegram::crearCodigo((int)$u->id, 'grupo', (int)$proy->id);
TelegramBot::procesar($msg(1001, "/start $cg"));
$ok('el código de grupo no sirve en el chat privado', Telegram::destinos((int)$proy->id) === []);
TelegramBot::procesar($msg(-9001, "/start@pw_prueba_bot $cg", 'supergroup'));
$d = Telegram::destinos((int)$proy->id);
$ok('en un grupo, el código lo deja como destino de avisos', count($d) === 1 && (int)$d[0]['chat_id'] === -9001 && $d[0]['eventos'] === implode(',', Telegram::INICIALES));
TelegramBot::procesar($msg(-9001, '/avance', 'supergroup'));
$ok('el grupo no recibe órdenes', (int)Model\Actividad::find($act2)->progreso === 25);

$antes = count($enviados);
$n = Telegram::entregarAviso((int)$proy->id, 'rfi.creado', ['id' => 1, 'numero' => 'RFI-001', 'asunto' => 'Conflicto de columna <C>', 'prioridad' => 'alta', 'fecha_limite' => '2026-12-01']);
$ok('un RFI nuevo avisa al grupo con el asunto escapado', $n === 1 && count($enviados) === $antes + 1 && str_contains($ultimo(), 'RFI-001') && str_contains($ultimo(), '&lt;C&gt;') && (int)end($enviados)['chat_id'] === -9001);
$ok('un evento que el destino no pidió no se envía', Telegram::entregarAviso((int)$proy->id, 'punch.estado', ['titulo' => 'x', 'antes' => 'abierto', 'estado' => 'cerrado']) === 0);
$ok('la bitácora de importancia media no se avisa', Telegram::mensajeEvento((int)$proy->id, 'bitacora.creada', ['importancia' => 'media', 'descripcion' => 'x']) === null);
Telegram::guardarEventos((int)$proy->id, (int)$d[0]['id'], ['bitacora.creada', 'inventado.x']);
$ok('guardar avisos ignora los desconocidos', Telegram::destino((int)$proy->id, (int)$d[0]['id'])['eventos'] === 'bitacora.creada');
Telegram::guardarEventos((int)$proy->id, (int)$d[0]['id'], array_keys(Telegram::EVENTOS));
foreach (array_keys(Telegram::EVENTOS) as $ev) {
    $datos = ['id' => 1, 'numero' => 'N', 'asunto' => 'a', 'titulo' => 't', 'severidad' => 'alta', 'estado' => 'cerrado', 'actividad_id' => $actId, 'descripcion' => 'd', 'numero_submittal' => 'S-1', 'estatus' => 'aprobado', 'importancia' => 'alta', 'texto' => 'T'];
    if (Telegram::mensajeEvento((int)$proy->id, $ev, $datos) === null) { $ok("el evento $ev tiene mensaje", false); }
}
$ok('todos los eventos del catálogo tienen mensaje', true);

// el aviso sale solo cuando un modelo emite (Webhooks::emitir → Telegram)
$antes = count($enviados);
Classes\Webhooks::emitir((int)$proy->id, 'punch.creado', ['id' => 5, 'titulo' => 'Grieta en muro', 'severidad' => 'alta', 'ubicacion' => 'Eje C', 'responsable' => 'Obra civil']);
Telegram::vaciar();
$ok('Webhooks::emitir también avisa por Telegram', count($enviados) === $antes + 1 && str_contains($ultimo(), 'Grieta en muro'));

// bot expulsado del grupo
TelegramBot::procesar(['update_id' => ++$upd, 'my_chat_member' => ['chat' => ['id' => -9001, 'type' => 'supergroup'], 'new_chat_member' => ['status' => 'kicked']]]);
$ok('si expulsan al bot, el grupo deja de ser destino', Telegram::destinos((int)$proy->id) === []);

// mi chat como destino
[$okc] = Telegram::activarMiChat((int)$proy->id, ['id' => (int)$u->id, 'nombre' => 'Admin'], true);
$ok('puedo recibir avisos en mi chat privado', $okc && Telegram::destinos((int)$proy->id)[0]['usuario_id'] == $u->id);

// ---- clima automático ----
$proy->ubicacion = 'Silao, Guanajuato';
$db->query("UPDATE proyectos SET ubicacion = 'Silao, Guanajuato' WHERE id = {$proy->id}");
Telegram::guardarEventos((int)$proy->id, (int)Telegram::destinos((int)$proy->id)[0]['id'], ['clima.alerta']);
Clima::$http = function (string $url) {
    if (str_contains($url, 'geocoding')) return ['results' => [['name' => 'Silao', 'admin1' => 'Guanajuato', 'country' => 'México', 'latitude' => 20.9, 'longitude' => -101.4]]];
    if (str_contains($url, 'archive')) return null;
    $t = $l = $v = $tx = $tn = $pr = [];
    for ($i = 0; $i < 16; $i++) { $t[] = date('Y-m-d', strtotime("+$i days")); $l[] = $i === 1 ? 24.0 : 0.0; $v[] = 12.0; $tx[] = 30.0; $tn[] = 15.0; $pr[] = $i === 1 ? 90 : 5; }
    return ['daily' => ['time' => $t, 'precipitation_sum' => $l, 'wind_speed_10m_max' => $v, 'temperature_2m_max' => $tx, 'temperature_2m_min' => $tn, 'precipitation_probability_max' => $pr]];
};
$ok('el clima automático arranca apagado', !ClimaAuto::activo((int)$proy->id) && ClimaAuto::ejecutar() === []);
ClimaAuto::cambiarActivo((int)$proy->id, true);
$db->query("UPDATE actividades SET progreso = 40, status = 'en_proceso' WHERE id = $actId");   // la losa vuelve a estar en curso
$antes = count($enviados);
$proy = Model\Proyecto::find((int)$proy->id);
$r = ClimaAuto::procesar($proy);
Telegram::vaciar();
$nb = (int)$db->query("SELECT COUNT(*) n FROM bitacora WHERE proyecto_id = {$proy->id} AND departamento = 'Clima' AND fecha = '$hoy'")->fetch_assoc()['n'];
$ok('registra el clima de hoy en la bitácora', $r['ok'] && $nb === 1);
$ok('avisa que mañana llueve y menciona la actividad de exterior', count($enviados) === $antes + 1 && str_contains($ultimo(), 'lluvia') && str_contains($ultimo(), 'Colado de losa exterior') && !str_contains($ultimo(), 'Muros interiores'));
$antes = count($enviados);
ClimaAuto::procesar($proy); Telegram::vaciar();
$nb = (int)$db->query("SELECT COUNT(*) n FROM bitacora WHERE proyecto_id = {$proy->id} AND departamento = 'Clima' AND fecha = '$hoy'")->fetch_assoc()['n'];
$ok('una segunda corrida el mismo día no repite bitácora ni aviso', $nb === 1 && count($enviados) === $antes);
$antes = count($enviados);
ClimaAuto::procesar($proy, true); Telegram::vaciar();
$ok('«Probar ahora» (forzar) vuelve a avisar sin duplicar la bitácora', count($enviados) === $antes + 1 && (int)$db->query("SELECT COUNT(*) n FROM bitacora WHERE proyecto_id = {$proy->id} AND departamento = 'Clima'")->fetch_assoc()['n'] === 1);
Clima::$http = null;

// ---- /salud, /pdf y acciones sobre avisos ----
echo "Bot: /salud, /pdf y acciones sobre avisos\n";
// datos de prueba del bloque: pendiente, RFI y submittal, y el destino con todos los avisos activos
$db->query("INSERT INTO punch_items (proyecto_id, titulo, severidad, estado) VALUES ({$proy->id}, 'Falta etiqueta en gabinete', 'alta', 'abierto')");
$puId = (int)$db->insert_id;
$db->query("INSERT INTO rfis (proyecto_id, numero, asunto, pregunta, prioridad, estado, fecha, fecha_limite) VALUES ({$proy->id}, 'RFI-002', 'Tipo de impermeabilizante', '¿Cuál especificación?', 'alta', 'abierto', '$hoy', DATE_ADD('$hoy', INTERVAL 3 DAY))");
$rfiId = (int)$db->insert_id;
$db->query("INSERT INTO submittals (proyecto_id, fecha, numero_submittal, estatus) VALUES ({$proy->id}, '$hoy', 'SUB-001', 'enviado')");
$subId = (int)$db->insert_id;
$destinoId = (int)Telegram::destinos((int)$proy->id)[0]['id'];
Telegram::guardarEventos((int)$proy->id, $destinoId, array_keys(Telegram::EVENTOS));

TelegramBot::procesar($msg(1001, '/salud'));
$ok('/salud devuelve el semáforo con su indicador ámbar o rojo', str_contains($ultimo(), 'Salud de Prueba CI') && preg_match('/[🔴🟠]/u', $ultimo()) === 1 && str_contains($ultimo(), 'punch'));
TelegramBot::procesar($msg(1001, '/pdf'));
$ok('/pdf sin tipo pregunta con botones de tipo', count(array_filter($teclas(), fn($t) => ($t['callback_data'] ?? '') === 'doc:punch')) === 1 && count(array_filter($teclas(), fn($t) => ($t['callback_data'] ?? '') === 'doc:programa')) === 1);
$docs = [];
$mockBase2 = Telegram::$http;
Telegram::$http = function (string $m, array $p) use (&$enviados, &$docs) {
    if ($m === 'sendDocument') { $docs[] = $p; $enviados[] = ['chat_id' => $p['chat_id'], 'text' => '[archivo] ' . $p['file_name']]; return ['ok' => true]; }
    if ($m === 'sendMessage') { $enviados[] = $p; return ['ok' => true]; }
    return ['ok' => true, 'result' => []];
};
TelegramBot::procesar($cb(1001, 'doc:punch'));
$ok('el botón del punch manda el PDF al chat', count($docs) === 1 && str_ends_with($docs[0]['file_name'], '.pdf') && ($docs[0]['bytes'] ?? '') !== '' && str_contains((string)$docs[0]['caption'], 'Punch list'));
$ok('el PDF generado no se queda en el servidor', count(glob(sys_get_temp_dir() . '/tgpunch*.pdf') ?: []) === 0);
Telegram::$http = $mockBase2;

// punch: botón «Corregido» desde un aviso (con permiso) y sin permiso
$antes = count($enviados);
Telegram::entregarAviso((int)$proy->id, 'punch.creado', ['id' => $puId, 'titulo' => 'Falta etiqueta en gabinete', 'severidad' => 'alta', 'ubicacion' => 'Nivel 2', 'responsable' => 'Juan']);
$ok('el aviso de punch trae los botones de acción en el chat privado', count($enviados) === $antes + 1 && count(array_filter($teclas(), fn($t) => ($t['callback_data'] ?? '') === "pu:$puId:corregido")) === 1);
TelegramBot::procesar($cb(1001, "pu:$puId:corregido"));
$ok('el botón «Corregido» cambia el estado del pendiente', (string)$db->query("SELECT estado FROM punch_items WHERE id = $puId")->fetch_row()[0] === 'corregido' && str_contains($ultimo(), 'Corregido'));
TelegramBot::procesar($cb(1001, "pu:$puId:corregido"));
$ok('pulsarlo dos veces no repite el cambio', str_contains($ultimo(), 'ya estaba'));
TelegramBot::procesar($cb(2001, "pu:$puId:corregido"));   // cliente con acceso pero no dueño
$ok('un cliente que no es dueño no puede cambiar el punch por botón', (string)$db->query("SELECT estado FROM punch_items WHERE id = $puId")->fetch_row()[0] === 'corregido' && str_contains($ultimo(), 'dueño del proyecto'));

// RFI: botón «Responder» → el siguiente texto se guarda como respuesta
$antes = count($enviados);
Telegram::entregarAviso((int)$proy->id, 'rfi.creado', ['id' => $rfiId, 'numero' => 'RFI-002', 'asunto' => 'Tipo de impermeabilizante', 'prioridad' => 'alta', 'fecha_limite' => date('Y-m-d', strtotime('+3 days'))]);
$ok('el aviso de RFI trae el botón «Responder»', count($enviados) === $antes + 1 && count(array_filter($teclas(), fn($t) => ($t['callback_data'] ?? '') === "rs:$rfiId")) === 1);
TelegramBot::procesar($cb(1001, "rs:$rfiId"));
$ok('el botón «Responder» pide el texto', str_contains($ultimo(), 'RFI-002') && str_contains($ultimo(), 'Respondido'));
TelegramBot::procesar($cb(2001, "rs:$rfiId"));   // el cliente no dueño lo intenta
$ok('un cliente que no es dueño no puede responder RFI', str_contains($ultimo(), 'dueño del proyecto'));
TelegramBot::procesar($msg(1001, 'Usar impermeabilizante de 2 capas, marca Sika.'));
$rf = $db->query("SELECT respuesta, estado, fecha_respuesta FROM rfis WHERE id = $rfiId")->fetch_assoc();
$ok('el texto escrito se guarda como respuesta y pasa a Respondido', str_contains($rf['respuesta'], '2 capas') && $rf['estado'] === 'respondido' && $rf['fecha_respuesta'] !== null);

// submittal: botones de estatus
$antes = count($enviados);
Telegram::entregarAviso((int)$proy->id, 'submittal.actualizado', ['id' => $subId, 'numero_submittal' => 'SUB-001', 'estatus' => 'enviado']);
$ok('el aviso de submittal trae Aprobado, A/AN y Resubmitar', count($enviados) === $antes + 1 && count(array_filter($teclas(), fn($t) => str_starts_with($t['callback_data'] ?? '', "su:$subId:"))) === 3);
TelegramBot::procesar($cb(1001, "su:$subId:aprobado"));
$ok('el botón «Aprobado» cambia el estatus del submittal', (string)$db->query("SELECT estatus FROM submittals WHERE id = $subId")->fetch_row()[0] === 'aprobado' && str_contains($ultimo(), 'Aprobado'));
TelegramBot::procesar($cb(2001, "su:$subId:rechazado"));
$ok('un cliente que no es dueño no cambia submittals por botón', (string)$db->query("SELECT estatus FROM submittals WHERE id = $subId")->fetch_row()[0] === 'aprobado');

// ---- resumen de la obra cada mañana ----
echo "Resumen de la obra\n";
$ok('el resumen arranca apagado y sin corridas', !Classes\ResumenObra::activo((int)$proy->id) && Classes\ResumenObra::ejecutar() === [] && Classes\ResumenObra::texto($proy) !== '');
Classes\ResumenObra::cambiarActivo((int)$proy->id, true);
Telegram::guardarEventos((int)$proy->id, $destinoId, ['resumen.matutino']);
$antes = count($enviados);
$r = Classes\ResumenObra::procesar($proy); Telegram::vaciar();
$ok('el resumen se envía a los destinos que pidieron el evento', $r['ok'] && count($enviados) === $antes + 1 && str_contains($ultimo(), 'Resumen de la mañana') && str_contains($ultimo(), 'Actividades de hoy'));
$ok('queda marcado el día para no repetir', (string)$db->query("SELECT resumen_ultimo FROM proyectos WHERE id = {$proy->id}")->fetch_row()[0] === $hoy);
$antes = count($enviados);
$r = Classes\ResumenObra::procesar($proy); Telegram::vaciar();
$ok('una segunda corrida el mismo día no repite el resumen', $r['ok'] && count($enviados) === $antes);
$antes = count($enviados);
$r = Classes\ResumenObra::procesar($proy, true); Telegram::vaciar();
$ok('«Probar ahora» (forzar) lo vuelve a mandar', $r['ok'] && count($enviados) === $antes + 1 && str_contains($ultimo(), 'Resumen de la mañana'));
Telegram::guardarEventos((int)$proy->id, $destinoId, ['clima.alerta']);
$antes = count($enviados);
$r = Classes\ResumenObra::procesar($proy, true); Telegram::vaciar();
$ok('si ningún destino pidió el evento se explica en vez de mandar a ciegas', $r['ok'] && count($enviados) === $antes && str_contains($r['mensaje'], 'Resumen de la obra cada mañana'));
$sinEvento = Classes\ResumenObra::texto($proy);
$ok('el resumen trae clima, actividades y semáforo', str_contains($sinEvento, 'Resumen de la mañana') && str_contains($sinEvento, 'Actividades de hoy') && str_contains($sinEvento, 'Semáforo'));
// día sin nada relevante: sin actividades de hoy, atrasadas, restricciones, punch ni RFI → no manda nada
$vt = new Model\Proyecto(['codigo' => 'VACIO', 'nombre' => 'Sin actividades', 'fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-12-31', 'usuario_id' => (int)$u->id]);
$vt->id = $vt->guardar()['id'];
$ok('un proyecto sin nada relevante no manda resumen', Classes\ResumenObra::texto($vt) === '');
Classes\ResumenObra::cambiarActivo((int)$proy->id, false);
Telegram::$http = null;

// ---- límite de peticiones al webhook por IP ----
echo "Límite del webhook por IP\n";
$ok('la tabla de límites existe en el esquema', (bool)$db->query("SHOW TABLES LIKE 'telegram_limites'")->fetch_row());
putenv('TELEGRAM_RATE_MAX=3'); $_ENV['TELEGRAM_RATE_MAX'] = '3';
$ipL = '203.0.113.77';
$db->query("DELETE FROM telegram_limites WHERE ip = '$ipL'");
$pasa = 0;
for ($i = 0; $i < 5; $i++) if (Telegram::limite($ipL)) $pasa++;
$ok('la IP pasa hasta el tope (3) y luego queda bloqueada', $pasa === 3);
$otra = 0;
for ($i = 0; $i < 3; $i++) if (Telegram::limite('198.51.100.' . $i)) $otra++;
$ok('otra IP tiene su propia cuota (no comparte el bloqueo)', $otra === 3);
putenv('TELEGRAM_RATE_MAX=0'); $_ENV['TELEGRAM_RATE_MAX'] = '0';
$ok('TELEGRAM_RATE_MAX=0 desactiva el límite', Telegram::limite($ipL) && Telegram::limite($ipL));
putenv('TELEGRAM_RATE_MAX'); unset($_ENV['TELEGRAM_RATE_MAX']);
$ok('una IP vacía nunca se bloquea (sin identidad no hay cuota)', Telegram::limite(''));
$db->query("DELETE FROM telegram_limites WHERE ip LIKE '203.0.113.%' OR ip LIKE '198.51.100.%'");

// ---- Historial de cambios: solo lo que cambia el usuario con las herramientas ----
echo "Historial de cambios\n";
$pidH = (int)$proy->id;
$cuenta = fn() => (int)$db->query("SELECT COUNT(*) FROM auditoria_cambios WHERE proyecto_id = $pidH")->fetch_row()[0];
$_SESSION = ['id' => (int)$u->id, 'nombre' => 'Historial Prueba', 'email' => $u->email, 'rol' => $u->rol];
$ok('con alguien en sesión hay a quién atribuir el cambio', Classes\HistorialCambios::conUsuario());
$antesH = $cuenta();
$ph = new Model\Partida(['proyecto_id' => $pidH, 'codigo' => 'HIST.1', 'nombre' => 'Partida del historial', 'orden' => 900]);
$ph->id = $ph->guardar()['id'];
$filaH = $db->query('SELECT usuario, evento, herramienta FROM auditoria_cambios ORDER BY id DESC LIMIT 1')->fetch_assoc();
$ok('un cambio del usuario queda registrado con su nombre', $cuenta() === $antesH + 1 && ($filaH['usuario'] ?? '') === 'Historial Prueba'
    && ($filaH['evento'] ?? '') === 'creado' && ($filaH['herramienta'] ?? '') === 'programa');
$ah = new Model\Actividad(['partida_id' => $ph->id, 'codigo' => 'HIST.1.1', 'nombre' => 'Actividad del historial',
    'fecha_inicio' => '2026-11-04', 'fecha_fin' => '2026-11-10', 'duracion_dias' => 5, 'progreso' => 0]);
$ok('crear una actividad también queda registrada', (bool)$ah->guardar()['resultado'] && $cuenta() === $antesH + 2);
$antesH = $cuenta();
$rec = Model\Partida::find($ph->id);
$rec->recalcularFechas();
$ok('las fechas que recalcula el sistema no ensucian el historial', $cuenta() === $antesH
    && (string)$db->query("SELECT fecha_inicio FROM partidas WHERE id = {$ph->id}")->fetch_row()[0] === '2026-11-04');
// ---- Subactividades: el avance de la actividad es su promedio ----
echo "Subactividades\n";
$ps = new Model\Partida(['proyecto_id' => $pidH, 'codigo' => 'SUB.1', 'nombre' => 'Partida con subactividades', 'orden' => 902]);
$ps->id = $ps->guardar()['id'];
$as = new Model\Actividad(['partida_id' => $ps->id, 'codigo' => 'SUB.1.1', 'nombre' => 'Actividad con subactividades',
    'fecha_inicio' => '2026-11-04', 'fecha_fin' => '2026-11-10', 'duracion_dias' => 5, 'progreso' => 40]);
$as->id = $as->guardar()['id'];
$ok('sin subactividades conserva el avance manual', (int)Model\Actividad::find($as->id)->progreso === 40 && !$as->tieneSubactividades());
foreach ([100, 50] as $i => $st) {
    $sb = new Model\Subactividad(['actividad_id' => $as->id, 'nombre' => 'Tramo ' . ($i + 1),
        'fecha_inicio' => '2026-11-04', 'fecha_fin' => '2026-11-10', 'status' => $st]);
    $ok('se guarda la subactividad ' . ($i + 1), (bool)$sb->guardar()['resultado']);
}
$as->recalcularDesdeSubactividades();
$f = Model\Actividad::find($as->id);
$ok('con subactividades el avance es su promedio', (int)$f->progreso === 75 && $f->status === 'en_proceso' && $as->tieneSubactividades());
$ok('el programa sabe qué actividades tienen subactividades', (Model\Subactividad::conteoPorProyecto($pidH)[$as->id] ?? 0) === 2);
$subs = Model\Subactividad::deActividad((int)$as->id);
$subs[1]->status = 100;
$ok('se edita una subactividad', (bool)$subs[1]->guardar());
$as->recalcularDesdeSubactividades();
$f = Model\Actividad::find($as->id);
$ok('al completar la última sube a 100 y queda completada', (int)$f->progreso === 100 && $f->status === 'completada');
$f->progreso = 10;
$f->sincronizarStatus();
$f->guardar();
$ok('cualquier guardado de la actividad respeta el promedio', (int)Model\Actividad::find($as->id)->progreso === 100);
$subs[0]->eliminar();
$as->recalcularDesdeSubactividades();
$ok('al quitar una subactividad recalcula con las que quedan', (int)Model\Actividad::find($as->id)->progreso === 100);
$subs[1]->eliminar();
$as->recalcularDesdeSubactividades();
$ok('al quitar la última se conserva el último promedio como avance manual',
    (int)Model\Actividad::find($as->id)->progreso === 100 && !$as->tieneSubactividades());

// ---- Subactividades ↔ SheetFlow: el vínculo se actualiza en los dos sentidos ----
echo "Subactividades ↔ SheetFlow\n";
$pf = new Model\Partida(['proyecto_id' => $pidH, 'codigo' => 'SUB.2', 'nombre' => 'Partida vinculada a SheetFlow', 'orden' => 903]);
$pf->id = $pf->guardar()['id'];
$asf = new Model\Actividad(['partida_id' => $pf->id, 'codigo' => 'SUB.2.1', 'nombre' => 'Actividad vinculada a SheetFlow',
    'fecha_inicio' => '2026-11-04', 'fecha_fin' => '2026-11-10', 'duracion_dias' => 5, 'progreso' => 0]);
$asf->id = $asf->guardar()['id'];
$sfA = new Model\Subactividad(['actividad_id' => $asf->id, 'nombre' => 'Tramo A', 'fecha_inicio' => '2026-11-04', 'fecha_fin' => '2026-11-06', 'status' => 40]);
$sfA->id = $sfA->guardar()['id'];
$sfB = new Model\Subactividad(['actividad_id' => $asf->id, 'nombre' => 'Tramo B', 'fecha_inicio' => '2026-11-07', 'fecha_fin' => '2026-11-10', 'status' => 60]);
$sfB->id = $sfB->guardar()['id'];
$asf->recalcularDesdeSubactividades();
[$sh, $sv] = Model\SubactividadHoja::crearParaActividad($asf, $proy, null);
$ok('crea la hoja y el vínculo con la actividad', $sh->id && $sv->id && (int)$sv->hoja_id === (int)$sh->id);
$shd = $sh->contenido();
$ok('la hoja trae una fila por subactividad', count($shd['rows']) === 2 && count($shd['meta']['subactividad']['ids']) === 2);
$roles = [];
foreach ($shd['cols'] as $i => $c) if (!empty($c['rol'])) $roles[$c['rol']] = $i;
$ok('las columnas base llevan su rol y están protegidas', ($roles['nombre'] ?? -1) === 0 && ($roles['status'] ?? -1) === 3 && !empty($shd['cols'][0]['fija']));

// Editar A, borrar B, agregar C y una columna extra desde la hoja
$ids = $shd['meta']['subactividad']['ids'];
$rA = array_search((int)$sfA->id, $ids, true);
$rB = array_search((int)$sfB->id, $ids, true);
$shd['rows'][$rA][0] = 'Tramo A editado';
$shd['rows'][$rA][3] = '100';
array_splice($shd['rows'], $rB, 1);
array_splice($ids, $rB, 1);
$shd['cols'][] = ['n' => 'Responsable'];
foreach ($shd['rows'] as &$fila) $fila[] = 'Ing. X';
unset($fila);
$shd['rows'][] = ['Tramo C', '2026-11-05', '2026-11-08', '25', 'Ing. Y'];
$ids[] = null;
$shd['meta']['subactividad']['ids'] = $ids;
\Classes\Sheetflow::guardarDatos($sh, $shd);

$subsF = Model\Subactividad::deActividad((int)$asf->id);
$porNombre = [];
foreach ($subsF as $s) $porNombre[$s->nombre] = (int)$s->status;
$ok('la hoja actualiza, crea y elimina subactividades', $porNombre === ['Tramo A editado' => 100, 'Tramo C' => 25]);
$ok('el avance de la actividad se recalcula desde la hoja', (int)Model\Actividad::find($asf->id)->progreso === 63);
$shd = Model\SheetflowHoja::find((int)$sh->id)->contenido();
$idsF = $shd['meta']['subactividad']['ids'];
$ok('la hoja conserva los ids de las subactividades', in_array((int)$subsF[0]->id, $idsF, true) && in_array((int)$subsF[1]->id, $idsF, true));
$ok('la columna extra se queda en SheetFlow', in_array('Responsable', array_column($shd['cols'], 'n'), true) && $shd['rows'][0][4] === 'Ing. X');

// La hoja se queda con los valores ya validados (límite de avance y fechas de respaldo)
$rA2 = array_search((int)$subsF[0]->id, $shd['meta']['subactividad']['ids'], true);
$shd['rows'][$rA2][1] = '';
$shd['rows'][$rA2][2] = '';
$shd['rows'][$rA2][3] = '150';
\Classes\Sheetflow::guardarDatos($sh, $shd);
$shd = Model\SheetflowHoja::find((int)$sh->id)->contenido();
$ok('la hoja se queda con el avance acotado y las fechas de la actividad', $shd['rows'][$rA2][1] === '2026-11-04'
    && $shd['rows'][$rA2][2] === '2026-11-10' && $shd['rows'][$rA2][3] === '100');

// Cambio desde la sección de subactividades y se refleja en la hoja
$sfC = new Model\Subactividad(['actividad_id' => $asf->id, 'nombre' => 'Tramo D', 'fecha_inicio' => '2026-11-09', 'fecha_fin' => '2026-11-10', 'status' => 50]);
$sfC->id = $sfC->guardar()['id'];
$asf->recalcularDesdeSubactividades();
$errF = Model\SubactividadHoja::reflejar($asf);
$shd = Model\SheetflowHoja::find((int)$sh->id)->contenido();
$ok('la sección se refleja en la hoja', $errF === null && in_array((int)$sfC->id, $shd['meta']['subactividad']['ids'], true)
    && in_array('Tramo D', array_column($shd['rows'], 0), true));
$ok('la columna extra sigue después de reflejar', in_array('Responsable', array_column($shd['cols'], 'n'), true));

// Quitar un renglón desde vPlan borra su subactividad y no desalinea los ids
$rC = array_search((int)$subsF[1]->id, $shd['meta']['subactividad']['ids'], true);
$errQ = \Classes\Sheetflow::quitarFila(Model\SheetflowHoja::find((int)$sh->id), (int)$rC);
$ok('quitar un renglón elimina su subactividad y conserva las demás', $errQ === null
    && Model\Subactividad::find((int)$subsF[1]->id) === null && Model\Subactividad::find((int)$sfC->id) !== null);
$shd = Model\SheetflowHoja::find((int)$sh->id)->contenido();
$ok('los ids siguen alineados después de quitar un renglón', in_array((int)$sfC->id, $shd['meta']['subactividad']['ids'], true));

// No se puede dejar sin nombre una subactividad existente; el intento se deshace completo
$shd2 = $shd;
$rD = array_search((int)$sfC->id, $shd2['meta']['subactividad']['ids'], true);
$shd2['rows'][$rD][0] = '';
$falloF = null;
try {
    \Classes\Sheetflow::guardarDatos(Model\SheetflowHoja::find((int)$sh->id), $shd2);
} catch (\RuntimeException $e) {
    $falloF = $e->getMessage();
}
$sfC = Model\Subactividad::find((int)$sfC->id);
$ok('no acepta dejar sin nombre una subactividad y no deja cambios a medias', $falloF !== null && str_contains($falloF, 'sin nombre')
    && $sfC->nombre === 'Tramo D' && (int)$sfC->status === 50);

// Desvincular conserva la tabla y las subactividades
Model\SubactividadHoja::desvincular($asf);
$ok('desvincular borra el vínculo pero conserva la hoja', !Model\SubactividadHoja::deActividad((int)$asf->id) && (bool)Model\SheetflowHoja::find((int)$sh->id));
$ok('desvincular conserva las subactividades', count(Model\Subactividad::deActividad((int)$asf->id)) === 2);
$shd = Model\SheetflowHoja::find((int)$sh->id)->contenido();
$ok('la hoja desvinculada ya no tiene ids ni roles', empty($shd['meta']['subactividad']) && empty(array_filter(array_column($shd['cols'], 'rol'))));

$_SESSION = [];
$ok('sin nadie con sesión no hay usuario que atribuir', !Classes\HistorialCambios::conUsuario());
$antesH = $cuenta();
$ph2 = new Model\Partida(['proyecto_id' => $pidH, 'codigo' => 'HIST.2', 'nombre' => 'Partida sin sesión', 'orden' => 901]);
$ok('lo que escribe el código solo se guarda, pero no se anota', (bool)$ph2->guardar()['resultado'] && $cuenta() === $antesH);
$_SESSION = ['id' => (int)$u->id, 'nombre' => 'Historial Prueba', 'email' => $u->email, 'rol' => $u->rol];
$db->query("INSERT INTO auditoria_cambios (proyecto_id, origen, herramienta, evento, estado, accion, resumen, usuario) VALUES ($pidH, 'manual', 'programa', 'modificado', 'pendiente', 'actividades', 'Fila del sistema', '')");
$selH = ['estado' => 'pendiente', 'q' => 'Fila del sistema'];
$ok('una fila vieja sin usuario ya no se cuenta', Classes\HistorialCambios::contar($pidH, $selH) === 0);
$ok('una fila vieja sin usuario ya no aparece en la lista (ni en el Excel/PDF)', Classes\HistorialCambios::listar($pidH, $selH, 1, 10) === []);
$db->query("DELETE FROM auditoria_cambios WHERE proyecto_id = $pidH AND (usuario = 'Historial Prueba' OR resumen = 'Fila del sistema')");
$db->query("DELETE FROM actividades WHERE partida_id = {$ph->id}");
$db->query("DELETE FROM partidas WHERE proyecto_id = $pidH AND codigo IN ('HIST.1', 'HIST.2')");
$_SESSION = [];

echo $fallos ? "\n$fallos prueba(s) fallaron\n" : "\nIntegración OK\n";
exit($fallos ? 1 : 0);
