<?php

namespace Classes;

/**
 * Medidor de uso de la IA: la app de escritorio informa cada llamada al modelo (herramienta, modelo, duración, tamaño) y aquí se suma.
 * Las llamadas se hacen en el equipo del administrador, por eso el servidor solo puede saberlas porque se las avisan.
 * Si la migración está pendiente nada lanza excepciones.
 */
class UsoIA
{
    use AccesoDb;

    public const MAX_POR_ENVIO = 200;

    /** Guarda las llamadas informadas; descarta lo mal formado. @return int cuántas se guardaron */
    public static function registrar(array $registros, string $usuario): int
    {
        $n = 0;
        $db = self::db();
        foreach (array_slice($registros, 0, self::MAX_POR_ENVIO) as $r) {
            if (!is_array($r) || trim((string)($r['herramienta'] ?? '')) === '') continue;
            $pid = (int)($r['proyectoId'] ?? 0);
            if ($pid && !self::filas("SELECT 1 FROM proyectos WHERE id = $pid")) $pid = 0;
            $num = fn($k, $max) => max(0, min($max, (int)($r[$k] ?? 0)));
            try {
                $db->query('INSERT INTO ia_uso (proyecto_id, herramienta, modelo, proveedor, duracion_ms, caracteres_entrada, caracteres_salida, exito, usuario) VALUES ('
                    . ($pid ?: 'NULL') . ", '" . self::esc(mb_substr((string)$r['herramienta'], 0, 60)) . "', '" . self::esc(mb_substr((string)($r['modelo'] ?? ''), 0, 150)) . "', '" . self::esc(mb_substr((string)($r['proveedor'] ?? ''), 0, 30)) . "', "
                    . $num('ms', 86400000) . ', ' . $num('entrada', 4000000000) . ', ' . $num('salida', 4000000000) . ', ' . (!empty($r['ok']) ? 1 : 0) . ", '" . self::esc(mb_substr($usuario, 0, 150)) . "')");
                $n++;
            } catch (\Throwable $e) {
                error_log('UsoIA::registrar: ' . $e->getMessage());
                break;
            }
        }
        return $n;
    }

    /** Condición de periodo y proyecto */
    private static function donde(int $pid, int $dias): string
    {
        return "proyecto_id = $pid AND creado >= DATE_SUB(NOW(), INTERVAL " . max(1, $dias) . ' DAY)';
    }

    /** @return array{total: array, herramientas: array, modelos: array, dias: array} */
    public static function resumen(int $pid, int $dias = 7): array
    {
        $w = self::donde($pid, $dias);
        $campos = 'COUNT(*) llamadas, SUM(exito = 0) errores, COALESCE(SUM(duracion_ms), 0) ms, COALESCE(AVG(duracion_ms), 0) promedio_ms, COALESCE(MAX(duracion_ms), 0) max_ms, COALESCE(SUM(caracteres_entrada), 0) entrada, COALESCE(SUM(caracteres_salida), 0) salida';
        return [
            'total' => self::filas("SELECT $campos FROM ia_uso WHERE $w")[0] ?? [],
            'herramientas' => self::filas("SELECT herramienta, $campos FROM ia_uso WHERE $w GROUP BY herramienta ORDER BY llamadas DESC"),
            'modelos' => self::filas("SELECT modelo, proveedor, $campos FROM ia_uso WHERE $w GROUP BY modelo, proveedor ORDER BY llamadas DESC"),
            'dias' => self::filas("SELECT DATE(creado) dia, $campos FROM ia_uso WHERE $w GROUP BY DATE(creado) ORDER BY dia DESC"),
        ];
    }

    /** Nombre legible de la clave de herramienta que informa el escritorio */
    public static function nombre(string $clave): string
    {
        if (str_starts_with($clave, 'informe:')) return 'Informe: ' . substr($clave, 8);
        return Auditoria::HERRAMIENTAS[$clave] ?? ['chat' => 'Pregunta a tu proyecto', 'inteligentes' => 'Materiales inteligentes'][$clave] ?? $clave;
    }

    public static function duracion(float $ms): string
    {
        $s = $ms / 1000;
        return $s < 60 ? number_format($s, 1) . ' s' : ($s < 3600 ? floor($s / 60) . ' min ' . round(fmod($s, 60)) . ' s' : floor($s / 3600) . ' h ' . round(fmod($s, 3600) / 60) . ' min');
    }
}
