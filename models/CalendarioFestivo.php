<?php

namespace Model;

class CalendarioFestivo extends ActiveRecord
{
    protected static $tabla = 'calendario_festivos';
    protected static $columnasDB = ['id', 'proyecto_id', 'fecha', 'motivo', 'tipo'];

    public $id;
    public $proyecto_id;
    public $fecha;
    public $motivo;
    public $tipo;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->fecha = $args['fecha'] ?? '';
        $this->motivo = $args['motivo'] ?? '';
        $this->tipo = $args['tipo'] ?? 'festivo';
    }
}
