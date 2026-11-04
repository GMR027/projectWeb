<?php

namespace Classes;

/**
 * Calendarios propios por cuadrilla, subcontratista o cliente.
 * El calendario general del proyecto sigue en calendario_jornada / calendario_festivos;
 * un calendario propio copia la jornada base al crearse y guarda sus propios festivos.
 */
class Calendarios
{
    use AccesoDb;

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM calendarios LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function lista(int $pid): array
    {
        return self::filas("SELECT c.*, (SELECT COUNT(*) FROM actividades a WHERE a.calendario_id = c.id) actividades
            FROM calendarios c WHERE c.proyecto_id = $pid AND c.activo = 1 ORDER BY c.nombre");
    }

    public static function calendario(int $pid, int $id): ?array
    {
        $c = self::filas("SELECT * FROM calendarios WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
        if (!$c) return null;
        $c['jornada'] = [];
        foreach (self::filas("SELECT * FROM calendarios_jornada WHERE calendario_id = $id ORDER BY dia_semana") as $j) $c['jornada'][(int)$j['dia_semana']] = (float)$j['factor'];
        $c['festivos'] = self::filas("SELECT * FROM calendarios_festivos WHERE calendario_id = $id ORDER BY fecha");
        return $c;
    }

    public static function crear(int $pid, string $nombre): array
    {
        $nombre = trim(mb_substr($nombre, 0, 120));
        if ($nombre === '') return [false, 'Escribe el nombre del calendario'];
        if (self::filas("SELECT id FROM calendarios WHERE proyecto_id = $pid AND nombre = '" . self::esc($nombre) . "'")) return [false, 'Ya existe un calendario con ese nombre'];
        self::db()->query("INSERT INTO calendarios (proyecto_id, nombre) VALUES ($pid, '" . self::esc($nombre) . "')");
        $id = (int)self::db()->insert_id;
        // Copiar la jornada base del proyecto
        $base = self::filas("SELECT dia_semana, factor FROM calendario_jornada WHERE proyecto_id = $pid");
        if (!$base) $base = [1 => ['dia_semana' => 1, 'factor' => 1], 2 => ['dia_semana' => 2, 'factor' => 1], 3 => ['dia_semana' => 3, 'factor' => 1], 4 => ['dia_semana' => 4, 'factor' => 1], 5 => ['dia_semana' => 5, 'factor' => 1], 6 => ['dia_semana' => 6, 'factor' => 0], 7 => ['dia_semana' => 7, 'factor' => 0]];
        foreach ($base as $j) self::db()->query("INSERT INTO calendarios_jornada (calendario_id, dia_semana, factor) VALUES ($id, " . (int)$j['dia_semana'] . ", " . (float)$j['factor'] . ')');
        return [true, 'Calendario creado', $id];
    }

    public static function guardarJornada(int $pid, int $id, array $factores): bool
    {
        if (!self::filas("SELECT id FROM calendarios WHERE id = $id AND proyecto_id = $pid")) return false;
        foreach ($factores as $dia => $factor) {
            $dia = (int)$dia;
            if ($dia < 1 || $dia > 7) continue;
            $f = max(0, min(1, (float)$factor));
            self::db()->query("INSERT INTO calendarios_jornada (calendario_id, dia_semana, factor) VALUES ($id, $dia, $f)
                ON DUPLICATE KEY UPDATE factor = VALUES(factor)");
        }
        return true;
    }

    public static function agregarFestivo(int $pid, int $id, array $d): array
    {
        if (!self::filas("SELECT id FROM calendarios WHERE id = $id AND proyecto_id = $pid")) return [false, 'El calendario no existe'];
        $fecha = trim((string)($d['fecha'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return [false, 'La fecha no es válida'];
        $tipo = isset(['festivo' => 1, 'paro_tecnico' => 1, 'clima' => 1, 'otro' => 1][$d['tipo'] ?? '']) ? $d['tipo'] : 'festivo';
        self::db()->query("INSERT INTO calendarios_festivos (calendario_id, fecha, motivo, tipo) VALUES ($id, '$fecha', '" . self::esc(mb_substr((string)($d['motivo'] ?? ''), 0, 200)) . "', '$tipo')
            ON DUPLICATE KEY UPDATE motivo = VALUES(motivo), tipo = VALUES(tipo)");
        return [true, 'Día no laborable guardado'];
    }

    public static function eliminarFestivo(int $pid, int $id, int $festivoId): bool
    {
        if (!self::filas("SELECT id FROM calendarios WHERE id = $id AND proyecto_id = $pid")) return false;
        self::db()->query("DELETE FROM calendarios_festivos WHERE id = $festivoId AND calendario_id = $id");
        return true;
    }

    public static function opciones(int $pid): array
    {
        return self::filas("SELECT id, nombre FROM calendarios WHERE proyecto_id = $pid AND activo = 1 ORDER BY nombre");
    }
}
