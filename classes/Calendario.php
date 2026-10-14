<?php

namespace Classes;

use Model\CalendarioJornada;
use Model\CalendarioFestivo;

/**
 * Motor de cálculo de días hábiles por proyecto.
 * - Lee la jornada semanal configurada (factor 0-1 por día).
 * - Lee festivos/paros del proyecto.
 * - Permite calcular fecha_fin a partir de fecha_inicio + duración en días hábiles.
 */
class Calendario
{
    private array $jornada = [];  // [dia_semana(1-7) => factor]
    private array $festivos = []; // [Y-m-d => true]
    private int $proyectoId;

    public function __construct(int $proyectoId)
    {
        $this->proyectoId = $proyectoId;
        $this->cargar();
    }

    private function cargar(): void
    {
        $jornada = CalendarioJornada::whereAll('proyecto_id', $this->proyectoId, 'dia_semana ASC');
        foreach ($jornada as $j) {
            $this->jornada[(int)$j->dia_semana] = (float)$j->factor;
        }
        // Si no hay jornada, usar L-V = 1, S = 0, D = 0
        if (empty($this->jornada)) {
            $this->jornada = [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 0, 7 => 0];
        }

        $festivos = CalendarioFestivo::whereAll('proyecto_id', $this->proyectoId, 'fecha ASC');
        foreach ($festivos as $f) {
            $this->festivos[$f->fecha] = true;
        }
    }

    public function esHabil(string $fechaYmd): bool
    {
        if (isset($this->festivos[$fechaYmd])) return false;
        $diaSemana = (int)date('N', strtotime($fechaYmd));
        return ($this->jornada[$diaSemana] ?? 0) > 0;
    }

    public function getFactor(string $fechaYmd): float
    {
        if (isset($this->festivos[$fechaYmd])) return 0.0;
        $diaSemana = (int)date('N', strtotime($fechaYmd));
        return (float)($this->jornada[$diaSemana] ?? 0);
    }

    /**
     * Calcula fecha_fin a partir de fecha_inicio y duración en días hábiles.
     * Los días con factor 0.5 cuentan como medio día.
     * Si la duración es N días, la fecha_fin es el día en que se completa el N-ésimo día hábil.
     */
    public function calcularFechaFin(string $fechaInicioYmd, int $duracionDias): string
    {
        $duracionDias = max(1, $duracionDias);
        $fecha = new \DateTime($fechaInicioYmd);

        // Retroceder si la fecha de inicio cae en día inhábil
        while (!$this->esHabil($fecha->format('Y-m-d'))) {
            $fecha->modify('+1 day');
        }

        $restante = (float)$duracionDias;
        while ($restante > 0) {
            $factor = $this->getFactor($fecha->format('Y-m-d'));
            if ($factor >= $restante) {
                // Cabe en este día
                return $fecha->format('Y-m-d');
            }
            $restante -= $factor;
            $fecha->modify('+1 day');
            // Saltar inhábiles
            while (!$this->esHabil($fecha->format('Y-m-d'))) {
                $fecha->modify('+1 day');
            }
        }
        return $fecha->format('Y-m-d');
    }

    /**
     * Calcula la duración en días hábiles entre dos fechas (inclusivo).
     */
    public function calcularDuracion(string $fechaInicioYmd, string $fechaFinYmd): float
    {
        $inicio = new \DateTime($fechaInicioYmd);
        $fin = new \DateTime($fechaFinYmd);
        if ($fin < $inicio) return 0;
        $total = 0;
        $curr = clone $inicio;
        while ($curr <= $fin) {
            $total += $this->getFactor($curr->format('Y-m-d'));
            $curr->modify('+1 day');
        }
        return $total;
    }

    /** Genera un array de semanas entre dos fechas: cada semana tiene su número ISO y rango */
    public function generarSemanas(string $fechaInicioYmd, string $fechaFinYmd): array
    {
        $inicio = new \DateTime($fechaInicioYmd);
        $fin = new \DateTime($fechaFinYmd);
        // Ir al lunes de la semana de inicio
        $inicio->modify('monday this week');

        $semanas = [];
        $curr = clone $inicio;
        while ($curr <= $fin) {
            $semanaInicio = clone $curr;
            $semanaFin = clone $curr;
            $semanaFin->modify('+6 days');
            $semanas[] = [
                'numero' => (int)$curr->format('W'),
                'anio' => (int)$curr->format('o'),
                'inicio' => $semanaInicio->format('Y-m-d'),
                'fin' => $semanaFin->format('Y-m-d'),
                'inicio_fmt' => $semanaInicio->format('d M'),
                'fin_fmt' => $semanaFin->format('d M')
            ];
            $curr->modify('+7 days');
        }
        return $semanas;
    }
}
