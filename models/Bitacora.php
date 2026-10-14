<?php

namespace Model;

class Bitacora extends ActiveRecord
{
    protected static $tabla = 'bitacora';
    protected static $columnasDB = ['id', 'proyecto_id', 'fecha', 'departamento', 'descripcion', 'importancia'];

    public const IMPORTANCIAS = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'];

    public $id;
    public $proyecto_id;
    public $fecha;
    public $departamento;
    public $descripcion;
    public $importancia;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->fecha = $args['fecha'] ?? date('Y-m-d');
        $this->departamento = $args['departamento'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->importancia = $args['importancia'] ?? 'media';
    }

    public function validar(): array
    {
        self::$alertas = [];
        $f = \DateTime::createFromFormat('Y-m-d', (string)$this->fecha);
        if (!$f || $f->format('Y-m-d') !== $this->fecha) self::$alertas['error'][] = 'La fecha no es válida';
        if (!trim($this->descripcion)) self::$alertas['error'][] = 'La descripción es obligatoria';
        if (mb_strlen($this->departamento) > 100) self::$alertas['error'][] = 'El departamento es demasiado largo (máx. 100)';
        if (!isset(self::IMPORTANCIAS[$this->importancia])) self::$alertas['error'][] = 'Nivel de importancia no válido';
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM bitacora WHERE proyecto_id = $proyectoId ORDER BY fecha DESC, id DESC");
    }
}
