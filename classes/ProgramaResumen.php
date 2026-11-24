<?php

namespace Classes;

/**
 * Resumen del programa de obra para la cabecera (KPIs y bandas de meses del Gantt).
 *
 * Son funciones puras sobre las filas que ya prepara GanttRenderer: no consultan
 * la base de datos, así que se pueden probar sin conexión y no añaden consultas
 * a la vista.
 */
class ProgramaResumen
{
    /**
     * Cuenta partidas, actividades, subactividades, estados, hitos y restricciones.
     *
     * @param array $filas filas de GanttRenderer: partida, actividad o subactividad
     * @param float $avance avance ponderado del proyecto
     */
    public static function deFilas(array $filas, float $avance = 0.0): array
    {
        $r = [
            'partidas' => 0,
            'actividades' => 0,
            'subactividades' => 0,
            'completadas' => 0,
            'en_proceso' => 0,
            'no_iniciadas' => 0,
            'hitos' => 0,
            'restricciones' => 0,
            'con_restriccion' => 0,
            'prioridad_critica' => 0,
            'avance' => round($avance, 1),
        ];

        foreach ($filas as $f) {
            $tipo = (string)($f['tipo'] ?? '');
            if ($tipo === 'partida') {
                $r['partidas']++;
                continue;
            }
            if ($tipo !== 'actividad' && $tipo !== 'subactividad') continue;

            $r[$tipo === 'actividad' ? 'actividades' : 'subactividades']++;

            $status = (string)($f['status'] ?? '');
            if ($status === 'completada') $r['completadas']++;
            elseif ($status === 'en_proceso') $r['en_proceso']++;
            else $r['no_iniciadas']++;

            if (!empty($f['hito'])) $r['hitos']++;
            if (!empty($f['tiene_restriccion'])) $r['con_restriccion']++;
            if (($f['prioridad'] ?? '') === 'critica') $r['prioridad_critica']++;

            // El Gantt trae las restricciones activas de cada fila: se suman todas,
            // no solo se marca si la actividad tiene alguna.
            $r['restricciones'] += count($f['restricciones'] ?? []);
        }

        return $r;
    }

    /**
     * Agrupa las semanas del Gantt en bandas de mes consecutivas.
     *
     * @return array<int,array{clave:string,nombre:string,corto:string,span:int,inicio:string,fin:string}>
     */
    public static function mesesDeSemanas(array $semanas): array
    {
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $out = [];

        foreach ($semanas as $s) {
            $inicio = (string)($s['inicio'] ?? '');
            if ($inicio === '') continue;

            $t = strtotime($inicio);
            if (!$t) continue;
            $clave = date('Y-m', $t);
            $numMes = (int)date('n', $t);
            $fin = (string)($s['fin'] ?? $inicio);

            if (!$out || $out[count($out) - 1]['clave'] !== $clave) {
                $out[] = [
                    'clave' => $clave,
                    'nombre' => $meses[$numMes] . ' ' . date('Y', $t),
                    'corto' => mb_strtoupper(mb_substr($meses[$numMes], 0, 3)) . ' ' . date('y', $t),
                    'span' => 0,
                    'inicio' => $inicio,
                    'fin' => $fin,
                ];
            }

            $i = count($out) - 1;
            $out[$i]['span']++;
            $out[$i]['fin'] = $fin;
        }

        return $out;
    }

    /** Días hábiles que quedan del proyecto desde hoy (0 si ya terminó). */
    public static function diasHabilesRestantes(Calendario $calendario, ?string $hoy = null, ?string $fin = null): int
    {
        $hoy = $hoy ?: date('Y-m-d');
        if (!$fin || $fin < $hoy) return 0;
        return (int)round($calendario->calcularDuracion($hoy, $fin));
    }
}
