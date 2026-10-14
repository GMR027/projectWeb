<?php

namespace Model;

class PersonalContratista extends ActiveRecord
{
    protected static $tabla = 'personal_contratista';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'puesto', 'empresa'];

    public $id;
    public $proyecto_id;
    public $nombre;
    public $puesto;
    public $empresa;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->puesto = $args['puesto'] ?? '';
        $this->empresa = $args['empresa'] ?? '';
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->nombre)) self::$alertas['error'][] = 'El nombre es obligatorio';
        if (mb_strlen($this->nombre) > 150 || mb_strlen($this->empresa) > 150) self::$alertas['error'][] = 'Nombre o empresa demasiado largos (máx. 150)';
        if (mb_strlen($this->puesto) > 100) self::$alertas['error'][] = 'El puesto es demasiado largo (máx. 100)';
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM personal_contratista WHERE proyecto_id = $proyectoId ORDER BY empresa ASC, nombre ASC");
    }

    public static function indexado(int $proyectoId): array
    {
        $r = [];
        foreach (self::deProyecto($proyectoId) as $p) $r[(int)$p->id] = $p;
        return $r;
    }
}
