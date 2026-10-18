<?php

namespace Classes\Herramientas;

use Classes\Cambios;
use Classes\CruceMateriales;
use Classes\CurvaS;
use Classes\Informes;
use Classes\LineasBase;
use Model\LineaBase;
use Model\Proyecto;

/**
 * Resumen ejecutivo diario: avance contra el programa, desviación contra la línea base, cambios del día, retiros pendientes,
 * materiales contra cronograma y desviaciones sin orden de cambio. Toda cifra la calcula el servidor; la IA solo explica y sugiere.
 */
class ResumenEjecutivoDiario extends Base
{
    public static function id(): string { return 'resumen_diario'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Resumen ejecutivo diario', 'grupo' => 'control', 'tipo' => 'resumen_diario', 'aplicable' => false,
            'descripcion' => 'El estado del día en una página: avance contra el programa, desviación contra la línea base, cambios del día, retiros pendientes, materiales contra el cronograma y desviaciones sin orden de cambio. La IA explica por qué se movió la fecha final y sugiere acciones; las cifras las calcula el sistema.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [
                ['nombre' => 'fecha', 'etiqueta' => 'Día del resumen', 'tipo' => 'fecha', 'defecto' => date('Y-m-d')],
                ['nombre' => 'linea', 'etiqueta' => 'Id de la línea base (0 = la más reciente)', 'tipo' => 'numero', 'defecto' => 0, 'min' => 0, 'max' => 1000000],
            ]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Resumen ejecutivo ' . date('d/m/Y', strtotime($params['fecha']));
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

    public static function preparar(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $fecha = $params['fecha'];
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
        $riesgos = array_values(array_filter($cruce['filas'], fn($f) => in_array($f['estado'], ['tarde', 'compra_vencida', 'sin_fecha', 'submittal'], true)));
        $pend = self::filas("SELECT numero, titulo, impacto_costo, impacto_dias FROM ordenes_cambio WHERE proyecto_id = $pid AND estado IN ('borrador','solicitada') ORDER BY id LIMIT 8");

        $datos = ['fecha' => $fecha, 'curva' => $curva, 'linea' => $lb ? ['id' => (int)$lb->id, 'nombre' => $lb->nombre, 'creado' => substr((string)$lb->creado, 0, 10)] : null,
            'comparacion' => $cmp ? ['resumen' => array_diff_key($cmp['resumen'], ['linea' => 1]), 'movidas' => array_slice(array_values(array_filter($cmp['filas'], fn($f) => $f['estado'] !== 'igual')), 0, 40)] : null,
            'cruce' => $cruce['resumen'], 'riesgos_materiales' => array_slice($riesgos, 0, 12), 'retiros' => $retiros, 'retiros_vencidos' => count(array_filter($retiros, fn($r) => $r['vencido'])),
            'retiros_sin_programar' => count(array_filter($retiros, fn($r) => $r['sin_programar'])), 'dia' => $dia, 'cambios' => $resCambios, 'sin_oc' => array_slice($sinOc, 0, 15), 'ordenes_por_decidir' => $pend];
        $datos['semaforo'] = self::semaforo($datos);

        $r = $cmp['resumen'] ?? null;
        $prompt = "Eres el gerente de proyecto de una obra y escribes el resumen ejecutivo DIARIO para la dirección. Usa SOLO los datos de abajo, sin inventar cifras ni causas; si algo no está en los datos, no lo afirmes. Sé breve y concreto.\n\n"
            . "Proyecto: {$p->nombre} · Día: " . self::fmt($fecha) . " · Semáforo: {$datos['semaforo']}\n"
            . 'Avance programado ' . (isset($curva['plan']) ? $curva['plan'] . '%' : 's/d') . ' · real ' . (isset($curva['real']) ? $curva['real'] . '%' : 's/d') . (($curva['spi'] ?? null) !== null ? " · SPI {$curva['spi']}" : '') . "\n"
            . ($r ? "Línea base «{$lb->nombre}»: fin {$r['fin_base']} → {$r['fin_actual']} ({$r['d_fin']} días hábiles); actividades movidas {$r['movida']}, nuevas {$r['nueva']}, eliminadas {$r['eliminada']}; justificadas por orden de cambio {$r['oc']}, por material en riesgo {$r['material']}, SIN justificar {$r['sin_justificar']}.\n"
                . 'Ruta crítica: entran ' . (implode(', ', array_map(fn($f) => $f['codigo'] . ' ' . self::corto($f['nombre'], 40), $r['criticas_entran'])) ?: 'ninguna') . '; salen ' . (implode(', ', array_map(fn($f) => $f['codigo'], $r['criticas_salen'])) ?: 'ninguna') . ".\n" : "Sin línea base guardada.\n")
            . "\n## ACTIVIDADES MOVIDAS (máx. 12)\n" . ($cmp ? implode("\n", array_map(fn($f) => "{$f['codigo']} " . self::corto($f['nombre'], 50) . " [{$f['estado']}] fin {$f['d_fin']} d" . ($f['causa'] === 'oc' ? " · orden {$f['detalle']}" : ($f['causa'] === 'material' ? ' · material en riesgo' : ' · sin justificar')), array_slice($datos['comparacion']['movidas'], 0, 12))) : '(sin datos)')
            . "\n\n## MATERIALES EN RIESGO\n" . ($riesgos ? implode("\n", array_map(fn($f) => self::corto($f['material'], 50) . ' → ' . self::corto($f['actividad'], 40) . ': ' . CruceMateriales::ESTADOS[$f['estado']][0] . ($f['dias'] ? " ({$f['dias']} d)" : ''), array_slice($riesgos, 0, 8))) : '(ninguno)')
            . "\n\n## RETIROS PENDIENTES\n" . ($retiros ? implode("\n", array_map(fn($x) => "{$x['numero']}: " . self::corto($x['descripcion'], 60) . " (instalado hace {$x['dias_instalado']} d" . ($x['sin_programar'] ? ', retiro sin programar' : '') . ')', array_slice($retiros, 0, 6))) : '(ninguno)')
            . "\n\n## CAMBIOS DEL DÍA\nÓrdenes con movimiento: " . count($dia['ordenes']) . ' · renglones con movimiento: ' . count($dia['renglones']) . "\n"
            . "\nDevuelve SOLO un JSON: {\"analisis\":\"1 o 2 párrafos: por qué se movió (o no) la fecha final y qué pesa más\",\"sugerencias\":[\"2 a 4 acciones concretas\"],\"pendientes\":[\"decisiones o acciones del administrador para hoy\"]}";
        return ['datos' => $datos, 'lotes' => [['titulo' => 'Redactando el resumen ejecutivo diario', 'prompt' => $prompt]]];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $c = $datos['curva'];
        $cmp = $datos['comparacion']['resumen'] ?? null;
        $sem = $datos['semaforo'];
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
        $pendSis = [];
        foreach ($datos['ordenes_por_decidir'] as $o) $pendSis[] = "Decidir {$o['numero']} «" . self::corto($o['titulo'], 60) . '»' . ($o['impacto_dias'] ? " ({$o['impacto_dias']} d)" : '');
        foreach (array_slice($datos['retiros'], 0, 5) as $r) if ($r['sin_programar'] || $r['vencido']) $pendSis[] = "Programar el retiro de {$r['numero']}: " . self::corto($r['descripcion'], 60);
        foreach (array_slice($datos['riesgos_materiales'], 0, 3) as $m) if ($m['estado'] === 'tarde') $pendSis[] = 'Confirmar fecha de ' . self::corto($m['material'], 40) . " ({$m['dias']} d tarde)";

        $secs = [
            Informes::kpis('Estado del día: ' . $sem, [
                ['Avance programado', isset($c['plan']) ? $c['plan'] . '%' : 's/d'], ['Avance real', isset($c['real']) ? $c['real'] . '%' : 's/d'], ['SPI', ($c['spi'] ?? null) !== null ? $c['spi'] : 's/d', 'real ÷ programado'],
                ['Fin vs línea base', $cmp ? ($cmp['d_fin'] >= 0 ? '+' : '') . $cmp['d_fin'] . ' d' : 's/d', 'días hábiles'], ['Sin justificar', $cmp ? $cmp['sin_justificar'] : 's/d', 'desviaciones sin orden'],
                ['Retiros pendientes', count($datos['retiros']), $datos['retiros_vencidos'] . ' vencido(s)'], ['Materiales en riesgo', $datos['cruce']['en_riesgo'], 'de ' . $datos['cruce']['total']],
                ['Costo autorizado', '$' . number_format($datos['cambios']['costo_autorizado'], 2), $datos['cambios']['dias_autorizados'] . ' días en órdenes'],
            ]),
            Informes::texto('Análisis' . ($ia ? ' (redactado con IA, revísalo)' : ' (automático)'), $analisis),
        ];
        if ($cmp && ($cmp['criticas_entran'] || $cmp['criticas_salen'])) {
            $secs[] = Informes::lista('Cambios en la ruta crítica', array_merge(
                array_map(fn($f) => 'Entra: ' . $f['codigo'] . ' ' . self::corto($f['nombre'], 70), $cmp['criticas_entran']),
                array_map(fn($f) => 'Sale: ' . $f['codigo'] . ' ' . self::corto($f['nombre'], 70), $cmp['criticas_salen'])));
        }
        $secs[] = Informes::tabla('Cambios del día', ['Orden', 'Qué pasó', 'Estado'], array_merge(
            array_map(fn($o) => [$o['numero'], ($o['nueva'] ? 'Nueva: ' : 'Actualizada: ') . self::corto($o['titulo'], 70) . ($o['impacto_dias'] ? " · {$o['impacto_dias']} d" : '') . ((float)$o['impacto_costo'] ? ' · $' . number_format((float)$o['impacto_costo'], 2) : ''), \Model\OrdenCambio::ESTADOS[$o['estado']] ?? $o['estado']], $datos['dia']['ordenes']),
            array_map(fn($r) => [$r['numero'], \Model\CambioPartida::TIPOS[$r['tipo']] . ': ' . self::corto($r['descripcion'], 70), \Model\CambioPartida::ESTADOS[$r['estado']] ?? $r['estado']], $datos['dia']['renglones'])));
        $secs[] = Informes::tabla('Retiros pendientes', ['Orden', 'Instalado', 'Días', 'Retiro previsto'], array_map(fn($r) => [$r['numero'], self::corto($r['descripcion'], 70), $r['dias_instalado'], $r['sin_programar'] ? 'SIN PROGRAMAR' : self::fmt($r['fecha_retiro_prevista']) . ($r['vencido'] ? ' (vencido)' : '')], $datos['retiros']));
        $secs[] = Informes::tabla('Materiales contra cronograma (en riesgo)', ['Material', 'Para', 'Se necesita', 'Entrega', 'Situación'], array_map(fn($m) => [self::corto($m['material'], 50), self::corto(($m['ref'] ? $m['ref'] . ' ' : '') . $m['actividad'], 50), self::fmt($m['necesidad']), self::fmt($m['entrega']), CruceMateriales::ESTADOS[$m['estado']][0] . ($m['dias'] ? " ({$m['dias']} d)" : '')], $datos['riesgos_materiales']));
        $secs[] = Informes::tabla('Desviaciones sin orden de cambio', ['Actividad', 'Estado', 'Δ inicio (d)', 'Δ fin (d)'], array_map(fn($f) => [$f['codigo'] . ' ' . self::corto($f['nombre'], 60), $f['estado'], $f['d_inicio'], $f['d_fin']], $datos['sin_oc']));
        if ($sug) $secs[] = Informes::lista('Sugerencias', $sug);
        $secs[] = Informes::lista('Pendientes de hoy', array_values(array_unique(array_merge($pendIa, $pendSis))));
        return ['secciones' => $secs];
    }
}
