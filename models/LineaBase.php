<?php

namespace Model;

class LineaBase extends ActiveRecord
{
    protected static $tabla = 'lineas_base';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'notas', 'fin_programado', 'total_actividades', 'criticas_json', 'creado_por'];

    public $id;
    public $proyecto_id;
    public $nombre;
    public $notas;
    public $fin_programado;
    public $total_actividades;
    public $criticas_json;
    public $creado_por;
    public $creado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->notas = $args['notas'] ?? '';
        $this->fin_programado = $args['fin_programado'] ?? null;
        $this->total_actividades = (int)($args['total_actividades'] ?? 0);
        $this->criticas_json = $args['criticas_json'] ?? null;
        $this->creado_por = $args['creado_por'] ?? '';
        $this->creado = $args['creado'] ?? null;
    }

    /** @return LineaBase[] la más reciente primero */
    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM lineas_base WHERE proyecto_id = $proyectoId ORDER BY id DESC");
    }

    public function criticas(): array
    {
        return array_map('intval', json_decode((string)$this->criticas_json, true) ?: []);
    }
}
