<?php
/**
 * Estampas inteligentes del proyecto DEMO: seis estampas de ejemplo (cajetín de plano, sello de actividad, etiqueta de restricción,
 * pie de reporte con SPI, semáforo y la plantilla de Last Planner), un cajetín dinámico y la lámina «LAST PLANNER DEL DÍA» del vPlan.
 *
 *   php database/seed_demo_estampas.php            (crea/recrea solo esto)
 *   php database/seed_demo_estampas.php --limpiar  (solo borra lo que sembró)
 *
 * Solo toca el proyecto DEMO. seed_demo_nuevas.php lo ejecuta al final (por eso reutiliza $pid, $q, $rows, $str si ya existen).
 */

if (!isset($pid)) {
    require_once __DIR__ . '/../includes/app.php';
    global $db;
    $proyecto = \Model\Proyecto::where('codigo', 'DEMO');
    if (!$proyecto) die("No existe el proyecto DEMO. Ejecuta primero php database/seed_demo.php\n");
    $pid = (int)$proyecto->id;
    $q = function (string $sql) use ($db) {
        if (!$db->query($sql)) throw new RuntimeException($db->error . "\n" . $sql);
        return $db->insert_id;
    };
    $rows = fn(string $sql) => $db->query($sql)->fetch_all(MYSQLI_ASSOC);
    $str = fn($v) => "'" . $db->escape_string((string)$v) . "'";
}

$db->query(file_get_contents(__DIR__ . '/migracion_estampas.sql'));   // CREATE TABLE IF NOT EXISTS
// Limpieza de lo propio
$q("DELETE FROM estampas WHERE proyecto_id = $pid");
$q("DELETE FROM vplan_laminas WHERE proyecto_id = $pid AND titulo = 'LAST PLANNER DEL DÍA'");
$q("DELETE FROM cajetines WHERE proyecto_id = $pid AND nombre LIKE 'Cajetín dinámico%'");
if (in_array('--limpiar', $argv ?? [], true)) { echo "Estampas del demo: solo se limpió.\n"; return; }

echo "Estampas inteligentes...\n";
$estId = [];
foreach (\Classes\Estampas::plantillas() as $clave => $pl) {
    $cfg = $pl['config'];
    if ($clave === 'cajetin') $cfg['datos'] = ['revision' => 'Rev. B', 'escala' => '1:100'];
    $e = new \Model\Estampa(['proyecto_id' => $pid, 'nombre' => $pl['nombre'], 'config_json' => json_encode(\Classes\Estampas::limpiarConfig($cfg, $pid), JSON_UNESCAPED_UNICODE)]);
    $estId[$clave] = (int)($e->guardar()['id'] ?? 0);
}

// Cajetines dinámicos: el de plano ahora usa campos que se llenan solos, y uno nuevo con el estado de la obra
$q("UPDATE cajetines SET filas_json = {$str(json_encode([
    ['Proyecto', '{proyecto}'], ['Cliente', '{cliente}'], ['Plano', '{titulo}'], ['Escala', '1:100'], ['Fecha', '{fecha:corto}'], ['Elaboró', '{autor}'], ['Revisó', 'Diego Carranza'],
    ['Avance al corte', '{avance_real} % de {avance_prog} %'], ['Hoja', '{lamina} de {total}'],
], JSON_UNESCAPED_UNICODE))} WHERE proyecto_id = $pid AND nombre = 'Cajetín de plano · Nexo Data Campus'");
$cajDin = (int)$q("INSERT INTO cajetines (proyecto_id, nombre, orientacion, ancho, filas_json) VALUES ($pid, 'Cajetín dinámico · Estado de la obra', 'vertical', 30, {$str(json_encode([
    ['Proyecto', '{codigo} · {proyecto}'], ['Corte', '{corte:largo}'], ['Semana de obra', '{semana}'], ['Avance real', '{avance_real} %'], ['SPI', '{spi}'], ['Restricciones activas', '{restr_activas}'],
    ['Órdenes de cambio abiertas', '{oc_abiertas}'], ['Retiros pendientes', '{retiros_pendientes}'], ['Último reporte', '{reporte_ultimo}'],
], JSON_UNESCAPED_UNICODE))})");

// Lámina «Last Planner del día» en el mismo vPlan que las láminas libres del demo
$paquete = (int)($rows("SELECT id FROM vplan_paquetes WHERE proyecto_id = $pid AND nombre = 'vPlan · Sala blanca y patio de equipos' LIMIT 1")[0]['id'] ?? 0);
if (!$paquete) $paquete = (int)($rows("SELECT id FROM vplan_paquetes WHERE proyecto_id = $pid ORDER BY id DESC LIMIT 1")[0]['id'] ?? 0);
$orden = 1 + (int)($rows("SELECT COALESCE(MAX(orden),0) m FROM vplan_laminas WHERE paquete_id = $paquete")[0]['m'] ?? 0);
$actEjemplo = (int)($rows("SELECT a.id FROM actividades a JOIN partidas p ON p.id = a.partida_id JOIN restricciones r ON r.actividad_id = a.id AND r.estado = 'activa'
    WHERE p.proyecto_id = $pid AND a.status = 'en_proceso' ORDER BY a.id LIMIT 1")[0]['id'] ?? ($rows("SELECT a.id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.status = 'en_proceso' ORDER BY a.id LIMIT 1")[0]['id'] ?? 0));
$l5 = (int)$q("INSERT INTO vplan_laminas (proyecto_id, paquete_id, partida_id, titulo, supervisor, ancho_mm, alto_mm, preset, recovery_n, restr_auto, plano, orden, grupo)
    VALUES ($pid, $paquete, NULL, 'LAST PLANNER DEL DÍA', 'Valeria Sotomayor', 432, 279, 'tabloide', 0, 0, NULL, $orden, 'Obra')");
$cmX = fn(float $cm) => round($cm / 43.2 * 100, 3);
$cmY = fn(float $cm) => round($cm / 27.9 * 100, 3);
$bl = [
    ['logo', 1.0, 0.9, 8.2, 3.0, ['cual' => 'contratista', 'alinear' => 'izquierda']],
    ['titulo', 9.2, 0.9, 24.7, 3.0, ['texto' => "LAST PLANNER\nPOST-ITS DEL DÍA"]],
    ['logo', 33.9, 0.9, 8.2, 3.0, ['cual' => 'empresa', 'alinear' => 'derecha']],
    ['estampa', 1.0, 4.1, 29.0, 19.6, ['estampa_id' => $estId['lastplanner'], 'fecha_modo' => 'hoy', 'actividad_id' => 0]],
    ['estampa', 30.6, 4.1, 11.5, 8.6, ['estampa_id' => $estId['semaforo'], 'fecha_modo' => 'hoy', 'actividad_id' => 0]],
    ['estampa', 30.6, 13.1, 11.5, 6.6, ['estampa_id' => $estId['actividad'], 'fecha_modo' => 'hoy', 'actividad_id' => $actEjemplo]],
    ['estampa', 30.6, 20.1, 11.5, 5.2, ['estampa_id' => $estId['cajetin'], 'fecha_modo' => 'hoy', 'actividad_id' => 0]],
    ['estampa', 1.0, 24.2, 29.0, 1.5, ['estampa_id' => $estId['pie'], 'fecha_modo' => 'hoy', 'actividad_id' => 0]],
    ['supervisor', 1.0, 26.35, 20.0, 0.7, []],
    ['fecha', 21.2, 26.35, 20.9, 0.7, []],
];
foreach ($bl as $z => [$tipo, $x, $y, $w, $h, $cfg]) {
    $b = new \Model\VplanBloque(['lamina_id' => $l5, 'tipo' => $tipo, 'x' => $cmX($x), 'y' => $cmY($y), 'w' => $cmX($w), 'h' => $cmY($h), 'z' => $z]);
    $b->setConfig($cfg);
    $b->guardar();
}
// El cajetín de la lámina de logística pasa al dinámico (se llena con el estado de la obra)
$q("UPDATE vplan_bloques SET config_json = {$str(json_encode(['cajetin_id' => $cajDin]))} WHERE tipo = 'cajetin' AND lamina_id IN (SELECT id FROM vplan_laminas WHERE paquete_id = $paquete AND titulo = 'PLANO DE LOCALIZACIÓN Y LOGÍSTICA')");
echo "Estampas del demo listas (lámina $l5).\n";
