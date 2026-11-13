<?php

namespace Classes;

use Model\Actividad;
use Model\Partida;
use Model\Proyecto;
use Model\Subactividad;

/**
 * Dependencias de obra de un proyecto: relaciones guardadas, cálculo de ruta crítica y
 * reprogramación (modo automático).
 *
 * Nodos: actividades (`a<id>`) y subactividades (`s<id>`). Las relaciones actividad↔actividad
 * siguen en actividad_dependencias; las que tocan una subactividad van a subactividad_dependencias.
 * La actividad con subactividades es un resumen: su inicio es el mínimo de las hijas y su fin el máximo.
 */
class Dependencias
{
    use AccesoDb;

    public const MODOS = ['apagado' => 'Apagado', 'informativo' => 'Informativo', 'automatico' => 'Automático'];
    public const TIPOS = ['FS' => 'Fin → inicio', 'SS' => 'Inicio → inicio', 'FF' => 'Fin → fin', 'SF' => 'Inicio → fin'];

    /** Actividades del proyecto con los datos de su partida (sin subactividades) */
    public static function actividades(int $proyectoId): array
    {
        $r = self::db()->query(
            "SELECT a.id, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin, a.duracion_dias, a.hito, a.fecha_fija, a.progreso, a.status, a.calendario_id, p.id AS partida_id, p.codigo AS partida_codigo, p.nombre AS partida, p.color_bg AS color
             FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $proyectoId ORDER BY p.orden, p.codigo, a.orden, a.codigo"
        );
        $out = [];
        while ($f = $r->fetch_assoc()) $out[(int)$f['id']] = $f;
        return $out;
    }

    /** Nodos del diagrama: actividades (`a<id>`) + subactividades (`s<id>`) */
    public static function nodos(int $proyectoId): array
    {
        $acts = self::actividades($proyectoId);
        $subs = \Model\Subactividad::deProyecto($proyectoId);
        $out = [];
        foreach ($acts as $id => $a) {
            $out['a' . $id] = array_merge($a, [
                'id' => 'a' . $id, 'tipo' => 'actividad', 'padre' => null,
                'con_sub' => count($subs[$id] ?? []),
            ]);
        }
        foreach ($subs as $aid => $lista) {
            $a = $acts[$aid] ?? null;
            if (!$a) continue;
            foreach ($lista as $i => $s) {
                $out['s' . (int)$s->id] = [
                    'id' => 's' . (int)$s->id, 'tipo' => 'subactividad', 'padre' => 'a' . (int)$aid,
                    'actividad_id' => (int)$aid, 'codigo' => $a['codigo'] . '.' . ($i + 1), 'nombre' => (string)$s->nombre,
                    'fecha_inicio' => (string)$s->fecha_inicio, 'fecha_fin' => (string)$s->fecha_fin,
                    'duracion_dias' => (int)ceil($s->duracion()), 'hito' => $s->esHito() ? 1 : 0,
                    'fecha_fija' => (int)$s->fecha_fija, 'progreso' => (int)$s->progreso, 'status' => $s->estado(),
                    'calendario_id' => $s->calendario_id,
                    'partida_id' => (int)$a['partida_id'], 'partida_codigo' => $a['partida_codigo'], 'partida' => $a['partida'], 'color' => $a['color'],
                ];
            }
        }
        return $out;
    }

    /** Relaciones actividad ↔ actividad (compatibilidad: ids numéricos de actividad) */
    public static function relaciones(int $proyectoId): array
    {
        $out = [];
        try {
            $r = self::db()->query("SELECT id, predecesora_id AS pred, sucesora_id AS suc, tipo, retraso FROM actividad_dependencias WHERE proyecto_id = $proyectoId ORDER BY id");
            while ($f = $r->fetch_assoc()) $out[] = ['id' => (int)$f['id'], 'pred' => (int)$f['pred'], 'suc' => (int)$f['suc'], 'tipo' => $f['tipo'], 'retraso' => (int)$f['retraso']];
        } catch (\Throwable $e) {   // migración pendiente
        }
        return $out;
    }

    /** Relaciones guardadas, con nodos `a<id>` / `s<id>` y un id de relación `a<id>` / `s<id>` */
    public static function relacionesNodos(int $proyectoId): array
    {
        $out = [];
        try {
            $r = self::db()->query("SELECT id, predecesora_id AS pred, sucesora_id AS suc, tipo, retraso FROM actividad_dependencias WHERE proyecto_id = $proyectoId ORDER BY id");
            while ($f = $r->fetch_assoc()) {
                $out[] = ['id' => 'a' . (int)$f['id'], 'pred' => 'a' . (int)$f['pred'], 'suc' => 'a' . (int)$f['suc'], 'tipo' => $f['tipo'], 'retraso' => (int)$f['retraso'], 'origen' => 'actividad'];
            }
        } catch (\Throwable $e) {   // migración pendiente
        }
        try {
            $r = self::db()->query("SELECT id, pred_tipo, pred_id, suc_tipo, suc_id, tipo, retraso FROM subactividad_dependencias WHERE proyecto_id = $proyectoId ORDER BY id");
            while ($f = $r->fetch_assoc()) {
                $out[] = [
                    'id' => 's' . (int)$f['id'],
                    'pred' => ($f['pred_tipo'] === 'subactividad' ? 's' : 'a') . (int)$f['pred_id'],
                    'suc' => ($f['suc_tipo'] === 'subactividad' ? 's' : 'a') . (int)$f['suc_id'],
                    'tipo' => $f['tipo'], 'retraso' => (int)$f['retraso'], 'origen' => 'subactividad',
                ];
            }
        } catch (\Throwable $e) {   // migración pendiente
        }
        return $out;
    }

    /** Calcula con el motor las fechas, holguras y conflictos (null si el modo está apagado) */
    private static function calculo(Proyecto $proyecto, array $acts, array $rels): ?array
    {
        if (($proyecto->modo_dependencias ?? 'apagado') === 'apagado' || !$rels) return null;
        $m = [];
        foreach ($acts as $id => $a) {
            $m[$id] = ['id' => $id, 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'dur' => (int)$a['duracion_dias'], 'hito' => (int)$a['hito'], 'fija' => (int)$a['fecha_fija']];
        }
        return (new RutaCritica(new Calendario((int)$proyecto->id)))->calcular($m, $rels);
    }

    /** Todo lo que necesita la pantalla */
    public static function datos(Proyecto $proyecto): array
    {
        $pid = (int)$proyecto->id;
        $nodos = self::nodos($pid);
        $rels = array_values(array_filter(self::relacionesNodos($pid), fn($r) => isset($nodos[$r['pred']], $nodos[$r['suc']])));
        $c = self::calculo($proyecto, $nodos, $rels);
        $lista = [];
        foreach ($nodos as $id => $a) {
            $k = $c['acts'][$id] ?? null;
            $lista[] = [
                'id' => $id, 'tipo' => $a['tipo'], 'padre' => $a['padre'], 'con_sub' => (int)($a['con_sub'] ?? 0),
                'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'partida_id' => (int)$a['partida_id'], 'partida' => $a['partida_codigo'] . ' · ' . $a['partida'], 'color' => $a['color'],
                'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'dur' => (int)$a['duracion_dias'], 'hito' => (int)$a['hito'], 'fija' => (int)$a['fecha_fija'], 'progreso' => (int)$a['progreso'],
                'es' => $k['es'] ?? null, 'ef' => $k['ef'] ?? null, 'holgura' => $k['holgura'] ?? null, 'critica' => (bool)($k['critica'] ?? false), 'conflicto' => (bool)($k['conflicto'] ?? false),
                'empuje' => (int)($k['empuje'] ?? 0), 'enRed' => (bool)($k['enRed'] ?? false),
            ];
        }
        foreach ($rels as &$r) {
            $r['conflicto'] = (bool)($c['deps'][$r['id']]['conflicto'] ?? false);
            $r['requerido'] = $c['deps'][$r['id']]['requerido'] ?? null;
        }
        unset($r);
        $pendientes = $c && !$c['ciclo'] ? count(array_filter($c['acts'], fn($k) => $k['empuje'] > 0)) : 0;
        $ultimo = null;
        try {
            $q = self::db()->query("SELECT creado FROM dependencias_cambios WHERE proyecto_id = $pid ORDER BY id DESC LIMIT 1");
            $f = $q ? $q->fetch_row() : null;
            $ultimo = $f ? $f[0] : null;
        } catch (\Throwable $e) {
        }
        return [
            'modo' => $proyecto->modo_dependencias ?? 'apagado', 'actividades' => $lista, 'relaciones' => $rels, 'ciclo' => $c['ciclo'] ?? null,
            'resumen' => [
                'relaciones' => count($rels), 'criticas' => $c ? count(array_filter($c['acts'], fn($k) => $k['critica'])) : 0,
                'conflictos' => $c ? count(array_filter($c['acts'], fn($k) => $k['conflicto'])) : 0, 'pendientes' => $pendientes,
                'finProgramado' => $c['finProgramadoRed'] ?? ($nodos ? max(array_column($nodos, 'fecha_fin')) : null), 'finCalculado' => $c['finCalculado'] ?? null,
            ],
            'ultimaAplicacion' => $ultimo,
        ];
    }

    /** ¿Se puede llegar de $desde a $hasta siguiendo las relaciones? (para no crear ciclos) */
    private static function alcanza(string $desde, string $hasta, array $rels): bool
    {
        $adj = [];
        foreach ($rels as $r) $adj[$r['pred']][] = $r['suc'];
        $visto = [];
        $pila = [$desde];
        while ($pila) {
            $x = array_pop($pila);
            if ($x === $hasta) return true;
            if (isset($visto[$x])) continue;
            $visto[$x] = true;
            foreach ($adj[$x] ?? [] as $y) $pila[] = $y;
        }
        return false;
    }

    /** Normaliza un nodo: acepta `a12`, `s7` o un id numérico (actividad) */
    private static function nodo($v): string
    {
        $v = (string)$v;
        return preg_match('/^[as]\d+$/', $v) ? $v : 'a' . max(0, (int)$v);
    }

    /** [tipo, id] de una clave de nodo */
    private static function partir(string $nodo): array
    {
        return [$nodo[0] === 's' ? 'subactividad' : 'actividad', (int)substr($nodo, 1)];
    }

    /** Crea o actualiza una relación. Devuelve [ok, mensaje] */
    public static function guardar(int $proyectoId, $pred, $suc, string $tipo, int $retraso): array
    {
        $pred = self::nodo($pred);
        $suc = self::nodo($suc);
        $nodos = self::nodos($proyectoId);
        if (!isset($nodos[$pred], $nodos[$suc])) return [false, 'Las actividades o subactividades deben ser de este proyecto'];
        if ($pred === $suc) return [false, 'Un nodo no puede depender de sí mismo'];
        // Una subactividad no puede depender de su propia actividad padre (ni al revés)
        foreach ([[$pred, $suc], [$suc, $pred]] as [$x, $y]) {
            if (isset($nodos[$x]) && $nodos[$x]['tipo'] === 'subactividad' && $nodos[$x]['padre'] === $y) {
                return [false, 'Una subactividad no puede depender de su propia actividad padre'];
            }
        }
        if (!isset(self::TIPOS[$tipo])) return [false, 'Tipo de relación no válido'];
        $retraso = max(-365, min(365, $retraso));
        $rels = self::relacionesNodos($proyectoId);
        $existe = null;
        foreach ($rels as $r) if ($r['pred'] === $pred && $r['suc'] === $suc) $existe = $r;
        if (!$existe && self::alcanza($suc, $pred, $rels)) return [false, 'Esa relación crearía un ciclo (A depende de B y B de A, directa o indirectamente)'];
        $db = self::db();
        $ambosActividades = $pred[0] === 'a' && $suc[0] === 'a';
        if ($ambosActividades) {
            if ($existe) {
                $idRel = (int)substr($existe['id'], 1);
                HistorialCambios::escribir('actividad_dependencias', 'modificado', $idRel,
                    fn() => $db->query("UPDATE actividad_dependencias SET tipo = '" . $db->real_escape_string($tipo) . "', retraso = $retraso WHERE id = $idRel"));
            } else {
                HistorialCambios::escribir('actividad_dependencias', 'creado', 0,
                    fn() => $db->query("INSERT INTO actividad_dependencias (proyecto_id, predecesora_id, sucesora_id, tipo, retraso) VALUES ($proyectoId, " . (int)substr($pred, 1) . ', ' . (int)substr($suc, 1) . ", '" . $db->real_escape_string($tipo) . "', $retraso)"));
            }
        } else {
            [$pt, $pidN] = self::partir($pred);
            [$st, $sidN] = self::partir($suc);
            $sql = "INSERT INTO subactividad_dependencias (proyecto_id, pred_tipo, pred_id, suc_tipo, suc_id, tipo, retraso)
                    VALUES ($proyectoId, '$pt', $pidN, '$st', $sidN, '" . $db->real_escape_string($tipo) . "', $retraso)
                    ON DUPLICATE KEY UPDATE tipo = VALUES(tipo), retraso = VALUES(retraso)";
            HistorialCambios::escribir('subactividad_dependencias', $existe ? 'modificado' : 'creado', $existe ? (int)substr($existe['id'], 1) : 0, fn() => $db->query($sql));
        }
        return [true, ''];
    }

    public static function eliminar(int $proyectoId, $id): void
    {
        $id = (string)$id;
        $esSub = str_starts_with($id, 's');
        $n = (int)preg_replace('/^[as]/', '', $id);
        if ($n <= 0) return;
        $tabla = $esSub ? 'subactividad_dependencias' : 'actividad_dependencias';
        HistorialCambios::escribir($tabla, 'eliminado', $n,
            fn() => self::db()->query("DELETE FROM $tabla WHERE id = $n AND proyecto_id = $proyectoId"));
    }

    public static function fijar(int $proyectoId, $nodo, bool $fija): bool
    {
        $nodo = self::nodo($nodo);
        [$tipo, $id] = self::partir($nodo);
        if ($tipo === 'subactividad') {
            $s = Subactividad::find($id);
            if (!$s || (int)$s->proyectoId() !== $proyectoId) return false;
            HistorialCambios::escribir('subactividades', 'modificado', $id,
                fn() => self::db()->query('UPDATE subactividades SET fecha_fija = ' . ($fija ? 1 : 0) . ' WHERE id = ' . $id));
            return true;
        }
        $acts = self::actividades($proyectoId);
        if (!isset($acts[$id])) return false;
        HistorialCambios::escribir('actividades', 'modificado', $id,
            fn() => self::db()->query('UPDATE actividades SET fecha_fija = ' . ($fija ? 1 : 0) . ' WHERE id = ' . $id));
        return true;
    }

    public static function cambiarModo(Proyecto $proyecto, string $modo): bool
    {
        if (!isset(self::MODOS[$modo])) return false;
        $proyecto->modo_dependencias = $modo;
        $proyecto->guardar();
        return true;
    }

    /**
     * Qué movería la reprogramación automática. Los padres con hijas no se mueven solos:
     * se desplazan sus hijas y el padre recalcula su envolvente.
     */
    public static function previa(Proyecto $proyecto): array
    {
        $pid = (int)$proyecto->id;
        $nodos = self::nodos($pid);
        $rels = array_values(array_filter(self::relacionesNodos($pid), fn($r) => isset($nodos[$r['pred']], $nodos[$r['suc']])));
        $c = self::calculo($proyecto, $nodos, $rels);
        if (!$c || $c['ciclo']) return [];
        $out = [];
        foreach ($c['acts'] as $id => $k) {
            if ($k['empuje'] <= 0) continue;
            $a = $nodos[$id] ?? null;
            if (!$a || (int)$a['fecha_fija']) continue;
            if ($a['tipo'] === 'subactividad') {
                $out[$id] = ['tipo' => 'subactividad', 'id' => $id, 'codigo' => $a['codigo'], 'nombre' => $a['nombre'],
                    'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'nuevoInicio' => $k['es'], 'nuevoFin' => $k['ef'], 'dias' => $k['empuje']];
                continue;
            }
            if ((int)$a['con_sub'] > 0) {
                // Padre resumen: se desplazan sus hijas el mismo empuje (días naturales para conservar el dibujo).
                foreach ($nodos as $hijo) {
                    if ($hijo['tipo'] !== 'subactividad' || $hijo['padre'] !== $id) continue;
                    $ni = date('Y-m-d', strtotime($hijo['fecha_inicio'] . ' +' . $k['empuje'] . ' days'));
                    $nf = date('Y-m-d', strtotime($hijo['fecha_fin'] . ' +' . $k['empuje'] . ' days'));
                    $out[$hijo['id']] = ['tipo' => 'subactividad', 'id' => $hijo['id'], 'codigo' => $hijo['codigo'], 'nombre' => $hijo['nombre'],
                        'inicio' => $hijo['fecha_inicio'], 'fin' => $hijo['fecha_fin'], 'nuevoInicio' => $ni, 'nuevoFin' => $nf, 'dias' => $k['empuje']];
                }
                continue;
            }
            $out[$id] = ['tipo' => 'actividad', 'id' => $id, 'codigo' => $a['codigo'], 'nombre' => $a['nombre'],
                'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'nuevoInicio' => $k['es'], 'nuevoFin' => $k['ef'], 'dias' => $k['empuje']];
        }
        $out = array_values($out);
        usort($out, fn($x, $y) => [$x['nuevoInicio'], $x['id']] <=> [$y['nuevoInicio'], $y['id']]);
        return $out;
    }

    /** Aplica la reprogramación: guarda las fechas anteriores para poder deshacerla. Devuelve cuántos nodos se movieron */
    public static function aplicar(Proyecto $proyecto, bool $guardarSnapshot = true): int
    {
        $cambios = self::previa($proyecto);
        if (!$cambios) return 0;
        $db = self::db();
        if ($guardarSnapshot) {
            $prev = array_map(fn($c) => ['tipo' => $c['tipo'], 'id' => $c['id'], 'inicio' => $c['inicio'], 'fin' => $c['fin']], $cambios);
            $db->query("INSERT INTO dependencias_cambios (proyecto_id, cambios_json) VALUES (" . (int)$proyecto->id . ", '" . $db->real_escape_string(json_encode($prev)) . "')");
        }
        self::escribir($proyecto, array_map(fn($c) => ['tipo' => $c['tipo'], 'id' => $c['id'], 'inicio' => $c['nuevoInicio'], 'fin' => $c['nuevoFin']], $cambios));
        return count($cambios);
    }

    /** Escribe fechas en actividades o subactividades y refresca partidas, materiales y vPlan */
    private static function escribir(Proyecto $proyecto, array $fechas): void
    {
        $partidas = [];
        $padres = [];
        foreach ($fechas as $f) {
            $tipo = $f['tipo'] ?? 'actividad';
            $id = $f['id'];
            if ($tipo === 'subactividad') {
                $sub = Subactividad::find((int)preg_replace('/^s/', '', (string)$id));
                if (!$sub) continue;
                $sub->fecha_inicio = $f['inicio'];
                $sub->fecha_fin = $f['fin'];
                if (!$sub->guardar()) continue;
                $act = Actividad::find((int)$sub->actividad_id);
                if ($act) $padres[(int)$act->id] = $act;
            } else {
                $act = Actividad::find((int)preg_replace('/^a/', '', (string)$id));
                if (!$act) continue;
                $act->fecha_inicio = $f['inicio'];
                $act->fecha_fin = $f['fin'];
                if (!$act->guardar()) continue;
                $partidas[(int)$act->partida_id] = true;
                if ($act->tieneSubactividades()) $padres[(int)$act->id] = $act;
            }
        }
        // Recalcular el envolvente de los padres afectados (sin volver a propagar para no entrar en bucle)
        foreach ($padres as $act) {
            \Classes\Subactividades::recalcular($act, ['propagar' => false, 'hoja' => true]);
            $partidas[(int)$act->partida_id] = true;
        }
        foreach (array_keys($partidas) as $pid) {
            if ($p = Partida::find($pid)) $p->recalcularFechas();
        }
        try {
            \Model\ActividadMaterial::sincronizarInstalacion((int)$proyecto->id);
        } catch (\Throwable $e) {
        }
    }

    /** Devuelve las fechas anteriores a la última reprogramación. Devuelve cuántos nodos se restauraron */
    public static function deshacer(Proyecto $proyecto): int
    {
        $db = self::db();
        $pid = (int)$proyecto->id;
        $q = $db->query("SELECT id, cambios_json FROM dependencias_cambios WHERE proyecto_id = $pid ORDER BY id DESC LIMIT 1");
        $f = $q ? $q->fetch_assoc() : null;
        if (!$f) return 0;
        $prev = json_decode($f['cambios_json'], true) ?: [];
        self::escribir($proyecto, $prev);
        $db->query('DELETE FROM dependencias_cambios WHERE id = ' . (int)$f['id']);
        return count($prev);
    }

    /** En modo automático, empuja las sucesoras tras un cambio de fechas en el programa (sin vista previa). Devuelve cuántas movió */
    public static function propagar(Proyecto $proyecto): int
    {
        if (($proyecto->modo_dependencias ?? 'apagado') !== 'automatico') return 0;
        try {
            return self::aplicar($proyecto, false);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
