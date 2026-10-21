<?php

namespace Classes;

use Model\Proyecto;

/**
 * Seguridad e higiene: permisos de trabajo (altura, trabajos en caliente, espacios confinados, excavación, eléctrico, izaje) con su lista de medidas, vigencia y
 * aprobación del administrador; registro de incidentes; y qué permisos exige cada actividad próxima según su nombre (el sistema avisa si falta). Los registros
 * internos NO sustituyen la firma ni el cumplimiento de la normativa de seguridad aplicable.
 */
class Seguridad
{
    public const TIPOS = [
        'altura' => ['nombre' => 'Trabajo en altura', 'palabras' => ['azotea', 'fachada', 'andamio', 'escalera', 'plataforma de elevacion', 'torre', 'montaje en altura', 'estructura metalica', 'plafon', 'alturas', 'canalizacion en techo', 'colocacion de camaras en poste', 'poste', 'mastil'],
            'medidas' => ['Personal capacitado y con DC-3 vigente en trabajo en altura', 'Arnés de cuerpo completo con doble línea de vida inspeccionado', 'Puntos de anclaje certificados', 'Barandales o líneas de vida instalados', 'Área inferior delimitada y señalizada', 'Clima favorable (sin lluvia ni viento fuerte)', 'Escaleras o plataformas inspeccionadas y estables']],
        'caliente' => ['nombre' => 'Trabajo en caliente', 'palabras' => ['soldadura', 'soldar', 'corte con', 'esmeril', 'oxicorte', 'termofusion', 'cutweld', 'cadweld'],
            'medidas' => ['Extintor de polvo químico a menos de 5 m', 'Área libre de material combustible o protegida con manta ignífuga', 'Vigía de fuego durante y 30 min después', 'Detectores de incendio cercanos aislados o avisados', 'Equipo de soldar inspeccionado y con tierra física', 'EPP: careta, guantes y ropa de algodón']],
        'espacio_confinado' => ['nombre' => 'Espacio confinado', 'palabras' => ['cisterna', 'fosa', 'pozo', 'registro subterraneo', 'ducto subterraneo', 'carcamo', 'tanque', 'cuarto de bombas', 'sotano sin ventilacion'],
            'medidas' => ['Medición de atmósfera (oxígeno, gases) antes y durante', 'Ventilación forzada', 'Vigía permanente en el exterior', 'Plan de rescate y equipo de rescate disponible', 'Comunicación continua con quien entra', 'Personal capacitado en espacios confinados']],
        'excavacion' => ['nombre' => 'Excavación y zanjas', 'palabras' => ['excavacion', 'zanja', 'cimentacion', 'cepa', 'trinchera', 'relleno y compactacion', 'demolicion de piso'],
            'medidas' => ['Localización de instalaciones subterráneas (agua, luz, gas, datos)', 'Taludes o ademes según la profundidad', 'Barreras y señalización alrededor', 'Acceso y salida seguros (escalera) cada 7.5 m', 'Material excavado a más de 60 cm del borde', 'Inspección diaria antes de entrar']],
        'electrico' => ['nombre' => 'Trabajo eléctrico / energizado', 'palabras' => ['energiz', 'tablero', 'subestacion', 'alta tension', 'media tension', 'conexion electrica', 'cuchilla', 'transformador', 'gabinete electrico', 'alimentador', 'conexionado'],
            'medidas' => ['Bloqueo y etiquetado (LOTO) aplicado', 'Verificación de ausencia de tensión', 'EPP dieléctrico y herramienta aislada', 'Personal calificado y autorizado', 'Distancias de seguridad respetadas y área delimitada', 'Comunicación con el responsable de la instalación']],
        'izaje' => ['nombre' => 'Izaje de cargas', 'palabras' => ['grua', 'izaje', 'montacargas', 'elevacion de carga', 'maniobra de carga', 'telehandler', 'polipasto'],
            'medidas' => ['Peso de la carga y capacidad del equipo verificados', 'Operador certificado y señalero designado', 'Aparejos y eslingas inspeccionados', 'Terreno nivelado y estabilizado', 'Zona de maniobra delimitada y sin personal debajo de la carga', 'Viento dentro del límite del fabricante']],
        'general' => ['nombre' => 'Trabajo de riesgo general', 'palabras' => [], 'medidas' => ['Análisis de riesgos del trabajo realizado', 'EPP básico completo (casco, lentes, calzado de seguridad)', 'Personal informado de los riesgos y medidas', 'Área limpia y señalizada']],
    ];
    public const ESTADOS = ['borrador' => 'Borrador', 'solicitado' => 'Solicitado', 'aprobado' => 'Aprobado', 'rechazado' => 'Rechazado', 'cerrado' => 'Cerrado'];
    public const INCIDENTES = ['incidente' => 'Incidente', 'casi_accidente' => 'Casi accidente', 'acto_inseguro' => 'Acto inseguro', 'condicion_insegura' => 'Condición insegura'];

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    private static function filas(string $sql): array
    {
        try {
            $r = self::db()->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function esc($v): string
    {
        return self::db()->real_escape_string((string)$v);
    }

    /** Tipos de permiso que exige el nombre de una actividad (según palabras clave) */
    public static function tiposRequeridos(string $nombre, string $descripcion = ''): array
    {
        $t = Voz::sinAcentos($nombre . ' ' . $descripcion);
        $out = [];
        foreach (self::TIPOS as $k => $d) foreach ($d['palabras'] as $w) if ($w !== '' && str_contains($t, $w)) { $out[] = $k; break; }
        return $out;
    }

    /** Medidas del tipo como lista [{texto, ok}] lista para guardar */
    public static function medidas(string $tipo, array $extra = []): array
    {
        $base = self::TIPOS[$tipo]['medidas'] ?? self::TIPOS['general']['medidas'];
        return array_map(fn($t) => ['texto' => $t, 'ok' => false], array_values(array_unique(array_merge($base, array_filter(array_map('trim', $extra))))));
    }

    // ---------- permisos ----------

    public static function listarPermisos(int $pid, string $estado = ''): array
    {
        $w = "p.proyecto_id = $pid" . (isset(self::ESTADOS[$estado]) ? " AND p.estado = '$estado'" : '');
        $f = self::filas("SELECT p.*, a.codigo act_codigo, a.nombre act_nombre FROM permisos_trabajo p LEFT JOIN actividades a ON a.id = p.actividad_id WHERE $w ORDER BY FIELD(p.estado,'solicitado','borrador','aprobado','rechazado','cerrado'), p.vigente_hasta DESC, p.id DESC LIMIT 300");
        $hoy = date('Y-m-d');
        foreach ($f as &$x) {
            $x['medidas'] = json_decode((string)$x['medidas_json'], true) ?: [];
            $x['vencido'] = $x['estado'] === 'aprobado' && $x['vigente_hasta'] < $hoy;
            $x['por_vencer'] = $x['estado'] === 'aprobado' && !$x['vencido'] && $x['vigente_hasta'] <= date('Y-m-d', strtotime('+2 days'));
        }
        return $f;
    }

    public static function obtenerPermiso(int $pid, int $id): ?array
    {
        $f = self::filas("SELECT p.*, a.codigo act_codigo, a.nombre act_nombre FROM permisos_trabajo p LEFT JOIN actividades a ON a.id = p.actividad_id WHERE p.id = $id AND p.proyecto_id = $pid")[0] ?? null;
        if ($f) $f['medidas'] = json_decode((string)$f['medidas_json'], true) ?: [];
        return $f;
    }

    /** @return array{0: bool, 1: string, 2: int} */
    public static function crearPermiso(int $pid, array $d, string $origen = 'manual', string $estado = 'solicitado'): array
    {
        $tipo = isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'general';
        $titulo = trim(mb_substr((string)($d['titulo'] ?? ''), 0, 200));
        $desde = \DateTime::createFromFormat('Y-m-d', (string)($d['vigente_desde'] ?? ''));
        $hasta = \DateTime::createFromFormat('Y-m-d', (string)($d['vigente_hasta'] ?? ''));
        if ($titulo === '') return [false, 'El título es obligatorio', 0];
        if (!$desde || !$hasta || $hasta < $desde) return [false, 'Las fechas de vigencia no son válidas (la final debe ser igual o posterior a la inicial).', 0];
        $aid = (int)($d['actividad_id'] ?? 0);
        if ($aid && !self::filas("SELECT 1 FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $aid AND pa.proyecto_id = $pid")) $aid = 0;
        $medidas = $d['medidas'] ?? self::medidas($tipo);
        $medidas = array_values(array_map(fn($m) => ['texto' => mb_substr(trim((string)($m['texto'] ?? $m)), 0, 250), 'ok' => !empty($m['ok'])], array_filter((array)$medidas, fn($m) => trim((string)($m['texto'] ?? $m)) !== '')));
        $db = self::db();
        $db->query("INSERT INTO permisos_trabajo (proyecto_id, tipo, titulo, actividad_id, area, responsable, contratista, descripcion, riesgos, medidas_json, vigente_desde, vigente_hasta, estado, origen, solicitado_por) VALUES ($pid, '$tipo', '" . self::esc($titulo) . "', " . ($aid ?: 'NULL') . ", '" . self::esc(mb_substr((string)($d['area'] ?? ''), 0, 150)) . "', '" . self::esc(mb_substr((string)($d['responsable'] ?? ''), 0, 150)) . "', '" . self::esc(mb_substr((string)($d['contratista'] ?? ''), 0, 150)) . "', '"
            . self::esc(mb_substr((string)($d['descripcion'] ?? ''), 0, 3000)) . "', '" . self::esc(mb_substr((string)($d['riesgos'] ?? ''), 0, 3000)) . "', '" . self::esc(json_encode($medidas, JSON_UNESCAPED_UNICODE)) . "', '{$desde->format('Y-m-d')}', '{$hasta->format('Y-m-d')}', '" . (isset(self::ESTADOS[$estado]) ? $estado : 'solicitado') . "', '" . ($origen === 'ia' ? 'ia' : 'manual') . "', '" . self::esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150)) . "')");
        return [true, 'Permiso registrado.', (int)$db->insert_id];
    }

    /** Cambia el estado (solo admin aprueba/rechaza). Marca quién y cuándo. Todo queda en el historial de cambios. */
    public static function cambiarEstado(Proyecto $p, int $id, string $estado, bool $esAdmin): array
    {
        $pid = (int)$p->id;
        $x = self::obtenerPermiso($pid, $id);
        if (!$x || !isset(self::ESTADOS[$estado])) return [false, 'Permiso no encontrado'];
        if (in_array($estado, ['aprobado', 'rechazado'], true) && !$esAdmin) return [false, 'Solo el administrador aprueba o rechaza permisos'];
        if ($estado === 'aprobado') {
            $pend = array_filter($x['medidas'], fn($m) => empty($m['ok']));
            if ($pend && empty($_POST['forzar'])) return [false, 'Faltan ' . count($pend) . ' medida(s) de seguridad por marcar como verificadas. Márcalas antes de aprobar.'];
        }
        $quien = self::esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150));
        self::db()->query("UPDATE permisos_trabajo SET estado = '$estado', resuelto_por = '$quien', resuelto_en = NOW() WHERE id = $id AND proyecto_id = $pid");
        Auditoria::registrar($pid, 'seguridad', in_array($estado, ['rechazado'], true) ? 'rechazado' : 'aplicado', 'permiso_trabajo', 'Permiso «' . mb_substr($x['titulo'], 0, 80) . '»: ' . self::ESTADOS[$x['estado']] . ' → ' . self::ESTADOS[$estado], ['estado' => $x['estado']], ['estado' => $estado], $id, '', ['permiso', $id, $x['titulo']]);
        return [true, 'Permiso ' . mb_strtolower(self::ESTADOS[$estado]) . '.'];
    }

    public static function marcarMedidas(int $pid, int $id, array $ok): bool
    {
        $x = self::obtenerPermiso($pid, $id);
        if (!$x) return false;
        $m = $x['medidas'];
        foreach ($m as $i => &$med) $med['ok'] = !empty($ok[$i]);
        self::db()->query("UPDATE permisos_trabajo SET medidas_json = '" . self::esc(json_encode($m, JSON_UNESCAPED_UNICODE)) . "' WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    public static function eliminarPermiso(int $pid, int $id): void
    {
        self::db()->query("DELETE FROM permisos_trabajo WHERE id = $id AND proyecto_id = $pid AND estado IN ('borrador','rechazado','solicitado')");
    }

    /**
     * Actividades de los próximos `$dias` días que exigen permiso y si lo tienen vigente (aprobado o en trámite) para la fecha de inicio.
     * @return array lista de ['actividad','tipo','permiso'=>?array,'estado'=>'ok'|'tramite'|'falta']
     */
    public static function requeridos(int $pid, int $dias = 14): array
    {
        $hoy = date('Y-m-d');
        $hasta = date('Y-m-d', strtotime("+$dias days"));
        $permisos = self::filas("SELECT id, tipo, actividad_id, estado, vigente_desde, vigente_hasta, titulo FROM permisos_trabajo WHERE proyecto_id = $pid AND estado IN ('solicitado','aprobado','borrador')");
        $out = [];
        foreach (Dependencias::actividades($pid) as $id => $a) {
            if ((int)$a['hito'] || (int)$a['progreso'] >= 100 || $a['fecha_fin'] < $hoy || $a['fecha_inicio'] > $hasta) continue;
            foreach (self::tiposRequeridos($a['nombre']) as $tipo) {
                $cubre = array_values(array_filter($permisos, fn($p) => (int)$p['actividad_id'] === $id && $p['tipo'] === $tipo && $p['vigente_hasta'] >= max($a['fecha_inicio'], $hoy)));
                $apr = array_values(array_filter($cubre, fn($p) => $p['estado'] === 'aprobado'));
                $out[] = ['actividad' => $a, 'tipo' => $tipo, 'permiso' => $apr[0] ?? ($cubre[0] ?? null), 'estado' => $apr ? 'ok' : ($cubre ? 'tramite' : 'falta')];
            }
        }
        usort($out, fn($x, $y) => [['falta' => 0, 'tramite' => 1, 'ok' => 2][$x['estado']], $x['actividad']['fecha_inicio']] <=> [['falta' => 0, 'tramite' => 1, 'ok' => 2][$y['estado']], $y['actividad']['fecha_inicio']]);
        return $out;
    }

    // ---------- incidentes ----------

    public static function registrarIncidente(int $pid, array $d): array
    {
        $f = \DateTime::createFromFormat('Y-m-d', (string)($d['fecha'] ?? ''));
        $desc = trim((string)($d['descripcion'] ?? ''));
        if (!$f || $desc === '') return [false, 'La fecha y la descripción son obligatorias.'];
        $aid = (int)($d['actividad_id'] ?? 0);
        if ($aid && !self::filas("SELECT 1 FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $aid AND pa.proyecto_id = $pid")) $aid = 0;
        self::db()->query("INSERT INTO seguridad_incidentes (proyecto_id, fecha, tipo, severidad, area, actividad_id, descripcion, acciones, reportado_por) VALUES ($pid, '{$f->format('Y-m-d')}', '" . (isset(self::INCIDENTES[$d['tipo'] ?? '']) ? $d['tipo'] : 'incidente') . "', '" . (in_array($d['severidad'] ?? '', ['baja', 'media', 'alta', 'critica'], true) ? $d['severidad'] : 'media') . "', '"
            . self::esc(mb_substr((string)($d['area'] ?? ''), 0, 150)) . "', " . ($aid ?: 'NULL') . ", '" . self::esc(mb_substr($desc, 0, 3000)) . "', '" . self::esc(mb_substr((string)($d['acciones'] ?? ''), 0, 3000)) . "', '" . self::esc(mb_substr(usuario_actual()['nombre'] ?? '', 0, 150)) . "')");
        return [true, 'Incidente registrado.'];
    }

    public static function incidentes(int $pid, int $limite = 200): array
    {
        return self::filas("SELECT i.*, a.codigo act_codigo FROM seguridad_incidentes i LEFT JOIN actividades a ON a.id = i.actividad_id WHERE i.proyecto_id = $pid ORDER BY i.estado = 'cerrado', i.fecha DESC, i.id DESC LIMIT $limite");
    }

    public static function cerrarIncidente(int $pid, int $id, string $acciones): bool
    {
        self::db()->query("UPDATE seguridad_incidentes SET estado = 'cerrado', acciones = CONCAT(COALESCE(acciones, ''), IF(acciones IS NULL OR acciones = '', '', '\n'), '" . self::esc(mb_substr($acciones, 0, 1000)) . "') WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    public static function resumen(int $pid): array
    {
        $c = fn(string $sql) => (int)(self::filas($sql)[0]['n'] ?? 0);
        $hoy = date('Y-m-d');
        return ['vigentes' => $c("SELECT COUNT(*) n FROM permisos_trabajo WHERE proyecto_id = $pid AND estado = 'aprobado' AND vigente_hasta >= '$hoy'"), 'por_aprobar' => $c("SELECT COUNT(*) n FROM permisos_trabajo WHERE proyecto_id = $pid AND estado = 'solicitado'"),
            'vencidos' => $c("SELECT COUNT(*) n FROM permisos_trabajo WHERE proyecto_id = $pid AND estado = 'aprobado' AND vigente_hasta < '$hoy'"), 'incidentes_abiertos' => $c("SELECT COUNT(*) n FROM seguridad_incidentes WHERE proyecto_id = $pid AND estado = 'abierto'"),
            'faltan' => count(array_filter(self::requeridos($pid, 7), fn($x) => $x['estado'] === 'falta'))];
    }

    public static function pdfPermiso(Proyecto $p, array $x): string
    {
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $fmt = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
        $med = implode('', array_map(fn($m) => '<tr><td style="width:18px;text-align:center">' . (!empty($m['ok']) ? '☑' : '☐') . '</td><td>' . $e($m['texto']) . '</td></tr>', $x['medidas']));
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:10px;color:#111}h1{font-size:16px;margin:0}h2{font-size:11px;color:#555;margin:2px 0 12px;font-weight:normal}table{width:100%;border-collapse:collapse;margin:4px 0 10px}td,th{border:1px solid #999;padding:4px 6px;vertical-align:top;text-align:left}th{background:#eee;width:28%}.firmas td{height:55px;text-align:center;vertical-align:bottom;width:33%}</style></head><body>'
            . '<h1>Permiso de trabajo · ' . $e(self::TIPOS[$x['tipo']]['nombre']) . '</h1><h2>' . $e($p->nombre) . ' · folio #' . (int)$x['id'] . ' · estado: ' . $e(self::ESTADOS[$x['estado']]) . '</h2>'
            . '<table><tr><th>Trabajo</th><td>' . $e($x['titulo']) . '</td></tr><tr><th>Actividad</th><td>' . $e(($x['act_codigo'] ?? '') . ' ' . ($x['act_nombre'] ?? '')) . '</td></tr><tr><th>Área</th><td>' . $e($x['area']) . '</td></tr><tr><th>Responsable / contratista</th><td>' . $e($x['responsable'] . ' · ' . $x['contratista']) . '</td></tr>'
            . '<tr><th>Vigencia</th><td>' . $fmt($x['vigente_desde']) . ' al ' . $fmt($x['vigente_hasta']) . '</td></tr><tr><th>Descripción</th><td>' . nl2br($e($x['descripcion'])) . '</td></tr><tr><th>Riesgos</th><td>' . nl2br($e($x['riesgos'])) . '</td></tr></table>'
            . '<b>Medidas de seguridad verificadas</b><table>' . $med . '</table>'
            . '<table class="firmas"><tr><td>Solicitó: ' . $e($x['solicitado_por']) . '<br>______________________<br>Nombre y firma</td><td>Responsable del área<br>______________________<br>Nombre y firma</td><td>Autorizó: ' . $e($x['resuelto_por']) . '<br>______________________<br>Nombre y firma</td></tr></table>'
            . '<p style="color:#666">Documento generado por el sistema; no sustituye la firma autógrafa ni el cumplimiento de la normativa de seguridad aplicable (NOM-009-STPS, NOM-017-STPS, etc.).</p></body></html>';
        return Pdf::generar($html, 'letter', 'portrait');
    }
}
