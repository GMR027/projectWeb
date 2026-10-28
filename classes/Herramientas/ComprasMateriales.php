<?php

namespace Classes\Herramientas;

use Classes\CruceMateriales;
use Classes\Informes;
use Model\Proyecto;

/**
 * Compras y suministros: cruza los materiales con el cronograma y prioriza qué comprar o reclamar primero.
 * El servidor calcula el riesgo; la IA solo redacta el plan de acción. No crea órdenes de compra.
 */
class ComprasMateriales extends Base
{
    public static function id(): string { return 'compras'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Compras y suministros', 'grupo' => 'compras', 'tipo' => 'compras', 'aplicable' => false,
            'descripcion' => 'Revisa los materiales contra la fecha en que los necesita cada actividad: compras vencidas, entregas tardías, materiales sin fecha y submittals sin aprobar. La IA redacta el plan de compras y reclamos; las cifras y los estados los calcula el sistema.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [['nombre' => 'maximo', 'etiqueta' => 'Máximo de materiales en el informe', 'tipo' => 'numero', 'defecto' => 25, 'min' => 5, 'max' => 80]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Compras y suministros · ' . date('d/m/Y');
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $cruce = CruceMateriales::cruzar($p);
        $riesgo = array_values(array_filter($cruce['filas'], fn($f) => in_array($f['estado'], ['tarde', 'compra_vencida', 'sin_fecha', 'submittal'], true)));
        $filas = array_slice($riesgo, 0, (int)$params['maximo']);
        $datos = ['filas' => $filas, 'resumen' => $cruce['resumen'], 'hoy' => date('Y-m-d')];
        $lineas = array_map(fn($f) => '- ' . $f['material'] . ' (' . $f['cantidad'] . ' ' . $f['unidad'] . ') para ' . $f['ref'] . ' ' . self::corto($f['actividad'], 50)
            . ': ' . (CruceMateriales::ESTADOS[$f['estado']][0] ?? $f['estado']) . ($f['dias'] ? ', ' . $f['dias'] . ' día(s)' : '')
            . '; se necesita el ' . self::fmt($f['necesidad']) . '; entrega ' . self::fmt($f['entrega']) . '; compra ' . ($f['compra'] ?: 'sin estado')
            . ($f['submittal'] ? '; submittal ' . $f['submittal'] : ''), $filas);
        $prompt = "Eres el comprador de una obra. Con SOLO estos materiales en riesgo, redacta un plan de compras y reclamos para hoy (" . self::fmt($datos['hoy']) . ").\n"
            . "No inventes proveedores, precios ni fechas. Ordena por urgencia real (lo que detiene una actividad primero).\n\n"
            . "## MATERIALES EN RIESGO\n" . ($lineas ? implode("\n", $lineas) : '(ninguno)')
            . "\n\n## RESUMEN\n" . json_encode($cruce['resumen'], JSON_UNESCAPED_UNICODE)
            . "\n\nDevuelve SOLO un JSON: {\"prioridades\":[\"acción concreta y a quién\"],\"riesgos\":[\"qué actividad se detiene si no llega\"],\"nota\":\"una frase de cierre\"}";
        return ['datos' => $datos, 'lotes' => $filas ? [['titulo' => 'Redactando el plan de compras', 'prompt' => $prompt]] : []];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $filas = $datos['filas'];
        $prioridades = self::textos(self::dato($respuestas, 'prioridades'), 8, 220);
        $riesgos = self::textos(self::dato($respuestas, 'riesgos'), 6, 220);
        $nota = Informes::limpiarTexto((string)(self::dato($respuestas, 'nota') ?? ''), 400);
        if (!$prioridades) {
            foreach (array_slice($filas, 0, 6) as $f) {
                $prioridades[] = ($f['estado'] === 'tarde' ? 'Reclamar entrega de ' : 'Comprar ') . self::corto($f['material'], 60)
                    . ' para ' . $f['ref'] . ' (se necesita el ' . self::fmt($f['necesidad']) . ').';
            }
        }
        $r = $datos['resumen'];
        $secs = [
            Informes::kpis('Materiales en riesgo', [
                ['En riesgo', $r['en_riesgo'] ?? 0, 'de ' . ($r['total'] ?? 0) . ' renglones'],
                ['Llegan tarde', $r['tarde'] ?? 0], ['Compra vencida', $r['compra_vencida'] ?? 0],
                ['Sin fecha', $r['sin_fecha'] ?? 0], ['Submittal pendiente', $r['submittal'] ?? 0],
            ]),
            Informes::texto('Lectura', $nota ?: ($filas ? 'Estos materiales pueden detener actividades del programa. Atiende primero los que ya debían haber llegado o cuya compra está vencida.' : 'No hay materiales en riesgo con el programa actual.')),
        ];
        if ($prioridades) $secs[] = Informes::lista('Prioridades de compra y reclamo', $prioridades);
        if ($riesgos) $secs[] = Informes::lista('Qué se detiene si no llega', $riesgos);
        $secs[] = Informes::tabla('Materiales en riesgo', ['Material', 'Cantidad', 'Para', 'Estado', 'Se necesita', 'Entrega', 'Compra', 'Submittal'],
            array_map(fn($f) => [self::corto($f['material'], 70), $f['cantidad'] . ' ' . $f['unidad'], $f['ref'] . ' ' . self::corto($f['actividad'], 45),
                CruceMateriales::ESTADOS[$f['estado']][0] ?? $f['estado'], self::fmt($f['necesidad']), self::fmt($f['entrega']), $f['compra'] ?: '—', $f['submittal'] ?: '—'], $filas));
        return ['secciones' => $secs];
    }
}
