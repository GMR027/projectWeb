<?php

namespace Classes;

use Model\Proyecto;

/**
 * Clima del proyecto con Open-Meteo (gratis, sin clave): geocodifica la ubicación, guarda el pronóstico de 16 días y el clima real pasado, y detecta días de lluvia
 * fuerte o viento que afectan actividades de exterior. Solo se envían a Open-Meteo el nombre del lugar o las coordenadas; ningún dato del proyecto.
 */
class Clima
{
    public const LLUVIA_MM = 10.0;       // lluvia que detiene trabajos de exterior
    public const LLUVIA_FUERTE_MM = 20.0;
    public const VIENTO_KMH = 50.0;
    public const VIENTO_FUERTE_KMH = 60.0;
    private const EXTERIOR = ['exterior', 'azotea', 'fachada', 'cimbra', 'colado', 'excavac', 'cimentac', 'estacionamiento', 'banqueta', 'areas verdes', 'jardin', 'estructura metalica', 'impermeabiliz', 'poste', 'techumbre', 'andamio', 'grua', 'concreto', 'zanja', 'relleno', 'pavimento', 'nivelacion', 'trazo', 'camara en poste', 'bajada de tuberia', 'conduit exterior', 'cancela', 'barda', 'cerca'];

    /** Para pruebas: función que reemplaza la consulta HTTP (url → arreglo decodificado o null) */
    public static $http = null;

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    private static function filas(string $sql): array
    {
        try {
            $r = self::db()->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function pedir(string $url): ?array
    {
        if (self::$http) return (self::$http)($url);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_USERAGENT => 'ProjectWeb/1.0', CURLOPT_FOLLOWLOCATION => false]);
        $r = curl_exec($ch);
        $ok = $r !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        return $ok ? (json_decode((string)$r, true) ?: null) : null;
    }

    /** Ubicación (coordenadas) del proyecto: la manual «lat,lon», la guardada o la geocodificada a partir de proyectos.ubicacion */
    public static function ubicacion(Proyecto $p, string $manual = ''): ?array
    {
        $pid = (int)$p->id;
        $db = self::db();
        if (preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $manual, $m) && abs((float)$m[1]) <= 90 && abs((float)$m[2]) <= 180) {
            $db->query("REPLACE INTO clima_proyecto (proyecto_id, lugar, lat, lon, actualizado) VALUES ($pid, 'Coordenadas manuales', {$m[1]}, {$m[2]}, NOW())");
            return ['lugar' => 'Coordenadas manuales', 'lat' => (float)$m[1], 'lon' => (float)$m[2]];
        }
        $g = self::filas("SELECT lugar, lat, lon FROM clima_proyecto WHERE proyecto_id = $pid")[0] ?? null;
        if ($g && $manual === '') return ['lugar' => $g['lugar'], 'lat' => (float)$g['lat'], 'lon' => (float)$g['lon']];
        $texto = trim((string)$p->ubicacion);
        if ($texto === '') return null;
        // «Zona Industrial, Silao, Gto.» → se prueba el texto completo, cada parte (separadas por coma, guion o diagonal) y, al final, las palabras largas
        $partes = array_values(array_filter(array_map('trim', preg_split('/[,;\/\-–]+/u', $texto))));
        $palabras = array_values(array_filter(preg_split('/[^\p{L}]+/u', $texto), fn($w) => mb_strlen($w) >= 4));
        usort($palabras, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach (array_slice(array_unique([$texto, ...array_reverse($partes), ...$palabras]), 0, 6) as $q) {
            if ($q === '') continue;
            $r = self::pedir('https://geocoding-api.open-meteo.com/v1/search?count=1&language=es&format=json&name=' . rawurlencode($q));
            $x = $r['results'][0] ?? null;
            if ($x) {
                $lugar = trim(($x['name'] ?? $q) . ', ' . ($x['admin1'] ?? '') . ', ' . ($x['country'] ?? ''), ', ');
                $db->query("REPLACE INTO clima_proyecto (proyecto_id, lugar, lat, lon, actualizado) VALUES ($pid, '" . $db->real_escape_string(mb_substr($lugar, 0, 200)) . "', " . (float)$x['latitude'] . ', ' . (float)$x['longitude'] . ', NOW())');
                return ['lugar' => $lugar, 'lat' => (float)$x['latitude'], 'lon' => (float)$x['longitude']];
            }
        }
        return null;
    }

    private static function guardar(int $pid, array $d, string $tipo): int
    {
        $db = self::db();
        $n = 0;
        foreach ($d['time'] ?? [] as $i => $f) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$f)) continue;
            $v = fn($k) => isset($d[$k][$i]) && is_numeric($d[$k][$i]) ? (float)$d[$k][$i] : null;
            $db->query("REPLACE INTO clima_dias (proyecto_id, fecha, precip_mm, prob, viento_kmh, tmax, tmin, tipo, actualizado) VALUES ($pid, '$f', " . ($v('precipitation_sum') ?? 0) . ', ' . ($tipo === 'pronostico' && $v('precipitation_probability_max') !== null ? (int)$v('precipitation_probability_max') : 'NULL') . ', ' . ($v('wind_speed_10m_max') ?? 0) . ', ' . ($v('temperature_2m_max') ?? 'NULL') . ', ' . ($v('temperature_2m_min') ?? 'NULL') . ", '$tipo', NOW())");
            $n++;
        }
        return $n;
    }

    /** Trae el pronóstico (16 días) y el clima real reciente. @return array{ok: bool, mensaje: string, lugar: ?string} */
    public static function actualizar(Proyecto $p, string $manual = ''): array
    {
        $pid0 = (int)$p->id;
        $reciente = self::filas("SELECT COUNT(*) n, MAX(actualizado) a FROM clima_dias WHERE proyecto_id = $pid0 AND tipo = 'pronostico'")[0] ?? [];
        if ($manual === '' && (int)($reciente['n'] ?? 0) > 0 && strtotime((string)$reciente['a']) > time() - 3 * 3600) {
            return ['ok' => true, 'mensaje' => 'Clima ya actualizado hace menos de 3 horas.', 'lugar' => self::filas("SELECT lugar FROM clima_proyecto WHERE proyecto_id = $pid0")[0]['lugar'] ?? null];
        }
        $u = self::ubicacion($p, $manual);
        if (!$u) return ['ok' => false, 'mensaje' => 'No se pudo ubicar el proyecto: revisa su campo «Ubicación» o escribe las coordenadas como «lat,lon».', 'lugar' => null];
        $pid = (int)$p->id;
        $daily = 'precipitation_sum,wind_speed_10m_max,temperature_2m_max,temperature_2m_min';
        $f = self::pedir("https://api.open-meteo.com/v1/forecast?latitude={$u['lat']}&longitude={$u['lon']}&daily=$daily,precipitation_probability_max&timezone=auto&forecast_days=16");
        if (!$f || empty($f['daily'])) return ['ok' => false, 'mensaje' => 'No se pudo consultar el pronóstico (sin conexión o servicio no disponible). Se usan los últimos datos guardados, si los hay.', 'lugar' => $u['lugar']];
        self::guardar($pid, $f['daily'], 'pronostico');
        $desde = max($p->fecha_inicio ?: date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('-90 days')));
        $hasta = date('Y-m-d', strtotime('-2 days'));   // el archivo histórico tiene unos días de retraso
        if ($desde <= $hasta) {
            $h = self::pedir("https://archive-api.open-meteo.com/v1/archive?latitude={$u['lat']}&longitude={$u['lon']}&start_date=$desde&end_date=$hasta&daily=$daily&timezone=auto");
            if ($h && !empty($h['daily'])) self::guardar($pid, $h['daily'], 'real');
        }
        return ['ok' => true, 'mensaje' => 'Clima actualizado para ' . $u['lugar'] . '.', 'lugar' => $u['lugar']];
    }

    public static function dias(int $pid, string $desde, string $hasta): array
    {
        return self::filas("SELECT * FROM clima_dias WHERE proyecto_id = $pid AND fecha BETWEEN '" . self::db()->real_escape_string($desde) . "' AND '" . self::db()->real_escape_string($hasta) . "' ORDER BY fecha");
    }

    /** Condición adversa de un día: 'lluvia', 'viento', 'lluvia y viento' o null */
    public static function condicion(array $d): ?string
    {
        $ll = (float)$d['precip_mm'] >= self::LLUVIA_MM || ($d['prob'] !== null && (int)$d['prob'] >= 80 && (float)$d['precip_mm'] >= 5);
        $vi = (float)$d['viento_kmh'] >= self::VIENTO_KMH;
        return $ll && $vi ? 'lluvia y viento' : ($ll ? 'lluvia' : ($vi ? 'viento' : null));
    }

    public static function esFuerte(array $d): bool
    {
        return (float)$d['precip_mm'] >= self::LLUVIA_FUERTE_MM || (float)$d['viento_kmh'] >= self::VIENTO_FUERTE_KMH;
    }

    public static function esExterior(string $nombre, string $descripcion = ''): bool
    {
        $t = Voz::sinAcentos($nombre . ' ' . $descripcion);
        foreach (self::EXTERIOR as $k) if (str_contains($t, $k)) return true;
        return false;
    }
}
