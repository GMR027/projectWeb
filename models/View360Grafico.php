<?php

namespace Model;

/** Gráfico interactivo de View360: lienzo con hipervínculos que abren vistas 360 del proyecto */
class View360Grafico extends ActiveRecord
{
    protected static $tabla = 'view360_graficos';
    protected static $columnasDB = ['id', 'proyecto_id', 'fecha', 'nombre', 'version', 'grafico_id'];

    public $id, $proyecto_id, $fecha, $nombre, $version, $grafico_id;
    public $creado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->fecha = $args['fecha'] ?? date('Y-m-d');
        $this->nombre = mb_substr(trim((string)($args['nombre'] ?? '')), 0, 150);
        $this->version = mb_substr(trim((string)($args['version'] ?? '')), 0, 30);
        $this->grafico_id = $args['grafico_id'] ?? null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if ($this->nombre === '') self::$alertas['error'][] = 'El nombre del gráfico es obligatorio';
        if (!$this->fecha || !\DateTime::createFromFormat('Y-m-d', $this->fecha)) self::$alertas['error'][] = 'La fecha no es válida';
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM view360_graficos WHERE proyecto_id = $proyectoId ORDER BY fecha DESC, id DESC");
    }

    /** Gráfico interactivo que usa un gráfico del Editor como lienzo (null si ese gráfico no es de View360) */
    public static function dePorGrafico(int $graficoId): ?self
    {
        try {
            return self::consultarSQL("SELECT * FROM view360_graficos WHERE grafico_id = $graficoId LIMIT 1")[0] ?? null;
        } catch (\Throwable $e) {   // migración pendiente
            return null;
        }
    }
}
