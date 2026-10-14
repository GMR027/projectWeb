<?php

namespace Model;

class Curva extends ActiveRecord
{
    protected static $tabla = 'curvas';
    protected static $columnasDB = ['id', 'proyecto_id', 'nombre', 'material_id', 'cantidad', 'unidad', 'tipo_curva', 'fecha_inicio', 'fecha_fin'];

    public $id;
    public $proyecto_id;
    public $nombre;
    public $material_id;
    public $cantidad;
    public $unidad;
    public $tipo_curva;
    public $fecha_inicio;
    public $fecha_fin;
    // Unión (solo lectura)
    public $mat_nombre;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->material_id = $args['material_id'] ?? null;
        $this->cantidad = $args['cantidad'] ?? 0;
        $this->unidad = $args['unidad'] ?? 'm';
        $this->tipo_curva = $args['tipo_curva'] ?? 'campana';
        $this->fecha_inicio = $args['fecha_inicio'] ?? '';
        $this->fecha_fin = $args['fecha_fin'] ?? '';
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!trim($this->nombre)) self::$alertas['error'][] = 'El nombre de la curva es obligatorio';
        if ((int)$this->cantidad < 1) self::$alertas['error'][] = 'La cantidad debe ser un entero mayor a 0';
        foreach (['fecha_inicio' => 'inicio', 'fecha_fin' => 'fin'] as $c => $t) {
            $d = \DateTime::createFromFormat('Y-m-d', (string)$this->$c);
            if (!$d || $d->format('Y-m-d') !== $this->$c) self::$alertas['error'][] = "La fecha de $t no es válida";
        }
        if (empty(self::$alertas) && $this->fecha_fin < $this->fecha_inicio) {
            self::$alertas['error'][] = 'La fecha fin no puede ser anterior al inicio';
        }
        if (!in_array($this->tipo_curva, ['lineal', 'final', 'inicio', 'campana'], true)) {
            self::$alertas['error'][] = 'Tipo de curva no válido';
        }
        return self::$alertas;
    }

    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL(
            "SELECT c.*, m.nombre AS mat_nombre FROM curvas c LEFT JOIN materiales m ON m.id = c.material_id
             WHERE c.proyecto_id = $proyectoId ORDER BY c.nombre ASC"
        );
    }

    /** [periodo => valor|null] */
    public function reales(): array
    {
        $res = self::$db->query("SELECT periodo, valor FROM curva_reales WHERE curva_id = " . (int)$this->id);
        $out = [];
        while ($r = $res->fetch_assoc()) $out[(int)$r['periodo']] = $r['valor'] === null ? null : (float)$r['valor'];
        return $out;
    }

    /** Guarda (o borra si es null) el real de un periodo */
    public function guardarReal(int $periodo, ?float $valor): void
    {
        $id = (int)$this->id;
        $periodo = (int)$periodo;
        if ($valor === null) {
            self::$db->query("DELETE FROM curva_reales WHERE curva_id = $id AND periodo = $periodo");
        } else {
            $v = (float)$valor;
            self::$db->query("INSERT INTO curva_reales (curva_id, periodo, valor) VALUES ($id, $periodo, $v) ON DUPLICATE KEY UPDATE valor = $v");
        }
    }
}
