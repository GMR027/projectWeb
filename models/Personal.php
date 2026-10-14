<?php

namespace Model;

class Personal extends ActiveRecord
{
    protected static $tabla = 'personal';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'departamento', 'imagen'];

    public $id;
    public $proyecto_id;
    public $nombre;
    public $departamento;
    public $imagen;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->departamento = $args['departamento'] ?? '';
        $this->imagen = $args['imagen'] ?? null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->nombre)) self::$alertas['error'][] = 'El nombre es obligatorio';
        if (mb_strlen($this->nombre) > 150) self::$alertas['error'][] = 'El nombre es demasiado largo';
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM personal WHERE proyecto_id = $proyectoId ORDER BY departamento ASC, nombre ASC");
    }

    /** Personal del proyecto indexado por id */
    public static function indexado(int $proyectoId): array
    {
        $r = [];
        foreach (self::deProyecto($proyectoId) as $p) $r[(int)$p->id] = $p;
        return $r;
    }
}
