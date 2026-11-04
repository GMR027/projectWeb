<?php

namespace Classes;

use Model\Proyecto;
use Model\Partida;
use Model\Actividad;

/**
 * Línea de balance (takt): actividades agrupadas por frente y por partida (oficio).
 * La pendiente entre frentes es el takt. Detecta cruces entre oficios y frentes sin actividad.
 */
class LineaBalance
{
    use AccesoDb;

    public const TIPOS = ['piso' => 'Piso', 'tramo' => 'Tramo', 'tunel' => 'Túnel', 'anillo' => 'Anillo', 'otro' => 'Otro'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM frentes LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function frentes(int $pid): array
    {
        return self::filas("SELECT f.*, (SELECT COUNT(*) FROM actividades a WHERE a.frente_id = f.id) actividades
            FROM frentes f WHERE f.proyecto_id = $pid AND f.activo = 1 ORDER BY f.orden, f.id");
    }

    public static function guardarFrente(int $pid, array $d): array
    {
        $nombre = mb_substr(trim((string)($d['nombre'] ?? '')), 0, 150);
        if ($nombre === '') return [false, 'Escribe el nombre del frente'];
        $tipo = isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'piso';
        $orden = (int)($d['orden'] ?? 0);
        $color = preg_match('/^#[0-9a-f]{6}$/i', (string)($d['color'] ?? '')) ? $d['color'] : '#00b4d8';
        self::db()->query("INSERT INTO frentes (proyecto_id, nombre, tipo, orden, color) VALUES ($pid, '" . self::esc($nombre) . "', '$tipo', $orden, '$color')");
        return [true, 'Frente creado'];
    }

    public static function eliminarFrente(int $pid, int $id): bool
    {
        if (self::filas("SELECT 1 FROM actividades WHERE frente_id = $id LIMIT 1")) return false;
        self::db()->query("DELETE FROM frentes WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    /**
     * Datos de la línea: frentes, líneas por partida, takt, conflictos y huecos.
     * @return array{frentes:array,lineas:array,takt:?float,conflictos:array,huecos:array,rango:?array}
     */
    public static function datos(Proyecto $proyecto): array
    {
        $pid = (int)$proyecto->id;
        $frentes = self::frentes($pid);
        $fmap = [];
        foreach ($frentes as $f) $fmap[(int)$f['id']] = $f;
        $filas = self::filas("SELECT a.id, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin, a.progreso, a.frente_id,
                p.id partida_id, p.codigo partida_codigo, p.nombre partida, p.color_bg color, p.orden partida_orden
            FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE p.proyecto_id = $pid AND a.frente_id IS NOT NULL
            ORDER BY p.orden, p.codigo, a.fecha_inicio, a.orden");
        $lineas = [];
        $huecos = [];
        $activos = [];
        $min = null; $max = null;
        foreach ($filas as $a) {
            $fid = (int)$a['frente_id'];
            if (!isset($fmap[$fid])) continue;
            $pidPartida = (int)$a['partida_id'];
            $lineas[$pidPartida] ??= ['id' => $pidPartida, 'codigo' => $a['partida_codigo'], 'nombre' => $a['partida'], 'color' => $a['color'] ?: '#00b4d8', 'puntos' => []];
            $lineas[$pidPartida]['puntos'][] = ['id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'],
                'frente_id' => $fid, 'frente' => $fmap[$fid]['nombre'], 'orden' => (int)$fmap[$fid]['orden'],
                'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'progreso' => (int)$a['progreso']];
            $activos[$fid] = true;
            $min = $min === null || $a['fecha_inicio'] < $min ? $a['fecha_inicio'] : $min;
            $max = $max === null || $a['fecha_fin'] > $max ? $a['fecha_fin'] : $max;
        }
        foreach ($lineas as &$l) usort($l['puntos'], fn($x, $y) => [$x['orden'], $x['inicio']] <=> [$y['orden'], $y['inicio']]);
        unset($l);
        foreach ($frentes as $f) if (!isset($activos[(int)$f['id']])) $huecos[] = $f['nombre'];
        $cal = new Calendario($pid);
        $takt = null;
        foreach ($lineas as $l) {
            $dif = [];
            for ($i = 1; $i < count($l['puntos']); $i++) {
                $desde = date('Y-m-d', strtotime($l['puntos'][$i - 1]['inicio'] . ' +1 day'));
                $d = $cal->calcularDuracion($desde, $l['puntos'][$i]['inicio']);
                if ($d > 0) $dif[] = $d;
            }
            if ($dif) { sort($dif); $takt = $dif[(int)floor(count($dif) / 2)]; break; }
        }
        // Cruces: un oficio posterior no puede empezar en un frente antes de que termine el anterior
        $conflictos = [];
        $porFrente = [];
        foreach ($lineas as $l) foreach ($l['puntos'] as $pt) $porFrente[$pt['frente_id']][] = $pt + ['partida_id' => $l['id'], 'partida' => $l['nombre'], 'partida_orden' => 0];
        foreach ($porFrente as $fid => $pts) {
            usort($pts, fn($x, $y) => [$x['partida_id'], $x['inicio']] <=> [$y['partida_id'], $y['inicio']]);
            $n = count($pts);
            for ($i = 0; $i < $n; $i++) for ($j = 0; $j < $n; $j++) {
                if ($i === $j || $pts[$i]['partida_id'] >= $pts[$j]['partida_id']) continue;
                if ($pts[$j]['inicio'] < $pts[$i]['fin']) {
                    $conflictos[] = ['frente' => $fmap[$fid]['nombre'] ?? '', 'antes' => $pts[$i]['partida'], 'despues' => $pts[$j]['partida'],
                        'fin_antes' => $pts[$i]['fin'], 'inicio_despues' => $pts[$j]['inicio']];
                    break 2;
                }
            }
        }
        return ['frentes' => $frentes, 'lineas' => array_values($lineas), 'takt' => $takt, 'conflictos' => $conflictos,
            'huecos' => $huecos, 'rango' => $min && $max ? ['inicio' => $min, 'fin' => $max] : null];
    }

    /** Genera una actividad por frente para un oficio, espaciadas por el takt */
    public static function generar(Proyecto $proyecto, array $d): array
    {
        $pid = (int)$proyecto->id;
        $partida = Partida::find((int)($d['partida_id'] ?? 0));
        if (!$partida || (int)$partida->proyecto_id !== $pid) return [false, 'Elige una partida válida'];
        $nombre = mb_substr(trim((string)($d['nombre'] ?? '')), 0, 200);
        if ($nombre === '') return [false, 'Escribe el nombre del oficio'];
        $duracion = max(1, (int)($d['duracion'] ?? 1));
        $takt = max(0, (int)($d['takt'] ?? 0));
        $inicio = trim((string)($d['inicio'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) return [false, 'La fecha de inicio no es válida'];
        $ids = array_map('intval', (array)($d['frentes'] ?? []));
        $frentes = array_values(array_filter(self::frentes($pid), fn($f) => in_array((int)$f['id'], $ids, true)));
        if (!$frentes) return [false, 'Selecciona al menos un frente'];
        $cal = new Calendario($pid);
        $prefijo = mb_substr(trim((string)($d['codigo'] ?? '')), 0, 40) ?: $partida->codigo;
        $maxOrden = (int)(self::filas("SELECT COALESCE(MAX(orden), 0) n FROM actividades WHERE partida_id = " . (int)$partida->id)[0]['n'] ?? 0);
        $n = 0;
        foreach ($frentes as $i => $f) {
            $ini = $i === 0 ? $inicio : $cal->sumarHabiles($inicio, $takt * $i);
            $codigoBase = $prefijo . '-' . ($i + 1);
            $codigo = $codigoBase;
            $k = 1;
            while (self::filas("SELECT 1 FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.codigo = '" . self::esc($codigo) . "' LIMIT 1")) $codigo = $codigoBase . '-' . (++$k);
            $a = new Actividad([
                'partida_id' => (int)$partida->id, 'codigo' => $codigo, 'nombre' => $nombre . ' · ' . $f['nombre'],
                'descripcion' => '', 'fecha_inicio' => $ini, 'duracion_dias' => $duracion,
                'fecha_fin' => $cal->calcularFechaFin($ini, $duracion), 'progreso' => 0, 'prioridad' => 'media',
                'status' => 'no_iniciada', 'pta' => 1, 'hito' => 0, 'sin_material' => 0, 'orden' => $maxOrden + $i + 1,
                'fecha_fija' => 0, 'frente_id' => (int)$f['id'],
            ]);
            $res = $a->guardar();
            if (is_array($res) ? !empty($res['resultado']) : (bool)$res) $n++;
        }
        if ($n) $partida->recalcularFechas();
        try { Auditoria::registrar($pid, 'programa', 'creado', 'generar_balance', 'Generó ' . $n . ' actividad(es) de «' . $nombre . '» por frente.', null, null, null, 'Actividad'); } catch (\Throwable $e) {}
        return [true, 'Se generaron ' . $n . ' actividad(es)'];
    }
}
