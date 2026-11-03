<?php

namespace Classes;

/**
 * Evaluador de fórmulas de SheetFlow (mismo lenguaje que public/vendor/sheetflow.js, sin eval).
 * Celdas A1 (el encabezado es la fila 1), rangos A1:B3, + - * / ^ % &, comparaciones (números o texto) y funciones:
 * SUMA PROMEDIO MIN MAX CONTAR REDONDEAR · condicionales: SI Y O NO SI.ERROR SUMAR.SI CONTAR.SI PROMEDIO.SI.
 * Resultado: ['v' => float] | ['s' => string] | ['e' => '#ERROR!'].
 */
/** Error de valor en tiempo de ejecución (#DIV/0!, #VALOR!…): viaja como valor de celda; un error de sintaxis es una RuntimeException normal */
class ErrorValor extends \RuntimeException
{
}

class SheetflowFormulas
{
    private array $filas;
    private array $cols;
    private array $memo = [];
    private array $visitando = [];
    private string $src = '';
    private int $i = 0;
    private ?array $actual = null;
    /** Resultados que envió el cliente: las fórmulas del proyecto (AVANCE, FIN…) se conservan si no hay datos del programa */
    private array $cache = [];
    /** Filas de Sheetflow::datosProyecto() para resolver AVANCE/INICIO/FIN/DIAS.REST/PARTIDA */
    private static array $proyecto = [];

    private function __construct(array $d)
    {
        $this->filas = $d['rows'];
        $this->cols = $d['cols'];
        $this->cache = (array)($d['calc'] ?? []);
    }

    /** Datos del programa del proyecto para las fórmulas de proyecto (opcional) */
    public static function setProyecto(array $filas): void
    {
        self::$proyecto = $filas;
    }

    public static function esFormula($t): bool
    {
        return (bool)preg_match('/^\s*=/', (string)$t);
    }

    /** Completa `calc` y `tot` de una hoja ya saneada */
    public static function recalcular(array $d): array
    {
        $ev = new self($d);
        $calc = [];
        foreach ($d['rows'] as $r => $fila) {
            foreach ($fila as $c => $t) if (self::esFormula($t)) $calc["$r,$c"] = self::resultado($ev->celda($r, $c));
        }
        $tot = [];
        foreach ($d['cols'] as $c => $col) {
            if ($col['tot'] === '') continue;
            $vals = [];
            $llenas = 0;
            foreach ($d['rows'] as $r => $fila) {
                $v = self::esFormula($fila[$c]) ? ($calc["$r,$c"] ?? '') : $fila[$c];
                if ($v !== '') $llenas++;
                if (($n = Sheetflow::numero($v)) !== null) $vals[] = $n;
            }
            $res = match ($col['tot']) {
                'cuenta' => $llenas,
                'suma' => array_sum($vals),
                'promedio' => $vals ? array_sum($vals) / count($vals) : 0,
                'min' => $vals ? min($vals) : 0,
                default => $vals ? max($vals) : 0,
            };
            $tot[$c] = self::num($res);
        }
        $d['calc'] = (object)$calc;
        $d['tot'] = (object)$tot;
        return $d;
    }

    private static function num($x): string
    {
        $s = rtrim(rtrim(number_format((float)$x, 10, '.', ''), '0'), '.');
        return $s === '' || $s === '-0' ? '0' : $s;
    }

    private static function resultado(array $r): string
    {
        return isset($r['e']) ? $r['e'] : (isset($r['s']) ? $r['s'] : self::num($r['v']));
    }

    private function celda(int $r, int $c): array
    {
        if ($r < -1 || $c < 0) return ['e' => '#REF!'];
        if ($r === -1) return $c < count($this->cols) ? ['s' => $this->cols[$c]['n']] : ['v' => 0.0, 'vacio' => true];
        if ($r >= count($this->filas) || $c >= count($this->cols)) return ['v' => 0.0, 'vacio' => true];
        $t = $this->filas[$r][$c];
        if (!self::esFormula($t)) {
            if ($t === '') return ['v' => 0.0, 'vacio' => true];
            $n = Sheetflow::numero($t);
            return $n === null ? ['s' => $t] : ['v' => $n];
        }
        $k = "$r,$c";
        if (isset($this->memo[$k])) return $this->memo[$k];
        if (isset($this->visitando[$k])) return ['e' => '#CICLO!'];
        $this->visitando[$k] = true;
        [$ss, $si] = [$this->src, $this->i];
        $sa = $this->actual;
        $this->actual = ['r' => $r, 'c' => $c];
        $res = $this->evaluar(preg_replace('/^\s*=/', '', $t));
        [$this->src, $this->i] = [$ss, $si];
        $this->actual = $sa;
        unset($this->visitando[$k]);
        return $this->memo[$k] = $res;
    }

    private function evaluar(string $src): array
    {
        $this->src = $src;
        $this->i = 0;
        try {
            $r = $this->comparacion();
            $this->esp();
            if (isset($r['rango'])) {   // una referencia/rango como resultado de la fórmula: vale si es una sola celda
                if (count($r['rango']) === 1) return $r['rango'][0];
                $this->error('#VALOR!');
            }
            if ($this->i < strlen($this->src)) $this->error('#VALOR!');
            return $r;
        } catch (\RuntimeException $e) {
            return ['e' => $e->getMessage()];
        }
    }

    private function error(string $e): never
    {
        throw new \RuntimeException($e);
    }

    private function fallo(string $e): never
    {
        throw new ErrorValor($e);
    }

    private function esp(): void
    {
        while ($this->i < strlen($this->src) && ctype_space($this->src[$this->i])) $this->i++;
    }

    private function n(array $x): float
    {
        if (isset($x['e'])) $this->fallo($x['e']);
        if (isset($x['rango'])) $this->fallo('#VALOR!');
        if (isset($x['s'])) {
            $n = Sheetflow::numero($x['s']);
            if ($n === null) $this->fallo('#VALOR!');
            return $n;
        }
        return (float)$x['v'];
    }

    /** Ejecuta una operación y, si falla por un valor, devuelve el error como valor (el análisis sigue donde iba) */
    private function seguro(callable $f): array
    {
        try {
            return $f();
        } catch (ErrorValor $e) {
            return ['e' => $e->getMessage()];
        }
    }

    private function esTexto(array $x): bool
    {
        return isset($x['s']) && Sheetflow::numero($x['s']) === null;
    }

    private function texto(array $x): string
    {
        if (isset($x['e'])) $this->fallo($x['e']);
        if (isset($x['rango'])) $this->fallo('#VALOR!');
        return isset($x['s']) ? $x['s'] : (!empty($x['vacio']) ? '' : self::num($x['v']));
    }

    private function comparacion(): array
    {
        $a = $this->concat();
        $this->esp();
        if (!preg_match('/^(<=|>=|<>|<|>|=)/', substr($this->src, $this->i), $m)) return $a;
        $this->i += strlen($m[1]);
        $b = $this->concat();
        return $this->seguro(function () use ($a, $b, $m) {
            if ($this->esTexto($a) || $this->esTexto($b)) {
                $c = strcasecmp($this->texto($a), $this->texto($b));
                $x = $c <=> 0;
                $y = 0;
            } else {
                $x = $this->n($a);
                $y = $this->n($b);
            }
            return ['v' => (float)match ($m[1]) { '=' => $x == $y, '<>' => $x != $y, '<' => $x < $y, '>' => $x > $y, '<=' => $x <= $y, default => $x >= $y }];
        });
    }

    private function concat(): array
    {
        $a = $this->suma();
        $this->esp();
        while (($this->src[$this->i] ?? '') === '&') {
            $this->i++;
            $b = $this->suma();
            $a = $this->seguro(fn() => ['s' => $this->texto($a) . $this->texto($b)]);
            $this->esp();
        }
        return $a;
    }

    private function suma(): array
    {
        $a = $this->prod();
        $this->esp();
        while ($this->i < strlen($this->src) && ($this->src[$this->i] === '+' || $this->src[$this->i] === '-')) {
            $op = $this->src[$this->i++];
            $b = $this->prod();
            $a = $this->seguro(fn() => ['v' => $op === '+' ? $this->n($a) + $this->n($b) : $this->n($a) - $this->n($b)]);
            $this->esp();
        }
        return $a;
    }

    private function prod(): array
    {
        $a = $this->pot();
        $this->esp();
        while ($this->i < strlen($this->src) && ($this->src[$this->i] === '*' || $this->src[$this->i] === '/')) {
            $op = $this->src[$this->i++];
            $b = $this->pot();
            $a = $this->seguro(function () use ($op, $a, $b) {
                if ($op === '/') {
                    $d = $this->n($b);
                    if ($d == 0) $this->fallo('#DIV/0!');
                    return ['v' => $this->n($a) / $d];
                }
                return ['v' => $this->n($a) * $this->n($b)];
            });
            $this->esp();
        }
        return $a;
    }

    private function pot(): array
    {
        $a = $this->unario();
        $this->esp();
        if (($this->src[$this->i] ?? '') === '^') {
            $this->i++;
            $b = $this->pot();
            $a = $this->seguro(fn() => ['v' => (float)pow($this->n($a), $this->n($b))]);
        }
        return $a;
    }

    private function unario(): array
    {
        $this->esp();
        $c = $this->src[$this->i] ?? '';
        if ($c === '-') { $this->i++; $x = $this->unario(); return $this->seguro(fn() => ['v' => -$this->n($x)]); }
        if ($c === '+') { $this->i++; return $this->unario(); }
        return $this->primario();
    }

    private function args(): array
    {
        $out = [];
        $this->esp();
        if (($this->src[$this->i] ?? '') === ')') { $this->i++; return $out; }
        for (;;) {
            $out[] = $this->comparacion();
            $this->esp();
            $c = $this->src[$this->i] ?? '';
            if ($c === ',' || $c === ';') { $this->i++; continue; }
            if ($c === ')') { $this->i++; return $out; }
            $this->error('#VALOR!');
        }
    }

    private function lista(array $a): array
    {
        $nums = [];
        foreach ($a as $x) {
            if (isset($x['rango'])) {
                foreach ($x['rango'] as $c) {
                    if (isset($c['e'])) $this->fallo($c['e']);
                    if (isset($c['v']) && empty($c['vacio'])) $nums[] = $c['v'];
                }
            } elseif (isset($x['e'])) {
                $this->fallo($x['e']);
            } elseif (isset($x['v'])) {
                $nums[] = $x['v'];
            } elseif (isset($x['s']) && ($n = Sheetflow::numero($x['s'])) !== null) {
                $nums[] = $n;
            }
        }
        return $nums;
    }

    private function letraAIndice(string $l): int
    {
        $n = 0;
        foreach (str_split(strtoupper($l)) as $ch) $n = $n * 26 + ord($ch) - 64;
        return $n - 1;
    }

    private function primario(): array
    {
        $this->esp();
        $rest = substr($this->src, $this->i);
        if (preg_match('/^(\d+([.,]\d+)?|[.]\d+)/', $rest, $m)) {
            $this->i += strlen($m[0]);
            $v = (float)str_replace(',', '.', $m[0]);
            $this->esp();
            if (($this->src[$this->i] ?? '') === '%') { $this->i++; $v /= 100; }
            return ['v' => $v];
        }
        if (preg_match('/^(VERDADERO|FALSO|TRUE|FALSE)\b/i', $rest, $m)) {
            $this->i += strlen($m[0]);
            return ['v' => in_array(strtoupper($m[1]), ['VERDADERO', 'TRUE'], true) ? 1.0 : 0.0];
        }
        if (preg_match('/^#(REF!|N\/A|NUM!|DIV\/0!|VALOR!|CICLO!|NOMBRE\?)/', $rest, $m)) { $this->i += strlen($m[0]); return ['e' => '#' . $m[1]]; }
        $c = $rest[0] ?? '';
        if ($c === '(') {
            $this->i++;
            $x = $this->comparacion();
            $this->esp();
            if (($this->src[$this->i] ?? '') !== ')') $this->error('#VALOR!');
            $this->i++;
            return $x;
        }
        if ($c === '"') {
            $j = strpos($this->src, '"', $this->i + 1);
            if ($j === false) $this->error('#VALOR!');
            $s = substr($this->src, $this->i + 1, $j - $this->i - 1);
            $this->i = $j + 1;
            return ['s' => $s];
        }
        if (preg_match('/^\$?([A-Za-z]{1,2})\$?(\d+)(?::\$?([A-Za-z]{1,2})\$?(\d+))?(?![A-Za-z(])/', $rest, $m)) {
            $this->i += strlen($m[0]);
            $c0 = $this->letraAIndice($m[1]);
            $r0 = (int)$m[2] - 2;
            if (!empty($m[3])) {
                $c1 = $this->letraAIndice($m[3]);
                $r1 = (int)$m[4] - 2;
                $out = [];
                for ($r = min($r0, $r1); $r <= max($r0, $r1); $r++) for ($cc = min($c0, $c1); $cc <= max($c0, $c1); $cc++) $out[] = $this->celda($r, $cc);
                return ['rango' => $out, 'r0' => min($r0, $r1), 'c0' => min($c0, $c1), 'r1' => max($r0, $r1), 'c1' => max($c0, $c1)];
            }
            $x = $this->celda($r0, $c0);
            $x['ref'] = ['r' => $r0, 'c' => $c0];
            return $x;
        }
        if (preg_match('/^([A-Za-zÁÉÍÓÚáéíóúÑñ.]+)\s*\(/u', $rest, $m)) {
            $this->i += strlen($m[0]);
            $f = strtr(mb_strtoupper($m[1]), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
            $a = $this->args();
            return $this->seguro(fn() => $this->funcion($f, $a));
        }
        $this->error('#VALOR!');
    }

    /** ¿La celda cumple el criterio de SUMAR.SI / CONTAR.SI? (número, texto, «>5», «<>x», comodines * y ?) */
    private function cumple(array $c, array $crit): bool
    {
        if (isset($c['e'])) return false;
        $t = isset($crit['s']) ? $crit['s'] : (isset($crit['v']) ? self::num($crit['v']) : '');
        $op = '=';
        if (preg_match('/^(<=|>=|<>|<|>|=)(.*)$/s', $t, $m)) [$op, $t] = [$m[1], $m[2]];
        $valor = isset($c['s']) ? $c['s'] : (!empty($c['vacio']) ? '' : self::num($c['v']));
        $nc = Sheetflow::numero($valor);
        $nt = Sheetflow::numero($t);
        if ($nt !== null && $nc !== null && $valor !== '') {
            $x = $nc;
            $y = $nt;
            return match ($op) { '=' => $x == $y, '<>' => $x != $y, '<' => $x < $y, '>' => $x > $y, '<=' => $x <= $y, default => $x >= $y };
        }
        if ($op === '=' || $op === '<>') {
            $re = '/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($t, '/')) . '$/iu';
            return (bool)preg_match($re, $valor) === ($op === '=');
        }
        if ($nt !== null || $valor === '') return false;
        $k = strcasecmp($valor, $t);
        return match ($op) { '<' => $k < 0, '>' => $k > 0, '<=' => $k <= 0, default => $k >= 0 };
    }

    private function funcion(string $f, array $a): array
    {
        switch ($f) {
            // ---------- Matemáticas ----------
            case 'SUMA': case 'SUM': return ['v' => array_sum($this->lista($a))];
            case 'PROMEDIO': case 'AVERAGE': case 'PROM':
                $n = $this->lista($a);
                if (!$n) $this->fallo('#DIV/0!');
                return ['v' => array_sum($n) / count($n)];
            case 'MIN': $n = $this->lista($a); return ['v' => $n ? (float)min($n) : 0.0];
            case 'MAX': $n = $this->lista($a); return ['v' => $n ? (float)max($n) : 0.0];
            case 'CONTAR': case 'COUNT': return ['v' => (float)count($this->lista($a))];
            case 'PRODUCTO': case 'PRODUCT':
                $n = $this->lista($a);
                if (!$n) return ['v' => 0.0];
                $p = 1.0;
                foreach ($n as $x) $p *= $x;
                return ['v' => $p];
            case 'CONTARA': case 'COUNTA':
                $n = 0;
                foreach ($this->celdasDe($a) as $c) {
                    if (isset($c['e']) || !empty($c['vacio'])) continue;
                    if (isset($c['s']) && $c['s'] === '') continue;
                    $n++;
                }
                return ['v' => (float)$n];
            case 'CONTAR.BLANCO': case 'COUNTBLANK':
                $n = 0;
                foreach ($this->celdasDe($a) as $c) {
                    if (isset($c['e'])) continue;
                    if (!empty($c['vacio']) || (isset($c['s']) && $c['s'] === '')) $n++;
                }
                return ['v' => (float)$n];
            case 'SUMAPRODUCTO': case 'SUMPRODUCT': return ['v' => $this->sumaProducto($a)];
            case 'SUMA.CUADRADOS': case 'SUMSQ':
                $tot = 0.0;
                foreach ($this->lista($a) as $x) $tot += $x * $x;
                return ['v' => $tot];
            case 'ABS': return ['v' => abs($this->n($a[0] ?? ['v' => 0]))];
            case 'ENTERO': case 'INT': return ['v' => floor($this->n($a[0] ?? ['v' => 0]))];
            case 'TRUNCAR': case 'TRUNC':
                $dec = isset($a[1]) ? (int)$this->n($a[1]) : 0;
                return ['v' => $this->redondear($this->n($a[0] ?? ['v' => 0]), $dec, 'menos')];
            case 'RESIDUO': case 'MOD':
                $d = $this->n($a[1] ?? ['v' => 0]);
                if ($d == 0) $this->fallo('#DIV/0!');
                $v = $this->n($a[0] ?? ['v' => 0]);
                return ['v' => $v - $d * floor($v / $d)];
            case 'POTENCIA': case 'POWER': return ['v' => (float)pow($this->n($a[0] ?? ['v' => 0]), $this->n($a[1] ?? ['v' => 0]))];
            case 'RAIZ': case 'SQRT':
                $v = $this->n($a[0] ?? ['v' => 0]);
                if ($v < 0) $this->fallo('#NUM!');
                return ['v' => sqrt($v)];
            case 'EXP': return ['v' => exp($this->n($a[0] ?? ['v' => 0]))];
            case 'LN': case 'LOG':
                $v = $this->n($a[0] ?? ['v' => 0]);
                if ($v <= 0) $this->fallo('#NUM!');
                $base = isset($a[1]) ? $this->n($a[1]) : M_E;
                if ($base <= 0 || $base == 1) $this->fallo('#NUM!');
                return ['v' => log($v, $base)];
            case 'LOG10':
                $v = $this->n($a[0] ?? ['v' => 0]);
                if ($v <= 0) $this->fallo('#NUM!');
                return ['v' => log10($v)];
            case 'PI': return ['v' => M_PI];
            case 'SIGNO': case 'SIGN':
                $v = $this->n($a[0] ?? ['v' => 0]);
                return ['v' => $v > 0 ? 1.0 : ($v < 0 ? -1.0 : 0.0)];
            case 'COCIENTE': case 'QUOTIENT':
                $d = $this->n($a[1] ?? ['v' => 0]);
                if ($d == 0) $this->fallo('#DIV/0!');
                return ['v' => (float)(int)($this->n($a[0] ?? ['v' => 0]) / $d)];
            case 'REDONDEAR': case 'ROUND': return ['v' => $this->redondear($this->n($a[0] ?? ['v' => 0]), isset($a[1]) ? (int)$this->n($a[1]) : 0)];
            case 'REDONDEAR.MAS': case 'ROUNDUP': return ['v' => $this->redondear($this->n($a[0] ?? ['v' => 0]), isset($a[1]) ? (int)$this->n($a[1]) : 0, 'mas')];
            case 'REDONDEAR.MENOS': case 'ROUNDDOWN': return ['v' => $this->redondear($this->n($a[0] ?? ['v' => 0]), isset($a[1]) ? (int)$this->n($a[1]) : 0, 'menos')];
            case 'REDONDEAR.MULT': case 'MROUND':
                $m = $this->n($a[1] ?? ['v' => 0]);
                if ($m == 0) return ['v' => 0.0];
                return ['v' => $this->redondear($this->n($a[0] ?? ['v' => 0]) / $m, 0) * $m];
            case 'MULTIPLO.SUPERIOR': case 'CEILING':
                $m = $this->n($a[1] ?? ['v' => 1]);
                if ($m == 0) $this->fallo('#DIV/0!');
                return ['v' => ceil($this->n($a[0] ?? ['v' => 0]) / $m) * $m];
            case 'MULTIPLO.INFERIOR': case 'FLOOR':
                $m = $this->n($a[1] ?? ['v' => 1]);
                if ($m == 0) $this->fallo('#DIV/0!');
                return ['v' => floor($this->n($a[0] ?? ['v' => 0]) / $m) * $m];
            case 'M.C.D.': case 'GCD': {
                $x = abs((int)$this->n($a[0] ?? ['v' => 0]));
                $y = abs((int)$this->n($a[1] ?? ['v' => 0]));
                while ($y) { [$x, $y] = [$y, $x % $y]; }
                return ['v' => (float)$x];
            }
            case 'M.C.M.': case 'LCM': {
                $x = abs((int)$this->n($a[0] ?? ['v' => 0]));
                $y = abs((int)$this->n($a[1] ?? ['v' => 0]));
                if ($x === 0 || $y === 0) return ['v' => 0.0];
                $a1 = $x; $b1 = $y;
                while ($b1) { [$a1, $b1] = [$b1, $a1 % $b1]; }
                return ['v' => (float)($x / $a1 * $y)];
            }
            case 'ALEATORIO': case 'RAND': return ['v' => mt_rand() / mt_getrandmax()];
            case 'ALEATORIO.ENTRE': case 'RANDBETWEEN':
                $lo = (int)$this->n($a[0] ?? ['v' => 0]); $hi = (int)$this->n($a[1] ?? ['v' => 1]);
                if ($lo > $hi) [$lo, $hi] = [$hi, $lo];
                return ['v' => (float)mt_rand($lo, $hi)];
            case 'SUBTOTALES': case 'SUBTOTAL': {
                $tipo = (int)$this->n($a[0] ?? ['v' => 0]);
                $vals = $this->lista(array_slice($a, 1));
                return match ($tipo) {
                    1 => ['v' => $vals ? array_sum($vals) / count($vals) : 0.0],
                    2 => ['v' => (float)count($vals)],
                    4 => ['v' => $vals ? (float)max($vals) : 0.0],
                    5 => ['v' => $vals ? (float)min($vals) : 0.0],
                    9 => ['v' => array_sum($vals)],
                    default => $this->fallo('#VALOR!'),
                };
            }

            // ---------- Estadística ----------
            case 'MEDIANA': case 'MEDIAN':
                $n = $this->lista($a);
                if (!$n) $this->fallo('#NUM!');
                sort($n);
                $k = count($n);
                return ['v' => $k % 2 ? (float)$n[intdiv($k, 2)] : ($n[$k / 2 - 1] + $n[$k / 2]) / 2];
            case 'MODA': case 'MODE': return ['v' => $this->moda($this->lista($a))];
            case 'DESVEST': case 'STDEV': return ['v' => $this->varianza($this->lista($a), true, true)];
            case 'DESVEST.P': case 'STDEVP': return ['v' => $this->varianza($this->lista($a), true, false)];
            case 'VAR': return ['v' => $this->varianza($this->lista($a), false, true)];
            case 'VAR.P': case 'VARP': return ['v' => $this->varianza($this->lista($a), false, false)];
            case 'K.ESIMO.MAYOR': case 'LARGE': return ['v' => $this->kEsimo($this->lista($a), $this->n($a[1] ?? ['v' => 0]), true)];
            case 'K.ESIMO.MENOR': case 'SMALL': return ['v' => $this->kEsimo($this->lista($a), $this->n($a[1] ?? ['v' => 0]), false)];
            case 'JERARQUIA': case 'RANK': return ['v' => $this->jerarquia($this->n($a[0] ?? ['v' => 0]), $this->lista(array_slice($a, 1, 1)), isset($a[2]) ? $this->n($a[2]) : 0)];
            case 'PERCENTIL': case 'PERCENTILE': return ['v' => $this->percentil($this->lista($a), $this->n($a[1] ?? ['v' => 0]))];
            case 'CUARTIL': case 'QUARTILE': {
                $q = (int)$this->n($a[1] ?? ['v' => 0]);
                if ($q < 0 || $q > 4) $this->fallo('#NUM!');
                return ['v' => $this->percentil($this->lista($a), $q / 4)];
            }
            case 'MAX.SI': case 'MAXIFS':
            case 'MIN.SI': case 'MINIFS': {
                $r = $this->rangoDe($a[0] ?? []);
                $idx = $this->indicesCriterios($a, 1);
                $vals = [];
                foreach ($idx as $i) {
                    $c = $r['vals'][$i] ?? ['v' => 0, 'vacio' => true];
                    if (isset($c['e'])) $this->fallo($c['e']);
                    $x = isset($c['s']) ? Sheetflow::numero($c['s']) : ($c['v'] ?? null);
                    if ($x !== null && empty($c['vacio'])) $vals[] = (float)$x;
                }
                if (!$vals) return ['v' => 0.0];
                return ['v' => in_array($f, ['MAX.SI', 'MAXIFS'], true) ? (float)max($vals) : (float)min($vals)];
            }
            case 'SUMAR.SI.CONJUNTO': case 'SUMIFS':
            case 'PROMEDIO.SI.CONJUNTO': case 'AVERAGEIFS':
            case 'CONTAR.SI.CONJUNTO': case 'COUNTIFS': {
                $suma = in_array($f, ['CONTAR.SI.CONJUNTO', 'COUNTIFS'], true) ? null : $this->rangoDe($a[0] ?? []);
                $idx = $this->indicesCriterios($a, $suma ? 1 : 0);
                if ($suma === null) return ['v' => (float)count($idx)];
                $tot = 0.0;
                foreach ($idx as $i) {
                    $c = $suma['vals'][$i] ?? ['v' => 0, 'vacio' => true];
                    if (isset($c['e'])) $this->fallo($c['e']);
                    $x = isset($c['s']) ? Sheetflow::numero($c['s']) : ($c['v'] ?? null);
                    $tot += $x ?? 0.0;
                }
                if (in_array($f, ['PROMEDIO.SI.CONJUNTO', 'AVERAGEIFS'], true)) {
                    if (!$idx) $this->fallo('#DIV/0!');
                    return ['v' => $tot / count($idx)];
                }
                return ['v' => $tot];
            }

            // ---------- Lógica e información ----------
            case 'SI': case 'IF': return $this->n($a[0] ?? ['v' => 0]) ? ($a[1] ?? ['v' => 0.0]) : ($a[2] ?? ['v' => 0.0]);
            case 'SI.CONJUNTO': case 'IFS': {
                $n = count($a);
                for ($i = 0; $i + 1 < $n; $i += 2) {
                    if ($this->n($a[$i])) return $a[$i + 1];
                }
                if ($n % 2 === 1) return $a[$n - 1];
                $this->fallo('#N/A');
            }
            case 'Y': case 'AND':
            case 'O': case 'OR':
                if (!$a) $this->fallo('#VALOR!');
                $y = in_array($f, ['Y', 'AND'], true);
                foreach ($a as $x) {
                    foreach (isset($x['rango']) ? $x['rango'] : [$x] as $v) {
                        if (isset($v['e'])) $this->fallo($v['e']);
                        if (!empty($v['vacio'])) continue;
                        $b = $this->n($v) != 0;
                        if ($y && !$b) return ['v' => 0.0];
                        if (!$y && $b) return ['v' => 1.0];
                    }
                }
                return ['v' => $y ? 1.0 : 0.0];
            case 'NO': case 'NOT': return ['v' => $this->n($a[0] ?? ['v' => 0]) == 0 ? 1.0 : 0.0];
            case 'SI.ERROR': case 'IFERROR':
                $x = $a[0] ?? ['v' => 0.0];
                return isset($x['e']) ? ($a[1] ?? ['s' => '']) : $x;
            case 'SI.ND': case 'IFNA':
                $x = $a[0] ?? ['v' => 0.0];
                return (isset($x['e']) && $x['e'] === '#N/A') ? ($a[1] ?? ['s' => '']) : $x;
            case 'ES.ERROR': case 'ISERROR': return ['v' => isset($a[0]['e']) ? 1.0 : 0.0];
            case 'ES.NUMERO': case 'ISNUMBER': $x = $a[0] ?? []; return ['v' => isset($x['v']) && !isset($x['s']) ? 1.0 : 0.0];
            case 'ES.TEXTO': case 'ISTEXT': $x = $a[0] ?? []; return ['v' => isset($x['s']) ? 1.0 : 0.0];
            case 'ES.BLANCO': case 'ISBLANK': $x = $a[0] ?? []; return ['v' => (!empty($x['vacio']) || (isset($x['s']) && $x['s'] === '')) ? 1.0 : 0.0];
            case 'ES.PAR': case 'ISEVEN': return ['v' => (int)floor($this->n($a[0] ?? ['v' => 0])) % 2 === 0 ? 1.0 : 0.0];
            case 'ES.IMPAR': case 'ISODD': return ['v' => abs((int)floor($this->n($a[0] ?? ['v' => 0]))) % 2 === 1 ? 1.0 : 0.0];
            case 'ESLOGICO': case 'ISLOGICAL': $x = $a[0] ?? []; return ['v' => (isset($x['v']) && ($x['v'] == 0 || $x['v'] == 1)) ? 1.0 : 0.0];
            case 'CAMBIAR': case 'SWITCH': return $this->cambiar($a);
            case 'ELEGIR': case 'CHOOSE': return $this->elegir($a);
            case 'SUMAR.SI': case 'SUMIF': case 'CONTAR.SI': case 'COUNTIF': case 'PROMEDIO.SI': case 'AVERAGEIF':
                $rango = $a[0]['rango'] ?? (isset($a[0]) ? [$a[0]] : null);
                if ($rango === null || !isset($a[1])) $this->fallo('#VALOR!');
                $suma = in_array($f, ['SUMAR.SI', 'SUMIF', 'PROMEDIO.SI', 'AVERAGEIF'], true) ? ($a[2]['rango'] ?? $rango) : null;
                $n = 0;
                $tot = 0.0;
                foreach ($rango as $i => $c) {
                    if (!$this->cumple($c, $a[1])) continue;
                    $n++;
                    if ($suma !== null) {
                        $v = $suma[$i] ?? ['v' => 0.0, 'vacio' => true];
                        if (isset($v['e'])) $this->fallo($v['e']);
                        $x = isset($v['s']) ? Sheetflow::numero($v['s']) : ($v['v'] ?? 0.0);
                        $tot += $x ?? 0.0;
                    }
                }
                if (in_array($f, ['CONTAR.SI', 'COUNTIF'], true)) return ['v' => (float)$n];
                if (in_array($f, ['PROMEDIO.SI', 'AVERAGEIF'], true)) {
                    if (!$n) $this->fallo('#DIV/0!');
                    return ['v' => $tot / $n];
                }
                return ['v' => $tot];

            // ---------- Texto ----------
            case 'IZQUIERDA': case 'LEFT': return ['s' => mb_substr($this->texto($a[0] ?? ['s' => '']), 0, $this->enteroNoNeg($a[1] ?? ['v' => 1]))];
            case 'DERECHA': case 'RIGHT': {
                $t = $this->texto($a[0] ?? ['s' => '']);
                $n = $this->enteroNoNeg($a[1] ?? ['v' => 1]);
                return ['s' => $n === 0 ? '' : mb_substr($t, -$n)];
            }
            case 'EXTRAE': case 'MID': {
                $t = $this->texto($a[0] ?? ['s' => '']);
                $ini = (int)$this->n($a[1] ?? ['v' => 1]);
                $n = isset($a[2]) ? $this->enteroNoNeg($a[2]) : mb_strlen($t);
                if ($ini < 1) $this->fallo('#VALOR!');
                return ['s' => mb_substr($t, $ini - 1, $n)];
            }
            case 'LARGO': case 'LEN': return ['v' => (float)mb_strlen($this->texto($a[0] ?? ['s' => '']))];
            case 'MAYUSC': case 'UPPER': return ['s' => mb_strtoupper($this->texto($a[0] ?? ['s' => '']))];
            case 'MINUSC': case 'LOWER': return ['s' => mb_strtolower($this->texto($a[0] ?? ['s' => '']))];
            case 'NOMPROPIO': case 'PROPER': return ['s' => mb_convert_case($this->texto($a[0] ?? ['s' => '']), MB_CASE_TITLE, 'UTF-8')];
            case 'RECORTAR': case 'ESPACIOS': case 'TRIM': return ['s' => trim(preg_replace('/\s+/u', ' ', $this->texto($a[0] ?? ['s' => ''])))];
            case 'SUSTITUIR': case 'SUBSTITUTE': return ['s' => $this->sustituir($this->texto($a[0] ?? ['s' => '']), $this->texto($a[1] ?? ['s' => '']), $this->texto($a[2] ?? ['s' => '']), isset($a[3]) ? (int)$this->n($a[3]) : 0)];
            case 'REEMPLAZAR': case 'REPLACE': {
                $t = $this->texto($a[0] ?? ['s' => '']);
                $ini = (int)$this->n($a[1] ?? ['v' => 1]);
                if ($ini < 1) $this->fallo('#VALOR!');
                $cuenta = $this->enteroNoNeg($a[2] ?? ['v' => 0]);
                return ['s' => mb_substr($t, 0, $ini - 1) . $this->texto($a[3] ?? ['s' => '']) . mb_substr($t, $ini - 1 + $cuenta)];
            }
            case 'EXACTO': case 'EXACT': return ['v' => $this->texto($a[0] ?? ['s' => '']) === $this->texto($a[1] ?? ['s' => '']) ? 1.0 : 0.0];
            case 'LIMPIA': case 'CLEAN': return ['s' => preg_replace('/[\x00-\x1F\x7F]/u', '', $this->texto($a[0] ?? ['s' => '']))];
            case 'MONEDA': case 'DOLLAR': {
                $num = $this->n($a[0] ?? ['v' => 0]);
                $dec = isset($a[1]) ? max(0, min(10, (int)$this->n($a[1]))) : 2;
                return ['s' => ($num < 0 ? '-' : '') . '$' . number_format(abs($num), $dec, '.', ',')];
            }
            case 'ENCONTRAR': case 'FIND': {
                $pos = mb_strpos($this->texto($a[1] ?? ['s' => '']), $this->texto($a[0] ?? ['s' => '']), isset($a[2]) ? max(0, (int)$this->n($a[2]) - 1) : 0);
                if ($pos === false) $this->fallo('#VALOR!');
                return ['v' => (float)($pos + 1)];
            }
            case 'HALLAR': case 'SEARCH': {
                $pos = mb_stripos($this->texto($a[1] ?? ['s' => '']), $this->texto($a[0] ?? ['s' => '']), isset($a[2]) ? max(0, (int)$this->n($a[2]) - 1) : 0);
                if ($pos === false) $this->fallo('#VALOR!');
                return ['v' => (float)($pos + 1)];
            }
            case 'REPETIR': case 'REPT': {
                $n = $this->enteroNoNeg($a[1] ?? ['v' => 0]);
                if ($n > 1000) $this->fallo('#VALOR!');
                return ['s' => str_repeat($this->texto($a[0] ?? ['s' => '']), $n)];
            }
            case 'CONCATENAR': case 'CONCATENATE': case 'CONCAT': {
                $s = '';
                foreach ($this->celdasDe($a) as $c) $s .= $this->textoDe($c);
                return ['s' => $s];
            }
            case 'UNIRCADENAS': case 'TEXTJOIN': return ['s' => $this->unirCadenas($a)];
            case 'TEXTO': case 'TEXT': return ['s' => $this->textoFormato($a[0] ?? ['v' => 0], $this->texto($a[1] ?? ['s' => '']))];
            case 'VALOR': return ['v' => $this->valorNumerico($a[0] ?? ['s' => ''], false)];
            case 'NUMERO.VALOR': case 'NUMBERVALUE': return ['v' => $this->valorNumerico($a[0] ?? ['s' => ''], true)];
            case 'T': { $x = $a[0] ?? []; return ['s' => isset($x['s']) ? $x['s'] : '']; }

            // ---------- Búsqueda y referencias ----------
            case 'BUSCARV': case 'VLOOKUP': return $this->buscarColumna($a);
            case 'BUSCARH': case 'HLOOKUP': return $this->buscarFila($a);
            case 'COINCIDIR': case 'MATCH': return ['v' => (float)$this->coincidir($a)];
            case 'INDICE': case 'INDEX': return $this->indice($a);
            case 'BUSCARX': case 'XLOOKUP': return $this->buscarX($a);
            case 'BUSCAR': case 'LOOKUP': {
                $clave = $a[0] ?? ['s' => ''];
                $r = $this->rangoDe($a[1] ?? []);
                $destino = isset($a[2]) ? $this->rangoDe($a[2]) : $r;
                $idx = -1;
                foreach ($r['vals'] as $i => $c) {
                    if (isset($c['e'])) continue;
                    $cmp = $this->comparar($c, $clave);
                    if ($cmp <= 0) $idx = $i;
                    if ($cmp > 0) break;
                }
                if ($idx < 0) $this->fallo('#N/A');
                return $destino['vals'][$idx] ?? ['e' => '#N/A'];
            }
            case 'FILA': case 'ROW':
                if (!isset($a[0])) { $r = $this->actual['r'] ?? 0; return ['v' => (float)($r + 2)]; }
                return ['v' => (float)(($a[0]['ref']['r'] ?? $a[0]['r0'] ?? 0) + 2)];
            case 'COLUMNA': case 'COLUMN':
                if (!isset($a[0])) { $c = $this->actual['c'] ?? 0; return ['v' => (float)($c + 1)]; }
                return ['v' => (float)(($a[0]['ref']['c'] ?? $a[0]['c0'] ?? 0) + 1)];
            case 'DIRECCION': case 'ADDRESS': {
                $r = (int)$this->n($a[0] ?? ['v' => 1]); $c = (int)$this->n($a[1] ?? ['v' => 1]);
                $abs = isset($a[2]) ? (int)$this->n($a[2]) : 1;
                if ($r < 1 || $c < 1) $this->fallo('#VALOR!');
                $l = $this->letra($c - 1);
                return ['s' => ($abs === 1 || $abs === 3 ? '$' : '') . $l . ($abs === 1 || $abs === 2 ? '$' : '') . $r];
            }
            case 'DESREF': case 'OFFSET': return $this->desref($a);
            case 'INDIRECTO': case 'INDIRECT': return $this->indirecto($a);

            // ---------- Fórmulas del proyecto ----------
            case 'AVANCE': case 'INICIO': case 'FIN': case 'DIAS.REST': case 'PARTIDA': case 'NOMBRE.PARTIDA': case 'TIPO':
                return $this->funcionProyecto($f, $a);

            // ---------- Fechas ----------
            case 'HOY': case 'TODAY': return ['s' => date('Y-m-d')];
            case 'AHORA': case 'NOW': return ['s' => date('Y-m-d H:i:s')];
            case 'FECHA': case 'DATE':
                $y = (int)$this->n($a[0] ?? ['v' => 0]); $m = (int)$this->n($a[1] ?? ['v' => 1]); $d = (int)$this->n($a[2] ?? ['v' => 1]);
                if ($m < 1 || $m > 12 || $d < 1 || $d > 31) {
                    // Excel normaliza meses/días fuera de rango
                    $ts = mktime(12, 0, 0, $m, $d, $y);
                    if ($ts === false) $this->fallo('#NUM!');
                    return ['s' => date('Y-m-d', $ts)];
                }
                return ['s' => sprintf('%04d-%02d-%02d', $y, $m, $d)];
            case 'FECHAVALOR': case 'DATEVALUE': {
                $iso = $this->fechaISO($a[0] ?? []);
                if (!$iso) $this->fallo('#VALOR!');
                return ['s' => $iso];
            }
            case 'HORA': case 'HOUR':
            case 'MINUTO': case 'MINUTE':
            case 'SEGUNDO': case 'SECOND': {
                $t = trim($this->texto($a[0] ?? ['s' => '']));
                if (!preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?/', $t, $m)) $this->fallo('#VALOR!');
                $h = (int)$m[1]; $mi = (int)$m[2]; $se = isset($m[3]) ? (int)$m[3] : 0;
                if ($h > 23 || $mi > 59 || $se > 59) $this->fallo('#NUM!');
                if ($f === 'HORA' || $f === 'HOUR') return ['v' => (float)$h];
                if ($f === 'MINUTO' || $f === 'MINUTE') return ['v' => (float)$mi];
                return ['v' => (float)$se];
            }
            case 'AÑO': case 'ANO': case 'YEAR': return ['v' => (float)$this->fechaParte($a[0] ?? [], 'Y')];
            case 'MES': case 'MONTH': return ['v' => (float)$this->fechaParte($a[0] ?? [], 'n')];
            case 'DIA': case 'DAY': return ['v' => (float)$this->fechaParte($a[0] ?? [], 'j')];
            case 'DIAS': case 'DAYS': return ['v' => (float)$this->diasEntre($this->fechaISO($a[1] ?? []), $this->fechaISO($a[0] ?? []))];
            case 'FECHA.MES': case 'EDATE': return ['s' => $this->sumarMeses($this->fechaISO($a[0] ?? []), (int)$this->n($a[1] ?? ['v' => 0]), false)];
            case 'FIN.MES': case 'EOMONTH': return ['s' => $this->sumarMeses($this->fechaISO($a[0] ?? []), (int)$this->n($a[1] ?? ['v' => 0]), true)];
            case 'SIFECHA': case 'DATEDIF': return ['v' => (float)$this->siFecha($a)];
            case 'NUM.DE.SEMANA': case 'WEEKDAY': return ['v' => (float)$this->numeroSemana($this->fechaISO($a[0] ?? []), isset($a[1]) ? (int)$this->n($a[1]) : 1)];
            case 'DIAS.LAB': case 'WORKDAY': return ['s' => $this->diasLaborables($a)];
            case 'DIAS.LAB.INTL': case 'WORKDAY.INTL': {
                $b = [$a[0] ?? [], $a[1] ?? ['v' => 0], $a[3] ?? []];
                return ['s' => $this->diasLaborables($b)];
            }

            // ---------- Finanzas ----------
            case 'PAGO': case 'PMT': return ['v' => $this->pago($this->n($a[0] ?? ['v' => 0]), $this->n($a[1] ?? ['v' => 0]), $this->n($a[2] ?? ['v' => 0]), isset($a[3]) ? $this->n($a[3]) : 0.0, isset($a[4]) ? $this->n($a[4]) : 0.0)];
            case 'VA': case 'PV': {
                $r = $this->n($a[0] ?? ['v' => 0]); $nper = $this->n($a[1] ?? ['v' => 0]); $pago = $this->n($a[2] ?? ['v' => 0]);
                $vf = isset($a[3]) ? $this->n($a[3]) : 0.0; $tipo = isset($a[4]) ? $this->n($a[4]) : 0.0;
                return ['v' => $this->valorActual($r, $nper, $pago, $vf, $tipo)];
            }
            case 'VF': case 'FV': {
                $r = $this->n($a[0] ?? ['v' => 0]); $nper = $this->n($a[1] ?? ['v' => 0]); $pago = $this->n($a[2] ?? ['v' => 0]);
                $vp = isset($a[3]) ? $this->n($a[3]) : 0.0; $tipo = isset($a[4]) ? $this->n($a[4]) : 0.0;
                return ['v' => $this->valorFuturo($r, $nper, $pago, $vp, $tipo)];
            }
            case 'PAGOINT': case 'IPMT': {
                $r = $this->n($a[0] ?? ['v' => 0]); $per = $this->n($a[1] ?? ['v' => 1]); $nper = $this->n($a[2] ?? ['v' => 0]); $vp = $this->n($a[3] ?? ['v' => 0]);
                $vf = isset($a[4]) ? $this->n($a[4]) : 0.0; $tipo = isset($a[5]) ? $this->n($a[5]) : 0.0;
                return ['v' => $this->pagoInteres($r, $per, $nper, $vp, $vf, $tipo)];
            }
            case 'PAGOPRIN': case 'PPMT': {
                $r = $this->n($a[0] ?? ['v' => 0]); $per = $this->n($a[1] ?? ['v' => 1]); $nper = $this->n($a[2] ?? ['v' => 0]); $vp = $this->n($a[3] ?? ['v' => 0]);
                $vf = isset($a[4]) ? $this->n($a[4]) : 0.0; $tipo = isset($a[5]) ? $this->n($a[5]) : 0.0;
                return ['v' => $this->pago($r, $nper, $vp, $vf, $tipo) - $this->pagoInteres($r, $per, $nper, $vp, $vf, $tipo)];
            }
            case 'TASA': case 'RATE':
                return ['v' => $this->tasa($this->n($a[0] ?? ['v' => 0]), $this->n($a[1] ?? ['v' => 0]), $this->n($a[2] ?? ['v' => 0]), isset($a[3]) ? $this->n($a[3]) : 0.0, isset($a[4]) ? $this->n($a[4]) : 0.0, isset($a[5]) ? $this->n($a[5]) : 0.1)];
            case 'NPER': {
                $r = $this->n($a[0] ?? ['v' => 0]); $pago = $this->n($a[1] ?? ['v' => 0]); $vp = $this->n($a[2] ?? ['v' => 0]);
                $vf = isset($a[3]) ? $this->n($a[3]) : 0.0; $tipo = isset($a[4]) ? $this->n($a[4]) : 0.0;
                if ($r == 0) { if ($pago == 0) $this->fallo('#DIV/0!'); return ['v' => -($vp + $vf) / $pago]; }
                $num = $pago * (1 + $r * $tipo) - $vf * $r;
                $den = $vp * $r + $pago * (1 + $r * $tipo);
                if ($num == 0 || $den == 0 || $num / $den <= 0) $this->fallo('#NUM!');
                return ['v' => log($num / $den) / log(1 + $r)];
            }
            case 'VNA': case 'NPV': {
                $r = $this->n($a[0] ?? ['v' => 0]);
                $vals = $this->lista(array_slice($a, 1));
                $tot = 0.0;
                foreach ($vals as $i => $v) $tot += $v / pow(1 + $r, $i + 1);
                return ['v' => $tot];
            }
            case 'TIR': case 'IRR': return ['v' => $this->tir($this->lista($a), isset($a[1]) ? $this->n($a[1]) : 0.1)];

            default: $this->fallo('#NOMBRE?');
        }
    }

    // ---------- Ayudantes de las funciones ----------

    /** Celdas y valores sueltos, aplanados */
    private function celdasDe(array $a): array
    {
        $out = [];
        foreach ($a as $x) {
            if (isset($x['rango'])) foreach ($x['rango'] as $c) $out[] = $c;
            else $out[] = $x;
        }
        return $out;
    }

    /** Texto de una celda resultado (vacía, texto o número) */
    private function textoDe(array $c): string
    {
        if (isset($c['e'])) return '';
        if (isset($c['s'])) return $c['s'];
        if (!empty($c['vacio'])) return '';
        return self::num($c['v'] ?? 0);
    }

    /** Metadatos de un rango: valores y coordenadas (0 = primera fila de datos, -1 = encabezado) */
    private function rangoDe(array $x): array
    {
        if (!isset($x['rango']) || !isset($x['r0'])) $this->fallo('#VALOR!');
        return ['vals' => $x['rango'], 'r0' => (int)$x['r0'], 'c0' => (int)$x['c0'], 'r1' => (int)$x['r1'], 'c1' => (int)$x['c1']];
    }

    private function leerRango(int $r0, int $c0, int $r1, int $c1): array
    {
        $out = [];
        for ($r = min($r0, $r1); $r <= max($r0, $r1); $r++) for ($c = min($c0, $c1); $c <= max($c0, $c1); $c++) $out[] = $this->celda($r, $c);
        $x = ['rango' => $out, 'r0' => min($r0, $r1), 'c0' => min($c0, $c1), 'r1' => max($r0, $r1), 'c1' => max($c0, $c1)];
        return $x;
    }

    private function letra(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }

    private function enteroNoNeg(array $x): int
    {
        $n = (int)$this->n($x);
        if ($n < 0) $this->fallo('#VALOR!');
        return $n;
    }

    /** Redondeo con la semántica de Excel (mitad fuera de cero), igual en PHP y JS */
    private function redondear(float $n, int $dec = 0, string $modo = 'normal'): float
    {
        $f = 10 ** $dec;
        if ($f == 0) return 0.0;
        $x = $n * $f;
        if ($modo === 'mas') $x = $x >= 0 ? ceil($x) : floor($x);
        elseif ($modo === 'menos') $x = $x >= 0 ? floor($x) : ceil($x);
        else $x = $x >= 0 ? floor($x + 0.5) : ceil($x - 0.5);
        return $x / $f;
    }

    private function sumaProducto(array $a): float
    {
        $grupos = [];
        foreach ($a as $x) {
            $g = [];
            foreach (isset($x['rango']) ? $x['rango'] : [$x] as $c) {
                if (isset($c['e'])) $this->fallo($c['e']);
                if (!empty($c['vacio'])) { $g[] = 0.0; continue; }
                $g[] = isset($c['v']) ? (float)$c['v'] : (float)(Sheetflow::numero($c['s'] ?? '') ?? 0.0);
            }
            $grupos[] = $g;
        }
        if (!$grupos) return 0.0;
        $n = max(array_map('count', $grupos));
        foreach ($grupos as $g) if (count($g) !== $n && count($g) !== 1) $this->fallo('#VALOR!');
        $tot = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $p = 1.0;
            foreach ($grupos as $g) $p *= $g[count($g) === 1 ? 0 : $i];
            $tot += $p;
        }
        return $tot;
    }

    private function moda(array $n): float
    {
        if (!$n) $this->fallo('#N/A');
        $cuenta = [];
        foreach ($n as $x) { $k = self::num($x); $cuenta[$k] = ($cuenta[$k] ?? 0) + 1; }
        arsort($cuenta);
        $clave = array_key_first($cuenta);
        if (($cuenta[$clave] ?? 0) < 2) $this->fallo('#N/A');
        return (float)$clave;
    }

    private function varianza(array $n, bool $muestra, bool $raiz): float
    {
        $k = count($n);
        if ($k === 0 || ($muestra && $k < 2)) $this->fallo('#DIV/0!');
        $media = array_sum($n) / $k;
        $suma = 0.0;
        foreach ($n as $x) $suma += ($x - $media) ** 2;
        $v = $suma / ($muestra ? $k - 1 : $k);
        return $raiz ? sqrt($v) : $v;
    }

    private function kEsimo(array $n, float $k, bool $mayor): float
    {
        $i = (int)$k;
        if ($i < 1 || $i > count($n)) $this->fallo('#NUM!');
        $mayor ? rsort($n) : sort($n);
        return (float)$n[$i - 1];
    }

    private function jerarquia(float $x, array $n, float $orden): float
    {
        if (!$n) $this->fallo('#N/A');
        $r = 0;
        foreach ($n as $v) {
            if ($orden == 0 ? $v > $x : $v < $x) $r++;
            elseif ($v == $x) $r += 0;
        }
        if (!in_array($x, $n, true)) $this->fallo('#N/A');
        return (float)($r + 1);
    }

    private function percentil(array $n, float $k): float
    {
        if (!$n) $this->fallo('#NUM!');
        if ($k < 0 || $k > 1) $this->fallo('#NUM!');
        sort($n);
        $pos = (count($n) - 1) * $k;
        $lo = (int)floor($pos);
        $hi = (int)ceil($pos);
        return $lo === $hi ? (float)$n[$lo] : $n[$lo] + ($n[$hi] - $n[$lo]) * ($pos - $lo);
    }

    /** Índices (0 = primero del rango) que cumplen todos los pares rango/criterio desde $desde */
    private function indicesCriterios(array $a, int $desde): array
    {
        $rangos = [];
        $crits = [];
        for ($i = $desde; isset($a[$i]) && isset($a[$i + 1]); $i += 2) {
            if (!isset($a[$i]['rango'])) $this->fallo('#VALOR!');
            $rangos[] = $a[$i]['rango'];
            $crits[] = $a[$i + 1];
        }
        if (!$rangos) $this->fallo('#VALOR!');
        $n = count($rangos[0]);
        $out = [];
        for ($k = 0; $k < $n; $k++) {
            $ok = true;
            foreach ($rangos as $j => $r) {
                $c = $r[$k] ?? ['v' => 0, 'vacio' => true];
                if (!$this->cumple($c, $crits[$j])) { $ok = false; break; }
            }
            if ($ok) $out[] = $k;
        }
        return $out;
    }

    private function cambiar(array $a): array
    {
        $x = $a[0] ?? ['v' => 0];
        $n = count($a);
        for ($i = 1; $i + 1 < $n; $i += 2) if ($this->igualesCeldas($x, $a[$i])) return $a[$i + 1];
        return $n > 1 && $n % 2 === 0 ? $a[$n - 1] : ['e' => '#N/A'];
    }

    private function elegir(array $a): array
    {
        $i = (int)$this->n($a[0] ?? ['v' => 0]);
        if ($i < 1 || $i >= count($a)) $this->fallo('#VALOR!');
        return $a[$i];
    }

    /** ¿Dos valores son iguales? (números por valor, texto sin distinguir mayúsculas) */
    private function igualesCeldas(array $a, array $b): bool
    {
        if (isset($a['e']) || isset($b['e'])) return false;
        $va = isset($a['v']) ? (float)$a['v'] : null;
        $vb = isset($b['v']) ? (float)$b['v'] : null;
        if ($va !== null && $vb !== null) return $va == $vb;
        return strcasecmp($this->textoDe($a), $this->textoDe($b)) === 0;
    }

    private function sustituir(string $t, string $viejo, string $nuevo, int $n): string
    {
        if ($viejo === '') return $t;
        if ($n <= 0) return str_replace($viejo, $nuevo, $t);
        $off = 0;
        $p = false;
        for ($i = 0; $i < $n; $i++) {
            $p = mb_strpos($t, $viejo, $off);
            if ($p === false) return $t;
            $off = $p + mb_strlen($viejo);
        }
        return mb_substr($t, 0, $p) . $nuevo . mb_substr($t, $p + mb_strlen($viejo));
    }

    private function unirCadenas(array $a): string
    {
        $d = $this->texto($a[0] ?? ['s' => '']);
        $ign = isset($a[1]) && $this->n($a[1]) != 0;
        $out = [];
        foreach (array_slice($a, 2) as $x) {
            foreach (isset($x['rango']) ? $x['rango'] : [$x] as $c) {
                if (isset($c['e'])) $this->fallo($c['e']);
                $t = $this->textoDe($c);
                if ($ign && $t === '') continue;
                $out[] = $t;
            }
        }
        return implode($d, $out);
    }

    private function fechaISO(array $x): ?string
    {
        $t = trim($this->texto($x));
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $t, $m)) return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})$/', $t, $m)) {
            $y = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
            return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
        }
        return null;
    }

    private function fechaParte(array $x, string $parte): int
    {
        $iso = $this->fechaISO($x);
        if ($iso === null) $this->fallo('#VALOR!');
        $ts = strtotime($iso . ' 12:00:00');
        return (int)date($parte, $ts);
    }

    private function diasEntre(?string $a, ?string $b): int
    {
        if (!$a || !$b) $this->fallo('#VALOR!');
        $x = new \DateTimeImmutable($a . 'T12:00:00Z');
        $y = new \DateTimeImmutable($b . 'T12:00:00Z');
        return (int)round(($y->getTimestamp() - $x->getTimestamp()) / 86400);
    }

    private function sumarMeses(?string $iso, int $n, bool $fin): string
    {
        if (!$iso) $this->fallo('#VALOR!');
        [$y, $m, $d] = array_map('intval', explode('-', $iso));
        $total = $y * 12 + ($m - 1) + $n;
        $ny = intdiv($total, 12);
        $nm = $total % 12 + 1;
        if ($nm < 1) { $nm += 12; $ny--; }
        $ultimo = (int)date('t', strtotime(sprintf('%04d-%02d-01 12:00:00', $ny, $nm)));
        $nd = $fin ? $ultimo : min($d, $ultimo);
        return sprintf('%04d-%02d-%02d', $ny, $nm, $nd);
    }

    private function siFecha(array $a): float
    {
        $i = $this->fechaISO($a[0] ?? []);
        $f = $this->fechaISO($a[1] ?? []);
        if (!$i || !$f) $this->fallo('#VALOR!');
        $u = strtoupper(trim($this->texto($a[2] ?? ['s' => 'd'])));
        [$y1, $m1, $d1] = array_map('intval', explode('-', $i));
        [$y2, $m2, $d2] = array_map('intval', explode('-', $f));
        if ($f < $i) $this->fallo('#NUM!');
        switch ($u) {
            case 'D': return (float)$this->diasEntre($i, $f);
            case 'Y': return (float)($y2 - $y1 - (($m2 < $m1 || ($m2 === $m1 && $d2 < $d1)) ? 1 : 0));
            case 'M': return (float)(($y2 - $y1) * 12 + ($m2 - $m1) - ($d2 < $d1 ? 1 : 0));
            case 'YM': $m = ($m2 - $m1 + 12) % 12; if ($d2 < $d1) $m = ($m + 11) % 12; return (float)$m;
            case 'MD':
                if ($d2 >= $d1) return (float)($d2 - $d1);
                $pm = $m2 - 1; $py = $y2;
                if ($pm < 1) { $pm = 12; $py--; }
                $diasMes = (int)date('t', strtotime(sprintf('%04d-%02d-01 12:00:00', $py, $pm)));
                return (float)($diasMes - $d1 + $d2);
            case 'YD':
                $a1 = sprintf('2000-%02d-%02d', $m1, $d1);
                $a2 = sprintf('2000-%02d-%02d', $m2, $d2);
                return (float)$this->diasEntre($a1, $a2);
            default: $this->fallo('#NUM!');
        }
    }

    private function numeroSemana(?string $iso, int $tipo): int
    {
        if (!$iso) $this->fallo('#VALOR!');
        $w = (int)date('w', strtotime($iso . ' 12:00:00'));
        if ($tipo === 2) return $w === 0 ? 7 : $w;
        if ($tipo === 3) return $w === 0 ? 6 : $w - 1;
        return $w + 1;
    }

    private function diasLaborables(array $a): string
    {
        $iso = $this->fechaISO($a[0] ?? []);
        if (!$iso) $this->fallo('#VALOR!');
        $n = (int)$this->n($a[1] ?? ['v' => 0]);
        $feriados = [];
        $h = $a[2] ?? null;
        if ($h && isset($h['rango'])) foreach ($h['rango'] as $c) { $f = $this->fechaISO($c); if ($f) $feriados[$f] = true; }
        $ts = strtotime($iso . ' 12:00:00');
        $paso = $n >= 0 ? 1 : -1;
        $faltan = abs($n);
        while ($faltan > 0) {
            $ts = strtotime(($paso > 0 ? '+' : '-') . '1 day', $ts);
            $w = (int)date('N', $ts);
            if ($w >= 6 || isset($feriados[date('Y-m-d', $ts)])) continue;
            $faltan--;
        }
        return date('Y-m-d', $ts);
    }

    private function valorComparable(array $c)
    {
        if (isset($c['e'])) return null;
        if (isset($c['v']) && !isset($c['s'])) return (float)$c['v'];
        return $this->textoDe($c);
    }

    /** Compara una celda con la clave: 0 iguales, <0 celda menor, >0 celda mayor */
    private function comparar(array $c, array $clave): int
    {
        $a = $this->valorComparable($c);
        $b = $this->valorComparable($clave);
        if ($a === null || $b === null) return 1;
        if (is_float($a) && is_float($b)) return $a <=> $b;
        return strcasecmp((string)$a, (string)$b);
    }

    private function filaBusqueda(array $r, array $clave, bool $aprox, bool $vertical): int
    {
        $width = $r['c1'] - $r['c0'] + 1;
        $height = $r['r1'] - $r['r0'] + 1;
        $n = $vertical ? $height : $width;
        $paso = $vertical ? $width : 1;
        $idx = -1;
        for ($i = 0; $i < $n; $i++) {
            $c = $r['vals'][$i * $paso] ?? null;
            if (!$c) continue;
            $cmp = $this->comparar($c, $clave);
            if ($cmp === 0) return $i;
            if ($aprox && $cmp < 0) $idx = $i;
        }
        return $aprox ? $idx : -1;
    }

    private function buscarColumna(array $a): array
    {
        $r = $this->rangoDe($a[1] ?? []);
        $col = (int)$this->n($a[2] ?? ['v' => 0]);
        $width = $r['c1'] - $r['c0'] + 1;
        if ($col < 1 || $col > $width) $this->fallo('#REF!');
        $aprox = !isset($a[3]) || $this->n($a[3]) != 0;
        $fila = $this->filaBusqueda($r, $a[0] ?? ['s' => ''], $aprox, true);
        if ($fila < 0) $this->fallo('#N/A');
        return $r['vals'][$fila * $width + ($col - 1)] ?? ['v' => 0, 'vacio' => true];
    }

    private function buscarFila(array $a): array
    {
        $r = $this->rangoDe($a[1] ?? []);
        $fila = (int)$this->n($a[2] ?? ['v' => 0]);
        $width = $r['c1'] - $r['c0'] + 1;
        $height = $r['r1'] - $r['r0'] + 1;
        if ($fila < 1 || $fila > $height) $this->fallo('#REF!');
        $aprox = !isset($a[3]) || $this->n($a[3]) != 0;
        $col = $this->filaBusqueda($r, $a[0] ?? ['s' => ''], $aprox, false);
        if ($col < 0) $this->fallo('#N/A');
        return $r['vals'][($fila - 1) * $width + $col] ?? ['v' => 0, 'vacio' => true];
    }

    private function coincidir(array $a): int
    {
        $r = $this->rangoDe($a[1] ?? []);
        $tipo = isset($a[2]) ? (int)$this->n($a[2]) : 1;
        $vals = $r['vals'];
        $n = count($vals);
        $clave = $a[0] ?? ['s' => ''];
        if ($tipo === 0) {
            for ($i = 0; $i < $n; $i++) if ($this->comparar($vals[$i], $clave) === 0) return $i + 1;
            $this->fallo('#N/A');
        }
        $idx = -1;
        for ($i = 0; $i < $n; $i++) {
            $cmp = $this->comparar($vals[$i], $clave);
            if ($tipo > 0) { if ($cmp <= 0) $idx = $i; } elseif ($cmp >= 0) $idx = $i;
        }
        if ($idx < 0) $this->fallo('#N/A');
        return $idx + 1;
    }

    private function indice(array $a): array
    {
        $r = $this->rangoDe($a[0] ?? []);
        $width = $r['c1'] - $r['c0'] + 1;
        $height = $r['r1'] - $r['r0'] + 1;
        $fila = isset($a[1]) ? (int)$this->n($a[1]) : 0;
        $col = isset($a[2]) ? (int)$this->n($a[2]) : null;
        if ($col === null && $height === 1) { $col = $fila; $fila = 1; }
        if ($col === null) $col = 1;
        if ($fila < 1 || $col < 1 || $fila > $height || $col > $width) $this->fallo('#REF!');
        return $r['vals'][($fila - 1) * $width + ($col - 1)];
    }

    private function buscarX(array $a): array
    {
        $r1 = $this->rangoDe($a[1] ?? []);
        $r2 = $this->rangoDe($a[2] ?? []);
        $clave = $a[0] ?? ['s' => ''];
        $modo = isset($a[4]) ? (int)$this->n($a[4]) : 0;
        $n = count($r1['vals']);
        $idx = -1;
        for ($i = 0; $i < $n; $i++) {
            $cmp = $this->comparar($r1['vals'][$i], $clave);
            if ($cmp === 0) { $idx = $i; break; }
            if ($modo === -1 && $cmp < 0) $idx = $i;
            if ($modo === 1 && $cmp > 0) { $idx = $i; break; }
        }
        if ($idx < 0) return $a[3] ?? ['e' => '#N/A'];
        return $r2['vals'][$idx] ?? ($a[3] ?? ['e' => '#N/A']);
    }

    private function desref(array $a): array
    {
        $base = $a[0] ?? [];
        $r0 = $base['ref']['r'] ?? $base['r0'] ?? null;
        $c0 = $base['ref']['c'] ?? $base['c0'] ?? null;
        if ($r0 === null || $c0 === null) $this->fallo('#VALOR!');
        $r1 = $base['ref']['r'] ?? $base['r1'] ?? $r0;
        $c1 = $base['ref']['c'] ?? $base['c1'] ?? $c0;
        $dr = (int)$this->n($a[1] ?? ['v' => 0]);
        $dc = (int)$this->n($a[2] ?? ['v' => 0]);
        $alto = isset($a[3]) ? max(1, (int)$this->n($a[3])) : ($r1 - $r0 + 1);
        $ancho = isset($a[4]) ? max(1, (int)$this->n($a[4])) : ($c1 - $c0 + 1);
        return $this->leerRango($r0 + $dr, $c0 + $dc, $r0 + $dr + $alto - 1, $c0 + $dc + $ancho - 1);
    }

    private function indirecto(array $a): array
    {
        $t = trim($this->texto($a[0] ?? ['s' => '']));
        if (!preg_match('/^\$?([A-Za-z]{1,2})\$?(\d+)(?::\$?([A-Za-z]{1,2})\$?(\d+))?$/', $t, $m)) $this->fallo('#REF!');
        $c0 = $this->letraAIndice($m[1]);
        $r0 = (int)$m[2] - 2;
        if (!empty($m[3])) {
            $c1 = $this->letraAIndice($m[3]);
            $r1 = (int)$m[4] - 2;
            return $this->leerRango($r0, $c0, $r1, $c1);
        }
        $x = $this->celda($r0, $c0);
        $x['ref'] = ['r' => $r0, 'c' => $c0];
        return $x;
    }

    // ---------- Fórmulas del proyecto ----------

    /** Busca una partida o actividad por código (o por nombre) en los datos del programa */
    private static function delProyecto(string $clave): ?array
    {
        if (!self::$proyecto) return null;
        $c = mb_strtolower(trim($clave));
        foreach (self::$proyecto as $f) if (mb_strtolower((string)$f['codigo']) === $c) return $f;
        foreach (self::$proyecto as $f) if (mb_strtolower((string)$f['nombre']) === $c) return $f;
        foreach (self::$proyecto as $f) if ($c !== '' && mb_stripos((string)$f['nombre'], $c) !== false) return $f;
        return null;
    }

    /** AVANCE("1.2.3"), INICIO(…), FIN(…), DIAS.REST(…), PARTIDA(…) y TIPO(…) */
    private function funcionProyecto(string $f, array $a): array
    {
        $clave = trim($this->texto($a[0] ?? ['s' => '']));
        $d = $clave !== '' ? self::delProyecto($clave) : null;
        if ($d === null) {
            // Sin el programa cargado (al guardar, al importar…): vale el resultado que calculó el cliente
            $k = ($this->actual['r'] ?? 0) . ',' . ($this->actual['c'] ?? 0);
            $v = $this->cache[$k] ?? null;
            if ($v === null || $v === '') return ['e' => '#N/A'];
            $n = Sheetflow::numero($v);
            if (in_array($f, ['AVANCE', 'DIAS.REST'], true) && $n !== null) return ['v' => $n];
            return ['s' => (string)$v];
        }
        return match ($f) {
            'AVANCE' => ['v' => (float)$d['avance']],
            'DIAS.REST' => ['v' => (float)$d['dias_rest']],
            'INICIO' => ['s' => (string)$d['inicio']],
            'FIN' => ['s' => (string)$d['fin']],
            'TIPO' => ['s' => (string)$d['tipo']],
            default => ['s' => (string)$d['nombre']],   // PARTIDA / NOMBRE.PARTIDA
        };
    }

    // ---------- Finanzas ----------

    private function pago(float $r, float $n, float $vp, float $vf = 0.0, float $tipo = 0.0): float
    {
        if ($n == 0) $this->fallo('#DIV/0!');
        if ($r == 0) return -($vp + $vf) / $n;
        $p = pow(1 + $r, $n);
        if ($p == 0 || ($p - 1) == 0) $this->fallo('#NUM!');
        return -($r * ($vp * $p + $vf)) / ((1 + $r * $tipo) * ($p - 1));
    }

    private function valorFuturo(float $r, float $n, float $pago, float $vp = 0.0, float $tipo = 0.0): float
    {
        if ($r == 0) return -($vp + $pago * $n);
        $p = pow(1 + $r, $n);
        return -($vp * $p + $pago * (1 + $r * $tipo) * ($p - 1) / $r);
    }

    private function valorActual(float $r, float $n, float $pago, float $vf = 0.0, float $tipo = 0.0): float
    {
        if ($r == 0) return -($vf + $pago * $n);
        $p = pow(1 + $r, $n);
        return -($vf + $pago * (1 + $r * $tipo) * ($p - 1) / $r) / $p;
    }

    private function pagoInteres(float $r, float $per, float $n, float $vp, float $vf = 0.0, float $tipo = 0.0): float
    {
        if ($per < 1 || $per > $n) $this->fallo('#NUM!');
        if ($r == 0) return 0.0;
        $p = pow(1 + $r, $per - 1);
        $saldo = $vp * $p + $this->pago($r, $n, $vp, $vf, $tipo) * (1 + $r * $tipo) * ($p - 1) / $r;
        return -$saldo * $r;
    }

    private function tasa(float $nper, float $pago, float $vp, float $vf = 0.0, float $tipo = 0.0, float $guess = 0.1): float
    {
        $r = $guess > 0 ? $guess : 0.1;
        for ($i = 0; $i < 100; $i++) {
            $f = $this->valorFuturo($r, $nper, $pago, $vp, $tipo) - $vf;
            if (abs($f) < 1e-9) return $r;
            $h = 1e-6;
            $d = ($this->valorFuturo($r + $h, $nper, $pago, $vp, $tipo) - $vf - $f) / $h;
            if ($d == 0 || !is_finite($d)) break;
            $nr = $r - $f / $d;
            if ($nr <= -1) $nr = ($r - 1) / 2;
            if (abs($nr - $r) < 1e-10) return $nr;
            $r = $nr;
        }
        $this->fallo('#NUM!');
    }

    private function tir(array $vals, float $guess = 0.1): float
    {
        if (!$vals) $this->fallo('#NUM!');
        $f = function (float $r) use ($vals): float {
            $t = 0.0;
            foreach ($vals as $i => $v) $t += $v / pow(1 + $r, $i);
            return $t;
        };
        $lo = -0.9999;
        $hi = 10.0;
        $flo = $f($lo);
        $fhi = $f($hi);
        if ($flo * $fhi > 0) {
            // amplía el rango antes de rendirse
            $hi = 1000.0;
            $fhi = $f($hi);
            if ($flo * $fhi > 0) $this->fallo('#NUM!');
        }
        for ($i = 0; $i < 200; $i++) {
            $mid = ($lo + $hi) / 2;
            $fm = $f($mid);
            if (abs($fm) < 1e-9) return $mid;
            if ($flo * $fm <= 0) { $hi = $mid; $fhi = $fm; }
            else { $lo = $mid; $flo = $fm; }
        }
        return ($lo + $hi) / 2;
    }

    /** Convierte un valor a número para VALOR / NUMERO.VALOR */
    private function valorNumerico(array $x, bool $permisivo): float
    {
        if (isset($x['e'])) $this->fallo($x['e']);
        if (isset($x['v']) && !isset($x['s'])) return (float)$x['v'];
        $t = trim($this->textoDe($x));
        if ($t === '') return 0.0;
        $pct = str_ends_with($t, '%');
        $t = str_replace([' ', '%', '$', '€'], '', $t);
        if (str_contains($t, ',') && !str_contains($t, '.')) $t = str_replace(',', '.', $t);
        $n = Sheetflow::numero($t);
        if ($n === null) $this->fallo('#VALOR!');
        if ($pct && $permisivo) $n /= 100;
        return $n;
    }

    /** TEXTO(valor; formato) para los patrones habituales de Excel */
    private function textoFormato(array $x, string $fmt): string
    {
        $fmt = trim($fmt);
        if ($fmt === '') return $this->textoDe($x);
        $iso = $this->fechaISO($x);
        if ($iso !== null && preg_match('/(aaaa|yyyy|yy|mmmm|mmm|mm|dd|m|d)/i', $fmt)) {
            $ts = strtotime($iso . ' 12:00:00');
            $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $mes = $meses[(int)date('n', $ts) - 1];
            return preg_replace_callback('/aaaa|yyyy|yy|mmmm|mmm|mm|dd|m|d/', function ($m) use ($ts, $mes) {
                return match (strtolower($m[0])) {
                    'aaaa', 'yyyy' => date('Y', $ts),
                    'yy' => date('y', $ts),
                    'mmmm' => $mes,
                    'mmm' => mb_substr($mes, 0, 3),
                    'mm' => date('m', $ts),
                    'dd' => date('d', $ts),
                    'm' => (string)(int)date('n', $ts),
                    default => (string)(int)date('j', $ts),
                };
            }, $fmt);
        }
        $num = null;
        if (isset($x['v']) && !isset($x['s'])) $num = (float)$x['v'];
        else { $num = Sheetflow::numero($this->textoDe($x)); if ($num === null) return $this->textoDe($x); }
        preg_match_all('/[.,][0#]+/', $fmt, $mm);
        $dec = 0;
        if (!empty($mm[0])) {
            $ult = end($mm[0]);
            if ($ult[0] === '.' || (count($mm[0]) === 1 && preg_match('/^[.,]0+$/', $ult) && !str_contains($fmt, '.'))) $dec = strlen($ult) - 1;
        }
        $miles = str_contains($fmt, ',') && (str_contains($fmt, '.') || preg_match('/^[^,]*#,##/', $fmt));
        $pct = strpos($fmt, '%') !== false;
        if ($pct) $num *= 100;
        $pref = '';
        if (preg_match('/^(.*?)[0#]/', $fmt, $m)) $pref = trim(str_replace('"', '', $m[1]));
        $s = number_format($num, $dec, '.', $miles ? ',' : '');
        return $pref . $s . ($pct ? '%' : '');
    }
}
