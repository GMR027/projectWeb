<?php

namespace Classes\Herramientas;

use Classes\Cambios;
use Classes\CruceMateriales;
use Classes\CurvaS;
use Classes\Dependencias;
use Classes\Informes;
use Classes\LineasBase;
use Classes\Punch;
use Model\LineaBase;
use Model\Proyecto;

/**
 * Resumen ejecutivo diario: avance contra el programa, desviación contra la línea base, cambios del día, retiros pendientes,
 * materiales contra cronograma, desviaciones sin orden de cambio, y —además— actividades en riesgo alto y en ruta crítica,
 * restricciones activas, contradicciones entre documentos, punch list abierto y causas de atraso. Toda cifra la calcula el
 * servidor; la IA solo explica y sugiere. El usuario elige tipo de reporte (simple / detallado) y nivel de detalle
 * (básico / medio / alto / máximo) que controla cuántas filas lleva cada bloque.
 */
class ResumenEjecutivoDiario extends Base
{
    public const TIPOS = ['simple' => 'Simple (una página)', 'detallado' => 'Detallado (todo)'];
    public const NIVELES = ['basico' => 'Básico', 'medio' => 'Medio', 'alto' => 'Alto', 'maximo' => 'Máximo'];
    private const TOPE = ['basico' => 5, 'medio' => 10, 'alto' => 20, 'maximo' => 40];
    /** Horizonte de la predicción de atrasos dentro de este informe (días) */
    private const HORIZONTE = 14;

    public static function id(): string { return 'resumen_diario'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Resumen ejecutivo diario', 'grupo' => 'control', 'tipo' => 'resumen_diario', 'aplicable' => false,
            'descripcion' => 'El estado del día en una página: avance contra el programa, desviación contra la línea base, cambios del día, riesgo de atraso y ruta crítica, restricciones activas, contradicciones, punch list, causas de atraso, retiros y materiales. La IA explica por qué se movió la fecha final y sugiere acciones; las cifras las calcula el sistema. Elige tipo de reporte y nivel de detalle.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [
                ['nombre' => 'fecha', 'etiqueta' => 'Día del resumen', 'tipo' => 'fecha', 'defecto' => date('Y-m-d')],
                ['nombre' => 'tipo', 'etiqueta' => 'Tipo de reporte', 'tipo' => 'opcion', 'defecto' => 'detallado', 'opciones' => self::TIPOS],
                ['nombre' => 'detalle', 'etiqueta' => 'Nivel de detalle', 'tipo' => 'opcion', 'defecto' => 'medio', 'opciones' => self::NIVELES],
                ['nombre' => 'linea', 'etiqueta' => 'Id de la línea base (0 = la más reciente)', 'tipo' => 'numero', 'defecto' => 0, 'min' => 0, 'max' => 1000000],
            ]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Resumen ejecutivo ' . date('d/m/Y', strtotime($params['fecha']));
    }

    /** Cuántas filas lleva cada bloque según el nivel de detalle elegido (5 / 10 / 20 / 40) */
    public static function tope(string $nivel): int
    {
        return self::TOPE[$nivel] ?? self::TOPE['medio'];
    }

    /** CRITICO | ATENCION | EN ORDEN según los datos calculados */
    public static function semaforo(array $d): string
    {
        $spi = $d['curva']['spi'] ?? null;
        $c = $d['comparacion']['resumen'] ?? null;
        if (($spi !== null && $spi < 0.85) || ($c && $c['d_fin'] > 10) || $d['cruce']['tarde'] > 0 || $d['retiros_vencidos'] > 0) return 'CRITICO';
        if (($spi !== null && $spi < 0.95) || ($c && ($c['d_fin'] > 0 || $c['sin_justificar'] > 0)) || $d['cruce']['en_riesgo'] > 0 || $d['retiros_sin_programar'] > 0) return 'ATENCION';
        return 'EN ORDEN';
    }

    /** Último informe de contradicciones confirmado (≤ 7 días); null si no hay. No se vuelve a analizar cada día. */
    public static function ultimasContradicciones(Proyecto $p): ?array
    {
        $pid = (int)$p->id;
        $f = self::filas("SELECT titulo, creado, contenido_json FROM informes_ia WHERE proyecto_id = $pid AND tipo = 'contradicciones' AND estado <> 'descartado' AND creado >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY id DESC LIMIT 1")[0] ?? null;
        if (!$f) return null;
        $contenido = json_decode((string)$f['contenido_json'], true) ?: [];
        foreach (($contenido['secciones'] ?? []) as $s) {
            if (($s['tipo'] ?? '') !== 'tabla') continue;
            $col = $s['columnas'] ?? [];
            if (!in_array('Tema', $col, true) || !in_array('Explicación', $col, true)) continue;   // la tabla de confirmadas
            if (empty($s['filas'])) continue;
            return ['titulo' => (string)$f['titulo'], 'creado' => (string)$f['creado'], 'columnas' => $col, 'filas' => $s['filas']];
        }
        return null;
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $fecha = $params['fecha'];
        $tipo = $params['tipo'];
        $nivel = $params['detalle'];
        $tope = self::tope($nivel);

        // ---- cifras que ya calculaba este informe ----
        $curva = CurvaS::calcular($p)['resumen'] ?? [];
        $lineas = LineaBase::deProyecto($pid);
        $lb = null;
        foreach ($lineas as $l) if ((int)$params['linea'] === (int)$l->id) $lb = $l;
        $lb ??= $lineas[0] ?? null;
        $cmp = $lb ? LineasBase::comparar($p, $lb) : null;
        $cruce = CruceMateriales::cruzar($p);
        $retiros = Cambios::retirosPendientes($pid);
        $dia = Cambios::delDia($pid, $fecha);
        $resCambios = Cambios::resumen($pid);
        $sinOc = $cmp ? array_values(array_filter($cmp['filas'], fn($f) => $f['causa'] === 'sin_justificar' && $f['estado'] !== 'igual')) : [];
        $riesgosMat = array_values(array_filter($cruce['filas'], fn($f) => in_array($f['estado'], ['tarde', 'compra_vencida', 'sin_fecha', 'submittal'], true)));
        $pend = self::filas("SELECT numero, titulo, impacto_costo, impacto_dias FROM ordenes_cambio WHERE proyecto_id = $pid AND estado IN ('borrador','solicitada') ORDER BY id LIMIT 8");

        // ---- bloques nuevos (todo determinista, sin IA) ----
        $ritmo = PrediccionAtrasos::riesgos($p, self::HORIZONTE);
        $alto = array_values(array_filter($ritmo['filas'], fn($f) => $f['nivel'] === 'alto'));
        $dep = Dependencias::datos($p);
        $criticas = array_values(array_filter($dep['actividades'] ?? [], fn($f) => !$f['hito'] && $f['progreso'] < 100 && $f['critica']));
        usort($criticas, fn($x, $y) => ($x['inicio'] ?: '') <=> ($y['inicio'] ?: ''));
        $restricciones = self::restriccionesActivas($pid);
        $contradicciones = self::ultimasContradicciones($p);
        $punch = Punch::listar($pid, ['estado' => 'pendientes']);
        $causas = CausasRetraso::analizar($p, 1);

        $datos = ['fecha' => $fecha, 'tipo' => $tipo, 'detalle' => $nivel, 'tope' => $tope,
            'curva' => $curva, 'linea' => $lb ? ['id' => (int)$lb->id, 'nombre' => $lb->nombre, 'creado' => substr((string)$lb->creado, 0, 10)] : null,
            'comparacion' => $cmp ? ['resumen' => array_diff_key($cmp['resumen'], ['linea' => 1]), 'movidas' => array_slice(array_values(array_filter($cmp['filas'], fn($f) => $f['estado'] !== 'igual')), 0, 40)] : null,
            'cruce' => $cruce['resumen'], 'riesgos_materiales' => array_slice($riesgosMat, 0, $tope), 'retiros' => $retiros, 'retiros_vencidos' => count(array_filter($retiros, fn($r) => $r['vencido'])),
            'retiros_sin_programar' => count(array_filter($retiros, fn($r) => $r['sin_programar'])), 'dia' => $dia, 'cambios' => $resCambios,
            'sin_oc' => array_slice($sinOc, 0, $tope), 'ordenes_por_decidir' => $pend,
            'riesgo_alto' => array_slice($alto, 0, $tope), 'riesgo_conteo' => $ritmo['conteo'], 'riesgo_horizonte' => self::HORIZONTE,
            'criticas' => array_slice($criticas, 0, $tope), 'criticas_total' => count($criticas), 'dependencias_modo' => $dep['modo'] ?? 'apagado',
            'restricciones' => array_slice($restricciones, 0, $tope), 'restricciones_total' => count($restricciones),
            'contradicciones' => $contradicciones, 'punch' => array_slice($punch, 0, $tope), 'punch_total' => count($punch),
            'punch_criticos' => count(array_filter($punch, fn($x) => $x['severidad'] === 'critica')),
            'punch_vencidos' => count(array_filter($punch, fn($x) => !empty($x['vencido']))),
            'causas' => ['por_causa' => $causas['por_causa'], 'total' => $causas['total'],
                'filas' => array_slice($causas['actividades'], 0, $tope)]];
        $datos['semaforo'] = self::semaforo($datos);

        $r = $cmp['resumen'] ?? null;
        $prompt = "Eres el gerente de proyecto de una obra y escribes el resumen ejecutivo DIARIO para la dirección. Usa SOLO los datos de abajo, sin inventar cifras ni causas; si algo no está en los datos, no lo afirmes. Sé breve y concreto.\n\n"
            . "Proyecto: {$p->nombre} · Día: " . self::fmt($fecha) . " · Semáforo: {$datos['semaforo']} · Tipo: " . (self::TIPOS[$tipo] ?? $tipo) . " · Detalle: " . (self::NIVELES[$nivel] ?? $nivel) . "\n"
            . 'Avance programado ' . (isset($curva['plan']) ? $curva['plan'] . '%' : 's/d') . ' · real ' . (isset($curva['real']) ? $curva['real'] . '%' : 's/d') . (($curva['spi'] ?? null) !== null ? " · SPI {$curva['spi']}" : '') . "\n"
            . ($r ? "Línea base «{$lb->nombre}»: fin {$r['fin_base']} → {$r['fin_actual']} ({$r['d_fin']} días hábiles); actividades movidas {$r['movida']}, nuevas {$r['nueva']}, eliminadas {$r['eliminada']}; justificadas por orden de cambio {$r['oc']}, por material en riesgo {$r['material']}, SIN justificar {$r['sin_justificar']}.\n"
                . 'Ruta crítica: entran ' . (implode(', ', array_map(fn($f) => $f['codigo'] . ' ' . self::corto($f['nombre'], 40), $r['criticas_entran'])) ?: 'ninguna') . '; salen ' . (implode(', ', array_map(fn($f) => $f['codigo'], $r['criticas_salen'])) ?: 'ninguna') . ".\n" : "Sin línea base guardada.\n")
            . self::bloquePrompt('ACTIVIDADES EN RIESGO ALTO (próximos ' . self::HORIZONTE . ' días)', $tope, $alto,
                fn($f) => "{$f['codigo']} " . self::corto($f['nombre'], 45) . " — puntaje {$f['puntos']}, avance {$f['progreso']} %" . ($f['atraso_dias'] ? ", atraso probable {$f['atraso_dias']} d" : '') . ($f['razones'] ? ' · ' . self::corto(implode('; ', $f['razones']), 120) : ''))
            . self::bloquePrompt('EN RUTA CRÍTICA (holgura cero, aún sin terminar)', $tope, $criticas,
                fn($f) => "{$f['codigo']} " . self::corto($f['nombre'], 45) . ' — ' . self::fmt($f['inicio']) . ' → ' . self::fmt($f['fin']) . " · {$f['progreso']} %")
            . self::bloquePrompt('RESTRICCIONES ACTIVAS', $tope, $restricciones,
                fn($x) => "{$x['codigo']} " . self::corto($x['actividad'], 40) . ' — ' . $x['tipo'] . ': ' . self::corto($x['descripcion'], 70) . ($x['dias'] !== null ? " ({$x['dias']} día(s))" : ''))
            . "\n## CONTRADICCIONES ENTRE DOCUMENTOS\n" . ($contradicciones
                ? 'Último análisis: ' . self::fmt($contradicciones['creado']) . ' · ' . count($contradicciones['filas']) . ' confirmada(s):' . "\n"
                    . implode("\n", array_map(fn($f) => '- ' . self::corto((string)($f[0] ?? ''), 30) . ' · ' . self::corto((string)($f[2] ?? ''), 60) . ' da ' . self::corto((string)($f[3] ?? ''), 40) . ($f[4] ? ' [' . $f[4] . ']' : ''), array_slice($contradicciones['filas'], 0, $tope)))
                : '(sin análisis reciente)')
            . self::bloquePrompt('PUNCH LIST ABIERTO', $tope, $punch,
                fn($x) => self::corto((string)$x['titulo'], 60) . ' — ' . strtoupper($x['severidad']) . ($x['vencido'] ? ', VENCIDO' : '') . ($x['responsable'] ? ' · ' . self::corto($x['responsable'], 40) : ''))
            . "\n## CAUSAS DE RETRASO\n" . ($causas['total']
                ? $causas['total'] . ' actividad(es) atrasada(s) · ' . implode(', ', array_map(fn($k, $v) => CausasRetraso::CAUSAS[$k] . " $v", array_keys(array_slice(array_filter($causas['por_causa']), 0, 5, true)), array_slice(array_filter($causas['por_causa']), 0, 5, true)))
                    . "\n" . implode("\n", array_map(fn($x) => "- {$x['codigo']} " . self::corto($x['nombre'], 45) . " (atraso {$x['atraso']} d, {$x['progreso']} %): " . self::corto(implode('; ', array_map(fn($c) => CausasRetraso::CAUSAS[$c[0]] . ' – ' . $c[1], $x['causas'])), 180), array_slice($causas['actividades'], 0, $tope)))
                : '(ninguna)')
            . "\n\n## ACTIVIDADES MOVIDAS (máx. $tope)\n" . ($cmp ? implode("\n", array_map(fn($f) => "{$f['codigo']} " . self::corto($f['nombre'], 50) . " [{$f['estado']}] fin {$f['d_fin']} d" . ($f['causa'] === 'oc' ? " · orden {$f['detalle']}" : ($f['causa'] === 'material' ? ' · material en riesgo' : ' · sin justificar')), array_slice($datos['comparacion']['movidas'], 0, $tope))) : '(sin datos)')
            . "\n\n## MATERIALES EN RIESGO\n" . ($riesgosMat ? implode("\n", array_map(fn($f) => self::corto($f['material'], 50) . ' → ' . self::corto($f['actividad'], 40) . ': ' . CruceMateriales::ESTADOS[$f['estado']][0] . ($f['dias'] ? " ({$f['dias']} d)" : ''), array_slice($riesgosMat, 0, $tope))) : '(ninguno)')
            . "\n\n## RETIROS PENDIENTES\n" . ($retiros ? implode("\n", array_map(fn($x) => "{$x['numero']}: " . self::corto($x['descripcion'], 60) . " (instalado hace {$x['dias_instalado']} d" . ($x['sin_programar'] ? ', retiro sin programar' : '') . ')', array_slice($retiros, 0, $tope))) : '(ninguno)')
            . "\n\n## CAMBIOS DEL DÍA\nÓrdenes con movimiento: " . count($dia['ordenes']) . ' · renglones con movimiento: ' . count($dia['renglones']) . "\n"
            . "\nDevuelve SOLO un JSON: {\"analisis\":\"1 o 2 párrafos: por qué se movió (o no) la fecha final, qué pesa más hoy y dónde está el riesgo principal\",\"sugerencias\":[\"2 a 4 acciones concretas\"],\"pendientes\":[\"decisiones o acciones del administrador para hoy\"]}";
        return ['datos' => $datos, 'lotes' => [['titulo' => 'Redactando el resumen ejecutivo diario', 'prompt' => $prompt]]];
    }

    /** Restricciones activas con su tipo, actividad y días de antigüedad (null si no trae fecha) */
    private static function restriccionesActivas(int $pid): array
    {
        $hoy = date('Y-m-d');
        $out = [];
        foreach (self::filas("SELECT r.descripcion, r.fecha, t.nombre AS tipo, a.codigo, a.nombre AS actividad, a.fecha_inicio
            FROM restricciones r JOIN tipos_restriccion t ON t.id = r.tipo_id
            JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id
            WHERE pa.proyecto_id = $pid AND r.estado = 'activa' ORDER BY a.fecha_inicio, a.codigo, r.fecha") as $f) {
            $out[] = ['codigo' => $f['codigo'], 'actividad' => $f['actividad'], 'tipo' => $f['tipo'],
                'descripcion' => $f['descripcion'], 'fecha' => $f['fecha'], 'inicio' => $f['fecha_inicio'],
                'dias' => $f['fecha'] && $f['fecha'] <= $hoy ? (int)floor((strtotime($hoy) - strtotime($f['fecha'])) / 86400) : null];   // fecha futura = sin antigüedad aún
        }
        return $out;
    }

    /** Encabezado de sección del prompt con lista limitada al tope del nivel de detalle */
    private static function bloquePrompt(string $titulo, int $tope, array $filas, callable $linea): string
    {
        return "\n## $titulo\n" . ($filas ? implode("\n", array_map($linea, array_slice($filas, 0, $tope))) : '(ninguno)');
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $c = $datos['curva'];
        $cmp = $datos['comparacion']['resumen'] ?? null;
        $sem = $datos['semaforo'];
        $tope = (int)($datos['tope'] ?? self::tope((string)($datos['detalle'] ?? 'medio')));
        $detallado = ($datos['tipo'] ?? 'detallado') === 'detallado';
        $analisis = Informes::limpiarTexto(self::dato($respuestas, 'analisis') ?? '', 2500);
        $ia = $analisis !== '';
        if (!$ia) {
            $analisis = $cmp
                ? 'Contra la línea base «' . $datos['linea']['nombre'] . '» la fecha de término pasó del ' . self::fmt($cmp['fin_base']) . ' al ' . self::fmt($cmp['fin_actual']) . ' (' . ($cmp['d_fin'] >= 0 ? '+' : '') . $cmp['d_fin'] . ' días hábiles). '
                    . "Hay {$cmp['movida']} actividad(es) movida(s), {$cmp['nueva']} nueva(s) y {$cmp['eliminada']} eliminada(s): {$cmp['oc']} con orden de cambio, {$cmp['material']} por material en riesgo y {$cmp['sin_justificar']} sin justificar."
                : 'Aún no hay una línea base guardada; guarda una en «Líneas base» para comparar el programa a lo largo del tiempo.';
        }
        $sug = self::textos(self::dato($respuestas, 'sugerencias'), 5, 260);
        $pendIa = self::textos(self::dato($respuestas, 'pendientes'), 6, 200);
        $pendSis = self::pendientesSistema($datos);

        $secs = [
            Informes::kpis('Estado del día: ' . $sem, array_merge([
                ['Avance programado', isset($c['plan']) ? $c['plan'] . '%' : 's/d'], ['Avance real', isset($c['real']) ? $c['real'] . '%' : 's/d'], ['SPI', ($c['spi'] ?? null) !== null ? $c['spi'] : 's/d', 'real ÷ programado'],
                ['Fin vs línea base', $cmp ? ($cmp['d_fin'] >= 0 ? '+' : '') . $cmp['d_fin'] . ' d' : 's/d', 'días hábiles'], ['Sin justificar', $cmp ? $cmp['sin_justificar'] : 's/d', 'desviaciones sin orden'],
                ['Retiros pendientes', count($datos['retiros']), $datos['retiros_vencidos'] . ' vencido(s)'], ['Materiales en riesgo', $datos['cruce']['en_riesgo'], 'de ' . $datos['cruce']['total']],
                ['Costo autorizado', '$' . number_format($datos['cambios']['costo_autorizado'], 2), $datos['cambios']['dias_autorizados'] . ' días en órdenes'],
            ], self::kpisNuevos($datos))),
            Informes::texto('Análisis', $analisis),
        ];
        $secs = array_merge($secs, self::seccionesRiesgo($datos, $tope, $detallado));
        if ($detallado) {
            if ($cmp && ($cmp['criticas_entran'] || $cmp['criticas_salen'])) {
                $secs[] = Informes::lista('Cambios en la ruta crítica', array_merge(
                    array_map(fn($f) => 'Entra: ' . $f['codigo'] . ' ' . self::corto($f['nombre'], 70), $cmp['criticas_entran']),
                    array_map(fn($f) => 'Sale: ' . $f['codigo'] . ' ' . self::corto($f['nombre'], 70), $cmp['criticas_salen'])));
            }
            $secs[] = Informes::tabla('Cambios del día', ['Orden', 'Qué pasó', 'Estado'], array_merge(
                array_map(fn($o) => [$o['numero'], ($o['nueva'] ? 'Nueva: ' : 'Actualizada: ') . self::corto($o['titulo'], 70) . ($o['impacto_dias'] ? " · {$o['impacto_dias']} d" : '') . ((float)$o['impacto_costo'] ? ' · $' . number_format((float)$o['impacto_costo'], 2) : ''), \Model\OrdenCambio::ESTADOS[$o['estado']] ?? $o['estado']], $datos['dia']['ordenes']),
                array_map(fn($r) => [$r['numero'], \Model\CambioPartida::TIPOS[$r['tipo']] . ': ' . self::corto($r['descripcion'], 70), \Model\CambioPartida::ESTADOS[$r['estado']] ?? $r['estado']], $datos['dia']['renglones'])));
            $secs[] = Informes::tabla('Retiros pendientes', ['Orden', 'Instalado', 'Días', 'Retiro previsto'], array_map(fn($r) => [$r['numero'], self::corto($r['descripcion'], 70), $r['dias_instalado'], $r['sin_programar'] ? 'SIN PROGRAMAR' : self::fmt($r['fecha_retiro_prevista']) . ($r['vencido'] ? ' (vencido)' : '')], array_slice($datos['retiros'], 0, $tope)));
            $secs[] = Informes::tabla('Materiales contra cronograma (en riesgo)', ['Material', 'Para', 'Se necesita', 'Entrega', 'Situación'], array_map(fn($m) => [self::corto($m['material'], 50), self::corto(($m['ref'] ? $m['ref'] . ' ' : '') . $m['actividad'], 50), self::fmt($m['necesidad']), self::fmt($m['entrega']), CruceMateriales::ESTADOS[$m['estado']][0] . ($m['dias'] ? " ({$m['dias']} d)" : '')], $datos['riesgos_materiales']));
            $secs[] = Informes::tabla('Desviaciones sin orden de cambio', ['Actividad', 'Estado', 'Δ inicio (d)', 'Δ fin (d)'], array_map(fn($f) => [$f['codigo'] . ' ' . self::corto($f['nombre'], 60), $f['estado'], $f['d_inicio'], $f['d_fin']], $datos['sin_oc']));
        }
        if ($sug) $secs[] = Informes::lista('Sugerencias', $sug);
        $secs[] = Informes::lista('Pendientes de hoy', array_values(array_unique(array_merge($pendIa, $pendSis))));
        return ['secciones' => $secs];
    }

    /** KPIs de los bloques nuevos */
    private static function kpisNuevos(array $datos): array
    {
        $k = [];
        if (isset($datos['riesgo_alto'])) $k[] = ['Riesgo alto', count($datos['riesgo_alto']), 'de ' . $datos['riesgo_conteo']['alto'] . ' en ' . $datos['riesgo_horizonte'] . ' d'];
        if (isset($datos['criticas'])) $k[] = ['Ruta crítica', $datos['criticas_total'], 'actividades sin terminar'];
        if (isset($datos['restricciones'])) $k[] = ['Restricciones activas', $datos['restricciones_total']];
        if (isset($datos['punch'])) $k[] = ['Punch abierto', $datos['punch_total'], $datos['punch_criticos'] . ' crítico(s) · ' . $datos['punch_vencidos'] . ' vencido(s)'];
        if (isset($datos['contradicciones'])) $k[] = ['Contradicciones', $datos['contradicciones'] ? count($datos['contradicciones']['filas']) : 0, $datos['contradicciones'] ? 'análisis del ' . self::fmt($datos['contradicciones']['creado']) : 'sin análisis reciente'];
        if (isset($datos['causas'])) $k[] = ['Atrasadas por causa', $datos['causas']['total'], 'actividades con atraso'];
        return $k;
    }

    /**
     * Los seis bloques nuevos. En «simple» salen como lista de una línea; en «detallado», como tabla con todas las columnas.
     * Ambos respetan el tope del nivel de detalle.
     */
    private static function seccionesRiesgo(array $datos, int $tope, bool $detallado): array
    {
        $secs = [];
        $alto = $datos['riesgo_alto'] ?? [];
        if ($alto) {
            $secs[] = $detallado
                ? Informes::tabla('Actividades en riesgo alto', ['Código', 'Actividad', 'Puntaje', 'Atraso probable', 'Razones'],
                    array_map(fn($f) => [$f['codigo'], self::corto($f['nombre'], 60), $f['puntos'], $f['atraso_dias'] ? $f['atraso_dias'] . ' d' : '—', self::corto(implode('; ', $f['razones']), 160)], $alto))
                : Informes::lista('Actividades en riesgo alto', array_map(fn($f) => "{$f['codigo']} " . self::corto($f['nombre'], 60) . " (puntaje {$f['puntos']}" . ($f['atraso_dias'] ? ", +{$f['atraso_dias']} d" : '') . ')', $alto));
        } else {
            $secs[] = Informes::texto('Actividades en riesgo alto', 'Ninguna de las próximas ' . ($datos['riesgo_horizonte'] ?? 14) . ' días pasó de 60 puntos de riesgo'
                . (!empty($datos['riesgo_conteo']) ? ' (medio riesgo: ' . $datos['riesgo_conteo']['medio'] . ', bajo: ' . $datos['riesgo_conteo']['bajo'] . ')' : '') . '.');
        }
        $crit = $datos['criticas'] ?? [];
        $notaCrit = ($datos['dependencias_modo'] ?? 'apagado') === 'apagado' ? ' (dependencias apagadas)' : '';
        if ($crit) {
            $secs[] = $detallado
                ? Informes::tabla('En ruta crítica' . $notaCrit, ['Código', 'Actividad', 'Inicio', 'Fin', 'Avance'], array_map(fn($f) => [$f['codigo'], self::corto($f['nombre'], 60), self::fmt($f['inicio']), self::fmt($f['fin']), $f['progreso'] . ' %'], $crit))
                : Informes::lista('En ruta crítica' . $notaCrit, array_map(fn($f) => "{$f['codigo']} " . self::corto($f['nombre'], 60) . ' · ' . self::fmt($f['fin']) . " · {$f['progreso']} %", $crit));
        } else {
            $secs[] = Informes::texto('En ruta crítica' . $notaCrit, ($notaCrit ? 'Con las dependencias apagadas no se calcula ruta crítica: actívalas en la pantalla Dependencias para ver qué actividades gobiernan la fecha de fin.' : 'No hay actividades en la ruta crítica sin terminar.'));
        }
        $restr = $datos['restricciones'] ?? [];
        if (array_key_exists('restricciones', $datos)) {
            $secs[] = $restr
                ? ($detallado
                    ? Informes::tabla('Restricciones activas (' . $datos['restricciones_total'] . ')', ['Actividad', 'Tipo', 'Descripción', 'Desde', 'Días'],
                        array_map(fn($x) => [$x['codigo'] . ' ' . self::corto($x['actividad'], 45), $x['tipo'], self::corto($x['descripcion'], 90), self::fmt($x['fecha']), $x['dias'] ?? '—'], $restr))
                    : Informes::lista('Restricciones activas (' . $datos['restricciones_total'] . ')', array_map(fn($x) => "{$x['codigo']} " . self::corto($x['actividad'], 45) . ' — ' . $x['tipo'] . ': ' . self::corto($x['descripcion'], 70), $restr)))
                : Informes::texto('Restricciones activas', 'Ninguna. No hay restricciones sin resolver en las actividades del proyecto.');
        }
        $con = $datos['contradicciones'] ?? null;
        if ($con !== null) {
            $secs[] = Informes::tabla('Contradicciones entre documentos (' . count($con['filas']) . ')', $con['columnas'],
                array_slice($con['filas'], 0, $tope));
        } elseif (array_key_exists('contradicciones', $datos)) {
            $secs[] = Informes::texto('Contradicciones entre documentos', 'Sin análisis reciente. Este bloque toma el último informe «Contradicciones» de hasta 7 días; si aún no hay ninguno, genera uno nuevo desde la sección de Informes y volverá a aparecer aquí.');
        }
        $punch = $datos['punch'] ?? [];
        if (array_key_exists('punch', $datos)) {
            $nota = implode(' · ', array_filter([$datos['punch_criticos'] ? $datos['punch_criticos'] . ' crítico(s)' : '', $datos['punch_vencidos'] ? $datos['punch_vencidos'] . ' vencido(s)' : '']));
            $nota = $nota !== '' ? ' — ' . $nota : '';
            $secs[] = $punch
                ? ($detallado
                    ? Informes::tabla('Punch list abierto (' . $datos['punch_total'] . ')' . $nota, ['Pendiente', 'Ubicación', 'Severidad', 'Estado', 'Responsable', 'Límite'],
                        array_map(fn($x) => [self::corto((string)$x['titulo'], 70), self::corto((string)$x['ubicacion'], 40) ?: '—', Punch::SEVERIDADES[$x['severidad']] ?? $x['severidad'], Punch::ESTADOS[$x['estado']] ?? $x['estado'], self::corto($x['responsable'] ?: (string)$x['contratista'], 40) ?: '—', self::fmt($x['fecha_limite']) . (!empty($x['vencido']) ? ' (vencido)' : '')], $punch))
                    : Informes::lista('Punch list abierto (' . $datos['punch_total'] . ')' . $nota, array_map(fn($x) => self::corto((string)$x['titulo'], 70) . ' — ' . strtoupper($x['severidad']) . (!empty($x['vencido']) ? ' (vencido)' : ''), $punch)))
                : Informes::texto('Punch list abierto', 'Ninguno: no hay pendientes de punch sin resolver.');
        }
        $causas = $datos['causas'] ?? null;
        if ($causas !== null && $causas['total']) {
            $por = array_filter($causas['por_causa']);
            arsort($por);
            if ($por) $secs[] = Informes::lista('Causas de atraso', array_map(fn($k, $v) => CausasRetraso::CAUSAS[$k] . ': ' . $v . ' actividad(es)', array_keys($por), array_values($por)));
            if ($detallado) $secs[] = Informes::tabla('Actividades atrasadas y sus causas (' . $causas['total'] . ')', ['Código', 'Actividad', 'Atraso (días)', 'Avance', 'Causas'],
                array_map(fn($x) => [$x['codigo'], self::corto($x['nombre'], 60), $x['atraso'], $x['progreso'] . ' %', self::corto(implode(' · ', array_map(fn($q) => CausasRetraso::CAUSAS[$q[0]] . ': ' . $q[1], $x['causas'])), 180)], $causas['filas']));
        } elseif ($causas !== null) {
            $secs[] = Informes::texto('Causas de atraso', 'No hay actividades atrasadas: ninguna supera su fecha de fin con trabajo pendiente.');
        }
        return $secs;
    }

    /** Accionables que se agregan siempre a «Pendientes de hoy» (los calcula el sistema, no la IA) */
    private static function pendientesSistema(array $datos): array
    {
        $out = [];
        foreach ($datos['ordenes_por_decidir'] as $o) $out[] = "Decidir {$o['numero']} «" . self::corto($o['titulo'], 60) . '»' . ($o['impacto_dias'] ? " ({$o['impacto_dias']} d)" : '');
        foreach (array_slice($datos['retiros'], 0, 5) as $r) if ($r['sin_programar'] || $r['vencido']) $out[] = "Programar el retiro de {$r['numero']}: " . self::corto($r['descripcion'], 60);
        foreach (array_slice($datos['riesgos_materiales'], 0, 3) as $m) if ($m['estado'] === 'tarde') $out[] = 'Confirmar fecha de ' . self::corto($m['material'], 40) . " ({$m['dias']} d tarde)";
        foreach (array_slice($datos['restricciones'] ?? [], 0, 5) as $x) $out[] = "Levantar restricción ({$x['tipo']}) en {$x['codigo']} " . self::corto($x['actividad'], 40);
        foreach (array_slice($datos['punch'] ?? [], 0, 5) as $x) if (!empty($x['vencido']) || $x['severidad'] === 'critica') $out[] = 'Resolver punch ' . strtoupper($x['severidad']) . ': ' . self::corto((string)$x['titulo'], 60);
        $con = $datos['contradicciones'] ?? null;
        if ($con) foreach (array_slice($con['filas'], 0, 3) as $f) if (stripos((string)($f[4] ?? ''), 'alta') !== false) $out[] = 'Escalar contradicción: ' . self::corto((string)($f[0] ?? ''), 30) . ' — ' . self::corto((string)($f[2] ?? ''), 50);
        foreach (array_slice($datos['riesgo_alto'] ?? [], 0, 3) as $f) $out[] = 'Revisar riesgo alto en ' . $f['codigo'] . ' ' . self::corto($f['nombre'], 40);
        return $out;
    }
}
