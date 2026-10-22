<?php

namespace Classes;

/**
 * Enlaces de solo lectura de un proyecto, sin crear usuario (tabla `enlaces_proyecto`):
 *  - `cliente`: página temporal (/cliente?t=TOKEN) con avance, fotos y reportes; caduca y se puede revocar.
 *  - `ics`: suscripción de calendario (/calendario/ics?t=TOKEN) para el celular; no caduca, se revoca para cortarla.
 * El token son 160 bits al azar; en la URL viaja tal cual. Nada de lo que muestran lleva costos ni datos personales.
 */
class Enlaces
{
    use AccesoDb;

    public const SECCIONES = ['avance' => 'Avance del programa', 'fotos' => 'Fotos de evidencia', 'reportes' => 'Reportes diarios'];
    public const DIAS = [7 => '7 días', 15 => '15 días', 30 => '30 días', 90 => '90 días'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query("SELECT 1 FROM enlaces_proyecto LIMIT 1");
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function crear(int $pid, string $tipo, string $nombre, array $secciones, int $dias, string $usuario): ?string
    {
        if (!in_array($tipo, ['cliente', 'ics'], true)) return 'Tipo de enlace no válido';
        $sec = array_values(array_intersect(array_keys(self::SECCIONES), $secciones));
        if ($tipo === 'cliente' && !$sec) return 'Elige al menos una sección para el cliente';
        $token = bin2hex(random_bytes(20));
        $expira = $tipo === 'cliente' && isset(self::DIAS[$dias]) ? "DATE_ADD(NOW(), INTERVAL $dias DAY)" : 'NULL';
        if ($tipo === 'cliente' && !isset(self::DIAS[$dias])) return 'Elige cuánto debe durar el enlace';
        self::db()->query("INSERT INTO enlaces_proyecto SET proyecto_id = $pid, tipo = '$tipo', token = '$token', nombre = '" . self::esc(mb_substr(trim($nombre), 0, 150)) . "',
            secciones = '" . self::esc(implode(',', $tipo === 'cliente' ? $sec : [])) . "', expira = $expira, creado_por = '" . self::esc(mb_substr($usuario, 0, 150)) . "'");
        return null;
    }

    public static function revocar(int $pid, int $id): void
    {
        self::db()->query("UPDATE enlaces_proyecto SET revocado = 1 WHERE id = $id AND proyecto_id = $pid");
    }

    public static function lista(int $pid, string $tipo): array
    {
        $rows = self::filas("SELECT *, (expira IS NOT NULL AND expira < NOW()) caducado FROM enlaces_proyecto WHERE proyecto_id = $pid AND tipo = '" . self::esc($tipo) . "' ORDER BY revocado, id DESC");
        foreach ($rows as &$r) $r['activo'] = !(int)$r['revocado'] && !(int)$r['caducado'];
        return $rows;
    }

    /** El enlace vigente de ese token y tipo (null si no existe, caducó o fue revocado). Anota el acceso */
    public static function resolver(string $token, string $tipo): ?array
    {
        if (!preg_match('/^[0-9a-f]{40}$/', $token)) return null;
        $e = self::filas("SELECT * FROM enlaces_proyecto WHERE token = '$token' AND tipo = '" . self::esc($tipo) . "' AND revocado = 0 AND (expira IS NULL OR expira > NOW())")[0] ?? null;
        if ($e) self::db()->query('UPDATE enlaces_proyecto SET accesos = accesos + 1, ultimo_acceso = NOW() WHERE id = ' . (int)$e['id']);
        return $e;
    }

    public static function url(array $e): string
    {
        $base = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $base . ($e['tipo'] === 'ics' ? '/calendario/ics' : '/cliente') . '?t=' . $e['token'];
    }

    // ---------- contenido de la página del cliente ----------

    /** Avance por partida (% pesado por la duración de sus actividades) y general */
    public static function avancePartidas(int $pid): array
    {
        $out = [];
        $tot = $acum = 0.0;
        foreach (self::filas("SELECT p.id, p.codigo, p.nombre, p.color_bg color, p.fecha_inicio, p.fecha_fin,
                COALESCE(SUM(IF(a.hito = 0, a.duracion_dias, 0)), 0) peso, COALESCE(SUM(IF(a.hito = 0, a.duracion_dias * a.progreso / 100, 0)), 0) hecho, COUNT(a.id) acts
            FROM partidas p LEFT JOIN actividades a ON a.partida_id = p.id WHERE p.proyecto_id = $pid GROUP BY p.id ORDER BY p.orden, p.codigo") as $p) {
            $pct = (float)$p['peso'] > 0 ? round($p['hecho'] / $p['peso'] * 100, 1) : 0.0;
            $out[] = ['codigo' => $p['codigo'], 'nombre' => $p['nombre'], 'color' => $p['color'], 'inicio' => $p['fecha_inicio'], 'fin' => $p['fecha_fin'], 'pct' => $pct, 'acts' => (int)$p['acts']];
            $tot += (float)$p['peso'];
            $acum += (float)$p['hecho'];
        }
        return ['partidas' => $out, 'general' => $tot > 0 ? round($acum / $tot * 100, 1) : 0.0];
    }

    /** Actividades de la semana en curso y las 2 siguientes, e hitos próximos */
    public static function proximas(int $pid): array
    {
        $ini = date('Y-m-d', strtotime('monday this week'));
        $fin = date('Y-m-d', strtotime($ini . ' +20 days'));
        return [
            'actividades' => self::filas("SELECT a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin, a.progreso, p.nombre partida FROM actividades a JOIN partidas p ON p.id = a.partida_id
                WHERE p.proyecto_id = $pid AND a.hito = 0 AND a.progreso < 100 AND a.fecha_inicio <= '$fin' AND a.fecha_fin >= '$ini' ORDER BY a.fecha_inicio, a.codigo LIMIT 40"),
            'hitos' => self::filas("SELECT a.codigo, a.nombre, a.fecha_inicio fecha, a.progreso FROM actividades a JOIN partidas p ON p.id = a.partida_id
                WHERE p.proyecto_id = $pid AND a.hito = 1 AND a.fecha_inicio >= '$ini' ORDER BY a.fecha_inicio LIMIT 10"),
        ];
    }

    /** Fotos recientes: evidencias subidas y fotos de los reportes diarios [['url','fecha','texto']] */
    public static function fotos(int $pid, int $limite = 36): array
    {
        $out = [];
        foreach (self::filas("SELECT foto, fecha, descripcion FROM evidencias WHERE proyecto_id = $pid ORDER BY fecha DESC, id DESC LIMIT $limite") as $f) {
            $out[] = ['url' => '/uploads/evidencias/' . basename($f['foto']), 'fecha' => $f['fecha'], 'texto' => $f['descripcion']];
        }
        foreach (self::filas("SELECT fecha, area, fotos_json FROM reportes_diarios WHERE proyecto_id = $pid ORDER BY fecha DESC, id DESC LIMIT 20") as $r) {
            foreach ((array)json_decode((string)$r['fotos_json'], true) as $f) $out[] = ['url' => '/uploads/reportes/' . basename((string)$f), 'fecha' => $r['fecha'], 'texto' => $r['area']];
        }
        usort($out, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        return array_slice($out, 0, $limite);
    }

    /** Últimos reportes diarios (sin nombres de personal) con las actividades trabajadas */
    public static function reportes(int $pid, int $limite = 15): array
    {
        $out = [];
        foreach (self::filas("SELECT fecha, area, comentarios, actividades_json FROM reportes_diarios WHERE proyecto_id = $pid ORDER BY fecha DESC, id DESC LIMIT $limite") as $r) {
            $ids = array_filter(array_map('intval', (array)json_decode((string)$r['actividades_json'], true)));
            $nombres = $ids ? array_column(self::filas('SELECT a.id, CONCAT(a.codigo, " ", a.nombre) n FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = ' . $pid . ' AND a.id IN (' . implode(',', $ids) . ')'), 'n') : [];
            $out[] = ['fecha' => $r['fecha'], 'area' => $r['area'], 'comentarios' => $r['comentarios'], 'actividades' => $nombres];
        }
        return $out;
    }
}
