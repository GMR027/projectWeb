<?php

namespace Model;

class Material extends ActiveRecord
{
    protected static $tabla = 'materiales';
    protected static $columnasDB = ['id', 'proyecto_id', 'referencia', 'nombre', 'especialidad', 'link_web', 'imagen', 'ficha_tecnica', 'certificado_calidad', 'cantidad', 'unidad', 'compra', 'fecha_compra_prog', 'fecha_compra_real', 'fecha_entrega_prog', 'fecha_entrega_real'];
    public const COMPRA = ['pendiente' => 'Pendiente', 'comprado' => 'Comprado'];
    private const FECHAS = ['fecha_compra_prog' => 'compra programada', 'fecha_compra_real' => 'compra real', 'fecha_entrega_prog' => 'entrega programada', 'fecha_entrega_real' => 'entrega real'];

    public $id;
    public $proyecto_id;
    public $referencia;
    public $nombre;
    public $especialidad;
    public $link_web;
    public $imagen;
    public $ficha_tecnica;
    public $certificado_calidad;
    public $cantidad;
    public $unidad;
    public $compra;
    public $fecha_compra_prog;
    public $fecha_compra_real;
    public $fecha_entrega_prog;
    public $fecha_entrega_real;

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
        $this->cantidad = $args['cantidad'] ?? null;
        $this->unidad = $args['unidad'] ?? '';
        $this->compra = $args['compra'] ?? 'pendiente';
        foreach (array_keys(self::FECHAS) as $c) $this->$c = $args[$c] ?? null;
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
        if ($this->cantidad !== null && $this->cantidad !== '' && (!is_numeric($this->cantidad) || $this->cantidad < 0 || $this->cantidad >= 1e10)) {
            self::$alertas['error'][] = 'La cantidad no es válida';
        }
        foreach (self::FECHAS as $c => $etq) {
            if ($this->$c && !\DateTime::createFromFormat('Y-m-d', $this->$c)) {
                self::$alertas['error'][] = "La fecha de $etq no es válida";
            }
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

    /** Marca como comprado (fecha real = hoy) o pendiente (sin fecha real) */
    public function fijarCompra(string $estado): void
    {
        $this->compra = $estado === 'comprado' ? 'comprado' : 'pendiente';
        $this->fecha_compra_real = $this->compra === 'comprado' ? ($this->fecha_compra_real ?: date('Y-m-d')) : null;
    }

    /** Marca como entregado (fecha real = hoy) o no entregado */
    public function fijarEntregado(bool $entregado): void
    {
        $this->fecha_entrega_real = $entregado ? ($this->fecha_entrega_real ?: date('Y-m-d')) : null;
    }

    public function entregado(): bool
    {
        return (bool)$this->fecha_entrega_real;
    }

    /** Copia las fechas de compra y entrega a los renglones del MSR de este material */
    public function sincronizarMsr(): void
    {
        $sets = [];
        foreach (array_keys(self::FECHAS) as $c) {
            $sets[] = "$c = " . ($this->$c ? "'" . self::$db->escape_string($this->$c) . "'" : 'NULL');
        }
        self::$db->query("UPDATE msr_items SET " . implode(', ', $sets) . " WHERE material_id = " . (int)$this->id);
    }

    /** Nombre del PDF: nombrematerial-especialidad.pdf */
    public function nombreFicha(): string
    {
        return slug_archivo($this->nombre) . '-' . slug_archivo($this->especialidad ?: 'general') . '.pdf';
    }
}
