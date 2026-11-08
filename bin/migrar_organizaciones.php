<?php
// Migración de datos: una organización por cada cliente existente.
// Uso: php bin/migrar_organizaciones.php
// Requiere haber aplicado database/migracion_planes_organizaciones.sql.
// Es idempotente: se puede volver a ejecutar sin duplicar organizaciones.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/app.php';

use Classes\Almacenamiento;
use Model\Organizacion;
use Model\Usuario;

$db = $GLOBALS['db'];
$ok = fn(string $m) => print("  ok   $m\n");
$warn = fn(string $m) => print("  aviso $m\n");

function columna(mysqli $db, string $tabla, string $col): bool
{
    $t = $db->real_escape_string($tabla);
    $c = $db->real_escape_string($col);
    $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return (bool)($r && $r->fetch_row());
}

if (!Organizacion::disponible()) {
    fwrite(STDERR, "Falta la tabla organizaciones. Aplica database/migracion_planes_organizaciones.sql primero.\n");
    exit(1);
}
foreach (['organizacion_id', 'rol_org', 'puede_crear_proyectos'] as $c) {
    if (!columna($db, 'usuarios', $c)) {
        fwrite(STDERR, "Falta usuarios.$c. Aplica la migración primero.\n");
        exit(1);
    }
}
foreach (['organizacion_id', 'archivado'] as $c) {
    if (!columna($db, 'proyectos', $c)) {
        fwrite(STDERR, "Falta proyectos.$c. Aplica la migración primero.\n");
        exit(1);
    }
}

$interna = Organizacion::interna();
if (!$interna) {
    $admin = Usuario::where('email', 'admin@po.local');
    if (!$admin) {
        // En el clúster el admin puede tener otro correo: se toma el primer administrador.
        $ra = $db->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id ASC LIMIT 1");
        if ($ra && ($fa = $ra->fetch_assoc())) $admin = Usuario::find((int)$fa['id']);
    }
    if ($admin) {
        $r = (new Organizacion([
            'nombre' => 'ProjectWeb', 'plan_id' => 'legacy', 'estado_plan' => 'activo',
            'propietario_id' => (int)$admin->id, 'plan_inicio' => date('Y-m-d'),
            'plan_fin' => date('Y-m-d', strtotime('+12 months')),
        ]))->guardar();
        if (!empty($r['resultado'])) {
            $interna = Organizacion::find((int)$r['id']);
            $db->query("UPDATE usuarios SET organizacion_id = " . (int)$interna->id . ", rol_org = 'propietario', puede_crear_proyectos = 1 WHERE id = " . (int)$admin->id);
            $ok('organización interna ProjectWeb creada');
        }
    }
}
$internaId = $interna ? (int)$interna->id : 0;

// Admin y demo a la organización interna (antes de asignar proyectos)
if ($internaId > 0) {
    $db->query("UPDATE usuarios SET organizacion_id = $internaId, rol_org = 'propietario', puede_crear_proyectos = 1 WHERE rol = 'admin' AND (organizacion_id IS NULL OR organizacion_id = 0)");
    $db->query("UPDATE usuarios SET organizacion_id = $internaId, rol_org = 'visitante' WHERE email = '" . DEMO_EMAIL . "' AND (organizacion_id IS NULL OR organizacion_id = 0)");
    $ok('admin y demo en la organización interna');
}

// 1) Un cliente = una organización (legacy con sus límites actuales)
$clientes = $db->query("SELECT * FROM usuarios WHERE rol = 'cliente' AND (organizacion_id IS NULL OR organizacion_id = 0) ORDER BY id ASC");
$creadas = 0;
while ($u = $clientes->fetch_assoc()) {
    $limites = [
        'obras' => (int)($u['limite_proyectos'] ?? 0),
        'usuarios' => 1 + (int)($u['limite_colaboradores'] ?? 0),
        'visitantes' => (int)($u['limite_visitantes'] ?? 0),
        'almacenamiento_gb' => null,
        'almacenamiento_por_obra_mb' => (int)($u['limite_almacenamiento_mb'] ?? 300),
    ];
    $org = new Organizacion([
        'nombre' => trim((string)$u['nombre']) ?: ('Cliente ' . (int)$u['id']),
        'correo_facturacion' => (string)$u['email'],
        'propietario_id' => (int)$u['id'],
        'plan_id' => 'legacy',
        'estado_plan' => 'activo',
        'plan_inicio' => date('Y-m-d'),
        'plan_fin' => date('Y-m-d', strtotime('+12 months')),
        'limites_json' => json_encode($limites, JSON_UNESCAPED_UNICODE),
    ]);
    $r = $org->guardar();
    if (empty($r['resultado'])) { $warn('no se pudo crear la organización de ' . $u['email']); continue; }
    $orgId = (int)$r['id'];
    $db->query("UPDATE usuarios SET organizacion_id = $orgId, rol_org = 'propietario', puede_crear_proyectos = 1 WHERE id = " . (int)$u['id']);
    $db->query("UPDATE proyectos SET organizacion_id = $orgId WHERE usuario_id = " . (int)$u['id'] . " AND (organizacion_id IS NULL OR organizacion_id = 0)");
    $creadas++;
}
$ok("organizaciones creadas para clientes: $creadas");

// 2) Equipo: hereda la organización de su dueño
$equipo = $db->query("SELECT u.id, u.nivel_acceso, u.creado_por, d.organizacion_id FROM usuarios u JOIN usuarios d ON d.id = u.creado_por WHERE u.organizacion_id IS NULL OR u.organizacion_id = 0");
$equipoN = 0;
while ($u = $equipo->fetch_assoc()) {
    $orgId = (int)$u['organizacion_id'];
    if ($orgId <= 0) continue;
    $rolOrg = (string)$u['nivel_acceso'] === 'editar' ? 'colaborador' : 'visitante';
    $db->query("UPDATE usuarios SET organizacion_id = $orgId, rol_org = '$rolOrg', puede_crear_proyectos = 0 WHERE id = " . (int)$u['id']);
    $equipoN++;
}
$ok("usuarios de equipo vinculados: $equipoN");

// 3) Proyectos sin organización: la del dueño o la interna
$proyectos = $db->query("SELECT p.id, p.usuario_id, u.organizacion_id FROM proyectos p LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE p.organizacion_id IS NULL OR p.organizacion_id = 0");
$proyN = 0;
while ($p = $proyectos->fetch_assoc()) {
    $orgId = (int)($p['organizacion_id'] ?? 0);
    if ($orgId <= 0) $orgId = $internaId;
    if ($orgId <= 0) continue;
    $db->query("UPDATE proyectos SET organizacion_id = $orgId, archivado = 0 WHERE id = " . (int)$p['id']);
    $proyN++;
}
$ok("proyectos vinculados a una organización: $proyN");

// 4) Contador de almacenamiento por organización
$orgs = $db->query('SELECT id FROM organizaciones');
$almN = 0;
while ($o = $orgs->fetch_assoc()) {
    Almacenamiento::recalcularCuenta((int)$o['id']);
    $almN++;
}
$ok("contadores de almacenamiento recalculados: $almN");

// 6) Verificación
$sinOrg = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente' AND (organizacion_id IS NULL OR organizacion_id = 0)")->fetch_row()[0];
$proySin = (int)$db->query("SELECT COUNT(*) FROM proyectos WHERE organizacion_id IS NULL OR organizacion_id = 0")->fetch_row()[0];
$ok("clientes sin organización: $sinOrg");
$ok("proyectos sin organización: $proySin");
echo "Migración terminada.\n";
