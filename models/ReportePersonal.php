<?php

namespace Model;

/** Personal que trabajó en el parte diario, con sus horas y la actividad a la que se imputa */
class ReportePersonal extends ActiveRecord
{
    protected static $tabla = 'reporte_personal';
    protected static $columnasDB = ['id', 'reporte_id', 'personal_id', 'nombre', 'actividad_id', 'horas_normal', 'horas_extra', 'cuadrilla', 'observacion'];

    public $id;
    public $reporte_id;
    public $personal_id;
    public $nombre;
    public $actividad_id;
    public $horas_normal;
    public $horas_extra;
    public $cuadrilla;
    public $observacion;

    public static function deReporte(int $reporteId): array
    {
        return self::consultarSQL("SELECT * FROM reporte_personal WHERE reporte_id = $reporteId ORDER BY id ASC");
    }

    public static function costo(int $reporteId): float
    {
        $r = self::$db->query("SELECT COALESCE(SUM(rp.horas_normal * COALESCE(p.tarifa_hora, 0) + rp.horas_extra * COALESCE(p.tarifa_hora, 0) * 1.5), 0) total
            FROM reporte_personal rp LEFT JOIN personal p ON p.id = rp.personal_id
            WHERE rp.reporte_id = $reporteId");
        return $r ? (float)($r->fetch_assoc()['total'] ?? 0) : 0.0;
    }
}
