<?php

namespace Classes\Herramientas;

use Classes\Informes;
use Classes\PruebasIA;
use Model\Proyecto;

/**
 * Pruebas de calidad de la IA (Fase 5.3): corre la batería de casos fijos de Classes\PruebasIA con el modelo elegido en la app de
 * escritorio y guarda la nota en un informe, para comparar modelos (pantalla «Calidad de la IA») o ver si uno empeora con el tiempo.
 * Cada caso es un lote con su `clave`; el servidor califica lo que contestó el modelo con reglas deterministas, sin IA.
 * El informe lleva además un bloque `pruebas` ({version, nota, casos}) que lee la pantalla de comparación.
 */
class PruebasCalidad extends Base
{
    public static function id(): string { return 'pruebas_ia'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Pruebas de calidad de la IA', 'grupo' => 'control', 'tipo' => 'pruebas_ia', 'aplicable' => false,
            'descripcion' => 'Pone a prueba el modelo elegido con casos fijos (extraer fechas y cantidades, clasificar, detectar contradicciones, redactar un RFI sin inventar cifras, razonar con números). Da una nota por caso y total para comparar modelos entre sí o ver si uno empeora. No cambia datos del proyecto.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'],
            'parametros' => [['nombre' => 'suite', 'etiqueta' => 'Qué probar', 'tipo' => 'opcion', 'defecto' => 'todas', 'opciones' => ['todas' => 'Todos los casos'] + PruebasIA::SUITES]]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Prueba de calidad de la IA · ' . date('d/m/Y H:i');
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $casos = PruebasIA::deSuite((string)($params['suite'] ?? 'todas'));
        return [
            'datos' => ['casos' => array_column($casos, 'id')],
            'lotes' => array_map(fn($c) => ['titulo' => $c['nombre'], 'clave' => $c['id'], 'prompt' => $c['prompt']], $casos),
        ];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $por = [];
        foreach ($respuestas as $r) if (is_array($r) && isset($r['_clave'])) $por[$r['_clave']] = $r;
        $filas = []; $notas = []; $casos = []; $aprobados = 0; $ms = [];
        foreach ($datos['casos'] as $id) {
            $caso = PruebasIA::caso($id);
            if (!$caso) continue;
            $r = $por[$id] ?? null;
            $res = PruebasIA::puntuar($caso, $r ? array_diff_key($r, ['_clave' => 1, '_ms' => 1]) : null);
            $notas[] = $res['nota'];
            $aprobados += $res['aprobado'] ? 1 : 0;
            if (isset($r['_ms'])) $ms[] = (int)$r['_ms'];
            $casos[$id] = $res['nota'];
            $filas[] = [PruebasIA::SUITES[$caso['suite']], $caso['nombre'], $res['aprobado'] ? '✅ ' . round($res['nota'] * 100) . ' %' : ($res['nota'] > 0 ? '⚠ ' : '❌ ') . round($res['nota'] * 100) . ' %', $res['fallas'] ? implode(' · ', $res['fallas']) : '—'];
        }
        $n = count($notas);
        $nota = $n ? round(array_sum($notas) / $n * 100) : 0;
        $kpis = [['Nota total', $nota . ' %', $n ? "$aprobados de $n casos perfectos" : 'sin casos'], ['Versión de los casos', 'v' . PruebasIA::VERSION, 'solo se comparan corridas de la misma versión']];
        if ($ms) $kpis[] = ['Tiempo medio por caso', round(array_sum($ms) / count($ms) / 1000, 1) . ' s', 'depende del equipo y del modelo'];
        $porSuite = [];
        foreach ($datos['casos'] as $id) { $c = PruebasIA::caso($id); if ($c) $porSuite[$c['suite']][] = $casos[$id] ?? 0; }
        foreach ($porSuite as $s => $l) $kpis[] = [PruebasIA::SUITES[$s], round(array_sum($l) / count($l) * 100) . ' %', count($l) . ' caso(s)'];
        return [
            'secciones' => [
                Informes::kpis('Resultado', $kpis),
                Informes::tabla('Casos', ['Suite', 'Caso', 'Nota', 'Qué falló'], $filas),
                Informes::texto('Cómo leerlo', 'Cada caso es fijo: el mismo texto y la misma respuesta esperada. La nota es la fracción de reglas que cumplió la respuesta; un caso sin respuesta válida (el modelo no devolvió el JSON pedido) vale 0. Compara siempre corridas de la misma versión de casos y, de ser posible, repite la prueba: los modelos no contestan idéntico cada vez.'),
            ],
            'pruebas' => ['version' => PruebasIA::VERSION, 'nota' => $nota, 'casos' => $casos, 'suite' => (string)($params['suite'] ?? 'todas'), 'ms_medio' => $ms ? (int)round(array_sum($ms) / count($ms)) : null],
        ];
    }
}
