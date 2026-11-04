<?php

namespace Classes;

use Model\Proyecto;
use Model\Actividad;
use Model\Partida;

/**
 * Nivelación de recursos: propone mover actividades no críticas dentro de su holgura
 * para bajar una sobrecarga. Solo simula y aplica fechas; no cambia duraciones ni recursos.
 */
class NivelacionRecursos
{
    use AccesoDb;

    /** @return array<int,array> propuestas con actividad, recurso, semana, carga, movimiento y nueva carga */
    public static function propuestas(Proyecto $proyecto, int $semanas = 8): array
    {
        $pid = (int)$proyecto->id;
        $carga = Recursos::carga($proyecto, 0, $semanas);
        $deps = Dependencias::datos($proyecto);
        $acts = [];
        foreach ($deps['actividades'] as $a) $acts[(int)$a['id']] = $a;
        $vistos = [];
        $out = [];
        foreach ($carga['sobrecargas'] as $s) {
            $semana = $carga['semanas'][$s['semana']] ?? null;
            if (!$semana) continue;
            $habiles = max(1, (float)$semana['habiles']);
            foreach ($s['acts'] as $act) {
                $aid = (int)$act['id'];
                $a = $acts[$aid] ?? null;
                if (!$a || isset($vistos[$aid]) || (int)$a['progreso'] >= 100 || $a['hito'] || $a['fija']) continue;
                if ($a['critica']) continue;
                $holgura = $a['holgura'] === null ? 5 : (int)floor((float)$a['holgura']);
                if ($holgura < 1) continue;
                $cal = new Calendario($pid, (int)($a['calendario_id'] ?? 0));
                $diasViejos = max(0, $cal->calcularDuracion(max($a['inicio'], $semana['inicio']), min($a['fin'], $semana['fin'])));
                if ($diasViejos <= 0) continue;
                $propuesta = null;
                for ($shift = 1; $shift <= min($holgura, 14); $shift++) {
                    $nuevoIni = $cal->sumarHabiles($a['inicio'], $shift);
                    $nuevoFin = $cal->calcularFechaFin($nuevoIni, max(1, (int)$a['dur']));
                    $diasNuevos = max(0, $cal->calcularDuracion(max($nuevoIni, $semana['inicio']), min($nuevoFin, $semana['fin'])));
                    $nueva = max(0, (float)$s['pct'] - ((int)$act['dedicacion'] * ($diasViejos - $diasNuevos) / $habiles));
                    if ($nueva <= 100.5) { $propuesta = ['shift' => $shift, 'nueva' => (int)round($nueva), 'nuevo_inicio' => $nuevoIni, 'nuevo_fin' => $nuevoFin]; break; }
                }
                if (!$propuesta) continue;
                $vistos[$aid] = true;
                $out[] = [
                    'recurso_id' => (int)$s['recurso_id'], 'recurso' => $s['recurso'], 'semana' => (int)$s['semana'],
                    'semana_inicio' => $semana['inicio'], 'semana_fin' => $semana['fin'], 'carga' => (int)$s['pct'],
                    'actividad_id' => $aid, 'codigo' => $a['codigo'], 'actividad' => $a['nombre'],
                    'inicio' => $a['inicio'], 'fin' => $a['fin'], 'dedicacion' => (int)$act['dedicacion'],
                    'holgura' => $holgura, 'shift' => $propuesta['shift'], 'nueva' => $propuesta['nueva'],
                    'nuevo_inicio' => $propuesta['nuevo_inicio'], 'nuevo_fin' => $propuesta['nuevo_fin'],
                ];
            }
        }
        usort($out, fn($a, $b) => [$b['carga'], $a['semana']] <=> [$a['carga'], $b['semana']]);
        return $out;
    }

    /** Aplica propuestas seleccionadas: [['actividad_id'=>, 'shift'=>], …]. */
    public static function aplicar(Proyecto $proyecto, array $seleccion): int
    {
        $pid = (int)$proyecto->id;
        $n = 0;
        foreach ($seleccion as $x) {
            $aid = (int)($x['actividad_id'] ?? 0);
            $shift = max(1, min(30, (int)($x['shift'] ?? 0)));
            if (!$aid || !$shift) continue;
            $a = Actividad::find($aid);
            if (!$a) continue;
            $partida = Partida::find($a->partida_id);
            if (!$partida || (int)$partida->proyecto_id !== $pid || (int)$a->progreso >= 100 || (int)$a->hito || (int)$a->fecha_fija) continue;
            $cal = new Calendario($pid, (int)($a->calendario_id ?? 0));
            $nuevoIni = $cal->sumarHabiles($a->fecha_inicio, $shift);
            $a->fecha_inicio = $nuevoIni;
            $a->fecha_fin = $a->esHito() ? $nuevoIni : $cal->calcularFechaFin($nuevoIni, max(1, (int)$a->duracion_dias));
            if (!$a->guardar()) continue;
            $partida->recalcularFechas();
            $n++;
        }
        if ($n) {
            Dependencias::propagar($proyecto);
            try {
                Auditoria::registrar($pid, 'recursos', 'modificado', 'nivelar', 'Aplicó ' . $n . ' movimiento(s) para nivelar recursos.', null, null, null, 'Recurso');
            } catch (\Throwable $e) {
            }
        }
        return $n;
    }
}
