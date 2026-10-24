<?php

namespace Classes;

/**
 * RFI (solicitudes de información): responsable, fecha límite y la actividad del programa que bloquean mientras estén abiertos.
 * Estados: abierto → respondido → cerrado. Un RFI abierto sobre una actividad pendiente la marca «en riesgo» (Salud) y vence al pasar su fecha límite.
 * Cada RFI lleva el chip de revisión (✅ ⚠ ❌) de Revisiones (tipo `rfi`).
 */
class Rfi
{
    use AccesoDb;

    public const ESTADOS = ['abierto' => 'Abierto', 'respondido' => 'Respondido', 'cerrado' => 'Cerrado'];
    public const PRIORIDADES = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query("SELECT 1 FROM rfis LIMIT 1");
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function siguienteNumero(int $pid): string
    {
        $n = (int)(self::filas("SELECT COUNT(*) n FROM rfis WHERE proyecto_id = $pid")[0]['n'] ?? 0);
        do {
            $num = 'RFI-' . str_pad((string)(++$n), 3, '0', STR_PAD_LEFT);
        } while (self::filas("SELECT id FROM rfis WHERE proyecto_id = $pid AND numero = '" . self::esc($num) . "'"));
        return $num;
    }

    /** Lista con la actividad que bloquean y los días de retraso (negativo = faltan) */
    public static function lista(int $pid, string $estado = '', bool $soloVencidos = false): array
    {
        $w = isset(self::ESTADOS[$estado]) ? " AND r.estado = '$estado'" : '';
        if ($soloVencidos) $w .= " AND r.estado = 'abierto' AND r.fecha_limite < CURDATE()";
        $rows = self::filas("SELECT r.*, a.codigo act_codigo, a.nombre act_nombre, a.fecha_inicio act_inicio, a.progreso act_progreso, DATEDIFF(CURDATE(), r.fecha_limite) dias_retraso
            FROM rfis r LEFT JOIN actividades a ON a.id = r.actividad_id WHERE r.proyecto_id = $pid$w
            ORDER BY FIELD(r.estado, 'abierto', 'respondido', 'cerrado'), r.fecha_limite IS NULL, r.fecha_limite, r.id DESC");
        foreach ($rows as &$r) $r['vencido'] = $r['estado'] === 'abierto' && $r['fecha_limite'] && (int)$r['dias_retraso'] > 0;
        return $rows;
    }

    public static function resumen(int $pid): array
    {
        $r = ['total' => 0, 'abierto' => 0, 'respondido' => 0, 'cerrado' => 0, 'vencidos' => 0, 'bloqueando' => 0];
        foreach (self::filas("SELECT estado, COUNT(*) n FROM rfis WHERE proyecto_id = $pid GROUP BY estado") as $f) {
            $r[$f['estado']] = (int)$f['n'];
            $r['total'] += (int)$f['n'];
        }
        $r['vencidos'] = (int)(self::filas("SELECT COUNT(*) n FROM rfis WHERE proyecto_id = $pid AND estado = 'abierto' AND fecha_limite < CURDATE()")[0]['n'] ?? 0);
        $r['bloqueando'] = (int)(self::filas("SELECT COUNT(*) n FROM rfis r JOIN actividades a ON a.id = r.actividad_id WHERE r.proyecto_id = $pid AND r.estado = 'abierto' AND a.progreso < 100")[0]['n'] ?? 0);
        return $r;
    }

    /** @return string|null mensaje de error */
    public static function guardar(int $pid, array $d, string $usuario): ?string
    {
        $asunto = mb_substr(trim((string)($d['asunto'] ?? '')), 0, 200);
        if ($asunto === '') return 'El asunto es obligatorio';
        $fecha = self::fecha($d['fecha'] ?? '') ?? date('Y-m-d');
        $limite = self::fecha($d['fecha_limite'] ?? '');
        if ($limite && $limite < $fecha) return 'La fecha límite no puede ser anterior a la del RFI';
        $act = (int)($d['actividad_id'] ?? 0);
        if ($act && !self::filas("SELECT a.id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $act AND p.proyecto_id = $pid")) $act = 0;
        $prio = isset(self::PRIORIDADES[$d['prioridad'] ?? '']) ? $d['prioridad'] : 'media';
        $estado = isset(self::ESTADOS[$d['estado'] ?? '']) ? $d['estado'] : 'abierto';
        $resp = mb_substr(trim((string)($d['respuesta'] ?? '')), 0, 5000);
        $id = (int)($d['id'] ?? 0);
        $antes = $id ? (self::filas("SELECT * FROM rfis WHERE id = $id AND proyecto_id = $pid")[0] ?? null) : null;
        if ($id && !$antes) return 'El RFI no existe';
        // La fecha de respuesta se anota al pasar a respondido/cerrado
        $respFecha = $estado === 'abierto' ? 'NULL' : "'" . ($antes['fecha_respuesta'] ?? date('Y-m-d')) . "'";
        if ($estado !== 'abierto' && !$antes) $respFecha = "'" . date('Y-m-d') . "'";
        $v = fn($x) => "'" . self::esc($x) . "'";
        $campos = "asunto = {$v($asunto)}, pregunta = {$v(mb_substr(trim((string)($d['pregunta'] ?? '')), 0, 5000))}, respuesta = {$v($resp)}, responsable = {$v(mb_substr(trim((string)($d['responsable'] ?? '')), 0, 150))},
            solicitante = {$v(mb_substr(trim((string)($d['solicitante'] ?? '')) ?: $usuario, 0, 150))}, prioridad = '$prio', estado = '$estado', fecha = '$fecha',
            fecha_limite = " . ($limite ? "'$limite'" : 'NULL') . ", fecha_respuesta = $respFecha, actividad_id = " . ($act ?: 'NULL');
        if ($id) {
            HistorialCambios::escribir('rfis', 'modificado', $id, fn() => self::db()->query("UPDATE rfis SET $campos WHERE id = $id"));
            Webhooks::emitir($pid, 'rfi.actualizado', ['id' => $id, 'asunto' => $asunto, 'estado' => $estado, 'prioridad' => $prio]);
        } else {
            $numero = mb_substr(trim((string)($d['numero'] ?? '')), 0, 30) ?: self::siguienteNumero($pid);
            if (self::filas("SELECT id FROM rfis WHERE proyecto_id = $pid AND numero = {$v($numero)}")) return 'Ya existe un RFI con ese número';
            HistorialCambios::escribir('rfis', 'creado', 0, fn() => self::db()->query("INSERT INTO rfis SET proyecto_id = $pid, numero = {$v($numero)}, $campos"));
            Webhooks::emitir($pid, 'rfi.creado', ['id' => (int)self::db()->insert_id, 'numero' => $numero, 'asunto' => $asunto, 'estado' => $estado, 'prioridad' => $prio, 'fecha_limite' => $limite]);
        }
        return null;
    }

    public static function cambiarEstado(int $pid, int $id, string $estado): bool
    {
        if (!isset(self::ESTADOS[$estado])) return false;
        $f = $estado === 'abierto' ? 'NULL' : "COALESCE(fecha_respuesta, CURDATE())";
        HistorialCambios::escribir('rfis', 'modificado', $id,
            fn() => self::db()->query("UPDATE rfis SET estado = '$estado', fecha_respuesta = $f WHERE id = $id AND proyecto_id = $pid"));
        Webhooks::emitir($pid, 'rfi.estado', ['id' => $id, 'estado' => $estado]);
        return self::db()->affected_rows >= 0;
    }

    public static function eliminar(int $pid, int $id): void
    {
        HistorialCambios::escribir('rfis', 'eliminado', $id, fn() => self::db()->query("DELETE FROM rfis WHERE id = $id AND proyecto_id = $pid"));
        self::db()->query("DELETE FROM revisiones WHERE proyecto_id = $pid AND tipo = 'rfi' AND ref = '$id'");
    }

    private static function fecha($f): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', (string)$f);
        return $d && $d->format('Y-m-d') === $f ? $f : null;
    }
}
