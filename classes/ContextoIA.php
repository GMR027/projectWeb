<?php

namespace Classes;

/**
 * Contexto preparado por una herramienta de IA, guardado entre la petición que arma los lotes y la que compone el informe.
 *
 * Antes, `contexto` y `resultado` llamaban dos veces a `Herramienta::preparar()`: en un resumen ejecutivo eso recalcula
 * Curva S, líneas base, punch, causas y riesgos. Aquí se guarda el resultado de la primera llamada (datos ya calculados por
 * el servidor, nunca por el cliente) y se reutiliza durante unos minutos. Si el archivo no está o caducó, el controlador
 * vuelve a preparar los datos: el informe siempre se puede generar.
 */
class ContextoIA
{
    private const TTL = 1800;   // 30 minutos: el escritorio ejecuta el modelo justo después de pedir el contexto

    private static function dir(): string
    {
        $d = __DIR__ . '/../storage/cache/ia';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    /** Guarda el contexto y devuelve un token aleatorio que no revela ningún dato */
    public static function guardar(int $pid, string $herramienta, array $params, array $datos, array $lotes): string
    {
        if (random_int(1, 100) === 1) self::limpiar();
        try {
            $token = bin2hex(random_bytes(16));
            $cuerpo = json_encode([
                'pid' => $pid, 'herramienta' => $herramienta, 'params' => $params,
                'datos' => $datos, 'lotes' => $lotes, 'creado' => time(),
            ], JSON_UNESCAPED_UNICODE);
            if ($cuerpo === false) return '';
            $ruta = self::dir() . '/' . $token . '.json';
            if (@file_put_contents($ruta, $cuerpo, LOCK_EX) === false) return '';
            @chmod($ruta, 0600);
            return $token;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @return array{pid:int,herramienta:string,params:array,datos:array,lotes:array,creado:int}|null */
    public static function leer(string $token, int $pid, string $herramienta): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
        $ruta = self::dir() . '/' . $token . '.json';
        if (!is_file($ruta)) return null;
        try {
            $j = json_decode((string)file_get_contents($ruta), true);
            if (!is_array($j) || (int)($j['pid'] ?? 0) !== $pid || (string)($j['herramienta'] ?? '') !== $herramienta) return null;
            if (time() - (int)($j['creado'] ?? 0) > self::TTL) { @unlink($ruta); return null; }
            if (!is_array($j['datos'] ?? null)) return null;
            return $j;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function borrar(string $token): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token)) @unlink(self::dir() . '/' . $token . '.json');
    }

    /** Borra contextos viejos (se llama de vez en cuando, no en cada petición) */
    public static function limpiar(): void
    {
        $dir = self::dir();
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < time() - 86400) @unlink($f);
        }
    }
}
