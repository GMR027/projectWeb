<?php

namespace Classes;

/** Ayudas para interpretar lo dictado: números dichos en español, códigos de actividad ("tres punto dos") y comandos sencillos sin IA */
class Voz
{
    private const UNIDADES = ['cero' => 0, 'uno' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10, 'once' => 11, 'doce' => 12, 'trece' => 13, 'catorce' => 14, 'quince' => 15,
        'dieciseis' => 16, 'diecisiete' => 17, 'dieciocho' => 18, 'diecinueve' => 19, 'veinte' => 20, 'veintiuno' => 21, 'veintidos' => 22, 'veintitres' => 23, 'veinticuatro' => 24, 'veinticinco' => 25, 'veintiseis' => 26, 'veintisiete' => 27, 'veintiocho' => 28, 'veintinueve' => 29];
    private const DECENAS = ['treinta' => 30, 'cuarenta' => 40, 'cincuenta' => 50, 'sesenta' => 60, 'setenta' => 70, 'ochenta' => 80, 'noventa' => 90];

    public static function sinAcentos(string $t): string
    {
        return strtr(mb_strtolower($t), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    /** Minúsculas sin acentos, números en letras a cifras, «punto» → «.», «por ciento» → «%» */
    public static function normalizar(string $t): string
    {
        $t = self::sinAcentos($t);
        $t = preg_replace('/\bpor\s*ciento\b/', '%', $t);
        $t = preg_replace('/\s+punto\s+/', '.', $t);
        $t = preg_replace('/\bcien\b/', '100', $t);
        $t = preg_replace_callback('/\b(' . implode('|', array_keys(self::DECENAS)) . ')(?:\s+y\s+(' . implode('|', array_keys(self::UNIDADES)) . '))?\b/', fn($m) => (string)(self::DECENAS[$m[1]] + (isset($m[2]) ? self::UNIDADES[$m[2]] : 0)), $t);
        $t = preg_replace_callback('/\b(' . implode('|', array_keys(self::UNIDADES)) . ')\b/', fn($m) => (string)self::UNIDADES[$m[1]], $t);
        $t = preg_replace('/(\d)\s*\.\s*(\d)/', '$1.$2', $t);
        return trim((string)preg_replace('/\s+/', ' ', $t));
    }

    /** Comandos de avance dichos de forma directa: «actividad 3.2 al 80 por ciento», «la 9.4 a 50%» */
    public static function comandosSimples(string $texto): array
    {
        $n = self::normalizar($texto);
        $out = [];
        if (preg_match_all('/(?:actividad\s+|la\s+)?(\d+(?:\.\d+)+)\s*(?:al|a|en|llega\s+a|esta\s+al)\s*(\d{1,3})\s*%?/', $n, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) if ((int)$x[2] <= 100) $out[] = ['referencia' => $x[1], 'accion' => 'avance', 'valor' => (int)$x[2]];
        }
        return $out;
    }

    /** @return array{0: ?int, 1: string} id de la actividad y motivo si no se pudo resolver */
    public static function resolverActividad(array $acts, string $ref): array
    {
        $r = self::normalizar($ref);
        if ($r === '') return [null, 'sin referencia'];
        if (preg_match('/\d+(?:\.\d+)+/', $r, $m)) {
            $coin = array_keys(array_filter($acts, fn($a) => self::normalizar((string)$a['codigo']) === $m[0]));
            if (count($coin) === 1) return [$coin[0], ''];
            if (count($coin) > 1) return [null, 'código repetido'];
        }
        $t = self::palabras($r);
        if (count($t) < 2) return [null, 'no se encontró una actividad con ese código o nombre'];
        $mejor = [];
        foreach ($acts as $id => $a) {
            $n = self::palabras($a['nombre']);
            if (!$n) continue;
            $comunes = count(array_intersect($t, $n));
            $puntaje = $comunes / max(count($t), 1);
            if ($comunes >= 2 && $puntaje >= 0.6) $mejor[$id] = $puntaje;
        }
        arsort($mejor);
        if (!$mejor) return [null, 'no se encontró una actividad con ese código o nombre'];
        $ids = array_keys($mejor);
        if (count($ids) > 1 && $mejor[$ids[0]] - $mejor[$ids[1]] < 0.15) return [null, 'varias actividades coinciden'];
        return [$ids[0], ''];
    }

    public static function palabras(string $t): array
    {
        $vacias = ['de', 'del', 'la', 'el', 'los', 'las', 'en', 'y', 'a', 'al', 'con', 'para', 'por', 'una', 'un', 'actividad'];
        return array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/', self::sinAcentos($t)), fn($w) => strlen($w) > 1 && !in_array($w, $vacias, true))));
    }
}
