<?php

namespace Classes;

/**
 * Motor de simulación del programa: pasada hacia adelante sobre un calendario precalculado (índices enteros), pensado para correr cientos de
 * iteraciones. Respeta jornada, festivos y los tipos de relación FS/SS/FF/SF con retraso. Sirve para escenarios («¿y si esto se atrasa?») y para
 * estimar la probabilidad de terminar a tiempo variando las duraciones. No reemplaza a RutaCritica (que calcula holguras); coinciden en el caso base.
 */
class Simulacion
{
    private string $base;       // fecha del índice 0
    private int $n;             // días del calendario
    /** @var float[] capacidad acumulada de trabajo hasta el día i (inclusive) */
    private array $cum = [];
    /** @var int[] cantidad de días hábiles hasta el día i (inclusive) */
    private array $wd = [];
    /** @var int[] índice del k-ésimo día hábil (k desde 1) */
    private array $hab = [0];
    private array $acts = [];   // [i => modelo]
    private array $orden = [];  // orden topológico
    private array $preds = [];  // [i => [[pred, tipo, retraso]]]
    private array $ids = [];    // actividad_id => i
    private int $hoy;

    public function __construct(Calendario $cal, string $desde, int $dias = 1500)
    {
        $this->base = $desde;
        $this->n = $dias;
        $acum = 0.0;
        $w = 0;
        $t = strtotime($desde);
        for ($i = 0; $i < $dias; $i++) {
            $f = $cal->getFactor(date('Y-m-d', $t + $i * 86400));
            $acum += $f;
            if ($f > 0) { $w++; $this->hab[$w] = $i; }
            $this->cum[$i] = $acum;
            $this->wd[$i] = $w;
        }
        $this->hoy = $this->idx(date('Y-m-d'));
    }

    public function idx(string $fecha): int
    {
        return max(0, min($this->n - 1, (int)round((strtotime($fecha) - strtotime($this->base)) / 86400)));
    }

    public function fecha(int $i): string
    {
        return date('Y-m-d', strtotime($this->base) + $i * 86400);
    }

    /** Primer día hábil con índice ≥ i */
    public function sig(int $i): int
    {
        $k = $this->wd[min($i, $this->n - 1)];
        return ($i <= $this->n - 1 && $this->hab[$k] === $i) ? $i : $this->hab[min($k + 1, count($this->hab) - 1)];
    }

    /** k días hábiles después de la fecha i (k puede ser negativo; i se lleva primero al día hábil siguiente) */
    private function masHab(int $i, int $k): int
    {
        $w = $this->wd[$this->sig($i)] + $k;
        return $this->hab[max(1, min($w, count($this->hab) - 1))];
    }

    /** Día en que termina un trabajo de `dur` días que empieza en `es` (como Calendario::calcularFechaFin) */
    public function fin(int $es, float $dur): int
    {
        $es = $this->sig($es);
        $objetivo = ($es > 0 ? $this->cum[$es - 1] : 0.0) + max($dur, 0.5) - 1e-9;
        $lo = $es; $hi = $this->n - 1;
        if ($this->cum[$hi] < $objetivo) return $hi;
        while ($lo < $hi) {
            $m = intdiv($lo + $hi, 2);
            if ($this->cum[$m] >= $objetivo) $hi = $m; else $lo = $m + 1;
        }
        return $lo;
    }

    /** Carga las actividades [['id','inicio','fin','dur','hito','fija','progreso']] y relaciones [['pred','suc','tipo','retraso']] */
    public function cargar(array $acts, array $rels): void
    {
        $this->acts = []; $this->ids = []; $this->preds = [];
        foreach (array_values($acts) as $i => $a) {
            $this->ids[(int)$a['id']] = $i;
            $prog = (int)$a['progreso'];
            $hecho = $prog >= 100;
            $durRest = (float)max(1, (int)$a['dur']) * (1 - min($prog, 99) / 100);
            $this->acts[$i] = ['id' => (int)$a['id'], 'es' => $this->idx($a['inicio']), 'ef' => $this->idx($a['fin']), 'dur' => (int)$a['dur'], 'rest' => $prog > 0 ? max(1.0, ceil($durRest)) : (float)max(1, (int)$a['dur']),
                'hito' => (bool)$a['hito'], 'fija' => (bool)$a['fija'] || $hecho, 'hecho' => $hecho, 'piso' => $this->idx($a['inicio']), 'prog' => $prog];
            // lo que ya debía haber empezado o está en curso se reprograma desde hoy; lo terminado o fijo no se mueve
            if (!$hecho && !$this->acts[$i]['fija'] && $this->acts[$i]['piso'] < $this->hoy) $this->acts[$i]['piso'] = $this->hoy;
        }
        $grado = array_fill(0, count($this->acts), 0);
        $sale = [];
        foreach ($rels as $r) {
            if (!isset($this->ids[$r['pred']], $this->ids[$r['suc']]) || $r['pred'] === $r['suc']) continue;
            $p = $this->ids[$r['pred']]; $s = $this->ids[$r['suc']];
            $this->preds[$s][] = [$p, $r['tipo'], (int)$r['retraso']];
            $sale[$p][] = $s; $grado[$s]++;
        }
        $cola = array_keys(array_filter($grado, fn($g) => $g === 0));
        $this->orden = [];
        while ($cola) {
            $x = array_shift($cola); $this->orden[] = $x;
            foreach ($sale[$x] ?? [] as $y) if (--$grado[$y] === 0) $cola[] = $y;
        }
        if (count($this->orden) < count($this->acts)) { $this->preds = []; $this->orden = array_keys($this->acts); }   // ciclo: se ignoran las relaciones
    }

    /** Retrasa el piso de una actividad `dias` días hábiles (escenario) */
    public function retrasarInicio(int $actividadId, int $dias): bool
    {
        $i = $this->ids[$actividadId] ?? null;
        if ($i === null || $this->acts[$i]['hecho'] || $this->acts[$i]['fija']) return false;
        $this->acts[$i]['piso'] = $this->masHab($this->acts[$i]['piso'], $dias);
        return true;
    }

    /** No antes de una fecha (p. ej. llegada tardía de un material) */
    public function noAntesDe(int $actividadId, string $fecha): bool
    {
        $i = $this->ids[$actividadId] ?? null;
        if ($i === null || $this->acts[$i]['hecho'] || $this->acts[$i]['fija']) return false;
        $this->acts[$i]['piso'] = max($this->acts[$i]['piso'], $this->sig($this->idx($fecha)));
        return true;
    }

    /** Una pasada. $factores: [i => multiplicador de duración] (o null = duraciones esperadas). @return array{fin: int, es: array, ef: array, impulsor: array} */
    public function correr(?array $factores = null): array
    {
        $es = []; $ef = []; $imp = [];
        foreach ($this->orden as $i) {
            $a = $this->acts[$i];
            if ($a['fija']) { $es[$i] = $a['es']; $ef[$i] = $a['ef']; $imp[$i] = -1; continue; }
            $d = $a['hito'] ? 0.0 : max(1.0, round($a['rest'] * ($factores[$i] ?? 1.0)));
            $e = $this->sig($a['piso']);
            $m = -1;
            foreach ($this->preds[$i] ?? [] as [$p, $tipo, $L]) {
                $req = match ($tipo) {
                    'SS' => $this->masHab($es[$p], $L),
                    'FF' => $this->desdeFin($this->masHab($ef[$p], $L), $d),
                    'SF' => $this->desdeFin($this->masHab($es[$p], $L), $d),
                    default => $this->masHab($ef[$p] + 1, $L),   // FS
                };
                if ($req > $e) { $e = $req; $m = $p; }
            }
            $es[$i] = $e; $imp[$i] = $m;
            $ef[$i] = $a['hito'] ? $e : $this->fin($e, $d);
        }
        $fin = 0; $quien = 0;
        foreach ($ef as $i => $x) if ($x >= $fin) { $fin = $x; $quien = $i; }
        return ['fin' => $fin, 'ultima' => $quien, 'es' => $es, 'ef' => $ef, 'impulsor' => $imp];
    }

    /** Primer inicio tal que un trabajo de `dur` días termine en `finReq` o después */
    private function desdeFin(int $finReq, float $dur): int
    {
        $w = $this->wd[$this->sig($finReq)] - (int)max($dur, 1) + 1;
        return $this->hab[max(1, $w)];
    }

    /** Monte Carlo: duraciones con distribución triangular (mín 1−u, moda 1, máx 1+2u: el trabajo se atrasa más de lo que se adelanta). @return array{fines: int[], criticidad: array} */
    public function montecarlo(int $iteraciones, float $u, int $semilla = 7): array
    {
        mt_srand($semilla);
        $fines = []; $crit = [];
        $n = count($this->acts);
        $a = 1 - $u; $b = 1 + 2 * $u; $c = 1.0;
        for ($k = 0; $k < $iteraciones; $k++) {
            $f = [];
            for ($i = 0; $i < $n; $i++) {
                if ($this->acts[$i]['fija']) continue;
                $r = mt_rand() / mt_getrandmax();
                $f[$i] = $r < ($c - $a) / ($b - $a) ? $a + sqrt($r * ($b - $a) * ($c - $a)) : $b - sqrt((1 - $r) * ($b - $a) * ($b - $c));
            }
            $res = $this->correr($f);
            $fines[] = $res['fin'];
            for ($x = $res['ultima']; $x >= 0; $x = $res['impulsor'][$x]) { $crit[$x] = ($crit[$x] ?? 0) + 1; if ($res['impulsor'][$x] < 0) break; }
        }
        sort($fines);
        return ['fines' => $fines, 'criticidad' => $crit];
    }

    public function actividad(int $i): array
    {
        return $this->acts[$i];
    }

    public function indiceDe(int $actividadId): ?int
    {
        return $this->ids[$actividadId] ?? null;
    }

    public function totalActividades(): int
    {
        return count($this->acts);
    }

    public function dif(int $a, int $b): int
    {
        return $this->wd[$this->sig($b)] - $this->wd[$this->sig($a)];
    }
}
