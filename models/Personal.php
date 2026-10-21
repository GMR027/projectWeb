<?php

namespace Model;

class Personal extends ActiveRecord
{
    protected static $tabla = 'personal';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'departamento', 'imagen'];

    public $id;
    public $proyecto_id;
    public $nombre;
    public $departamento;
    public $imagen;

    public const TIPOS = ['directivo' => 'Directivo', 'administrativo' => 'Administrativo', 'operativo' => 'Operativo'];
    /** Datos que viven en personal_contacto: columna => longitud máxima */
    public const CONTACTO = ['tipo' => 20, 'cargo' => 100, 'empresa' => 150, 'telefono' => 40, 'correo' => 150, 'direccion' => 250, 'rfc' => 13, 'notas' => 5000];

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->departamento = $args['departamento'] ?? '';
        $this->imagen = $args['imagen'] ?? null;
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->nombre)) self::$alertas['error'][] = 'El nombre es obligatorio';
        if (mb_strlen($this->nombre) > 150) self::$alertas['error'][] = 'El nombre es demasiado largo';
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL("SELECT * FROM personal WHERE proyecto_id = $proyectoId ORDER BY departamento ASC, nombre ASC");
    }

    /** Contacto y datos adicionales de una persona (vacíos si no hay) */
    public function contacto(): array
    {
        $r = $this->id ? self::$db->query("SELECT * FROM personal_contacto WHERE personal_id = " . (int)$this->id) : null;
        $f = $r ? $r->fetch_assoc() : null;
        return array_merge(array_fill_keys(array_keys(self::CONTACTO), ''), array_map('strval', array_intersect_key($f ?: [], self::CONTACTO)));
    }

    /** Contactos de todo el personal del proyecto: [personal_id => datos] */
    public static function contactosDeProyecto(int $proyectoId): array
    {
        $r = self::$db->query("SELECT c.* FROM personal_contacto c JOIN personal p ON p.id = c.personal_id WHERE p.proyecto_id = $proyectoId");
        $o = [];
        while ($r && ($f = $r->fetch_assoc())) $o[(int)$f['personal_id']] = array_merge(array_fill_keys(array_keys(self::CONTACTO), ''), array_map('strval', array_intersect_key($f, self::CONTACTO)));
        return $o;
    }

    /** Guarda solo las claves recibidas; las demás conservan su valor (no usar REPLACE: borraría los datos que no se envían) */
    public static function guardarContacto(int $personalId, array $datos): void
    {
        $datos = array_intersect_key($datos, self::CONTACTO);
        $cols = ['personal_id'];
        $vals = [(string)$personalId];
        $upd = [];
        foreach ($datos as $c => $v) {
            $v = mb_substr(trim((string)$v), 0, self::CONTACTO[$c]);
            $cols[] = $c;
            $vals[] = "'" . self::$db->escape_string($v) . "'";
            $upd[] = "$c = VALUES($c)";
        }
        if (!$upd) return;
        self::$db->query("INSERT INTO personal_contacto (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ") ON DUPLICATE KEY UPDATE " . implode(',', $upd));
    }

    /** Valida los datos adicionales (RFC con formato SAT, correo, tipo) */
    public static function validarContacto(array $d): array
    {
        $e = [];
        if (($d['tipo'] ?? '') !== '' && !isset(self::TIPOS[$d['tipo']])) $e[] = 'El tipo de personal no es válido';
        if (($d['rfc'] ?? '') !== '' && !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $d['rfc'])) $e[] = 'El RFC no tiene un formato válido (ej. XAXX010101000)';
        if (($d['correo'] ?? '') !== '' && !filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) $e[] = 'El correo no es válido';
        return $e;
    }

    /** Personal del proyecto indexado por id */
    public static function indexado(int $proyectoId): array
    {
        $r = [];
        foreach (self::deProyecto($proyectoId) as $p) $r[(int)$p->id] = $p;
        return $r;
    }
}
