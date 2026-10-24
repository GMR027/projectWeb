<?php

namespace Classes;

use Model\Bitacora;
use Model\Proyecto;

/**
 * Clima automático por proyecto (opción «Registrar el clima cada día» de Integraciones, proyectos.clima_auto):
 *  - Cada tarde (CLIMA_AUTO_HORA, 18 por defecto) anota en la bitácora el clima del día: máxima, mínima, lluvia y viento (Open-Meteo vía Clima).
 *  - Si el pronóstico de mañana trae lluvia o viento que frenan trabajos de exterior, avisa por Telegram (evento clima.alerta)
 *    con las actividades de exterior programadas para mañana.
 * Se ejecuta con el cron (bin/clima_programado.php) o, sin cron, al terminar la primera petición de una persona con sesión después de la hora.
 * Cada día se registra una sola vez por proyecto (clima_ultimo) y cada alerta una sola vez (clima_aviso).
 */
class ClimaAuto
{
    use AccesoDb;

    private const DEPARTAMENTO = 'Clima';
    private const MARCA = 'Clima automático';
    private const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    private const MESES = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public static function hora(): int
    {
        $h = Telegram::env('CLIMA_AUTO_HORA');
        return $h !== '' && ctype_digit($h) && (int)$h <= 23 ? (int)$h : 18;
    }

    /** Texto corto de un día: «máx 31° / mín 18° · lluvia 12 mm · viento 35 km/h (⚠ lluvia)» */
    public static function resumen(array $d): string
    {
        $c = Clima::condicion($d);
        return ($d['tmax'] !== null ? 'máx ' . round((float)$d['tmax']) . '°' : '') . ($d['tmin'] !== null ? ' / mín ' . round((float)$d['tmin']) . '°' : '')
            . ' · lluvia ' . rtrim(rtrim(number_format((float)$d['precip_mm'], 1, '.', ''), '0'), '.') . ' mm' . ($d['prob'] !== null ? ' (' . (int)$d['prob'] . '%)' : '')
            . ' · viento ' . round((float)$d['viento_kmh']) . ' km/h' . ($c ? " ⚠ $c" : '');
    }

    public static function fechaLarga(string $f): string
    {
        $t = strtotime($f);
        return self::DIAS[(int)date('w', $t)] . ' ' . (int)date('j', $t) . ' ' . self::MESES[(int)date('n', $t)];
    }

    // ---------- cuándo toca ----------

    private static function marca(): string
    {
        return __DIR__ . '/../storage/clima_auto.json';
    }

    private static function leerMarca(): array
    {
        $j = is_file(self::marca()) ? json_decode((string)@file_get_contents(self::marca()), true) : null;
        return is_array($j) ? $j : [];
    }

    /** ¿Ya pasó la hora y aún no se hizo la corrida de hoy? (un reintento cada 30 min si falló) */
    public static function pendiente(): bool
    {
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

    /** @return array<int, array{ok: bool, mensaje: string}> por proyecto con el clima automático activo */
    public static function ejecutar(): array
    {
        $r = [];
        foreach (self::filas('SELECT id FROM proyectos WHERE clima_auto = 1') as $f) {
            $p = Proyecto::find((int)$f['id']);
            if ($p) $r[(int)$p->id] = self::procesar($p);
        }
        return $r;
    }

    // ---------- un proyecto ----------

    public static function activo(int $pid): bool
    {
        return (bool)(self::filas("SELECT clima_auto FROM proyectos WHERE id = $pid")[0]['clima_auto'] ?? 0);
    }

    public static function cambiarActivo(int $pid, bool $activo): void
    {
        self::db()->query('UPDATE proyectos SET clima_auto = ' . ($activo ? 1 : 0) . " WHERE id = $pid");
    }

    /** Registra el clima de hoy (una vez al día) y avisa si mañana será adverso (una vez). @return array{ok: bool, mensaje: string} */
    public static function procesar(Proyecto $p, bool $forzar = false): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $man = date('Y-m-d', strtotime('+1 day'));
        $r = Clima::actualizar($p);
        if (!$r['ok']) return ['ok' => false, 'mensaje' => $r['mensaje']];
        $dias = [];
        foreach (Clima::dias($pid, $hoy, $man) as $d) $dias[$d['fecha']] = $d;
        $est = self::filas("SELECT clima_ultimo, clima_aviso FROM proyectos WHERE id = $pid")[0] ?? ['clima_ultimo' => null, 'clima_aviso' => null];
        $hecho = [];

        if (isset($dias[$hoy]) && ($forzar || $est['clima_ultimo'] !== $hoy) && !self::yaAnotado($pid, $hoy)) {
            $d = $dias[$hoy];
            $c = Clima::condicion($d);
            $b = new Bitacora(['proyecto_id' => $pid, 'fecha' => $hoy, 'departamento' => self::DEPARTAMENTO, 'importancia' => $c ? (Clima::esFuerte($d) ? 'alta' : 'media') : 'baja',
                'descripcion' => self::MARCA . ' (' . ($r['lugar'] ?: 'Open-Meteo') . '): ' . self::resumen($d) . ($c ? '. Puede afectar los trabajos de exterior.' : '.')]);
            if (!$b->validar() && (HistorialCambios::silenciar(fn() => $b->guardar())['resultado'] ?? false)) $hecho[] = 'bitácora de hoy registrada';   // la escribe el sistema, no una decisión del usuario
        }
        if (isset($dias[$hoy])) self::db()->query("UPDATE proyectos SET clima_ultimo = '$hoy' WHERE id = $pid");

        if (isset($dias[$man]) && Clima::condicion($dias[$man]) && ($forzar || $est['clima_aviso'] !== $man)) {
            Telegram::aviso($pid, 'clima.alerta', ['texto' => self::textoAlerta($pid, $dias[$man])]);
            self::db()->query("UPDATE proyectos SET clima_aviso = '$man' WHERE id = $pid");
            $hecho[] = 'aviso de mañana enviado';
        }
        return ['ok' => true, 'mensaje' => ($hecho ? ucfirst(implode(' y ', $hecho)) : 'Nada nuevo que registrar hoy') . ' · ' . ($r['lugar'] ?: '')];
    }

    private static function yaAnotado(int $pid, string $fecha): bool
    {
        return (bool)self::filas("SELECT id FROM bitacora WHERE proyecto_id = $pid AND fecha = '$fecha' AND departamento = '" . self::DEPARTAMENTO . "' AND descripcion LIKE '" . self::MARCA . "%' LIMIT 1");
    }

    /** Aviso de mañana con las actividades de exterior que caen ese día */
    public static function textoAlerta(int $pid, array $dia): string
    {
        $c = Clima::condicion($dia);
        $t = ($c === 'viento' ? '💨' : '🌧') . ' <b>Mañana (' . self::fechaLarga($dia['fecha']) . '): ' . Telegram::h($c) . "</b>\n" . Telegram::h(self::resumen($dia));
        $ext = [];
        foreach (self::filas("SELECT a.codigo, a.nombre, a.descripcion FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE p.proyecto_id = $pid AND a.hito = 0 AND a.progreso < 100 AND a.fecha_inicio <= '{$dia['fecha']}' AND a.fecha_fin >= '{$dia['fecha']}' ORDER BY a.fecha_inicio, a.id") as $a) {
            if (Clima::esExterior((string)$a['nombre'], (string)$a['descripcion'])) $ext[] = '• ' . Telegram::h(trim($a['codigo'] . ' ' . $a['nombre']));
        }
        if ($ext) $t .= "\n\n<b>Actividades de exterior programadas</b>\n" . implode("\n", array_slice($ext, 0, 8)) . (count($ext) > 8 ? "\n… y " . (count($ext) - 8) . ' más' : '') . "\nConviene reprogramarlas o preparar protección.";
        else $t .= "\n\nNo hay actividades de exterior programadas para mañana.";
        return $t;
    }
}
