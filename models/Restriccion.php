<?php

namespace Model;

class Restriccion extends ActiveRecord
{
    protected static $tabla = 'restricciones';
    protected static $columnasDB = ['id', 'actividad_id', 'tipo_id', 'codigo', 'descripcion', 'fecha', 'estado'];

    public $id;
    public $actividad_id;
    public $tipo_id;
    public $codigo;
    public $descripcion;
    public $fecha;
    public $estado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->actividad_id = $args['actividad_id'] ?? null;
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
}
