<?php

namespace Classes;

/** Espacio ocupado en los volúmenes del servidor (archivos subidos y base de datos), con historial y pronóstico */
class Volumen
{
    private const RAIZ = __DIR__ . '/..';
    /** Carpetas que viven en el volumen de archivos */
    public const CARPETAS = [
        'Uploads (fotos, planos, materiales)' => 'public/uploads',
        'Exportaciones' => 'public/exportaciones',
        'Archivos del proyecto' => 'storage/archivos',
        'Fichas técnicas' => 'storage/fichas',
        'Importaciones' => 'storage/import',
        'Normativa y especificaciones' => 'storage/normativa',
        'Grabaciones (audio)' => 'storage/grabaciones',
    ];

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    private static function tamCarpeta(string $ruta): int
    {
        if (!is_dir($ruta)) return 0;
        $total = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) $total += $f->getSize();
        }
        return $total;
    }

    /** Capacidad en bytes declarada para el volumen (VOLUMEN_*_GB); sin variable, el tamaño del disco */
    private static function capacidad(string $var, string $rutaRespaldo): int
    {
        $gb = (float)($_ENV[$var] ?? 0);
        if ($gb > 0) return (int)($gb * 1073741824);
        return (int)(@disk_total_space($rutaRespaldo) ?: 0);
    }

    /** ['usado', 'capacidad', 'desglose' => [nombre => bytes]] del volumen de archivos */
    public static function archivos(): array
    {
        $desglose = [];
        foreach (self::CARPETAS as $nombre => $rel) $desglose[$nombre] = self::tamCarpeta(self::RAIZ . '/' . $rel);
        return [
            'usado' => array_sum($desglose),
            'capacidad' => self::capacidad('VOLUMEN_ARCHIVOS_GB', self::RAIZ . '/public/uploads'),
            'desglose' => $desglose,
        ];
    }

    /** ['usado', 'capacidad', 'desglose' => [tabla => bytes]] de la base de datos */
    public static function baseDatos(): array
    {
        $db = self::db();
        $nombre = $db->real_escape_string($_ENV['DB_NAME'] ?? 'project');
        $r = $db->query("SELECT table_name AS t, (data_length + index_length) AS b FROM information_schema.tables WHERE table_schema = '$nombre' ORDER BY b DESC");
        $desglose = [];
        $usado = 0;
        while ($r && ($fila = $r->fetch_assoc())) {
            $usado += (int)$fila['b'];
            if (count($desglose) < 8) $desglose[$fila['t']] = (int)$fila['b'];
        }
        return ['usado' => $usado, 'capacidad' => self::capacidad('VOLUMEN_BD_GB', self::RAIZ), 'desglose' => $desglose];
    }

    /** Espacio realmente libre en el disco del servidor (los volúmenes hostpath no lo reservan) */
    public static function discoServidor(): array
    {
        $ruta = self::RAIZ . '/public/uploads';
        return ['libre' => (int)@disk_free_space($ruta), 'total' => (int)@disk_total_space($ruta)];
    }

    /** Guarda (o actualiza) la foto de hoy */
    public static function registrarHoy(int $archivos, int $bd): void
    {
        $db = self::db();
        $db->query("INSERT INTO almacenamiento_historial (fecha, archivos_bytes, bd_bytes) VALUES (CURDATE(), $archivos, $bd)
                    ON DUPLICATE KEY UPDATE archivos_bytes = VALUES(archivos_bytes), bd_bytes = VALUES(bd_bytes)");
    }

    /** Fotos de los últimos $dias días: [['fecha' => 'Y-m-d', 'archivos' => bytes, 'bd' => bytes], ...] */
    public static function historial(int $dias = 90): array
    {
        $r = self::db()->query("SELECT fecha, archivos_bytes, bd_bytes FROM almacenamiento_historial
                                WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL " . (int)$dias . " DAY) ORDER BY fecha ASC");
        $out = [];
        while ($r && ($f = $r->fetch_assoc())) $out[] = ['fecha' => $f['fecha'], 'archivos' => (int)$f['archivos_bytes'], 'bd' => (int)$f['bd_bytes']];
        return $out;
    }

    /**
     * Días que faltan para llenar el volumen según la tendencia lineal (mínimos cuadrados) del historial.
     * null si no hay datos suficientes o el uso no crece.
     * @return array{bytes_dia: float, dias: ?int, fecha: ?string}|null
     */
    public static function pronostico(array $historial, string $clave, int $usado, int $capacidad): ?array
    {
        $n = count($historial);
        if ($n < 2 || $capacidad <= 0) return null;
        $t0 = strtotime($historial[0]['fecha']);
        $sx = $sy = $sxy = $sxx = 0.0;
        foreach ($historial as $h) {
            $x = (strtotime($h['fecha']) - $t0) / 86400;
            $y = (float)$h[$clave];
            $sx += $x; $sy += $y; $sxy += $x * $y; $sxx += $x * $x;
        }
        $den = $n * $sxx - $sx * $sx;
        if ($den == 0) return null;
        $pendiente = ($n * $sxy - $sx * $sy) / $den;   // bytes por día
        if ($pendiente <= 0) return ['bytes_dia' => 0.0, 'dias' => null, 'fecha' => null];
        $dias = (int)ceil(max(0, $capacidad - $usado) / $pendiente);
        return ['bytes_dia' => $pendiente, 'dias' => $dias, 'fecha' => date('Y-m-d', strtotime("+$dias days"))];
    }

    public static function formato(int|float $bytes): string
    {
        return Almacenamiento::formato((int)$bytes);
    }
}
