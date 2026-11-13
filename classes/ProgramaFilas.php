<?php

namespace Classes;

/**
 * Filtrado de las filas del Programa (partidas, actividades y subactividades).
 *
 * Vive fuera del controlador para poder probarlo sin HTTP. Una subactividad
 * hereda el filtro de campos personalizados y de riesgo de su actividad, pero
 * evalúa su propio estado, prioridad y restricción.
 */
class ProgramaFilas
{
    /**
     * @param array $todas     filas de GanttRenderer: partida, actividad o subactividad
     * @param array $filtros   ['partida','status','prioridad','restriccion','riesgo']
     * @param array $filtrosCp filtros de campos personalizados
     * @param array $camposCp  definición de campos personalizados
     * @param array $riesgos   [actividad_id => [...]]
     * @param array $valoresCp [actividad_id => [campo_id => valor]]
     */
    public static function filtrar(array $todas, array $filtros, array $filtrosCp, array $camposCp, array $riesgos, array $valoresCp): array
    {
        $hayFiltroAct = $filtros['status'] || $filtros['prioridad'] || $filtros['restriccion'] || $filtros['riesgo'] || $filtrosCp;
        if (!$hayFiltroAct && empty($filtros['partida'])) return $todas;

        $padres = [];
        foreach ($todas as $f) if (($f['tipo'] ?? '') === 'actividad') $padres[(int)$f['id']] = $f;

        $coincide = function (array $f, bool $esSub, ?array $padre) use ($filtros, $filtrosCp, $camposCp, $riesgos, $valoresCp): bool {
            if ($filtros['status'] && (string)$f['status'] !== $filtros['status']) return false;
            if ($filtros['prioridad'] && (string)$f['prioridad'] !== $filtros['prioridad']) return false;
            if ($filtros['restriccion'] === 'con' && empty($f['tiene_restriccion'])) return false;
            if ($filtros['restriccion'] === 'sin' && !empty($f['tiene_restriccion'])) return false;
            $ref = $esSub ? (int)($padre['id'] ?? 0) : (int)$f['id'];
            if ($filtros['riesgo'] && empty($riesgos[$ref])) return false;
            if ($filtrosCp && !CamposPersonalizados::coincide($valoresCp[$ref] ?? [], $filtrosCp, $camposCp)) return false;
            return true;
        };

        // Si un hijo cumple, su actividad se muestra como cabecera aunque ella no cumpla.
        $padreConHijo = [];
        if ($hayFiltroAct) {
            foreach ($todas as $f) {
                if (($f['tipo'] ?? '') !== 'subactividad') continue;
                $padre = $padres[(int)$f['actividad_id']] ?? null;
                if ($padre && $coincide($f, true, $padre)) $padreConHijo[(int)$f['actividad_id']] = true;
            }
        }

        $filas = [];
        $cabecera = null;
        $mostrarHijos = false;
        foreach ($todas as $f) {
            if (($f['tipo'] ?? '') === 'partida') {
                $cabecera = (!$filtros['partida'] || (int)$f['id'] === $filtros['partida']) ? $f : null;
                if ($cabecera && !$hayFiltroAct) { $filas[] = $cabecera; $cabecera = null; }
                $mostrarHijos = false;
                continue;
            }

            $partidaOk = !$filtros['partida'] || (int)($f['partida_id'] ?? 0) === $filtros['partida'];
            if (($f['tipo'] ?? '') === 'subactividad') {
                if (!$partidaOk || !$mostrarHijos) continue;
                if ($hayFiltroAct && !$coincide($f, true, $padres[(int)$f['actividad_id']] ?? null)) continue;
                $filas[] = $f;
                continue;
            }

            // Actividad
            if (!$partidaOk) { $mostrarHijos = false; continue; }
            $mostrar = !$hayFiltroAct || $coincide($f, false, null) || !empty($padreConHijo[(int)$f['id']]);
            $mostrarHijos = $mostrar;
            if (!$mostrar) continue;
            if ($cabecera) { $filas[] = $cabecera; $cabecera = null; }
            $filas[] = $f;
        }
        return $filas;
    }
}
