<?php

namespace Model;

class BitacoraDepartamento extends ActiveRecord
{
    protected static $tabla = 'bitacora_departamentos';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre'];

    public $id;
    public $proyecto_id;
    public $nombre;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
    }

    public function validar(): array
    {
        self::$alertas = [];
        $n = trim($this->nombre);
        if ($n === '') self::$alertas['error'][] = 'El nombre del departamento es obligatorio';
        if (mb_strlen($n) > 100) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 100)';
        if ($n !== '') {
            $e = self::$db->escape_string(mb_strtolower($n));
            $otro = self::consultarSQL("SELECT * FROM bitacora_departamentos WHERE proyecto_id = " . (int)$this->proyecto_id . " AND LOWER(nombre) = '$e' AND id <> " . (int)$this->id . " LIMIT 1");
            if ($otro) self::$alertas['error'][] = 'Ya existe un departamento con ese nombre';
        }
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM bitacora_departamentos WHERE proyecto_id = $proyectoId ORDER BY nombre ASC");
    }

    /** Registros de la bitácora que usan este departamento */
    public function enUso(): int
    {
        return (int)Bitacora::total("proyecto_id = " . (int)$this->proyecto_id . " AND departamento = '" . self::$db->escape_string($this->nombre) . "'");
    }
}
