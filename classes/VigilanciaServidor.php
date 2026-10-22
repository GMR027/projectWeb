<?php

namespace Classes;

use Model\Proyecto;

/**
 * Vigilancia en el servidor: comprueba los proyectos con las reglas del sistema (sin IA, `Inconsistencias::vigilar`) sin depender de que
 * la app de escritorio esté abierta. Corre con `bin/vigilancia_programada.php` (cron) o, si no hay cron, la primera vez que el
 * administrador abre el resumen del proyecto después de vencido el intervalo. La comparación no mueve la foto de referencia:
 * lo nuevo se sigue viendo hasta que alguien pulse «Marcar como visto» o la app de escritorio lo comprueba.
 */
class VigilanciaServidor
{
    use AccesoDb;

    public const MINUTOS = [15 => 'Cada 15 minutos', 30 => 'Cada 30 minutos', 60 => 'Cada hora', 180 => 'Cada 3 horas', 720 => 'Cada 12 horas', 1440 => 'Una vez al día'];

    public static function config(int $pid): array
    {
        $f = self::filas("SELECT vigilancia_servidor, vigilancia_minutos, vigilancia_ultima, vigilancia_origen FROM ia_ajustes WHERE proyecto_id = $pid")[0] ?? null;
        return ['activa' => (bool)($f['vigilancia_servidor'] ?? 0), 'minutos' => (int)($f['vigilancia_minutos'] ?? 60) ?: 60, 'ultima' => $f['vigilancia_ultima'] ?? null, 'origen' => $f['vigilancia_origen'] ?? ''];
    }

    public static function guardar(int $pid, bool $activa, int $minutos): bool
    {
        if (!isset(self::MINUTOS[$minutos])) $minutos = 60;
        try {
            self::db()->query("INSERT INTO ia_ajustes (proyecto_id, vigilancia_servidor, vigilancia_minutos) VALUES ($pid, " . (int)$activa . ", $minutos) ON DUPLICATE KEY UPDATE vigilancia_servidor = " . (int)$activa . ", vigilancia_minutos = $minutos");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Próxima comprobación (timestamp) o null si está apagada */
    public static function proxima(array $c): ?int
    {
        if (!$c['activa']) return null;
        return $c['ultima'] ? strtotime($c['ultima']) + $c['minutos'] * 60 : time();
    }

    public static function comprobar(Proyecto $p, string $origen): array
    {
        $pid = (int)$p->id;
        $r = Inconsistencias::vigilar($p, false);
        self::db()->query("UPDATE ia_ajustes SET vigilancia_ultima = NOW(), vigilancia_origen = '" . self::esc($origen) . "' WHERE proyecto_id = $pid");
        return $r;
    }

    /** Comprueba los proyectos activos a los que les toca. @return int cuántos se comprobaron */
    public static function ejecutarPendientes(string $origen = 'cron', bool $forzar = false, ?int $soloProyecto = null): int
    {
        $n = 0;
        foreach (self::filas('SELECT proyecto_id FROM ia_ajustes WHERE vigilancia_servidor = 1' . ($soloProyecto ? ' AND proyecto_id = ' . (int)$soloProyecto : '')) as $f) {
            $pid = (int)$f['proyecto_id'];
            $c = self::config($pid);
            if (!$forzar && ($prox = self::proxima($c)) !== null && $prox > time()) continue;
            $p = Proyecto::find($pid);
            if (!$p) continue;
            try {
                self::comprobar($p, $origen);
                $n++;
            } catch (\Throwable $e) {
                error_log("VigilanciaServidor proyecto $pid: " . $e->getMessage());
            }
        }
        return $n;
    }
}
