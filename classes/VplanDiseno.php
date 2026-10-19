<?php

namespace Classes;

use Model\VplanBloque;
use Model\VplanLamina;
use Model\VplanTabla;
use Classes\Exportadores\VplanPdfExporter;

/**
 * Diseño de las láminas de vPlan: tamaños de lienzo, escala de las tablas según el espacio de su bloque y el
 * diseño por defecto (el mismo que tenían las láminas antes de ser libres).
 *
 * Todo se calcula en "espacio de referencia" (tabloide horizontal, 432 mm de ancho): la letra real de una hoja
 * más chica o más grande es la de referencia × (ancho de la hoja / 432).
 */
class VplanDiseno
{
    public const REF_ANCHO_MM = 432.0;
    private const REF_ANCHO_PT = 1224.57;   // 432 mm en puntos

    /** clave => [nombre, ancho mm, alto mm] (horizontal; "vertical" intercambia) */
    public const PRESETS = [
        'tabloide' => ['Tabloide 11×17"', 432, 279],
        'carta' => ['Carta 8.5×11"', 279, 216],
        'oficio' => ['Oficio 8.5×14"', 356, 216],
        'a4' => ['A4', 297, 210],
        'a3' => ['A3', 420, 297],
        'a2' => ['A2', 594, 420],
        'a1' => ['A1', 841, 594],
        'archd' => ['Arch D 24×36"', 914, 610],
    ];
    public const MIN_MM = 100;
    public const MAX_MM = 1500;

    /** [clase, letra pt, relleno vertical pt] */
    public const ESCALAS = [['e0', 9.0, 3.0], ['e1', 7.5, 2.0], ['e2', 6.2, 1.5], ['e3', 5.2, 0.9], ['e4', 4.4, 0.5], ['e5', 3.6, 0.3]];

    /** Factor de la hoja respecto del tabloide de referencia */
    public static function factor(VplanLamina $l): float
    {
        return max(0.1, (float)$l->ancho_mm / self::REF_ANCHO_MM);
    }

    /** Normaliza lo que llega del formulario: preset (o personalizado en mm/cm/in) y orientación. [ancho, alto, preset] */
    public static function tamanoDe(array $in): array
    {
        $preset = (string)($in['preset'] ?? 'tabloide');
        $vertical = ($in['orientacion'] ?? 'horizontal') === 'vertical';
        if (isset(self::PRESETS[$preset])) {
            [, $w, $h] = self::PRESETS[$preset];
            if ($vertical) [$w, $h] = [$h, $w];
        } else {
            $preset = 'personalizado';
            $u = ['mm' => 1.0, 'cm' => 10.0, 'in' => 25.4][$in['unidad'] ?? 'mm'] ?? 1.0;
            $w = (float)str_replace(',', '.', (string)($in['ancho'] ?? 0)) * $u;
            $h = (float)str_replace(',', '.', (string)($in['alto'] ?? 0)) * $u;
        }
        $w = (int)round(max(self::MIN_MM, min(self::MAX_MM, $w)));
        $h = (int)round(max(self::MIN_MM, min(self::MAX_MM, $h)));
        return [$w, $h, $preset];
    }

    // ---------- estimación de altura (pt de referencia) ----------

    private const PT_CM = 28.35;

    /** Líneas que ocupa un texto en una columna de $cm (cm de referencia), con letra $f y relleno $p */
    private static function lineas(string $t, float $cm, float $f, float $p): int
    {
        $cpl = max(4, (int)floor(($cm * self::PT_CM - 3.6 * $p - 2) / (0.6 * $f) * 0.92));
        return max(1, (int)ceil(mb_strlen($t) / $cpl));
    }

    private static function fila(int $lineas, float $f, float $p): float
    {
        return $lineas * 1.25 * $f + 2 * $p + 1.5;   // línea + relleno + bordes
    }

    /** Ancho (cm) que necesita una columna con $caracteres de texto */
    private static function colCm(int $caracteres, float $f, float $p): float
    {
        return ($caracteres * 0.62 * $f + 3.6 * $p + 3) / self::PT_CM;
    }

    /**
     * Anchos (cm) de las columnas de la tabla de tareas, que el estimador, el PDF y la vista usan por igual:
     * [seq, tarea, inicio, fin, (recovery inicio, fin)…, %, restricciones]. La tarea toma el espacio que sobra.
     */
    public static function columnasTareas(float $anchoCm, int $nRec, float $f, float $p): array
    {
        $seq = self::colCm(4, $f, $p);
        $fecha = self::colCm(10, $f, $p);
        $pct = self::colCm(4, $f, $p);
        $restr = self::colCm(10, $f, $p) * 1.05;
        $fijo = $seq + $fecha * (2 + 2 * $nRec) + $pct + $restr;
        $tarea = max(2.0, $anchoCm - $fijo);
        $cols = array_merge([$seq, $tarea], array_fill(0, 2 + 2 * $nRec, $fecha), [$pct, $restr]);
        $suma = array_sum($cols);
        return array_map(fn($c) => round($c / $suma * 100, 3), $cols);   // en % del ancho de la tabla
    }

    /** Alto de la tabla de tareas. $nombres = textos de cada renglón; $subtitulos = renglones de partida */
    public static function estimarTareas(array $nombres, int $nRec, int $subtitulos, float $anchoCm, float $f, float $p): float
    {
        $anchoTarea = self::columnasTareas($anchoCm, $nRec, $f, $p)[1] / 100 * $anchoCm;
        $h = self::fila(1, $f, $p) * (3 + $subtitulos) + 0.25 * $f * 1.25;   // el título es 25 % más grande
        foreach ($nombres as $n) $h += self::fila(self::lineas((string)$n, $anchoTarea, $f, $p), $f, $p);
        return $h;
    }

    /** Anchos (%) de las columnas de una tabla adicional según su tipo; la primera de texto es la que "crece" */
    public static function columnasTabla(string $tipo, int $nCampos = 2): array
    {
        return match ($tipo) {
            'punch' => [6, 44, 20, 20, 10],
            'restricciones' => [9, 91],
            'secundarias' => [50, 25, 25],
            default => array_merge([8], array_fill(0, max(1, $nCampos), round(92 / max(1, $nCampos), 2))),
        };
    }

    /** Alto de una tabla adicional. $textos = texto largo de cada renglón */
    public static function estimarTabla(string $tipo, array $textos, float $anchoCm, float $f, float $p, int $nCampos = 2): float
    {
        $h = self::fila(1, $f, $p) * ($tipo === 'restricciones' ? 1 : ($tipo === 'punch' ? 3 : 2)) + 0.25 * $f * 1.25;
        $col = self::columnasTabla($tipo, $nCampos);
        $texto = $tipo === 'punch' || $tipo === 'restricciones' ? $col[1] : ($tipo === 'secundarias' ? $col[0] : $col[1]);
        $cm = $anchoCm * $texto / 100;
        foreach ($textos as $t) $h += self::fila(self::lineas((string)$t, $cm, $f, $p), $f, $p);
        return $h;
    }

    /** Capacidad (pt de referencia) y ancho (cm de referencia) del bloque */
    public static function capacidadPt(VplanLamina $l, VplanBloque $b): float
    {
        return (float)$b->h / 100 * self::altoRefPt($l);
    }

    private static function altoRefPt(VplanLamina $l): float
    {
        return ((float)$l->alto_mm / max(1, (float)$l->ancho_mm)) * self::REF_ANCHO_PT;
    }

    public static function anchoRefCm(VplanBloque $b): float
    {
        return (float)$b->w / 100 * (self::REF_ANCHO_MM / 10);
    }

    /** Índice de ESCALAS: la letra más grande con la que la estimación cabe en la capacidad (holgura 6 %) */
    public static function elegirEscala(callable $estimar, float $capacidadPt): int
    {
        foreach (self::ESCALAS as $i => [, $f, $p]) {
            if ($estimar($f, $p) * 1.06 <= $capacidadPt) return $i;
        }
        return count(self::ESCALAS) - 1;
    }

    public static function textosDeTareas(array $td): array
    {
        $out = [];
        foreach ($td['grupos'] as $g) foreach ($g['tareas'] as $t) $out[] = $t['actividad']->nombre;
        return $out;
    }

    private static function textosDeFilas(array $filas): array
    {
        return array_map(fn($f) => (string)($f['descripcion'] ?? $f['elemento'] ?? ($f['c0'] ?? '')), $filas);
    }

    public static function escalaTareas(VplanLamina $l, VplanBloque $b, array $td, int $nRec): int
    {
        $nombres = self::textosDeTareas($td);
        $sub = $td['subtitulos'] ? count($td['grupos']) : 0;
        $ancho = self::anchoRefCm($b);
        return self::elegirEscala(fn($f, $p) => self::estimarTareas($nombres, $nRec, $sub, $ancho, $f, $p), self::capacidadPt($l, $b));
    }

    public static function escalaTabla(VplanLamina $l, VplanBloque $b, array $tb): int
    {
        $textos = self::textosDeFilas($tb['filas']);
        $ancho = self::anchoRefCm($b);
        $n = count($tb['campos']);
        return self::elegirEscala(fn($f, $p) => self::estimarTabla($tb['tabla']->tipo, $textos, $ancho, $f, $p, $n), self::capacidadPt($l, $b));
    }

    // ---------- diseño por defecto ----------

    private const HOJA_W_CM = 43.18, HOJA_H_CM = 27.94;

    private static function px(float $cm): float { return round($cm / self::HOJA_W_CM * 100, 3); }
    private static function py(float $cm): float { return round($cm / self::HOJA_H_CM * 100, 3); }

    /** Bloque en cm sobre el tabloide de referencia (origen en la esquina de la hoja) */
    private static function bloque(string $tipo, float $x, float $y, float $w, float $h, int $z, array $cfg = []): VplanBloque
    {
        $b = new VplanBloque(['tipo' => $tipo, 'x' => self::px($x), 'y' => self::py($y), 'w' => self::px($w), 'h' => self::py($h), 'z' => $z]);
        $b->setConfig($cfg);
        return $b;
    }

    /** Bloque ya en % de la hoja */
    private static function bloquePct(string $tipo, float $x, float $y, float $w, float $h, int $z, array $cfg = []): VplanBloque
    {
        $b = new VplanBloque(['tipo' => $tipo, 'x' => round($x, 3), 'y' => round($y, 3), 'w' => round($w, 3), 'h' => round($h, 3), 'z' => $z]);
        $b->setConfig($cfg);
        return $b;
    }

    /**
     * Diseño con el que se crea (o se convierte) una lámina. Hoja horizontal: el de siempre — logos y título arriba,
     * plano a la izquierda, tareas y tablas apiladas a la derecha, simbologías abajo y supervisor/fecha al pie.
     * Hoja vertical: plano arriba a todo el ancho y tareas y tablas debajo.
     * $fuentes = [['partida' => id, 'actividades' => null|[ids]], …]; null = sin bloque de tareas.
     * @return VplanBloque[] sin guardar
     */
    public static function porDefecto(VplanLamina $l, ?array $fuentes, string $seq = 'continuo'): array
    {
        $proyecto = \Model\Proyecto::find((int)$l->proyecto_id);
        $vertical = (int)$l->alto_mm > (int)$l->ancho_mm;
        $dim = VplanPdfExporter::ajustarPlano($l->marcas_png ?: $l->plano, $l->simbologia, $l->imagenSimbologiaSchedule());
        $ratioPlano = $dim && $dim[1] > 0 ? $dim[0] / $dim[1] : 1.5;
        $z = 0;
        $out = [];
        if (!$vertical) {
            $out[] = self::bloque('logo', 1.0, 0.9, 8.24, 3.0, $z++, ['cual' => 'contratista', 'alinear' => 'izquierda']);
            $out[] = self::bloque('titulo', 9.24, 0.9, 24.7, 3.0, $z++);
            $out[] = self::bloque('logo', 33.94, 0.9, 8.24, 3.0, $z++, ['cual' => 'empresa', 'alinear' => 'derecha']);
            $out[] = self::bloque('plano', 1.0, 4.1, 20.1, $dim ? $dim[1] : 17.0, $z++);
            $out[] = self::bloque('simbologia', 1.0, 21.3, 12.0, 4.6, $z++);
            $out[] = self::bloque('schedule', 14.1, 21.3, 7.0, 4.6, $z++);
            $out[] = self::bloque('supervisor', 1.0, 26.35, 20.0, 0.7, $z++);
            $out[] = self::bloque('fecha', 21.2, 26.35, 20.98, 0.7, $z++);
            [$colX, $colY, $colW, $colH] = [self::px(21.5), self::py(4.1), self::px(20.5), self::py(21.6)];
        } else {
            $out[] = self::bloquePct('logo', 3, 2, 22, 7, $z++, ['cual' => 'contratista', 'alinear' => 'izquierda']);
            $out[] = self::bloquePct('titulo', 26, 2, 48, 7, $z++);
            $out[] = self::bloquePct('logo', 75, 2, 22, 7, $z++, ['cual' => 'empresa', 'alinear' => 'derecha']);
            $hPlano = min(40.0, 94 * (float)$l->ancho_mm / $ratioPlano / max(1, (float)$l->alto_mm));
            $out[] = self::bloquePct('plano', 3, 10, 94, $hPlano, $z++);
            $out[] = self::bloquePct('simbologia', 3, 85, 55, 10, $z++);
            $out[] = self::bloquePct('schedule', 60, 85, 37, 10, $z++);
            $out[] = self::bloquePct('supervisor', 3, 96.3, 47, 2.2, $z++);
            $out[] = self::bloquePct('fecha', 50, 96.3, 47, 2.2, $z++);
            [$colX, $colY, $colW, $colH] = [3.0, 10 + $hPlano + 1.5, 94.0, 84 - (10 + $hPlano + 1.5)];
        }

        // Tareas y tablas apiladas en su columna con la letra más grande con la que caben
        $apilar = [];   // [tipo, objeto, textos, subtítulos]
        $nRec = (int)$l->recovery_n;
        $nRestr = 0;
        if ($fuentes) {
            $tmp = new VplanBloque(['tipo' => 'tareas']);
            $tmp->setConfig(['fuentes' => $fuentes, 'seq' => $seq]);
            $td = Vplan::tareasDeBloque($tmp, $proyecto);
            $apilar[] = ['tareas', $tmp, self::textosDeTareas($td), $td['subtitulos'] ? count($td['grupos']) : 0];
            $ids = [];
            foreach ($td['grupos'] as $g) foreach ($g['tareas'] as $t) $ids[] = (int)$t['actividad']->id;
            $nRestr = count(Vplan::restriccionesDe($ids)['lista']);
        }
        foreach ($l->tablas() as $t) {
            $textos = self::textosDeFilas($t->filas());
            if ($t->tipo === 'restricciones') $textos = array_merge(array_fill(0, $nRestr, 'Restricción del programa con una descripción de largo promedio'), $textos);
            $apilar[] = ['tabla', $t, $textos, 0];
        }
        $altoRef = self::altoRefPt($l);
        $anchoCol = $colW / 100 * (self::REF_ANCHO_MM / 10);
        $alto = fn($a, $f, $p) => $a[0] === 'tareas' ? self::estimarTareas($a[2], $nRec, $a[3], $anchoCol, $f, $p) : self::estimarTabla($a[1]->tipo, $a[2], $anchoCol, $f, $p, count($a[1]->campos()));
        $esc = self::elegirEscala(function ($f, $p) use ($apilar, $alto) {
            $h = 0;
            foreach ($apilar as $a) $h += $alto($a, $f, $p) * 1.1 + 8;
            return $h;
        }, $colH / 100 * $altoRef);
        [, $f, $p] = self::ESCALAS[$esc];
        $y = $colY;
        foreach ($apilar as $a) {
            $h = ($alto($a, $f, $p) * 1.1 + 6) / $altoRef * 100;
            $out[] = $a[0] === 'tareas'
                ? self::bloquePct('tareas', $colX, $y, $colW, $h, $z++, $a[1]->config())
                : self::bloquePct('tabla', $colX, $y, $colW, $h, $z++, ['tabla_id' => (int)$a[1]->id]);
            $y += $h + 8 / $altoRef * 100;
        }
        return $out;
    }

    /** Dónde poner una tabla recién agregada: debajo de lo último de la columna derecha (el usuario la mueve a gusto) */
    public static function posicionNueva(VplanLamina $l, VplanTabla $t, array $existentes): VplanBloque
    {
        $x = 49.8;
        $w = 47.5;
        $fondo = 14.7;
        foreach ($existentes as $e) {
            if (in_array($e->tipo, ['tareas', 'tabla'], true)) {
                $x = (float)$e->x;
                $w = (float)$e->w;
                $fondo = max($fondo, (float)$e->y + (float)$e->h + 0.9);
            }
        }
        $pt = self::estimarTabla($t->tipo, self::textosDeFilas($t->filas()), $w / 100 * 43.2, 6.2, 1.5, count($t->campos()));
        $h = max(5.0, round($pt * 1.1 / self::altoRefPt($l) * 100, 3));
        $b = new VplanBloque(['lamina_id' => $l->id, 'tipo' => 'tabla', 'x' => $x, 'y' => max(0, min($fondo, 100 - $h)), 'w' => $w, 'h' => $h, 'z' => count($existentes)]);
        $b->setConfig(['tabla_id' => (int)$t->id]);
        return $b;
    }
}
