<?php

namespace Classes;

use Model\Partida;
use Model\Proyecto;

/**
 * Last Planner: una tarjeta (post-it) por día hábil y por partida con todas las actividades de ese día.
 * Se calcula al vuelo desde el programa de obra, por lo que siempre refleja las fechas vigentes.
 */
class LastPlanner
{
    public const POR_TARJETA = 4;   // actividades por post-it; si hay más se genera otra tarjeta del mismo día
    private const MAX_DIAS = 800;

    /** Días hábiles acumulados desde $ini: ['Y-m-d' => n]. Los días restantes entre dos fechas son acum[fin] - acum[día] */
    private static function acumuladoHabil(Calendario $cal, string $ini, string $fin): array
    {
        $acum = [];
        $n = 0;
        $f = new \DateTime($ini);
        $limite = new \DateTime($fin);
        for ($i = 0; $f <= $limite && $i < self::MAX_DIAS; $i++, $f->modify('+1 day')) {
            $k = $f->format('Y-m-d');
            if ($cal->esHabil($k)) $n++;
            $acum[$k] = $n;
        }
        return $acum;
    }

    /**
     * @return array[] tarjetas ['fecha','partida','items'=>[['actividad','dias','completada','restriccion']],'parte','partes']
     */
    public static function tarjetas(Proyecto $proyecto, Partida $partida, string $desde, string $hasta, bool $ocultarCompletadas = false): array
    {
        $acts = $partida->getActividades();
        if ($ocultarCompletadas) {
            $acts = array_values(array_filter($acts, fn($a) => !self::completada($a)));
        }
        if (!$acts) return [];

        $cal = new Calendario((int)$proyecto->id);
        $restricciones = \Controllers\LookaheadController::restriccionesActivas((int)$proyecto->id);
        $riesgos = Salud::riesgosActividades((int)$proyecto->id);

        $ini = min(array_map(fn($a) => $a->fecha_inicio, $acts));
        $fin = max(array_map(fn($a) => $a->fecha_fin, $acts));

        // Días hábiles acumulados desde el primer inicio; restantes(d, fin) = acum[fin] - acum[d]
        $acum = self::acumuladoHabil($cal, $ini, $fin);

        $tarjetas = [];
        $d = new \DateTime(max($desde, $ini));
        $tope = new \DateTime(min($hasta, $fin));
        for ($i = 0; $d <= $tope && $i < self::MAX_DIAS; $i++, $d->modify('+1 day')) {
            $dia = $d->format('Y-m-d');
            if (!$cal->esHabil($dia)) continue;
            $items = [];
            foreach ($acts as $a) {
                if ($a->fecha_inicio <= $dia && $dia <= $a->fecha_fin) {
                    $items[] = [
                        'actividad' => $a,
                        'dias' => ($acum[$a->fecha_fin] ?? $acum[$dia]) - $acum[$dia],   // 0 el último día
                        'completada' => self::completada($a),
                        'proceso' => !self::completada($a) && ((int)$a->progreso > 0 || $a->status === 'en_proceso'),
                        'restriccion' => !empty($restricciones[$a->id]),
                        'riesgo' => implode(' · ', array_column($riesgos[$a->id] ?? [], 'texto')),
                    ];
                }
            }
            if (!$items) continue;
            $grupos = array_chunk($items, self::POR_TARJETA);
            foreach ($grupos as $g => $grupo) {
                $tarjetas[] = ['fecha' => $dia, 'partida' => $partida, 'items' => $grupo, 'parte' => $g + 1, 'partes' => count($grupos)];
            }
        }
        return $tarjetas;
    }

    /**
     * "Mostrar todas": un post-it por día hábil del proyecto con las actividades de TODAS las partidas de ese día.
     * Cada renglón lleva su partida ('partida'); la tarjeta no pertenece a una sola partida ('partida' => null).
     */
    /** Rango [desde, hasta] de un período: «fechas» (campos vacíos = abierto) o «mes» (YYYY-MM). Sin período, todo el programa */
    public static function rangoPeriodo(string $periodo, string $desde, string $hasta, string $mes): array
    {
        $fecha = fn(string $v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        if ($periodo === 'fechas') return [$fecha($desde) ?: '1900-01-01', $fecha($hasta) ?: '2999-12-31'];
        if ($periodo === 'mes' && preg_match('/^\d{4}-\d{2}$/', $mes)) return [$mes . '-01', date('Y-m-t', strtotime($mes . '-01'))];
        return ['1900-01-01', '2999-12-31'];
    }

    /** Solo los post-its que traen alguna actividad con restricción */
    public static function conRestriccion(array $tarjetas): array
    {
        return array_values(array_filter($tarjetas, fn($t) => array_filter($t['items'], fn($i) => !empty($i['restriccion']))));
    }

    public static function tarjetasTodas(Proyecto $proyecto, bool $ocultarCompletadas = false, string $desde = '1900-01-01', string $hasta = '2999-12-31'): array
    {
        $acts = [];
        foreach ($proyecto->getPartidas() as $partida) {
            foreach ($partida->getActividades() as $a) {
                if ($ocultarCompletadas && self::completada($a)) continue;
                $acts[] = [$partida, $a];
            }
        }
        if (!$acts) return [];

        $cal = new Calendario((int)$proyecto->id);
        $restricciones = \Controllers\LookaheadController::restriccionesActivas((int)$proyecto->id);
        $riesgos = Salud::riesgosActividades((int)$proyecto->id);
        // Del inicio del proyecto a su fin (ampliado si alguna actividad queda fuera)
        $ini = min($proyecto->fecha_inicio, min(array_map(fn($x) => $x[1]->fecha_inicio, $acts)));
        $fin = max($proyecto->fecha_fin, max(array_map(fn($x) => $x[1]->fecha_fin, $acts)));

        $acum = self::acumuladoHabil($cal, $ini, $fin);
        $limite = new \DateTime(min($hasta, $fin));

        $tarjetas = [];
        $d = new \DateTime(max($desde, $ini));
        for ($i = 0; $d <= $limite && $i < self::MAX_DIAS; $i++, $d->modify('+1 day')) {
            $dia = $d->format('Y-m-d');
            if (!$cal->esHabil($dia)) continue;
            $items = [];
            foreach ($acts as [$partida, $a]) {
                if ($a->fecha_inicio <= $dia && $dia <= $a->fecha_fin) {
                    $items[] = [
                        'actividad' => $a, 'partida' => $partida,
                        'dias' => ($acum[$a->fecha_fin] ?? $acum[$dia]) - $acum[$dia],
                        'completada' => self::completada($a),
                        'proceso' => !self::completada($a) && ((int)$a->progreso > 0 || $a->status === 'en_proceso'),
                        'restriccion' => !empty($restricciones[$a->id]),
                        'riesgo' => implode(' · ', array_column($riesgos[$a->id] ?? [], 'texto')),
                    ];
                }
            }
            if (!$items) continue;
            $grupos = array_chunk($items, self::POR_TARJETA);
            foreach ($grupos as $g => $grupo) {
                $tarjetas[] = ['fecha' => $dia, 'partida' => null, 'items' => $grupo, 'parte' => $g + 1, 'partes' => count($grupos)];
            }
        }
        return $tarjetas;
    }

    public static function completada($a): bool
    {
        return $a->status === 'completada' || (int)$a->progreso >= 100;
    }
}
