<?php

namespace Classes\Herramientas;

use Classes\Dependencias;
use Classes\Informes;
use Classes\Salud;
use Model\Proyecto;

/**
 * Predicción de atrasos (Fase 5.4): puntúa de 0 a 100 qué tan probable es que cada actividad pendiente (que empieza o termina en el horizonte
 * elegido) se atrase, sumando señales verificables —avance por debajo de lo esperado, inicio vencido, predecesoras atrasadas, restricciones activas,
 * material sin submittal aprobado, RFI abiertos, punch grave, permisos que faltan— y estima cuántos días se pasaría si sigue al ritmo actual. También
 * proyecta la fecha de fin de la obra con el índice de desempeño del programa (SPI). Todo es estadística simple y se explica; la IA solo redacta el
 * resumen y las acciones. Es una estimación, no una promesa.
 */
class PrediccionAtrasos extends Base
{
    private const PESOS = ['atraso' => 35, 'inicio' => 25, 'predecesora' => 20, 'restriccion' => 12, 'riesgo' => 10];
    private const NIVELES = ['alto' => 60, 'medio' => 30];

    public static function id(): string { return 'prediccion_atrasos'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Predicción de atrasos', 'grupo' => 'planeacion', 'tipo' => 'prediccion_atrasos', 'aplicable' => false,
            'descripcion' => 'Calcula qué actividades pendientes tienen más probabilidad de atrasarse (avance, inicio vencido, predecesoras, restricciones, material, RFI, punch, permisos), cuántos días se pasarían al ritmo actual y la fecha de fin probable de la obra. Cada puntaje muestra sus razones. La IA redacta el resumen y las acciones.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [['nombre' => 'dias', 'etiqueta' => 'Horizonte (días hacia adelante)', 'tipo' => 'numero', 'defecto' => 30, 'min' => 7, 'max' => 120]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Predicción de atrasos · ' . date('d/m/Y');
    }

    private static function dias(string $a, string $b): int
    {
        return (int)round((strtotime($b) - strtotime($a)) / 86400);
    }

    /** Avance esperado (0-100) de una actividad hoy según sus fechas programadas, lineal */
    public static function esperado(string $ini, string $fin, string $hoy): float
    {
        if ($hoy < $ini) return 0.0;
        if ($hoy >= $fin) return 100.0;
        return round((self::dias($ini, $hoy) + 1) / (self::dias($ini, $fin) + 1) * 100, 1);
    }

    /**
     * Puntúa una actividad. Devuelve ['puntos', 'nivel', 'razones'=>[…], 'atraso_dias' (probable), 'fin_probable'] o null si no aplica.
     * @param array $a fila de Dependencias::actividades; $predsAtrasadas códigos de predecesoras vencidas; $restr n.º de restricciones activas; $riesgos de Salud
     */
    public static function puntuar(array $a, string $hoy, array $predsAtrasadas, int $restr, array $riesgos): ?array
    {
        if ((int)$a['hito'] || (int)$a['progreso'] >= 100 || !$a['fecha_inicio'] || !$a['fecha_fin']) return null;
        $prog = (int)$a['progreso'];
        $pts = 0.0; $razones = [];
        $esperado = self::esperado($a['fecha_inicio'], $a['fecha_fin'], $hoy);
        $brecha = $esperado - $prog;
        $atrasoDias = 0;
        $finProbable = $a['fecha_fin'];

        if ($a['fecha_inicio'] <= $hoy) {
            $transc = self::dias($a['fecha_inicio'], $hoy) + 1;
            if ($prog === 0 && $hoy > $a['fecha_inicio']) {
                $dias = self::dias($a['fecha_inicio'], $hoy);
                $pts += self::PESOS['inicio'] + min(10, $dias);
                $razones[] = "No ha iniciado y debía empezar hace $dias día(s)";
                $finProbable = date('Y-m-d', strtotime($a['fecha_fin'] . " +$dias days"));   // si arranca hoy, termina tantos días después como se retrasó el inicio
            } elseif ($brecha > 10) {
                $pts += min(self::PESOS['atraso'], $brecha * 0.6);
                $razones[] = 'Avance ' . $prog . ' % contra ' . round($esperado) . ' % esperado';
                $ritmo = $prog / $transc;   // % por día
                $faltan = $ritmo > 0 ? (100 - $prog) / $ritmo : 365;
                $finProbable = date('Y-m-d', strtotime("$hoy +" . (int)min(365, ceil($faltan)) . ' days'));
            }
            if ($finProbable > $a['fecha_fin']) $atrasoDias = self::dias($a['fecha_fin'], $finProbable);
            elseif ($a['fecha_fin'] < $hoy) { $atrasoDias = self::dias($a['fecha_fin'], $hoy); $finProbable = $hoy; }
            if ($a['fecha_fin'] < $hoy) { $pts += 10; $razones[] = 'Su fecha de fin ya pasó (' . self::dias($a['fecha_fin'], $hoy) . ' día(s))'; }
        }
        if ($predsAtrasadas) { $pts += self::PESOS['predecesora']; $razones[] = 'Predecesora(s) atrasada(s): ' . implode(', ', array_slice($predsAtrasadas, 0, 3)); }
        if ($restr > 0) { $pts += min(2 * self::PESOS['restriccion'], $restr * self::PESOS['restriccion']); $razones[] = $restr . ' restricción(es) activa(s)'; }
        foreach ($riesgos as $r) { $pts += self::PESOS['riesgo']; $razones[] = $r['texto']; }

        $pts = (int)min(100, round($pts));
        if ($pts <= 0) return null;
        $nivel = $pts >= self::NIVELES['alto'] ? 'alto' : ($pts >= self::NIVELES['medio'] ? 'medio' : 'bajo');
        return ['puntos' => $pts, 'nivel' => $nivel, 'razones' => $razones, 'atraso_dias' => $atrasoDias, 'fin_probable' => $finProbable];
    }

    /** Avance programado y real del proyecto hoy (ponderado por duración) y el SPI */
    public static function desempeno(array $acts, string $hoy): array
    {
        $dur = 0.0; $plan = 0.0; $real = 0.0;
        foreach ($acts as $a) {
            if ((int)$a['hito'] || !$a['fecha_inicio'] || !$a['fecha_fin']) continue;
            $d = max(1, (int)$a['duracion_dias']);
            $dur += $d;
            $plan += $d * self::esperado($a['fecha_inicio'], $a['fecha_fin'], $hoy) / 100;
            $real += $d * (int)$a['progreso'] / 100;
        }
        $spi = $plan > 0 ? round($real / $plan, 2) : null;
        return ['plan' => $dur ? round($plan / $dur * 100, 1) : 0, 'real' => $dur ? round($real / $dur * 100, 1) : 0, 'spi' => $spi];
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $tope = date('Y-m-d', strtotime("$hoy +" . (int)$params['dias'] . ' days'));
        $acts = Dependencias::actividades($pid);
        $preds = [];
        foreach (Dependencias::relaciones($pid) as $r) $preds[$r['suc']][] = $r['pred'];
        $restr = [];
        foreach (self::filas("SELECT r.actividad_id, COUNT(*) n FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND r.estado = 'activa' GROUP BY r.actividad_id") as $f) $restr[(int)$f['actividad_id']] = (int)$f['n'];
        $riesgos = Salud::riesgosActividades($pid);

        $filas = [];
        foreach ($acts as $id => $a) {
            if ($a['fecha_inicio'] > $tope && $a['fecha_fin'] > $tope) continue;   // fuera del horizonte
            $atr = [];
            foreach ($preds[$id] ?? [] as $pr) if (isset($acts[$pr]) && (int)$acts[$pr]['progreso'] < 100 && $acts[$pr]['fecha_fin'] < $hoy) $atr[] = $acts[$pr]['codigo'];
            $r = self::puntuar($a, $hoy, $atr, $restr[$id] ?? 0, $riesgos[$id] ?? []);
            if (!$r) continue;
            $filas[] = ['id' => $id, 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'partida' => $a['partida'], 'progreso' => (int)$a['progreso'], 'fin' => $a['fecha_fin']] + $r;
        }
        usort($filas, fn($x, $y) => [$y['puntos'], $y['atraso_dias']] <=> [$x['puntos'], $x['atraso_dias']]);

        $d = self::desempeno($acts, $hoy);
        $fin = $p->fecha_fin;
        $finProb = $fin;
        if ($d['spi'] !== null && $d['spi'] > 0 && $p->fecha_inicio) {
            $total = self::dias($p->fecha_inicio, $fin);
            $restan = max(0, self::dias($hoy, $fin));
            $finProb = date('Y-m-d', strtotime("$hoy +" . (int)round(min($restan / max(0.3, min(1.5, $d['spi'])), $total * 2)) . ' days'));
            if ($finProb < $fin && $d['spi'] >= 1) $finProb = $fin;   // adelantarse no se promete
        }
        $datos = ['actividades' => $filas, 'desempeno' => $d, 'fin_programado' => $fin, 'fin_probable' => $finProb, 'atraso_obra' => max(0, self::dias($fin, $finProb)), 'horizonte' => (int)$params['dias'],
            'conteo' => ['alto' => count(array_filter($filas, fn($f) => $f['nivel'] === 'alto')), 'medio' => count(array_filter($filas, fn($f) => $f['nivel'] === 'medio')), 'bajo' => count(array_filter($filas, fn($f) => $f['nivel'] === 'bajo'))]];

        $top = array_slice($filas, 0, 10);
        $lin = array_map(fn($f) => "{$f['codigo']} {$f['nombre']} (puntaje {$f['puntos']}, {$f['progreso']} %, atraso probable {$f['atraso_dias']} d): " . implode('; ', array_slice($f['razones'], 0, 3)), $top);
        $prompt = "Eres planeador de obra y explicas a la dirección dónde es más probable que haya atrasos en las próximas semanas. Usa SOLO los datos; no inventes causas ni cifras. Las cifras son estimaciones, dilo.\n\n"
            . "Proyecto: {$p->nombre} · Hoy: " . self::fmt($hoy) . "\nAvance programado hoy: {$d['plan']} % · avance real: {$d['real']} % · SPI: " . ($d['spi'] ?? 'sin dato') . "\nFin programado: " . self::fmt($fin) . " · fin probable al ritmo actual: " . self::fmt($finProb) . " (" . $datos['atraso_obra'] . " día(s) de diferencia)\n"
            . "Actividades con riesgo alto: {$datos['conteo']['alto']}, medio: {$datos['conteo']['medio']}\n\n## LAS DE MAYOR RIESGO\n" . ($lin ? implode("\n", $lin) : '(ninguna)')
            . "\n\nDevuelve SOLO un JSON: {\"resumen\":\"1 o 2 párrafos: situación y dónde está el riesgo principal\",\"acciones\":[\"3 a 5 acciones concretas para evitar los atrasos más probables\"]}";
        return ['datos' => $datos, 'lotes' => $filas || $d['spi'] !== null ? [['titulo' => 'Explicando los riesgos de atraso', 'prompt' => $prompt]] : []];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $d = $datos['desempeno'];
        $c = $datos['conteo'];
        $resumen = Informes::limpiarTexto(self::dato($respuestas, 'resumen') ?? '', 2500);
        $ia = $resumen !== '';
        if (!$ia) {
            $resumen = ($c['alto'] + $c['medio'])
                ? "Hay {$c['alto']} actividad(es) con riesgo alto y {$c['medio']} con riesgo medio en los próximos {$datos['horizonte']} días. " . ($d['spi'] !== null ? "El proyecto lleva {$d['real']} % de avance contra {$d['plan']} % programado (SPI {$d['spi']})." : '')
                : 'Ninguna actividad del horizonte elegido muestra señales fuertes de atraso.';
        }
        $acc = self::textos(self::dato($respuestas, 'acciones'), 6, 240);
        $etq = ['alto' => '🔴 Alto', 'medio' => '🟡 Medio', 'bajo' => '🟢 Bajo'];
        $secs = [
            Informes::kpis('Proyección', [
                ['Avance real / programado', $d['real'] . ' % / ' . $d['plan'] . ' %'],
                ['SPI (desempeño del programa)', $d['spi'] === null ? '—' : (string)$d['spi'], $d['spi'] !== null && $d['spi'] < 1 ? 'por debajo de 1 = más lento que lo programado' : ''],
                ['Fin programado', self::fmt($datos['fin_programado'])],
                ['Fin probable al ritmo actual', self::fmt($datos['fin_probable']), $datos['atraso_obra'] ? $datos['atraso_obra'] . ' día(s) de diferencia' : 'sin diferencia'],
                ['Riesgo alto / medio', $c['alto'] . ' / ' . $c['medio']],
            ]),
            Informes::texto('Lectura' . ($ia ? ' (redactado con IA, revísalo)' : ' (automático)'), $resumen),
        ];
        if ($acc) $secs[] = Informes::lista('Acciones sugeridas', $acc);
        $secs[] = Informes::tabla('Actividades por riesgo de atraso (las 40 de mayor puntaje)', ['Código', 'Actividad', 'Riesgo', 'Puntaje', 'Avance', 'Atraso probable', 'Razones'],
            array_map(fn($f) => [$f['codigo'], self::corto($f['nombre'], 60), $etq[$f['nivel']], $f['puntos'], $f['progreso'] . ' %', $f['atraso_dias'] ? $f['atraso_dias'] . ' d' : '—', implode(' · ', $f['razones'])], array_slice($datos['actividades'], 0, 40)));
        $secs[] = Informes::texto('Cómo se calcula', 'El puntaje (0 a 100) suma señales verificables: avance por debajo de lo esperado según las fechas, inicio vencido, predecesoras atrasadas, restricciones activas, material sin submittal aprobado, RFI abiertos, pendientes graves del punch y permisos que faltan. 60 o más es riesgo alto; 30 a 59, medio. El atraso probable proyecta el ritmo de avance actual de cada actividad; el fin de la obra usa el SPI (avance real entre avance programado). Son estimaciones para decidir dónde mirar primero, no promesas.');
        return ['secciones' => $secs];
    }
}
