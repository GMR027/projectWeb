<?php

namespace Classes;

use Model\Partida;
use Model\Proyecto;
use Model\Restriccion;
use Model\VplanLamina;

/** Datos de una lámina de vPlan: tareas del programa + recovery + restricciones numeradas + tablas adicionales */
class Vplan
{
    public static function estado($a): string
    {
        if ($a->status === 'completada' || (int)$a->progreso >= 100) return 'completo';
        return (int)$a->progreso > 0 ? 'proceso' : 'espera';
    }

    /** Color de la partida en el programa de obra (o el azul de referencia) y un color de texto legible sobre él */
    public static function colorTabla(?string $hex): array
    {
        $hex = $hex && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : '#5b82b4';
        [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return [$hex, $lum > 0.6 ? '#000000' : '#ffffff'];
    }

    /**
     * Actividades que muestra la lámina, cada una con su número (SEQ) original dentro de la partida.
     * Sin selección guardada se muestra la partida completa. Devuelve [lista, hayFiltro].
     * @return array{0: array<int, array{actividad: \Model\Actividad, seq: int}>, 1: bool}
     */
    public static function visibles(VplanLamina $lamina, ?Partida $partida): array
    {
        $todas = $partida ? $partida->getActividades() : [];
        $sel = array_flip($lamina->seleccion());
        $out = [];
        foreach ($todas as $i => $a) {
            if (!$sel || isset($sel[(int)$a->id])) $out[] = ['actividad' => $a, 'seq' => $i + 1];
        }
        if ($sel && !$out) {   // selección obsoleta (ya no hay ninguna de esas actividades): se muestra todo
            foreach ($todas as $i => $a) $out[] = ['actividad' => $a, 'seq' => $i + 1];
        }
        return [$out, count($out) < count($todas)];
    }

    /** Condición SQL extra para limitar consultas de restricciones a las actividades visibles */
    private static function filtroActividades(array $visibles, bool $filtrada): string
    {
        if (!$filtrada) return '';
        $ids = array_map(fn($v) => (int)$v['actividad']->id, $visibles);
        return ' AND a.id IN (' . implode(',', $ids ?: [0]) . ')';
    }

    /**
     * Si la partida tiene restricciones activas en alguna actividad, agrega por defecto la tabla de
     * restricciones a la lámina (una sola vez: si luego se quita a mano, no se vuelve a agregar).
     */
    public static function asegurarRestricciones(VplanLamina $lamina, Proyecto $proyecto): void
    {
        if ((int)$lamina->restr_auto === 1 || !$lamina->partida_id || !$lamina->id) return;
        $partida = Partida::find($lamina->partida_id);
        if (!$partida || (int)$partida->proyecto_id !== (int)$proyecto->id) return;
        [$vis, $filtrada] = self::visibles($lamina, $partida);
        $n = (int)Restriccion::consultarSQL(
            "SELECT r.* FROM restricciones r JOIN actividades a ON a.id = r.actividad_id
             WHERE a.partida_id = " . (int)$lamina->partida_id . " AND r.estado = 'activa'" . self::filtroActividades($vis, $filtrada) . " LIMIT 1"
        ) ? 1 : 0;
        if (!$n) return;   // sin restricciones todavía: se vuelve a evaluar en la siguiente visita

        // Si la lámina ya tiene una tabla de restricciones (p. ej. con renglones manuales), no se duplica;
        // si no, se crea una propia de la lámina para que los renglones manuales no se compartan con otras láminas.
        foreach ($lamina->tablas() as $t) {
            if ($t->tipo === 'restricciones') {
                $lamina->restr_auto = 1;
                $lamina->guardar();
                return;
            }
        }
        $tabla = new \Model\VplanTabla(['proyecto_id' => $proyecto->id, 'titulo' => 'RESTRICCIONES', 'nombre_interno' => 'Restricciones · ' . $lamina->titulo, 'tipo' => 'restricciones']);
        $tabla->id = $tabla->guardar()['id'];
        $lamina->agregarTabla((int)$tabla->id);
        $lamina->restr_auto = 1;
        $lamina->guardar();
    }

    public static function datos(VplanLamina $lamina, Proyecto $proyecto): array
    {
        $partida = $lamina->partida_id ? Partida::find($lamina->partida_id) : null;
        if ($partida && (int)$partida->proyecto_id !== (int)$proyecto->id) $partida = null;
        [$visibles, $filtrada] = self::visibles($lamina, $partida);
        $rec = $lamina->recovery();

        // Restricciones activas del programa, numeradas 1..n en orden de actividad
        $numPorAct = [];
        $listaRestr = [];
        if ($partida) {
            $rows = Restriccion::consultarSQL(
                "SELECT r.* FROM restricciones r JOIN actividades a ON a.id = r.actividad_id
                 WHERE a.partida_id = " . (int)$partida->id . " AND r.estado = 'activa'" . self::filtroActividades($visibles, $filtrada) . " ORDER BY a.orden, a.codigo, r.id"
            );
            $n = 0;
            foreach ($rows as $r) {
                $n++;
                $numPorAct[$r->actividad_id][] = $n;
                $listaRestr[] = ['n' => $n, 'descripcion' => trim((string)$r->descripcion) ?: (trim((string)$r->codigo) ?: 'Restricción')];
            }
        }

        $tareas = [];
        $usaR2 = false;
        $hash = [];
        foreach ($visibles as ['actividad' => $a, 'seq' => $seq]) {
            $r = $rec[(int)$a->id] ?? [];
            if (!empty($r['r2_inicio']) || !empty($r['r2_fin'])) $usaR2 = true;
            $tareas[] = [
                'actividad' => $a, 'seq' => $seq, 'estado' => self::estado($a),
                'r1_inicio' => $r['r1_inicio'] ?? null, 'r1_fin' => $r['r1_fin'] ?? null,
                'r2_inicio' => $r['r2_inicio'] ?? null, 'r2_fin' => $r['r2_fin'] ?? null,
                'restricciones' => $numPorAct[$a->id] ?? [],
            ];
            $hash[] = $a->id . ':' . $a->progreso . ':' . $a->status;
        }

        $tablas = [];
        foreach ($lamina->tablas() as $t) {
            $filas = $t->filas();
            if ($t->tipo === 'restricciones') {
                $n = count($listaRestr);
                $filas = array_merge($listaRestr, array_map(function ($f, $i) use (&$n) {
                    return ['n' => ++$n, 'descripcion' => $f['descripcion'] ?? '', '_i' => $i];
                }, $filas, array_keys($filas)));
            } else {
                foreach ($filas as $i => &$f) { $f['n'] = $i + 1; $f['_i'] = $i; }
                unset($f);
            }
            $tablas[] = ['tabla' => $t, 'campos' => $t->campos(), 'filas' => $filas, 'base' => $t->tipo === 'restricciones' ? count($listaRestr) : 0];
        }

        return [
            'partida' => $partida, 'filtrada' => $filtrada, 'tareas' => $tareas, 'usaR2' => $usaR2, 'rec1' => ((int)$lamina->recovery_cols & 1) === 1, 'rec2' => ((int)$lamina->recovery_cols & 2) === 2, 'restricciones' => $listaRestr,
            'tablas' => $tablas, 'hash' => md5(implode('|', $hash)),
            'colorTabla' => self::colorTabla($partida ? $partida->color_bg : null),
        ];
    }
}
