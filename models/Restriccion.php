<?php

namespace Model;

class Restriccion extends ActiveRecord
{
    protected static $tabla = 'restricciones';
    protected static $columnasDB = ['id', 'actividad_id', 'subactividad_id', 'tipo_id', 'codigo', 'descripcion', 'fecha', 'estado'];

    public $id;
    public $actividad_id;
    /** Si la restricción es propia de una subactividad (NULL = de la actividad) */
    public $subactividad_id;
    public $tipo_id;
    public $codigo;
    public $descripcion;
    public $fecha;
    public $estado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->actividad_id = $args['actividad_id'] ?? null;
        $this->subactividad_id = $args['subactividad_id'] ?? null;
        $this->tipo_id = $args['tipo_id'] ?? null;
        $this->codigo = $args['codigo'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->fecha = $args['fecha'] ?? null;
        $this->estado = $args['estado'] ?? 'activa';
    }

    public function getTipo(): ?TipoRestriccion
    {
        return TipoRestriccion::find($this->tipo_id);
    }

    /**
     * Restricciones activas del proyecto agrupadas por actividad: [actividad_id => [Restriccion, …]].
     * Una sola consulta para todo el programa (antes el Gantt hacía dos por actividad).
     */
    public static function activasPorProyecto(int $proyectoId): array
    {
        $id = (int)$proyectoId;
        $rows = self::consultarSQL(
            "SELECT r.* FROM restricciones r
             JOIN actividades a ON a.id = r.actividad_id
             JOIN partidas p ON p.id = a.partida_id
             WHERE p.proyecto_id = $id AND r.estado = 'activa' AND r.subactividad_id IS NULL
             ORDER BY r.creado DESC"
        );
        $out = [];
        foreach ($rows as $r) $out[(int)$r->actividad_id][] = $r;
        return $out;
    }

    /** Restricciones activas propias de subactividades: [subactividad_id => [Restriccion, …]] */
    public static function activasSubactividadesPorProyecto(int $proyectoId): array
    {
        $id = (int)$proyectoId;
        try {
            $rows = self::consultarSQL(
                "SELECT r.* FROM restricciones r
                 JOIN subactividades s ON s.id = r.subactividad_id
                 JOIN actividades a ON a.id = s.actividad_id
                 JOIN partidas p ON p.id = a.partida_id
                 WHERE p.proyecto_id = $id AND r.estado = 'activa'
                 ORDER BY r.creado DESC"
            );
        } catch (\Throwable $e) {   // migración pendiente
            return [];
        }
        $out = [];
        foreach ($rows as $r) $out[(int)$r->subactividad_id][] = $r;
        return $out;
    }

    public function getSubactividad(): ?Subactividad
    {
        return $this->subactividad_id ? Subactividad::find((int)$this->subactividad_id) : null;
    }
}
