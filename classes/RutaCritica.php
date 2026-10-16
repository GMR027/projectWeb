<?php

namespace Classes;

/**
 * Motor de dependencias entre actividades y ruta crítica (CPM) sobre el calendario del proyecto.
 *
 * Relaciones (con retraso en días hábiles, negativo = adelanto):
 *   FS fin-inicio: la sucesora empieza el día hábil siguiente al fin de la predecesora (+ retraso)
 *   SS inicio-inicio · FF fin-fin · SF inicio-fin
 *
 * El cálculo nunca ADELANTA actividades: la fecha calculada es la mayor entre la propia (base) y la que exigen sus
 * predecesoras. Una actividad con «fecha fija» conserva su fecha; solo se marca el conflicto.
 * Calendario: cualquier objeto con esHabil(), calcularFechaFin() y getFactor() (el de Classes\Calendario).
 */
class RutaCritica
{
    private object $cal;

    public function __construct(object $calendario)
    {
        $this->cal = $calendario;
    }

    // ---------- aritmética de días hábiles ----------
    private function dia(string $d, int $n): string
    {
        return date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' day', strtotime($d)));
    }

    /** Primer día hábil igual o posterior (tope de 400 días para calendarios sin jornada) */
    public function sig(string $d): string
    {
        for ($i = 0; $i < 400 && !$this->cal->esHabil($d); $i++) $d = $this->dia($d, 1);
        return $d;
    }

    public function ant(string $d): string
    {
        for ($i = 0; $i < 400 && !$this->cal->esHabil($d); $i++) $d = $this->dia($d, -1);
        return $d;
    }

    /** $n días hábiles después (o antes, si es negativo) de $d */
    public function mas(string $d, int $n): string
    {
        $d = $n >= 0 ? $this->sig($d) : $this->ant($d);
        for ($i = abs($n); $i > 0; $i--) $d = $n > 0 ? $this->sig($this->dia($d, 1)) : $this->ant($this->dia($d, -1));
        return $d;
    }

    /** Días hábiles de a a b (positivo si b es posterior) */
    public function difHab(string $a, string $b): int
    {
        if ($a === $b) return 0;
        [$x, $y, $signo] = $a < $b ? [$a, $b, 1] : [$b, $a, -1];
        $n = 0;
        for ($d = $this->dia($x, 1); $d <= $y; $d = $this->dia($d, 1)) {
            if ($this->cal->esHabil($d)) $n++;
        }
        return $n * $signo;
    }

    private function fin(string $inicio, int $dur, bool $hito): string
    {
        return $hito ? $inicio : $this->cal->calcularFechaFin($inicio, max(1, $dur));
    }

    /** Último inicio cuyo fin no pasa de $fin */
    private function inicioMax(string $fin, int $dur, bool $hito): string
    {
        $s = $this->ant($fin);
        for ($i = 0; $i < 3000 && $this->fin($s, $dur, $hito) > $fin; $i++) $s = $this->ant($this->dia($s, -1));
        return $s;
    }

    /** Primer inicio cuyo fin llega a $fin */
    private function inicioMin(string $fin, int $dur, bool $hito): string
    {
        $s = $this->inicioMax($fin, $dur, $hito);
        for ($i = 0; $i < 3000 && $this->fin($s, $dur, $hito) < $fin; $i++) $s = $this->sig($this->dia($s, 1));
        return $s;
    }

    /** Inicio mínimo que exige la relación según las fechas (es/ef) de la predecesora */
    private function exigido(array $d, string $esP, string $efP, array $a): string
    {
        $L = (int)$d['retraso'];
        $hito = (bool)$a['hito'];
        switch ($d['tipo']) {
            case 'SS': return $this->mas($esP, $L);
            case 'FF': return $this->inicioMin($this->mas($efP, $L), (int)$a['dur'], $hito);
            case 'SF': return $this->inicioMin($this->mas($esP, $L), (int)$a['dur'], $hito);
            default: return $this->mas($efP, 1 + $L);   // FS
        }
    }

    /**
     * @param array $acts  id => ['id','inicio','fin','dur','hito','fija']
     * @param array $deps  lista de ['id','pred','suc','tipo','retraso']
     * @return array{ciclo: ?array, acts: array, deps: array, finCalculado: ?string, finProgramado: ?string, finProgramadoRed?: string}
     */
    public function calcular(array $acts, array $deps): array
    {
        $deps = array_values(array_filter($deps, fn($d) => isset($acts[$d['pred']], $acts[$d['suc']]) && $d['pred'] !== $d['suc']));
        $entra = $sale = [];
        $enRed = [];
        foreach ($deps as $i => $d) {
            $entra[$d['suc']][] = $i;
            $sale[$d['pred']][] = $i;
            $enRed[$d['pred']] = $enRed[$d['suc']] = true;
        }
        $res = ['ciclo' => null, 'acts' => [], 'deps' => [], 'finCalculado' => null, 'finProgramado' => $acts ? max(array_column($acts, 'fin')) : null];
        foreach ($acts as $id => $a) {
            $res['acts'][$id] = ['es' => $a['inicio'], 'ef' => $a['fin'], 'ls' => null, 'lf' => null, 'holgura' => null, 'critica' => false, 'conflicto' => false, 'empuje' => 0, 'enRed' => isset($enRed[$id])];
        }

        // Orden topológico (Kahn) de las actividades con dependencias
        $grado = [];
        foreach (array_keys($enRed) as $id) $grado[$id] = count($entra[$id] ?? []);
        $cola = array_keys(array_filter($grado, fn($g) => $g === 0));
        $orden = [];
        while ($cola) {
            $id = array_shift($cola);
            $orden[] = $id;
            foreach ($sale[$id] ?? [] as $i) {
                if (--$grado[$deps[$i]['suc']] === 0) $cola[] = $deps[$i]['suc'];
            }
        }
        if (count($orden) < count($enRed)) {
            $res['ciclo'] = array_values(array_diff(array_keys($enRed), $orden));
            return $res;
        }

        // Conflictos con las fechas actuales (sin propagar): ¿cada sucesora respeta lo que exige su predecesora?
        foreach ($deps as $d) {
            $s = $acts[$d['suc']];
            $req = $this->exigido($d, $acts[$d['pred']]['inicio'], $acts[$d['pred']]['fin'], $s);
            $mal = $s['inicio'] < $req;
            $res['deps'][$d['id']] = ['conflicto' => $mal, 'requerido' => $req];
            if ($mal) $res['acts'][$d['suc']]['conflicto'] = true;
        }

        // Pasada hacia adelante: fechas calculadas (solo empuja hacia después)
        foreach ($orden as $id) {
            $a = $acts[$id];
            $es = $a['inicio'];
            if (!$a['fija']) {
                foreach ($entra[$id] ?? [] as $i) {
                    $r = $res['acts'][$deps[$i]['pred']];
                    $req = $this->exigido($deps[$i], $r['es'], $r['ef'], $a);
                    if ($req > $es) $es = $req;
                }
            }
            $res['acts'][$id]['es'] = $es;
            $res['acts'][$id]['ef'] = $es === $a['inicio'] ? $a['fin'] : $this->fin($es, (int)$a['dur'], (bool)$a['hito']);
            $res['acts'][$id]['empuje'] = max(0, $this->difHab($a['inicio'], $es));
        }
        $res['finCalculado'] = max(array_map(fn($id) => $res['acts'][$id]['ef'], $orden));
        $res['finProgramadoRed'] = max(array_map(fn($id) => $acts[$id]['fin'], $orden));   // fin actual de las actividades enlazadas (para comparar con el calculado)

        // Pasada hacia atrás: fechas más tardías y holgura
        foreach (array_reverse($orden) as $id) {
            $a = $acts[$id];
            $lf = $res['finCalculado'];
            foreach ($sale[$id] ?? [] as $i) {
                $d = $deps[$i];
                $s = $res['acts'][$d['suc']];
                $L = (int)$d['retraso'];
                switch ($d['tipo']) {
                    case 'SS': $lim = $this->fin($this->mas($s['ls'], -$L), (int)$a['dur'], (bool)$a['hito']); break;
                    case 'FF': $lim = $this->mas($s['lf'], -$L); break;
                    case 'SF': $lim = $this->fin($this->mas($s['lf'], -$L), (int)$a['dur'], (bool)$a['hito']); break;
                    default: $lim = $this->mas($s['ls'], -(1 + $L));
                }
                if ($lim < $lf) $lf = $lim;
            }
            $ls = $this->inicioMax($lf, (int)$a['dur'], (bool)$a['hito']);
            $h = $this->difHab($res['acts'][$id]['es'], $ls);
            $res['acts'][$id]['lf'] = $lf;
            $res['acts'][$id]['ls'] = $ls;
            $res['acts'][$id]['holgura'] = $h;
            $res['acts'][$id]['critica'] = $h <= 0;
        }
        return $res;
    }
}
