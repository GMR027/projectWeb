<?php
/**
 * Datos de ejemplo del proyecto DEMO para las herramientas nuevas que no cubren seed_demo.php ni seed_demo_extras.php:
 * órdenes de cambio, líneas base (con comparación), cruce de materiales (materiales nuevos + vínculos con actividades),
 * Redactor (documentos en carpetas), SheetFlow (tablas con fórmulas), carpetas de Minutas, un MSR configurable,
 * un vPlan de láminas libres, Comparador (dos revisiones de un plano en Archivo) y calendario con más festivos/paros.
 * Al final ejecuta seed_demo_herramientas.php (evidencias, punch, seguridad, RFI, recursos, costos, mapa de avance,
 * subactividades, campos personalizados, carpetas del Archivo y Unir documentos).
 *
 *   php database/seed_demo_nuevas.php            (crea/recrea)
 *   php database/seed_demo_nuevas.php --limpiar  (solo borra lo que sembró)
 *
 * - Solo toca el proyecto con código DEMO; antes de crear borra lo suyo (y sus imágenes) y lo vuelve a crear: se puede correr las veces que sea.
 * - seed_demo.php lo ejecuta al final; también puede correrse solo sobre un demo ya existente.
 * - Nada de lo que se siembra es exclusivo del administrador (IA, Centro de IA, Ajustes, Sistema…).
 *
 * Imágenes (descarga con curl/file_get_contents y caché en sys_get_temp_dir()/projectweb_demo_img; si falla la red se dibujan con GD):
 *  - Planta de la sala blanca (racks, pasillos fríos/calientes, sala eléctrica y UPS): se dibuja con GD, sin red.
 *  - Steel frame building under construction in Taiwan.jpg (Wikimedia Commons, CC0).
 *  - Steel frame structure.jpg (Wikimedia Commons, CC0).
 *  - public/build/img/landing/{campo,grua,planos}.jpg (ya incluidas en el proyecto; Unsplash License).
 *  - Logos «Nexo» y «Meridian»: marcas ficticias dibujadas con GD (no representan a ninguna empresa real).
 */

require_once __DIR__ . '/../includes/app.php';
if (PHP_SAPI === 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) @session_start();   // usuario_actual() la pide al capturar líneas base

use Classes\Calendario;
use Classes\Imagenes;
use Classes\LineasBase;
use Classes\Redactor;
use Classes\Sheetflow;
use Model\Archivo;
use Model\Proyecto;
use Model\SheetflowHoja;

global $db;
$proyecto = Proyecto::where('codigo', 'DEMO');
if (!$proyecto) die("No existe el proyecto DEMO. Ejecuta primero php database/seed_demo.php\n");
$pid = (int)$proyecto->id;
$q = function (string $sql) use ($db) {
    if (!$db->query($sql)) throw new RuntimeException($db->error . "\n" . $sql);
    return $db->insert_id;
};
$rows = fn(string $sql) => ($r = $db->query($sql)) ? $r->fetch_all(MYSQLI_ASSOC) : [];
$str = fn($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string((string)$v) . "'";
$num = fn($v) => $v === null ? 'NULL' : (string)(float)$v;
$hoy = new DateTime('today');
$dia = fn(int $off) => (clone $hoy)->modify(($off >= 0 ? '+' : '') . "$off days")->format('Y-m-d');
$act = function (string $codigo) use ($db, $pid): ?int {
    $r = $db->query("SELECT a.id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.codigo = '" . $db->real_escape_string($codigo) . "' LIMIT 1")->fetch_row();
    return $r ? (int)$r[0] : null;
};
$partidaId = function (string $codigo) use ($db, $pid): ?int {
    $r = $db->query("SELECT id FROM partidas WHERE proyecto_id = $pid AND codigo = '" . $db->real_escape_string($codigo) . "' LIMIT 1")->fetch_row();
    return $r ? (int)$r[0] : null;
};
$persona = function (string $nombre) use ($db, $pid): ?int {
    $r = $db->query("SELECT id FROM personal WHERE proyecto_id = $pid AND nombre = '" . $db->real_escape_string($nombre) . "' LIMIT 1")->fetch_row();
    return $r ? (int)$r[0] : null;
};

// ---------------------------------------------------------------- imágenes
$cache = sys_get_temp_dir() . '/projectweb_demo_img';
@mkdir($cache, 0775, true);
$landing = __DIR__ . '/../public/build/img/landing';
$wiki = function (string $archivo, int $ancho, string $local) use ($cache): ?string {   // Wikimedia Commons (licencia indicada arriba)
    $f = "$cache/$local";
    if (!is_file($f) || filesize($f) < 5000) {
        $ctx = stream_context_create(['http' => ['timeout' => 60, 'header' => "User-Agent: ProjectWebDemo/1.0 (seed del demo)\r\n", 'follow_location' => 1]]);
        $d = @file_get_contents('https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($archivo) . '?width=' . $ancho, false, $ctx);
        if (!$d || !@getimagesizefromstring($d)) { echo "  ! no se pudo descargar $archivo\n"; return null; }
        file_put_contents($f, $d);
    }
    return $f;
};
$guardarImg = fn(?string $ruta, int $max = 2400): ?string => $ruta && is_file($ruta) ? Imagenes::guardarGrande($ruta, $max) : null;
$fuenteB = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
$fuenteR = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
$color = fn($im, string $hex) => imagecolorallocate($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
$texto = function ($im, float $size, int $x, int $y, string $hex, string $t, bool $neg = false) use ($fuenteB, $fuenteR, $color) {
    $f = $neg ? $fuenteB : $fuenteR;
    if (is_file($f) && function_exists('imagettftext')) imagettftext($im, $size, 0, $x, $y, $color($im, $hex), $f, $t);
    else imagestring($im, 4, $x, $y - 12, $t, $color($im, $hex));
};

// ---------------------------------------------------------------- tablas de Datos de obra (migración del Ensamblador; solo se crean si faltan)
$esquema = (string)@file_get_contents(__DIR__ . '/schema.sql');
foreach (['proyecto_fechas_contrato', 'actividad_responsables', 'actividad_cantidades', 'personal_contacto'] as $tabla) {
    if ($db->query("SHOW TABLES LIKE '$tabla'")->num_rows) continue;
    if (preg_match('/CREATE TABLE IF NOT EXISTS ' . $tabla . ' \(.*?\) ENGINE=InnoDB;/s', $esquema, $m)) { $q($m[0]); echo "  tabla $tabla creada (faltaba)\n"; }
}

// ---------------------------------------------------------------- limpieza previa (solo lo del demo)
echo "Limpiando lo anterior de las herramientas nuevas...\n";
$hashesDe = function (string $s): array {
    preg_match_all('#/uploads/vplan/([a-f0-9]{32}\.(?:png|jpg))#', $s, $m);
    return $m[1];
};
foreach ($rows("SELECT contenido FROM redactor_documentos WHERE proyecto_id = $pid") as $r) foreach ($hashesDe((string)$r['contenido']) as $h) Imagenes::eliminarVplan($h);
$q("DELETE FROM redactor_documentos WHERE proyecto_id = $pid");
$q("DELETE FROM vplan_tablas WHERE proyecto_id = $pid AND sheetflow_id IS NOT NULL");
$q("DELETE FROM sheetflow_hojas WHERE proyecto_id = $pid");
$q("DELETE FROM ordenes_cambio WHERE proyecto_id = $pid");
$q("DELETE FROM lineas_base WHERE proyecto_id = $pid");
$q("DELETE FROM msrs WHERE proyecto_id = $pid AND nombre LIKE 'MSR 2%'");
foreach ($rows("SELECT id FROM vplan_paquetes WHERE proyecto_id = $pid AND nombre = 'vPlan · Sala blanca y patio de equipos'") as $paq) {
    foreach ($rows("SELECT id, plano FROM vplan_laminas WHERE paquete_id = " . (int)$paq['id']) as $l) {
        Imagenes::eliminarVplan($l['plano']);
        foreach ($rows("SELECT config_json FROM vplan_bloques WHERE lamina_id = " . (int)$l['id'] . " AND tipo = 'imagen'") as $b) {
            $c = json_decode((string)$b['config_json'], true);
            if (!empty($c['archivo'])) Imagenes::eliminarVplan($c['archivo']);
        }
    }
    $q("DELETE FROM vplan_paquetes WHERE id = " . (int)$paq['id']);
}
$q("DELETE FROM vplan_tablas WHERE proyecto_id = $pid AND nombre_interno LIKE 'Demo · %'");
$q("DELETE FROM materiales WHERE proyecto_id = $pid AND referencia LIKE 'NUE-%'");
$q("DELETE FROM actividad_materiales WHERE material_id IN (SELECT id FROM materiales WHERE proyecto_id = $pid)");
$nombresArchivo = ['Planta de sala blanca Nave A · Rev. A', 'Planta de sala blanca Nave A · Rev. B', 'Avance de estructura metálica · Fotografía'];
$nombresViejos = ['Planta arquitectónica nivel 7 · Rev. A', 'Planta arquitectónica nivel 7 · Rev. B', 'Planta tipo nivel 7 · Arquitectónico', 'Planta tipo nivel 7 · Arquitectónico (revisado)'];   // nombres de versiones anteriores de este seed
$nomBorrar = implode(',', array_map($str, array_merge($nombresArchivo, $nombresViejos)));
foreach (Archivo::consultarSQL("SELECT * FROM archivos WHERE proyecto_id = $pid AND nombre IN ($nomBorrar)") as $a) @unlink(Archivo::carpeta($pid) . '/' . basename((string)$a->archivo_guardado));
$q("DELETE FROM archivos WHERE proyecto_id = $pid AND nombre IN ($nomBorrar)");
$q("DELETE FROM calendario_festivos WHERE proyecto_id = $pid AND motivo IN ('Día de la Revolución', 'Navidad', 'Mantenimiento de grúa torre', 'Lluvia intensa', 'Mantenimiento de grúa', 'Lluvia atípica')");
foreach (\Classes\Normativa::listar($pid) as $nd) \Classes\Normativa::eliminar($pid, (int)$nd['id']);
$q("DELETE FROM proyecto_fechas_contrato WHERE proyecto_id = $pid");
$q("DELETE FROM actividad_responsables WHERE actividad_id IN (SELECT a.id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid)");
$q("DELETE FROM actividad_cantidades WHERE proyecto_id = $pid");
$q("DELETE FROM personal_contacto WHERE personal_id IN (SELECT id FROM personal WHERE proyecto_id = $pid)");
$q("DELETE FROM cajetines WHERE proyecto_id = $pid");
$db->query(file_get_contents(__DIR__ . '/migracion_estampas.sql'));   // tabla de estampas (CREATE TABLE IF NOT EXISTS)
$q("DELETE FROM estampas WHERE proyecto_id = $pid");
$simbolosNuevos = ['Extintor', 'Botiquín', 'Grúa torre', 'Acceso de personal', 'Ruta de evacuación', 'Tablero provisional'];
foreach (\Model\Simbolo::deProyecto($pid) as $sy) if (in_array($sy->nombre, $simbolosNuevos, true)) { $sy->eliminarArchivo(); $q("DELETE FROM simbolos WHERE id = " . (int)$sy->id); }
$q("DELETE FROM minutas WHERE proyecto_id = $pid AND nombre IN ('Coordinación con el cliente · Avance de fachada', 'Revisión de instalaciones especiales', 'Junta de arranque de estructura metálica')");

if (in_array('--limpiar', $argv ?? [], true)) { echo "Solo se limpió lo anterior.\n"; return; }   // php database/seed_demo_nuevas.php --limpiar

// ---------------------------------------------------------------- imágenes de la obra
echo "Imágenes...\n";
$fotoCampo = "$landing/campo.jpg";
$fotoGrua = "$landing/grua.jpg";
$fotoPlanos = "$landing/planos.jpg";
$fotoAcero = $wiki('Steel frame building under construction in Taiwan.jpg', 1800, 'wiki-acero-taiwan.jpg') ?: $fotoCampo;
$fotoEstr = $wiki('Steel frame structure.jpg', 1800, 'wiki-acero-estructura.jpg') ?: $fotoGrua;

// Logos ficticios
$logo = function (string $nombre, string $hexA, string $hexB, string $sub) use ($color, $texto, $cache): ?string {
    if (!function_exists('imagecreatetruecolor')) return null;
    $im = imagecreatetruecolor(640, 200);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagefilledrectangle($im, 20, 30, 150, 160, $color($im, $hexA));
    imagefilledpolygon($im, [20, 160, 150, 160, 150, 60], 3, $color($im, $hexB));
    $texto($im, 38, 175, 105, '#0f172a', $nombre, true);
    $texto($im, 15, 178, 145, '#667085', $sub);
    $f = "$cache/logo-" . md5($nombre) . '.png';
    imagepng($im, $f);
    return $f;
};
$logoNexo = $guardarImg($logo('NEXO', '#06b6d4', '#0e7490', 'DATA CAMPUS'), 640);
$logoMeridian = $guardarImg($logo('MERIDIAN', '#0ea5e9', '#0f172a', 'CONSTRUCTORA'), 640);

// Planta de la sala blanca (dibujada con GD: racks, pasillos fríos/calientes, sala eléctrica y UPS) con cajetín,
// en dos revisiones: R1 de emisión inicial y R2 con la fila nueva de racks marcada para el Comparador
$plantaR1 = $plantaR2 = null;
if (function_exists('imagecreatetruecolor')) {
    $ancho = 1121;
    $alto = 700;
    $planta = function (string $rev, string $fecha, string $notaRev) use ($ancho, $alto, $color, $texto): string {
        $im = imagecreatetruecolor($ancho, $alto);
        imagefill($im, 0, 0, $color($im, '#ffffff'));
        $tinta = $color($im, '#0f172a');
        $gris = $color($im, '#94a3b8');

        // retícula tenue (cada 2 m) dentro del edificio
        for ($x = 30; $x <= 1090; $x += 46) imageline($im, $x, 30, $x, 600, $color($im, '#eef2f7'));
        for ($y = 30; $y <= 600; $y += 46) imageline($im, 30, $y, 1090, $y, $color($im, '#eef2f7'));

        // edificio y zonas
        imagefilledrectangle($im, 30, 30, 1090, 600, $color($im, '#ffffff'));
        imagerectangle($im, 30, 30, 1090, 600, $tinta);
        imagefilledrectangle($im, 50, 95, 790, 580, $color($im, '#f0fdff'));
        imagerectangle($im, 50, 95, 790, 580, $tinta);
        imagefilledrectangle($im, 810, 95, 1070, 330, $color($im, '#fffbeb'));
        imagerectangle($im, 810, 95, 1070, 330, $tinta);
        imagefilledrectangle($im, 810, 350, 1070, 580, $color($im, '#f5f3ff'));
        imagerectangle($im, 810, 350, 1070, 580, $tinta);

        // racks: tres bloques de dos filas con pasillo frío al centro y caliente al final
        foreach ([130, 268, 406] as $k => $base) {
            for ($x = 75; $x <= 745; $x += 47) {
                imagefilledrectangle($im, $x, $base, $x + 40, $base + 38, $color($im, '#334155'));
                imagerectangle($im, $x, $base, $x + 40, $base + 38, $color($im, '#0e7490'));
                imagefilledrectangle($im, $x, $base + 70, $x + 40, $base + 108, $color($im, '#334155'));
                imagerectangle($im, $x, $base + 70, $x + 40, $base + 108, $color($im, '#0e7490'));
            }
            imagefilledrectangle($im, 60, $base + 38, 770, $base + 70, $color($im, '#cffafe'));
            imagefilledrectangle($im, 60, $base + 108, 770, $base + 130, $color($im, '#fee2e2'));
            imagerectangle($im, 60, $base + 38, 770, $base + 70, $color($im, '#67e8f9'));
            imagerectangle($im, 60, $base + 108, 770, $base + 130, $color($im, '#fca5a5'));
            $texto($im, 10, 68, $base + 60, '#0e7490', 'PASILLO FRÍO ' . ($k + 1) . ' · SUMINISTRO POR PISO TÉCNICO', true);
            $texto($im, 9, 68, $base + 124, '#b91c1c', 'PASILLO CALIENTE ' . ($k + 1) . ' · RETORNO A CRAC', true);
        }

        // sala eléctrica, subestación y UPS
        $texto($im, 11, 826, 120, '#0f172a', 'SALA ELÉCTRICA', true);
        $texto($im, 9, 826, 138, '#667085', 'SUBESTACIÓN 34.5 kV · TABLEROS TG');
        for ($i = 0; $i < 4; $i++) {
            imagefilledrectangle($im, 826 + $i * 58, 155, 872 + $i * 58, 245, $color($im, '#f59e0b'));
            imagerectangle($im, 826 + $i * 58, 155, 872 + $i * 58, 245, $tinta);
        }
        $texto($im, 9, 826, 272, '#667085', 'Transformadores 2 × 2,500 kVA');
        $texto($im, 11, 826, 375, '#0f172a', 'SALA DE UPS Y BATERÍAS', true);
        for ($i = 0; $i < 4; $i++) {
            imagefilledrectangle($im, 826, 395 + $i * 44, 1054, 423 + $i * 44, $color($im, '#8b5cf6'));
            imagerectangle($im, 826, 395 + $i * 44, 1054, 423 + $i * 44, $tinta);
        }

        // accesos y norte
        imagefilledrectangle($im, 380, 580, 470, 600, $color($im, '#ffffff'));
        imagerectangle($im, 380, 580, 470, 600, $tinta);
        $texto($im, 9, 390, 615, '#0f172a', 'ACCESO PRINCIPAL');
        $texto($im, 9, 1000, 620, '#667085', 'N ↑');

        // nota de revisión (solo R2)
        if ($notaRev !== '') {
            $rojo = $color($im, '#e11d48');
            imagerectangle($im, 300, 50, 780, 88, $rojo);
            imagefilledrectangle($im, 300, 50, 780, 88, $color($im, '#fff1f2'));
            $texto($im, 10, 312, 70, '#e11d48', 'REV. B · Se agrega la fila 3 de racks y se amplía el pasillo caliente', true);
            $texto($im, 9, 312, 84, '#e11d48', 'Coordinado con la OC-006 (ampliación del MDF-1) y el layout de PDUs');
            imagesetthickness($im, 3);
            $rojo2 = $color($im, '#e11d48');
            imageellipse($im, 430, 450, 260, 150, $rojo2);
            imageellipse($im, 430, 450, 268, 158, $rojo2);
            imagesetthickness($im, 1);
        }

        // cajetín
        imagefilledrectangle($im, 4, 622, 1116, 695, $color($im, '#f0f9ff'));
        imagerectangle($im, 4, 4, 1116, 695, $tinta);
        imagerectangle($im, 4, 622, 1116, 695, $tinta);
        $texto($im, 15, 20, 650, '#0f172a', 'NEXO DATA CAMPUS · CENTRO DE DATOS TIER III', true);
        $texto($im, 11, 20, 678, '#667085', 'SALA BLANCA NAVE A · PLANTA DE RACKS Y PASILLOS · ESC. 1:100');
        $texto($im, 12, 640, 650, '#0f172a', "REV. $rev", true);
        $texto($im, 10, 640, 678, '#667085', $fecha . ($notaRev !== '' ? ' · Cambios marcados en rojo' : ' · Emisión inicial'));
        $texto($im, 10, 940, 650, '#06b6d4', 'A-201', true);

        $ruta = sys_get_temp_dir() . '/planta-r' . strtolower($rev) . '.png';
        imagepng($im, $ruta);
        imagedestroy($im);
        return $ruta;
    };
    $plantaR1 = $planta('A', $GLOBALS['hoy']->format('d/m/Y'), '');
    $plantaR2 = $planta('B', (clone $GLOBALS['hoy'])->modify('+6 days')->format('d/m/Y'), 'nota');
}

// ---------------------------------------------------------------- calendario: más festivos, paros y clima
echo "Calendario...\n";
$q("INSERT INTO calendario_festivos (proyecto_id, fecha, motivo, tipo) VALUES
    ($pid, '" . (clone $hoy)->modify('third monday of november ' . $hoy->format('Y'))->format('Y-m-d') . "', 'Día de la Revolución', 'festivo'),
    ($pid, '" . $hoy->format('Y') . "-12-25', 'Navidad', 'festivo'),
    ($pid, '{$dia(9)}', 'Mantenimiento de grúa', 'paro_tecnico'),
    ($pid, '{$dia(-5)}', 'Lluvia atípica', 'clima')");

// ---------------------------------------------------------------- materiales nuevos y vínculos con actividades (Planificador y cruce con el cronograma)
echo "Materiales y cruce con el cronograma...\n";
$nuevos = [
    // [ref, nombre, especialidad, compra, compra_prog, compra_real, entrega_prog, entrega_real, cantidad, unidad]
    ['NUE-CHI-250', 'Chiller enfriado por agua 250 TR', 'Aire acondicionado', 'pendiente', $dia(-9), null, '2026-12-20', null, 3, 'pza'],
    ['NUE-ROC-01', 'Rociadores y tubería de acero cédula 40', 'Hidrosanitaria', 'comprado', $dia(-30), $dia(-28), $dia(24), null, 3200, 'm'],
    ['NUE-CAN-01', 'Charola portacable tipo malla galvanizada', 'Voz y datos', 'comprado', $dia(-6), $dia(-5), $dia(3), null, 1450, 'm'],
    ['NUE-LUM-01', 'Luminaria LED panel 60x60 regulable', 'Instalación eléctrica', 'pendiente', null, null, null, null, 860, 'pza'],
    ['NUE-RACK-01', 'Rack de cómputo 42U con PDU monitoreado', 'Sala blanca', 'pendiente', $dia(12), null, '2027-01-04', null, 48, 'pza'],
    ['NUE-DUC-01', 'Ducto de lámina galvanizada cal. 24', 'Aire acondicionado', 'comprado', $dia(-45), $dia(-44), $dia(-14), $dia(-13), 5200, 'kg'],
];
$mn = [];
foreach ($nuevos as [$ref, $nom, $esp, $compra, $cp, $cr, $ep, $er, $cant, $uni]) {
    $mn[$ref] = (int)$q("INSERT INTO materiales (proyecto_id, referencia, nombre, especialidad, compra, fecha_compra_prog, fecha_compra_real, fecha_entrega_prog, fecha_entrega_real, cantidad, unidad)
        VALUES ($pid, {$str($ref)}, {$str($nom)}, {$str($esp)}, '$compra', {$str($cp)}, {$str($cr)}, {$str($ep)}, {$str($er)}, $cant, {$str($uni)})");
}
$q("INSERT IGNORE INTO especialidades (proyecto_id, nombre) SELECT DISTINCT proyecto_id, especialidad FROM materiales WHERE proyecto_id = $pid AND especialidad <> ''");
// La cancelería de fachada (ya en el catálogo) tiene la compra vencida: arrastra el inicio de la fachada
$canc = $rows("SELECT id FROM materiales WHERE proyecto_id = $pid AND nombre LIKE 'Cancelería de aluminio%' LIMIT 1")[0]['id'] ?? null;
if ($canc) {
    $q("UPDATE materiales SET compra = 'pendiente', fecha_compra_prog = '{$dia(-6)}', fecha_entrega_prog = '2026-11-20' WHERE id = " . (int)$canc);
    $q("UPDATE msr_items SET fecha_compra_prog = '{$dia(-6)}', fecha_entrega_prog = '2026-11-20' WHERE material_id = " . (int)$canc);
}
$mid = fn(string $prefijo) => (int)($rows("SELECT id FROM materiales WHERE proyecto_id = $pid AND nombre LIKE {$str($prefijo . '%')} ORDER BY id LIMIT 1")[0]['id'] ?? 0);
$enlaces = [
    ['3.4', $mid('Varilla corrugada')], ['3.4', $mid("Concreto premezclado")],
    ['5.4', $mid('Tablero de distribución')], ['5.5', $mn['NUE-LUM-01']], ['5.3', $mn['NUE-CAN-01']],
    ['6.4', $mn['NUE-ROC-01']], ['6.5', $mid('Equipo de bombeo')],
    ['7.1', $mn['NUE-DUC-01']], ['7.3', $mn['NUE-CHI-250']],
    ['8.1', $mn['NUE-CAN-01']], ['8.2', $mid('Fibra óptica')], ['8.3', $mid('Gabinete de comunicaciones')], ['8.4', $mid('Cámara IP')],
    ['4.4', (int)$canc], ['9.2', $mid('Porcelanato')], ['9.5', $mn['NUE-RACK-01']],
];
foreach ($enlaces as [$codigo, $m]) {
    $a = $act($codigo);
    if ($a && $m) $q("INSERT IGNORE INTO actividad_materiales (actividad_id, material_id) VALUES ($a, $m)");
}

// ---------------------------------------------------------------- órdenes de cambio
echo "Órdenes de cambio...\n";
$oc = function (string $num, string $titulo, string $desc, string $solic, string $estado, int $off, float $costo, int $dias, ?string $codAct, array $renglones) use ($q, $str, $pid, $act, $dia) {
    $a = $codAct ? $act($codAct) : null;
    $id = (int)$q("INSERT INTO ordenes_cambio (proyecto_id, numero, titulo, descripcion, solicitante, estado, fecha, impacto_costo, impacto_dias, actividad_id, creado_por)
        VALUES ($pid, {$str($num)}, {$str($titulo)}, {$str($desc)}, {$str($solic)}, '$estado', '{$dia($off)}', $costo, $dias, " . ($a ?: 'NULL') . ", 'Valeria Sotomayor')");
    foreach ($renglones as $r) {
        [$tipo, $d, $orig, $nueva, $cant, $uni, $cod, $mat, $retiro, $est, $prev, $ejec, $retPrev, $retReal, $notas] = $r + array_fill(0, 15, null);
        $ra = $cod ? $act($cod) : null;
        $q("INSERT INTO cambio_partidas (orden_id, proyecto_id, tipo, descripcion, ubicacion_original, ubicacion_nueva, cantidad, unidad, actividad_id, material_id, requiere_retiro, estado, fecha_prevista, fecha_ejecucion, fecha_retiro_prevista, fecha_retiro_real, notas)
            VALUES ($id, $pid, '$tipo', {$str($d)}, {$str($orig ?? '')}, {$str($nueva ?? '')}, " . ($cant === null ? 'NULL' : (float)$cant) . ", {$str($uni ?? '')}, " . ($ra ?: 'NULL') . ', ' . ($mat ?: 'NULL') . ', ' . (int)$retiro . ", '$est', {$str($prev)}, {$str($ejec)}, {$str($retPrev)}, {$str($retReal)}, {$str($notas ?? '')})");
    }
    return $id;
};
$oc('OC-001', 'Refuerzo de acero adicional en columnas de la Nave B', 'El cálculo estructural revisado (R2) pide aumentar el armado longitudinal en las columnas del eje 7 por la sobrecarga de las unidades CRAC en cubierta.', 'Diego Carranza', 'ejecutada', -34, 148500, 4, '3.4', [
    ['adicion', 'Acero de refuerzo adicional #8 en columnas eje 7', 'Nave B', 'Nave B', 12.5, 'ton', '3.4', $mid('Varilla corrugada'), 0, 'verificado', $dia(-30), $dia(-27), null, null, 'Verificado por supervisión de calidad.'],
    ['modificacion', 'Ajuste de estribos en nudos de columna C-14', 'Nave B', 'Nave B', 18, 'pza', '3.4', null, 0, 'verificado', $dia(-29), $dia(-26), null, null, ''],
]);
$oc('OC-002', 'Andamio y red de protección en fachada oriente', 'Protección temporal para trabajos en altura mientras llega la cancelería; se retira antes de instalar el cristal.', 'Raúl Espinoza', 'aprobada', -21, 96000, 0, '4.4', [
    ['instalacion_temporal', 'Andamio multidireccional en fachada oriente', 'Almacén', 'Fachada oriente', 640, 'm²', '4.4', null, 1, 'ejecutado', $dia(-18), $dia(-17), $dia(-3), null, 'Retiro vencido: coordinar con instalación de cancelería.'],
    ['instalacion_temporal', 'Red de protección contra caída de objetos', 'Almacén', 'Fachada oriente', 480, 'm²', '4.4', null, 1, 'ejecutado', $dia(-18), $dia(-16), $dia(20), null, ''],
    ['instalacion_temporal', 'Plataforma de descarga provisional en acceso', 'Sin ubicar', 'Acceso principal', 1, 'pza', '4.4', null, 1, 'ejecutado', $dia(-15), $dia(-14), null, null, 'Sin fecha de retiro programada.'],
]);
$oc('OC-003', 'Reubicación del tablero de distribución TD-3', 'Interferencia con el ducto principal de enfriamiento detectada en el RFI-018.', 'Lucía Fonseca', 'solicitada', -8, 42000, 3, '5.4', [
    ['reubicacion', 'Mover tablero TD-3 y su canalización', 'Nave A · eje C-4', 'Nave A · eje D-2', 1, 'pza', '5.4', $mid('Tablero de distribución'), 0, 'pendiente', $dia(20), null, null, null, 'Pendiente de autorización del cliente.'],
]);
$oc('OC-004', 'Luminarias LED regulables en pasillos técnicos', 'El cliente solicita cambiar la luminaria de especificación por LED regulable con sensor de presencia.', 'Arturo Landa', 'aprobada', -12, -35000, 0, '5.5', [
    ['modificacion', 'Sustituir luminaria por LED regulable 60x60', 'Naves A y B', 'Naves A y B', 860, 'pza', '5.5', $mn['NUE-LUM-01'], 0, 'pendiente', $dia(75), null, null, null, 'El proveedor aún no confirma fecha de entrega.'],
]);
$oc('OC-005', 'Demolición del muro provisional de acceso peatonal', 'Se libera el acceso definitivo al campus.', 'Valeria Sotomayor', 'cerrada', -50, 18000, 0, null, [
    ['demolicion', 'Demoler muro provisional de block', 'Acceso norte', '', 38, 'm²', null, null, 0, 'verificado', $dia(-45), $dia(-44), null, null, ''],
]);
$oc('OC-006', 'Ampliación del cuarto de telecomunicaciones MDF-1', 'Mayor número de racks por requerimiento del cliente.', 'Iván Trejo', 'borrador', -2, 27500, 2, '8.3', [
    ['adicion', 'Ampliar MDF-1 y agregar dos racks', 'Nave A', 'Nave A', 14, 'm²', '8.3', $mid('Gabinete de comunicaciones'), 0, 'pendiente', $dia(60), null, null, null, ''],
]);
$oc('OC-007', 'Cambio de porcelanato en vestíbulos', 'Se propone mármol; el cliente lo rechaza por costo.', 'Sergio Beltrán', 'rechazada', -26, 310000, 12, '9.2', []);

// ---------------------------------------------------------------- líneas base (foto del programa + diferencias para la comparación)
echo "Líneas base...\n";
$_SESSION['nombre'] = $_SESSION['nombre'] ?? 'Valeria Sotomayor';
$lb1 = LineasBase::capturar($proyecto, 'Línea base contractual', 'Programa firmado con el cliente al inicio de la obra.');
$q("UPDATE lineas_base SET creado = NOW() - INTERVAL 110 DAY WHERE id = $lb1");
$desvio = ['3.4' => 6, '4.4' => 10, '5.3' => 3, '6.4' => 7, '7.1' => 4, '9.2' => 5];   // días que hoy van atrasadas contra la línea base
foreach ($desvio as $cod => $d) {
    $a = $act($cod);
    if ($a) $q("UPDATE lineas_base_actividades SET fecha_inicio = DATE_SUB(fecha_inicio, INTERVAL $d DAY), fecha_fin = DATE_SUB(fecha_fin, INTERVAL $d DAY) WHERE linea_id = $lb1 AND actividad_id = $a");
}
foreach (['5.6', '9.5'] as $cod) { $a = $act($cod); if ($a) $q("DELETE FROM lineas_base_actividades WHERE linea_id = $lb1 AND actividad_id = $a"); }   // actividades nuevas
$q("INSERT INTO lineas_base_actividades (linea_id, actividad_id, codigo, nombre, partida, fecha_inicio, fecha_fin, duracion_dias, progreso, hito) VALUES
    ($lb1, 99991, '2.7', 'Demolición de muro provisional de acceso', '2.0 · Cimentación y losa técnica', '{$dia(-80)}', '{$dia(-70)}', 8, 100, 0)");   // actividad eliminada
$criticasHoy = [];
foreach (\Classes\Dependencias::datos($proyecto)['actividades'] as $a) if ($a['critica']) $criticasHoy[] = (int)$a['id'];
$sinSer = array_filter(array_map($act, ['3.5', '9.3', '10.3']));   // hoy son críticas y antes no lo eran (entran a la ruta)
$criticas = array_values(array_unique(array_merge(array_diff($criticasHoy, $sinSer), array_filter(array_map($act, ['4.3', '4.4', '5.3', '6.4'])))));   // y estas ya no (salen)
$q("UPDATE lineas_base SET criticas_json = {$str(json_encode($criticas))}, fin_programado = DATE_SUB(fin_programado, INTERVAL 14 DAY) WHERE id = $lb1");
foreach ([['Cancelería', 12], ['Rociadores', 6], ['Chiller', 15]] as [$p, $d]) {
    $q("UPDATE lineas_base_materiales SET fecha_entrega_prog = DATE_SUB(fecha_entrega_prog, INTERVAL $d DAY), fecha_compra_prog = DATE_SUB(fecha_compra_prog, INTERVAL $d DAY) WHERE linea_id = $lb1 AND nombre LIKE {$str($p . '%')}");
}
$lb2 = LineasBase::capturar($proyecto, 'Reprogramación · cierre de estructura', 'Ajuste tras la OC-001 y el cambio de proveedor de acero.');
$q("UPDATE lineas_base SET creado = NOW() - INTERVAL 28 DAY WHERE id = $lb2");
foreach (['3.4' => 2, '4.4' => 4, '7.1' => 2] as $cod => $d) {
    $a = $act($cod);
    if ($a) $q("UPDATE lineas_base_actividades SET fecha_inicio = DATE_SUB(fecha_inicio, INTERVAL $d DAY), fecha_fin = DATE_SUB(fecha_fin, INTERVAL $d DAY) WHERE linea_id = $lb2 AND actividad_id = $a");
}

// ---------------------------------------------------------------- SheetFlow (tablas con fórmulas, en carpetas)
echo "SheetFlow...\n";
$hoja = function (string $nombre, string $depto, string $desc, array $cols, array $filas, int $off) use ($pid, $dia) {
    $h = new SheetflowHoja(['proyecto_id' => $pid, 'nombre' => $nombre, 'fecha' => $dia($off), 'departamento' => $depto, 'descripcion' => $desc]);
    $h->id = $h->guardar()['id'];
    Sheetflow::guardarDatos($h, ['cols' => $cols, 'rows' => $filas, 'estilo' => ['enc' => '#0e7490', 'encTxt' => '#ffffff', 'zebra' => false, 'bordes' => true]]);
    return $h;
};
$ins = [['Concreto premezclado f\'c 350 kg/cm²', 'm³', 412, 2850], ['Acero de refuerzo fy 4200 #8 y #6', 'ton', 36.4, 21800], ['Cimbra de contacto', 'm²', 1950, 385], ['Malla electrosoldada 6x6', 'm²', 1240, 74],
    ['Separadores y silletas', 'lote', 1, 18500], ['Bombeo de concreto', 'm³', 412, 120], ['Curado y aditivos', 'm²', 1240, 38], ['Mano de obra especializada', 'jornal', 560, 780]];
$filasIns = [];
foreach ($ins as $i => [$c, $u, $ct, $pu]) $filasIns[] = [$c, $u, (string)$ct, (string)$pu, '=C' . ($i + 2) . '*D' . ($i + 2)];
$sfIns = $hoja('Explosión de insumos · Losa de sala blanca', 'Ingeniería', 'Cantidades y costo directo de la losa técnica', [['n' => 'Concepto'], ['n' => 'Unidad'], ['n' => 'Cantidad', 't' => 'numero'], ['n' => 'P.U.', 't' => 'moneda'], ['n' => 'Importe', 't' => 'moneda', 'tot' => 'suma']], $filasIns, -9);
$colados = [[-40, 'Zapatas Z-1 a Z-16', 96, 350, 372, 12], [-33, 'Losa de cimentación sala blanca', 280, 350, 361, 11], [-24, 'Columnas Nave A', 64, 350, 358, 12], [-15, 'Losa mezzanine', 210, 350, 349, 12],
    [-9, 'Columnas Nave B', 58, 350, 366, 13], [-4, 'Losa de cubierta técnica', 205, 350, 355, 12], [-1, 'Dados de equipos', 52, 350, 344, 12]];
$filasCol = [];
foreach ($colados as $i => [$off, $el, $vol, $fcp, $fcr, $rev]) $filasCol[] = [$dia($off), $el, (string)$vol, (string)$fcp, (string)$fcr, (string)$rev, '=SI(E' . ($i + 2) . '>=D' . ($i + 2) . ',"Cumple","Revisar")'];
$sfCol = $hoja('Control de colados y resistencias', 'Calidad y seguridad', 'Registro de colados con resultado de cilindros a 28 días', [['n' => 'Fecha', 't' => 'fecha'], ['n' => 'Elemento'], ['n' => 'Volumen m³', 't' => 'numero', 'tot' => 'suma'],
    ['n' => 'f\'c proyecto', 't' => 'numero'], ['n' => 'f\'c obtenido', 't' => 'numero'], ['n' => 'Revenimiento cm', 't' => 'numero', 'tot' => 'promedio'], ['n' => 'Resultado']], $filasCol, -1);
$costos = [['Preliminares y movimiento de tierras', 1850000, 1790000], ['Cimentación y losa técnica', 14200000, 14650000], ['Estructura', 38400000, 21300000], ['Envolvente y obra exterior', 16900000, 6100000],
    ['Instalación eléctrica y respaldo', 12300000, 5200000], ['Hidrosanitaria y contra incendio', 9800000, 3900000], ['Enfriamiento y ventilación', 14500000, 1900000], ['Fibra, seguridad y monitoreo', 7600000, 800000]];
$filasCos = [];
foreach ($costos as $i => [$p, $pres, $ej]) $filasCos[] = [$p, (string)$pres, (string)$ej, '=B' . ($i + 2) . '-C' . ($i + 2), '=SI(D' . ($i + 2) . '<0,"Sobrecosto","Dentro de presupuesto")'];
$sfCos = $hoja('Presupuesto contra ejercido por partida', 'Costos', 'Control mensual de costos directos', [['n' => 'Partida'], ['n' => 'Presupuesto', 't' => 'moneda', 'tot' => 'suma'], ['n' => 'Ejercido', 't' => 'moneda', 'tot' => 'suma'],
    ['n' => 'Disponible', 't' => 'moneda', 'tot' => 'suma'], ['n' => 'Estado']], $filasCos, -3);

// ---------------------------------------------------------------- Minutas en carpetas
echo "Minutas...\n";
$q("UPDATE minutas SET departamento = 'Coordinación general' WHERE proyecto_id = $pid AND nombre = 'Junta semanal de coordinación'");
$q("UPDATE minutas SET departamento = 'Seguridad y calidad' WHERE proyecto_id = $pid AND nombre = 'Revisión de seguridad y calidad'");
$pj = fn(array $nombres) => json_encode(array_values(array_filter(array_map($persona, $nombres))));
$cj = json_encode(array_map('intval', array_column($rows("SELECT id FROM personal_contratista WHERE proyecto_id = $pid ORDER BY id LIMIT 3"), 'id')));
$tc = fn(array $l) => json_encode(array_map(fn($x) => ['tema' => $x[0], 'responsable' => $x[1], 'departamento' => $x[2]], $l), JSON_UNESCAPED_UNICODE);
$minutas = [
    ['Coordinación con el cliente · Avance de fachada', -6, 'alta', 'Cliente', ['Valeria Sotomayor', 'Diego Carranza', 'Mónica Aldana'], [['Muestra aprobada de cancelería y fecha de entrega', 'Sergio Beltrán', 'Sala blanca'], ['Andamio de fachada y retiro de instalaciones temporales (OC-002)', 'Raúl Espinoza', 'Seguridad'], ['Impacto de la OC-004 en luminarias', 'Arturo Landa', 'Supervisión']],
        [['Entregar al cliente la muestra física de cristal templado', 'Sergio Beltrán', 'Sala blanca'], ['Programar el retiro del andamio de la fachada oriente', 'Raúl Espinoza', 'Seguridad']]],
    ['Revisión de instalaciones especiales', -13, 'media', 'Instalaciones', ['Iván Trejo', 'Diego Carranza', 'Paola Rentería'], [['Interferencias eléctricas y de enfriamiento en plafón', 'Paola Rentería', 'Ingeniería'], ['Ruta de charolas de comunicaciones en la Nave A', 'Iván Trejo', 'Instalaciones'], ['Pruebas de contra incendio previas al cierre de muros', 'Fernanda Quiroz', 'Calidad']],
        [['Emitir plano coordinado de plafón de la Nave A', 'Paola Rentería', 'Ingeniería']]],
    ['Junta de arranque de estructura metálica', -27, 'media', 'Coordinación general', ['Óscar Villalpando', 'Valeria Sotomayor', 'Raúl Espinoza'], [['Secuencia de montaje de cubierta y dunnage', 'Óscar Villalpando', 'Estructura'], ['Plan de izaje y permisos de trabajo en altura', 'Raúl Espinoza', 'Seguridad']], []],
];
foreach ($minutas as [$nom, $off, $imp, $depto, $personas, $temas, $comps]) {
    $q("INSERT INTO minutas (proyecto_id, fecha, nombre, importancia, departamento, contratista_json, personal_json, temas_json, compromisos_json) VALUES
        ($pid, '{$dia($off)}', {$str($nom)}, '$imp', {$str($depto)}, {$str($cj)}, {$str($pj($personas))}, {$str($tc($temas))}, " . ($comps ? $str($tc($comps)) : 'NULL') . ')');
}

// ---------------------------------------------------------------- MSR configurable (columnas propias, columna de texto libre y color por partida)
echo "MSR configurable...\n";
$cols = [
    ['k' => 'imagen', 'n' => 'FOTO', 'c' => '#0e7490', 'g' => 'IDENTIFICACIÓN'],
    ['k' => 'descripcion', 'n' => 'MATERIAL O EQUIPO', 'c' => '#0e7490', 'g' => 'IDENTIFICACIÓN'],
    ['k' => 'txt_1', 'n' => 'ZONA / SALA', 'c' => '#0e7490', 'g' => 'IDENTIFICACIÓN'],
    ['k' => 'status', 'n' => 'ESTATUS', 'c' => '#0891b2', 'g' => 'SEGUIMIENTO'],
    ['k' => 'fecha_compra_prog', 'n' => 'COMPRA PROG.', 'c' => '#0891b2', 'g' => 'SEGUIMIENTO'],
    ['k' => 'fecha_entrega_prog', 'n' => 'ENTREGA PROG.', 'c' => '#0891b2', 'g' => 'SEGUIMIENTO'],
    ['k' => 'proveedor', 'n' => 'PROVEEDOR', 'c' => '#1d4ed8', 'g' => 'LOGÍSTICA'],
    ['k' => 'txt_2', 'n' => 'RESIDENTE RESPONSABLE', 'c' => '#1d4ed8', 'g' => 'LOGÍSTICA'],
    ['k' => 'observaciones', 'n' => 'OBSERVACIONES', 'c' => '#1d4ed8', 'g' => 'LOGÍSTICA'],
];
$msr2 = (int)$q("INSERT INTO msrs (proyecto_id, nombre, descripcion, columnas_json) VALUES ($pid, 'MSR 2 · Equipos de instalaciones', 'Seguimiento de equipos mayores con columnas propias de la obra', {$str(\Model\Msr::normalizarColumnas('personalizado', $cols))})");
$secciones = [
    ['ENFRIAMIENTO DE PRECISIÓN', '#0891b2', [['NUE-CHI-250', 'Patio de equipos', 'A', 'Trane', 'Ing. Paola Rentería', 'Equipo de 12 semanas de fabricación'], ['NUE-DUC-01', 'Naves A y B', 'A', 'Ductos del Bajío', 'Ing. Paola Rentería', 'Entregado y en sitio']]],
    ['PROTECCIÓN CONTRA INCENDIO', '#dc2626', [['NUE-ROC-01', 'Naves y cuartos técnicos', 'AN', 'Tyco Fire', 'Ing. Fernanda Quiroz', 'Se adelanta entrega parcial por obra']]],
    ['ELÉCTRICO Y COMUNICACIONES', '#d97706', [['NUE-LUM-01', 'Naves A y B', 'P', 'Lumileds', 'Ing. Iván Trejo', 'Ligado a la OC-004'], ['NUE-CAN-01', 'Nave A', 'A', 'Charolas MX', 'Ing. Iván Trejo', '']]],
    ['SALA BLANCA', '#0e7490', [['NUE-RACK-01', 'Pasillos calientes A-1 y A-2', 'P', 'Vertiv', 'Ing. Sergio Beltrán', 'Pendiente de aprobar el submittal y la densidad de carga']]],
];
$ordenMsr = 0;
foreach ($secciones as $i => [$titulo, $col, $items]) {
    $q("INSERT INTO msr_partidas (msr_id, titulo, color, orden) VALUES ($msr2, {$str($titulo)}, '$col', $i)");
    foreach ($items as [$ref, $zona, $st, $prov, $resp, $obs]) {
        $m = $mn[$ref];
        $mt = $rows("SELECT fecha_compra_prog, fecha_entrega_prog FROM materiales WHERE id = $m")[0];
        $q("INSERT INTO msr_items (proyecto_id, msr_id, submittal_id, material_id, seccion, status, fecha_compra_prog, fecha_entrega_prog, proveedor, observaciones, extras_json, orden)
            VALUES ($pid, $msr2, NULL, $m, {$str($titulo)}, '$st', {$str($mt['fecha_compra_prog'])}, {$str($mt['fecha_entrega_prog'])}, {$str($prov)}, {$str($obs)}, {$str(json_encode(['txt_1' => $zona, 'txt_2' => $resp], JSON_UNESCAPED_UNICODE))}, " . (++$ordenMsr) . ')');
    }
}

// ---------------------------------------------------------------- Archivo: dos revisiones del mismo plano (para el Comparador) y una foto de avance
echo "Archivo y Comparador...\n";
$catPlanos = $rows("SELECT id FROM archivo_categorias WHERE proyecto_id = $pid AND nombre = 'Planos' LIMIT 1")[0]['id'] ?? null;
if (!$catPlanos) $catPlanos = $q("INSERT INTO archivo_categorias (proyecto_id, nombre) VALUES ($pid, 'Planos')");
$catFotos = $rows("SELECT id FROM archivo_categorias WHERE proyecto_id = $pid AND nombre = 'Fotografías de avance' LIMIT 1")[0]['id'] ?? null;
if (!$catFotos) $catFotos = $q("INSERT INTO archivo_categorias (proyecto_id, nombre) VALUES ($pid, 'Fotografías de avance')");
@mkdir(Archivo::carpeta($pid), 0775, true);
$subirArchivo = function (?string $ruta, string $ext, int $cat, string $nombre, string $rev, int $off) use ($pid, $q, $str, $dia) {
    if (!$ruta || !is_file($ruta)) return;
    $guardado = bin2hex(random_bytes(12)) . '.' . $ext;
    copy($ruta, Archivo::carpeta($pid) . '/' . $guardado);
    $original = preg_replace('/[^A-Za-z0-9_-]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre)) . '.' . $ext;
    $q("INSERT INTO archivos (proyecto_id, categoria_id, fecha, nombre, numero_revision, archivo_original, archivo_guardado, tamano)
        VALUES ($pid, $cat, {$str($dia($off))}, {$str($nombre)}, {$str($rev)}, {$str($original)}, {$str($guardado)}, " . filesize($ruta) . ')');
};
$subirArchivo($plantaR1, 'png', (int)$catPlanos, $nombresArchivo[0], 'A', -12);
$subirArchivo($plantaR2, 'png', (int)$catPlanos, $nombresArchivo[1], 'B', -2);
$subirArchivo($fotoAcero, 'jpg', (int)$catFotos, $nombresArchivo[2], 'Oct', -1);

// ---------------------------------------------------------------- Redactor (documentos de texto en carpetas)
echo "Redactor...\n";
$img = fn(?string $h, string $ancho = '60%') => $h ? '<p style="text-align:center"><img src="/uploads/vplan/' . $h . '" style="width:' . $ancho . '"></p>' : '';
$celda = 'border:1px solid #9aa3b5;padding:4px';
$cab = 'border:1px solid #9aa3b5;padding:4px;background-color:#0e7490;color:#ffffff';
$tabla = function (array $enc, array $filas) use ($celda, $cab): string {
    $t = '<table style="border-collapse:collapse;width:100%"><tr>';
    foreach ($enc as $e) $t .= '<th style="' . $cab . '">' . htmlspecialchars($e) . '</th>';
    $t .= '</tr>';
    foreach ($filas as $f) { $t .= '<tr>'; foreach ($f as $c) $t .= '<td style="' . $celda . '">' . htmlspecialchars((string)$c) . '</td>'; $t .= '</tr>'; }
    return $t . '</table><p></p>';
};
$portada = fn(string $titulo, string $sub) => $img($logoMeridian, '28%') . '<p></p><p></p><h1 style="color:#0891b2;text-align:center">' . htmlspecialchars($titulo) . '</h1><p style="text-align:center;font-size:14pt">' . htmlspecialchars($sub)
    . '</p><p></p>' . $img($logoNexo, '24%') . '<p style="text-align:center">Cliente: Nexo Digital · Contratista: Constructora Meridian</p><div class="salto-pagina"></div>';
$partidasTabla = array_map(fn($p) => [$p['codigo'], $p['nombre'], $p['fecha_inicio'], $p['fecha_fin']], $rows("SELECT codigo, nombre, fecha_inicio, fecha_fin FROM partidas WHERE proyecto_id = $pid ORDER BY codigo + 0"));
$avanceTabla = array_map(fn($p) => [$p['codigo'], $p['nombre'], round((float)$p['avance']) . ' %'], $rows("SELECT p.codigo, p.nombre, COALESCE(AVG(a.progreso), 0) avance FROM partidas p LEFT JOIN actividades a ON a.partida_id = p.id WHERE p.proyecto_id = $pid GROUP BY p.id ORDER BY p.codigo + 0"));

$docs = [];
$docs[] = ['Memoria descriptiva · Nexo Data Campus', 'Ingeniería', -20, 'carta', 'vertical', 'CONTROLADO',
    $portada('MEMORIA DESCRIPTIVA', 'Nexo Data Campus · Centro de datos Tier III de dos naves')
    . '<h2>1. Antecedentes</h2><p style="text-align:justify">El proyecto contempla la construcción de un campus de centro de datos de dos naves con sala blanca, sala eléctrica, patio de equipos y edificio de oficinas de operación, en un parque tecnológico con disponibilidad de energía y fibra redundante. El desarrollador es Nexo Digital y la construcción está a cargo de Constructora Meridian.</p>'
    . '<h2>2. Descripción del proyecto</h2>' . $tabla(['Concepto', 'Dato'], [['Naves', '2 naves de una planta + mezzanine técnico'], ['Superficie de construcción', '24,000 m²'], ['Sala blanca', '3,700 m² en dos módulos'], ['Capacidad eléctrica', '2 × 2,500 kVA con respaldo N+1'], ['Carga por rack', 'Hasta 12 kW por gabinete'], ['Certificación objetivo', 'Uptime Institute Tier III']])
    . '<h2>3. Sistema estructural</h2><p style="text-align:justify">La estructura es de concreto reforzado en las naves y de acero A992 en la cubierta y el dunnage de equipos. Las losas técnicas se diseñaron para una sobrecarga de 1.5 t/m² y las columnas de 70 × 70 cm con concreto f\'c 350 kg/cm² y acero de refuerzo fy 4,200 kg/cm².</p>' . $img($fotoEstr, '70%')
    . '<p style="text-align:center"><em>Figura 1. Estructura metálica de referencia para la cubierta técnica.</em></p>'
    . '<h2>4. Instalaciones</h2><ul><li>Subestación de 34.5 kV con transformadores de 2,500 kVA y generadores N+1.</li><li>Enfriamiento por chillers enfriados por agua con torres y unidades CRAC de precisión.</li><li>Red hidrosanitaria, cisterna y sistema de contra incendio por rociadores y agente limpio.</li><li>Fibra óptica monomodo, cobre Cat 6A, videovigilancia, control de acceso y DCIM.</li></ul>'
    . '<h2>5. Programa general de obra</h2>' . $tabla(['Partida', 'Descripción', 'Inicio', 'Fin'], $partidasTabla)];
$docs[] = ['Procedimiento constructivo · Colado de losa de sala blanca', 'Calidad y seguridad', -7, 'carta', 'vertical', 'BORRADOR',
    '<h1 style="color:#0f172a">Procedimiento constructivo</h1><h2>Colado de losa técnica de sala blanca</h2>'
    . '<h3>1. Objetivo</h3><p style="text-align:justify">Establecer la secuencia, los controles de calidad y las medidas de seguridad para el colado de la losa de sala blanca de la Nave A con concreto premezclado f\'c 350 kg/cm² y acabado de alta planicidad.</p>'
    . '<h3>2. Responsables</h3>' . $tabla(['Rol', 'Nombre', 'Responsabilidad'], [['Superintendente', 'Óscar Villalpando', 'Programar y dirigir el colado'], ['Calidad', 'Fernanda Quiroz', 'Muestreo, revenimiento y cilindros'], ['Seguridad', 'Raúl Espinoza', 'Permisos de trabajo y áreas de maniobra'], ['Supervisión', 'Valeria Sotomayor', 'Liberar acero y cimbra']])
    . '<h3>3. Secuencia de trabajo</h3><ol><li>Liberación de cimbra, acero e instalaciones ahogadas por supervisión.</li><li>Verificación de nivelación con láser y limpieza de la superficie.</li><li>Colocación de la bomba y de la ruta de tubería.</li><li>Recepción del concreto: remisión, revenimiento (12 ± 2 cm) y toma de cilindros cada 40 m³.</li><li>Colado por franjas, vibrado y regleado con allanadora mecánica.</li><li>Curado durante 7 días y control de planicidad FF/FL.</li></ol>'
    . $img($fotoCampo, '65%')
    . '<h3>4. Controles de calidad</h3>' . $tabla(['Prueba', 'Frecuencia', 'Criterio'], [['Revenimiento', 'Cada camión', '12 ± 2 cm'], ['Cilindros', 'Cada 40 m³', 'f\'c ≥ 350 kg/cm² a 28 días'], ['Planicidad', 'Por franja', 'FF ≥ 50 / FL ≥ 35']])
    . '<h3>5. Seguridad</h3><p style="text-align:justify">Todo el personal usará casco, lentes, guantes y arnés cuando trabaje sobre plataformas. No se inicia el colado sin el permiso de trabajo firmado y la zona de maniobra de la bomba delimitada.</p>'];
$docs[] = ['Acta de inicio de obra', 'Administración', -170, 'carta', 'vertical', '',
    '<h1 style="text-align:center">ACTA DE INICIO DE OBRA</h1><p style="text-align:right">Fecha: ' . $dia(-170) . '</p>'
    . '<p style="text-align:justify">Reunidos en el sitio de la obra «Nexo Data Campus · Centro de datos Tier III», los representantes del cliente y del contratista hacen constar el inicio formal de los trabajos conforme al contrato de obra y a sus anexos.</p>'
    . $tabla(['Concepto', 'Detalle'], [['Modalidad de contratación', 'Precios unitarios'], ['Plazo de ejecución', '420 días naturales'], ['Anticipo', '20 %'], ['Fecha de inicio', $dia(-170)], ['Fecha de terminación', $dia(236)]])
    . '<p style="text-align:justify">El cliente entrega el terreno libre de obstáculos y con los accesos y servicios provisionales. El contratista presenta el programa de obra, el plan de seguridad y el organigrama de la obra.</p><p></p>'
    . $tabla(['Por el cliente', 'Por el contratista', 'Por la supervisión'], [['Arturo Landa', 'Emilio Guzmán', 'Valeria Sotomayor'], ['Firma:', 'Firma:', 'Firma:']])];
$docs[] = ['Informe mensual de avance · Octubre', 'Administración', -1, 'carta', 'vertical', '',
    $portada('INFORME MENSUAL DE AVANCE', 'Corte al ' . $dia(-1))
    . '<h2>1. Resumen ejecutivo</h2><p style="text-align:justify">La obra mantiene el avance de la estructura de la Nave A con el colado de la losa de cubierta técnica en curso. Las desviaciones principales se deben al refuerzo de acero adicional (OC-001), al retraso de la cancelería de fachada y a la compra pendiente de los chillers. Los detalles de cada orden de cambio se consultan en la herramienta correspondiente del proyecto.</p>' . $img($fotoGrua, '70%')
    . '<h2>2. Avance por partida</h2>' . $tabla(['Partida', 'Descripción', 'Avance'], $avanceTabla)
    . '<h2>3. Riesgos y acciones</h2><ul><li><strong>Fachada:</strong> la compra de cancelería está vencida; se escaló con el cliente.</li><li><strong>Enfriamiento:</strong> los chillers tienen 12 semanas de fabricación y deben comprarse esta semana.</li><li><strong>Instalaciones temporales:</strong> hay un retiro de andamio vencido (OC-002).</li></ul>'
    . '<h2>4. Siguientes pasos</h2><ol><li>Terminar la losa de cubierta técnica de la Nave A.</li><li>Iniciar la fachada en cuanto llegue la cancelería.</li><li>Cerrar la OC-003 con el cliente.</li></ol>'];
foreach ($docs as [$nom, $depto, $off, $papel, $orient, $marca, $html]) {
    $d = new \Model\RedactorDocumento(['proyecto_id' => $pid, 'nombre' => $nom, 'fecha' => $dia($off), 'departamento' => $depto, 'papel' => $papel, 'orientacion' => $orient, 'numerar' => 1, 'encabezado' => 1,
        'marca_agua' => $marca, 'encabezado_texto' => '{nombre} · {departamento}', 'pie_texto' => 'Nexo Data Campus · Constructora Meridian', 'contenido' => Redactor::limpiar(preg_replace('/<(h[23])>/', '<$1 style="color:#0f172a">', $html))]);
    $d->guardar();
}

// ---------------------------------------------------------------- Datos de obra (fechas de contrato, responsables, cantidades y contactos)
echo "Datos de obra...\n";
$finProg = $rows("SELECT MAX(a.fecha_fin) f, MIN(a.fecha_inicio) i FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid")[0];
$fechaDe = fn(string $cod, int $off) => ($r = $rows("SELECT a.fecha_fin f FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.codigo = {$str($cod)}")) ? date('Y-m-d', strtotime($r[0]['f'] . " $off days")) : $dia(60);
$contrato = [
    ['inicio', 'Inicio del contrato', $finProg['i'], '', 'Contrato de obra, cláusula 4.1'],
    ['hito', 'Cierre de estructura', $fechaDe('3.6', -9), '0.5 % del contrato por semana de atraso', 'Anexo de programa, hito 3'],
    ['hito', 'Fachada cerrada y hermética', $fechaDe('4.4', -4), '0.25 % del contrato por semana de atraso', 'Anexo de programa, hito 5'],
    ['hito', 'Energización definitiva del campus', $fechaDe('5.4', 20), '', 'Contrato de obra, cláusula 9.2'],
    ['hito', 'Pruebas integrales de sistemas', $fechaDe('10.2', 5), '1 % del contrato por semana de atraso', 'Anexo técnico'],
    ['fin', 'Término del contrato', date('Y-m-d', strtotime($finProg['f'] . ' -5 days')), '$25,000 MXN por día natural de atraso', 'Contrato de obra, cláusula 4.2'],
];
foreach ($contrato as [$tipo, $nombre, $fecha, $pen, $origen]) $q("INSERT INTO proyecto_fechas_contrato (proyecto_id, tipo, nombre, fecha, penalizacion, origen) VALUES ($pid, '$tipo', {$str($nombre)}, {$str($fecha)}, {$str($pen)}, {$str($origen)})");
$contactos = [['Valeria Sotomayor', 'Supervisora de obra', 'Constructora Meridian', '44 2250 0101', 'valeria.sotomayor@ejemplo.mx'], ['Diego Carranza', 'Gerente de ingeniería', 'Constructora Meridian', '44 2250 0102', 'diego.carranza@ejemplo.mx'],
    ['Óscar Villalpando', 'Superintendente de estructura', 'Constructora Meridian', '44 2250 0103', 'oscar.villalpando@ejemplo.mx'], ['Fernanda Quiroz', 'Jefa de calidad', 'Constructora Meridian', '44 2250 0104', 'fernanda.quiroz@ejemplo.mx'],
    ['Raúl Espinoza', 'Coordinador de seguridad', 'Constructora Meridian', '44 2250 0105', 'raul.espinoza@ejemplo.mx'], ['Mónica Aldana', 'Jefa de compras', 'Constructora Meridian', '44 2250 0106', 'monica.aldana@ejemplo.mx'],
    ['Iván Trejo', 'Residente de instalaciones', 'Instalaciones del Centro', '44 2250 0107', 'ivan.trejo@ejemplo.mx'], ['Paola Rentería', 'Ingeniera de proyecto', 'Constructora Meridian', '44 2250 0108', 'paola.renteria@ejemplo.mx']];
foreach ($contactos as [$n, $cargo, $emp, $tel, $mail]) if ($pe = $persona($n)) $q("REPLACE INTO personal_contacto (personal_id, cargo, empresa, telefono, correo) VALUES ($pe, {$str($cargo)}, {$str($emp)}, {$str($tel)}, {$str($mail)})");
$resp = ['3.3' => 'Óscar Villalpando', '3.4' => 'Óscar Villalpando', '3.5' => 'Óscar Villalpando', '4.3' => 'Valeria Sotomayor', '4.4' => 'Valeria Sotomayor', '5.3' => 'Iván Trejo', '5.4' => 'Iván Trejo', '6.3' => 'Iván Trejo',
    '6.4' => 'Fernanda Quiroz', '7.1' => 'Paola Rentería', '8.2' => 'Iván Trejo', '10.2' => 'Diego Carranza'];
foreach ($resp as $cod => $n) if (($a = $act($cod)) && ($pe = $persona($n))) $q("REPLACE INTO actividad_responsables (actividad_id, personal_id) VALUES ($a, $pe)");
$cant = [['3.3', 'EST-3.3', 'Concreto en columnas y losa de sala blanca', 1850, 'm³', 5272500], ['3.4', 'EST-3.4', 'Concreto en losa de cubierta técnica', 1910, 'm³', 5443500], ['4.3', 'ALB-4.3', 'Cancelería y puertas de acceso', 7600, 'm²', 3040000],
    ['4.4', 'FAC-4.4', 'Fachada de cristal templado y sellos', 4300, 'm²', 12470000], ['5.3', 'ELE-5.3', 'Cableado de fuerza y tableros', 18500, 'm', 1850000], ['6.4', 'HID-6.4', 'Red de rociadores y agente limpio', 3200, 'm', 2880000],
    ['7.1', 'HVA-7.1', 'Ductería de lámina galvanizada Nave A', 5200, 'kg', 1196000], ['8.2', 'VOZ-8.2', 'Cableado estructurado Cat 6A', 96000, 'm', 1440000], ['9.2', 'ACA-9.2', 'Piso técnico modular 60x60', 21500, 'm²', 6665000]];
foreach ($cant as [$cod, $clave, $concepto, $cantidad, $uni, $imp]) if ($a = $act($cod)) $q("REPLACE INTO actividad_cantidades (actividad_id, proyecto_id, clave, concepto, cantidad, unidad, importe, origen) VALUES ($a, $pid, {$str($clave)}, {$str($concepto)}, $cantidad, {$str($uni)}, $imp, 'Catálogo de conceptos del contrato')");

// ---------------------------------------------------------------- Símbolos propios del proyecto (se suman a los de seed_demo_extras) y cajetines
echo "Símbolos y cajetines...\n";
$svg = fn(string $cuerpo) => '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 64 64">' . $cuerpo . '</svg>';
$simbolos = [
    ['Extintor', 'Contra incendio', $svg('<rect x="24" y="20" width="16" height="34" rx="6" fill="#dc2626"/><rect x="28" y="12" width="8" height="10" fill="#374151"/><path d="M36 14h10l4 6" stroke="#374151" stroke-width="3" fill="none"/><rect x="26" y="30" width="12" height="8" fill="#fff"/>')],
    ['Botiquín', 'Seguridad', $svg('<rect x="8" y="16" width="48" height="36" rx="6" fill="#16a34a"/><rect x="28" y="22" width="8" height="24" fill="#fff"/><rect x="20" y="30" width="24" height="8" fill="#fff"/>')],
    ['Grúa torre', 'Obra', $svg('<path d="M30 58V10M22 58V10M22 20h8M22 34h8M22 48h8" stroke="#f59e0b" stroke-width="3" fill="none"/><rect x="6" y="8" width="52" height="5" fill="#f59e0b"/><path d="M52 13v18" stroke="#374151" stroke-width="2"/><rect x="48" y="31" width="8" height="6" fill="#374151"/>')],
    ['Acceso de personal', 'Obra', $svg('<rect x="8" y="8" width="48" height="48" rx="8" fill="#0891b2"/><circle cx="32" cy="22" r="6" fill="#fff"/><path d="M20 46c0-9 5-14 12-14s12 5 12 14z" fill="#fff"/>')],
    ['Ruta de evacuación', 'Seguridad', $svg('<rect x="6" y="14" width="52" height="36" rx="5" fill="#16a34a"/><path d="M14 32h26M32 22l12 10-12 10" stroke="#fff" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>')],
    ['Tablero provisional', 'Obra', $svg('<rect x="16" y="8" width="32" height="48" rx="4" fill="#facc15" stroke="#374151" stroke-width="3"/><path d="M34 16L26 32h8l-4 14 12-20h-8z" fill="#374151"/>')],
];
foreach ($simbolos as [$nombre, $cat, $xml]) {
    $tmp = tempnam(sys_get_temp_dir(), 'sim');
    file_put_contents($tmp, $xml);
    $arch = \Model\Simbolo::guardarArchivo($tmp);
    @unlink($tmp);
    if ($arch) $q("INSERT INTO simbolos (proyecto_id, nombre, categoria, archivo) VALUES ($pid, {$str($nombre)}, {$str($cat)}, {$str($arch)})");
    else echo "  ! símbolo no válido: $nombre\n";
}
$cajetinVert = (int)$q("INSERT INTO cajetines (proyecto_id, nombre, orientacion, ancho, filas_json) VALUES ($pid, 'Cajetín de plano · Nexo Data Campus', 'vertical', 30, {$str(json_encode([
    ['Proyecto', '{proyecto}'], ['Cliente', '{cliente}'], ['Plano', '{titulo}'], ['Escala', '1:100'], ['Fecha', '{fecha:corto}'], ['Elaboró', '{autor}'], ['Revisó', 'Diego Carranza'],
    ['Avance al corte', '{avance_real} % de {avance_prog} %'], ['Hoja', '{lamina} de {total}'],
], JSON_UNESCAPED_UNICODE))})");
$q("INSERT INTO cajetines (proyecto_id, nombre, orientacion, ancho, filas_json) VALUES ($pid, 'Cajetín dinámico · Estado de la obra', 'vertical', 30, {$str(json_encode([
    ['Proyecto', '{codigo} · {proyecto}'], ['Corte', '{corte:largo}'], ['Semana de obra', '{semana}'], ['Avance real', '{avance_real} %'], ['SPI', '{spi}'], ['Restricciones activas', '{restr_activas}'],
    ['Órdenes de cambio abiertas', '{oc_abiertas}'], ['Retiros pendientes', '{retiros_pendientes}'], ['Último reporte', '{reporte_ultimo}'],
], JSON_UNESCAPED_UNICODE))})");
$q("INSERT INTO cajetines (proyecto_id, nombre, orientacion, ancho, filas_json) VALUES ($pid, 'Banda de revisión', 'banda', 100, {$str(json_encode([
    ['Revisión', 'B'], ['Estado', 'Para construcción'], ['Aprobó', 'Valeria Sotomayor'], ['Fecha', '{fecha}'],
], JSON_UNESCAPED_UNICODE))})");

// ---------------------------------------------------------------- Normativa y especificaciones (PDF de ejemplo con texto propio, indexados y vinculados)
echo "Normativa...\n";
$pdfNorma = function (array $lineas): string {
    $h = '';
    foreach ($lineas as $l) $h .= '<p style="margin:0 0 7px">' . ($l[0] === '#' ? '<b>' . htmlspecialchars(substr($l, 1)) . '</b>' : htmlspecialchars($l)) . '</p>';
    $d = new \Dompdf\Dompdf();
    $d->loadHtml('<html><body style="font-family:Helvetica,sans-serif;font-size:12px;color:#111">' . $h . '<p style="margin-top:24px;font-size:10px;color:#667">Documento de ejemplo con texto propio para el proyecto de demostración de ProjectWeb. No es una norma oficial.</p></body></html>');
    $d->setPaper('letter');
    $d->render();
    return $d->output();
};
$normas = [
    ['Concreto estructural', 'especificacion', 'CSI 03 30 00', [
        '#SECCIÓN 03 30 00 – CONCRETO ESTRUCTURAL', '#PARTE 1 – GENERALIDADES', '1.1 Alcance',
        'Esta sección cubre el suministro, colocación, curado y control de calidad del concreto estructural de cimentación, columnas y losas técnicas del campus.',
        '1.2 Referencias', 'El concreto cumplirá las normas mexicanas aplicables y los planos estructurales vigentes.', '#PARTE 2 – PRODUCTOS', '2.1 Concreto premezclado',
        'La resistencia especificada es f\'c 350 kg/cm² a los 28 días con revenimiento de 12 ± 2 cm. El proveedor entregará la remisión de cada camión.',
        '2.2 Acero de refuerzo', 'La varilla corrugada tendrá fy 4,200 kg/cm² y certificado de molino. No se aceptan traslapes fuera de los indicados en planos.',
        '#PARTE 3 – EJECUCIÓN', '3.1 Colocación', 'El concreto se colocará dentro de los 90 minutos posteriores a su mezclado, con vibrado interno y sin segregación.',
        '3.2 Muestreo y pruebas', 'Se tomarán cilindros cada 40 m³ de concreto colocado y se probarán a 7 y 28 días. Los resultados se registran en el control de colados.', '3.3 Curado', 'El curado húmedo durará un mínimo de 7 días.']],
    ['Cableado estructurado de comunicaciones', 'especificacion', 'CSI 27 15 00', [
        '#SECCIÓN 27 15 00 – CABLEADO ESTRUCTURADO DE COMUNICACIONES', '#PARTE 1 – GENERALIDADES', '1.1 Alcance',
        'Suministro e instalación del cableado horizontal Cat 6A, la fibra óptica monomodo de la troncal y los gabinetes de comunicaciones de cada sala.',
        '#PARTE 2 – PRODUCTOS', '2.1 Fibra óptica', 'La troncal será de fibra óptica monomodo de 12 hilos con certificación de atenuación en ambas ventanas.',
        '2.2 Gabinetes de comunicaciones', 'Los gabinetes serán de 42U con puesta a tierra, organizadores verticales y ventilación forzada.',
        '#PARTE 3 – EJECUCIÓN', '3.1 Pruebas', 'Cada enlace se certificará con equipo de campo y se entregará el reporte de resultados al supervisor.']],
    ['Fachada y muro cortina', 'especificacion', 'CSI 08 44 00', [
        '#SECCIÓN 08 44 00 – FACHADA Y MURO CORTINA', '#PARTE 1 – GENERALIDADES', '1.1 Alcance',
        'Suministro y montaje de la cancelería de aluminio anodizado con cristal templado de control solar en las fachadas del campus.',
        '1.2 Muestras', 'El contratista presentará una muestra física de perfil, cristal y herrajes para aprobación del cliente antes de fabricar.',
        '#PARTE 2 – PRODUCTOS', '2.1 Perfiles y cristal', 'Perfil de aluminio anodizado y cristal templado de 10 mm con control solar. La garantía será de 10 años contra defectos de fabricación.',
        '#PARTE 3 – EJECUCIÓN', '3.1 Pruebas de hermeticidad', 'Se realizarán pruebas de hermeticidad al agua por muestreo antes de retirar el andamio de cada elevación.']],
    ['Procedimiento de trabajos en altura', 'otro', 'PRO-SEG-01', [
        '#PROCEDIMIENTO PRO-SEG-01 – TRABAJOS EN ALTURA', 'ARTÍCULO 1. Alcance',
        'Aplica a todo trabajo que se realice a más de 1.80 m sobre el nivel del piso, incluidos montaje de acero, cubierta, cancelería y andamios.',
        'ARTÍCULO 2. Permisos', 'Ninguna cuadrilla trabaja en altura sin el permiso firmado por el coordinador de seguridad y vigente para el turno.',
        'ARTÍCULO 3. Equipo de protección', 'Es obligatorio el arnés con doble línea de vida anclada a un punto certificado, casco con barbiquejo y calzado de seguridad.',
        'ARTÍCULO 4. Andamios y redes', 'Los andamios deben contar con plataformas completas, barandales y tarjeta verde de inspección. Las redes de protección se revisan a diario.']],
];
$docNorma = [];
foreach ($normas as [$titulo, $tipo, $codigo, $lineas]) {
    $tmp = sys_get_temp_dir() . '/norma-' . bin2hex(random_bytes(5)) . '.pdf';
    file_put_contents($tmp, $pdfNorma($lineas));
    [$ok, $msg, $docId] = \Classes\Normativa::subir($proyecto, ['tmp_name' => $tmp, 'name' => $titulo . '.pdf'], $titulo, $tipo, $codigo);
    @unlink($tmp);
    if (!$ok) echo "  ! $titulo: $msg\n"; else $docNorma[$codigo] = $docId;
}
$vincular = function (string $codigoDoc, string $buscar, string $tipo, ?int $id, string $requisito, string $origen, string $estado) use ($docNorma, $rows, $str, $q, $pid) {
    $doc = $docNorma[$codigoDoc] ?? null;
    if (!$doc || !$id) return;
    $f = $rows("SELECT id FROM normativa_fragmentos WHERE documento_id = $doc AND (titulo LIKE {$str('%' . $buscar . '%')} OR texto LIKE {$str('%' . $buscar . '%')}) ORDER BY id LIMIT 1")
        ?: $rows("SELECT id FROM normativa_fragmentos WHERE documento_id = $doc ORDER BY id LIMIT 1");
    if (!$f) return;
    $q("INSERT IGNORE INTO normativa_aplicaciones (proyecto_id, documento_id, fragmento_id, entidad_tipo, entidad_id, requisito, origen, estado, puntaje, resuelto_por)
        VALUES ($pid, $doc, " . (int)$f[0]['id'] . ", '$tipo', $id, {$str($requisito)}, '$origen', '$estado', 90, " . ($estado === 'propuesta' ? "''" : "'Valeria Sotomayor'") . ')');
};
$vincular('CSI 03 30 00', 'Concreto premezclado', 'material', $mid('Concreto premezclado'), 'Concreto f\'c 350 kg/cm² a 28 días, revenimiento 12 ± 2 cm y remisión por camión.', 'csi', 'aceptada');
$vincular('CSI 03 30 00', 'Acero de refuerzo', 'material', $mid('Varilla corrugada'), 'Varilla fy 4,200 kg/cm² con certificado de molino y traslapes solo donde lo indican los planos.', 'palabras', 'aceptada');
$vincular('CSI 03 30 00', 'Muestreo y pruebas', 'actividad', $act('3.4'), 'Cilindros cada 40 m³, probados a 7 y 28 días y registrados en el control de colados.', 'palabras', 'aceptada');
$vincular('CSI 03 30 00', 'Curado', 'actividad', $act('3.3'), 'Curado húmedo mínimo de 7 días.', 'palabras', 'propuesta');
$vincular('CSI 27 15 00', 'Fibra óptica', 'material', $mid('Fibra óptica'), 'Fibra monomodo de 12 hilos con certificación de atenuación.', 'csi', 'aceptada');
$vincular('CSI 27 15 00', 'Gabinetes', 'material', $mid('Gabinete de comunicaciones'), 'Gabinetes de 42U con tierra, organizadores y ventilación forzada.', 'csi', 'aceptada');
$vincular('CSI 27 15 00', 'Pruebas', 'actividad', $act('8.2'), 'Certificar cada enlace con equipo de campo y entregar el reporte al supervisor.', 'csi', 'propuesta');
$vincular('CSI 08 44 00', 'Perfiles y cristal', 'material', (int)$canc, 'Aluminio anodizado y cristal templado de 10 mm con control solar; garantía de 10 años.', 'palabras', 'aceptada');
$vincular('CSI 08 44 00', 'Muestras', 'actividad', $act('4.4'), 'Muestra física aprobada por el cliente antes de fabricar.', 'palabras', 'aceptada');
$vincular('PRO-SEG-01', 'Permisos', 'actividad', $act('3.5'), 'Permiso de trabajo en altura firmado y vigente para cada turno.', 'manual', 'aceptada');
$vincular('PRO-SEG-01', 'Andamios', 'actividad', $act('4.4'), 'Andamios con barandales y tarjeta verde; redes revisadas a diario.', 'manual', 'aceptada');

// ---------------------------------------------------------------- vPlan: láminas libres de un paquete propio
echo "vPlan...\n";
$paquete = (int)$q("INSERT INTO vplan_paquetes (proyecto_id, nombre, orden) VALUES ($pid, 'vPlan · Sala blanca y patio de equipos', 1)");
$planoL1 = $guardarImg($plantaR2 ?: $fotoPlanos, 2400);
$planoL2 = $guardarImg($fotoEstr ?: $fotoGrua, 2400);
$planoL3 = $guardarImg($fotoAcero ?: $fotoCampo, 2400);
$laminas = [
    ['SALA BLANCA · PLANTA DE RACKS Y PASILLOS', '3.0', $planoL1, 'Sala blanca', 2, [['3.3'], ['3.4']]],
    ['PATIO DE EQUIPOS · CHILLERS Y GENERADORES', '7.0', $planoL2, 'Enfriamiento', 2, [['7.3', '7.4']]],
    ['CUBIERTA TÉCNICA · DUCTERÍA Y CHAROLAS', '3.0', $planoL3, 'Estructura', 1, [['3.5', '3.6', '8.1']]],
];
$tPunch = (int)$q("INSERT INTO vplan_tablas (proyecto_id, titulo, nombre_interno, tipo) VALUES ($pid, 'PUNCH LIST · SALA BLANCA', 'Demo · Punch list sala blanca', 'punch')");
foreach ([['Corregir nivelación de piso técnico en pasillo frío A-3', -4, 2, 60], ['Resanar y pintar muro cortafuego del eje C', -1, 4, 25], ['Ajustar puertas cortafuego del cuarto de UPS', 0, 6, 0], ['Limpieza y retiro de escombro de la Nave A', 1, 3, 10]] as $i => [$d, $o, $f, $pct])
    $q("INSERT INTO vplan_tabla_filas (tabla_id, orden, datos) VALUES ($tPunch, $i, {$str(json_encode(['descripcion' => $d, 'inicio' => $dia($o), 'fin' => $dia($o + $f), 'pct' => $pct], JSON_UNESCAPED_UNICODE))})");
$tSec = (int)$q("INSERT INTO vplan_tablas (proyecto_id, titulo, nombre_interno, tipo) VALUES ($pid, 'ACTIVIDADES SECUNDARIAS', 'Demo · Secundarias patio de equipos', 'secundarias')");
foreach ([['Anclaje de chillers en plataforma', $dia(40), $dia(46), 'espera'], ['Prueba de estanqueidad de agua helada', $dia(48), $dia(50), 'espera'], ['Montaje de torres de enfriamiento', $dia(30), $dia(36), 'proceso']] as $i => [$e, $f, $r, $est])
    $q("INSERT INTO vplan_tabla_filas (tabla_id, orden, datos) VALUES ($tSec, $i, {$str(json_encode(['elemento' => $e, 'fecha' => $f, 'recovery' => $r, 'estado' => $est], JSON_UNESCAPED_UNICODE))})");
$tRestr = (int)($rows("SELECT id FROM vplan_tablas WHERE proyecto_id = $pid AND tipo = 'restricciones' LIMIT 1")[0]['id'] ?? 0);
$orden = 0;
foreach ($laminas as $i => [$titulo, $codPartida, $plano, $grupo, $recN, $fuentes]) {
    $l = (int)$q("INSERT INTO vplan_laminas (proyecto_id, paquete_id, partida_id, titulo, supervisor, ancho_mm, alto_mm, preset, recovery_n, restr_auto, plano, orden, grupo)
        VALUES ($pid, $paquete, {$partidaId($codPartida)}, {$str($titulo)}, 'Valeria Sotomayor', 432, 279, 'tabloide', $recN, 1, {$str($plano)}, " . (++$orden) . ", {$str($grupo)})");
    foreach (array_merge(...$fuentes) as $cod) if ($a = $act($cod)) $q("INSERT IGNORE INTO vplan_lamina_actividades (lamina_id, actividad_id) VALUES ($l, $a)");
    if ($i < 2) $q("INSERT INTO vplan_lamina_tablas (lamina_id, tabla_id, orden) VALUES ($l, $tPunch, 1)");
    if ($i === 1) $q("INSERT INTO vplan_lamina_tablas (lamina_id, tabla_id, orden) VALUES ($l, $tSec, 2)");
    if ($i === 0 && $tRestr) $q("INSERT IGNORE INTO vplan_lamina_tablas (lamina_id, tabla_id, orden) VALUES ($l, $tRestr, 2)");
}
// Lámina 3: la tabla de colados sale en vivo de SheetFlow
$lam3 = (int)$rows("SELECT id FROM vplan_laminas WHERE paquete_id = $paquete ORDER BY orden DESC LIMIT 1")[0]['id'];
$vt = Sheetflow::aVplan($sfCol);
$q("INSERT IGNORE INTO vplan_lamina_tablas (lamina_id, tabla_id, orden) VALUES ($lam3, " . (int)$vt->id . ', 1)');
$q("UPDATE vplan_tablas SET nombre_interno = 'Demo · Control de colados' WHERE id = " . (int)$vt->id);
foreach ($rows("SELECT id FROM vplan_laminas WHERE paquete_id = $paquete ORDER BY orden") as $l) {
    \Model\VplanLamina::find((int)$l['id'])->bloques();   // arma el diseño libre de bloques (títulos, plano, tareas, tablas, simbología, supervisor…)
}

// Lámina libre con cajetín, texto y plano de localización (bloques colocados a mano, en % de la hoja)
$l4 = (int)$q("INSERT INTO vplan_laminas (proyecto_id, paquete_id, partida_id, titulo, supervisor, ancho_mm, alto_mm, preset, recovery_n, restr_auto, plano, orden, grupo)
    VALUES ($pid, $paquete, NULL, 'PLANO DE LOCALIZACIÓN Y LOGÍSTICA', 'Valeria Sotomayor', 432, 279, 'tabloide', 0, 0, {$str($guardarImg($fotoGrua, 2400))}, 4, 'Obra')");
$cmX = fn(float $cm) => round($cm / 43.2 * 100, 3);
$cmY = fn(float $cm) => round($cm / 27.9 * 100, 3);
$bl = [
    ['logo', 1.0, 0.9, 8.2, 3.0, ['cual' => 'contratista', 'alinear' => 'izquierda']],
    ['titulo', 9.2, 0.9, 24.7, 3.0, []],
    ['logo', 33.9, 0.9, 8.2, 3.0, ['cual' => 'empresa', 'alinear' => 'derecha']],
    ['plano', 1.0, 4.1, 30.5, 21.4, []],
    ['cajetin', 32.2, 4.1, 9.9, 13.5, ['cajetin_id' => $cajetinVert]],
    ['texto', 32.2, 18.2, 9.9, 7.3, ['texto' => "Notas de logística\n• Acceso de camiones por la calle norte.\n• Zona de acopio de acero junto a la grúa.\n• Descarga de equipos solo de 6:00 a 9:00.\n• Punto de reunión en el estacionamiento.", 'tamano' => 10, 'negrita' => false, 'color' => '#0f172a', 'alinear' => 'izquierda']],
    ['supervisor', 1.0, 26.35, 20.0, 0.7, []],
    ['fecha', 21.2, 26.35, 20.9, 0.7, []],
];
foreach ($bl as $z => [$tipo, $x, $y, $w, $h, $cfg]) {
    $b = new \Model\VplanBloque(['lamina_id' => $l4, 'tipo' => $tipo, 'x' => $cmX($x), 'y' => $cmY($y), 'w' => $cmX($w), 'h' => $cmY($h), 'z' => $z]);
    $b->setConfig($cfg);
    $b->guardar();
}

// Estampas inteligentes y lámina «Last Planner del día» (también se puede correr solo: php database/seed_demo_estampas.php)
require __DIR__ . '/seed_demo_estampas.php';

// El demo solo muestra el MSR configurable (el «MSR 1» clásico se quita; seed_demo.php lo vuelve a crear)
$q("DELETE FROM msrs WHERE proyecto_id = $pid AND nombre = 'MSR 1'");

// Evidencias, punch, seguridad, RFI, recursos, costos, mapa de avance, subactividades, campos, carpetas, Unir documentos…
require __DIR__ . '/seed_demo_herramientas.php';

echo "Herramientas nuevas del demo listas.\n";
