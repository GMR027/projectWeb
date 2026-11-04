<?php

namespace Model;

/** Horas muertas o improductivas del día, con su causa */
class ReporteTiempo extends ActiveRecord
{
    protected static $tabla = 'reporte_tiempos';
    protected static $columnasDB = ['id', 'reporte_id', 'actividad_id', 'horas', 'causa', 'nota'];

    public const CAUSAS = [
        'lluvia' => 'Lluvia',
        'falta_material' => 'Falta de material',
        'espera' => 'Espera / instrucción',
        'falla_equipo' => 'Falla de equipo',
        'seguridad' => 'Seguridad',
        'otra' => 'Otra',
    ];

    public $id;
    public $reporte_id;
    public $actividad_id;
    public $horas;
    public $causa;
    public $nota;

    public static function deReporte(int $reporteId): array
    {
        return self::consultarSQL("SELECT * FROM reporte_tiempos WHERE reporte_id = $reporteId ORDER BY id ASC");
    }

    public static function totalHoras(int $reporteId): float
    {
        $r = self::$db->query("SELECT COALESCE(SUM(horas), 0) total FROM reporte_tiempos WHERE reporte_id = $reporteId");
        return $r ? (float)($r->fetch_assoc()['total'] ?? 0) : 0.0;
    }
}
