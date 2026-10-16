<?php

namespace Model;

/** Nota de un gráfico interactivo de View360 */
class View360Nota extends ActiveRecord
{
    protected static $tabla = 'view360_notas';
    protected static $columnasDB = ['id', 'view360_id', 'descripcion', 'fecha'];

    public $id, $view360_id, $descripcion, $fecha;

    public const MAX_POR_GRAFICO = 200;
    public const MAX_LARGO = 2000;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->view360_id = $args['view360_id'] ?? null;
        $this->descripcion = $args['descripcion'] ?? '';
        $this->fecha = $args['fecha'] ?? date('Y-m-d H:i:s');
    }

    public static function deGrafico(int $graficoId): array
    {
        try {
            return self::consultarSQL("SELECT * FROM view360_notas WHERE view360_id = $graficoId ORDER BY fecha DESC, id DESC");
        } catch (\Throwable $e) {   // migración pendiente
            return [];
        }
    }

    public static function totalDeGrafico(int $graficoId): int
    {
        return (int)self::$db->query("SELECT COUNT(*) FROM view360_notas WHERE view360_id = $graficoId")->fetch_row()[0];
    }

    /** Para el JSON de la página */
    public function aArray(): array
    {
        return ['id' => (int)$this->id, 'descripcion' => (string)$this->descripcion, 'fecha' => date('d/m/Y H:i', strtotime((string)$this->fecha))];
    }
}
