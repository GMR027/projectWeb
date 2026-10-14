<?php

namespace Model;

class ReporteDiario extends ActiveRecord
{
    protected static $tabla = 'reportes_diarios';
    protected static $columnasDB = ['id', 'proyecto_id', 'fecha', 'area', 'comentarios', 'personal_json', 'actividades_json', 'fotos_json'];

    public $id;
    public $proyecto_id;
    public $fecha;
    public $area;
    public $comentarios;
    public $personal_json;
    public $actividades_json;
    public $fotos_json;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->fecha = $args['fecha'] ?? date('Y-m-d');
        $this->area = $args['area'] ?? '';
        $this->comentarios = $args['comentarios'] ?? '';
        $this->personal_json = $args['personal_json'] ?? '[]';
        $this->actividades_json = $args['actividades_json'] ?? '[]';
        $this->fotos_json = $args['fotos_json'] ?? '[]';
    }

    public function validar(): array
    {
        self::$alertas = [];
        $f = \DateTime::createFromFormat('Y-m-d', (string)$this->fecha);
        if (!$f || $f->format('Y-m-d') !== $this->fecha) self::$alertas['error'][] = 'La fecha no es válida';
        if (mb_strlen($this->area) > 150) self::$alertas['error'][] = 'El área es demasiado larga (máx. 150)';
        if (empty($this->getActividades())) self::$alertas['error'][] = 'Selecciona al menos una actividad del programa';
        return self::$alertas;
    }

    private static function lista($json): array
    {
        $d = json_decode((string)$json, true);
        return is_array($d) ? array_values($d) : [];
    }

    public function getPersonal(): array { return array_map('intval', self::lista($this->personal_json)); }
    public function getActividades(): array { return array_map('intval', self::lista($this->actividades_json)); }
    public function getFotos(): array { return array_map('basename', array_map('strval', self::lista($this->fotos_json))); }

    public function setPersonal(array $ids): void { $this->personal_json = json_encode(array_values(array_unique(array_map('intval', $ids)))); }
    public function setActividades(array $ids): void { $this->actividades_json = json_encode(array_values(array_unique(array_map('intval', $ids)))); }
    public function setFotos(array $nombres): void { $this->fotos_json = json_encode(array_values($nombres)); }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM reportes_diarios WHERE proyecto_id = $proyectoId ORDER BY fecha DESC, id DESC");
    }
}
