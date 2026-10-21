<?php

namespace Classes;

use Model\Proyecto;

/**
 * Mapa de avance: lee las marcas del Editor de cada lámina (líneas, círculos, muros, columnas… de cualquier disciplina) y traduce su COLOR a avance:
 * rojo = no avanzado, amarillo = a medias, verde = terminado (porcentajes configurables). Las líneas pesan por su longitud y las figuras cerradas por cantidad.
 * El porcentaje de cada actividad se propone como cambio de avance (Inconsistencias → ajustar_progreso); nada se aplica sin aceptar.
 */
class MapaAvance
{
    use AccesoDb;

    private const LINEAS = ['line', 'arrow', 'darrow', 'measure'];
    private const IGNORAR = ['text', 'image', 'table', 'link', 'restr', 'pin', 'note', 'legend', 'label', 'group'];

    /** 'rojo' | 'amarillo' | 'verde' | null según el matiz de un color #rgb / #rrggbb (grises, negros, azules, morados y cualquier otro color no cuentan) */
    public static function estadoColor(?string $hex): ?string
    {
        $h = ltrim(trim((string)$hex), '#');
        if (strlen($h) === 3) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        if (strlen($h) === 8) $h = substr($h, 0, 6);   // #rrggbbaa
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $h)) return null;
        [$r, $g, $b] = [hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255];
        $max = max($r, $g, $b); $min = min($r, $g, $b); $d = $max - $min;
        if ($max < 0.30 || $max == 0 || $d / $max < 0.40) return null;
        $t = match (true) { $max == $r => fmod(($g - $b) / $d, 6), $max == $g => ($b - $r) / $d + 2, default => ($r - $g) / $d + 4 };
        $deg = fmod($t * 60 + 360, 360);
        // Solo rojo, amarillo y verde cuentan; morado, azul, naranja, rosa, cian… se ignoran
        if ($deg >= 345 || $deg < 15) return 'rojo';
        if ($deg >= 40 && $deg < 70) return 'amarillo';
        if ($deg >= 85 && $deg <= 160) return 'verde';
        return null;
    }

    /** Estado de una marca: las líneas por su trazo; las figuras por su relleno y, si no tienen, por su borde. Un relleno de otro color (morado, azul…) la deja fuera aunque el borde sea rojo */
    private static function estadoMarca(array $s, bool $linea): ?string
    {
        if ($linea) return self::estadoColor($s['stroke'] ?? null);
        $fill = strtolower(trim((string)($s['fill'] ?? '')));
        $sinRelleno = $fill === '' || $fill === 'transparent' || $fill === 'none' || (strlen($fill) === 9 && str_ends_with($fill, '00'));
        if (!$sinRelleno) return self::estadoColor($fill);
        return self::estadoColor($s['stroke'] ?? null);
    }

    public static function ajustes(int $pid): array
    {
        $f = self::filas("SELECT rojo, amarillo, verde FROM mapa_avance_ajustes WHERE proyecto_id = $pid")[0] ?? null;
        return $f ? array_map('intval', $f) : ['rojo' => 0, 'amarillo' => 50, 'verde' => 100];
    }

    public static function guardarAjustes(int $pid, int $r, int $a, int $v): bool
    {
        [$r, $a, $v] = [max(0, min(100, $r)), max(0, min(100, $a)), max(0, min(100, $v))];
        if (!($r <= $a && $a <= $v)) return false;
        self::db()->query("REPLACE INTO mapa_avance_ajustes (proyecto_id, rojo, amarillo, verde) VALUES ($pid, $r, $a, $v)");
        return true;
    }

    /** Pesos por estado de las marcas de una lámina, por capa: [capa_id => ['rojo'=>w, 'amarillo'=>w, 'verde'=>w, 'n_rojo'=>…]] y la unidad de longitud */
    public static function pesos(?string $marcasJson): array
    {
        $j = json_decode((string)$marcasJson, true);
        if (!is_array($j)) return ['capas' => [], 'capasInfo' => [], 'escala' => null];
        $capas = [];
        foreach ($j['shapes'] ?? [] as $s) {
            $tipo = (string)($s['type'] ?? '');
            if (in_array($tipo, self::IGNORAR, true)) continue;
            $linea = in_array($tipo, self::LINEAS, true);
            $estado = self::estadoMarca($s, $linea);
            if (!$estado) continue;
            $peso = 1.0;
            if ($linea) {
                $pts = array_map('floatval', (array)($s['points'] ?? []));
                $peso = 0.0;
                for ($i = 2; $i + 1 < count($pts); $i += 2) $peso += hypot($pts[$i] - $pts[$i - 2], $pts[$i + 1] - $pts[$i - 1]);
                if ($peso <= 0) continue;
            }
            $capa = (string)($s['layer'] ?? '');
            $capas[$capa] ??= ['rojo' => 0.0, 'amarillo' => 0.0, 'verde' => 0.0, 'n_rojo' => 0, 'n_amarillo' => 0, 'n_verde' => 0];
            $capas[$capa][$estado] += $peso;
            $capas[$capa]['n_' . $estado]++;
        }
        $info = [];
        foreach ($j['layers'] ?? [] as $l) $info[(string)$l['id']] = trim((string)($l['name'] ?? '')) !== '' ? $l['name'] : 'Capa ' . ($l['num'] ?? '?');
        return ['capas' => $capas, 'capasInfo' => $info, 'escala' => $j['escala'] ?? null];
    }

    private static function pct(array $w, array $aj): ?float
    {
        $t = $w['rojo'] + $w['amarillo'] + $w['verde'];
        return $t > 0 ? round(($w['rojo'] * $aj['rojo'] + $w['amarillo'] * $aj['amarillo'] + $w['verde'] * $aj['verde']) / $t, 2) : null;
    }

    private static function sumar(array $a, array $b): array
    {
        foreach (['rojo', 'amarillo', 'verde', 'n_rojo', 'n_amarillo', 'n_verde'] as $k) $a[$k] = ($a[$k] ?? 0) + $b[$k];
        return $a;
    }

    public static function mapeos(int $pid): array
    {
        return self::filas("SELECT id, lamina_id, capa_id, actividad_id FROM mapa_avance_capas WHERE proyecto_id = $pid");
    }

    public static function mapear(int $pid, int $lamina, string $capa, int $actividad): bool
    {
        $db = self::db();
        if (!self::filas("SELECT 1 FROM vplan_laminas WHERE id = $lamina AND proyecto_id = $pid")) return false;
        $capa = mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', $capa), 0, 40);
        $db->query("DELETE FROM mapa_avance_capas WHERE proyecto_id = $pid AND lamina_id = $lamina AND capa_id = '$capa'");
        if ($actividad > 0) {
            if (!self::filas("SELECT 1 FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $actividad AND pa.proyecto_id = $pid")) return false;
            $db->query("INSERT INTO mapa_avance_capas (proyecto_id, lamina_id, capa_id, actividad_id) VALUES ($pid, $lamina, '$capa', $actividad)");
        }
        return true;
    }

    /**
     * Lo que dicen las marcas, por lámina y por actividad.
     * @return array{laminas: array, actividades: array} actividades: [id => ['pct','peso','rojo'…,'laminas'=>[…]]]
     */
    public static function calcular(Proyecto $p): array
    {
        $pid = (int)$p->id;
        $aj = self::ajustes($pid);
        $map = [];
        foreach (self::mapeos($pid) as $m) $map[(int)$m['lamina_id']][(string)$m['capa_id']][] = (int)$m['actividad_id'];
        $vinc = [];
        foreach (self::filas("SELECT la.lamina_id, la.actividad_id FROM vplan_lamina_actividades la JOIN vplan_laminas l ON l.id = la.lamina_id WHERE l.proyecto_id = $pid") as $f) $vinc[(int)$f['lamina_id']][] = (int)$f['actividad_id'];
        $laminas = [];
        $acts = [];
        foreach (self::filas("SELECT id, titulo, marcas_json FROM vplan_laminas WHERE proyecto_id = $pid ORDER BY orden, id") as $l) {
            $lid = (int)$l['id'];
            $pe = self::pesos($l['marcas_json']);
            $explicitas = array_filter(array_keys($map[$lid] ?? []), fn($c) => $c !== '');
            $porAct = [];   // actividad => pesos
            $resto = null;
            foreach ($pe['capas'] as $capa => $w) {
                if ($capa !== '' && isset($map[$lid][$capa])) { foreach ($map[$lid][$capa] as $a) $porAct[$a] = isset($porAct[$a]) ? self::sumar($porAct[$a], $w) : $w; }
                else $resto = $resto ? self::sumar($resto, $w) : $w;
            }
            if ($resto) foreach (array_unique(array_merge($map[$lid][''] ?? [], $vinc[$lid] ?? [])) as $a) $porAct[$a] = isset($porAct[$a]) ? self::sumar($porAct[$a], $resto) : $resto;
            $tot = array_sum(array_map(fn($w) => $w['n_rojo'] + $w['n_amarillo'] + $w['n_verde'], $pe['capas']));
            $todas = $pe['capas'] ? array_reduce($pe['capas'], fn($c, $w) => $c ? self::sumar($c, $w) : $w) : null;
            $laminas[] = ['id' => $lid, 'titulo' => $l['titulo'], 'marcas' => $tot, 'pct' => $todas ? self::pct($todas, $aj) : null, 'rojo' => $todas['n_rojo'] ?? 0, 'amarillo' => $todas['n_amarillo'] ?? 0, 'verde' => $todas['n_verde'] ?? 0,
                'capas' => array_map(fn($capa, $w) => ['id' => $capa, 'nombre' => $capa === '' ? '(sin capa)' : ($pe['capasInfo'][$capa] ?? $capa), 'rojo' => $w['n_rojo'], 'amarillo' => $w['n_amarillo'], 'verde' => $w['n_verde'], 'pct' => self::pct($w, $aj)], array_keys($pe['capas']), $pe['capas']),
                'capas_definidas' => $pe['capasInfo'], 'actividades' => array_values(array_unique(array_merge($vinc[$lid] ?? [], ...array_values($map[$lid] ?? [])))), 'sin_asignar' => $resto && !array_unique(array_merge($map[$lid][''] ?? [], $vinc[$lid] ?? []))];
            foreach ($porAct as $a => $w) {
                $pw = $w['rojo'] + $w['amarillo'] + $w['verde'];
                $acts[$a]['laminas'][] = ['lamina' => $lid, 'titulo' => $l['titulo'], 'pct' => self::pct($w, $aj), 'peso' => $pw, 'rojo' => $w['n_rojo'], 'amarillo' => $w['n_amarillo'], 'verde' => $w['n_verde']];
            }
        }
        foreach ($acts as $a => &$x) {
            $pt = array_sum(array_column($x['laminas'], 'peso'));
            $x['pct'] = $pt > 0 ? round(array_sum(array_map(fn($l) => $l['pct'] * $l['peso'], $x['laminas'])) / $pt, 2) : null;
            foreach (['rojo', 'amarillo', 'verde'] as $k) $x[$k] = array_sum(array_column($x['laminas'], $k));
        }
        unset($x);
        return ['laminas' => $laminas, 'actividades' => $acts, 'ajustes' => $aj];
    }

    /** Guarda la foto del día (una por lámina y actividad) y devuelve cuántas filas */
    public static function instantanea(Proyecto $p, array $calc, ?string $fecha = null): int
    {
        $pid = (int)$p->id;
        $db = self::db();
        $fecha = $fecha ?: date('Y-m-d');
        $n = 0;
        foreach ($calc['actividades'] as $a => $x) foreach ($x['laminas'] as $l) {
            if ($l['pct'] === null) continue;
            $db->query("REPLACE INTO mapa_avance_instantaneas (proyecto_id, lamina_id, actividad_id, fecha, porcentaje, peso, detalle_json) VALUES ($pid, {$l['lamina']}, " . (int)$a . ", '" . $db->real_escape_string($fecha) . "', {$l['pct']}, " . round($l['peso'], 2) . ", '" . $db->real_escape_string(json_encode(['rojo' => $l['rojo'], 'amarillo' => $l['amarillo'], 'verde' => $l['verde']])) . "')");
            $n++;
        }
        return $n;
    }

    /** Historial de una actividad: [fecha => porcentaje combinado de sus láminas] */
    public static function historial(int $pid, int $actividad, int $limite = 30): array
    {
        $out = [];
        foreach (self::filas("SELECT fecha, SUM(porcentaje * peso) / NULLIF(SUM(peso), 0) pct FROM mapa_avance_instantaneas WHERE proyecto_id = $pid AND actividad_id = $actividad GROUP BY fecha ORDER BY fecha DESC LIMIT $limite") as $f) $out[$f['fecha']] = round((float)$f['pct'], 1);
        return $out;
    }

    /** Foto de hoy + propuestas de cambio de avance. @return array{actividades: int, propuestas: int, corrida: ?int} */
    public static function proponer(Proyecto $p, float $umbral = 5.0): array
    {
        $pid = (int)$p->id;
        $calc = self::calcular($p);
        self::instantanea($p, $calc);
        $acts = Dependencias::actividades($pid);
        $hall = [];
        foreach ($calc['actividades'] as $a => $x) {
            if ($x['pct'] === null || !isset($acts[$a])) continue;
            $act = $acts[$a];
            $nuevo = (int)round($x['pct']);
            $actual = (int)$act['progreso'];
            if (abs($nuevo - $actual) < $umbral) continue;
            $det = "{$x['rojo']} marca(s) en rojo, {$x['amarillo']} en amarillo y {$x['verde']} en verde en " . count($x['laminas']) . ' lámina(s)';
            $sube = $nuevo > $actual;
            $hall[] = ['huella' => sha1("mapa:$pid:$a:$nuevo:" . date('Y-m-d')), 'categoria' => 'avance_plano', 'severidad' => 'media', 'origen' => 'sistema',
                'titulo' => "Los planos indican {$nuevo}% en {$act['codigo']} (registrado {$actual}%)", 'descripcion' => "Según el color de las marcas ($det), «" . mb_substr($act['nombre'], 0, 90) . "» está al $nuevo%; el programa dice $actual%.", 'actividades' => [$a],
                'solucion' => $sube || $nuevo > 0 ? ['texto' => "Poner el avance de «{$act['codigo']}» en $nuevo%.", 'accion' => 'ajustar_progreso', 'params' => ['actividad_id' => $a, 'progreso' => $nuevo]] : ['texto' => 'Revisar las marcas del plano: indican menos avance que el registrado.', 'accion' => 'nota', 'params' => []]];
        }
        $corrida = null;
        if ($hall) {
            $r = Inconsistencias::guardar($p, $hall, '', ['del_sistema' => count($hall), 'de_la_ia' => 0]);
            if ($r['nuevos']) { Inconsistencias::marcarCorrida($pid, $r['corrida'], true, 'Avance en planos ' . date('d/m H:i')); $corrida = $r['corrida']; }
            return ['actividades' => count($calc['actividades']), 'propuestas' => $r['nuevos'], 'corrida' => $corrida];
        }
        return ['actividades' => count($calc['actividades']), 'propuestas' => 0, 'corrida' => null];
    }
}
