<?php

namespace Classes\Exportadores;

/** Gráficas de curvas dibujadas con GD (PNG) para PDF y hojas de cálculo */
class GraficaGd
{
    private const W = 1700, H = 900;
    private const FUENTE = __DIR__ . '/../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';
    private const FUENTE_B = __DIR__ . '/../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';

    private static function texto($im, float $tam, int $x, int $y, int $color, string $t, bool $negrita = false, string $ancla = 'l', float $ang = 0): void
    {
        $f = $negrita ? self::FUENTE_B : self::FUENTE;
        $caja = imagettfbbox($tam, 0, $f, $t);
        $w = abs($caja[2] - $caja[0]);
        if ($ancla === 'c') $x -= (int)($w / 2);
        elseif ($ancla === 'r') $x -= (int)$w;
        imagettftext($im, $tam, $ang, $x, $y, $color, $f, $t);
    }

    /** Línea con grosor y patrón discontinuo opcional */
    private static function linea($im, float $x1, float $y1, float $x2, float $y2, int $color, int $grosor, bool $guiones = false): void
    {
        imagesetthickness($im, $grosor);
        if (!$guiones) {
            imageline($im, (int)$x1, (int)$y1, (int)$x2, (int)$y2, $color);
            return;
        }
        $largo = hypot($x2 - $x1, $y2 - $y1);
        if ($largo < 1) return;
        $dx = ($x2 - $x1) / $largo;
        $dy = ($y2 - $y1) / $largo;
        for ($t = 0; $t < $largo; $t += 26) {
            $e = min($t + 16, $largo);
            imageline($im, (int)($x1 + $dx * $t), (int)($y1 + $dy * $t), (int)($x1 + $dx * $e), (int)($y1 + $dy * $e), $color);
        }
    }

    private static function marcador($im, string $forma, float $x, float $y, int $color, int $r = 9): void
    {
        if ($forma === 'triangulo') {
            imagefilledpolygon($im, [(int)$x, (int)($y - $r), (int)($x - $r), (int)($y + $r * .8), (int)($x + $r), (int)($y + $r * .8)], $color);
        } else {
            imagefilledellipse($im, (int)$x, (int)$y, $r * 2, $r * 2, $color);
        }
    }

    private static function escala(float $max, int $pasos = 9): array
    {
        $max = max($max, 1);
        $bruto = $max / $pasos;
        $mag = 10 ** floor(log10($bruto));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $mag >= $bruto) { $paso = $m * $mag; break; }
        }
        return [$paso, ceil($max / $paso) * $paso];
    }

    private static function nuevo(): array
    {
        $im = imagecreatetruecolor(self::W, self::H);
        imageantialias($im, true);
        $blanco = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $blanco);
        return [$im, $blanco, imagecolorallocate($im, 0, 0, 0), imagecolorallocate($im, 200, 200, 200)];
    }

    private static function png($im): string
    {
        ob_start();
        imagepng($im);
        return ob_get_clean();
    }

    /** Línea vertical punteada azul con la fecha de hoy */
    private static function hoy($im, float $x, int $arr, int $abj): void
    {
        $azul = imagecolorallocate($im, 0, 112, 192);
        self::linea($im, $x, $arr, $x, $abj, $azul, 4, true);
        self::texto($im, 12, (int)$x, $arr - 10, $azul, 'HOY ' . date('d/m/y'), true, 'c');
    }

    /** Barras (planeado/real) + líneas acumuladas en eje derecho */
    public static function curva(array $s, string $nombre, string $unidad): string
    {
        [$im, $blanco, $negro, $gris] = self::nuevo();
        $rojo = imagecolorallocate($im, 255, 0, 0);
        $barraP = imagecolorallocate($im, 190, 190, 190);
        $barraR = imagecolorallocate($im, 90, 90, 90);
        [$izq, $der, $arr, $abj] = [120, 1580, 110, 760];
        $n = count($s['fechas']);

        $maxBar = max(array_merge($s['planeado'], array_map('floatval', array_filter($s['actual'], fn($v) => $v !== null)), [1]));
        $maxAcum = max(array_merge($s['planeado_acum'], array_filter($s['actual_acum'], fn($v) => $v !== null), [1]));
        [$pasoL, $topeL] = self::escala($maxBar);
        [$pasoR, $topeR] = self::escala($maxAcum);

        self::texto($im, 24, (int)((self::W) / 2), 62, $negro, mb_strtoupper($nombre), true, 'c');
        // Marco, rejilla y ejes
        $yL = fn($v) => $abj - ($v / $topeL) * ($abj - $arr);
        $yR = fn($v) => $abj - ($v / $topeR) * ($abj - $arr);
        for ($v = 0; $v <= $topeL + 0.001; $v += $pasoL) {
            $y = (int)$yL($v);
            imagesetthickness($im, 1);
            imageline($im, $izq, $y, $der, $y, $gris);
            self::texto($im, 12, $izq - 10, $y + 5, $negro, number_format($v), false, 'r');
        }
        for ($v = 0; $v <= $topeR + 0.001; $v += $pasoR) {
            self::texto($im, 12, $der + 10, (int)$yR($v) + 5, $negro, number_format($v));
        }
        imagesetthickness($im, 2);
        imagerectangle($im, $izq, $arr, $der, $abj, $negro);
        self::texto($im, 14, 40, (int)(($arr + $abj) / 2) + 20, $negro, mb_strtoupper($unidad), true, 'l', 90);
        self::texto($im, 14, 1665, (int)(($arr + $abj) / 2) + 55, $negro, 'Cumulative', true, 'l', 90);

        // Barras
        $ancho = ($der - $izq) / $n;
        $bw = $ancho * 0.34;
        foreach ($s['fechas'] as $i => $f) {
            $cx = $izq + $ancho * ($i + .5);
            imagefilledrectangle($im, (int)($cx - $bw), (int)$yL($s['planeado'][$i]), (int)$cx, $abj, $barraP);
            imagerectangle($im, (int)($cx - $bw), (int)$yL($s['planeado'][$i]), (int)$cx, $abj, $negro);
            if ($s['actual'][$i] !== null) {
                imagefilledrectangle($im, (int)$cx, (int)$yL($s['actual'][$i]), (int)($cx + $bw), $abj, $barraR);
                imagerectangle($im, (int)$cx, (int)$yL($s['actual'][$i]), (int)($cx + $bw), $abj, $negro);
            }
            self::texto($im, 12, (int)$cx, $abj + 28, $negro, date('m/d/y', strtotime($f)), false, 'c');
        }
        // Líneas acumuladas
        $pts = fn(array $vals) => array_map(fn($v, $i) => $v === null ? null : [$izq + $ancho * ($i + .5), $yR($v)], $vals, array_keys($vals));
        foreach ([[$pts($s['planeado_acum']), $rojo, false, 'triangulo'], [$pts($s['actual_acum']), $negro, true, 'circulo']] as [$p, $col, $guion, $forma]) {
            for ($i = 1; $i < count($p); $i++) {
                if ($p[$i] && $p[$i - 1]) self::linea($im, $p[$i - 1][0], $p[$i - 1][1], $p[$i][0], $p[$i][1], $col, 5, $guion);
            }
            foreach ($p as $pt) if ($pt) self::marcador($im, $forma, $pt[0], $pt[1], $col);
        }
        // Fecha actual
        if (($s['hoy'] ?? null) !== null) {
            self::hoy($im, $izq + $ancho * ($s['hoy'] + .5), $arr, $abj);
        }
        // Leyenda
        $x = 380;
        $y = 860;
        foreach ([['Planned ' . $unidad, 'barraP'], ['Actual ' . $unidad, 'barraR'], ['Planned Cumulative ' . $unidad, 'lp'], ['Actual Cumulative ' . $unidad, 'la']] as [$t, $k]) {
            if ($k === 'barraP' || $k === 'barraR') {
                imagefilledrectangle($im, $x, $y - 14, $x + 34, $y + 2, $k === 'barraP' ? $barraP : $barraR);
                imagerectangle($im, $x, $y - 14, $x + 34, $y + 2, $negro);
            } elseif ($k === 'lp') {
                self::linea($im, $x, $y - 6, $x + 40, $y - 6, $rojo, 4);
                self::marcador($im, 'triangulo', $x + 20, $y - 6, $rojo, 7);
            } else {
                self::linea($im, $x, $y - 6, $x + 40, $y - 6, $negro, 4, true);
                self::marcador($im, 'circulo', $x + 20, $y - 6, $negro, 6);
            }
            self::texto($im, 12, $x + 50, $y, $negro, $t);
            $x += 60 + (int)(mb_strlen($t) * 9.5);
        }
        return self::png($im);
    }

    /** Líneas de avance acumulado (%) de todas las curvas */
    public static function maestra(array $m, string $titulo = 'GRÁFICA MAESTRA'): string
    {
        [$im, $blanco, $negro, $gris] = self::nuevo();
        [$izq, $der, $arr, $abj] = [110, 1650, 110, 720];
        $paleta = [[255, 90, 31], [0, 150, 200], [22, 163, 74], [168, 85, 247], [200, 160, 0], [236, 72, 153], [14, 165, 233], [132, 204, 22], [244, 63, 94], [20, 184, 166]];
        $n = max(2, count($m['semanas']));
        self::texto($im, 24, (int)(self::W / 2), 62, $negro, $titulo, true, 'c');
        for ($v = 0; $v <= 100; $v += 10) {
            $y = (int)($abj - ($v / 100) * ($abj - $arr));
            imagesetthickness($im, 1);
            imageline($im, $izq, $y, $der, $y, $gris);
            self::texto($im, 12, $izq - 10, $y + 5, $negro, $v . '%', false, 'r');
        }
        imagesetthickness($im, 2);
        imagerectangle($im, $izq, $arr, $der, $abj, $negro);
        self::texto($im, 14, 40, (int)(($arr + $abj) / 2) + 90, $negro, 'Avance acumulado (%)', true, 'l', 90);
        $x = fn($i) => $izq + ($der - $izq) * ($i / ($n - 1));
        $y = fn($v) => $abj - ($v / 100) * ($abj - $arr);
        $cadaX = max(1, (int)ceil($n / 14));
        foreach ($m['semanas'] as $i => $f) {
            if ($i % $cadaX === 0) self::texto($im, 11, (int)$x($i), $abj + 26, $negro, date('d/m/y', strtotime($f)), false, 'c');
        }
        if (($m['hoy'] ?? null) !== null) self::hoy($im, $x($m['hoy']), $arr, $abj);
        $lx = 110;
        $ly = 800;
        foreach ($m['series'] as $k => $s) {
            $c = $paleta[$k % count($paleta)];
            $col = imagecolorallocate($im, ...$c);
            foreach ([['planeado', false], ['real', true]] as [$campo, $guion]) {
                $v = $s[$campo];
                for ($i = 1; $i < count($v); $i++) {
                    if ($v[$i] !== null && $v[$i - 1] !== null) self::linea($im, $x($i - 1), $y($v[$i - 1]), $x($i), $y($v[$i]), $col, 4, $guion);
                }
                if ($guion) foreach ($v as $i => $val) if ($val !== null) self::marcador($im, 'circulo', $x($i), $y($val), $col, 5);
            }
            // Leyenda en columnas
            if ($lx > 1250) { $lx = 110; $ly += 28; }
            self::linea($im, $lx, $ly - 5, $lx + 40, $ly - 5, $col, 4);
            self::texto($im, 11, $lx + 50, $ly, $negro, mb_strimwidth($s['nombre'], 0, 34, '…'));
            $lx += 480;
        }
        self::texto($im, 10, $der, 885, $negro, 'Línea continua = planeado · discontinua = real', false, 'r');
        return self::png($im);
    }
}
