<?php

namespace Classes\Herramientas;

use Classes\Informes;
use Classes\Punch;
use Model\Proyecto;

/** Análisis del punch list: dónde se repiten los pendientes (categoría, contratista, ubicación) y cuáles están vencidos o son graves. La estadística la calcula el sistema; la IA redacta las causas probables */
class PunchAnalisis extends Base
{
    public static function id(): string { return 'punch_analisis'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Análisis de causas repetidas (punch list)', 'grupo' => 'seguridad', 'tipo' => 'punch_analisis', 'aplicable' => false,
            'descripcion' => 'Agrupa los pendientes de calidad por categoría, contratista y ubicación para ver dónde se repiten las fallas y atacar la causa. La IA redacta las causas probables y las acciones.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'basico'], 'parametros' => []];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Análisis del punch list · ' . date('d/m/Y');
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $a = Punch::analisis((int)$p->id);
        $graves = array_slice(array_filter(Punch::listar((int)$p->id, ['estado' => 'pendientes']), fn($x) => in_array($x['severidad'], ['alta', 'critica'], true) || $x['vencido']), 0, 12);
        $t = fn(array $g) => implode('; ', array_map(fn($r) => "{$r['clave']}: {$r['pendientes']} pendientes de {$r['n']}" . ($r['graves'] ? " ({$r['graves']} graves)" : ''), array_slice($g, 0, 6)));
        $prompt = "Eres jefe de calidad de una obra. Con las estadísticas del punch list explica dónde se repiten los problemas y por qué podría ser, y qué hacer. Usa SOLO los datos; no inventes causas como si fueran hechos (di «posible» cuando sea una hipótesis).\n\nProyecto: {$p->nombre} · pendientes: {$a['pendientes']} de {$a['total']} · vencidos: {$a['vencidos']} · antigüedad media: " . ($a['edad'] ?? 's/d') . " días\n"
            . "Por categoría: " . $t($a['categorias']) . "\nPor contratista: " . $t($a['contratistas']) . "\nPor ubicación: " . $t($a['ubicaciones'])
            . "\n\n## GRAVES O VENCIDOS\n" . ($graves ? implode("\n", array_map(fn($x) => "[{$x['severidad']}] {$x['titulo']} ({$x['ubicacion']}; " . ($x['contratista'] ?: $x['responsable']) . ')', $graves)) : '(ninguno)')
            . "\n\nDevuelve SOLO un JSON: {\"resumen\":\"1 o 2 párrafos\",\"causas\":[\"causas probables, 3 a 5\"],\"acciones\":[\"acciones concretas, 3 a 5\"]}";
        return ['datos' => ['analisis' => $a, 'graves' => $graves], 'lotes' => $a['total'] ? [['titulo' => 'Redactando el análisis de causas', 'prompt' => $prompt]] : []];
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $a = $datos['analisis'];
        $resumen = Informes::limpiarTexto(self::dato($respuestas, 'resumen') ?? '', 2500);
        if ($resumen === '') $resumen = $a['total'] ? "Hay {$a['pendientes']} pendiente(s) de {$a['total']}, {$a['vencidos']} vencido(s)" . ($a['categorias'] ? '; la categoría con más pendientes es «' . $a['categorias'][0]['clave'] . '»' : '') . ($a['contratistas'] ? ' y el contratista con más es «' . $a['contratistas'][0]['clave'] . '»' : '') . '.' : 'Aún no hay pendientes registrados.';
        $secs = [Informes::kpis('Punch list', [['Pendientes', $a['pendientes'], 'de ' . $a['total']], ['Vencidos', $a['vencidos']], ['Cerrados', $a['cerrados']], ['Antigüedad media', $a['edad'] !== null ? $a['edad'] . ' d' : '—']]), Informes::texto('Resumen', $resumen)];
        foreach (['causas' => 'Causas probables', 'acciones' => 'Acciones sugeridas'] as $k => $tit) if ($l = self::textos(self::dato($respuestas, $k), 6, 240)) $secs[] = Informes::lista($tit, $l);
        foreach (['categorias' => 'Por categoría', 'contratistas' => 'Por contratista', 'ubicaciones' => 'Por ubicación'] as $k => $tit) $secs[] = Informes::tabla($tit, ['', 'Total', 'Pendientes', 'Graves'], array_map(fn($r) => [$r['clave'], $r['n'], $r['pendientes'], $r['graves']], $a[$k]));
        $secs[] = Informes::tabla('Graves o vencidos', ['Pendiente', 'Ubicación', 'Severidad', 'Responsable', 'Límite'], array_map(fn($x) => [self::corto($x['titulo'], 80), $x['ubicacion'], Punch::SEVERIDADES[$x['severidad']], $x['responsable'] ?: $x['contratista'], self::fmt($x['fecha_limite']) . ($x['vencido'] ? ' ⚠' : '')], $datos['graves']));
        return ['secciones' => $secs];
    }
}
