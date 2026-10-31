<?php
/**
 * Proyecto DEMO para presentar el sistema a clientes potenciales.
 *
 *   php database/seed_demo.php
 *
 * - Crea (o recrea) el proyecto ficticio "DEMO" (campus de centro de datos Tier III: movimiento de tierras,
 *   cimentación, estructura y envolvente, obra exterior, instalación eléctrica y respaldo, hidrosanitaria y contra
 *   incendio, enfriamiento de precisión, fibra/seguridad/DCIM, acabados técnicos y comisionamiento) con programa,
 *   restricciones, reportes diarios, bitácora, minutas, materiales, submittals/MSR, curvas de productividad y vPlan
 *   (y, con seed_demo_extras.php, dependencias, Archivo, Editor, Símbolos y View360). Al final, seed_demo_nuevas.php y
 *   seed_demo_herramientas.php completan las herramientas de campo, planeación, costos y documentos. Las fechas son
 *   relativas a hoy.
 * - Crea el usuario de solo lectura «demo» (rol visitante) con acceso únicamente a ese proyecto.
 *   No tiene contraseña utilizable: se entra desde el botón «Demo» de la página principal (/demo).
 * - Las fotos se descargan de Unsplash (licencia gratuita) y se guardan en public/uploads.
 * - Asigna un icono por disciplina a las actividades (SVG en public/build/img/actividades-iconos/demo-*.svg): se ven en la
 *   columna «Icono» del programa y, en la Línea de tiempo, con la casilla «Icono de actividad».
 * Es seguro ejecutarlo varias veces: borra y vuelve a crear solo el proyecto con código DEMO.
 */

require_once __DIR__ . '/../includes/app.php';
if (PHP_SAPI === 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) @session_start();   // las herramientas que pasan por usuario_actual() la piden

use Classes\Calendario;
use Classes\CurvaProductividad;
use Classes\Imagenes;
use Model\Actividad;
use Model\Partida;
use Model\Proyecto;

global $db;
$esc = fn($v) => $db->real_escape_string((string)$v);
$q = function (string $sql) use ($db) {
    if (!$db->query($sql)) throw new RuntimeException($db->error . "\n" . $sql);
    return $db->insert_id;
};
$str = fn($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string((string)$v) . "'";
$json = fn($v) => $str(json_encode($v, JSON_UNESCAPED_UNICODE));

// ---------------------------------------------------------------- imágenes
$cache = sys_get_temp_dir() . '/projectweb_demo_img';
@mkdir($cache, 0775, true);
$foto = function (string $id, int $w = 1600) use ($cache): ?string {
    $f = "$cache/$id-$w.jpg";
    if (!is_file($f) || filesize($f) < 5000) {
        $url = "https://images.unsplash.com/photo-$id?w=$w&q=80&fit=crop&auto=format";
        $datos = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 30, 'header' => "User-Agent: ProjectWebDemo\r\n"]]));
        if (!$datos) { echo "  ! no se pudo descargar $id\n"; return null; }
        file_put_contents($f, $datos);
    }
    return $f;
};
$retrato = function (string $genero, int $n) use ($cache): ?string {
    $f = "$cache/retrato-$genero-$n.jpg";
    if (!is_file($f) || filesize($f) < 2000) {
        $datos = @file_get_contents("https://randomuser.me/api/portraits/$genero/$n.jpg", false, stream_context_create(['http' => ['timeout' => 30]]));
        if (!$datos) return null;
        file_put_contents($f, $datos);
    }
    return $f;
};

// ---------------------------------------------------------------- limpieza previa
$previo = Proyecto::where('codigo', 'DEMO');
if ($previo) {
    echo "Borrando demo anterior...\n";
    foreach ($previo ? [$previo] : [] as $p) {
        $id = (int)$p->id;
        foreach ($db->query("SELECT fotos_json FROM reportes_diarios WHERE proyecto_id=$id")->fetch_all(MYSQLI_ASSOC) as $r)
            foreach (json_decode($r['fotos_json'] ?: '[]', true) as $n) Imagenes::eliminarEn('reportes', $n);
        foreach ($db->query("SELECT imagen FROM personal WHERE proyecto_id=$id")->fetch_all(MYSQLI_ASSOC) as $r) Imagenes::eliminarEn('personal', $r['imagen']);
        foreach ($db->query("SELECT imagen FROM materiales WHERE proyecto_id=$id")->fetch_all(MYSQLI_ASSOC) as $r) Imagenes::eliminar($r['imagen']);
        foreach ($db->query("SELECT plano FROM vplan_laminas WHERE proyecto_id=$id")->fetch_all(MYSQLI_ASSOC) as $r) Imagenes::eliminarVplan($r['plano']);
        // archivos de Archivo, Editor, Símbolos y View360 (las filas se van en cascada; lo vuelve a crear seed_demo_extras.php)
        foreach (\Model\Archivo::consultarSQL("SELECT * FROM archivos WHERE proyecto_id=$id") as $ar) @unlink(\Model\Archivo::carpeta($id) . '/' . basename((string)$ar->archivo_guardado));
        foreach (\Model\Simbolo::deProyecto($id) as $si) $si->eliminarArchivo();
        foreach (\Model\Grafico::consultarSQL("SELECT * FROM editor_graficos WHERE proyecto_id=$id") as $gr) {
            foreach (\Model\GraficoLamina::de((int)$gr->id) as $la) {
                Imagenes::eliminarVplan($la->marcas_png);
                foreach (json_decode((string)$la->marcas_json, true)['shapes'] ?? [] as $sh)
                    if (($sh['type'] ?? '') === 'link' && preg_match('#^/uploads/vplan/([a-f0-9]{32}\.(png|jpg))$#', (string)($sh['url'] ?? ''), $mm)) Imagenes::eliminarVplan($mm[1]);
            }
            Imagenes::eliminarVplan($gr->fondo_imagen);
        }
        $q("DELETE FROM proyectos WHERE id=$id");
    }
}

// ---------------------------------------------------------------- proyecto
$admin = $db->query("SELECT id FROM usuarios WHERE rol='admin' ORDER BY id LIMIT 1")->fetch_assoc();
if (!$admin) die("Primero crea un usuario administrador.\n");

$hoy = new DateTime('today');
$inicioProy = (clone $hoy)->modify('monday this week')->modify('-26 weeks');
$ymd = fn(DateTime $d) => $d->format('Y-m-d');

$proyecto = new Proyecto([
    'codigo' => 'DEMO',
    'nombre' => 'NEXO DATA CAMPUS · CENTRO DE DATOS TIER III',
    'descripcion' => 'Campus de centro de datos de 24,000 m² en dos naves: sala blanca con pasillos fríos y calientes, subestación de 34.5 kV, respaldo con generadores, enfriamiento N+1, redes de fibra óptica, seguridad perimetral, control de acceso y comisionamiento con certificación Tier III.',
    'ubicacion' => 'Parque Tecnológico, Querétaro, México',
    'empresa' => 'Constructora Meridian',
    'contratista' => 'Nexo Digital',
    'numero_trabajo' => 'JOB-DEMO-001',
    'fecha_inicio' => $ymd($inicioProy),
    'fecha_fin' => $ymd((clone $inicioProy)->modify('+60 weeks')),
    'color' => '#06B6D4',
    'usuario_id' => $admin['id'],
]);
$proyecto->guardar();
$pid = (int)$db->insert_id;
$proyecto = Proyecto::find($pid);
$proyecto->inicializarJornadaDefault();
$q("UPDATE proyectos SET tema_modo = 'usuario', tema_paleta = 'cian' WHERE id = $pid");
$q("INSERT INTO calendario_festivos (proyecto_id, fecha, motivo, tipo) VALUES
    ($pid, '" . $ymd((clone $hoy)->modify('monday this week')->modify('-3 weeks')->modify('+2 days')) . "', 'Día festivo', 'festivo'),
    ($pid, '" . $ymd((clone $hoy)->modify('monday this week')->modify('+2 weeks')->modify('+1 day')) . "', 'Paro técnico por maniobra de transformador', 'paro_tecnico')");
echo "Proyecto DEMO id=$pid\n";

// ---------------------------------------------------------------- programa
$calendario = new Calendario($pid);
// [codigo, nombre, semana de inicio (desde el arranque), días de duración, hito?, prioridad]
$programa = [
    ['Preliminares y movimiento de tierras', '#06B6D4', [
        ['Kick off y orden de inicio', 0, 1, 0, 'alta'],
        ['Desmonte, despalme y trazo del campus', 0.4, 6, 0, 'media'],
        ['Movimiento de tierras y plataforma', 0.8, 10, 0, 'media'],
        ['Permisos, impacto ambiental y factibilidad eléctrica', 0.2, 14, 0, 'alta'],
    ]],
    ['Cimentación y losa técnica', '#0EA5E9', [
        ['Excavación y mejoramiento de terreno', 2, 12, 0, 'alta'],
        ['Plantilla, acero y zapatas aisladas', 4, 12, 0, 'alta'],
        ['Dados y anclajes de columnas', 6, 10, 0, 'critica'],
        ['Losa de cimentación de sala blanca', 7.5, 8, 0, 'critica'],
        ['Cimentación de equipos: chillers y generadores', 9, 12, 0, 'alta'],
        ['Impermeabilización y drenaje perimetral', 11, 8, 0, 'media'],
    ]],
    ['Estructura', '#6366F1', [
        ['Columnas y trabes - Nave A', 11.5, 22, 0, 'critica'],
        ['Columnas y trabes - Nave B', 15.5, 22, 0, 'critica'],
        ['Losa de sala blanca y mezzanine', 19, 18, 0, 'critica'],
        ['Losa de cubierta técnica', 22, 16, 0, 'critica'],
        ['Estructura metálica de cubierta y dunnage', 25, 16, 0, 'alta'],
        ['Hito: Cierre de estructura', 29, 0, 1, 'critica'],
    ]],
    ['Envolvente y obra exterior', '#14B8A6', [
        ['Muros prefabricados y cubierta termoacústica', 18, 24, 0, 'media'],
        ['Fachada de acceso y muro cortina', 24, 24, 0, 'media'],
        ['Cancelería y puertas de acceso', 27, 22, 0, 'media'],
        ['Fachada: cristal templado y sellos', 29, 30, 0, 'alta'],
        ['Impermeabilización de cubierta técnica', 33, 8, 0, 'media'],
    ]],
    ['Instalación eléctrica y respaldo', '#EAB308', [
        ['Acometida de 34.5 kV y subestación', 14, 22, 0, 'critica'],
        ['Canalización eléctrica - Naves A y B', 18, 24, 0, 'alta'],
        ['Cableado de fuerza y tableros', 26, 26, 0, 'alta'],
        ['Tableros de distribución y UPS', 29, 26, 0, 'alta'],
        ['Luminarias y contactos', 33, 24, 0, 'media'],
        ['Generadores de respaldo y planta de emergencia', 34, 14, 0, 'critica'],
    ]],
    ['Hidrosanitaria y contra incendio', '#10B981', [
        ['Red hidráulica y sanitaria - servicios', 12, 18, 0, 'alta'],
        ['Drenaje técnico y fosa de bombeo', 16, 18, 0, 'media'],
        ['Red hidráulica - salas técnicas', 20, 22, 0, 'media'],
        ['Rociadores y agente limpio', 26, 32, 0, 'critica'],
        ['Equipo de bombeo y cisterna', 30, 14, 0, 'alta'],
        ['Muebles y accesorios sanitarios', 38, 16, 0, 'baja'],
    ]],
    ['Enfriamiento y ventilación', '#EF4444', [
        ['Ductería y soportes - Nave A', 23, 26, 0, 'alta'],
        ['Ductería y soportes - Nave B', 28, 24, 0, 'alta'],
        ['Montaje de chillers y torres de enfriamiento', 31, 12, 0, 'critica'],
        ['Unidades CRAC y difusores de precisión', 35, 24, 0, 'media'],
        ['Balanceo y puesta en marcha HVAC', 44, 12, 0, 'alta'],
    ]],
    ['Fibra, seguridad y monitoreo', '#8B5CF6', [
        ['Canalización y charolas de comunicaciones', 25, 24, 0, 'media'],
        ['Cableado estructurado Cat 6A y fibra óptica', 31, 30, 0, 'alta'],
        ['Cuartos de telecomunicaciones (MDF/IDF)', 34, 16, 0, 'alta'],
        ['Videovigilancia y control de acceso', 37, 26, 0, 'media'],
        ['Detección de incendio, BMS y DCIM', 40, 20, 0, 'alta'],
    ]],
    ['Acabados técnicos y sala blanca', '#EC4899', [
        ['Aplanados y pintura epóxica', 27, 24, 0, 'media'],
        ['Pisos técnicos y recubrimientos', 34, 28, 0, 'media'],
        ['Plafones y pintura', 38, 26, 0, 'media'],
        ['Puertas cortafuego y cancelería interior', 41, 22, 0, 'baja'],
        ['Racks, PDUs y mobiliario técnico', 43, 18, 0, 'alta'],
    ]],
    ['Comisionamiento y entrega', '#64748B', [
        ['Pruebas de carga y redundancia N+1', 48, 14, 0, 'critica'],
        ['Pruebas integrales de sistemas y comisionamiento', 52, 12, 0, 'critica'],
        ['Limpieza fina y detalles (punch list)', 55, 12, 0, 'media'],
        ['Hito: Entrega y certificación Tier III', 58, 0, 1, 'critica'],
    ]],
];

$actividadesPorNombre = [];
$todas = [];
foreach ($programa as $i => [$nombrePartida, $color, $acts]) {
    $partida = new Partida(['proyecto_id' => $pid, 'codigo' => ($i + 1) . '.0', 'nombre' => $nombrePartida, 'color_bg' => $color, 'orden' => $i + 1]);
    $partida->guardar();
    $partidaId = (int)$db->insert_id;
    foreach ($acts as $j => [$nombre, $semana, $dur, $hito, $prio]) {
        $ini = (clone $inicioProy)->modify('+' . (int)round($semana * 7) . ' days');
        if ($ini->format('N') == 7) $ini->modify('+1 day');
        $inicio = $ymd($ini);
        $fin = $dur > 0 ? $calendario->calcularFechaFin($inicio, $dur) : $inicio;
        // Avance según la fecha de hoy
        if ($fin < $ymd($hoy)) $prog = 100;
        elseif ($inicio <= $ymd($hoy)) {
            $tot = max(1, (int)(new DateTime($inicio))->diff(new DateTime($fin))->days + 1);
            $pas = (int)(new DateTime($inicio))->diff($hoy)->days + 1;
            $prog = (int)max(10, min(90, round($pas / $tot * 100 / 5) * 5));
        } else $prog = 0;
        $a = new Actividad([
            'partida_id' => $partidaId, 'codigo' => ($i + 1) . '.' . ($j + 1), 'nombre' => $nombre,
            'fecha_inicio' => $inicio, 'duracion_dias' => $dur, 'fecha_fin' => $fin, 'progreso' => $prog,
            'prioridad' => $prio, 'hito' => $hito, 'orden' => $j + 1, 'pta' => 1,
        ]);
        $a->sincronizarStatus();
        $res = $a->guardar();
        $aid = (int)($res['id'] ?? 0);   // Actividad::guardar también anota el avance en actividad_avance: insert_id ya no es la actividad
        $todas[] = ['id' => $aid, 'nombre' => $nombre, 'inicio' => $inicio, 'fin' => $fin, 'prog' => $prog, 'partida' => $partidaId, 'ipartida' => $i];
    }
    Partida::find($partidaId)->recalcularFechas();
}
$act = fn(string $parte) => current(array_filter($todas, fn($a) => str_contains($a['nombre'], $parte)));
echo count($todas) . " actividades\n";

// ---------------------------------------------------------------- iconos de actividad (un icono por disciplina)
// SVG que viven en el repo (public/build/img/actividades-iconos/): la Línea de tiempo los muestra con la casilla
// «Icono de actividad» y el programa con la columna «Icono» del diseño.
$iconosDemo = [
    '1.0' => 'demo-casco.svg', '2.0' => 'demo-pala.svg', '3.0' => 'demo-estructura.svg', '4.0' => 'demo-ladrillos.svg',
    '5.0' => 'demo-electricidad.svg', '6.0' => 'demo-hidro.svg', '7.0' => 'demo-hvac.svg', '8.0' => 'demo-datos.svg',
    '9.0' => 'demo-acabados.svg', '10.0' => 'demo-comisionamiento.svg',
];
foreach ($iconosDemo as $codPartida => $archivo) {
    if (!is_file(__DIR__ . '/../public/build/img/actividades-iconos/' . $archivo)) echo "  ! falta el icono $archivo\n";
    $q("UPDATE actividades a JOIN partidas p ON p.id=a.partida_id SET a.icono={$str($archivo)} WHERE p.proyecto_id=$pid AND p.codigo={$str($codPartida)}");
}
echo count($iconosDemo) . " iconos de actividad\n";

// Diseño del programa: nombre propio y columna «Icono» visible (el clásico no la muestra)
$_SESSION['nombre'] = $_SESSION['nombre'] ?? 'Valeria Sotomayor';
\Classes\ProgramaColumnas::guardarDiseno($pid, 'Programa de obra · Nexo Data Campus', [
    ['k' => 'codigo'], ['k' => 'nombre'], ['k' => 'icono'], ['k' => 'fecha_inicio'], ['k' => 'fecha_fin'],
    ['k' => 'duracion'], ['k' => 'progreso'], ['k' => 'status'], ['k' => 'restriccion'], ['k' => 'responsable'], ['k' => 'acciones'],
], ['campo' => 'progreso', 'reglas' => \Classes\ProgramaColumnas::REGLAS_DEFAULT]);

// Restricciones y PTA en lo que viene en las próximas semanas
$tipo = fn(string $n) => (int)$db->query("SELECT id FROM tipos_restriccion WHERE nombre='" . $esc($n) . "'")->fetch_row()[0];
$restr = [
    ['Losa de cimentación de sala blanca', 'Clima', 'Lluvia atípica obligó a reprogramar el colado de la losa técnica; se protegió con membrana provisional.', 'resuelta'],
    ['Columnas y trabes - Nave B', 'Material', 'Retraso de 12 días en la entrega de acero estructural para la Nave B.', 'activa'],
    ['Losa de cubierta técnica', 'Ingeniería', 'Pendiente revisión del cálculo de cargas por las unidades manejadoras de cubierta.', 'activa'],
    ['Estructura metálica de cubierta y dunnage', 'Equipo', 'Se requiere una segunda grúa para el montaje de las trabes de cubierta.', 'activa'],
    ['Acometida de 34.5 kV y subestación', 'Contrato', 'Pendiente convenio y fecha de energización con la empresa suministradora.', 'activa'],
    ['Canalización eléctrica - Naves A y B', 'RFI', 'RFI-018: conflicto entre charola eléctrica y ducto de enfriamiento en el pasillo de la Nave A.', 'activa'],
    ['Ductería y soportes - Nave A', 'Submittal', 'Submittal ALT-DEMO-004 de ductería en revisión por el cliente.', 'activa'],
    ['Rociadores y agente limpio', 'Acceso', 'Se requiere liberar el cuarto de bombas contra incendio para iniciar el montaje.', 'activa'],
    ['Fachada: cristal templado y sellos', 'Material', 'Cristal templado con 3 semanas de entrega adicionales.', 'activa'],
    ['Muros prefabricados y cubierta termoacústica', 'Mano de Obra', 'Falta una cuadrilla de montaje para sostener el ritmo de la Nave B.', 'resuelta'],
];
$k = 0;
foreach ($restr as [$parteAct, $tipoN, $desc, $estado]) {
    $a = $act($parteAct);
    if (!$a) continue;
    $k++;
    $q("INSERT INTO restricciones (actividad_id, tipo_id, codigo, descripcion, fecha, estado) VALUES ({$a['id']}, " . $tipo($tipoN) . ", 'R-" . str_pad((string)$k, 2, '0', STR_PAD_LEFT) . "', {$str($desc)}, '" . $ymd((clone $hoy)->modify('+' . (3 + $k * 2) . ' days')) . "', '$estado')");
    if ($estado === 'activa') $q("UPDATE actividades SET pta = 0 WHERE id = {$a['id']}");
}

// ---------------------------------------------------------------- personal y catálogos
$deptos = ['Seguridad', 'Calidad', 'Ingeniería', 'Estructura', 'Instalaciones', 'Sala blanca', 'Compras', 'Comisionamiento'];
foreach ($deptos as $d) $q("INSERT INTO bitacora_departamentos (proyecto_id, nombre) VALUES ($pid, {$str($d)})");

$equipo = [
    ['Valeria Sotomayor', 'Supervisión', 'women', 44], ['Diego Carranza', 'Ingeniería', 'men', 32], ['Óscar Villalpando', 'Estructura', 'men', 75],
    ['Fernanda Quiroz', 'Calidad', 'women', 65], ['Raúl Espinoza', 'Seguridad', 'men', 52], ['Mónica Aldana', 'Compras', 'women', 26],
    ['Iván Trejo', 'Instalaciones', 'men', 18], ['Paola Rentería', 'Ingeniería', 'women', 79], ['Sergio Beltrán', 'Sala blanca', 'men', 41],
    ['Ximena Duarte', 'Comisionamiento', 'women', 12],
];
$personal = [];
foreach ($equipo as [$nombre, $depto, $g, $n]) {
    $img = ($f = $retrato($g, $n)) ? Imagenes::guardarEn('personal', $f, 600, 85) : null;
    $personal[] = (int)$q("INSERT INTO personal (proyecto_id, nombre, departamento, imagen) VALUES ($pid, {$str($nombre)}, {$str($depto)}, {$str($img)})");
}
$contratista = [];
foreach ([['Arturo Landa', 'Director de infraestructura', 'Nexo Digital'], ['Lucía Fonseca', 'Gerente de proyecto', 'Nexo Digital'], ['Emilio Guzmán', 'Supervisor de obra', 'Nexo Digital']] as [$n, $p, $e])
    $contratista[] = (int)$q("INSERT INTO personal_contratista (proyecto_id, nombre, puesto, empresa) VALUES ($pid, {$str($n)}, {$str($p)}, {$str($e)})");

// Responsable de cada partida (columna «Responsable» del programa)
$responsables = [
    '1.0' => 'Valeria Sotomayor', '2.0' => 'Óscar Villalpando', '3.0' => 'Óscar Villalpando', '4.0' => 'Valeria Sotomayor',
    '5.0' => 'Iván Trejo', '6.0' => 'Iván Trejo', '7.0' => 'Paola Rentería', '8.0' => 'Iván Trejo', '9.0' => 'Sergio Beltrán', '10.0' => 'Ximena Duarte',
];
foreach ($responsables as $cod => $nombre) $q("UPDATE actividades a JOIN partidas p ON p.id = a.partida_id SET a.responsable = {$str($nombre)} WHERE p.proyecto_id = $pid AND p.codigo = {$str($cod)}");

// ---------------------------------------------------------------- reportes diarios (últimos días hábiles con avance)
$fotos = [
    '1580901368919-7738efb0f87e', '1531834685032-c34bf0d84c77', '1504307651254-35680f356dfd', '1541888946425-d81bb19240f5', '1589939705384-5185137a7f0f',
    '1504917595217-d4dc5ebe6122', '1558618666-fcd25c85cd64', '1621905251189-08b45d6a269e', '1632759145351-1d592919f522', '1591588582259-e675bd2e6088', '1545324418-cc1a3fa10c00',
];
$guardadas = [];
foreach ($fotos as $id) $guardadas[] = ($f = $foto($id)) ? Imagenes::guardarEn('reportes', $f, 1600, 82) : null;
$enCurso = array_values(array_filter($todas, fn($a) => $a['prog'] > 0 && $a['prog'] < 100));
$dias = [];
for ($d = clone $hoy; count($dias) < 5; $d->modify('-1 day')) if ($d->format('N') < 6 && $d <= $hoy) $dias[] = clone $d;
$reportes = [
    ['Nave A · Sala blanca', 'Colado de la losa técnica por franjas y revisión de nivelación con láser. Se tomaron cilindros cada 40 m³. Pendiente liberar la zona de pasillos fríos para el piso técnico.', [1, 2, 4]],
    ['Patio de equipos · Chillers', 'Montaje de soportes y anclajes de los chillers enfriados por agua. Pruebas de estanqueidad de la línea de agua helada en el tramo A-C, sin fugas.', [6, 3]],
    ['Nave B · Estructura metálica', 'Izaje de trabes de cubierta y ajuste de conexiones atornilladas. Inspección de soldaduras por el área de calidad con líquidos penetrantes.', [5, 9, 10]],
    ['Subestación · 34.5 kV', 'Montaje de gabinetes de media tensión y canalización de control. Se documenta interferencia con la ruta de ductos en RFI-018.', [7, 3, 8]],
    ['Cubierta técnica · Ductería', 'Soportes de ductería y colocación de aislamiento en la Nave A. Se coordinó el acceso para las unidades CRAC del pasillo caliente.', [0, 3]],
];
foreach ($reportes as $i => [$area, $coment, $idxFotos]) {
    $fs = array_values(array_filter(array_map(fn($x) => $guardadas[$x] ?? null, $idxFotos)));
    $acts = array_slice(array_column($enCurso, 'id'), $i * 2, 3);
    $pers = array_slice($personal, $i % 5, 5);
    $q("INSERT INTO reportes_diarios (proyecto_id, fecha, area, comentarios, personal_json, actividades_json, fotos_json) VALUES
        ($pid, '" . $ymd($dias[$i]) . "', {$str($area)}, {$str($coment)}, {$json($pers)}, {$json($acts)}, {$json($fs)})");
}

// ---------------------------------------------------------------- bitácora
$bit = [
    [0, 'Seguridad', 'Plática de seguridad previa a trabajos en cubierta y maniobras de izaje en la Nave B. Revisión de arnés, líneas de vida y zonas de exclusión.', 'media'],
    [1, 'Estructura', 'Se concluye el colado de la losa de cubierta técnica de la Nave A; los cilindros de prueba a 7 días reportan la resistencia esperada.', 'media'],
    [2, 'Compras', 'El proveedor de acero estructural confirma retraso de 12 días en las trabes de la Nave B. Se cotiza un proveedor alterno para no detener el montaje.', 'critica'],
    [3, 'Ingeniería', 'RFI-018: interferencia entre charola eléctrica y ducto de enfriamiento en el pasillo de la Nave A. Se solicita respuesta al diseñador.', 'alta'],
    [4, 'Calidad', 'Inspección de soldaduras en la estructura de cubierta con líquidos penetrantes: sin hallazgos mayores.', 'baja'],
    [6, 'Instalaciones', 'Se recibe el transformador de 2,500 kVA en sitio. Pendiente la fecha de energización de la empresa suministradora.', 'alta'],
    [8, 'Sala blanca', 'Reunión con el cliente para validar el layout de racks y la capacidad de los PDUs por gabinete.', 'media'],
];
foreach ($bit as [$off, $depto, $desc, $imp]) {
    $q("INSERT INTO bitacora (proyecto_id, fecha, departamento, descripcion, importancia) VALUES ($pid, '" . $ymd((clone $hoy)->modify("-$off days")) . "', {$str($depto)}, {$str($desc)}, '$imp')");
}

// ---------------------------------------------------------------- minutas
$q("INSERT INTO minutas (proyecto_id, fecha, nombre, importancia, contratista_json, personal_json, temas_json, compromisos_json) VALUES
    ($pid, '" . $ymd((clone $hoy)->modify('-6 days')) . "', 'Junta semanal de coordinación', 'alta', {$json($contratista)}, {$json(array_slice($personal, 0, 6))},
    {$json([
        ['tema' => 'Retraso en entrega de acero estructural para la Nave B', 'responsable' => 'Mónica Aldana', 'departamento' => 'Compras'],
        ['tema' => 'Interferencia eléctrica / enfriamiento en la Nave A (RFI-018)', 'responsable' => 'Diego Carranza', 'departamento' => 'Ingeniería'],
        ['tema' => 'Fecha de energización de la subestación de 34.5 kV', 'responsable' => 'Iván Trejo', 'departamento' => 'Instalaciones'],
        ['tema' => 'Avance de fachada y entrega de cristal templado', 'responsable' => 'Sergio Beltrán', 'departamento' => 'Sala blanca'],
    ])},
    {$json([
        ['tema' => 'Responder RFI-018 con solución de rutas en el pasillo técnico', 'responsable' => 'Lucía Fonseca', 'departamento' => 'Ingeniería'],
        ['tema' => 'Confirmar proveedor alterno de trabes metálicas', 'responsable' => 'Mónica Aldana', 'departamento' => 'Compras'],
        ['tema' => 'Gestionar fecha de energización con la empresa suministradora', 'responsable' => 'Arturo Landa', 'departamento' => 'Supervisión'],
    ])}),
    ($pid, '" . $ymd((clone $hoy)->modify('-13 days')) . "', 'Revisión de seguridad y calidad', 'media', {$json(array_slice($contratista, 0, 2))}, {$json(array_slice($personal, 2, 5))},
    {$json([
        ['tema' => 'Permisos de trabajo en cubierta y maniobras de izaje', 'responsable' => 'Raúl Espinoza', 'departamento' => 'Seguridad'],
        ['tema' => 'Resultados de pruebas de concreto a 28 días', 'responsable' => 'Fernanda Quiroz', 'departamento' => 'Calidad'],
        ['tema' => 'Plan de colados de la losa técnica y cubierta', 'responsable' => 'Óscar Villalpando', 'departamento' => 'Estructura'],
    ])}, NULL)");

// ---------------------------------------------------------------- materiales, submittals y MSR
$mats = [
    ['EST-VAR-8', 'Varilla corrugada #8 fy 4200 kg/cm²', 'Estructura', '1504307651254-35680f356dfd', 'NMX-B-457 · Certificado de molino'],
    ['EST-IPR-01', 'Perfil estructural IPR A992 (cubierta)', 'Estructura', '1590069261209-f8e9b8642343', 'ASTM A992'],
    ['CIV-CON-35', 'Concreto premezclado f\'c 350 kg/cm²', 'Obra civil', '1580901368919-7738efb0f87e', 'NMX-C-155 · Pruebas de cilindro'],
    ['ELE-TAB-01', 'Tablero de distribución general 800 A', 'Instalación eléctrica', '1544724569-5f546fd6f2b5', 'NOM-001-SEDE'],
    ['ELE-MT-15', 'Cable de media tensión 34.5 kV (acometida)', 'Instalación eléctrica', '1473341304170-971dccb5ac1e', 'NMX-J-142 · UL'],
    ['HID-BOM-02', 'Equipo de bombeo hidroneumático', 'Hidrosanitaria', '1513828583688-c52646db42da', 'Hydraulic Institute · NOM-006'],
    ['HID-SAN-01', 'Muebles sanitarios y grifería (juego)', 'Hidrosanitaria', '1584622650111-993a426fbf0a', 'NMX-C-407'],
    ['VOZ-FO-12', 'Fibra óptica monomodo 12 hilos', 'Voz y datos', '1597733336794-12d05021d510', 'ITU-T G.652.D'],
    ['VOZ-RACK-42', 'Gabinete de comunicaciones 42U', 'Voz y datos', '1558494949-ef010cbdcc31', 'EIA-310-D'],
    ['SEG-CAM-01', 'Cámara IP domo 4MP', 'Seguridad electrónica', '1557597774-9d273605dfa9', 'UL Listed · IK10'],
    ['SEG-ACC-01', 'Cerradura electrónica con lector de proximidad', 'Seguridad electrónica', '1558002038-1055907df827', 'FCC · CE'],
    ['ACA-PIS-60', 'Porcelanato técnico 60x60 cm alto tránsito', 'Acabados', '1581858726788-75bc0f6a952d', 'NMX-C-358 · PEI 4'],
    ['ACA-FAC-01', 'Cancelería de aluminio y cristal templado', 'Acabados', '1511818966892-d7d671e672a2', 'AAMA 2605'],
    ['ENE-SOL-01', 'Paneles solares fotovoltaicos 550 W (marquesina)', 'Instalación eléctrica', '1497435334941-8c899ee9e8e9', 'IEC 61215'],
];
$mid = [];
foreach ($mats as [$ref, $nombre, $esp, $imgId, $cert]) {
    $f = $foto($imgId, 800);
    $img = $f ? Imagenes::miniatura($f) : null;
    $mid[$ref] = (int)$q("INSERT INTO materiales (proyecto_id, referencia, nombre, especialidad, imagen, certificado_calidad) VALUES ($pid, {$str($ref)}, {$str($nombre)}, {$str($esp)}, {$str($img)}, {$str($cert)})");
}
$q("INSERT IGNORE INTO especialidades (proyecto_id, nombre) SELECT DISTINCT proyecto_id, especialidad FROM materiales WHERE proyecto_id = $pid AND especialidad <> ''");
$subs = [
    ['ALT-DEMO-001', 'Estructura', ['EST-VAR-8', 'CIV-CON-35'], -150, 'A'],
    ['ALT-DEMO-002', 'Estructura', ['EST-IPR-01'], -110, 'A'],
    ['ALT-DEMO-003', 'Instalación eléctrica', ['ELE-TAB-01', 'ELE-MT-15', 'ENE-SOL-01'], -90, 'A'],
    ['ALT-DEMO-004', 'Hidrosanitaria', ['HID-BOM-02', 'HID-SAN-01'], -60, 'RA'],
    ['ALT-DEMO-005', 'Voz y datos', ['VOZ-FO-12', 'VOZ-RACK-42'], -35, 'A'],
    ['ALT-DEMO-006', 'Seguridad electrónica', ['SEG-CAM-01', 'SEG-ACC-01'], -20, 'P'],
    ['ALT-DEMO-007', 'Acabados', ['ACA-PIS-60', 'ACA-FAC-01'], -12, 'P'],
];
$msrId = (int)$q("INSERT INTO msrs (proyecto_id, nombre, descripcion) VALUES ($pid, 'MSR 1', 'Reporte principal de materiales')");
$ord = 0;
$provs = ['Estructura' => 'Aceros del Bajío', 'Instalación eléctrica' => 'Schneider Electric', 'Hidrosanitaria' => 'Grundfos', 'Voz y datos' => 'Panduit', 'Seguridad electrónica' => 'Axis Communications', 'Acabados' => 'Interceramic'];
foreach ($subs as [$num, $esp, $refs, $off, $st]) {
    $sid = (int)$q("INSERT INTO submittals (proyecto_id, msr_id, fecha, numero_trabajo, departamento, para, numero_submittal, especialidad) VALUES
        ($pid, $msrId, '" . $ymd((clone $hoy)->modify("$off days")) . "', 'JOB-DEMO-001', 'Ingeniería', 'Nexo Digital', {$str($num)}, {$str($esp)})");
    foreach ($refs as $r) {
        $ord++;
        $q("INSERT INTO submittal_materiales (submittal_id, material_id) VALUES ($sid, {$mid[$r]})");
        $envio = (clone $hoy)->modify(($off + 2) . ' days');
        $resp = $st === 'P' ? null : (clone $envio)->modify('+6 days');
        $compra = $st === 'A' ? (clone $envio)->modify('+14 days') : null;
        $entrega = $st === 'A' ? (clone $envio)->modify('+40 days') : null;
        $q("INSERT INTO msr_items (proyecto_id, msr_id, submittal_id, material_id, seccion, fecha_envio_requerida, no_submittal_contratista, fecha_envio_real, fecha_respuesta, status,
              fecha_compra_prog, fecha_instalacion_prog, fecha_compra_real, fecha_entrega_prog, fecha_entrega_real, proveedor, observaciones, orden) VALUES
            ($pid, $msrId, $sid, {$mid[$r]}, {$str(mb_strtoupper($esp))}, '" . $ymd((clone $envio)->modify('-3 days')) . "', {$str(900 + $ord)}, '" . $ymd($envio) . "',
             {$str($resp ? $ymd($resp) : null)}, '$st', {$str($compra ? $ymd($compra) : null)}, '" . $ymd((clone $envio)->modify('+75 days')) . "',
             {$str($compra && $compra < $hoy ? $ymd($compra) : null)}, {$str($entrega ? $ymd($entrega) : null)}, {$str($entrega && $entrega < $hoy ? $ymd($entrega) : null)},
             {$str($provs[$esp] ?? '')}, '', $ord)");
    }
}

// ---------------------------------------------------------------- curvas de productividad
$curvas = [
    ['Concreto colado (m³)', 'CIV-CON-35', 2400, 'm³', 'campana', 7, 34],
    ['Acero de refuerzo colocado (ton)', 'EST-VAR-8', 380, 'ton', 'inicio', 6, 30],
    ['Cable de fuerza instalado (ML)', 'ELE-MT-15', 24000, 'ML', 'final', 28, 54],
];
foreach ($curvas as [$nombre, $ref, $cant, $uni, $tipoC, $semIni, $semFin]) {
    $ini = $ymd((clone $inicioProy)->modify("+$semIni weeks"));
    $fin = $ymd((clone $inicioProy)->modify("+$semFin weeks")->modify('-1 day'));
    $cid = (int)$q("INSERT INTO curvas (proyecto_id, nombre, material_id, cantidad, unidad, tipo_curva, fecha_inicio, fecha_fin) VALUES ($pid, {$str($nombre)}, {$mid[$ref]}, $cant, {$str($uni)}, '$tipoC', '$ini', '$fin')");
    $n = CurvaProductividad::numPeriodos($ini, $fin);
    $plan = CurvaProductividad::planeado($cant, $tipoC, $n);
    $pos = CurvaProductividad::posicionHoy($ini, $n);
    $hasta = $pos === null ? ($hoy > new DateTime($fin) ? $n - 1 : -1) : (int)floor($pos);
    foreach ($plan as $i => $v) {
        if ($i > $hasta) break;
        $factor = [0.85, 1.1, 0.92, 1.05, 0.88, 1.0][$i % 6];
        $q("INSERT INTO curva_reales (curva_id, periodo, valor) VALUES ($cid, $i, " . round($v * $factor) . ")");
    }
}

// vPlan lo arma seed_demo_nuevas.php (paquete de láminas, tablas y cajetines)

$q("UPDATE proyectos SET fecha_fin = (SELECT MAX(fecha_fin) FROM partidas WHERE proyecto_id = $pid) WHERE id = $pid");

// ---------------------------------------------------------------- usuario demo (solo lectura)
$q("DELETE FROM usuarios WHERE email = 'demo@projectweb.demo'");
$hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);   // contraseña aleatoria que nadie conoce
$uid = (int)$q("INSERT INTO usuarios (nombre, email, password, rol, confirmado) VALUES ('Usuario Demo', 'demo@projectweb.demo', {$str($hash)}, 'visitante', 1)");
$q("INSERT INTO proyecto_accesos (proyecto_id, usuario_id) VALUES ($pid, $uid)");

require __DIR__ . '/seed_demo_extras.php';       // dependencias, Archivo, Editor, Símbolos y View360
require __DIR__ . '/seed_demo_nuevas.php';       // órdenes de cambio, líneas base, Redactor, SheetFlow, MSR configurable, vPlan libre, Comparador
// seed_demo_nuevas.php (que ya ejecuta seed_demo_estampas.php) termina con seed_demo_herramientas.php (campo, costos, recursos, RFI, seguridad, punch, evidencias…)

echo "Listo. Demo: proyecto $pid · usuario demo $uid (entra por /demo)\n";
