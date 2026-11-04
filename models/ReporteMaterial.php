<?php

namespace Model;

/** Material recibido, consumido o devuelto en el parte diario */
class ReporteMaterial extends ActiveRecord
{
    protected static $tabla = 'reporte_materiales';
    protected static $columnasDB = ['id', 'reporte_id', 'material_id', 'nombre', 'actividad_id', 'cantidad', 'unidad', 'tipo', 'proveedor', 'remision'];

    public const TIPOS = ['recibido' => 'Recibido', 'consumido' => 'Consumido', 'devuelto' => 'Devuelto'];

    public $id;
    public $reporte_id;
    public $material_id;
    public $nombre;
    public $actividad_id;
    public $cantidad;
    public $unidad;
    public $tipo;
    public $proveedor;
    public $remision;

    public static function deReporte(int $reporteId): array
    {
        return self::consultarSQL("SELECT * FROM reporte_materiales WHERE reporte_id = $reporteId ORDER BY id ASC");
    }

    public static function costoConsumido(int $reporteId): float
    {
        $r = self::$db->query("SELECT COALESCE(SUM(rm.cantidad * m.costo_unitario), 0) total
            FROM reporte_materiales rm LEFT JOIN materiales m ON m.id = rm.material_id
            WHERE rm.reporte_id = $reporteId AND rm.tipo = 'consumido'");
        return $r ? (float)($r->fetch_assoc()['total'] ?? 0) : 0.0;
    }
}
