<?php

namespace Model;

class VplanNota extends ActiveRecord
{
    protected static $tabla = 'vplan_notas';
    protected static $columnasDB = ['id', 'lamina_id', 'descripcion', 'fecha'];

    public $id, $lamina_id, $descripcion, $fecha;

    public const MAX_POR_LAMINA = 200;
    public const MAX_LARGO = 2000;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->lamina_id = $args['lamina_id'] ?? null;
        $this->descripcion = $args['descripcion'] ?? '';
        $this->fecha = $args['fecha'] ?? date('Y-m-d H:i:s');
    }

    public static function deLamina(int $laminaId): array
    {
        return self::consultarSQL("SELECT * FROM vplan_notas WHERE lamina_id = $laminaId ORDER BY fecha DESC, id DESC");
    }

    public static function totalDeLamina(int $laminaId): int
    {
        return (int)self::$db->query("SELECT COUNT(*) FROM vplan_notas WHERE lamina_id = $laminaId")->fetch_row()[0];
    }

    /** Para el JSON de la página */
    public function aArray(): array
    {
        return ['id' => (int)$this->id, 'descripcion' => (string)$this->descripcion, 'fecha' => date('d/m/Y H:i', strtotime((string)$this->fecha))];
    }
}
