<?php

namespace Classes;

/**
 * Calidad e inspecciones (ITP): plan de puntos de inspección por actividad o por proyecto.
 * Cada punto se marca cumple / no cumple / no aplica; las no conformidades abiertas se ven aparte.
 */
class Calidad
{
    use AccesoDb;

    /** Bloqueos por proyecto calculados en esta petición (se reinicia al cambiar puntos o liberaciones) */
    private static array $cacheBloqueos = [];

    public const TIPOS = ['itp' => 'ITP (plan de inspección)', 'inspeccion' => 'Inspección', 'prueba' => 'Prueba de laboratorio'];
    public const ESTADOS = ['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'conforme' => 'Conforme', 'no_conforme' => 'No conforme', 'cerrado' => 'Cerrado'];
    public const RESULTADOS = ['pendiente' => 'Pendiente', 'cumple' => 'Cumple', 'no_cumple' => 'No cumple', 'na' => 'No aplica'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM calidad_planes LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Planes con su actividad y el conteo de puntos */
    public static function planes(int $pid, string $estado = ''): array
    {
        $w = isset(self::ESTADOS[$estado]) ? " AND c.estado = '$estado'" : '';
        return self::filas("SELECT c.*, a.codigo act_codigo, a.nombre act_nombre,
                (SELECT COUNT(*) FROM calidad_puntos p WHERE p.plan_id = c.id) puntos,
                (SELECT COUNT(*) FROM calidad_puntos p WHERE p.plan_id = c.id AND p.resultado = 'cumple') cumplen,
                (SELECT COUNT(*) FROM calidad_puntos p WHERE p.plan_id = c.id AND p.resultado = 'no_cumple' AND p.resuelto = 0) nc
            FROM calidad_planes c LEFT JOIN actividades a ON a.id = c.actividad_id
            WHERE c.proyecto_id = $pid$w
            ORDER BY FIELD(c.estado, 'no_conforme', 'pendiente', 'en_proceso', 'conforme', 'cerrado'), c.fecha IS NULL, c.fecha, c.id DESC");
    }

    public static function plan(int $pid, int $id): ?array
    {
        return self::filas("SELECT c.*, a.codigo act_codigo, a.nombre act_nombre FROM calidad_planes c
            LEFT JOIN actividades a ON a.id = c.actividad_id WHERE c.id = $id AND c.proyecto_id = $pid")[0] ?? null;
    }

    public static function puntos(int $planId): array
    {
        return self::filas("SELECT * FROM calidad_puntos WHERE plan_id = $planId ORDER BY orden, id");
    }

    /** Crea o actualiza un plan. En uno nuevo, `puntos` es una lista separada por líneas. */
    public static function guardarPlan(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $titulo = trim((string)($d['titulo'] ?? ''));
        if ($titulo === '') return [false, 'El título es obligatorio', 0];
        $actividad = (int)($d['actividad_id'] ?? 0);
        if ($actividad) {
            $ok = self::filas("SELECT 1 FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $actividad AND p.proyecto_id = $pid");
            if (!$ok) $actividad = 0;
        }
        $tipo = isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'itp';
        $estado = isset(self::ESTADOS[$d['estado'] ?? '']) ? $d['estado'] : 'pendiente';
        $fecha = self::fecha($d['fecha'] ?? '');
        $bloquea = array_key_exists('bloquea', $d) ? (int)!empty($d['bloquea']) : null;
        $campos = "actividad_id = " . ($actividad ?: 'NULL') . ", codigo = '" . self::esc(mb_substr(trim((string)($d['codigo'] ?? '')), 0, 40)) . "',
            titulo = '" . self::esc(mb_substr($titulo, 0, 200)) . "', tipo = '$tipo', norma = '" . self::esc(mb_substr(trim((string)($d['norma'] ?? '')), 0, 200)) . "',
            responsable = '" . self::esc(mb_substr(trim((string)($d['responsable'] ?? '')), 0, 150)) . "',
            fecha = " . ($fecha ? "'$fecha'" : 'NULL') . ", estado = '$estado', notas = '" . self::esc(mb_substr(trim((string)($d['notas'] ?? '')), 0, 5000)) . "'"
            . ($bloquea === null ? '' : ", bloquea = $bloquea");

        if ($id) {
            if (!self::plan($pid, $id)) return [false, 'El plan no existe', 0];
            self::db()->query("UPDATE calidad_planes SET $campos WHERE id = $id AND proyecto_id = $pid");
        } else {
            self::db()->query("INSERT INTO calidad_planes SET proyecto_id = $pid, $campos");
            $id = (int)self::db()->insert_id;
            $lineas = preg_split('/\R/', (string)($d['puntos'] ?? '')) ?: [];
            $orden = 0;
            foreach ($lineas as $linea) {
                $linea = trim($linea);
                if ($linea === '') continue;
                [$desc, $criterio] = array_pad(explode('|', $linea, 2), 2, '');
                self::db()->query("INSERT INTO calidad_puntos SET plan_id = $id, orden = " . (++$orden)
                    . ", descripcion = '" . self::esc(mb_substr(trim($desc), 0, 500)) . "', criterio = '" . self::esc(mb_substr(trim($criterio), 0, 300)) . "'");
            }
        }
        self::recalcular($id);
        self::$cacheBloqueos = [];
        return [true, 'Plan guardado', $id];
    }

    /** Agrega un punto a un plan existente */
    public static function agregarPunto(int $pid, int $planId, string $descripcion, string $criterio = ''): bool
    {
        $plan = self::plan($pid, $planId);
        $descripcion = trim($descripcion);
        if (!$plan || $descripcion === '') return false;
        $orden = (int)(self::filas("SELECT COALESCE(MAX(orden), 0) n FROM calidad_puntos WHERE plan_id = $planId")[0]['n'] ?? 0) + 1;
        self::db()->query("INSERT INTO calidad_puntos SET plan_id = $planId, orden = $orden,
            descripcion = '" . self::esc(mb_substr($descripcion, 0, 500)) . "',
            criterio = '" . self::esc(mb_substr(trim($criterio), 0, 300)) . "'");
        self::recalcular($planId);
        self::$cacheBloqueos = [];
        return true;
    }

    /** Marca un punto (y recalcula el estado del plan) */
    public static function marcarPunto(int $pid, int $puntoId, string $resultado, string $observacion, string $responsable = '', bool $resuelto = false): bool
    {
        if (!isset(self::RESULTADOS[$resultado])) return false;
        $p = self::filas("SELECT p.*, c.id plan, c.proyecto_id FROM calidad_puntos p JOIN calidad_planes c ON c.id = p.plan_id
            WHERE p.id = $puntoId AND c.proyecto_id = $pid")[0] ?? null;
        if (!$p) return false;
        $fecha = $resultado === 'pendiente' ? 'NULL' : "'" . date('Y-m-d') . "'";
        self::db()->query("UPDATE calidad_puntos SET resultado = '$resultado', fecha = $fecha,
            observacion = '" . self::esc(mb_substr(trim($observacion), 0, 1000)) . "',
            responsable = '" . self::esc(mb_substr(trim($responsable), 0, 150)) . "',
            resuelto = " . ($resuelto ? 1 : 0) . " WHERE id = $puntoId");
        self::recalcular((int)$p['plan']);
        self::$cacheBloqueos = [];
        return true;
    }

    /** El estado del plan sale de sus puntos: una no conformidad abierta manda */
    private static function recalcular(int $planId): void
    {
        $p = self::filas("SELECT resultado, resuelto FROM calidad_puntos WHERE plan_id = $planId");
        if (!$p) return;
        $nc = 0; $ok = 0; $hechos = 0;
        foreach ($p as $x) {
            if ($x['resultado'] === 'no_cumple') {
                // Una no conformidad resuelta ya no bloquea el plan: cuenta como punto atendido
                if ((int)$x['resuelto']) $ok++; else $nc++;
            } elseif ($x['resultado'] === 'cumple' || $x['resultado'] === 'na') {
                $ok++;
            }
            if ($x['resultado'] !== 'pendiente') $hechos++;
        }
        $estado = $nc ? 'no_conforme' : ($hechos === 0 ? 'pendiente' : ($ok === count($p) ? 'conforme' : 'en_proceso'));
        self::db()->query("UPDATE calidad_planes SET estado = '$estado' WHERE id = $planId AND estado <> 'cerrado'");
    }

    /** Puntos con no conformidad abierta (para la lista y el resumen) */
    public static function noConformidades(int $pid): array
    {
        return self::filas("SELECT p.*, c.titulo plan_titulo, c.codigo plan_codigo, a.codigo act_codigo, a.nombre act_nombre
            FROM calidad_puntos p JOIN calidad_planes c ON c.id = p.plan_id LEFT JOIN actividades a ON a.id = c.actividad_id
            WHERE c.proyecto_id = $pid AND p.resultado = 'no_cumple' AND p.resuelto = 0
            ORDER BY p.fecha, p.id");
    }

    public static function resumen(int $pid): array
    {
        $c = self::filas("SELECT
            COUNT(*) total,
            SUM(estado = 'pendiente') pendiente, SUM(estado = 'en_proceso') en_proceso,
            SUM(estado = 'conforme') conforme, SUM(estado = 'no_conforme') no_conforme,
            SUM(estado = 'cerrado') cerrado
            FROM calidad_planes WHERE proyecto_id = $pid")[0] ?? [];
        $p = self::filas("SELECT
            COUNT(*) total,
            SUM(p.resultado = 'pendiente') pendiente, SUM(p.resultado = 'cumple') cumple,
            SUM(p.resultado = 'no_cumple' AND p.resuelto = 0) nc
            FROM calidad_puntos p JOIN calidad_planes c ON c.id = p.plan_id WHERE c.proyecto_id = $pid")[0] ?? [];
        return [
            'planes' => (int)($c['total'] ?? 0), 'pendiente' => (int)($c['pendiente'] ?? 0), 'en_proceso' => (int)($c['en_proceso'] ?? 0),
            'conforme' => (int)($c['conforme'] ?? 0), 'no_conforme' => (int)($c['no_conforme'] ?? 0), 'cerrado' => (int)($c['cerrado'] ?? 0),
            'puntos' => (int)($p['total'] ?? 0), 'puntos_pendientes' => (int)($p['pendiente'] ?? 0), 'puntos_cumplen' => (int)($p['cumple'] ?? 0),
            'nc' => (int)($p['nc'] ?? 0),
        ];
    }

    // ---------------------------------------------------------------- compuerta de avance

    /**
     * ¿Puede la actividad subir su avance? Devuelve null si no hay nada que la detenga, o el
     * bloqueo con su tope: una no conformidad abierta lo congela y un punto pendiente lo topa en 99 %.
     * Una liberación firmada por el dueño/admin levanta el tope hasta el porcentaje autorizado.
     */
    public static function bloqueo(int $pid, int $actividadId, int $progresoActual = 0): ?array
    {
        $b = self::bloqueosProyecto($pid)[$actividadId] ?? null;
        if (!$b) return null;
        $b['tope'] = self::tope($b, $progresoActual);
        $b['progreso'] = $progresoActual;
        return $b['tope'] >= 100 ? null : $b;
    }

    /** Tope de avance de un bloqueo: con NC abierta no sube nada; con puntos pendientes, 99 %. */
    public static function tope(array $bloqueo, int $progresoActual): int
    {
        if (isset($bloqueo['tope'])) return (int)$bloqueo['tope'];   // liberación
        return $bloqueo['nivel'] === 'nc' ? max(0, min(100, $progresoActual)) : 99;
    }

    /**
     * Bloqueos de todas las actividades del proyecto en dos consultas (para listas, Gantt y el parte diario).
     * @return array<int, array> actividad_id => ['nivel' => 'nc'|'pendiente', 'puntos' => n, 'nc' => n, 'plan_id', 'plan_codigo', 'motivo']
     */
    public static function bloqueosProyecto(int $pid): array
    {
        if (isset(self::$cacheBloqueos[$pid])) return self::$cacheBloqueos[$pid];
        $out = [];
        $planes = self::filas("SELECT c.actividad_id, c.id plan_id, c.codigo, c.titulo,
                SUM(p.resultado = 'pendiente') pend, SUM(p.resultado = 'no_cumple' AND p.resuelto = 0) nc
            FROM calidad_planes c JOIN calidad_puntos p ON p.plan_id = c.id
            WHERE c.proyecto_id = $pid AND c.bloquea = 1 AND c.estado <> 'cerrado' AND c.actividad_id IS NOT NULL
            GROUP BY c.id");
        $liberadas = [];
        foreach (self::filas("SELECT * FROM calidad_liberaciones WHERE proyecto_id = $pid ORDER BY id DESC") as $l) {
            $aid = (int)$l['actividad_id'];
            if (isset($liberadas[$aid])) continue;   // solo la más reciente
            $liberadas[$aid] = $l;
        }
        foreach ($planes as $p) {
            $aid = (int)$p['actividad_id'];
            $nc = (int)$p['nc'];
            $pend = (int)$p['pend'];
            if (!$nc && !$pend) continue;
            $nivel = $nc ? 'nc' : 'pendiente';
            $actual = $out[$aid] ?? null;
            if ($actual && ($actual['nivel'] === 'nc' || $nivel !== 'nc')) {
                // ya hay un bloqueo igual o más fuerte: solo se acumulan los puntos
                $out[$aid]['puntos'] += $pend;
                $out[$aid]['nc'] += $nc;
                continue;
            }
            $out[$aid] = [
                'nivel' => $nivel, 'puntos' => $pend, 'nc' => $nc,
                'plan_id' => (int)$p['plan_id'], 'plan_codigo' => trim((string)$p['codigo']),
                'motivo' => self::motivoBloqueo($nivel, $p, $pend, $nc),
            ];
        }
        // No conformidades formales abiertas (calidad_nc) congelan igual que un punto no cumple
        foreach (self::filas("SELECT actividad_id, COUNT(*) n, MIN(codigo) codigo FROM calidad_nc
            WHERE proyecto_id = $pid AND estado IN ('abierta','en_correccion') AND actividad_id IS NOT NULL
            GROUP BY actividad_id") as $f) {
            $aid = (int)$f['actividad_id'];
            $n = (int)$f['n'];
            if (!$n) continue;
            if (isset($out[$aid])) {
                $out[$aid]['nc'] += $n;
                $out[$aid]['nivel'] = 'nc';
                $out[$aid]['motivo'] = 'Calidad detuvo el avance: ' . $n . ' no conformidad(es) formal(es) abierta(s).';
            } else {
                $out[$aid] = ['nivel' => 'nc', 'puntos' => 0, 'nc' => $n, 'plan_id' => 0, 'plan_codigo' => '',
                    'motivo' => 'Calidad detuvo el avance: ' . $n . ' no conformidad(es) formal(es) abierta(s).'];
            }
        }

        // La liberación caduca si apareció una no conformidad nueva desde que se firmó
        foreach ($liberadas as $aid => $l) {
            if (!isset($out[$aid])) continue;
            if ((int)$l['nc_snapshot'] < $out[$aid]['nc']) continue;
            $out[$aid] = [
                'nivel' => 'liberada', 'tope' => max(0, min(100, (int)$l['progreso_autorizado'])),
                'puntos' => $out[$aid]['puntos'], 'nc' => $out[$aid]['nc'],
                'plan_id' => (int)$l['plan_id'], 'plan_codigo' => $out[$aid]['plan_codigo'],
                'liberada' => true, 'liberada_por' => (string)$l['usuario'], 'liberada_motivo' => (string)$l['motivo'],
                'motivo' => 'Avance liberado por ' . $l['usuario'] . ' hasta ' . (int)$l['progreso_autorizado'] . ' %',
            ];
        }
        return self::$cacheBloqueos[$pid] = $out;
    }

    private static function motivoBloqueo(string $nivel, array $plan, int $pend, int $nc): string
    {
        $nombre = trim(($plan['codigo'] ? $plan['codigo'] . ' · ' : '') . $plan['titulo']);
        if ($nivel === 'nc') {
            return 'Calidad detuvo el avance: ' . $nombre . ' tiene ' . $nc . ' no conformidad(es) abierta(s).';
        }
        return 'El plan de calidad ' . $nombre . ' tiene ' . $pend . ' punto(s) de inspección pendiente(s): no se puede cerrar la actividad.';
    }

    /** ¿Puede este usuario firmar una liberación de avance? Admin o dueño del proyecto. */
    public static function puedeLiberar(\Model\Proyecto $proyecto): bool
    {
        return is_admin() || es_propietario($proyecto);
    }

    /** Actividades detenidas por calidad, con el % en que están y su tope */    public static function detenidas(int $pid): array
    {
        $filas = [];
        foreach (self::bloqueosProyecto($pid) as $aid => $b) {
            $a = self::filas("SELECT a.id, a.codigo, a.nombre, a.progreso, a.hito, p.nombre partida
                FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $aid")[0] ?? null;
            if (!$a || (int)$a['hito']) continue;
            $b['tope'] = self::tope($b, (int)$a['progreso']);
            if ($b['tope'] >= 100) continue;   // ya está cerrada: el candado no tiene nada que detener
            $b['actividad'] = $a;
            $filas[] = $b;
        }
        usort($filas, fn($x, $y) => strcmp($x['actividad']['codigo'], $y['actividad']['codigo']));
        return $filas;
    }

    /** Liberaciones vigentes del proyecto (o de una actividad), de la más reciente a la más antigua */
    public static function liberaciones(int $pid, int $actividadId = 0): array
    {
        $w = $actividadId ? " AND l.actividad_id = $actividadId" : '';
        $filas = self::filas("SELECT l.*, a.codigo act_codigo, a.nombre act_nombre
            FROM calidad_liberaciones l LEFT JOIN actividades a ON a.id = l.actividad_id
            WHERE l.proyecto_id = $pid$w ORDER BY l.id DESC LIMIT 100");
        // Una liberación caduca si apareció una no conformidad nueva en esa actividad
        $nc = [];
        foreach (self::filas("SELECT c.actividad_id, COUNT(*) n FROM calidad_puntos p JOIN calidad_planes c ON c.id = p.plan_id
            WHERE c.proyecto_id = $pid AND p.resultado = 'no_cumple' AND p.resuelto = 0 GROUP BY c.actividad_id") as $f) {
            $nc[(int)$f['actividad_id']] = (int)$f['n'];
        }
        foreach ($filas as &$l) $l['vigente'] = (int)$l['nc_snapshot'] >= ($nc[(int)$l['actividad_id']] ?? 0);
        return $filas;
    }

    /**
     * Firma una liberación de avance para una actividad detenida.
     * No conformidades abiertas al momento quedan cubiertas; si aparece una nueva, el candado vuelve.
     */
    public static function liberar(int $pid, int $actividadId, int $tope, string $motivo): array
    {
        $pid = (int)$pid;
        $actividadId = (int)$actividadId;
        $tope = max(1, min(100, (int)$tope));
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo));
        if (mb_strlen($motivo) < 10) return [false, 'Escribe el motivo de la liberación (mínimo 10 caracteres)'];
        $act = self::filas("SELECT a.id FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE a.id = $actividadId AND p.proyecto_id = $pid")[0] ?? null;
        if (!$act) return [false, 'La actividad no pertenece al proyecto'];
        $b = self::bloqueosProyecto($pid)[$actividadId] ?? null;
        if (!$b) return [false, 'Esa actividad no está detenida por calidad'];
        $plan = (int)($b['plan_id'] ?? 0);
        $usuario = (string)(usuario_actual()['nombre'] ?? '');
        self::db()->query("INSERT INTO calidad_liberaciones (proyecto_id, actividad_id, plan_id, progreso_autorizado, nc_snapshot, motivo, usuario)
            VALUES ($pid, $actividadId, " . ($plan ?: 'NULL') . ", $tope, " . (int)$b['nc'] . ", '" . self::esc(mb_substr($motivo, 0, 500)) . "', '" . self::esc(mb_substr($usuario, 0, 150)) . "')");
        try {
            \Classes\Auditoria::registrar($pid, 'calidad', 'liberar_avance', 'editar',
                'Liberó el avance de la actividad ' . $actividadId . ' hasta ' . $tope . ' % · ' . mb_substr($motivo, 0, 200),
                null, null, $actividadId, 'Actividad');
        } catch (\Throwable $e) {
        }
        self::$cacheBloqueos = [];
        return [true, 'Avance liberado hasta ' . $tope . ' %'];
    }

    /** Quita una liberación; el candado vuelve a aplicar desde el siguiente guardado */
    public static function revocarLiberacion(int $pid, int $id): bool
    {
        $l = self::filas("SELECT * FROM calidad_liberaciones WHERE id = " . (int)$id . " AND proyecto_id = $pid")[0] ?? null;
        if (!$l) return false;
        self::db()->query("DELETE FROM calidad_liberaciones WHERE id = " . (int)$id . " AND proyecto_id = $pid");
        try {
            \Classes\Auditoria::registrar($pid, 'calidad', 'revocar_liberacion', 'eliminar',
                'Revocó la liberación de avance de la actividad ' . (int)$l['actividad_id'], null, null, (int)$l['actividad_id'], 'Actividad');
        } catch (\Throwable $e) {
        }
        self::$cacheBloqueos = [];
        return true;
    }

    public static function eliminar(int $pid, int $id): bool
    {
        if (!self::plan($pid, $id)) return false;
        self::db()->query("DELETE FROM calidad_planes WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    private static function fecha($v): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', (string)$v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }
}
