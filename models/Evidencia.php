<?php

namespace Model;

use Classes\Imagenes;

/** Foto de evidencia subida directamente a una actividad del programa */
class Evidencia extends ActiveRecord
{
    protected static $tabla = 'evidencias';
    protected static $columnasDB = ['id', 'proyecto_id', 'actividad_id', 'fecha', 'descripcion', 'foto', 'departamento', 'categoria', 'usuario_id'];

    public $id, $proyecto_id, $actividad_id, $fecha, $descripcion, $foto, $departamento, $categoria, $usuario_id;
    public $creado;

    public const CARPETA = 'evidencias';

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->actividad_id = !empty($args['actividad_id']) ? (int)$args['actividad_id'] : null;
        $this->fecha = $args['fecha'] ?? date('Y-m-d');
        $this->descripcion = mb_substr(trim((string)($args['descripcion'] ?? '')), 0, 300);
        $this->foto = $args['foto'] ?? '';
        $this->departamento = mb_substr(trim((string)($args['departamento'] ?? '')), 0, 100);
        $this->categoria = mb_substr(trim((string)($args['categoria'] ?? '')), 0, 100);
        $this->usuario_id = $args['usuario_id'] ?? null;
    }

    public function url(): string
    {
        return '/uploads/' . self::CARPETA . '/' . basename((string)$this->foto);
    }

    public function bytes(): int
    {
        $r = __DIR__ . '/../public/uploads/' . self::CARPETA . '/' . basename((string)$this->foto);
        return is_file($r) ? (int)filesize($r) : 0;
    }

    public function eliminarArchivo(): void
    {
        Imagenes::eliminarEn(self::CARPETA, $this->foto);
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM evidencias WHERE proyecto_id = $proyectoId ORDER BY fecha DESC, id DESC");
    }
}
