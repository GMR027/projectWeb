<?php

namespace Classes;

use Model\Partida;
use Model\Proyecto;
use Model\Restriccion;
use Model\VplanBloque;
use Model\VplanLamina;

/**
 * Datos de una lámina de vPlan. La lámina es un lienzo de bloques: cada bloque de tareas lista actividades de una o varias
 * partidas (todas o solo algunas), con recovery por actividad, restricciones numeradas en toda la lámina y tablas adicionales.
 */
class Vplan
{
    public static function estado($a): string
    {
        if ($a->status === 'completada' || (int)$a->progreso >= 100) return 'completo';
        return (int)$a->progreso > 0 ? 'proceso' : 'espera';
    }

    /** Color de la partida en el programa de obra (o el azul de referencia) y un color de texto legible sobre él */
    public static function colorTabla(?string $hex): array
    {
        $hex = $hex && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : '#5b82b4';
        [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return [$hex, $lum > 0.6 ? '#000000' : '#ffffff'];
    }

    /** Cache por petición: partida => actividades, para no repetir consultas entre bloques */
    private static array $actividadesDe = [];

    private static function actividadesDePartida(Partida $p): array
    {
        return self::$actividadesDe[(int)$p->id] ??= $p->getActividades();
    }

    /** Fuentes de un bloque de tareas equivalentes a la configuración antigua de la lámina (una partida + selección) */
    public static function fuentesAntiguas(VplanLamina $lamina): ?array
    {
        if (!$lamina->partida_id) return null;
        $sel = $lamina->seleccion();
        return [['partida' => (int)$lamina->partida_id, 'actividades' => $sel ?: null]];
    }

    /**
     * Qué muestra un bloque de tareas: grupos por partida con sus actividades. Numeración (SEQ):
     *  - 'continuo' (por defecto): 1..n en el bloque;
     *  - 'partida': la original de cada actividad dentro de su partida, con un renglón de subtítulo por partida.
     * @return array{grupos: array, subtitulos: bool, titulo: string, color: array, seq: string}
     */
    public static function tareasDeBloque(VplanBloque $b, Proyecto $proyecto): array
    {
        $cfg = $b->config();
        $modo = ($cfg['seq'] ?? 'continuo') === 'partida' ? 'partida' : 'continuo';
        $grupos = [];
        $vistas = [];
        $n = 0;
        foreach ((array)($cfg['fuentes'] ?? []) as $f) {
            $partida = Partida::find((int)($f['partida'] ?? 0));
            if (!$partida || (int)$partida->proyecto_id !== (int)$proyecto->id) continue;
            $todas = self::actividadesDePartida($partida);
            $sel = isset($f['actividades']) && is_array($f['actividades']) && $f['actividades'] ? array_flip(array_map('intval', $f['actividades'])) : null;
            $tareas = [];
            foreach ($todas as $i => $a) {
                if (($sel && !isset($sel[(int)$a->id])) || isset($vistas[(int)$a->id])) continue;
                $vistas[(int)$a->id] = true;
                $tareas[] = ['actividad' => $a, 'seq_partida' => $i + 1];
            }
            if (!$tareas) continue;
            foreach ($tareas as &$t) {
                $n++;
                $t['seq'] = $modo === 'partida' ? $t['seq_partida'] : $n;
                $t['estado'] = self::estado($t['actividad']);
            }
            unset($t);
            $grupos[] = ['partida' => $partida, 'tareas' => $tareas];
        }
        $titulo = trim((string)($cfg['titulo'] ?? ''));
        if ($titulo === '') $titulo = implode(' / ', array_map(fn($g) => $g['partida']->nombre, $grupos));
        $color = trim((string)($cfg['color'] ?? ''));
        return [
            'grupos' => $grupos, 'subtitulos' => $modo === 'partida' && count($grupos) > 1, 'seq' => $modo,
            'titulo' => mb_strtoupper($titulo),
            'color' => self::colorTabla($color !== '' ? $color : ($grupos ? $grupos[0]['partida']->color_bg : null)),
        ];
    }

    /**
     * Restricciones activas de esas actividades, numeradas 1..n en el orden recibido.
     * @return array{lista: array, porAct: array}
     */
    public static function restriccionesDe(array $idsActividad): array
    {
        $ids = array_values(array_unique(array_map('intval', $idsActividad)));
        if (!$ids) return ['lista' => [], 'porAct' => []];
        $filas = [];
        foreach (Restriccion::consultarSQL("SELECT r.* FROM restricciones r WHERE r.actividad_id IN (" . implode(',', $ids) . ") AND r.estado = 'activa' ORDER BY r.id") as $r) {
            $filas[(int)$r->actividad_id][] = $r;
        }
        $lista = [];
        $porAct = [];
        $n = 0;
        foreach ($ids as $id) {
            foreach ($filas[$id] ?? [] as $r) {
                $n++;
                $porAct[$id][] = $n;
                $lista[] = ['n' => $n, 'descripcion' => trim((string)$r->descripcion) ?: (trim((string)$r->codigo) ?: 'Restricción')];
            }
        }
        return ['lista' => $lista, 'porAct' => $porAct];
    }

    /** Bloques de tareas ordenados como se leen en la hoja (arriba→abajo, izquierda→derecha) */
    private static function tareasEnOrden(array $bloques): array
    {
        $bt = array_values(array_filter($bloques, fn($b) => $b->tipo === 'tareas' && empty($b->config()['oculto'])));   // un bloque oculto no cuenta (ni numera restricciones)
        usort($bt, fn($a, $b) => [round((float)$a->y, 1), (float)$a->x] <=> [round((float)$b->y, 1), (float)$b->x]);
        return $bt;
    }

    /**
     * Si las actividades de la lámina tienen restricciones activas, agrega por defecto la tabla de
     * restricciones a la lámina (una sola vez: si luego se quita a mano, no se vuelve a agregar).
     */
    public static function asegurarRestricciones(VplanLamina $lamina, Proyecto $proyecto): void
    {
        if ((int)$lamina->restr_auto === 1 || !$lamina->id) return;
        $ids = [];
        foreach (self::tareasEnOrden($lamina->bloques()) as $b) {
            foreach (self::tareasDeBloque($b, $proyecto)['grupos'] as $g) foreach ($g['tareas'] as $t) $ids[] = (int)$t['actividad']->id;
        }
        if (!self::restriccionesDe($ids)['lista']) return;   // sin restricciones todavía: se vuelve a evaluar en la siguiente visita

        // Si la lámina ya tiene una tabla de restricciones (p. ej. con renglones manuales), no se duplica;
        // si no, se crea una propia de la lámina para que los renglones manuales no se compartan con otras láminas.
        foreach ($lamina->tablas() as $t) {
            if ($t->tipo === 'restricciones') {
                $lamina->restr_auto = 1;
                $lamina->guardar();
                return;
            }
        }
        $tabla = new \Model\VplanTabla(['proyecto_id' => $proyecto->id, 'titulo' => 'RESTRICCIONES', 'nombre_interno' => 'Restricciones · ' . $lamina->titulo, 'tipo' => 'restricciones']);
        $tabla->id = $tabla->guardar()['id'];
        $lamina->agregarTabla((int)$tabla->id);
        $lamina->restr_auto = 1;
        $lamina->guardar();
    }

    public static function textoFecha(): string
    {
        $meses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
        return $meses[(int)date('n')] . ' | ' . date('d') . ' | ' . date('Y');
    }

    /** Filas [etiqueta, valor] de un cajetín (0 = el estándar) con sus variables resueltas para esta lámina, y su forma */
    public static function cajetinDeBloque(VplanBloque $b, VplanLamina $lamina, Proyecto $proyecto): array
    {
        $id = (int)($b->config()['cajetin_id'] ?? 0);
        $c = null;
        foreach (\Model\Cajetin::paraEditor((int)$proyecto->id) as $x) if ((int)$x['id'] === $id) { $c = $x; break; }
        $c ??= \Model\Cajetin::paraEditor((int)$proyecto->id)[0];
        $ctx = new EstampaContexto($proyecto, $lamina);   // todas las variables del catálogo ({proyecto}, {titulo}, {fecha}, {avance_real}…)
        $val = fn($t) => Estampas::texto((string)$t, $ctx);
        return ['banda' => $c['orientacion'] === 'banda', 'filas' => array_map(fn($f) => [$val($f[0]), $val($f[1])], $c['filas'])];
    }

    /** Opciones de contexto de un bloque de estampa: fecha de corte fija (o la de hoy) y actividad de las etiquetas «de la actividad del bloque» */
    public static function opcionesEstampa(array $cfg): array
    {
        return ['corte' => ($cfg['fecha_modo'] ?? '') === 'fija' ? (string)($cfg['fecha'] ?? '') : '', 'actividad' => (int)($cfg['actividad_id'] ?? 0)];
    }

    /** Textos que se calculan solos si el bloque no trae uno propio */
    public static function textoDeBloque(VplanBloque $b, VplanLamina $lamina, Proyecto $proyecto): string
    {
        $c = $b->config();
        $propio = trim((string)($c['texto'] ?? ''));
        return match ($b->tipo) {
            'titulo' => $propio !== '' ? $propio : ($proyecto->codigo ?: $proyecto->nombre) . "\n" . mb_strtoupper((string)$lamina->titulo),
            'supervisor' => $propio !== '' ? $propio : ($lamina->supervisor ? 'SUPERVISOR: ' . $lamina->supervisor : ''),
            'fecha' => $propio !== '' ? $propio : 'UPDATED: ' . self::textoFecha(),
            default => $propio,
        };
    }

    /**
     * Todo lo que necesitan las vistas, el editor de marcas y el PDF.
     * $bloques permite calcular con un diseño aún no guardado (se usa al crear el diseño por defecto).
     */
    /** Encabezado de la columna de recovery n: RECOVERY, RECOVERY 02, RECOVERY 03… */
    public static function etiquetaRecovery(int $n): string
    {
        return $n <= 1 ? 'RECOVERY' : 'RECOVERY ' . str_pad((string)$n, 2, '0', STR_PAD_LEFT);
    }

    public static function datos(VplanLamina $lamina, Proyecto $proyecto, ?array $bloques = null): array
    {
        $bloques ??= $lamina->bloques();
        try { $lamina->sincronizarImagenes($bloques); } catch (\Throwable $e) { error_log('vPlan imágenes vinculadas: ' . $e->getMessage()); }
        $partida = $lamina->partida_id ? Partida::find($lamina->partida_id) : null;
        if ($partida && (int)$partida->proyecto_id !== (int)$proyecto->id) $partida = null;
        $rec = $lamina->recovery();
        $textos = $lamina->textosColumnas();

        // Qué muestra cada bloque de tareas y, con todo junto, las restricciones numeradas de la lámina
        $bt = self::tareasEnOrden($bloques);
        $base = [];
        $orden = [];
        foreach ($bt as $b) {
            $base[spl_object_id($b)] = self::tareasDeBloque($b, $proyecto);
            foreach ($base[spl_object_id($b)]['grupos'] as $g) foreach ($g['tareas'] as $t) $orden[] = (int)$t['actividad']->id;
        }
        ['lista' => $listaRestr, 'porAct' => $numPorAct] = self::restriccionesDe($orden);

        $nRec = max(0, min(VplanLamina::MAX_RECOVERY, (int)$lamina->recovery_n));
        $hash = [];
        $tareas = [];       // todas las actividades de la lámina, una vez (para el editor de marcas)
        $porBloque = [];
        foreach ($bt as $b) {
            $td = $base[spl_object_id($b)];
            foreach ($td['grupos'] as &$g) {
                foreach ($g['tareas'] as &$t) {
                    $a = $t['actividad'];
                    $r = $rec[(int)$a->id] ?? [];
                    $t += [
                        'rec' => array_map(fn($n) => ['inicio' => $r[$n]['inicio'] ?? null, 'fin' => $r[$n]['fin'] ?? null], $nRec ? range(1, $nRec) : []),
                        'textos' => $textos[(int)$a->id] ?? [],
                        'restricciones' => $numPorAct[(int)$a->id] ?? [],
                    ];
                    if (!isset($tareas[(int)$a->id])) {
                        $tareas[(int)$a->id] = $t;
                        $hash[] = $a->id . ':' . $a->progreso . ':' . $a->status;
                    }
                }
                unset($t);
            }
            unset($g);
            $porBloque[spl_object_id($b)] = $td;
        }

        $tablas = [];
        $tablasPorId = [];
        foreach ($lamina->tablas() as $t) {
            $filas = $t->filas();
            if ($t->tipo === 'restricciones') {
                $n = count($listaRestr);
                $filas = array_merge($listaRestr, array_map(function ($f, $i) use (&$n) {
                    return ['n' => ++$n, 'descripcion' => $f['descripcion'] ?? '', '_i' => $i];
                }, $filas, array_keys($filas)));
            } else {
                foreach ($filas as $i => &$f) { $f['n'] = $i + 1; $f['_i'] = $i; }
                unset($f);
            }
            $tablas[] = $tablasPorId[(int)$t->id] = ['tabla' => $t, 'campos' => $t->campos(), 'filas' => $filas, 'base' => $t->tipo === 'restricciones' ? count($listaRestr) : 0];
        }

        $primero = $bt ? $porBloque[spl_object_id($bt[0])] : null;
        return [
            'partida' => $partida, 'bloques' => $bloques, 'porBloque' => $porBloque, 'tareas' => array_values($tareas),
            'nRec' => $nRec, 'restricciones' => $listaRestr,
            'tablas' => $tablas, 'tablasPorId' => $tablasPorId, 'hash' => md5(implode('|', $hash)),
            'colorTabla' => $primero ? $primero['color'] : self::colorTabla($partida ? $partida->color_bg : null),
        ];
    }
}
