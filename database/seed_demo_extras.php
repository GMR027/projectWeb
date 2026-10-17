<?php
/**
 * Datos de ejemplo del proyecto DEMO para las herramientas que no cubre seed_demo.php:
 * dependencias y ruta crítica, Archivo, Editor (gráficos), Símbolos y View360.
 *
 *   php database/seed_demo_extras.php
 *
 * - Solo toca el proyecto con código DEMO: antes de crear borra lo suyo (filas y archivos de esas herramientas) y lo vuelve a crear.
 * - seed_demo.php lo ejecuta al final; también puede correrse solo sobre un demo ya existente.
 * - Las fotos 360 se descargan de Poly Haven (licencia CC0) y se guardan en public/uploads/vplan.
 */

require_once __DIR__ . '/../includes/app.php';

use Classes\Dependencias;
use Classes\Imagenes;
use Dompdf\Dompdf;
use Model\Archivo;
use Model\Grafico;
use Model\GraficoLamina;
use Model\Proyecto;
use Model\Simbolo;

global $db;
$proyecto = Proyecto::where('codigo', 'DEMO');
if (!$proyecto) die("No existe el proyecto DEMO. Ejecuta primero php database/seed_demo.php\n");
$pid = (int)$proyecto->id;
$q = function (string $sql) use ($db) {
    if (!$db->query($sql)) throw new RuntimeException($db->error . "\n" . $sql);
    return $db->insert_id;
};
$str = fn($v) => "'" . $db->real_escape_string((string)$v) . "'";
$hoy = new DateTime('today');
$dia = fn(int $o) => (clone $hoy)->modify("$o days")->format('Y-m-d');

// ---------------------------------------------------------------- limpieza previa (solo lo del demo)
echo "Limpiando extras anteriores del demo...\n";
foreach (Archivo::consultarSQL("SELECT * FROM archivos WHERE proyecto_id = $pid") as $a) @unlink(Archivo::carpeta($pid) . '/' . basename((string)$a->archivo_guardado));
$q("DELETE FROM archivos WHERE proyecto_id = $pid");
$q("DELETE FROM archivo_categorias WHERE proyecto_id = $pid");
foreach (Grafico::consultarSQL("SELECT * FROM editor_graficos WHERE proyecto_id = $pid") as $g) {
    foreach (GraficoLamina::de((int)$g->id) as $l) {
        Imagenes::eliminarVplan($l->marcas_png);
        foreach (Grafico::imagenesDe($l->marcas_json) as $img) Imagenes::eliminarVplan($img);
        foreach (json_decode((string)$l->marcas_json, true)['shapes'] ?? [] as $sh)   // fotos 360 de los hipervínculos
            if (($sh['type'] ?? '') === 'link' && preg_match('#^/uploads/vplan/([a-f0-9]{32}\.(png|jpg))$#', (string)($sh['url'] ?? ''), $m)) Imagenes::eliminarVplan($m[1]);
    }
    Imagenes::eliminarVplan($g->fondo_imagen);
}
$q("DELETE FROM view360_graficos WHERE proyecto_id = $pid");
$q("DELETE FROM editor_graficos WHERE proyecto_id = $pid");   // las láminas se van en cascada
$q("DELETE FROM editor_categorias WHERE proyecto_id = $pid");
foreach (Simbolo::deProyecto($pid) as $s) $s->eliminarArchivo();
$q("DELETE FROM simbolos WHERE proyecto_id = $pid");
$q("DELETE FROM actividad_dependencias WHERE proyecto_id = $pid");

// ---------------------------------------------------------------- dependencias y ruta crítica
echo "Dependencias...\n";
$porNombre = [];
foreach ($db->query("SELECT a.id, a.nombre FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid")->fetch_all(MYSQLI_ASSOC) as $r) $porNombre[$r['nombre']] = (int)$r['id'];
$rel = [   // [predecesora, sucesora, tipo, retraso en días]
    ['Kick off y orden de inicio', 'Permisos de construcción y licencias', 'FS', 0],
    ['Trazo, nivelación y topografía', 'Excavación masiva de sótanos', 'FS', 0],
    ['Instalación de oficinas, bodegas y cerco perimetral', 'Excavación masiva de sótanos', 'FS', -3],
    ['Excavación masiva de sótanos', 'Muro de contención y troquelado', 'SS', 7],
    ['Muro de contención y troquelado', 'Plantilla y acero de zapatas', 'FF', 8],
    ['Plantilla y acero de zapatas', 'Colado de losa de cimentación', 'FS', 0],
    ['Colado de losa de cimentación', 'Muros de sótano y cisterna', 'FS', 0],
    ['Muros de sótano y cisterna', 'Impermeabilización de sótanos', 'SS', 10],
    ['Muros de sótano y cisterna', 'Columnas y losa nivel 1-3', 'FS', 0],
    ['Columnas y losa nivel 1-3', 'Columnas y losa nivel 4-6', 'SS', 28],
    ['Columnas y losa nivel 4-6', 'Columnas y losa nivel 7-9', 'SS', 28],
    ['Columnas y losa nivel 7-9', 'Columnas y losa nivel 10-12', 'SS', 28],
    ['Columnas y losa nivel 10-12', 'Estructura metálica de azotea y helipuerto', 'FS', 0],
    ['Estructura metálica de azotea y helipuerto', 'Hito: Cierre de estructura', 'FS', 3],
    ['Muros de block y tablaroca - sótanos y N1-3', 'Muros de block y tablaroca - N4-6', 'FS', 0],
    ['Muros de block y tablaroca - N4-6', 'Muros de block y tablaroca - N7-12', 'FS', 0],
    ['Columnas y losa nivel 7-9', 'Muros de block y tablaroca - N7-12', 'FS', 0],
    ['Muros de block y tablaroca - N7-12', 'Fachada: cancelería de aluminio y cristal', 'SS', 7],
    ['Canalización eléctrica - sótanos y N1-6', 'Canalización eléctrica - N7-12', 'FS', 0],
    ['Canalización eléctrica - N7-12', 'Cableado y tableros de distribución', 'SS', 21],
    ['Cableado y tableros de distribución', 'Luminarias y contactos', 'FS', 0],
    ['Red hidráulica y sanitaria - N1-6', 'Red hidráulica y sanitaria - N7-12', 'FS', 0],
    ['Red hidráulica y sanitaria - N7-12', 'Sistema de contra incendio (rociadores)', 'SS', 7],
    ['Ductería y soportes - N1-6', 'Ductería y soportes - N7-12', 'FS', 4],
    ['Ductería y soportes - N7-12', 'Montaje de chillers y torres de enfriamiento', 'FS', 0],
    ['Montaje de chillers y torres de enfriamiento', 'Balanceo y puesta en marcha HVAC', 'FS', 0],
    ['Canalización y charolas de comunicaciones', 'Cableado estructurado Cat 6A y fibra óptica', 'FS', 20],
    ['Cableado estructurado Cat 6A y fibra óptica', 'Videovigilancia y control de acceso', 'FS', 10],
    ['Aplanados y pastas - N1-6', 'Pisos y recubrimientos cerámicos', 'FS', 21],
    ['Hito: Cierre de estructura', 'Pisos y recubrimientos cerámicos', 'FS', 0],
    ['Pisos y recubrimientos cerámicos', 'Plafones y pintura', 'SS', 21],
    ['Plafones y pintura', 'Carpintería, puertas y cancelería interior', 'FS', -7],
    ['Balanceo y puesta en marcha HVAC', 'Pruebas integrales de sistemas', 'FS', 0],
    ['Carpintería, puertas y cancelería interior', 'Pruebas integrales de sistemas', 'FS', 0],
    ['Pruebas integrales de sistemas', 'Limpieza fina y detalles (punch list)', 'FS', 3],
    ['Limpieza fina y detalles (punch list)', 'Entrega y acta de recepción', 'FS', 0],
];
foreach ($rel as [$a, $b, $tipo, $ret]) {
    if (!isset($porNombre[$a], $porNombre[$b])) { echo "  ! actividad no encontrada: $a / $b\n"; continue; }
    [$ok, $msg] = Dependencias::guardar($pid, $porNombre[$a], $porNombre[$b], $tipo, $ret);
    if (!$ok) echo "  ! $a → $b: $msg\n";
}
$q("UPDATE proyectos SET modo_dependencias = 'informativo' WHERE id = $pid");
$proyecto->modo_dependencias = 'informativo';

// Ajusta cada retraso para que las relaciones no marquen conflicto con las fechas del programa: las de la ruta principal quedan
// ajustadas al día (sin holgura, así salen críticas) y el resto con unos días de holgura
$principal = ['Excavación masiva de sótanos', 'Muro de contención y troquelado', 'Plantilla y acero de zapatas', 'Colado de losa de cimentación', 'Muros de sótano y cisterna',
    'Columnas y losa nivel 1-3', 'Columnas y losa nivel 4-6', 'Columnas y losa nivel 7-9', 'Columnas y losa nivel 10-12', 'Estructura metálica de azotea y helipuerto',
    'Hito: Cierre de estructura', 'Pisos y recubrimientos cerámicos', 'Plafones y pintura', 'Carpintería, puertas y cancelería interior',
    'Pruebas integrales de sistemas', 'Limpieza fina y detalles (punch list)', 'Entrega y acta de recepción'];
foreach (Dependencias::relaciones($pid) as $r) {
    $suc = array_search((int)$r['suc'], $porNombre, true);
    $pred = array_search((int)$r['pred'], $porNombre, true);
    $apretada = in_array($suc, $principal, true) && in_array($pred, $principal, true);
    $sinConflicto = function (int $ret) use ($db, $r, $proyecto): bool {
        $db->query("UPDATE actividad_dependencias SET retraso = $ret WHERE id = " . (int)$r['id']);
        foreach (Dependencias::datos($proyecto)['relaciones'] as $x) if ((int)$x['id'] === (int)$r['id']) return !$x['conflicto'];
        return false;
    };
    [$lo, $hi, $mejor] = [-120, 120, null];   // mayor retraso sin conflicto (búsqueda binaria: más retraso, más conflicto)
    while ($lo <= $hi) {
        $mid = intdiv($lo + $hi, 2);
        if ($sinConflicto($mid)) { $mejor = $mid; $lo = $mid + 1; } else $hi = $mid - 1;
    }
    if ($mejor !== null && !$apretada) $mejor = $mejor - 4;
    if ($mejor !== null) $db->query("UPDATE actividad_dependencias SET retraso = $mejor WHERE id = " . (int)$r['id']);
}
$res = Dependencias::datos($proyecto)['resumen'];
echo "  relaciones {$res['relaciones']} · críticas {$res['criticas']} · con conflicto {$res['conflictos']}\n";

// ---------------------------------------------------------------- Archivo
echo "Archivo...\n";
$catArch = [];
foreach (['Planos', 'Especificaciones', 'Contratos y administrativos'] as $c) $catArch[$c] = (int)$q("INSERT INTO archivo_categorias (proyecto_id, nombre) VALUES ($pid, {$str($c)})");
$pdf = function (string $titulo, string $rev, array $filas) use ($proyecto): string {
    $h = '';
    foreach ($filas as [$a, $b]) $h .= '<tr><td>' . htmlspecialchars($a) . '</td><td>' . htmlspecialchars($b) . '</td></tr>';
    $html = '<html><body style="font-family:Helvetica,sans-serif;color:#1c2541"><h1 style="color:#ff5a1f;margin:0">' . htmlspecialchars($titulo) . '</h1>'
        . '<p style="margin:4px 0 18px">' . htmlspecialchars($proyecto->nombre) . ' · Revisión ' . htmlspecialchars($rev) . ' · Documento de ejemplo</p>'
        . '<table border="1" cellspacing="0" cellpadding="8" width="100%" style="border-collapse:collapse;font-size:13px">' . $h . '</table>'
        . '<p style="margin-top:30px;font-size:11px;color:#667">Archivo generado para el proyecto de demostración de ProjectWeb.</p></body></html>';
    $d = new Dompdf();
    $d->loadHtml($html);
    $d->setPaper('letter');
    $d->render();
    return $d->output();
};
$docs = [
    ['Planos', 'Plano estructural · Niveles 1 a 6', 'R2', -60, [['Elemento', 'Especificación'], ['Columnas', 'Sección 80×80 cm, f\'c 350 kg/cm²'], ['Losas', 'Losa reticular h = 35 cm'], ['Acero', 'fy 4,200 kg/cm²']]],
    ['Planos', 'Plano arquitectónico · Planta tipo', 'R1', -45, [['Zona', 'Área'], ['Oficinas abiertas', '620 m²'], ['Sala de juntas', '48 m²'], ['Núcleo de servicios', '110 m²']]],
    ['Planos', 'Plano de instalaciones eléctricas · Sótano 1', 'R3', -20, [['Circuito', 'Descripción'], ['C-01', 'Alumbrado de estacionamiento'], ['C-02', 'Fuerza bombas de achique'], ['C-03', 'Tablero general TG-1']]],
    ['Especificaciones', 'Especificación de concreto estructural', 'R0', -75, [['Parámetro', 'Valor'], ['Resistencia', 'f\'c 350 kg/cm² a 28 días'], ['Revenimiento', '12 ± 2 cm'], ['Pruebas', 'Cilindros cada 40 m³']]],
    ['Especificaciones', 'Especificación de cancelería de fachada', 'R1', -30, [['Parámetro', 'Valor'], ['Perfil', 'Aluminio anodizado'], ['Vidrio', 'Templado 10 mm con control solar'], ['Garantía', '10 años']]],
    ['Contratos y administrativos', 'Contrato de obra y anexos', 'Firmado', -120, [['Concepto', 'Detalle'], ['Modalidad', 'Precios unitarios'], ['Plazo', '406 días naturales'], ['Anticipo', '20 %']]],
];
@mkdir(Archivo::carpeta($pid), 0775, true);
foreach ($docs as [$cat, $nombre, $rev, $off, $filas]) {
    $guardado = bin2hex(random_bytes(12)) . '.pdf';
    $bin = $pdf($nombre, $rev, $filas);
    file_put_contents(Archivo::carpeta($pid) . '/' . $guardado, $bin);
    $original = preg_replace('/[^A-Za-z0-9_-]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre)) . '.pdf';
    $q("INSERT INTO archivos (proyecto_id, categoria_id, fecha, nombre, numero_revision, archivo_original, archivo_guardado, tamano)
        VALUES ($pid, {$catArch[$cat]}, {$str($dia($off))}, {$str($nombre)}, {$str($rev)}, {$str($original)}, {$str($guardado)}, " . strlen($bin) . ")");
}

// ---------------------------------------------------------------- lienzos (JSON del editor + PNG compuesto con GD)
$fuente = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
$hex = function ($im, string $c, int $alfa = 0) {
    $c = ltrim($c, '#');
    return imagecolorallocatealpha($im, hexdec(substr($c, 0, 2)), hexdec(substr($c, 2, 2)), hexdec(substr($c, 4, 2)), $alfa);
};
$sid = 0;
$forma = function (array $d) use (&$sid): array { return ['id' => 'd' . (++$sid)] + $d + ['opacity' => 100, 'rotation' => 0]; };

/** Dibuja las formas en $im (misma geometría que el JSON): rect, ellipse, text, line, arrow, link */
$dibujar = function ($im, array $formas) use ($hex, $fuente): void {
    foreach ($formas as $s) {
        $t = $s['type'];
        if ($t === 'rect' || $t === 'zone') {
            if (!empty($s['fill'])) imagefilledrectangle($im, (int)$s['x'], (int)$s['y'], (int)($s['x'] + $s['w']), (int)($s['y'] + $s['h']), $hex($im, $s['fill'], (int)round(127 * (1 - ($s['fillAlpha'] ?? 100) / 100))));
            if (!empty($s['stroke']) && ($s['strokeWidth'] ?? 0) > 0) {
                imagesetthickness($im, (int)$s['strokeWidth']);
                imagerectangle($im, (int)$s['x'], (int)$s['y'], (int)($s['x'] + $s['w']), (int)($s['y'] + $s['h']), $hex($im, $s['stroke']));
                imagesetthickness($im, 1);
            }
        } elseif ($t === 'ellipse') {
            if (!empty($s['fill'])) imagefilledellipse($im, (int)$s['x'], (int)$s['y'], (int)($s['rx'] * 2), (int)($s['ry'] * 2), $hex($im, $s['fill'], (int)round(127 * (1 - ($s['fillAlpha'] ?? 100) / 100))));
            if (!empty($s['stroke'])) { imagesetthickness($im, (int)($s['strokeWidth'] ?? 2)); imageellipse($im, (int)$s['x'], (int)$s['y'], (int)($s['rx'] * 2), (int)($s['ry'] * 2), $hex($im, $s['stroke'])); imagesetthickness($im, 1); }
        } elseif ($t === 'line' || $t === 'arrow') {
            [$x0, $y0, $x1, $y1] = [$s['x'] + $s['points'][0], $s['y'] + $s['points'][1], $s['x'] + $s['points'][2], $s['y'] + $s['points'][3]];
            $c = $hex($im, $s['stroke']);
            imagesetthickness($im, (int)($s['strokeWidth'] ?? 4));
            imageline($im, (int)$x0, (int)$y0, (int)$x1, (int)$y1, $c);
            if ($t === 'arrow') {
                $a = atan2($y1 - $y0, $x1 - $x0); $L = 26;
                imagefilledpolygon($im, [(int)$x1, (int)$y1, (int)($x1 - $L * cos($a - .45)), (int)($y1 - $L * sin($a - .45)), (int)($x1 - $L * cos($a + .45)), (int)($y1 - $L * sin($a + .45))], $c);
            }
            imagesetthickness($im, 1);
        } elseif ($t === 'text') {
            $bb = imagettfbbox((int)($s['fontSize'] * .75), 0, $fuente, $s['text']);
            imagettftext($im, $s['fontSize'] * .75, 0, (int)$s['x'], (int)($s['y'] + abs($bb[7]) + 2), $hex($im, $s['fill']), $fuente, $s['text']);
        } elseif ($t === 'link') {   // marcador visible del hipervínculo (el sistema dibuja encima el icono interactivo)
            imagefilledellipse($im, (int)($s['x'] + $s['w'] / 2), (int)($s['y'] + $s['h'] / 2), 10, 10, $hex($im, '#e65100'));
        }
    }
};
$guardarPng = function ($im): string {
    if (!is_dir(Imagenes::CARPETA_VPLAN)) mkdir(Imagenes::CARPETA_VPLAN, 0775, true);
    $n = md5(uniqid('', true)) . '.png';
    imagepng($im, Imagenes::CARPETA_VPLAN . '/' . $n, 6);
    return $n;
};
$crearGrafico = function (string $nombre, ?int $cat, int $w, int $h, string $fondo, ?string $fondoImg, array $laminas) use ($pid, $guardarPng): int {
    $g = new Grafico(['proyecto_id' => $pid, 'nombre' => $nombre, 'categoria_id' => $cat, 'ancho' => $w, 'alto' => $h, 'fondo' => $fondo, 'fondo_color' => '#ffffff',
        'fondo_imagen' => $fondoImg, 'fondo_config' => $fondoImg ? json_encode(Grafico::limpiarConfigFondo(['modo' => 'estirar'])) : null]);
    $g->id = $g->guardar()['id'];
    foreach ($laminas as $i => [$titulo, $json, $im]) {
        (new GraficoLamina(['grafico_id' => $g->id, 'orden' => $i + 1, 'nombre' => $titulo, 'marcas_json' => json_encode($json, JSON_UNESCAPED_UNICODE), 'marcas_png' => $guardarPng($im)]))->guardar();
    }
    return (int)$g->id;
};
$nuevoLienzo = function (int $w, int $h, string $color) use ($hex) {
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
    imagefilledrectangle($im, 0, 0, $w, $h, $hex($im, $color));
    return $im;
};
$json = fn(int $w, int $h, array $formas) => ['v' => 2, 'w' => $w, 'h' => $h, 'shapes' => $formas, 'layers' => [], 'escala' => null];
$T = fn($x, $y, $txt, $tam = 40, $c = '#ffffff') => ['type' => 'text', 'x' => $x, 'y' => $y, 'text' => $txt, 'fontSize' => $tam, 'fill' => $c, 'bold' => true];

// ---------------------------------------------------------------- Editor (gráficos)
echo "Editor...\n";
$catEd = [];
foreach (['Logística de obra', 'Programa'] as $c) $catEd[$c] = (int)$q("INSERT INTO editor_categorias (proyecto_id, nombre) VALUES ($pid, {$str($c)})");

// 1) Logística: plano azul con terreno, edificio, grúa y accesos
[$W, $H] = [1920, 1080];
$im = $nuevoLienzo($W, $H, '#16365f');
$rej = $hex($im, '#2b5287');
for ($x = 0; $x <= $W; $x += 60) imageline($im, $x, 0, $x, $H, $rej);
for ($y = 0; $y <= $H; $y += 60) imageline($im, 0, $y, $W, $y, $rej);
$f1 = array_map($forma, [
    ['type' => 'rect', 'x' => 160, 'y' => 150, 'w' => 1600, 'h' => 800, 'fill' => '', 'stroke' => '#ffffff', 'strokeWidth' => 6, 'dash' => 'dash'],
    ['type' => 'rect', 'x' => 620, 'y' => 330, 'w' => 640, 'h' => 420, 'fill' => '#ff5a1f', 'fillAlpha' => 60, 'stroke' => '#ffffff', 'strokeWidth' => 5],
    ['type' => 'ellipse', 'x' => 940, 'y' => 540, 'rx' => 330, 'ry' => 330, 'fill' => '#00b4d8', 'fillAlpha' => 14, 'stroke' => '#00b4d8', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 220, 'y' => 200, 'w' => 260, 'h' => 130, 'fill' => '#8b5cf6', 'fillAlpha' => 70, 'stroke' => '#ffffff', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 220, 'y' => 360, 'w' => 260, 'h' => 130, 'fill' => '#10b981', 'fillAlpha' => 70, 'stroke' => '#ffffff', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 1380, 'y' => 620, 'w' => 330, 'h' => 260, 'fill' => '#f59e0b', 'fillAlpha' => 65, 'stroke' => '#ffffff', 'strokeWidth' => 4],
    ['type' => 'arrow', 'x' => 40, 'y' => 800, 'points' => [0, 0, 360, 0], 'stroke' => '#ffeb00', 'strokeWidth' => 10],
    ['type' => 'arrow', 'x' => 1880, 'y' => 300, 'points' => [0, 0, -260, 0], 'stroke' => '#ffeb00', 'strokeWidth' => 10],
    $T(660, 500, 'TORRE AURORA', 56), $T(250, 240, 'Oficinas', 38), $T(250, 400, 'Bodega', 38), $T(1410, 730, 'Zona de acopio', 38),
    $T(560, 180, 'Grúa torre · radio 45 m', 38, '#7fe3f5'), $T(50, 840, 'Acceso vehicular', 34, '#ffeb00'), $T(1470, 240, 'Acceso peatonal', 34, '#ffeb00'),
    $T(70, 30, 'LOGÍSTICA Y ACCESOS DE OBRA', 62), $T(70, 100, 'Vista en planta · cerco perimetral', 34, '#9fc0ea'),
]);
$dibujar($im, $f1);
$crearGrafico('Logística y accesos de obra', $catEd['Logística de obra'], $W, $H, 'plano-azul', null, [['Planta de logística', $json($W, $H, $f1), $im]]);

// 2) Programa: diagrama de flujo de la ruta crítica de estructura (2 láminas)
$im = $nuevoLienzo($W, $H, '#ffffff');
$pasos = [['Excavación', '#ff5a1f'], ['Cimentación', '#f59e0b'], ['Estructura N1-6', '#10b981'], ['Estructura N7-12', '#00b4d8'], ['Cierre de estructura', '#8b5cf6']];
$f2 = [];
foreach ($pasos as $i => [$n, $c]) {
    $x = 70 + $i * 365;
    $f2[] = ['type' => 'rect', 'x' => $x, 'y' => 440, 'w' => 290, 'h' => 150, 'fill' => $c, 'fillAlpha' => 100, 'stroke' => '#1c2541', 'strokeWidth' => 4];
    $f2[] = $T($x + 20, 495, $n, 30, '#ffffff');
    if ($i < 4) $f2[] = ['type' => 'arrow', 'x' => $x + 295, 'y' => 515, 'points' => [0, 0, 60, 0], 'stroke' => '#1c2541', 'strokeWidth' => 6];
}
$f2[] = $T(70, 60, 'RUTA CRÍTICA · ESTRUCTURA', 62, '#1c2541');
$f2[] = $T(70, 130, 'Secuencia de actividades que no pueden atrasarse', 34, '#667085');
$f2[] = ['type' => 'rect', 'x' => 70, 'y' => 700, 'w' => 1780, 'h' => 6, 'fill' => '#ff5a1f', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0];
$f2[] = $T(70, 740, 'Cualquier retraso en estas actividades mueve la fecha de entrega.', 34, '#1c2541');
$f2 = array_map($forma, $f2);
$dibujar($im, $f2);
$im2 = $nuevoLienzo($W, $H, '#ffffff');
$f2b = array_map($forma, [
    $T(70, 60, 'SECUENCIA DE COLADOS POR NIVEL', 62, '#1c2541'),
    ['type' => 'rect', 'x' => 120, 'y' => 760, 'w' => 1680, 'h' => 80, 'fill' => '#1c2541', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0],
    ['type' => 'rect', 'x' => 200, 'y' => 600, 'w' => 1520, 'h' => 80, 'fill' => '#10b981', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0],
    ['type' => 'rect', 'x' => 320, 'y' => 440, 'w' => 1280, 'h' => 80, 'fill' => '#00b4d8', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0],
    ['type' => 'rect', 'x' => 440, 'y' => 280, 'w' => 1040, 'h' => 80, 'fill' => '#ff5a1f', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0],
    $T(140, 780, 'Cimentación y sótanos', 34, '#ffffff'), $T(220, 620, 'Niveles 1 a 6', 34, '#ffffff'), $T(340, 460, 'Niveles 7 a 9', 34, '#ffffff'), $T(460, 300, 'Niveles 10 a 12 (en proceso)', 34, '#ffffff'),
]);
$dibujar($im2, $f2b);
$crearGrafico('Ruta crítica de estructura', $catEd['Programa'], $W, $H, 'liso', null, [['Secuencia', $json($W, $H, $f2), $im], ['Colados por nivel', $json($W, $H, $f2b), $im2]]);

// ---------------------------------------------------------------- Símbolos propios del proyecto
echo "Símbolos...\n";
$svgs = [
    ['Hidrante', 'Contra incendio', '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 64 64"><rect x="22" y="22" width="20" height="30" rx="6" fill="#dc2626"/><rect x="26" y="12" width="12" height="10" rx="3" fill="#b91c1c"/><rect x="14" y="30" width="8" height="8" fill="#374151"/><rect x="42" y="30" width="8" height="8" fill="#374151"/><rect x="18" y="52" width="28" height="6" fill="#374151"/></svg>'],
    ['Andamio', 'Obra', '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 64 64"><g stroke="#f59e0b" stroke-width="4" fill="none"><path d="M12 8v50M52 8v50M12 20h40M12 36h40M12 52h40M12 20l40 16M12 36l40 16"/></g></svg>'],
    ['Zona de acopio', 'Obra', '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 64 64"><rect x="8" y="14" width="48" height="40" rx="4" fill="#fde68a" stroke="#d97706" stroke-width="3"/><rect x="16" y="24" width="14" height="10" fill="#d97706"/><rect x="34" y="24" width="14" height="10" fill="#d97706"/><rect x="16" y="38" width="14" height="10" fill="#d97706"/><rect x="34" y="38" width="14" height="10" fill="#d97706"/></svg>'],
    ['Punto de reunión', 'Seguridad', '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 64 64"><rect x="6" y="6" width="52" height="52" rx="8" fill="#16a34a"/><circle cx="32" cy="22" r="6" fill="#fff"/><path d="M20 46c0-9 5-14 12-14s12 5 12 14z" fill="#fff"/></svg>'],
];
foreach ($svgs as [$nombre, $cat, $svg]) {
    $tmp = tempnam(sys_get_temp_dir(), 'sim');
    file_put_contents($tmp, $svg);
    $arch = Simbolo::guardarArchivo($tmp);
    @unlink($tmp);
    if ($arch) $q("INSERT INTO simbolos (proyecto_id, nombre, categoria, archivo) VALUES ($pid, {$str($nombre)}, {$str($cat)}, {$str($arch)})");
    else echo "  ! símbolo no válido: $nombre\n";
}

// ---------------------------------------------------------------- View360
echo "View360 (descarga de fotos 360)...\n";
$cache = sys_get_temp_dir() . '/projectweb_demo_img';
@mkdir($cache, 0775, true);
$pano = function (string $id) use ($cache): ?string {   // Poly Haven (CC0): se reduce a 4096×2048
    $f = "$cache/pano-$id.jpg";
    if (!is_file($f) || filesize($f) < 50000) {
        $datos = @file_get_contents("https://dl.polyhaven.org/file/ph-assets/HDRIs/extra/Tonemapped%20JPG/$id.jpg", false, stream_context_create(['http' => ['timeout' => 180]]));
        if (!$datos) { echo "  ! no se pudo descargar $id\n"; return null; }
        $o = imagecreatefromstring($datos);
        if (!$o) return null;
        $r = imagescale($o, 4096, 2048, IMG_BICUBIC);
        imagejpeg($r, $f, 82);
    }
    $n = md5(uniqid('', true)) . '.jpg';
    if (!is_dir(Imagenes::CARPETA_VPLAN)) mkdir(Imagenes::CARPETA_VPLAN, 0775, true);
    copy($f, Imagenes::CARPETA_VPLAN . '/' . $n);
    return '/uploads/vplan/' . $n;
};
$enlace = fn(float $x, float $y, string $url, string $etq) => ['type' => 'link', 'x' => $x, 'y' => $y, 'w' => 90, 'h' => 90, 'kind' => '360', 'url' => $url, 'label' => $etq, 'icon' => 'globe', 'iconColor' => '#e65100', 'showIcon' => false];
$ver360 = [];   // [nombre, versión, notas[], fondoGD, formas(sin enlaces), [[x,y,id foto,etiqueta]]]

// A) Planta del nivel 7 (croquis de oficinas)
[$W, $H] = [1920, 1080];
$im = $nuevoLienzo($W, $H, '#f4f6fb');
$a = array_map($forma, [
    ['type' => 'rect', 'x' => 120, 'y' => 170, 'w' => 1680, 'h' => 790, 'fill' => '#ffffff', 'fillAlpha' => 100, 'stroke' => '#1c2541', 'strokeWidth' => 8],
    ['type' => 'rect', 'x' => 120, 'y' => 170, 'w' => 760, 'h' => 400, 'fill' => '#dbe5f1', 'fillAlpha' => 100, 'stroke' => '#1c2541', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 880, 'y' => 170, 'w' => 920, 'h' => 400, 'fill' => '#e8f5ee', 'fillAlpha' => 100, 'stroke' => '#1c2541', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 120, 'y' => 570, 'w' => 400, 'h' => 390, 'fill' => '#fdf0d5', 'fillAlpha' => 100, 'stroke' => '#1c2541', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 520, 'y' => 570, 'w' => 1280, 'h' => 390, 'fill' => '#eef0f7', 'fillAlpha' => 100, 'stroke' => '#1c2541', 'strokeWidth' => 4],
    $T(160, 200, 'Oficinas abiertas', 40, '#1c2541'), $T(920, 200, 'Sala de juntas', 40, '#1c2541'), $T(150, 600, 'Servicios', 40, '#1c2541'), $T(560, 600, 'Zona de columnas · colado N7', 40, '#1c2541'),
    $T(70, 30, 'NIVEL 7 · RECORRIDO VIRTUAL', 62, '#1c2541'), $T(70, 100, 'Toca los puntos naranja para ver la foto 360° de cada zona', 32, '#667085'),
]);
$dibujar($im, $a);
$ver360[] = ['Recorrido virtual · Nivel 7', 'v1.0', ['Armado de acero de columnas C-12 a C-18 revisado y liberado por supervisión.', 'Pendiente retirar material sobrante de la zona de servicios antes del colado del lunes.'], $im, $a, [[420, 330, 'abandoned_construction', 'Nivel 7 · estructura'], [1300, 330, 'boiler_room', 'Cuarto de máquinas']]];

// B) Fachada y exteriores
$im = $nuevoLienzo($W, $H, '#0f1b36');
$b = [];
for ($i = 0; $i < 12; $i++) {
    $b[] = ['type' => 'rect', 'x' => 620, 'y' => 880 - ($i + 1) * 62, 'w' => 680, 'h' => 56, 'fill' => $i < 9 ? '#00b4d8' : '#ff5a1f', 'fillAlpha' => $i < 9 ? 70 : 85, 'stroke' => '#ffffff', 'strokeWidth' => 2];
}
$b[] = ['type' => 'rect', 'x' => 380, 'y' => 880, 'w' => 1160, 'h' => 36, 'fill' => '#374151', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0];
$b[] = $T(70, 30, 'FACHADA Y EXTERIORES', 62, '#ffffff');
$b[] = $T(70, 100, 'Elevación esquemática · niveles terminados en azul', 32, '#9fc0ea');
$b = array_map($forma, $b);
$dibujar($im, $b);
$ver360[] = ['Fachada y exteriores', 'v1.0', ['Vista exterior del avance de estructura; la cancelería de fachada inicia el 26 de octubre.'], $im, $b, [[1380, 480, 'canary_wharf', 'Vista exterior']]];

foreach ($ver360 as $k => [$nombre, $version, $notas, $im, $formas, $puntos]) {
    $links = [];
    foreach ($puntos as [$x, $y, $idPano, $etq]) {
        $url = $pano($idPano);
        if ($url) $links[] = $forma($enlace($x, $y, $url, $etq));
    }
    $fondoGuardado = $guardarPng($im);   // el plano base sirve de fondo del lienzo y de imagen de la lámina
    $todas = array_merge($formas, $links);
    $dibujar($im, $links);
    $lamina = $guardarPng($im);
    $g = new Grafico(['proyecto_id' => $pid, 'nombre' => $nombre, 'ancho' => $W, 'alto' => $H, 'fondo' => 'imagen', 'fondo_color' => '#ffffff', 'fondo_imagen' => $fondoGuardado,
        'fondo_config' => json_encode(Grafico::limpiarConfigFondo(['modo' => 'estirar']))]);
    $g->id = $g->guardar()['id'];
    (new GraficoLamina(['grafico_id' => $g->id, 'orden' => 1, 'nombre' => '', 'marcas_json' => json_encode($json($W, $H, $todas), JSON_UNESCAPED_UNICODE), 'marcas_png' => $lamina]))->guardar();
    $vid = (int)$q("INSERT INTO view360_graficos (proyecto_id, fecha, nombre, version, grafico_id) VALUES ($pid, {$str($dia(-3 * ($k + 1)))}, {$str($nombre)}, {$str($version)}, {$g->id})");
    foreach ($notas as $n => $texto) $q("INSERT INTO view360_notas (view360_id, descripcion, fecha) VALUES ($vid, {$str($texto)}, {$str($dia(-2 - $n) . ' 10:30:00')})");
}

echo "Extras del demo listos.\n";
