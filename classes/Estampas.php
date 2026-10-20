<?php

namespace Classes;

/**
 * Estampas inteligentes: bloques reutilizables cuyas etiquetas (prefijo + campo + sufijo) se llenan solas con los datos
 * del proyecto. Este archivo trae el catálogo de campos, las piezas puras (formato, semáforo, variables {campo}), el
 * saneado de la configuración y el dibujo en HTML (para la hoja del vPlan, el PDF y la vista previa).
 */
class Estampas
{
    public const STATUS = ['no_iniciada' => 'No iniciada', 'en_proceso' => 'En proceso', 'completada' => 'Completada'];

    public const GRUPOS = [
        'documento' => 'Documento y lámina',
        'programa' => 'Programa de obra',
        'actividad' => 'Actividad elegida',
        'obra' => 'Control de obra',
    ];

    /** clave => [grupo, etiqueta, tipo (texto|fecha|num|pct), ejemplo]. Los campos «act_*» necesitan una actividad. */
    public const CAMPOS = [
        'proyecto' => ['documento', 'Nombre del proyecto', 'texto', 'Torre Aurora'],
        'codigo' => ['documento', 'Código del proyecto', 'texto', 'DEMO'],
        'cliente' => ['documento', 'Cliente (empresa)', 'texto', 'Grupo Aurora'],
        'contratista' => ['documento', 'Contratista', 'texto', 'Constructora Horizonte'],
        'ubicacion' => ['documento', 'Ubicación', 'texto', 'Zona Corporativa'],
        'trabajo' => ['documento', 'Número de trabajo', 'texto', 'JOB-001'],
        'titulo' => ['documento', 'Título de la lámina', 'texto', 'Planta nivel 7'],
        'lamina' => ['documento', 'Número de lámina', 'num', '3'],
        'total' => ['documento', 'Total de láminas', 'num', '12'],
        'escala' => ['documento', 'Escala', 'texto', '1:100'],
        'revision' => ['documento', 'Revisión', 'texto', 'Rev. 02'],
        'fecha' => ['documento', 'Fecha de impresión (hoy)', 'fecha', ''],
        'corte' => ['documento', 'Fecha de corte', 'fecha', ''],
        'autor' => ['documento', 'Autor (quien imprime)', 'texto', 'Ana Pérez'],
        'inicio' => ['programa', 'Inicio del proyecto', 'fecha', ''],
        'fin' => ['programa', 'Fin del proyecto', 'fecha', ''],
        'duracion' => ['programa', 'Duración total (días)', 'num', '406'],
        'dias_transcurridos' => ['programa', 'Días transcurridos', 'num', '120'],
        'dias_restantes' => ['programa', 'Días restantes', 'num', '286'],
        'semana' => ['programa', 'Semana actual de la obra', 'num', '18'],
        'avance_real' => ['programa', 'Avance real (%)', 'pct', '31.0'],
        'avance_prog' => ['programa', 'Avance programado (%)', 'pct', '34.8'],
        'desvio' => ['programa', 'Desvío (puntos)', 'pct', '-3.9'],
        'spi' => ['programa', 'Índice SPI', 'num', '0.89'],
        'actividades_total' => ['programa', 'Actividades del programa', 'num', '52'],
        'actividades_hechas' => ['programa', 'Actividades terminadas', 'num', '14'],
        'actividades_atrasadas' => ['programa', 'Actividades atrasadas', 'num', '3'],
        'act_codigo' => ['actividad', 'Código de la actividad', 'texto', '3.4'],
        'act_nombre' => ['actividad', 'Nombre de la actividad', 'texto', 'Colado de losa nivel 9'],
        'act_partida' => ['actividad', 'Partida de la actividad', 'texto', 'Estructura'],
        'act_inicio' => ['actividad', 'Inicio de la actividad', 'fecha', ''],
        'act_fin' => ['actividad', 'Fin de la actividad', 'fecha', ''],
        'act_avance' => ['actividad', 'Avance de la actividad (%)', 'pct', '65'],
        'act_status' => ['actividad', 'Estatus de la actividad', 'texto', 'En proceso'],
        'act_responsable' => ['actividad', 'Responsable', 'texto', 'Jorge Salinas'],
        'act_prioridad' => ['actividad', 'Prioridad', 'texto', 'Alta'],
        'act_critica' => ['actividad', 'Ruta crítica (Sí/No)', 'texto', 'Sí'],
        'act_holgura' => ['actividad', 'Holgura (días)', 'num', '2'],
        'act_dias' => ['actividad', 'Días hábiles para terminar', 'num', '4'],
        'act_restriccion' => ['actividad', 'Restricción activa', 'texto', 'Falta submittal'],
        'restr_activas' => ['obra', 'Restricciones activas', 'num', '6'],
        'restr_vencidas' => ['obra', 'Restricciones vencidas', 'num', '1'],
        'oc_abiertas' => ['obra', 'Órdenes de cambio abiertas', 'num', '4'],
        'retiros_pendientes' => ['obra', 'Retiros pendientes (instalación temporal)', 'num', '3'],
        'retiros_vencidos' => ['obra', 'Retiros vencidos', 'num', '1'],
        'personal_ultimo' => ['obra', 'Personal en el último reporte', 'num', '42'],
        'reporte_ultimo' => ['obra', 'Fecha del último reporte', 'fecha', ''],
        'fecha_dia' => ['obra', 'Fecha de las actividades del día', 'fecha', ''],
    ];

    /** Semáforo por defecto de cada campo numérico: [mejor cuando es, umbral verde, umbral rojo] */
    public const SEMAFORO = [
        'spi' => ['mayor', 0.95, 0.85], 'desvio' => ['mayor', -2, -8], 'actividades_atrasadas' => ['menor', 0, 3], 'restr_activas' => ['menor', 0, 3],
        'restr_vencidas' => ['menor', 0, 1], 'oc_abiertas' => ['menor', 2, 5], 'retiros_pendientes' => ['menor', 0, 2], 'retiros_vencidos' => ['menor', 0, 1],
        'dias_restantes' => ['mayor', 30, 10], 'act_holgura' => ['mayor', 3, 0], 'act_dias' => ['mayor', 3, 1], 'act_avance' => ['mayor', 100, 50],
    ];

    /** Campos que se pueden mostrar en cada post-it de «Actividades del día» */
    public const CAMPOS_POSTIT = ['codigo' => 'Código', 'partida' => 'Partida', 'responsable' => 'Responsable', 'avance' => 'Avance', 'dias' => 'Días para terminar',
        'restriccion' => 'Restricción', 'status' => 'Estatus', 'inicio' => 'Inicio', 'fin' => 'Fin', 'prioridad' => 'Prioridad'];

    public const FORMATOS_FECHA = ['' => '15/10/2026', 'corto' => '15 oct', 'largo' => '15 de octubre de 2026', 'completo' => 'jueves 15 de octubre de 2026', 'mes' => 'octubre 2026', 'iso' => '2026-10-15', 'texto' => 'jueves, octubre 15, 2026'];

    // ------------------------------------------------------------------ piezas puras

    private const MESES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    private const DIAS = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    /** Valor → texto según el tipo del campo y su formato (fecha: '', corto, largo, completo, mes, iso · números: decimales «0», «1», «2») */
    public static function formatear($valor, string $tipo, string $formato = ''): string
    {
        if ($valor === null || $valor === '') return '';
        if ($tipo === 'fecha') {
            $t = strtotime((string)$valor);
            if (!$t) return (string)$valor;
            [$d, $m, $y, $w] = [(int)date('j', $t), (int)date('n', $t), date('Y', $t), (int)date('N', $t)];
            return match ($formato) {
                'iso' => date('Y-m-d', $t), 'texto' => self::DIAS[$w] . ', ' . self::MESES[$m] . sprintf(' %02d, %s', $d, $y), 'corto' => sprintf('%02d %s', $d, mb_substr(self::MESES[$m], 0, 3)),
                'largo' => "$d de " . self::MESES[$m] . " de $y", 'completo' => self::DIAS[$w] . " $d de " . self::MESES[$m] . " de $y",
                'mes' => self::MESES[$m] . " $y", default => date('d/m/Y', $t),
            };
        }
        if ($tipo === 'num' || $tipo === 'pct') {
            if (!is_numeric($valor)) return (string)$valor;
            $dec = preg_match('/^[0-3]$/', $formato) ? (int)$formato : ($tipo === 'pct' ? 1 : (floor((float)$valor) == (float)$valor ? 0 : 2));
            $txt = number_format((float)$valor, $dec, '.', '');
            return $dec > 0 && preg_match('/\.0+$/', $txt) && $formato === '' ? preg_replace('/\.0+$/', '', $txt) : $txt;
        }
        return (string)$valor;
    }

    /** Color del semáforo para un valor: «mayor» = mientras más alto mejor (verde si ≥ verde, rojo si < rojo); «menor» al revés */
    public static function semaforo(float $v, string $modo, float $verde, float $rojo): string
    {
        if ($modo === 'menor') return $v <= $verde ? 'verde' : ($v > $rojo ? 'rojo' : 'amarillo');
        return $v >= $verde ? 'verde' : ($v < $rojo ? 'rojo' : 'amarillo');
    }

    public const COLOR_SEMAFORO = ['verde' => '#16a34a', 'amarillo' => '#eab308', 'rojo' => '#dc2626'];

    /** Reemplaza {campo} y {campo:formato} con lo que devuelva $valor(clave) (null = se deja el texto tal cual). Sin DB: se prueba aparte. */
    public static function reemplazar(string $texto, callable $valor): string
    {
        return preg_replace_callback('/\{([a-z_]+)(?::([a-z0-9]+))?\}/', function ($m) use ($valor) {
            $r = $valor($m[1], $m[2] ?? '');
            return $r === null ? $m[0] : $r;
        }, $texto);
    }

    /** Texto con variables resueltas desde el contexto. $soloNuevas = true deja sin tocar las variables antiguas (las resuelve el Editor en el navegador) */
    public static function texto(string $texto, EstampaContexto $ctx, bool $soloNuevas = false): string
    {
        $viejas = ['proyecto', 'titulo', 'fecha', 'autor', 'lamina', 'total', 'escala'];
        return self::reemplazar($texto, function ($k, $f) use ($ctx, $soloNuevas, $viejas) {
            if (!isset(self::CAMPOS[$k])) return null;
            if ($soloNuevas && $f === '' && in_array($k, $viejas, true)) return null;   // «{fecha}» lo llena el Editor; «{fecha:corto}» ya no
            return self::formatear($ctx->campo($k), self::CAMPOS[$k][2], $f);
        });
    }

    /** Valor ya formateado de una etiqueta de campo ('' si no hay dato) */
    public static function valorEtiqueta(array $e, EstampaContexto $ctx): string
    {
        $k = (string)($e['campo'] ?? '');
        if (!isset(self::CAMPOS[$k])) return '';
        return self::formatear($ctx->campo($k, (int)($e['actividad_id'] ?? 0)), self::CAMPOS[$k][2], (string)($e['formato'] ?? ''));
    }

    // ------------------------------------------------------------------ saneado

    private static function hex($c): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c) ? strtolower($c) : '';
    }

    private static function fechaOk($f): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$f) && strtotime((string)$f) ? (string)$f : '';
    }

    /** Deja de una configuración solo lo válido (campos del catálogo, colores, rangos, partidas y actividades del proyecto) */
    public static function limpiarConfig(array $c, int $proyectoId): array
    {
        $txt = fn($v, $max) => mb_substr(str_replace("\r", '', trim((string)$v)), 0, $max);
        $num = fn($v, $min, $max, $def) => is_numeric($v) ? max($min, min($max, (float)$v)) : $def;
        $partidas = [];
        $acts = [];
        foreach (\Model\Partida::consultarSQL("SELECT * FROM partidas WHERE proyecto_id = " . (int)$proyectoId) as $p) $partidas[(int)$p->id] = true;
        foreach ((new EstampaContexto(\Model\Proyecto::find($proyectoId)))->actividades() as $id => $a) $acts[$id] = true;
        $etiquetas = [];
        $limpiaEt = function ($e) use ($txt, $num, $partidas, $acts) {
            if (!is_array($e)) return null;
            $t = in_array($e['t'] ?? '', ['campo', 'texto', 'semaforo', 'actividades'], true) ? $e['t'] : 'campo';
            $o = ['t' => $t, 'tamano' => (float)($e['tamano'] ?? 0) > 0 ? $num($e['tamano'], 3, 60, 0) : 0, 'negrita' => !empty($e['negrita']), 'color' => self::hex($e['color'] ?? ''),
                'alinear' => in_array($e['alinear'] ?? '', ['izquierda', 'centro', 'derecha'], true) ? $e['alinear'] : ''];
            if ($t === 'texto') {
                $o['texto'] = $txt($e['texto'] ?? '', 500);
            } elseif ($t === 'actividades') {
                $campos = array_values(array_intersect(array_keys(self::CAMPOS_POSTIT), array_map('strval', (array)($e['campos'] ?? []))));
                $o += ['fecha_modo' => ($e['fecha_modo'] ?? '') === 'fija' ? 'fija' : 'corte', 'fecha' => self::fechaOk($e['fecha'] ?? ''),
                    'partidas' => array_values(array_filter(array_map('intval', (array)($e['partidas'] ?? [])), fn($i) => isset($partidas[$i]))),
                    'responsable' => (int)($e['responsable'] ?? 0), 'max' => (int)$num($e['max'] ?? 8, 1, 24, 8), 'columnas' => (int)$num($e['columnas'] ?? 2, 1, 4, 2),
                    'campos' => $campos, 'solo_pendientes' => !empty($e['solo_pendientes']),
                    'color_por' => in_array($e['color_por'] ?? '', ['partida', 'estado', 'amarillo'], true) ? $e['color_por'] : 'amarillo'];
            } else {
                $k = (string)($e['campo'] ?? '');
                if (!isset(self::CAMPOS[$k])) return null;
                $act = (int)($e['actividad_id'] ?? 0);
                $o += ['campo' => $k, 'prefijo' => $txt($e['prefijo'] ?? '', 80), 'sufijo' => $txt($e['sufijo'] ?? '', 80), 'formato' => preg_match('/^[a-z0-9]{0,10}$/', (string)($e['formato'] ?? '')) ? (string)($e['formato'] ?? '') : '',
                    'actividad_id' => isset($acts[$act]) ? $act : 0];
                if ($t === 'semaforo') {
                    $d = self::SEMAFORO[$k] ?? ['mayor', 80, 50];
                    $o += ['modo' => ($e['modo'] ?? $d[0]) === 'menor' ? 'menor' : 'mayor', 'verde' => $num($e['verde'] ?? $d[1], -1000000, 1000000, $d[1]), 'rojo' => $num($e['rojo'] ?? $d[2], -1000000, 1000000, $d[2])];
                }
            }
            return $o;
        };
        foreach (array_slice((array)($c['etiquetas'] ?? []), 0, 30) as $e) {
            if ($o = $limpiaEt($e)) $etiquetas[] = $o;
        }
        $elementos = self::limpiarElementos((array)($c['elementos'] ?? []), $limpiaEt, $partidas, $txt, $num);
        return [
            'modo' => ($c['modo'] ?? '') === 'lienzo' ? 'lienzo' : 'etiquetas', 'elementos' => $elementos, 'lp' => !empty($c['lp']),
            'disposicion' => in_array($c['disposicion'] ?? '', ['lineas', 'tabla', 'banda'], true) ? $c['disposicion'] : 'lineas',
            'tamano' => $num($c['tamano'] ?? 9, 3, 40, 9), 'borde_grosor' => $num($c['borde_grosor'] ?? 1.4, 0.2, 10, 1.4), 'fondo' => self::hex($c['fondo'] ?? ''), 'borde' => self::hex($c['borde'] ?? ''),
            'titulo' => $txt($c['titulo'] ?? '', 200), 'titulo_fondo' => self::hex($c['titulo_fondo'] ?? '') ?: '#5b82b4', 'titulo_color' => self::hex($c['titulo_color'] ?? '') ?: '#ffffff',
            'datos' => ['revision' => $txt($c['datos']['revision'] ?? '', 40), 'escala' => $txt($c['datos']['escala'] ?? '', 40)],
            'w' => $num($c['w'] ?? 26, 5, 100, 26), 'h' => $num($c['h'] ?? 16, 3, 100, 16),
            'etiquetas' => $etiquetas,
        ];
    }

    // ------------------------------------------------------------------ lienzo (editor gráfico)

    public const TIPOS_GRAFICOS = ['linea', 'rect', 'elipse', 'rombo'];
    public const TIPOS_ELEMENTO = ['linea', 'rect', 'elipse', 'rombo', 'texto', 'campo', 'semaforo', 'partida_dia', 'actividades_dia', 'logo'];
    /** Unidades de la hoja por 1 % de ancho / de alto del bloque (hoja 17x11: 1224.57 unidades de ancho) */
    private const U_X = 12.2457;
    private const U_Y = 7.906;

    /** Tamaño de un bloque en unidades de la hoja (1 unidad = 1 pt en una lámina de 432 mm de ancho): [ancho, alto] */
    public static function dimensiones(array $cfg): array
    {
        return [(float)($cfg['w'] ?? 26) * self::U_X, (float)($cfg['h'] ?? 16) * self::U_Y];
    }

    /** Elementos del lienzo: posición y tamaño en % del bloque; solo valores válidos */
    private static function limpiarElementos(array $lista, callable $limpiaEt, array $partidas, callable $txt, callable $num): array
    {
        $out = [];
        foreach (array_slice($lista, 0, 80) as $e) {
            if (!is_array($e) || !in_array($e['tipo'] ?? '', self::TIPOS_ELEMENTO, true)) continue;
            $tipo = $e['tipo'];
            $o = ['tipo' => $tipo, 'x' => $num($e['x'] ?? 0, 0, 100, 0), 'y' => $num($e['y'] ?? 0, 0, 100, 0), 'w' => $num($e['w'] ?? 10, 0, 100, 10), 'h' => $num($e['h'] ?? 10, 0, 100, 10)];
            $o['w'] = min($o['w'], 100 - $o['x']);
            $o['h'] = min($o['h'], 100 - $o['y']);
            $o['nombre'] = $txt($e['nombre'] ?? '', 60);
            $o['oculto'] = !empty($e['oculto']);
            $o['bloqueado'] = !empty($e['bloqueado']);
            $estilo = [
                'tamano' => (float)($e['tamano'] ?? 0) > 0 ? $num($e['tamano'], 3, 80, 0) : 0, 'negrita' => !empty($e['negrita']), 'color' => self::hex($e['color'] ?? ''),
                'alinear' => in_array($e['alinear'] ?? '', ['izquierda', 'centro', 'derecha'], true) ? $e['alinear'] : '',
                'valign' => in_array($e['valign'] ?? '', ['arriba', 'medio', 'abajo'], true) ? $e['valign'] : 'arriba',
                'pad' => $num($e['pad'] ?? 2, 0, 30, 2), 'padv' => $num($e['padv'] ?? 0.8, 0, 30, 0.8),
            ];
            if ($tipo === 'linea') {
                $o += ['color' => self::hex($e['color'] ?? '') ?: '#000000', 'grosor' => $num($e['grosor'] ?? 1, 0.2, 20, 1), 'inv' => !empty($e['inv']),
                    'estilo' => in_array($e['estilo'] ?? '', ['guiones', 'puntos'], true) ? $e['estilo'] : 'solida'];
            } elseif (in_array($tipo, ['rect', 'elipse', 'rombo'], true)) {
                $o += ['relleno' => self::hex($e['relleno'] ?? ''), 'relleno2' => self::hex($e['relleno2'] ?? '') ?: '#ffffff', 'degradado' => in_array($e['degradado'] ?? '', ['v', 'h', 'd'], true) ? $e['degradado'] : '',
                    'borde' => self::hex($e['borde'] ?? ''), 'grosor' => $num($e['grosor'] ?? 1, 0.2, 20, 1), 'radio' => $num($e['radio'] ?? 0, 0, 60, 0), 'sombra' => !empty($e['sombra'])];
            } elseif ($tipo === 'texto') {
                $o += $estilo + ['prefijo' => $txt($e['prefijo'] ?? '', 120), 'valor' => $txt($e['valor'] ?? '', 300), 'tamano_pre' => $num($e['tamano_pre'] ?? 0, 0, 80, 0), 'salto' => !empty($e['salto'])];
            } elseif ($tipo === 'campo' || $tipo === 'semaforo') {
                $et = $limpiaEt(['t' => $tipo] + $e);
                if (!$et) continue;
                unset($et['t']);
                $o += $et + $estilo;
            } elseif ($tipo === 'partida_dia') {
                $pid = (int)($e['partida_id'] ?? 0);
                $o += $estilo + ['tamano_pre' => $num($e['tamano_pre'] ?? 0, 0, 80, 0), 'prefijo' => $txt($e['prefijo'] ?? '', 80), 'modo' => ($e['modo'] ?? '') === 'fija' && isset($partidas[$pid]) ? 'fija' : 'dia', 'partida_id' => isset($partidas[$pid]) ? $pid : 0,
                    'varias' => ($e['varias'] ?? '') === 'unir' ? 'unir' : 'todas', 'fecha_modo' => ($e['fecha_modo'] ?? '') === 'fija' ? 'fija' : 'corte', 'fecha' => self::fechaOk($e['fecha'] ?? '')];
            } elseif ($tipo === 'actividades_dia') {
                $o += $estilo + ['fecha_modo' => ($e['fecha_modo'] ?? '') === 'fija' ? 'fija' : 'corte', 'fecha' => self::fechaOk($e['fecha'] ?? ''),
                    'partidas' => array_values(array_filter(array_map('intval', (array)($e['partidas'] ?? [])), fn($i) => isset($partidas[$i]))),
                    'responsable' => (int)($e['responsable'] ?? 0), 'max' => (int)$num($e['max'] ?? 4, 1, 24, 4), 'solo_pendientes' => !empty($e['solo_pendientes']),
                    'encabezado' => !isset($e['encabezado']) || !empty($e['encabezado']), 'codigo_partida' => ($e['codigo_partida'] ?? '') === 'auto' ? 'auto' : !empty($e['codigo_partida']), 'tamano_enc' => $num($e['tamano_enc'] ?? 0, 0, 40, 0), 'rombos' => !isset($e['rombos']) || !empty($e['rombos']),
                    'tit_actividad' => $txt($e['tit_actividad'] ?? 'ACTIVIDAD:', 60), 'tit_dias' => $txt($e['tit_dias'] ?? 'DÍAS PARA TERMINAR:', 60)];
            } elseif ($tipo === 'logo') {
                $o += ['fuente' => in_array($e['fuente'] ?? '', ['proyecto', 'contratista'], true) ? $e['fuente'] : 'compania', 'alinear' => in_array($e['alinear'] ?? '', ['izquierda', 'centro', 'derecha'], true) ? $e['alinear'] : 'izquierda'];
            }
            $out[] = $o;
        }
        return $out;
    }

    /** Figuras y líneas del lienzo como SVG (coordenadas en unidades de la hoja; se estira al bloque) */
    private static function lienzoSvg(array $cfg, array $els, float $W, float $H): string
    {
        $f = fn($n) => round((float)$n, 2);
        $defs = $body = '';
        foreach ($els as $i => $e) {
            $tipo = $e['tipo'] ?? '';
            if (!in_array($tipo, self::TIPOS_GRAFICOS, true)) continue;
            [$x, $y, $w, $h] = [$e['x'] * $W / 100, $e['y'] * $H / 100, $e['w'] * $W / 100, $e['h'] * $H / 100];
            if ($tipo === 'linea') {
                [$y1, $y2] = !empty($e['inv']) ? [$y + $h, $y] : [$y, $y + $h];
                $dash = ($e['estilo'] ?? '') === 'guiones' ? ' stroke-dasharray="' . $f($e['grosor'] * 4) . ' ' . $f($e['grosor'] * 2.5) . '"' : (($e['estilo'] ?? '') === 'puntos' ? ' stroke-dasharray="' . $f($e['grosor']) . ' ' . $f($e['grosor'] * 1.8) . '"' : '');
                $body .= '<line x1="' . $f($x) . '" y1="' . $f($y1) . '" x2="' . $f($x + $w) . '" y2="' . $f($y2) . '" stroke="' . $e['color'] . '" stroke-width="' . $f($e['grosor']) . '"' . $dash . '/>';
                continue;
            }
            $forma = function (string $attrs) use ($tipo, $x, $y, $w, $h, $e, $f) {
                return match ($tipo) {
                    'elipse' => '<ellipse cx="' . $f($x + $w / 2) . '" cy="' . $f($y + $h / 2) . '" rx="' . $f($w / 2) . '" ry="' . $f($h / 2) . '" ' . $attrs . '/>',
                    'rombo' => '<polygon points="' . $f($x + $w / 2) . ',' . $f($y) . ' ' . $f($x + $w) . ',' . $f($y + $h / 2) . ' ' . $f($x + $w / 2) . ',' . $f($y + $h) . ' ' . $f($x) . ',' . $f($y + $h / 2) . '" ' . $attrs . '/>',
                    default => '<rect x="' . $f($x) . '" y="' . $f($y) . '" width="' . $f($w) . '" height="' . $f($h) . '" rx="' . $f($e['radio']) . '" ' . $attrs . '/>',
                };
            };
            if (!empty($e['sombra'])) {
                $d = max(1.5, min($w, $h) * 0.05);
                $sh = $tipo === 'elipse' ? '<ellipse cx="' . $f($x + $w / 2 + $d) . '" cy="' . $f($y + $h / 2 + $d) . '" rx="' . $f($w / 2) . '" ry="' . $f($h / 2) . '" fill="#000000" fill-opacity="0.28"/>'
                    : ($tipo === 'rombo' ? '<polygon points="' . $f($x + $w / 2 + $d) . ',' . $f($y + $d) . ' ' . $f($x + $w + $d) . ',' . $f($y + $h / 2 + $d) . ' ' . $f($x + $w / 2 + $d) . ',' . $f($y + $h + $d) . ' ' . $f($x + $d) . ',' . $f($y + $h / 2 + $d) . '" fill="#000000" fill-opacity="0.28"/>'
                    : '<rect x="' . $f($x + $d) . '" y="' . $f($y + $d) . '" width="' . $f($w) . '" height="' . $f($h) . '" rx="' . $f($e['radio']) . '" fill="#000000" fill-opacity="0.28"/>');
                $body .= $sh;
            }
            $fill = 'none';
            if ($e['relleno'] !== '' && $e['degradado'] !== '') {
                [$gx, $gy] = ['v' => [0, 1], 'h' => [1, 0], 'd' => [1, 1]][$e['degradado']];
                $defs .= '<linearGradient id="g' . $i . '" x1="0" y1="0" x2="' . $gx . '" y2="' . $gy . '"><stop offset="0" stop-color="' . $e['relleno'] . '"/><stop offset="1" stop-color="' . $e['relleno2'] . '"/></linearGradient>';
                $fill = 'url(#g' . $i . ')';
            } elseif ($e['relleno'] !== '') {
                $fill = $e['relleno'];
            }
            $body .= $forma('fill="' . $fill . '"' . ($e['borde'] !== '' ? ' stroke="' . $e['borde'] . '" stroke-width="' . $f($e['grosor']) . '"' : ''));
        }
        if (!empty($cfg['borde'])) {
            $g = (float)($cfg['borde_grosor'] ?? 1.4);
            $body .= '<rect x="' . $f($g / 2) . '" y="' . $f($g / 2) . '" width="' . $f($W - $g) . '" height="' . $f($H - $g) . '" fill="none" stroke="' . $cfg['borde'] . '" stroke-width="' . $f($g) . '"/>';
        }
        if ($body === '') return '';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $f($W) . '" height="' . $f($H) . '" viewBox="0 0 ' . $f($W) . ' ' . $f($H) . '" preserveAspectRatio="none">' . ($defs ? '<defs>' . $defs . '</defs>' : '') . $body . '</svg>';
        return '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" style="position:absolute;left:0;top:0;width:100%;height:100%" alt="">';
    }

    private const ROMBO = ['verde' => '#059669', 'amarillo' => '#d97706', 'rojo' => '#dc2626'];

    /** Rombos de semáforo de una actividad: finalizada, en proceso y con restricción (pueden ir varios) */
    private static function rombos(array $a, callable $u, float $b): string
    {
        $r = '';
        $mk = fn($c) => '<span style="color:' . self::ROMBO[$c] . ';font-size:' . $u($b * 2.4) . ';line-height:0.8">&#9670;</span>';
        if ($a['hecha']) $r .= $mk('verde');
        elseif ($a['avance'] > 0 || $a['status'] === 'en_proceso') $r .= $mk('amarillo');
        if ($a['restricciones']) $r .= $mk('rojo');
        return $r === '' ? '' : $r . ' ';
    }

    private static function actividadesDia(array $e, EstampaContexto $ctx, callable $u, float $base): string
    {
        $b = !empty($e['tamano']) ? (float)$e['tamano'] : $base;
        $d = $ctx->actividadesDelDia($e);
        $te = !empty($e['tamano_enc']) ? (float)$e['tamano_enc'] : $b * 0.7;
        $h = '';
        if (!empty($e['encabezado'])) {
            $h .= '<table style="width:100%;border-collapse:collapse"><tr><td style="font-size:' . $u($te) . ';padding:0">' . self::esc($e['tit_actividad'] ?? 'ACTIVIDAD:') . '</td><td style="font-size:' . $u($te) . ';text-align:right;padding:0">' . self::esc($e['tit_dias'] ?? 'DÍAS PARA TERMINAR:') . '</td></tr></table>';
        }
        if (!$d['filas']) {
            return $h . '<div style="font-size:' . $u($b * 0.85) . ';color:#555">Sin actividades para el ' . self::esc(self::formatear($d['fecha'], 'fecha')) . '.</div>';
        }
        $conCodigo = ($e['codigo_partida'] ?? false) === 'auto' ? isset($ctx->opts['partida']) && (int)$ctx->opts['partida'] === 0 : !empty($e['codigo_partida']);
        $h .= '<table style="width:100%;border-collapse:collapse">';
        foreach ($d['filas'] as $a) {
            $cod = $conCodigo && $a['partida_codigo'] !== '' ? '<span style="font-family:DejaVu Sans Mono,monospace;font-size:' . $u($b * 0.78) . ';font-weight:bold;background:#e5e7eb">&nbsp;' . self::esc($a['partida_codigo']) . '&nbsp;</span> ' : '';
            $h .= '<tr><td style="font-size:' . $u($b) . ';line-height:1.15;padding:' . $u(0.8) . ' ' . $u(2.8) . ' ' . $u(1.7) . ' ' . $u(2.8) . ';border-bottom:' . $u(0.5) . ' dotted #888;vertical-align:middle;' . (!empty($e['negrita']) ? 'font-weight:bold;' : '') . '">'
                . (($e['rombos'] ?? true) ? self::rombos($a, $u, $b) : '') . $cod . self::esc($a['nombre']) . '</td>'
                . '<td style="width:' . $u(39) . ';text-align:center;font-weight:bold;font-size:' . $u($b * 1.56) . ';border-bottom:' . $u(0.5) . ' dotted #888;vertical-align:middle">' . (int)$a['dias'] . '</td></tr>';
        }
        if ($d['hay_mas']) $h .= '<tr><td colspan="2" style="font-size:' . $u($b * 0.7) . ';color:#555;text-align:right">+' . (int)$d['hay_mas'] . ' más</td></tr>';
        return $h . '</table>';
    }

    private static function partidaDia(array $e, EstampaContexto $ctx): string
    {
        if (($e['modo'] ?? 'dia') === 'fija') return $ctx->partidaNombre((int)($e['partida_id'] ?? 0));
        if (isset($ctx->opts['partida'])) return (int)$ctx->opts['partida'] > 0 ? $ctx->partidaNombre((int)$ctx->opts['partida']) : 'TODAS LAS PARTIDAS';   // tarjeta del Last Planner
        $d = $ctx->actividadesDelDia(['partidas' => [], 'responsable' => 0, 'solo_pendientes' => false, 'max' => 24] + $e);
        $n = [];
        foreach ($d['todas'] as $a) $n[$a['partida']] = true;
        $n = array_keys($n);
        if (!$n) return '';
        return count($n) === 1 || ($e['varias'] ?? 'todas') === 'unir' ? implode(', ', $n) : 'TODAS LAS PARTIDAS';
    }

    /** Contenido de texto de un elemento dentro de su caja (alineación horizontal y vertical) */
    private static function cajaTexto(array $e, string $html, callable $u, float $base, float $alto = 0): string
    {
        $al = ['izquierda' => 'left', 'centro' => 'center', 'derecha' => 'right'][$e['alinear'] ?? ''] ?? 'left';
        $va = ['arriba' => 'top', 'medio' => 'middle', 'abajo' => 'bottom'][$e['valign'] ?? 'arriba'] ?? 'top';
        $st = 'font-size:' . $u(!empty($e['tamano']) ? (float)$e['tamano'] : $base) . ';text-align:' . $al . ';line-height:1.15;' . (!empty($e['negrita']) ? 'font-weight:bold;' : '') . (!empty($e['color']) ? 'color:' . $e['color'] . ';' : '');
        $tam = !empty($e['tamano']) ? (float)$e['tamano'] : $base;
        $padv = (float)($e['padv'] ?? 0.8);
        $padTop = $padv;
        if ($alto > $tam * 4 && $va !== 'top') {   // Dompdf no centra en celdas altas: se calcula el margen con las líneas que tendrá el texto
            $lineas = substr_count($html, '<br') + substr_count($html, '<div') + 1;
            $libre = max(0, $alto - $lineas * $tam * 1.18);
            $padTop = max($padv, $va === 'middle' ? $libre / 2 : $libre - $padv);
            $va = 'top';
        }
        return '<div style="display:table;width:100%;' . ($alto > 0 ? 'height:' . $u($alto) . ';' : 'height:100%;') . '"><div style="display:table-cell;vertical-align:' . $va . ';padding:' . $u($padTop) . ' ' . $u((float)($e['pad'] ?? 2)) . ' ' . $u($padv) . ';' . $st . '">' . $html . '</div></div>';
    }

    private static function renderLienzo(array $cfg, EstampaContexto $ctx, callable $u): string
    {
        $base = (float)($cfg['tamano'] ?? 9);
        $els = array_filter((array)($cfg['elementos'] ?? []), fn($e) => empty($e['oculto']));   // capas ocultas: no se dibujan (las claves conservan el orden)
        $ctx->cfgDia = [];
        foreach ($els as $e) if (in_array($e['tipo'] ?? '', ['actividades_dia', 'partida_dia'], true) && ($e['fecha_modo'] ?? '') !== null) { $ctx->cfgDia = $e; if ($e['tipo'] === 'actividades_dia') break; }
        $W = (float)$cfg['w'] * self::U_X;
        $H = (float)$cfg['h'] * self::U_Y;
        $out = '<div class="est est-lienzo" style="position:relative;width:100%;height:100%;box-sizing:border-box;overflow:hidden;' . (!empty($cfg['fondo']) ? 'background:' . $cfg['fondo'] . ';' : '') . '">';
        $sinBorde = ['borde' => ''] + $cfg;
        $tanda = [];   // figuras seguidas: se dibujan juntas y respetan el orden de las capas
        foreach ($els as $i => $e) {
            $tipo = $e['tipo'] ?? '';
            if (in_array($tipo, self::TIPOS_GRAFICOS, true)) { $tanda[$i] = $e; continue; }
            if ($tanda) { $out .= self::lienzoSvg($sinBorde, $tanda, $W, $H); $tanda = []; }
            $in = '';
            $hb = $e['h'] / 100 * $H;
            if ($tipo === 'texto') {
                $pre = (string)($e['prefijo'] ?? '');
                $in = self::cajaTexto($e, ($pre !== '' ? (!empty($e['salto']) ? '<div style="font-weight:normal;font-size:' . $u(!empty($e['tamano_pre']) ? (float)$e['tamano_pre'] : $base * 0.7) . '">' . self::esc(self::texto($pre, $ctx)) . '</div>'
                    : '<span style="font-weight:normal;font-size:' . $u(!empty($e['tamano_pre']) ? (float)$e['tamano_pre'] : (!empty($e['tamano']) ? (float)$e['tamano'] : $base)) . '">' . self::esc(self::texto($pre, $ctx)) . '</span>' . ((string)($e['valor'] ?? '') !== '' ? ' ' : '')) : '')
                    . nl2br(self::esc(self::texto((string)($e['valor'] ?? ''), $ctx))), $u, $base, $hb);
            } elseif ($tipo === 'campo') {
                $v = self::valorEtiqueta($e, $ctx);
                $pre = self::texto((string)($e['prefijo'] ?? ''), $ctx);
                $in = self::cajaTexto($e, ($pre !== '' ? self::esc($pre) . ' ' : '') . ($v === '' ? '—' : self::esc($v)) . self::esc(self::texto((string)($e['sufijo'] ?? ''), $ctx)), $u, $base, $hb);
            } elseif ($tipo === 'semaforo') {
                [, $h] = self::celdaSemaforo($e, $ctx, $u, $base, $hb);
                $in = self::cajaTexto($e, self::esc(self::texto((string)($e['prefijo'] ?? ''), $ctx)) . ' ' . $h . self::esc(self::texto((string)($e['sufijo'] ?? ''), $ctx)), $u, $base, $hb);
            } elseif ($tipo === 'partida_dia') {
                $n = self::partidaDia($e, $ctx);
                $pre = (string)($e['prefijo'] ?? '');
                $in = self::cajaTexto($e, ($pre !== '' ? '<div style="font-size:' . $u(!empty($e['tamano_pre']) ? (float)$e['tamano_pre'] : $base * 0.62) . ';font-weight:normal">' . self::esc($pre) . '</div>' : '') . '<span style="font-weight:bold">' . self::esc($n === '' ? '—' : $n) . '</span>', $u, $base, $hb);
            } elseif ($tipo === 'actividades_dia') {
                $in = '<div style="padding:' . $u((float)($e['padv'] ?? 0.8)) . ' ' . $u((float)($e['pad'] ?? 2)) . '">' . self::actividadesDia($e, $ctx, $u, $base) . '</div>';
            } elseif ($tipo === 'logo') {
                $img = $ctx->logo((string)($e['fuente'] ?? 'compania'), $u('1') !== '' && str_ends_with($u('1'), 'pt'));
                if ($img) {
                    $pos = ['izquierda' => 'left', 'centro' => 'center', 'derecha' => 'right'][$e['alinear'] ?? 'izquierda'] ?? 'left';
                    $in = '<div style="width:100%;height:100%;background-image:url(' . $img . ');background-repeat:no-repeat;background-size:contain;background-position:' . $pos . ' center"></div>';
                }
            }
            if ($in === '') continue;
            $out .= '<div style="position:absolute;box-sizing:border-box;overflow:hidden;left:' . round($e['x'], 3) . '%;top:' . round($e['y'], 3) . '%;width:' . round($e['w'], 3) . '%;height:' . round($e['h'], 3) . '%">' . $in . '</div>';
        }
        if ($tanda) $out .= self::lienzoSvg($sinBorde, $tanda, $W, $H);
        $out .= self::lienzoSvg(['borde' => $cfg['borde'] ?? ''] + $cfg, [], $W, $H);   // el marco de la estampa va encima
        return $out . '</div>';
    }

    // ------------------------------------------------------------------ dibujo

    /** Unidades de tamaño: 'cq' = hoja del vPlan (cqw), 'pt' = PDF (pt × $K), 'px' = vista previa (px × $K) */
    public static function unidad(string $modo, float $K = 1.0): callable
    {
        return match ($modo) {
            'pt' => fn($n) => round($n * $K, 2) . 'pt',
            'px' => fn($n) => round($n * $K, 2) . 'px',
            default => fn($n) => round($n / 1224.57 * 100, 4) . 'cqw',
        };
    }

    private static function esc($t): string
    {
        return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
    }

    /** Color de fondo claro y de borde de un post-it según su partida, su estado o el amarillo clásico */
    private static function colorPostit(string $por, array $a): array
    {
        if ($por === 'partida' && preg_match('/^#[0-9a-fA-F]{6}$/', $a['color'])) {
            [$r, $g, $b] = [hexdec(substr($a['color'], 1, 2)), hexdec(substr($a['color'], 3, 2)), hexdec(substr($a['color'], 5, 2))];
            $mez = fn($v) => (int)round($v + (255 - $v) * 0.72);
            return [sprintf('#%02x%02x%02x', $mez($r), $mez($g), $mez($b)), $a['color']];
        }
        if ($por === 'estado') {
            return $a['hecha'] ? ['#bbf7d0', '#16a34a'] : ($a['restricciones'] ? ['#fecaca', '#dc2626'] : ($a['avance'] > 0 ? ['#fde68a', '#d97706'] : ['#e5e7eb', '#6b7280']));
        }
        return ['#fff59d', '#d4b800'];
    }

    /** Post-its de «Actividades del día» (tabla de N columnas: se dibuja igual en la hoja y en Dompdf) */
    private static function postits(array $e, EstampaContexto $ctx, callable $u, float $base): string
    {
        if (!empty($e['tamano'])) $base = (float)$e['tamano'];
        $d = $ctx->actividadesDelDia($e);
        $n = max(1, (int)($e['columnas'] ?? 2));
        $campos = $e['campos'] ?: ['responsable', 'avance', 'restriccion'];
        if (!$d['filas']) return '<div style="font-size:' . $u($base) . ';padding:' . $u(3) . ';color:#555">Sin actividades programadas para el ' . self::esc(self::formatear($d['fecha'], 'fecha')) . '.</div>';
        $celdas = [];
        foreach ($d['filas'] as $a) {
            [$bg, $bd] = self::colorPostit($e['color_por'] ?? 'amarillo', $a);
            $h = '<div style="font-size:' . $u($base * 0.78) . ';color:#444;font-weight:bold">' . self::esc(mb_strtoupper(trim($a['partida_codigo'] . ' ' . $a['partida'])))
                . ($a['restricciones'] ? ' <span style="color:#dc2626">&#9670;</span>' : '') . ($a['hecha'] ? ' <span style="color:#16a34a">&#9670;</span>' : '') . '</div>';
            $h .= '<div style="font-size:' . $u($base * 1.05) . ';font-weight:bold;padding:' . $u(1) . ' 0">' . self::esc(trim(($a['codigo'] !== '' && in_array('codigo', $campos, true) ? $a['codigo'] . ' · ' : '') . $a['nombre'])) . '</div>';
            foreach ($campos as $c) {
                if ($c === 'codigo' || $c === 'partida') continue;
                $v = match ($c) {
                    'responsable' => $a['responsable'], 'avance' => $a['avance'] . ' %', 'dias' => (string)$a['dias'], 'restriccion' => $a['restriccion'],
                    'status' => self::STATUS[$a['status']] ?? $a['status'], 'inicio' => self::formatear($a['inicio'], 'fecha', 'corto'), 'fin' => self::formatear($a['fin'], 'fecha', 'corto'),
                    'prioridad' => ucfirst($a['prioridad']), default => '',
                };
                if ($v === '' || $v === null) continue;
                $h .= '<div style="font-size:' . $u($base * 0.85) . '"><span style="color:#555">' . self::esc(self::CAMPOS_POSTIT[$c]) . ':</span> ' . self::esc($v) . '</div>';
            }
            $celdas[] = '<td style="width:' . round(100 / $n, 3) . '%;height:' . $u($base * 5.6) . ';vertical-align:top;background:' . $bg . ';border:' . $u(0.8) . ' solid ' . $bd . ';border-left:' . $u(3) . ' solid ' . $bd . ';padding:' . $u(3) . ' ' . $u(4) . '">' . $h . '</td>';
        }
        while (count($celdas) % $n) $celdas[] = '<td style="border:0"></td>';
        $filas = array_map(fn($f) => '<tr>' . implode('', $f) . '</tr>', array_chunk($celdas, $n));
        $mas = $d['hay_mas'] ? '<div style="font-size:' . $u($base * 0.8) . ';color:#555;padding:' . $u(2) . '">+' . (int)$d['hay_mas'] . ' actividad(es) más</div>' : '';
        return '<table style="width:100%;border-collapse:separate;border-spacing:' . $u(3) . '">' . implode('', $filas) . '</table>' . $mas;
    }

    /** Estilo en línea de una etiqueta (tamaño, negrita, color y alineación) */
    private static function estilo(array $e, callable $u, float $base): string
    {
        $al = ['izquierda' => 'left', 'centro' => 'center', 'derecha' => 'right'][$e['alinear'] ?? ''] ?? '';
        return 'font-size:' . $u(!empty($e['tamano']) ? (float)$e['tamano'] : $base) . ';' . (!empty($e['negrita']) ? 'font-weight:bold;' : '')
            . (!empty($e['color']) ? 'color:' . $e['color'] . ';' : '') . ($al ? 'text-align:' . $al . ';' : '');
    }

    /** Punto de color del semáforo + valor */
    private static function celdaSemaforo(array $e, EstampaContexto $ctx, callable $u, float $base): array
    {
        $raw = $ctx->campo($e['campo'], (int)($e['actividad_id'] ?? 0));
        $val = self::valorEtiqueta($e, $ctx);
        if (!is_numeric($raw)) return ['', $val === '' ? '—' : $val];
        $col = self::COLOR_SEMAFORO[self::semaforo((float)$raw, $e['modo'] ?? 'mayor', (float)($e['verde'] ?? 0), (float)($e['rojo'] ?? 0))];
        $tam = $u((!empty($e['tamano']) ? (float)$e['tamano'] : $base) * 1.05);
        return [$col, '<span style="display:inline-block;width:' . $tam . ';height:' . $tam . ';border-radius:50%;background:' . $col . ';vertical-align:middle;margin-right:' . $u(2) . '"></span>' . self::esc($val)];
    }

    /**
     * HTML de una estampa. $cfg ya saneada, $ctx con los datos, $u la función de unidades (ver unidad()).
     * Se compone con tablas y estilos en línea para que Dompdf (PDF) y el navegador la dibujen igual.
     */
    public static function render(array $cfg, EstampaContexto $ctx, callable $u): string
    {
        if (($cfg['modo'] ?? '') === 'lienzo') return self::renderLienzo($cfg, $ctx, $u);
        $base = (float)($cfg['tamano'] ?? 9);
        $disp = $cfg['disposicion'] ?? 'lineas';
        $et = (array)($cfg['etiquetas'] ?? []);
        $ctx->cfgDia = [];
        foreach ($et as $x) if (($x['t'] ?? '') === 'actividades') { $ctx->cfgDia = $x; break; }
        $caja = 'width:100%;height:100%;box-sizing:border-box;overflow:hidden;' . (!empty($cfg['fondo']) ? 'background:' . $cfg['fondo'] . ';' : '')
            . (!empty($cfg['borde']) ? 'border:' . $u(0.9) . ' solid ' . $cfg['borde'] . ';' : '');
        $pad = $u(3);
        $out = '<div class="est est-' . self::esc($disp) . '" style="' . $caja . '">';
        if (($cfg['titulo'] ?? '') !== '') {
            $out .= '<div style="background:' . self::esc($cfg['titulo_fondo'] ?? '#5b82b4') . ';color:' . self::esc($cfg['titulo_color'] ?? '#fff') . ';font-weight:bold;text-align:center;font-size:' . $u($base * 1.1) . ';padding:' . $u(2.5) . ' ' . $pad . '">'
                . self::esc(self::texto($cfg['titulo'], $ctx)) . '</div>';
        }
        $filas = [];   // [prefijo, contenido HTML, estilo, ancho completo?]
        foreach ($et as $e) {
            $t = $e['t'] ?? 'campo';
            $st = self::estilo($e, $u, $base);
            if ($t === 'texto') {
                $filas[] = ['', self::esc(self::texto((string)($e['texto'] ?? ''), $ctx)), $st, true];
            } elseif ($t === 'actividades') {
                $filas[] = ['', self::postits($e, $ctx, $u, $base), '', true];
            } elseif ($t === 'semaforo') {
                [, $h] = self::celdaSemaforo($e, $ctx, $u, $base);
                $filas[] = [self::esc(self::texto((string)($e['prefijo'] ?? ''), $ctx)), $h . ((string)($e['sufijo'] ?? '') !== '' ? ' ' . self::esc(self::texto($e['sufijo'], $ctx)) : ''), $st, false];
            } else {
                $v = self::valorEtiqueta($e, $ctx);
                $filas[] = [self::esc(self::texto((string)($e['prefijo'] ?? ''), $ctx)), ($v === '' ? '—' : self::esc($v)) . ((string)($e['sufijo'] ?? '') !== '' ? ' ' . self::esc(self::texto($e['sufijo'], $ctx)) : ''), $st, false];
            }
        }
        if ($disp === 'tabla') {
            $out .= '<table style="width:100%;border-collapse:collapse">';
            foreach ($filas as [$pre, $h, $st, $completa]) {
                if ($completa) { $out .= '<tr><td colspan="2" style="' . $st . 'padding:' . $pad . ';border-top:' . $u(0.6) . ' solid #999">' . $h . '</td></tr>'; continue; }
                $out .= '<tr><th style="width:38%;text-align:left;font-size:' . $u($base) . ';background:#dbe5f1;color:#111;padding:' . $u(2) . ' ' . $pad . ';border:' . $u(0.6) . ' solid #999">' . $pre . '</th>'
                    . '<td style="' . $st . 'padding:' . $u(2) . ' ' . $pad . ';border:' . $u(0.6) . ' solid #999">' . $h . '</td></tr>';
            }
            $out .= '</table>';
        } elseif ($disp === 'banda') {
            $out .= '<table style="width:100%;border-collapse:collapse"><tr>';
            foreach ($filas as [$pre, $h, $st, $completa]) {
                $out .= '<td style="vertical-align:top;text-align:center;padding:' . $u(2) . ' ' . $pad . ';border-right:' . $u(0.6) . ' solid #999;' . $st . '">'
                    . ($pre !== '' ? '<div style="font-size:' . $u($base * 0.8) . ';color:#555;font-weight:normal">' . $pre . '</div>' : '') . $h . '</td>';
            }
            $out .= '</tr></table>';
        } else {
            foreach ($filas as [$pre, $h, $st, $completa]) {
                $out .= '<div style="padding:' . $u(1.5) . ' ' . $pad . ';' . $st . '">' . ($pre !== '' ? '<span style="font-weight:normal">' . $pre . ' </span>' : '') . $h . '</div>';
            }
        }
        return $out . '</div>';
    }

    /** Estampa guardada → HTML. $modo: 'cq' | 'pt' | 'px'. $opts: corte, actividad, autor… (ver EstampaContexto) */
    public static function renderGuardada(\Model\Estampa $est, \Model\Proyecto $proyecto, ?\Model\VplanLamina $lamina, array $opts, string $modo = 'cq', float $K = 1.0): string
    {
        static $contextos = [];   // un contexto por (proyecto, lámina, opciones): varios bloques de una lámina no repiten consultas
        $cfg = $est->config();
        $o = $opts + ['revision' => $cfg['datos']['revision'] ?? '', 'escala' => $cfg['datos']['escala'] ?? ''];
        $k = md5(json_encode([(int)$proyecto->id, $lamina ? (int)$lamina->id : 0, $o]));
        $ctx = $contextos[$k] ??= new EstampaContexto($proyecto, $lamina, $o);
        return self::render($cfg, $ctx, self::unidad($modo, $K));
    }

    // ------------------------------------------------------------------ plantillas

    private static function c(string $campo, string $pre = '', string $suf = '', array $extra = []): array
    {
        return ['t' => 'campo', 'campo' => $campo, 'prefijo' => $pre, 'sufijo' => $suf] + $extra;
    }

    /** Encabezado del MSR (A4 horizontal, 802 x 111.9 pt) con las mismas medidas que el encabezado de siempre */
    public static function encabezadoMsr(): array
    {
        $W = 802.0;
        $H = 111.9;
        $X = fn($pt) => round($pt / $W * 100, 3);
        $Y = fn($pt) => round($pt / $H * 100, 3);
        $caja = fn($x, $y, $w, $h, $relleno = '', $g = 0.4) => ['tipo' => 'rect', 'x' => $X($x), 'y' => $Y($y), 'w' => $X($w), 'h' => $Y($h), 'relleno' => $relleno, 'borde' => '#000000', 'grosor' => $g];
        $tx = fn($x, $y, $w, $h, $val, $extra = []) => $extra + ['tipo' => 'texto', 'x' => $X($x), 'y' => $Y($y), 'w' => $X($w), 'h' => $Y($h), 'prefijo' => '', 'valor' => $val, 'tamano' => 5.6, 'valign' => 'arriba', 'pad' => 3, 'padv' => 1.1];
        $campo = fn($x, $y, $w, $h, $c, $extra = []) => $extra + ['tipo' => 'campo', 'x' => $X($x), 'y' => $Y($y), 'w' => $X($w), 'h' => $Y($h), 'campo' => $c, 'prefijo' => '', 'sufijo' => '', 'formato' => '', 'actividad_id' => 0, 'tamano' => 5.6, 'negrita' => true, 'valign' => 'arriba', 'pad' => 3, 'padv' => 1.1];
        $fila = 10.92;
        $els = [
            ['tipo' => 'logo', 'x' => 0, 'y' => $Y(9), 'w' => $X(130), 'h' => $Y(38), 'fuente' => 'contratista', 'alinear' => 'izquierda'],
            $tx(182, 0, 243, 56, "MSR -MATERIAL STATUS REPORT\nREPORTE DEL STATUS DE LOS MATERIALES", ['tamano' => 8, 'negrita' => true, 'pad' => 0]),
            $caja(425, 0, 200.5, 56, '', 0.8),
            ['tipo' => 'logo', 'x' => $X(470), 'y' => $Y(10), 'w' => $X(110), 'h' => $Y(36), 'fuente' => 'compania', 'alinear' => 'centro'],
            $caja(632.6, 0, 169.4, $fila),
            $campo(632.6, 0, 169.4, $fila, 'fecha', ['prefijo' => 'FECHA:', 'formato' => 'texto', 'alinear' => 'derecha']),
        ];
        foreach ([['CLIENTE:', 'contratista'], ['PROYECTO:', 'proyecto'], ['JOB No.', 'trabajo'], ['LOCALIZACIÓN:', 'ubicacion']] as $i => [$et, $c]) {
            $y = $fila * ($i + 1);
            $els[] = $caja(632.6, $y, 50.4, $fila);
            $els[] = $caja(683, $y, 119, $fila);
            $els[] = $tx(632.6, $y, 50.4, $fila, $et);
            $els[] = $campo(683, $y, 119, $fila, $c);
        }
        $els[] = $tx(425, 60, 150, 9, 'STATUS SUBMITTAL', ['negrita' => true, 'pad' => 0]);
        foreach ([['A', 'Aprobado', '#92D050'], ['AN', 'Aprobado con anotaciones', '#C5E384'], ['P', 'En revisión', '#FFEB9C'], ['RA', 'Resubmitar para aprobación', '#F79646']] as $i => [$c, $d, $col]) {
            $y = 69.6 + 10.6 * $i;
            $els[] = $caja(425, $y, 33, 10.6, $col);
            $els[] = $tx(425, $y, 33, 10.6, $c, ['negrita' => true, 'alinear' => 'centro']);
            $els[] = $caja(458, $y, 167.6, 10.6);
            $els[] = $tx(458, $y, 167.6, 10.6, $d, ['tamano' => 5.4]);
        }
        $els[] = $caja(632.6, 90.8, 169.4, 10.6);
        $els[] = $tx(632.6, 90.8, 169.4, 10.6, 'Identificación tiempo de entrega', ['negrita' => true, 'tamano' => 5.4]);
        $els[] = $caja(632.6, 101.4, 33, 10.6, '#FF99FF');
        $els[] = $caja(665.6, 101.4, 136.4, 10.6);
        $els[] = $tx(665.6, 101.4, 136.4, 10.6, 'Tiempo de entrega de Material Crítico', ['tamano' => 5.4]);
        return ['modo' => 'lienzo', 'tamano' => 5.6, 'fondo' => '', 'borde' => '', 'w' => round($W / self::U_X, 3), 'h' => round($H / self::U_Y, 3), 'lp' => false, 'etiquetas' => [], 'elementos' => $els];
    }

    /** El post-it de Last Planner (7 x 7 cm en una lámina de 432 x 279 mm) con las medidas del post-it de siempre */
    public static function postItLastPlanner(): array
    {
        $ln = fn($x, $y, $w, $h) => ['tipo' => 'linea', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'color' => '#000000', 'grosor' => 1.4];
        $m = ['pad' => 4.25, 'padv' => 4.2];
        $rombo = fn($y, $col) => ['tipo' => 'rombo', 'x' => 57.4, 'y' => $y - 2.1, 'w' => 4.2, 'h' => 4.2, 'relleno' => $col, 'borde' => '', 'grosor' => 0.2];
        $leyenda = fn($y, $t) => ['tipo' => 'texto', 'x' => 62.4, 'y' => $y - 1.5, 'w' => 37.6, 'h' => 6, 'prefijo' => '', 'valor' => $t, 'tamano' => 4.4, 'valign' => 'medio', 'pad' => 0, 'padv' => 0];
        return ['modo' => 'lienzo', 'tamano' => 6.4, 'fondo' => '#ffffff', 'borde' => '#000000', 'borde_grosor' => 1.4, 'w' => 16.43, 'h' => 25.45, 'lp' => false, 'etiquetas' => [], 'elementos' => [
            $ln(0, 17.9, 100, 0), $ln(55, 0, 0, 17.9), $ln(0, 76.6, 100, 0), $ln(55, 76.6, 0, 23.4),
            $m + ['tipo' => 'partida_dia', 'x' => 0, 'y' => 0, 'w' => 55, 'h' => 17.9, 'prefijo' => 'PARTIDA:', 'tamano' => 6.6, 'tamano_pre' => 4.5, 'negrita' => true, 'varias' => 'todas', 'alinear' => 'izquierda', 'valign' => 'arriba'],
            $m + ['tipo' => 'texto', 'x' => 55, 'y' => 0, 'w' => 45, 'h' => 17.9, 'prefijo' => 'FECHA:', 'valor' => '{fecha_dia}', 'salto' => true, 'tamano' => 10, 'tamano_pre' => 4.5, 'negrita' => true, 'valign' => 'arriba'],
            $m + ['tipo' => 'actividades_dia', 'x' => 0, 'y' => 17.9, 'w' => 100, 'h' => 58.7, 'max' => 4, 'tamano' => 6.4, 'tamano_enc' => 4.5, 'encabezado' => true, 'codigo_partida' => 'auto', 'rombos' => true],
            $m + ['tipo' => 'texto', 'x' => 0, 'y' => 76.6, 'w' => 55, 'h' => 8, 'prefijo' => 'COMPAÑÍA:', 'valor' => '', 'tamano' => 4.5, 'tamano_pre' => 4.5, 'valign' => 'arriba'],
            ['tipo' => 'logo', 'x' => 3.0, 'y' => 81.9, 'w' => 43, 'h' => 11.4, 'fuente' => 'compania', 'alinear' => 'izquierda'],
            $rombo(85.3, '#059669'), $leyenda(85.3, 'Finalizada'), $rombo(91.0, '#d97706'), $leyenda(91.0, 'En proceso'), $rombo(96.7, '#dc2626'), $leyenda(96.7, 'Con restricción'),
        ]];
    }

    /** Estampas de ejemplo que se pueden copiar a un proyecto: clave => config (se sanean al guardar) */
    public static function plantillas(): array
    {
        $sem = fn($campo, $pre, $suf = '', $x = []) => ['t' => 'semaforo', 'campo' => $campo, 'prefijo' => $pre, 'sufijo' => $suf] + $x;
        return [
            'cajetin' => ['nombre' => 'Cajetín de plano', 'config' => ['disposicion' => 'tabla', 'tamano' => 8, 'fondo' => '#ffffff', 'borde' => '#222222', 'titulo' => '{proyecto}', 'w' => 26, 'h' => 20, 'datos' => ['revision' => 'Rev. 00', 'escala' => '1:100'],
                'etiquetas' => [self::c('titulo', 'Plano'), self::c('escala', 'Escala'), self::c('revision', 'Revisión'), self::c('fecha', 'Fecha', '', ['formato' => 'corto']), self::c('autor', 'Elaboró'),
                    self::c('lamina', 'Lámina', ' de {total}')]]],
            'actividad' => ['nombre' => 'Sello de estatus de actividad', 'config' => ['disposicion' => 'lineas', 'tamano' => 9, 'fondo' => '#fffbe6', 'borde' => '#d97706', 'titulo' => 'ACTIVIDAD', 'titulo_fondo' => '#d97706', 'w' => 24, 'h' => 12,
                'etiquetas' => [self::c('act_codigo', '', '', ['negrita' => true]), self::c('act_nombre', '', '', ['negrita' => true, 'tamano' => 10]), self::c('act_avance', 'Avance: ', ' %'), self::c('act_status', 'Estatus: '),
                    self::c('act_responsable', 'Responsable: '), $sem('act_dias', 'Días hábiles restantes ', '')]]],
            'restriccion' => ['nombre' => 'Etiqueta de restricción', 'config' => ['disposicion' => 'lineas', 'tamano' => 9, 'fondo' => '#fef2f2', 'borde' => '#dc2626', 'titulo' => 'RESTRICCIÓN', 'titulo_fondo' => '#dc2626', 'w' => 24, 'h' => 12,
                'etiquetas' => [self::c('act_nombre', '', '', ['negrita' => true]), self::c('act_restriccion', 'Frena: '), self::c('act_fin', 'Debía terminar: ', '', ['formato' => 'corto']), $sem('restr_activas', 'Restricciones activas: ', '', ['modo' => 'menor'])]]],
            'pie' => ['nombre' => 'Pie de reporte con corte y SPI', 'config' => ['disposicion' => 'banda', 'tamano' => 8, 'fondo' => '#f1f5f9', 'borde' => '#64748b', 'w' => 70, 'h' => 6,
                'etiquetas' => [self::c('corte', 'Corte', '', ['formato' => 'largo']), self::c('avance_real', 'Avance real', ' %'), self::c('avance_prog', 'Programado', ' %'), $sem('spi', 'SPI', '', ['negrita' => true]),
                    self::c('semana', 'Semana'), self::c('dias_restantes', 'Días restantes')]]],
            'semaforo' => ['nombre' => 'Semáforo de avance', 'config' => ['disposicion' => 'tabla', 'tamano' => 9, 'fondo' => '#ffffff', 'borde' => '#334155', 'titulo' => 'SEMÁFORO DE LA OBRA', 'titulo_fondo' => '#334155', 'w' => 28, 'h' => 20,
                'etiquetas' => [$sem('spi', 'Índice SPI'), $sem('desvio', 'Desvío', ' pp'), $sem('actividades_atrasadas', 'Atrasadas'), $sem('restr_activas', 'Restricciones'), $sem('oc_abiertas', 'Órdenes de cambio'), $sem('retiros_vencidos', 'Retiros vencidos')]]],
            'lastplanner' => ['nombre' => 'Last Planner · Post-its del día', 'config' => ['disposicion' => 'lineas', 'tamano' => 9, 'fondo' => '#ffffff', 'borde' => '#334155', 'titulo' => 'LAST PLANNER · {fecha_dia:completo}', 'titulo_fondo' => '#0f766e', 'w' => 64, 'h' => 62,
                'etiquetas' => [['t' => 'actividades', 'fecha_modo' => 'corte', 'max' => 8, 'columnas' => 2, 'tamano' => 15, 'campos' => ['responsable', 'avance', 'dias', 'restriccion'], 'color_por' => 'partida', 'solo_pendientes' => false],
                    ['t' => 'texto', 'texto' => '◆ rojo = con restricción · ◆ verde = terminada', 'tamano' => 7]]]],
            'lastplanner_post' => ['nombre' => 'Last Planner · Post-it 7 x 7 cm', 'config' => self::postItLastPlanner()],
            'msr_encabezado' => ['nombre' => 'Encabezado de MSR', 'config' => self::encabezadoMsr()],
        ];
    }
}
