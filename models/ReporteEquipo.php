<?php

namespace Model;

/** Equipo que trabajó en el parte diario, con horas, operador y costo */
class ReporteEquipo extends ActiveRecord
{
    protected static $tabla = 'reporte_equipo';
    protected static $columnasDB = ['id', 'reporte_id', 'equipo_id', 'nombre', 'actividad_id', 'horas', 'operador', 'importe'];

    public $id;
    public $reporte_id;
    public $equipo_id;
    public $nombre;
    public $actividad_id;
    public $horas;
    public $operador;
    public $importe;

    public static function deReporte(int $reporteId): array
    {
        return self::consultarSQL("SELECT * FROM reporte_equipo WHERE reporte_id = $reporteId ORDER BY id ASC");
    }

    public static function costo(int $reporteId): float
    {
        $r = self::$db->query("SELECT COALESCE(SUM(importe), 0) total FROM reporte_equipo WHERE reporte_id = $reporteId");
        return $r ? (float)($r->fetch_assoc()['total'] ?? 0) : 0.0;
    }
}
