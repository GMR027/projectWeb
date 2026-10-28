<?php

namespace Classes;

use Model\Proyecto;
use Model\Partida;
use Model\Subactividad;

/**
 * Prepara los datos para renderizar el Gantt en la vista.
 * Retorna la estructura de semanas + filas (partidas y actividades) con posición calculada.
 */
class GanttRenderer
{
    private Proyecto $proyecto;
    private Calendario $calendario;
    private array $semanas;

    public function __construct(Proyecto $proyecto)
    {
        $this->proyecto = $proyecto;
        $this->calendario = new Calendario($proyecto->id);
        $this->semanas = $this->calendario->generarSemanas($proyecto->fecha_inicio, $proyecto->fecha_fin);
    }

    public function getSemanas(): array
    {
        return $this->semanas;
    }

    public function getTotalSemanas(): int
    {
        return count($this->semanas);
    }

    /** Calcula columna inicio y fin (1-based) de una actividad en el grid de semanas */
    public function calcularPosicion(string $fechaInicio, string $fechaFin): array
    {
        $colInicio = 1;
        $colFin = 1;
        foreach ($this->semanas as $i => $semana) {
            if ($fechaInicio >= $semana['inicio'] && $fechaInicio <= $semana['fin']) {
                $colInicio = $i + 1;
            }
            if ($fechaFin >= $semana['inicio'] && $fechaFin <= $semana['fin']) {
                $colFin = $i + 1;
            }
        }
        // Si la fecha_fin es posterior al rango, ajustar a última columna
        if ($fechaFin > end($this->semanas)['fin']) {
            $colFin = count($this->semanas);
        }
        if ($colFin < $colInicio) $colFin = $colInicio;
        return [$colInicio, $colFin];
    }

    /**
     * Genera el arreglo de filas del Gantt:
     * [ { tipo: 'partida', data: Partida, colInicio, colFin }, { tipo: 'actividad', data: Actividad, ...}, ...]
     */
    public function getFilas(): array
    {
        $filas = [];
        $pid = (int)$this->proyecto->id;
        // Tres consultas para todo el Gantt: partidas+actividades, avance por partida y restricciones activas
        $subactividades = Subactividad::conteoPorProyecto($pid);
        $progresos = \Model\Partida::progresosDeProyecto($pid);
        $restricciones = \Model\Restriccion::activasPorProyecto($pid);
        foreach ($this->proyecto->getPartidasConActividades() as $bloque) {
            $partida = $bloque['partida'];
            $filas[] = [
                'tipo' => 'partida',
                'id' => $partida->id,
                'codigo' => $partida->codigo,
                'nombre' => $partida->nombre,
                'color_bg' => $partida->color_bg,
                'fecha_inicio' => $partida->fecha_inicio,
                'fecha_fin' => $partida->fecha_fin,
                'progreso' => $progresos[(int)$partida->id] ?? 0.0,
                'pos' => $partida->fecha_inicio && $partida->fecha_fin
                    ? $this->calcularPosicion($partida->fecha_inicio, $partida->fecha_fin)
                    : [1, 1]
            ];
            foreach ($bloque['actividades'] as $act) {
                $activas = $restricciones[(int)$act->id] ?? [];
                $filas[] = [
                    'tipo' => 'actividad',
                    'id' => $act->id,
                    'partida_id' => $partida->id,
                    'codigo' => $act->codigo,
                    'nombre' => $act->nombre,
                    'fecha_inicio' => $act->fecha_inicio,
                    'fecha_fin' => $act->fecha_fin,
                    'progreso' => (int)$act->progreso,
                    'status' => $act->status,
                    'hito' => $act->esHito(),
                    'responsable' => $act->responsable,
                    'icono' => $act->icono,
                    'rec_inicio' => $act->rec_inicio,
                    'rec_fin' => $act->rec_fin,
                    'libre_texto' => $act->libre_texto,
                    'libre_numero' => $act->libre_numero,
                    'libre_fecha' => $act->libre_fecha,
                    'prioridad' => $act->prioridad,
                    'tiene_restriccion' => !empty($activas),
                    'subactividades_total' => (int)($subactividades[(int)$act->id] ?? 0),
                    'restricciones' => $activas,
                    'pos' => $this->calcularPosicion($act->fecha_inicio, $act->fecha_fin)
                ];
            }
        }
        return $filas;
    }
}
