<?php

namespace Model;

/**
 * Relación entre dos materiales del catálogo: el sucesor requiere que el predecesor esté
 * disponible (entregado en obra o instalado) antes de poder usarse.
 */
class MaterialDependencia extends ActiveRecord
{
    protected static $tabla = 'material_dependencias';
    protected static $columnasDB = ['id', 'proyecto_id', 'predecesor_id', 'sucesor_id', 'requisito', 'retraso', 'nota'];

    public $id;
    public $proyecto_id;
    public $predecesor_id;
    public $sucesor_id;
    public $requisito;
    public $retraso;
    public $nota;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->predecesor_id = $args['predecesor_id'] ?? null;
        $this->sucesor_id = $args['sucesor_id'] ?? null;
        $this->requisito = $args['requisito'] ?? 'entrega';
        $this->retraso = (int)($args['retraso'] ?? 0);
        $this->nota = $args['nota'] ?? '';
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!in_array($this->requisito, ['entrega', 'instalacion'], true)) {
            self::$alertas['error'][] = 'El requisito no es válido';
        }
        if ((int)$this->retraso < 0 || (int)$this->retraso > 365) {
            self::$alertas['error'][] = 'El retraso debe estar entre 0 y 365 días hábiles';
        }
        if (mb_strlen((string)$this->nota) > 255) {
            self::$alertas['error'][] = 'La nota es demasiado larga (máx. 255 caracteres)';
        }
        return self::$alertas;
    }

    /** @return self[] relaciones de un proyecto */
    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM material_dependencias WHERE proyecto_id = $proyectoId ORDER BY id ASC");
    }
}
