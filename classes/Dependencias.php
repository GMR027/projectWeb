<?php

namespace Classes;

use Model\Actividad;
use Model\Partida;
use Model\Proyecto;

/** Dependencias de obra de un proyecto: relaciones guardadas, cálculo de ruta crítica y reprogramación (modo automático) */
class Dependencias
{
    use AccesoDb;

    public const MODOS = ['apagado' => 'Apagado', 'informativo' => 'Informativo', 'automatico' => 'Automático'];
    public const TIPOS = ['FS' => 'Fin → inicio', 'SS' => 'Inicio → inicio', 'FF' => 'Fin → fin', 'SF' => 'Inicio → fin'];

    /** Actividades del proyecto con los datos de su partida */
    public static function actividades(int $proyectoId): array
    {
        $r = self::db()->query(
            "SELECT a.id, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin, a.duracion_dias, a.hito, a.fecha_fija, a.progreso, a.status, p.id AS partida_id, p.codigo AS partida_codigo, p.nombre AS partida, p.color_bg AS color
             FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $proyectoId ORDER BY p.orden, p.codigo, a.orden, a.codigo"
        );
        $out = [];
        while ($f = $r->fetch_assoc()) $out[(int)$f['id']] = $f;
        return $out;
    }

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
        $acts = self::actividades($pid);
        $rels = self::relaciones($pid);
        $c = self::calculo($proyecto, $acts, $rels);
        $lista = [];
        foreach ($acts as $id => $a) {
            $k = $c['acts'][$id] ?? null;
            $lista[] = [
                'id' => $id, 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'partida_id' => (int)$a['partida_id'], 'partida' => $a['partida_codigo'] . ' · ' . $a['partida'], 'color' => $a['color'],
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
                'finProgramado' => $c['finProgramadoRed'] ?? ($acts ? max(array_column($acts, 'fecha_fin')) : null), 'finCalculado' => $c['finCalculado'] ?? null,
            ],
            'ultimaAplicacion' => $ultimo,
        ];
    }

    /** ¿Se puede llegar de $desde a $hasta siguiendo las relaciones? (para no crear ciclos) */
    private static function alcanza(int $desde, int $hasta, array $rels): bool
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

    /** Crea o actualiza una relación. Devuelve [ok, mensaje] */
    public static function guardar(int $proyectoId, int $pred, int $suc, string $tipo, int $retraso): array
    {
        $acts = self::actividades($proyectoId);
        if (!isset($acts[$pred], $acts[$suc])) return [false, 'Las actividades deben ser de este proyecto'];
        if ($pred === $suc) return [false, 'Una actividad no puede depender de sí misma'];
        if (!isset(self::TIPOS[$tipo])) return [false, 'Tipo de relación no válido'];
        $retraso = max(-365, min(365, $retraso));
        $rels = self::relaciones($proyectoId);
        $existe = null;
        foreach ($rels as $r) if ($r['pred'] === $pred && $r['suc'] === $suc) $existe = $r;
        if (!$existe && self::alcanza($suc, $pred, $rels)) return [false, 'Esa relación crearía un ciclo (A depende de B y B de A, directa o indirectamente)'];
        $db = self::db();
        if ($existe) {
            $idRel = (int)$existe['id'];
            HistorialCambios::escribir('actividad_dependencias', 'modificado', $idRel,
                fn() => $db->query("UPDATE actividad_dependencias SET tipo = '" . $db->real_escape_string($tipo) . "', retraso = $retraso WHERE id = " . $idRel));
        } else {
            HistorialCambios::escribir('actividad_dependencias', 'creado', 0,
                fn() => $db->query("INSERT INTO actividad_dependencias (proyecto_id, predecesora_id, sucesora_id, tipo, retraso) VALUES ($proyectoId, $pred, $suc, '" . $db->real_escape_string($tipo) . "', $retraso)"));
        }
        return [true, ''];
    }

    public static function eliminar(int $proyectoId, int $id): void
    {
        HistorialCambios::escribir('actividad_dependencias', 'eliminado', $id,
            fn() => self::db()->query("DELETE FROM actividad_dependencias WHERE id = $id AND proyecto_id = $proyectoId"));
    }

    public static function fijar(int $proyectoId, int $actividadId, bool $fija): bool
    {
        $acts = self::actividades($proyectoId);
        if (!isset($acts[$actividadId])) return false;
        HistorialCambios::escribir('actividades', 'modificado', $actividadId,
            fn() => self::db()->query('UPDATE actividades SET fecha_fija = ' . ($fija ? 1 : 0) . ' WHERE id = ' . $actividadId));
        return true;
    }

    public static function cambiarModo(Proyecto $proyecto, string $modo): bool
    {
        if (!isset(self::MODOS[$modo])) return false;
        $proyecto->modo_dependencias = $modo;
        $proyecto->guardar();
        return true;
    }

    /** Qué movería la reprogramación automática: [['id','codigo','nombre','inicio','fin','nuevoInicio','nuevoFin','dias'], ...] */
    public static function previa(Proyecto $proyecto): array
    {
        $acts = self::actividades((int)$proyecto->id);
        $c = self::calculo($proyecto, $acts, self::relaciones((int)$proyecto->id));
        if (!$c || $c['ciclo']) return [];
        $out = [];
        foreach ($c['acts'] as $id => $k) {
            $a = $acts[$id];
            if ($k['empuje'] > 0 && !(int)$a['fecha_fija']) {
                $out[] = ['id' => $id, 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'nuevoInicio' => $k['es'], 'nuevoFin' => $k['ef'], 'dias' => $k['empuje']];
            }
        }
        usort($out, fn($x, $y) => [$x['nuevoInicio'], $x['id']] <=> [$y['nuevoInicio'], $y['id']]);
        return $out;
    }

    /** Aplica la reprogramación: guarda las fechas anteriores para poder deshacerla. Devuelve cuántas actividades se movieron */
    public static function aplicar(Proyecto $proyecto, bool $guardarSnapshot = true): int
    {
        $cambios = self::previa($proyecto);
        if (!$cambios) return 0;
        $db = self::db();
        if ($guardarSnapshot) {
            $prev = array_map(fn($c) => ['id' => $c['id'], 'inicio' => $c['inicio'], 'fin' => $c['fin']], $cambios);
            $db->query("INSERT INTO dependencias_cambios (proyecto_id, cambios_json) VALUES (" . (int)$proyecto->id . ", '" . $db->real_escape_string(json_encode($prev)) . "')");
        }
        self::escribir($proyecto, array_map(fn($c) => ['id' => $c['id'], 'inicio' => $c['nuevoInicio'], 'fin' => $c['nuevoFin']], $cambios));
        return count($cambios);
    }

    /** Escribe fechas en las actividades y refresca partidas e instalación de materiales */
    private static function escribir(Proyecto $proyecto, array $fechas): void
    {
        $partidas = [];
        foreach ($fechas as $f) {
            $a = Actividad::find($f['id']);
            if (!$a) continue;
            $a->fecha_inicio = $f['inicio'];
            $a->fecha_fin = $f['fin'];
            $a->guardar();
            $partidas[(int)$a->partida_id] = true;
        }
        foreach (array_keys($partidas) as $pid) {
            if ($p = Partida::find($pid)) $p->recalcularFechas();
        }
        try {
            \Model\ActividadMaterial::sincronizarInstalacion((int)$proyecto->id);
        } catch (\Throwable $e) {
        }
    }

    /** Devuelve las fechas anteriores a la última reprogramación. Devuelve cuántas actividades se restauraron */
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
