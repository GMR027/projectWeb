<?php

namespace Classes;

use Model\Proyecto;

/**
 * Centro de IA: reúne en un solo lugar lo que producen las herramientas de IA. La bandeja lista lo pendiente de todas y delega
 * aceptar/rechazar en la clase de cada herramienta; la biblioteca lista y compara sus análisis guardados.
 * No tiene tablas propias: lee las de cada herramienta, así que lo ya construido sigue funcionando igual.
 */
class CentroIA
{
    use AccesoDb;

    public const SEVERIDADES = ['baja' => 1, 'media' => 2, 'alta' => 3, 'critica' => 4];
    public const HERRAMIENTAS = ['inconsistencias' => 'Inconsistencias de proyecto', 'materiales' => 'Análisis de materiales', 'programador' => 'Programador de actividades', 'normativa' => 'Normativa y especificaciones'];

    // ---------- bandeja ----------

    /** Actividades implicadas en un hallazgo (con su partida, fechas y avance) y las relaciones entre ellas, para verlas sin abrir el proyecto */
    private static function detalleActividades(int $pid, array $ids, array $sol): array
    {
        static $cache = [];
        if (!isset($cache[$pid])) $cache[$pid] = ['acts' => Dependencias::actividades($pid), 'rels' => Dependencias::relaciones($pid)];
        $acts = $cache[$pid]['acts'];
        foreach ([$sol['params']['pred'] ?? 0, $sol['params']['suc'] ?? 0, $sol['params']['actividad_id'] ?? 0] as $i) if ($i) $ids[] = $i;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => isset($acts[$i]))));
        $lista = array_map(fn($i) => ['id' => $i, 'codigo' => $acts[$i]['codigo'], 'nombre' => $acts[$i]['nombre'], 'partida_codigo' => $acts[$i]['partida_codigo'], 'partida' => $acts[$i]['partida'], 'color' => $acts[$i]['color'],
            'inicio' => $acts[$i]['fecha_inicio'], 'fin' => $acts[$i]['fecha_fin'], 'dur' => (int)$acts[$i]['duracion_dias'], 'progreso' => (float)$acts[$i]['progreso'], 'status' => $acts[$i]['status'], 'hito' => (int)$acts[$i]['hito']], $ids);
        $rels = array_values(array_filter($cache[$pid]['rels'], fn($r) => in_array($r['pred'], $ids, true) && in_array($r['suc'], $ids, true)));
        return ['actividades' => $lista, 'relaciones' => array_map(fn($r) => ['pred' => $r['pred'], 'suc' => $r['suc'], 'tipo' => $r['tipo'], 'retraso' => $r['retraso']], $rels), 'params' => $sol['params'] ?? new \stdClass()];
    }

    /** Pendientes de todas las herramientas, ya normalizados. Lo repetido entre análisis se muestra una sola vez */
    public static function pendientes(Proyecto $p, array $f = []): array
    {
        $pid = (int)$p->id;
        $out = [];
        $unica = fn(string $tabla) => "i.id IN (SELECT m FROM (SELECT MAX(id) m FROM $tabla WHERE proyecto_id = $pid AND estado = 'pendiente' AND huella NOT IN (SELECT huella FROM $tabla WHERE proyecto_id = $pid AND estado <> 'pendiente') GROUP BY huella) t)";
        foreach (self::filas("SELECT i.* FROM inconsistencias i WHERE i.proyecto_id = $pid AND i.estado = 'pendiente' AND " . $unica('inconsistencias')) as $x) {
            $s = json_decode($x['solucion_json'], true) ?: [];
            $out[] = ['herramienta' => 'inconsistencias', 'id' => (int)$x['id'], 'titulo' => $x['titulo'], 'descripcion' => $x['descripcion'], 'severidad' => $x['severidad'], 'categoria' => Inconsistencias::CATEGORIAS[$x['categoria']] ?? $x['categoria'],
                'origen' => $x['origen'], 'accion' => $s['accion'] ?? 'nota', 'solucion' => $s['texto'] ?? '', 'fecha' => $x['detectado'], 'url' => '/inconsistencias?proyecto=' . $pid . '&corrida=' . (int)$x['corrida_id'], 'resoluble' => true,
                'detalle' => self::detalleActividades($pid, (array)json_decode((string)$x['actividades_json'], true), $s)];
        }
        foreach (self::filas("SELECT i.* FROM analisis_materiales_hallazgos i WHERE i.proyecto_id = $pid AND i.estado = 'pendiente' AND " . $unica('analisis_materiales_hallazgos')) as $x) {
            $s = json_decode($x['solucion_json'], true) ?: [];
            $out[] = ['herramienta' => 'materiales', 'id' => (int)$x['id'], 'titulo' => $x['titulo'], 'descripcion' => $x['descripcion'], 'severidad' => $x['severidad'], 'categoria' => AnalisisMateriales::CATEGORIAS[$x['categoria']] ?? $x['categoria'],
                'origen' => $x['origen'], 'accion' => $s['accion'] ?? 'nota', 'solucion' => $s['texto'] ?? '', 'fecha' => $x['detectado'], 'url' => '/analisis-materiales?proyecto=' . $pid . '&corrida=' . (int)$x['corrida_id'], 'resoluble' => true];
        }
        foreach (Normativa::aplicaciones($pid, ['estado' => 'propuesta']) as $x) {
            $out[] = ['herramienta' => 'normativa', 'id' => (int)$x['id'], 'titulo' => 'Normativa: ' . self::corto($x['entidad'], 70) . ' ← ' . ($x['doc'] ?: 'referencia') . ($x['clausula'] ? ' · ' . $x['clausula'] : ''),
                'descripcion' => $x['requisito'] ?: self::corto(trim((string)$x['ftitulo'] . '. ' . (string)$x['ftexto']), 260), 'severidad' => $x['origen'] === 'csi' ? 'media' : 'baja', 'categoria' => $x['entidad_tipo'] === 'material' ? 'Material' : 'Actividad',
                'origen' => $x['origen'] === 'ia' ? 'ia' : 'sistema', 'accion' => 'vincular_normativa', 'solucion' => 'Vincular esta cláusula al elemento (se muestra en su formulario como normativa aplicable).', 'fecha' => $x['creado'], 'url' => '/normativa/vinculos?proyecto=' . $pid, 'resoluble' => true];
        }
        // Programador: una fila por propuesta reciente con partidas aún por aceptar (aceptar cambia el programa, se hace en su pantalla)
        foreach (array_slice(Programador::lista($pid), 0, 3) as $l) {
            $prop = Programador::obtener($pid, (int)$l['id']);
            if (!$prop) continue;
            $n = count(array_filter(ProgramadorAcciones::impacto($p, $prop), fn($x) => $x['aplicables'] > 0 && !$x['aceptada']));
            if ($n) $out[] = ['herramienta' => 'programador', 'id' => (int)$l['id'], 'titulo' => "Propuesta del " . date('d/m/Y H:i', strtotime($l['creado'])) . ": $n partida(s) con cambios por aceptar", 'descripcion' => 'El Programador propone cambios de fechas y predecesoras. Acéptalos por partida en su pantalla.',
                'severidad' => 'media', 'categoria' => 'Propuesta de programa', 'origen' => 'ia', 'accion' => 'aceptar_partida', 'solucion' => 'Revisar la propuesta y aceptar partida por partida.', 'fecha' => $l['creado'], 'url' => '/programador?proyecto=' . $pid . '&propuesta=' . (int)$l['id'], 'resoluble' => false];
        }
        foreach ($out as &$x) {
            $x['riesgo'] = RiesgoIA::nivel($pid, $x['accion']);
            $x['partidas'] = [];
            foreach ($x['detalle']['actividades'] ?? [] as $a) $x['partidas'][$a['partida_codigo']] = $a['partida'];
        }
        unset($x);
        $conteo = array_fill_keys(array_keys(self::HERRAMIENTAS), 0);
        foreach ($out as $x) $conteo[$x['herramienta']]++;
        $partidas = [];
        foreach ($out as $x) foreach ($x['partidas'] as $cod => $nom) $partidas[$cod] = $nom;
        ksort($partidas);
        $bajo = count(array_filter($out, fn($x) => $x['resoluble'] && $x['riesgo'] === 'simple' && !in_array($x['accion'], RiesgoIA::SIN_LOTE, true)));
        if (in_array($f['riesgo'] ?? '', ['simple', 'permiso'], true)) $out = array_filter($out, fn($x) => $x['riesgo'] === $f['riesgo']);
        if (($f['partida'] ?? '') !== '') $out = array_filter($out, fn($x) => isset($x['partidas'][$f['partida']]));
        if (isset(self::HERRAMIENTAS[$f['herramienta'] ?? ''])) $out = array_filter($out, fn($x) => $x['herramienta'] === $f['herramienta']);
        if (isset(self::SEVERIDADES[$f['severidad'] ?? ''])) $out = array_filter($out, fn($x) => $x['severidad'] === $f['severidad']);
        if (($f['origen'] ?? '') === 'ia' || ($f['origen'] ?? '') === 'sistema') $out = array_filter($out, fn($x) => $x['origen'] === $f['origen']);
        usort($out, fn($a, $b) => [self::SEVERIDADES[$b['severidad']], $a['herramienta'], $a['id']] <=> [self::SEVERIDADES[$a['severidad']], $b['herramienta'], $b['id']]);
        return ['items' => array_values($out), 'conteo' => $conteo, 'partidas' => $partidas, 'bajo_riesgo' => $bajo];
    }

    public static function aceptar(Proyecto $p, string $herramienta, int $id): array
    {
        return match ($herramienta) {
            'inconsistencias' => Inconsistencias::aceptar($p, $id),
            'materiales' => AnalisisMateriales::aceptar($p, $id),
            'normativa' => Normativa::resolver($p, $id, 'aceptada'),
            default => [false, 'Esta herramienta se acepta desde su propia pantalla'],
        };
    }

    /**
     * Acepta de una vez las propuestas de bajo riesgo («simple» en Niveles de riesgo) que cumplen los filtros (herramienta, severidad, origen, partida).
     * Las que piden permiso nunca entran: se aceptan una por una. Cada una se aplica con su propia comprobación, así que si una deja de ser aplicable solo falla esa.
     * @return array{aplicadas: int, fallos: list<array{titulo: string, motivo: string}>, omitidas: int}
     */
    public static function aceptarLote(Proyecto $p, array $f, int $max = 200): array
    {
        $f['riesgo'] = 'simple';
        $items = array_filter(self::pendientes($p, $f)['items'], fn($x) => $x['resoluble'] && !in_array($x['accion'], RiesgoIA::SIN_LOTE, true));
        $res = ['aplicadas' => 0, 'fallos' => [], 'omitidas' => max(0, count($items) - $max)];
        foreach (array_slice($items, 0, $max) as $x) {
            [$ok, $msg] = self::aceptar($p, $x['herramienta'], $x['id']);
            if ($ok) $res['aplicadas']++;
            else $res['fallos'][] = ['titulo' => $x['titulo'], 'motivo' => $msg];
        }
        return $res;
    }

    public static function rechazar(Proyecto $p, string $herramienta, int $id): array
    {
        return match ($herramienta) {
            'inconsistencias' => Inconsistencias::rechazar((int)$p->id, $id),
            'materiales' => AnalisisMateriales::rechazar((int)$p->id, $id),
            'normativa' => Normativa::resolver($p, $id, 'rechazada'),
            default => [false, 'Esta herramienta se resuelve desde su propia pantalla'],
        };
    }

    // ---------- biblioteca ----------

    /** Análisis de todas las herramientas, del más reciente al más antiguo */
    public static function biblioteca(int $pid, array $f = []): array
    {
        $out = [];
        foreach (Inconsistencias::corridas($pid) as $c) {
            $out[] = ['herramienta' => 'inconsistencias', 'id' => (int)$c['id'], 'nombre' => $c['nombre'], 'guardada' => (int)$c['guardada'], 'modelo' => $c['modelo'], 'creado' => $c['creado'],
                'pendientes' => (int)$c['pendientes'], 'aceptadas' => (int)$c['aceptadas'], 'rechazadas' => (int)$c['rechazadas'], 'total' => (int)$c['total'], 'url' => "/inconsistencias?proyecto=$pid&corrida=" . (int)$c['id'], 'comparable' => true];
        }
        foreach (AnalisisMateriales::corridas($pid) as $c) {
            $out[] = ['herramienta' => 'materiales', 'id' => (int)$c['id'], 'nombre' => $c['nombre'], 'guardada' => (int)$c['guardada'], 'modelo' => $c['modelo'], 'creado' => $c['creado'],
                'pendientes' => (int)$c['pendientes'], 'aceptadas' => (int)$c['aceptadas'], 'rechazadas' => (int)$c['rechazadas'], 'total' => (int)$c['total'], 'url' => "/analisis-materiales?proyecto=$pid&corrida=" . (int)$c['id'], 'comparable' => true];
        }
        foreach (self::filas("SELECT id, nombre, guardada, modelo, creado, resumen_json, aceptadas_json FROM programador_propuestas WHERE proyecto_id = $pid ORDER BY id DESC LIMIT 40") as $c) {
            $r = json_decode($c['resumen_json'], true) ?: [];
            $acept = count(json_decode((string)$c['aceptadas_json'], true) ?: []);
            $out[] = ['herramienta' => 'programador', 'id' => (int)$c['id'], 'nombre' => $c['nombre'], 'guardada' => (int)$c['guardada'], 'modelo' => $c['modelo'], 'creado' => $c['creado'],
                'pendientes' => count($r['obstaculos'] ?? []) + count($r['mejoras'] ?? []), 'aceptadas' => $acept, 'rechazadas' => 0, 'total' => (int)($r['movidas'] ?? 0), 'url' => "/programador?proyecto=$pid&propuesta=" . (int)$c['id'], 'comparable' => false,
                'nota' => ($r['movidas'] ?? 0) . ' actividades cambian · ' . count($r['obstaculos'] ?? []) . ' obstáculos · ' . $acept . ' partida(s) aceptada(s)'];
        }
        if (isset(self::HERRAMIENTAS[$f['herramienta'] ?? ''])) $out = array_filter($out, fn($x) => $x['herramienta'] === $f['herramienta']);
        if (!empty($f['solo_guardadas'])) $out = array_filter($out, fn($x) => $x['guardada']);
        usort($out, fn($a, $b) => strcmp($b['creado'], $a['creado']) ?: $b['id'] <=> $a['id']);
        return array_values($out);
    }

    public static function marcar(Proyecto $p, string $herramienta, int $id, bool $guardar, string $nombre): void
    {
        $pid = (int)$p->id;
        match ($herramienta) {
            'inconsistencias' => Inconsistencias::marcarCorrida($pid, $id, $guardar, $nombre),
            'materiales' => AnalisisMateriales::marcarCorrida($pid, $id, $guardar, $nombre),
            'programador' => Programador::marcar($pid, $id, $guardar, $nombre),
            default => null,
        };
    }

    public static function eliminar(Proyecto $p, string $herramienta, int $id): void
    {
        $pid = (int)$p->id;
        match ($herramienta) {
            'inconsistencias' => Inconsistencias::eliminarCorrida($pid, $id),
            'materiales' => AnalisisMateriales::eliminarCorrida($pid, $id),
            'programador' => Programador::eliminar($pid, $id),
            default => null,
        };
    }

    /** Qué cambió entre dos análisis de la misma herramienta (por huella del hallazgo): nuevos, persistentes y ya no presentes */
    public static function comparar(int $pid, string $herramienta, int $a, int $b): ?array
    {
        $tabla = ['inconsistencias' => 'inconsistencias', 'materiales' => 'analisis_materiales_hallazgos'][$herramienta] ?? null;
        if (!$tabla) return null;
        $carga = fn(int $c) => array_column(self::filas("SELECT huella, titulo, severidad, estado FROM $tabla WHERE proyecto_id = $pid AND corrida_id = $c"), null, 'huella');
        $A = $carga($a);
        $B = $carga($b);
        $res = ['nuevos' => [], 'persisten' => [], 'desaparecen' => []];
        foreach ($B as $h => $x) $res[isset($A[$h]) ? 'persisten' : 'nuevos'][] = $x;
        foreach ($A as $h => $x) if (!isset($B[$h])) $res['desaparecen'][] = $x;
        foreach ($res as &$l) usort($l, fn($x, $y) => (self::SEVERIDADES[$y['severidad']] ?? 0) <=> (self::SEVERIDADES[$x['severidad']] ?? 0));
        return $res;
    }
}
