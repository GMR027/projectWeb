<?php
/**
 * Datos de ejemplo del proyecto DEMO para las herramientas que no cubren seed_demo.php,
 * seed_demo_extras.php ni seed_demo_nuevas.php:
 *
 *  - Campo: evidencias fotográficas, punch list con fotos antes/después, seguridad
 *    (permisos de trabajo, tipos propios e incidentes), RFI, calidad/ITP, equipo y mapa de avance.
 *  - Administración: contratos/fianzas/seguros con vencimientos y cierre de obra con su acta.
 *  - Planeación: recursos y cuadrillas con carga semanal, subactividades de una actividad
 *    (con su tabla vinculada de SheetFlow), notas de vPlan y ajustes del Lookahead.
 *  - Costos: presupuesto por partida y movimientos reales/estimaciones ligados a las OC.
 *  - Documentos: carpetas del Archivo, un ensamble de «Unir documentos (PDF)», plantillas
 *    y versiones del Redactor, y láminas del Editor adjuntas a submittals y RFI.
 *  - Personalización: campos propios (Zona, Frente, Nave) con sus valores.
 *  - Historial de cambios: movimientos manuales de ejemplo (pendientes y aceptados).
 *
 *   php database/seed_demo_herramientas.php            (crea/recrea)
 *   php database/seed_demo_herramientas.php --limpiar  (solo borra lo que sembró)
 *
 * - Lo ejecuta seed_demo_nuevas.php al final; también puede correrse solo sobre un demo ya existente.
 * - Solo toca el proyecto con código DEMO y borra lo suyo antes de crearlo: se puede correr las veces que sea.
 * - Las fotos salen de public/build/img/landing (Unsplash License, ver CREDITOS.txt): no descarga nada.
 */

require_once __DIR__ . '/../includes/app.php';

use Classes\CamposPersonalizados;
use Classes\HistorialCambios;
use Classes\Imagenes;
use Classes\ListaDiseno;
use Classes\MapaAvance;
use Classes\PunchVista;
use Classes\Vplan;
use Model\Actividad;
use Model\Ensamble;
use Model\Proyecto;
use Model\RedactorDocumento;
use Model\Subactividad;
use Model\SubactividadHoja;
use Model\VplanLamina;

global $db;
$proyecto = Proyecto::where('codigo', 'DEMO');
if (!$proyecto) die("No existe el proyecto DEMO. Ejecuta primero php database/seed_demo.php\n");
$pid = (int)$proyecto->id;

$sql = function (string $consulta) use ($db) {
    if (!$db->query($consulta)) throw new RuntimeException($db->error . "\n" . $consulta);
    return (int)$db->insert_id;
};
$rg = fn(string $consulta) => ($r = $db->query($consulta)) ? $r->fetch_all(MYSQLI_ASSOC) : [];
$e = fn($v) => $db->real_escape_string((string)$v);
$S = fn($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string((string)$v) . "'";
$N = fn($v) => $v === null ? 'NULL' : (is_int($v) ? (string)$v : rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'));
$tabla = fn(string $t) => (bool)$db->query("SHOW TABLES LIKE '" . $e($t) . "'")->num_rows;
$hoy = new DateTime('today');
$dia = fn(int $off) => (clone $hoy)->modify(($off >= 0 ? '+' : '') . "$off days")->format('Y-m-d');
$ahora = fn(int $off, string $hora = '09:00:00') => $dia($off) . ' ' . $hora;
$actId = function (string $codigo) use ($db, $pid): int {
    $r = $db->query("SELECT a.id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.codigo = '" . $db->real_escape_string($codigo) . "' LIMIT 1")->fetch_row();
    return $r ? (int)$r[0] : 0;
};
$partidaId = function (string $codigo) use ($db, $pid): int {
    $r = $db->query("SELECT id FROM partidas WHERE proyecto_id = $pid AND codigo = '" . $db->real_escape_string($codigo) . "' LIMIT 1")->fetch_row();
    return $r ? (int)$r[0] : 0;
};
$usuarioDemo = (int)($rg("SELECT id FROM usuarios WHERE email = 'demo@projectweb.demo'")[0]['id'] ?? 0);
$fotos = __DIR__ . '/../public/build/img/landing';
$foto = fn(string $archivo) => is_file("$fotos/$archivo") ? "$fotos/$archivo" : null;
$guardarFoto = fn(string $carpeta, string $archivo) => ($f = $foto($archivo)) ? Imagenes::guardarEn($carpeta, $f, 1800, 84) : null;

echo "Herramientas del demo: limpiando lo anterior...\n";

// ---------------------------------------------------------------- limpieza (solo lo del demo)
if ($tabla('evidencias')) {
    foreach ($rg("SELECT foto FROM evidencias WHERE proyecto_id = $pid") as $f) Imagenes::eliminarEn('evidencias', $f['foto']);
    $sql("DELETE FROM evidencias WHERE proyecto_id = $pid");
}
if ($tabla('punch_items')) {
    foreach ($rg("SELECT foto_antes, foto_despues FROM punch_items WHERE proyecto_id = $pid") as $f) {
        foreach (['foto_antes', 'foto_despues'] as $c) {
            if (!empty($f[$c])) { Imagenes::eliminarEn('punch', $f[$c]); PunchVista::borrarMiniatura($f[$c]); }
        }
    }
    $sql("DELETE FROM punch_items WHERE proyecto_id = $pid");
}
foreach (['mapa_avance_instantaneas', 'mapa_avance_capas', 'mapa_avance_ajustes', 'costos_movimientos', 'costos_partidas',
    'permisos_trabajo', 'seguridad_incidentes', 'permisos_tipos', 'rfis', 'recursos', 'lookahead_config'] as $t) {
    if ($tabla($t)) $sql("DELETE FROM $t WHERE proyecto_id = $pid");
}
if ($tabla('catalogo_categorias')) $sql("DELETE FROM catalogo_categorias WHERE proyecto_id = $pid AND ambito IN ('punch','evidencias')");
foreach (['calidad_planes', 'equipo', 'contratos', 'cierre_items', 'cierre_obra'] as $t) {
    if ($tabla($t)) $sql("DELETE FROM $t WHERE proyecto_id = $pid");
}
if ($tabla('campos_personalizados')) $sql("DELETE FROM campos_personalizados WHERE proyecto_id = $pid");
if ($tabla('disenos_lista')) $sql("DELETE FROM disenos_lista WHERE proyecto_id = $pid AND herramienta IN ('punch','bitacora','lookahead')");
if ($tabla('subactividad_hojas')) {
    foreach ($rg("SELECT hoja_id FROM subactividad_hojas WHERE proyecto_id = $pid") as $h) $sql("DELETE FROM sheetflow_hojas WHERE id = " . (int)$h['hoja_id']);
    $sql("DELETE FROM subactividad_hojas WHERE proyecto_id = $pid");
}
if ($tabla('subactividades')) $sql("DELETE FROM subactividades WHERE actividad_id IN (SELECT a.id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid)");
if ($tabla('vplan_notas')) $sql("DELETE FROM vplan_notas WHERE lamina_id IN (SELECT id FROM vplan_laminas WHERE proyecto_id = $pid)");
if ($tabla('redactor_plantillas')) $sql("DELETE FROM redactor_plantillas WHERE proyecto_id = $pid");
if ($tabla('ensambles')) $sql("DELETE FROM ensambles WHERE proyecto_id = $pid");
if ($tabla('archivo_carpetas')) $sql("DELETE FROM archivo_carpetas WHERE proyecto_id = $pid AND nombre IN ('Planos vigentes','Documentación contractual','Fotografías de avance')");
if ($tabla('auditoria_cambios')) $sql("DELETE FROM auditoria_cambios WHERE proyecto_id = $pid AND origen = 'manual' AND resumen LIKE 'Demo · %'");

if (in_array('--limpiar', $argv ?? [], true)) { echo "Solo se limpió lo anterior.\n"; return; }

// ---------------------------------------------------------------- evidencias fotográficas
if ($tabla('evidencias')) {
    echo "Evidencias...\n";
    foreach (['Avance de estructura', 'Instalaciones', 'Fachada', 'Calidad', 'Sala blanca'] as $c) {
        $sql("INSERT IGNORE INTO catalogo_categorias (proyecto_id, ambito, nombre) VALUES ($pid, 'evidencias', {$S($c)})");
    }
    $evidencias = [
        ['3.4', -1, 'Colado de la losa de cubierta técnica por franjas; se revisó revenimiento en cada camión.', 'Avance de estructura', 'Estructura'],
        ['4.4', -2, 'Colocación de cristal templado en fachada oriente con andamio y red de protección.', 'Fachada', 'Sala blanca'],
        ['5.2', -3, 'Canalización eléctrica en pasillo de la Nave A; pendiente liberar interferencia con enfriamiento.', 'Instalaciones', 'Instalaciones'],
        ['6.4', -5, 'Tendido de rociadores en cuarto de UPS y prueba de hermeticidad del tramo A-C.', 'Instalaciones', 'Hidrosanitaria'],
        ['7.1', -6, 'Ductería galvanizada y soportes en la Nave A; revisión dimensional por calidad.', 'Calidad', 'Enfriamiento'],
        ['3.5', -8, 'Montaje de estructura metálica de cubierta y revisión de soldaduras.', 'Avance de estructura', 'Estructura'],
        ['9.2', -10, 'Muestra de piso técnico modular colocada en el pasillo frío A-1.', 'Sala blanca', 'Sala blanca'],
    ];
    $archivos = ['campo.jpg', 'grua.jpg', 'planos.jpg'];
    foreach ($evidencias as $i => [$cod, $off, $desc, $cat, $depto]) {
        $a = $actId($cod);
        if (!$a) continue;
        $n = $guardarFoto('evidencias', $archivos[$i % count($archivos)]);
        if (!$n) continue;
        $sql("INSERT INTO evidencias (proyecto_id, actividad_id, fecha, descripcion, foto, departamento, categoria, usuario_id)
            VALUES ($pid, $a, '{$dia($off)}', {$S($desc)}, {$S($n)}, {$S($depto)}, {$S($cat)}, " . ($usuarioDemo ?: 'NULL') . ')');
    }
}

// ---------------------------------------------------------------- punch list
if ($tabla('punch_items')) {
    echo "Punch list...\n";
    foreach (['Estructura', 'Sala blanca', 'Instalaciones', 'Seguridad', 'Fachada', 'Limpieza'] as $c) {
        $sql("INSERT IGNORE INTO catalogo_categorias (proyecto_id, ambito, nombre) VALUES ($pid, 'punch', {$S($c)})");
    }
    $punch = [
        ['Resane de oquedades en muro cortafuego de la Nave A', 'Se detectaron oquedades y cangrejeras después del descimbrado.', '3.3', 'Nave A · eje B-C', 'Estructura', 'media', 'Óscar Villalpando', 'Constructora Meridian', -2, 'en_proceso', true, true],
        ['Filtración en muro de la cisterna contra incendio', 'Se observa humedad en la junta del muro sur; revisar impermeabilización y sello.', '6.5', 'Cisterna · muro sur', 'Instalaciones', 'alta', 'Iván Trejo', 'Impermeabilizantes del Bajío', 3, 'abierto', true, false],
        ['Puerta cortafuego del cuarto de UPS descuadrada', 'La hoja roza el marco y no cierra con el herraje completo.', '9.4', 'Nave A · cuarto de UPS', 'Sala blanca', 'baja', 'Sergio Beltrán', 'Carpintería Metálica del Norte', 8, 'corregido', true, true],
        ['Falta señalización de ruta de evacuación en Nave B', 'Colocar señales fotoluminiscentes y plano de evacuación junto a la salida.', '10.3', 'Nave B · pasillo principal', 'Seguridad', 'media', 'Raúl Espinoza', 'Constructora Meridian', 2, 'abierto', false, false],
        ['Tablero TD-3 sin identificación de circuitos', 'Faltan etiquetas y directorio de circuitos; no coincide con el plano eléctrico.', '5.4', 'Nave A · sala eléctrica', 'Instalaciones', 'alta', 'Iván Trejo', 'Instalaciones del Centro', 5, 'en_proceso', false, false],
        ['Charola sin tapa en pasillo caliente A-2', 'Tramo de charola portacable sin tapa sobre el pasillo; riesgo de acumulación de polvo.', '8.1', 'Nave A · pasillo caliente', 'Instalaciones', 'media', 'Iván Trejo', 'Instalaciones del Centro', -1, 'cerrado', false, false],
        ['Vidrio con sello deficiente en fachada oriente', 'Se observa filtración de aire en la junta perimetral del cristal del acceso principal.', '4.4', 'Fachada oriente · acceso', 'Fachada', 'critica', 'Sergio Beltrán', 'Cancelería del Centro', 1, 'abierto', true, false],
        ['Limpieza de escombro en cubierta técnica', 'Retirar recortes de lámina y empaques antes del montaje de las unidades CRAC.', '3.5', 'Nave A · cubierta técnica', 'Limpieza', 'baja', 'Valeria Sotomayor', 'Constructora Meridian', -4, 'cerrado', false, false],
    ];
    foreach ($punch as $i => [$titulo, $desc, $cod, $ubic, $cat, $sev, $resp, $contr, $limite, $estado, $antes, $despues]) {
        $a = $actId($cod);
        $fAntes = $antes ? $guardarFoto('punch', ['campo.jpg', 'grua.jpg', 'planos.jpg'][$i % 3]) : null;
        $fDesp = $despues ? $guardarFoto('punch', $i % 2 ? 'campo.jpg' : 'planos.jpg') : null;
        $sql("INSERT INTO punch_items (proyecto_id, titulo, descripcion, actividad_id, ubicacion, categoria, severidad, responsable, contratista, fecha_limite, estado, foto_antes, foto_despues, origen, creado_por, cerrado_en)
            VALUES ($pid, {$S($titulo)}, {$S($desc)}, " . ($a ?: 'NULL') . ", {$S($ubic)}, {$S($cat)}, '$sev', {$S($resp)}, {$S($contr)}, '{$dia($limite)}', '$estado',
            {$S($fAntes)}, {$S($fDesp)}, 'inspeccion', 'Valeria Sotomayor', " . ($estado === 'cerrado' ? "'{$ahora(-2)}'" : 'NULL') . ')');
    }
}

// ---------------------------------------------------------------- seguridad (permisos e incidentes)
if ($tabla('permisos_trabajo')) {
    echo "Seguridad...\n";
    $tipoPropio = 0;
    if ($tabla('permisos_tipos')) {
        $tipoPropio = $sql("INSERT INTO permisos_tipos (proyecto_id, nombre, palabras, medidas_json) VALUES ($pid, 'Montaje de cristal templado',
            'cancelería\ncristal templado\nmuro cortina\nvidrio', " . $S(json_encode([
                'Andamio o plataforma con tarjeta de inspección vigente',
                'Ventosas y equipo de izaje revisados antes de la maniobra',
                'Zona de caída de vidrio delimitada y sin personal',
                'Arnés con doble línea de vida para trabajo en borde',
                'Coordinación con el izaje de la grúa',
            ], JSON_UNESCAPED_UNICODE)) . ')');
    }
    $medidas = fn(array $m) => json_encode(array_map(fn($t) => ['texto' => $t, 'ok' => true], $m), JSON_UNESCAPED_UNICODE);
    $permisos = [
        ['altura', 'Trabajo en altura · fachada oriente', '4.4', 'Fachada oriente', 'Valeria Sotomayor', 'Constructora Meridian', 'Montaje de cancelería con andamio multidireccional.', 'Caída de personas y objetos', -3, 6, 'aprobado', true],
        ['izaje', 'Izaje de trabes de cubierta', '3.5', 'Nave A · cubierta técnica', 'Óscar Villalpando', 'Montajes Metálicos del Norte', 'Izaje de trabes con grúa de 80 toneladas.', 'Caída de carga y atropellamiento', -1, 3, 'aprobado', true],
        ['caliente', 'Soldadura de conexiones en cubierta', '3.5', 'Nave A · cubierta técnica', 'Raúl Espinoza', 'Montajes Metálicos del Norte', 'Soldadura de placas y conexiones de dunnage.', 'Incendio y quemaduras', -8, -6, 'cerrado', false],
        ['electrico', 'Conexión de tablero TD-3', '5.4', 'Nave A · sala eléctrica', 'Iván Trejo', 'Instalaciones del Centro', 'Conexionado y pruebas del tablero TD-3.', 'Contacto eléctrico', 1, 5, 'solicitado', false],
        ['espacio_confinado', 'Limpieza de cisterna', '6.5', 'Cisterna · patio de equipos', 'Iván Trejo', 'Hidrosanitarias del Centro', 'Limpieza interior y revisión de impermeabilización.', 'Atmósfera peligrosa', 4, 6, 'solicitado', false],
        ['general', 'Apoyo de limpieza en cubierta', '3.5', 'Nave A · cubierta', 'Valeria Sotomayor', 'Constructora Meridian', 'Retiro de recortes y empaques.', 'Trabajo general', 2, 4, 'borrador', false],
    ];
    foreach ($permisos as $i => [$tipo, $titulo, $cod, $area, $resp, $contr, $desc, $riesgos, $desde, $hasta, $estado, $medidasOk]) {
        $a = $actId($cod);
        $mp = ['Análisis de riesgos del trabajo realizado', 'EPP básico completo (casco, lentes, calzado de seguridad)'];
        if ($medidasOk && $tipo === 'altura') $mp = ['Personal capacitado y con DC-3 vigente en trabajo en altura', 'Arnés de cuerpo completo con doble línea de vida inspeccionado', 'Puntos de anclaje certificados'];
        if ($tipoPropio && $i === 0) { $tipo = 't' . $tipoPropio; $titulo = 'Montaje de cristal templado · fachada sur'; $area = 'Fachada sur · acceso principal'; $resp = 'Sergio Beltrán'; }
        $sql("INSERT INTO permisos_trabajo (proyecto_id, tipo, nombre_permiso, titulo, actividad_id, area, responsable, contratista, descripcion, riesgos, medidas_json, vigente_desde, vigente_hasta, estado, origen, solicitado_por, resuelto_por, resuelto_en)
            VALUES ($pid, '$tipo', '', {$S($titulo)}, " . ($a ?: 'NULL') . ", {$S($area)}, {$S($resp)}, {$S($contr)}, {$S($desc)}, {$S($riesgos)}, {$S($medidas($mp))}, '{$dia($desde)}', '{$dia($hasta)}', '$estado', 'manual', 'Valeria Sotomayor',
            " . (in_array($estado, ['aprobado', 'rechazado', 'cerrado'], true) ? "'Diego Carranza'" : "''") . ', ' . (in_array($estado, ['aprobado', 'rechazado', 'cerrado'], true) ? "'{$ahora($desde, '08:30:00')}'" : 'NULL') . ')');
    }
    $incidentes = [
        [-12, 'casi_accidente', 'media', 'Nave A · pasillo técnico', '3.4', 'Un trabajador tropezó con tubería de cimbra sin señalizar; no hubo lesión.', 'Se delimitó el área y se reforzó el orden y limpieza.', 'cerrado', 'Raúl Espinoza'],
        [-7, 'acto_inseguro', 'baja', 'Fachada oriente', '4.4', 'Se observó personal sin arnés en la plataforma de andamio.', 'Se detuvo la actividad y se dio plática de refuerzo.', 'cerrado', 'Raúl Espinoza'],
        [-3, 'condicion_insegura', 'alta', 'Patio de equipos · cisterna', '6.5', 'Escalera de acceso a cisterna sin barandal provisional.', 'Se instaló barandal y se restringió el acceso.', 'abierto', 'Fernanda Quiroz'],
        [-1, 'incidente', 'media', 'Nave A · acceso principal', '4.4', 'Corte leve en la mano al manipular vidrio sin guantes anticorte.', 'Atención en botiquín y cambio de guantes; se investiga el procedimiento.', 'abierto', 'Raúl Espinoza'],
    ];
    foreach ($incidentes as [$off, $tipo, $sev, $area, $cod, $desc, $acc, $estado, $reporta]) {
        $a = $actId($cod);
        $sql("INSERT INTO seguridad_incidentes (proyecto_id, fecha, tipo, severidad, area, actividad_id, descripcion, acciones, estado, reportado_por)
            VALUES ($pid, '{$dia($off)}', '$tipo', '$sev', {$S($area)}, " . ($a ?: 'NULL') . ", {$S($desc)}, {$S($acc)}, '$estado', {$S($reporta)})");
    }
}

// ---------------------------------------------------------------- RFI (con láminas del Editor adjuntas)
if ($tabla('rfis')) {
    echo "RFI...\n";
    $rfis = [
        ['RFI-001', 'Interferencia entre charola eléctrica y ducto de enfriamiento en el pasillo de la Nave A', '5.2', 'Diego Carranza', 'Paola Rentería', 'alta', 'abierto', -12, -2, null, 'La charola eléctrica choca con el ducto principal de enfriamiento en el eje C-4. Se requiere definir qué instalación se reubica y con qué pendiente.', null],
        ['RFI-002', 'Detalle de anclaje de la fachada en el borde de losa', '4.4', 'Sergio Beltrán', 'Paola Rentería', 'alta', 'abierto', -6, 4, null, 'El plano de taller propone anclaje químico en el borde de losa; falta confirmar si el refuerzo existente resiste la tensión de arranque por viento.', null],
        ['RFI-003', 'Resistencia de concreto para la losa de cubierta técnica', '3.4', 'Fernanda Quiroz', 'Diego Carranza', 'media', 'respondido', -20, -14, -15, 'Se solicita confirmar si la losa de cubierta mantiene f\'c 350 kg/cm² o cambia a 300 kg/cm² por el peso de las unidades CRAC.', 'Se mantiene f\'c 350 kg/cm² y se agrega un colado de nivelación de 5 cm en la zona de equipos.'],
        ['RFI-004', 'Ruta de fibra óptica por el cuarto MDF-1', '8.2', 'Iván Trejo', 'Lucía Fonseca', 'media', 'respondido', -16, -9, -10, 'La ruta original cruza el cuarto de máquinas; se propone subir por el ducto de servicios hasta el MDF-1.', 'Se aprueba la ruta alterna por el ducto de servicios; se debe dejar reserva de 10 m y etiquetar ambos extremos.'],
        ['RFI-005', 'Cambio de color del piso técnico en pasillos', '9.2', 'Sergio Beltrán', 'Arturo Landa', 'baja', 'cerrado', -30, -24, -25, 'El cliente pidió revisar un tono más claro para los pasillos fríos de las naves.', 'Se aprueba el tono gris claro; el proveedor confirma entrega en 15 días.'],
        ['RFI-006', 'Coordinación de rociadores con trabes de cubierta', '6.4', 'Iván Trejo', 'Fernanda Quiroz', 'critica', 'abierto', -4, 2, null, 'La red de rociadores queda por debajo del plafón en la zona de trabes; se requiere detalle de paso o cambio de ruta.', null],
        ['RFI-007', 'Nivel de desplante de la cisterna contra incendio', '6.5', 'Óscar Villalpando', 'Diego Carranza', 'media', 'cerrado', -40, -34, -35, 'El nivel de desplante del proyecto no coincide con el levantamiento topográfico del sitio.', 'Se ajusta el desplante a -4.80 m respecto del nivel de banqueta; se actualiza el plano de cimentación.'],
    ];
    foreach ($rfis as [$num, $asunto, $cod, $solic, $resp, $prio, $estado, $fecha, $limite, $respFecha, $pregunta, $respuesta]) {
        $a = $actId($cod);
        $sql("INSERT INTO rfis (proyecto_id, numero, asunto, pregunta, respuesta, responsable, solicitante, prioridad, estado, fecha, fecha_limite, fecha_respuesta, actividad_id)
            VALUES ($pid, {$S($num)}, {$S($asunto)}, {$S($pregunta)}, {$S($respuesta)}, {$S($resp)}, {$S($solic)}, '$prio', '$estado', '{$dia($fecha)}', '{$dia($limite)}',
            " . ($respFecha === null ? 'NULL' : "'{$dia($respFecha)}'") . ', ' . ($a ?: 'NULL') . ')');
    }
    if ($tabla('rfi_laminas')) {
        $laminas = $rg("SELECT l.id FROM editor_laminas l JOIN editor_graficos g ON g.id = l.grafico_id WHERE g.proyecto_id = $pid ORDER BY l.id");
        $r1 = (int)($rg("SELECT id FROM rfis WHERE proyecto_id = $pid AND numero = 'RFI-001'")[0]['id'] ?? 0);
        $r2 = (int)($rg("SELECT id FROM rfis WHERE proyecto_id = $pid AND numero = 'RFI-002'")[0]['id'] ?? 0);
        if ($r1 && isset($laminas[0])) $sql("INSERT IGNORE INTO rfi_laminas (rfi_id, lamina_id, orden) VALUES ($r1, " . (int)$laminas[0]['id'] . ', 1)');
        if ($r2 && isset($laminas[1])) $sql("INSERT IGNORE INTO rfi_laminas (rfi_id, lamina_id, orden) VALUES ($r2, " . (int)$laminas[1]['id'] . ', 1)');
    }
}

// ---------------------------------------------------------------- recursos y cuadrillas
if ($tabla('recursos')) {
    echo "Recursos y cuadrillas...\n";
    $personal = [];
    foreach ($rg("SELECT id, nombre FROM personal WHERE proyecto_id = $pid") as $p) $personal[$p['nombre']] = (int)$p['id'];
    $recursos = [
        ['Cuadrilla de estructura', 'cuadrilla', 14, 8.0, null],
        ['Cuadrilla de envolvente', 'cuadrilla', 9, 8.0, null],
        ['Cuadrilla de instalaciones', 'cuadrilla', 7, 8.0, null],
        ['Cuadrilla de sala blanca', 'cuadrilla', 6, 8.0, null],
        ['Supervisión de obra', 'persona', 1, 8.0, 'Valeria Sotomayor'],
        ['Ingeniería de campo', 'persona', 1, 8.0, 'Paola Rentería'],
        ['Seguridad e higiene', 'persona', 1, 8.0, 'Raúl Espinoza'],
        ['Calidad', 'persona', 1, 8.0, 'Fernanda Quiroz'],
    ];
    $rid = [];
    foreach ($recursos as [$nombre, $tipo, $integrantes, $horas, $persona]) {
        $rid[$nombre] = $sql("INSERT INTO recursos (proyecto_id, nombre, tipo, personal_id, integrantes, horas_dia)
            VALUES ($pid, {$S($nombre)}, '$tipo', " . ($persona && isset($personal[$persona]) ? $personal[$persona] : 'NULL') . ", $integrantes, $horas)");
    }
    $asignaciones = [
        ['2.2', 'Cuadrilla de estructura'], ['2.3', 'Cuadrilla de estructura'], ['2.4', 'Cuadrilla de estructura'], ['2.5', 'Cuadrilla de estructura'],
        ['3.2', 'Cuadrilla de estructura'], ['3.3', 'Cuadrilla de estructura'], ['3.4', 'Cuadrilla de estructura'], ['3.5', 'Cuadrilla de estructura'],
        ['4.2', 'Cuadrilla de envolvente'], ['4.3', 'Cuadrilla de envolvente'], ['4.4', 'Cuadrilla de envolvente'],
        ['5.2', 'Cuadrilla de instalaciones'], ['5.3', 'Cuadrilla de instalaciones'], ['5.5', 'Cuadrilla de instalaciones'],
        ['6.3', 'Cuadrilla de instalaciones'], ['6.4', 'Cuadrilla de instalaciones'], ['7.1', 'Cuadrilla de instalaciones'], ['7.3', 'Cuadrilla de instalaciones'],
        ['8.2', 'Cuadrilla de instalaciones'], ['8.3', 'Cuadrilla de instalaciones'],
        ['9.2', 'Cuadrilla de sala blanca'], ['9.3', 'Cuadrilla de sala blanca'], ['9.4', 'Cuadrilla de sala blanca'],
        ['3.4', 'Supervisión de obra'], ['4.4', 'Supervisión de obra'], ['5.4', 'Supervisión de obra'], ['3.5', 'Seguridad e higiene'],
        ['3.4', 'Calidad'], ['4.4', 'Calidad'], ['6.5', 'Ingeniería de campo'],
    ];
    foreach ($asignaciones as [$cod, $recurso]) {
        $a = $actId($cod);
        if ($a && isset($rid[$recurso])) $sql("INSERT IGNORE INTO actividad_recursos (actividad_id, recurso_id, dedicacion) VALUES ($a, " . (int)$rid[$recurso] . ', ' . ($recurso === 'Supervisión de obra' ? 35 : 100) . ')');
    }
}

// ---------------------------------------------------------------- subactividades (y su tabla vinculada de SheetFlow)
if ($tabla('subactividades')) {
    echo "Subactividades...\n";
    $a = $actId('3.4');
    if ($a) {
        $act = Actividad::find($a);
        $base = (int)($act->progreso ?? 50);
        $hitos = [
            ['Cimbra y apuntalamiento de losa de cubierta', -14, -10, 100],
            ['Armado de acero de losa de cubierta', -11, -5, 100],
            ['Instalaciones ahogadas y revisión de calidad', -7, -3, max(0, min(100, $base))],
            ['Colado y curado de losa de cubierta', -2, 2, max(0, min(90, $base - 20))],
        ];
        foreach ($hitos as [$nombre, $ini, $fin, $status]) {
            $sql("INSERT INTO subactividades (actividad_id, nombre, fecha_inicio, fecha_fin, status)
                VALUES ($a, {$S($nombre)}, '{$dia($ini)}', '{$dia($fin)}', " . (int)$status . ')');
        }
        if ($tabla('subactividad_hojas')) {
            try {
                SubactividadHoja::crearParaActividad(Actividad::find($a), $proyecto, $usuarioDemo ?: null);
            } catch (Throwable $x) {
                echo "  ! no se pudo crear la tabla vinculada de SheetFlow: " . $x->getMessage() . "\n";
            }
        }
        Actividad::find($a)->recalcularDesdeSubactividades();
    }
}

// ---------------------------------------------------------------- costos y presupuesto
if ($tabla('costos_partidas') && $tabla('costos_movimientos')) {
    echo "Costos...\n";
    $presupuestos = ['1.0' => 1850000, '2.0' => 14200000, '3.0' => 38400000, '4.0' => 16900000, '5.0' => 12300000,
        '6.0' => 9800000, '7.0' => 14500000, '8.0' => 7600000, '9.0' => 18200000, '10.0' => 4500000];
    foreach ($presupuestos as $cod => $monto) {
        $part = $partidaId($cod);
        if ($part) $sql("REPLACE INTO costos_partidas (partida_id, proyecto_id, presupuesto) VALUES ($part, $pid, $monto)");
    }
    $oc = fn(string $num) => (int)($rg("SELECT id FROM ordenes_cambio WHERE proyecto_id = $pid AND numero = " . $S($num))[0]['id'] ?? 0);
    $movimientos = [
        ['real', -35, '3.0', 'Factura F-1024 · acero de refuerzo Nave B', 'F-1024', 2450000, 'aprobada', $oc('OC-001')],
        ['real', -28, '3.0', 'Estimación 18 · estructura Naves A y B', 'EST-18', 6200000, 'pagada', null],
        ['real', -22, '2.0', 'Factura F-0998 · concreto premezclado cimentación', 'F-0998', 1980000, 'aprobada', null],
        ['real', -18, '4.0', 'Factura F-1012 · cancelería de aluminio (anticipo)', 'F-1012', 3150000, 'aprobada', null],
        ['real', -12, '5.0', 'Factura F-1031 · tableros y cable de media tensión', 'F-1031', 1720000, 'aprobada', $oc('OC-004')],
        ['real', -8, '6.0', 'Factura F-1040 · rociadores y tubería cédula 40', 'F-1040', 890000, 'aprobada', null],
        ['real', -4, '7.0', 'Factura F-1046 · ductería galvanizada Nave A', 'F-1046', 640000, 'pagada', null],
        ['real', -2, '3.0', 'Factura F-1051 · cimbra y apuntalamiento de cubierta', 'F-1051', 580000, 'aprobada', null],
        ['estimacion', -15, '3.0', 'Estimación 19 · estructura (en revisión)', 'EST-19', 7200000, 'borrador', null],
        ['estimacion', -10, '5.0', 'Estimación 20 · instalación eléctrica', 'EST-20', 1450000, 'aprobada', null],
        ['estimacion', -5, '9.0', 'Estimación 21 · acabados técnicos y racks', 'EST-21', 2100000, 'aprobada', null],
    ];
    foreach ($movimientos as [$tipo, $off, $cod, $concepto, $ref, $monto, $estado, $ocId]) {
        $part = $partidaId($cod);
        if (!$part) continue;
        $sql("INSERT INTO costos_movimientos (proyecto_id, partida_id, tipo, fecha, concepto, referencia, monto, estado, orden_cambio_id, usuario)
            VALUES ($pid, $part, '$tipo', '{$dia($off)}', {$S($concepto)}, {$S($ref)}, $monto, '$estado', " . ($ocId ?: 'NULL') . ", 'Valeria Sotomayor')");
    }
}

// ---------------------------------------------------------------- archivo: carpetas, ensamble y adjuntos de submittals
echo "Archivo, Unir documentos y adjuntos...\n";
if ($tabla('archivo_carpetas')) {
    $carpetas = [];
    foreach (['Planos vigentes', 'Documentación contractual', 'Fotografías de avance'] as $nombre) {
        $carpetas[$nombre] = $sql("INSERT IGNORE INTO archivo_carpetas (proyecto_id, nombre) VALUES ($pid, {$S($nombre)})");
        if (!$carpetas[$nombre]) $carpetas[$nombre] = (int)($rg("SELECT id FROM archivo_carpetas WHERE proyecto_id = $pid AND nombre = " . $S($nombre))[0]['id'] ?? 0);
    }
    $cat = fn(string $n) => (int)($rg("SELECT id FROM archivo_categorias WHERE proyecto_id = $pid AND nombre = " . $S($n))[0]['id'] ?? 0);
    if ($cat('Planos')) $sql("UPDATE archivos SET carpeta_id = " . $carpetas['Planos vigentes'] . " WHERE proyecto_id = $pid AND categoria_id = " . $cat('Planos'));
    if ($cat('Contratos y administrativos')) $sql("UPDATE archivos SET carpeta_id = " . $carpetas['Documentación contractual'] . " WHERE proyecto_id = $pid AND categoria_id = " . $cat('Contratos y administrativos'));
    if ($cat('Fotografías de avance')) $sql("UPDATE archivos SET carpeta_id = " . $carpetas['Fotografías de avance'] . " WHERE proyecto_id = $pid AND categoria_id = " . $cat('Fotografías de avance'));
}
if ($tabla('ensambles')) {
    $docId = fn(string $n) => (int)($rg("SELECT id FROM redactor_documentos WHERE proyecto_id = $pid AND nombre = " . $S($n))[0]['id'] ?? 0);
    $arcId = fn(string $n) => (int)($rg("SELECT id FROM archivos WHERE proyecto_id = $pid AND nombre = " . $S($n))[0]['id'] ?? 0);
    $fuentes = [];
    foreach ([['redactor', $docId('Informe mensual de avance · Octubre')], ['redactor', $docId('Memoria descriptiva · Nexo Data Campus')],
        ['redactor', $docId('Procedimiento constructivo · Colado de losa de sala blanca')], ['archivo', $arcId('Plano estructural · Naves A y B')]] as [$t, $id]) {
        if ($id) $fuentes[] = ['t' => $t, 'id' => $id];
    }
    if ($fuentes) {
        $sql("INSERT INTO ensambles (proyecto_id, nombre, fuentes, creado_por) VALUES ($pid, 'Expediente de comisionamiento · Octubre', " . $S(json_encode($fuentes, JSON_UNESCAPED_UNICODE)) . ', ' . ($usuarioDemo ?: 'NULL') . ')');
    }
}
if ($tabla('submittal_laminas')) {
    $laminas = $rg("SELECT l.id FROM editor_laminas l JOIN editor_graficos g ON g.id = l.grafico_id WHERE g.proyecto_id = $pid ORDER BY l.id");
    $subs = $rg("SELECT id FROM submittals WHERE proyecto_id = $pid AND numero_submittal IN ('ALT-DEMO-005','ALT-DEMO-006') ORDER BY numero_submittal");
    if ($laminas) $sql("DELETE FROM submittal_laminas WHERE submittal_id IN (SELECT id FROM submittals WHERE proyecto_id = $pid)");
    foreach ($subs as $i => $s) {
        if (isset($laminas[$i])) $sql("INSERT IGNORE INTO submittal_laminas (submittal_id, lamina_id, orden) VALUES (" . (int)$s['id'] . ', ' . (int)$laminas[$i]['id'] . ', 1)');
    }
}

// ---------------------------------------------------------------- Redactor: plantillas y versiones
if ($tabla('redactor_plantillas')) {
    echo "Redactor (plantillas y versiones)...\n";
    $plantillas = [
        ['Acta de junta semanal', 'Acta con asistentes, temas, acuerdos y compromisos.', '<h1 style="text-align:center">ACTA DE JUNTA SEMANAL</h1><p style="text-align:right">Fecha: {{fecha}}</p><h2>1. Asistentes</h2><table border="1" cellspacing="0" cellpadding="4" width="100%"><tr><th>Nombre</th><th>Empresa</th><th>Puesto</th></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr></table><h2>2. Temas tratados</h2><ol><li>&nbsp;</li></ol><h2>3. Acuerdos y compromisos</h2><table border="1" cellspacing="0" cellpadding="4" width="100%"><tr><th>Acuerdo</th><th>Responsable</th><th>Fecha</th></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr></table>'],
        ['Portada de informe mensual', 'Portada con proyecto, corte y logo del cliente.', '<p style="text-align:center">&nbsp;</p><p style="text-align:center;font-size:20pt;color:#0891b2"><strong>INFORME MENSUAL DE AVANCE</strong></p><p style="text-align:center;font-size:14pt">{{proyecto}}</p><p style="text-align:center">Corte al {{fecha}}</p><p style="text-align:center">&nbsp;</p><p style="text-align:center">Elaboró: {{usuario}}</p>'],
    ];
    foreach ($plantillas as [$nombre, $desc, $html]) {
        $sql("INSERT INTO redactor_plantillas (proyecto_id, nombre, descripcion, papel, orientacion, contenido)
            VALUES ($pid, {$S($nombre)}, {$S($desc)}, 'carta', 'vertical', {$S($html)})");
    }
    if ($tabla('redactor_versiones')) {
        foreach ([['Memoria descriptiva · Nexo Data Campus', 'Versión enviada al cliente', -6], ['Informe mensual de avance · Octubre', 'Corte revisado por supervisión', -1]] as [$doc, $etq, $off]) {
            $d = $rg("SELECT id, contenido FROM redactor_documentos WHERE proyecto_id = $pid AND nombre = " . $S($doc))[0] ?? null;
            if ($d) $sql("INSERT INTO redactor_versiones (documento_id, etiqueta, usuario, contenido, creado)
                VALUES (" . (int)$d['id'] . ", {$S($etq)}, 'Valeria Sotomayor', {$S($d['contenido'])}, '{$ahora($off, '17:40:00')}')");
        }
    }
}

// ---------------------------------------------------------------- vPlan: notas y mapa de avance
echo "vPlan (notas y mapa de avance)...\n";
$laminas = [];
foreach ($rg("SELECT l.id, l.titulo FROM vplan_laminas l JOIN vplan_paquetes p ON p.id = l.paquete_id WHERE l.proyecto_id = $pid AND p.nombre = 'vPlan · Sala blanca y patio de equipos' ORDER BY l.orden") as $l) $laminas[] = $l;
if ($laminas && $tabla('vplan_notas')) {
    $notas = [
        [0, -2, 'Se liberó el armado de acero de la losa de cubierta; falta la revisión de instalaciones ahogadas antes del colado.'],
        [0, -1, 'Retirar el material sobrante del pasillo caliente y proteger el piso técnico recién colocado.'],
        [1, -2, 'La muestra de cristal templado fue aprobada por el cliente; el proveedor confirma entrega en 12 días.'],
        [1, 0, 'Coordinar el retiro del andamio de la fachada oriente con la instalación de cancelería (OC-002).'],
        [2, -1, 'Las soldaduras de la cubierta pasaron la inspección con líquidos penetrantes; no hay hallazgos mayores.'],
    ];
    foreach ($notas as [$idx, $off, $texto]) {
        if (!isset($laminas[$idx])) continue;
        $sql("INSERT INTO vplan_notas (lamina_id, descripcion, fecha) VALUES (" . (int)$laminas[$idx]['id'] . ", {$S($texto)}, '{$ahora($off, '16:20:00')}')");
    }
}
if ($tabla('mapa_avance_capas') && $tabla('mapa_avance_instantaneas')) {
    $mk = fn(string $id, string $type, array $extra) => ['id' => $id, 'type' => $type, 'opacity' => 100, 'rotation' => 0] + $extra;
    $marcas = [
        'estructura' => [
            'layers' => [['id' => 'estructura', 'name' => 'Estructura · cubierta Nave A', 'num' => 1]],
            'shapes' => [
                $mk('me1', 'rect', ['x' => 180, 'y' => 220, 'w' => 420, 'h' => 260, 'fill' => '#22c55e', 'fillAlpha' => 90, 'stroke' => '#22c55e', 'strokeWidth' => 2, 'layer' => 'estructura']),
                $mk('me2', 'rect', ['x' => 640, 'y' => 220, 'w' => 390, 'h' => 260, 'fill' => '#eab308', 'fillAlpha' => 90, 'stroke' => '#eab308', 'strokeWidth' => 2, 'layer' => 'estructura']),
                $mk('me3', 'rect', ['x' => 1080, 'y' => 220, 'w' => 300, 'h' => 260, 'fill' => '#ef4444', 'fillAlpha' => 90, 'stroke' => '#ef4444', 'strokeWidth' => 2, 'layer' => 'estructura']),
                $mk('me4', 'line', ['x' => 0, 'y' => 0, 'points' => [180, 540, 1380, 540], 'stroke' => '#22c55e', 'strokeWidth' => 6, 'layer' => 'estructura']),
            ],
        ],
        'fachada' => [
            'layers' => [['id' => 'fachada', 'name' => 'Patio de equipos', 'num' => 1]],
            'shapes' => [
                $mk('mf1', 'rect', ['x' => 260, 'y' => 300, 'w' => 280, 'h' => 170, 'fill' => '#22c55e', 'fillAlpha' => 90, 'stroke' => '#22c55e', 'strokeWidth' => 2, 'layer' => 'fachada']),
                $mk('mf2', 'rect', ['x' => 580, 'y' => 300, 'w' => 280, 'h' => 170, 'fill' => '#eab308', 'fillAlpha' => 90, 'stroke' => '#eab308', 'strokeWidth' => 2, 'layer' => 'fachada']),
                $mk('mf3', 'rect', ['x' => 900, 'y' => 300, 'w' => 280, 'h' => 170, 'fill' => '#ef4444', 'fillAlpha' => 90, 'stroke' => '#ef4444', 'strokeWidth' => 2, 'layer' => 'fachada']),
                $mk('mf4', 'line', ['x' => 0, 'y' => 0, 'points' => [260, 520, 1180, 520], 'stroke' => '#eab308', 'strokeWidth' => 5, 'layer' => 'fachada']),
            ],
        ],
    ];
    $mapa = [];
    foreach ([2 => 'estructura', 1 => 'fachada'] as $idx => $capa) {
        if (!isset($laminas[$idx])) continue;
        $lid = (int)$laminas[$idx]['id'];
        $json = json_encode(['v' => 2, 'w' => 1920, 'h' => 1080, 'shapes' => $marcas[$capa]['shapes'], 'layers' => $marcas[$capa]['layers'], 'escala' => null], JSON_UNESCAPED_UNICODE);
        $lamina = VplanLamina::find($lid);
        $hash = $lamina ? (string)(Vplan::datos($lamina, $proyecto)['hash'] ?? '') : '';
        $sql("UPDATE vplan_laminas SET marcas_json = {$S($json)}, marcas_estados_hash = {$S($hash)} WHERE id = $lid AND proyecto_id = $pid");
        $mapa[] = [$lid, $capa];
    }
    $sql("REPLACE INTO mapa_avance_ajustes (proyecto_id, rojo, amarillo, verde) VALUES ($pid, 0, 55, 100)");
    foreach ($mapa as [$lid, $capa]) {
        foreach (['3.4', '3.5'] as $cod) {
            $a = $actId($cod);
            if ($a) $sql("INSERT IGNORE INTO mapa_avance_capas (proyecto_id, lamina_id, capa_id, actividad_id) VALUES ($pid, $lid, '$capa', $a)");
        }
    }
    $calc = MapaAvance::calcular($proyecto);
    if ($calc['actividades']) {
        MapaAvance::instantanea($proyecto, $calc, $dia(0));
        foreach ([-1, -2, -3] as $atras) {
            foreach ($calc['actividades'] as $aid => $x) {
                foreach ($x['laminas'] as $l) {
                    if ($l['pct'] === null) continue;
                    $pct = max(0, min(100, round((float)$l['pct'] - $atras * 1.5, 2)));
                    $detalle = json_encode(['rojo' => $l['rojo'], 'amarillo' => $l['amarillo'], 'verde' => $l['verde']], JSON_UNESCAPED_UNICODE);
                    $sql("REPLACE INTO mapa_avance_instantaneas (proyecto_id, lamina_id, actividad_id, fecha, porcentaje, peso, detalle_json)
                        VALUES ($pid, " . (int)$l['lamina'] . ', ' . (int)$aid . ", '{$dia(-$atras)}', $pct, " . round((float)$l['peso'], 2) . ", {$S($detalle)})");
                }
            }
        }
    }
}

// ---------------------------------------------------------------- Lookahead configurable
if ($tabla('lookahead_config')) {
    $sql("INSERT INTO lookahead_config (proyecto_id, nombre, pta_nombre, semanas, titulo)
        VALUES ($pid, 'Lookahead', 'PTA', 6, 'Próximas {semanas} semanas · {proyecto}')");
}

// ---------------------------------------------------------------- campos personalizados y diseño de listas
if ($tabla('campos_personalizados')) {
    echo "Campos personalizados...\n";
    $campos = [
        ['actividad', 'Zona', 'lista', "Sala blanca\nNave A\nNave B\nPatio de equipos\nSubestación\nCubierta", 0],
        ['actividad', 'Frente', 'lista', "Norte\nSur\nOriente\nPoniente", 1],
        ['actividad', 'Nave', 'texto', null, 2],
        ['punch', 'Especialidad', 'lista', "Estructura\nInstalaciones\nSala blanca\nFachada\nSeguridad", 0],
        ['submittal', 'Contratista', 'texto', null, 0],
        ['reporte', 'Cuadrilla', 'texto', null, 0],
    ];
    $campoId = [];
    foreach ($campos as [$entidad, $nombre, $tipo, $opciones, $orden]) {
        $id = $sql("INSERT INTO campos_personalizados (proyecto_id, entidad, nombre, tipo, opciones, orden)
            VALUES ($pid, '$entidad', {$S($nombre)}, '$tipo', " . ($opciones === null ? 'NULL' : $S($opciones)) . ", $orden)");
        $campoId[$entidad . ':' . $nombre] = $id;
    }
    $valor = fn(int $campo, int $registro, string $v) => $campo && $registro ? $sql("REPLACE INTO campos_valores (campo_id, registro_id, valor) VALUES ($campo, $registro, {$S($v)})") : 0;
    foreach ([['3.4', 'Cubierta', 'Oriente', 'Nave A'], ['4.4', 'Nave A', 'Oriente', 'Nave A'], ['5.2', 'Nave A', 'Norte', 'Nave A'],
        ['6.4', 'Nave B', 'Sur', 'Nave B'], ['7.1', 'Nave A', 'Oriente', 'Nave A']] as [$cod, $nivel, $frente, $nave]) {
        $a = $actId($cod);
        $valor($campoId['actividad:Zona'], $a, $nivel);
        $valor($campoId['actividad:Frente'], $a, $frente);
        $valor($campoId['actividad:Nave'], $a, $nave);
    }
    $punch = $rg("SELECT id, categoria FROM punch_items WHERE proyecto_id = $pid");
    foreach ($punch as $p) $valor($campoId['punch:Especialidad'], (int)$p['id'], (string)$p['categoria']);
    $subs = $rg("SELECT id FROM submittals WHERE proyecto_id = $pid LIMIT 4");
    foreach ($subs as $i => $s) $valor($campoId['submittal:Contratista'], (int)$s['id'], ['Constructora Meridian', 'Instalaciones del Centro', 'Cancelería del Centro', 'Aceros del Bajío'][$i % 4]);
    $reps = $rg("SELECT id FROM reportes_diarios WHERE proyecto_id = $pid LIMIT 3");
    foreach ($reps as $i => $r) $valor($campoId['reporte:Cuadrilla'], (int)$r['id'], ['Estructura', 'Instalaciones', 'Sala blanca'][$i % 3]);

    if ($tabla('disenos_lista')) {
        ListaDiseno::guardar($pid, 'punch', [
            ['k' => 'severidad', 'n' => 'Prioridad'], ['k' => 'titulo'], ['k' => 'cp_' . $campoId['punch:Especialidad'], 'n' => 'Especialidad'],
            ['k' => 'ubicacion'], ['k' => 'responsable'], ['k' => 'limite', 'n' => 'Compromiso'], ['k' => 'estado'], ['k' => 'acciones'],
        ]);
        ListaDiseno::guardar($pid, 'bitacora', [
            ['k' => 'fecha'], ['k' => 'departamento', 'n' => 'Área responsable'], ['k' => 'descripcion'], ['k' => 'importancia'], ['k' => 'acciones'],
        ]);
    }
}

// ---------------------------------------------------------------- calidad, equipo, contratos y cierre
$archivoDemo = $tabla('archivos') ? (int)($rg("SELECT id FROM archivos WHERE proyecto_id = $pid ORDER BY id LIMIT 1")[0]['id'] ?? 0) : 0;

if ($tabla('calidad_planes')) {
    echo "Calidad e inspecciones...\n";
    $actLosa = $actId('2.4') ?: 'NULL';
    $actAcero = $actId('3.5') ?: 'NULL';

    $c1 = $sql("INSERT INTO calidad_planes (proyecto_id, actividad_id, codigo, titulo, tipo, norma, responsable, fecha, estado, notas)
        VALUES ($pid, $actLosa, 'ITP-01', 'Cimentación: losa técnica y zapatas', 'itp', 'NTC-Cimentaciones / ACI 318', 'Valeria Sotomayor', '{$dia(-18)}', 'no_conforme',
        'Plan de inspección de la losa de sala blanca y zapatas. Incluye revisión de acero, colado y curado.')");
    $sql("INSERT INTO calidad_puntos (plan_id, orden, descripcion, criterio, resultado, observacion, fecha, responsable, resuelto) VALUES
        ($c1, 1, 'Replanteo y niveles', '±5 mm respecto a eje', 'cumple', '', '{$dia(-18)}', 'Valeria Sotomayor', 1),
        ($c1, 2, 'Armado de acero según plano estructural', 'Separación y recubrimiento según proyecto', 'cumple', '', '{$dia(-17)}', 'Valeria Sotomayor', 1),
        ($c1, 3, 'Resistencia del concreto', 'f''c ≥ 250 kg/cm² a 28 días', 'cumple', 'Resultado de laboratorio: 285 kg/cm².', '{$dia(-15)}', 'Laboratorio externo', 1),
        ($c1, 4, 'Impermeabilización de juntas y colados', 'Sin escurrimientos ni filtraciones', 'no_cumple', 'Se detectó filtración en junta fría del eje C-4; se programó resane e impermeabilización.', '{$dia(-13)}', 'Valeria Sotomayor', 0),
        ($c1, 5, 'Curado y protección de la losa', '7 días con humedad constante', 'cumple', '', '{$dia(-14)}', 'Valeria Sotomayor', 1)");

    $c2 = $sql("INSERT INTO calidad_planes (proyecto_id, actividad_id, codigo, titulo, tipo, norma, responsable, fecha, estado, notas)
        VALUES ($pid, $actAcero, 'ITP-02', 'Estructura metálica de cubierta y dunnage', 'itp', 'AWS D1.1 / especificación estructural', 'Fernanda Quiroz', '{$dia(-6)}', 'conforme',
        'Revisión de soldaduras, torque de tornillería y alineación de la estructura metálica.')");
    $sql("INSERT INTO calidad_puntos (plan_id, orden, descripcion, criterio, resultado, observacion, fecha, responsable, resuelto) VALUES
        ($c2, 1, 'Revisión visual de soldaduras', 'Sin grietas, porosidad ni socavación', 'cumple', '', '{$dia(-6)}', 'Fernanda Quiroz', 1),
        ($c2, 2, 'Torque de tornillería estructural', 'Según ficha del fabricante', 'cumple', 'Muestreo de 20 uniones, todas conformes.', '{$dia(-5)}', 'Fernanda Quiroz', 1),
        ($c2, 3, 'Alineación y verticalidad', 'Tolerancia ±10 mm', 'cumple', '', '{$dia(-5)}', 'Topografía', 1)");

    $c3 = $sql("INSERT INTO calidad_planes (proyecto_id, actividad_id, codigo, titulo, tipo, norma, responsable, fecha, estado, notas)
        VALUES ($pid, $actLosa, 'PRU-01', 'Prueba de resistencia del concreto', 'prueba', 'NMX-C-083', 'Laboratorio externo', '{$dia(-15)}', 'conforme',
        'Cilindros de la losa de cimentación, edad 28 días.')");
    $sql("INSERT INTO calidad_puntos (plan_id, orden, descripcion, criterio, resultado, observacion, fecha, responsable, resuelto) VALUES
        ($c3, 1, 'Resistencia a compresión, 28 días', 'f''c ≥ 250 kg/cm²', 'cumple', 'Resultado: 285 kg/cm². Muestra testigo 284 kg/cm².', '{$dia(-15)}', 'Laboratorio externo', 1)");

    // Plan en proceso: unos puntos ya cumplen y otros siguen pendientes
    $actElec = $actId('5.2') ?: 'NULL';
    $c4 = $sql("INSERT INTO calidad_planes (proyecto_id, actividad_id, codigo, titulo, tipo, norma, responsable, fecha, estado, notas)
        VALUES ($pid, $actElec, 'ITP-03', 'Instalaciones eléctricas: canalización en naves', 'itp', 'NOM-001-SEDE / proyecto eléctrico', 'Iván Trejo', '{$dia(-2)}', 'en_proceso',
        'Revisión de canalización, cajas y registros antes de cablear.')");
    $sql("INSERT INTO calidad_puntos (plan_id, orden, descripcion, criterio, resultado, observacion, fecha, responsable, resuelto) VALUES
        ($c4, 1, 'Trazo y ubicación de cajas y registros', 'Según plano eléctrico', 'cumple', '', '{$dia(-2)}', 'Iván Trejo', 1),
        ($c4, 2, 'Fijación y soportería de tubería', 'Cada 1.50 m en tramos horizontales', 'cumple', '', '{$dia(-2)}', 'Iván Trejo', 1),
        ($c4, 3, 'Continuidad de tierra en charolas', 'Sin interrupciones', 'cumple', '', '{$dia(-1)}', 'Iván Trejo', 1),
        ($c4, 4, 'Cajas libres de escombro y con tapa', '100 % de las cajas', 'pendiente', 'Se revisará al terminar el frente oriente.', NULL, '', 0),
        ($c4, 5, 'Identificación de circuitos', 'Etiqueta en cada tablero', 'pendiente', 'Pendiente de recibir planos actualizados.', NULL, '', 0)");

    // Plan pendiente: aún no empieza la revisión
    $actPisos = $actId('9.2') ?: 'NULL';
    $c5 = $sql("INSERT INTO calidad_planes (proyecto_id, actividad_id, codigo, titulo, tipo, norma, responsable, fecha, estado, notas)
        VALUES ($pid, $actPisos, 'ITP-04', 'Sala blanca: piso técnico y recubrimientos', 'itp', 'Especificación de acabados / fabricante', 'Fernanda Quiroz', '{$dia(8)}', 'pendiente',
        'Se revisará cuando el frente esté liberado y el material aprobado.')");
    $sql("INSERT INTO calidad_puntos (plan_id, orden, descripcion, criterio, resultado, observacion, fecha, responsable, resuelto) VALUES
        ($c5, 1, 'Muestra de material aprobada', 'Contra muestra física del cliente', 'pendiente', '', NULL, '', 0),
        ($c5, 2, 'Nivel y planicidad del piso técnico', '±3 mm en 3 m', 'pendiente', '', NULL, '', 0),
        ($c5, 3, 'Juntas y alineación de placas', 'Sin escalonamientos visibles', 'pendiente', '', NULL, '', 0),
        ($c5, 4, 'Limpieza y protección final', 'Sin manchas ni golpes', 'pendiente', '', NULL, '', 0)");

    // Plan cerrado: se conserva como historial aunque ya terminó
    $actCubierta = $actId('4.5') ?: 'NULL';
    $c6 = $sql("INSERT INTO calidad_planes (proyecto_id, actividad_id, codigo, titulo, tipo, norma, responsable, fecha, estado, notas)
        VALUES ($pid, $actCubierta, 'ITP-05', 'Impermeabilización de cubierta técnica', 'itp', 'Ficha técnica del impermeabilizante', 'Fernanda Quiroz', '{$dia(-30)}', 'cerrado',
        'Plan cerrado y archivado como evidencia de la entrega de la cubierta.')");
    $sql("INSERT INTO calidad_puntos (plan_id, orden, descripcion, criterio, resultado, observacion, fecha, responsable, resuelto) VALUES
        ($c6, 1, 'Limpieza y preparación de la superficie', 'Libre de polvo, grasa y humedad', 'cumple', '', '{$dia(-30)}', 'Fernanda Quiroz', 1),
        ($c6, 2, 'Aplicación de primario y capas', 'Espesor y traslapes según ficha', 'cumple', 'Se aplicaron 3 capas con malla en juntas.', '{$dia(-29)}', 'Fernanda Quiroz', 1),
        ($c6, 3, 'Prueba de encharcamiento 24 h', 'Sin filtraciones', 'cumple', '', '{$dia(-28)}', 'Fernanda Quiroz', 1)");
}

if ($tabla('equipo')) {
    echo "Equipo y maquinaria...\n";
    $eq1 = $sql("INSERT INTO equipo (proyecto_id, nombre, tipo, marca, modelo, serie, propietario, rentado, tarifa_hora, proximo_servicio, proximo_servicio_horas, estado, notas)
        VALUES ($pid, 'Grúa todo terreno Liebherr LTM 1090', 'Grúa', 'Liebherr', 'LTM 1090', 'GT-2025-014', 'Renta Grúas del Centro', 1, 1200.00, '{$dia(6)}', 500, 'operativo',
        'Grúa principal del montaje de cubierta. Servicio preventivo cada 500 horas o 3 meses.')");
    $eq2 = $sql("INSERT INTO equipo (proyecto_id, nombre, tipo, marca, modelo, serie, propietario, rentado, tarifa_hora, proximo_servicio, proximo_servicio_horas, estado, notas)
        VALUES ($pid, 'Retroexcavadora CAT 320', 'Excavadora', 'Caterpillar', '320', 'CAT-320-778', 'Constructora Meridian', 0, 850.00, '{$dia(24)}', 500, 'operativo',
        'Equipo propio para excavación y carga en el patio de equipos.')");
    $eq3 = $sql("INSERT INTO equipo (proyecto_id, nombre, tipo, marca, modelo, serie, propietario, rentado, tarifa_hora, proximo_servicio, proximo_servicio_horas, estado, notas)
        VALUES ($pid, 'Revolvedora 1 saco', 'Revolvedora', 'Torrey', 'MR-1', 'REV-009', 'Constructora Meridian', 0, 180.00, '{$dia(-2)}', 300, 'mantenimiento',
        'En taller por cambio de baleros; se espera de regreso esta semana.')");

    $horas = [
        [$eq1, '3.4', -1, 8, 'Operador grúa', 'Colado de losa de cubierta por franjas.', 9600.00],
        [$eq1, '3.5', -2, 8, 'Operador grúa', 'Izaje de trabes y dunnage.', 9600.00],
        [$eq1, '3.5', -3, 6, 'Operador grúa', 'Montaje de estructura metálica.', 7200.00],
        [$eq2, '2.1', -4, 10, 'Juan Pérez', 'Excavación y mejoramiento de terreno.', 8500.00],
        [$eq2, '1.3', -7, 8, 'Juan Pérez', 'Movimiento de tierras y plataforma.', 6800.00],
        [$eq3, '6.1', -9, 6, 'Cuadrilla de instalaciones', 'Colado de plantillas y dados.', 1080.00],
    ];
    $eq4 = $sql("INSERT INTO equipo (proyecto_id, nombre, tipo, marca, modelo, serie, propietario, rentado, tarifa_hora, proximo_servicio, proximo_servicio_horas, estado, notas)
        VALUES ($pid, 'Bomba de concreto Putzmeister BSF 36', 'Bomba de concreto', 'Putzmeister', 'BSF 36', 'BOM-2025-004', 'Renta de Bombeo del Norte', 1, 1500.00, '{$dia(30)}', 100, 'operativo',
        'Bomba de pluma para colados de losa. Servicio por horas: avisa 20 horas antes del límite.')");
    $eq5 = $sql("INSERT INTO equipo (proyecto_id, nombre, tipo, marca, modelo, serie, propietario, rentado, tarifa_hora, proximo_servicio, proximo_servicio_horas, estado, notas)
        VALUES ($pid, 'Camión volteo 7 m³', 'Camión', 'International', '4300', 'CAM-018', 'Constructora Meridian', 0, 650.00, NULL, NULL, 'baja',
        'Fuera de servicio por transmisión; se reemplazará por renta mientras se repara.')");

    $horas = array_merge($horas, [
        [$eq4, '2.4', -5, 6, 'Operador de bomba', 'Colado de losa de sala blanca, frente A-B.', 9000.00],
        [$eq4, '3.4', -1, 6, 'Operador de bomba', 'Colado de losa de cubierta técnica.', 9000.00],
        [$eq2, '2.5', -1, 6, 'Juan Pérez', 'Excavación para cimentación de equipos.', 5100.00],
    ]);
    foreach ($horas as [$eq, $cod, $off, $h, $operador, $obs, $importe]) {
        $act = $actId($cod) ?: 'NULL';
        $sql("INSERT INTO equipo_horas (proyecto_id, equipo_id, actividad_id, fecha, horas, operador, observaciones, importe)
            VALUES ($pid, $eq, $act, '{$dia($off)}', {$N($h)}, {$S($operador)}, {$S($obs)}, {$N($importe)})");
    }
}

if ($tabla('contratos')) {
    echo "Contratos, fianzas y seguros...\n";
    $contratos = [
        ['contrato', 'CT-2025-014', 'Nexo Digital', 'Contrato de obra: campus de centro de datos Tier III', 48500000, 'MXN', -190, 120, 30, 'vigente', 'Contrato principal con el cliente.'],
        ['fianza', 'FZ-2025-031', 'Aseguradora del Bajío', 'Fianza de cumplimiento del contrato de obra', 2425000, 'MXN', -190, 12, 30, 'vigente', 'Renovar antes del vencimiento para no detener estimaciones.'],
        ['seguro', 'SRC-2025-118', 'Seguros Atlas', 'Responsabilidad civil y daños a terceros', 850000, 'MXN', -365, -5, 30, 'vigente', 'Póliza vencida: solicitar endoso de renovación.'],
        ['licencia', 'LIC-2025-009', 'Municipio de Querétaro', 'Licencia de construcción y uso de suelo', 0, 'MXN', -210, 200, 15, 'vigente', 'Vigente hasta el siguiente año; mantener copia en el Archivo.'],
        ['fianza', 'FZ-2025-045', 'Afianzadora del Occidente', 'Fianza de anticipo del contrato de obra', 4850000, 'MXN', -190, 25, 30, 'vigente', 'Vence pronto: solicitar renovación para no afectar estimaciones.'],
        ['contrato', 'CT-2025-021', 'Clima Integral del Norte', 'Subcontrato de suministro e instalación de enfriamiento', 6200000, 'MXN', -120, 90, 30, 'vigente', 'Incluye balanceo y puesta en marcha.'],
        ['contrato', 'CT-2024-008', 'Demoliciones del Norte', 'Contrato de demolición y retiro de escombro', 850000, 'MXN', -400, -60, 30, 'cerrado', 'Cerrado y liquidado; se conserva como historial.'],
    ];
    foreach ($contratos as $i => [$tipo, $num, $contra, $objeto, $monto, $mon, $ini, $fin, $aviso, $estado, $notas]) {
        $arch = ($i === 0 && $archivoDemo) ? $archivoDemo : 'NULL';
        $sql("INSERT INTO contratos (proyecto_id, tipo, numero, contraparte, objeto, monto, moneda, inicio, vencimiento, aviso_dias, estado, archivo_id, notas)
            VALUES ($pid, '$tipo', '$num', {$S($contra)}, {$S($objeto)}, {$N($monto)}, '$mon', '{$dia($ini)}', '{$dia($fin)}', $aviso, '$estado', $arch, {$S($notas)})");
    }
}

if ($tabla('cierre_items')) {
    echo "Cierre de obra...\n";
    $sql("INSERT INTO cierre_obra (proyecto_id, fecha_entrega, responsable, notas) VALUES ($pid, '{$dia(45)}', 'Valeria Sotomayor', 'Entrega-recepción prevista al término de las pruebas integrales y la limpieza final.')
        ON DUPLICATE KEY UPDATE fecha_entrega = VALUES(fecha_entrega), responsable = VALUES(responsable), notas = VALUES(notas)");
    $items = [
        ['as_built', 'Planos as-built de estructura y arquitectura', 'Fernanda Quiroz', -10, 'completado', 'Revisados y firmados por el cliente.'],
        ['as_built', 'Planos as-built de instalaciones eléctricas e hidrosanitarias', 'Iván Trejo', -2, 'completado', 'Incluyen detalles de subestación y cuartos técnicos.'],
        ['as_built', 'Planos as-built de enfriamiento y seguridad electrónica', 'Paola Rentería', 5, 'en_proceso', 'Falta actualizar la última revisión de ductería en cubierta.'],
        ['garantia', 'Garantías de equipos y materiales', 'Valeria Sotomayor', 20, 'en_proceso', 'Se tienen 18 de 26 cartas de garantía; faltan chillers y tableros.'],
        ['garantia', 'Fianzas de vicios ocultos y cumplimiento', 'Diego Carranza', 30, 'pendiente', 'Se solicitan al área legal al cerrar el contrato.'],
        ['manual', 'Manuales de operación y mantenimiento', 'Paola Rentería', 15, 'pendiente', 'El fabricante de chillers entregará el manual en dos semanas.'],
        ['manual', 'Capacitación al personal del cliente', 'Valeria Sotomayor', 38, 'pendiente', 'Agendar con el cliente para la última semana de obra.'],
        ['punch', 'Punch list cerrado y firmado', 'Fernanda Quiroz', 25, 'pendiente', 'Quedan pendientes de acabados en Naves A y B.'],
        ['documento', 'Actas de entrega-recepción firmadas', 'Diego Carranza', 45, 'pendiente', 'Se firmarán en la junta de entrega con el cliente.'],
        ['documento', 'Certificados de calidad y pruebas integrales', 'Fernanda Quiroz', -1, 'completado', 'Pruebas de sistemas y certificados de laboratorio archivados.'],
    ];
    foreach ($items as $i => [$cat, $titulo, $resp, $off, $estado, $notas]) {
        $sql("INSERT INTO cierre_items (proyecto_id, categoria, titulo, responsable, fecha_compromiso, estado, notas, orden)
            VALUES ($pid, '$cat', {$S($titulo)}, {$S($resp)}, '{$dia($off)}', '$estado', {$S($notas)}, " . ($i + 1) . ")");
    }
}

// ---------------------------------------------------------------- historial de cambios (ejemplos manuales)
if ($tabla('auditoria_cambios')) {
    $historial = [
        [-3, 'cambios', 'modificado', 'aceptado', 'orden', 0, 'OC-003', 'Demo · Se actualizó el impacto en costo de la OC-003', 'Valeria Sotomayor'],
        [-2, 'programa', 'modificado', 'pendiente', 'actividad', $actId('3.4'), 'Losa de cubierta técnica', 'Demo · Se movió 2 días el inicio de «Losa de cubierta técnica»', 'Valeria Sotomayor'],
        [-1, 'punch', 'creado', 'aceptado', 'punch', 0, 'Vidrio con sello deficiente en fachada oriente', 'Demo · Se registró el pendiente «Vidrio con sello deficiente en fachada oriente»', 'Fernanda Quiroz'],
        [0, 'rfi', 'creado', 'pendiente', 'rfi', 0, 'RFI-006', 'Demo · Se abrió el RFI-006 de coordinación de rociadores', 'Iván Trejo'],
    ];
    foreach ($historial as [$off, $herramienta, $evento, $estado, $entidad, $entidadId, $nombre, $resumen, $usuario]) {
        $sql("INSERT INTO auditoria_cambios (proyecto_id, origen, herramienta, evento, estado, estado_en, estado_por, accion, entidad_tipo, entidad_id, entidad_nombre, resumen, modelo, usuario, creado)
            VALUES ($pid, 'manual', '$herramienta', '$evento', '$estado', " . ($estado === 'pendiente' ? 'NULL' : "'{$ahora($off, '18:10:00')}'") . ', ' . ($estado === 'pendiente' ? "''" : "'Diego Carranza'") . ",
            'manual', '$entidad', " . ($entidadId ?: 'NULL') . ", {$S($nombre)}, {$S($resumen)}, '', {$S($usuario)}, '{$ahora($off, '17:55:00')}')");
    }
}

echo "Herramientas del demo listas.\n";
