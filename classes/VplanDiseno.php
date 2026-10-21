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

    /** [clase, letra pt, relleno vertical pt]. Escalón fino: con mucha información la letra baja poco a poco
     *  (antes saltaba de 7.5 a 6.2 pt y las tablas quedaban con letra muy chica). */
    public const ESCALAS = [['e0', 9.0, 3.0], ['e1', 8.2, 2.6], ['e2', 7.5, 2.2], ['e3', 6.8, 1.9], ['e4', 6.2, 1.5],
        ['e5', 5.7, 1.2], ['e6', 5.2, 1.0], ['e7', 4.8, 0.8], ['e8', 4.4, 0.6], ['e9', 4.0, 0.4], ['e10', 3.6, 0.3]];

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

    /**
     * Líneas que ocupa un texto en una columna de $cm (cm de referencia), con letra $f y relleno $p.
     * El ancho medio del carácter es 0.55 de la letra (Helvetica/Arial), como en el PDF y en el navegador.
     */
    private static function lineas(string $t, float $cm, float $f, float $p): int
    {
        $cpl = max(4, (int)floor(($cm * self::PT_CM - 3.6 * $p - 2) / (0.55 * $f)));
        return max(1, (int)ceil(mb_strlen($t) / $cpl));
    }

    private static function fila(int $lineas, float $f, float $p): float
    {
        return $lineas * 1.25 * $f + 2 * $p + 1.5;   // línea + relleno + bordes
    }

    /** Ancho (cm) que necesita una columna con $caracteres de texto */
    private static function colCm(int $caracteres, float $f, float $p): float
    {
        return ($caracteres * 0.55 * $f + 3.6 * $p + 3) / self::PT_CM;
    }

    /** Columnas fijas de la tabla de tareas (`rec` agrupa todas las de recovery) */
    public const COLS_BASE = ['seq', 'tarea', 'inicio', 'fin', 'rec', 'pct', 'restr'];
    public const MAX_COLS_TEXTO = 6;
    /** Caracteres de ancho extra que necesita un campo de fecha (el calendario del navegador) */
    public const EXTRA_FECHA = 3;

    /**
     * Orden de las columnas de un bloque de tareas: las fijas y las de texto propias (`x:<id>`), según `config.orden`.
     * Lo que falte en el orden se agrega al final (las de texto) o en su sitio de siempre (las fijas).
     */
    public static function ordenTareas(array $cfg): array
    {
        $ids = array_map(fn($c) => 'x:' . $c['id'], self::columnasTexto($cfg));
        $validos = array_merge(self::COLS_BASE, $ids);
        $orden = array_values(array_unique(array_filter((array)($cfg['orden'] ?? []), fn($t) => in_array($t, $validos, true))));
        foreach (self::COLS_BASE as $i => $t) if (!in_array($t, $orden, true)) array_splice($orden, min($i, count($orden)), 0, [$t]);
        foreach ($ids as $t) if (!in_array($t, $orden, true)) $orden[] = $t;
        return $orden;
    }

    /** Columnas de texto de un bloque: [{id, titulo}] */
    public static function columnasTexto(array $cfg): array
    {
        $out = [];
        foreach (array_slice((array)($cfg['columnas'] ?? []), 0, self::MAX_COLS_TEXTO) as $c) {
            $id = preg_replace('/[^a-z0-9]/i', '', (string)($c['id'] ?? ''));
            if ($id === '' || isset($out[$id])) continue;
            $out[$id] = ['id' => substr($id, 0, 12), 'titulo' => mb_substr(trim((string)($c['titulo'] ?? '')), 0, 40)];
        }
        return array_values($out);
    }

    /**
     * Anchos (%) de las columnas físicas de la tabla de tareas, en el orden de `$orden`, que el estimador, el PDF y la vista usan por igual.
     * Por defecto: [seq, tarea, inicio, fin, (recovery inicio, fin)…, %, restricciones]. La tarea toma el espacio que sobra.
     */
    public static function columnasTareas(float $anchoCm, int $nRec, float $f, float $p, ?array $orden = null): array
    {
        $orden ??= self::COLS_BASE;
        $ancho = [
            'seq' => self::colCm(4, $f, $p), 'inicio' => self::colCm(10 + self::EXTRA_FECHA, $f, $p),
            'fin' => self::colCm(10 + self::EXTRA_FECHA, $f, $p),
            'pct' => self::colCm(4, $f, $p), 'restr' => self::colCm(10, $f, $p) * 1.05,
        ];
        $texto = self::colCm(14, $f, $p);
        $fijo = 0.0;
        foreach ($orden as $t) {
            if ($t === 'tarea') continue;
            $fijo += $t === 'rec' ? self::colCm(10, $f, $p) * 2 * $nRec : ($ancho[$t] ?? $texto);
        }
        $tarea = max(2.0, $anchoCm - $fijo);
        $cols = [];
        foreach ($orden as $t) {
            if ($t === 'tarea') $cols[] = $tarea;
            elseif ($t === 'rec') { for ($i = 0; $i < 2 * $nRec; $i++) $cols[] = self::colCm(10, $f, $p); }
            else $cols[] = $ancho[$t] ?? $texto;
        }
        $suma = array_sum($cols);
        return array_map(fn($c) => round($c / $suma * 100, 3), $cols);   // en % del ancho de la tabla
    }

    /** Índice de la columna TASK entre las columnas físicas */
    public static function posTarea(array $orden, int $nRec = 0): int
    {
        $i = 0;
        foreach ($orden as $t) { if ($t === 'tarea') return $i; $i += $t === 'rec' ? 2 * $nRec : 1; }
        return 0;
    }

    /** Alto de la tabla de tareas. $nombres = textos de cada renglón; $subtitulos = renglones de partida */
    public static function estimarTareas(array $nombres, int $nRec, int $subtitulos, float $anchoCm, float $f, float $p, ?array $orden = null): float
    {
        $orden ??= self::COLS_BASE;
        $anchoTarea = self::columnasTareas($anchoCm, $nRec, $f, $p, $orden)[self::posTarea($orden, $nRec)] / 100 * $anchoCm;
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

    /** Celdas de un renglón de tabla adicional, por columna (la 0 es el número) */
    private static function celdasDeFila(string $tipo, array $fila, int $nCampos): array
    {
        return match ($tipo) {
            'punch' => [(string)($fila['n'] ?? ''), (string)($fila['descripcion'] ?? ''), (string)($fila['inicio'] ?? ''),
                (string)($fila['fin'] ?? ''), (string)($fila['pct'] ?? '')],
            'restricciones' => [(string)($fila['n'] ?? ''), (string)($fila['descripcion'] ?? '')],
            'secundarias' => [(string)($fila['elemento'] ?? ''), (string)($fila['fecha'] ?? ''), (string)($fila['recovery'] ?? '')],
            default => array_merge([(string)($fila['n'] ?? '')],
                array_map(fn($i) => (string)($fila['c' . $i] ?? ''), range(0, max(0, $nCampos - 1)))),
        };
    }

    /** Títulos de las columnas de una tabla adicional (para saber qué tan anchas tienen que quedar) */
    private static function encabezadosDeFila(string $tipo, array $campos): array
    {
        return match ($tipo) {
            'punch' => ['N.', 'Descripción', 'Inicio', 'Fin', '%'],
            'restricciones' => ['N.', 'Descripción'],
            'secundarias' => ['Elemento', 'Fecha', 'Fecha recuperación'],
            default => array_merge(['N.'], array_values(array_map(fn($c) => (string)($c[0] ?? ''), $campos))),
        };
    }

    /**
     * Ancho (cm) de cada columna con reparto automático: cada una toma lo que mide su contenido
     * (como el navegador y el PDF). Si sobra espacio, se reparte en proporción a lo que pide cada
     * columna; si no caben, se reparten en proporción también (y el texto se parte en más renglones).
     * $extra = caracteres extra que necesita cada columna (el campo de fecha, por ejemplo).
     */
    private static function anchosAuto(array $cols, float $anchoCm, float $f, float $p, array $extra = []): array
    {
        $w = [];
        foreach ($cols as $c => $celdas) {
            $largo = 0;
            foreach ($celdas as $t) $largo = max($largo, mb_strlen((string)$t));
            $w[$c] = self::colCm($largo + (int)($extra[$c] ?? 0), $f, $p);
        }
        $suma = array_sum($w);
        if ($suma <= 0) return [];
        $factor = $anchoCm / $suma;   // el sobrante (o el faltante) se reparte en proporción
        foreach ($w as $c => $x) $w[$c] = $x * $factor;
        return $w;
    }

    /**
     * Reparto de las columnas de una tabla adicional, en % del ancho del bloque.
     * El reparto es el mismo en la vista, en el PDF y en el estimador: así lo que se ve en pantalla
     * y lo que sale en el PDF se ven igual.
     * Las tablas de anchos fijos (punch, restricciones, secundarias) usan `columnasTabla()`; las demás
     * reparten sus columnas según lo que mide el contenido de cada una.
     * @param int $nAcciones columnas de acciones que se reservan (solo en la vista: el PDF no las lleva)
     * @param array $tipos tipos de cada columna (los de `VplanTabla::metaColumnas()`)
     * @return array [anchos de las columnas de datos en %, ancho de las acciones en %]
     */
    public static function repartoColumnas(string $tipo, array $filas, array $campos, float $anchoCm, float $f, float $p, int $nAcciones = 0, array $tipos = []): array
    {
        $nCampos = count($campos);
        $accPct = $nAcciones > 0 ? min(14.0, 2.4 * $f / max(1.0, $anchoCm * self::PT_CM) * 100) : 0.0;
        $disponible = 100 - $accPct;
        if (in_array($tipo, ['punch', 'restricciones', 'secundarias'], true)) {
            $pct = self::columnasTabla($tipo, $nCampos);
            $suma = array_sum($pct) ?: 100;
            return [array_map(fn($w) => round($w / $suma * $disponible, 3), $pct), round($accPct, 3)];
        }
        // Reparto por contenido (como el navegador)
        $cols = [];
        foreach ($filas as $fila) {
            foreach (self::celdasDeFila($tipo, $fila, $nCampos) as $c => $txt) $cols[$c][] = (string)$txt;
        }
        foreach (self::encabezadosDeFila($tipo, $campos) as $c => $etq) $cols[$c][] = (string)$etq;
        // El campo de fecha del navegador necesita más sitio que el texto (el calendario y los espacios)
        $extra = [];
        foreach (array_keys($cols) as $c) {
            if (self::columnaEsFecha($tipo, (int)$c, $tipos)) $extra[$c] = self::EXTRA_FECHA;
        }
        $anchos = self::anchosAuto($cols, $anchoCm, $f, $p, $extra);
        $suma = array_sum($anchos);
        if ($suma <= 0) return [[], round($accPct, 3)];
        $out = [];
        foreach ($anchos as $x) $out[] = round($x / $suma * $disponible, 3);
        return [$out, round($accPct, 3)];
    }

    /** ¿La columna es un campo de fecha? (columna 0 = el número; la 1 = c0, la 2 = c1…) */
    private static function columnaEsFecha(string $tipo, int $columna, array $tipos): bool
    {
        if ($columna < 1) return false;
        if ($tipo === 'punch') return $columna === 2 || $columna === 3;   // inicio y fin
        $t = $tipos['c' . ($columna - 1)]['t'] ?? '';
        return $t === 'fecha';
    }

    /**
     * Alto de una tabla adicional (pt de referencia).
     * $filas = renglones de la tabla; $campos = encabezados y tipos de las columnas de datos.
     * El alto se calcula con el mismo reparto de columnas que usan la vista y el PDF.
     */
    public static function estimarTablaDe(string $tipo, array $filas, array $campos, float $anchoCm, float $f, float $p, int $nAcciones = 1, array $tipos = []): float
    {
        $nCampos = count($campos);
        [$pct] = self::repartoColumnas($tipo, $filas, $campos, $anchoCm, $f, $p, $nAcciones, $tipos);
        $anchos = [];
        foreach ($pct as $i => $w) $anchos[$i] = $anchoCm * $w / 100;
        $encabezados = self::encabezadosDeFila($tipo, $campos);
        // Encabezado de la tabla (2 o 3 renglones) y su título (un 25 % más grande)
        $nCab = $tipo === 'restricciones' ? 1 : ($tipo === 'punch' ? 3 : 2);
        $lineasCab = 1;
        foreach ($encabezados as $c => $etq) {
            if (!isset($anchos[$c])) continue;
            $lineasCab = max($lineasCab, self::lineas((string)$etq, $anchos[$c], $f, $p));
        }
        $h = self::fila($lineasCab, $f, $p) * $nCab + 0.25 * $f * 1.25;
        // Renglones: cada uno mide lo que mide su celda más alta
        foreach ($filas as $fila) {
            $lineas = 1;
            foreach (self::celdasDeFila($tipo, $fila, $nCampos) as $c => $txt) {
                if (!isset($anchos[$c])) continue;
                $lineas = max($lineas, self::lineas((string)$txt, $anchos[$c], $f, $p));
            }
            $h += self::fila($lineas, $f, $p);
        }
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

    public static function escalaTareas(VplanLamina $l, VplanBloque $b, array $td, int $nRec): int
    {
        $nombres = self::textosDeTareas($td);
        $sub = $td['subtitulos'] ? count($td['grupos']) : 0;
        $ancho = self::anchoRefCm($b);
        $orden = self::ordenTareas($b->config());
        return self::elegirEscala(fn($f, $p) => self::estimarTareas($nombres, $nRec, $sub, $ancho, $f, $p, $orden), self::capacidadPt($l, $b));
    }

    public static function escalaTabla(VplanLamina $l, VplanBloque $b, array $tb): int
    {
        $ancho = self::anchoRefCm($b);
        $tipo = $tb['tabla']->tipo;
        $filas = $tb['filas'];
        $campos = $tb['campos'];
        return self::elegirEscala(fn($f, $p) => self::estimarTablaDe($tipo, $filas, $campos, $ancho, $f, $p, 1, $tb['tabla']->metaColumnas()), self::capacidadPt($l, $b));
    }

    /** [letra, relleno] en pt de una tabla: la escala de ESCALAS y, si cabe con la más grande, crece (hasta 48 pt) para llenar el bloque */
    public static function medidasTabla(VplanLamina $l, VplanBloque $b, array $tb, int $extra = 0): array
    {
        $i = min(count(self::ESCALAS) - 1, self::escalaTabla($l, $b, $tb) + $extra);
        [, $f, $p] = self::ESCALAS[$i];
        if ($i > 0 || $extra < 0) return [$f, $p];
        $ancho = self::anchoRefCm($b);
        $tipo = $tb['tabla']->tipo;
        $filas = $tb['filas'];
        $campos = $tb['campos'];
        $cap = self::capacidadPt($l, $b);
        $cabe = fn(float $fz) => self::estimarTablaDe($tipo, $filas, $campos, $ancho, $fz, $fz / 3, 1, $tb['tabla']->metaColumnas()) * 1.06 <= $cap;
        $lo = 9.0; $hi = 48.0;
        if ($cabe($hi)) return [$hi, $hi / 3];
        for ($k = 0; $k < 14; $k++) { $m = ($lo + $hi) / 2; if ($cabe($m)) $lo = $m; else $hi = $m; }
        return [round($lo, 2), round($lo / 3, 2)];
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
        $apilar = [];   // [tipo, objeto, datos, subtítulos]
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
            $filas = $t->filas();
            if ($t->tipo === 'restricciones' && $nRestr) {
                // Las restricciones del programa también salen en la lámina: se cuentan para el alto
                $filas = array_merge(array_fill(0, $nRestr, ['n' => 0, 'descripcion' => 'Restricción del programa con una descripción de largo promedio']), $filas);
            }
            $apilar[] = ['tabla', $t, $filas, 0];
        }
        $altoRef = self::altoRefPt($l);
        $anchoCol = $colW / 100 * (self::REF_ANCHO_MM / 10);
        $alto = fn($a, $f, $p) => $a[0] === 'tareas'
            ? self::estimarTareas($a[2], $nRec, $a[3], $anchoCol, $f, $p)
            : self::estimarTablaDe($a[1]->tipo, $a[2], $a[1]->campos(), $anchoCol, $f, $p, 1, $a[1]->metaColumnas());
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
        $pt = self::estimarTablaDe($t->tipo, $t->filas(), $t->campos(), $w / 100 * 43.2, 6.2, 1.5, 1, $t->metaColumnas());
        $h = max(5.0, round($pt * 1.1 / self::altoRefPt($l) * 100, 3));
        $b = new VplanBloque(['lamina_id' => $l->id, 'tipo' => 'tabla', 'x' => $x, 'y' => max(0, min($fondo, 100 - $h)), 'w' => $w, 'h' => $h, 'z' => count($existentes)]);
        $b->setConfig(['tabla_id' => (int)$t->id]);
        return $b;
    }
}
