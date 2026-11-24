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
     * @param string $texto    búsqueda libre por código, nombre o responsable
     */
    public static function filtrar(array $todas, array $filtros, array $filtrosCp, array $camposCp, array $riesgos, array $valoresCp, string $texto = ''): array
    {
        $texto = trim(mb_strtolower($texto));
        $hayTexto = $texto !== '';
        $hayFiltroAct = $filtros['status'] || $filtros['prioridad'] || $filtros['restriccion'] || $filtros['riesgo'] || $filtrosCp || $hayTexto;
        if (!$hayFiltroAct && empty($filtros['partida'])) return $todas;

        $padres = [];
        $partidas = [];
        foreach ($todas as $f) {
            if (($f['tipo'] ?? '') === 'actividad') $padres[(int)$f['id']] = $f;
            elseif (($f['tipo'] ?? '') === 'partida') $partidas[(int)$f['id']] = $f;
        }

        // Si la búsqueda coincide con una partida se muestran todas sus filas;
        // si coincide con una actividad, también sus subactividades.
        $partidaTexto = [];
        $padreTexto = [];
        if ($hayTexto) {
            foreach ($partidas as $id => $p) {
                if (self::coincideTexto($p, $texto)) $partidaTexto[$id] = true;
            }
            foreach ($padres as $id => $p) {
                if (self::coincideTexto($p, $texto)) $padreTexto[$id] = true;
            }
        }

        $coincide = function (array $f, bool $esSub, ?array $padre) use ($filtros, $filtrosCp, $camposCp, $riesgos, $valoresCp, $hayTexto, $texto, $partidaTexto, $padreTexto): bool {
            if ($filtros['status'] && (string)$f['status'] !== $filtros['status']) return false;
            if ($filtros['prioridad'] && (string)$f['prioridad'] !== $filtros['prioridad']) return false;
            if ($filtros['restriccion'] === 'con' && empty($f['tiene_restriccion'])) return false;
            if ($filtros['restriccion'] === 'sin' && !empty($f['tiene_restriccion'])) return false;
            $ref = $esSub ? (int)($padre['id'] ?? 0) : (int)$f['id'];
            if ($filtros['riesgo'] && empty($riesgos[$ref])) return false;
            if ($filtrosCp && !CamposPersonalizados::coincide($valoresCp[$ref] ?? [], $filtrosCp, $camposCp)) return false;

            if ($hayTexto) {
                $partidaId = (int)($f['partida_id'] ?? 0);
                if (!isset($partidaTexto[$partidaId])) {
                    if ($esSub) {
                        $padreId = (int)($padre['id'] ?? 0);
                        if (!isset($padreTexto[$padreId]) && !self::coincideTexto($f, $texto)) return false;
                    } elseif (!self::coincideTexto($f, $texto)) {
                        return false;
                    }
                }
            }

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

    /** ¿El código, el nombre o el responsable contienen el texto buscado? */
    private static function coincideTexto(array $f, string $texto): bool
    {
        foreach (['codigo', 'nombre', 'responsable'] as $campo) {
            $valor = trim((string)($f[$campo] ?? ''));
            if ($valor !== '' && mb_strpos(mb_strtolower($valor), $texto) !== false) return true;
        }
        return false;
    }
}
