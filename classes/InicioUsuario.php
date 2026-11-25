<?php

namespace Classes;

/**
 * Inicio personalizable por usuario (Fase 4.5): qué bloques ve en su pantalla de inicio, en qué orden
 * y con qué ancho. Se guarda en usuarios.inicio_json =
 * {"bloques":[{"k":"resumen","on":true,"ancho":12},…],"proyectos_n":6}. Sin configuración (o sin
 * migración) se usa el inicio de siempre: resumen, atención, proyectos y herramientas.
 *
 * Las claves antiguas «hoy» y «atrasadas» se migran en lectura al bloque «atencion», que las muestra
 * juntas con pestañas. No se modifica la base de datos.
 */
class InicioUsuario
{
    use AccesoDb;

    /** clave => [título, descripción] */
    public const BLOQUES = [
        'resumen' => ['Resumen', 'Proyectos, actividades de hoy y atrasadas de un vistazo'],
        'atencion' => ['Requiere atención', 'Actividades de hoy y atrasadas, las más urgentes primero'],
        'proyectos' => ['Proyectos recientes', 'Tarjetas con avance, salud y accesos'],
        'herramientas' => ['Herramientas', 'Accesos a las secciones y herramientas de tus obras'],
        'novedades' => ['Novedades de la plataforma', 'Lo último que se agregó'],
        'atajos' => ['Accesos rápidos', 'Nuevo proyecto, plantillas, manual y novedades'],
    ];

    public const DEFECTO = ['resumen', 'atencion', 'proyectos', 'herramientas'];
    public const CANTIDADES = [3, 6, 12, 24];

    /** Anchos de la rejilla de 12 columnas: clave numérica => etiqueta */
    public const ANCHOS = [12 => 'Completo', 6 => 'Medio', 4 => 'Tercio'];

    public const ANCHO_DEFECTO = [
        'resumen' => 12,
        'atencion' => 12,
        'proyectos' => 12,
        'herramientas' => 4,
        'novedades' => 4,
        'atajos' => 4,
    ];

    /** Claves antiguas que ahora forman un solo bloque. */
    private const LEGADO = ['hoy' => 'atencion', 'atrasadas' => 'atencion'];

    /** Ancho válido para un bloque (si viene vacío o fuera de la lista, el de por defecto). */
    public static function ancho(string $k, $valor = null): int
    {
        $n = (int)$valor;
        return isset(self::ANCHOS[$n]) ? $n : (self::ANCHO_DEFECTO[$k] ?? 12);
    }

    /** Configuración vigente: ['bloques' => [{k, on, ancho}] (todos los bloques, en orden), 'proyectos_n' => int, 'propia' => bool] */
    public static function de(int $uid): array
    {
        $f = self::filas("SELECT inicio_json FROM usuarios WHERE id = $uid")[0] ?? null;
        $j = $f ? json_decode((string)$f['inicio_json'], true) : null;
        $propia = is_array($j) && isset($j['bloques']) && is_array($j['bloques']);
        $lista = $propia ? $j['bloques'] : array_map(fn($k) => ['k' => $k, 'on' => true], self::DEFECTO);
        $out = [];
        $vistas = [];
        foreach ($lista as $b) {
            if (!is_array($b)) continue;
            $k = (string)($b['k'] ?? '');
            $k = self::LEGADO[$k] ?? $k;
            if (!isset(self::BLOQUES[$k])) continue;
            $on = !empty($b['on']);
            $ancho = self::ancho($k, $b['ancho'] ?? $b['tam'] ?? null);
            if (isset($vistas[$k])) {
                // «hoy» y «atrasadas» se funden en «atencion»: si cualquiera estaba activo, queda activo.
                if ($k === 'atencion') {
                    foreach ($out as &$o) {
                        if ($o['k'] === 'atencion') { $o['on'] = $o['on'] || $on; break; }
                    }
                    unset($o);
                }
                continue;
            }
            $vistas[$k] = true;
            $out[] = ['k' => $k, 'on' => $on, 'ancho' => $ancho];
        }
        foreach (array_keys(self::BLOQUES) as $k) {
            if (!isset($vistas[$k])) $out[] = ['k' => $k, 'on' => false, 'ancho' => self::ancho($k)];
        }
        $n = (int)($j['proyectos_n'] ?? 6);
        return ['bloques' => $out, 'proyectos_n' => in_array($n, self::CANTIDADES, true) ? $n : 6, 'propia' => $propia];
    }

    public static function guardar(int $uid, array $bloques, int $proyectosN): bool
    {
        $out = [];
        $vistas = [];
        foreach ($bloques as $b) {
            if (!is_array($b)) continue;
            $k = (string)($b['k'] ?? '');
            $k = self::LEGADO[$k] ?? $k;
            if (!isset(self::BLOQUES[$k])) continue;
            $on = !empty($b['on']);
            $ancho = self::ancho($k, $b['ancho'] ?? $b['tam'] ?? null);
            if (isset($vistas[$k])) {
                if ($k === 'atencion') {
                    foreach ($out as &$o) {
                        if ($o['k'] === 'atencion') { $o['on'] = $o['on'] || $on; break; }
                    }
                    unset($o);
                }
                continue;
            }
            $vistas[$k] = true;
            $out[] = ['k' => $k, 'on' => $on, 'ancho' => $ancho];
        }
        $json = json_encode(['bloques' => $out, 'proyectos_n' => in_array($proyectosN, self::CANTIDADES, true) ? $proyectosN : 6], JSON_UNESCAPED_UNICODE);
        try {
            $st = self::db()->prepare('UPDATE usuarios SET inicio_json = ? WHERE id = ?');
            $st->bind_param('si', $json, $uid);
            return $st->execute();
        } catch (\Throwable $e) {
            error_log('Inicio: ' . $e->getMessage());
            return false;
        }
    }

    public static function restablecer(int $uid): void
    {
        try { self::db()->query("UPDATE usuarios SET inicio_json = NULL WHERE id = $uid"); } catch (\Throwable $e) {}
    }

    /** Datos de los bloques activos que necesitan consultas (actividades de hoy y atrasadas) sobre los proyectos visibles */
    public static function datos(array $proyectos, array $activos): array
    {
        $ids = array_map(fn($p) => (int)$p->id, $proyectos);
        $d = ['hoy' => [], 'atrasadas' => [], 'n_hoy' => 0, 'n_atrasadas' => 0];
        if (!$ids || !array_intersect(['resumen', 'atencion'], $activos)) return $d;
        $in = implode(',', $ids);
        $base = "FROM actividades a JOIN partidas pa ON pa.id = a.partida_id JOIN proyectos pr ON pr.id = pa.proyecto_id WHERE pr.id IN ($in) AND a.hito = 0 AND a.progreso < 100";
        $campos = 'SELECT a.id, a.codigo, a.nombre, a.progreso, a.fecha_inicio, a.fecha_fin, pr.id AS proyecto_id, pr.nombre AS proyecto';
        $d['n_hoy'] = (int)(self::filas("SELECT COUNT(*) n $base AND CURDATE() BETWEEN a.fecha_inicio AND a.fecha_fin")[0]['n'] ?? 0);
        $d['n_atrasadas'] = (int)(self::filas("SELECT COUNT(*) n $base AND a.fecha_fin < CURDATE()")[0]['n'] ?? 0);
        // Restricciones activas de las obras visibles (incluye las de subactividades).
        try {
            $d['n_restricciones'] = (int)(self::filas(
                "SELECT COUNT(*) n FROM restricciones r
                 LEFT JOIN actividades a ON a.id = r.actividad_id
                 LEFT JOIN subactividades s ON s.id = r.subactividad_id
                 LEFT JOIN actividades a2 ON a2.id = s.actividad_id
                 JOIN partidas pa ON pa.id = COALESCE(a.partida_id, a2.partida_id)
                 WHERE pa.proyecto_id IN ($in) AND r.estado = 'activa'"
            )[0]['n'] ?? 0);
        } catch (\Throwable $e) {
            $d['n_restricciones'] = 0;   // migración pendiente
        }
        if (in_array('atencion', $activos, true)) {
            $d['hoy'] = self::filas("$campos $base AND CURDATE() BETWEEN a.fecha_inicio AND a.fecha_fin ORDER BY a.fecha_fin, pr.nombre LIMIT 12");
            $d['atrasadas'] = self::filas("$campos $base AND a.fecha_fin < CURDATE() ORDER BY a.fecha_fin LIMIT 12");
        }
        return $d;
    }
}
