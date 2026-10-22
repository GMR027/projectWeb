<?php

namespace Classes;

use Model\Proyecto;

/**
 * Calendario iCalendar (.ics) del proyecto para suscribirse desde el celular: actividades pendientes de la semana pasada
 * a las próximas 4 semanas, todos los hitos y la fecha límite de los RFI abiertos. Eventos de día completo con UID estable,
 * así que al actualizarse el programa el calendario del teléfono corrige solo los eventos existentes.
 */
class CalendarioIcs
{
    use AccesoDb;

    public const SEMANAS_ATRAS = 1;
    public const SEMANAS_ADELANTE = 4;

    private static function texto(string $t): string
    {
        return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\;", "\\,", "\\n", "\\n", "\\n"], $t);
    }

    /** Plegado de líneas a 75 octetos (sin partir caracteres UTF-8) */
    private static function plegar(string $linea): string
    {
        $out = '';
        $max = 75;
        while (strlen($linea) > $max) {
            $corte = $max;
            while ($corte > 0 && (ord($linea[$corte]) & 0xC0) === 0x80) $corte--;
            $out .= substr($linea, 0, $corte) . "\r\n ";
            $linea = substr($linea, $corte);
            $max = 74;   // la continuación empieza con un espacio, que también cuenta
        }
        return $out . $linea;
    }

    private static function dia(string $ymd): string
    {
        return str_replace('-', '', $ymd);
    }

    public static function generar(Proyecto $proyecto): string
    {
        $pid = (int)$proyecto->id;
        $ini = date('Y-m-d', strtotime('monday this week -' . self::SEMANAS_ATRAS . ' week'));
        $fin = date('Y-m-d', strtotime('sunday this week +' . self::SEMANAS_ADELANTE . ' week'));
        $host = $_SERVER['HTTP_HOST'] ?? 'projectweb';
        $sello = gmdate('Ymd\THis\Z');
        $l = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//ProjectWeb//Programa de obra//ES', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::texto($proyecto->nombre), 'X-WR-TIMEZONE:' . date_default_timezone_get(), 'REFRESH-INTERVAL;VALUE=DURATION:PT6H', 'X-PUBLISHED-TTL:PT6H'];
        $evento = function (string $uid, string $inicio, string $finInclusivo, string $titulo, string $desc, string $cat) use (&$l, $sello, $host) {
            $fin = date('Y-m-d', strtotime($finInclusivo . ' +1 day'));   // DTEND de un evento de día completo es exclusivo
            array_push($l, 'BEGIN:VEVENT', "UID:$uid@$host", "DTSTAMP:$sello", 'DTSTART;VALUE=DATE:' . self::dia($inicio), 'DTEND;VALUE=DATE:' . self::dia($fin),
                'SUMMARY:' . self::texto($titulo), 'DESCRIPTION:' . self::texto($desc), 'CATEGORIES:' . self::texto($cat), 'TRANSP:TRANSPARENT', 'END:VEVENT');
        };
        foreach (self::filas("SELECT a.*, p.nombre partida FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE p.proyecto_id = $pid AND ((a.hito = 1) OR (a.progreso < 100 AND a.fecha_inicio <= '$fin' AND a.fecha_fin >= '$ini')) ORDER BY a.fecha_inicio, a.codigo") as $a) {
            $desc = $a['partida'] . "\nAvance: " . (int)$a['progreso'] . ' %' . (!empty($a['responsable']) ? "\nResponsable: " . $a['responsable'] : '');
            if ((int)$a['hito']) $evento('hito-' . $a['id'], $a['fecha_inicio'], $a['fecha_inicio'], '◆ ' . $a['nombre'], $desc, 'Hito');
            else $evento('act-' . $a['id'], $a['fecha_inicio'], $a['fecha_fin'], $a['codigo'] . ' ' . $a['nombre'] . ' (' . (int)$a['progreso'] . '%)', $desc, 'Actividad');
        }
        foreach (self::filas("SELECT id, numero, asunto, responsable, fecha_limite FROM rfis WHERE proyecto_id = $pid AND estado = 'abierto' AND fecha_limite IS NOT NULL") as $r) {
            $evento('rfi-' . $r['id'], $r['fecha_limite'], $r['fecha_limite'], 'RFI vence: ' . $r['numero'] . ' ' . $r['asunto'], $r['responsable'] ? 'Responsable: ' . $r['responsable'] : '', 'RFI');
        }
        $l[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([self::class, 'plegar'], $l)) . "\r\n";
    }
}
