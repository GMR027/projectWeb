<?php

namespace Classes\Herramientas;

use Classes\Calidad;
use Classes\Dependencias;
use Classes\Informes;
use Classes\Normativa;
use Model\Proyecto;

/**
 * Plan de inspección (ITP) con IA: parte de las actividades pendientes y de las cláusulas de normativa ya vinculadas,
 * y propone los puntos de inspección con su criterio. El admin revisa el informe y, al aprobarlo, se crean los planes
 * y puntos en Calidad e inspecciones. Nada se crea al generar el borrador.
 */
class ItpCalidad extends Base
{
    public static function id(): string { return 'itp_calidad'; }

    public static function ficha(): array
    {
        return ['titulo' => 'Plan de inspección (ITP) con IA', 'grupo' => 'seguridad', 'tipo' => 'itp_calidad', 'aplicable' => true,
            'descripcion' => 'Para las actividades pendientes (o una en concreto) reúne la normativa y especificaciones vinculadas y propone los puntos de inspección: qué revisar, con qué criterio y con qué referencia. Al aprobar el informe se crean los planes y puntos en Calidad e inspecciones para que los marques en campo.',
            'requiere' => ['minimo' => 'basico', 'recomendado' => 'medio'],
            'parametros' => [
                ['nombre' => 'codigo', 'etiqueta' => 'Código o nombre de la actividad (vacío = pendientes)', 'tipo' => 'texto', 'defecto' => '', 'max' => 80],
                ['nombre' => 'maximo', 'etiqueta' => 'Máximo de actividades', 'tipo' => 'numero', 'defecto' => 6, 'min' => 1, 'max' => 20],
            ]];
    }

    public static function titulo(Proyecto $p, array $params): string
    {
        return 'Plan de inspección (ITP) · ' . date('d/m/Y');
    }

    /** Actividades pendientes con su normativa vinculada y sus materiales */
    private static function actividades(Proyecto $p, array $params): array
    {
        $pid = (int)$p->id;
        $busca = trim((string)$params['codigo']);
        $out = [];
        foreach (Dependencias::actividades($pid) as $id => $a) {
            if ((int)$a['hito'] || (int)$a['progreso'] >= 100) continue;
            if ($busca !== '' && mb_stripos((string)$a['codigo'], $busca) === false && mb_stripos((string)$a['nombre'], $busca) === false) continue;
            $a['id'] = (int)$id;
            $a['normativa'] = [];
            try { $a['normativa'] = Normativa::deEntidad($pid, 'actividad', (int)$id); } catch (\Throwable $e) { /* módulo pendiente */ }
            $a['materiales'] = self::filas("SELECT m.nombre, m.cantidad, m.unidad FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id WHERE am.actividad_id = $id ORDER BY m.nombre LIMIT 12");
            $out[] = $a;
            if (count($out) >= (int)$params['maximo']) break;
        }
        return $out;
    }

    public static function preparar(Proyecto $p, array $params): array
    {
        $acts = self::actividades($p, $params);
        $lotes = [];
        foreach ($acts as $a) {
            $norm = array_map(fn($n) => '- ' . trim(($n['codigo'] ? $n['codigo'] . ' ' : '') . ($n['doc'] ?? '')) . ($n['clausula'] ? ' · ' . $n['clausula'] : '')
                . ($n['requisito'] ? ': ' . self::corto($n['requisito'], 220) : ''), array_slice($a['normativa'], 0, 8));
            $mats = array_map(fn($m) => '- ' . $m['nombre'] . ' (' . $m['cantidad'] . ' ' . $m['unidad'] . ')', $a['materiales']);
            $lotes[] = ['titulo' => 'Redactando el ITP de ' . $a['codigo'] . ' ' . self::corto($a['nombre'], 40), 'clave' => 'A' . $a['id'], 'prompt' =>
                "Eres el responsable de calidad de una obra. Prepara el plan de inspección (ITP) de la actividad «{$a['codigo']} {$a['nombre']}» de la partida «{$a['partida_codigo']} {$a['partida']}».\n"
                . "Propón entre 3 y 6 puntos de inspección concretos y verificables. Para cada punto indica qué revisar, el criterio de aceptación y, si existe, la norma o cláusula que lo respalda. No inventes normas ni cifras que no estén en los datos.\n\n"
                . "## NORMATIVA Y ESPECIFICACIONES VINCULADAS\n" . ($norm ? implode("\n", $norm) : '(sin vínculos; usa buenas prácticas generales de la actividad)')
                . "\n\n## MATERIALES DE LA ACTIVIDAD\n" . ($mats ? implode("\n", $mats) : '(sin materiales capturados)')
                . "\n\nDevuelve SOLO un JSON: {\"puntos\":[{\"descripcion\":\"qué revisar\",\"criterio\":\"cómo se acepta\",\"norma\":\"referencia o vacío\"}]}"];
        }
        return ['datos' => ['actividades' => $acts], 'lotes' => $lotes];
    }

    /** Puntos de una respuesta de la IA, saneados; si no hay respuesta se usan los requisitos de normativa */
    private static function puntos(array $a, ?array $r): array
    {
        $out = [];
        foreach ((array)($r['puntos'] ?? []) as $x) {
            if (!is_array($x)) continue;
            $d = Informes::limpiarTexto($x['descripcion'] ?? '', 500);
            if ($d === '') continue;
            $out[] = ['descripcion' => $d, 'criterio' => Informes::limpiarTexto($x['criterio'] ?? '', 300), 'norma' => Informes::limpiarTexto($x['norma'] ?? '', 200)];
            if (count($out) >= 8) break;
        }
        if (!$out) {
            foreach (array_slice($a['normativa'], 0, 5) as $n) {
                $req = Informes::limpiarTexto($n['requisito'] ?? '', 300);
                if ($req === '') continue;
                $out[] = ['descripcion' => $req, 'criterio' => 'Cumple lo indicado en la cláusula', 'norma' => trim(($n['codigo'] ? $n['codigo'] . ' ' : '') . ($n['doc'] ?? '') . ($n['clausula'] ? ' · ' . $n['clausula'] : ''))];
            }
        }
        if (!$out) $out = [
            ['descripcion' => 'Revisar materiales, ubicación y condiciones previas', 'criterio' => 'Sin faltantes ni defectos visibles', 'norma' => ''],
            ['descripcion' => 'Verificar la ejecución contra el alcance de la actividad', 'criterio' => 'Terminado conforme al proyecto', 'norma' => ''],
            ['descripcion' => 'Comprobar acabado, limpieza y pruebas finales', 'criterio' => 'Sin observaciones abiertas', 'norma' => ''],
        ];
        return $out;
    }

    public static function componer(Proyecto $p, array $params, array $datos, array $respuestas): array
    {
        $por = [];
        foreach ($respuestas as $r) if (isset($r['_clave'])) $por[(string)$r['_clave']] = $r;
        $filas = [];
        $planes = [];
        $total = 0;
        $sinNorma = 0;
        foreach ($datos['actividades'] as $a) {
            $puntos = self::puntos($a, $por['A' . $a['id']] ?? null);
            $total += count($puntos);
            if (!$a['normativa']) $sinNorma++;
            foreach ($puntos as $x) $filas[] = [$a['codigo'] . ' ' . self::corto($a['nombre'], 45), self::corto($x['descripcion'], 80), self::corto($x['criterio'], 55), self::corto($x['norma'], 45)];
            $planes[] = ['actividad_id' => $a['id'], 'codigo' => 'ITP-' . mb_substr((string)$a['codigo'], 0, 30), 'titulo' => 'ITP · ' . $a['codigo'] . ' ' . self::corto($a['nombre'], 140),
                'tipo' => 'itp', 'norma' => self::corto(implode('; ', array_map(fn($n) => trim(($n['codigo'] ? $n['codigo'] . ' ' : '') . ($n['doc'] ?? '')), $a['normativa'])), 190),
                'puntos' => $puntos];
        }
        $secs = [
            Informes::kpis('Plan de inspección', [['Actividades', count($datos['actividades'])], ['Puntos propuestos', $total], ['Sin normativa vinculada', $sinNorma]]),
            Informes::texto('Cómo usarlo', $datos['actividades']
                ? 'Revisa los puntos y, si estás de acuerdo, aprueba el informe: se crearán los planes y sus puntos en Calidad e inspecciones. Después se marcan en campo como cumple, no cumple o no aplica.'
                : 'No hay actividades pendientes que coincidan. Cambia el código o revisa que el programa tenga actividades sin terminar.'),
        ];
        if ($filas) $secs[] = Informes::tabla('Puntos de inspección propuestos', ['Actividad', 'Qué revisar', 'Criterio de aceptación', 'Norma / cláusula'], $filas);
        if ($sinNorma) $secs[] = Informes::texto('Aviso de normativa', 'Algunas actividades no tienen normativa aceptada vinculada. Los puntos se proponen con buenas prácticas; para exigir una cláusula concreta, vincúlala en Normativa y especificaciones y vuelve a generar el ITP.');
        return ['secciones' => $secs, 'aplicar' => ['tipo' => 'itp_calidad', 'planes' => $planes]];
    }
}
