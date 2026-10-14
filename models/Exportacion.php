<?php

namespace Model;

class Exportacion extends ActiveRecord
{
    protected static $tabla = 'exportaciones';
    protected static $columnasDB = ['id', 'proyecto_id', 'usuario_id', 'formato', 'nombre_archivo', 'hash_sha256', 'ruta'];

    public $id;
    public $proyecto_id;
    public $usuario_id;
    public $formato;
    public $nombre_archivo;
    public $hash_sha256;
    public $ruta;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->usuario_id = $args['usuario_id'] ?? null;
        $this->formato = $args['formato'] ?? '';
        $this->nombre_archivo = $args['nombre_archivo'] ?? '';
        $this->hash_sha256 = $args['hash_sha256'] ?? '';
        $this->ruta = $args['ruta'] ?? '';
    }
}
