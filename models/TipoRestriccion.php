<?php

namespace Model;

class TipoRestriccion extends ActiveRecord
{
    protected static $tabla = 'tipos_restriccion';
    protected static $columnasDB = ['id', 'nombre', 'color'];

    public $id;
    public $nombre;
    public $color;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->color = $args['color'] ?? '#FF5A1F';
    }
}
