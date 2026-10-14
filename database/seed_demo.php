<?php
/**
 * Proyecto DEMO para presentar el sistema a clientes potenciales.
 *
 *   php database/seed_demo.php
 *
 * - Crea (o recrea) el proyecto ficticio "DEMO" (edificio corporativo multidisciplinario: obra civil, estructura,
 *   instalaciones eléctricas, hidrosanitarias, HVAC, voz y datos, acabados) con programa, restricciones, reportes diarios,
 *   bitácora, minutas, materiales, submittals/MSR, curvas de productividad y vPlan. Las fechas son relativas a hoy.
 * - Crea el usuario de solo lectura «demo» (rol visitante) con acceso únicamente a ese proyecto.
 *   No tiene contraseña utilizable: se entra desde el botón «Demo» de la página principal (/demo).
 * - Las fotos se descargan de Unsplash (licencia gratuita) y se guardan en public/uploads.
 * Es seguro ejecutarlo varias veces: borra y vuelve a crear solo el proyecto con código DEMO.
 */

require_once __DIR__ . '/../includes/app.php';

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
        $q("DELETE FROM proyectos WHERE id=$id");
    }
}

// ---------------------------------------------------------------- proyecto
$admin = $db->query("SELECT id FROM usuarios WHERE rol='admin' ORDER BY id LIMIT 1")->fetch_assoc();
if (!$admin) die("Primero crea un usuario administrador.\n");

$hoy = new DateTime('today');
$inicioProy = (clone $hoy)->modify('monday this week')->modify('-24 weeks');
$ymd = fn(DateTime $d) => $d->format('Y-m-d');

$proyecto = new Proyecto([
    'codigo' => 'DEMO',
    'nombre' => 'TORRE AURORA · EDIFICIO CORPORATIVO',
    'descripcion' => 'Proyecto de demostración: edificio corporativo de 12 niveles y 2 sótanos con obra civil, estructura, instalaciones eléctricas, hidrosanitarias, aire acondicionado, voz y datos, seguridad electrónica, acabados y urbanización.',
    'ubicacion' => 'Zona Corporativa, México',
    'empresa' => 'Constructora Horizonte',
    'contratista' => 'Grupo Inmobiliario Aurora',
    'numero_trabajo' => 'JOB-DEMO-001',
    'fecha_inicio' => $ymd($inicioProy),
    'fecha_fin' => $ymd((clone $inicioProy)->modify('+60 weeks')),
    'color' => '#FF5A1F',
    'usuario_id' => $admin['id'],
]);
$proyecto->guardar();
$pid = (int)$db->insert_id;
$proyecto = Proyecto::find($pid);
$proyecto->inicializarJornadaDefault();
$q("INSERT INTO calendario_festivos (proyecto_id, fecha, motivo, tipo) VALUES
    ($pid, '" . $ymd((clone $hoy)->modify('monday this week')->modify('-3 weeks')->modify('+2 days')) . "', 'Día festivo', 'festivo'),
    ($pid, '" . $ymd((clone $hoy)->modify('monday this week')->modify('+2 weeks')->modify('+1 day')) . "', 'Paro técnico por colado masivo', 'paro_tecnico')");
echo "Proyecto DEMO id=$pid\n";

// ---------------------------------------------------------------- programa
$calendario = new Calendario($pid);
// [codigo, nombre, semana de inicio (desde el arranque), días de duración, hito?, prioridad]
$programa = [
    ['Preliminares y movilización', '#FF5A1F', [
        ['Kick off y orden de inicio', 0, 1, 0, 'alta'],
        ['Trazo, nivelación y topografía', 0.4, 6, 0, 'media'],
        ['Instalación de oficinas, bodegas y cerco perimetral', 0.8, 8, 0, 'media'],
        ['Permisos de construcción y licencias', 0.2, 12, 0, 'alta'],
    ]],
    ['Cimentación y obra civil', '#00B4D8', [
        ['Excavación masiva de sótanos', 2, 14, 0, 'alta'],
        ['Muro de contención y troquelado', 3.5, 16, 0, 'critica'],
        ['Plantilla y acero de zapatas', 6, 10, 0, 'alta'],
        ['Colado de losa de cimentación', 7.5, 6, 0, 'critica'],
        ['Muros de sótano y cisterna', 9, 14, 0, 'alta'],
        ['Impermeabilización de sótanos', 11, 8, 0, 'media'],
    ]],
    ['Estructura', '#8B5CF6', [
        ['Columnas y losa nivel 1-3', 11.5, 24, 0, 'critica'],
        ['Columnas y losa nivel 4-6', 15.5, 24, 0, 'critica'],
        ['Columnas y losa nivel 7-9', 19.5, 24, 0, 'critica'],
        ['Columnas y losa nivel 10-12', 23.5, 24, 0, 'critica'],
        ['Estructura metálica de azotea y helipuerto', 28, 14, 0, 'alta'],
        ['Hito: Cierre de estructura', 31, 0, 1, 'critica'],
    ]],
    ['Albañilería y envolvente', '#F59E0B', [
        ['Muros de block y tablaroca - sótanos y N1-3', 17, 22, 0, 'media'],
        ['Muros de block y tablaroca - N4-6', 21, 22, 0, 'media'],
        ['Muros de block y tablaroca - N7-12', 25, 28, 0, 'media'],
        ['Fachada: cancelería de aluminio y cristal', 26, 34, 0, 'alta'],
        ['Impermeabilización de azotea', 32, 8, 0, 'media'],
    ]],
    ['Instalación eléctrica', '#EAB308', [
        ['Acometida de media tensión y subestación', 14, 20, 0, 'critica'],
        ['Canalización eléctrica - sótanos y N1-6', 19, 24, 0, 'alta'],
        ['Canalización eléctrica - N7-12', 25, 24, 0, 'alta'],
        ['Cableado y tableros de distribución', 28, 30, 0, 'alta'],
        ['Luminarias y contactos', 34, 26, 0, 'media'],
        ['Planta de emergencia y UPS', 36, 12, 0, 'alta'],
    ]],
    ['Hidrosanitaria y contra incendio', '#10B981', [
        ['Red hidráulica y sanitaria - sótanos', 12, 16, 0, 'alta'],
        ['Red hidráulica y sanitaria - N1-6', 19, 24, 0, 'media'],
        ['Red hidráulica y sanitaria - N7-12', 25, 24, 0, 'media'],
        ['Sistema de contra incendio (rociadores)', 27, 36, 0, 'critica'],
        ['Equipo de bombeo y cisterna', 32, 12, 0, 'alta'],
        ['Muebles y accesorios sanitarios', 40, 18, 0, 'baja'],
    ]],
    ['Aire acondicionado y ventilación', '#EF4444', [
        ['Ductería y soportes - N1-6', 24, 24, 0, 'alta'],
        ['Ductería y soportes - N7-12', 29, 24, 0, 'alta'],
        ['Montaje de chillers y torres de enfriamiento', 33, 10, 0, 'critica'],
        ['Fan & coils y difusores', 38, 22, 0, 'media'],
        ['Balanceo y puesta en marcha HVAC', 46, 10, 0, 'alta'],
    ]],
    ['Voz, datos y seguridad electrónica', '#6366F1', [
        ['Canalización y charolas de comunicaciones', 26, 22, 0, 'media'],
        ['Cableado estructurado Cat 6A y fibra óptica', 33, 28, 0, 'alta'],
        ['Cuartos de telecomunicaciones (MDF/IDF)', 36, 14, 0, 'alta'],
        ['Videovigilancia y control de acceso', 40, 24, 0, 'media'],
        ['Sistema de detección de incendio y BMS', 43, 18, 0, 'alta'],
    ]],
    ['Acabados', '#EC4899', [
        ['Aplanados y pastas - N1-6', 28, 22, 0, 'media'],
        ['Pisos y recubrimientos cerámicos', 36, 30, 0, 'media'],
        ['Plafones y pintura', 41, 28, 0, 'media'],
        ['Carpintería, puertas y cancelería interior', 45, 22, 0, 'baja'],
        ['Elevadores: instalación y puesta en marcha', 38, 34, 0, 'alta'],
    ]],
    ['Urbanización y entrega', '#14B8A6', [
        ['Estacionamiento, banquetas y áreas verdes', 48, 22, 0, 'baja'],
        ['Pruebas integrales de sistemas', 52, 12, 0, 'critica'],
        ['Limpieza fina y detalles (punch list)', 55, 14, 0, 'media'],
        ['Entrega y acta de recepción', 58, 0, 1, 'critica'],
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
        $a->guardar();
        $aid = (int)$db->insert_id;
        $todas[] = ['id' => $aid, 'nombre' => $nombre, 'inicio' => $inicio, 'fin' => $fin, 'prog' => $prog, 'partida' => $partidaId, 'ipartida' => $i];
    }
    Partida::find($partidaId)->recalcularFechas();
}
$act = fn(string $parte) => current(array_filter($todas, fn($a) => str_contains($a['nombre'], $parte)));
echo count($todas) . " actividades\n";

// Restricciones y PTA en lo que viene en las próximas semanas
$tipo = fn(string $n) => (int)$db->query("SELECT id FROM tipos_restriccion WHERE nombre='" . $esc($n) . "'")->fetch_row()[0];
$restr = [
    ['Colado de losa de cimentación', 'Clima', 'Lluvias fuertes impidieron el colado programado; se reprogramó ventana de 48 h.', 'resuelta'],
    ['Columnas y losa nivel 7-9', 'Material', 'Retraso de 10 días en la entrega de acero de refuerzo del nivel 8.', 'activa'],
    ['Columnas y losa nivel 7-9', 'Ingeniería', 'Pendiente revisión del cálculo estructural por cambio de cargas en el nivel 9.', 'activa'],
    ['Columnas y losa nivel 10-12', 'Equipo', 'Se requiere grúa torre adicional para el montaje de cimbra del nivel 10.', 'activa'],
    ['Acometida de media tensión', 'Contrato', 'Pendiente contrato y fecha de energización con la compañía suministradora.', 'activa'],
    ['Canalización eléctrica - N7-12', 'RFI', 'RFI-018: conflicto entre charola eléctrica y ducto de HVAC en el plafón del nivel 8.', 'activa'],
    ['Ductería y soportes - N1-6', 'Submittal', 'Submittal ALT-DEMO-004 de ductería en revisión por el cliente.', 'activa'],
    ['Sistema de contra incendio', 'Acceso', 'Se requiere liberar el cuarto de bombas para iniciar el montaje.', 'activa'],
    ['Fachada: cancelería', 'Material', 'Cristal templado con 3 semanas de entrega adicionales.', 'activa'],
    ['Muros de block y tablaroca - N4-6', 'Mano de Obra', 'Falta una cuadrilla de tablaroca para sostener el ritmo.', 'resuelta'],
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
$deptos = ['Seguridad', 'Calidad', 'Ingeniería', 'Estructura', 'Instalaciones', 'Acabados', 'Compras', 'Supervisión'];
foreach ($deptos as $d) $q("INSERT INTO bitacora_departamentos (proyecto_id, nombre) VALUES ($pid, {$str($d)})");

$equipo = [
    ['Laura Mendoza', 'Supervisión', 'women', 44], ['Carlos Ibarra', 'Ingeniería', 'men', 32], ['Marco Villalobos', 'Estructura', 'men', 75],
    ['Daniela Rojas', 'Calidad', 'women', 65], ['Jorge Salinas', 'Seguridad', 'men', 52], ['Patricia Núñez', 'Compras', 'women', 26],
    ['Héctor Barrera', 'Instalaciones', 'men', 18], ['Andrea Fuentes', 'Ingeniería', 'women', 79], ['Rubén Cázares', 'Acabados', 'men', 41],
    ['Mariana Ortega', 'Instalaciones', 'women', 12],
];
$personal = [];
foreach ($equipo as [$nombre, $depto, $g, $n]) {
    $img = ($f = $retrato($g, $n)) ? Imagenes::guardarEn('personal', $f, 600, 85) : null;
    $personal[] = (int)$q("INSERT INTO personal (proyecto_id, nombre, departamento, imagen) VALUES ($pid, {$str($nombre)}, {$str($depto)}, {$str($img)})");
}
$contratista = [];
foreach ([['Roberto Aguilar', 'Director de desarrollo', 'Grupo Inmobiliario Aurora'], ['Sofía Cantú', 'Gerente de proyecto', 'Grupo Inmobiliario Aurora'], ['Ernesto Lara', 'Supervisor de obra', 'Grupo Inmobiliario Aurora']] as [$n, $p, $e])
    $contratista[] = (int)$q("INSERT INTO personal_contratista (proyecto_id, nombre, puesto, empresa) VALUES ($pid, {$str($n)}, {$str($p)}, {$str($e)})");

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
    ['Niveles 7 a 9 · Estructura', 'Armado de acero y cimbra en losa del nivel 8; colado de columnas del nivel 7 con concreto f\'c 350. Sin incidentes. Pendiente entrega de varilla #8 para el nivel 9.', [1, 2, 4]],
    ['Sótano 2 · Hidrosanitaria', 'Montaje de tubería de PPR para red hidráulica y bajadas sanitarias. Pruebas de hermeticidad satisfactorias en el tramo A-C.', [6, 3]],
    ['Azotea · Estructura metálica', 'Soldadura de armaduras y colocación de anclas para el helipuerto. Inspección de soldaduras por el área de calidad.', [5, 9, 10]],
    ['Niveles 3 a 5 · Eléctrica y HVAC', 'Canalización eléctrica y colocación de soportes de ductería. Se detecta interferencia en nivel 4; se documenta en RFI-018.', [7, 3, 8]],
    ['Sótano 1 · Cisterna e impermeabilización', 'Aplicación de impermeabilizante en muros de cisterna y limpieza para prueba de carga. Se coordinó el acceso para equipo de bombeo.', [0, 3]],
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
    [0, 'Seguridad', 'Plática de seguridad previa a trabajos en altura en niveles 8 al 10. Revisión de líneas de vida, barandales y redes perimetrales.', 'media'],
    [1, 'Estructura', 'Se concluye el colado de losa del nivel 7 con resistencia de 28 días satisfactoria en los primeros cilindros de prueba.', 'media'],
    [2, 'Compras', 'El proveedor de acero confirma retraso de 10 días en varilla #8. Se cotiza proveedor alterno para no detener el nivel 9.', 'critica'],
    [3, 'Ingeniería', 'RFI-018: interferencia entre charola eléctrica y ducto de HVAC en el plafón del nivel 8. Se solicita respuesta al diseñador.', 'alta'],
    [4, 'Calidad', 'Inspección de soldaduras en estructura metálica de azotea con líquidos penetrantes: sin hallazgos mayores.', 'baja'],
    [6, 'Instalaciones', 'Se recibe el transformador de 1,000 kVA en sitio. Pendiente la fecha de energización de la compañía suministradora.', 'alta'],
    [8, 'Supervisión', 'Reunión con el cliente para validar cambios en acabados de lobby y áreas comunes.', 'media'],
];
foreach ($bit as [$off, $depto, $desc, $imp]) {
    $q("INSERT INTO bitacora (proyecto_id, fecha, departamento, descripcion, importancia) VALUES ($pid, '" . $ymd((clone $hoy)->modify("-$off days")) . "', {$str($depto)}, {$str($desc)}, '$imp')");
}

// ---------------------------------------------------------------- minutas
$q("INSERT INTO minutas (proyecto_id, fecha, nombre, importancia, contratista_json, personal_json, temas_json, compromisos_json) VALUES
    ($pid, '" . $ymd((clone $hoy)->modify('-6 days')) . "', 'Junta semanal de coordinación', 'alta', {$json($contratista)}, {$json(array_slice($personal, 0, 6))},
    {$json([
        ['tema' => 'Retraso en entrega de acero de refuerzo para nivel 9', 'responsable' => 'Patricia Núñez', 'departamento' => 'Compras'],
        ['tema' => 'Interferencia eléctrica / HVAC en nivel 8 (RFI-018)', 'responsable' => 'Carlos Ibarra', 'departamento' => 'Ingeniería'],
        ['tema' => 'Fecha de energización de la subestación', 'responsable' => 'Héctor Barrera', 'departamento' => 'Instalaciones'],
        ['tema' => 'Avance de fachada y entrega de cristal templado', 'responsable' => 'Rubén Cázares', 'departamento' => 'Acabados'],
    ])},
    {$json([
        ['tema' => 'Responder RFI-018 con solución de rutas en plafón', 'responsable' => 'Sofía Cantú', 'departamento' => 'Ingeniería'],
        ['tema' => 'Confirmar proveedor alterno de varilla #8', 'responsable' => 'Patricia Núñez', 'departamento' => 'Compras'],
        ['tema' => 'Gestionar fecha de energización con la compañía suministradora', 'responsable' => 'Roberto Aguilar', 'departamento' => 'Supervisión'],
    ])}),
    ($pid, '" . $ymd((clone $hoy)->modify('-13 days')) . "', 'Revisión de seguridad y calidad', 'media', {$json(array_slice($contratista, 0, 2))}, {$json(array_slice($personal, 2, 5))},
    {$json([
        ['tema' => 'Permisos de trabajo en altura y espacios confinados', 'responsable' => 'Jorge Salinas', 'departamento' => 'Seguridad'],
        ['tema' => 'Resultados de pruebas de concreto a 28 días', 'responsable' => 'Daniela Rojas', 'departamento' => 'Calidad'],
        ['tema' => 'Plan de colados de niveles 10 a 12', 'responsable' => 'Marco Villalobos', 'departamento' => 'Estructura'],
    ])}, NULL)");

// ---------------------------------------------------------------- materiales, submittals y MSR
$mats = [
    ['EST-VAR-8', 'Varilla corrugada #8 fy 4200 kg/cm²', 'Estructura', '1504307651254-35680f356dfd', 'NMX-B-457 · Certificado de molino'],
    ['EST-IPR-01', 'Perfil estructural IPR A992 (azotea)', 'Estructura', '1590069261209-f8e9b8642343', 'ASTM A992'],
    ['CIV-CON-35', 'Concreto premezclado f\'c 350 kg/cm²', 'Obra civil', '1580901368919-7738efb0f87e', 'NMX-C-155 · Pruebas de cilindro'],
    ['ELE-TAB-01', 'Tablero de distribución general 800 A', 'Instalación eléctrica', '1544724569-5f546fd6f2b5', 'NOM-001-SEDE'],
    ['ELE-MT-15', 'Cable de media tensión 15 kV (acometida)', 'Instalación eléctrica', '1473341304170-971dccb5ac1e', 'NMX-J-142 · UL'],
    ['HID-BOM-02', 'Equipo de bombeo hidroneumático', 'Hidrosanitaria', '1513828583688-c52646db42da', 'Hydraulic Institute · NOM-006'],
    ['HID-SAN-01', 'Muebles sanitarios y grifería (juego)', 'Hidrosanitaria', '1584622650111-993a426fbf0a', 'NMX-C-407'],
    ['VOZ-FO-12', 'Fibra óptica monomodo 12 hilos', 'Voz y datos', '1597733336794-12d05021d510', 'ITU-T G.652.D'],
    ['VOZ-RACK-42', 'Gabinete de comunicaciones 42U', 'Voz y datos', '1558494949-ef010cbdcc31', 'EIA-310-D'],
    ['SEG-CAM-01', 'Cámara IP domo 4MP', 'Seguridad electrónica', '1557597774-9d273605dfa9', 'UL Listed · IK10'],
    ['SEG-ACC-01', 'Cerradura electrónica con lector de proximidad', 'Seguridad electrónica', '1558002038-1055907df827', 'FCC · CE'],
    ['ACA-PIS-60', 'Porcelanato 60x60 cm alto tránsito', 'Acabados', '1581858726788-75bc0f6a952d', 'NMX-C-358 · PEI 4'],
    ['ACA-FAC-01', 'Cancelería de aluminio y cristal templado', 'Acabados', '1511818966892-d7d671e672a2', 'AAMA 2605'],
    ['ENE-SOL-01', 'Paneles solares fotovoltaicos 550 W', 'Instalación eléctrica', '1497435334941-8c899ee9e8e9', 'IEC 61215'],
];
$mid = [];
foreach ($mats as [$ref, $nombre, $esp, $imgId, $cert]) {
    $f = $foto($imgId, 800);
    $img = $f ? Imagenes::miniatura($f) : null;
    $mid[$ref] = (int)$q("INSERT INTO materiales (proyecto_id, referencia, nombre, especialidad, imagen, certificado_calidad) VALUES ($pid, {$str($ref)}, {$str($nombre)}, {$str($esp)}, {$str($img)}, {$str($cert)})");
}
$subs = [
    ['ALT-DEMO-001', 'Estructura', ['EST-VAR-8', 'CIV-CON-35'], -150, 'A'],
    ['ALT-DEMO-002', 'Estructura', ['EST-IPR-01'], -110, 'A'],
    ['ALT-DEMO-003', 'Instalación eléctrica', ['ELE-TAB-01', 'ELE-MT-15', 'ENE-SOL-01'], -90, 'A'],
    ['ALT-DEMO-004', 'Hidrosanitaria', ['HID-BOM-02', 'HID-SAN-01'], -60, 'RA'],
    ['ALT-DEMO-005', 'Voz y datos', ['VOZ-FO-12', 'VOZ-RACK-42'], -35, 'A'],
    ['ALT-DEMO-006', 'Seguridad electrónica', ['SEG-CAM-01', 'SEG-ACC-01'], -20, 'P'],
    ['ALT-DEMO-007', 'Acabados', ['ACA-PIS-60', 'ACA-FAC-01'], -12, 'P'],
];
$ord = 0;
$provs = ['Estructura' => 'Aceros del Norte', 'Instalación eléctrica' => 'Schneider Electric', 'Hidrosanitaria' => 'Grundfos', 'Voz y datos' => 'Panduit', 'Seguridad electrónica' => 'Axis Communications', 'Acabados' => 'Interceramic'];
foreach ($subs as [$num, $esp, $refs, $off, $st]) {
    $sid = (int)$q("INSERT INTO submittals (proyecto_id, fecha, numero_trabajo, departamento, para, numero_submittal, especialidad) VALUES
        ($pid, '" . $ymd((clone $hoy)->modify("$off days")) . "', 'JOB-DEMO-001', 'Ingeniería', 'Grupo Inmobiliario Aurora', {$str($num)}, {$str($esp)})");
    foreach ($refs as $r) {
        $ord++;
        $q("INSERT INTO submittal_materiales (submittal_id, material_id) VALUES ($sid, {$mid[$r]})");
        $envio = (clone $hoy)->modify(($off + 2) . ' days');
        $resp = $st === 'P' ? null : (clone $envio)->modify('+6 days');
        $compra = $st === 'A' ? (clone $envio)->modify('+14 days') : null;
        $entrega = $st === 'A' ? (clone $envio)->modify('+40 days') : null;
        $q("INSERT INTO msr_items (proyecto_id, submittal_id, material_id, seccion, fecha_envio_requerida, no_submittal_contratista, fecha_envio_real, fecha_respuesta, status,
              fecha_compra_prog, fecha_instalacion_prog, fecha_compra_real, fecha_entrega_prog, fecha_entrega_real, proveedor, observaciones, orden) VALUES
            ($pid, $sid, {$mid[$r]}, {$str(mb_strtoupper($esp))}, '" . $ymd((clone $envio)->modify('-3 days')) . "', {$str(900 + $ord)}, '" . $ymd($envio) . "',
             {$str($resp ? $ymd($resp) : null)}, '$st', {$str($compra ? $ymd($compra) : null)}, '" . $ymd((clone $envio)->modify('+75 days')) . "',
             {$str($compra && $compra < $hoy ? $ymd($compra) : null)}, {$str($entrega ? $ymd($entrega) : null)}, {$str($entrega && $entrega < $hoy ? $ymd($entrega) : null)},
             {$str($provs[$esp] ?? '')}, '', $ord)");
    }
}

// ---------------------------------------------------------------- curvas de productividad
$curvas = [
    ['Concreto colado (m³)', 'CIV-CON-35', 2400, 'm³', 'campana', 7, 34],
    ['Acero de refuerzo colocado (ton)', 'EST-VAR-8', 380, 'ton', 'inicio', 6, 30],
    ['Cableado eléctrico instalado (ML)', 'ELE-MT-15', 18000, 'ML', 'final', 28, 54],
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

// ---------------------------------------------------------------- vPlan
$planoFoto = $foto('1581092160562-40aa08e78837', 2400);
$plano = $planoFoto ? Imagenes::guardarGrande($planoFoto, 2400) : null;
$partidaCanal = (int)$db->query("SELECT id FROM partidas WHERE proyecto_id=$pid AND codigo='3.0'")->fetch_row()[0];   // Estructura
$partidaCam = (int)$db->query("SELECT id FROM partidas WHERE proyecto_id=$pid AND codigo='5.0'")->fetch_row()[0];     // Instalación eléctrica
$lam = [];
foreach ([['ESTRUCTURA · NIVELES 1 A 12', $partidaCanal, 1], ['INSTALACIÓN ELÉCTRICA · SÓTANOS Y TORRE', $partidaCam, 2]] as [$titulo, $part, $orden]) {
    $lam[] = (int)$q("INSERT INTO vplan_laminas (proyecto_id, partida_id, titulo, supervisor, recovery_cols, restr_auto, plano, orden) VALUES
        ($pid, $part, {$str($titulo)}, 'Laura Mendoza', 1, 1, {$str($plano)}, $orden)");
}
$tid = (int)$q("INSERT INTO vplan_tablas (proyecto_id, titulo, nombre_interno, tipo) VALUES ($pid, 'PUNCH LIST', 'Punch list planta', 'punch')");
foreach ([['Corregir traslape de acero en columna C-14 (nivel 7)', -6, 0, 100], ['Resanar oquedades en columnas del sótano 2', -2, 3, 40], ['Limpieza y retiro de cimbra nivel 6', 0, 5, 20]] as $i => [$d, $o, $f, $pct])
    $q("INSERT INTO vplan_tabla_filas (tabla_id, orden, datos) VALUES ($tid, $i, " . $json(['descripcion' => $d, 'inicio' => $ymd((clone $hoy)->modify("$o days")), 'fin' => $ymd((clone $hoy)->modify('+' . ($o + $f + 1) . ' days')), 'pct' => $pct]) . ")");
foreach ($lam as $i => $l) $q("INSERT INTO vplan_lamina_tablas (lamina_id, tabla_id, orden) VALUES ($l, $tid, 1)");

$q("UPDATE proyectos SET fecha_fin = (SELECT MAX(fecha_fin) FROM partidas WHERE proyecto_id = $pid) WHERE id = $pid");

// ---------------------------------------------------------------- usuario demo (solo lectura)
$q("DELETE FROM usuarios WHERE email = 'demo@projectweb.demo'");
$hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);   // contraseña aleatoria que nadie conoce
$uid = (int)$q("INSERT INTO usuarios (nombre, email, password, rol, confirmado) VALUES ('Usuario Demo', 'demo@projectweb.demo', {$str($hash)}, 'visitante', 1)");
$q("INSERT INTO proyecto_accesos (proyecto_id, usuario_id) VALUES ($pid, $uid)");

echo "Listo. Demo: proyecto $pid · usuario demo $uid (entra por /demo)\n";
