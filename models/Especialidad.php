<?php

namespace Model;

/** Catálogo de especialidades; es propio de cada proyecto (no se comparte entre proyectos ni usuarios) */
class Especialidad extends ActiveRecord
{
    protected static $tabla = 'especialidades';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre'];

    public $id;
    public $proyecto_id;
    public $nombre;
    public $n_materiales, $n_submittals;   // conteos (solo lectura)

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
    }

    public function validar(): array
    {
        self::$alertas = [];
        $n = trim($this->nombre);
        if ($n === '') self::$alertas['error'][] = 'El nombre de la especialidad es obligatorio';
        elseif (mb_strlen($n) > 100) self::$alertas['error'][] = 'El nombre es demasiado largo (máx. 100)';
        else {
            $e = self::$db->escape_string($n);
            $otro = self::$db->query("SELECT id FROM especialidades WHERE proyecto_id = " . (int)$this->proyecto_id . " AND nombre = '$e'" . ($this->id ? ' AND id <> ' . (int)$this->id : '') . " LIMIT 1");
            if ($otro && $otro->num_rows) self::$alertas['error'][] = 'Ya existe una especialidad con ese nombre';
        }
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL(
            "SELECT e.*,
                (SELECT COUNT(*) FROM materiales m WHERE m.proyecto_id = e.proyecto_id AND m.especialidad = e.nombre) AS n_materiales,
                (SELECT COUNT(*) FROM submittals s WHERE s.proyecto_id = e.proyecto_id AND s.especialidad = e.nombre) AS n_submittals
             FROM especialidades e WHERE e.proyecto_id = $proyectoId ORDER BY e.nombre ASC"
        );
    }

    public static function deProyectoPorId(int $proyectoId, $id): ?self
    {
        $e = self::find(filter_var($id, FILTER_VALIDATE_INT));
        return $e && (int)$e->proyecto_id === $proyectoId ? $e : null;
    }

    /** Nombres del catálogo, para selects */
    public static function nombres(int $proyectoId): array
    {
        return array_map(fn($e) => $e->nombre, self::deProyecto($proyectoId));
    }

    /**
     * Devuelve el nombre canónico: el del catálogo si ya existe (sin importar mayúsculas),
     * o lo agrega (en formato título) cuando no está. '' si viene vacío.
     */
    public static function resolver(int $proyectoId, string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
        if ($nombre === '') return '';
        $n = self::$db->escape_string($nombre);
        $r = self::$db->query("SELECT nombre FROM especialidades WHERE proyecto_id = $proyectoId AND nombre = '$n' LIMIT 1");
        if ($r && ($fila = $r->fetch_assoc())) return $fila['nombre'];
        if ($nombre === mb_strtoupper($nombre)) $nombre = mb_convert_case($nombre, MB_CASE_TITLE);   // «ESTRUCTURA» → «Estructura»
        $n = self::$db->escape_string(mb_substr($nombre, 0, 100));
        self::$db->query("INSERT IGNORE INTO especialidades (proyecto_id, nombre) VALUES ($proyectoId, '$n')");
        return mb_substr($nombre, 0, 100);
    }

    /** Cambia el nombre y lo propaga a materiales y submittals que la usaban */
    public function renombrar(string $anterior): void
    {
        if ($anterior === $this->nombre) return;
        $a = self::$db->escape_string($anterior);
        $n = self::$db->escape_string($this->nombre);
        $pid = (int)$this->proyecto_id;
        self::$db->query("UPDATE materiales SET especialidad = '$n' WHERE proyecto_id = $pid AND especialidad = '$a'");
        self::$db->query("UPDATE submittals SET especialidad = '$n' WHERE proyecto_id = $pid AND especialidad = '$a'");
    }
}
