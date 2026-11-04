<?php

namespace Model;

/** Foto del parte diario con actividad, leyenda y ubicación */
class ReporteFoto extends ActiveRecord
{
    protected static $tabla = 'reporte_fotos';
    protected static $columnasDB = ['id', 'reporte_id', 'archivo', 'actividad_id', 'leyenda', 'lat', 'lng', 'tomada_en'];

    public $id;
    public $reporte_id;
    public $archivo;
    public $actividad_id;
    public $leyenda;
    public $lat;
    public $lng;
    public $tomada_en;

    public static function deReporte(int $reporteId): array
    {
        return self::consultarSQL("SELECT * FROM reporte_fotos WHERE reporte_id = $reporteId ORDER BY id ASC");
    }
}
