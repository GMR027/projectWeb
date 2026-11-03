<?php

namespace Classes;

use Model\SheetflowHoja;
use Model\SubactividadHoja;
use Model\VplanTabla;
use Model\RedactorDocumento;

/**
 * SheetFlow: tablas del proyecto. Un documento guarda un JSON con
 *   cols  [{n nombre, t tipo, a alineación, tot total}]
 *   rows  [[texto crudo de cada celda]]      (una celda que empieza con «=» es una fórmula)
 *   calc  {"fila,col": resultado}            (resultado de las fórmulas; lo calcula el cliente)
 *   tot   {col: resultado}                   (fila de totales; lo calcula el cliente)
 *   estilo {enc, encTxt, zebra, bordes}
 * Todo lo que llega del cliente pasa por limpiar().
 */
class Sheetflow
{
    public const TIPOS = ['texto' => 'Texto', 'numero' => 'Número', 'moneda' => 'Moneda', 'porcentaje' => 'Porcentaje', 'fecha' => 'Fecha', 'lista' => 'Lista desplegable', 'casilla' => 'Casilla (sí/no)', 'semaforo' => 'Semáforo'];
    public const SEMAFORO = ['verde' => 'Verde', 'amarillo' => 'Amarillo', 'rojo' => 'Rojo'];
    public const MAX_OPCIONES = 20;
    public const ALINEAR = ['izquierda', 'centro', 'derecha'];
    public const TOTALES = ['' => 'Sin total', 'suma' => 'Suma', 'promedio' => 'Promedio', 'min' => 'Mínimo', 'max' => 'Máximo', 'cuenta' => 'Cuenta'];
    public const MAX_COLS = 30;
    public const MAX_FILAS = 1000;
    public const MAX_CELDA = 500;
    public const MAX_BYTES = 2_000_000;
    /** Columnas base de una tabla vinculada a las subactividades de una actividad */
    public const ROLES_SUBACTIVIDAD = ['nombre', 'inicio', 'fin', 'status'];
    /** Columnas base de la tabla de instalación de materiales de una lámina de vPlan */
    public const ROLES_MATERIALES = ['material', 'fecha_material', 'status_material'];
    /** Roles que sobreviven a las herramientas (los demás se descartan) */
    public const ROLES = ['nombre', 'inicio', 'fin', 'status', 'material', 'fecha_material', 'status_material'];

    private static function hex($c, string $def): string
    {
        return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : $def;
    }

    /** Hoja nueva: n columnas de texto y filas vacías */
    public static function vacia(int $cols = 4, int $filas = 10): array
    {
        return self::limpiar([
            'cols' => array_map(fn($i) => ['n' => 'Columna ' . ($i + 1)], range(0, $cols - 1)),
            'rows' => array_fill(0, $filas, array_fill(0, $cols, '')),
        ]);
    }

    /** Estructura válida y acotada a partir de lo que sea que llegue */
    public static function limpiar($d): array
    {
        $d = is_array($d) ? $d : [];
        $cols = [];
        foreach (array_slice(array_values((array)($d['cols'] ?? [])), 0, self::MAX_COLS) as $c) {
            $c = is_array($c) ? $c : [];
            $t = isset(self::TIPOS[$c['t'] ?? '']) ? $c['t'] : 'texto';
            $numerico = in_array($t, ['numero', 'moneda', 'porcentaje'], true);
            $a = in_array($c['a'] ?? '', self::ALINEAR, true) ? $c['a'] : ($numerico ? 'derecha' : (in_array($t, ['casilla', 'semaforo'], true) ? 'centro' : 'izquierda'));
            $tot = (string)($c['tot'] ?? '');
            $cols[] = [
                'n' => mb_substr(trim(preg_replace('/[\x00-\x1f]/u', ' ', (string)($c['n'] ?? ''))), 0, 60),
                't' => $t, 'a' => $a,
                'tot' => isset(self::TOTALES[$tot]) && ($numerico || $tot === 'cuenta') ? $tot : '',
                'w' => isset($c['w']) && is_numeric($c['w']) && $c['w'] >= 40 ? (int)min(800, $c['w']) : 0,
            ];
            if ($t === 'lista') $cols[count($cols) - 1]['op'] = self::opciones($c['op'] ?? []);
            $rol = (string)($c['rol'] ?? '');
            if (in_array($rol, self::ROLES, true)) $cols[count($cols) - 1]['rol'] = $rol;
            if (!empty($c['fija'])) $cols[count($cols) - 1]['fija'] = 1;
        }
        if (!$cols) $cols[] = ['n' => 'Columna 1', 't' => 'texto', 'a' => 'izquierda', 'tot' => ''];
        $nc = count($cols);
        $rows = [];
        foreach (array_slice(array_values((array)($d['rows'] ?? [])), 0, self::MAX_FILAS) as $f) {
            $f = array_values((array)$f);
            $fila = [];
            for ($c = 0; $c < $nc; $c++) $fila[] = mb_substr(str_replace("\r", '', (string)($f[$c] ?? '')), 0, self::MAX_CELDA);
            $rows[] = $fila;
        }
        $nr = count($rows);
        $calc = [];
        foreach ((array)($d['calc'] ?? []) as $k => $v) {
            if (!preg_match('/^(\d+),(\d+)$/', (string)$k, $m) || $m[1] >= $nr || $m[2] >= $nc || !str_starts_with(ltrim($rows[$m[1]][$m[2]]), '=')) continue;
            $calc[$k] = mb_substr((string)$v, 0, 60);
        }
        $tot = [];
        foreach ((array)($d['tot'] ?? []) as $k => $v) {
            if (is_numeric($k) && $k >= 0 && $k < $nc && $cols[(int)$k]['tot'] !== '') $tot[(int)$k] = mb_substr((string)$v, 0, 60);
        }
        $e = (array)($d['estilo'] ?? []);
        $estilo = ['enc' => self::hex($e['enc'] ?? '', '#1f3a5f'), 'encTxt' => self::hex($e['encTxt'] ?? '', '#ffffff'),
            'zebra' => !array_key_exists('zebra', $e) || !empty($e['zebra']), 'bordes' => !array_key_exists('bordes', $e) || !empty($e['bordes'])];
        $fmt = [];
        foreach (array_slice((array)($d['fmt'] ?? []), 0, 20000, true) as $k => $f) {
            if (!preg_match('/^(-1|\d+),(\d+)$/', (string)$k, $m) || $m[1] >= $nr || $m[2] >= $nc) continue;
            if ($f = self::limpiarFmt($f)) $fmt[$k] = $f;
        }
        $meta = [];
        $m = is_array($d['meta'] ?? null) ? $d['meta'] : [];
        $s = is_array($m['subactividad'] ?? null) ? $m['subactividad'] : [];
        if (!empty($s) && array_key_exists('ids', $s)) {
            $ids = [];
            foreach ((array)($s['ids'] ?? []) as $id) $ids[] = (is_numeric($id) && (int)$id > 0) ? (int)$id : null;
            $ids = array_slice(array_pad($ids, $nr, null), 0, $nr);
            $meta['subactividad'] = ['actividad_id' => (int)($s['actividad_id'] ?? 0), 'ids' => $ids];
        }
        $mat = is_array($m['materiales'] ?? null) ? $m['materiales'] : [];
        if (!empty($mat) && array_key_exists('ids', $mat)) {
            $ids = [];
            foreach ((array)($mat['ids'] ?? []) as $clave) {
                $clave = is_string($clave) ? trim($clave) : '';
                $ids[] = preg_match('/^[as]\d+$/', $clave) ? $clave : null;
            }
            $ids = array_slice(array_pad($ids, $nr, null), 0, $nr);
            $meta['materiales'] = ['ids' => $ids];
        }
        // Vista: congelar filas/columnas, filtros por columna, formato condicional y gráficos
        $vista = self::limpiarVista(is_array($d['vista'] ?? null) ? $d['vista'] : [], $nr, $nc);
        // Celdas combinadas: rectángulos {r0,c0,r1,c1}; la fila -1 es el encabezado y no se mezcla con los datos.
        $merges = [];
        foreach (array_slice((array)($d['merges'] ?? []), 0, 500) as $mg) {
            if (!is_array($mg)) continue;
            $r0 = (int)($mg['r0'] ?? 0); $c0 = (int)($mg['c0'] ?? 0);
            $r1 = (int)($mg['r1'] ?? 0); $c1 = (int)($mg['c1'] ?? 0);
            if ($r0 > $r1) [$r0, $r1] = [$r1, $r0];
            if ($c0 > $c1) [$c0, $c1] = [$c1, $c0];
            if ($r0 < -1 || $c0 < 0 || $r1 >= $nr || $c1 >= $nc) continue;
            if ($r0 === -1 && $r1 > -1) continue;
            if ($r0 === $r1 && $c0 === $c1) continue;
            $choca = false;
            foreach ($merges as $x) if ($x['r0'] <= $r1 && $x['r1'] >= $r0 && $x['c0'] <= $c1 && $x['c1'] >= $c0) { $choca = true; break; }
            if ($choca) continue;
            $merges[] = ['r0' => $r0, 'c0' => $c0, 'r1' => $r1, 'c1' => $c1];
        }
        return ['cols' => $cols, 'rows' => $rows, 'calc' => (object)$calc, 'tot' => (object)$tot, 'estilo' => $estilo, 'fmt' => (object)$fmt, 'meta' => $meta, 'merges' => $merges, 'vista' => $vista];
    }

    /** Tipos de formato condicional (el nombre viaja en datos.vista.cond[].tipo) */
    public const CONDICIONALES = [
        'mayor' => 'Es mayor que', 'menor' => 'Es menor que', 'igual' => 'Es igual a', 'entre' => 'Está entre',
        'contiene' => 'El texto contiene', 'duplicados' => 'Valores duplicados', 'escala' => 'Escala de color', 'barra' => 'Barra de datos',
    ];

    /** Vista de la hoja: congelar, filtros, reglas condicionales y gráficos (todo acotado a la tabla actual) */
    private static function limpiarVista($v, int $nr, int $nc): array
    {
        $v = is_array($v) ? $v : [];
        $ent = fn($x, $def = 0, $max = 100000) => is_numeric($x) ? (int)max($def, min($max, $x)) : $def;
        $filtros = [];
        foreach ((array)($v['filtros'] ?? []) as $c => $f) {
            if (!is_numeric($c) || (int)$c < 0 || (int)$c >= $nc || !is_array($f)) continue;
            $vals = [];
            foreach (array_slice((array)($f['vals'] ?? []), 0, 500) as $x) {
                $x = mb_substr((string)$x, 0, 200);
                if (!in_array($x, $vals, true)) $vals[] = $x;   // '' es la marca de «ninguno marcado»
            }
            $texto = mb_substr(trim((string)($f['texto'] ?? '')), 0, 60);
            if ($vals || $texto !== '') $filtros[(int)$c] = ['vals' => $vals, 'texto' => $texto];
        }
        $cond = [];
        foreach (array_slice((array)($v['cond'] ?? []), 0, 40) as $r) {
            if (!is_array($r)) continue;
            $tipo = (string)($r['tipo'] ?? '');
            if (!isset(self::CONDICIONALES[$tipo])) continue;
            $r0 = $ent($r['r0'] ?? 0, 0, max(0, $nr - 1));
            $c0 = $ent($r['c0'] ?? 0, 0, max(0, $nc - 1));
            $r1 = $ent($r['r1'] ?? $r0, 0, max(0, $nr - 1));
            $c1 = $ent($r['c1'] ?? $c0, 0, max(0, $nc - 1));
            if ($r0 > $r1) [$r0, $r1] = [$r1, $r0];
            if ($c0 > $c1) [$c0, $c1] = [$c1, $c0];
            $cond[] = [
                'r0' => $r0, 'c0' => $c0, 'r1' => $r1, 'c1' => $c1, 'tipo' => $tipo,
                'a' => (string)($r['a'] ?? ''), 'b' => (string)($r['b'] ?? ''), 'txt' => mb_substr((string)($r['txt'] ?? ''), 0, 60),
                'color' => self::hex($r['color'] ?? '', in_array($tipo, ['escala', 'barra'], true) ? '#2563eb' : '#dc2626'),
                'fondo' => self::hex($r['fondo'] ?? '', $tipo === 'escala' ? '#dcfce7' : '#fee2e2'),
                'b' => !empty($r['b']) ? 1 : 0,
            ];
        }
        $graficos = [];
        foreach (array_slice((array)($v['graficos'] ?? []), 0, 6) as $g) {
            if (!is_array($g)) continue;
            $tipo = (string)($g['tipo'] ?? 'barra');
            if (!in_array($tipo, ['barra', 'linea'], true)) continue;
            $r0 = $ent($g['r0'] ?? 0, 0, max(0, $nr - 1));
            $c0 = $ent($g['c0'] ?? 0, 0, max(0, $nc - 1));
            $r1 = $ent($g['r1'] ?? $r0, 0, max(0, $nr - 1));
            $c1 = $ent($g['c1'] ?? $c0, 0, max(0, $nc - 1));
            if ($r0 > $r1) [$r0, $r1] = [$r1, $r0];
            if ($c0 > $c1) [$c0, $c1] = [$c1, $c0];
            $graficos[] = ['tipo' => $tipo, 'r0' => $r0, 'c0' => $c0, 'r1' => $r1, 'c1' => $c1, 'titulo' => mb_substr(trim((string)($g['titulo'] ?? '')), 0, 80)];
        }
        return [
            'filas' => $ent($v['filas'] ?? 0, 0, $nr),
            'cols' => $ent($v['cols'] ?? 0, 0, $nc),
            'filtros' => (object)$filtros,
            'cond' => $cond,
            'graficos' => $graficos,
        ];
    }

    public const BORDES = ['bt' => 'top', 'br' => 'right', 'bb' => 'bottom', 'bl' => 'left'];
    public const ESTILOS_BORDE = ['solid', 'dashed', 'dotted', 'double'];

    /** Formato de una celda: negrita/cursiva/subrayado, tamaño, colores, alineación, bordes («grosor|estilo|#color» o «n» = sin borde») e imagen */
    private static function limpiarFmt($f): array
    {
        $f = is_array($f) ? $f : [];
        $o = [];
        foreach (['b', 'i', 'u'] as $k) if (!empty($f[$k])) $o[$k] = 1;
        if (isset($f['fs']) && is_numeric($f['fs'])) $o['fs'] = (int)max(6, min(72, $f['fs']));
        foreach (['tc', 'bg'] as $k) if (isset($f[$k]) && is_string($f[$k]) && preg_match('/^#[0-9a-fA-F]{6}$/', $f[$k])) $o[$k] = strtolower($f[$k]);
        if (in_array($f['al'] ?? '', self::ALINEAR, true)) $o['al'] = $f['al'];
        foreach (array_keys(self::BORDES) as $k) {
            $v = (string)($f[$k] ?? '');
            if ($v === 'n') $o[$k] = 'n';
            elseif (preg_match('/^([1-6])\|(solid|dashed|dotted|double)\|(#[0-9a-fA-F]{6})$/', $v, $m)) $o[$k] = $m[1] . '|' . $m[2] . '|' . strtolower($m[3]);
        }
        if (isset($f['im']) && is_string($f['im']) && preg_match('#^/uploads/vplan/[a-f0-9]{32}\.(png|jpg)$#', $f['im'])) $o['im'] = $f['im'];
        $nf = trim((string)($f['nf'] ?? ''));
        if ($nf !== '' && preg_match('/^[#0.,%$\/\-: A-Za-zÁÉÍÓÚáéíóúñÑ]{1,40}$/u', $nf)) $o['nf'] = $nf;
        return $o;
    }

    /** Letra negra o blanca según la luminosidad del relleno (igual que contraste() en sheetflow.js) */
    public static function contraste(string $hex): string
    {
        [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 140 ? '#111111' : '#ffffff';
    }

    /** CSS de un formato de celda (tamaño en pt). $sinBordes = true no emite bordes */
    public static function cssFmt(array $f, bool $sinBordes = false): string
    {
        $c = '';
        if (!empty($f['b'])) $c .= 'font-weight:bold;';
        if (!empty($f['i'])) $c .= 'font-style:italic;';
        if (!empty($f['u'])) $c .= 'text-decoration:underline;';
        if (!empty($f['fs'])) $c .= 'font-size:' . round($f['fs'] * 0.75, 1) . 'pt;';
        if (!empty($f['tc'])) $c .= 'color:' . $f['tc'] . ';';
        elseif (!empty($f['bg'])) $c .= 'color:' . self::contraste($f['bg']) . ';';
        if (!empty($f['bg'])) $c .= 'background-color:' . $f['bg'] . ';';
        if (!empty($f['al'])) $c .= 'text-align:' . ['izquierda' => 'left', 'centro' => 'center', 'derecha' => 'right'][$f['al']] . ';';
        if (!$sinBordes) foreach (self::BORDES as $k => $lado) {
            if (!isset($f[$k])) continue;
            if ($f[$k] === 'n') { $c .= "border-$lado:none;"; continue; }
            [$w, $est, $col] = explode('|', $f[$k]);
            $c .= "border-$lado:{$w}px $col $est;";
        }
        return $c;
    }

    // ---------- Valores y formato (mismo criterio que public/vendor/sheetflow.js) ----------

    /** Opciones de una columna de lista: sin repetidos, máx. 20 de 40 caracteres */
    public static function opciones($op): array
    {
        $out = [];
        foreach ((array)$op as $x) {
            $x = mb_substr(trim(preg_replace('/[\x00-\x1f]/u', ' ', (string)$x)), 0, 40);
            if ($x !== '' && !in_array($x, $out, true)) $out[] = $x;
            if (count($out) >= self::MAX_OPCIONES) break;
        }
        return $out;
    }

    /** Valor que se guarda en una celda de casilla o semáforo («si», «x», «Verde»…); los demás tipos lo dejan igual */
    public static function normalizarValor(string $v, string $tipo): string
    {
        $v = trim($v);
        if ($tipo === 'casilla') return in_array(mb_strtolower($v), ['1', 'si', 'sí', 'x', 'true', 'yes', '✔', '✓'], true) ? '1' : '';
        if ($tipo === 'semaforo') {
            $k = mb_strtolower($v);
            $alias = ['green' => 'verde', 'yellow' => 'amarillo', 'red' => 'rojo'];
            $k = $alias[$k] ?? $k;
            return isset(self::SEMAFORO[$k]) ? $k : '';
        }
        return $v;
    }

    /** Hoja nueva a partir del constructor de «tabla inteligente»: columnas {n, t, op, bg} y número de renglones */
    public static function inteligente($cols, int $filas): array
    {
        $out = [];
        $fmt = [];
        foreach (array_slice(array_values((array)$cols), 0, self::MAX_COLS) as $i => $c) {
            $c = is_array($c) ? $c : [];
            $out[] = ['n' => $c['n'] ?? 'Columna ' . ($i + 1), 't' => $c['t'] ?? 'texto', 'op' => $c['op'] ?? []];
            $bg = (string)($c['bg'] ?? '');
            if (preg_match('/^#[0-9a-f]{6}$/i', $bg)) $fmt["-1,$i"] = ['bg' => strtolower($bg)];
        }
        $n = max(1, count($out));
        $filas = max(1, min(200, $filas));
        return self::limpiar(['cols' => $out, 'rows' => array_fill(0, $filas, array_fill(0, $n, '')), 'fmt' => $fmt]);
    }

    /** Número de un texto («1,234.5», «$ 12», «45%») o null */
    public static function numero($v): ?float
    {
        $t = trim(str_replace([',', '$', '%', ' '], '', (string)$v));
        return $t !== '' && is_numeric($t) ? (float)$t : null;
    }

    private static function decimales(float $n, int $max = 4): string
    {
        $s = number_format($n, $max, '.', ',');
        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    public static function formatear($v, string $tipo, string $nf = ''): string
    {
        $v = trim((string)$v);
        if ($v === '') return '';
        if ($nf !== '' && ($x = self::formatoNumero($v, $nf)) !== null) return $x;
        $n = self::numero($v);
        switch ($tipo) {
            case 'numero': return $n === null ? $v : self::decimales($n);
            case 'moneda': return $n === null ? $v : ($n < 0 ? '-' : '') . '$' . number_format(abs($n), 2, '.', ',');
            case 'porcentaje': return $n === null ? $v : self::decimales($n, 2) . '%';
            case 'fecha':
                return preg_match('/^(\d{4})-(\d\d)-(\d\d)$/', $v, $m) ? "$m[3]/$m[2]/$m[1]" : $v;
            case 'casilla': return self::normalizarValor($v, 'casilla') === '1' ? '✔' : '';
            case 'semaforo': return self::SEMAFORO[self::normalizarValor($v, 'semaforo')] ?? $v;
        }
        return $v;
    }

    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /**
     * Aplica un código de formato de número o fecha (estilo Excel) a un valor.
     * Devuelve null si el código no se reconoce (se usa entonces el formato del tipo de columna).
     * Mismos códigos en public/vendor/sheetflow.js (formatoNumero).
     */
    public static function formatoNumero(string $v, string $codigo): ?string
    {
        $codigo = trim($codigo);
        if ($codigo === '') return null;
        // Fechas: cualquier código con d/m/a (día/mes/año)
        if (preg_match('/[dma]/i', $codigo) && !preg_match('/[#0]/', $codigo)) {
            if (!preg_match('/^(\d{4})-(\d\d)-(\d\d)/', $v, $f)) return null;
            $ts = strtotime($f[1] . '-' . $f[2] . '-' . $f[3] . ' 12:00:00');
            return preg_replace_callback('/mmmm|mmm|aaaa|yyyy|yy|dd|d|mm|m/i', function ($m) use ($ts) {
                $t = strtolower($m[0]);
                $mes = self::MESES[(int)date('n', $ts) - 1];
                return match ($t) {
                    'mmmm' => mb_convert_case($mes, MB_CASE_TITLE, 'UTF-8'),
                    'mmm' => mb_substr($mes, 0, 3),
                    'aaaa', 'yyyy' => date('Y', $ts),
                    'yy' => date('y', $ts),
                    'dd' => date('d', $ts),
                    'd' => (string)(int)date('j', $ts),
                    'mm' => date('m', $ts),
                    default => (string)(int)date('n', $ts),
                };
            }, $codigo);
        }
        $n = self::numero($v);
        if ($n === null) return null;
        if (!preg_match('/([#0,]*[#0](?:\.[0#]+)?)/', $codigo, $m, PREG_OFFSET_CAPTURE)) return null;
        $num = $m[1][0];
        $ini = $m[1][1];
        $pref = str_replace(['"', '\\'], '', substr($codigo, 0, $ini));
        $suf = str_replace(['"', '\\'], '', substr($codigo, $ini + strlen($num)));
        $pct = str_contains($codigo, '%');
        if ($pct) { $n *= 100; $suf = str_replace('%', '', $suf); }
        $dec = str_contains($num, '.') ? strlen(substr($num, strpos($num, '.') + 1)) : 0;
        $miles = str_contains(explode('.', $num)[0], ',');
        $neg = $n < 0 ? '-' : '';
        return $neg . $pref . number_format(abs($n), $dec, '.', $miles ? ',' : '') . $suf . ($pct ? '%' : '');
    }

    /** Valor mostrado de una celda: el resultado si es fórmula, formateado según el tipo de su columna */
    public static function celda(array $d, int $r, int $c): string
    {
        $crudo = (string)($d['rows'][$r][$c] ?? '');
        $calc = (array)$d['calc'];
        $v = str_starts_with(ltrim($crudo), '=') ? (string)($calc["$r,$c"] ?? '') : $crudo;
        $f = (array)($d['fmt'] ?? []);
        return self::formatear($v, $d['cols'][$c]['t'], (string)($f["$r,$c"]['nf'] ?? ''));
    }

    /** Todo lo que necesita quien consume la tabla: nombres, tipos, alineación, renglones ya formateados y fila de totales (o null) */
    public static function matriz(SheetflowHoja $h): array
    {
        $d = $h->contenido();
        $cols = $d['cols'];
        $filas = [];
        foreach ($d['rows'] as $r => $fila) {
            // los renglones totalmente vacíos al final no se llevan a otras herramientas
            $filas[] = array_map(fn($c) => self::celda($d, $r, $c), array_keys($cols));
        }
        $fmt = (array)$d['fmt'];
        $conImagen = [];
        foreach ($fmt as $k => $f) if (!empty($f['im'])) $conImagen[(int)explode(',', $k)[0]] = true;
        while ($filas && !isset($conImagen[count($filas) - 1]) && !array_filter(end($filas), fn($x) => $x !== '')) array_pop($filas);
        $tot = (array)$d['tot'];
        $totales = null;
        if (array_filter(array_column($cols, 'tot'))) {
            $totales = [];
            foreach ($cols as $c => $col) $totales[] = $col['tot'] !== '' && isset($tot[$c]) ? ($col['tot'] === 'cuenta' ? (string)$tot[$c] : self::formatear($tot[$c], $col['t'])) : '';
            $totales[0] = $totales[0] !== '' ? $totales[0] : 'Total';
        }
        return ['columnas' => array_column($cols, 'n'), 'tipos' => array_column($cols, 't'), 'alinear' => array_column($cols, 'a'), 'filas' => $filas, 'totales' => $totales, 'estilo' => $d['estilo'], 'fmt' => $fmt, 'anchos' => array_column($cols, 'w'), 'opciones' => array_map(fn($c) => $c['op'] ?? [], $cols), 'meta' => $d['meta'] ?? [], 'merges' => $d['merges'] ?? [], 'vista' => $d['vista'] ?? []];
    }

    // ---------- Plantillas ----------

    public static function plantillas(): array
    {
        return [
            'materiales' => ['Control de materiales', 'Material, unidad, cantidad, precio e importe con total', 'fa-cubes'],
            'presupuesto' => ['Presupuesto por conceptos', 'Concepto, unidad, cantidad, P.U. e importe', 'fa-coins'],
            'restricciones' => ['Registro de restricciones', 'Descripción, responsable, fecha compromiso y estado', 'fa-ban'],
            'asistencia' => ['Asistencia semanal', 'Nombre, puesto y los seis días de la semana', 'fa-user-check'],
            'programa' => ['Programa (partidas y actividades)', 'Se llena con el programa de obra del proyecto', 'fa-bars-progress'],
            'estimacion' => ['Estimación de obra', 'Conceptos, cantidades, precios, amortización y neto a pagar', 'fa-file-invoice-dollar'],
            'submittals' => ['Control de submittals', 'Documentos, disciplina, estado y fechas de respuesta', 'fa-folder-open'],
            'bitacora' => ['Bitácora de obra', 'Registro diario de personal, actividades, materiales y equipo', 'fa-clipboard-list'],
            'almacen' => ['Control de almacén', 'Entradas, salidas, existencia y alerta de compra', 'fa-warehouse'],
            'calidad' => ['Inspección de calidad', 'Puntos de inspección, cumplimiento y observaciones', 'fa-clipboard-check'],
            'equipo' => ['Control de equipo', 'Horas, tarifa e importe de maquinaria y equipo rentado', 'fa-truck'],
        ];
    }

    private static function plano(array $cols, array $filas, array $extra = []): array
    {
        return SheetflowFormulas::recalcular(self::limpiar(['cols' => $cols, 'rows' => $filas] + $extra));
    }

    /** Saneado + resultados de fórmulas y totales calculados aquí (el servidor es quien manda) */
    public static function normalizar($d): array
    {
        return SheetflowFormulas::recalcular(self::limpiar($d));
    }

    /**
     * Guarda el contenido de la hoja (saneado y recalculado). Si la hoja está vinculada
     * a las subactividades de una actividad, primero aplica los cambios a las subactividades
     * y guarda el identificador de cada renglón; todo en una transacción.
     */
    public static function guardarDatos(SheetflowHoja $h, array $d): SheetflowHoja
    {
        $d = self::normalizar($d);
        $vinculo = SubactividadHoja::deHoja((int)$h->id);
        if (!$vinculo) {
            $h->datos = json_encode(self::sinVinculoSubactividades($d), JSON_UNESCAPED_UNICODE);
            $h->guardar();
            return $h;
        }
        $db = SheetflowHoja::conexion();
        $db->begin_transaction();
        try {
            $d = SubactividadHoja::aplicar($h, $d);
            $d = self::normalizar($d);   // los valores ya validados pueden cambiar fórmulas y totales
            $h->datos = json_encode($d, JSON_UNESCAPED_UNICODE);
            if (!$h->guardar()) throw new \RuntimeException('No se pudo guardar la tabla.');
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return $h;
    }

    /** Copia de los datos sin el vínculo a subactividades (para duplicar, exportar o importar) */
    public static function sinVinculo($d): array
    {
        $d = self::sinVinculoSubactividades($d);
        if (isset($d['meta']['materiales'])) unset($d['meta']['materiales']);
        if (empty($d['meta'])) unset($d['meta']);
        if (isset($d['cols']) && is_array($d['cols'])) {
            foreach ($d['cols'] as &$c) if (is_array($c)) unset($c['rol'], $c['fija']);
            unset($c);
        }
        return $d;
    }

    /**
     * Copia sin el vínculo a subactividades, pero conserva el de instalación de materiales
     * (lo usa guardarDatos: una hoja normal no debe perder sus roles de materiales).
     */
    public static function sinVinculoSubactividades($d): array
    {
        $d = is_array($d) ? $d : [];
        if (isset($d['meta']['subactividad'])) unset($d['meta']['subactividad']);
        if (empty($d['meta'])) unset($d['meta']);
        if (isset($d['cols']) && is_array($d['cols'])) {
            foreach ($d['cols'] as &$c) {
                if (!is_array($c)) continue;
                if (in_array($c['rol'] ?? '', self::ROLES_SUBACTIVIDAD, true)) unset($c['rol'], $c['fija']);
            }
            unset($c);
        }
        return $d;
    }

    /** Datos iniciales de una plantilla (o null si la clave no existe) */
    public static function plantilla(string $clave, int $proyectoId): ?array
    {
        $vacias = fn($n, $cols) => array_fill(0, $n, array_fill(0, $cols, ''));
        switch ($clave) {
            case 'materiales': case 'presupuesto':
                $mat = $clave === 'materiales';
                $filas = [];
                for ($i = 2; $i <= 11; $i++) $filas[] = ['', '', '', '', "=C$i*D$i"];
                return self::plano([
                    ['n' => $mat ? 'Material' : 'Concepto'], ['n' => 'Unidad'], ['n' => 'Cantidad', 't' => 'numero'], ['n' => $mat ? 'Precio unitario' : 'P.U.', 't' => 'moneda'],
                    ['n' => 'Importe', 't' => 'moneda', 'tot' => 'suma'],
                ], $filas);
            case 'restricciones':
                return self::plano([['n' => 'No.'], ['n' => 'Descripción'], ['n' => 'Responsable'], ['n' => 'Fecha compromiso', 't' => 'fecha'], ['n' => 'Estado']], $vacias(10, 5));
            case 'asistencia':
                return self::plano(array_merge([['n' => 'Nombre'], ['n' => 'Puesto']], array_map(fn($x) => ['n' => $x, 'a' => 'centro'], ['L', 'M', 'M', 'J', 'V', 'S'])), $vacias(12, 8));
            case 'programa':
                $filas = [];
                $vistas = [];
                foreach (RedactorDocumento::programa($proyectoId) as $f) {
                    if (empty($vistas[$f['pid']])) {
                        $vistas[$f['pid']] = true;
                        $filas[] = [(string)$f['pcodigo'], (string)$f['pnombre'], '', '', '', ''];
                    }
                    if ($f['nombre']) $filas[] = [(string)$f['codigo'], (string)$f['nombre'], (string)$f['fecha_inicio'], (string)$f['fecha_fin'], (string)$f['duracion_dias'], (string)(int)$f['progreso']];
                }
                if (!$filas) $filas = $vacias(5, 6);
                return self::plano([['n' => 'Código'], ['n' => 'Partida / actividad'], ['n' => 'Inicio', 't' => 'fecha'], ['n' => 'Fin', 't' => 'fecha'], ['n' => 'Días', 't' => 'numero'], ['n' => 'Avance', 't' => 'porcentaje']], $filas);
            case 'estimacion':
                $filas = [];
                for ($i = 2; $i <= 16; $i++) $filas[] = ['', '', '', '', '', "=D$i*E$i", '', "=F$i*G$i/100", "=F$i-H$i"];
                return self::plano([
                    ['n' => 'No.', 'w' => 50], ['n' => 'Concepto', 'w' => 240], ['n' => 'Unidad', 'w' => 70],
                    ['n' => 'Cantidad', 't' => 'numero', 'w' => 80], ['n' => 'P.U.', 't' => 'moneda', 'w' => 100],
                    ['n' => 'Importe', 't' => 'moneda', 'tot' => 'suma', 'w' => 110],
                    ['n' => 'Amortización %', 't' => 'porcentaje', 'w' => 100],
                    ['n' => 'Amortización', 't' => 'moneda', 'tot' => 'suma', 'w' => 110],
                    ['n' => 'Neto a pagar', 't' => 'moneda', 'tot' => 'suma', 'w' => 110],
                ], $filas);
            case 'submittals':
                return self::plano([
                    ['n' => 'No.', 'w' => 50], ['n' => 'Documento', 'w' => 240], ['n' => 'Disciplina', 'w' => 110],
                    ['n' => 'Especificación', 'w' => 140], ['n' => 'Estado', 't' => 'lista', 'op' => ['Pendiente', 'Enviado', 'Aprobado', 'Aprobado con comentarios', 'Rechazado', 'Resubmitir'], 'w' => 130],
                    ['n' => 'Fecha envío', 't' => 'fecha', 'w' => 100], ['n' => 'Fecha respuesta', 't' => 'fecha', 'w' => 110], ['n' => 'Responsable', 'w' => 120],
                ], $vacias(12, 8));
            case 'bitacora':
                return self::plano([
                    ['n' => 'Fecha', 't' => 'fecha', 'w' => 100], ['n' => 'Clima', 'w' => 90], ['n' => 'Turno', 'w' => 80],
                    ['n' => 'Personal', 't' => 'numero', 'w' => 80], ['n' => 'Actividades', 'w' => 260], ['n' => 'Materiales', 'w' => 220],
                    ['n' => 'Equipo', 'w' => 160], ['n' => 'Incidencias', 'w' => 220],
                ], $vacias(12, 8));
            case 'almacen':
                $filas = [];
                for ($i = 2; $i <= 16; $i++) $filas[] = ['', '', '', '', "=C$i-D$i", '', "=SI(E$i<F$i,\"Sí\",\"No\")", ''];
                return self::plano([
                    ['n' => 'Material', 'w' => 240], ['n' => 'Unidad', 'w' => 80],
                    ['n' => 'Entrada', 't' => 'numero', 'tot' => 'suma', 'w' => 90], ['n' => 'Salida', 't' => 'numero', 'tot' => 'suma', 'w' => 90],
                    ['n' => 'Existencia', 't' => 'numero', 'w' => 100], ['n' => 'Mínimo', 't' => 'numero', 'w' => 90],
                    ['n' => '¿Comprar?', 'w' => 100], ['n' => 'Proveedor', 'w' => 160],
                ], $filas);
            case 'calidad':
                return self::plano([
                    ['n' => 'Punto de inspección', 'w' => 260], ['n' => 'Cumple', 't' => 'lista', 'op' => ['Sí', 'No', 'N/A'], 'w' => 90],
                    ['n' => 'Observaciones', 'w' => 260], ['n' => 'Responsable', 'w' => 140], ['n' => 'Fecha', 't' => 'fecha', 'w' => 100],
                ], $vacias(15, 5));
            case 'equipo':
                $filas = [];
                for ($i = 2; $i <= 16; $i++) $filas[] = ['', '', '', "=B$i*C$i", '', '', ''];
                return self::plano([
                    ['n' => 'Equipo', 'w' => 200], ['n' => 'Horas', 't' => 'numero', 'w' => 80],
                    ['n' => 'Tarifa', 't' => 'moneda', 'w' => 100], ['n' => 'Importe', 't' => 'moneda', 'tot' => 'suma', 'w' => 110],
                    ['n' => 'Operador', 'w' => 140], ['n' => 'Fecha', 't' => 'fecha', 'w' => 100], ['n' => 'Observaciones', 'w' => 220],
                ], $filas);
            case '': case 'blanco':
                return self::vacia();
        }
        return null;
    }

    // ---------- Importar ----------

    /** Primera hoja de un .csv/.xlsx/.ods; la primera fila son los encabezados */
    public static function importar(string $ruta, string $nombreOriginal): array
    {
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if ($ext === 'sheetflow') return self::deArchivo($ruta)[0];
        if (!in_array($ext, ['csv', 'xlsx', 'ods'], true)) throw new \RuntimeException('Usa un archivo .sheetflow, .csv, .xlsx u .ods');
        return self::deMatriz(TablaDeDocumento::deHoja($ruta, $ext));
    }

    /** Datos de SheetFlow a partir de una matriz de textos: la primera fila son los encabezados; el tipo (número/texto) se deduce de cada columna */
    public static function deMatriz(array $filas): array
    {
        $filas = array_values(array_filter(array_map(fn($f) => array_map(fn($v) => trim((string)$v), array_values($f)), $filas), fn($f) => array_filter($f, fn($v) => $v !== '')));
        if (!$filas) throw new \RuntimeException('El archivo no tiene datos');
        $nc = min(self::MAX_COLS, max(array_map('count', $filas)));
        while ($nc > 1 && !array_filter(array_column($filas, $nc - 1), fn($v) => $v !== '')) $nc--;
        $enc = array_pad(array_slice(array_shift($filas), 0, $nc), $nc, '');
        $cols = [];
        foreach ($enc as $i => $n) {
            $col = array_map(fn($f) => (string)($f[$i] ?? ''), $filas);
            $llenas = array_filter($col, fn($v) => $v !== '');
            $num = $llenas && !array_filter($llenas, fn($v) => self::numero($v) === null);
            $cols[] = ['n' => $n !== '' ? $n : 'Columna ' . ($i + 1), 't' => $num ? 'numero' : 'texto'];
        }
        $rows = array_map(fn($f) => array_map(fn($i) => ($cols[$i]['t'] === 'numero' && ($n = self::numero($f[$i] ?? '')) !== null) ? (string)$n : (string)($f[$i] ?? ''), array_keys($cols)), $filas);
        return self::limpiar(['cols' => $cols, 'rows' => $rows ?: [array_fill(0, $nc, '')]]);
    }

    // ---------- Exportar ----------

    public static function csv(SheetflowHoja $h): string
    {
        $m = self::matriz($h);
        $f = fopen('php://temp', 'w+');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, $m['columnas'], ',', '"', '\\');
        foreach ($m['filas'] as $fila) fputcsv($f, $fila, ',', '"', '\\');
        if ($m['totales']) fputcsv($f, $m['totales'], ',', '"', '\\');
        rewind($f);
        return (string)stream_get_contents($f);
    }

    public static function xlsx(SheetflowHoja $h): string
    {
        $d = $h->contenido();
        $m = self::matriz($h);
        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(mb_substr(preg_replace('/[\\\\\/?*:\[\]]/u', ' ', $h->nombre) ?: 'Tabla', 0, 31));
        $Coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::class;
        $nc = count($d['cols']);
        $argb = fn($hex) => 'FF' . strtoupper(ltrim($hex, '#'));
        $formato = ['numero' => '#,##0', 'moneda' => '"$"#,##0.00', 'porcentaje' => '0"%"', 'fecha' => 'dd/mm/yyyy'];
        $alinea = ['izquierda' => 'left', 'centro' => 'center', 'derecha' => 'right'];
        foreach ($d['cols'] as $c => $col) {
            $L = $Coord::stringFromColumnIndex($c + 1);
            $hoja->setCellValue($L . '1', $col['n']);
            $hoja->getColumnDimension($L)->setAutoSize(true);
            $hoja->getStyle($L . '2:' . $L . (count($m['filas']) + 2))->getAlignment()->setHorizontal($alinea[$col['a']]);
            if (isset($formato[$col['t']])) {
                $codigo = $formato[$col['t']];
                if (in_array($col['t'], ['numero', 'porcentaje'], true) && array_filter(array_column($m['filas'], $c), fn($v) => ($n = self::numero($v)) !== null && floor($n) != $n)) {
                    $codigo = $col['t'] === 'numero' ? '#,##0.00##' : '0.00"%"';
                }
                $hoja->getStyle($L . '2:' . $L . (count($m['filas']) + 2))->getNumberFormat()->setFormatCode($codigo);
            }
        }
        $cubierta = [];
        foreach ($d['merges'] as $mg) {
            for ($rr = $mg['r0']; $rr <= $mg['r1']; $rr++) for ($cc = $mg['c0']; $cc <= $mg['c1']; $cc++) {
                if ($rr !== $mg['r0'] || $cc !== $mg['c0']) $cubierta[$rr . ',' . $cc] = true;
            }
        }
        foreach ($d['rows'] as $r => $fila) {
            if ($r >= count($m['filas'])) break;
            foreach ($fila as $c => $crudo) {
                if (isset($cubierta["$r,$c"])) continue;
                $L = $Coord::stringFromColumnIndex($c + 1) . ($r + 2);
                $v = str_starts_with(ltrim($crudo), '=') ? (string)(((array)$d['calc'])["$r,$c"] ?? '') : $crudo;
                if ($v === '') continue;
                $tipo = $d['cols'][$c]['t'];
                $n = self::numero($v);
                if ($tipo === 'fecha' && preg_match('/^\d{4}-\d\d-\d\d$/', $v)) {
                    $hoja->setCellValue($L, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($v . ' 12:00:00')));
                } elseif (in_array($tipo, ['numero', 'moneda', 'porcentaje'], true) && $n !== null) {
                    $hoja->setCellValue($L, $n);
                } else {
                    $hoja->setCellValueExplicit($L, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }
        $ult = $Coord::stringFromColumnIndex($nc);
        foreach ($d['merges'] as $mg) {
            $L0 = $Coord::stringFromColumnIndex($mg['c0'] + 1) . ($mg['r0'] + 2);
            $L1 = $Coord::stringFromColumnIndex($mg['c1'] + 1) . ($mg['r1'] + 2);
            $hoja->mergeCells($L0 . ':' . $L1);
        }
        $est = $hoja->getStyle("A1:{$ult}1");
        $est->getFont()->setBold(true)->getColor()->setARGB($argb($d['estilo']['encTxt']));
        $est->getFill()->setFillType('solid')->getStartColor()->setARGB($argb($d['estilo']['enc']));
        $est->getAlignment()->setHorizontal('center');
        $fin = count($m['filas']) + 1;
        if ($m['totales']) {
            $fin++;
            foreach ($m['totales'] as $c => $t) {
                $col = $d['cols'][$c];
                $L = $Coord::stringFromColumnIndex($c + 1) . $fin;
                $n = self::numero(((array)$d['tot'])[$c] ?? '');
                if ($t !== '' && $col['tot'] !== '' && $n !== null) $hoja->setCellValue($L, $n);
                elseif ($t !== '') $hoja->setCellValueExplicit($L, $t, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $hoja->getStyle("A$fin:$ult$fin")->getFont()->setBold(true);
            $hoja->getStyle("A$fin:$ult$fin")->getBorders()->getTop()->setBorderStyle('medium');
        }
        if ($d['estilo']['bordes']) $hoja->getStyle("A1:$ult$fin")->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFBBBBBB');
        if ($d['estilo']['zebra']) for ($r = 3; $r <= $fin - ($m['totales'] ? 1 : 0); $r += 2) $hoja->getStyle("A$r:$ult$r")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFF1F5F9');
        self::formatosXlsx($hoja, $d, $m);
        $vista = $d['vista'] ?? [];
        $fr = (int)($vista['filas'] ?? 0);
        $fc = (int)($vista['cols'] ?? 0);
        if ($fr || $fc) $hoja->freezePane($Coord::stringFromColumnIndex(min($nc, $fc + 1)) . (min(count($m['filas']), $fr) + 2));
        else $hoja->freezePane('A2');
        $tmp = tempnam(sys_get_temp_dir(), 'sf');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($tmp);
        $datos = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $datos;
    }

    /** Aplica a la hoja de Excel el formato por celda (fuente, colores, bordes, alineación), los anchos de columna y las imágenes */
    private static function formatosXlsx($hoja, array $d, array $m): void
    {
        $Coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::class;
        $argb = fn($hex) => 'FF' . strtoupper(ltrim($hex, '#'));
        $est = ['solid' => 'thin', 'dashed' => 'dashed', 'dotted' => 'dotted', 'double' => 'double'];
        foreach ($d['cols'] as $c => $col) if ($col['w']) $hoja->getColumnDimension($Coord::stringFromColumnIndex($c + 1))->setAutoSize(false)->setWidth(round($col['w'] / 7, 1));
        foreach ((array)$d['fmt'] as $k => $f) {
            [$r, $c] = array_map('intval', explode(',', $k));
            if ($r >= count($m['filas'])) continue;
            $ref = $Coord::stringFromColumnIndex($c + 1) . ($r + 2);
            $st = $hoja->getStyle($ref);
            if (!empty($f['b'])) $st->getFont()->setBold(true);
            if (!empty($f['i'])) $st->getFont()->setItalic(true);
            if (!empty($f['u'])) $st->getFont()->setUnderline('single');
            if (!empty($f['fs'])) $st->getFont()->setSize(round($f['fs'] * 0.75, 1));
            if (!empty($f['tc'])) $st->getFont()->getColor()->setARGB($argb($f['tc']));
            elseif (!empty($f['bg'])) $st->getFont()->getColor()->setARGB($argb(self::contraste($f['bg'])));
            if (!empty($f['bg'])) $st->getFill()->setFillType('solid')->getStartColor()->setARGB($argb($f['bg']));
            if (!empty($f['nf'])) {
                $codigoNf = str_ireplace(['aaaa', 'aa'], ['yyyy', 'yy'], $f['nf']);   // Excel no entiende los códigos en español
                $st->getNumberFormat()->setFormatCode($codigoNf);
            }
            if (!empty($f['al'])) $st->getAlignment()->setHorizontal(['izquierda' => 'left', 'centro' => 'center', 'derecha' => 'right'][$f['al']]);
            foreach (self::BORDES as $kb => $lado) {
                if (!isset($f[$kb])) continue;
                $b = $st->getBorders()->{'get' . ucfirst($lado)}();
                if ($f[$kb] === 'n') { $b->setBorderStyle('none'); continue; }
                [$w, $es, $co] = explode('|', $f[$kb]);
                $b->setBorderStyle($w >= 3 && $es === 'solid' ? 'medium' : $est[$es])->getColor()->setARGB($argb($co));
            }
            if (!empty($f['im']) && is_file($ruta = Imagenes::CARPETA_VPLAN . '/' . basename($f['im']))) {
                $dim = @getimagesize($ruta);
                if ($dim) {
                    $alto = 80;
                    $dr = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                    $dr->setPath($ruta)->setCoordinates($ref)->setResizeProportional(true)->setHeight($alto)->setOffsetX(2)->setOffsetY(2)->setWorksheet($hoja);
                    $hoja->getRowDimension($r + 2)->setRowHeight(max(15, $alto * 0.75 + 4));
                }
            }
        }
        // Formato condicional: en Excel se aplica como color fijo (la regla vive en SheetFlow)
        foreach (self::condicional($d, $m) as $k => $cf) {
            if ($k === '') continue;
            [$r, $c] = array_map('intval', explode(',', $k));
            if ($r >= count($m['filas'])) continue;
            $st = $hoja->getStyle($Coord::stringFromColumnIndex($c + 1) . ($r + 2));
            if (!empty($cf['bg'])) $st->getFill()->setFillType('solid')->getStartColor()->setARGB($argb($cf['bg']));
            if (!empty($cf['tc'])) $st->getFont()->getColor()->setARGB($argb($cf['tc']));
            if (!empty($cf['b'])) $st->getFont()->setBold(true);
            if (!empty($cf['grad'])) $st->getFill()->setFillType('solid')->getStartColor()->setARGB('FFDBEAFE');
        }
    }

    // ---------- Imágenes de celdas (uploads/vplan, como el Redactor) ----------

    /** Nombres de archivo de imagen que usa un contenido (JSON o HTML) */
    public static function imagenesDe($datos): array
    {
        $t = is_string($datos) ? $datos : json_encode($datos);
        preg_match_all('#/uploads/vplan/([a-f0-9]{32}\.(?:png|jpg))#', str_replace('\\/', '/', (string)$t), $m);
        return array_values(array_unique($m[1]));
    }

    /** ¿Alguna otra tabla o documento del Redactor del proyecto usa la imagen? */
    private static function imagenEnUso(string $nombre, int $proyectoId, int $excluirHoja): bool
    {
        $db = SheetflowHoja::conexion();
        $n = $db->escape_string($nombre);
        foreach (["SELECT 1 FROM sheetflow_hojas WHERE proyecto_id = $proyectoId AND id <> $excluirHoja AND datos LIKE '%$n%' LIMIT 1",
                  "SELECT 1 FROM redactor_documentos WHERE proyecto_id = $proyectoId AND contenido LIKE '%$n%' LIMIT 1"] as $q) {
            $r = $db->query($q);
            if ($r && $r->num_rows) return true;
        }
        return false;
    }

    /** Borra del disco las imágenes de $antes que ya no están en $despues ni las usa otra cosa */
    public static function limpiarImagenes(array $antes, array $despues, int $proyectoId, int $hojaId): void
    {
        foreach (array_diff($antes, $despues) as $n) {
            if (!self::imagenEnUso($n, $proyectoId, $hojaId)) Imagenes::eliminarVplan($n);
        }
    }

    // ---------- Archivo propio .sheetflow (copia de seguridad / intercambio; con su propio icono en Archivo) ----------

    public static function aArchivo(SheetflowHoja $h): string
    {
        $d = self::sinVinculo($h->contenido());
        $img = [];
        foreach (self::imagenesDe($d) as $n) {
            $ruta = Imagenes::CARPETA_VPLAN . '/' . $n;
            if (is_file($ruta) && filesize($ruta) <= 6_000_000) $img[$n] = base64_encode((string)file_get_contents($ruta));
        }
        return json_encode(['sheetflow' => 1, 'nombre' => $h->nombre, 'descripcion' => $h->descripcion, 'departamento' => $h->departamento, 'datos' => $d, 'imagenes' => $img], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Lee un .sheetflow: devuelve [datos, meta]. Las imágenes se vuelven a guardar (nombres nuevos) */
    public static function deArchivo(string $ruta): array
    {
        $j = json_decode((string)file_get_contents($ruta), true);
        if (!is_array($j) || ($j['sheetflow'] ?? 0) !== 1 || !is_array($j['datos'] ?? null)) throw new \RuntimeException('El archivo no es una tabla de SheetFlow válida');
        $mapa = [];
        foreach (array_slice((array)($j['imagenes'] ?? []), 0, 200, true) as $n => $b64) {
            if (!preg_match('/^[a-f0-9]{32}\.(png|jpg)$/', (string)$n)) continue;
            $tmp = tempnam(sys_get_temp_dir(), 'sfi');
            file_put_contents($tmp, (string)base64_decode((string)$b64, true));
            if ($nuevo = Imagenes::guardarGrande($tmp, 2400)) $mapa['/uploads/vplan/' . $n] = '/uploads/vplan/' . $nuevo;
            @unlink($tmp);
        }
        $json = strtr(json_encode($j['datos'], JSON_UNESCAPED_SLASHES), $mapa);
        return [self::normalizar(self::sinVinculo(json_decode($json, true))), ['nombre' => (string)($j['nombre'] ?? ''), 'descripcion' => (string)($j['descripcion'] ?? ''), 'departamento' => (string)($j['departamento'] ?? '')]];
    }

    /** Hoja recalculada con el programa del proyecto al día (sin guardar): la usan la vista, el PDF y el Excel */
    public static function recalcularHoja(SheetflowHoja $h): SheetflowHoja
    {
        SheetflowFormulas::setProyecto(self::datosProyecto((int)$h->proyecto_id));
        $h->datos = json_encode(self::normalizar($h->contenido()), JSON_UNESCAPED_UNICODE);
        return $h;
    }

    /** HTML de la tabla (PDF y vista de solo lectura). Todo va escapado. $rutas = las imágenes salen como ruta del disco (Dompdf) */
    public static function html(SheetflowHoja $h, string $clase = 'sf-tabla', bool $rutas = false): string
    {
        return self::tabla($h, ['clase' => $clase, 'rutas' => $rutas, 'pdf' => $rutas]) . self::graficosHtml($h, $rutas);
    }

    /** Tabla HTML para el Redactor, marcada con data-sf (se refresca sola al abrir, ver y exportar). Bordes en el orden «1px #color solid» que lee PhpWord */
    public static function htmlRedactor(SheetflowHoja $h): string
    {
        return self::tabla($h, ['redactor' => true]);
    }

    private static function tabla(SheetflowHoja $h, array $op): string
    {
        $m = self::matriz($h);
        $d = $h->contenido();
        $cond = self::condicional($d, $m);
        $e = $m['estilo'];
        $fmt = $m['fmt'];
        $red = !empty($op['redactor']);
        $esc = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
        $alin = ['izquierda' => 'left', 'centro' => 'center', 'derecha' => 'right'];
        $borde = $red ? 'border:1px #888888 solid;' : ($e['bordes'] ? 'border:1px solid #b6bfcc;' : 'border-bottom:1px solid #e2e8f0;');
        $bg = $red ? 'background-color:' : 'background:';
        $img = function (array $f) use ($op, $esc) {
            if (empty($f['im'])) return '';
            $src = !empty($op['rutas']) ? Imagenes::CARPETA_VPLAN . '/' . basename($f['im']) : $f['im'];
            if (!empty($op['rutas']) && !is_file($src)) return '';
            return '<img src="' . $esc($src) . '" style="max-width:100%;max-height:' . (!empty($op['rutas']) ? '70pt' : '9rem') . ';display:block;margin:0 auto 2pt">';
        };
        $col = '';
        if (array_filter($m['anchos'])) {
            $col = '<colgroup>' . implode('', array_map(fn($w) => '<col' . ($w ? ' style="width:' . round($w * 0.75) . 'pt"' : '') . '>', $m['anchos'])) . '</colgroup>';
        }
        $cubierta = [];
        $ancla = [];
        foreach ($m['merges'] as $mg) {
            $ancla[$mg['r0'] . ',' . $mg['c0']] = $mg;
            for ($rr = $mg['r0']; $rr <= $mg['r1']; $rr++) for ($cc = $mg['c0']; $cc <= $mg['c1']; $cc++) {
                if ($rr !== $mg['r0'] || $cc !== $mg['c0']) $cubierta[$rr . ',' . $cc] = true;
            }
        }
        $o = $red ? '<table data-sf="' . (int)$h->id . '" style="width:100%;border-collapse:collapse"><tbody>' . '<tr>'
            : '<table class="' . $esc($op['clase'] ?? 'sf-tabla') . '" style="border-collapse:collapse;width:100%">' . $col . '<thead><tr>';
        foreach ($m['columnas'] as $c => $n) {
            if (isset($cubierta["-1,$c"])) continue;
            $f = $fmt["-1,$c"] ?? [];
            $mg = $ancla["-1,$c"] ?? null;
            $span = $mg ? ' colspan="' . ($mg['c1'] - $mg['c0'] + 1) . '"' : '';
            $o .= '<th' . $span . ' style="' . $borde . 'padding:4pt 6pt;' . $bg . $e['enc'] . ';color:' . $e['encTxt'] . ';text-align:center;' . self::cssFmt($f) . '">' . $img($f) . $esc($n) . '</th>';
        }
        $o .= $red ? '</tr>' : '</tr></thead><tbody>';
        foreach ($m['filas'] as $r => $fila) {
            $o .= '<tr>';
            foreach ($fila as $c => $v) {
                if (isset($cubierta["$r,$c"])) continue;
                $f = $fmt["$r,$c"] ?? [];
                $mg = $ancla["$r,$c"] ?? null;
                $span = '';
                if ($mg) {
                    $cs = $mg['c1'] - $mg['c0'] + 1;
                    $rs = $mg['r1'] - $mg['r0'] + 1;
                    if ($cs > 1) $span .= ' colspan="' . $cs . '"';
                    if ($rs > 1) $span .= ' rowspan="' . $rs . '"';
                }
                $cero = $e['zebra'] && $r % 2 && empty($f['bg']) ? ';' . $bg . '#f1f5f9' : '';
                $cf = $cond["$r,$c"] ?? null;
                if ($cf) {
                    if (!empty($cf['bg'])) $f['bg'] = $cf['bg'];
                    if (!empty($cf['tc'])) $f['tc'] = $cf['tc'];
                    if (!empty($cf['b'])) $f['b'] = 1;
                }
                $extra = $cf && !empty($cf['grad']) ? 'background-image:' . $cf['grad'] . ';' : '';
                if (!empty($op['pdf']) && $cf && isset($cf['pct'], $cf['color'])) {
                    // Dompdf no entiende los degradados: la barra de datos se ve como un tinte del color
                    $f['bg'] = self::mezclar($cf['color'], '#ffffff', 0.78);
                }
                $o .= '<td' . $span . ' style="' . $borde . 'padding:3pt 6pt;text-align:' . $alin[$m['alinear'][$c]] . $cero . ';' . self::cssFmt($f) . $extra . '">' . $img($f) . ($v !== '' ? $esc($v) : ($red && !$img($f) ? '<br>' : '')) . '</td>';
            }
            $o .= '</tr>';
        }
        if ($m['totales']) {
            $o .= '<tr>';
            foreach ($m['totales'] as $c => $v) $o .= '<td style="' . $borde . 'border-top:2px solid #334155;padding:3pt 6pt;font-weight:bold;' . ($red ? 'background-color:#e2e8f0;' : '') . 'text-align:' . $alin[$m['alinear'][$c]] . '">' . ($v !== '' ? $esc($v) : ($red ? '<br>' : '')) . '</td>';
            $o .= '</tr>';
        }
        return $o . '</tbody></table>';
    }

    public static function pdf(SheetflowHoja $h): string
    {
        @ini_set('memory_limit', '512M');
        $ancha = count($h->contenido()['cols']) > 6;
        $esc = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
        $css = '@page{margin:1.6cm}body{font-family:DejaVu Sans,Helvetica,sans-serif;font-size:' . ($ancha ? 8 : 10) . 'pt;color:#000}h1{font-size:15pt;margin:0 0 2pt}p.sub{margin:0 0 10pt;color:#555;font-size:9pt}tr{page-break-inside:avoid}thead{display:table-header-group}';
        $sub = formatear_fecha($h->fecha, 'd/m/Y') . ($h->departamento !== '' ? ' · ' . $h->departamento : '');
        $html = '<html><head><meta charset="utf-8"><title>' . $esc($h->nombre) . '</title><style>' . $css . '</style></head><body><h1>' . $esc($h->nombre) . '</h1><p class="sub">' . $esc($sub) . '</p>' . self::html($h, 'sf-tabla', true) . '</body></html>';
        $dompdf = Pdf::preparar($html, 'letter', $ancha ? 'landscape' : 'portrait', ['isRemoteEnabled' => false, 'chroot' => realpath(Imagenes::CARPETA_VPLAN) ?: Imagenes::CARPETA_VPLAN, 'defaultFont' => 'serif']);
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() / 2 - 30, $canvas->get_height() - 22, 'Página {PAGE_NUM} de {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('helvetica'), 8, [0.4, 0.4, 0.4]);
        return $dompdf->output();
    }

    // ---------- Vínculo con vPlan ----------

    /** Crea (o actualiza, si $existente) una tabla «otro» de vPlan con los valores ya formateados de la hoja. Máx. 8 columnas */
    public static function aVplan(SheetflowHoja $h, ?VplanTabla $existente = null): VplanTabla
    {
        $m = self::matriz($h);
        $columnas = array_slice($m['columnas'], 0, 8);
        $nc = count($columnas);
        $t = $existente ?? new VplanTabla(['proyecto_id' => $h->proyecto_id, 'titulo' => mb_strtoupper(mb_substr($h->nombre, 0, 190)), 'tipo' => 'otro']);
        $t->tipo = 'otro';
        $t->columnas_json = json_encode(array_map(fn($n, $i) => mb_substr($n !== '' ? $n : 'Col. ' . ($i + 1), 0, 40), $columnas, array_keys($columnas)), JSON_UNESCAPED_UNICODE);
        $t->sheetflow_id = (int)$h->id;
        if (!$existente) $t->nombre_interno = mb_substr('SheetFlow · ' . $h->nombre, 0, 150);
        $r = $t->guardar();
        if (!$t->id) $t->id = $r['id'];
        $filas = [];
        $lista = $m['filas'];
        if ($m['totales']) $lista[] = $m['totales'];
        foreach (array_slice($lista, 0, 200) as $fila) {
            $f = [];
            foreach (array_slice($fila, 0, $nc) as $c => $v) $f['c' . $c] = mb_substr((string)$v, 0, 300);
            $filas[] = $f;
        }
        $t->guardarFilas($filas);
        return $t;
    }

    /** Tabla de vPlan que ya muestra esta hoja, o una nueva vinculada (nunca duplica) */
    public static function tablaVplanDe(SheetflowHoja $h): VplanTabla
    {
        foreach (VplanTabla::deProyecto((int)$h->proyecto_id) as $x) if ((int)$x->sheetflow_id === (int)$h->id) return $x;
        return self::aVplan($h);
    }

    /** Hoja nueva (vacía, o con la matriz dada: encabezados primero) para que otra herramienta la use como fuente */
    public static function crearHoja(int $proyectoId, string $nombre, ?array $filas = null, ?int $usuarioId = null): SheetflowHoja
    {
        $datos = $filas ? self::deMatriz($filas) : self::vacia();
        $h = new SheetflowHoja(['proyecto_id' => $proyectoId, 'nombre' => mb_substr(trim($nombre) ?: 'Tabla', 0, 150), 'fecha' => date('Y-m-d'), 'creado_por' => $usuarioId]);
        $h->datos = json_encode(self::normalizar($datos), JSON_UNESCAPED_UNICODE);
        $h->id = $h->guardar()['id'];
        return $h;
    }

    /** Pasa una tabla «otro» de vPlan con datos propios a una hoja de SheetFlow y la vincula. Los renglones propios se conservan como respaldo */
    public static function deTablaVplan(VplanTabla $t, ?int $usuarioId = null): ?SheetflowHoja
    {
        if ($t->tipo !== 'otro' || $t->sheetflow_id || $t->esPunchVista()) return null;
        $cols = $t->columnasOtro();
        if (!$cols) return null;
        $filas = [$cols];
        foreach ($t->filas() as $f) {
            $r = [];
            foreach (array_keys($cols) as $i) $r[] = (string)($f['c' . $i] ?? '');
            $filas[] = $r;
        }
        $nombre = trim((string)$t->etiqueta()) ?: 'Tabla';
        $h = self::crearHoja((int)$t->proyecto_id, $nombre, $filas, $usuarioId);   // los encabezados siempre traen datos
        $t->sheetflow_id = (int)$h->id;
        $t->guardar();
        return $h;
    }

    // ---------- Formato condicional ----------

    /** Mezcla dos colores #rrggbb: $k = 0 devuelve $a, 1 devuelve $b */
    public static function mezclar(string $a, string $b, float $k): string
    {
        $k = max(0.0, min(1.0, $k));
        $x = [];
        for ($i = 0; $i < 3; $i++) {
            $x[] = (int)round(hexdec(substr($a, 1 + $i * 2, 2)) * (1 - $k) + hexdec(substr($b, 1 + $i * 2, 2)) * $k);
        }
        return sprintf('#%02x%02x%02x', $x[0], $x[1], $x[2]);
    }

    /**
     * Estilos que impone el formato condicional: ["r,c" => ['bg'=>?, 'tc'=>?, 'b'=>?, 'grad'=>?]].
     * `grad` es un CSS de fondo (barra de datos); el resto son relleno, color de letra y negrita.
     */
    public static function condicional(array $d, array $m): array
    {
        $reglas = (array)($d['vista']['cond'] ?? []);
        if (!$reglas) return [];
        $filas = $m['filas'] ?? [];
        $out = [];
        foreach ($reglas as $r) {
            $r0 = (int)$r['r0']; $r1 = (int)$r['r1']; $c0 = (int)$r['c0']; $c1 = (int)$r['c1'];
            $tipo = (string)$r['tipo'];
            $num = $txt = [];
            for ($f = $r0; $f <= $r1; $f++) for ($c = $c0; $c <= $c1; $c++) {
                $v = (string)($filas[$f][$c] ?? '');
                $txt[$f . ',' . $c] = trim($v);
                $n = self::numero($v);
                if ($n !== null) $num[] = $n;
            }
            $min = $num ? min($num) : null;
            $max = $num ? max($num) : null;
            $a = self::numero($r['a'] ?? '');
            $b = self::numero($r['b'] ?? '');
            $cuenta = [];
            if ($tipo === 'duplicados') {
                foreach ($txt as $v) {
                    $k = mb_strtolower($v);
                    if ($k !== '') $cuenta[$k] = ($cuenta[$k] ?? 0) + 1;
                }
            }
            $aguja = mb_strtolower(trim((string)(($r['txt'] ?? '') !== '' ? $r['txt'] : ($r['a'] ?? ''))));
            $base = ['bg' => $r['fondo'], 'tc' => $r['color'], 'b' => $r['b']];
            for ($f = $r0; $f <= $r1; $f++) for ($c = $c0; $c <= $c1; $c++) {
                $k = $f . ',' . $c;
                $v = (string)($filas[$f][$c] ?? '');
                $n = self::numero($v);
                $ok = false;
                switch ($tipo) {
                    case 'mayor': $ok = $n !== null && $a !== null && $n > $a; break;
                    case 'menor': $ok = $n !== null && $a !== null && $n < $a; break;
                    case 'igual':
                        $ok = ($n !== null && $a !== null) ? $n == $a : mb_strtolower(trim($v)) === mb_strtolower(trim((string)($r['a'] ?? '')));
                        break;
                    case 'entre': $ok = $n !== null && $a !== null && $b !== null && $n >= min($a, $b) && $n <= max($a, $b); break;
                    case 'contiene': $ok = $aguja !== '' && mb_stripos($v, $aguja) !== false; break;
                    case 'duplicados': $ok = mb_strtolower(trim($v)) !== '' && ($cuenta[mb_strtolower(trim($v))] ?? 0) > 1; break;
                    case 'escala':
                        if ($n === null || $min === null || $max === $min) break;
                        $out[$k] = ['bg' => self::mezclar($r['fondo'], $r['color'], ($n - $min) / ($max - $min)), 'tc' => self::contraste(self::mezclar($r['fondo'], $r['color'], ($n - $min) / ($max - $min)))];
                        continue 2;
                    case 'barra':
                        if ($n === null || $max === null || $max <= 0) break;
                        $pct = max(2.0, min(100.0, $n / $max * 100));
                        $hex = $r['color'];
                        $out[$k] = ['grad' => 'linear-gradient(90deg,' . $hex . ' 0%,' . $hex . ' ' . round($pct, 1) . '%,transparent ' . round($pct, 1) . '%,transparent 100%)', 'pct' => round($pct, 1), 'color' => $hex];
                        continue 2;
                }
                if ($ok) $out[$k] = $base;
            }
        }
        return $out;
    }

    // ---------- Gráficos sencillos (barras o líneas) ----------

    /** Serie de datos de un gráfico a partir del rectángulo elegido: primera columna = etiquetas, resto = series */
    public static function graficoDatos(array $m, array $g): array
    {
        $filas = $m['filas'] ?? [];
        $etiquetas = [];
        $series = [];
        $cols = range((int)$g['c0'], (int)$g['c1']);
        $primera = (int)$g['c0'];
        $soloNumeros = true;
        for ($f = (int)$g['r0']; $f <= (int)$g['r1']; $f++) {
            if (self::numero((string)($filas[$f][$primera] ?? '')) === null && trim((string)($filas[$f][$primera] ?? '')) !== '') $soloNumeros = false;
        }
        $datos = [];
        foreach ($cols as $c) {
            $vals = [];
            for ($f = (int)$g['r0']; $f <= (int)$g['r1']; $f++) $vals[] = self::numero((string)($filas[$f][$c] ?? ''));
            if (count(array_filter($vals, fn($x) => $x !== null)) >= 1) $datos[$c] = $vals;
        }
        if ($soloNumeros) {   // no hay etiquetas de texto: cada renglón es una categoría
            for ($f = (int)$g['r0']; $f <= (int)$g['r1']; $f++) $etiquetas[] = 'Fila ' . ($f + 2);
        } else {
            for ($f = (int)$g['r0']; $f <= (int)$g['r1']; $f++) {
                $t = trim((string)($filas[$f][$primera] ?? ''));
                $etiquetas[] = $t !== '' ? $t : 'Fila ' . ($f + 2);
            }
            unset($datos[$primera]);
        }
        foreach ($datos as $c => $vals) $series[] = ['nombre' => (string)($m['columnas'][$c] ?? ('Col. ' . ($c + 1))), 'vals' => $vals];
        return ['tipo' => $g['tipo'], 'titulo' => (string)($g['titulo'] ?? ''), 'etiquetas' => $etiquetas, 'series' => array_slice($series, 0, 6)];
    }

    /** HTML (SVG) de los gráficos guardados en la hoja; $rutas = colores fijos para PDF */
    public static function graficosHtml(SheetflowHoja $h, bool $pdf = false): string
    {
        $d = $h->contenido();
        $gs = (array)($d['vista']['graficos'] ?? []);
        if (!$gs) return '';
        $m = self::matriz($h);
        $out = '';
        foreach ($gs as $g) {
            $dat = self::graficoDatos($m, $g);
            if (!$dat['series']) continue;
            $svg = self::svg($dat, $pdf);
            if ($pdf) {   // Dompdf dibuja el SVG como imagen, no incrustado en el HTML
                $out .= '<div style="margin-top:14pt;text-align:center"><img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" width="440" height="280" style="display:block;margin:0 auto"></div>';
            } else {
                $out .= '<figure class="sf-grafico">' . $svg . '</figure>';
            }
        }
        return $out ? '<div class="sf-graficos">' . $out . '</div>' : '';
    }

    private const COLORES_SERIE = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2'];

    /** Dibuja un gráfico de barras o de líneas en SVG (sin dependencias) */
    private static function svg(array $dat, bool $pdf = false): string
    {
        $W = $pdf ? 440 : 720; $H = 280; $ml = 54; $mr = 16; $mt = $dat['titulo'] !== '' ? 34 : 14; $mb = 42;
        $n = count($dat['etiquetas']);
        if ($n === 0) return '';
        $series = $dat['series'];
        $vals = [];
        foreach ($series as $s) foreach ($s['vals'] as $v) if ($v !== null) $vals[] = $v;
        if (!$vals) return '';
        $max = max($vals); $min = min($vals);
        if ($max === $min) { $max = $min + 1; }
        $max = max(0, $max); $min = min(0, $min);
        $rango = $max - $min ?: 1;
        $pw = $W - $ml - $mr; $ph = $H - $mt - $mb;
        $y = fn($v) => $mt + $ph - (($v - $min) / $rango) * $ph;
        $esc = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
        $s = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" width="100%" style="max-width:72rem;height:auto;font-family:sans-serif" role="img" aria-label="' . $esc($dat['titulo'] ?: 'Gráfico') . '">';
        if ($pdf) $s = str_replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" ', $s);
        $s .= '<rect x="0" y="0" width="' . $W . '" height="' . $H . '" fill="#ffffff"/>';
        if ($dat['titulo'] !== '') $s .= '<text x="' . ($W / 2) . '" y="20" text-anchor="middle" font-size="14" font-weight="bold" fill="#111">' . $esc($dat['titulo']) . '</text>';
        // ejes y guías
        for ($i = 0; $i <= 4; $i++) {
            $v = $min + $rango * $i / 4;
            $yy = $y($v);
            $s .= '<line x1="' . $ml . '" y1="' . round($yy, 1) . '" x2="' . ($W - $mr) . '" y2="' . round($yy, 1) . '" stroke="#e2e8f0" stroke-width="1"/>';
            $s .= '<text x="' . ($ml - 6) . '" y="' . round($yy + 3, 1) . '" text-anchor="end" font-size="9" fill="#555">' . $esc(self::decimales($v, 2)) . '</text>';
        }
        $s .= '<line x1="' . $ml . '" y1="' . round($y(0), 1) . '" x2="' . ($W - $mr) . '" y2="' . round($y(0), 1) . '" stroke="#94a3b8" stroke-width="1.2"/>';
        if ($dat['tipo'] === 'barra') {
            $grupo = $pw / max(1, $n);
            $ancho = max(3, $grupo * 0.7 / count($series));
            foreach ($dat['etiquetas'] as $i => $eti) {
                foreach ($series as $j => $se) {
                    $v = $se['vals'][$i] ?? null;
                    if ($v === null) continue;
                    $x = $ml + $grupo * $i + $grupo * 0.15 + $ancho * $j;
                    $y0 = $y(0); $y1 = $y($v);
                    $s .= '<rect x="' . round($x, 1) . '" y="' . round(min($y0, $y1), 1) . '" width="' . round($ancho, 1) . '" height="' . round(abs($y0 - $y1), 1) . '" fill="' . self::COLORES_SERIE[$j % 6] . '" rx="2"/>';
                }
            }
        } else {
            foreach ($series as $j => $se) {
                $pts = [];
                foreach ($se['vals'] as $i => $v) {
                    if ($v === null) continue;
                    $pts[] = round($ml + ($pw / max(1, $n - 1)) * $i, 1) . ',' . round($y($v), 1);
                }
                if (count($pts) > 1) $s .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . self::COLORES_SERIE[$j % 6] . '" stroke-width="2.4" stroke-linejoin="round"/>';
                foreach ($pts as $p) { [$px, $py] = explode(',', $p); $s .= '<circle cx="' . $px . '" cy="' . $py . '" r="3.2" fill="' . self::COLORES_SERIE[$j % 6] . '"/>'; }
            }
        }
        // etiquetas del eje X (máx. 14, giradas si no caben)
        $paso = max(1, (int)ceil($n / 14));
        foreach ($dat['etiquetas'] as $i => $eti) {
            if ($i % $paso) continue;
            $x = $dat['tipo'] === 'barra' ? $ml + ($pw / max(1, $n)) * ($i + 0.5) : $ml + ($pw / max(1, $n - 1)) * $i;
            $t = mb_substr((string)$eti, 0, 18);
            $s .= '<text x="' . round($x, 1) . '" y="' . ($H - $mb + 16) . '" text-anchor="middle" font-size="9" fill="#334155">' . $esc($t) . '</text>';
        }
        // leyenda
        $lx = $ml;
        foreach ($series as $j => $se) {
            if (count($series) < 2 && $dat['titulo'] !== '') continue;
            $s .= '<rect x="' . $lx . '" y="' . ($H - 16) . '" width="9" height="9" fill="' . self::COLORES_SERIE[$j % 6] . '"/>';
            $s .= '<text x="' . ($lx + 13) . '" y="' . ($H - 8) . '" font-size="9" fill="#334155">' . $esc(mb_substr((string)$se['nombre'], 0, 22)) . '</text>';
            $lx += 24 + mb_strlen(mb_substr((string)$se['nombre'], 0, 22)) * 5;
        }
        return $s . '</svg>';
    }

    // ---------- Fórmulas del proyecto (AVANCE, INICIO, FIN…) ----------

    /** Partidas y actividades con su avance y fechas, para las fórmulas =AVANCE("1.2.3"), =FIN(…)… */
    public static function datosProyecto(int $proyectoId): array
    {
        try {
            $p = \Model\Proyecto::find($proyectoId);
            if (!$p) return [];
            $prog = \Model\Partida::progresosDeProyecto($proyectoId);
            $hoy = new \DateTimeImmutable(date('Y-m-d'));
            $rest = function (?string $fin) use ($hoy): int {
                if (!$fin) return 0;
                try { return (int)$hoy->diff(new \DateTimeImmutable(substr((string)$fin, 0, 10)))->format('%r%a'); } catch (\Throwable $e) { return 0; }
            };
            $out = [];
            foreach ($p->getPartidasConActividades() as $g) {
                $pt = $g['partida'];
                $out[] = [
                    'codigo' => (string)$pt->codigo, 'nombre' => (string)$pt->nombre, 'tipo' => 'partida',
                    'avance' => round((float)($prog[(int)$pt->id] ?? 0), 1),
                    'inicio' => (string)($pt->fecha_inicio ?? ''), 'fin' => (string)($pt->fecha_fin ?? ''),
                    'dias_rest' => $rest($pt->fecha_fin),
                ];
                foreach ($g['actividades'] as $a) {
                    $out[] = [
                        'codigo' => (string)$a->codigo, 'nombre' => (string)$a->nombre, 'tipo' => 'actividad',
                        'avance' => round((float)$a->progreso, 1),
                        'inicio' => (string)($a->fecha_inicio ?? ''), 'fin' => (string)($a->fecha_fin ?? ''),
                        'dias_rest' => $rest($a->fecha_fin),
                    ];
                }
            }
            return $out;
        } catch (\Throwable $e) {   // sin programa o sin migraciones: las fórmulas dan #N/A
            return [];
        }
    }

    // ---------- Vínculos vivos ----------

    /** Renglones de la hoja como los lee una tabla de vPlan: claves c0..cN, máx. 200 (con la fila de totales al final) */
    public static function filasVplan(SheetflowHoja $h, int $nc): array
    {
        $m = self::matriz($h);
        $lista = $m['totales'] ? array_merge($m['filas'], [$m['totales']]) : $m['filas'];
        $out = [];
        foreach (array_slice($lista, 0, 200) as $fila) {
            $f = [];
            foreach (array_slice($fila, 0, $nc) as $c => $v) $f['c' . $c] = mb_substr((string)$v, 0, 300);
            $out[] = $f;
        }
        $d = $h->contenido();   // valor crudo (fecha ISO, 1, verde…) para los controles de captura; los totales no lo llevan
        $calc = (array)($d['calc'] ?? []);   // `limpiar()` lo deja como objeto: se lee como arreglo
        foreach ($out as $r => &$f) {
            if (!isset($d['rows'][$r]) || $r >= count($m['filas'])) continue;
            for ($c = 0; $c < $nc; $c++) {
                $x = (string)($d['rows'][$r][$c] ?? '');
                $f['v' . $c] = mb_substr(SheetflowFormulas::esFormula($x) ? (string)($calc["$r,$c"] ?? '') : $x, 0, 300);
            }
        }
        unset($f);
        // formato de SheetFlow (colores, negrita, alineación) para que la lámina y el PDF se vean como la hoja
        $fmt = $m['fmt'];
        $cnd = self::condicional($d, $m);
        foreach ($out as $r => &$f) {
            if ($r >= count($m['filas'])) break;
            for ($c = 0; $c < $nc; $c++) {
                $fc = $fmt["$r,$c"] ?? [];
                $cf = $cnd["$r,$c"] ?? null;
                if ($cf) {
                    if (!empty($cf['bg'])) $fc['bg'] = $cf['bg'];
                    if (!empty($cf['tc'])) $fc['tc'] = $cf['tc'];
                    if (!empty($cf['b'])) $fc['b'] = 1;
                    if (!empty($cf['grad']) && empty($fc['bg'])) $fc['bg'] = '#dbeafe';
                }
                $f['s' . $c] = self::cssVplan($fc, $m['alinear'][$c] ?? 'izquierda');
            }
        }
        unset($f);
        return $out;
    }

    /** CSS de una celda para vPlan: sin tamaño de letra ni bordes (la lámina escala sola); la alineación de la columna si la celda no trae la suya */
    public static function cssVplan(array $f, string $alinCol): string
    {
        unset($f['fs'], $f['im'], $f['bt'], $f['br'], $f['bb'], $f['bl']);
        $f['al'] = $f['al'] ?? $alinCol;
        return self::cssFmt($f, true);
    }

    /** CSS de los encabezados de una hoja para vPlan: ['c0' => css, …] (color del encabezado de la hoja si la celda no trae el suyo) */
    public static function estiloEncabezados(SheetflowHoja $h, int $nc): array
    {
        $m = self::matriz($h);
        $e = $m['estilo'];
        $out = [];
        for ($c = 0; $c < $nc; $c++) {
            $f = $m['fmt']["-1,$c"] ?? [];
            if (empty($f['bg']) && !empty($e['enc'])) { $f['bg'] = $e['enc']; if (empty($f['tc']) && !empty($e['encTxt'])) $f['tc'] = $e['encTxt']; }
            $out['c' . $c] = self::cssVplan($f, 'centro');
        }
        return $out;
    }

    /** Reemplaza las tablas `data-sf` de un HTML del Redactor por su versión actual (solo de este proyecto; si la hoja ya no existe, se deja como está) */
    public static function refrescarHtml(string $html, int $proyectoId): string
    {
        $html = PunchVista::refrescarHtml($html, $proyectoId);   // tablas `data-punch` del Punch list
        if (!str_contains($html, 'data-sf=')) return $html;
        return preg_replace_callback('#<table\b[^>]*\bdata-sf="(\d{1,9})"[^>]*>.*?</table>#s', function ($m) use ($proyectoId) {
            $h = SheetflowHoja::find((int)$m[1]);
            return $h && (int)$h->proyecto_id === $proyectoId ? self::htmlRedactor($h) : $m[0];
        }, $html);
    }

    /** Fila de la hoja que corresponde al renglón $i de la vista de vPlan (la fila de totales no es editable); null si no se puede */
    private static function filaReal(array $d, int $i): ?int
    {
        return $i >= 0 && $i < count($d['rows']) ? $i : null;
    }

    /** Escribe desde vPlan el valor mostrado de una celda. Devuelve un mensaje de error o null */
    public static function cambiarCelda(SheetflowHoja $h, int $fila, int $col, string $valor): ?string
    {
        $d = $h->contenido();
        $m = self::matriz($h);
        if ($col < 0 || $col >= count($d['cols'])) return 'Columna no válida';
        if ($m['totales'] && $fila === count($m['filas'])) return 'El renglón de totales se calcula en SheetFlow';
        if ($fila < 0 || $fila >= self::MAX_FILAS) return 'Renglón no válido';
        foreach ((array)($d['merges'] ?? []) as $mg) {
            if ($fila >= $mg['r0'] && $fila <= $mg['r1'] && $col >= $mg['c0'] && $col <= $mg['c1'] && ($fila !== $mg['r0'] || $col !== $mg['c0'])) {
                return 'Esa celda está combinada; edita la celda superior izquierda';
            }
        }
        while (count($d['rows']) <= $fila) $d['rows'][] = array_fill(0, count($d['cols']), '');
        if (SheetflowFormulas::esFormula($d['rows'][$fila][$col])) return 'Esa celda es una fórmula; edítala en SheetFlow';
        $tipo = $d['cols'][$col]['t'];
        $valor = trim($valor);
        if ($tipo === 'fecha' && preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})$/', $valor, $x)) $valor = (strlen($x[3]) === 2 ? '20' . $x[3] : $x[3]) . '-' . sprintf('%02d-%02d', $x[2], $x[1]);
        elseif (in_array($tipo, ['numero', 'moneda', 'porcentaje'], true) && ($n = self::numero($valor)) !== null) $valor = (string)$n;
        elseif (in_array($tipo, ['casilla', 'semaforo'], true)) $valor = self::normalizarValor($valor, $tipo);
        elseif ($tipo === 'lista' && $valor !== '' && ($op = $d['cols'][$col]['op'] ?? []) && !in_array($valor, $op, true)) return 'Elige una de las opciones de la lista';
        $d['rows'][$fila][$col] = mb_substr($valor, 0, self::MAX_CELDA);
        try {
            self::guardarDatos($h, $d);
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
        return null;
    }

    /** Quita desde vPlan el renglón $fila de la hoja. Devuelve un mensaje de error o null */
    public static function quitarFila(SheetflowHoja $h, int $fila): ?string
    {
        $d = $h->contenido();
        $m = self::matriz($h);
        if ($m['totales'] && $fila === count($m['filas'])) return 'El renglón de totales no se puede quitar';
        if (self::filaReal($d, $fila) === null) return null;   // era un renglón nuevo todavía vacío
        foreach ($d['rows'] as $f) foreach ($f as $x) if (SheetflowFormulas::esFormula($x)) return 'La tabla tiene fórmulas; quita el renglón en SheetFlow para que sus referencias se ajusten';
        array_splice($d['rows'], $fila, 1);
        if (isset($d['meta']['subactividad']['ids']) && is_array($d['meta']['subactividad']['ids'])) {
            array_splice($d['meta']['subactividad']['ids'], $fila, 1);
        }
        $d['fmt'] = self::fmtSinFila($d['fmt'] ?? [], $fila);
        $d['merges'] = self::mergesSinFila($d['merges'] ?? [], $fila);
        if (!$d['rows']) $d['rows'][] = array_fill(0, count($d['cols']), '');
        try {
            self::guardarDatos($h, $d);
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
        return null;
    }

    /** Quita una fila del mapa de formatos y desplaza las siguientes */
    public static function fmtSinFila($fmt, int $fila): array
    {
        $out = [];
        foreach ((array)$fmt as $k => $f) {
            if (!preg_match('/^(-1|\d+),(\d+)$/', (string)$k, $m)) continue;
            $r = (int)$m[1];
            if ($r === $fila) continue;
            if ($r > $fila) $r--;
            $out[$r . ',' . $m[2]] = $f;
        }
        return $out;
    }

    /** Quita una fila de las celdas combinadas y desplaza las siguientes */
    public static function mergesSinFila($merges, int $fila): array
    {
        $out = [];
        foreach ((array)$merges as $m) {
            $a = (int)($m['r0'] ?? 0);
            $b = (int)($m['r1'] ?? 0);
            if ($b < $fila || $a > $fila) {
                if ($a > $fila) { $a--; $b--; }
            } else {
                $na = $a < $fila ? $a : $fila;
                $nb = $b > $fila ? $b - 1 : $fila - 1;
                if ($nb < $na) continue;
                $a = $na;
                $b = $nb;
            }
            $m['r0'] = $a;
            $m['r1'] = $b;
            $out[] = $m;
        }
        return $out;
    }
}
