<?php

namespace Model;

class CalendarioJornada extends ActiveRecord
{
    protected static $tabla = 'calendario_jornada';
    protected static $columnasDB = ['id', 'proyecto_id', 'dia_semana', 'factor'];

    public $id;
    public $proyecto_id;
    public $dia_semana;
    public $factor;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->dia_semana = $args['dia_semana'] ?? null;
        $this->factor = $args['factor'] ?? 1.0;
    }
}
