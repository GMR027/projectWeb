<?php
/**
 * Migración de subactividades a su forma completa (fase 0).
 *
 * Uso:
 *   php bin/migrar_subactividades.php
 *   DB_HOST=127.0.0.1 php bin/migrar_subactividades.php
 *
 * Es idempotente: revisa el esquema antes de cada cambio y se puede volver a
 * ejecutar si una vez se quedó a medias. También convierte el antiguo
 * `subactividades.status` (0-100) en `progreso` + `status` (estado).
 */

require_once __DIR__ . '/../includes/database.php';

/** @var mysqli $db */
$tabla = 'subactividades';

function columnas(mysqli $db, string $tabla): array
{
    $out = [];
    $r = $db->query("SHOW COLUMNS FROM `$tabla`");
    if (!$r) return $out;
    while ($f = $r->fetch_assoc()) $out[$f['Field']] = $f;
    return $out;
}

function indices(mysqli $db, string $tabla): array
{
    $out = [];
    $r = $db->query("SHOW INDEX FROM `$tabla`");
    if (!$r) return $out;
    while ($f = $r->fetch_assoc()) $out[$f['Key_name']] = true;
    return $out;
}

function existeTabla(mysqli $db, string $tabla): bool
{
    $t = $db->real_escape_string($tabla);
    $r = $db->query("SHOW TABLES LIKE '$t'");
    return $r && $r->num_rows > 0;
}

function addCol(mysqli $db, string $tabla, string $col, string $def, array &$cols): void
{
    if (isset($cols[$col])) return;
    if (!$db->query("ALTER TABLE `$tabla` ADD COLUMN `$col` $def")) {
        throw new RuntimeException("No se pudo agregar $col: " . $db->error);
    }
    $cols[$col] = ['Field' => $col];
    echo "  + $col\n";
}

if (!existeTabla($db, $tabla)) {
    fwrite(STDERR, "No existe la tabla `$tabla`. Aplica database/schema.sql primero.\n");
    exit(1);
}

$cols = columnas($db, $tabla);
echo "Migrando `$tabla`…\n";

// 1) El antiguo status numérico pasa a llamarse progreso.
$statusType = strtolower((string)($cols['status']['Type'] ?? ''));
$statusEsNumero = $statusType !== '' && (str_contains($statusType, 'int') || str_contains($statusType, 'decimal'));

if (isset($cols['status']) && !isset($cols['progreso']) && $statusEsNumero) {
    if (!$db->query("ALTER TABLE `$tabla` CHANGE COLUMN `status` `progreso` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'avance 0-100'")) {
        throw new RuntimeException('No se pudo renombrar status a progreso: ' . $db->error);
    }
    $cols = columnas($db, $tabla);
    echo "  ~ status (numérico) → progreso\n";
} elseif (isset($cols['progreso'], $cols['status']) && $statusEsNumero) {
    // Estado a medias: ya existe progreso pero status sigue siendo numérico.
    $db->query("UPDATE `$tabla` SET `progreso` = `status` WHERE (`progreso` IS NULL OR `progreso` = 0) AND `status` IS NOT NULL");
    if (!$db->query("ALTER TABLE `$tabla` DROP COLUMN `status`")) {
        throw new RuntimeException('No se pudo quitar el status numérico: ' . $db->error);
    }
    $cols = columnas($db, $tabla);
    echo "  ~ status (numérico) eliminado; se conservó en progreso\n";
}

// 2) Columnas nuevas (mismas que la actividad, más las de jerarquía).
$nuevas = [
    'calendario_id' => 'INT NULL',
    'frente_id' => 'INT NULL',
    'descripcion' => 'TEXT NULL',
    'fecha_inicio_real' => 'DATE NULL',
    'fecha_fin_real' => 'DATE NULL',
    'duracion_restante' => 'DECIMAL(6,2) NULL',
    'restriccion_tipo' => "ENUM('ninguna','no_antes','no_despues','terminar_antes') NOT NULL DEFAULT 'ninguna'",
    'restriccion_fecha' => 'DATE NULL',
    'restriccion_motivo' => "VARCHAR(500) NOT NULL DEFAULT ''",
    'progreso' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT \'avance 0-100\'',
    'prioridad' => "ENUM('baja','media','alta','critica') NULL DEFAULT 'media'",
    'status' => "ENUM('no_iniciada','en_proceso','completada') NOT NULL DEFAULT 'no_iniciada'",
    'pta' => 'TINYINT(1) NOT NULL DEFAULT 1',
    'hito' => 'TINYINT NOT NULL DEFAULT 0',
    'sin_material' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'orden' => 'INT NULL DEFAULT 0',
    'fecha_fija' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'responsable' => 'VARCHAR(150) NULL',
    'rec_inicio' => 'DATE NULL',
    'rec_fin' => 'DATE NULL',
    'libre_texto' => 'VARCHAR(255) NULL',
    'libre_numero' => 'DECIMAL(14,4) NULL',
    'libre_fecha' => 'DATE NULL',
    'icono' => 'VARCHAR(120) NULL',
    'actualizado' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
];
foreach ($nuevas as $col => $def) addCol($db, $tabla, $col, $def, $cols);

// 3) Índice de orden por actividad.
$idx = indices($db, $tabla);
if (!isset($idx['idx_subactividades_actividad_orden'])) {
    if (!$db->query("ALTER TABLE `$tabla` ADD KEY `idx_subactividades_actividad_orden` (`actividad_id`, `orden`)")) {
        throw new RuntimeException('No se pudo crear el índice de orden: ' . $db->error);
    }
    echo "  + índice idx_subactividades_actividad_orden\n";
}

// 4) Backfill: estado a partir del avance y orden por actividad.
$db->query("UPDATE `$tabla` SET `status` = CASE
        WHEN `progreso` <= 0 THEN 'no_iniciada'
        WHEN `progreso` >= 100 THEN 'completada'
        ELSE 'en_proceso' END");

$db->query("UPDATE `$tabla` s
    JOIN (
        SELECT `id`, ROW_NUMBER() OVER (PARTITION BY `actividad_id` ORDER BY `fecha_inicio` ASC, `id` ASC) AS rn
        FROM `$tabla`
    ) x ON x.id = s.id
    SET s.`orden` = x.rn
    WHERE s.`orden` IS NULL OR s.`orden` = 0");

// 5) Ajuste del proyecto: mostrar subactividades por defecto (Ajustes → General).
if (existeTabla($db, 'proyectos')) {
    $pcols = columnas($db, 'proyectos');
    addCol($db, 'proyectos', 'mostrar_subactividades', 'TINYINT(1) NOT NULL DEFAULT 0', $pcols);
}

// 6) Restricciones propias de subactividad (columna, índice y FK idempotentes).
if (existeTabla($db, 'restricciones') && existeTabla($db, 'subactividades')) {
    $rcols = columnas($db, 'restricciones');
    addCol($db, 'restricciones', 'subactividad_id', 'INT NULL', $rcols);
    $ridx = indices($db, 'restricciones');
    if (!isset($ridx['idx_restriccion_subactividad'])) {
        $db->query('ALTER TABLE restricciones ADD KEY idx_restriccion_subactividad (subactividad_id)');
    }
    $fk = $db->query("SELECT COUNT(*) FROM information_schema.table_constraints
        WHERE table_schema = DATABASE() AND table_name = 'restricciones' AND constraint_name = 'fk_restriccion_subactividad'");
    if ($fk && (int)$fk->fetch_row()[0] === 0) {
        $db->query('ALTER TABLE restricciones ADD CONSTRAINT fk_restriccion_subactividad FOREIGN KEY (subactividad_id) REFERENCES subactividades(id) ON DELETE CASCADE');
    }
}

// 7) Tabla de dependencias con subactividades.
if (!existeTabla($db, 'subactividad_dependencias')) {
    $sqlDep = @file_get_contents(__DIR__ . '/../database/migracion_subactividad_dependencias.sql');
    if ($sqlDep) {
        $db->multi_query($sqlDep);
        while ($db->more_results() && $db->next_result()) { /* vaciar resultados */ }
        echo "  + tabla subactividad_dependencias\n";
    }
}

echo "Listo. Columnas actuales: " . count(columnas($db, $tabla)) . "\n";
