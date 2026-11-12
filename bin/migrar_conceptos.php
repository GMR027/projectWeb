<?php
// Migración de datos: convierte los «conceptos de obra» sueltos de cada actividad
// (tabla actividad_cantidades) al catálogo de conceptos (conceptos_obra + actividad_conceptos).
//
// Uso: php bin/migrar_conceptos.php
// Requiere haber aplicado database/migracion_catalogo_conceptos.sql.
// Es idempotente: se puede volver a ejecutar sin duplicar conceptos ni asignaciones.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/app.php';

use Classes\Conceptos;

$db = $GLOBALS['db'];
$ok = fn(string $m) => print("  ok   $m\n");
$warn = fn(string $m) => print("  aviso $m\n");

$estado = Conceptos::estadoEsquema();
if (!$estado['ok']) {
    fwrite(STDERR, "Falta completar el esquema del catálogo:\n  - " . implode("\n  - ", $estado['faltan']) . "\n");
    fwrite(STDERR, "Ejecuta primero: php bin/reparar_conceptos.php (o aplica database/migracion_catalogo_conceptos.sql).\n");
    exit(1);
}

$filas = [];
try {
    $r = $db->query("SELECT c.actividad_id, c.proyecto_id, c.clave, c.concepto, c.cantidad, c.unidad, c.precio_unitario,
            a.partida_id
        FROM actividad_cantidades c
        JOIN actividades a ON a.id = c.actividad_id
        JOIN partidas p ON p.id = a.partida_id
        WHERE (TRIM(c.concepto) <> '' OR TRIM(COALESCE(c.clave, '')) <> '')
        ORDER BY c.proyecto_id, a.partida_id, a.orden, c.actividad_id");
    while ($r && ($f = $r->fetch_assoc())) $filas[] = $f;
} catch (\Throwable $e) {
    fwrite(STDERR, "No se pudo leer actividad_cantidades: " . $e->getMessage() . "\n");
    exit(1);
}

if (!$filas) print("No hay conceptos de obra antiguos que migrar.\n");

$creados = $asignados = $existentes = 0;
$proyectos = [];
$avisos = [];

foreach ($filas as $f) {
    $pid = (int)$f['proyecto_id'];
    $aid = (int)$f['actividad_id'];
    $partidaId = (int)$f['partida_id'];
    $clave = trim((string)$f['clave']);
    $desc = trim((string)$f['concepto']);
    $unidad = trim((string)$f['unidad']);
    $cantidad = $f['cantidad'] !== null && $f['cantidad'] !== '' ? max(0, (float)$f['cantidad']) : 0.0;
    $precio = $f['precio_unitario'] !== null && $f['precio_unitario'] !== '' ? max(0, (float)$f['precio_unitario']) : 0.0;
    if ($desc === '') $desc = $clave;

    // Buscar el concepto destino: por clave (única por proyecto) o por descripción + unidad + precio
    $concepto = null;
    if ($clave !== '') {
        $q = $db->query("SELECT * FROM conceptos_obra WHERE proyecto_id = $pid AND clave = '" . $db->real_escape_string(mb_substr($clave, 0, 60)) . "'");
        $concepto = $q ? $q->fetch_assoc() : null;
        if ($concepto && ((float)$concepto['precio_unitario'] !== $precio || (string)$concepto['unidad'] !== $unidad)) {
            $avisos[] = "La clave «$clave» ya existía con otro precio o unidad; se reutilizó la del catálogo.";
        }
    } else {
        $q = $db->query("SELECT * FROM conceptos_obra WHERE proyecto_id = $pid AND partida_id = $partidaId
            AND LOWER(descripcion) = '" . $db->real_escape_string(mb_strtolower(mb_substr($desc, 0, 255))) . "'
            AND unidad = '" . $db->real_escape_string(mb_substr($unidad, 0, 30)) . "'
            AND ABS(precio_unitario - $precio) < 0.01 LIMIT 1");
        $concepto = $q ? $q->fetch_assoc() : null;
    }

    if (!$concepto) {
        $sql = "INSERT INTO conceptos_obra (proyecto_id, partida_id, clave, descripcion, unidad, cantidad, precio_unitario, origen)
            VALUES ($pid, $partidaId, " . ($clave !== '' ? "'" . $db->real_escape_string(mb_substr($clave, 0, 60)) . "'" : 'NULL') . ",
            '" . $db->real_escape_string(mb_substr($desc, 0, 255)) . "', '" . $db->real_escape_string(mb_substr($unidad, 0, 30)) . "',
            0, $precio, 'Migrado del concepto de obra')";
        if (!$db->query($sql)) { $avisos[] = "No se pudo crear el concepto «" . mb_substr($desc, 0, 60) . "»: " . $db->error; continue; }
        $cid = (int)$db->insert_id;
        $concepto = ['id' => $cid];
        $creados++;
    } else {
        $existentes++;
    }

    $cid = (int)$concepto['id'];
    if ($cantidad > 0) {
        $ya = $db->query("SELECT cantidad FROM actividad_conceptos WHERE actividad_id = $aid AND concepto_id = $cid");
        if ($ya && ($prev = $ya->fetch_assoc())) {
            if ((float)$prev['cantidad'] !== $cantidad) {
                $db->query("UPDATE actividad_conceptos SET cantidad = $cantidad WHERE actividad_id = $aid AND concepto_id = $cid");
                $asignados++;
            }
        } elseif ($db->query("INSERT INTO actividad_conceptos (actividad_id, concepto_id, cantidad, motivo_excedido) VALUES ($aid, $cid, $cantidad, '')")) {
            $asignados++;
        }
    }
    $proyectos[$pid] = true;
}

// Aunque no haya conceptos viejos, normaliza los presupuestos de las obras que ya
// tengan costos guardados (instalaciones con el importe solo en «presupuesto»).
$r = $db->query("SELECT DISTINCT proyecto_id FROM costos_partidas");
while ($r && ($f = $r->fetch_assoc())) $proyectos[(int)$f['proyecto_id']] = true;

// La cantidad contractual arranca en lo que quedó asignado (hasta que el usuario ponga la del contrato)
$ajustes = 0;
foreach (array_keys($proyectos) as $pid) {
    $db->query("UPDATE conceptos_obra co SET co.cantidad = COALESCE((
            SELECT SUM(ac.cantidad) FROM actividad_conceptos ac WHERE ac.concepto_id = co.id
        ), co.cantidad)
        WHERE co.proyecto_id = $pid AND co.cantidad = 0");
    // Instalaciones antiguas guardaban el presupuesto solo en «presupuesto» (con
    // presupuesto_manual en NULL). Se conserva como referencia manual antes de recalcular;
    // si no, al pasar al catálogo el importe se perdería.
    $db->query("UPDATE costos_partidas SET presupuesto_manual = presupuesto
        WHERE proyecto_id = $pid AND presupuesto_manual IS NULL AND presupuesto > 0");
    // Conserva el presupuesto manual anterior como ajuste visible (solo la primera vez:
    // después de recalcular, el origen ya es «conceptos» y no se vuelve a tocar).
    $db->query("UPDATE costos_partidas cp
        JOIN (SELECT co.partida_id, SUM(co.cantidad * co.precio_unitario) suma
              FROM conceptos_obra co WHERE co.proyecto_id = $pid AND co.partida_id IS NOT NULL
              GROUP BY co.partida_id) t ON t.partida_id = cp.partida_id
        SET cp.ajuste = ROUND(GREATEST(0, COALESCE(cp.presupuesto_manual, cp.presupuesto, 0) - t.suma), 2)
        WHERE cp.proyecto_id = $pid AND cp.origen = 'manual' AND cp.ajuste = 0
          AND COALESCE(cp.presupuesto_manual, cp.presupuesto, 0) > t.suma + 0.005");
    $ajustes += (int)$db->affected_rows;
    $n = Conceptos::recalcularProyecto((int)$pid);
    $ok("proyecto $pid: presupuestos recalculados en $n partida(s)");
}
if ($ajustes > 0) $ok("presupuestos manuales anteriores conservados como ajuste: $ajustes partida(s)");

$ok("conceptos creados: $creados · reutilizados: $existentes · actividades ligadas: $asignados");
foreach (array_unique($avisos) as $a) $warn($a);
print("\nListo. La tabla actividad_cantidades se conserva como respaldo de la migración.\n");
