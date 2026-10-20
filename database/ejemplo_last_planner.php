<?php
/**
 * Agrega a un proyecto la plantilla «Last Planner · Post-its del día» (estampa inteligente) y una lámina del vPlan que la usa.
 *
 *   php database/ejemplo_last_planner.php GRX            (crea/recrea solo esto)
 *   php database/ejemplo_last_planner.php GRX --limpiar  (quita lo que creó)
 *
 * Solo agrega: no toca ninguna otra lámina, estampa ni dato del proyecto; lo único que borra es lo que este mismo script creó
 * (estampas «Last Planner · Post-its del día» / «Semáforo de avance» / «Pie de reporte con corte y SPI» y la lámina «LAST PLANNER DEL DÍA»).
 */
require_once __DIR__ . '/../includes/app.php';
global $db;

$codigo = $argv[1] ?? '';
$proyecto = $codigo !== '' ? \Model\Proyecto::where('codigo', $codigo) : null;
if (!$proyecto) die("Uso: php database/ejemplo_last_planner.php CODIGO_DEL_PROYECTO [--limpiar]\n");
$pid = (int)$proyecto->id;
$q = function (string $sql) use ($db) {
    if (!$db->query($sql)) throw new RuntimeException($db->error . "\n" . $sql);
    return $db->insert_id;
};
$rows = fn(string $sql) => $db->query($sql)->fetch_all(MYSQLI_ASSOC);

$db->query(file_get_contents(__DIR__ . '/migracion_estampas.sql'));
$claves = ['lastplanner', 'semaforo', 'pie'];
$plantillas = \Classes\Estampas::plantillas();
$nombres = array_map(fn($c) => "'" . $db->escape_string($plantillas[$c]['nombre']) . "'", $claves);

// Limpieza de lo propio (la lámina y sus bloques; las estampas por nombre)
foreach ($rows("SELECT id, plano FROM vplan_laminas WHERE proyecto_id = $pid AND titulo = 'LAST PLANNER DEL DÍA'") as $l) {
    $q("DELETE FROM vplan_laminas WHERE id = " . (int)$l['id']);   // los bloques caen en cascada
}
$q("DELETE FROM estampas WHERE proyecto_id = $pid AND nombre IN (" . implode(',', $nombres) . ")");
if (in_array('--limpiar', $argv, true)) { echo "Proyecto {$proyecto->codigo}: se quitó la plantilla de Last Planner.\n"; return; }

// Si hoy no hay actividades programadas (obra terminada o en pausa), la estampa de actividades toma como fecha fija el día con más
// actividades en curso, para que se vea cómo queda; con actividades hoy se queda dinámica («corte» = hoy).
$hoy = date('Y-m-d');
$enCurso = fn(string $d) => (int)($rows("SELECT COUNT(*) n FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.hito = 0 AND '$d' BETWEEN a.fecha_inicio AND a.fecha_fin")[0]['n'] ?? 0);
$fechaEjemplo = '';
if ($enCurso($hoy) === 0) {
    $mejor = 0;
    foreach ($rows("SELECT DISTINCT a.fecha_inicio d FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.hito = 0") as $f) {
        $n = $enCurso($f['d']);
        if ($n > $mejor) { $mejor = $n; $fechaEjemplo = $f['d']; }
    }
}
$estId = [];
foreach ($claves as $c) {
    $cfg = $plantillas[$c]['config'];
    if ($c === 'lastplanner' && $fechaEjemplo !== '') {
        foreach ($cfg['etiquetas'] as &$et) if (($et['t'] ?? '') === 'actividades') { $et['fecha_modo'] = 'fija'; $et['fecha'] = $fechaEjemplo; }
        unset($et);
    }
    $e = new \Model\Estampa(['proyecto_id' => $pid, 'nombre' => $plantillas[$c]['nombre'], 'config_json' => json_encode(\Classes\Estampas::limpiarConfig($cfg, $pid), JSON_UNESCAPED_UNICODE)]);
    $estId[$c] = (int)($e->guardar()['id'] ?? 0);
}

// vPlan del proyecto (si no tiene, se crea uno)
$paquete = (int)($rows("SELECT id FROM vplan_paquetes WHERE proyecto_id = $pid ORDER BY orden, id LIMIT 1")[0]['id'] ?? 0);
if (!$paquete) $paquete = (int)$q("INSERT INTO vplan_paquetes (proyecto_id, nombre, orden) VALUES ($pid, 'vPlan', 1)");
$orden = 1 + (int)($rows("SELECT COALESCE(MAX(orden),0) m FROM vplan_laminas WHERE paquete_id = $paquete")[0]['m'] ?? 0);
$sup = $db->escape_string((string)($proyecto->supervisor ?? ''));
$l = (int)$q("INSERT INTO vplan_laminas (proyecto_id, paquete_id, partida_id, titulo, supervisor, ancho_mm, alto_mm, preset, recovery_n, restr_auto, plano, orden, grupo)
    VALUES ($pid, $paquete, NULL, 'LAST PLANNER DEL DÍA', '$sup', 432, 279, 'tabloide', 0, 0, NULL, $orden, 'Last Planner')");
$cmX = fn(float $cm) => round($cm / 43.2 * 100, 3);
$cmY = fn(float $cm) => round($cm / 27.9 * 100, 3);
$bl = [
    ['logo', 1.0, 0.9, 8.2, 3.0, ['cual' => 'contratista', 'alinear' => 'izquierda']],
    ['titulo', 9.2, 0.9, 24.7, 3.0, ['texto' => "LAST PLANNER\nPOST-ITS DEL DÍA"]],
    ['logo', 33.9, 0.9, 8.2, 3.0, ['cual' => 'empresa', 'alinear' => 'derecha']],
    ['estampa', 1.0, 4.1, 31.0, 19.6, ['estampa_id' => $estId['lastplanner'], 'fecha_modo' => 'hoy', 'actividad_id' => 0]],
    ['estampa', 32.6, 4.1, 9.5, 8.6, ['estampa_id' => $estId['semaforo'], 'fecha_modo' => 'hoy', 'actividad_id' => 0]],
    ['estampa', 1.0, 24.2, 31.0, 1.5, ['estampa_id' => $estId['pie'], 'fecha_modo' => 'hoy', 'actividad_id' => 0]],
    ['supervisor', 1.0, 26.35, 20.0, 0.7, []],
    ['fecha', 21.2, 26.35, 20.9, 0.7, []],
];
foreach ($bl as $z => [$tipo, $x, $y, $w, $h, $cfg]) {
    $b = new \Model\VplanBloque(['lamina_id' => $l, 'tipo' => $tipo, 'x' => $cmX($x), 'y' => $cmY($y), 'w' => $cmX($w), 'h' => $cmY($h), 'z' => $z]);
    $b->setConfig($cfg);
    $b->guardar();
}
echo ($fechaEjemplo !== '' ? "Hoy no hay actividades: la plantilla usa la fecha fija $fechaEjemplo (cámbiala en la estampa para volver a «hoy»).\n" : '') . "Proyecto {$proyecto->codigo} (id $pid): estampas creadas y lámina $l en el vPlan $paquete.\nVer: /vplan/ver?proyecto=$pid&id=$l   y   /simbolos?proyecto=$pid#estampas\n";
