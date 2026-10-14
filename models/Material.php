<?php

namespace Model;

class Material extends ActiveRecord
{
    protected static $tabla = 'materiales';
    protected static $columnasDB = ['id', 'proyecto_id', 'referencia', 'nombre', 'especialidad', 'link_web', 'imagen', 'ficha_tecnica', 'certificado_calidad'];

    public $id;
    public $proyecto_id;
    public $referencia;
    public $nombre;
    public $especialidad;
    public $link_web;
    public $imagen;
    public $ficha_tecnica;
    public $certificado_calidad;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->referencia = $args['referencia'] ?? '';
        $this->nombre = $args['nombre'] ?? '';
        $this->especialidad = $args['especialidad'] ?? '';
        $this->link_web = $args['link_web'] ?? null;
        $this->imagen = $args['imagen'] ?? null;
        $this->ficha_tecnica = $args['ficha_tecnica'] ?? null;
        $this->certificado_calidad = $args['certificado_calidad'] ?? null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->nombre)) {
            self::$alertas['error'][] = 'El nombre del material es obligatorio';
        }
        if (mb_strlen((string)$this->certificado_calidad) > 255) {
            self::$alertas['error'][] = 'El certificado de calidad es demasiado largo (máx. 255 caracteres)';
        }
        if ($this->link_web && !filter_var($this->link_web, FILTER_VALIDATE_URL)) {
            self::$alertas['error'][] = 'El link web no es una URL válida (incluye http:// o https://)';
        }
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM materiales WHERE proyecto_id = $proyectoId ORDER BY especialidad ASC, nombre ASC");
    }

    public static function buscar(int $proyectoId, string $nombre, string $referencia): ?self
    {
        $n = self::$db->escape_string(mb_strtolower(trim($nombre)));
        $r = self::$db->escape_string(mb_strtolower(trim($referencia)));
        $res = self::consultarSQL("SELECT * FROM materiales WHERE proyecto_id = $proyectoId AND LOWER(nombre) = '$n' AND LOWER(referencia) = '$r' LIMIT 1");
        return $res[0] ?? null;
    }

    /** Nombre del PDF: nombrematerial-especialidad.pdf */
    public function nombreFicha(): string
    {
        return slug_archivo($this->nombre) . '-' . slug_archivo($this->especialidad ?: 'general') . '.pdf';
    }
}
