<?php
/**
 * Planos técnicos del proyecto DEMO (solo datos de ejemplo).
 *
 * Se dibujan con GD, sin red: planta arquitectónica, corte, alzado, patio de equipos,
 * instalaciones, detalles, plano de acabados y localización. Se usan desde
 * database/seed_demo_nuevas.php (vPlan y Comparador) y no forman parte del runtime.
 *
 * Coordenadas en píxeles de la hoja final; la clase dibuja al doble y reduce al guardar
 * para que las líneas y los textos salgan suaves.
 */

@ini_set('memory_limit', '512M');

final class DemoPlanoHoja
{
    public const S = 2;
    public int $W;
    public int $H;
    /** @var resource|\GdImage */
    private $im;
    /** @var array<string,int> */
    private array $cache = [];
    private string $font;
    private string $fontB;

    public function __construct(int $w, int $h, string $fondo = '#ffffff')
    {
        $this->W = $w;
        $this->H = $h;
        $this->im = imagecreatetruecolor($w * self::S, $h * self::S);
        imageantialias($this->im, true);
        imagefill($this->im, 0, 0, $this->color($fondo));
        $base = '/usr/share/fonts/truetype/dejavu/';
        $this->font = is_file($base . 'DejaVuSansCondensed.ttf') ? $base . 'DejaVuSansCondensed.ttf' : $base . 'DejaVuSans.ttf';
        $this->fontB = is_file($base . 'DejaVuSansCondensed-Bold.ttf') ? $base . 'DejaVuSansCondensed-Bold.ttf' : $base . 'DejaVuSans-Bold.ttf';
    }

    private function sx(float $v): int
    {
        return (int)round($v * self::S);
    }

    public function color(string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        $k = strtolower($hex);
        if (!isset($this->cache[$k])) {
            $this->cache[$k] = imagecolorallocate($this->im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
        }
        return $this->cache[$k];
    }

    /** @param array{0:float,1:float}|null $dash [trazo, espacio] */
    public function line(float $x1, float $y1, float $x2, float $y2, string $hex, float $w = 1, ?array $dash = null): void
    {
        if ($w > 1) imagesetthickness($this->im, $this->sx($w));
        $c = $this->color($hex);
        if (!$dash) {
            imageline($this->im, $this->sx($x1), $this->sx($y1), $this->sx($x2), $this->sx($y2), $c);
        } else {
            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $largo = sqrt($dx * $dx + $dy * $dy);
            if ($largo > 0.01) {
                $ux = $dx / $largo;
                $uy = $dy / $largo;
                $pos = 0.0;
                while ($pos < $largo) {
                    $fin = min($pos + $dash[0], $largo);
                    imageline($this->im, $this->sx($x1 + $ux * $pos), $this->sx($y1 + $uy * $pos), $this->sx($x1 + $ux * $fin), $this->sx($y1 + $uy * $fin), $c);
                    $pos = $fin + $dash[1];
                }
            }
        }
        if ($w > 1) imagesetthickness($this->im, $this->sx(1));
    }

    public function rect(float $x, float $y, float $w, float $h, ?string $fill = null, ?string $stroke = null, float $lw = 1): void
    {
        if ($fill) imagefilledrectangle($this->im, $this->sx($x), $this->sx($y), $this->sx($x + $w), $this->sx($y + $h), $this->color($fill));
        if ($stroke) {
            if ($lw > 1) imagesetthickness($this->im, $this->sx($lw));
            imagerectangle($this->im, $this->sx($x), $this->sx($y), $this->sx($x + $w), $this->sx($y + $h), $this->color($stroke));
            if ($lw > 1) imagesetthickness($this->im, $this->sx(1));
        }
    }

    public function ellipse(float $cx, float $cy, float $rx, float $ry, ?string $fill = null, ?string $stroke = null, float $lw = 1): void
    {
        $w = $this->sx($rx * 2);
        $h = $this->sx($ry * 2);
        if ($fill) imagefilledellipse($this->im, $this->sx($cx), $this->sx($cy), $w, $h, $this->color($fill));
        if ($stroke) {
            if ($lw > 1) imagesetthickness($this->im, $this->sx($lw));
            imageellipse($this->im, $this->sx($cx), $this->sx($cy), $w, $h, $this->color($stroke));
            if ($lw > 1) imagesetthickness($this->im, $this->sx(1));
        }
    }

    /** Arco (para abatimiento de puertas y curvas) */
    public function arc(float $cx, float $cy, float $rx, float $ry, float $inicio, float $fin, string $hex, float $lw = 1): void
    {
        if ($lw > 1) imagesetthickness($this->im, $this->sx($lw));
        imagearc($this->im, $this->sx($cx), $this->sx($cy), $this->sx($rx * 2), $this->sx($ry * 2), $inicio, $fin, $this->color($hex));
        if ($lw > 1) imagesetthickness($this->im, $this->sx(1));
    }

    /** @param array<int,array{0:float,1:float}> $puntos */
    public function poly(array $puntos, ?string $fill = null, ?string $stroke = null, float $lw = 1): void
    {
        if (count($puntos) < 2) return;
        $plano = [];
        foreach ($puntos as $p) {
            $plano[] = $this->sx($p[0]);
            $plano[] = $this->sx($p[1]);
        }
        if ($fill) imagefilledpolygon($this->im, $plano, $this->color($fill));
        if ($stroke) {
            if ($lw > 1) imagesetthickness($this->im, $this->sx($lw));
            $n = count($puntos);
            for ($i = 0; $i < $n; $i++) {
                $a = $puntos[$i];
                $b = $puntos[($i + 1) % $n];
                imageline($this->im, $this->sx($a[0]), $this->sx($a[1]), $this->sx($b[0]), $this->sx($b[1]), $this->color($stroke));
            }
            if ($lw > 1) imagesetthickness($this->im, $this->sx(1));
        }
    }

    public function text(float $x, float $y, float $size, string $hex, string $txt, bool $bold = false, string $align = 'left', float $angle = 0): void
    {
        if ($txt === '') return;
        $font = $bold ? $this->fontB : $this->font;
        $alto = $size * 1.22;
        foreach (explode("\n", $txt) as $i => $linea) {
            $bbox = imagettfbbox($size * self::S, $angle, $font, $linea);
            $ancho = ($bbox[2] - $bbox[0]) / self::S;
            $dx = $align === 'center' ? -$ancho / 2 : ($align === 'right' ? -$ancho : 0);
            imagettftext($this->im, $size * self::S, $angle, $this->sx($x + $dx), $this->sx($y + $i * $alto), $this->color($hex), $font, $linea);
        }
    }

    public function textWidth(float $size, string $txt, bool $bold = false): float
    {
        $font = $bold ? $this->fontB : $this->font;
        $bbox = imagettfbbox($size * self::S, 0, $font, $txt);
        return ($bbox[2] - $bbox[0]) / self::S;
    }

    /** Rellena un rectángulo con líneas diagonales (trama de material) */
    public function hatch(float $x, float $y, float $w, float $h, string $hex, float $space = 12, float $angle = 45, float $lw = 1): void
    {
        if ($w < 1 || $h < 1 || $space < 2) return;
        $c = $this->color($hex);
        if ($lw > 1) imagesetthickness($this->im, $this->sx($lw));
        if ($angle === 45) {
            for ($k = -$h; $k < $w; $k += $space) {
                $this->clip($x + $k, $y, $x + $k + $h, $y + $h, $x, $y, $w, $h, $c);
            }
        } else {
            for ($k = 0; $k < $w + $h; $k += $space) {
                $this->clip($x + $k, $y, $x + $k - $h, $y + $h, $x, $y, $w, $h, $c);
            }
        }
        if ($lw > 1) imagesetthickness($this->im, $this->sx(1));
    }

    /** Recorta un segmento al rectángulo (Liang-Barsky) y lo dibuja */
    private function clip(float $x1, float $y1, float $x2, float $y2, float $rx, float $ry, float $rw, float $rh, int $color): void
    {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $p = [-$dx, $dx, -$dy, $dy];
        $q = [$x1 - $rx, $rx + $rw - $x1, $y1 - $ry, $ry + $rh - $y1];
        $u1 = 0.0;
        $u2 = 1.0;
        for ($i = 0; $i < 4; $i++) {
            if (abs($p[$i]) < 0.00001) {
                if ($q[$i] < 0) return;
            } else {
                $t = $q[$i] / $p[$i];
                if ($p[$i] < 0) {
                    if ($t > $u2) return;
                    if ($t > $u1) $u1 = $t;
                } else {
                    if ($t < $u1) return;
                    if ($t < $u2) $u2 = $t;
                }
            }
        }
        imageline($this->im, $this->sx($x1 + $u1 * $dx), $this->sx($y1 + $u1 * $dy), $this->sx($x1 + $u2 * $dx), $this->sx($y1 + $u2 * $dy), $color);
    }

    /** Aislamiento: línea en zigzag */
    public function zigzag(float $x1, float $x2, float $y, float $amp = 8, float $step = 22, string $hex = '#64748b'): void
    {
        $puntos = [];
        $arriba = true;
        for ($x = $x1; $x <= $x2 + 0.01; $x += $step) {
            $puntos[] = [$x, $y + ($arriba ? -$amp : $amp)];
            $arriba = !$arriba;
        }
        $this->poly($puntos, null, $hex, 1.5);
    }

    public function arrow(float $x1, float $y1, float $x2, float $y2, string $hex = '#0f172a', float $lw = 1.5, float $cabeza = 10): void
    {
        $this->line($x1, $y1, $x2, $y2, $hex, $lw);
        $ang = atan2($y2 - $y1, $x2 - $x1);
        $a1 = $ang + 2.6;
        $a2 = $ang - 2.6;
        $this->line($x2, $y2, $x2 + $cabeza * cos($a1), $y2 + $cabeza * sin($a1), $hex, $lw);
        $this->line($x2, $y2, $x2 + $cabeza * cos($a2), $y2 + $cabeza * sin($a2), $hex, $lw);
    }

    /** Cota horizontal: extensiones, ticks y texto centrado */
    public function dimH(float $x1, float $x2, float $y, string $txt, string $hex = '#475569', float $size = 17): void
    {
        $this->line($x1, $y - 8, $x1, $y + 8, $hex, 1);
        $this->line($x2, $y - 8, $x2, $y + 8, $hex, 1);
        $this->line($x1, $y, $x2, $y, $hex, 1);
        $this->line($x1 - 5, $y + 5, $x1 + 5, $y - 5, $hex, 1);
        $this->line($x2 - 5, $y + 5, $x2 + 5, $y - 5, $hex, 1);
        $ancho = $this->textWidth($size, $txt) + 8;
        $cx = ($x1 + $x2) / 2;
        $this->rect($cx - $ancho / 2, $y - $size - 2, $ancho, $size + 4, '#ffffff');
        $this->text($cx, $y - 3, $size, $hex, $txt, false, 'center');
    }

    public function dimV(float $y1, float $y2, float $x, string $txt, string $hex = '#475569', float $size = 17): void
    {
        $this->line($x - 8, $y1, $x + 8, $y1, $hex, 1);
        $this->line($x - 8, $y2, $x + 8, $y2, $hex, 1);
        $this->line($x, $y1, $x, $y2, $hex, 1);
        $this->line($x - 5, $y1 + 5, $x + 5, $y1 - 5, $hex, 1);
        $this->line($x - 5, $y2 + 5, $x + 5, $y2 - 5, $hex, 1);
        $cy = ($y1 + $y2) / 2;
        $ancho = $this->textWidth($size, $txt) + 8;
        $this->rect($x - $size - 2, $cy - $ancho / 2, $size + 4, $ancho, '#ffffff');
        $this->text($x - 5, $cy, $size, $hex, $txt, false, 'center', 90);
    }

    public function bubble(float $x, float $y, string $txt, string $hex = '#0f172a', float $r = 15, float $size = 17): void
    {
        imagefilledellipse($this->im, $this->sx($x), $this->sx($y), $this->sx($r * 2), $this->sx($r * 2), $this->color('#ffffff'));
        imagesetthickness($this->im, $this->sx(1.4));
        imageellipse($this->im, $this->sx($x), $this->sx($y), $this->sx($r * 2), $this->sx($r * 2), $this->color($hex));
        imagesetthickness($this->im, $this->sx(1));
        $this->text($x, $y + $size * 0.38, $size, $hex, $txt, true, 'center');
    }

    /** Nube de revisión con festones */
    public function cloud(float $x, float $y, float $w, float $h, string $hex = '#e11d48', float $r = 11): void
    {
        $c = $this->color($hex);
        imagesetthickness($this->im, $this->sx(2));
        for ($i = 0; $i < $w; $i += $r * 2) {
            $ancho = min($r * 2, $w - $i);
            imagearc($this->im, $this->sx($x + $i + $ancho / 2), $this->sx($y), $this->sx($ancho), $this->sx($r * 2), 180, 360, $c);
            imagearc($this->im, $this->sx($x + $w - $i - $ancho / 2), $this->sx($y + $h), $this->sx($ancho), $this->sx($r * 2), 0, 180, $c);
        }
        for ($i = 0; $i < $h; $i += $r * 2) {
            $alto = min($r * 2, $h - $i);
            imagearc($this->im, $this->sx($x), $this->sx($y + $i + $alto / 2), $this->sx($r * 2), $this->sx($alto), 90, 270, $c);
            imagearc($this->im, $this->sx($x + $w), $this->sx($y + $h - $i - $alto / 2), $this->sx($r * 2), $this->sx($alto), 270, 90, $c);
        }
        imagesetthickness($this->im, $this->sx(1));
    }

    public function norte(float $x, float $y, float $r = 30): void
    {
        imagefilledellipse($this->im, $this->sx($x), $this->sx($y), $this->sx($r * 2), $this->sx($r * 2), $this->color('#ffffff'));
        imagesetthickness($this->im, $this->sx(1.4));
        imageellipse($this->im, $this->sx($x), $this->sx($y), $this->sx($r * 2), $this->sx($r * 2), $this->color('#475569'));
        imagesetthickness($this->im, $this->sx(1));
        $this->poly([[$x, $y - $r * 0.72], [$x - $r * 0.28, $y + $r * 0.28], [$x, $y + $r * 0.05], [$x + $r * 0.28, $y + $r * 0.28]], '#0f172a', '#0f172a', 1);
        $this->text($x, $y - $r - 8, 18, '#0f172a', 'N', true, 'center');
    }

    public function escala(float $x, float $y, float $metros, float $pxPorMetro, string $texto = 'm'): void
    {
        $tramos = 4;
        $largo = $metros * $pxPorMetro;
        $paso = $largo / $tramos;
        for ($i = 0; $i < $tramos; $i++) {
            $this->rect($x + $i * $paso, $y, $paso, 7, $i % 2 ? '#ffffff' : '#0f172a', '#0f172a', 1);
        }
        $this->text($x, $y - 6, 15, '#475569', '0');
        $this->text($x + $largo, $y - 6, 15, '#475569', rtrim(rtrim(number_format($metros, 2, '.', ''), '0'), '.') . ' ' . $texto, false, 'center');
    }

    /** Marco, cajetín y datos comunes del plano */
    public function hoja(string $titulo, string $subtitulo, string $lamina, string $rev, string $fecha, string $escala, string $proyecto = 'NEXO DATA CAMPUS · CENTRO DE DATOS TIER III', string $lugar = 'Parque Tecnológico, Querétaro, México'): void
    {
        $this->rect(18, 18, $this->W - 36, $this->H - 36, null, '#0f172a', 2);
        $top = $this->H - 168;
        $this->line(18, $top, $this->W - 18, $top, '#0f172a', 2);
        $this->rect(18, $top, $this->W - 36, 150, '#f8fafc');
        $this->line(18, $top, $this->W - 18, $top, '#0f172a', 2);
        $this->text(34, $top + 34, 25, '#0f172a', $proyecto, true);
        $this->text(34, $top + 64, 20, '#0e7490', $titulo, true);
        $this->text(34, $top + 90, 16, '#475569', $subtitulo);
        $this->text(34, $top + 116, 14, '#64748b', $lugar . '  ·  PROYECTO EJECUTIVO · PARA CONSTRUCCIÓN');
        $x = $this->W - 650;
        $this->line($x, $top, $x, $this->H - 18, '#0f172a', 1.5);
        $campos = [['REV.', $rev], ['ESCALA', $escala], ['FECHA', $fecha], ['LÁMINA', $lamina], ['DIBUJÓ', 'P. Rentería'], ['REVISÓ', 'D. Carranza']];
        foreach ($campos as $i => [$k, $v]) {
            $col = $i % 3;
            $fila = intdiv($i, 3);
            $cx = $x + 12 + $col * 212;
            $cy = $top + 34 + $fila * 56;
            $this->text($cx, $cy, 13, '#64748b', $k, true);
            $this->text($cx, $cy + 24, 19, '#0f172a', $v, true);
            if ($col) $this->line($cx - 12, $cy - 16, $cx - 12, $cy + 34, '#cbd5e1', 1);
        }
    }

    public function guardar(string $ruta): bool
    {
        $final = imagescale($this->im, $this->W, $this->H, IMG_BICUBIC);
        if (!$final) return false;
        $ok = imagepng($final, $ruta);
        imagedestroy($this->im);
        imagedestroy($final);
        return (bool)$ok;
    }
}

/** Puerta en un muro horizontal: abre el hueco y dibuja la hoja y su abatimiento */
function demo_plano_puerta_h(DemoPlanoHoja $h, float $x, float $y, float $ancho, int $giro = 1, float $grosor = 9): void
{
    $h->rect($x, $y - $grosor, $ancho, $grosor * 2, '#ffffff');
    $h->line($x, $y, $x + $ancho, $y, '#ffffff', $grosor + 2);
    $h->arc($x, $y, $ancho, $ancho, $giro > 0 ? 270 : 180, $giro > 0 ? 360 : 270, '#475569', 1.5);
    $h->line($x, $y, $x, $y + $giro * $ancho, '#475569', 2);
    $h->ellipse($x, $y, 3, 3, '#475569');
}

/** Puerta en un muro vertical */
function demo_plano_puerta_v(DemoPlanoHoja $h, float $x, float $y, float $alto, int $giro = 1, float $grosor = 9): void
{
    $h->rect($x - $grosor, $y, $grosor * 2, $alto, '#ffffff');
    $h->line($x, $y, $x, $y + $alto, '#ffffff', $grosor + 2);
    $h->arc($x, $y, $alto, $alto, $giro > 0 ? 0 : 90, $giro > 0 ? 90 : 180, '#475569', 1.5);
    $h->line($x, $y, $x + $giro * $alto, $y, '#475569', 2);
    $h->ellipse($x, $y, 3, 3, '#475569');
}

/** Planta arquitectónica de la sala blanca (rev A/B) */
function demo_plano_sala_blanca(string $rev, string $fecha, bool $acabados = false): ?string
{
    $h = new DemoPlanoHoja(2600, 1700, '#ffffff');
    $x0 = 150.0;
    $y0 = 110.0;
    $e = 36.0;
    $X = fn(float $m) => $x0 + $m * $e;
    $Y = fn(float $m) => $y0 + $m * $e;
    $M = fn(float $m) => $m * $e;

    // Marco de la hoja
    $h->hoja(
        $acabados ? 'PLANO DE ACABADOS · SALA BLANCA Y ÁREAS TÉCNICAS' : 'PLANTA ARQUITECTÓNICA · SALA BLANCA NAVE A',
        $acabados ? 'Claves de material por zona · Nivel +0.45 · Escala 1:100' : 'Distribución de racks, pasillos fríos/calientes y salas técnicas · Nivel +0.45 · Escala 1:100',
        $acabados ? 'ARQ-502' : 'A-201', $rev, $fecha, '1:100'
    );

    // Retícula de ejes
    for ($i = 0; $i <= 6; $i++) {
        $x = $X($i * 10);
        $h->line($x, $Y(0) - 34, $x, $Y(36) + 20, '#cbd5e1', 1, [9, 7]);
        $h->bubble($x, $Y(0) - 50, (string)($i + 1), '#0f172a', 14, 16);
    }
    foreach (['A', 'B', 'C', 'D', 'E'] as $i => $letra) {
        $y = $Y($i * 9);
        $h->line($X(0) - 34, $y, $X(60) + 20, $y, '#cbd5e1', 1, [9, 7]);
        $h->bubble($X(0) - 50, $y, $letra, '#0f172a', 14, 16);
    }

    // Envolvente
    $h->rect($X(0), $Y(0), $M(60), $M(36), '#cbd5e1', '#0f172a', 4);
    $h->rect($X(0) + 9, $Y(0) + 9, $M(60) - 18, $M(36) - 18, '#ffffff', null);

    // Zonas
    $zonas = [
        ['sala', 1, 1, 43, 34, '#f0fdff'],
        ['electrica', 46, 1, 13, 11, '#fffbeb'],
        ['ups', 46, 12, 13, 8, '#f5f3ff'],
        ['mdf', 46, 20, 13, 7, '#ecfdf5'],
        ['almacen', 46, 27, 13, 8, '#f8fafc'],
    ];
    foreach ($zonas as [$id, $zx, $zy, $zw, $zh, $color]) {
        $h->rect($X($zx), $Y($zy), $M($zw), $M($zh), $color, '#334155', 2.5);
    }
    // Pasillo
    $h->rect($X(44), $Y(1), $M(1.6), $M(34), '#f1f5f9', '#334155', 2.5);
    // Vestíbulo
    $h->rect($X(24), $Y(34), $M(6), $M(2), '#ffffff', '#334155', 2.5);

    // Muros interiores
    $h->line($X(44), $Y(1), $X(44), $Y(35), '#334155', 7);
    $h->line($X(45.6), $Y(1), $X(45.6), $Y(35), '#334155', 7);
    foreach ([12, 20, 27] as $yy) $h->line($X(45.6), $Y($yy), $X(59), $Y($yy), '#334155', 7);

    // Puertas
    demo_plano_puerta_h($h, $X(25), $Y(36), $M(2.4), 1, 9);
    demo_plano_puerta_v($h, $X(44.8), $Y(18), $M(2.4), 1, 9);
    foreach ([[6, 2.2], [15, 2.2], [23, 2.2], [30, 2.2]] as [$yy, $aa]) {
        demo_plano_puerta_v($h, $X(45.6), $Y($yy), $M($aa), 1, 9);
    }

    // Retícula de piso técnico y zona de expansión
    if (!$acabados) {
        for ($gx = 2.0; $gx < 43; $gx += 1.2) $h->line($X($gx), $Y(1.2), $X($gx), $Y(34.8), '#e8eef4', 1);
        for ($gy = 2.0; $gy < 35; $gy += 1.2) $h->line($X(1.2), $Y($gy), $X(43), $Y($gy), '#e8eef4', 1);
        $h->rect($X(7.5), $Y(25.0), $M(10.5), $M(6.8), '#f8fafc', '#94a3b8', 1.2);
        $h->hatch($X(7.5), $Y(25.0), $M(10.5), $M(6.8), '#e2e8f0', 16, 45, 1);
        $h->text($X(12.75), $Y(28.3), 14, '#64748b', 'ZONA DE EXPANSIÓN', true, 'center');
        $h->text($X(12.75), $Y(29.2), 11, '#94a3b8', 'FUTURA · 48 RACKS ADICIONALES', false, 'center');
    }

    // Racks: 4 pods de 2 filas x 6 racks = 48
    if (!$acabados) {
        $pods = [[7, 7, 'A'], [7, 18, 'B'], [22, 7, 'C'], [22, 18, 'D']];
        foreach ($pods as [$px, $py, $letra]) {
            // Pasillo frío
            $h->rect($X($px), $Y($py + 1.2), $M(3.6), $M(1.2), '#cffafe', '#67e8f9', 1.5);
            for ($i = 0; $i < 6; $i++) {
                $h->rect($X($px + 0.15 + $i * 0.6), $Y($py + 1.35), $M(0.3), $M(0.9), '#a5f3fc', null);
            }
            // Filas de racks
            for ($fila = 0; $fila < 2; $fila++) {
                $ry = $py + $fila * 2.4;
                for ($i = 0; $i < 6; $i++) {
                    $rx = $px + $i * 0.6;
                    $h->rect($X($rx), $Y($ry), $M(0.6), $M(1.2), '#1e293b', '#0f172a', 1.5);
                    $h->rect($X($rx) + 4, $Y($ry) + 5, $M(0.6) - 8, $M(0.18), '#06b6d4', null);
                    for ($u = 0; $u < 6; $u++) $h->line($X($rx) + 6, $Y($ry) + 14 + $u * 6, $X($rx) + $M(0.6) - 6, $Y($ry) + 14 + $u * 6, '#475569', 1);
                }
            }
            // Pasillo caliente
            $h->rect($X($px), $Y($py + 3.6), $M(3.6), $M(1.2), '#fee2e2', '#fca5a5', 1);
            $h->arrow($X($px + 0.4), $Y($py + 4.2), $X($px + 1.2), $Y($py + 4.2), '#b91c1c', 1.2, 6);
            $h->arrow($X($px + 3.2), $Y($py + 4.2), $X($px + 2.4), $Y($py + 4.2), '#b91c1c', 1.2, 6);
            $h->text($X($px + 0.25), $Y($py + 1.0), 14, '#0e7490', 'PASILLO FRÍO', true);
            $h->text($X($px + 0.25), $Y($py + 4.55), 12, '#b91c1c', 'PASILLO CALIENTE');
            $h->text($X($px + 1.8), $Y($py + 5.2), 18, '#0f172a', 'POD ' . $letra, true, 'center');
        }
        // CRAC en el muro oriente
        for ($i = 0; $i < 8; $i++) {
            $yy = 4 + $i * 3.7;
            $h->rect($X(37), $Y($yy), $M(2.2), $M(2.6), '#e0f2fe', '#0e7490', 2);
            $h->ellipse($X(38.1), $Y($yy + 0.9), $M(0.55), $M(0.55), '#bae6fd', '#0e7490', 1.5);
            $h->ellipse($X(38.1), $Y($yy + 1.9), $M(0.55), $M(0.55), '#bae6fd', '#0e7490', 1.5);
            $h->text($X(37.1), $Y($yy + 2.45), 11, '#0e7490', 'CRAC-' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT), true);
        }
        // Zona de staging
        $h->rect($X(29), $Y(27), $M(8), $M(5), '#f8fafc', '#94a3b8', 1.5);
        $h->text($X(33), $Y(29.2), 13, '#64748b', 'ZONA DE STAGING', true, 'center');
        $h->text($X(33), $Y(30.2), 11, '#94a3b8', 'EQUIPOS EN PRUEBA', false, 'center');
    }

    // Sala eléctrica: subestación y tableros
    $h->rect($X(47.2), $Y(2.0), $M(4.0), $M(2.2), '#fde68a', '#92400e', 2);
    $h->text($X(49.2), $Y(2.7), 12, '#92400e', 'TR-01 / TR-02', true, 'center');
    $h->text($X(49.2), $Y(3.5), 10, '#92400e', '2 × 2,500 kVA', false, 'center');
    for ($i = 0; $i < 4; $i++) {
        $h->rect($X(47.2 + $i * 2.8), $Y(5.6), $M(2.2), $M(4.0), '#fef3c7', '#92400e', 1.5);
        $h->text($X(48.3 + $i * 2.8), $Y(7.8), 10, '#92400e', 'TG-' . ($i + 1), true, 'center');
    }
    $h->text($X(49.5), $Y(10.7), 12, '#92400e', 'SUBESTACIÓN 34.5 kV', true, 'center');
    // UPS
    for ($i = 0; $i < 4; $i++) {
        $h->rect($X(47.2), $Y(13 + $i * 1.7), $M(4.5), $M(1.1), '#ddd6fe', '#6d28d9', 1.5);
        $h->text($X(49.4), $Y(13.7 + $i * 1.7), 9, '#6d28d9', 'UPS-' . ($i + 1), true, 'center');
    }
    $h->text($X(52.5), $Y(15.5), 11, '#6d28d9', 'BATERÍAS', true, 'center');
    // MDF
    $h->rect($X(47.2), $Y(21), $M(4), $M(4.5), '#d1fae5', '#047857', 1.5);
    $h->text($X(49.2), $Y(23.3), 11, '#047857', 'MDF / IDF', true, 'center');
    $h->text($X(49.2), $Y(24.3), 9, '#047857', 'RACKS 42U', false, 'center');
    // Almacén
    $h->rect($X(47.2), $Y(28), $M(9), $M(3), '#f1f5f9', '#94a3b8', 1.5);
    $h->text($X(51.7), $Y(30), 11, '#64748b', 'ALMACÉN Y REFACCIONES', true, 'center');

    // Rótulos de zonas
    $h->text($X(20.5), $Y(3.0), 26, '#0e7490', 'SALA BLANCA · NAVE A', true, 'center');
    $h->text($X(20.5), $Y(3.9), 13, '#0e7490', '48 RACKS · 4 PODS · N+1', false, 'center');
    $h->text($X(49.3), $Y(1.7), 14, '#92400e', 'SALA ELÉCTRICA', true, 'center');
    $h->text($X(52.5), $Y(12.8), 14, '#6d28d9', 'SALA DE UPS', true, 'center');
    $h->text($X(47.3), $Y(20.8), 12, '#047857', 'TELECOM', true);
    $h->text($X(44.8), $Y(32.0), 11, '#475569', 'PASILLO', true, 'center');

    // Nube de revisión B sobre el pod D
    if ($rev !== 'A') {
        $h->cloud($X(21.3), $Y(17.4), $M(5.1), $M(4.9), '#e11d48', 10);
        $h->text($X(23.8), $Y(16.6), 12, '#e11d48', 'REV. B · POD D NUEVO', true, 'center');
    }

    // Línea de corte A-A
    $h->line($X(31.5), $Y(-1.0), $X(31.5), $Y(37.0), '#e11d48', 2, [14, 8]);
    $h->arrow($X(31.5), $Y(-1.0), $X(31.5), $Y(0.6), '#e11d48', 1.6, 8);
    $h->arrow($X(31.5), $Y(37.0), $X(31.5), $Y(35.4), '#e11d48', 1.6, 8);
    $h->bubble($X(31.5), $Y(-2.0), 'A', '#e11d48', 14, 16);
    $h->bubble($X(31.5), $Y(38.0), 'A', '#e11d48', 14, 16);

    // Callouts de detalle
    $h->bubble($X(28.0), $Y(21.0), '1', '#0e7490', 13, 15);
    $h->line($X(28.0), $Y(21.0), $X(26.4), $Y(20.6), '#0e7490', 1.5);
    $h->bubble($X(42.0), $Y(25.0), '2', '#0e7490', 13, 15);
    $h->line($X(42.0), $Y(25.0), $X(39.4), $Y(25.0), '#0e7490', 1.5);

    // Cotas
    $h->dimH($X(0), $X(60), $Y(37.9), '60.00', '#475569', 17);
    $h->dimV($Y(0), $Y(36), $X(-2.2), '36.00', '#475569', 17);
    foreach ([10, 20, 30, 40, 50] as $i => $m) {
        $h->dimH($X($m - 10), $X($m), $Y(38.9), '10.00', '#94a3b8', 12);
    }

    // Norte y escala
    $h->norte($X(57.2), $Y(33.0), 24);
    $h->rect($X(1.5) - 8, $Y(32.9) - 30, 430, 58, '#ffffff', null);
    $h->escala($X(2.0), $Y(33.4), 10, $e, 'm');

    // Notas
    $h->rect($X(30.5), $Y(33.2), $M(12.4), $M(1.9), '#ffffff', '#cbd5e1', 1);
    $h->text($X(31.0), $Y(33.8), 9, '#475569', 'NOTAS:', true);
    $h->text($X(31.0), $Y(34.4), 8, '#64748b', '1. Piso técnico modular 600 × 600 mm, carga 1.5 t/m².');
    $h->text($X(31.0), $Y(35.0), 8, '#64748b', '2. Pasillos fríos con suministro por piso perforado.');

    $ruta = sys_get_temp_dir() . '/demo-plano-sala-' . strtolower($rev) . '.png';
    return $h->guardar($ruta) ? $ruta : null;
}

/**
 * Genera (y cachea) un plano del demo.
 * Claves: sala-blanca, acabados, corte, alzado, patio, instalaciones, detalles, localizacion.
 */
function demo_plano(string $clave, string $rev = 'B', string $fecha = ''): ?string
{
    static $cache = [];
    $fecha = $fecha !== '' ? $fecha : date('d/m/Y');
    $k = $clave . '-' . $rev . '-' . $fecha;
    if (isset($cache[$k]) && is_file($cache[$k])) return $cache[$k];
    if (!function_exists('imagecreatetruecolor')) return null;
    $ruta = match ($clave) {
        'sala-blanca' => demo_plano_sala_blanca($rev, $fecha),
        'acabados' => demo_plano_acabados($rev, $fecha),
        'corte' => demo_plano_corte($rev, $fecha),
        'alzado' => demo_plano_alzado($rev, $fecha),
        'patio' => demo_plano_patio($rev, $fecha),
        'instalaciones' => demo_plano_instalaciones($rev, $fecha),
        'detalles' => demo_plano_detalles($rev, $fecha),
        'localizacion' => demo_plano_localizacion($rev, $fecha),
        default => null,
    };
    return $ruta ? $cache[$k] = $ruta : null;
}

/** Plano clave de acabados: zonas, claves y leyenda (sin equipos) */
function demo_plano_acabados(string $rev, string $fecha): ?string
{
    $h = new DemoPlanoHoja(1800, 1320, '#ffffff');
    $x0 = 120.0;
    $y0 = 110.0;
    $e = 25.0;
    $X = fn(float $m) => $x0 + $m * $e;
    $Y = fn(float $m) => $y0 + $m * $e;

    $h->rect(18, 18, 1764, 1284, null, '#0f172a', 2);
    $h->text(40, 60, 26, '#0f172a', 'PLANO CLAVE DE ACABADOS · NAVE A', true);
    $h->text(40, 88, 15, '#475569', 'Claves de material por zona · Nivel +0.45 · Escala 1:200 · Rev. ' . $rev . ' · ' . $fecha);

    // Envolvente
    $h->rect($X(0), $Y(0), 60 * $e, 36 * $e, '#cbd5e1', '#0f172a', 3);
    $h->rect($X(0) + 7, $Y(0) + 7, 60 * $e - 14, 36 * $e - 14, '#ffffff', null);

    // Zonas con clave
    $zonas = [
        [1, 1, 43, 34, '#e0f2fe', 'SALA BLANCA', 'M-01'],
        [46, 1, 13, 11, '#fef3c7', 'ELÉCTRICA', 'M-06'],
        [46, 12, 13, 8, '#ede9fe', 'UPS', 'M-06'],
        [46, 20, 13, 7, '#dcfce7', 'TELECOM', 'M-03'],
        [46, 27, 13, 8, '#f1f5f9', 'ALMACÉN', 'M-06'],
    ];
    foreach ($zonas as [$zx, $zy, $zw, $zh, $color, $nombre, $clave]) {
        $h->rect($X($zx), $Y($zy), $zw * $e, $zh * $e, $color, '#334155', 2);
        $h->text($X($zx + $zw / 2), $Y($zy + $zh / 2 - 0.5), 13, '#334155', $nombre, true, 'center');
        $h->rect($X($zx + 0.6), $Y($zy + $zh - 2.0), 76, 26, '#ffffff', '#64748b', 1);
        $h->text($X($zx + 0.6) + 38, $Y($zy + $zh - 2.0) + 18, 14, '#0f172a', $clave, true, 'center');
    }
    $h->rect($X(44), $Y(1), 1.6 * $e, 34 * $e, '#f8fafc', '#334155', 2);
    $h->text($X(44.8), $Y(18), 10, '#475569', 'PASILLO', true, 'center', 90);

    // Muros y puertas
    $h->line($X(44), $Y(1), $X(44), $Y(35), '#334155', 5);
    $h->line($X(45.6), $Y(1), $X(45.6), $Y(35), '#334155', 5);
    foreach ([12, 20, 27] as $yy) $h->line($X(45.6), $Y($yy), $X(59), $Y($yy), '#334155', 5);
    $h->rect($X(25), $Y(35.7), 4 * $e, 6, '#ffffff');
    $h->line($X(25), $Y(34), $X(29), $Y(34), '#ffffff', 9);

    // Claves en el perímetro y en el interior
    $claves = [
        [7, 15, 'M-01'], [2.2, 2.2, 'M-02'], [48, 23, 'M-03'], [38, 33.2, 'M-04'],
        [44.4, 6, 'M-05'], [49, 6.5, 'M-06'], [20, 35.2, 'M-07'], [20, 0.6, 'M-08'],
        [57, 35.2, 'M-09'], [26, 35.2, 'M-10'], [30, 0.6, 'M-11'], [44.5, 33.5, 'M-12'],
    ];
    foreach ($claves as [$cx, $cy, $cod]) {
        $h->rect($X($cx) - 26, $Y($cy) - 15, 52, 30, '#ffffff', '#0f172a', 1.5);
        $h->text($X($cx), $Y($cy) + 5, 13, '#0f172a', $cod, true, 'center');
        $h->ellipse($X($cx), $Y($cy) + 18, 2, 2, '#0f172a');
    }

    // Leyenda
    $leyenda = [
        ['#e0f2fe', 'M-01', 'PISO TÉCNICO 600 × 600'],
        ['#fef3c7', 'M-02', 'PINTURA EPÓXICA EN MUROS'],
        ['#ede9fe', 'M-03', 'PLAFÓN ACÚSTICO 600 × 600'],
        ['#dcfce7', 'M-04', 'PORCELANATO TÉCNICO'],
        ['#fee2e2', 'M-05', 'SELLO CORTAFUEGO'],
        ['#fde68a', 'M-06', 'CONCRETO PULIDO'],
        ['#bae6fd', 'M-07', 'CANCELERÍA DE ALUMINIO'],
        ['#cbd5e1', 'M-08', 'PINTURA INTUMESCENTE'],
        ['#dbeafe', 'M-09', 'IMPERMEABILIZACIÓN'],
        ['#f1f5f9', 'M-10', 'PORCELANATO EXTERIOR'],
        ['#93c5fd', 'M-11', 'MURO CORTINA'],
        ['#fca5a5', 'M-12', 'SEÑALIZACIÓN DE EVACUACIÓN'],
    ];
    $lx = 60;
    $ly = 1080;
    $h->text($lx, $ly, 16, '#0f172a', 'CLAVES DE ACABADO', true);
    foreach ($leyenda as $i => [$c, $cod, $txt]) {
        $col = $i % 3;
        $fila = intdiv($i, 3);
        $xx = $lx + $col * 560;
        $yy = $ly + 34 + $fila * 32;
        $h->rect($xx, $yy - 18, 24, 24, $c, '#64748b', 1);
        $h->text($xx + 34, $yy, 14, '#0f172a', $cod, true);
        $h->text($xx + 96, $yy, 13, '#475569', $txt);
    }

    // Norte, cotas y escala
    $h->norte($X(57), $Y(3.2), 22);
    $h->dimH($X(0), $X(60), $Y(37.6), '60.00', '#475569', 15);
    $h->dimV($Y(0), $Y(36), $X(-2.0), '36.00', '#475569', 15);

    $ruta = sys_get_temp_dir() . '/demo-plano-acabados-' . strtolower($rev) . '.png';
    return $h->guardar($ruta) ? $ruta : null;
}

/** Corte transversal A-A de la sala blanca */
function demo_plano_corte(string $rev, string $fecha): ?string
{
    $h = new DemoPlanoHoja(2600, 1100, '#ffffff');
    $h->hoja('CORTE TRANSVERSAL A-A · SALA BLANCA', 'Estructura, piso técnico, plenum y cubierta · Escala 1:50', 'A-202', $rev, $fecha, '1:50');
    $x0 = 300.0;
    $y0 = 820.0;
    $e = 72.0;
    $X = fn(float $m) => $x0 + $m * $e;
    $Y = fn(float $m) => $y0 - $m * $e;
    $banda = function (float $x1, float $x2, float $y1, float $y2, ?string $fill, ?string $stroke = null, float $lw = 1) use ($h, $X, $Y): void {
        $h->rect($X($x1), $Y($y2), $X($x2) - $X($x1), $Y($y1) - $Y($y2), $fill, $stroke, $lw);
    };

    // Terreno y cimentación
    $h->line($X(-2), $Y(0), $X(32), $Y(0), '#0f172a', 3);
    $banda(-1, 31, -0.8, 0, '#e2e8f0', '#334155', 2);
    $h->hatch($X(-1), $Y(0), $X(31) - $X(-1), $Y(-0.8) - $Y(0), '#94a3b8', 14, 45, 1);
    $banda(0, 30, 0, 0.4, '#cbd5e1', '#334155', 2);
    $h->hatch($X(0), $Y(0.4), $X(30) - $X(0), $Y(0) - $Y(0.4), '#94a3b8', 12, 45, 1);

    // Piso técnico
    $banda(0, 30, 0.4, 0.75, '#f1f5f9', '#475569', 1.5);
    for ($x = 1; $x < 30; $x += 2) {
        $h->rect($X($x) - 6, $Y(0.75), 12, $Y(0.4) - $Y(0.75), '#94a3b8', '#475569', 1);
    }
    $h->line($X(0), $Y(0.75), $X(30), $Y(0.75), '#0f172a', 2);

    // Columnas
    foreach ([0, 10, 20, 30] as $cx) {
        $banda($cx - 0.3, $cx + 0.3, 0, 6.0, '#cbd5e1', '#334155', 2);
        $h->hatch($X($cx - 0.3), $Y(6.0), $X($cx + 0.3) - $X($cx - 0.3), $Y(0) - $Y(6.0), '#94a3b8', 10, 45, 1);
    }

    // Racks en corte
    foreach ([[4, 10], [18, 24]] as [$rx1, $rx2]) {
        $banda($rx1, $rx2, 0.75, 3.0, '#1e293b', '#0f172a', 2);
        for ($y = 0.95; $y < 2.9; $y += 0.26) {
            $h->line($X($rx1) + 4, $Y($y), $X($rx2) - 4, $Y($y), '#475569', 1);
        }
        $h->rect($X($rx1), $Y(3.0), $X($rx2) - $X($rx1), 8, '#06b6d4', null);
        $h->text($X(($rx1 + $rx2) / 2), $Y(3.24), 14, '#0f172a', 'RACKS 42U', true, 'center');
    }

    // CRAC
    $banda(26, 29, 0.75, 3.6, '#e0f2fe', '#0e7490', 2);
    $h->ellipse($X(27.5), $Y(2.7), 22, 22, '#bae6fd', '#0e7490', 1.5);
    $h->text($X(27.5), $Y(1.2), 12, '#0e7490', 'CRAC', true, 'center');

    // Plenum, cubierta y pretil
    $h->line($X(0), $Y(4.2), $X(30), $Y(4.2), '#0f172a', 2);
    $banda(0, 30, 6.0, 6.35, '#cbd5e1', '#334155', 2);
    $h->hatch($X(0), $Y(6.35), $X(30) - $X(0), $Y(6.0) - $Y(6.35), '#94a3b8', 12, 45, 1);
    $banda(0, 30, 6.35, 6.5, '#fef3c7', '#92400e', 1.5);
    $h->zigzag($X(0), $X(30), $Y(6.42), 5, 24, '#92400e');
    $banda(0, 0.4, 6.35, 6.9, '#cbd5e1', '#334155', 2);
    $banda(29.6, 30, 6.35, 6.9, '#cbd5e1', '#334155', 2);

    // Ductos y charolas en el plenum
    $banda(2, 28, 4.55, 5.05, '#e0f2fe', '#0e7490', 2);
    $h->text($X(15), $Y(4.8), 12, '#0e7490', 'DUCTO DE SUMINISTRO', true, 'center');
    $h->line($X(2), $Y(3.6), $X(28), $Y(3.6), '#f59e0b', 4);
    $h->line($X(2), $Y(3.7), $X(28), $Y(3.7), '#f59e0b', 2);
    $h->text($X(15), $Y(3.95), 10, '#b45309', 'CHAROLA PORTACABLE', false, 'center');

    // Niveles
    $niveles = [[-0.6, '-0.60'], [0, '±0.00'], [0.45, '+0.45'], [4.2, '+4.20'], [6.0, '+6.00'], [6.6, '+6.60']];
    foreach ($niveles as [$n, $txt]) {
        $h->line($X(-1.4), $Y($n), $X(0), $Y($n), '#475569', 1);
        $h->poly([[$X(-1.4), $Y($n)], [$X(-1.1), $Y($n) - 6], [$X(-1.1), $Y($n) + 6]], '#475569', '#475569', 1);
        $h->text($X(-1.55), $Y($n) + 5, 13, '#475569', $txt, false, 'right');
    }

    // Cotas
    $h->dimH($X(0), $X(10), $Y(9.4), '10.00', '#475569', 15);
    $h->dimH($X(10), $X(20), $Y(9.4), '10.00', '#475569', 15);
    $h->dimH($X(20), $X(30), $Y(9.4), '10.00', '#475569', 15);
    $h->dimV($Y(0), $Y(6.5), $X(-2.5), '6.50', '#475569', 15);

    // Callouts
    $h->bubble($X(12), $Y(1.2), '1', '#0e7490', 14, 16);
    $h->line($X(12), $Y(1.2), $X(10), $Y(1.0), '#0e7490', 1.5);
    $h->bubble($X(12), $Y(5.7), '2', '#0e7490', 14, 16);
    $h->line($X(12), $Y(5.7), $X(12), $Y(6.3), '#0e7490', 1.5);

    // Leyendas
    $h->text($X(1), $Y(7.5), 13, '#0f172a', 'LOSA DE CIMENTACIÓN e = 0.40 m', true);
    $h->text($X(1), $Y(7.15), 12, '#475569', 'PISO TÉCNICO MODULAR 600 × 600 mm, CARGA 1.5 t/m²');
    $h->text($X(15), $Y(7.5), 13, '#0f172a', 'CUBIERTA TÉCNICA', true);
    $h->text($X(15), $Y(7.15), 12, '#475569', 'LOSA + IMPERMEABILIZACIÓN + AISLAMIENTO TÉRMICO');

    $ruta = sys_get_temp_dir() . '/demo-plano-corte-' . strtolower($rev) . '.png';
    return $h->guardar($ruta) ? $ruta : null;
}

/** Alzado sur con muro cortina */
function demo_plano_alzado(string $rev, string $fecha): ?string
{
    $h = new DemoPlanoHoja(2600, 1100, '#ffffff');
    $h->hoja('ALZADO SUR · MURO CORTINA', 'Fachada de acceso y piel de cristal · Escala 1:100', 'A-301', $rev, $fecha, '1:100');
    $x0 = 330.0;
    $y0 = 820.0;
    $e = 72.0;
    $X = fn(float $m) => $x0 + $m * $e;
    $Y = fn(float $m) => $y0 - $m * $e;

    // Terreno
    $h->line($X(-2), $Y(0), $X(15.8), $Y(0), '#0f172a', 3);
    $h->hatch($X(-2), $Y(0), $X(15.8) - $X(-2), 26, '#e2e8f0', 14, 45, 1);

    // Paño de fachada
    $h->rect($X(0), $Y(9.0), $X(30) - $X(0), $Y(0) - $Y(9.0), '#e0f2fe', '#0f172a', 3);
    // Paneles de cristal
    for ($i = 0; $i < 20; $i++) {
        $gx = $i * 1.5;
        $h->rect($X($gx), $Y(8.8), $X($gx + 1.42) - $X($gx), $Y(0.2) - $Y(8.8), $i % 4 === 0 ? '#bae6fd' : '#e0f2fe', '#94a3b8', 1);
    }
    // Montantes y travesaños
    for ($i = 0; $i <= 20; $i++) $h->line($X($i * 1.5), $Y(9.0), $X($i * 1.5), $Y(0), '#64748b', 2);
    foreach ([0.9, 3.0, 4.5, 6.0, 7.5, 9.0] as $ty) $h->line($X(0), $Y($ty), $X(30), $Y($ty), '#64748b', 2);
    // Reflejos
    for ($i = 0; $i < 6; $i++) {
        $gx = $i * 5 + 0.4;
        $h->poly([[$X($gx), $Y(8.4)], [$X($gx + 1.2), $Y(8.4)], [$X($gx + 0.4), $Y(1.0)], [$X($gx - 0.4), $Y(1.0)]], '#dbeafe', null);
    }
    // Acceso y louver
    $h->rect($X(13), $Y(3.0), $X(16) - $X(13), $Y(0) - $Y(3.0), '#0f172a', '#334155', 2);
    $h->rect($X(13.2), $Y(2.8), $X(14.2) - $X(13.2), $Y(0.1) - $Y(2.8), '#bae6fd', '#64748b', 1.5);
    $h->rect($X(14.8), $Y(2.8), $X(15.8) - $X(14.8), $Y(0.1) - $Y(2.8), '#bae6fd', '#64748b', 1.5);
    $h->rect($X(22), $Y(6.0), $X(24) - $X(22), $Y(3.0) - $Y(6.0), '#cbd5e1', '#475569', 1.5);
    for ($ly = 3.2; $ly < 6; $ly += 0.35) $h->line($X(22.2), $Y($ly), $X(23.8), $Y($ly), '#64748b', 1);

    // Pretil y cubierta
    $h->rect($X(0), $Y(9.6), $X(30) - $X(0), $Y(9.0) - $Y(9.6), '#cbd5e1', '#334155', 2);
    $h->text($X(15), $Y(9.35), 12, '#475569', 'PRETIL h = 0.60 m', true, 'center');

    // Letrero
    $h->text($X(7), $Y(7.9), 22, '#0f172a', 'NEXO DATA CAMPUS', true, 'center');
    $h->text($X(7), $Y(7.25), 12, '#0e7490', 'CENTRO DE DATOS TIER III', true, 'center');

    // Niveles
    foreach ([[0, '±0.00'], [3.0, '+3.00'], [6.0, '+6.00'], [9.0, '+9.00']] as [$n, $txt]) {
        $h->line($X(-1.2), $Y($n), $X(0), $Y($n), '#475569', 1);
        $h->text($X(-1.35), $Y($n) + 4, 12, '#475569', $txt, false, 'right');
    }

    // Cotas y callouts
    $h->dimH($X(0), $X(30), $Y(-0.8), '30.00', '#475569', 15);
    $h->dimV($Y(0), $Y(9.0), $X(-2.2), '9.00', '#475569', 15);
    $h->bubble($X(51), $Y(4.5), '1', '#0e7490', 14, 16);
    $h->line($X(51), $Y(4.5), $X(24), $Y(4.5), '#0e7490', 1.5);
    $h->bubble($X(11.5), $Y(4.0), '2', '#0e7490', 14, 16);
    $h->line($X(11.5), $Y(4.0), $X(13.2), $Y(2.6), '#0e7490', 1.5);

    $h->text($X(1), $Y(10.6), 13, '#0f172a', 'PIEL DE CRISTAL TEMPLADO 10 mm', true);
    $h->text($X(1), $Y(10.25), 12, '#475569', 'ALUMINIO ANODIZADO · SELLO EPDM · CONTROL SOLAR');
    $h->text($X(18), $Y(10.6), 13, '#0f172a', 'ACCESO PRINCIPAL', true);
    $h->text($X(18), $Y(10.25), 12, '#475569', 'PUERTAS DE CRISTAL TEMPLADO Y HERRAJES INOX');

    $ruta = sys_get_temp_dir() . '/demo-plano-alzado-' . strtolower($rev) . '.png';
    return $h->guardar($ruta) ? $ruta : null;
}

/** Patio de equipos: chillers, torres, bombas y generadores */
function demo_plano_patio(string $rev, string $fecha): ?string
{
    $h = new DemoPlanoHoja(1800, 1500, '#ffffff');
    $h->hoja('PLANTA DE EQUIPOS · PATIO DE ENFRIAMIENTO', 'Chillers, torres, bombas, generadores y acometidas · Escala 1:100', 'M-201', $rev, $fecha, '1:100');
    $x0 = 200.0;
    $y0 = 150.0;
    $e = 36.0;
    $X = fn(float $m) => $x0 + $m * $e;
    $Y = fn(float $m) => $y0 + $m * $e;

    // Terreno y vialidad
    $h->rect($X(0), $Y(0), 40 * $e, 30 * $e, '#f8fafc', '#334155', 2);
    $h->rect($X(0), $Y(0), 40 * $e, 3 * $e, '#e2e8f0', '#94a3b8', 1);
    $h->text($X(20), $Y(1.9), 14, '#64748b', 'ACCESO DE CAMIONES · 6.00 m', true, 'center');
    $h->line($X(0), $Y(3), $X(40), $Y(3), '#94a3b8', 1, [10, 6]);

    // Chillers
    for ($i = 0; $i < 3; $i++) {
        $cx = 4 + $i * 4.5;
        $h->rect($X($cx), $Y(5), 3.6 * $e, 2.4 * $e, '#e0f2fe', '#0e7490', 2);
        $h->ellipse($X($cx + 0.8), $Y(6.2), 16, 16, '#bae6fd', '#0e7490', 1.5);
        $h->ellipse($X($cx + 2.8), $Y(6.2), 16, 16, '#bae6fd', '#0e7490', 1.5);
        $h->text($X($cx + 1.8), $Y(6.2), 12, '#0e7490', 'CH-0' . ($i + 1), true, 'center');
        $h->text($X($cx + 1.8), $Y(7.0), 9, '#0e7490', '250 TR', false, 'center');
        $h->rect($X($cx - 0.7), $Y(4.5), 5 * $e, 3.4 * $e, null, '#94a3b8', 1, );
        $h->text($X($cx - 0.5), $Y(4.3), 8, '#94a3b8', '1.5 m LIBRE');
    }

    // Torres de enfriamiento
    for ($i = 0; $i < 2; $i++) {
        $cx = 19 + $i * 4.5;
        $h->rect($X($cx), $Y(5), 3.6 * $e, 3.2 * $e, '#f0fdfa', '#0f766e', 2);
        $h->ellipse($X($cx + 1.8), $Y(6.6), 24, 24, '#ccfbf1', '#0f766e', 1.5);
        $h->text($X($cx + 1.8), $Y(6.6), 11, '#0f766e', 'CT-0' . ($i + 1), true, 'center');
    }

    // Bombas
    for ($i = 0; $i < 4; $i++) {
        $px = 4 + $i * 2.2;
        $h->rect($X($px), $Y(12), 1.6 * $e, 1.4 * $e, '#ecfdf5', '#047857', 1.5);
        $h->ellipse($X($px + 0.8), $Y(12.7), 12, 12, '#a7f3d0', '#047857', 1);
        $h->text($X($px + 0.8), $Y(13.7), 9, '#047857', 'BC-0' . ($i + 1), true, 'center');
    }
    // Generadores
    for ($i = 0; $i < 2; $i++) {
        $gx = 13 + $i * 6;
        $h->rect($X($gx), $Y(12), 5 * $e, 2.2 * $e, '#fef3c7', '#92400e', 2);
        $h->rect($X($gx + 0.4), $Y(12.4), 1.2 * $e, 1.2 * $e, '#fde68a', '#92400e', 1);
        $h->text($X($gx + 2.5), $Y(13.3), 12, '#92400e', 'GEN-0' . ($i + 1), true, 'center');
        $h->text($X($gx + 2.5), $Y(14.0), 9, '#92400e', '2.5 MW', false, 'center');
    }
    // Tanque de combustible
    $h->ellipse($X(26), $Y(14), 2.0 * $e, 1.5 * $e, '#fef9c3', '#a16207', 2);
    $h->text($X(26), $Y(14), 10, '#a16207', 'TQ-01', true, 'center');
    // Transformador y tableros
    $h->rect($X(31), $Y(5), 3 * $e, 2.6 * $e, '#fde68a', '#92400e', 2);
    $h->text($X(32.5), $Y(6.3), 10, '#92400e', 'TR-01', true, 'center');
    $h->rect($X(28), $Y(12), 7 * $e, 1.8 * $e, '#fef3c7', '#92400e', 2);
    $h->text($X(31.5), $Y(13.0), 10, '#92400e', 'TABLEROS TG', true, 'center');

    // Tuberías
    $h->line($X(4), $Y(8.2), $X(31), $Y(8.2), '#0ea5e9', 6);
    $h->line($X(4), $Y(9.0), $X(31), $Y(9.0), '#0f766e', 6);
    $h->arrow($X(8), $Y(8.2), $X(12), $Y(8.2), '#0ea5e9', 2, 8);
    $h->arrow($X(28), $Y(9.0), $X(24), $Y(9.0), '#0f766e', 2, 8);
    $h->text($X(15), $Y(8.0), 9, '#0369a1', 'AGUA HELADA · SUMINISTRO', false, 'center');
    $h->text($X(15), $Y(9.6), 9, '#0f766e', 'AGUA HELADA · RETORNO', false, 'center');
    $h->line($X(6), $Y(14.8), $X(13), $Y(14.8), '#0ea5e9', 4);
    $h->text($X(9.5), $Y(15.4), 8, '#0369a1', 'CABEZAL DE BOMBAS', false, 'center');

    // Cotas y norte
    $h->dimH($X(0), $X(40), $Y(31.8), '40.00', '#475569', 15);
    $h->dimV($Y(0), $Y(30), $X(-2.0), '30.00', '#475569', 15);
    $h->norte($X(37.5), $Y(27), 26);

    // Notas al pie
    $h->text($X(1), $Y(30.6), 13, '#0f172a', 'NOTAS:', true);
    $h->text($X(1), $Y(31.3), 11, '#475569', '• Área libre de 1.5 m para mantenimiento de chillers y torres.');
    $h->text($X(1), $Y(32.0), 11, '#475569', '• Plataformas de concreto con anclaje sísmico y aislador de vibración.');
    $h->text($X(1), $Y(32.7), 11, '#475569', '• Tuberías de agua helada con soportería, aislamiento y válvulas de corte.');

    $ruta = sys_get_temp_dir() . '/demo-plano-patio-' . strtolower($rev) . '.png';
    return $h->guardar($ruta) ? $ruta : null;
}

/** Planta de instalaciones: HVAC, eléctrica, datos e incendio */
function demo_plano_instalaciones(string $rev, string $fecha): ?string
{
    $h = new DemoPlanoHoja(2600, 1700, '#ffffff');
    $h->hoja('PLANTA DE INSTALACIONES · HVAC, ELÉCTRICA Y DATOS', 'Superposición de sistemas · Nivel +4.20 · Escala 1:100', 'M-301', $rev, $fecha, '1:100');
    $x0 = 150.0;
    $y0 = 110.0;
    $e = 36.0;
    $X = fn(float $m) => $x0 + $m * $e;
    $Y = fn(float $m) => $y0 + $m * $e;

    // Envolvente y zonas
    $h->rect($X(0), $Y(0), 60 * $e, 36 * $e, '#cbd5e1', '#0f172a', 4);
    $h->rect($X(0) + 9, $Y(0) + 9, 60 * $e - 18, 36 * $e - 18, '#ffffff', null);
    $h->rect($X(1), $Y(1), 43 * $e, 34 * $e, '#f8fafc', '#334155', 2);
    foreach ([[46, 1, 13, 11, '#fffbeb'], [46, 12, 13, 8, '#f5f3ff'], [46, 20, 13, 7, '#ecfdf5'], [46, 27, 13, 8, '#f8fafc']] as [$zx, $zy, $zw, $zh, $c]) {
        $h->rect($X($zx), $Y($zy), $zw * $e, $zh * $e, $c, '#334155', 2);
    }
    $h->rect($X(44), $Y(1), 1.6 * $e, 34 * $e, '#f1f5f9', '#334155', 2);
    $h->line($X(44), $Y(1), $X(44), $Y(35), '#334155', 6);
    $h->line($X(45.6), $Y(1), $X(45.6), $Y(35), '#334155', 6);
    foreach ([12, 20, 27] as $yy) $h->line($X(45.6), $Y($yy), $X(59), $Y($yy), '#334155', 6);

    // Racks de referencia
    foreach ([[7, 7, 'A'], [7, 18, 'B'], [22, 7, 'C'], [22, 18, 'D']] as [$px, $py, $l]) {
        $h->rect($X($px), $Y($py), 3.6 * $e, 3.6 * $e, '#e2e8f0', '#94a3b8', 1.2);
        $h->text($X($px + 1.8), $Y($py + 1.8), 11, '#64748b', 'POD ' . $l, true, 'center');
    }

    // HVAC: CRAC y ductos
    for ($i = 0; $i < 8; $i++) {
        $yy = 4 + $i * 3.7;
        $h->rect($X(37), $Y($yy), 2.2 * $e, 2.6 * $e, '#e0f2fe', '#0e7490', 1.8);
        $h->text($X(38.1), $Y($yy + 1.6), 9, '#0e7490', 'CRAC-' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT), true, 'center');
    }
    $h->line($X(2), $Y(4.2), $X(36), $Y(4.2), '#0e7490', 9);
    $h->line($X(2), $Y(4.8), $X(36), $Y(4.8), '#0e7490', 9);
    $h->line($X(2), $Y(31.2), $X(36), $Y(31.2), '#0e7490', 9);
    $h->line($X(2), $Y(31.8), $X(36), $Y(31.8), '#0e7490', 9);
    for ($i = 0; $i < 8; $i++) {
        $yy = 4 + $i * 3.7;
        $h->line($X(36), $Y($yy + 1.3), $X(37), $Y($yy + 1.3), '#0e7490', 7);
        $h->line($X(37), $Y($yy + 1.3), $X(38.1), $Y($yy + 1.3), '#0e7490', 7);
    }
    for ($i = 0; $i < 12; $i++) {
        $dx = 5 + $i * 3;
        $h->rect($X($dx), $Y(5.2), 16, 16, '#bae6fd', '#0e7490', 1);
        $h->rect($X($dx), $Y(30.0), 16, 16, '#bae6fd', '#0e7490', 1);
    }
    $h->text($X(15), $Y(3.6), 12, '#0e7490', 'DUCTO PRINCIPAL DE SUMINISTRO', true, 'center');
    $h->text($X(15), $Y(32.8), 12, '#0e7490', 'DUCTO PRINCIPAL DE RETORNO', true, 'center');

    // Eléctrica: charolas y tableros
    $h->line($X(3), $Y(34.2), $X(43), $Y(34.2), '#f59e0b', 6, [12, 6]);
    $h->line($X(44.5), $Y(2), $X(44.5), $Y(34), '#f59e0b', 6, [12, 6]);
    foreach ([[7, 7], [7, 18], [22, 7], [22, 18]] as [$px, $py]) {
        $h->line($X($px + 1.8), $Y($py + 1.8), $X(44.5), $Y($py + 1.8), '#f59e0b', 4, [8, 5]);
    }
    $h->text($X(24), $Y(34.9), 11, '#b45309', 'CHAROLA ELÉCTRICA 300 mm', true, 'center');
    foreach ([2.0, 5.6, 9.2, 12.8] as $i => $yy) {
        $h->rect($X(47.2), $Y($yy), 2.4 * $e, 1.4 * $e, '#fde68a', '#92400e', 1.5);
        $h->text($X(48.4), $Y($yy + 0.9), 9, '#92400e', 'TG-' . ($i + 1), true, 'center');
    }

    // Datos: charolas y MDF
    $h->line($X(46.5), $Y(23.5), $X(43), $Y(23.5), '#7c3aed', 6, [10, 5]);
    foreach ([[7, 7], [7, 18], [22, 7], [22, 18]] as [$px, $py]) {
        $h->line($X(43), $Y(23.5), $X($px + 1.8), $Y($py + 1.8), '#7c3aed', 3, [7, 5]);
    }
    $h->text($X(40.0), $Y(21.2), 9, '#6d28d9', 'CHAROLA DE DATOS', true, 'center');
    $h->rect($X(47.2), $Y(21), 4 * $e, 4.5 * $e, '#ddd6fe', '#6d28d9', 1.5);
    $h->text($X(49.2), $Y(23.3), 10, '#6d28d9', 'MDF / IDF', true, 'center');

    // Incendio: red y rociadores
    $h->line($X(2), $Y(2.4), $X(43), $Y(2.4), '#dc2626', 5);
    for ($i = 0; $i < 10; $i++) {
        $sx = 5 + $i * 4;
        $h->line($X($sx), $Y(2.4), $X($sx), $Y(3.2), '#dc2626', 3);
        $h->ellipse($X($sx), $Y(3.2), 6, 6, '#fca5a5', '#dc2626', 1.5);
    }
    $h->text($X(22), $Y(1.8), 10, '#b91c1c', 'RED DE ROCIADORES · RAMAL PRINCIPAL', true, 'center');

    // Leyenda
    $lx = $X(26.0);
    $ly = $Y(23.0);
    $h->rect($lx - 8, $ly - 22, 470, 240, '#ffffff', '#cbd5e1', 1.5);
    $h->text($lx, $ly, 13, '#0f172a', 'SISTEMAS', true);
    foreach ([['#0e7490', 'HVAC · DUCTOS Y CRAC'], ['#f59e0b', 'ELÉCTRICA · CHAROLAS'], ['#7c3aed', 'DATOS · CHAROLAS'], ['#dc2626', 'INCENDIO · ROCIADORES']] as $i => [$c, $txt]) {
        $yy = $ly + 28 + $i * 30;
        $h->line($lx, $yy, $lx + 40, $yy, $c, 5);
        $h->text($lx + 52, $yy + 4, 11, '#334155', $txt);
    }

    // Cotas, norte y notas
    $h->dimH($X(0), $X(60), $Y(37.9), '60.00', '#475569', 15);
    $h->dimV($Y(0), $Y(36), $X(-2.2), '36.00', '#475569', 15);
    $h->norte($X(57.2), $Y(33), 24);
    $h->text($X(1), $Y(36.7), 10, '#64748b', 'PLANTA DE INSTALACIONES · SUPERPOSICIÓN DE SISTEMAS · VER LEYENDA');

    $ruta = sys_get_temp_dir() . '/demo-plano-instalaciones-' . strtolower($rev) . '.png';
    return $h->guardar($ruta) ? $ruta : null;
}

/** Detalles constructivos: piso técnico, paso de charolas, muro-losa y base de chiller */
function demo_plano_detalles(string $rev, string $fecha): ?string
{
    $h = new DemoPlanoHoja(2600, 1600, '#ffffff');
    $h->hoja('DETALLES CONSTRUCTIVOS', 'Piso técnico, sellos cortafuego, unión muro-losa y anclaje de equipos', 'D-401', $rev, $fecha, '1:10 / 1:20');
    $paneles = [[60, 90, 1190, 560, '1', 'PEDESTAL DE PISO TÉCNICO Y ANCLAJE DE RACK', '1:10'],
        [1350, 90, 1190, 560, '2', 'PASO DE CHAROLA CON SELLO CORTAFUEGO', '1:10'],
        [60, 700, 1190, 680, '3', 'UNIÓN MURO CORTAFUEGO · LOSA', '1:20'],
        [1350, 700, 1190, 680, '4', 'BASE DE CHILLER Y ANCLAJE SÍSMICO', '1:20']];
    foreach ($paneles as [$px, $py, $pw, $ph, $num, $tit, $esc]) {
        $h->rect($px, $py, $pw, $ph, '#ffffff', '#334155', 2);
        $h->rect($px, $py, $pw, 40, '#0f172a', null);
        $h->text($px + 14, $py + 27, 16, '#ffffff', $num . ' · ' . $tit, true);
        $h->text($px + $pw - 90, $py + 27, 14, '#bae6fd', 'ESC. ' . $esc, true);
    }

    // Detalle 1
    $h->rect(120, 470, 1050, 80, '#cbd5e1', '#334155', 2);
    $h->hatch(120, 470, 1050, 80, '#94a3b8', 14, 45, 1);
    $h->line(120, 470, 1170, 470, '#0f172a', 3);
    foreach ([300, 600, 900] as $x) {
        $h->rect($x - 14, 400, 28, 70, '#94a3b8', '#334155', 2);
        $h->rect($x - 70, 386, 140, 14, '#cbd5e1', '#334155', 1.5);
    }
    $h->rect(260, 330, 680, 56, '#1e293b', '#0f172a', 2);
    $h->rect(300, 300, 600, 30, '#e2e8f0', '#334155', 2);
    $h->line(390, 386, 390, 330, '#e11d48', 5);
    $h->ellipse(390, 358, 14, 14, '#fecdd3', '#e11d48', 2);
    $h->text(700, 600, 13, '#475569', 'LOSA DE CONCRETO f\'c 350 kg/cm² · IMPERMEABILIZACIÓN', true, 'center');
    $h->text(700, 630, 12, '#64748b', 'PEDESTAL DE ACERO GALVANIZADO CON NIVELACIÓN ± 25 mm', false, 'center');
    $h->text(930, 330, 12, '#e11d48', 'ANCLAJE QUÍMICO M12', true);
    $h->line(920, 340, 840, 360, '#e11d48', 1.5);
    $h->dimH(300, 600, 650, '600', '#475569', 15);
    $h->dimH(600, 900, 680, '600', '#475569', 15);

    // Detalle 2
    $h->rect(1600, 170, 150, 420, '#cbd5e1', '#334155', 2);
    $h->hatch(1600, 170, 150, 420, '#94a3b8', 12, 45, 1);
    $h->rect(1630, 330, 90, 100, '#ffffff', '#334155', 2);
    $h->rect(1630, 330, 90, 100, '#fde68a', '#92400e', 2);
    $h->line(1380, 380, 1600, 380, '#94a3b8', 12);
    $h->line(1750, 380, 1960, 380, '#94a3b8', 12);
    foreach ([[1390, '#38bdf8'], [1420, '#facc15'], [1450, '#f97316']] as [$x, $c]) {
        $h->line($x, 368, 1960, 368, $c, 3);
    }
    $h->text(1725, 470, 13, '#92400e', 'SELLO CORTAFUEGO 2 h', true, 'center');
    $h->text(1725, 500, 11, '#92400e', 'MORTERO INTUMESCENTE + FIBRA', false, 'center');
    $h->text(1450, 340, 11, '#475569', 'CHAROLA PORTACABLE', false, 'center');
    $h->dimH(1600, 1750, 620, '300', '#475569', 15);
    $h->dimH(1630, 1720, 560, '150', '#475569', 13);

    // Detalle 3
    $h->rect(130, 1030, 1030, 80, '#cbd5e1', '#334155', 2);
    $h->hatch(130, 1030, 1030, 80, '#94a3b8', 14, 45, 1);
    $h->rect(610, 780, 120, 250, '#cbd5e1', '#334155', 2);
    $h->hatch(610, 780, 120, 250, '#94a3b8', 12, 45, 1);
    $h->rect(590, 1020, 160, 30, '#fde68a', '#92400e', 2);
    $h->text(670, 1090, 13, '#92400e', 'SELLO INTUMESCENTE EN LA UNIÓN', true, 'center');
    $h->text(670, 1120, 11, '#92400e', 'MURO CORTAFUEGO 2 h · LOSA e = 0.20 m', false, 'center');
    $h->dimV(780, 1030, 480, '2.50', '#475569', 13);
    $h->dimH(610, 730, 1160, '0.15', '#475569', 13);

    // Detalle 4
    $h->rect(1480, 1000, 880, 90, '#cbd5e1', '#334155', 2);
    $h->hatch(1480, 1000, 880, 90, '#94a3b8', 14, 45, 1);
    $h->rect(1540, 960, 760, 40, '#334155', '#0f172a', 2);
    $h->rect(1560, 840, 720, 120, '#e2e8f0', '#334155', 2);
    $h->rect(1600, 800, 640, 40, '#f1f5f9', '#334155', 2);
    foreach ([1580, 2300] as $x) {
        $h->line($x, 840, $x, 960, '#94a3b8', 8);
        $h->ellipse($x, 900, 14, 14, '#fecdd3', '#e11d48', 2);
    }
    $h->line(1920, 870, 1920, 930, '#e11d48', 5);
    $h->ellipse(1920, 900, 20, 20, '#fee2e2', '#e11d48', 2);
    $h->line(2280, 890, 2440, 890, '#b45309', 10);
    $h->text(2400, 870, 11, '#b45309', 'AGUA HELADA', true);
    $h->text(1920, 1130, 13, '#475569', 'PLINTO DE CONCRETO + AISLADOR DE VIBRACIÓN', true, 'center');
    $h->text(1920, 1160, 11, '#64748b', 'ANCLAJE SÍSMICO CON PERNO M16 Y PLACA DE ACERO', false, 'center');
    $h->dimH(1480, 2360, 1080, '2.40', '#475569', 13);

    $ruta = sys_get_temp_dir() . '/demo-plano-detalles-' . strtolower($rev) . '.png';
    return $h->guardar($ruta) ? $ruta : null;
}

/** Localización y logística de obra */
function demo_plano_localizacion(string $rev, string $fecha): ?string
{
    $h = new DemoPlanoHoja(2600, 1700, '#ffffff');
    $h->hoja('PLANO DE LOCALIZACIÓN Y LOGÍSTICA', 'Campus, accesos, acopios y ruta de evacuación · Escala 1:500', 'C-601', $rev, $fecha, '1:500');
    $x0 = 250.0;
    $y0 = 100.0;
    $e = 16.0;
    $X = fn(float $m) => $x0 + $m * $e;
    $Y = fn(float $m) => $y0 + $m * $e;

    // Predio
    $h->rect($X(0), $Y(0), 120 * $e, 80 * $e, '#f8fafc', '#334155', 2);
    $h->hatch($X(0), $Y(0), 120 * $e, 80 * $e, '#eef2f7', 26, 45, 1);

    // Vialidades
    $h->rect($X(0), $Y(0), 120 * $e, 6 * $e, '#e2e8f0', '#94a3b8', 1);
    $h->rect($X(52), $Y(0), 8 * $e, 80 * $e, '#e2e8f0', '#94a3b8', 1);
    $h->text($X(60), $Y(3.6), 12, '#64748b', 'AV. DEL PARQUE TECNOLÓGICO', true, 'center');
    // Accesos
    foreach ([20, 84] as $gx) {
        $h->rect($X($gx), $Y(0), 10 * $e, 1.2 * $e, '#ffffff', '#0f172a', 2);
        $h->text($X($gx + 5), $Y(-0.5), 10, '#0f172a', 'ACCESO', true, 'center');
    }

    // Naves A y B
    $h->rect($X(10), $Y(16), 38 * $e, 28 * $e, '#e0f2fe', '#0f172a', 2);
    $h->text($X(29), $Y(31), 17, '#0e7490', 'NAVE A · SALA BLANCA', true, 'center');
    $h->text($X(29), $Y(35), 11, '#0e7490', '48 RACKS · 2,400 m²', false, 'center');
    $h->rect($X(64), $Y(16), 38 * $e, 28 * $e, '#f0fdfa', '#0f172a', 2);
    $h->text($X(83), $Y(31), 17, '#0f766e', 'NAVE B · RESPALDO', true, 'center');
    $h->text($X(83), $Y(35), 11, '#0f766e', 'GENERADORES · TALLER', false, 'center');

    // Oficinas, estacionamiento y caseta
    $h->rect($X(10), $Y(52), 26 * $e, 14 * $e, '#fffbeb', '#92400e', 1.5);
    $h->text($X(23), $Y(59), 13, '#92400e', 'OFICINAS DE OBRA', true, 'center');
    $h->rect($X(66), $Y(52), 34 * $e, 16 * $e, '#f1f5f9', '#64748b', 1.5);
    $h->text($X(83), $Y(57), 12, '#64748b', 'ESTACIONAMIENTO', true, 'center');
    for ($i = 0; $i < 10; $i++) {
        $h->line($X(68 + $i * 3.2), $Y(60), $X(68 + $i * 3.2), $Y(66), '#94a3b8', 1);
    }

    // Subestación y contra incendio
    $h->rect($X(100), $Y(10), 14 * $e, 12 * $e, '#fde68a', '#92400e', 1.5);
    $h->text($X(107), $Y(16), 11, '#92400e', 'SUBESTACIÓN', true, 'center');
    $h->ellipse($X(8), $Y(56), 3.2 * $e, 2.6 * $e, '#bae6fd', '#0369a1', 2);
    $h->text($X(8), $Y(56), 10, '#0369a1', 'CISTERNA', true, 'center');

    // Grúa, acopio y oficinas
    $h->ellipse($X(40), $Y(50), 2.4 * $e, 2.4 * $e, null, '#f59e0b', 3);
    $h->line($X(40), $Y(50), $X(40), $Y(42), '#f59e0b', 4);
    $h->line($X(34), $Y(42), $X(46), $Y(42), '#f59e0b', 4);
    $h->text($X(40), $Y(54), 10, '#b45309', 'GRÚA TORRE', true, 'center');
    $h->rect($X(10), $Y(70), 20 * $e, 8 * $e, '#fef3c7', '#a16207', 1.5);
    $h->text($X(20), $Y(74.5), 11, '#a16207', 'ACOPIO DE ACERO', true, 'center');
    $h->rect($X(32), $Y(70), 16 * $e, 8 * $e, '#ecfdf5', '#047857', 1.5);
    $h->text($X(40), $Y(74.5), 11, '#047857', 'OFICINAS Y VESTIDORES', true, 'center');
    $h->rect($X(80), $Y(72), 24 * $e, 6 * $e, '#fef2f2', '#b91c1c', 1.5);
    $h->text($X(92), $Y(75.5), 11, '#b91c1c', 'RESIDUOS Y ESCOMBRO', true, 'center');

    // Ruta de evacuación
    $ruta = [[$X(30), $Y(46)], [$X(30), $Y(52)], [$X(56), $Y(52)], [$X(56), $Y(6)], [$X(20), $Y(6)]];
    for ($i = 0; $i < count($ruta) - 1; $i++) {
        $h->arrow($ruta[$i][0], $ruta[$i][1], $ruta[$i + 1][0], $ruta[$i + 1][1], '#16a34a', 4, 12);
    }
    $h->text($X(48), $Y(49), 11, '#16a34a', 'RUTA DE EVACUACIÓN', true, 'center');

    // Norte, cotas y leyenda
    $h->norte($X(112), $Y(72), 26);
    $h->dimH($X(0), $X(120), $Y(82), '120.00', '#475569', 15);
    $h->dimV($Y(0), $Y(80), $X(-2.5), '80.00', '#475569', 15);
    $lx = $X(62);
    $ly = $Y(70);
    $h->rect($lx - 20, $ly - 20, 480, 230, '#ffffff', '#cbd5e1', 1.5);
    $h->text($lx, $ly, 13, '#0f172a', 'LEYENDA', true);
    foreach ([['#e0f2fe', 'Nave A · sala blanca'], ['#f0fdfa', 'Nave B · respaldo'], ['#fffbeb', 'Oficinas de obra'], ['#fde68a', 'Subestación'], ['#16a34a', 'Ruta de evacuación']] as $i => [$c, $txt]) {
        $yy = $ly + 28 + $i * 30;
        $h->rect($lx, $yy - 12, 18, 18, $c, '#64748b', 1);
        $h->text($lx + 28, $yy + 2, 11, '#475569', $txt);
    }

    $ruta = sys_get_temp_dir() . '/demo-plano-localizacion-' . strtolower($rev) . '.png';
    return $h->guardar($ruta) ? $ruta : null;
}
