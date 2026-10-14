<?php

namespace Model;

class MsrItem extends ActiveRecord
{
    protected static $tabla = 'msr_items';
    protected static $columnasDB = ['id', 'proyecto_id', 'submittal_id', 'material_id', 'seccion', 'fecha_envio_requerida', 'no_submittal_contratista', 'fecha_envio_real', 'fecha_respuesta', 'status', 'fecha_compra_prog', 'fecha_instalacion_prog', 'fecha_compra_real', 'fecha_entrega_prog', 'fecha_entrega_real', 'proveedor', 'observaciones', 'orden'];

    /** Campos editables en línea y su tipo */
    public const EDITABLES = [
        'seccion' => 'texto', 'no_submittal_contratista' => 'texto', 'proveedor' => 'texto', 'observaciones' => 'texto',
        'fecha_envio_requerida' => 'fecha', 'fecha_envio_real' => 'fecha', 'fecha_respuesta' => 'fecha',
        'fecha_compra_prog' => 'fecha', 'fecha_instalacion_prog' => 'fecha', 'fecha_compra_real' => 'fecha',
        'fecha_entrega_prog' => 'fecha', 'fecha_entrega_real' => 'fecha',
    ];
    /** Ciclo del toggle de status */
    public const STATUS_CICLO = ['P' => 'A', 'A' => 'RA', 'RA' => 'P'];

    public $id, $proyecto_id, $submittal_id, $material_id, $seccion, $fecha_envio_requerida, $no_submittal_contratista,
        $fecha_envio_real, $fecha_respuesta, $status, $fecha_compra_prog, $fecha_instalacion_prog, $fecha_compra_real,
        $fecha_entrega_prog, $fecha_entrega_real, $proveedor, $observaciones, $orden;
    // Datos de unión (solo lectura)
    public $mat_nombre, $mat_referencia, $mat_imagen, $sub_numero;

    public function __construct($args = [])
    {
        foreach (static::$columnasDB as $c) {
            $this->$c = $args[$c] ?? null;
        }
        $this->status = $args['status'] ?? 'P';
        foreach (['seccion', 'no_submittal_contratista', 'proveedor', 'observaciones'] as $c) $this->$c = $args[$c] ?? '';
        $this->orden = $args['orden'] ?? 0;
    }

    /** Renglones del MSR con datos del material y del paquete */
    public static function deProyecto(int $proyectoId): array
    {
        return self::consultarSQL(
            "SELECT i.*, m.nombre AS mat_nombre, m.referencia AS mat_referencia, m.imagen AS mat_imagen, s.numero_submittal AS sub_numero
             FROM msr_items i
             JOIN materiales m ON m.id = i.material_id
             JOIN submittals s ON s.id = i.submittal_id
             WHERE i.proyecto_id = $proyectoId ORDER BY i.orden ASC, i.id ASC"
        );
    }

    /** Días entre la compra programada y la entrega programada (Delivery Time Schedule) */
    public function diasEntrega(): ?int
    {
        if (!$this->fecha_compra_prog || !$this->fecha_entrega_prog) return null;
        return (int)(new \DateTime($this->fecha_compra_prog))->diff(new \DateTime($this->fecha_entrega_prog))->format('%r%a');
    }

    public function esCritico(int $umbral): bool
    {
        $d = $this->diasEntrega();
        return $d !== null && $d >= $umbral;
    }

    public function compraAtrasada(): bool
    {
        return $this->fecha_compra_prog && !$this->fecha_compra_real && $this->fecha_compra_prog < date('Y-m-d');
    }

    public function entregaAtrasada(): bool
    {
        return $this->fecha_entrega_prog && !$this->fecha_entrega_real && $this->fecha_entrega_prog < date('Y-m-d');
    }
}
