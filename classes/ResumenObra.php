<?php

namespace Classes;

use Model\Proyecto;

/**
 * Resumen de la obra cada mañana (opción «Resumen de la obra» de Integraciones, proyectos.resumen_matutino):
 * a la hora RESUMEN_HORA (6 por defecto) manda por Telegram —evento resumen.matutino, que cada destino activa
 * con su casilla— un resumen con el clima de hoy, las actividades de hoy y atrasadas, restricciones urgentes,
 * punch grave, RFI pendientes y el semáforo. Si el día no trae nada relevante, no manda nada.
 * Se ejecuta con cron (bin/resumen_programado.php) o, sin cron, al terminar la primera petición con sesión pasada
 * la hora (mismo patrón que ClimaAuto). Cada proyecto se resume una sola vez al día (proyectos.resumen_ultimo).
 */
class ResumenObra
{
    use AccesoDb;

    private const MAX_LISTA_HOY = 6;
    private const MAX_LISTA_ATRASADAS = 4;

    public static function hora(): int
    {
        $h = Telegram::env('RESUMEN_HORA');
        return $h !== '' && ctype_digit($h) && (int)$h <= 23 ? (int)$h : 6;
    }

    // ---------- cuándo toca ----------

    private static function marca(): string
    {
        return __DIR__ . '/../storage/resumen_obra.json';
    }

    private static function leerMarca(): array
    {
        $j = is_file(self::marca()) ? json_decode((string)@file_get_contents(self::marca()), true) : null;
        return is_array($j) ? $j : [];
    }

    /** ¿Ya pasó la hora y aún no se hace la corrida de hoy? (un reintento cada 30 min si falló) */
    public static function pendiente(): bool
    {
        if (!Telegram::configurado()) return false;
        if ((int)date('G') < self::hora()) return false;
        $m = self::leerMarca();
        return ($m['ok'] ?? '') !== date('Y-m-d') && time() - (int)($m['intento'] ?? 0) > 1800;
    }

    /** Corre si toca (o si se fuerza). Devuelve null si no tocaba o ya había otra corrida en marcha */
    public static function ejecutarSiToca(bool $forzar = false): ?array
    {
        $lock = @fopen(self::marca() . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return null;
        try {
            if (!$forzar && !self::pendiente()) return null;
            $r = self::ejecutar();
            $fallos = count(array_filter($r, fn($x) => !$x['ok']));
            @file_put_contents(self::marca(), json_encode(['intento' => time(), 'ok' => $fallos ? (self::leerMarca()['ok'] ?? '') : date('Y-m-d')]));
            return $r;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<int, array{ok: bool, mensaje: string}> por proyecto con el resumen activo */
    public static function ejecutar(): array
    {
        $r = [];
        foreach (self::filas('SELECT id FROM proyectos WHERE resumen_matutino = 1') as $f) {
            $p = Proyecto::find((int)$f['id']);
            if ($p) $r[(int)$p->id] = self::procesar($p);
        }
        return $r;
    }

    // ---------- por proyecto ----------

    public static function activo(int $pid): bool
    {
        return (bool)(self::filas("SELECT resumen_matutino FROM proyectos WHERE id = $pid")[0]['resumen_matutino'] ?? 0);
    }

    public static function cambiarActivo(int $pid, bool $activo): void
    {
        self::db()->query('UPDATE proyectos SET resumen_matutino = ' . ($activo ? 1 : 0) . " WHERE id = $pid");
    }

    /** Manda el resumen de hoy (una vez al día; con $forzar se repite, para «Probar ahora»).
     *  @return array{ok: bool, mensaje: string} */
    public static function procesar(Proyecto $p, bool $forzar = false): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $est = self::filas("SELECT resumen_ultimo FROM proyectos WHERE id = $pid")[0] ?? ['resumen_ultimo' => null];
        if (!$forzar && $est['resumen_ultimo'] === $hoy) return ['ok' => true, 'mensaje' => 'El resumen de hoy ya se envió.'];
        if (!self::tieneDestinoConEvento($pid)) {
            return ['ok' => true, 'mensaje' => 'Ningún destino de este proyecto tiene marcado el aviso «Resumen de la obra cada mañana» (márcalo en Avisos de un proyecto, arriba).'];
        }
        $texto = self::texto($p);
        self::db()->query("UPDATE proyectos SET resumen_ultimo = '$hoy' WHERE id = $pid");   // aunque hoy no haya novedad, no se vuelve a evaluar
        if ($texto === '') return ['ok' => true, 'mensaje' => 'Hoy no hay nada relevante que resumir (sin actividades ni pendientes).'];
        Telegram::aviso($pid, 'resumen.matutino', ['texto' => $texto]);
        return ['ok' => true, 'mensaje' => 'Resumen de hoy en cola de envío.'];
    }

    /** ¿Al menos un destino activo pidió este evento? */
    private static function tieneDestinoConEvento(int $pid): bool
    {
        return (bool)self::filas("SELECT 1 FROM telegram_destinos WHERE proyecto_id = $pid AND activo = 1 AND FIND_IN_SET('resumen.matutino', eventos) LIMIT 1");
    }

    /** Texto del resumen; vacío si el día no trae nada relevante */
    public static function texto(Proyecto $p): string
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $en7 = date('Y-m-d', strtotime('+7 days'));
        $h = fn($t) => Telegram::h($t);
        $cuenta = fn(string $sql) => (int)(self::filas($sql)[0]['n'] ?? 0);
        $actividades = fn(string $donde) => self::filas("SELECT a.codigo, a.nombre, a.progreso, a.fecha_fin FROM actividades a JOIN partidas pa ON pa.id = a.partida_id
            WHERE pa.proyecto_id = $pid AND a.hito = 0 AND a.progreso < 100 AND $donde ORDER BY a.fecha_fin, a.id");

        $hoyN = $cuenta("SELECT COUNT(*) n FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND a.hito = 0 AND a.progreso < 100 AND a.fecha_inicio <= '$hoy' AND a.fecha_fin >= '$hoy'");
        $atrN = $cuenta("SELECT COUNT(*) n FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid AND a.hito = 0 AND a.progreso < 100 AND a.fecha_fin < '$hoy'");
        $restUrg = $cuenta("SELECT COUNT(DISTINCT r.id) n FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id
            WHERE pa.proyecto_id = $pid AND r.estado = 'activa' AND a.progreso < 100 AND a.fecha_inicio <= '$en7'");
        $punchN = $cuenta("SELECT COUNT(*) n FROM punch_items WHERE proyecto_id = $pid AND estado IN ('abierto','en_proceso') AND severidad IN ('alta','critica')");
        $rfi = Rfi::disponible() ? Rfi::resumen($pid) : ['abierto' => 0, 'vencidos' => 0, 'bloqueando' => 0];

        if (!$hoyN && !$atrN && !$restUrg && !$punchN && !($rfi['abierto'] ?? 0)) return '';

        $t = '🌅 <b>Resumen de la mañana · ' . ClimaAuto::fechaLarga($hoy) . "</b>\n";

        // Clima de hoy (solo si ya hay datos guardados: no se consulta la red aquí)
        if ($d = self::filas("SELECT * FROM clima_dias WHERE proyecto_id = $pid AND fecha = '$hoy'")[0] ?? null) {
            $t .= "\n🌤 " . $h(ClimaAuto::resumen($d)) . (Clima::condicion($d) ? ' ⚠ puede afectar el exterior' : '');
        }

        if ($hoyN) {
            $t .= "\n\n<b>Actividades de hoy ($hoyN)</b>";
            $i = 0;
            foreach ($actividades("a.fecha_inicio <= '$hoy' AND a.fecha_fin >= '$hoy'") as $a) {
                if (++$i > self::MAX_LISTA_HOY) { $t .= "\n… y " . max(0, $hoyN - self::MAX_LISTA_HOY) . ' más'; break; }
                $t .= "\n• " . $h(trim($a['codigo'] . ' ' . $a['nombre'])) . ' — <b>' . (int)$a['progreso'] . '%</b>';
            }
        }
        if ($atrN) {
            $t .= "\n\n<b>⚠ Atrasadas ($atrN)</b>";
            $i = 0;
            foreach ($actividades("a.fecha_fin < '$hoy'") as $a) {
                if (++$i > self::MAX_LISTA_ATRASADAS) { $t .= "\n… y " . max(0, $atrN - self::MAX_LISTA_ATRASADAS) . ' más'; break; }
                $t .= "\n• " . $h(trim($a['codigo'] . ' ' . $a['nombre'])) . ' — <b>' . (int)$a['progreso'] . '%</b> (debía terminar ' . date('d/m', strtotime((string)$a['fecha_fin'])) . ')';
            }
        }
        if ($restUrg) $t .= "\n\n🚧 <b>$restUrg restricción(es)</b> afectan actividades que inician en 7 días o ya están en curso.";
        if ($punchN) $t .= "\n🔧 <b>$punchN pendiente(s) grave(s)</b> del punch list.";
        if ($rfi['abierto'] ?? 0) $t .= "\n❓ <b>{$rfi['abierto']} RFI abierto(s)</b>" . ($rfi['vencidos'] ? " · {$rfi['vencidos']} vencido(s)" : '') . '.';

        $sem = Salud::semaforo($p);
        $ico = ['ok' => '🟢', 'aviso' => '🟠', 'critico' => '🔴'];
        $t .= "\n\n" . $ico[$sem['nivel']] . ' Semáforo: <b>' . $h(Salud::NIVELES[$sem['nivel']]) . '</b>';
        $t .= "\n<i>Reporta avance con /avance o mira el detalle en la web.</i>";
        return mb_substr($t, 0, 3900);
    }
}
