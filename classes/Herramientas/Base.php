<?php

namespace Classes\Herramientas;

/** Utilidades comunes de las herramientas que generan informes */
abstract class Base implements Herramienta
{
    protected static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    protected static function filas(string $sql): array
    {
        try {
            $r = self::db()->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected static function corto(?string $t, int $n): string
    {
        $t = trim((string)preg_replace('/\s+/u', ' ', (string)$t));
        return mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
    }

    protected static function fmt(?string $d): string
    {
        return $d ? date('d/m/Y', strtotime($d)) : '—';
    }

    /** Primer valor no vacío de $clave entre las respuestas de la IA (o null) */
    protected static function dato(array $respuestas, string $clave)
    {
        foreach ($respuestas as $r) if (is_array($r) && isset($r[$clave]) && $r[$clave] !== '' && $r[$clave] !== []) return $r[$clave];
        return null;
    }

    /** Lista de textos limpios de una respuesta de la IA */
    protected static function textos($v, int $max = 12, int $largo = 240): array
    {
        if (!is_array($v)) return [];
        $out = [];
        foreach ($v as $x) {
            $t = \Classes\Informes::limpiarTexto(is_array($x) ? implode(' · ', array_filter(array_map('strval', $x))) : $x, $largo);
            if ($t !== '') $out[] = $t;
            if (count($out) >= $max) break;
        }
        return $out;
    }

    protected static function lunes(string $fecha): string
    {
        return date('Y-m-d', strtotime('monday this week', strtotime($fecha)));
    }
}
