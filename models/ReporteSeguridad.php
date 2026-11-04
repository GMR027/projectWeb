<?php

namespace Model;

/** Charla de seguridad y EPP del parte diario; se liga al incidente si hubo uno */
class ReporteSeguridad extends ActiveRecord
{
    protected static $tabla = 'reporte_seguridad';
    protected static $columnasDB = ['id', 'reporte_id', 'charla_tema', 'asistentes', 'epp_ok', 'incidente_id'];

    public $id;
    public $reporte_id;
    public $charla_tema;
    public $asistentes;
    public $epp_ok;
    public $incidente_id;

    public static function deReporte(int $reporteId): ?ReporteSeguridad
    {
        $r = self::consultarSQL("SELECT * FROM reporte_seguridad WHERE reporte_id = $reporteId LIMIT 1");
        return $r[0] ?? null;
    }
}
