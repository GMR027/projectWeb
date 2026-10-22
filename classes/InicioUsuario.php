<?php

namespace Classes;

/**
 * Inicio personalizable por usuario (Fase 4.5): qué bloques ve en su pantalla de inicio y en qué orden.
 * Se guarda en usuarios.inicio_json = {"bloques":[{"k":"resumen","on":true},…],"proyectos_n":6}. Sin configuración (o sin migración)
 * se usa el inicio de siempre: resumen y proyectos recientes.
 */
class InicioUsuario
{
    use AccesoDb;

    /** clave => [título, descripción] */
    public const BLOQUES = [
        'resumen' => ['Resumen', 'Proyectos, actividades de hoy y atrasadas de un vistazo'],
        'hoy' => ['Actividades de hoy', 'Lo que se está ejecutando hoy en tus proyectos'],
        'atrasadas' => ['Atrasadas', 'Actividades vencidas sin terminar, las más viejas primero'],
        'proyectos' => ['Proyectos recientes', 'Tarjetas con avance, salud y accesos'],
        'novedades' => ['Novedades de la plataforma', 'Lo último que se agregó'],
        'atajos' => ['Accesos rápidos', 'Nuevo proyecto, plantillas, manual y novedades'],
    ];
    public const DEFECTO = ['resumen', 'proyectos'];
    public const CANTIDADES = [3, 6, 12, 24];

    /** Configuración vigente: ['bloques' => [{k, on}] (todos los bloques, en orden), 'proyectos_n' => int, 'propia' => bool] */
    public static function de(int $uid): array
    {
        $f = self::filas("SELECT inicio_json FROM usuarios WHERE id = $uid")[0] ?? null;
        $j = $f ? json_decode((string)$f['inicio_json'], true) : null;
        $propia = is_array($j) && isset($j['bloques']);
        $lista = $propia ? $j['bloques'] : array_map(fn($k) => ['k' => $k, 'on' => true], self::DEFECTO);
        $out = []; $vistas = [];
        foreach ($lista as $b) {
            $k = (string)($b['k'] ?? '');
            if (!isset(self::BLOQUES[$k]) || isset($vistas[$k])) continue;
            $vistas[$k] = true;
            $out[] = ['k' => $k, 'on' => !empty($b['on'])];
        }
        foreach (array_keys(self::BLOQUES) as $k) if (!isset($vistas[$k])) $out[] = ['k' => $k, 'on' => false];
        $n = (int)($j['proyectos_n'] ?? 6);
        return ['bloques' => $out, 'proyectos_n' => in_array($n, self::CANTIDADES, true) ? $n : 6, 'propia' => $propia];
    }

    public static function guardar(int $uid, array $bloques, int $proyectosN): bool
    {
        $out = []; $vistas = [];
        foreach ($bloques as $b) {
            $k = (string)($b['k'] ?? '');
            if (!isset(self::BLOQUES[$k]) || isset($vistas[$k])) continue;
            $vistas[$k] = true;
            $out[] = ['k' => $k, 'on' => !empty($b['on'])];
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
        if (!$ids || !array_intersect(['resumen', 'hoy', 'atrasadas'], $activos)) return $d;
        $in = implode(',', $ids);
        $base = "FROM actividades a JOIN partidas pa ON pa.id = a.partida_id JOIN proyectos pr ON pr.id = pa.proyecto_id WHERE pr.id IN ($in) AND a.hito = 0 AND a.progreso < 100";
        $campos = 'SELECT a.id, a.codigo, a.nombre, a.progreso, a.fecha_inicio, a.fecha_fin, pr.id AS proyecto_id, pr.nombre AS proyecto';
        $d['n_hoy'] = (int)(self::filas("SELECT COUNT(*) n $base AND CURDATE() BETWEEN a.fecha_inicio AND a.fecha_fin")[0]['n'] ?? 0);
        $d['n_atrasadas'] = (int)(self::filas("SELECT COUNT(*) n $base AND a.fecha_fin < CURDATE()")[0]['n'] ?? 0);
        if (in_array('hoy', $activos, true)) $d['hoy'] = self::filas("$campos $base AND CURDATE() BETWEEN a.fecha_inicio AND a.fecha_fin ORDER BY a.fecha_fin, pr.nombre LIMIT 12");
        if (in_array('atrasadas', $activos, true)) $d['atrasadas'] = self::filas("$campos $base AND a.fecha_fin < CURDATE() ORDER BY a.fecha_fin LIMIT 12");
        return $d;
    }
}
