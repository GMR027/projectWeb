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
    ['Kick off y orden de inicio', 'Permisos, impacto ambiental y factibilidad eléctrica', 'FS', 0],
    ['Desmonte, despalme y trazo del campus', 'Movimiento de tierras y plataforma', 'FS', 0],
    ['Movimiento de tierras y plataforma', 'Excavación y mejoramiento de terreno', 'FS', -3],
    ['Excavación y mejoramiento de terreno', 'Plantilla, acero y zapatas aisladas', 'SS', 7],
    ['Plantilla, acero y zapatas aisladas', 'Dados y anclajes de columnas', 'FF', 8],
    ['Dados y anclajes de columnas', 'Losa de cimentación de sala blanca', 'FS', 0],
    ['Losa de cimentación de sala blanca', 'Cimentación de equipos: chillers y generadores', 'FS', 0],
    ['Cimentación de equipos: chillers y generadores', 'Impermeabilización y drenaje perimetral', 'SS', 10],
    ['Losa de cimentación de sala blanca', 'Columnas y trabes - Nave A', 'FS', 0],
    ['Columnas y trabes - Nave A', 'Columnas y trabes - Nave B', 'SS', 28],
    ['Columnas y trabes - Nave B', 'Losa de sala blanca y mezzanine', 'SS', 28],
    ['Losa de sala blanca y mezzanine', 'Losa de cubierta técnica', 'SS', 21],
    ['Losa de cubierta técnica', 'Estructura metálica de cubierta y dunnage', 'FS', 0],
    ['Estructura metálica de cubierta y dunnage', 'Hito: Cierre de estructura', 'FS', 3],
    ['Muros prefabricados y cubierta termoacústica', 'Fachada de acceso y muro cortina', 'FS', 0],
    ['Fachada de acceso y muro cortina', 'Cancelería y puertas de acceso', 'FS', 0],
    ['Columnas y trabes - Nave A', 'Cancelería y puertas de acceso', 'FS', 0],
    ['Cancelería y puertas de acceso', 'Fachada: cristal templado y sellos', 'SS', 7],
    ['Canalización eléctrica - Naves A y B', 'Cableado de fuerza y tableros', 'FS', 0],
    ['Cableado de fuerza y tableros', 'Tableros de distribución y UPS', 'SS', 21],
    ['Tableros de distribución y UPS', 'Luminarias y contactos', 'FS', 0],
    ['Red hidráulica y sanitaria - servicios', 'Red hidráulica - salas técnicas', 'FS', 0],
    ['Red hidráulica - salas técnicas', 'Rociadores y agente limpio', 'SS', 7],
    ['Ductería y soportes - Nave A', 'Ductería y soportes - Nave B', 'FS', 4],
    ['Ductería y soportes - Nave B', 'Montaje de chillers y torres de enfriamiento', 'FS', 0],
    ['Montaje de chillers y torres de enfriamiento', 'Unidades CRAC y difusores de precisión', 'FS', 0],
    ['Montaje de chillers y torres de enfriamiento', 'Balanceo y puesta en marcha HVAC', 'FS', 0],
    ['Canalización y charolas de comunicaciones', 'Cableado estructurado Cat 6A y fibra óptica', 'FS', 20],
    ['Cableado estructurado Cat 6A y fibra óptica', 'Videovigilancia y control de acceso', 'FS', 10],
    ['Aplanados y pintura epóxica', 'Pisos técnicos y recubrimientos', 'FS', 21],
    ['Hito: Cierre de estructura', 'Pisos técnicos y recubrimientos', 'FS', 0],
    ['Pisos técnicos y recubrimientos', 'Plafones y pintura', 'SS', 21],
    ['Plafones y pintura', 'Puertas cortafuego y cancelería interior', 'FS', -7],
    ['Balanceo y puesta en marcha HVAC', 'Pruebas integrales de sistemas y comisionamiento', 'FS', 0],
    ['Puertas cortafuego y cancelería interior', 'Pruebas integrales de sistemas y comisionamiento', 'FS', 0],
    ['Generadores de respaldo y planta de emergencia', 'Pruebas integrales de sistemas y comisionamiento', 'FS', 0],
    ['Racks, PDUs y mobiliario técnico', 'Pruebas de carga y redundancia N+1', 'FS', 0],
    ['Pruebas de carga y redundancia N+1', 'Pruebas integrales de sistemas y comisionamiento', 'FS', 0],
    ['Pruebas integrales de sistemas y comisionamiento', 'Limpieza fina y detalles (punch list)', 'FS', 3],
    ['Limpieza fina y detalles (punch list)', 'Hito: Entrega y certificación Tier III', 'FS', 0],
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
$principal = ['Excavación y mejoramiento de terreno', 'Plantilla, acero y zapatas aisladas', 'Dados y anclajes de columnas', 'Losa de cimentación de sala blanca',
    'Columnas y trabes - Nave A', 'Columnas y trabes - Nave B', 'Losa de sala blanca y mezzanine', 'Losa de cubierta técnica', 'Estructura metálica de cubierta y dunnage',
    'Hito: Cierre de estructura', 'Pisos técnicos y recubrimientos', 'Plafones y pintura', 'Puertas cortafuego y cancelería interior',
    'Pruebas integrales de sistemas y comisionamiento', 'Limpieza fina y detalles (punch list)', 'Hito: Entrega y certificación Tier III'];
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
    $html = '<html><body style="font-family:Helvetica,sans-serif;color:#0f172a"><h1 style="color:#0891b2;margin:0">' . htmlspecialchars($titulo) . '</h1>'
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
    ['Planos', 'Plano estructural · Naves A y B', 'R2', -60, [['Elemento', 'Especificación'], ['Columnas', 'Sección 70×70 cm, f\'c 350 kg/cm²'], ['Losas', 'Losa maciza h = 30 cm con sobrecarga de 1.5 t/m²'], ['Acero', 'fy 4,200 kg/cm²']]],
    ['Planos', 'Plano arquitectónico · Sala blanca', 'R1', -45, [['Zona', 'Área'], ['Sala blanca', '1,850 m²'], ['Pasillo frío / caliente', '320 m²'], ['Sala eléctrica y UPS', '410 m²']]],
    ['Planos', 'Plano de instalaciones eléctricas · Subestación', 'R3', -20, [['Circuito', 'Descripción'], ['MT-01', 'Acometida 34.5 kV'], ['TG-1', 'Tablero general 800 A'], ['UPS-A', 'Respaldo sala blanca']]],
    ['Especificaciones', 'Especificación de concreto estructural', 'R0', -75, [['Parámetro', 'Valor'], ['Resistencia', 'f\'c 350 kg/cm² a 28 días'], ['Revenimiento', '12 ± 2 cm'], ['Pruebas', 'Cilindros cada 40 m³']]],
    ['Especificaciones', 'Especificación de fachada y cubierta', 'R1', -30, [['Parámetro', 'Valor'], ['Perfil', 'Aluminio anodizado'], ['Vidrio', 'Templado 10 mm con control solar'], ['Cubierta', 'Panel termoacústico con membrana']]],
    ['Contratos y administrativos', 'Contrato de obra y anexos', 'Firmado', -120, [['Concepto', 'Detalle'], ['Modalidad', 'Precios unitarios'], ['Plazo', '420 días naturales'], ['Anticipo', '20 %']]],
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
foreach (['Logística del campus', 'Programa'] as $c) $catEd[$c] = (int)$q("INSERT INTO editor_categorias (proyecto_id, nombre) VALUES ($pid, {$str($c)})");

// 1) Logística: planta del campus con naves, patio de equipos, subestación y accesos
[$W, $H] = [1920, 1080];
$im = $nuevoLienzo($W, $H, '#0b2b3c');
$rej = $hex($im, '#155066');
for ($x = 0; $x <= $W; $x += 60) imageline($im, $x, 0, $x, $H, $rej);
for ($y = 0; $y <= $H; $y += 60) imageline($im, 0, $y, $W, $y, $rej) ;
$f1 = array_map($forma, [
    ['type' => 'rect', 'x' => 160, 'y' => 150, 'w' => 1600, 'h' => 800, 'fill' => '', 'stroke' => '#ffffff', 'strokeWidth' => 6, 'dash' => 'dash'],
    ['type' => 'rect', 'x' => 300, 'y' => 300, 'w' => 620, 'h' => 430, 'fill' => '#06b6d4', 'fillAlpha' => 55, 'stroke' => '#ffffff', 'strokeWidth' => 5],
    ['type' => 'rect', 'x' => 980, 'y' => 300, 'w' => 520, 'h' => 430, 'fill' => '#22d3ee', 'fillAlpha' => 45, 'stroke' => '#ffffff', 'strokeWidth' => 5],
    ['type' => 'rect', 'x' => 300, 'y' => 760, 'w' => 300, 'h' => 150, 'fill' => '#f59e0b', 'fillAlpha' => 75, 'stroke' => '#ffffff', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 640, 'y' => 760, 'w' => 300, 'h' => 150, 'fill' => '#ef4444', 'fillAlpha' => 70, 'stroke' => '#ffffff', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 1540, 'y' => 300, 'w' => 170, 'h' => 240, 'fill' => '#8b5cf6', 'fillAlpha' => 75, 'stroke' => '#ffffff', 'strokeWidth' => 4],
    ['type' => 'arrow', 'x' => 40, 'y' => 900, 'points' => [0, 0, 340, 0], 'stroke' => '#ffeb00', 'strokeWidth' => 10],
    ['type' => 'arrow', 'x' => 1880, 'y' => 240, 'points' => [0, 0, -280, 0], 'stroke' => '#ffeb00', 'strokeWidth' => 10],
    $T(330, 420, 'NAVE A', 56), $T(330, 480, 'Sala blanca', 34), $T(1010, 420, 'NAVE B', 56), $T(1010, 480, 'Sala eléctrica y UPS', 34),
    $T(310, 820, 'Chillers', 34), $T(650, 820, 'Generadores', 34), $T(1550, 360, 'Subestación', 30), $T(1550, 400, '34.5 kV', 30),
    $T(50, 940, 'Acceso vehicular', 34, '#ffeb00'), $T(1500, 240, 'Acceso peatonal', 34, '#ffeb00'),
    $T(70, 30, 'LOGÍSTICA Y ACCESOS DEL CAMPUS', 58), $T(70, 100, 'Planta general · fases de construcción', 34, '#9fd8e6'),
]);
$dibujar($im, $f1);
$crearGrafico('Logística y accesos del campus', $catEd['Logística del campus'], $W, $H, 'plano-azul', null, [['Planta del campus', $json($W, $H, $f1), $im]]);

// 2) Programa: flujo de la ruta crítica y avance por nave (2 láminas)
$im = $nuevoLienzo($W, $H, '#ffffff');
$pasos = [['Movimiento de tierras', '#06b6d4'], ['Cimentación', '#0ea5e9'], ['Estructura', '#6366f1'], ['Envolvente', '#14b8a6'], ['Comisionamiento', '#64748b']];
$f2 = [];
foreach ($pasos as $i => [$n, $c]) {
    $x = 70 + $i * 365;
    $f2[] = ['type' => 'rect', 'x' => $x, 'y' => 440, 'w' => 290, 'h' => 150, 'fill' => $c, 'fillAlpha' => 100, 'stroke' => '#0f172a', 'strokeWidth' => 4];
    $f2[] = $T($x + 20, 495, $n, 28, '#ffffff');
    if ($i < 4) $f2[] = ['type' => 'arrow', 'x' => $x + 295, 'y' => 515, 'points' => [0, 0, 60, 0], 'stroke' => '#0f172a', 'strokeWidth' => 6];
}
$f2[] = $T(70, 60, 'RUTA CRÍTICA · DEL TERRENO AL TIER III', 54, '#0f172a');
$f2[] = $T(70, 130, 'Secuencia de actividades que no pueden atrasarse', 34, '#667085');
$f2[] = ['type' => 'rect', 'x' => 70, 'y' => 700, 'w' => 1780, 'h' => 6, 'fill' => '#06b6d4', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0];
$f2[] = $T(70, 740, 'Cualquier retraso en estas actividades mueve la fecha de certificación.', 34, '#0f172a');
$f2 = array_map($forma, $f2);
$dibujar($im, $f2);
$im2 = $nuevoLienzo($W, $H, '#ffffff');
$f2b = array_map($forma, [
    $T(70, 60, 'AVANCE POR FRENTE DE OBRA', 58, '#0f172a'),
    ['type' => 'rect', 'x' => 120, 'y' => 760, 'w' => 1680, 'h' => 80, 'fill' => '#0f172a', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0],
    ['type' => 'rect', 'x' => 200, 'y' => 600, 'w' => 1520, 'h' => 80, 'fill' => '#10b981', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0],
    ['type' => 'rect', 'x' => 320, 'y' => 440, 'w' => 1280, 'h' => 80, 'fill' => '#0ea5e9', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0],
    ['type' => 'rect', 'x' => 440, 'y' => 280, 'w' => 1040, 'h' => 80, 'fill' => '#06b6d4', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0],
    $T(140, 780, 'Movimiento de tierras y cimentación', 32, '#ffffff'), $T(220, 620, 'Nave A · estructura y sala blanca', 32, '#ffffff'), $T(340, 460, 'Nave B · estructura y cubierta', 32, '#ffffff'), $T(460, 300, 'Instalaciones y comisionamiento', 32, '#ffffff'),
]);
$dibujar($im2, $f2b);
$crearGrafico('Ruta crítica y avance del campus', $catEd['Programa'], $W, $H, 'liso', null, [['Ruta crítica', $json($W, $H, $f2), $im], ['Avance por frente', $json($W, $H, $f2b), $im2]]);

// ---------------------------------------------------------------- Símbolos propios del proyecto
echo "Símbolos...\n";
$svgs = [
    ['Hidrante', 'Contra incendio', '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 64 64"><rect x="22" y="22" width="20" height="30" rx="6" fill="#dc2626"/><rect x="26" y="12" width="12" height="10" rx="3" fill="#b91c1c"/><rect x="14" y="30" width="8" height="8" fill="#374151"/><rect x="42" y="30" width="8" height="8" fill="#374151"/><rect x="18" y="52" width="28" height="6" fill="#374151"/></svg>'],
    ['Andamio', 'Obra', '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 64 64"><g stroke="#f59e0b" stroke-width="4" fill="none"><path d="M12 8v50M52 8v50M12 20h40M12 36h40M12 52h40M12 20l40 16M12 36l40 16"/></g></svg>'],
    ['Zona de acopio', 'Obra', '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 64 64"><rect x="8" y="14" width="48" height="40" rx="4" fill="#cffafe" stroke="#0891b2" stroke-width="3"/><rect x="16" y="24" width="14" height="10" fill="#0891b2"/><rect x="34" y="24" width="14" height="10" fill="#0891b2"/><rect x="16" y="38" width="14" height="10" fill="#0891b2"/><rect x="34" y="38" width="14" height="10" fill="#0891b2"/></svg>'],
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

// A) Sala blanca de la Nave A (pasillos fríos y calientes, racks)
[$W, $H] = [1920, 1080];
$im = $nuevoLienzo($W, $H, '#f4f8fb');
$a = array_map($forma, [
    ['type' => 'rect', 'x' => 120, 'y' => 170, 'w' => 1680, 'h' => 790, 'fill' => '#ffffff', 'fillAlpha' => 100, 'stroke' => '#0f172a', 'strokeWidth' => 8],
    ['type' => 'rect', 'x' => 160, 'y' => 210, 'w' => 760, 'h' => 330, 'fill' => '#cffafe', 'fillAlpha' => 100, 'stroke' => '#0f172a', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 960, 'y' => 210, 'w' => 800, 'h' => 330, 'fill' => '#e0f2fe', 'fillAlpha' => 100, 'stroke' => '#0f172a', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 160, 'y' => 580, 'w' => 560, 'h' => 340, 'fill' => '#fef3c7', 'fillAlpha' => 100, 'stroke' => '#0f172a', 'strokeWidth' => 4],
    ['type' => 'rect', 'x' => 760, 'y' => 580, 'w' => 1000, 'h' => 340, 'fill' => '#eef2ff', 'fillAlpha' => 100, 'stroke' => '#0f172a', 'strokeWidth' => 4],
    $T(190, 240, 'Pasillo frío A-1', 38, '#0f172a'), $T(990, 240, 'Pasillo caliente A-2', 38, '#0f172a'), $T(190, 610, 'Sala eléctrica', 38, '#0f172a'), $T(790, 610, 'Racks y PDU · filas 1 a 6', 38, '#0f172a'),
    $T(70, 30, 'NAVE A · SALA BLANCA', 60, '#0f172a'), $T(70, 100, 'Toca los puntos naranja para ver la foto 360° de cada zona', 32, '#667085'),
]);
$dibujar($im, $a);
$ver360[] = ['Recorrido virtual · Sala blanca Nave A', 'v1.0', ['Losa técnica nivelada y pasillos fríos liberados; el piso técnico inicia la próxima semana.', 'Pendiente sellar penetraciones de charola en el muro cortafuego antes del comisionamiento.'], $im, $a, [[450, 360, 'abandoned_construction', 'Pasillo frío A-1'], [1350, 360, 'boiler_room', 'Pasillo caliente A-2']]];

// B) Patio de equipos y exteriores
$im = $nuevoLienzo($W, $H, '#081a26');
$b = [];
for ($i = 0; $i < 8; $i++) {
    $b[] = ['type' => 'rect', 'x' => 300 + $i * 165, 'y' => 620, 'w' => 120, 'h' => 210, 'fill' => $i < 4 ? '#06b6d4' : '#f59e0b', 'fillAlpha' => 75, 'stroke' => '#ffffff', 'strokeWidth' => 3];
}
$b[] = ['type' => 'rect', 'x' => 260, 'y' => 850, 'w' => 1400, 'h' => 40, 'fill' => '#334155', 'fillAlpha' => 100, 'stroke' => '', 'strokeWidth' => 0];
$b[] = $T(70, 30, 'PATIO DE EQUIPOS Y EXTERIORES', 56, '#ffffff');
$b[] = $T(70, 100, 'Chillers (cian) y generadores (ámbar) sobre plataforma', 32, '#9fd8e6');
$b[] = $T(320, 580, 'Chillers N+1', 34, '#67e8f9');
$b[] = $T(990, 580, 'Generadores', 34, '#fcd34d');
$b = array_map($forma, $b);
$dibujar($im, $b);
$ver360[] = ['Patio de equipos y exteriores', 'v1.0', ['Plataforma de chillers colada y anclajes liberados; el montaje de equipos inicia en dos semanas.'], $im, $b, [[1500, 500, 'canary_wharf', 'Vista exterior del campus']]];

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
