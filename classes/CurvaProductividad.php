<?php

namespace Classes;

use Model\Curva;

/**
 * Cálculo de curvas de productividad (equivale a las macros/hojas del Excel de referencia).
 * Periodos semanales a partir de la fecha de inicio; el planeado suma siempre la cantidad total.
 */
class CurvaProductividad
{
    public const MAX_PERIODOS = 90;
    public const TIPOS = ['campana' => 'Campana', 'lineal' => 'Lineal', 'inicio' => 'Cargada al inicio', 'final' => 'Cargada al final'];
    private static ?array $tablas = null;

    /** Número de periodos semanales entre inicio y fin (ambos incluidos), mínimo 1 */
    public static function numPeriodos(string $inicio, string $fin): int
    {
        $dias = (int)(new \DateTime($inicio))->diff(new \DateTime($fin))->format('%r%a') + 1;
        return max(1, min(self::MAX_PERIODOS, (int)ceil($dias / 7)));
    }

    /** Fechas de inicio de cada periodo (Y-m-d) */
    public static function fechasPeriodos(string $inicio, int $n): array
    {
        $f = new \DateTime($inicio);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $f->format('Y-m-d');
            $f->modify('+7 days');
        }
        return $out;
    }

    /** Porcentajes por periodo (suman 1) */
    public static function porcentajes(string $tipo, int $n): array
    {
        if ($n <= 1) return [1.0];
        if ($tipo !== 'lineal') {
            self::$tablas ??= json_decode(file_get_contents(__DIR__ . '/../database/curvas_porcentajes.json'), true);
            $fila = self::$tablas[$tipo][$n] ?? null;
            if ($fila) {
                $suma = array_sum($fila) ?: 1;
                return array_map(fn($p) => $p / $suma, $fila);
            }
        }
        return array_fill(0, $n, 1 / $n);
    }

    /** Planeado por periodo en enteros; el redondeo se reparte para que el total sea exactamente la cantidad */
    public static function planeado(int $cantidad, string $tipo, int $n): array
    {
        $pct = self::porcentajes($tipo, $n);
        $base = $resto = [];
        foreach ($pct as $i => $p) {
            $v = $cantidad * $p;
            $base[$i] = (int)floor($v);
            $resto[$i] = $v - $base[$i];
        }
        $faltan = $cantidad - array_sum($base);
        arsort($resto);
        foreach (array_keys($resto) as $i) {
            if ($faltan <= 0) break;
            $base[$i]++;
            $faltan--;
        }
        ksort($base);
        return array_values($base);
    }

    /** Posición de la fecha actual en el eje de periodos (centro del periodo k = k) o null si queda fuera */
    public static function posicionHoy(string $inicio, int $n): ?float
    {
        $dias = (int)(new \DateTime($inicio))->diff(new \DateTime('today'))->format('%r%a');
        $pos = $dias / 7 - 0.5;
        return ($pos < -0.5 || $pos > $n - 0.5) ? null : round($pos, 3);
    }

    public static function acumular(array $valores): array
    {
        $acc = 0;
        $out = [];
        foreach ($valores as $v) {
            $acc += (float)$v;
            $out[] = $acc;
        }
        return $out;
    }

    /**
     * Series completas de una curva.
     * actual[i] es null mientras no haya captura; el acumulado real se detiene en el último dato capturado.
     */
    public static function series(Curva $c): array
    {
        $n = self::numPeriodos($c->fecha_inicio, $c->fecha_fin);
        $planeado = self::planeado((int)$c->cantidad, $c->tipo_curva, $n);
        $reales = $c->reales();
        $actual = [];
        for ($i = 0; $i < $n; $i++) {
            $actual[] = array_key_exists($i, $reales) && $reales[$i] !== null ? (float)$reales[$i] : null;
        }
        $ultimo = -1;
        foreach ($actual as $i => $v) if ($v !== null) $ultimo = $i;
        $actualAcum = [];
        $acc = 0;
        foreach ($actual as $i => $v) {
            $acc += $v ?? 0;
            $actualAcum[] = $i <= $ultimo ? $acc : null;
        }
        return [
            'n' => $n,
            'fechas' => self::fechasPeriodos($c->fecha_inicio, $n),
            'planeado' => $planeado,
            'actual' => $actual,
            'planeado_acum' => self::acumular($planeado),
            'actual_acum' => $actualAcum,
            // posición de hoy sobre el eje (0 = centro del 1er periodo); null si está fuera de la curva
            'hoy' => self::posicionHoy($c->fecha_inicio, $n),
        ];
    }

    /**
     * Gráfica maestra: avance acumulado (%) por semana de cada curva entre las fechas del programa.
     * @param Curva[] $curvas
     */
    public static function maestra(array $curvas, string $inicio, string $fin): array
    {
        $semanas = [];
        $f = new \DateTime($inicio);
        if ((int)$f->format('N') !== 1) $f->modify('last monday');
        $limite = new \DateTime($fin);
        while ($f <= $limite && count($semanas) < 520) {
            $semanas[] = $f->format('Y-m-d');
            $f->modify('+7 days');
        }
        $series = [];
        foreach ($curvas as $c) {
            $s = self::series($c);
            $cant = max(1, (int)$c->cantidad);
            $plan = $real = [];
            foreach ($semanas as $d) {
                $dias = (int)(new \DateTime($c->fecha_inicio))->diff(new \DateTime($d))->format('%r%a');
                // semana (lunes a domingo) que termina antes del inicio de la curva => 0 %
                if ($dias + 6 < 0) {
                    $plan[] = 0;
                    $real[] = null;
                    continue;
                }
                $k = max(0, min($s['n'] - 1, (int)floor(max(0, $dias) / 7)));
                $plan[] = round($s['planeado_acum'][$k] / $cant * 100, 1);
                // El real solo existe dentro de las semanas de la curva (no se extiende tras su fin)
                $dentro = $dias <= ($s['n'] * 7 - 1);
                $real[] = $dentro && $s['actual_acum'][$k] !== null ? round($s['actual_acum'][$k] / $cant * 100, 1) : null;
            }
            $series[] = ['id' => (int)$c->id, 'nombre' => $c->nombre, 'planeado' => $plan, 'real' => $real];
        }
        // Hoy en el eje de semanas (0 = primera semana, 1 por semana) o null si queda fuera del programa
        $hoy = null;
        if ($semanas) {
            $pos = (int)(new \DateTime($semanas[0]))->diff(new \DateTime('today'))->format('%r%a') / 7;
            $hoy = ($pos >= 0 && $pos <= count($semanas) - 1) ? round($pos, 3) : null;
        }
        return ['semanas' => $semanas, 'series' => $series, 'hoy' => $hoy];
    }
}
