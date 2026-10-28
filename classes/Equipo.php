<?php

namespace Classes;

/**
 * Equipo y maquinaria: registro del equipo, horas trabajadas por día/actividad y costo.
 * Avisa de los servicios preventivos próximos por fecha o por horas acumuladas.
 */
class Equipo
{
    use AccesoDb;

    public const ESTADOS = ['operativo' => 'Operativo', 'mantenimiento' => 'En mantenimiento', 'baja' => 'Fuera de servicio'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM equipo LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Equipo con horas y costo acumulados */
    public static function lista(int $pid): array
    {
        return self::filas("SELECT e.*,
                COALESCE(SUM(h.horas), 0) horas, COALESCE(SUM(h.importe), 0) importe,
                MAX(h.fecha) ultima
            FROM equipo e LEFT JOIN equipo_horas h ON h.equipo_id = e.id
            WHERE e.proyecto_id = $pid
            GROUP BY e.id ORDER BY FIELD(e.estado, 'mantenimiento', 'operativo', 'baja'), e.nombre");
    }

    public static function obtener(int $pid, int $id): ?array
    {
        return self::filas("SELECT * FROM equipo WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
    }

    public static function guardar(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $nombre = trim((string)($d['nombre'] ?? ''));
        if ($nombre === '') return [false, 'El nombre del equipo es obligatorio', 0];
        $estado = isset(self::ESTADOS[$d['estado'] ?? '']) ? $d['estado'] : 'operativo';
        $fecha = self::fecha($d['proximo_servicio'] ?? '');
        $horasServicio = max(0, (int)($d['proximo_servicio_horas'] ?? 0));
        $campos = "nombre = '" . self::esc(mb_substr($nombre, 0, 150)) . "',
            tipo = '" . self::esc(mb_substr(trim((string)($d['tipo'] ?? '')), 0, 80)) . "',
            marca = '" . self::esc(mb_substr(trim((string)($d['marca'] ?? '')), 0, 80)) . "',
            modelo = '" . self::esc(mb_substr(trim((string)($d['modelo'] ?? '')), 0, 80)) . "',
            serie = '" . self::esc(mb_substr(trim((string)($d['serie'] ?? '')), 0, 80)) . "',
            propietario = '" . self::esc(mb_substr(trim((string)($d['propietario'] ?? '')), 0, 150)) . "',
            rentado = " . (!empty($d['rentado']) ? 1 : 0) . ",
            tarifa_hora = " . max(0, (float)($d['tarifa_hora'] ?? 0)) . ",
            proximo_servicio = " . ($fecha ? "'$fecha'" : 'NULL') . ",
            proximo_servicio_horas = " . ($horasServicio ?: 'NULL') . ",
            estado = '$estado',
            notas = '" . self::esc(mb_substr(trim((string)($d['notas'] ?? '')), 0, 1000)) . "'";
        if ($id) {
            if (!self::obtener($pid, $id)) return [false, 'El equipo no existe', 0];
            self::db()->query("UPDATE equipo SET $campos WHERE id = $id AND proyecto_id = $pid");
        } else {
            self::db()->query("INSERT INTO equipo SET proyecto_id = $pid, $campos");
            $id = (int)self::db()->insert_id;
        }
        return [true, 'Equipo guardado', $id];
    }

    public static function eliminar(int $pid, int $id): bool
    {
        if (!self::obtener($pid, $id)) return false;
        self::db()->query("DELETE FROM equipo WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    /** Registros de horas (filtrados por equipo si se indica) */
    public static function horas(int $pid, int $equipoId = 0, int $limite = 200): array
    {
        $w = $equipoId ? " AND h.equipo_id = $equipoId" : '';
        return self::filas("SELECT h.*, e.nombre equipo, e.tarifa_hora, a.codigo act_codigo, a.nombre act_nombre
            FROM equipo_horas h JOIN equipo e ON e.id = h.equipo_id LEFT JOIN actividades a ON a.id = h.actividad_id
            WHERE h.proyecto_id = $pid$w
            ORDER BY h.fecha DESC, h.id DESC LIMIT " . max(1, min(1000, $limite)));
    }

    public static function registrarHoras(int $pid, array $d): array
    {
        $equipoId = (int)($d['equipo_id'] ?? 0);
        $eq = self::obtener($pid, $equipoId);
        if (!$eq) return [false, 'Elige un equipo del proyecto'];
        $fecha = self::fecha($d['fecha'] ?? '') ?: date('Y-m-d');
        $horas = max(0, min(24, round((float)($d['horas'] ?? 0), 2)));
        if ($horas <= 0) return [false, 'Escribe las horas trabajadas'];
        $actividad = (int)($d['actividad_id'] ?? 0);
        if ($actividad) {
            $ok = self::filas("SELECT 1 FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $actividad AND p.proyecto_id = $pid");
            if (!$ok) $actividad = 0;
        }
        $importe = round($horas * (float)$eq['tarifa_hora'], 2);
        self::db()->query("INSERT INTO equipo_horas SET proyecto_id = $pid, equipo_id = $equipoId, actividad_id = " . ($actividad ?: 'NULL') . ",
            fecha = '$fecha', horas = $horas, importe = $importe,
            operador = '" . self::esc(mb_substr(trim((string)($d['operador'] ?? '')), 0, 150)) . "',
            observaciones = '" . self::esc(mb_substr(trim((string)($d['observaciones'] ?? '')), 0, 1000)) . "'");
        return [true, 'Horas registradas', (int)self::db()->insert_id];
    }

    public static function eliminarHoras(int $pid, int $id): bool
    {
        $ok = self::filas("SELECT 1 FROM equipo_horas WHERE id = $id AND proyecto_id = $pid");
        if (!$ok) return false;
        self::db()->query("DELETE FROM equipo_horas WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    /** Resumen del proyecto y equipo con servicio próximo */
    public static function resumen(int $pid): array
    {
        $r = self::filas("SELECT
            (SELECT COUNT(*) FROM equipo WHERE proyecto_id = $pid) total,
            (SELECT COUNT(*) FROM equipo WHERE proyecto_id = $pid AND estado = 'operativo') operativos,
            (SELECT COUNT(*) FROM equipo WHERE proyecto_id = $pid AND estado = 'mantenimiento') mantenimiento,
            (SELECT COALESCE(SUM(horas), 0) FROM equipo_horas WHERE proyecto_id = $pid AND fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) horas_mes,
            (SELECT COALESCE(SUM(importe), 0) FROM equipo_horas WHERE proyecto_id = $pid AND fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) costo_mes
        ")[0] ?? [];
        return ['total' => (int)($r['total'] ?? 0), 'operativos' => (int)($r['operativos'] ?? 0), 'mantenimiento' => (int)($r['mantenimiento'] ?? 0),
            'horas_mes' => (float)($r['horas_mes'] ?? 0), 'costo_mes' => (float)($r['costo_mes'] ?? 0)];
    }

    /** Equipo cuyo servicio vence en los próximos $dias días o por horas acumuladas */
    public static function serviciosProximos(int $pid, int $dias = 15): array
    {
        $limite = date('Y-m-d', strtotime("+$dias days"));
        return self::filas("SELECT e.*, COALESCE(SUM(h.horas), 0) horas,
                DATEDIFF(e.proximo_servicio, CURDATE()) dias
            FROM equipo e LEFT JOIN equipo_horas h ON h.equipo_id = e.id
            WHERE e.proyecto_id = $pid AND e.estado <> 'baja' AND (
                (e.proximo_servicio IS NOT NULL AND e.proximo_servicio <= '$limite')
                OR (e.proximo_servicio_horas IS NOT NULL AND COALESCE((SELECT SUM(x.horas) FROM equipo_horas x WHERE x.equipo_id = e.id), 0) >= e.proximo_servicio_horas - 20)
            )
            GROUP BY e.id ORDER BY e.proximo_servicio, e.nombre");
    }

    private static function fecha($v): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', (string)$v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }
}
