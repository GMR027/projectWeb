<?php

namespace Model;

class ArchivoCategoria extends ActiveRecord
{
    protected static $tabla = 'archivo_categorias';
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
        if ($n === '') self::$alertas['error'][] = 'El nombre de la categoría es obligatorio';
        if (mb_strlen($n) > 100) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 100)';
        if ($n !== '') {
            $e = self::$db->escape_string(mb_strtolower($n));
            if (self::consultarSQL("SELECT * FROM archivo_categorias WHERE proyecto_id = " . (int)$this->proyecto_id . " AND LOWER(nombre) = '$e' AND id <> " . (int)$this->id . " LIMIT 1")) {
                self::$alertas['error'][] = 'Ya existe una categoría con ese nombre';
            }
        }
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM archivo_categorias WHERE proyecto_id = $proyectoId ORDER BY nombre ASC");
    }

    public function enUso(): int
    {
        return (int)Archivo::total("categoria_id = " . (int)$this->id);
    }
}
