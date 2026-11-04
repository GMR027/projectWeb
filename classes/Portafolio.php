<?php

namespace Classes;

use Model\Proyecto;
use Model\Usuario;

/**
 * Tablero de portafolio: compara las obras visibles para el usuario y avisa cuando un indicador
 * empeora por tendencia (no solo cuando ya está mal). Lee lo que ya calculan Curva S, Costos,
 * Salud y el propio programa; no modifica datos de las obras.
 */
class Portafolio
{
    use AccesoDb;

    public const ORDENES = ['alerta' => 'Más alertas', 'ppc' => 'PPC', 'cpi' => 'CPI', 'spi' => 'SPI', 'avance' => 'Avance real', 'nombre' => 'Nombre'];
    private const NIVEL_PESO = ['ok' => 0, 'aviso' => 1, 'critico' => 2];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM portafolio_alertas LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function consulta(string $sql): array
    {
        try {
            $r = self::db()->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array[] filas del tablero */
    public static function filas(array $usuario, array $filtros = []): array
    {
        $filas = [];
        foreach (Proyecto::visiblesPara($usuario) as $p) {
            if (!empty($filtros['cliente']) && (int)$p->usuario_id !== (int)$filtros['cliente']) continue;
            $filas[] = self::metricas($p);
        }
        $q = mb_strtolower(trim((string)($filtros['q'] ?? '')));
        if ($q !== '') {
            $filas = array_values(array_filter($filas, fn($f) => mb_strpos(mb_strtolower($f['nombre'] . ' ' . $f['codigo'] . ' ' . $f['cliente']), $q) !== false));
        }
        if (!empty($filtros['alertas'])) {
            $filas = array_values(array_filter($filas, fn($f) => !empty($f['alertas'])));
        }
        $orden = array_key_exists($filtros['orden'] ?? '', self::ORDENES) ? $filtros['orden'] : 'alerta';
        $dir = ($filtros['dir'] ?? 'desc') === 'asc' ? 1 : -1;
        usort($filas, function ($a, $b) use ($orden, $dir) {
            if ($orden === 'nombre') return $dir * strcmp($a['nombre'], $b['nombre']);
            if ($orden === 'alerta') {
                $cmp = [count($b['alertas']), self::NIVEL_PESO[$b['salud']] ?? 0] <=> [count($a['alertas']), self::NIVEL_PESO[$a['salud']] ?? 0];
                return $cmp !== 0 ? $cmp : strcmp($a['nombre'], $b['nombre']);
            }
            $va = $a[$orden] ?? null;
            $vb = $b[$orden] ?? null;
            if ($va === null && $vb === null) return strcmp($a['nombre'], $b['nombre']);
            if ($va === null) return 1;
            if ($vb === null) return -1;
            return $dir * (($va <=> $vb) ?: strcmp($a['nombre'], $b['nombre']));
        });
        return $filas;
    }

    /** Indicadores de una obra. Cualquier fuente que falte (tabla sin migrar) devuelve el dato en null. */
    public static function metricas(Proyecto $p): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $cs = ['resumen' => [], 'periodos' => []];
        try { $cs = CurvaS::calcular($p); } catch (\Throwable $e) {}
        $plan = isset($cs['resumen']['plan']) ? (float)$cs['resumen']['plan'] : null;
        $real = isset($cs['resumen']['real']) ? (float)$cs['resumen']['real'] : null;
        $spi = isset($cs['resumen']['spi']) ? (float)$cs['resumen']['spi'] : null;
        [$spiAnt, $spiDelta] = self::tendencia($cs['periodos'] ?? [], 'plan', 'real', $spi);

        $cpi = $cpiAnt = $cpiDelta = null;
        if (Costos::disponible()) {
            try {
                $t = Costos::resumen($p)['total'];
                if ((float)$t['bac'] > 0) $cpi = $t['cpi'];
                $curvaC = Costos::curva($p, (float)$t['bac']);
                if ($curvaC) [$cpiAnt, $cpiDelta] = self::tendenciaCosto($curvaC, $cpi);
            } catch (\Throwable $e) {}
        }

        [$ppc, $ppcAnt, $ppcDelta] = self::ppc($pid);

        $c = self::consulta("SELECT
            (SELECT COUNT(*) FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.hito = 0 AND a.progreso < 100 AND a.fecha_fin < '$hoy') atras,
            (SELECT COUNT(*) FROM punch_items WHERE proyecto_id = $pid AND estado IN ('abierto','en_proceso') AND severidad IN ('alta','critica')) punch,
            (SELECT COUNT(*) FROM punch_items WHERE proyecto_id = $pid AND estado IN ('abierto','en_proceso') AND severidad = 'critica') punch_crit
        ")[0] ?? [];

        $salud = 'ok';
        try {
            $sal = Salud::semaforo($p);
            $salud = $sal['nivel'] ?? 'ok';
        } catch (\Throwable $e) {}

        $alertas = self::alertasDe($pid, $spi, $spiAnt, $spiDelta, $cpi, $cpiAnt, $cpiDelta, $ppc, $ppcAnt, $ppcDelta, $salud);
        $cliente = '';
        $dueno = Usuario::find((int)$p->usuario_id);
        if ($dueno) $cliente = $dueno->nombre;

        return [
            'id' => $pid, 'codigo' => (string)$p->codigo, 'nombre' => (string)$p->nombre, 'cliente' => $cliente,
            'empresa' => (string)$p->empresa, 'color' => $p->color ?: '#00b4d8', 'inicio' => $p->fecha_inicio, 'fin' => $p->fecha_fin,
            'plan' => $plan, 'real' => $real, 'spi' => $spi, 'spi_antes' => $spiAnt,
            'cpi' => $cpi, 'cpi_antes' => $cpiAnt, 'ppc' => $ppc, 'ppc_antes' => $ppcAnt,
            'atrasadas' => (int)($c['atras'] ?? 0), 'punch' => (int)($c['punch'] ?? 0), 'punch_critico' => (int)($c['punch_crit'] ?? 0),
            'salud' => $salud, 'alertas' => $alertas,
        ];
    }

    private static function alertasDe(int $pid, ?float $spi, ?float $spiAnt, ?float $spiDelta, ?float $cpi, ?float $cpiAnt, ?float $cpiDelta, ?int $ppc, ?int $ppcAnt, ?int $ppcDelta, string $salud): array
    {
        $a = [];
        if ($spi !== null && $spi < 0.95 && $spiDelta !== null && $spiDelta <= -0.05) {
            $a[] = ['clave' => 'spi', 'nivel' => 'critico', 'mensaje' => 'El avance se frenó: SPI ' . number_format($spi, 2) . ' (hace 4 semanas ' . number_format((float)$spiAnt, 2) . ').'];
        }
        if ($cpi !== null && $cpi < 1 && $cpiDelta !== null && $cpiDelta <= -0.05) {
            $a[] = ['clave' => 'cpi', 'nivel' => 'critico', 'mensaje' => 'El costo se deterioró: CPI ' . number_format($cpi, 2) . ' (hace 4 semanas ' . number_format((float)$cpiAnt, 2) . ').'];
        }
        if ($ppc !== null && $ppc < 70 && $ppcDelta !== null && $ppcDelta <= -10) {
            $a[] = ['clave' => 'ppc', 'nivel' => 'aviso', 'mensaje' => 'Cumplimiento en caída: PPC ' . $ppc . ' % (semanas anteriores ' . $ppcAnt . ' %).'];
        }
        if ($salud === 'critico') $a[] = ['clave' => 'salud', 'nivel' => 'critico', 'mensaje' => 'La salud de la obra está en rojo: hay puntos urgentes abiertos.'];
        return $a;
    }

    /** Tendencia de SPI: valor actual y hace 4 semanas a partir de la Curva S */
    private static function tendencia(array $periodos, string $clavePlan, string $claveReal, ?float $actual): array
    {
        $idx = null;
        foreach ($periodos as $i => $per) {
            if (($per['inicio'] ?? '') <= date('Y-m-d') && ($per[$claveReal] ?? null) !== null) $idx = $i;
        }
        if ($idx === null || $actual === null) return [null, null];
        $ant = $idx >= 4 ? self::spiPeriodo($periodos[$idx - 4], $clavePlan, $claveReal) : null;
        return [$ant, $ant === null ? null : round($actual - $ant, 2)];
    }

    private static function spiPeriodo(array $per, string $clavePlan, string $claveReal): ?float
    {
        $plan = (float)($per[$clavePlan] ?? 0);
        $real = $per[$claveReal] ?? null;
        return $plan > 0 && $real !== null ? round((float)$real / $plan, 2) : null;
    }

    /** Tendencia del CPI a partir de la curva de costos semanal */
    private static function tendenciaCosto(array $curva, ?float $actual): array
    {
        $vals = [];
        foreach ($curva as $c) {
            if (($c['ac'] ?? null) !== null && (float)$c['ac'] > 0 && ($c['ev'] ?? null) !== null) $vals[] = round((float)$c['ev'] / (float)$c['ac'], 2);
        }
        if (count($vals) < 2 || $actual === null) return [null, null];
        $ant = count($vals) >= 5 ? $vals[count($vals) - 5] : null;
        return [$ant, $ant === null ? null : round($actual - $ant, 2)];
    }

    /**
     * PPC aproximado con las últimas 4 semanas completas (lunes a domingo):
     * de las actividades que debían terminar, cuántas están al 100 % (o con fin real dentro de la semana).
     * @return array{0:?int,1:?int,2:?int} [actual, promedio anterior, diferencia]
     */
    private static function ppc(int $pid): array
    {
        $hoy = new \DateTime('today');
        $dow = (int)$hoy->format('N');
        $domingo = (clone $hoy)->modify('-' . $dow . ' days');
        $semanas = [];
        for ($k = 3; $k >= 0; $k--) {
            $fin = (clone $domingo)->modify('-' . (7 * $k) . ' days')->format('Y-m-d');
            $ini = date('Y-m-d', strtotime($fin . ' -6 days'));
            $semanas[] = [$ini, $fin, 0, 0];
        }
        $desde = $semanas[0][0];
        $hasta = $semanas[3][1];
        $acts = self::consulta("SELECT a.fecha_fin, a.progreso, a.fecha_fin_real FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE p.proyecto_id = $pid AND a.hito = 0 AND a.fecha_fin BETWEEN '$desde' AND '$hasta'");
        foreach ($acts as $a) {
            foreach ($semanas as &$s) {
                if ($a['fecha_fin'] < $s[0] || $a['fecha_fin'] > $s[1]) continue;
                $s[2]++;
                $cumple = (int)$a['progreso'] >= 100 && (!$a['fecha_fin_real'] || $a['fecha_fin_real'] <= $s[1]);
                if ($cumple) $s[3]++;
                break;
            }
            unset($s);
        }
        $vals = [];
        foreach ($semanas as $s) if ($s[2] > 0) $vals[] = (int)round($s[3] / $s[2] * 100);
        if (!$vals) return [null, null, null];
        $actual = array_pop($vals);
        $ant = $vals ? (int)round(array_sum($vals) / count($vals)) : null;
        return [$actual, $ant, $ant === null ? null : $actual - $ant];
    }

    /** Guarda las alertas ya calculadas (una fila = una obra) para que la campana las lea sin recalcular */
    public static function guardarAlertas(array $filas): int
    {
        if (!self::disponible()) return 0;
        $db = self::db();
        $n = 0;
        foreach ($filas as $f) {
            $pid = (int)$f['id'];
            $db->query("DELETE FROM portafolio_alertas WHERE proyecto_id = $pid");
            foreach ($f['alertas'] as $a) {
                $db->query("INSERT INTO portafolio_alertas (proyecto_id, clave, nivel, mensaje) VALUES ($pid, '" . $db->real_escape_string($a['clave']) . "', '" . $db->real_escape_string($a['nivel']) . "', '" . $db->real_escape_string($a['mensaje']) . "')");
                $n++;
            }
        }
        return $n;
    }

    /** Recalcula las alertas de todas las obras del usuario (para el script programado) */
    public static function refrescar(array $usuario): int
    {
        return self::guardarAlertas(self::filas($usuario));
    }

    /** Alertas recientes para la campana de notificaciones (solo las obras que el usuario puede ver) */
    public static function alertasRecientes(array $usuario, int $limite = 4): array
    {
        if (!self::disponible()) return [];
        $uid = (int)($usuario['id'] ?? 0);
        $filtro = $usuario['rol'] === 'admin'
            ? '1=1'
            : "p.usuario_id = $uid OR p.id IN (SELECT proyecto_id FROM proyecto_accesos WHERE usuario_id = $uid)";
        return self::consulta("SELECT a.*, p.nombre proyecto, p.codigo FROM portafolio_alertas a JOIN proyectos p ON p.id = a.proyecto_id
            WHERE ($filtro) AND a.actualizado >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY FIELD(a.nivel, 'critico', 'aviso'), a.actualizado DESC LIMIT " . max(1, min(20, $limite)));
    }

    public static function resumen(array $filas): array
    {
        $n = count($filas);
        $alerta = count(array_filter($filas, fn($f) => !empty($f['alertas'])));
        $prom = function (string $k) use ($filas) {
            $v = array_values(array_filter(array_column($filas, $k), fn($x) => $x !== null));
            return $v ? round(array_sum($v) / count($v), 2) : null;
        };
        return ['total' => $n, 'con_alertas' => $alerta, 'ppc' => $prom('ppc'), 'cpi' => $prom('cpi'), 'spi' => $prom('spi'), 'real' => $prom('real')];
    }
}
