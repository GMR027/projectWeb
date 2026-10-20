<?php

namespace Model;

class MsrItem extends ActiveRecord
{
    protected static $tabla = 'msr_items';
    protected static $columnasDB = ['id', 'proyecto_id', 'msr_id', 'submittal_id', 'material_id', 'seccion', 'fecha_envio_requerida', 'no_submittal_contratista', 'fecha_envio_real', 'fecha_respuesta', 'status', 'fecha_compra_prog', 'fecha_instalacion_prog', 'fecha_compra_real', 'fecha_entrega_prog', 'fecha_entrega_real', 'proveedor', 'observaciones', 'extras_json', 'orden'];

    /** Campos editables en línea y su tipo */
    public const EDITABLES = [
        'seccion' => 'texto', 'no_submittal_contratista' => 'texto', 'proveedor' => 'texto', 'observaciones' => 'texto',
        'fecha_envio_requerida' => 'fecha', 'fecha_envio_real' => 'fecha', 'fecha_respuesta' => 'fecha',
        'fecha_compra_prog' => 'fecha', 'fecha_instalacion_prog' => 'fecha', 'fecha_compra_real' => 'fecha',
        'fecha_entrega_prog' => 'fecha', 'fecha_entrega_real' => 'fecha',
    ];
    /** Ciclo del toggle de status */
    public const STATUS_CICLO = ['P' => 'A', 'A' => 'AN', 'AN' => 'RA', 'RA' => 'P'];

    public $id, $proyecto_id, $msr_id, $submittal_id, $material_id, $seccion, $fecha_envio_requerida, $no_submittal_contratista,
        $fecha_envio_real, $fecha_respuesta, $status, $fecha_compra_prog, $fecha_instalacion_prog, $fecha_compra_real,
        $fecha_entrega_prog, $fecha_entrega_real, $proveedor, $observaciones, $extras_json, $orden;
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

    public static function getDB(): \mysqli
    {
        return self::$db;
    }

    /** Valor de una columna de texto libre del usuario (txt_N) */
    public function extra(string $k): string
    {
        $d = json_decode((string)$this->extras_json, true);
        return is_array($d) ? (string)($d[$k] ?? '') : '';
    }

    public function fijarExtra(string $k, string $v): void
    {
        $d = json_decode((string)$this->extras_json, true);
        $d = is_array($d) ? $d : [];
        if ($v === '') unset($d[$k]); else $d[$k] = $v;
        $this->extras_json = $d ? json_encode($d, JSON_UNESCAPED_UNICODE) : null;
    }

    /** Renglones del MSR con datos del material y del paquete */
    public static function deProyecto(int $proyectoId, ?int $msrId = null): array
    {
        $filtro = $msrId !== null ? " AND i.msr_id = $msrId" : '';
        return self::consultarSQL(
            "SELECT i.*, m.nombre AS mat_nombre, m.referencia AS mat_referencia, m.imagen AS mat_imagen, s.numero_submittal AS sub_numero
             FROM msr_items i
             JOIN materiales m ON m.id = i.material_id
             LEFT JOIN submittals s ON s.id = i.submittal_id
             WHERE i.proyecto_id = $proyectoId$filtro ORDER BY i.orden ASC, i.id ASC"
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

    /**
     * Un renglón nuevo hereda lo ya capturado del mismo material en otro MSR del proyecto
     * (prefiere el que viene de un paquete de submittal y el que ya tiene status).
     * Con submittal, el status y las fechas de envío/respuesta los manda el submittal y no se copian.
     */
    public static function heredarDeOtroMsr(int $itemId): void
    {
        $db = self::$db;
        $n = self::find($itemId);
        if (!$n) return;
        $res = $db->query("SELECT * FROM msr_items WHERE material_id = " . (int)$n->material_id . " AND proyecto_id = " . (int)$n->proyecto_id
            . " AND id <> $itemId AND (msr_id <> " . (int)$n->msr_id . " OR msr_id IS NULL)"
            . " ORDER BY (submittal_id IS NOT NULL) DESC, (status <> 'P') DESC, id ASC LIMIT 1");
        $o = $res ? $res->fetch_assoc() : null;
        if (!$o) return;
        $campos = ['fecha_envio_requerida', 'no_submittal_contratista', 'fecha_instalacion_prog', 'proveedor', 'observaciones'];
        if (!$n->submittal_id) $campos = array_merge($campos, ['status', 'fecha_envio_real', 'fecha_respuesta']);
        $sets = [];
        foreach ($campos as $c) {
            $sets[] = "$c = " . ($o[$c] === null || $o[$c] === '' ? ($c === 'status' ? "'P'" : (in_array($c, ['proveedor', 'observaciones', 'no_submittal_contratista'], true) ? "''" : 'NULL')) : "'" . $db->escape_string($o[$c]) . "'");
        }
        $db->query("UPDATE msr_items SET " . implode(', ', $sets) . " WHERE id = $itemId");
    }
}
