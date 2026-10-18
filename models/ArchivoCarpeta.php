<?php

namespace Model;

/** Carpeta virtual del archivero: agrupa archivos (compras, ingeniería, calidad…) sin mover nada en disco */
class ArchivoCarpeta extends ActiveRecord
{
    protected static $tabla = 'archivo_carpetas';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre'];

    public $id;
    public $proyecto_id;
    public $nombre;
    // Unión (solo lectura)
    public $total;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->total = $args['total'] ?? 0;
    }

    public function validar(): array
    {
        self::$alertas = [];
        $n = trim($this->nombre);
        if ($n === '') self::$alertas['error'][] = 'El nombre de la carpeta es obligatorio';
        if (mb_strlen($n) > 100) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 100)';
        if ($n !== '') {
            $e = self::$db->escape_string(mb_strtolower($n));
            if (self::consultarSQL("SELECT * FROM archivo_carpetas WHERE proyecto_id = " . (int)$this->proyecto_id . " AND LOWER(nombre) = '$e' AND id <> " . (int)$this->id . " LIMIT 1")) {
                self::$alertas['error'][] = 'Ya existe una carpeta con ese nombre';
            }
        }
        return self::$alertas;
    }

    /** Carpetas del proyecto con el número de archivos de cada una */
    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT c.*, (SELECT COUNT(*) FROM archivos a WHERE a.carpeta_id = c.id) AS total
            FROM archivo_carpetas c WHERE c.proyecto_id = $proyectoId ORDER BY c.nombre ASC");
    }

    public static function sinCarpeta(int $proyectoId): int
    {
        $r = self::$db->query("SELECT COUNT(*) AS n FROM archivos WHERE proyecto_id = $proyectoId AND carpeta_id IS NULL");
        return (int)($r->fetch_assoc()['n'] ?? 0);
    }
}
