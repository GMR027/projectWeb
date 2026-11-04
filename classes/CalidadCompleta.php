<?php

namespace Classes;

/**
 * Calidad completa: ensayos de laboratorio, no conformidades con causa/acción/costo
 * y seguimiento de acciones. La NC abierta alimenta la compuerta de avance.
 */
class CalidadCompleta
{
    use AccesoDb;

    public const VEREDICTOS = ['pendiente' => 'Pendiente', 'aprobado' => 'Aprobado', 'rechazado' => 'Rechazado'];
    public const NC_ESTADOS = ['abierta' => 'Abierta', 'en_correccion' => 'En corrección', 'cerrada' => 'Cerrada'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM calidad_ensayos LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ---------- ensayos ----------

    public static function ensayos(int $pid, string $veredicto = ''): array
    {
        $w = isset(self::VEREDICTOS[$veredicto]) ? " AND e.veredicto = '$veredicto'" : '';
        return self::filas("SELECT e.*, a.codigo act_codigo, a.nombre act_nombre, c.codigo plan_codigo, c.titulo plan_titulo, ar.nombre archivo_nombre
            FROM calidad_ensayos e
            LEFT JOIN actividades a ON a.id = e.actividad_id
            LEFT JOIN calidad_planes c ON c.id = e.plan_id
            LEFT JOIN archivos ar ON ar.id = e.archivo_id
            WHERE e.proyecto_id = $pid$w ORDER BY e.fecha_resultado IS NULL, e.fecha_resultado DESC, e.id DESC");
    }

    public static function guardarEnsayo(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $tipo = mb_substr(trim((string)($d['tipo'] ?? '')), 0, 100);
        if ($tipo === '') return [false, 'Escribe el tipo de ensayo', $id];
        $actividad = (int)($d['actividad_id'] ?? 0);
        if ($actividad && !self::filas("SELECT 1 FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $actividad AND p.proyecto_id = $pid")) $actividad = 0;
        $plan = (int)($d['plan_id'] ?? 0);
        if ($plan && !self::filas("SELECT id FROM calidad_planes WHERE id = $plan AND proyecto_id = $pid")) $plan = 0;
        $archivo = (int)($d['archivo_id'] ?? 0);
        if ($archivo && !self::filas("SELECT id FROM archivos WHERE id = $archivo AND proyecto_id = $pid")) $archivo = 0;
        $veredicto = isset(self::VEREDICTOS[$d['veredicto'] ?? '']) ? $d['veredicto'] : 'pendiente';
        $v = fn($x) => "'" . self::esc($x) . "'";
        $campos = "actividad_id = " . ($actividad ?: 'NULL') . ", plan_id = " . ($plan ?: 'NULL') . ",
            tipo = {$v($tipo)}, muestra = {$v(mb_substr((string)($d['muestra'] ?? ''), 0, 150))},
            ubicacion = {$v(mb_substr((string)($d['ubicacion'] ?? ''), 0, 200))},
            fecha_toma = " . (self::fecha($d['fecha_toma'] ?? '') ? "'" . self::fecha($d['fecha_toma']) . "'" : 'NULL') . ",
            fecha_resultado = " . (self::fecha($d['fecha_resultado'] ?? '') ? "'" . self::fecha($d['fecha_resultado']) . "'" : 'NULL') . ",
            laboratorio = {$v(mb_substr((string)($d['laboratorio'] ?? ''), 0, 150))},
            norma = {$v(mb_substr((string)($d['norma'] ?? ''), 0, 200))},
            resultado = {$v(mb_substr((string)($d['resultado'] ?? ''), 0, 100))},
            veredicto = '$veredicto', archivo_id = " . ($archivo ?: 'NULL') . ",
            notas = {$v(mb_substr((string)($d['notas'] ?? ''), 0, 5000))}";
        if ($id) {
            if (!self::filas("SELECT id FROM calidad_ensayos WHERE id = $id AND proyecto_id = $pid")) return [false, 'El ensayo no existe', $id];
            self::db()->query("UPDATE calidad_ensayos SET $campos WHERE id = $id AND proyecto_id = $pid");
        } else {
            $usuario = self::esc(mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150));
            self::db()->query("INSERT INTO calidad_ensayos SET proyecto_id = $pid, usuario = '$usuario', $campos");
            $id = (int)self::db()->insert_id;
        }
        self::auditar($pid, 'guardar_ensayo', 'editar', 'Guardó el ensayo ' . $tipo . ' (' . $veredicto . ')', $id);
        return [true, 'Ensayo guardado', $id];
    }

    public static function eliminarEnsayo(int $pid, int $id): bool
    {
        if (!self::filas("SELECT id FROM calidad_ensayos WHERE id = $id AND proyecto_id = $pid")) return false;
        self::db()->query("DELETE FROM calidad_ensayos WHERE id = $id AND proyecto_id = $pid");
        return true;
    }

    // ---------- no conformidades ----------

    public static function ncs(int $pid, string $estado = ''): array
    {
        $w = isset(self::NC_ESTADOS[$estado]) ? " AND n.estado = '$estado'" : '';
        return self::filas("SELECT n.*, a.codigo act_codigo, a.nombre act_nombre, p.codigo partida_codigo, p.nombre partida,
                c.codigo plan_codigo, c.titulo plan_titulo,
                (SELECT COUNT(*) FROM calidad_nc_acciones x WHERE x.nc_id = n.id) acciones
            FROM calidad_nc n
            LEFT JOIN actividades a ON a.id = n.actividad_id
            LEFT JOIN partidas p ON p.id = a.partida_id
            LEFT JOIN calidad_planes c ON c.id = n.plan_id
            WHERE n.proyecto_id = $pid$w
            ORDER BY FIELD(n.estado, 'abierta', 'en_correccion', 'cerrada'), n.fecha_compromiso IS NULL, n.fecha_compromiso, n.id DESC");
    }

    public static function nc(int $pid, int $id): ?array
    {
        $n = self::filas("SELECT n.*, a.codigo act_codigo, a.nombre act_nombre, p.codigo partida_codigo, p.nombre partida
            FROM calidad_nc n LEFT JOIN actividades a ON a.id = n.actividad_id LEFT JOIN partidas p ON p.id = a.partida_id
            WHERE n.id = $id AND n.proyecto_id = $pid")[0] ?? null;
        if ($n) $n['acciones'] = self::acciones($id);
        return $n;
    }

    public static function acciones(int $ncId): array
    {
        return self::filas("SELECT * FROM calidad_nc_acciones WHERE nc_id = $ncId ORDER BY fecha, id");
    }

    public static function guardarNC(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $hallazgo = trim((string)($d['hallazgo'] ?? ''));
        if ($hallazgo === '') return [false, 'Describe el hallazgo', $id];
        $actividad = (int)($d['actividad_id'] ?? 0);
        if ($actividad && !self::filas("SELECT 1 FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $actividad AND p.proyecto_id = $pid")) $actividad = 0;
        $plan = (int)($d['plan_id'] ?? 0);
        if ($plan && !self::filas("SELECT id FROM calidad_planes WHERE id = $plan AND proyecto_id = $pid")) $plan = 0;
        $punto = (int)($d['punto_id'] ?? 0);
        if ($punto && !self::filas("SELECT id FROM calidad_puntos WHERE id = $punto")) $punto = 0;
        $estado = isset(self::NC_ESTADOS[$d['estado'] ?? '']) ? $d['estado'] : 'abierta';
        $costo = self::numero($d['costo'] ?? null) ?? 0;
        $codigo = mb_substr(trim((string)($d['codigo'] ?? '')), 0, 40) ?: self::siguienteNC($pid);
        $antes = (int)($d['evidencia_antes'] ?? 0);
        $despues = (int)($d['evidencia_despues'] ?? 0);
        if ($antes && !self::filas("SELECT id FROM archivos WHERE id = $antes AND proyecto_id = $pid")) $antes = 0;
        if ($despues && !self::filas("SELECT id FROM archivos WHERE id = $despues AND proyecto_id = $pid")) $despues = 0;
        $v = fn($x) => "'" . self::esc($x) . "'";
        $campos = "actividad_id = " . ($actividad ?: 'NULL') . ", plan_id = " . ($plan ?: 'NULL') . ", punto_id = " . ($punto ?: 'NULL') . ",
            codigo = {$v($codigo)}, hallazgo = {$v(mb_substr($hallazgo, 0, 5000))}, causa = {$v(mb_substr((string)($d['causa'] ?? ''), 0, 5000))},
            accion = {$v(mb_substr((string)($d['accion'] ?? ''), 0, 5000))}, responsable = {$v(mb_substr((string)($d['responsable'] ?? ''), 0, 150))},
            fecha_compromiso = " . (self::fecha($d['fecha_compromiso'] ?? '') ? "'" . self::fecha($d['fecha_compromiso']) . "'" : 'NULL') . ",
            costo = " . round(max(0, $costo), 2) . ", estado = '$estado',
            evidencia_antes = " . ($antes ?: 'NULL') . ", evidencia_despues = " . ($despues ?: 'NULL') . ",
            cerrada_en = " . ($estado === 'cerrada' ? 'NOW()' : 'NULL') . ",
            cerrada_por = " . ($estado === 'cerrada' ? $v((string)(usuario_actual()['nombre'] ?? '')) : "''");
        if ($id) {
            if (!self::filas("SELECT id FROM calidad_nc WHERE id = $id AND proyecto_id = $pid")) return [false, 'La no conformidad no existe', $id];
            self::db()->query("UPDATE calidad_nc SET $campos WHERE id = $id AND proyecto_id = $pid");
        } else {
            $usuario = self::esc(mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150));
            self::db()->query("INSERT INTO calidad_nc SET proyecto_id = $pid, usuario = '$usuario', $campos");
            $id = (int)self::db()->insert_id;
        }
        self::auditar($pid, 'guardar_nc', 'editar', 'Guardó la no conformidad ' . $codigo . ' (' . $estado . ')', $id);
        return [true, 'No conformidad guardada', $id];
    }

    public static function accion(int $pid, int $ncId, array $d): array
    {
        if (!self::filas("SELECT id FROM calidad_nc WHERE id = $ncId AND proyecto_id = $pid")) return [false, 'La NC no existe'];
        $accion = trim((string)($d['accion'] ?? ''));
        if ($accion === '') return [false, 'Describe la acción'];
        $fecha = self::fecha($d['fecha'] ?? '') ?: date('Y-m-d');
        $verificada = !empty($d['verificada']) ? 1 : 0;
        $v = fn($x) => "'" . self::esc($x) . "'";
        self::db()->query("INSERT INTO calidad_nc_acciones (nc_id, fecha, accion, responsable, verificada, notas)
            VALUES ($ncId, '$fecha', {$v(mb_substr($accion, 0, 5000))}, {$v(mb_substr((string)($d['responsable'] ?? ''), 0, 150))}, $verificada, {$v(mb_substr((string)($d['notas'] ?? ''), 0, 500))})");
        if (!empty($d['en_correccion'])) self::db()->query("UPDATE calidad_nc SET estado = 'en_correccion' WHERE id = $ncId AND proyecto_id = $pid AND estado = 'abierta'");
        return [true, 'Acción registrada'];
    }

    public static function cerrarNC(int $pid, int $id): array
    {
        if (!self::filas("SELECT id FROM calidad_nc WHERE id = $id AND proyecto_id = $pid")) return [false, 'La NC no existe'];
        $u = self::esc(mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150));
        self::db()->query("UPDATE calidad_nc SET estado = 'cerrada', cerrada_en = NOW(), cerrada_por = '$u' WHERE id = $id AND proyecto_id = $pid");
        self::auditar($pid, 'cerrar_nc', 'editar', 'Cerró la no conformidad ' . $id, $id);
        return [true, 'No conformidad cerrada'];
    }

    /** Envía el costo de la NC a Costos como costo real (una sola vez) */
    public static function enviarCosto(int $pid, int $id): array
    {
        $n = self::filas("SELECT n.*, a.partida_id FROM calidad_nc n LEFT JOIN actividades a ON a.id = n.actividad_id
            WHERE n.id = $id AND n.proyecto_id = $pid")[0] ?? null;
        if (!$n) return [false, 'La NC no existe'];
        if (!empty($n['movimiento_id'])) return [false, 'Ese costo ya se envió a Costos'];
        if ((float)$n['costo'] <= 0) return [false, 'La NC no tiene costo capturado'];
        $partida = (int)($n['partida_id'] ?? 0);
        $concepto = 'NC ' . ($n['codigo'] ?: '#' . $id) . ' · ' . mb_substr((string)$n['hallazgo'], 0, 180);
        $u = self::esc(mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150));
        $v = fn($x) => "'" . self::esc($x) . "'";
        self::db()->query("INSERT INTO costos_movimientos (proyecto_id, partida_id, tipo, fecha, concepto, referencia, monto, estado, usuario)
            VALUES ($pid, " . ($partida ?: 'NULL') . ", 'real', CURDATE(), {$v(mb_substr($concepto, 0, 255))}, {$v((string)$n['codigo'])}, " . (float)$n['costo'] . ", 'aprobada', '$u')");
        $mov = (int)self::db()->insert_id;
        self::db()->query("UPDATE calidad_nc SET movimiento_id = $mov WHERE id = $id AND proyecto_id = $pid");
        self::auditar($pid, 'costo_nc', 'editar', 'Envió a Costos el costo de la NC ' . ($n['codigo'] ?: $id), $id);
        return [true, 'Costo enviado a Costos'];
    }

    public static function resumen(int $pid): array
    {
        $ens = (int)(self::filas("SELECT COUNT(*) n FROM calidad_ensayos WHERE proyecto_id = $pid")[0]['n'] ?? 0);
        $rec = (int)(self::filas("SELECT COUNT(*) n FROM calidad_ensayos WHERE proyecto_id = $pid AND veredicto = 'rechazado'")[0]['n'] ?? 0);
        $nc = (int)(self::filas("SELECT COUNT(*) n FROM calidad_nc WHERE proyecto_id = $pid AND estado IN ('abierta','en_correccion')")[0]['n'] ?? 0);
        $costo = (float)(self::filas("SELECT COALESCE(SUM(costo), 0) n FROM calidad_nc WHERE proyecto_id = $pid AND estado IN ('abierta','en_correccion')")[0]['n'] ?? 0);
        return ['ensayos' => $ens, 'rechazados' => $rec, 'nc_abiertas' => $nc, 'costo_nc' => round($costo, 2)];
    }

    public static function siguienteNC(int $pid): string
    {
        $n = (int)(self::filas("SELECT COUNT(*) n FROM calidad_nc WHERE proyecto_id = $pid")[0]['n'] ?? 0) + 1;
        return 'NC-' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
    }

    private static function fecha($v): ?string
    {
        $v = trim((string)$v);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return null;
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $v : null;
    }

    private static function numero($v): ?float
    {
        if ($v === null || $v === '') return null;
        $t = str_replace([',', '$', ' '], '', (string)$v);
        return is_numeric($t) ? (float)$t : null;
    }

    private static function auditar(int $pid, string $evento, string $accion, string $resumen, int $id): void
    {
        try {
            \Classes\Auditoria::registrar($pid, 'calidad', $evento, $accion, $resumen, null, null, $id, 'Calidad');
        } catch (\Throwable $e) {
        }
    }
}
