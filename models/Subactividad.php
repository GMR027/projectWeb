<?php

namespace Model;

/**
 * Subactividad de una actividad del programa (nombre, fechas y % de avance).
 * No aparece en el programa, dependencias, línea de tiempo ni lookahead:
 * solo se gestiona desde la edición de la actividad y alimenta su progreso.
 */
class Subactividad extends ActiveRecord
{
    protected static $tabla = 'subactividades';
    protected static $columnasDB = ['id', 'actividad_id', 'nombre', 'fecha_inicio', 'fecha_fin', 'status'];

    public $id;
    public $actividad_id;
    public $nombre;
    public $fecha_inicio;
    public $fecha_fin;
    public $status;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->actividad_id = $args['actividad_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->fecha_inicio = $args['fecha_inicio'] ?? null;
        $this->fecha_fin = $args['fecha_fin'] ?? null;
        $this->status = $args['status'] ?? 0;
    }

    public function validar(): array
    {
        self::$alertas = [];
        $this->nombre = trim((string)$this->nombre);
        $this->status = max(0, min(100, (int)round((float)$this->status)));

        if ($this->nombre === '') {
            self::$alertas['error'][] = 'El nombre de la subactividad es obligatorio';
        } elseif (mb_strlen($this->nombre) > 200) {
            self::$alertas['error'][] = 'El nombre de la subactividad no puede superar los 200 caracteres';
        }
        if (!$this->fecha_inicio || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$this->fecha_inicio)) {
            self::$alertas['error'][] = 'La fecha de inicio de la subactividad es obligatoria';
        }
        if (!$this->fecha_fin || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$this->fecha_fin)) {
            self::$alertas['error'][] = 'La fecha de fin de la subactividad es obligatoria';
        }
        if ($this->fecha_inicio && $this->fecha_fin && $this->fecha_fin < $this->fecha_inicio) {
            self::$alertas['error'][] = 'La fecha de fin no puede ser anterior a la de inicio';
        }
        return self::$alertas;
    }

    /** Promedio entero (0–100) de los avances de las subactividades; 0 si no hay */
    public static function promedio(array $statuses): int
    {
        $valores = [];
        foreach ($statuses as $s) {
            if (is_array($s)) $s = $s['status'] ?? 0;
            $valores[] = max(0, min(100, (int)$s));
        }
        if (!$valores) return 0;
        return (int)round(array_sum($valores) / count($valores));
    }

    /** Subactividades de una actividad, en orden de fecha */
    public static function deActividad(int $actividadId): array
    {
        return self::whereAll('actividad_id', $actividadId, 'fecha_inicio ASC, id ASC');
    }

    /** [actividad_id => total] de las subactividades que tiene cada actividad del proyecto */
    public static function conteoPorProyecto(int $proyectoId): array
    {
        $query = "SELECT s.actividad_id, COUNT(*) AS total
                  FROM subactividades s
                  JOIN actividades a ON a.id = s.actividad_id
                  JOIN partidas p ON p.id = a.partida_id
                  WHERE p.proyecto_id = " . (int)$proyectoId . "
                  GROUP BY s.actividad_id";
        $conteo = [];
        try {
            $r = self::$db->query($query);
            if ($r) {
                while ($fila = $r->fetch_assoc()) {
                    $conteo[(int)$fila['actividad_id']] = (int)$fila['total'];
                }
            }
        } catch (\Throwable $e) {   // migración pendiente: el programa sigue funcionando sin subactividades
        }
        return $conteo;
    }

    /** ¿La actividad tiene subactividades? (una sola consulta, tolerante a la migración pendiente) */
    public static function existePara(int $actividadId): bool
    {
        try {
            $r = self::$db->query('SELECT 1 FROM subactividades WHERE actividad_id = ' . (int)$actividadId . ' LIMIT 1');
            return $r && $r->num_rows > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
