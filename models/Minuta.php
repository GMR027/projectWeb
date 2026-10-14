<?php

namespace Model;

class Minuta extends ActiveRecord
{
    protected static $tabla = 'minutas';
    protected static $columnasDB = ['id', 'proyecto_id', 'fecha', 'nombre', 'importancia', 'contratista_json', 'personal_json', 'temas_json', 'compromisos_json'];

    public $id;
    public $proyecto_id;
    public $fecha;
    public $nombre;
    public $importancia;
    public $contratista_json;
    public $personal_json;
    public $temas_json;
    public $compromisos_json;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->fecha = $args['fecha'] ?? date('Y-m-d');
        $this->nombre = $args['nombre'] ?? '';
        $this->importancia = $args['importancia'] ?? 'media';
        $this->contratista_json = $args['contratista_json'] ?? '[]';
        $this->personal_json = $args['personal_json'] ?? '[]';
        $this->temas_json = $args['temas_json'] ?? '[]';
        $this->compromisos_json = $args['compromisos_json'] ?? '[]';
    }

    public function validar(): array
    {
        self::$alertas = [];
        $f = \DateTime::createFromFormat('Y-m-d', (string)$this->fecha);
        if (!$f || $f->format('Y-m-d') !== $this->fecha) self::$alertas['error'][] = 'La fecha no es válida';
        if (!trim($this->nombre)) self::$alertas['error'][] = 'El nombre de la minuta es obligatorio';
        if (mb_strlen($this->nombre) > 200) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 200)';
        if (!isset(Bitacora::IMPORTANCIAS[$this->importancia])) self::$alertas['error'][] = 'Nivel de importancia no válido';
        return self::$alertas;
    }

    private static function lista($json): array
    {
        $d = json_decode((string)$json, true);
        return is_array($d) ? array_values($d) : [];
    }

    public function getContratista(): array { return array_map('intval', self::lista($this->contratista_json)); }
    public function getPersonal(): array { return array_map('intval', self::lista($this->personal_json)); }
    public function getTemas(): array { return self::lista($this->temas_json); }
    public function getCompromisos(): array { return self::lista($this->compromisos_json); }

    public function setIds(string $campo, array $ids): void
    {
        $this->$campo = json_encode(array_values(array_unique(array_map('intval', $ids))));
    }

    public function setItems(string $campo, array $items): void
    {
        $this->$campo = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM minutas WHERE proyecto_id = $proyectoId ORDER BY fecha DESC, id DESC");
    }
}
